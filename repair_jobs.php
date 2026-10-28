<?php
require_once __DIR__ . '/config/init.php';
require_once __DIR__ . '/includes/header.php';

$bid = (int)$current_business['id'];
$pdo = getDB();

// If job management is disabled for this business, show informational notice
$jobEnabled = !empty($current_settings['repair_job_management_enabled']);

$statusFilter = $_GET['status'] ?? '';
$search = trim($_GET['search'] ?? '');

$sql = "SELECT * FROM repair_jobs WHERE business_id = ?";
$params = [$bid];

if ($statusFilter !== '') {
    $sql .= " AND status = ?";
    $params[] = $statusFilter;
}

if ($search !== '') {
    $sql .= " AND (job_number LIKE ? OR customer_name LIKE ? OR customer_phone LIKE ? OR printer_model LIKE ? OR serial_number LIKE ?)";
    $like = "%{$search}%";
    for ($i=0; $i<5; $i++) $params[] = $like;
}

$sql .= " ORDER BY id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$jobs = $stmt->fetchAll();
?>

<div class="page-header">
  <div>
    <h1 class="page-title">
      📋 <?php echo h(__('nav_repair_jobs')); ?>
    </h1>
    <p style="color:var(--text-muted); font-size:0.9rem;">
      <?php echo $is_rtl ? 'پرنٹر مرمت کے جاب کارڈز اور ٹریکنگ' : 'Manage equipment repair cards, inspection statuses, and technician notes'; ?>
    </p>
  </div>
  <div style="display:flex; gap:10px;">
    <a href="repair_invoice_create.php" class="btn btn-outline">⚡ <?php echo $is_rtl ? 'براہ راست مرمت بل' : 'Direct Repair Invoice'; ?></a>
    <?php if ($jobEnabled): ?>
      <a href="repair_job_create.php" class="btn btn-primary">➕ <?php echo $is_rtl ? 'نیا جاب کارڈ' : 'New Job Card'; ?></a>
    <?php endif; ?>
  </div>
</div>

<?php if (!$jobEnabled): ?>
  <div class="alert alert-info">
    ℹ️ <?php echo $is_rtl 
      ? 'اس بزنس میں "جاب کارڈ سسٹم" بند ہے مگر آپ براہ راست کسی بھی وقت مرمت کا بل جاری کر سکتے ہیں۔ آپ بزنس سیٹنگز میں جا کر اسے دوبارہ آن کر سکتے ہیں۔' 
      : 'Job card management workflow is toggled OFF for this business. You can still issue direct repair invoices anytime. You may enable job cards in Business Settings.'; ?>
  </div>
<?php endif; ?>

<div class="card">
  <div class="card-header">
    <form method="GET" action="repair_jobs.php" style="display:flex; gap:10px; width:100%; flex-wrap:wrap;">
      <input type="text" name="search" class="form-control" style="max-width:280px;" placeholder="<?php echo $is_rtl ? 'جاب نمبر، کسٹمر، پرنٹر ماڈل...' : 'Search job #, customer, model...'; ?>" value="<?php echo h($search); ?>">
      
      <select name="status" class="form-control" style="max-width:180px;">
        <option value=""><?php echo h(__('all')); ?> Statuses</option>
        <option value="received" <?php echo ($statusFilter==='received') ? 'selected':''; ?>><?php echo h(__('status_received')); ?></option>
        <option value="diagnosing" <?php echo ($statusFilter==='diagnosing') ? 'selected':''; ?>><?php echo h(__('status_diagnosing')); ?></option>
        <option value="waiting_parts" <?php echo ($statusFilter==='waiting_parts') ? 'selected':''; ?>><?php echo h(__('status_waiting_parts')); ?></option>
        <option value="repaired" <?php echo ($statusFilter==='repaired') ? 'selected':''; ?>><?php echo h(__('status_repaired')); ?></option>
        <option value="delivered" <?php echo ($statusFilter==='delivered') ? 'selected':''; ?>><?php echo h(__('status_delivered')); ?></option>
      </select>

      <button type="submit" class="btn btn-outline btn-sm">🔍 <?php echo h(__('filter')); ?></button>
      <?php if ($search || $statusFilter): ?>
        <a href="repair_jobs.php" class="btn btn-outline btn-sm"><?php echo h(__('reset')); ?></a>
      <?php endif; ?>
    </form>
  </div>

  <div class="table-responsive">
    <table class="table">
      <thead>
        <tr>
          <th><?php echo h(__('job_number')); ?></th>
          <th><?php echo h(__('received_date')); ?></th>
          <th><?php echo h(__('customer_name')); ?></th>
          <th><?php echo h(__('phone')); ?></th>
          <th><?php echo h(__('printer_model')); ?></th>
          <th><?php echo h(__('status')); ?></th>
          <th class="text-right"><?php echo h(__('estimated_cost')); ?></th>
          <th class="text-center"><?php echo h(__('actions')); ?></th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($jobs)): ?>
          <tr><td colspan="8" class="text-center text-muted" style="padding:25px;"><?php echo $is_rtl ? 'کوئی جاب کارڈ موجود نہیں' : 'No repair job cards found.'; ?></td></tr>
        <?php else: ?>
          <?php foreach ($jobs as $job): ?>
            <tr>
              <td><strong><?php echo h($job['job_number']); ?></strong></td>
              <td><?php echo format_date($job['received_date']); ?></td>
              <td><?php echo h($job['customer_name']); ?></td>
              <td><?php echo h($job['customer_phone']); ?></td>
              <td><?php echo h($job['printer_brand'] . ' ' . $job['printer_model']); ?></td>
              <td>
                <?php 
                  $cls = 'badge-primary';
                  if ($job['status'] === 'diagnosing') $cls = 'badge-warning';
                  if ($job['status'] === 'waiting_parts') $cls = 'badge-danger';
                  if ($job['status'] === 'repaired') $cls = 'badge-success';
                  if ($job['status'] === 'delivered') $cls = 'badge-secondary';
                ?>
                <span class="badge <?php echo $cls; ?>"><?php echo h(__('status_' . $job['status'], $job['status'])); ?></span>
              </td>
              <td class="text-right"><?php echo format_currency($job['estimated_cost']); ?></td>
              <td class="text-center">
                <a href="repair_job_view.php?id=<?php echo $job['id']; ?>" class="btn btn-outline btn-sm">
                  👁️ <?php echo h(__('view')); ?>
                </a>
                <?php if ($job['status'] !== 'delivered'): ?>
                  <a href="repair_invoice_create.php?job_id=<?php echo $job['id']; ?>" class="btn btn-success btn-sm">
                    ⚡ <?php echo $is_rtl ? 'بل بنائیں' : 'Create Bill'; ?>
                  </a>
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
