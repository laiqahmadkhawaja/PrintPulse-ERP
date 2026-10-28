<?php
/**
 * Application Core Initializer
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/db.php';

// Compute relative path to application root for assets, scripts, and links
$rootDir = str_replace('\\', '/', realpath(__DIR__ . '/..'));
$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_FILENAME'] ?? ''));
$rNorm = strtolower($rootDir);
$sNorm = strtolower($scriptDir);

if (strpos($sNorm, $rNorm) === 0) {
    $sub = trim(substr($sNorm, strlen($rNorm)), '/');
    $depth = ($sub === '') ? 0 : count(explode('/', $sub));
} else {
    $depth = 0;
}
$base_url = str_repeat('../', $depth);

// Language initialization
if (isset($_GET['lang']) && in_array($_GET['lang'], ['en', 'ur'])) {
    $_SESSION['lang'] = $_GET['lang'];
}
$current_lang = $_SESSION['lang'] ?? 'en';
$is_rtl = ($current_lang === 'ur');

$lang_file = __DIR__ . "/lang_{$current_lang}.php";
$L = file_exists($lang_file) ? require $lang_file : require __DIR__ . '/lang_en.php';

function __($key, $fallback = null) {
    global $L;
    if (isset($L[$key])) {
        return $L[$key];
    }
    return $fallback !== null ? $fallback : $key;
}

// CSRF Protection
function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf_token($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Security Escaping Helper
function h($string) {
    return htmlspecialchars((string)$string, ENT_QUOTES, 'UTF-8');
}

// Format Currency
function format_currency($amount, $showSymbol = true) {
    global $current_business;
    $sym = ($showSymbol && !empty($current_business['currency'])) ? $current_business['currency'] . ' ' : '';
    return $sym . number_format((float)$amount, 2);
}

// Format Date
function format_date($dateStr) {
    if (!$dateStr || $dateStr === '0000-00-00') return '-';
    return date('d-m-Y', strtotime($dateStr));
}

// Load Authenticated User & Business Context
$current_user = $_SESSION['user'] ?? null;
$current_business = null;
$current_settings = null;
$user_businesses = [];

if ($current_user) {
    $pdo = getDB();

    // Fetch businesses accessible by this user
    if ($current_user['role'] === 'platform_admin') {
        $stmt = $pdo->query("SELECT * FROM businesses WHERE status = 'active' ORDER BY name ASC");
        $user_businesses = $stmt->fetchAll();
    } else {
        $stmt = $pdo->prepare("SELECT b.* FROM businesses b 
                               JOIN user_businesses ub ON ub.business_id = b.id 
                               WHERE ub.user_id = ? AND b.status = 'active' 
                               ORDER BY b.name ASC");
        $stmt->execute([$current_user['id']]);
        $user_businesses = $stmt->fetchAll();
    }

    // Determine current business ID
    if (empty($_SESSION['current_business_id']) && !empty($user_businesses)) {
        $_SESSION['current_business_id'] = $user_businesses[0]['id'];
    }

    if (!empty($_SESSION['current_business_id'])) {
        $bid = (int)$_SESSION['current_business_id'];

        // Verify authorization
        $isAuthorized = false;
        foreach ($user_businesses as $ub) {
            if ($ub['id'] == $bid) {
                $isAuthorized = true;
                $current_business = $ub;
                break;
            }
        }

        if (!$isAuthorized && !empty($user_businesses)) {
            // Fallback to first authorized business
            $_SESSION['current_business_id'] = $user_businesses[0]['id'];
            $current_business = $user_businesses[0];
            $bid = $current_business['id'];
        }

        if ($current_business) {
            // Load business settings
            $stmt = $pdo->prepare("SELECT * FROM business_settings WHERE business_id = ?");
            $stmt->execute([$bid]);
            $current_settings = $stmt->fetch();

            if (!$current_settings) {
                // Insert default settings if missing
                $pdo->prepare("INSERT INTO business_settings (business_id, repair_job_management_enabled, refilling_job_management_enabled, inventory_enabled, barcode_enabled, serial_tracking_enabled, daily_closing_enabled) 
                               VALUES (?, 1, 1, 1, 1, 1, 1)")->execute([$bid]);
                $stmt->execute([$bid]);
                $current_settings = $stmt->fetch();
            }
        }
    }
}

// Function to log actions in audit trail
function log_audit($action, $entity_type = null, $entity_id = null, $details = null) {
    global $current_user, $current_business;
    $bid = $current_business['id'] ?? null;
    $uid = $current_user['id'] ?? null;
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

    try {
        $pdo = getDB();
        $pdo->prepare("INSERT INTO audit_logs (business_id, user_id, action, entity_type, entity_id, details, ip_address) 
                       VALUES (?, ?, ?, ?, ?, ?, ?)")
            ->execute([$bid, $uid, $action, $entity_type, $entity_id, is_array($details) ? json_encode($details) : $details, $ip]);
    } catch (Exception $e) {
        // Silent fail for audit logs to not block transaction
    }
}
