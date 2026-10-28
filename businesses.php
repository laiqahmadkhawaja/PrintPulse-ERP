<?php
require_once __DIR__ . '/config/init.php';

// Strict Platform Admin / SaaS Owner authorization
if (($current_user['role'] ?? '') !== 'platform_admin') {
    $_SESSION['flash_error'] = $is_rtl 
        ? 'ملٹی بزنس مینجمنٹ کی سہولت صرف ساس (SaaS) ایڈمنسٹریٹر کے لیے ہے۔' 
        : 'Multi-business registration and management is restricted to SaaS Platform Administrators.';
    header("Location: business_settings.php");
    exit;
}

require_once __DIR__ . '/includes/header.php';

$pdo = getDB();
$error = '';
$msg = '';

// Add New Business
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_business') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = "Security token mismatch.";
    } else {
        $name = trim($_POST['name'] ?? '');
        $city = trim($_POST['city'] ?? 'Gujranwala');
        $address = trim($_POST['address'] ?? '');
        $cell = trim($_POST['cell'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $currency = trim($_POST['currency'] ?? 'Rs');
        $format = $_POST['default_invoice_format'] ?? 'A5';

        if (empty($name)) {
            $error = $is_rtl ? 'بزنس کا نام درج کریں۔' : 'Business name is required.';
        } else {
            $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $name)) . '-' . rand(100, 999);

            $pdo->beginTransaction();
            try {
                $stmt = $pdo->prepare("INSERT INTO businesses (name, slug, city, address, phone, cell, email, currency, default_invoice_format, watermark_enabled, watermark_text, invoice_notes) 
                                       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, 'RECONDITIONED OR REPAIRED GOODS ONCE SOLD ARE NOT RETURNABLE NOR EXCHANGEABLE.')");
                $stmt->execute([$name, $slug, $city, $address, $phone, $cell, $email, $currency, $format, $name]);
                $newBid = $pdo->lastInsertId();

                // Settings
                $pdo->prepare("INSERT INTO business_settings (business_id, repair_job_management_enabled, refilling_job_management_enabled, inventory_enabled, barcode_enabled, serial_tracking_enabled, daily_closing_enabled) 
                               VALUES (?, 1, 1, 1, 1, 1, 1)")->execute([$newBid]);

                // Associate user with business
                $pdo->prepare("INSERT INTO user_businesses (user_id, business_id, role_in_business) VALUES (?, ?, 'owner')")
                    ->execute([$current_user['id'], $newBid]);

                // Seed standard CoA
                require_once __DIR__ . '/install.php';
                if (function_exists('seedCoA')) {
                    seedCoA($pdo, $newBid);
                    seedServiceDescriptions($pdo, $newBid);
                    seedExpenseCats($pdo, $newBid);
                }

                $pdo->commit();
                log_audit('CREATE_BUSINESS', 'business', $newBid, "Created business {$name}");
                $msg = $is_rtl ? "نیا بزنس {$name} کامیابی سے رجسٹر ہو گیا۔" : "Business {$name} registered successfully.";

            } catch (Exception $e) {
                $pdo->rollBack();
                $error = "Failed to create business: " . $e->getMessage();
            }
        }
    }
}

// Fetch all businesses accessible to current user
$businesses = $user_businesses;
?>

<div class="page-header">
  <div>
    <h1 class="page-title">
      🏢 <?php echo h(__('nav_businesses')); ?> &mdash; <?php echo $is_rtl ? 'ملٹی بزنس منیجمنٹ' : 'Multi-Business Management'; ?>
    </h1>
    <p style="color:var(--text-muted); font-size:0.9rem;">
      <?php echo $is_rtl ? 'ایک ہی ڈیٹا بیس پر مکمل آزادانہ بزنسز کا انتظام اور تبدیلی' : 'Independent tenant businesses sharing single application with strict data isolation'; ?>
    </p>
  </div>
</div>

<?php if (!empty($error)): ?>
  <div class="alert alert-danger"><?php echo h($error); ?></div>
<?php endif; ?>
<?php if (!empty($msg)): ?>
  <div class="alert alert-success"><?php echo h($msg); ?></div>
<?php endif; ?>

