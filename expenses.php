<?php
require_once __DIR__ . '/config/init.php';
require_once __DIR__ . '/includes/accounting_helper.php';

$bid = (int)$current_business['id'];
$pdo = getDB();
$error = '';
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = "Security token mismatch.";
    } else {
        $category_id = (int)($_POST['category_id'] ?? 0);
        $amount = (float)($_POST['amount'] ?? 0);
        $exp_date = $_POST['expense_date'] ?? date('Y-m-d');
        $pay_method = $_POST['payment_method'] ?? 'cash';
        $payee = trim($_POST['payee_name'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if ($category_id <= 0 || $amount <= 0) {
            $error = $is_rtl ? 'کیٹیگری اور درست رقم منتخب کریں۔' : 'Select category and valid amount.';
        } else {
            $catStmt = $pdo->prepare("SELECT account_id FROM expense_categories WHERE id = ? AND business_id = ?");
            $catStmt->execute([$category_id, $bid]);
            $expenseAccId = $catStmt->fetchColumn();

            if (!$expenseAccId) {
                $expenseAccId = AccountingEngine::getAccountIdByCode($bid, '6090'); // Misc Expense fallback
            }

            $creditAccCode = ($pay_method === 'bank') ? '1020' : '1010';
            $creditAccId = AccountingEngine::getAccountIdByCode($bid, $creditAccCode);

            $lastNo = $pdo->query("SELECT MAX(id) FROM expenses WHERE business_id = {$bid}")->fetchColumn() ?: 100;
            $voucher_number = "EXP-" . ($lastNo + 1);

            $pdo->beginTransaction();
            try {
                $stmt = $pdo->prepare("INSERT INTO expenses 
                    (business_id, voucher_number, category_id, account_id, payment_account_id, expense_date, amount, payee_name, description) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $bid,
                    $voucher_number,
                    $category_id,
                    $expenseAccId,
                    $creditAccId,
                    $exp_date,
                    $amount,
                    $payee,
                    $description
                ]);
                $expense_id = $pdo->lastInsertId();

                // Post Double-Entry Journal Entry
                // Debit: Expense Account (increases expense)
                // Credit: Cash/Bank Account (decreases cash/bank)
                $journalLines = [
                    [
                        'account_id' => $expenseAccId,
                        'debit' => $amount,
                        'credit' => 0.00,
                        'narration' => "Expense {$voucher_number}: {$description}"
                    ],
                    [
                        'account_id' => $creditAccId,
                        'debit' => 0.00,
                        'credit' => $amount,
                        'narration' => "Paid via " . strtoupper($pay_method) . " for {$voucher_number}"
                    ]
                ];

                AccountingEngine::postJournalEntry(
                    $bid,
                    $voucher_number,
                    $exp_date,
                    'expense',
                    $expense_id,
                    "Business Expense {$voucher_number} - {$description}",
                    $journalLines,
                    $current_user['id']
                );

                $pdo->commit();
                log_audit('CREATE_EXPENSE', 'expense', $expense_id, "Recorded expense {$voucher_number}");
                $msg = $is_rtl ? "خرچہ واؤچر {$voucher_number} کامیابی سے درج ہو گیا۔" : "Expense voucher {$voucher_number} recorded successfully.";

            } catch (Exception $e) {
                $pdo->rollBack();
                $error = "Failed to record expense: " . $e->getMessage();
            }
        }
    }
}

$categories = $pdo->query("SELECT * FROM expense_categories WHERE business_id = {$bid} AND status = 'active' ORDER BY name ASC")->fetchAll();

