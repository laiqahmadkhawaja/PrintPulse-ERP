<?php
require_once __DIR__ . '/../config/init.php';
require_once __DIR__ . '/../includes/header.php';

$bid = (int)$current_business['id'];
$pdo = getDB();

$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-d');
$inv_type = $_GET['invoice_type'] ?? '';

$sql = "SELECT * FROM invoices WHERE business_id = ? AND invoice_date BETWEEN ? AND ?";
$params = [$bid, $start_date, $end_date];

if ($inv_type !== '') {
    $sql .= " AND invoice_type = ?";
    $params[] = $inv_type;
}

$sql .= " ORDER BY invoice_date DESC, id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$tot_amount = 0;
$tot_paid = 0;
$tot_balance = 0;
foreach ($rows as $r) {
    $tot_amount += $r['total_amount'];
    $tot_paid += $r['paid_amount'];
    $tot_balance += $r['balance_amount'];
}
?>

<div class="page-header">
  <div>
    <h1 class="page-title">
      📊 <?php echo h(__('nav_sales_report')); ?> &mdash; <?php echo $is_rtl ? 'سیلز و ریونیو رپورٹ' : 'Sales & Revenue Summary'; ?>
    </h1>
    <p style="color:var(--text-muted); font-size:0.9rem;">
      <?php echo h($current_business['name']); ?> &bull; <?php echo format_date($start_date); ?> to <?php echo format_date($end_date); ?>
    </p>
  </div>
  <div>
    <form method="GET" action="sales_report.php" style="display:flex; gap:10px; flex-wrap:wrap;">
      <select name="invoice_type" class="form-control">
        <option value="">All Types (تمام)</option>
        <option value="repair" <?php echo ($inv_type==='repair')?'selected':''; ?>>Repair Invoices</option>
        <option value="refill" <?php echo ($inv_type==='refill')?'selected':''; ?>>Refill Invoices</option>
        <option value="sale" <?php echo ($inv_type==='sale')?'selected':''; ?>>Product Sales</option>
        <option value="pos" <?php echo ($inv_type==='pos')?'selected':''; ?>>Counter POS</option>
      </select>
      <input type="date" name="start_date" class="form-control" value="<?php echo h($start_date); ?>">
      <input type="date" name="end_date" class="form-control" value="<?php echo h($end_date); ?>">
      <button type="submit" class="btn btn-outline btn-sm">🔍 <?php echo h(__('filter')); ?></button>
    </form>
  </div>
</div>

<div class="card" style="margin-bottom:1.5rem;">
  <div class="card-body" style="display:flex; justify-content:space-around; align-items:center;">
    <div style="text-align:center;">
      <div style="font-size:0.85rem; color:#64748b;">Invoices Count</div>
      <div style="font-size:1.3rem; font-weight:700;"><?php echo count($rows); ?></div>
    </div>
    <div style="text-align:center;">
      <div style="font-size:0.85rem; color:#64748b;">Total Revenue</div>
      <div style="font-size:1.4rem; font-weight:800; color:var(--primary);"><?php echo format_currency($tot_amount); ?></div>
    </div>
    <div style="text-align:center;">
      <div style="font-size:0.85rem; color:#64748b;">Total Collected</div>
      <div style="font-size:1.4rem; font-weight:800; color:var(--success);"><?php echo format_currency($tot_paid); ?></div>
    </div>
    <div style="text-align:center;">
      <div style="font-size:0.85rem; color:#64748b;">Uncollected Receivables</div>
      <div style="font-size:1.4rem; font-weight:800; color:var(--danger);"><?php echo format_currency($tot_balance); ?></div>
    </div>
  </div>
</div>

<div class="card">
  <div class="table-responsive">
    <table class="table">
      <thead>
        <tr>
          <th>Invoice #</th>
          <th>Date</th>
          <th>Customer</th>
          <th>Type</th>
          <th class="text-right">Total Amount</th>
          <th class="text-right">Deposit / Paid</th>
          <th class="text-right">Balance</th>
          <th class="text-center">Action</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td><strong><?php echo h($r['invoice_number']); ?></strong></td>
            <td><?php echo format_date($r['invoice_date']); ?></td>
            <td><?php echo h($r['customer_name']); ?></td>
            <td><span class="badge badge-primary"><?php echo strtoupper($r['invoice_type']); ?></span></td>
            <td class="text-right"><strong><?php echo format_currency($r['total_amount']); ?></strong></td>
            <td class="text-right"><?php echo format_currency($r['paid_amount']); ?></td>
            <td class="text-right"><?php echo format_currency($r['balance_amount']); ?></td>
            <td class="text-center">
              <a href="<?php echo $base_url; ?>print_invoice.php?id=<?php echo $r['id']; ?>" target="_blank" class="btn btn-outline btn-sm">
                🖨️
              </a>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
