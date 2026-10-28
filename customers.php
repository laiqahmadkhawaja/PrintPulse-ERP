<?php
require_once __DIR__ . '/config/init.php';
require_once __DIR__ . '/includes/header.php';

$bid = (int)$current_business['id'];
$pdo = getDB();
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_customer') {
    if (verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $name = trim($_POST['name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $opening = (float)($_POST['opening_balance'] ?? 0);

        if (!empty($name)) {
            $stmt = $pdo->prepare("INSERT INTO customers (business_id, name, phone, email, address, opening_balance, current_balance) 
                                   VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$bid, $name, $phone, $email, $address, $opening, $opening]);
            $msg = $is_rtl ? 'نیا کسٹمر کھاتہ کامیابی سے بن گیا۔' : 'Customer account created.';
        }
    }
}

$search = trim($_GET['search'] ?? '');
$sql = "SELECT * FROM customers WHERE business_id = ?";
$params = [$bid];
if ($search !== '') {
    $sql .= " AND (name LIKE ? OR phone LIKE ?)";
    $like = "%{$search}%";
    $params[] = $like; $params[] = $like;
}
$sql .= " ORDER BY name ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$customers = $stmt->fetchAll();
?>

<div class="page-header">
  <div>
    <h1 class="page-title">
      👤 <?php echo h(__('nav_customers')); ?>
    </h1>
    <p style="color:var(--text-muted); font-size:0.9rem;">
      <?php echo $is_rtl ? 'گاہکوں کی فہرست، فون نمبرز اور کھاتہ بقایا جات' : 'Customer accounts, contact directory, and receivables'; ?>
    </p>
  </div>
</div>

<?php if ($msg): ?>
  <div class="alert alert-success"><?php echo h($msg); ?></div>
<?php endif; ?>

<div class="grid grid-cols-3">
  <!-- Add Customer Form -->
  <div class="card">
    <div class="card-header">
      <span>➕ <?php echo $is_rtl ? 'نیا کسٹمر کھاتہ درج کریں' : 'Add New Customer'; ?></span>
    </div>
    <div class="card-body">
      <form method="POST" action="customers.php">
        <input type="hidden" name="action" value="add_customer">
        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">

        <div class="form-group">
          <label class="form-label"><?php echo h(__('customer_name')); ?> *</label>
          <input type="text" name="name" class="form-control" required placeholder="e.g. Al-Rehman Traders">
        </div>
        <div class="form-group">
          <label class="form-label"><?php echo h(__('phone')); ?> / <?php echo h(__('cell')); ?></label>
          <input type="text" name="phone" class="form-control" placeholder="0300-1234567">
        </div>
        <div class="form-group">
          <label class="form-label"><?php echo h(__('email')); ?></label>
          <input type="email" name="email" class="form-control" placeholder="customer@mail.com">
        </div>
        <div class="form-group">
          <label class="form-label"><?php echo h(__('address')); ?></label>
          <input type="text" name="address" class="form-control" placeholder="Market, City">
        </div>
        <div class="form-group">
          <label class="form-label"><?php echo $is_rtl ? 'ابتدائی بقایا (Opening Balance)' : 'Opening Balance'; ?> (Rs)</label>
          <input type="number" step="0.01" name="opening_balance" class="form-control" value="0.00">
        </div>

        <button type="submit" class="btn btn-primary" style="width:100%; margin-top:10px;">
          💾 <?php echo h(__('save')); ?>
        </button>
      </form>
    </div>
  </div>

  <!-- Customers List -->
  <div class="card" style="grid-column: span 2;">
    <div class="card-header">
      <form method="GET" action="customers.php" style="display:flex; gap:10px; width:100%; max-width:350px;">
        <input type="text" name="search" class="form-control" placeholder="<?php echo $is_rtl ? 'کسٹمر یا فون تلاش کریں...' : 'Search name or phone...'; ?>" value="<?php echo h($search); ?>">
        <button type="submit" class="btn btn-outline btn-sm">🔍 <?php echo h(__('search')); ?></button>
      </form>
    </div>
    <div class="table-responsive">
      <table class="table">
        <thead>
          <tr>
            <th><?php echo h(__('customer_name')); ?></th>
            <th><?php echo h(__('phone')); ?></th>
            <th><?php echo h(__('address')); ?></th>
            <th class="text-right"><?php echo $is_rtl ? 'موجودہ بقایا' : 'Current Balance'; ?></th>
            <th class="text-center"><?php echo h(__('actions')); ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($customers as $c): ?>
            <tr>
              <td><strong><?php echo h($c['name']); ?></strong></td>
              <td><?php echo h($c['phone'] ?: '-'); ?></td>
              <td><?php echo h($c['address'] ?: '-'); ?></td>
              <td class="text-right">
                <?php if ($c['current_balance'] > 0): ?>
                  <span class="badge badge-danger"><?php echo format_currency($c['current_balance']); ?></span>
                <?php else: ?>
                  <span class="badge badge-success"><?php echo format_currency(0); ?></span>
                <?php endif; ?>
              </td>
              <td class="text-center">
                <a href="customer_view.php?id=<?php echo $c['id']; ?>" class="btn btn-outline btn-sm">
                  📜 <?php echo $is_rtl ? 'لیجر کھاتہ' : 'Statement'; ?>
                </a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