<div class="grid grid-cols-3">
  <!-- Register New Business Form -->
  <div class="card">
    <div class="card-header">
      <span>➕ <?php echo $is_rtl ? 'نیا بزنس رجسٹر کریں' : 'Register New Business'; ?></span>
    </div>
    <div class="card-body">
      <form method="POST" action="businesses.php">
        <input type="hidden" name="action" value="create_business">
        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">

        <div class="form-group">
          <label class="form-label"><?php echo $is_rtl ? 'بزنس کا نام' : 'Business Name'; ?> *</label>
          <input type="text" name="name" class="form-control" required placeholder="e.g. Royal Printer Solutions">
        </div>
        <div class="form-group">
          <label class="form-label"><?php echo $is_rtl ? 'شہر' : 'City'; ?></label>
          <input type="text" name="city" class="form-control" value="Gujranwala">
        </div>
        <div class="form-group">
          <label class="form-label"><?php echo h(__('address')); ?></label>
          <input type="text" name="address" class="form-control" placeholder="Shop #, Plaza, Market">
        </div>
        <div class="form-group">
          <label class="form-label"><?php echo h(__('cell')); ?> / <?php echo h(__('phone')); ?></label>
          <input type="text" name="cell" class="form-control" placeholder="0300-1234567">
        </div>
        <div class="form-group">
          <label class="form-label"><?php echo h(__('email')); ?></label>
          <input type="email" name="email" class="form-control" placeholder="info@business.pk">
        </div>
        <div class="form-group">
          <label class="form-label"><?php echo $is_rtl ? 'کرنسی سمبل' : 'Currency'; ?></label>
          <input type="text" name="currency" class="form-control" value="Rs">
        </div>
        <div class="form-group">
          <label class="form-label"><?php echo $is_rtl ? 'بل سائز' : 'Default Bill Format'; ?></label>
          <select name="default_invoice_format" class="form-control">
            <option value="A5">A5 (Standard Bill)</option>
            <option value="A4">A4 (Full Page)</option>
            <option value="80mm">80mm Thermal</option>
          </select>
        </div>

        <button type="submit" class="btn btn-primary" style="width:100%; padding:10px; font-weight:700;">
          💾 <?php echo $is_rtl ? 'نیا بزنس محفوظ کریں' : 'Register Business'; ?>
        </button>
      </form>
    </div>
  </div>

  <!-- Accessible Businesses List -->
  <div class="card" style="grid-column: span 2;">
    <div class="card-header">
      <span>🏢 <?php echo $is_rtl ? 'آپ کے مجاز بزنسز' : 'Your Accessible Businesses'; ?></span>
    </div>
    <div class="table-responsive">
      <table class="table">
        <thead>
          <tr>
            <th>Business Name</th>
            <th>City</th>
            <th>Cell / Phone</th>
            <th>Default Format</th>
            <th>Status</th>
            <th class="text-center">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($businesses as $b): ?>
            <tr>
              <td>
                <strong><?php echo h($b['name']); ?></strong>
                <?php if ($b['id'] == $current_business['id']): ?>
                  <span class="badge badge-success" style="margin-left:6px;">Active ✓</span>
                <?php endif; ?>
              </td>
              <td><?php echo h($b['city']); ?></td>
              <td><?php echo h($b['cell'] ?: $b['phone']); ?></td>
              <td><code><?php echo h($b['default_invoice_format']); ?></code></td>
              <td><span class="badge badge-primary"><?php echo h($b['status']); ?></span></td>
              <td class="text-center">
                <?php if ($b['id'] != $current_business['id']): ?>
                  <button type="button" class="btn btn-outline btn-sm" onclick="switchBiz(<?php echo $b['id']; ?>)">
                    🔄 <?php echo $is_rtl ? 'اس پر سوئچ کریں' : 'Switch Context'; ?>
                  </button>
                <?php else: ?>
                  <a href="business_settings.php" class="btn btn-outline btn-sm">
                    ⚙️ Settings
                  </a>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script>
function switchBiz(bid) {
  fetch('ajax/switch_business.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: 'business_id=' + encodeURIComponent(bid)
  })
  .then(res => res.json())
  .then(data => {
    if (data.success) {
      window.location.href = 'dashboard.php';
    } else {
      alert(data.message || 'Error switching business');
    }
  });
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
