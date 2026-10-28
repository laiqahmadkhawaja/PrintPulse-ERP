<?php
require_once __DIR__ . '/../config/init.php';
require_once __DIR__ . '/../includes/header.php';

$bid = (int)$current_business['id'];
$pdo = getDB();

$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-d');

// Query sales grouped by category
$sql = "
    SELECT 
        COALESCE(cat.name, 'Services, Refills & Parts') AS category_name,
        COALESCE(cat.name_ur, '') AS category_name_ur,
        COUNT(DISTINCT inv.id) AS invoices_count,
        SUM(ii.quantity) AS total_qty_sold,
        SUM(ii.total_amount) AS total_sales_amount
    FROM invoice_items ii
    JOIN invoices inv ON inv.id = ii.invoice_id
    LEFT JOIN products p ON p.id = ii.product_id
    LEFT JOIN categories cat ON cat.id = p.category_id
    WHERE inv.business_id = ? AND inv.invoice_date BETWEEN ? AND ?
    GROUP BY cat.id, cat.name, cat.name_ur
    ORDER BY total_sales_amount DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute([$bid, $start_date, $end_date]);
$rows = $stmt->fetchAll();

$grand_sales = 0.0;
$grand_qty = 0.0;
foreach ($rows as $r) {
    $grand_sales += (float)$r['total_sales_amount'];
    $grand_qty += (float)$r['total_qty_sold'];
}
?>

<div class="page-header">
  <div>
    <h1 class="page-title">
      🏷️ <?php echo $is_rtl ? 'سیلز بلحاظ کیٹیگری رپورٹ' : 'Sales by Category Report'; ?>
    </h1>
    <p style="color:var(--text-muted); font-size:0.9rem;">
      <?php echo h($current_business['name']); ?> &bull; <?php echo format_date($start_date); ?> to <?php echo format_date($end_date); ?>
    </p>
  </div>
  <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
    <form method="GET" action="sales_by_category.php" style="display:flex; gap:8px; align-items:center;">
      <input type="date" name="start_date" class="form-control" value="<?php echo h($start_date); ?>">
      <span style="color:var(--text-muted);">&mdash;</span>
      <input type="date" name="end_date" class="form-control" value="<?php echo h($end_date); ?>">
      <button type="submit" class="btn btn-primary btn-sm">🔍 <?php echo h(__('filter')); ?></button>
    </form>
    <button onclick="window.print()" class="btn btn-outline btn-sm">🖨️ <?php echo h(__('print')); ?></button>
  </div>
</div>

<!-- Summary Metrics Cards -->
<div class="grid grid-cols-3" style="gap:15px; margin-bottom:20px;">
  <div class="card" style="padding:15px 20px; text-align:center;">
    <div style="font-size:0.85rem; color:var(--text-muted);"><?php echo $is_rtl ? 'کل سیلز ریونیو' : 'Total Revenue'; ?></div>
    <div style="font-size:1.4rem; font-weight:800; color:var(--primary); margin-top:4px;">
      <?php echo format_currency($grand_sales); ?>
    </div>
  </div>

  <div class="card" style="padding:15px 20px; text-align:center;">
    <div style="font-size:0.85rem; color:var(--text-muted);"><?php echo $is_rtl ? 'کل فروخت شدہ اشیاء / تعداد' : 'Total Units Sold'; ?></div>
    <div style="font-size:1.4rem; font-weight:800; color:var(--success); margin-top:4px;">
      <?php echo number_format($grand_qty, 0); ?>
    </div>
  </div>

  <div class="card" style="padding:15px 20px; text-align:center;">
    <div style="font-size:0.85rem; color:var(--text-muted);"><?php echo $is_rtl ? 'کیٹیگریز کی تعداد' : 'Active Categories'; ?></div>
    <div style="font-size:1.4rem; font-weight:800; color:#0f172a; margin-top:4px;">
      <?php echo count($rows); ?>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-header">
    <span>📊 <?php echo $is_rtl ? 'کیٹیگری وائز سیلز بریک ڈاؤن' : 'Category Performance Breakdown'; ?></span>
  </div>
  <div class="table-responsive">
    <table class="table">
      <thead>
        <tr>
          <th>#</th>
          <th><?php echo $is_rtl ? 'کیٹیگری کا نام' : 'Category Name'; ?></th>
          <th class="text-center"><?php echo $is_rtl ? 'بلز کی تعداد' : 'Invoices Count'; ?></th>
          <th class="text-center"><?php echo $is_rtl ? 'فروخت شدہ مقدار' : 'Units Sold'; ?></th>
          <th class="text-right"><?php echo $is_rtl ? 'کل سیلز رقم' : 'Sales Revenue'; ?></th>
          <th style="width:200px;"><?php echo $is_rtl ? 'سیلز کا تناسب' : '% Share of Sales'; ?></th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($rows)): ?>
          <tr><td colspan="6" class="text-center text-muted" style="padding:25px;"><?php echo $is_rtl ? 'اس مدت میں کوئی سیلز ریکارڈ نہیں ملا' : 'No sales records found for this period.'; ?></td></tr>
        <?php else: ?>
          <?php foreach ($rows as $idx => $r): 
            $share = ($grand_sales > 0) ? round(($r['total_sales_amount'] / $grand_sales) * 100, 1) : 0;
          ?>
            <tr>
              <td><?php echo $idx + 1; ?></td>
              <td>
                <strong><?php echo h($r['category_name']); ?></strong>
                <?php if (!empty($r['category_name_ur'])): ?>
                  <span style="color:var(--text-muted); font-size:12px; margin-left:6px;">(<?php echo h($r['category_name_ur']); ?>)</span>
                <?php endif; ?>
              </td>
              <td class="text-center"><span class="badge badge-secondary"><?php echo (int)$r['invoices_count']; ?></span></td>
              <td class="text-center font-bold"><?php echo number_format((float)$r['total_qty_sold'], 0); ?></td>
              <td class="text-right" style="font-weight:700; color:var(--primary-dark);">
                <?php echo format_currency($r['total_sales_amount']); ?>
              </td>
              <td>
                <div style="display:flex; align-items:center; gap:8px;">
                  <div style="flex:1; height:8px; background:#e2e8f0; border-radius:4px; overflow:hidden;">
                    <div style="width:<?php echo min(100, $share); ?>%; height:100%; background:var(--primary); border-radius:4px;"></div>
                  </div>
                  <span style="font-size:12px; font-weight:700; width:45px; text-align:right;"><?php echo $share; ?>%</span>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
      <?php if (!empty($rows)): ?>
        <tfoot>
          <tr style="background:#f8fafc; font-weight:800; border-top:2px solid var(--border-dark);">
            <td colspan="3"><?php echo $is_rtl ? 'کل میزان' : 'Total'; ?></td>
            <td class="text-center"><?php echo number_format($grand_qty, 0); ?></td>
            <td class="text-right" style="color:var(--primary-dark); font-size:1.05rem;"><?php echo format_currency($grand_sales); ?></td>
            <td>100.0%</td>
          </tr>
        </tfoot>
      <?php endif; ?>
    </table>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
