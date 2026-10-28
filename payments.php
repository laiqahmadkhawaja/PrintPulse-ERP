<?php
require_once __DIR__ . '/config/init.php';
require_once __DIR__ . '/includes/accounting_helper.php';

$bid = (int)$current_business['id'];
$pdo = getDB();
$error = '';
$msg = '';

$pre_cust_id = (int)($_GET['customer_id'] ?? 0);
$pre_sup_id = (int)($_GET['supplier_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = "Security token mismatch.";
    } else {
        $payment_type = $_POST['payment_type'] ?? 'customer_receipt';
        $party_id = (int)($_POST['party_id'] ?? 0);
        $amount = (float)($_POST['amount'] ?? 0);
        $pay_date = $_POST['payment_date'] ?? date('Y-m-d');
        $method = $_POST['payment_method'] ?? 'cash';
        $account_code = ($method === 'bank_transfer') ? '1020' : '1010';
        $notes = trim($_POST['notes'] ?? '');

        if ($party_id <= 0 || $amount <= 0) {
            $error = $is_rtl ? 'کھاتہ اور درست رقم درج کریں۔' : 'Please select party and valid amount.';
        } else {
            $lastNo = $pdo->query("SELECT MAX(id) FROM payments WHERE business_id = {$bid}")->fetchColumn() ?: 100;
            $voucher_number = ($payment_type === 'customer_receipt' ? 'CRV-' : 'SPV-') . ($lastNo + 1);

            $pdo->beginTransaction();
            try {
                $accId = AccountingEngine::getAccountIdByCode($bid, $account_code);

                // Insert payment record
                $stmt = $pdo->prepare("INSERT INTO payments 
                    (business_id, payment_type, voucher_number, party_type, party_id, account_id, payment_date, amount, payment_method, notes) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $partyType = ($payment_type === 'customer_receipt') ? 'customer' : 'supplier';
                $stmt->execute([
                    $bid,
                    $payment_type,
                    $voucher_number,
                    $partyType,
                    $party_id,
                    $accId,
                    $pay_date,
                    $amount,
                    $method,
                    $notes
                ]);
                $payment_id = $pdo->lastInsertId();

                if ($payment_type === 'customer_receipt') {
                    // Update Customer Balance
                    $pdo->prepare("UPDATE customers SET current_balance = current_balance - ? WHERE id = ? AND business_id = ?")
                        ->execute([$amount, $party_id, $bid]);

                    // Double-Entry:
                    // Debit: Cash (1010) or Bank (1020)
                    // Credit: Accounts Receivable (1030)
                    $arAccId = AccountingEngine::getAccountIdByCode($bid, '1030');
                    $journalLines = [
                        ['account_id' => $accId, 'debit' => $amount, 'credit' => 0.00, 'narration' => "Receipt {$voucher_number}"],
                        ['account_id' => $arAccId, 'debit' => 0.00, 'credit' => $amount, 'narration' => "Customer Balance Paid {$voucher_number}"]
                    ];

                    AccountingEngine::postJournalEntry(
                        $bid,
                        $voucher_number,
                        $pay_date,
                        'receipt',
                        $payment_id,
                        "Customer Receipt from #{$party_id}",
                        $journalLines,
                        $current_user['id']
                    );
                } else {
                    // Supplier Payment
                    $pdo->prepare("UPDATE suppliers SET current_balance = current_balance - ? WHERE id = ? AND business_id = ?")
                        ->execute([$amount, $party_id, $bid]);

                    // Double-Entry:
                    // Debit: Accounts Payable (2010)
                    // Credit: Cash (1010) or Bank (1020)
                    $apAccId = AccountingEngine::getAccountIdByCode($bid, '2010');
                    $journalLines = [
                        ['account_id' => $apAccId, 'debit' => $amount, 'credit' => 0.00, 'narration' => "Payment to Supplier {$voucher_number}"],
                        ['account_id' => $accId, 'debit' => 0.00, 'credit' => $amount, 'narration' => "Cash/Bank paid {$voucher_number}"]
                    ];

                    AccountingEngine::postJournalEntry(
                        $bid,
                        $voucher_number,
                        $pay_date,
                        'payment',
                        $payment_id,
                        "Payment to Supplier #{$party_id}",
                        $journalLines,
                        $current_user['id']
                    );
                }

                $pdo->commit();
                $msg = $is_rtl ? "واؤچر {$voucher_number} کامیابی سے محفوظ ہو گیا اور اکاؤنٹنگ انٹری درج ہو گئی۔" : "Voucher {$voucher_number} posted successfully with double-entry.";

            } catch (Exception $e) {
                $pdo->rollBack();
                $error = "Payment failed: " . $e->getMessage();
            }
        }
    }
}

// Fetch lists for forms & recent payments
$customers = $pdo->query("SELECT id, name, current_balance FROM customers WHERE business_id = {$bid} ORDER BY name ASC")->fetchAll();
$suppliers = $pdo->query("SELECT id, name, current_balance FROM suppliers WHERE business_id = {$bid} ORDER BY name ASC")->fetchAll();

$stmtRecent = $pdo->prepare("SELECT p.*, 
    CASE WHEN p.party_type = 'customer' THEN c.name ELSE s.name END AS party_name 
    FROM payments p 
    LEFT JOIN customers c ON c.id = p.party_id AND p.party_type = 'customer' 
    LEFT JOIN suppliers s ON s.id = p.party_id AND p.party_type = 'supplier' 
    WHERE p.business_id = ? ORDER BY p.id DESC LIMIT 15");
$stmtRecent->execute([$bid]);
$recentPayments = $stmtRecent->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
  <div>
    <h1 class="page-title">
      💳 <?php echo h(__('nav_payments')); ?>
    </h1>
    <p style="color:var(--text-muted); font-size:0.9rem;">
      <?php echo $is_rtl ? 'گاہکوں سے رقوم کی وصولی (CRV) اور سپلائرز کو ادائیگیاں (SPV)' : 'Customer receipts, supplier payments, and double-entry reconciliation'; ?>
    </p>
  </div>
</div>

<?php if (!empty($error)): ?>
  <div class="alert alert-danger"><?php echo h($error); ?></div>
<?php endif; ?>
<?php if (!empty($msg)): ?>
  <div class="alert alert-success"><?php echo h($msg); ?></div>
<?php endif; ?>

<div class="grid grid-cols-3">
  <!-- Payment Form -->
  <div class="card">
    <div class="card-header">
      <span>➕ <?php echo $is_rtl ? 'نیا ادائیگی / وصولی واؤچر' : 'New Payment / Receipt Voucher'; ?></span>
    </div>
    <div class="card-body">
      <form method="POST" action="payments.php">
        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">

        <div class="form-group">
          <label class="form-label"><?php echo $is_rtl ? 'واؤچر کی قسم' : 'Voucher Type'; ?></label>
          <select name="payment_type" id="paymentTypeSelect" class="form-control" onchange="togglePartyDropdown(this.value)">
            <option value="customer_receipt" <?php echo ($pre_sup_id > 0) ? '' : 'selected'; ?>>
              📥 <?php echo $is_rtl ? 'گاہک سے وصولی (Customer Receipt - CRV)' : 'Customer Receipt (CRV)'; ?>
            </option>
            <option value="supplier_payment" <?php echo ($pre_sup_id > 0) ? 'selected' : ''; ?>>
              📤 <?php echo $is_rtl ? 'سپلائر کو ادائیگی (Supplier Payment - SPV)' : 'Supplier Payment (SPV)'; ?>
            </option>
          </select>
        </div>

        <!-- Customer Select -->
        <div class="form-group" id="customerGroup">
          <label class="form-label"><?php echo h(__('nav_customers')); ?> *</label>
          <select name="party_id" id="customerSelect" class="form-control">
            <option value="">-- <?php echo $is_rtl ? 'گاہک منتخب کریں' : 'Select Customer'; ?> --</option>
            <?php foreach ($customers as $c): ?>
              <option value="<?php echo $c['id']; ?>" <?php echo ($c['id'] == $pre_cust_id) ? 'selected' : ''; ?>>
                <?php echo h($c['name']); ?> (Bal: <?php echo format_currency($c['current_balance']); ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <!-- Supplier Select -->
        <div class="form-group" id="supplierGroup" style="display:none;">
          <label class="form-label"><?php echo h(__('nav_suppliers')); ?> *</label>
          <select name="party_id_sup" id="supplierSelect" class="form-control">
            <option value="">-- <?php echo $is_rtl ? 'سپلائر منتخب کریں' : 'Select Supplier'; ?> --</option>
            <?php foreach ($suppliers as $s): ?>
              <option value="<?php echo $s['id']; ?>" <?php echo ($s['id'] == $pre_sup_id) ? 'selected' : ''; ?>>
                <?php echo h($s['name']); ?> (Payable: <?php echo format_currency($s['current_balance']); ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-group">
          <label class="form-label"><?php echo h(__('amount')); ?> (Rs) *</label>
          <input type="number" step="0.01" name="amount" class="form-control" required placeholder="0.00">
        </div>

        <div class="form-group">
          <label class="form-label"><?php echo h(__('date')); ?> *</label>
          <input type="date" name="payment_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
        </div>

        <div class="form-group">
          <label class="form-label"><?php echo $is_rtl ? 'طریقہ ادائیگی' : 'Payment Mode'; ?></label>
          <select name="payment_method" class="form-control">
            <option value="cash">💵 Cash in Hand (نقد)</option>
            <option value="bank_transfer">🏦 Bank Transfer (بینک)</option>
            <option value="cheque">📝 Cheque (چیک)</option>
          </select>
        </div>

        <div class="form-group">
          <label class="form-label"><?php echo h(__('notes')); ?></label>
          <textarea name="notes" class="form-control" rows="2" placeholder="Reference, cheque # or remarks"></textarea>
        </div>

        <button type="submit" class="btn btn-primary" style="width:100%; padding:10px; font-weight:700;">
          💾 <?php echo $is_rtl ? 'واؤچر پوسٹ کریں' : 'Post Voucher'; ?>
        </button>
      </form>
    </div>
  </div>

  <!-- Recent Payments Table -->
  <div class="card" style="grid-column: span 2;">
    <div class="card-header">
      <span>📜 <?php echo $is_rtl ? 'حالیہ ادائیگیاں و وصولیاں' : 'Recent Payment Vouchers & Ledger Updates'; ?></span>
    </div>
    <div class="table-responsive">
      <table class="table">
        <thead>
          <tr>
            <th>Voucher #</th>
            <th>Date</th>
            <th>Type</th>
            <th>Party</th>
            <th>Mode</th>
            <th class="text-right">Amount</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($recentPayments)): ?>
            <tr><td colspan="6" class="text-center text-muted" style="padding:20px;"><?php echo $is_rtl ? 'کوئی واؤچر موجود نہیں ہے' : 'No payment records yet.'; ?></td></tr>
          <?php else: ?>
            <?php foreach ($recentPayments as $rp): ?>
              <tr>
                <td><strong><?php echo h($rp['voucher_number']); ?></strong></td>
                <td><?php echo format_date($rp['payment_date']); ?></td>
                <td>
                  <span class="badge <?php echo ($rp['payment_type']==='customer_receipt') ? 'badge-success' : 'badge-warning'; ?>">
                    <?php echo ($rp['payment_type']==='customer_receipt') ? 'Receipt (CRV)' : 'Payment (SPV)'; ?>
                  </span>
                </td>
                <td><strong><?php echo h($rp['party_name'] ?: 'N/A'); ?></strong></td>
                <td><?php echo strtoupper($rp['payment_method']); ?></td>
                <td class="text-right"><strong><?php echo format_currency($rp['amount']); ?></strong></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script>
function togglePartyDropdown(type) {
  const custGroup = document.getElementById('customerGroup');
  const supGroup = document.getElementById('supplierGroup');
  const custSelect = document.getElementById('customerSelect');
  const supSelect = document.getElementById('supplierSelect');

  if (type === 'customer_receipt') {
    custGroup.style.display = 'block';
    supGroup.style.display = 'none';
    custSelect.name = 'party_id';
    supSelect.name = 'party_id_sup';
  } else {
    custGroup.style.display = 'none';
    supGroup.style.display = 'block';
    custSelect.name = 'party_id_cust';
    supSelect.name = 'party_id';
  }
}

<?php if ($pre_sup_id > 0): ?>
document.addEventListener('DOMContentLoaded', function() {
  togglePartyDropdown('supplier_payment');
});
<?php endif; ?>
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
