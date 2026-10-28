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
        $desc = trim($_POST['description'] ?? '');

        if (!empty($name)) {
            $stmt = $pdo->prepare("INSERT INTO categories (business_id, name, name_ur, description) VALUES (?, ?, ?, ?)");
            $stmt->execute([$bid, $name, $name_ur, $desc]);
            $msg = $is_rtl ? 'کیٹیگری کامیابی سے محفوظ ہو گئی۔' : 'Category added successfully.';
        }
    }
}

$categories = $pdo->query("SELECT * FROM categories WHERE business_id = {$bid} ORDER BY name ASC")->fetchAll();
?>

<div class="page-header">
  <div>
    <h1 class="page-title">
      🏷️ <?php echo h(__('nav_categories')); ?>
    </h1>
  </div>
  <a href="products.php" class="btn btn-outline">&larr; <?php echo h(__('nav_products')); ?></a>
</div>

<?php if ($msg): ?>
  <div class="alert alert-success"><?php echo h($msg); ?></div>
<?php endif; ?>

<div class="grid grid-cols-3">
  <div class="card">
    <div class="card-header">
      <span>➕ <?php echo $is_rtl ? 'نئی کیٹیگری بنائیں' : 'Add Category'; ?></span>
    </div>
    <div class="card-body">
      <form method="POST" action="categories.php">
        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">

        <div class="form-group">
          <label class="form-label"><?php echo $is_rtl ? 'نام (English)' : 'Category Name'; ?> *</label>
          <input type="text" name="name" class="form-control" required placeholder="e.g. Printer Toners">
        </div>
        <div class="form-group">
          <label class="form-label"><?php echo $is_rtl ? 'نام (اردو)' : 'Category Name (Urdu)'; ?></label>
          <input type="text" name="name_ur" class="form-control" placeholder="پرنٹر ٹونرز">
        </div>
        <div class="form-group">
          <label class="form-label"><?php echo $is_rtl ? 'تفصیل' : 'Description'; ?></label>
          <input type="text" name="description" class="form-control">
        </div>

        <button type="submit" class="btn btn-primary" style="width:100%; margin-top:10px;">
          💾 <?php echo h(__('save')); ?>
        </button>
      </form>
    </div>
  </div>

  <div class="card" style="grid-column: span 2;">
    <div class="card-header">
      <span>📋 <?php echo $is_rtl ? 'موجودہ کیٹیگریز' : 'Existing Categories'; ?></span>
    </div>
    <div class="table-responsive">
      <table class="table">
        <thead>
          <tr>
            <th>Name (English)</th>
            <th>Name (Urdu)</th>
            <th>Description</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($categories as $c): ?>
            <tr>
              <td><strong><?php echo h($c['name']); ?></strong></td>
              <td><?php echo h($c['name_ur'] ?: '-'); ?></td>
              <td><?php echo h($c['description'] ?: '-'); ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
