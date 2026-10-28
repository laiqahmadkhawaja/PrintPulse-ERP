<?php
require_once __DIR__ . '/../config/init.php';

header('Content-Type: application/json');

if (empty($current_user)) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$target_bid = (int)($_POST['business_id'] ?? 0);
if ($target_bid <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid business ID']);
    exit;
}

// Verify authorization for current user
$isAuthorized = false;
foreach ($user_businesses as $b) {
    if ((int)$b['id'] === $target_bid) {
        $isAuthorized = true;
        break;
    }
}

if (($current_user['role'] ?? '') !== 'platform_admin') {
    echo json_encode(['success' => false, 'message' => 'Switching businesses is restricted to the SaaS Platform Administrator.']);
    exit;
}

// Set new session context
$_SESSION['current_business_id'] = $target_bid;
log_audit('SWITCH_BUSINESS', 'business', $target_bid, "User switched context to business #{$target_bid}");

echo json_encode(['success' => true, 'business_id' => $target_bid]);
