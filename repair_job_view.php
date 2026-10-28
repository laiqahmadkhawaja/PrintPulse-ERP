<?php
require_once __DIR__ . '/config/init.php';

$job_id = (int)($_GET['id'] ?? 0);
$bid = (int)$current_business['id'];
$pdo = getDB();

$stmt = $pdo->prepare("SELECT * FROM repair_jobs WHERE id = ? AND business_id = ?");
$stmt->execute([$job_id, $bid]);
$job = $stmt->fetch();

if (!$job) {
    die("Repair job #{$job_id} not found or unauthorized.");
}

// Handle Status & Diagnosis Update
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $status = $_POST['status'] ?? $job['status'];
        $tech_notes = trim($_POST['technician_notes'] ?? '');
        $final_cost = (float)($_POST['final_cost'] ?? $job['final_cost']);

        $upd = $pdo->prepare("UPDATE repair_jobs SET status = ?, technician_notes = ?, final_cost = ? WHERE id = ? AND business_id = ?");
        $upd->execute([$status, $tech_notes, $final_cost, $job_id, $bid]);
        $msg = $is_rtl ? 'جاب کارڈ کی تفصیلات کامیابی سے اپ ڈیٹ ہو گئیں۔' : 'Job card updated successfully.';

        // Reload job
        $stmt->execute([$job_id, $bid]);
        $job = $stmt->fetch();
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
  <div>
    <h1 class="page-title">
      📋 <?php echo h(__('job_number')); ?>: <?php echo h($job['job_number']); ?>
    </h1>
    <p style="color:var(--text-muted); font-size:0.9rem;">
      <?php echo h($job['printer_brand'] . ' ' . $job['printer_model']); ?> &bull; <?php echo h($job['customer_name']); ?>
    </p>
  </div>

  <div style="display:flex; gap:10px;">
    <a href="repair_jobs.php" class="btn btn-outline">&larr; <?php echo $is_rtl ? 'جابز فہرست' : 'All Jobs'; ?></a>
    <?php if ($job['status'] !== 'delivered'): ?>
      <a href="repair_invoice_create.php?job_id=<?php echo $job['id']; ?>" class="btn btn-success">
        ⚡ <?php echo $is_rtl ? 'فائنل بل بنائیں اور پرنٹ کریں' : 'Generate & Print Invoice'; ?> &rarr;
      </a>
    <?php endif; ?>
  </div>
</div>

<?php if (!empty($msg)): ?>
  <div class="alert alert-success"><?php echo h($msg); ?></div>
<?php endif; ?>

<div class="grid grid-cols-2">
  <div class="card">
    <div class="card-header">
      <span>🖨️ <?php echo $is_rtl ? 'پرنٹر و کسٹمر تفصیلات' : 'Equipment & Customer Details'; ?></span>
      <span class="badge badge-primary"><?php echo h(__('status_' . $job['status'], $job['status'])); ?></span>
    </div>
    <div class="card-body">
      <table class="table">
        <tr>
          <th style="width:160px;"><?php echo h(__('customer_name')); ?></th>
          <td><strong><?php echo h($job['customer_name']); ?></strong></td>
        </tr>
        <tr>
          <th><?php echo h(__('phone')); ?></th>
          <td><?php echo h($job['customer_phone'] ?: '-'); ?></td>
        </tr>
        <tr>
          <th><?php echo h(__('printer_model')); ?></th>
          <td><strong><?php echo h($job['printer_brand'] . ' ' . $job['printer_model']); ?></strong></td>
        </tr>
        <tr>
          <th><?php echo h(__('serial_number')); ?></th>
          <td><code><?php echo h($job['serial_number'] ?: 'N/A'); ?></code></td>
        </tr>
        <tr>
          <th><?php echo h(__('accessories')); ?></th>
          <td><?php echo h($job['accessories_received'] ?: 'None'); ?></td>
        </tr>
        <tr>
          <th><?php echo h(__('fault_reported')); ?></th>
          <td><?php echo nl2br(h($job['fault_reported'])); ?></td>
        </tr>
        <tr>
          <th><?php echo h(__('received_date')); ?></th>
          <td><?php echo format_date($job['received_date']); ?></td>
        </tr>
        <tr>
          <th><?php echo h(__('advance_paid')); ?></th>
          <td><?php echo format_currency($job['advance_paid']); ?></td>
        </tr>
      </table>
    </div>
  </div>

  <div class="card">
    <div class="card-header">
      <span>🛠️ <?php echo $is_rtl ? 'ٹیکنیشن معائنہ و حالت اپ ڈیٹ' : 'Technician Diagnosis & Status Update'; ?></span>
    </div>
    <div class="card-body">
      <form method="POST" action="repair_job_view.php?id=<?php echo $job['id']; ?>">
        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">

        <div class="form-group">
          <label class="form-label"><?php echo h(__('status')); ?></label>
          <select name="status" class="form-control">
            <option value="received" <?php echo ($job['status']==='received')?'selected':''; ?>><?php echo h(__('status_received')); ?></option>
            <option value="diagnosing" <?php echo ($job['status']==='diagnosing')?'selected':''; ?>><?php echo h(__('status_diagnosing')); ?></option>
            <option value="waiting_parts" <?php echo ($job['status']==='waiting_parts')?'selected':''; ?>><?php echo h(__('status_waiting_parts')); ?></option>
            <option value="repaired" <?php echo ($job['status']==='repaired')?'selected':''; ?>><?php echo h(__('status_repaired')); ?></option>
            <option value="delivered" <?php echo ($job['status']==='delivered')?'selected':''; ?>><?php echo h(__('status_delivered')); ?></option>
            <option value="cancelled" <?php echo ($job['status']==='cancelled')?'selected':''; ?>><?php echo h(__('status_cancelled')); ?></option>
          </select>
        </div>

        <div class="form-group">
          <label class="form-label"><?php echo h(__('technician_notes')); ?></label>
          <textarea name="technician_notes" class="form-control" rows="4" placeholder="<?php echo $is_rtl ? 'ٹیکنیشن معائنہ نوٹ...' : 'Details of diagnosed issues, required parts, repairs done...'; ?>"><?php echo h($job['technician_notes']); ?></textarea>
        </div>

        <div class="form-group">
          <label class="form-label"><?php echo $is_rtl ? 'فائنل لاگت (Final Cost)' : 'Final Cost'; ?> (Rs)</label>
          <input type="number" step="0.01" name="final_cost" class="form-control" value="<?php echo $job['final_cost'] > 0 ? $job['final_cost'] : $job['estimated_cost']; ?>">
        </div>

        <button type="submit" class="btn btn-primary" style="margin-top:10px;">
          🔄 <?php echo $is_rtl ? 'اسٹیٹس اپ ڈیٹ کریں' : 'Update Status & Notes'; ?>
        </button>
      </form>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
