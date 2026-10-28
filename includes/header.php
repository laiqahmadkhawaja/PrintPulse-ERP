<?php
/**
 * Global Header Template
 */

require_once __DIR__ . '/../config/init.php';
require_once __DIR__ . '/auth_helper.php';

// Auth Guard & Role-Based Access Enforcement
$publicPages = ['login.php', 'install.php'];
$currentPage = basename($_SERVER['PHP_SELF']);

if (!in_array($currentPage, $publicPages)) {
    if (empty($current_user)) {
        header("Location: " . $base_url . "login.php");
        exit;
    }
    AuthHelper::enforcePermission();
}
?>
<!DOCTYPE html>
<html lang="<?php echo $current_lang; ?>" dir="<?php echo $is_rtl ? 'rtl' : 'ltr'; ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo h(__('app_name')); ?> - <?php echo h($current_business['name'] ?? __('app_subtitle')); ?></title>
  <link rel="icon" type="image/svg+xml" href="<?php echo $base_url; ?>assets/images/favicon.svg">
  <link rel="stylesheet" href="<?php echo $base_url; ?>assets/css/app.css">
  <?php if ($is_rtl): ?>
    <link rel="stylesheet" href="<?php echo $base_url; ?>assets/css/rtl.css">
  <?php endif; ?>
  <script>
    window.APP_BASE_URL = '<?php echo $base_url; ?>';
  </script>
</head>
<body class="<?php echo $is_rtl ? 'rtl-mode' : 'ltr-mode'; ?>">

