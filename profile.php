<?php
require_once __DIR__ . '/config/init.php';

// Auth Guard
if (empty($current_user)) {
    header("Location: login.php");
    exit;
}

$pdo = getDB();
$userId = (int)$current_user['id'];
$msg = '';
$err = '';

// Fetch fresh user record from database
$stmtUser = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmtUser->execute([$userId]);
$userData = $stmtUser->fetch();

if (!$userData) {
    header("Location: logout.php");
    exit;
}

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $err = $is_rtl ? 'سیکورٹی ٹوکن غلط ہے۔ دوبارہ کوشش کریں۔' : 'Security token mismatch. Please try again.';
    } else {
        $action = $_POST['action'] ?? '';

        // 1. Update Profile Information (Name, Email, Phone)
        if ($action === 'update_profile') {
            $name = trim($_POST['name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $phone = trim($_POST['phone'] ?? '');

            if (empty($name)) {
                $err = $is_rtl ? 'نام درج کرنا لازمی ہے۔' : 'Name is required.';
            } else {
                $upd = $pdo->prepare("UPDATE users SET name = ?, email = ?, phone = ? WHERE id = ?");
                $upd->execute([$name, $email, $phone, $userId]);

                // Update session
                $_SESSION['user']['name'] = $name;
                $_SESSION['user']['email'] = $email;
                $current_user['name'] = $name;
                $current_user['email'] = $email;
                $userData['name'] = $name;
                $userData['email'] = $email;
                $userData['phone'] = $phone;

                log_audit('UPDATE_PROFILE', 'user', $userId, 'User updated their own profile information.');
                $msg = $is_rtl ? 'پروفائل کی معلومات کامیابی سے تبدیل ہو گئیں۔' : 'Profile details updated successfully.';
            }
        }

        // 2. Change Password (Own Password Only)
        elseif ($action === 'change_password') {
            $currentPassword = $_POST['current_password'] ?? '';
            $newPassword = $_POST['new_password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';

            if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
                $err = $is_rtl ? 'تمام پاس ورڈ خانے پر کرنا لازمی ہیں۔' : 'All password fields are required.';
            } elseif (!password_verify($currentPassword, $userData['password_hash'])) {
                $err = $is_rtl ? 'موجودہ پاس ورڈ غلط ہے۔' : 'Current password is incorrect.';
            } elseif (strlen($newPassword) < 6) {
                $err = $is_rtl ? 'نیا پاس ورڈ کم از کم 6 ہندسوں یا حروف پر مشتمل ہونا چاہیے۔' : 'New password must be at least 6 characters long.';
            } elseif ($newPassword !== $confirmPassword) {
                $err = $is_rtl ? 'نیا پاس ورڈ اور تصدیقی پاس ورڈ آپس میں مماثل نہیں ہیں۔' : 'New password and confirmation password do not match.';
            } elseif ($currentPassword === $newPassword) {
                $err = $is_rtl ? 'نیا پاس ورڈ پرانے پاس ورڈ سے مختلف ہونا چاہیے۔' : 'New password must be different from current password.';
            } else {
                // Strict update on self only
                $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
                $updPw = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
                $updPw->execute([$newHash, $userId]);

                log_audit('CHANGE_PASSWORD', 'user', $userId, 'User changed their own password successfully.');
                $msg = $is_rtl ? 'آپ کا پاس ورڈ کامیابی سے تبدیل ہو گیا ہے۔' : 'Your password has been changed successfully.';
            }
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
  <div>
    <h1 class="page-title">
      👤 <?php echo h(__('my_profile')); ?>
    </h1>
    <p style="color:var(--text-muted); font-size:0.9rem;">
      <?php echo $is_rtl ? 'ذاتی اکاؤنٹ کی معلومات اور پاس ورڈ سیکیورٹی مینیج کریں' : 'Manage your personal user profile, contact details, and account password security'; ?>
    </p>
  </div>
  <a href="dashboard.php" class="btn btn-outline">&larr; <?php echo h(__('dashboard')); ?></a>
</div>

<?php if ($msg): ?>
  <div class="alert alert-success" style="margin-bottom:20px;">✅ <?php echo h($msg); ?></div>
<?php endif; ?>
<?php if ($err): ?>
  <div class="alert alert-danger" style="margin-bottom:20px;">❌ <?php echo h($err); ?></div>
<?php endif; ?>

<div class="grid grid-cols-2" style="gap:25px; align-items:start;">

  <!-- Card 1: Account Information & Profile Edit -->
  <div class="card">
    <div class="card-header">
      <span>👤 <?php echo $is_rtl ? 'اکاؤنٹ کی تفصیلات' : 'Account Details'; ?></span>
      <span class="badge badge-primary"><?php echo strtoupper($userData['role']); ?></span>
    </div>
    <div class="card-body">
      <form method="POST" action="profile.php">
        <input type="hidden" name="action" value="update_profile">
        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">

        <div class="form-group">
          <label class="form-label"><?php echo $is_rtl ? 'یوزر نیم (لاگ ان آئی ڈی)' : 'Username (Login ID)'; ?></label>
          <input type="text" class="form-control" value="<?php echo h($userData['username']); ?>" readonly style="background:var(--slate-100); font-weight:700; color:var(--text-muted); cursor:not-allowed;">
          <small class="text-muted"><?php echo $is_rtl ? 'یوزر نیم کو تبدیل نہیں کیا جا سکتا۔' : 'Username cannot be modified.'; ?></small>
        </div>

        <div class="form-group">
          <label class="form-label"><?php echo $is_rtl ? 'مکمل نام' : 'Full Name'; ?> *</label>
          <input type="text" name="name" class="form-control" value="<?php echo h($userData['name']); ?>" required>
        </div>

        <div class="form-group">
          <label class="form-label"><?php echo h(__('email')); ?></label>
          <input type="email" name="email" class="form-control" value="<?php echo h($userData['email'] ?? ''); ?>" placeholder="user@printpulse.local">
        </div>

        <div class="form-group">
          <label class="form-label"><?php echo h(__('phone')); ?></label>
          <input type="text" name="phone" class="form-control" value="<?php echo h($userData['phone'] ?? ''); ?>" placeholder="0300-1234567">
        </div>

        <div class="form-group">
          <label class="form-label"><?php echo $is_rtl ? 'منسلک ادارہ / بزنس' : 'Assigned Business Context'; ?></label>
          <input type="text" class="form-control" value="<?php echo h($current_business['name'] ?? 'N/A'); ?>" readonly style="background:var(--slate-100); color:var(--primary-dark); font-weight:600; cursor:not-allowed;">
        </div>

        <button type="submit" class="btn btn-outline" style="width:100%; margin-top:10px; font-weight:600;">
          💾 <?php echo $is_rtl ? 'پروفائل معلومات اپ ڈیٹ کریں' : 'Update Profile Info'; ?>
        </button>
      </form>
    </div>
  </div>

  <!-- Card 2: Change Password (Self Only) -->
  <div class="card" style="border-top:4px solid var(--primary);">
    <div class="card-header">
      <span>🔑 <?php echo h(__('change_password')); ?></span>
      <span style="font-size:12px; color:var(--text-muted);"><?php echo $is_rtl ? 'صرف آپ کے اپنے اکاؤنٹ کے لیے' : 'Personal Account Only'; ?></span>
    </div>
    <div class="card-body">
      <div style="background:#f0f9ff; border:1px solid #bae6fd; border-radius:8px; padding:12px 15px; margin-bottom:18px; font-size:13px; color:#0369a1; line-height:1.5;">
        🔒 <strong><?php echo $is_rtl ? 'سیکیورٹی اصول:' : 'Security Policy:'; ?></strong> 
        <?php echo $is_rtl ? 'آپ صرف اپنے اکاؤنٹ کا پاس ورڈ تبدیل کر سکتے ہیں۔ کسی دوسرے صارف کا پاس ورڈ تبدیل نہیں کیا جا سکتا۔' : 'You can securely update your own login password. You cannot modify or access other users\' credentials.'; ?>
      </div>

      <form method="POST" action="profile.php">
        <input type="hidden" name="action" value="change_password">
        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">

        <div class="form-group">
          <label class="form-label"><?php echo h(__('current_password')); ?> *</label>
          <input type="password" name="current_password" class="form-control" required placeholder="••••••••" autocomplete="current-password">
        </div>

        <div class="form-group">
          <label class="form-label"><?php echo h(__('new_password')); ?> *</label>
          <input type="password" name="new_password" class="form-control" required minlength="6" placeholder="••••••••" autocomplete="new-password">
          <small class="text-muted"><?php echo $is_rtl ? 'کم از کم 6 حروف یا ہندسے' : 'Minimum 6 characters'; ?></small>
        </div>

        <div class="form-group">
          <label class="form-label"><?php echo h(__('confirm_password')); ?> *</label>
          <input type="password" name="confirm_password" class="form-control" required minlength="6" placeholder="••••••••" autocomplete="new-password">
        </div>

        <button type="submit" class="btn btn-primary" style="width:100%; margin-top:15px; font-weight:700; padding:10px;">
          🔐 <?php echo h(__('change_password')); ?>
        </button>
      </form>
    </div>
  </div>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
