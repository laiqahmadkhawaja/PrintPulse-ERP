<?php
/**
 * Role-Based Access Control (RBAC) Engine
 * Restricts module and page accessibility strictly to authorized roles.
 */

require_once __DIR__ . '/../config/init.php';

class AuthHelper {

    /**
     * Role definitions & page access map
     */
    public static $rolePermissions = [
        'platform_admin' => ['*'], // Full unrestricted access to all modules and SaaS admin
        
        'business_owner' => [
            'dashboard.php',
            'profile.php',
            'pos.php',
            'sales.php',
            'sale_create.php',
            'invoice_edit.php',
            'invoice_delete.php',
            'print_invoice.php',
            'repair_jobs.php',
            'repair_job_create.php',
            'repair_job_view.php',
            'repair_invoices.php',
            'repair_invoice_create.php',
            'refill_jobs.php',
            'refill_job_create.php',
            'refill_invoices.php',
            'refill_invoice_create.php',
            'products.php',
            'product_create.php',
            'categories.php',
            'service_descriptions.php',
            'purchases.php',
            'purchase_create.php',
            'customers.php',
            'customer_view.php',
            'suppliers.php',
            'supplier_view.php',
            'payments.php',
            'expenses.php',
            'expense_categories.php',
            'vouchers.php',
            'voucher_create.php',
            'accounting/chart_of_accounts.php',
            'accounting/general_ledger.php',
            'accounting/trial_balance.php',
            'accounting/profit_loss.php',
            'accounting/balance_sheet.php',
            'accounting/cash_book.php',
            'reports/daily_closing.php',
            'reports/sales_report.php',
            'reports/sales_by_category.php',
            'reports/sales_by_product.php',
            'business_settings.php',
            'users.php',
            'backup.php',
        ],

        // Direct alias for 'owner'
        'owner' => [
            'dashboard.php',
            'profile.php',
            'pos.php',
            'sales.php',
            'sale_create.php',
            'invoice_edit.php',
            'invoice_delete.php',
            'print_invoice.php',
            'repair_jobs.php',
            'repair_job_create.php',
            'repair_job_view.php',
            'repair_invoices.php',
            'repair_invoice_create.php',
            'refill_jobs.php',
            'refill_job_create.php',
            'refill_invoices.php',
            'refill_invoice_create.php',
            'products.php',
            'product_create.php',
            'categories.php',
            'service_descriptions.php',
            'purchases.php',
            'purchase_create.php',
            'customers.php',
            'customer_view.php',
            'suppliers.php',
            'supplier_view.php',
            'payments.php',
            'expenses.php',
            'expense_categories.php',
            'vouchers.php',
            'voucher_create.php',
            'accounting/chart_of_accounts.php',
            'accounting/general_ledger.php',
            'accounting/trial_balance.php',
            'accounting/profit_loss.php',
            'accounting/balance_sheet.php',
            'accounting/cash_book.php',
            'reports/daily_closing.php',
            'reports/sales_report.php',
            'reports/sales_by_category.php',
            'reports/sales_by_product.php',
            'business_settings.php',
            'users.php',
            'backup.php',
        ],

        'manager' => [
            'dashboard.php',
            'profile.php',
            'pos.php',
            'sales.php',
            'sale_create.php',
            'invoice_edit.php',
            'print_invoice.php',
            'repair_jobs.php',
            'repair_job_create.php',
            'repair_job_view.php',
            'repair_invoices.php',
            'repair_invoice_create.php',
            'refill_jobs.php',
            'refill_job_create.php',
            'refill_invoices.php',
            'refill_invoice_create.php',
            'products.php',
            'product_create.php',
            'categories.php',
            'service_descriptions.php',
            'purchases.php',
            'purchase_create.php',
            'customers.php',
            'customer_view.php',
            'suppliers.php',
            'supplier_view.php',
            'payments.php',
            'expenses.php',
            'expense_categories.php',
            'reports/daily_closing.php',
            'reports/sales_report.php',
            'reports/sales_by_category.php',
            'reports/sales_by_product.php',
        ],

        'technician' => [
            'dashboard.php',
            'profile.php',
            'repair_jobs.php',
            'repair_job_create.php',
            'repair_job_view.php',
            'repair_invoices.php',
            'repair_invoice_create.php',
            'refill_jobs.php',
            'refill_job_create.php',
            'refill_invoices.php',
            'refill_invoice_create.php',
            'products.php',
            'service_descriptions.php',
            'print_invoice.php',
        ],

        'accountant' => [
            'dashboard.php',
            'profile.php',
            'sales.php',
            'purchases.php',
            'customers.php',
            'customer_view.php',
            'suppliers.php',
            'supplier_view.php',
            'payments.php',
            'expenses.php',
            'expense_categories.php',
            'vouchers.php',
            'voucher_create.php',
            'accounting/chart_of_accounts.php',
            'accounting/general_ledger.php',
            'accounting/trial_balance.php',
            'accounting/profit_loss.php',
            'accounting/balance_sheet.php',
            'accounting/cash_book.php',
            'reports/daily_closing.php',
            'reports/sales_report.php',
            'reports/sales_by_category.php',
            'reports/sales_by_product.php',
        ],

        'cashier' => [
            'dashboard.php',
            'profile.php',
            'pos.php',
            'sales.php',
            'sale_create.php',
            'print_invoice.php',
            'repair_invoices.php',
            'repair_invoice_create.php',
            'refill_invoices.php',
            'refill_invoice_create.php',
            'customers.php',
            'customer_view.php',
            'payments.php',
            'reports/daily_closing.php',
        ],
    ];

