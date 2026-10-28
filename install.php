<?php
/**
 * PrintPulse ERP - Database Schema & Seeder
 */

$host = '127.0.0.1';
$port = '3306';
$user = 'root';
$pass = '';
$dbname = 'pshop_db';

try {
    $pdo = new PDO("mysql:host={$host};port={$port};charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    // Create database if not exists
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbname}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `{$dbname}`");

    echo "Database `{$dbname}` ready.\n";

    // SQL Schema
    $schema = "
    -- 1. Businesses
    CREATE TABLE IF NOT EXISTS `businesses` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `name` VARCHAR(191) NOT NULL,
        `slug` VARCHAR(100) NOT NULL UNIQUE,
        `logo_url` VARCHAR(255) DEFAULT NULL,
        `address` TEXT DEFAULT NULL,
        `city` VARCHAR(100) DEFAULT 'Gujranwala',
        `phone` VARCHAR(50) DEFAULT NULL,
        `cell` VARCHAR(100) DEFAULT NULL,
        `email` VARCHAR(100) DEFAULT NULL,
        `currency` VARCHAR(10) DEFAULT 'PKR',
        `financial_year_start` DATE DEFAULT '2026-01-01',
        `default_invoice_format` ENUM('A5', 'A4', '80mm') DEFAULT 'A5',
        `watermark_enabled` TINYINT(1) DEFAULT 1,
        `watermark_text` VARCHAR(100) DEFAULT NULL,
        `watermark_opacity` DECIMAL(3,2) DEFAULT 0.08,
        `watermark_rotation` INT DEFAULT -25,
        `watermark_mode` ENUM('text', 'logo') DEFAULT 'text',
        `invoice_notes` TEXT DEFAULT NULL,
        `bank_name` VARCHAR(100) DEFAULT NULL,
        `account_title` VARCHAR(150) DEFAULT NULL,
        `account_number` VARCHAR(50) DEFAULT NULL,
        `iban` VARCHAR(50) DEFAULT NULL,
        `branch_code` VARCHAR(50) DEFAULT NULL,
        `show_bank_on_invoice` TINYINT(1) DEFAULT 1,
        `status` ENUM('active', 'suspended') DEFAULT 'active',
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    -- 2. Business Settings & Feature Toggles
    CREATE TABLE IF NOT EXISTS `business_settings` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `business_id` INT NOT NULL,
        `repair_job_management_enabled` TINYINT(1) DEFAULT 1,
        `refilling_job_management_enabled` TINYINT(1) DEFAULT 1,
        `inventory_enabled` TINYINT(1) DEFAULT 1,
        `barcode_enabled` TINYINT(1) DEFAULT 1,
        `serial_tracking_enabled` TINYINT(1) DEFAULT 1,
        `daily_closing_enabled` TINYINT(1) DEFAULT 1,
        `tax_percentage` DECIMAL(5,2) DEFAULT 0.00,
        `invoice_prefix` VARCHAR(10) DEFAULT 'INV-',
        `repair_prefix` VARCHAR(10) DEFAULT 'REP-',
        `refill_prefix` VARCHAR(10) DEFAULT 'REF-',
        FOREIGN KEY (`business_id`) REFERENCES `businesses`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    -- 3. Users
    CREATE TABLE IF NOT EXISTS `users` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `name` VARCHAR(100) NOT NULL,
        `username` VARCHAR(50) NOT NULL UNIQUE,
        `password_hash` VARCHAR(255) NOT NULL,
        `email` VARCHAR(100) DEFAULT NULL,
        `phone` VARCHAR(50) DEFAULT NULL,
        `role` ENUM('platform_admin', 'business_owner', 'manager', 'accountant', 'technician', 'cashier') DEFAULT 'business_owner',
        `is_active` TINYINT(1) DEFAULT 1,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    -- 4. User Business Association
    CREATE TABLE IF NOT EXISTS `user_businesses` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT NOT NULL,
        `business_id` INT NOT NULL,
        `role_in_business` VARCHAR(50) DEFAULT 'owner',
        `is_default` TINYINT(1) DEFAULT 0,
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`business_id`) REFERENCES `businesses`(`id`) ON DELETE CASCADE,
        UNIQUE KEY `unique_user_business` (`user_id`, `business_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    -- 5. Chart of Accounts
    CREATE TABLE IF NOT EXISTS `chart_of_accounts` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `business_id` INT NOT NULL,
        `account_code` VARCHAR(20) NOT NULL,
        `account_name` VARCHAR(150) NOT NULL,
        `account_name_ur` VARCHAR(150) DEFAULT NULL,
        `account_type` ENUM('asset', 'liability', 'equity', 'income', 'cogs', 'expense') NOT NULL,
        `parent_id` INT DEFAULT NULL,
        `is_system` TINYINT(1) DEFAULT 1,
        `current_balance` DECIMAL(15,2) DEFAULT 0.00,
        `status` ENUM('active', 'inactive') DEFAULT 'active',
        FOREIGN KEY (`business_id`) REFERENCES `businesses`(`id`) ON DELETE CASCADE,
        UNIQUE KEY `unique_account_code_per_business` (`business_id`, `account_code`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    -- 6. Journal Entries
    CREATE TABLE IF NOT EXISTS `journal_entries` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `business_id` INT NOT NULL,
        `entry_number` VARCHAR(50) NOT NULL,
        `entry_date` DATE NOT NULL,
        `reference_type` ENUM('sale', 'purchase', 'repair', 'refill', 'receipt', 'payment', 'expense', 'voucher', 'opening') NOT NULL,
        `reference_id` INT DEFAULT NULL,
        `description` TEXT DEFAULT NULL,
        `total_debit` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        `total_credit` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        `created_by` INT DEFAULT NULL,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (`business_id`) REFERENCES `businesses`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    -- 7. Journal Entry Lines
    CREATE TABLE IF NOT EXISTS `journal_entry_lines` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `entry_id` INT NOT NULL,
        `account_id` INT NOT NULL,
        `debit` DECIMAL(15,2) DEFAULT 0.00,
        `credit` DECIMAL(15,2) DEFAULT 0.00,
        `narration` VARCHAR(255) DEFAULT NULL,
        FOREIGN KEY (`entry_id`) REFERENCES `journal_entries`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`account_id`) REFERENCES `chart_of_accounts`(`id`) ON DELETE RESTRICT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    -- 8. Customers
    CREATE TABLE IF NOT EXISTS `customers` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `business_id` INT NOT NULL,
        `name` VARCHAR(150) NOT NULL,
        `phone` VARCHAR(50) DEFAULT NULL,
        `email` VARCHAR(100) DEFAULT NULL,
        `address` TEXT DEFAULT NULL,
        `opening_balance` DECIMAL(12,2) DEFAULT 0.00,
        `current_balance` DECIMAL(12,2) DEFAULT 0.00,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (`business_id`) REFERENCES `businesses`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    -- 9. Suppliers
    CREATE TABLE IF NOT EXISTS `suppliers` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `business_id` INT NOT NULL,
        `name` VARCHAR(150) NOT NULL,
        `contact_person` VARCHAR(100) DEFAULT NULL,
        `phone` VARCHAR(50) DEFAULT NULL,
        `email` VARCHAR(100) DEFAULT NULL,
        `address` TEXT DEFAULT NULL,
        `opening_balance` DECIMAL(12,2) DEFAULT 0.00,
        `current_balance` DECIMAL(12,2) DEFAULT 0.00,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (`business_id`) REFERENCES `businesses`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    -- 10. Product Categories
    CREATE TABLE IF NOT EXISTS `categories` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `business_id` INT NOT NULL,
        `name` VARCHAR(100) NOT NULL,
        `name_ur` VARCHAR(100) DEFAULT NULL,
        `description` VARCHAR(255) DEFAULT NULL,
        FOREIGN KEY (`business_id`) REFERENCES `businesses`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    -- 11. Products / Inventory
    CREATE TABLE IF NOT EXISTS `products` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `business_id` INT NOT NULL,
        `category_id` INT DEFAULT NULL,
        `name` VARCHAR(150) NOT NULL,
        `name_ur` VARCHAR(150) DEFAULT NULL,
        `code_or_barcode` VARCHAR(50) DEFAULT NULL,
        `unit` VARCHAR(20) DEFAULT 'pcs',
        `purchase_price` DECIMAL(12,2) DEFAULT 0.00,
        `selling_price` DECIMAL(12,2) DEFAULT 0.00,
        `current_stock` DECIMAL(12,2) DEFAULT 0.00,
        `reorder_level` DECIMAL(12,2) DEFAULT 5.00,
        `product_type` ENUM('product', 'spare_part', 'consumable_powder_ink', 'service') DEFAULT 'product',
        `status` ENUM('active', 'inactive') DEFAULT 'active',
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (`business_id`) REFERENCES `businesses`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    -- 12. Stock Movements
    CREATE TABLE IF NOT EXISTS `stock_movements` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `business_id` INT NOT NULL,
        `product_id` INT NOT NULL,
        `movement_type` ENUM('purchase', 'sale', 'repair_part', 'refill_consumption', 'adjustment_in', 'adjustment_out', 'initial') NOT NULL,
        `reference_id` INT DEFAULT NULL,
        `quantity` DECIMAL(12,2) NOT NULL,
        `unit_cost` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        `notes` VARCHAR(255) DEFAULT NULL,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (`business_id`) REFERENCES `businesses`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    -- 13. Service Descriptions (Autocomplete Catalog)
    CREATE TABLE IF NOT EXISTS `service_descriptions` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `business_id` INT NOT NULL,
        `description` VARCHAR(255) NOT NULL,
        `description_ur` VARCHAR(255) DEFAULT NULL,
        `default_rate` DECIMAL(12,2) DEFAULT 0.00,
        `line_type` ENUM('service', 'labour', 'refill', 'repair', 'part') DEFAULT 'service',
        `usage_count` INT DEFAULT 1,
        `last_used_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (`business_id`) REFERENCES `businesses`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    -- 14. Repair Jobs (Workflow)
    CREATE TABLE IF NOT EXISTS `repair_jobs` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `business_id` INT NOT NULL,
        `job_number` VARCHAR(50) NOT NULL,
        `customer_id` INT DEFAULT NULL,
        `customer_name` VARCHAR(150) NOT NULL,
        `customer_phone` VARCHAR(50) DEFAULT NULL,
        `printer_brand` VARCHAR(100) DEFAULT NULL,
        `printer_model` VARCHAR(100) DEFAULT NULL,
        `serial_number` VARCHAR(100) DEFAULT NULL,
        `accessories_received` VARCHAR(255) DEFAULT NULL,
        `fault_reported` TEXT DEFAULT NULL,
        `technician_notes` TEXT DEFAULT NULL,
        `assigned_to` INT DEFAULT NULL,
        `estimated_cost` DECIMAL(12,2) DEFAULT 0.00,
        `final_cost` DECIMAL(12,2) DEFAULT 0.00,
        `advance_paid` DECIMAL(12,2) DEFAULT 0.00,
        `status` ENUM('received', 'diagnosing', 'waiting_parts', 'repaired', 'delivered', 'cancelled') DEFAULT 'received',
        `received_date` DATE NOT NULL,
        `delivery_date` DATE DEFAULT NULL,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (`business_id`) REFERENCES `businesses`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    -- 15. Refill Jobs (Workflow)
    CREATE TABLE IF NOT EXISTS `refill_jobs` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `business_id` INT NOT NULL,
        `job_number` VARCHAR(50) NOT NULL,
        `customer_id` INT DEFAULT NULL,
        `customer_name` VARCHAR(150) NOT NULL,
        `customer_phone` VARCHAR(50) DEFAULT NULL,
        `cartridge_model` VARCHAR(100) NOT NULL,
        `cartridge_type` ENUM('toner', 'ink', 'ribbon') DEFAULT 'toner',
        `service_type` VARCHAR(100) DEFAULT 'Refill',
        `weight_before` VARCHAR(50) DEFAULT NULL,
        `weight_after` VARCHAR(50) DEFAULT NULL,
        `test_print_status` ENUM('pending', 'passed', 'poor') DEFAULT 'passed',
        `charges` DECIMAL(12,2) DEFAULT 0.00,
        `paid_amount` DECIMAL(12,2) DEFAULT 0.00,
        `status` ENUM('received', 'in_process', 'ready', 'delivered') DEFAULT 'ready',
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (`business_id`) REFERENCES `businesses`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    -- 16. Invoices (Universal for Sale, Repair, Refill, POS)
    CREATE TABLE IF NOT EXISTS `invoices` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `business_id` INT NOT NULL,
        `invoice_type` ENUM('sale', 'repair', 'refill', 'pos') DEFAULT 'repair',
        `invoice_number` VARCHAR(50) NOT NULL,
        `customer_id` INT DEFAULT NULL,
        `customer_name` VARCHAR(150) NOT NULL,
        `customer_phone` VARCHAR(50) DEFAULT NULL,
        `invoice_date` DATE NOT NULL,
        `subtotal` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        `discount` DECIMAL(12,2) DEFAULT 0.00,
        `tax_amount` DECIMAL(12,2) DEFAULT 0.00,
        `total_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        `paid_amount` DECIMAL(12,2) DEFAULT 0.00,
        `balance_amount` DECIMAL(12,2) DEFAULT 0.00,
        `payment_method` ENUM('cash', 'bank', 'credit', 'split') DEFAULT 'cash',
        `reference_job_id` INT DEFAULT NULL,
        `notes` TEXT DEFAULT NULL,
        `created_by` INT DEFAULT NULL,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (`business_id`) REFERENCES `businesses`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    -- 17. Invoice Items
    CREATE TABLE IF NOT EXISTS `invoice_items` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `invoice_id` INT NOT NULL,
        `line_number` INT DEFAULT 1,
        `line_type` ENUM('service', 'labour', 'product', 'part', 'refill') DEFAULT 'service',
        `product_id` INT DEFAULT NULL,
        `description` VARCHAR(255) NOT NULL,
        `quantity` DECIMAL(12,2) NOT NULL DEFAULT 1.00,
        `unit_price` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        `total_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        FOREIGN KEY (`invoice_id`) REFERENCES `invoices`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    -- 18. Purchases (Supplier Bills)
    CREATE TABLE IF NOT EXISTS `purchases` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `business_id` INT NOT NULL,
        `purchase_number` VARCHAR(50) NOT NULL,
        `supplier_id` INT NOT NULL,
        `supplier_bill_no` VARCHAR(100) DEFAULT NULL,
        `purchase_date` DATE NOT NULL,
        `total_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        `paid_amount` DECIMAL(12,2) DEFAULT 0.00,
        `balance_amount` DECIMAL(12,2) DEFAULT 0.00,
        `notes` TEXT DEFAULT NULL,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (`business_id`) REFERENCES `businesses`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`supplier_id`) REFERENCES `suppliers`(`id`) ON DELETE RESTRICT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    -- 19. Purchase Items
    CREATE TABLE IF NOT EXISTS `purchase_items` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `purchase_id` INT NOT NULL,
        `product_id` INT NOT NULL,
        `quantity` DECIMAL(12,2) NOT NULL DEFAULT 1.00,
        `purchase_price` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        `total_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        FOREIGN KEY (`purchase_id`) REFERENCES `purchases`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE RESTRICT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    -- 20. Payments (Customer Receipts / Supplier Payments)
    CREATE TABLE IF NOT EXISTS `payments` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `business_id` INT NOT NULL,
        `payment_type` ENUM('customer_receipt', 'supplier_payment') NOT NULL,
        `voucher_number` VARCHAR(50) NOT NULL,
        `party_type` ENUM('customer', 'supplier') NOT NULL,
        `party_id` INT NOT NULL,
        `account_id` INT NOT NULL,
        `payment_date` DATE NOT NULL,
        `amount` DECIMAL(12,2) NOT NULL,
        `payment_method` ENUM('cash', 'bank_transfer', 'cheque', 'online') DEFAULT 'cash',
        `reference_invoice_id` INT DEFAULT NULL,
        `notes` TEXT DEFAULT NULL,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (`business_id`) REFERENCES `businesses`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`account_id`) REFERENCES `chart_of_accounts`(`id`) ON DELETE RESTRICT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    -- 21. Expense Categories
    CREATE TABLE IF NOT EXISTS `expense_categories` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `business_id` INT NOT NULL,
        `name` VARCHAR(100) NOT NULL,
        `name_ur` VARCHAR(100) DEFAULT NULL,
        `account_id` INT DEFAULT NULL,
        `status` ENUM('active', 'inactive') DEFAULT 'active',
        FOREIGN KEY (`business_id`) REFERENCES `businesses`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`account_id`) REFERENCES `chart_of_accounts`(`id`) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    -- 22. Expenses
    CREATE TABLE IF NOT EXISTS `expenses` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `business_id` INT NOT NULL,
        `voucher_number` VARCHAR(50) NOT NULL,
        `category_id` INT NOT NULL,
        `account_id` INT NOT NULL,
        `payment_account_id` INT NOT NULL,
        `expense_date` DATE NOT NULL,
        `amount` DECIMAL(12,2) NOT NULL,
        `payee_name` VARCHAR(150) DEFAULT NULL,
        `description` TEXT DEFAULT NULL,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (`business_id`) REFERENCES `businesses`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`category_id`) REFERENCES `expense_categories`(`id`) ON DELETE RESTRICT,
        FOREIGN KEY (`account_id`) REFERENCES `chart_of_accounts`(`id`) ON DELETE RESTRICT,
        FOREIGN KEY (`payment_account_id`) REFERENCES `chart_of_accounts`(`id`) ON DELETE RESTRICT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    -- 23. Double-Entry Vouchers (JV, CPV, CRV, BPV, BRV, Contra)
    CREATE TABLE IF NOT EXISTS `vouchers` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `business_id` INT NOT NULL,
        `voucher_type` ENUM('JV', 'CPV', 'CRV', 'BPV', 'BRV', 'CONTRA') NOT NULL,
        `voucher_number` VARCHAR(50) NOT NULL,
        `voucher_date` DATE NOT NULL,
        `total_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        `narration` TEXT DEFAULT NULL,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (`business_id`) REFERENCES `businesses`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    -- 24. Daily Closings
    CREATE TABLE IF NOT EXISTS `daily_closings` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `business_id` INT NOT NULL,
        `closing_date` DATE NOT NULL,
        `opening_cash` DECIMAL(12,2) DEFAULT 0.00,
        `cash_sales` DECIMAL(12,2) DEFAULT 0.00,
        `cash_repairs` DECIMAL(12,2) DEFAULT 0.00,
        `cash_refills` DECIMAL(12,2) DEFAULT 0.00,
        `customer_receipts` DECIMAL(12,2) DEFAULT 0.00,
        `supplier_payments` DECIMAL(12,2) DEFAULT 0.00,
        `cash_expenses` DECIMAL(12,2) DEFAULT 0.00,
        `expected_cash` DECIMAL(12,2) DEFAULT 0.00,
        `actual_cash` DECIMAL(12,2) DEFAULT 0.00,
        `difference` DECIMAL(12,2) DEFAULT 0.00,
        `closed_by` INT DEFAULT NULL,
        `status` ENUM('open', 'closed') DEFAULT 'closed',
        `notes` TEXT DEFAULT NULL,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (`business_id`) REFERENCES `businesses`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    -- 25. Audit Logs
    CREATE TABLE IF NOT EXISTS `audit_logs` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `business_id` INT DEFAULT NULL,
        `user_id` INT DEFAULT NULL,
        `action` VARCHAR(100) NOT NULL,
        `entity_type` VARCHAR(50) DEFAULT NULL,
        `entity_id` INT DEFAULT NULL,
        `details` TEXT DEFAULT NULL,
        `ip_address` VARCHAR(50) DEFAULT NULL,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";

    $pdo->exec($schema);
    echo "All tables created successfully.\n";

    // Seed Core Admin User
    $adminPasswordHash = password_hash('admin123', PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = 'admin'");
    $stmt->execute();
    if (!$stmt->fetch()) {
        $pdo->prepare("INSERT INTO users (name, username, password_hash, email, role, is_active) 
                       VALUES ('System Administrator', 'admin', ?, 'admin@printpulse.local', 'platform_admin', 1)")
            ->execute([$adminPasswordHash]);
        echo "Admin user created: admin / admin123\n";
    }

    // Seed Business 1: FAST PRINTERS & COMPUTERS (Matching reference invoice)
    $stmt = $pdo->prepare("SELECT id FROM businesses WHERE slug = 'fast-printers'");
    $stmt->execute();
    $biz1 = $stmt->fetch();
    if (!$biz1) {
        $pdo->prepare("INSERT INTO businesses (name, slug, city, address, phone, cell, email, currency, default_invoice_format, watermark_enabled, watermark_text, watermark_opacity, watermark_rotation, invoice_notes) 
            VALUES (
                'FAST PRINTERS & COMPUTERS', 
                'fast-printers', 
                'Gujranwala', 
                'Justice Deen Computer Market, Muneer Chowk, Gujranwala.', 
                '0300-2738648', 
                '0306-6400173, 0306-1700173, 0300-2738648', 
                'sajidnawab0088@gmail.com', 
                'Rs', 
                'A5', 
                1, 
                'FAST PRINTERS', 
                0.08, 
                -25, 
                'RECONDITIONED OR REPAIRED GOODS ONCE SOLD ARE NOT RETURNABLE NOR EXCHANGEABLE. NO WARRANTY OR GUARANTEE FOR REPAIRED ACCESSORIES.'
            )")->execute();
        $biz1_id = $pdo->lastInsertId();
    } else {
        $biz1_id = $biz1['id'];
    }

    // Seed Business 2: AL-MADINA PRINT & TONER SOLUTIONS (for multi-tenant isolation demonstration)
    $stmt = $pdo->prepare("SELECT id FROM businesses WHERE slug = 'al-madina'");
    $stmt->execute();
    $biz2 = $stmt->fetch();
    if (!$biz2) {
        $pdo->prepare("INSERT INTO businesses (name, slug, city, address, phone, cell, email, currency, default_invoice_format, watermark_enabled, watermark_text, watermark_opacity, watermark_rotation, invoice_notes) 
            VALUES (
                'AL-MADINA PRINT & TONER SOLUTIONS', 
                'al-madina', 
                'Lahore', 
                'Shop 14, Hafeez Center, Gulberg III, Lahore.', 
                '042-35712345', 
                '0321-9876543, 0333-1122334', 
                'info@almadinaprint.com', 
                'Rs', 
                'A5', 
                1, 
                'AL-MADINA', 
                0.08, 
                -25, 
                'All printer repair works carry 7 days checking warranty. Refilled cartridges are pre-tested before delivery.'
            )")->execute();
        $biz2_id = $pdo->lastInsertId();
    } else {
        $biz2_id = $biz2['id'];
    }

    // Associate admin user with both businesses
    $adminId = $pdo->query("SELECT id FROM users WHERE username = 'admin'")->fetchColumn();
    $pdo->prepare("INSERT IGNORE INTO user_businesses (user_id, business_id, role_in_business, is_default) VALUES (?, ?, 'owner', 1)")
        ->execute([$adminId, $biz1_id]);
    $pdo->prepare("INSERT IGNORE INTO user_businesses (user_id, business_id, role_in_business, is_default) VALUES (?, ?, 'owner', 0)")
        ->execute([$adminId, $biz2_id]);

    // Ensure business settings exist
    foreach ([$biz1_id, $biz2_id] as $bid) {
        $stmt = $pdo->prepare("SELECT id FROM business_settings WHERE business_id = ?");
        $stmt->execute([$bid]);
        if (!$stmt->fetch()) {
            $pdo->prepare("INSERT INTO business_settings (business_id, repair_job_management_enabled, refilling_job_management_enabled, inventory_enabled, barcode_enabled, serial_tracking_enabled, daily_closing_enabled, tax_percentage)
                           VALUES (?, 1, 1, 1, 1, 1, 1, 0.00)")->execute([$bid]);
        }
    }

    // Helper function to seed Chart of Accounts
    function seedCoA($pdo, $bid) {
        $accounts = [
            // Assets (1000)
            ['1010', 'Cash in Hand', 'نقد رقم', 'asset', null, 0],
            ['1020', 'Bank Account', 'بینک اکاؤنٹ', 'asset', null, 0],
            ['1030', 'Accounts Receivable (Customers)', 'گاہکوں کے بقایا جات', 'asset', null, 0],
            ['1040', 'Inventory / Stock on Hand', 'اسٹاک ان ہینڈ', 'asset', null, 0],
            // Liabilities (2000)
            ['2010', 'Accounts Payable (Suppliers)', 'سپلائرز کے واجبات', 'liability', null, 0],
            ['2020', 'Customer Advances', 'گاہکوں کی پیشگی رقوم', 'liability', null, 0],
            // Equity (3000)
            ['3010', "Owner's Capital", 'مالک کا سرمایہ', 'equity', null, 0],
            ['3020', 'Retained Earnings', 'محفوظ منافع', 'equity', null, 0],
            // Income (4000)
            ['4010', 'Product Sales Revenue', 'سامان کی فروخت سے آمدن', 'income', null, 0],
            ['4020', 'Printer Repairing Revenue', 'پرنٹر مرمت کی آمدن', 'income', null, 0],
            ['4030', 'Cartridge Refilling Revenue', 'کارٹریج ریفلنگ کی آمدن', 'income', null, 0],
            ['4040', 'Service & Inspection Charges', 'سروس اور معائنہ فیس', 'income', null, 0],
            // COGS (5000)
            ['5010', 'Cost of Goods Sold - Products', 'فروخت شدہ سامان کی لاگت', 'cogs', null, 0],
            ['5020', 'Raw Materials (Toner, Ink & Parts Consumed)', 'خام مال و پرزہ جات کی لاگت', 'cogs', null, 0],
            // Expenses (6000)
            ['6010', 'Shop Rent', 'دکان کا کرایہ', 'expense', null, 0],
            ['6020', 'Electricity & Utilities', 'بجلی اور گیس کے بل', 'expense', null, 0],
            ['6030', 'Salaries & Technician Wages', 'تنخواہیں اور کاریگروں کی اجرت', 'expense', null, 0],
            ['6040', 'Internet & Phone', 'انٹرنیٹ اور فون بل', 'expense', null, 0],
            ['6050', 'Shop Maintenance & Cleaning', 'دکان کی دیکھ بھال و صفائی', 'expense', null, 0],
            ['6060', 'Tea & Refreshments', 'چائے اور ضیافت', 'expense', null, 0],
            ['6070', 'Printing & Stationery', 'پرنٹنگ اور دفتری سامان', 'expense', null, 0],
            ['6090', 'Miscellaneous Expenses', 'متفرق اخراجات', 'expense', null, 0],
        ];

        foreach ($accounts as $acc) {
            $stmt = $pdo->prepare("SELECT id FROM chart_of_accounts WHERE business_id = ? AND account_code = ?");
            $stmt->execute([$bid, $acc[0]]);
            if (!$stmt->fetch()) {
                $pdo->prepare("INSERT INTO chart_of_accounts (business_id, account_code, account_name, account_name_ur, account_type, parent_id, is_system, current_balance) 
                               VALUES (?, ?, ?, ?, ?, ?, 1, ?)")
                    ->execute([$bid, $acc[0], $acc[1], $acc[2], $acc[3], $acc[4], $acc[5]]);
            }
        }
    }

    seedCoA($pdo, $biz1_id);
    seedCoA($pdo, $biz2_id);
    echo "Chart of Accounts seeded for both businesses.\n";

    // Helper to seed standard Service Descriptions (for autocomplete)
    function seedServiceDescriptions($pdo, $bid) {
        $services = [
            ['Toner Refill (Standard)', 'ٹونر ریفل (معیاری)', 800.00, 'refill', 15],
            ['HP Toner 81X Refill', 'ایچ پی ٹونر 81 ایکس ریفل', 2200.00, 'refill', 25],
            ['OPC Drum Replacement', 'ڈرم کی تبدیلی', 700.00, 'part', 20],
            ['Wiper Blade Replacement', 'وائپر بلیڈ کی تبدیلی', 400.00, 'part', 18],
            ['Doctor Blade Replacement', 'ڈاکٹر بلیڈ تبدیلی', 450.00, 'part', 8],
            ['PCR (Primary Charge Roller) Change', 'پی سی آر رولر تبدیلی', 500.00, 'part', 12],
            ['Magnetic Roller Change', 'میگنیٹک رولر تبدیلی', 600.00, 'part', 5],
            ['Laser Jet Printer General Servicing', 'لیزر جیٹ پرنٹر جنرل سروس', 1500.00, 'service', 14],
            ['Paper Pickup Roller Replacement', 'کاغذ کھینچنے والا رولر تبدیلی', 650.00, 'repair', 11],
            ['Fuser Unit Repair / Teflon Sleeve Change', 'فیوزر یونٹ ٹیفلون تبدیلی', 2500.00, 'repair', 9],
            ['Formatter Board Repairing', 'مین لاجک بورڈ مرمت', 3000.00, 'repair', 6],
            ['Toner Cartridge Chip Replacement', 'کارٹریج چپ تبدیلی', 350.00, 'part', 7],
            ['Printer Head Cleaning / Unclogging', 'پرنٹر ہیڈ صفائی', 1200.00, 'service', 16],
            ['Labour Charges', 'لیبر / کاریگری چارجز', 500.00, 'labour', 30],
        ];

        foreach ($services as $srv) {
            $stmt = $pdo->prepare("SELECT id FROM service_descriptions WHERE business_id = ? AND description = ?");
            $stmt->execute([$bid, $srv[0]]);
            if (!$stmt->fetch()) {
                $pdo->prepare("INSERT INTO service_descriptions (business_id, description, description_ur, default_rate, line_type, usage_count) 
                               VALUES (?, ?, ?, ?, ?, ?)")
                    ->execute([$bid, $srv[0], $srv[1], $srv[2], $srv[3], $srv[4]]);
            }
        }
    }

    seedServiceDescriptions($pdo, $biz1_id);
    seedServiceDescriptions($pdo, $biz2_id);
    echo "Service Descriptions catalogue seeded.\n";

    // Helper to seed Categories & Products
    function seedCatalog($pdo, $bid) {
        $cats = [
            ['Laser Printers', 'لیزر پرنٹرز'],
            ['Toner Cartridges', 'ٹونر کارٹریجز'],
            ['Spare Parts', 'پرنٹر اسپیئر پارٹس'],
            ['Inks & Powders', 'سیاہی و ٹونر پاؤڈر'],
            ['Computer Accessories', 'کمپیوٹر اشیاء'],
        ];

        $catIds = [];
        foreach ($cats as $c) {
            $stmt = $pdo->prepare("SELECT id FROM categories WHERE business_id = ? AND name = ?");
            $stmt->execute([$bid, $c[0]]);
            $row = $stmt->fetch();
            if ($row) {
                $catIds[$c[0]] = $row['id'];
            } else {
                $pdo->prepare("INSERT INTO categories (business_id, name, name_ur) VALUES (?, ?, ?)")
                    ->execute([$bid, $c[0], $c[1]]);
                $catIds[$c[0]] = $pdo->lastInsertId();
            }
        }

        $products = [
            ['HP LaserJet Pro M404dn (Reconditioned)', 'ایچ پی لیزر جیٹ ایم 404', 'PRN-HP-404', 'pcs', 32000.00, 38500.00, 4.00, $catIds['Laser Printers'], 'product'],
            ['Canon LBP 2900B Laser Printer', 'کینن ایل بی پی 2900 بی', 'PRN-CAN-2900', 'pcs', 26000.00, 31000.00, 3.00, $catIds['Laser Printers'], 'product'],
            ['HP 85A Compatible Toner Cartridge', 'ایچ پی 85 اے ٹونر کارٹریج', 'TON-HP-85A', 'pcs', 1400.00, 1950.00, 25.00, $catIds['Toner Cartridges'], 'product'],
            ['HP 81X High Yield Black Toner', 'ایچ پی 81 ایکس ٹونر', 'TON-HP-81X', 'pcs', 5500.00, 7200.00, 8.00, $catIds['Toner Cartridges'], 'product'],
            ['OPC Drum 12A / 85A / 78A (Long Life)', 'او پی سی ڈرم', 'PART-DRUM-85A', 'pcs', 350.00, 700.00, 50.00, $catIds['Spare Parts'], 'spare_part'],
            ['Wiper Blade 12A / 85A (Silicone)', 'وائپر بلیڈ سلیکون', 'PART-BLADE-85A', 'pcs', 180.00, 400.00, 40.00, $catIds['Spare Parts'], 'spare_part'],
            ['LaserJet Premium Toner Powder (1000g Foil)', 'پریمیم ٹونر پاؤڈر 1000 گرام', 'POW-HP-1KG', 'bottle', 1800.00, 2800.00, 15.00, $catIds['Inks & Powders'], 'consumable_powder_ink'],
            ['Printer Pickup Roller for HP 1020 / P1102', 'پک اپ رولر', 'PART-PICK-1102', 'pcs', 250.00, 650.00, 20.00, $catIds['Spare Parts'], 'spare_part'],
            ['High Speed USB 2.0 Printer Cable (3m)', 'پرنٹر کیبل 3 میٹر', 'ACC-USB-3M', 'pcs', 180.00, 350.00, 30.00, $catIds['Computer Accessories'], 'product'],
        ];

        foreach ($products as $p) {
            $stmt = $pdo->prepare("SELECT id FROM products WHERE business_id = ? AND code_or_barcode = ?");
            $stmt->execute([$bid, $p[2]]);
            if (!$stmt->fetch()) {
                $pdo->prepare("INSERT INTO products (business_id, name, name_ur, code_or_barcode, unit, purchase_price, selling_price, current_stock, category_id, product_type) 
                               VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")
                    ->execute([$bid, $p[0], $p[1], $p[2], $p[3], $p[4], $p[5], $p[6], $p[7], $p[8]]);
            }
        }
    }

    seedCatalog($pdo, $biz1_id);
    seedCatalog($pdo, $biz2_id);
    echo "Categories & Products catalog seeded.\n";

    // Seed Customers & Suppliers for Business 1
    $stmt = $pdo->prepare("SELECT id FROM customers WHERE business_id = ? AND name = 'Haji Muhammad Aslam'");
    $stmt->execute([$biz1_id]);
    if (!$stmt->fetch()) {
        $pdo->prepare("INSERT INTO customers (business_id, name, phone, email, address, opening_balance, current_balance) 
                       VALUES (?, 'Haji Muhammad Aslam', '0300-9641234', 'aslam.traders@gmail.com', 'Rail Bazaar, Gujranwala', 0, 0)")
            ->execute([$biz1_id]);
        $c1_id = $pdo->lastInsertId();

        $pdo->prepare("INSERT INTO customers (business_id, name, phone, email, address, opening_balance, current_balance) 
                       VALUES (?, 'Crown Printing Agency', '0321-7485960', 'crown.printers@yahoo.com', 'Trust Plaza, Model Town, Gujranwala', 0, 0)")
            ->execute([$biz1_id]);

        $pdo->prepare("INSERT INTO suppliers (business_id, name, contact_person, phone, email, address, opening_balance, current_balance) 
                       VALUES (?, 'National Toner & Cartridge Importers', 'Sheikh Tariq', '0300-8451234', 'sales@nationaltoner.pk', 'Brandreth Road, Lahore', 0, 0)")
            ->execute([$biz1_id]);
    }

    // Seed Expense Categories
    function seedExpenseCats($pdo, $bid) {
        $cats = [
            ['Shop Rent', 'دکان کا کرایہ', '6010'],
            ['Electricity & Power', 'بجلی کا بل', '6020'],
            ['Staff Salaries', 'عملے کی تنخواہیں', '6030'],
            ['Internet & Mobile', 'انٹرنیٹ و موبائل', '6040'],
            ['Shop Maintenance & Tools', 'دکان کی مرمت و اوزار', '6050'],
            ['Refreshments & Tea', 'چائے و تواضع', '6060'],
            ['Miscellaneous', 'متفرق اخراجات', '6090'],
        ];

        foreach ($cats as $ec) {
            $stmt = $pdo->prepare("SELECT id FROM chart_of_accounts WHERE business_id = ? AND account_code = ?");
            $stmt->execute([$bid, $ec[2]]);
            $accId = $stmt->fetchColumn();

            $stmtCheck = $pdo->prepare("SELECT id FROM expense_categories WHERE business_id = ? AND name = ?");
            $stmtCheck->execute([$bid, $ec[0]]);
            if (!$stmtCheck->fetch()) {
                $pdo->prepare("INSERT INTO expense_categories (business_id, name, name_ur, account_id) VALUES (?, ?, ?, ?)")
                    ->execute([$bid, $ec[0], $ec[1], $accId]);
            }
        }
    }

    seedExpenseCats($pdo, $biz1_id);
    seedExpenseCats($pdo, $biz2_id);
    echo "Expense categories seeded.\n";

    // Seed the EXACT Reference Invoice from the uploaded user image into Business 1!
    // Image details:
    // Business: FAST PRINTERS & COMPUTERS
    // Date: 20-10-2026
    // Description line 1: HP Toner 81X
    // Line 2: Refill | Qty: 01 | Rate: 2200 | Amount: 2200
    // Line 3: Drum   | Qty: 01 | Rate: 700  | Amount: 700
    // Line 4: Blade  | Qty: 01 | Rate: 400  | Amount: 400
    // Total: 3300/-
    // Deposit: 0 (or Paid)
    // Balance: 3300 (or as recorded)
    $stmt = $pdo->prepare("SELECT id FROM invoices WHERE business_id = ? AND invoice_number = 'INV-1001'");
    $stmt->execute([$biz1_id]);
    if (!$stmt->fetch()) {
        $custStmt = $pdo->prepare("SELECT id FROM customers WHERE business_id = ? LIMIT 1");
        $custStmt->execute([$biz1_id]);
        $custId = $custStmt->fetchColumn() ?: 1;

        $pdo->prepare("INSERT INTO invoices (business_id, invoice_type, invoice_number, customer_id, customer_name, customer_phone, invoice_date, subtotal, discount, tax_amount, total_amount, paid_amount, balance_amount, payment_method, notes) 
                       VALUES (?, 'repair', 'INV-1001', ?, 'M/s Sajid Nawab Traders', '0306-6400173', '2026-10-20', 3300.00, 0.00, 0.00, 3300.00, 3300.00, 0.00, 'cash', 'RECONDITIONED OR REPAIRED GOODS ONCE SOLD ARE NOT RETURNABLE NOR EXCHANGEABLE. NO WARRANTY OR GUARANTEE FOR REPAIRED ACCESSORIES.')")
            ->execute([$biz1_id, $custId]);
        $invId = $pdo->lastInsertId();

        // Items
        $items = [
            ['refill', 'HP Toner 81X Refill', 1, 2200.00, 2200.00],
            ['part', 'Drum (OPC Long Life)', 1, 700.00, 700.00],
            ['part', 'Blade (Wiper)', 1, 400.00, 400.00],
        ];
        $lno = 1;
        foreach ($items as $it) {
            $pdo->prepare("INSERT INTO invoice_items (invoice_id, line_number, line_type, description, quantity, unit_price, total_amount) 
                           VALUES (?, ?, ?, ?, ?, ?, ?)")
                ->execute([$invId, $lno++, $it[0], $it[1], $it[2], $it[3], $it[4]]);
        }

        // Post Double-Entry Accounting Journal for this invoice:
        // Cash Account (1010): Debit 3300
        // Cartridge Refilling Revenue (4030): Credit 2200
        // Product/Part Sales Revenue (4010): Credit 1100
        // Total Debit (3300) == Total Credit (3300)
        $cashAccId = $pdo->query("SELECT id FROM chart_of_accounts WHERE business_id = {$biz1_id} AND account_code = '1010'")->fetchColumn();
        $refillRevAccId = $pdo->query("SELECT id FROM chart_of_accounts WHERE business_id = {$biz1_id} AND account_code = '4030'")->fetchColumn();
        $partRevAccId = $pdo->query("SELECT id FROM chart_of_accounts WHERE business_id = {$biz1_id} AND account_code = '4010'")->fetchColumn();

        $pdo->prepare("INSERT INTO journal_entries (business_id, entry_number, entry_date, reference_type, reference_id, description, total_debit, total_credit, created_by) 
                       VALUES (?, 'JV-INV-1001', '2026-10-20', 'repair', ?, 'Invoice INV-1001 for M/s Sajid Nawab Traders', 3300.00, 3300.00, ?)")
            ->execute([$biz1_id, $invId, $adminId]);
        $entryId = $pdo->lastInsertId();

        // Debit Cash
        $pdo->prepare("INSERT INTO journal_entry_lines (entry_id, account_id, debit, credit, narration) VALUES (?, ?, 3300.00, 0.00, 'Cash received for INV-1001')")
            ->execute([$entryId, $cashAccId]);
        // Credit Refill Revenue
        $pdo->prepare("INSERT INTO journal_entry_lines (entry_id, account_id, debit, credit, narration) VALUES (?, ?, 0.00, 2200.00, 'HP Toner 81X Refill service')")
            ->execute([$entryId, $refillRevAccId]);
        // Credit Parts Revenue
        $pdo->prepare("INSERT INTO journal_entry_lines (entry_id, account_id, debit, credit, narration) VALUES (?, ?, 0.00, 1100.00, 'Drum & Blade parts')")
            ->execute([$entryId, $partRevAccId]);

        // Update Account Balances
        $pdo->exec("UPDATE chart_of_accounts SET current_balance = current_balance + 3300.00 WHERE id = {$cashAccId}");
        $pdo->exec("UPDATE chart_of_accounts SET current_balance = current_balance + 2200.00 WHERE id = {$refillRevAccId}");
        $pdo->exec("UPDATE chart_of_accounts SET current_balance = current_balance + 1100.00 WHERE id = {$partRevAccId}");

        echo "Reference invoice INV-1001 & balanced journal entry created.\n";
    }

    echo "Installation & Seeding completed successfully!\n";

} catch (Exception $e) {
    echo "ERROR during installation: " . $e->getMessage() . "\n";
    exit(1);
}
