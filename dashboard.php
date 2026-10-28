<?php
require_once __DIR__ . '/config/init.php';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/accounting_helper.php';

$bid = (int)$current_business['id'];
$pdo = getDB();
$today = date('Y-m-d');

// 1. Today's Sales
$stmt = $pdo->prepare("SELECT COALESCE(SUM(total_amount), 0) FROM invoices WHERE business_id = ? AND invoice_date = ? AND invoice_type IN ('sale', 'pos')");
$stmt->execute([$bid, $today]);
$today_sales = (float)$stmt->fetchColumn();

// 2. Today's Repairs
$stmt = $pdo->prepare("SELECT COALESCE(SUM(total_amount), 0) FROM invoices WHERE business_id = ? AND invoice_date = ? AND invoice_type = 'repair'");
$stmt->execute([$bid, $today]);
$today_repairs = (float)$stmt->fetchColumn();

// 3. Today's Refills
$stmt = $pdo->prepare("SELECT COALESCE(SUM(total_amount), 0) FROM invoices WHERE business_id = ? AND invoice_date = ? AND invoice_type = 'refill'");
$stmt->execute([$bid, $today]);
$today_refills = (float)$stmt->fetchColumn();

// 4. Cash in Hand (GL Account 1010)
$cashBalance = 0.0;
try {
    $cashAccId = AccountingEngine::getAccountIdByCode($bid, '1010');
    $stmt = $pdo->prepare("SELECT current_balance FROM chart_of_accounts WHERE id = ?");
    $stmt->execute([$cashAccId]);
    $cashBalance = (float)$stmt->fetchColumn();
} catch (Exception $e) {}

// 5. Total Receivables
$totalReceivables = 0.0;
try {
    $recAccId = AccountingEngine::getAccountIdByCode($bid, '1030');
    $stmt = $pdo->prepare("SELECT current_balance FROM chart_of_accounts WHERE id = ?");
    $stmt->execute([$recAccId]);
    $totalReceivables = (float)$stmt->fetchColumn();
} catch (Exception $e) {}

// 6. Total Payables
$totalPayables = 0.0;
try {
    $payAccId = AccountingEngine::getAccountIdByCode($bid, '2010');
    $stmt = $pdo->prepare("SELECT current_balance FROM chart_of_accounts WHERE id = ?");
    $stmt->execute([$payAccId]);
    $totalPayables = (float)$stmt->fetchColumn();
} catch (Exception $e) {}

// 7. Active Repair Jobs count
$activeRepairs = 0;
if (!empty($current_settings['repair_job_management_enabled'])) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM repair_jobs WHERE business_id = ? AND status IN ('received', 'diagnosing', 'waiting_parts')");
    $stmt->execute([$bid]);
    $activeRepairs = (int)$stmt->fetchColumn();
}

// 8. Ready Refills count
$readyRefills = 0;
if (!empty($current_settings['refilling_job_management_enabled'])) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM refill_jobs WHERE business_id = ? AND status = 'ready'");
    $stmt->execute([$bid]);
    $readyRefills = (int)$stmt->fetchColumn();
}

// Recent Invoices (Scoped to Business)
$stmt = $pdo->prepare("SELECT * FROM invoices WHERE business_id = ? ORDER BY id DESC LIMIT 8");
$stmt->execute([$bid]);
$recentInvoices = $stmt->fetchAll();

// Active Repair Jobs List
$recentJobs = [];
if (!empty($current_settings['repair_job_management_enabled'])) {
    $stmt = $pdo->prepare("SELECT * FROM repair_jobs WHERE business_id = ? AND status != 'delivered' ORDER BY id DESC LIMIT 5");
    $stmt->execute([$bid]);
    $recentJobs = $stmt->fetchAll();
}
?>

