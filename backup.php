<?php
require_once __DIR__ . '/config/init.php';
require_once __DIR__ . '/includes/header.php';

$bid = (int)$current_business['id'];
$pdo = getDB();
$msg = '';
$err = '';
$backupDir = __DIR__ . '/backups';

if (!is_dir($backupDir)) {
    mkdir($backupDir, 0777, true);
}

// 1. Full Database Backup
if (isset($_POST['action']) && $_POST['action'] === 'full_backup') {
    if (verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $filename = 'pshop_backup_' . date('Y-m-d_H-i-s') . '.sql';
        $filepath = $backupDir . '/' . $filename;

        // Generate clean SQL dump in pure PHP (no external shell dependencies required)
        $tables = [
            'businesses', 'business_settings', 'users', 'user_businesses', 'chart_of_accounts',
            'journal_entries', 'journal_entry_lines', 'customers', 'suppliers', 'categories',
            'products', 'stock_movements', 'service_descriptions', 'repair_jobs', 'refill_jobs',
            'invoices', 'invoice_items', 'purchases', 'purchase_items', 'payments',
            'expense_categories', 'expenses', 'vouchers', 'daily_closings', 'audit_logs'
        ];

        $dump = "-- PrintPulse ERP Database Backup\n";
        $dump .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
        $dump .= "SET FOREIGN_KEY_CHECKS = 0;\n\n";

        foreach ($tables as $tbl) {
            $createStmt = $pdo->query("SHOW CREATE TABLE `{$tbl}`")->fetch();
            $dump .= "DROP TABLE IF EXISTS `{$tbl}`;\n";
            $dump .= $createStmt['Create Table'] . ";\n\n";

            $rows = $pdo->query("SELECT * FROM `{$tbl}`")->fetchAll(PDO::FETCH_ASSOC);
            if (!empty($rows)) {
                $cols = array_keys($rows[0]);
                $colNames = implode('`, `', $cols);
                foreach ($rows as $r) {
                    $vals = array_map(function($val) use ($pdo) {
                        return ($val === null) ? 'NULL' : $pdo->quote($val);
                    }, array_values($r));
                    $dump .= "INSERT INTO `{$tbl}` (`{$colNames}`) VALUES (" . implode(', ', $vals) . ");\n";
                }
                $dump .= "\n";
            }
        }
        $dump .= "SET FOREIGN_KEY_CHECKS = 1;\n";

        file_put_contents($filepath, $dump);
        log_audit('DATABASE_BACKUP', 'backup', null, "Created backup {$filename}");
        $msg = $is_rtl ? "ڈیٹا بیس بیک اپ فائل {$filename} کامیابی سے تیار ہو گئی۔" : "Full database backup {$filename} generated successfully.";
    }
}

