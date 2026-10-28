<?php
/**
 * Invoice Deletion Endpoint
 * Reverses all financial, stock, customer, and job impacts with audit logging.
 */

require_once __DIR__ . '/config/init.php';
require_once __DIR__ . '/includes/accounting_helper.php';
require_once __DIR__ . '/includes/inventory_helper.php';
require_once __DIR__ . '/includes/invoice_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die("Method Not Allowed");
}

if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $_SESSION['flash_error'] = $is_rtl ? 'سیکورٹی ٹوکن غلط ہے۔ دوبارہ کوشش کریں۔' : 'Security token mismatch. Please try again.';
    $redirect = $_POST['return_to'] ?? ($_SERVER['HTTP_REFERER'] ?? 'dashboard.php');
    header("Location: " . $redirect);
    exit;
}

$bid = (int)$current_business['id'];
$invoice_id = (int)($_POST['invoice_id'] ?? 0);
$reason = trim($_POST['reason'] ?? '');
$return_to = trim($_POST['return_to'] ?? '');
$isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');

try {
    // 1. Verify existence and authorization
    $data = InvoiceHelper::getInvoiceWithItems($invoice_id, $bid);
    $inv = $data['invoice'];
    $invNum = $inv['invoice_number'];
    $invType = $inv['invoice_type'];

    // 2. Perform safe deletion and ledger rollback
    InvoiceHelper::deleteInvoice($invoice_id, $bid, $current_user['id'], $reason);

    $msg = $is_rtl 
        ? "بل نمبر #{$invNum} کامیابی سے حذف ہو گیا اور تمام کھاتہ جاتی اور اسٹاک اندراجات بحال ہو گئیں۔" 
        : "Invoice #{$invNum} has been permanently deleted and all ledger entries, customer balance, and inventory stock have been safely restored.";

    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => $msg]);
        exit;
    }

    $_SESSION['flash_success'] = $msg;

    // Determine redirection
    if (!empty($return_to) && strpos($return_to, 'print_invoice.php') === false) {
        header("Location: " . $return_to);
    } elseif ($invType === 'repair') {
        header("Location: repair_invoices.php");
    } elseif ($invType === 'refill') {
        header("Location: refill_invoices.php");
    } else {
        header("Location: sales.php");
    }
    exit;

} catch (Exception $e) {
    $err = ($is_rtl ? 'بل حذف کرنے میں غلطی: ' : 'Failed to delete invoice: ') . $e->getMessage();

    if ($isAjax) {
        header('Content-Type: application/json; charset=utf-8', true, 400);
        echo json_encode(['success' => false, 'error' => $err]);
        exit;
    }

    $_SESSION['flash_error'] = $err;
    $redirect = !empty($return_to) ? $return_to : ($_SERVER['HTTP_REFERER'] ?? 'dashboard.php');
    header("Location: " . $redirect);
    exit;
}
