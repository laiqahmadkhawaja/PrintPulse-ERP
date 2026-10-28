<?php
require_once __DIR__ . '/config/init.php';
require_once __DIR__ . '/includes/header.php';

$bid = (int)$current_business['id'];
$pdo = getDB();

$search = trim($_GET['search'] ?? '');
$sql = "SELECT p.*, c.name as category_name 
        FROM products p 
        LEFT JOIN categories c ON c.id = p.category_id 
        WHERE p.business_id = ?";
$params = [$bid];

if ($search !== '') {
    $sql .= " AND (p.name LIKE ? OR p.name_ur LIKE ? OR p.code_or_barcode LIKE ?)";
    $like = "%{$search}%";
    $params[] = $like; $params[] = $like; $params[] = $like;
}

$sql .= " ORDER BY p.name ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();
?>

<div class="page-header">
  <div>
    <h1 class="page-title">
      🗄️ <?php echo h(__('nav_products')); ?>
    </h1>
    <p style="color:var(--text-muted); font-size:0.9rem;">
      <?php echo $is_rtl ? 'پرنٹرز، کارٹریجز، اسپیئر پارٹس اور سیاہی کی مکمل انوینٹری' : 'Manage equipment, spare parts, toners, and stock levels'; ?>
    </p>
  </div>
  <div style="display:flex; gap:10px;">
    <a href="categories.php" class="btn btn-outline">🏷️ <?php echo h(__('nav_categories')); ?></a>
    <a href="product_create.php" class="btn btn-primary">➕ <?php echo $is_rtl ? 'نیا آئٹم شامل کریں' : 'Add New Product'; ?></a>
  </div>
</div>

<div class="card">
  <div class="card-header">
    <form method="GET" action="products.php" style="display:flex; gap:10px; width:100%; max-width:400px;">
      <input type="text" name="search" class="form-control" placeholder="<?php echo $is_rtl ? 'نام یا کوڈ تلاش کریں...' : 'Search product or code...'; ?>" value="<?php echo h($search); ?>">
      <button type="submit" class="btn btn-outline btn-sm">🔍 <?php echo h(__('search')); ?></button>
    </form>
  </div>

  <div class="table-responsive">
    <table class="table">
      <thead>
        <tr>
          <th><?php echo $is_rtl ? 'کوڈ / بارکوڈ' : 'Code / Barcode'; ?></th>
          <th><?php echo h(__('description')); ?></th>
          <th><?php echo h(__('nav_categories')); ?></th>
          <th><?php echo $is_rtl ? 'قسم' : 'Type'; ?></th>
          <th class="text-right"><?php echo $is_rtl ? 'خرید ریٹ' : 'Purchase Cost'; ?></th>
          <th class="text-right"><?php echo $is_rtl ? 'فروخت ریٹ' : 'Selling Price'; ?></th>
          <th class="text-center"><?php echo $is_rtl ? 'موجودہ اسٹاک' : 'Stock'; ?></th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($products)): ?>
          <tr><td colspan="7" class="text-center text-muted" style="padding:25px;"><?php echo $is_rtl ? 'کوئی پروڈکٹ موجود نہیں' : 'No products found.'; ?></td></tr>
        <?php else: ?>
          <?php foreach ($products as $p): ?>
            <tr>
              <td><code><?php echo h($p['code_or_barcode'] ?: '-'); ?></code></td>
              <td>
                <strong><?php echo h($p['name']); ?></strong>
                <?php if ($p['name_ur']): ?>
                  <div style="font-size:12px; color:#64748b;"><?php echo h($p['name_ur']); ?></div>
                <?php endif; ?>
              </td>
              <td><?php echo h($p['category_name'] ?: '-'); ?></td>
              <td><span class="badge badge-primary"><?php echo h($p['product_type']); ?></span></td>
              <td class="text-right"><?php echo format_currency($p['purchase_price']); ?></td>
              <td class="text-right"><strong><?php echo format_currency($p['selling_price']); ?></strong></td>
              <td class="text-center">
                <?php if ($p['product_type'] === 'service'): ?>
                  <span class="badge badge-secondary">Service</span>
                <?php elseif ($p['current_stock'] <= $p['reorder_level']): ?>
                  <span class="badge badge-danger"><?php echo (int)$p['current_stock']; ?> <?php echo h($p['unit']); ?> (Low)</span>
                <?php else: ?>
                  <span class="badge badge-success"><?php echo (int)$p['current_stock']; ?> <?php echo h($p['unit']); ?></span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
