<?php
require_once __DIR__ . '/../config/init.php';
require_once __DIR__ . '/../includes/header.php';

$bid = (int)$current_business['id'];
$pdo = getDB();

$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-d');
$search = trim($_GET['search'] ?? '');

$sql = "
    SELECT 
        ii.description,
        p.id AS product_id,
        p.code_or_barcode AS product_code,
        p.product_type,
        COALESCE(cat.name, 'Services & Custom Items') AS category_name,
        COUNT(DISTINCT inv.id) AS invoices_count,
        SUM(ii.quantity) AS total_qty_sold,
        AVG(ii.unit_price) AS avg_selling_rate,
        SUM(ii.total_amount) AS total_revenue,
        COALESCE(p.purchase_price, 0) AS unit_cost,
        SUM(ii.quantity * COALESCE(p.purchase_price, 0)) AS total_cogs
    FROM invoice_items ii
    JOIN invoices inv ON inv.id = ii.invoice_id
    LEFT JOIN products p ON p.id = ii.product_id
    LEFT JOIN categories cat ON cat.id = p.category_id
    WHERE inv.business_id = ? AND inv.invoice_date BETWEEN ? AND ?
";
$params = [$bid, $start_date, $end_date];

if ($search !== '') {
    $sql .= " AND ii.description LIKE ?";
    $params[] = "%{$search}%";
}

$sql .= " GROUP BY ii.description, p.id, p.code_or_barcode, p.product_type, p.purchase_price, cat.name
          ORDER BY total_revenue DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$grand_revenue = 0.0;
$grand_qty = 0.0;
$grand_cogs = 0.0;
$grand_profit = 0.0;

foreach ($rows as &$r) {
    $rev = (float)$r['total_revenue'];
    $cogs = (float)$r['total_cogs'];
    $profit = $rev - $cogs;
    $r['gross_profit'] = $profit;
    $r['margin_pct'] = ($rev > 0) ? round(($profit / $rev) * 100, 1) : 0;

    $grand_revenue += $rev;
    $grand_qty += (float)$r['total_qty_sold'];
    $grand_cogs += $cogs;
    $grand_profit += $profit;
}
unset($r);
?>

<div class="page-header">
  <div>
    <h1 class="page-title">
      📦 <?php echo $is_rtl ? 'سیلز بلحاظ پروڈکٹ / آئٹم رپورٹ' : 'Sales by Product / Item Report'; ?>
    </h1>
    <p style="color:var(--text-muted); font-size:0.9rem;">
      <?php echo h($current_business['name']); ?> &bull; <?php echo format_date($start_date); ?> to <?php echo format_date($end_date); ?>
    </p>
  </div>
  <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
    <form method="GET" action="sales_by_product.php" style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
      <input type="text" name="search" class="form-control" placeholder="<?php echo $is_rtl ? 'پروڈکٹ نام تلاش کریں...' : 'Search item...'; ?>" value="<?php echo h($search); ?>" style="width:160px;">
      <input type="date" name="start_date" class="form-control" value="<?php echo h($start_date); ?>">
      <span style="color:var(--text-muted);">&mdash;</span>
      <input type="date" name="end_date" class="form-control" value="<?php echo h($end_date); ?>">
      <button type="submit" class="btn btn-primary btn-sm">🔍 <?php echo h(__('filter')); ?></button>
      <?php if ($search): ?>
        <a href="sales_by_product.php" class="btn btn-outline btn-sm"><?php echo h(__('reset')); ?></a>
      <?php endif; ?>
    </form>
    <button onclick="window.print()" class="btn btn-outline btn-sm">🖨️ <?php echo h(__('print')); ?></button>
  </div>
</div>

