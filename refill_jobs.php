<?php
require_once __DIR__ . '/config/init.php';
require_once __DIR__ . '/includes/header.php';

$bid = (int)$current_business['id'];
$pdo = getDB();
$refillJobEnabled = !empty($current_settings['refilling_job_management_enabled']);

$search = trim($_GET['search'] ?? '');
$sql = "SELECT * FROM refill_jobs WHERE business_id = ?";
$params = [$bid];

if ($search !== '') {
    $sql .= " AND (job_number LIKE ? OR customer_name LIKE ? OR cartridge_model LIKE ?)";
    $like = "%{$search}%";
    $params[] = $like; $params[] = $like; $params[] = $like;
}

$sql .= " ORDER BY id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$jobs = $stmt->fetchAll();
?>

<div class="page-header">
  <div>
    <h1 class="page-title">
      🧪 <?php echo h(__('nav_refill_jobs')); ?>
    </h1>
    <p style="color:var(--text-muted); font-size:0.9rem;">
      <?php echo $is_rtl ? 'ٹونر اور انک کارٹریج ریفلنگ جاب کارڈز' : 'Track refilling progress, test page print status, and pickup delivery'; ?>
    </p>
  </div>
  <div style="display:flex; gap:10px;">
    <a href="refill_invoice_create.php" class="btn btn-outline">⚡ <?php echo $is_rtl ? 'براہ راست بل' : 'Direct Refill Bill'; ?></a>
    <?php if ($refillJobEnabled): ?>
      <a href="refill_job_create.php" class="btn btn-primary">➕ <?php echo $is_rtl ? 'نیا ریفل جاب کارڈ' : 'New Refill Job'; ?></a>
    <?php endif; ?>
  </div>
</div>

<?php if (!$refillJobEnabled): ?>
  <div class="alert alert-info">
    ℹ️ <?php echo $is_rtl 
      ? 'اس بزنس میں "ریفلنگ جاب کارڈ ٹریکر" بند ہے، آپ براہ راست کسی بھی وقت ریفلنگ انوائس بنا سکتے ہیں۔' 
      : 'Refilling job workflow is currently toggled OFF for this business. You can issue direct refill invoices anytime without job tracking.'; ?>
  </div>
<?php endif; ?>

<div class="card">
  <div class="table-responsive">
    <table class="table">
      <thead>
        <tr>
          <th><?php echo h(__('job_number')); ?></th>
          <th><?php echo h(__('customer_name')); ?></th>
          <th><?php echo h(__('cartridge_model')); ?></th>
          <th><?php echo h(__('service_type')); ?></th>
          <th><?php echo $is_rtl ? 'ٹیسٹ پرنٹ' : 'Test Print'; ?></th>
          <th><?php echo h(__('status')); ?></th>
          <th class="text-right"><?php echo h(__('amount')); ?></th>
          <th class="text-center"><?php echo h(__('action')); ?></th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($jobs)): ?>
          <tr><td colspan="8" class="text-center text-muted" style="padding:25px;"><?php echo $is_rtl ? 'کوئی جاب ریکارڈ نہیں ملا' : 'No refill jobs found.'; ?></td></tr>
        <?php else: ?>
          <?php foreach ($jobs as $job): ?>
            <tr>
              <td><strong><?php echo h($job['job_number']); ?></strong></td>
              <td><?php echo h($job['customer_name']); ?></td>
              <td><strong><?php echo h($job['cartridge_model']); ?></strong></td>
              <td><?php echo h($job['service_type']); ?></td>
              <td>
                <span class="badge <?php echo ($job['test_print_status']==='passed') ? 'badge-success' : 'badge-warning'; ?>">
                  <?php echo h($job['test_print_status']); ?>
                </span>
              </td>
              <td>
                <span class="badge badge-primary"><?php echo h($job['status']); ?></span>
              </td>
              <td class="text-right"><?php echo format_currency($job['charges']); ?></td>
              <td class="text-center">
                <a href="refill_invoice_create.php" class="btn btn-outline btn-sm">
                  ⚡ <?php echo $is_rtl ? 'بل بنائیں' : 'Invoice'; ?>
                </a>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
