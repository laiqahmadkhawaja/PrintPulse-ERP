<?php
require_once __DIR__ . '/config/init.php';
require_once __DIR__ . '/includes/accounting_helper.php';
require_once __DIR__ . '/includes/inventory_helper.php';

$bid = (int)$current_business['id'];
$pdo = getDB();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = "Security token mismatch.";
    } else {
        $supplier_id = (int)($_POST['supplier_id'] ?? 0);
        $bill_no = trim($_POST['supplier_bill_no'] ?? '');
        $purchase_date = $_POST['purchase_date'] ?? date('Y-m-d');
        $paid_amount = (float)($_POST['paid_amount'] ?? 0);
        $notes = trim($_POST['notes'] ?? '');
        $raw_items = $_POST['items'] ?? [];

        if ($supplier_id <= 0 || empty($raw_items)) {
            $error = $is_rtl ? 'سپلائر اور کم از کم ایک پروڈکٹ منتخب کریں۔' : 'Supplier and items are required.';
        } else {
            $lastNo = $pdo->query("SELECT MAX(id) FROM purchases WHERE business_id = {$bid}")->fetchColumn() ?: 100;
            $purchase_number = "PUR-" . ($lastNo + 1);

            $total_amount = 0.0;
            $validItems = [];
            foreach ($raw_items as $item) {
                $pid = (int)($item['product_id'] ?? 0);
                $qty = (float)($item['quantity'] ?? 1);
                $price = (float)($item['purchase_price'] ?? 0);
                $line_total = $qty * $price;

                if ($pid > 0 && $qty > 0 && $line_total >= 0) {
                    $total_amount += $line_total;
                    $validItems[] = [
                        'product_id' => $pid,
                        'quantity' => $qty,
                        'purchase_price' => $price,
                        'total_amount' => $line_total
                    ];
                }
            }

            $balance_amount = max(0, $total_amount - $paid_amount);

            $pdo->beginTransaction();
            try {
                $stmtPur = $pdo->prepare("INSERT INTO purchases 
                    (business_id, purchase_number, supplier_id, supplier_bill_no, purchase_date, total_amount, paid_amount, balance_amount, notes) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmtPur->execute([
                    $bid,
                    $purchase_number,
                    $supplier_id,
                    $bill_no,
                    $purchase_date,
                    $total_amount,
                    $paid_amount,
                    $balance_amount,
                    $notes
                ]);
                $purchase_id = $pdo->lastInsertId();

                $stmtItem = $pdo->prepare("INSERT INTO purchase_items 
                    (purchase_id, product_id, quantity, purchase_price, total_amount) 
                    VALUES (?, ?, ?, ?, ?)");

                foreach ($validItems as $v) {
                    $stmtItem->execute([
                        $purchase_id,
                        $v['product_id'],
                        $v['quantity'],
                        $v['purchase_price'],
                        $v['total_amount']
                    ]);

                    // Add stock and recalculate weighted average purchase price
                    InventoryHelper::recordMovement(
                        $bid,
                        $v['product_id'],
                        'purchase',
                        $v['quantity'],
                        $v['purchase_price'],
                        $purchase_id,
                        "Purchase {$purchase_number}"
                    );
                }

                // Update supplier balance if balance unpaid
                if ($balance_amount > 0) {
                    $pdo->prepare("UPDATE suppliers SET current_balance = current_balance + ? WHERE id = ? AND business_id = ?")
                        ->execute([$balance_amount, $supplier_id, $bid]);
                }

                // Double-Entry Journal Posting:
                // Debit: Inventory / Stock on Hand (1040)
                // Credit:
                //   If Cash Paid > 0 => Cash Account (1010)
                //   If Balance > 0   => Accounts Payable (2010)
                $invAccId = AccountingEngine::getAccountIdByCode($bid, '1040');
                $cashAccId = AccountingEngine::getAccountIdByCode($bid, '1010');
                $apAccId = AccountingEngine::getAccountIdByCode($bid, '2010');

                $journalLines = [
                    [
                        'account_id' => $invAccId,
                        'debit' => $total_amount,
                        'credit' => 0.00,
                        'narration' => "Stock Intake from Purchase {$purchase_number}"
                    ]
                ];

                if ($paid_amount > 0) {
                    $journalLines[] = [
                        'account_id' => $cashAccId,
                        'debit' => 0.00,
                        'credit' => $paid_amount,
                        'narration' => "Payment for Purchase {$purchase_number}"
                    ];
                }
                if ($balance_amount > 0) {
                    $journalLines[] = [
                        'account_id' => $apAccId,
                        'debit' => 0.00,
                        'credit' => $balance_amount,
                        'narration' => "Payable for Purchase {$purchase_number}"
                    ];
                }

                AccountingEngine::postJournalEntry(
                    $bid,
                    "JV-{$purchase_number}",
                    $purchase_date,
                    'purchase',
                    $purchase_id,
                    "Purchase {$purchase_number} from supplier #{$supplier_id}",
                    $journalLines,
                    $current_user['id']
                );

                $pdo->commit();
                log_audit('CREATE_PURCHASE', 'purchase', $purchase_id, "Purchase {$purchase_number}");

                header("Location: purchases.php");
                exit;

            } catch (Exception $e) {
                $pdo->rollBack();
                $error = "Failed to record purchase: " . $e->getMessage();
            }
        }
    }
}

// Fetch suppliers & products
$suppliers = $pdo->query("SELECT id, name FROM suppliers WHERE business_id = {$bid} ORDER BY name ASC")->fetchAll();
$products = $pdo->query("SELECT id, name, purchase_price, current_stock FROM products WHERE business_id = {$bid} AND status = 'active' ORDER BY name ASC")->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
  <div>
    <h1 class="page-title">
      📦 <?php echo $is_rtl ? 'نیا خریداری بل (Purchase Bill) درج کریں' : 'New Supplier Purchase Bill'; ?>
    </h1>
    <p style="color:var(--text-muted); font-size:0.9rem;">
      <?php echo $is_rtl ? 'اسٹاک میں خودکار اضافہ، اوسط لاگت کا حساب اور کھاتہ داری' : 'Automatic inventory intake, weighted-average costing, and AP posting'; ?>
    </p>
  </div>
  <a href="purchases.php" class="btn btn-outline">&larr; <?php echo h(__('cancel')); ?></a>
</div>

<?php if (!empty($error)): ?>
  <div class="alert alert-danger"><?php echo h($error); ?></div>
<?php endif; ?>

<form method="POST" action="purchase_create.php">
  <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">

  <div class="card">
    <div class="card-header">
      <span>🏢 <?php echo $is_rtl ? 'سپلائر و بل کی معلومات' : 'Supplier & Bill Details'; ?></span>
    </div>
    <div class="card-body">
      <div class="grid grid-cols-3">
        <div class="form-group">
          <label class="form-label"><?php echo h(__('nav_suppliers')); ?> *</label>
          <select name="supplier_id" class="form-control" required>
            <option value="">-- <?php echo $is_rtl ? 'سپلائر منتخب کریں' : 'Select Supplier'; ?> --</option>
            <?php foreach ($suppliers as $s): ?>
              <option value="<?php echo $s['id']; ?>"><?php echo h($s['name']); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label"><?php echo $is_rtl ? 'سپلائر انوائس / بل نمبر' : 'Supplier Invoice #'; ?></label>
          <input type="text" name="supplier_bill_no" class="form-control" placeholder="e.g. NT-4921">
        </div>
        <div class="form-group">
          <label class="form-label"><?php echo h(__('date')); ?> *</label>
          <input type="date" name="purchase_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
        </div>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-header">
      <span>📋 <?php echo $is_rtl ? 'خریداری آئٹمز' : 'Purchase Items'; ?></span>
    </div>
    <div class="card-body">
      <div class="table-responsive">
        <table class="table">
          <thead>
            <tr>
              <th>Product</th>
              <th style="width:130px;" class="text-center">Quantity</th>
              <th style="width:160px;" class="text-right">Unit Purchase Rate</th>
              <th style="width:160px;" class="text-right">Total Amount</th>
            </tr>
          </thead>
          <tbody>
            <?php for ($i=1; $i<=3; $i++): ?>
              <tr>
                <td>
                  <select name="items[<?php echo $i; ?>][product_id]" class="form-control" onchange="populatePurRate(this)">
                    <option value="">-- Select Product --</option>
                    <?php foreach ($products as $pr): ?>
                      <option value="<?php echo $pr['id']; ?>" data-cost="<?php echo $pr['purchase_price']; ?>">
                        <?php echo h($pr['name']); ?> (Curr Stock: <?php echo (int)$pr['current_stock']; ?>)
                      </option>
                    <?php endforeach; ?>
                  </select>
                </td>
                <td><input type="number" step="0.01" name="items[<?php echo $i; ?>][quantity]" class="form-control text-center pur-qty" value="1" oninput="calcPurRow(this)"></td>
                <td><input type="number" step="0.01" name="items[<?php echo $i; ?>][purchase_price]" class="form-control text-right pur-price" value="0.00" oninput="calcPurRow(this)"></td>
                <td><input type="number" step="0.01" class="form-control text-right pur-total" value="0.00" readonly></td>
              </tr>
            <?php endfor; ?>
          </tbody>
        </table>
      </div>

      <div class="grid grid-cols-2" style="margin-top:20px;">
        <div class="form-group">
          <label class="form-label"><?php echo h(__('notes')); ?></label>
          <textarea name="notes" class="form-control" rows="3" placeholder="Remarks / Freight / Goods condition..."></textarea>
        </div>
        <div>
          <div class="form-group">
            <label class="form-label"><?php echo $is_rtl ? 'فوری ادا شدہ رقم (Paid Deposit)' : 'Amount Paid to Supplier'; ?> (Rs)</label>
            <input type="number" step="0.01" name="paid_amount" class="form-control" value="0.00">
          </div>
          <button type="submit" class="btn btn-primary" style="width:100%; padding:10px; font-weight:700; margin-top:10px;">
            💾 <?php echo $is_rtl ? 'خریداری بل محفوظ کریں' : 'Save Purchase & Update Stock'; ?>
          </button>
        </div>
      </div>
    </div>
  </div>
</form>

<script>
function populatePurRate(select) {
  const opt = select.options[select.selectedIndex];
  const cost = opt.getAttribute('data-cost') || 0;
  const row = select.closest('tr');
  row.querySelector('.pur-price').value = parseFloat(cost).toFixed(2);
  calcPurRow(row.querySelector('.pur-qty'));
}

function calcPurRow(input) {
  const row = input.closest('tr');
  const qty = parseFloat(row.querySelector('.pur-qty').value) || 0;
  const price = parseFloat(row.querySelector('.pur-price').value) || 0;
  row.querySelector('.pur-total').value = (qty * price).toFixed(2);
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
