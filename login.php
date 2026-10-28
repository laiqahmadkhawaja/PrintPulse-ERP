<?php
require_once __DIR__ . '/config/init.php';

$error = '';

if (!empty($current_user)) {
    header("Location: dashboard.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = $is_rtl ? 'براہ کرم یوزر نیم اور پاس ورڈ درج کریں۔' : 'Please provide username and password.';
    } else {
        $pdo = getDB();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND is_active = 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            if (isset($user['is_active']) && (int)$user['is_active'] === 0) {
                $error = $is_rtl ? 'آپ کا اکاؤنٹ غیر فعال ہے۔ براہ کرم ایڈمن سے رابطہ کریں۔' : 'Your account is deactivated. Please contact your administrator.';
            } else {
                $_SESSION['user'] = [
                    'id' => $user['id'],
                    'name' => $user['name'],
                    'username' => $user['username'],
                    'email' => $user['email'],
                    'role' => $user['role'],
                ];

            // Set default business
            $stmtBiz = $pdo->prepare("SELECT business_id FROM user_businesses WHERE user_id = ? ORDER BY is_default DESC LIMIT 1");
            $stmtBiz->execute([$user['id']]);
            $defaultBiz = $stmtBiz->fetchColumn();

            if ($defaultBiz) {
                $_SESSION['current_business_id'] = $defaultBiz;
            } else {
                $firstBiz = $pdo->query("SELECT id FROM businesses WHERE status = 'active' ORDER BY id ASC LIMIT 1")->fetchColumn();
                $_SESSION['current_business_id'] = $firstBiz;
            }

            log_audit('LOGIN', 'user', $user['id'], "User logged in: {$user['username']}");

            header("Location: dashboard.php");
            exit;
            }
        } else {
            $error = $is_rtl ? 'غلط یوزر نیم یا پاس ورڈ درج کیا گیا ہے۔' : 'Invalid username or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="<?php echo $current_lang; ?>" dir="<?php echo $is_rtl ? 'rtl' : 'ltr'; ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo h(__('login')); ?> - <?php echo h(__('app_name')); ?></title>
  <link rel="icon" type="image/svg+xml" href="assets/images/favicon.svg">
  <link rel="stylesheet" href="assets/css/app.css">
  <?php if ($is_rtl): ?>
    <link rel="stylesheet" href="assets/css/rtl.css">
  <?php endif; ?>
  <style>
    body {
      background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
      display: flex;
      align-items: center;
      justify-content: center;
      min-height: 100vh;
      padding: 20px;
    }
    .login-box {
      background: #ffffff;
      border-radius: 16px;
      padding: 35px 30px;
      max-width: 440px;
      width: 100%;
      box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.4);
    }
    .login-logo {
      display: block;
      margin: 0 auto 20px auto;
      max-width: 250px;
      height: auto;
    }
  </style>
</head>
<body>

<div class="login-box">
  <img src="assets/images/logo.svg" alt="PrintPulse ERP" class="login-logo">
  
  <div style="text-align:center; margin-bottom: 24px;">
    <h2 style="font-size: 1.4rem; font-weight: 800; color:#0f172a;">
      <?php echo $is_rtl ? 'سسٹم لاگ ان' : 'Sign In to Your Workspace'; ?>
    </h2>
    <p style="font-size: 0.85rem; color: #64748b;">
      <?php echo h(__('app_subtitle')); ?>
    </p>
    
    <div style="margin-top: 10px;">
      <?php if ($current_lang === 'en'): ?>
        <a href="?lang=ur" class="btn btn-outline btn-sm">🇵🇰 Switch to Urdu (اردو)</a>
      <?php else: ?>
        <a href="?lang=en" class="btn btn-outline btn-sm">🇬🇧 Switch to English</a>
      <?php endif; ?>
    </div>
  </div>

  <?php if (!empty($error)): ?>
    <div class="alert alert-danger"><?php echo h($error); ?></div>
  <?php endif; ?>

  <form method="POST" action="login.php">
    <div class="form-group">
      <label class="form-label"><?php echo $is_rtl ? 'یوزر نیم (Username)' : 'Username'; ?></label>
      <input type="text" name="username" class="form-control" value="admin" required autofocus>
    </div>

    <div class="form-group">
      <label class="form-label"><?php echo $is_rtl ? 'پاس ورڈ (Password)' : 'Password'; ?></label>
      <input type="password" name="password" class="form-control" value="admin123" required>
    </div>

    <button type="submit" class="btn btn-primary" style="width: 100%; padding: 10px; font-size: 1rem; margin-top: 10px;">
      🔑 <?php echo h(__('login')); ?>
    </button>
  </form>

  <div style="margin-top: 25px; padding-top: 15px; border-top: 1px solid #e2e8f0; font-size: 0.8rem; color: #64748b; text-align: center;">
    <strong>Demo Credentials:</strong> <code>admin</code> / <code>admin123</code><br>
    Includes FAST PRINTERS &amp; AL-MADINA Tenants
  </div>
</div>

</body>
</html>