<!-- Summary Metrics Cards -->
<div class="grid grid-cols-4" style="gap:15px; margin-bottom:20px;">
  <div class="card" style="padding:15px 20px; text-align:center;">
    <div style="font-size:0.85rem; color:var(--text-muted);"><?php echo $is_rtl ? 'کل فروخت آمدن' : 'Total Sales Revenue'; ?></div>
    <div style="font-size:1.35rem; font-weight:800; color:var(--primary); margin-top:4px;">
      <?php echo format_currency($grand_revenue); ?>
    </div>
  </div>

  <div class="card" style="padding:15px 20px; text-align:center;">
    <div style="font-size:0.85rem; color:var(--text-muted);"><?php echo $is_rtl ? 'کل فروخت شدہ اشیاء' : 'Total Units Sold'; ?></div>
    <div style="font-size:1.35rem; font-weight:800; color:var(--success); margin-top:4px;">
      <?php echo number_format($grand_qty, 0); ?>
    </div>
  </div>

  <div class="card" style="padding:15px 20px; text-align:center;">
    <div style="font-size:0.85rem; color:var(--text-muted);"><?php echo $is_rtl ? 'کل مالیت لاگت (COGS)' : 'Cost of Goods (COGS)'; ?></div>
    <div style="font-size:1.35rem; font-weight:800; color:#475569; margin-top:4px;">
      <?php echo format_currency($grand_cogs); ?>
    </div>
  </div>

  <div class="card" style="padding:15px 20px; text-align:center;">
    <div style="font-size:0.85rem; color:var(--text-muted);"><?php echo $is_rtl ? 'مجموعی منافع (Gross Profit)' : 'Gross Profit'; ?></div>
    <div style="font-size:1.35rem; font-weight:800; color:#059669; margin-top:4px;">
      <?php echo format_currency($grand_profit); ?>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-header">
    <div style="display:flex; justify-content:space-between; align-items:center; width:100%;">
      <span>📊 <?php echo $is_rtl ? 'پروڈکٹ و آئٹم وائز کارکردگی' : 'Item-Level Performance & Margins'; ?></span>
      <span class="badge badge-primary"><?php echo count($rows); ?> Items</span>
    </div>
  </div>
  <div class="table-responsive">
    <table class="table">
      <thead>
        <tr>
          <th>#</th>
          <th><?php echo $is_rtl ? 'آئٹم / پروڈکٹ کی تفصیل' : 'Product / Item Description'; ?></th>
          <th><?php echo $is_rtl ? 'کیٹیگری' : 'Category'; ?></th>
          <th class="text-center"><?php echo $is_rtl ? 'بلز' : 'Bills'; ?></th>
          <th class="text-center"><?php echo $is_rtl ? 'تعداد' : 'Qty'; ?></th>
          <th class="text-right"><?php echo $is_rtl ? 'اوسط ریٹ' : 'Avg Rate'; ?></th>
          <th class="text-right"><?php echo $is_rtl ? 'کل ریونیو' : 'Revenue'; ?></th>
          <th class="text-right"><?php echo $is_rtl ? 'خام منافع' : 'Gross Profit'; ?></th>
          <th class="text-center"><?php echo $is_rtl ? 'مارجن' : 'Margin'; ?></th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($rows)): ?>
          <tr><td colspan="9" class="text-center text-muted" style="padding:25px;"><?php echo $is_rtl ? 'اس مدت میں کوئی ریکارڈ نہیں ملا' : 'No sales records found for this period.'; ?></td></tr>
        <?php else: ?>
          <?php foreach ($rows as $idx => $r): ?>
            <tr>
              <td><?php echo $idx + 1; ?></td>
              <td>
                <strong><?php echo h($r['description']); ?></strong>
                <?php if (!empty($r['product_code'])): ?>
                  <span style="font-size:11px; color:var(--text-muted); display:block;">Code: <?php echo h($r['product_code']); ?></span>
                <?php endif; ?>
              </td>
              <td><span class="badge badge-secondary"><?php echo h($r['category_name']); ?></span></td>
              <td class="text-center"><span class="badge badge-outline"><?php echo (int)$r['invoices_count']; ?></span></td>
              <td class="text-center font-bold"><?php echo number_format((float)$r['total_qty_sold'], 0); ?></td>
              <td class="text-right"><?php echo format_currency($r['avg_selling_rate']); ?></td>
              <td class="text-right" style="font-weight:700; color:var(--primary-dark);">
                <?php echo format_currency($r['total_revenue']); ?>
              </td>
              <td class="text-right" style="font-weight:700; color:<?php echo ($r['gross_profit'] >= 0) ? '#059669' : '#dc2626'; ?>;">
                <?php echo format_currency($r['gross_profit']); ?>
              </td>
              <td class="text-center">
                <span class="badge <?php echo ($r['margin_pct'] >= 30) ? 'badge-success' : (($r['margin_pct'] >= 10) ? 'badge-primary' : 'badge-danger'); ?>">
                  <?php echo $r['margin_pct']; ?>%
                </span>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
      <?php if (!empty($rows)): ?>
        <tfoot>
          <tr style="background:#f8fafc; font-weight:800; border-top:2px solid var(--border-dark);">
            <td colspan="4"><?php echo $is_rtl ? 'کل میزان' : 'Total'; ?></td>
            <td class="text-center"><?php echo number_format($grand_qty, 0); ?></td>
            <td>-</td>
            <td class="text-right" style="color:var(--primary-dark); font-size:1.05rem;"><?php echo format_currency($grand_revenue); ?></td>
            <td class="text-right" style="color:#059669; font-size:1.05rem;"><?php echo format_currency($grand_profit); ?></td>
            <td class="text-center">
              <?php echo ($grand_revenue > 0) ? round(($grand_profit / $grand_revenue) * 100, 1) : 0; ?>%
            </td>
          </tr>
        </tfoot>
      <?php endif; ?>
    </table>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
