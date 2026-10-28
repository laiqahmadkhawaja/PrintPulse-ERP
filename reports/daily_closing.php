<?php
require_once __DIR__ . '/../config/init.php';
require_once __DIR__ . '/../includes/header.php';

$bid = (int)$current_business['id'];
$pdo = getDB();
$closing_date = $_GET['date'] ?? date('Y-m-d');
$msg = '';

// Check if closing already performed
$stmtCheck = $pdo->prepare("SELECT * FROM daily_closings WHERE business_id = ? AND closing_date = ?");
$stmtCheck->execute([$bid, $closing_date]);
$existingClosing = $stmtCheck->fetch();

// Calculate Today's figures
// 1. Cash Sales
$stmt = $pdo->prepare("SELECT COALESCE(SUM(paid_amount), 0) FROM invoices WHERE business_id = ? AND invoice_date = ? AND invoice_type IN ('sale', 'pos') AND payment_method = 'cash'");
$stmt->execute([$bid, $closing_date]);
$cash_sales = (float)$stmt->fetchColumn();

// 2. Cash Repairs
$stmt = $pdo->prepare("SELECT COALESCE(SUM(paid_amount), 0) FROM invoices WHERE business_id = ? AND invoice_date = ? AND invoice_type = 'repair' AND payment_method = 'cash'");
$stmt->execute([$bid, $closing_date]);
$cash_repairs = (float)$stmt->fetchColumn();

// 3. Cash Refills
$stmt = $pdo->prepare("SELECT COALESCE(SUM(paid_amount), 0) FROM invoices WHERE business_id = ? AND invoice_date = ? AND invoice_type = 'refill' AND payment_method = 'cash'");
$stmt->execute([$bid, $closing_date]);
$cash_refills = (float)$stmt->fetchColumn();

// 4. Customer Receipts in cash
$stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE business_id = ? AND payment_date = ? AND payment_type = 'customer_receipt' AND payment_method = 'cash'");
$stmt->execute([$bid, $closing_date]);
$cust_receipts = (float)$stmt->fetchColumn();

// 5. Supplier Payments in cash
$stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE business_id = ? AND payment_date = ? AND payment_type = 'supplier_payment' AND payment_method = 'cash'");
$stmt->execute([$bid, $closing_date]);
$sup_payments = (float)$stmt->fetchColumn();

// 6. Cash Expenses
$stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE business_id = ? AND expense_date = ?");
$stmt->execute([$bid, $closing_date]);
$cash_expenses = (float)$stmt->fetchColumn();

// Net cash generated today
$net_today_cash = ($cash_sales + $cash_repairs + $cash_refills + $cust_receipts) - ($sup_payments + $cash_expenses);

// Handle Closing Post
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $actual_cash = (float)($_POST['actual_cash'] ?? 0);
        $opening_cash = (float)($_POST['opening_cash'] ?? 0);
        $expected_cash = $opening_cash + $net_today_cash;
        $difference = $actual_cash - $expected_cash;
        $notes = trim($_POST['notes'] ?? '');

        if ($existingClosing) {
            $upd = $pdo->prepare("UPDATE daily_closings SET actual_cash = ?, expected_cash = ?, difference = ?, notes = ?, closed_by = ? WHERE id = ?");
            $upd->execute([$actual_cash, $expected_cash, $difference, $notes, $current_user['id'], $existingClosing['id']]);
        } else {
            $ins = $pdo->prepare("INSERT INTO daily_closings (business_id, closing_date, opening_cash, cash_sales, cash_repairs, cash_refills, customer_receipts, supplier_payments, cash_expenses, expected_cash, actual_cash, difference, closed_by, status, notes) 
                                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'closed', ?)");
            $ins->execute([
                $bid,
                $closing_date,
                $opening_cash,
                $cash_sales,
                $cash_repairs,
                $cash_refills,
                $cust_receipts,
                $sup_payments,
                $cash_expenses,
                $expected_cash,
                $actual_cash,
                $difference,
                $current_user['id'],
                $notes
            ]);
        }

        $msg = $is_rtl ? 'روزانہ کیش کلوزنگ کامیابی سے محفوظ کر لی گئی۔' : 'Daily register closing saved successfully.';
        $stmtCheck->execute([$bid, $closing_date]);
        $existingClosing = $stmtCheck->fetch();
    }
}

$openingCash = $existingClosing ? (float)$existingClosing['opening_cash'] : 0.0;
$expectedCash = $openingCash + $net_today_cash;
?>

<div class="page-header">
  <div>
    <h1 class="page-title">
      🔒 <?php echo h(__('nav_daily_closing')); ?> &mdash; <?php echo $is_rtl ? 'روزانہ کیش کاؤنٹر کلوزنگ' : 'Daily Register Closing'; ?>
    </h1>
    <p style="color:var(--text-muted); font-size:0.9rem;">
      <?php echo h($current_business['name']); ?> &bull; Date: <?php echo format_date($closing_date); ?>
    </p>
  </div>
  <div>
    <form method="GET" action="daily_closing.php" style="display:flex; gap:10px;">
      <input type="date" name="date" class="form-control" value="<?php echo h($closing_date); ?>">
      <button type="submit" class="btn btn-outline btn-sm">🔍 <?php echo h(__('filter')); ?></button>
    </form>
  </div>
</div>

<?php if ($msg): ?>
  <div class="alert alert-success"><?php echo h($msg); ?></div>
<?php endif; ?>