// 2. Business-Level JSON Export (Tenant Specific)
if (isset($_GET['export_business'])) {
    $bizExport = [
        'business' => $current_business,
        'settings' => $current_settings,
        'customers' => $pdo->query("SELECT * FROM customers WHERE business_id = {$bid}")->fetchAll(),
        'suppliers' => $pdo->query("SELECT * FROM suppliers WHERE business_id = {$bid}")->fetchAll(),
        'products' => $pdo->query("SELECT * FROM products WHERE business_id = {$bid}")->fetchAll(),
        'invoices' => $pdo->query("SELECT * FROM invoices WHERE business_id = {$bid}")->fetchAll(),
        'services' => $pdo->query("SELECT * FROM service_descriptions WHERE business_id = {$bid}")->fetchAll(),
        'chart_of_accounts' => $pdo->query("SELECT * FROM chart_of_accounts WHERE business_id = {$bid}")->fetchAll(),
    ];

    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="' . $current_business['slug'] . '_export_' . date('Y-m-d') . '.json"');
    echo json_encode($bizExport, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

// 3. Admin Database Restoration
if (isset($_POST['action']) && $_POST['action'] === 'restore_db') {
    if ($current_user['role'] !== 'platform_admin') {
        $err = "Only platform administrators are authorized to restore the database.";
    } elseif (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $err = "Security token mismatch.";
    } else {
        $restoreFile = $_POST['restore_file'] ?? '';
        $filepath = $backupDir . '/' . basename($restoreFile);

        if (!file_exists($filepath)) {
            $err = "Backup file not found.";
        } else {
            $sql = file_get_contents($filepath);
            try {
                $pdo->exec($sql);
                log_audit('DATABASE_RESTORE', 'backup', null, "Restored database from {$restoreFile}");
                $msg = $is_rtl ? "ڈیٹا بیس کامیابی سے بحال (Restore) کر دیا گیا۔" : "Database restored successfully from {$restoreFile}.";
            } catch (Exception $e) {
                $err = "Restoration failed: " . $e->getMessage();
            }
        }
    }
}

// Fetch existing backups
$backupFiles = [];
if (is_dir($backupDir)) {
    foreach (scandir($backupDir) as $f) {
        if (substr($f, -4) === '.sql') {
            $backupFiles[] = [
                'name' => $f,
                'size' => round(filesize($backupDir . '/' . $f) / 1024, 2) . ' KB',
                'time' => date('Y-m-d H:i:s', filemtime($backupDir . '/' . $f))
            ];
        }
    }
}
?>

<div class="page-header">
  <div>
    <h1 class="page-title">
      💾 <?php echo h(__('nav_backup')); ?> &mdash; <?php echo $is_rtl ? 'بیک اپ اور بحالی' : 'Database Backup & Restore'; ?>
    </h1>
    <p style="color:var(--text-muted); font-size:0.9rem;">
      <?php echo $is_rtl ? 'مکمل مشترکہ ڈیٹا بیس اور انفرادی بزنس لیول ڈیٹا کی برآمدگی' : 'Full system SQL backups and tenant-specific business export'; ?>
    </p>
  </div>
</div>

<?php if ($err): ?>
  <div class="alert alert-danger"><?php echo h($err); ?></div>
<?php endif; ?>
<?php if ($msg): ?>
  <div class="alert alert-success"><?php echo h($msg); ?></div>
<?php endif; ?>

<div class="grid grid-cols-2">
  <!-- Full Database Backup -->
  <div class="card">
    <div class="card-header">
      <span>💾 <?php echo $is_rtl ? 'مکمل سسٹم SQL بیک اپ' : 'Full Database SQL Backup'; ?></span>
    </div>
    <div class="card-body">
      <p style="color:var(--text-muted); font-size:0.85rem; margin-bottom:15px;">
        <?php echo $is_rtl 
          ? 'ایک ہی کلک پر تمام بزنسز، پروڈکٹس، ڈبل انٹری اکاؤنٹنگ، اور بلز کا مکمل SQL ڈمپ تیار کریں اور ڈاؤنلوڈ کریں۔' 
          : 'Creates a full SQL dump of all registered businesses, chart of accounts, journal entries, inventory, and invoices.'; ?>
      </p>

      <form method="POST" action="backup.php">
        <input type="hidden" name="action" value="full_backup">
        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
        <button type="submit" class="btn btn-primary" style="padding:10px 20px; font-weight:700;">
          📥 <?php echo $is_rtl ? 'ابھی مکمل بیک اپ بنائیں' : 'Create Full SQL Backup Now'; ?>
        </button>
      </form>
    </div>
  </div>

  <!-- Business-Level JSON Export -->
  <div class="card">
    <div class="card-header">
      <span>📁 <?php echo $is_rtl ? 'صرف موجودہ بزنس کا ڈیٹا ایکسپورٹ' : 'Business-Level Data Export'; ?></span>
    </div>
    <div class="card-body">
      <p style="color:var(--text-muted); font-size:0.85rem; margin-bottom:15px;">
        <?php echo $is_rtl 
          ? 'صرف فعال بزنس ('.h($current_business['name']).') کا ڈیٹا JSON فارمیٹ میں ڈاؤنلوڈ کریں۔' 
          : 'Exports records belonging strictly to the currently selected business context (JSON format).'; ?>
      </p>

      <a href="backup.php?export_business=1" class="btn btn-outline" style="padding:10px 20px; font-weight:700;">
        📤 <?php echo $is_rtl ? 'موجودہ بزنس کا ڈیٹا ڈاؤنلوڈ کریں' : 'Download Business Data (JSON)'; ?>
      </a>
    </div>
  </div>
</div>

<!-- Backups History & Platform Admin Restoration -->
<div class="card">
  <div class="card-header">
    <span>📋 <?php echo $is_rtl ? 'محفوظ شدہ بیک اپ فائلیں' : 'Available Database Backup Files'; ?></span>
  </div>
  <div class="table-responsive">
    <table class="table">
      <thead>
        <tr>
          <th>Backup Filename</th>
          <th>Generated At</th>
          <th>Size</th>
          <th class="text-center">Action</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($backupFiles)): ?>
          <tr><td colspan="4" class="text-center text-muted" style="padding:20px;"><?php echo $is_rtl ? 'کوئی بیک اپ موجود نہیں ہے' : 'No backups created yet.'; ?></td></tr>
        <?php else: ?>
          <?php foreach ($backupFiles as $bf): ?>
            <tr>
              <td><code><?php echo h($bf['name']); ?></code></td>
              <td><?php echo h($bf['time']); ?></td>
              <td><?php echo h($bf['size']); ?></td>
              <td class="text-center">
                <?php if ($current_user['role'] === 'platform_admin'): ?>
                  <form method="POST" action="backup.php" style="display:inline;" onsubmit="return confirm('CAUTION: Restoring will overwrite all businesses data. Proceed?');">
                    <input type="hidden" name="action" value="restore_db">
                    <input type="hidden" name="restore_file" value="<?php echo h($bf['name']); ?>">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    <button type="submit" class="btn btn-danger btn-sm">
                      ⚠️ <?php echo $is_rtl ? 'بحال کریں (Restore)' : 'Restore DB'; ?>
                    </button>
                  </form>
                <?php else: ?>
                  <span class="text-muted">Admin Only</span>
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
