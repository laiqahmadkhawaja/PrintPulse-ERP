<?php
require_once __DIR__ . '/config/init.php';
require_once __DIR__ . '/includes/header.php';

$bid = (int)$current_business['id'];
$pdo = getDB();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = "Security token mismatch.";
    } else {
        $customer_name = trim($_POST['customer_name'] ?? '');
        $customer_phone = trim($_POST['customer_phone'] ?? '');
        $printer_brand = trim($_POST['printer_brand'] ?? 'HP');
        $printer_model = trim($_POST['printer_model'] ?? '');
        $serial_number = trim($_POST['serial_number'] ?? '');
        $accessories = trim($_POST['accessories_received'] ?? '');
        $fault = trim($_POST['fault_reported'] ?? '');
        $est_cost = (float)($_POST['estimated_cost'] ?? 0);
        $advance = (float)($_POST['advance_paid'] ?? 0);
        $rec_date = $_POST['received_date'] ?? date('Y-m-d');

        if (empty($customer_name) || empty($printer_model)) {
            $error = $is_rtl ? 'کسٹمر کا نام اور پرنٹر ماڈل درج کرنا ضروری ہے۔' : 'Customer name and printer model are required.';
        } else {
            // Find or insert customer
            $custStmt = $pdo->prepare("SELECT id FROM customers WHERE business_id = ? AND name = ? LIMIT 1");
            $custStmt->execute([$bid, $customer_name]);
            $customerId = $custStmt->fetchColumn();
            if (!$customerId) {
                $insCust = $pdo->prepare("INSERT INTO customers (business_id, name, phone) VALUES (?, ?, ?)");
                $insCust->execute([$bid, $customer_name, $customer_phone]);
                $customerId = $pdo->lastInsertId();
            }

            // Generate job number (e.g. REP-101)
            $lastJob = $pdo->query("SELECT MAX(id) FROM repair_jobs WHERE business_id = {$bid}")->fetchColumn() ?: 100;
            $job_number = ($current_settings['repair_prefix'] ?? 'REP-') . ($lastJob + 1);

            $stmt = $pdo->prepare("INSERT INTO repair_jobs 
                (business_id, job_number, customer_id, customer_name, customer_phone, printer_brand, printer_model, serial_number, accessories_received, fault_reported, estimated_cost, advance_paid, status, received_date) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'received', ?)");
            $stmt->execute([
                $bid,
                $job_number,
                $customerId,
                $customer_name,
                $customer_phone,
                $printer_brand,
                $printer_model,
                $serial_number,
                $accessories,
                $fault,
                $est_cost,
                $advance,
                $rec_date
            ]);
            $jobId = $pdo->lastInsertId();
            log_audit('CREATE_REPAIR_JOB', 'repair_job', $jobId, "Job {$job_number} for {$customer_name}");

            header("Location: repair_job_view.php?id={$jobId}");
            exit;
        }
    }
}
?>

<div class="page-header">
  <div>
    <h1 class="page-title">
      📋 <?php echo $is_rtl ? 'نیا پرنٹر جاب کارڈ بنائیں' : 'Create New Repair Job Card'; ?>
    </h1>
  </div>
  <a href="repair_jobs.php" class="btn btn-outline">&larr; <?php echo h(__('cancel')); ?></a>
</div>

<?php if (!empty($error)): ?>
  <div class="alert alert-danger"><?php echo h($error); ?></div>
<?php endif; ?>

<form method="POST" action="repair_job_create.php">
  <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">

  <div class="card">
    <div class="card-header">
      <span>👤 <?php echo $is_rtl ? 'کسٹمر و تاریخ' : 'Customer & Date'; ?></span>
    </div>
    <div class="card-body">
      <div class="grid grid-cols-3">
        <div class="form-group">
          <label class="form-label"><?php echo h(__('customer_name')); ?> *</label>
          <input type="text" name="customer_name" class="form-control" required placeholder="Customer or firm name">
        </div>
        <div class="form-group">
          <label class="form-label"><?php echo h(__('phone')); ?> / <?php echo h(__('cell')); ?></label>
          <input type="text" name="customer_phone" class="form-control" placeholder="0300-1234567">
        </div>
        <div class="form-group">
          <label class="form-label"><?php echo h(__('received_date')); ?> *</label>
          <input type="date" name="received_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
        </div>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-header">
      <span>🖨️ <?php echo $is_rtl ? 'پرنٹر تفصیلات اور مسئلہ' : 'Equipment Details & Problem Description'; ?></span>
    </div>
    <div class="card-body">
      <div class="grid grid-cols-3">
        <div class="form-group">
          <label class="form-label"><?php echo $is_rtl ? 'پرنٹر برانڈ (Brand)' : 'Brand'; ?></label>
          <input type="text" name="printer_brand" class="form-control" value="HP" placeholder="HP, Canon, Epson, Brother...">
        </div>
        <div class="form-group">
          <label class="form-label"><?php echo h(__('printer_model')); ?> *</label>
          <input type="text" name="printer_model" class="form-control" required placeholder="e.g. LaserJet Pro M404dn">
        </div>
        <div class="form-group">
          <label class="form-label"><?php echo h(__('serial_number')); ?></label>
          <input type="text" name="serial_number" class="form-control" placeholder="Serial / Service Tag">
        </div>
      </div>

      <div class="grid grid-cols-2">
        <div class="form-group">
          <label class="form-label"><?php echo h(__('accessories')); ?></label>
          <input type="text" name="accessories_received" class="form-control" placeholder="Power Cable, USB Cable, Cartridge Inside...">
        </div>
        <div class="form-group">
          <label class="form-label"><?php echo h(__('fault_reported')); ?></label>
          <textarea name="fault_reported" class="form-control" rows="2" placeholder="Paper jam, toner blur, not turning on, error 50.4..."></textarea>
        </div>
      </div>

      <div class="grid grid-cols-2">
        <div class="form-group">
          <label class="form-label"><?php echo h(__('estimated_cost')); ?> (Rs)</label>
          <input type="number" step="0.01" name="estimated_cost" class="form-control" value="1500.00">
        </div>
        <div class="form-group">
          <label class="form-label"><?php echo h(__('advance_paid')); ?> (Rs)</label>
          <input type="number" step="0.01" name="advance_paid" class="form-control" value="0.00">
        </div>
      </div>

      <button type="submit" class="btn btn-primary" style="margin-top:10px;">
        💾 <?php echo $is_rtl ? 'جاب کارڈ محفوظ کریں' : 'Save Job Card'; ?>
      </button>
    </div>
  </div>
</form>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