<div class="page-header">
  <div>
    <h1 class="page-title">
      🏢 <?php echo h($current_business['name']); ?>
    </h1>
    <p style="color: var(--text-muted); font-size: 0.9rem;">
      <?php echo h($current_business['address']); ?> &bull; 📞 <?php echo h($current_business['cell']); ?>
    </p>
  </div>
  
  <div style="display:flex; gap:10px; flex-wrap:wrap;">
    <a href="pos.php" class="btn btn-primary">⚡ <?php echo h(__('nav_pos')); ?></a>
    <a href="repair_invoice_create.php" class="btn btn-success">➕ <?php echo $is_rtl ? 'نیا مرمت بل' : 'New Repair Invoice'; ?></a>
    <a href="refill_invoice_create.php" class="btn btn-outline">🧪 <?php echo $is_rtl ? 'نیا ریفلنگ بل' : 'New Refill Invoice'; ?></a>
  </div>
</div>

<!-- Key Performance Indicators (KPIs) -->
<div class="grid grid-cols-4" style="margin-bottom: 1.5rem;">
  <div class="stat-card">
    <div class="stat-icon primary">💵</div>
    <div class="stat-info">
      <div class="stat-title"><?php echo h(__('stat_cash_balance')); ?></div>
      <div class="stat-value"><?php echo format_currency($cashBalance); ?></div>
    </div>
  </div>

  <div class="stat-card">
    <div class="stat-icon success">🛒</div>
    <div class="stat-info">
      <div class="stat-title"><?php echo h(__('stat_today_sales')); ?></div>
      <div class="stat-value"><?php echo format_currency($today_sales); ?></div>
    </div>
  </div>

  <div class="stat-card">
    <div class="stat-icon warning">🔧</div>
    <div class="stat-info">
      <div class="stat-title"><?php echo h(__('stat_today_repairs')); ?></div>
      <div class="stat-value"><?php echo format_currency($today_repairs); ?></div>
    </div>
  </div>

  <div class="stat-card">
    <div class="stat-icon accent">🧪</div>
    <div class="stat-info">
      <div class="stat-title"><?php echo h(__('stat_today_refills')); ?></div>
      <div class="stat-value"><?php echo format_currency($today_refills); ?></div>
    </div>
  </div>
</div>

<!-- Secondary Stats Row -->
<div class="grid grid-cols-4" style="margin-bottom: 2rem;">
  <div class="stat-card">
    <div class="stat-icon danger">📉</div>
    <div class="stat-info">
      <div class="stat-title"><?php echo h(__('stat_receivables')); ?></div>
      <div class="stat-value" style="color:var(--danger);"><?php echo format_currency($totalReceivables); ?></div>
    </div>
  </div>

  <div class="stat-card">
    <div class="stat-icon primary">🏢</div>
    <div class="stat-info">
      <div class="stat-title"><?php echo h(__('stat_payables')); ?></div>
      <div class="stat-value"><?php echo format_currency($totalPayables); ?></div>
    </div>
  </div>

  <?php if (!empty($current_settings['repair_job_management_enabled'])): ?>
  <div class="stat-card">
    <div class="stat-icon warning">📋</div>
    <div class="stat-info">
      <div class="stat-title"><?php echo h(__('stat_active_repairs')); ?></div>
      <div class="stat-value"><?php echo $activeRepairs; ?></div>
    </div>
  </div>
  <?php endif; ?>

  <?php if (!empty($current_settings['refilling_job_management_enabled'])): ?>
  <div class="stat-card">
    <div class="stat-icon success">📦</div>
    <div class="stat-info">
      <div class="stat-title"><?php echo h(__('stat_ready_refills')); ?></div>
      <div class="stat-value"><?php echo $readyRefills; ?></div>
    </div>
  </div>
  <?php endif; ?>
</div>

