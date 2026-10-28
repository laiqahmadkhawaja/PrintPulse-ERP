<?php
require_once __DIR__ . '/config/init.php';
require_once __DIR__ . '/includes/accounting_helper.php';

$bid = (int)$current_business['id'];
$pdo = getDB();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = "Security token mismatch.";
    } else {
        $customer_name = trim($_POST['customer_name'] ?? '');
        $customer_phone = trim($_POST['customer_phone'] ?? '');
        $invoice_date = $_POST['invoice_date'] ?? date('Y-m-d');
        $cartridge_model = trim($_POST['cartridge_model'] ?? 'HP Toner');
        $paid_amount = (float)($_POST['paid_amount'] ?? 0.0);
        $notes = trim($_POST['notes'] ?? ($current_business['invoice_notes'] ?? ''));
        $raw_items = $_POST['items'] ?? [];

        if (empty($customer_name)) {
            $error = $is_rtl ? 'کسٹمر کا نام درج کریں۔' : 'Customer name is required.';
        } elseif (empty($raw_items)) {
            $error = $is_rtl ? 'کم از کم ایک لائن شامل کریں۔' : 'Please add at least one line.';
        } else {
            // Find or create customer
            $custStmt = $pdo->prepare("SELECT id FROM customers WHERE business_id = ? AND name = ? LIMIT 1");
            $custStmt->execute([$bid, $customer_name]);
            $customerId = $custStmt->fetchColumn();
            if (!$customerId) {
                $insCust = $pdo->prepare("INSERT INTO customers (business_id, name, phone) VALUES (?, ?, ?)");
                $insCust->execute([$bid, $customer_name, $customer_phone]);
                $customerId = $pdo->lastInsertId();
            }

            // Generate invoice number
            $lastNo = $pdo->query("SELECT MAX(id) FROM invoices WHERE business_id = {$bid}")->fetchColumn() ?: 1000;
            $invoice_number = ($current_settings['refill_prefix'] ?? 'REF-') . ($lastNo + 1);

            $subtotal = 0.0;
            $validItems = [];
            foreach ($raw_items as $item) {
                $desc = trim($item['description'] ?? '');
                $qty = (float)($item['quantity'] ?? 1);
                $rate = (float)($item['unit_price'] ?? 0);
                $line_type = $item['line_type'] ?? 'refill';
                $line_total = $qty * $rate;

                if (!empty($desc) && $line_total >= 0) {
                    $subtotal += $line_total;
                    $validItems[] = [
                        'description' => $desc,
                        'line_type' => $line_type,
                        'quantity' => $qty,
                        'unit_price' => $rate,
                        'total_amount' => $line_total
                    ];
                }
            }

            $discount = (float)($_POST['discount'] ?? 0);
            $total_amount = max(0, $subtotal - $discount);
            $balance_amount = max(0, $total_amount - $paid_amount);

            $pdo->beginTransaction();
            try {
                $stmtInv = $pdo->prepare("INSERT INTO invoices 
                    (business_id, invoice_type, invoice_number, customer_id, customer_name, customer_phone, invoice_date, subtotal, discount, total_amount, paid_amount, balance_amount, payment_method, notes, created_by) 
                    VALUES (?, 'refill', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'cash', ?, ?)");
                $stmtInv->execute([
                    $bid,
                    $invoice_number,
                    $customerId,
                    $customer_name,
                    $customer_phone,
                    $invoice_date,
                    $subtotal,
                    $discount,
                    $total_amount,
                    $paid_amount,
                    $balance_amount,
                    $notes,
                    $current_user['id']
                ]);
                $invoice_id = $pdo->lastInsertId();

                $stmtItem = $pdo->prepare("INSERT INTO invoice_items 
                    (invoice_id, line_number, line_type, description, quantity, unit_price, total_amount) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)");

                $descCheckStmt = $pdo->prepare("SELECT id FROM service_descriptions WHERE business_id = ? AND description = ?");
                $descInsStmt = $pdo->prepare("INSERT INTO service_descriptions (business_id, description, default_rate, line_type, usage_count) VALUES (?, ?, ?, ?, 1)");
                $descUpdStmt = $pdo->prepare("UPDATE service_descriptions SET usage_count = usage_count + 1, last_used_at = NOW() WHERE id = ?");

                $lno = 1;
                $refillRevenue = 0.0;
                $partsRevenue = 0.0;

                foreach ($validItems as $v) {
                    $stmtItem->execute([
                        $invoice_id,
                        $lno++,
                        $v['line_type'],
                        $v['description'],
                        $v['quantity'],
                        $v['unit_price'],
                        $v['total_amount']
                    ]);

                    if (in_array($v['line_type'], ['part', 'product'])) {
                        $partsRevenue += $v['total_amount'];
                    } else {
                        $refillRevenue += $v['total_amount'];
                    }

                    // Autocomplete catalog update
                    $descCheckStmt->execute([$bid, $v['description']]);
                    $existingDescId = $descCheckStmt->fetchColumn();
                    if ($existingDescId) {
                        $descUpdStmt->execute([$existingDescId]);
                    } else {
                        $descInsStmt->execute([$bid, $v['description'], $v['unit_price'], $v['line_type']]);
                    }
                }

                // Customer balance update
                if ($balance_amount > 0) {
                    $pdo->prepare("UPDATE customers SET current_balance = current_balance + ? WHERE id = ? AND business_id = ?")
                        ->execute([$balance_amount, $customerId, $bid]);
                }

                // Double-Entry Journal Posting:
                $cashAccId = AccountingEngine::getAccountIdByCode($bid, '1010');
                $arAccId = AccountingEngine::getAccountIdByCode($bid, '1030');
                $refillRevAccId = AccountingEngine::getAccountIdByCode($bid, '4030');
                $partsRevAccId = AccountingEngine::getAccountIdByCode($bid, '4010');

                $journalLines = [];
                if ($paid_amount > 0) {
                    $journalLines[] = [
                        'account_id' => $cashAccId,
                        'debit' => $paid_amount,
                        'credit' => 0.00,
                        'narration' => "Deposit for {$invoice_number}"
                    ];
                }
                if ($balance_amount > 0) {
                    $journalLines[] = [
                        'account_id' => $arAccId,
                        'debit' => $balance_amount,
                        'credit' => 0.00,
                        'narration' => "Receivable for {$invoice_number}"
                    ];
                }
                if ($refillRevenue > 0) {
                    $journalLines[] = [
                        'account_id' => $refillRevAccId,
                        'debit' => 0.00,
                        'credit' => $refillRevenue,
                        'narration' => "Cartridge Refill Revenue {$invoice_number}"
                    ];
                }
                if ($partsRevenue > 0) {
                    $journalLines[] = [
                        'account_id' => $partsRevAccId,
                        'debit' => 0.00,
                        'credit' => $partsRevenue,
                        'narration' => "Parts Revenue {$invoice_number}"
                    ];
                }

                AccountingEngine::postJournalEntry(
                    $bid,
                    "JV-{$invoice_number}",
                    $invoice_date,
                    'refill',
                    $invoice_id,
                    "Refill Invoice {$invoice_number} for {$customer_name}",
                    $journalLines,
                    $current_user['id']
                );

                $pdo->commit();
                log_audit('CREATE_REFILL_INVOICE', 'invoice', $invoice_id, "Created {$invoice_number} for {$customer_name}");

                // Direct redirect to print preview!
                header("Location: print_invoice.php?id={$invoice_id}");
                exit;

            } catch (Exception $e) {
                $pdo->rollBack();
                $error = "Failed to save refill invoice: " . $e->getMessage();
            }
        }
    }
}