    /**
     * Check if a given role has permission to access a page
     */
    public static function hasPermission($page, $role = null) {
        global $current_user;
        if (!$role) {
            $role = $current_user['role'] ?? 'cashier';
        }

        $role = strtolower(trim((string)$role));
        if ($role === 'owner') {
            $role = 'business_owner';
        }

        if ($role === 'platform_admin') {
            return true;
        }

        $allowedPages = self::$rolePermissions[$role] ?? [];
        if (in_array('*', $allowedPages)) {
            return true;
        }

        $cleanPage = str_replace('\\', '/', $page);
        $cleanPage = ltrim($cleanPage, '/');
        $parts = explode('/', $cleanPage);
        $relativePage = (count($parts) >= 2 && in_array($parts[count($parts)-2], ['accounting', 'reports'])) 
            ? $parts[count($parts)-2] . '/' . end($parts) 
            : end($parts);

        return in_array($relativePage, $allowedPages);
    }

    /**
     * Enforce page permission - redirects with error if unauthorized
     */
    public static function enforcePermission() {
        global $current_user, $is_rtl, $base_url;

        $publicPages = ['login.php', 'install.php'];
        $currentPage = basename($_SERVER['PHP_SELF']);

        if (in_array($currentPage, $publicPages)) {
            return true;
        }

        if (empty($current_user)) {
            header("Location: " . $base_url . "login.php");
            exit;
        }

        $reqUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $cleanPage = basename($reqUri);
        if (strpos($reqUri, '/accounting/') !== false) {
            $cleanPage = 'accounting/' . $cleanPage;
        } elseif (strpos($reqUri, '/reports/') !== false) {
            $cleanPage = 'reports/' . $cleanPage;
        }

        if (!self::hasPermission($cleanPage, $current_user['role'])) {
            $_SESSION['flash_error'] = $is_rtl 
                ? 'رسائی ممنوع ہے: آپ کے اکاؤنٹ کو اس صفحے تک رسائی کی اجازت نہیں ہے۔' 
                : "Access Denied: Your account role ({$current_user['role']}) does not have permission to access '{$cleanPage}'.";
            header("Location: " . $base_url . "dashboard.php");
            exit;
        }

        return true;
    }
}

function can_access($page, $role = null) {
    return AuthHelper::hasPermission($page, $role);
}
