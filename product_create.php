<?php
require_once __DIR__ . '/config/init.php';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/inventory_helper.php';

$bid = (int)$current_business['id'];
$pdo = getDB();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = "Security token mismatch.";
    } else {
        $name = trim($_POST['name'] ?? '');
        $name_ur = trim($_POST['name_ur'] ?? '');
        $category_id = (int)($_POST['category_id'] ?? 0);
        $code = trim($_POST['code_or_barcode'] ?? '');
        $unit = trim($_POST['unit'] ?? 'pcs');
        $purchase_price = (float)($_POST['purchase_price'] ?? 0);
        $selling_price = (float)($_POST['selling_price'] ?? 0);
        $initial_stock = (float)($_POST['initial_stock'] ?? 0);
        $reorder_level = (float)($_POST['reorder_level'] ?? 5);
        $product_type = $_POST['product_type'] ?? 'product';

        if (empty($name)) {
            $error = $is_rtl ? 'پروڈکٹ کا نام درج کرنا لازمی ہے۔' : 'Product name is required.';
        } else {
            $stmt = $pdo->prepare("INSERT INTO products 
                (business_id, category_id, name, name_ur, code_or_barcode, unit, purchase_price, selling_price, current_stock, reorder_level, product_type) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $bid,
                $category_id ?: null,
                $name,
                $name_ur,
                $code,
                $unit,
                $purchase_price,
                $selling_price,
                $initial_stock,
                $reorder_level,
                $product_type
            ]);
            $newId = $pdo->lastInsertId();

            if ($initial_stock > 0) {
                InventoryHelper::recordMovement($bid, $newId, 'initial', $initial_stock, $purchase_price, null, "Initial Opening Stock");
            }

            log_audit('CREATE_PRODUCT', 'product', $newId, "Created product {$name}");
            header("Location: products.php");
            exit;
        }
    }
}

$categories = $pdo->query("SELECT * FROM categories WHERE business_id = {$bid} ORDER BY name ASC")->fetchAll();
?>

<div class="page-header">
  <div>
    <h1 class="page-title">
      ➕ <?php echo $is_rtl ? 'نیا آئٹم / پرزہ شامل کریں' : 'Add New Product / Part'; ?>
    </h1>
  </div>
  <a href="products.php" class="btn btn-outline">&larr; <?php echo h(__('cancel')); ?></a>
</div>

<?php if (!empty($error)): ?>
  <div class="alert alert-danger"><?php echo h($error); ?></div>
<?php endif; ?>

<form method="POST" action="product_create.php">
  <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">

  <div class="card">
    <div class="card-header">
      <span>📦 <?php echo $is_rtl ? 'مصنوعات کی بنیادی معلومات' : 'Product Details & Pricing'; ?></span>
    </div>
    <div class="card-body">
      <div class="grid grid-cols-2">
        <div class="form-group">
          <label class="form-label"><?php echo h(__('description')); ?> (English Name) *</label>
          <input type="text" name="name" class="form-control" required placeholder="e.g. Canon LBP 2900 Fuser Sleeve">
        </div>
        <div class="form-group">
          <label class="form-label"><?php echo h(__('description')); ?> (Urdu Name)</label>
          <input type="text" name="name_ur" class="form-control" placeholder="اردو نام درج کریں">
        </div>
      </div>

      <div class="grid grid-cols-3">
        <div class="form-group">
          <label class="form-label"><?php echo h(__('nav_categories')); ?></label>
          <select name="category_id" class="form-control">
            <option value="">-- None --</option>
            <?php foreach ($categories as $cat): ?>
              <option value="<?php echo $cat['id']; ?>"><?php echo h($cat['name']); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label"><?php echo $is_rtl ? 'کوڈ / بارکوڈ' : 'Item Code / Barcode'; ?></label>
          <input type="text" name="code_or_barcode" class="form-control" placeholder="PRN-1020">
        </div>
        <div class="form-group">
          <label class="form-label"><?php echo $is_rtl ? 'پروڈکٹ کی قسم' : 'Product Type'; ?></label>
          <select name="product_type" class="form-control">
            <option value="product">Retail Product (Printer / Cartridge)</option>
            <option value="spare_part">Spare Part (Drum, Blade, Roller)</option>
            <option value="consumable_powder_ink">Consumable (Toner Powder / Ink)</option>
            <option value="service">Pure Service (Labour / Servicing)</option>
          </select>
        </div>
      </div>

      <div class="grid grid-cols-4">
        <div class="form-group">
          <label class="form-label"><?php echo $is_rtl ? 'خرید لاگت (Cost)' : 'Purchase Cost'; ?> (Rs)</label>
          <input type="number" step="0.01" name="purchase_price" class="form-control" value="0.00">
        </div>
        <div class="form-group">
          <label class="form-label"><?php echo $is_rtl ? 'سیل ریٹ (Selling)' : 'Selling Price'; ?> (Rs) *</label>
          <input type="number" step="0.01" name="selling_price" class="form-control" value="0.00" required>
        </div>
        <div class="form-group">
          <label class="form-label"><?php echo $is_rtl ? 'ابتدائی اسٹاک' : 'Opening Stock'; ?></label>
          <input type="number" step="0.01" name="initial_stock" class="form-control" value="0.00">
        </div>
        <div class="form-group">
          <label class="form-label"><?php echo $is_rtl ? 'کم اسٹاک الرٹ حد' : 'Reorder Alert Limit'; ?></label>
          <input type="number" step="0.01" name="reorder_level" class="form-control" value="5.00">
        </div>
      </div>

      <button type="submit" class="btn btn-primary" style="margin-top:10px;">
        💾 <?php echo h(__('save')); ?>
      </button>
    </div>
  </div>
</form>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
