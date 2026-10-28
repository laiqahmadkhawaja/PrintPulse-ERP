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
        $cartridge_model = trim($_POST['cartridge_model'] ?? '');
        $cartridge_type = $_POST['cartridge_type'] ?? 'toner';
        $service_type = $_POST['service_type'] ?? 'Refill';
        $charges = (float)($_POST['charges'] ?? 0);
        $paid = (float)($_POST['paid_amount'] ?? 0);
        $test_status = $_POST['test_print_status'] ?? 'passed';

        if (empty($customer_name) || empty($cartridge_model)) {
            $error = $is_rtl ? 'گاہک کا نام اور کارٹریج ماڈل درج کریں۔' : 'Customer name and cartridge model are required.';
        } else {
            // Find or insert customer
            $custStmt = $pdo->prepare("SELECT id FROM customers WHERE business_id = ? AND name = ? LIMIT 1");
            $custStmt->execute([$bid, $customer_name]);
            $customerId = $custStmt->fetchColumn();
            if (!$customerId) {
                $ins = $pdo->prepare("INSERT INTO customers (business_id, name, phone) VALUES (?, ?, ?)");
                $ins->execute([$bid, $customer_name, $customer_phone]);
                $customerId = $pdo->lastInsertId();
            }

            $lastJob = $pdo->query("SELECT MAX(id) FROM refill_jobs WHERE business_id = {$bid}")->fetchColumn() ?: 100;
            $job_number = ($current_settings['refill_prefix'] ?? 'REF-') . ($lastJob + 1);

            $stmt = $pdo->prepare("INSERT INTO refill_jobs 
                (business_id, job_number, customer_id, customer_name, customer_phone, cartridge_model, cartridge_type, service_type, test_print_status, charges, paid_amount, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'ready')");
            $stmt->execute([
                $bid,
                $job_number,
                $customerId,
                $customer_name,
                $customer_phone,
                $cartridge_model,
                $cartridge_type,
                $service_type,
                $test_status,
                $charges,
                $paid
            ]);

            log_audit('CREATE_REFILL_JOB', 'refill_job', $pdo->lastInsertId(), "Created {$job_number} for {$customer_name}");
            header("Location: refill_jobs.php");
            exit;
        }
    }
}
?>

<div class="page-header">
  <div>
    <h1 class="page-title">
      🧪 <?php echo $is_rtl ? 'نیا کارٹریج ریفلنگ جاب کارڈ' : 'New Refill Job Card'; ?>
    </h1>
  </div>
  <a href="refill_jobs.php" class="btn btn-outline">&larr; <?php echo h(__('cancel')); ?></a>
</div>

<?php if (!empty($error)): ?>
  <div class="alert alert-danger"><?php echo h($error); ?></div>
<?php endif; ?>

<form method="POST" action="refill_job_create.php">
  <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">

  <div class="card">
    <div class="card-header">
      <span>👤 <?php echo $is_rtl ? 'کسٹمر و کارٹریج کی تفصیلات' : 'Customer & Cartridge Details'; ?></span>
    </div>
    <div class="card-body">
      <div class="grid grid-cols-3">
        <div class="form-group">
          <label class="form-label"><?php echo h(__('customer_name')); ?> *</label>
          <input type="text" name="customer_name" class="form-control" required placeholder="Customer or firm name">
        </div>
        <div class="form-group">
          <label class="form-label"><?php echo h(__('phone')); ?></label>
          <input type="text" name="customer_phone" class="form-control" placeholder="0300-1234567">
        </div>
        <div class="form-group">
          <label class="form-label"><?php echo h(__('cartridge_model')); ?> *</label>
          <input type="text" name="cartridge_model" class="form-control" required placeholder="e.g. HP 85A / Canon 303 / HP 81X">
        </div>
      </div>

      <div class="grid grid-cols-3">
        <div class="form-group">
          <label class="form-label"><?php echo $is_rtl ? 'کارٹریج کی قسم' : 'Type'; ?></label>
          <select name="cartridge_type" class="form-control">
            <option value="toner">Laser Toner Cartridge</option>
            <option value="ink">Inkjet Cartridge / Tank</option>
            <option value="ribbon">Dot Matrix Ribbon</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label"><?php echo h(__('service_type')); ?></label>
          <input type="text" name="service_type" class="form-control" value="Refill & Drum Change">
        </div>
        <div class="form-group">
          <label class="form-label"><?php echo $is_rtl ? 'ٹیسٹ پرنٹ کوالٹی' : 'Test Print Result'; ?></label>
          <select name="test_print_status" class="form-control">
            <option value="passed">Passed (100% Crisp Black)</option>
            <option value="pending">Pending Testing</option>
            <option value="poor">Poor (Drum/Blade worn out)</option>
          </select>
        </div>
      </div>

      <div class="grid grid-cols-2">
        <div class="form-group">
          <label class="form-label"><?php echo $is_rtl ? 'ریفلنگ چارجز' : 'Charges'; ?> (Rs)</label>
          <input type="number" step="0.01" name="charges" class="form-control" value="1200.00">
        </div>
        <div class="form-group">
          <label class="form-label"><?php echo h(__('paid')); ?> (Rs)</label>
          <input type="number" step="0.01" name="paid_amount" class="form-control" value="0.00">
        </div>
      </div>

      <button type="submit" class="btn btn-primary" style="margin-top:10px;">
        💾 <?php echo $is_rtl ? 'جاب کارڈ محفوظ کریں' : 'Save Refill Job Card'; ?>
      </button>
    </div>
  </div>
</form>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