<div class="grid grid-cols-2">
  <!-- Recent Invoices Table -->
  <div class="card">
    <div class="card-header">
      <span>📄 <?php echo $is_rtl ? 'حالیہ بلز و انوائسز' : 'Recent Invoices'; ?></span>
      <a href="sales.php" class="btn btn-outline btn-sm"><?php echo h(__('all')); ?></a>
    </div>
    <div class="table-responsive">
      <table class="table">
        <thead>
          <tr>
            <th><?php echo h(__('invoice_no')); ?></th>
            <th><?php echo h(__('customer_name')); ?></th>
            <th><?php echo h(__('date')); ?></th>
            <th><?php echo h(__('total')); ?></th>
            <th><?php echo h(__('balance')); ?></th>
            <th><?php echo h(__('action')); ?></th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($recentInvoices)): ?>
            <tr><td colspan="6" class="text-center text-muted"><?php echo $is_rtl ? 'کوئی بل موجود نہیں' : 'No invoices found.'; ?></td></tr>
          <?php else: ?>
            <?php foreach ($recentInvoices as $inv): ?>
              <tr>
                <td><strong><?php echo h($inv['invoice_number']); ?></strong></td>
                <td><?php echo h($inv['customer_name']); ?></td>
                <td><?php echo format_date($inv['invoice_date']); ?></td>
                <td><strong><?php echo format_currency($inv['total_amount']); ?></strong></td>
                <td>
                  <?php if ($inv['balance_amount'] > 0): ?>
                    <span class="badge badge-danger"><?php echo format_currency($inv['balance_amount']); ?></span>
                  <?php else: ?>
                    <span class="badge badge-success"><?php echo $is_rtl ? 'ادا شدہ' : 'Paid'; ?></span>
                  <?php endif; ?>
                </td>
                <td>
                  <a href="print_invoice.php?id=<?php echo $inv['id']; ?>" target="_blank" class="btn btn-outline btn-sm" title="Print Invoice">
                    🖨️ <?php echo h(__('print')); ?>
                  </a>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Active Repair Jobs or Quick Shortcuts -->
  <div class="card">
    <div class="card-header">
      <span>🔧 <?php echo $is_rtl ? 'زیر مرمت پرنٹرز جاب کارڈز' : 'Active Repair Jobs'; ?></span>
      <?php if (!empty($current_settings['repair_job_management_enabled'])): ?>
        <a href="repair_jobs.php" class="btn btn-outline btn-sm"><?php echo h(__('all')); ?></a>
      <?php endif; ?>
    </div>
    
    <?php if (empty($current_settings['repair_job_management_enabled'])): ?>
      <div class="card-body text-center" style="padding: 30px;">
        <p class="text-muted">
          <?php echo $is_rtl 
            ? 'پرنٹر جاب کارڈ سسٹم اس بزنس کے لیے غیر فعال ہے۔ آپ براہ راست مرمت کا بل تیار کر سکتے ہیں۔' 
            : 'Repair job card workflow is currently disabled for this business. You can issue direct repair invoices without job cards.'; ?>
        </p>
        <a href="repair_invoice_create.php" class="btn btn-primary" style="margin-top:10px;">
          ➕ <?php echo $is_rtl ? 'براہ راست مرمت کا بل بنائیں' : 'Create Direct Repair Bill'; ?>
        </a>
      </div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table">
          <thead>
            <tr>
              <th><?php echo h(__('job_number')); ?></th>
              <th><?php echo h(__('customer_name')); ?></th>
              <th><?php echo h(__('printer_model')); ?></th>
              <th><?php echo h(__('status')); ?></th>
              <th><?php echo h(__('action')); ?></th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($recentJobs)): ?>
              <tr><td colspan="5" class="text-center text-muted"><?php echo $is_rtl ? 'کوئی جاب کارڈ زیر التواء نہیں ہے' : 'No pending repair jobs.'; ?></td></tr>
            <?php else: ?>
              <?php foreach ($recentJobs as $job): ?>
                <tr>
                  <td><strong><?php echo h($job['job_number']); ?></strong></td>
                  <td><?php echo h($job['customer_name']); ?></td>
                  <td><?php echo h($job['printer_brand'] . ' ' . $job['printer_model']); ?></td>
                  <td>
                    <?php 
                      $badgeCls = 'badge-primary';
                      if ($job['status'] === 'diagnosing') $badgeCls = 'badge-warning';
                      if ($job['status'] === 'waiting_parts') $badgeCls = 'badge-danger';
                      if ($job['status'] === 'repaired') $badgeCls = 'badge-success';
                    ?>
                    <span class="badge <?php echo $badgeCls; ?>"><?php echo h(__('status_' . $job['status'], $job['status'])); ?></span>
                  </td>
                  <td>
                    <a href="repair_job_view.php?id=<?php echo $job['id']; ?>" class="btn btn-outline btn-sm">
                      👁️ <?php echo h(__('view')); ?>
                    </a>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