<div class="grid grid-cols-2">
  <!-- Inflow & Outflow Summary -->
  <div class="card">
    <div class="card-header">
      <span>💵 <?php echo $is_rtl ? 'آج کی نقد رقوم کا حساب' : "Today's Cash Flow Breakdown"; ?></span>
      <?php if ($existingClosing): ?>
        <span class="badge badge-success"><?php echo $is_rtl ? 'کلوزڈ (Closed)' : 'Closed'; ?></span>
      <?php endif; ?>
    </div>
    <div class="card-body">
      <table class="table">
        <tr>
          <td><?php echo $is_rtl ? 'نقد کاؤنٹر سیلز' : 'Cash Sales (POS/Retail)'; ?></td>
          <td class="text-right" style="color:var(--success); font-weight:700;">+ <?php echo format_currency($cash_sales); ?></td>
        </tr>
        <tr>
          <td><?php echo $is_rtl ? 'نقد پرنٹر مرمت آمدن' : 'Cash Repair Invoices'; ?></td>
          <td class="text-right" style="color:var(--success); font-weight:700;">+ <?php echo format_currency($cash_repairs); ?></td>
        </tr>
        <tr>
          <td><?php echo $is_rtl ? 'نقد ٹونر ریفلنگ آمدن' : 'Cash Refill Invoices'; ?></td>
          <td class="text-right" style="color:var(--success); font-weight:700;">+ <?php echo format_currency($cash_refills); ?></td>
        </tr>
        <tr>
          <td><?php echo $is_rtl ? 'گاہکوں سے نقد وصولیاں (CRV)' : 'Customer Cash Receipts'; ?></td>
          <td class="text-right" style="color:var(--success); font-weight:700;">+ <?php echo format_currency($cust_receipts); ?></td>
        </tr>
        <tr style="border-top:1px dashed #cbd5e1;">
          <td><?php echo $is_rtl ? 'سپلائرز کو نقد ادائیگیاں (SPV)' : 'Supplier Cash Payments'; ?></td>
          <td class="text-right" style="color:var(--danger); font-weight:700;">- <?php echo format_currency($sup_payments); ?></td>
        </tr>
        <tr>
          <td><?php echo $is_rtl ? 'دکان کے روزانہ اخراجات' : 'Cash Expenses (Rent, Bills, Tea)'; ?></td>
          <td class="text-right" style="color:var(--danger); font-weight:700;">- <?php echo format_currency($cash_expenses); ?></td>
        </tr>
        <tr style="background:#f8fafc; font-size:1.15rem; font-weight:800; border-top:2px solid #000;">
          <td>Net Cash Generated Today:</td>
          <td class="text-right"><?php echo format_currency($net_today_cash); ?></td>
        </tr>
      </table>
    </div>
  </div>

  <!-- Register Closing Submission Form -->
  <div class="card">
    <div class="card-header">
      <span>🔒 <?php echo $is_rtl ? 'کاؤنٹر نقد تصدیق و کلوزنگ' : 'Physical Cash Verification & Seal'; ?></span>
    </div>
    <div class="card-body">
      <form method="POST" action="daily_closing.php?date=<?php echo $closing_date; ?>">
        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">

        <div class="form-group">
          <label class="form-label"><?php echo $is_rtl ? 'صبح کا ابتدائی نقد (Opening Drawer Cash)' : 'Opening Drawer Cash'; ?> (Rs)</label>
          <input type="number" step="0.01" name="opening_cash" id="openCash" class="form-control" value="<?php echo $openingCash; ?>" oninput="calcClosing()">
        </div>

        <div class="form-group">
          <label class="form-label"><?php echo $is_rtl ? 'متوقع نقد رقم (Expected Drawer Cash)' : 'Expected Total Cash in Drawer'; ?> (Rs)</label>
          <input type="text" id="expectedCash" class="form-control" style="font-weight:800; background:#f1f5f9;" value="<?php echo format_currency($expectedCash, false); ?>" readonly>
        </div>

        <div class="form-group">
          <label class="form-label"><?php echo $is_rtl ? 'دکان دراز میں گنتی شدہ رقم (Actual Cash Counted)' : 'Physical Cash Counted in Drawer'; ?> (Rs) *</label>
          <input type="number" step="0.01" name="actual_cash" id="actCash" class="form-control" style="font-size:1.2rem; font-weight:800;" value="<?php echo $existingClosing ? $existingClosing['actual_cash'] : $expectedCash; ?>" required oninput="calcClosing()">
        </div>

        <div class="form-group">
          <label class="form-label"><?php echo $is_rtl ? 'فرق (Shortage / Surplus)' : 'Difference'; ?> (Rs)</label>
          <input type="text" id="diffCash" class="form-control" style="font-weight:800;" value="<?php echo $existingClosing ? format_currency($existingClosing['difference'], false) : '0.00'; ?>" readonly>
        </div>

        <div class="form-group">
          <label class="form-label"><?php echo h(__('notes')); ?></label>
          <textarea name="notes" class="form-control" rows="2" placeholder="Remarks or reasons for variance"><?php echo h($existingClosing['notes'] ?? ''); ?></textarea>
        </div>

        <button type="submit" class="btn btn-primary" style="width:100%; padding:10px; font-weight:800;">
          🔒 <?php echo $is_rtl ? 'روزانہ کیش کلوزنگ لاک کریں' : 'Confirm & Save Daily Closing'; ?>
        </button>
      </form>
    </div>
  </div>
</div>

<script>
const netToday = <?php echo $net_today_cash; ?>;
function calcClosing() {
  const openCash = parseFloat(document.getElementById('openCash').value) || 0;
  const actCash = parseFloat(document.getElementById('actCash').value) || 0;
  const expCash = openCash + netToday;
  document.getElementById('expectedCash').value = expCash.toFixed(2);
  const diff = actCash - expCash;
  const diffEl = document.getElementById('diffCash');
  diffEl.value = diff.toFixed(2);
  diffEl.style.color = (Math.abs(diff) < 0.01) ? 'var(--success)' : (diff > 0 ? 'var(--primary)' : 'var(--danger)');
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