$stmtCust = $pdo->prepare("SELECT id, name, phone FROM customers WHERE business_id = ? ORDER BY name ASC");
$stmtCust->execute([$bid]);
$savedCustomers = $stmtCust->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
  <div>
    <h1 class="page-title">
      🧪 <?php echo $is_rtl ? 'نیا کارٹریج / ٹونر ریفلنگ بل' : 'Create Cartridge Refill Invoice'; ?>
    </h1>
    <p style="color:var(--text-muted); font-size:0.9rem;">
      <?php echo $is_rtl ? 'ٹونر ریفل، ڈرم تبدیلی، بلیڈ، چپ اور پارٹس کا بل' : 'Toner refilling, drum, wiper blade, and chip replacement billing'; ?>
    </p>
  </div>
  <a href="refill_invoices.php" class="btn btn-outline">&larr; <?php echo h(__('cancel')); ?></a>
</div>

<?php if (!empty($error)): ?>
  <div class="alert alert-danger"><?php echo h($error); ?></div>
<?php endif; ?>

<form method="POST" action="refill_invoice_create.php">
  <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">

  <div class="card">
    <div class="card-header">
      <span>👤 <?php echo $is_rtl ? 'کسٹمر و کارٹریج کی معلومات' : 'Customer & Cartridge Information'; ?></span>
    </div>
    <div class="card-body">
      <div class="grid grid-cols-3">
        <div class="form-group">
          <label class="form-label"><?php echo h(__('customer_name')); ?> *</label>
          <input type="text" name="customer_name" id="custNameInput" list="customerDatalist" class="form-control" required placeholder="<?php echo $is_rtl ? 'کسٹمر یا کمپنی کا نام درج کریں' : 'Customer or Client Name'; ?>" onchange="autoFillCustPhone(this)">
          <datalist id="customerDatalist">
            <?php foreach ($savedCustomers as $sc): ?>
              <option value="<?php echo h($sc['name']); ?>" data-phone="<?php echo h($sc['phone']); ?>"><?php echo h($sc['phone'] ? $sc['name'] . ' (' . $sc['phone'] . ')' : $sc['name']); ?></option>
            <?php endforeach; ?>
          </datalist>
        </div>
        <div class="form-group">
          <label class="form-label"><?php echo h(__('phone')); ?></label>
          <input type="text" name="customer_phone" id="custPhoneInput" class="form-control" placeholder="0300-1234567">
        </div>
        <div class="form-group">
          <label class="form-label"><?php echo h(__('date')); ?> *</label>
          <input type="date" name="invoice_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
        </div>
      </div>
    </div>
  </div>

  <!-- Items Table (Starts Clean for new entry) -->
  <div class="card card-table-overflow">
    <div class="card-header">
      <span>📋 <?php echo $is_rtl ? 'ریفلنگ تفصیلات، ڈرم و پارٹس' : 'Refill Details, Drum & Parts'; ?></span>
      <div style="display:flex; gap:8px;">
        <a href="service_descriptions.php" target="_blank" class="btn btn-outline btn-sm" title="Manage catalog default rates">⚙️ <?php echo $is_rtl ? 'ریٹس کی فہرست' : 'Rates Catalog'; ?></a>
        <button type="button" id="btnAddRow" class="btn btn-primary btn-sm">➕ <?php echo $is_rtl ? 'مزید لائن شامل کریں' : 'Add Item Line'; ?></button>
      </div>
    </div>

    <div class="table-responsive table-overflow-visible">
      <table class="table" id="invoiceTable">
        <thead>
          <tr>
            <th style="width:45px;" class="text-center">#</th>
            <th><?php echo h(__('description')); ?> (Auto-suggests from database) *</th>
            <th style="width:140px;"><?php echo $is_rtl ? 'قسم' : 'Type'; ?></th>
            <th style="width:100px;" class="text-center"><?php echo h(__('qty')); ?></th>
            <th style="width:130px;" class="text-right"><?php echo h(__('rate')); ?></th>
            <th style="width:140px;" class="text-right"><?php echo h(__('amount')); ?></th>
            <th style="width:60px;" class="text-center"><?php echo h(__('action')); ?></th>
          </tr>
        </thead>
        <tbody id="invoiceItemsTbody">
          <!-- Row 1: Clean Initial Row -->
          <tr>
            <td class="text-center row-num">1</td>
            <td>
              <div class="autocomplete-wrapper">
                <input type="text" name="items[1][description]" class="form-control service-autocomplete-input" value="" placeholder="<?php echo $is_rtl ? 'ٹونر ریفل، ڈرم، بلیڈ یا پارٹ درج کریں...' : 'Type cartridge refill, drum, blade, or service...'; ?>" required autocomplete="off">
              </div>
            </td>
            <td>
              <select name="items[1][line_type]" class="form-control item-line-type">
                <option value="refill" selected><?php echo $is_rtl ? 'ریفلنگ' : 'Refill'; ?></option>
                <option value="part"><?php echo $is_rtl ? 'پرزہ جات' : 'Part'; ?></option>
                <option value="service"><?php echo $is_rtl ? 'سروس' : 'Service'; ?></option>
              </select>
            </td>
            <td><input type="number" step="0.01" name="items[1][quantity]" class="form-control item-qty text-center" value="1"></td>
            <td><input type="number" step="0.01" name="items[1][unit_price]" class="form-control item-rate text-right" value="0.00"></td>
            <td><input type="number" step="0.01" name="items[1][total_amount]" class="form-control item-amount text-right" value="0.00" readonly></td>
            <td class="text-center"><button type="button" class="btn btn-danger btn-sm btn-del-row">&times;</button></td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Totals Calculation Block -->
    <div class="card-footer">
      <div style="display:flex; justify-content:space-between; flex-wrap:wrap; gap:20px;">
        <div style="flex:1; min-width:300px;">
          <div class="form-group">
            <label class="form-label"><?php echo h(__('notes')); ?></label>
            <textarea name="notes" class="form-control" rows="3"><?php echo h($current_business['invoice_notes']); ?></textarea>
          </div>
        </div>

        <div style="width:320px;">
          <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
            <span style="font-weight:600;"><?php echo h(__('subtotal')); ?>:</span>
            <input type="text" id="invSubtotal" class="form-control" style="width:140px; text-align:right;" value="0.00" readonly>
          </div>
          <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
            <span><?php echo h(__('discount')); ?>:</span>
            <input type="number" step="0.01" name="discount" id="invDiscount" class="form-control" style="width:140px; text-align:right;" value="0.00">
          </div>
          <hr style="margin: 10px 0; border:0; border-top:1px solid #cbd5e1;">
          <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px; font-weight:800; font-size:1.1rem;">
            <span><?php echo h(__('total')); ?>:</span>
            <input type="text" id="invTotal" class="form-control" style="width:140px; text-align:right; font-weight:800;" value="0.00" readonly>
          </div>
          <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
            <span style="font-weight:700; color:var(--success);"><?php echo h(__('paid')); ?> (Deposit):</span>
            <input type="number" step="0.01" name="paid_amount" id="invPaid" class="form-control" style="width:140px; text-align:right; font-weight:700;" value="0.00">
          </div>
          <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px; font-weight:700; color:var(--danger);">
            <span><?php echo h(__('balance')); ?>:</span>
            <input type="text" id="invBalance" class="form-control" style="width:140px; text-align:right; font-weight:700;" value="0.00" readonly>
          </div>

          <button type="submit" class="btn btn-success" style="width:100%; margin-top:12px; font-size:1.05rem; padding:10px;">
            💾 <?php echo $is_rtl ? 'بل محفوظ کریں اور پرنٹ کریں' : 'Save & Print Invoice'; ?> &rarr;
          </button>
        </div>
      </div>
    </div>
  </div>
</form>

<script>
function autoFillCustPhone(input) {
  const val = input.value.trim();
  const options = document.querySelectorAll('#customerDatalist option');
  for (let opt of options) {
    if (opt.value.toLowerCase() === val.toLowerCase()) {
      const phone = opt.getAttribute('data-phone');
      if (phone) {
        document.getElementById('custPhoneInput').value = phone;
      }
      break;
    }
  }
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