$stmtExp = $pdo->prepare("SELECT e.*, ec.name as category_name 
                          FROM expenses e 
                          JOIN expense_categories ec ON ec.id = e.category_id 
                          WHERE e.business_id = ? 
                          ORDER BY e.id DESC LIMIT 20");
$stmtExp->execute([$bid]);
$expenses = $stmtExp->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
  <div>
    <h1 class="page-title">
      🧾 <?php echo h(__('nav_expenses')); ?>
    </h1>
    <p style="color:var(--text-muted); font-size:0.9rem;">
      <?php echo $is_rtl ? 'دکان کے روزمرہ اخراجات، کرایہ، بلز، تنخواہیں اور کیش بک کا اندراج' : 'Track rent, electricity, technician wages, tea, and operational costs'; ?>
    </p>
  </div>
  <div>
    <a href="expense_categories.php" class="btn btn-outline">📑 <?php echo $is_rtl ? 'کیٹیگریز منیجر' : 'Manage Categories'; ?></a>
  </div>
</div>

<?php if (!empty($error)): ?>
  <div class="alert alert-danger"><?php echo h($error); ?></div>
<?php endif; ?>
<?php if (!empty($msg)): ?>
  <div class="alert alert-success"><?php echo h($msg); ?></div>
<?php endif; ?>

<div class="grid grid-cols-3">
  <!-- Add Expense Form -->
  <div class="card">
    <div class="card-header">
      <span>➕ <?php echo $is_rtl ? 'نیا خرچہ واؤچر' : 'Record Expense'; ?></span>
    </div>
    <div class="card-body">
      <form method="POST" action="expenses.php">
        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">

        <div class="form-group">
          <label class="form-label"><?php echo $is_rtl ? 'اخراجات کیٹیگری' : 'Expense Category'; ?> *</label>
          <select name="category_id" class="form-control" required>
            <option value="">-- Select Category --</option>
            <?php foreach ($categories as $cat): ?>
              <option value="<?php echo $cat['id']; ?>">
                <?php echo h($cat['name']); ?> <?php echo $cat['name_ur'] ? '('.h($cat['name_ur']).')' : ''; ?>
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
          <input type="date" name="expense_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
        </div>

        <div class="form-group">
          <label class="form-label"><?php echo $is_rtl ? 'ادائیگی طریقہ' : 'Paid From'; ?></label>
          <select name="payment_method" class="form-control">
            <option value="cash">💵 Cash in Hand (نقد)</option>
            <option value="bank">🏦 Bank Account (بینک)</option>
          </select>
        </div>

        <div class="form-group">
          <label class="form-label"><?php echo $is_rtl ? 'وصول کنندہ (Payee)' : 'Paid To (Payee)'; ?></label>
          <input type="text" name="payee_name" class="form-control" placeholder="e.g. Electrician / Landlord">
        </div>

        <div class="form-group">
          <label class="form-label"><?php echo $is_rtl ? 'تفصیل' : 'Description / Narration'; ?></label>
          <textarea name="description" class="form-control" rows="2" placeholder="Details of expense"></textarea>
        </div>

        <button type="submit" class="btn btn-primary" style="width:100%; padding:10px; font-weight:700;">
          💾 <?php echo $is_rtl ? 'خرچہ محفوظ کریں' : 'Save Expense'; ?>
        </button>
      </form>
    </div>
  </div>

  <!-- Expenses Table -->
  <div class="card" style="grid-column: span 2;">
    <div class="card-header">
      <span>📋 <?php echo $is_rtl ? 'حالیہ اخراجات کی فہرست' : 'Recent Expenses'; ?></span>
    </div>
    <div class="table-responsive">
      <table class="table">
        <thead>
          <tr>
            <th>Voucher #</th>
            <th>Date</th>
            <th>Category</th>
            <th>Payee</th>
            <th>Description</th>
            <th class="text-right">Amount</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($expenses)): ?>
            <tr><td colspan="6" class="text-center text-muted" style="padding:20px;"><?php echo $is_rtl ? 'کوئی خرچہ ریکارڈ نہیں ہے' : 'No expenses recorded yet.'; ?></td></tr>
          <?php else: ?>
            <?php foreach ($expenses as $ex): ?>
              <tr>
                <td><strong><?php echo h($ex['voucher_number']); ?></strong></td>
                <td><?php echo format_date($ex['expense_date']); ?></td>
                <td><span class="badge badge-primary"><?php echo h($ex['category_name']); ?></span></td>
                <td><?php echo h($ex['payee_name'] ?: '-'); ?></td>
                <td><?php echo h($ex['description'] ?: '-'); ?></td>
                <td class="text-right"><strong><?php echo format_currency($ex['amount']); ?></strong></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
