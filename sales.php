<?php
require_once __DIR__ . '/config/init.php';
require_once __DIR__ . '/includes/header.php';

$bid = (int)$current_business['id'];
$pdo = getDB();

$search = trim($_GET['search'] ?? '');
$sql = "SELECT * FROM invoices WHERE business_id = ? AND invoice_type IN ('sale', 'pos')";
$params = [$bid];

if ($search !== '') {
    $sql .= " AND (invoice_number LIKE ? OR customer_name LIKE ? OR customer_phone LIKE ?)";
    $like = "%{$search}%";
    $params[] = $like; $params[] = $like; $params[] = $like;
}

$sql .= " ORDER BY id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$sales = $stmt->fetchAll();
?>

<div class="page-header">
  <div>
    <h1 class="page-title">
      🛒 <?php echo h(__('nav_sales')); ?>
    </h1>
    <p style="color:var(--text-muted); font-size:0.9rem;">
      <?php echo $is_rtl ? 'پرنٹرز، اسپیئر پارٹس اور کمپیوٹر سامان کے سیلز بلز' : 'All sales and counter POS invoices'; ?>
    </p>
  </div>
  <div style="display:flex; gap:10px;">
    <a href="pos.php" class="btn btn-primary">⚡ <?php echo h(__('nav_pos')); ?></a>
    <a href="sale_create.php" class="btn btn-success">➕ <?php echo $is_rtl ? 'نئی سیلز انوائس' : 'New Sales Invoice'; ?></a>
  </div>
</div>

<div class="card">
  <div class="card-header">
    <form method="GET" action="sales.php" style="display:flex; gap:10px; width:100%; max-width:400px;">
      <input type="text" name="search" class="form-control" placeholder="<?php echo $is_rtl ? 'بل نمبر یا گاہک کا نام...' : 'Search invoice # or customer...'; ?>" value="<?php echo h($search); ?>">
      <button type="submit" class="btn btn-outline btn-sm">🔍 <?php echo h(__('search')); ?></button>
    </form>
  </div>

  <div class="table-responsive">
    <table class="table">
      <thead>
        <tr>
          <th><?php echo h(__('invoice_no')); ?></th>
          <th><?php echo h(__('date')); ?></th>
          <th><?php echo h(__('customer_name')); ?></th>
          <th><?php echo $is_rtl ? 'قسم' : 'Type'; ?></th>
          <th class="text-right"><?php echo h(__('total')); ?></th>
          <th class="text-right"><?php echo h(__('paid')); ?></th>
          <th class="text-right"><?php echo h(__('balance')); ?></th>
          <th class="text-center"><?php echo h(__('actions')); ?></th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($sales)): ?>
          <tr><td colspan="8" class="text-center text-muted" style="padding:25px;"><?php echo $is_rtl ? 'کوئی بل موصول نہیں ہوا' : 'No sales invoices found.'; ?></td></tr>
        <?php else: ?>
          <?php foreach ($sales as $inv): ?>
            <tr>
              <td><strong><?php echo h($inv['invoice_number']); ?></strong></td>
              <td><?php echo format_date($inv['invoice_date']); ?></td>
              <td><?php echo h($inv['customer_name']); ?></td>
              <td><span class="badge badge-primary"><?php echo strtoupper($inv['invoice_type']); ?></span></td>
              <td class="text-right"><strong><?php echo format_currency($inv['total_amount']); ?></strong></td>
              <td class="text-right"><?php echo format_currency($inv['paid_amount']); ?></td>
              <td class="text-right">
                <?php if ($inv['balance_amount'] > 0): ?>
                  <span class="badge badge-danger"><?php echo format_currency($inv['balance_amount']); ?></span>
                <?php else: ?>
                  <span class="badge badge-success"><?php echo $is_rtl ? 'ادا شدہ' : 'Paid'; ?></span>
                <?php endif; ?>
              </td>
              <td class="text-center">
                <div style="display:inline-flex; gap:6px; align-items:center; justify-content:center;">
                  <a href="print_invoice.php?id=<?php echo $inv['id']; ?>" target="_blank" class="btn btn-outline btn-sm" title="<?php echo h(__('print')); ?>">
                    🖨️ <?php echo h(__('print')); ?>
                  </a>
                  <a href="invoice_edit.php?id=<?php echo $inv['id']; ?>" class="btn btn-outline btn-sm" title="<?php echo h(__('edit')); ?>">
                    ✏️ <?php echo h(__('edit')); ?>
                  </a>
                  <button type="button" class="btn btn-danger btn-sm" onclick="confirmDeleteInvoice(<?php echo $inv['id']; ?>, '<?php echo h($inv['invoice_number']); ?>')" title="<?php echo h(__('delete')); ?>" style="padding:4px 8px;">
                    🗑️
                  </button>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
