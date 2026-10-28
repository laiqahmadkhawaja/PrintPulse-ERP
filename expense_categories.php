<?php
require_once __DIR__ . '/config/init.php';
require_once __DIR__ . '/includes/header.php';

$bid = (int)$current_business['id'];
$pdo = getDB();
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $name = trim($_POST['name'] ?? '');
        $name_ur = trim($_POST['name_ur'] ?? '');
        $acc_id = (int)($_POST['account_id'] ?? 0);

        if (!empty($name)) {
            $stmt = $pdo->prepare("INSERT INTO expense_categories (business_id, name, name_ur, account_id) VALUES (?, ?, ?, ?)");
            $stmt->execute([$bid, $name, $name_ur, $acc_id ?: null]);
            $msg = $is_rtl ? 'اخراجات کیٹیگری کامیابی سے بن گئی۔' : 'Expense category created.';
        }
    }
}

$expenseAccounts = $pdo->query("SELECT id, account_code, account_name FROM chart_of_accounts WHERE business_id = {$bid} AND account_type = 'expense' ORDER BY account_code ASC")->fetchAll();
$categories = $pdo->query("SELECT ec.*, coa.account_name, coa.account_code 
                           FROM expense_categories ec 
                           LEFT JOIN chart_of_accounts coa ON coa.id = ec.account_id 
                           WHERE ec.business_id = {$bid} ORDER BY ec.name ASC")->fetchAll();
?>

<div class="page-header">
  <div>
    <h1 class="page-title">
      📑 <?php echo $is_rtl ? 'اخراجات کی کیٹیگریز' : 'Expense Categories'; ?>
    </h1>
  </div>
  <a href="expenses.php" class="btn btn-outline">&larr; <?php echo h(__('nav_expenses')); ?></a>
</div>

<?php if ($msg): ?>
  <div class="alert alert-success"><?php echo h($msg); ?></div>
<?php endif; ?>

<div class="grid grid-cols-3">
  <div class="card">
    <div class="card-header">
      <span>➕ <?php echo $is_rtl ? 'نئی اخراجات کیٹیگری' : 'Add Category'; ?></span>
    </div>
    <div class="card-body">
      <form method="POST" action="expense_categories.php">
        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">

        <div class="form-group">
          <label class="form-label">Category Name (English) *</label>
          <input type="text" name="name" class="form-control" required placeholder="e.g. Generator Fuel">
        </div>
        <div class="form-group">
          <label class="form-label">Category Name (Urdu)</label>
          <input type="text" name="name_ur" class="form-control" placeholder="مثال: جنریٹر فیول / ڈیزل">
        </div>
        <div class="form-group">
          <label class="form-label"><?php echo $is_rtl ? 'اکاؤنٹنگ کھاتہ (GL Mapping)' : 'Chart of Account Link'; ?></label>
          <select name="account_id" class="form-control">
            <option value="">-- Auto Map to Misc Expense --</option>
            <?php foreach ($expenseAccounts as $ea): ?>
              <option value="<?php echo $ea['id']; ?>">
                [<?php echo $ea['account_code']; ?>] <?php echo h($ea['account_name']); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <button type="submit" class="btn btn-primary" style="width:100%; margin-top:10px;">
          💾 <?php echo h(__('save')); ?>
        </button>
      </form>
    </div>
  </div>

  <div class="card" style="grid-column: span 2;">
    <div class="card-header">
      <span>📋 <?php echo $is_rtl ? 'موجودہ کیٹیگریز' : 'Configured Categories'; ?></span>
    </div>
    <div class="table-responsive">
      <table class="table">
        <thead>
          <tr>
            <th>Name (English)</th>
            <th>Name (Urdu)</th>
            <th>Linked Account (GL)</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($categories as $cat): ?>
            <tr>
              <td><strong><?php echo h($cat['name']); ?></strong></td>
              <td><?php echo h($cat['name_ur'] ?: '-'); ?></td>
              <td>
                <code>[<?php echo $cat['account_code'] ?: '6090'; ?>]</code>
                <?php echo h($cat['account_name'] ?: 'Miscellaneous Expenses'); ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