<?php if (!empty($current_user)): ?>
  <!-- Top Navbar -->
  <header class="navbar-top">
    <a href="<?php echo $base_url; ?>dashboard.php" class="brand-wrapper">
      <img src="<?php echo $base_url; ?>assets/images/logo.svg" alt="PrintPulse ERP" class="brand-logo">
    </a>

    <div class="nav-actions">
      <!-- Active Business Identity -->
      <div class="business-badge" style="cursor:default;" title="<?php echo h($current_business['name'] ?? ''); ?>">
        <span>🏢</span>
        <span style="font-weight:700; color:var(--primary-dark); font-size:13.5px; white-space:nowrap;">
          <?php echo h($current_business['name'] ?? ''); ?>
          <?php if (!empty($current_business['city'])): ?>
            <span style="font-weight:400; color:var(--text-muted); font-size:12px;">(<?php echo h($current_business['city']); ?>)</span>
          <?php endif; ?>
        </span>
      </div>

      <!-- Language Switcher -->
      <?php if ($current_lang === 'en'): ?>
        <a href="?lang=ur" class="btn-lang-toggle" title="Switch to Urdu">🇵🇰 اردو</a>
      <?php else: ?>
        <a href="?lang=en" class="btn-lang-toggle" title="Switch to English">🇬🇧 English</a>
      <?php endif; ?>

      <!-- User Profile (Click to change password / edit profile) -->
      <a href="<?php echo $base_url; ?>profile.php" class="user-profile-badge" style="text-decoration:none; cursor:pointer;" title="<?php echo h(__('my_profile')); ?>">
        <span>👤 <?php echo h($current_user['name']); ?></span>
        <span class="badge badge-primary"><?php echo h($current_user['role']); ?></span>
      </a>

      <!-- Logout -->
      <a href="<?php echo $base_url; ?>logout.php" class="btn btn-outline btn-sm" title="<?php echo h(__('logout')); ?>">
        🚪 <?php echo h(__('logout')); ?>
      </a>
    </div>
  </header>

  <!-- Secondary Navigation Menu -->
  <nav class="navbar-sub">
    <ul class="nav-menu">
      <li class="nav-item">
        <a href="<?php echo $base_url; ?>dashboard.php" class="nav-link <?php echo ($currentPage == 'dashboard.php') ? 'active' : ''; ?>">
          📊 <?php echo h(__('dashboard')); ?>
        </a>
      </li>

      <?php if (can_access('pos.php')): ?>
      <li class="nav-item">
        <a href="<?php echo $base_url; ?>pos.php" class="nav-link <?php echo ($currentPage == 'pos.php') ? 'active' : ''; ?>">
          ⚡ <?php echo h(__('nav_pos')); ?>
        </a>
      </li>
      <?php endif; ?>

      <!-- Repair Management (Respects Toggle & RBAC) -->
      <?php if (can_access('repair_invoices.php') || can_access('repair_jobs.php')): ?>
      <li class="nav-item">
        <a href="<?php echo $base_url; ?>repair_invoices.php" class="nav-link <?php echo in_array($currentPage, ['repair_invoices.php', 'repair_invoice_create.php', 'repair_jobs.php', 'repair_job_create.php', 'repair_job_view.php']) ? 'active' : ''; ?>">
          🔧 <?php echo h(__('nav_repair_invoices')); ?> ▾
        </a>
        <div class="nav-dropdown">
          <a href="<?php echo $base_url; ?>repair_invoice_create.php" class="nav-link">➕ <?php echo $is_rtl ? 'نیا مرمت کا بل' : 'New Repair Invoice'; ?></a>
          <a href="<?php echo $base_url; ?>repair_invoices.php" class="nav-link">📄 <?php echo h(__('nav_repair_invoices')); ?></a>
          <?php if (!empty($current_settings['repair_job_management_enabled'])): ?>
            <a href="<?php echo $base_url; ?>repair_jobs.php" class="nav-link">📋 <?php echo h(__('nav_repair_jobs')); ?></a>
            <a href="<?php echo $base_url; ?>repair_job_create.php" class="nav-link">➕ <?php echo $is_rtl ? 'نیا جاب کارڈ' : 'New Job Card'; ?></a>
          <?php endif; ?>
        </div>
      </li>
      <?php endif; ?>

      <!-- Refill Management (Respects Toggle & RBAC) -->
      <?php if (can_access('refill_invoices.php') || can_access('refill_jobs.php')): ?>
      <li class="nav-item">
        <a href="<?php echo $base_url; ?>refill_invoices.php" class="nav-link <?php echo in_array($currentPage, ['refill_invoices.php', 'refill_invoice_create.php', 'refill_jobs.php', 'refill_job_create.php']) ? 'active' : ''; ?>">
          🧪 <?php echo h(__('nav_refill_invoices')); ?> ▾
        </a>
        <div class="nav-dropdown">
          <a href="<?php echo $base_url; ?>refill_invoice_create.php" class="nav-link">➕ <?php echo $is_rtl ? 'نیا ریفلنگ بل' : 'New Refill Invoice'; ?></a>
          <a href="<?php echo $base_url; ?>refill_invoices.php" class="nav-link">📄 <?php echo h(__('nav_refill_invoices')); ?></a>
          <?php if (!empty($current_settings['refilling_job_management_enabled'])): ?>
            <a href="<?php echo $base_url; ?>refill_jobs.php" class="nav-link">📋 <?php echo h(__('nav_refill_jobs')); ?></a>
            <a href="<?php echo $base_url; ?>refill_job_create.php" class="nav-link">➕ <?php echo $is_rtl ? 'نیا ریفل جاب کارڈ' : 'New Refill Job'; ?></a>
          <?php endif; ?>
        </div>
      </li>
      <?php endif; ?>

      <!-- Sales -->
      <?php if (can_access('sales.php')): ?>
      <li class="nav-item">
        <a href="<?php echo $base_url; ?>sales.php" class="nav-link <?php echo in_array($currentPage, ['sales.php', 'sale_create.php']) ? 'active' : ''; ?>">
          🛒 <?php echo h(__('nav_sales')); ?> ▾
        </a>
        <div class="nav-dropdown">
          <a href="<?php echo $base_url; ?>sale_create.php" class="nav-link">➕ <?php echo $is_rtl ? 'نئی سیلز انوائس' : 'New Sales Invoice'; ?></a>
          <a href="<?php echo $base_url; ?>sales.php" class="nav-link">📄 <?php echo h(__('nav_sales')); ?></a>
        </div>
      </li>
      <?php endif; ?>

      <!-- Purchases -->
      <?php if (can_access('purchases.php')): ?>
      <li class="nav-item">
        <a href="<?php echo $base_url; ?>purchases.php" class="nav-link <?php echo in_array($currentPage, ['purchases.php', 'purchase_create.php']) ? 'active' : ''; ?>">
          📦 <?php echo h(__('nav_purchases')); ?> ▾
        </a>
        <div class="nav-dropdown">
          <a href="<?php echo $base_url; ?>purchase_create.php" class="nav-link">➕ <?php echo $is_rtl ? 'نیا خریداری بل' : 'New Purchase Bill'; ?></a>
          <a href="<?php echo $base_url; ?>purchases.php" class="nav-link">📄 <?php echo h(__('nav_purchases')); ?></a>
        </div>
      </li>
      <?php endif; ?>

      <!-- Inventory / Products -->
      <?php if (can_access('products.php') && !empty($current_settings['inventory_enabled'])): ?>
      <li class="nav-item">
        <a href="<?php echo $base_url; ?>products.php" class="nav-link <?php echo in_array($currentPage, ['products.php', 'product_create.php', 'categories.php', 'service_descriptions.php']) ? 'active' : ''; ?>">
          🗄️ <?php echo h(__('nav_inventory')); ?> ▾
        </a>
        <div class="nav-dropdown">
          <a href="<?php echo $base_url; ?>products.php" class="nav-link">📋 <?php echo h(__('nav_products')); ?></a>
          <a href="<?php echo $base_url; ?>product_create.php" class="nav-link">➕ <?php echo $is_rtl ? 'نیا آئٹم شامل کریں' : 'Add New Product'; ?></a>
          <a href="<?php echo $base_url; ?>categories.php" class="nav-link">🏷️ <?php echo h(__('nav_categories')); ?></a>
          <a href="<?php echo $base_url; ?>service_descriptions.php" class="nav-link">✨ <?php echo h(__('nav_service_catalog')); ?></a>
        </div>
      </li>
      <?php endif; ?>

      <!-- Parties / Ledgers -->
      <?php if (can_access('customers.php') || can_access('suppliers.php')): ?>
      <li class="nav-item">
        <a href="<?php echo $base_url; ?>customers.php" class="nav-link <?php echo in_array($currentPage, ['customers.php', 'customer_view.php', 'suppliers.php', 'supplier_view.php', 'payments.php']) ? 'active' : ''; ?>">
          👥 <?php echo h(__('nav_parties')); ?> ▾
        </a>
        <div class="nav-dropdown">
          <a href="<?php echo $base_url; ?>customers.php" class="nav-link">👤 <?php echo h(__('nav_customers')); ?></a>
          <?php if (can_access('suppliers.php')): ?>
            <a href="<?php echo $base_url; ?>suppliers.php" class="nav-link">🏢 <?php echo h(__('nav_suppliers')); ?></a>
          <?php endif; ?>
          <a href="<?php echo $base_url; ?>payments.php" class="nav-link">💳 <?php echo h(__('nav_payments')); ?></a>
        </div>
      </li>
      <?php endif; ?>

      <!-- Expenses & Vouchers -->
      <?php if (can_access('expenses.php') || can_access('vouchers.php')): ?>
      <li class="nav-item">
        <a href="<?php echo $base_url; ?>expenses.php" class="nav-link <?php echo in_array($currentPage, ['expenses.php', 'expense_categories.php', 'vouchers.php', 'voucher_create.php']) ? 'active' : ''; ?>">
          💸 <?php echo h(__('nav_expenses')); ?> ▾
        </a>
        <div class="nav-dropdown">
          <a href="<?php echo $base_url; ?>expenses.php" class="nav-link">🧾 <?php echo h(__('nav_expenses')); ?></a>
          <a href="<?php echo $base_url; ?>expense_categories.php" class="nav-link">📑 <?php echo $is_rtl ? 'اخراجات کیٹیگریز' : 'Expense Categories'; ?></a>
          <?php if (can_access('vouchers.php')): ?>
            <a href="<?php echo $base_url; ?>vouchers.php" class="nav-link">📜 <?php echo h(__('nav_vouchers')); ?></a>
            <a href="<?php echo $base_url; ?>voucher_create.php" class="nav-link">➕ <?php echo $is_rtl ? 'نیا ڈبل انٹری واؤچر' : 'New Journal Voucher'; ?></a>
          <?php endif; ?>
        </div>
      </li>
      <?php endif; ?>

      <!-- Double-Entry Accounting -->
      <?php if (can_access('accounting/trial_balance.php')): ?>
      <li class="nav-item">
        <a href="<?php echo $base_url; ?>accounting/trial_balance.php" class="nav-link <?php echo (strpos($_SERVER['PHP_SELF'], 'accounting/') !== false) ? 'active' : ''; ?>">
          ⚖️ <?php echo h(__('nav_accounting')); ?> ▾
        </a>
        <div class="nav-dropdown">
          <a href="<?php echo $base_url; ?>accounting/chart_of_accounts.php" class="nav-link">📖 <?php echo h(__('nav_chart_of_accounts')); ?></a>
          <a href="<?php echo $base_url; ?>accounting/trial_balance.php" class="nav-link">⚖️ <?php echo h(__('nav_trial_balance')); ?></a>
          <a href="<?php echo $base_url; ?>accounting/profit_loss.php" class="nav-link">📈 <?php echo h(__('nav_profit_loss')); ?></a>
          <a href="<?php echo $base_url; ?>accounting/balance_sheet.php" class="nav-link">🏛️ <?php echo h(__('nav_balance_sheet')); ?></a>
          <a href="<?php echo $base_url; ?>accounting/cash_book.php" class="nav-link">💵 <?php echo h(__('nav_cash_book')); ?></a>
        </div>
      </li>
      <?php endif; ?>

      <!-- Reports & Closings -->
      <?php if (can_access('reports/daily_closing.php') || can_access('reports/sales_report.php') || can_access('accounting/balance_sheet.php')): ?>
      <li class="nav-item">
        <a href="<?php echo $base_url; ?>reports/daily_closing.php" class="nav-link <?php echo (strpos($_SERVER['PHP_SELF'], 'reports/') !== false) ? 'active' : ''; ?>">
          📋 <?php echo h(__('nav_reports')); ?> ▾
        </a>
        <div class="nav-dropdown" style="min-width:240px;">
          <div style="padding:6px 14px; font-size:11px; font-weight:700; color:var(--text-muted); text-transform:uppercase; border-bottom:1px solid #f1f5f9;">
            <?php echo $is_rtl ? 'روزمرہ و سیلز رپورٹس' : 'Sales & Operations'; ?>
          </div>
          <a href="<?php echo $base_url; ?>reports/daily_closing.php" class="nav-link">🔒 <?php echo h(__('nav_daily_closing')); ?></a>
          <?php if (can_access('reports/sales_report.php')): ?>
            <a href="<?php echo $base_url; ?>reports/sales_report.php" class="nav-link">📊 <?php echo $is_rtl ? 'سیلز سمری رپورٹ' : 'Sales Summary Report'; ?></a>
          <?php endif; ?>
          <?php if (can_access('reports/sales_by_category.php')): ?>
            <a href="<?php echo $base_url; ?>reports/sales_by_category.php" class="nav-link">🏷️ <?php echo $is_rtl ? 'سیلز بلحاظ کیٹیگری' : 'Sales by Category'; ?></a>
          <?php endif; ?>
          <?php if (can_access('reports/sales_by_product.php')): ?>
            <a href="<?php echo $base_url; ?>reports/sales_by_product.php" class="nav-link">📦 <?php echo $is_rtl ? 'سیلز بلحاظ پروڈکٹ' : 'Sales by Product / Item'; ?></a>
          <?php endif; ?>

          <?php if (can_access('accounting/balance_sheet.php') || can_access('accounting/profit_loss.php')): ?>
            <div style="padding:6px 14px; font-size:11px; font-weight:700; color:var(--text-muted); text-transform:uppercase; border-top:1px solid #f1f5f9; border-bottom:1px solid #f1f5f9; margin-top:4px;">
              <?php echo $is_rtl ? 'مالیاتی گوشوارے' : 'Financial Statements'; ?>
            </div>
            <?php if (can_access('accounting/balance_sheet.php')): ?>
              <a href="<?php echo $base_url; ?>accounting/balance_sheet.php" class="nav-link">🏛️ <?php echo h(__('nav_balance_sheet')); ?></a>
            <?php endif; ?>
            <?php if (can_access('accounting/profit_loss.php')): ?>
              <a href="<?php echo $base_url; ?>accounting/profit_loss.php" class="nav-link">📈 <?php echo h(__('nav_profit_loss')); ?></a>
            <?php endif; ?>
            <?php if (can_access('accounting/trial_balance.php')): ?>
              <a href="<?php echo $base_url; ?>accounting/trial_balance.php" class="nav-link">⚖️ <?php echo h(__('nav_trial_balance')); ?></a>
            <?php endif; ?>
            <?php if (can_access('accounting/cash_book.php')): ?>
              <a href="<?php echo $base_url; ?>accounting/cash_book.php" class="nav-link">💵 <?php echo h(__('nav_cash_book')); ?></a>
            <?php endif; ?>
          <?php endif; ?>
        </div>
      </li>
      <?php endif; ?>

      <!-- Business Settings & Tools -->
      <?php if (can_access('business_settings.php') || can_access('users.php') || can_access('backup.php')): ?>
      <li class="nav-item">
        <a href="<?php echo $base_url; ?>business_settings.php" class="nav-link <?php echo in_array($currentPage, ['business_settings.php', 'businesses.php', 'backup.php', 'profile.php', 'users.php']) ? 'active' : ''; ?>">
          ⚙️ <?php echo h(__('nav_settings')); ?> ▾
        </a>
        <div class="nav-dropdown">
          <a href="<?php echo $base_url; ?>profile.php" class="nav-link">👤 <?php echo h(__('my_profile')); ?></a>
          <?php if (can_access('users.php')): ?>
            <a href="<?php echo $base_url; ?>users.php" class="nav-link">👥 <?php echo $is_rtl ? 'صارفین اور عملہ' : 'Users & Staff'; ?></a>
          <?php endif; ?>
          <?php if (can_access('business_settings.php')): ?>
            <a href="<?php echo $base_url; ?>business_settings.php" class="nav-link">⚙️ <?php echo h(__('nav_settings')); ?></a>
          <?php endif; ?>
          <?php if (($current_user['role'] ?? '') === 'platform_admin'): ?>
            <a href="<?php echo $base_url; ?>businesses.php" class="nav-link">🏢 <?php echo h(__('nav_businesses')); ?></a>
          <?php endif; ?>
          <?php if (can_access('backup.php')): ?>
            <a href="<?php echo $base_url; ?>backup.php" class="nav-link">💾 <?php echo h(__('nav_backup')); ?></a>
          <?php endif; ?>
        </div>
      </li>
      <?php endif; ?>
    </ul>
  </nav>
<?php endif; ?>

<main class="container">
<?php if (!empty($_SESSION['flash_success'])): ?>
  <div class="alert alert-success" style="margin-bottom:20px; display:flex; justify-content:space-between; align-items:center;">
    <span>✅ <?php echo h($_SESSION['flash_success']); ?></span>
    <button type="button" onclick="this.parentElement.remove()" style="background:none; border:none; font-size:1.2rem; cursor:pointer; color:inherit;">&times;</button>
  </div>
  <?php unset($_SESSION['flash_success']); ?>
<?php endif; ?>
<?php if (!empty($_SESSION['flash_error'])): ?>
  <div class="alert alert-danger" style="margin-bottom:20px; display:flex; justify-content:space-between; align-items:center;">
    <span>❌ <?php echo h($_SESSION['flash_error']); ?></span>
    <button type="button" onclick="this.parentElement.remove()" style="background:none; border:none; font-size:1.2rem; cursor:pointer; color:inherit;">&times;</button>
  </div>
  <?php unset($_SESSION['flash_error']); ?>
<?php endif; ?>
