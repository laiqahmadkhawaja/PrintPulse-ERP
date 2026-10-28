<?php
/**
 * User & Staff Management System
 * Full RBAC administration with business scoping and permission enforcement.
 */

require_once __DIR__ . '/config/init.php';
require_once __DIR__ . '/includes/auth_helper.php';

// Auth & Role Guard: Only Platform Admin & Business Owner can manage staff
if (!in_array($current_user['role'] ?? '', ['platform_admin', 'business_owner'])) {
    $_SESSION['flash_error'] = $is_rtl 
        ? 'رسائی ممنوع ہے: صرف بزنس اونر یا ایڈمنسٹریٹر کو عملہ مینیج کرنے کی اجازت ہے۔' 
        : 'Access Denied: Only Business Owners or Platform Administrators can manage users.';
    header("Location: dashboard.php");
    exit;
}

$bid = (int)$current_business['id'];
$pdo = getDB();
$msg = '';
$err = '';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $err = $is_rtl ? 'سیکورٹی ٹوکن غلط ہے۔ دوبارہ کوشش کریں۔' : 'Security token mismatch. Please try again.';
    } else {
        $action = $_POST['action'] ?? '';

        // 1. Create New Staff User
        if ($action === 'create_user') {
            $name = trim($_POST['name'] ?? '');
            $username = strtolower(trim($_POST['username'] ?? ''));
            $email = trim($_POST['email'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $password = $_POST['password'] ?? '';
            $role = $_POST['role'] ?? 'cashier';

            // Allowed roles for business assignment
            $allowedRoles = ['business_owner', 'manager', 'technician', 'cashier'];
            if ($current_user['role'] === 'platform_admin') {
                $allowedRoles[] = 'platform_admin';
            }

            if (empty($name) || empty($username) || empty($password)) {
                $err = $is_rtl ? 'نام، یوزر نیم اور پاس ورڈ درج کرنا لازمی ہے۔' : 'Name, username, and password are required.';
            } elseif (strlen($password) < 6) {
                $err = $is_rtl ? 'پاس ورڈ کم از کم 6 حروف پر مشتمل ہونا چاہیے۔' : 'Password must be at least 6 characters.';
            } elseif (!in_array($role, $allowedRoles)) {
                $err = "Invalid role selected.";
            } else {
                // Check if username already exists
                $chk = $pdo->prepare("SELECT id FROM users WHERE username = ?");
                $chk->execute([$username]);
                if ($chk->fetch()) {
                    $err = $is_rtl ? 'یہ یوزر نیم پہلے سے موجود ہے۔ مختلف یوزر نیم منتخب کریں۔' : 'Username is already taken. Please choose another.';
                } else {
                    $pdo->beginTransaction();
                    try {
                        $hash = password_hash($password, PASSWORD_DEFAULT);
                        $ins = $pdo->prepare("INSERT INTO users (name, username, password_hash, email, phone, role, is_active) VALUES (?, ?, ?, ?, ?, ?, 1)");
                        $ins->execute([$name, $username, $hash, $email, $phone, $role]);
                        $newUid = $pdo->lastInsertId();

                        // Bind to current business
                        $pdo->prepare("INSERT INTO user_businesses (user_id, business_id, role_in_business, is_default) VALUES (?, ?, ?, 1)")
                            ->execute([$newUid, $bid, $role]);

                        $pdo->commit();
                        log_audit('CREATE_USER', 'user', $newUid, "Created staff user {$username} with role {$role}");
                        $msg = $is_rtl ? "صارف {$name} کامیابی سے رجسٹر ہو گیا۔" : "User '{$name}' created successfully.";
                    } catch (Exception $e) {
                        $pdo->rollBack();
                        $err = "Failed to create user: " . $e->getMessage();
                    }
                }
            }
        }

        // 2. Edit Staff User
        elseif ($action === 'edit_user') {
            $editId = (int)($_POST['user_id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $role = $_POST['role'] ?? 'cashier';
            $isActive = isset($_POST['is_active']) ? 1 : 0;
            $newPassword = $_POST['new_password'] ?? '';

            // Verify user belongs to current business
            $chkBiz = $pdo->prepare("SELECT ub.id FROM user_businesses ub WHERE ub.user_id = ? AND ub.business_id = ?");
            $chkBiz->execute([$editId, $bid]);
            if (!$chkBiz->fetch() && $current_user['role'] !== 'platform_admin') {
                $err = "You are not authorized to edit this user.";
            } elseif ($editId === (int)$current_user['id'] && $isActive == 0) {
                $err = $is_rtl ? 'آپ اپنے اکاؤنٹ کو غیر فعال نہیں کر سکتے۔' : 'You cannot deactivate your own account.';
            } else {
                $pdo->beginTransaction();
                try {
                    if (!empty($newPassword)) {
                        if (strlen($newPassword) < 6) {
                            throw new Exception("Password must be at least 6 characters.");
                        }
                        $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
                        $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?")->execute([$newHash, $editId]);
                    }

                    $pdo->prepare("UPDATE users SET name = ?, email = ?, phone = ?, role = ?, is_active = ? WHERE id = ?")
                        ->execute([$name, $email, $phone, $role, $isActive, $editId]);

                    $pdo->prepare("UPDATE user_businesses SET role_in_business = ? WHERE user_id = ? AND business_id = ?")
                        ->execute([$role, $editId, $bid]);

                    $pdo->commit();
                    log_audit('EDIT_USER', 'user', $editId, "Updated staff user ID {$editId}");
                    $msg = $is_rtl ? "صارف کی معلومات کامیابی سے تبدیل ہو گئیں۔" : "User updated successfully.";
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $err = $e->getMessage();
                }
            }
        }

        // 3. Toggle Status (Activate / Deactivate)
        elseif ($action === 'toggle_status') {
            $targetUid = (int)($_POST['user_id'] ?? 0);
            if ($targetUid === (int)$current_user['id']) {
                $err = $is_rtl ? 'آپ اپنے اکاؤنٹ کا اسٹیٹس تبدیل نہیں کر سکتے۔' : 'You cannot change the status of your own account.';
            } else {
                $chk = $pdo->prepare("SELECT u.is_active FROM users u JOIN user_businesses ub ON ub.user_id = u.id WHERE u.id = ? AND ub.business_id = ?");
                $chk->execute([$targetUid, $bid]);
                $currStatus = $chk->fetchColumn();

                if ($currStatus === false && $current_user['role'] !== 'platform_admin') {
                    $err = "User not found in your business.";
                } else {
                    $newStatus = ($currStatus == 1) ? 0 : 1;
                    $pdo->prepare("UPDATE users SET is_active = ? WHERE id = ?")->execute([$newStatus, $targetUid]);
                    log_audit('TOGGLE_USER_STATUS', 'user', $targetUid, "User status toggled to {$newStatus}");
                    $msg = ($newStatus == 1) 
                        ? ($is_rtl ? 'صارف کو کامیابی سے فعال کر دیا گیا۔' : 'User activated successfully.')
                        : ($is_rtl ? 'صارف کو کامیابی سے غیر فعال کر دیا گیا۔' : 'User deactivated successfully.');
                }
            }
        }
    }
}

// Fetch all users associated with this business
$sql = "SELECT u.id, u.name, u.username, u.email, u.phone, u.role, u.is_active, u.created_at, ub.role_in_business 
        FROM users u 
        JOIN user_businesses ub ON ub.user_id = u.id 
        WHERE ub.business_id = ? 
        ORDER BY u.id ASC";
$stmtUsers = $pdo->prepare($sql);
$stmtUsers->execute([$bid]);
$usersList = $stmtUsers->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
  <div>
    <h1 class="page-title">
      👥 <?php echo $is_rtl ? 'صارفین اور عملے کی مینیجمنٹ' : 'User & Staff Management'; ?>
    </h1>
    <p style="color:var(--text-muted); font-size:0.9rem;">
      <?php echo $is_rtl ? 'عملے کے اکاؤنٹس، کردار اور تفویض کردہ ماڈیولز کا کنٹرول' : 'Role-based access control, staff credentials, and module permissions for ' . h($current_business['name']); ?>
    </p>
  </div>
  <button type="button" class="btn btn-primary" onclick="openAddUserModal()">
    ➕ <?php echo $is_rtl ? 'نیا صارف شامل کریں' : 'Add Staff User'; ?>
  </button>
</div>

<?php if ($msg): ?>
  <div class="alert alert-success" style="margin-bottom:20px;">✅ <?php echo h($msg); ?></div>
<?php endif; ?>
<?php if ($err): ?>
  <div class="alert alert-danger" style="margin-bottom:20px;">❌ <?php echo h($err); ?></div>
<?php endif; ?>

<!-- Users List Card -->
<div class="card" style="margin-bottom:25px;">
  <div class="card-header">
    <div style="display:flex; align-items:center; gap:8px;">
      <span>📋 <?php echo $is_rtl ? 'موجودہ رجسٹرڈ عملہ' : 'Active Staff Accounts'; ?></span>
      <span class="badge badge-primary"><?php echo count($usersList); ?></span>
    </div>
  </div>
  <div class="table-responsive">
    <table class="table">
      <thead>
        <tr>
          <th>#</th>
          <th><?php echo $is_rtl ? 'نام و رابطہ' : 'Staff Name & Contact'; ?></th>
          <th><?php echo $is_rtl ? 'لاگ ان یوزر نیم' : 'Username'; ?></th>
          <th><?php echo $is_rtl ? 'عہدہ و کردار' : 'Role & Permissions'; ?></th>
          <th class="text-center"><?php echo $is_rtl ? 'حیثیت' : 'Status'; ?></th>
          <th><?php echo $is_rtl ? 'شراکت تاریخ' : 'Joined Date'; ?></th>
          <th class="text-center" style="width:140px;"><?php echo h(__('action')); ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($usersList as $idx => $u): ?>
          <tr>
            <td><?php echo $idx + 1; ?></td>
            <td>
              <strong><?php echo h($u['name']); ?></strong>
              <?php if ($u['id'] === (int)$current_user['id']): ?>
                <span class="badge badge-success" style="font-size:10px; margin-left:4px;"><?php echo $is_rtl ? 'آپ خود' : 'You'; ?></span>
              <?php endif; ?>
              <div style="font-size:12px; color:var(--text-muted);">
                <?php echo h($u['email'] ?: ($u['phone'] ?: 'No contact')); ?>
              </div>
            </td>
            <td><code><?php echo h($u['username']); ?></code></td>
            <td>
              <?php
                $roleBadges = [
                  'platform_admin' => ['label' => 'SaaS Admin', 'class' => 'badge-danger'],
                  'business_owner' => ['label' => 'Business Owner', 'class' => 'badge-primary'],
                  'manager' => ['label' => 'Manager', 'class' => 'badge-success'],
                  'technician' => ['label' => 'Technician', 'class' => 'badge-warning'],
                  'cashier' => ['label' => 'Cashier', 'class' => 'badge-secondary'],
                ];
                $b = $roleBadges[$u['role']] ?? ['label' => ucfirst($u['role']), 'class' => 'badge-secondary'];
              ?>
              <span class="badge <?php echo $b['class']; ?>"><?php echo $b['label']; ?></span>
            </td>
            <td class="text-center">
              <?php if ($u['is_active']): ?>
                <span class="badge badge-success">✓ <?php echo $is_rtl ? 'فعال' : 'Active'; ?></span>
              <?php else: ?>
                <span class="badge badge-danger">✗ <?php echo $is_rtl ? 'غیر فعال' : 'Inactive'; ?></span>
              <?php endif; ?>
            </td>
            <td><?php echo format_date($u['created_at']); ?></td>
            <td class="text-center">
              <div style="display:inline-flex; gap:6px; align-items:center;">
                <button type="button" class="btn btn-outline btn-sm" onclick='openEditUserModal(<?php echo json_encode($u, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>)' title="<?php echo h(__('edit')); ?>">
                  ✏️ <?php echo h(__('edit')); ?>
                </button>
                <?php if ($u['id'] !== (int)$current_user['id']): ?>
                  <form method="POST" action="users.php" style="margin:0;" onsubmit="return confirm('<?php echo $is_rtl ? 'کیا آپ واقعی اس صارف کا اسٹیٹس تبدیل کرنا چاہتے ہیں؟' : 'Change user active status?'; ?>');">
                    <input type="hidden" name="action" value="toggle_status">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                    <?php if ($u['is_active']): ?>
                      <button type="submit" class="btn btn-danger btn-sm" style="padding:4px 8px;" title="Deactivate">🚫</button>
                    <?php else: ?>
                      <button type="submit" class="btn btn-outline btn-sm" style="padding:4px 8px; color:var(--success); border-color:var(--success);" title="Activate">✅</button>
                    <?php endif; ?>
                  </form>
                <?php endif; ?>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Role & Permission Reference Matrix -->
<div class="card">
  <div class="card-header">
    <span>🛡️ <?php echo $is_rtl ? 'کردار اور ماڈیول اجازتوں کا جدول' : 'Role-Based Access Control (RBAC) Permissions Matrix'; ?></span>
  </div>
  <div class="table-responsive">
    <table class="table" style="font-size:13px;">
      <thead>
        <tr style="background:#f8fafc;">
          <th><?php echo $is_rtl ? 'سسٹم ماڈیول' : 'Application Module'; ?></th>
          <th class="text-center">Business Owner</th>
          <th class="text-center">Operations Manager</th>
          <th class="text-center">Technician</th>
          <th class="text-center">Front Cashier</th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td>⚡ <strong>POS Counter Sale</strong></td>
          <td class="text-center text-success font-bold">✓ Full</td>
          <td class="text-center text-success font-bold">✓ Full</td>
          <td class="text-center text-muted">&mdash;</td>
          <td class="text-center text-success font-bold">✓ Full</td>
        </tr>
        <tr>
          <td>🛒 <strong>Sales Invoices & Billing</strong></td>
          <td class="text-center text-success font-bold">✓ Full</td>
          <td class="text-center text-success font-bold">✓ Full</td>
          <td class="text-center text-muted">&mdash;</td>
          <td class="text-center text-success font-bold">✓ Full</td>
        </tr>
        <tr>
          <td>🔧 <strong>Printer Repair Jobs & Billing</strong></td>
          <td class="text-center text-success font-bold">✓ Full</td>
          <td class="text-center text-success font-bold">✓ Full</td>
          <td class="text-center text-success font-bold">✓ Full</td>
          <td class="text-center text-success">✓ Create / View</td>
        </tr>
        <tr>
          <td>🧪 <strong>Cartridge Refilling Jobs & Billing</strong></td>
          <td class="text-center text-success font-bold">✓ Full</td>
          <td class="text-center text-success font-bold">✓ Full</td>
          <td class="text-center text-success font-bold">✓ Full</td>
          <td class="text-center text-success">✓ Create / View</td>
        </tr>
        <tr>
          <td>📦 <strong>Inventory, Products & Stock</strong></td>
          <td class="text-center text-success font-bold">✓ Full</td>
          <td class="text-center text-success font-bold">✓ Full</td>
          <td class="text-center text-muted">View Only</td>
          <td class="text-center text-muted">&mdash;</td>
        </tr>
        <tr>
          <td>🚚 <strong>Purchases & Suppliers</strong></td>
          <td class="text-center text-success font-bold">✓ Full</td>
          <td class="text-center text-success font-bold">✓ Full</td>
          <td class="text-center text-muted">&mdash;</td>
          <td class="text-center text-muted">&mdash;</td>
        </tr>
        <tr>
          <td>👥 <strong>Customers & Outstanding Balances</strong></td>
          <td class="text-center text-success font-bold">✓ Full</td>
          <td class="text-center text-success font-bold">✓ Full</td>
          <td class="text-center text-muted">&mdash;</td>
          <td class="text-center text-success font-bold">✓ Full</td>
        </tr>
        <tr>
          <td>💸 <strong>Business Expenses & Vouchers</strong></td>
          <td class="text-center text-success font-bold">✓ Full</td>
          <td class="text-center text-success font-bold">✓ Full</td>
          <td class="text-center text-muted">&mdash;</td>
          <td class="text-center text-muted">&mdash;</td>
        </tr>
        <tr>
          <td>🏛️ <strong>Double-Entry Accounting Books</strong></td>
          <td class="text-center text-success font-bold">✓ Full</td>
          <td class="text-center text-muted">&mdash;</td>
          <td class="text-center text-muted">&mdash;</td>
          <td class="text-center text-muted">&mdash;</td>
        </tr>
        <tr>
          <td>📊 <strong>Financial Reports & Daily Closing</strong></td>
          <td class="text-center text-success font-bold">✓ Full</td>
          <td class="text-center text-success font-bold">✓ Full</td>
          <td class="text-center text-muted">&mdash;</td>
          <td class="text-center text-success">Daily Closing</td>
        </tr>
        <tr>
          <td>⚙️ <strong>Business Settings, Users & Backup</strong></td>
          <td class="text-center text-success font-bold">✓ Full</td>
          <td class="text-center text-muted">&mdash;</td>
          <td class="text-center text-muted">&mdash;</td>
          <td class="text-center text-muted">&mdash;</td>
        </tr>
      </tbody>
    </table>
  </div>
</div>

<!-- Modal: Add Staff User -->
<div id="addUserModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15,23,42,0.6); z-index:999999; align-items:center; justify-content:center; padding:15px; backdrop-filter:blur(3px);">
  <div style="background:#ffffff; border-radius:12px; max-width:480px; width:100%; box-shadow:0 20px 25px -5px rgba(0,0,0,0.25); overflow:hidden; border:1px solid #e2e8f0;">
    <div style="padding:16px 20px; background:#f8fafc; border-bottom:1px solid #e2e8f0; display:flex; justify-content:space-between; align-items:center;">
      <h3 style="margin:0; font-size:16px; font-weight:700; color:#0f172a;">➕ <?php echo $is_rtl ? 'نیا صارف رجسٹر کریں' : 'Register New Staff User'; ?></h3>
      <button type="button" onclick="closeAddUserModal()" style="background:none; border:none; font-size:18px; cursor:pointer; color:#64748b;">&times;</button>
    </div>
    <form method="POST" action="users.php" style="padding:20px;">
      <input type="hidden" name="action" value="create_user">
      <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">

      <div class="form-group" style="margin-bottom:12px;">
        <label class="form-label"><?php echo $is_rtl ? 'مکمل نام' : 'Full Name'; ?> *</label>
        <input type="text" name="name" class="form-control" required placeholder="e.g. Muhammad Ali">
      </div>

      <div class="grid grid-cols-2" style="gap:10px; margin-bottom:12px;">
        <div class="form-group">
          <label class="form-label"><?php echo $is_rtl ? 'یوزر نیم' : 'Username'; ?> *</label>
          <input type="text" name="username" class="form-control" required placeholder="e.g. ali_tech" autocomplete="off">
        </div>
        <div class="form-group">
          <label class="form-label"><?php echo h(__('password')); ?> *</label>
          <input type="password" name="password" class="form-control" required minlength="6" placeholder="Min 6 chars" autocomplete="new-password">
        </div>
      </div>

      <div class="grid grid-cols-2" style="gap:10px; margin-bottom:12px;">
        <div class="form-group">
          <label class="form-label"><?php echo h(__('email')); ?></label>
          <input type="email" name="email" class="form-control" placeholder="user@printpulse.local">
        </div>
        <div class="form-group">
          <label class="form-label"><?php echo h(__('phone')); ?></label>
          <input type="text" name="phone" class="form-control" placeholder="0300-1234567">
        </div>
      </div>

      <div class="form-group" style="margin-bottom:18px;">
        <label class="form-label"><?php echo $is_rtl ? 'کردار و رسائی' : 'Role & Assigned Permissions'; ?> *</label>
        <select name="role" class="form-control" required>
          <option value="cashier">Cashier (POS Counter Sales, Billing & Receipts)</option>
          <option value="technician">Technician (Printer Repairs & Cartridge Refilling)</option>
          <option value="manager">Operations Manager (Sales, Purchases, Inventory, Expenses)</option>
          <option value="business_owner">Business Owner (Full Business Access & Settings)</option>
        </select>
      </div>

      <div style="display:flex; justify-content:flex-end; gap:10px;">
        <button type="button" onclick="closeAddUserModal()" class="btn btn-outline" style="padding:8px 16px;">
          <?php echo h(__('cancel')); ?>
        </button>
        <button type="submit" class="btn btn-primary" style="padding:8px 22px; font-weight:700;">
          💾 <?php echo $is_rtl ? 'صارف محفوظ کریں' : 'Save User'; ?>
        </button>
      </div>
    </form>
  </div>
</div>

<!-- Modal: Edit Staff User -->
<div id="editUserModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15,23,42,0.6); z-index:999999; align-items:center; justify-content:center; padding:15px; backdrop-filter:blur(3px);">
  <div style="background:#ffffff; border-radius:12px; max-width:480px; width:100%; box-shadow:0 20px 25px -5px rgba(0,0,0,0.25); overflow:hidden; border:1px solid #e2e8f0;">
    <div style="padding:16px 20px; background:#f8fafc; border-bottom:1px solid #e2e8f0; display:flex; justify-content:space-between; align-items:center;">
      <h3 style="margin:0; font-size:16px; font-weight:700; color:#0f172a;">✏️ <?php echo $is_rtl ? 'صارف میں ترمیم کریں' : 'Edit Staff User'; ?></h3>
      <button type="button" onclick="closeEditUserModal()" style="background:none; border:none; font-size:18px; cursor:pointer; color:#64748b;">&times;</button>
    </div>
    <form method="POST" action="users.php" style="padding:20px;">
      <input type="hidden" name="action" value="edit_user">
      <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
      <input type="hidden" name="user_id" id="editUserId" value="">

      <div class="form-group" style="margin-bottom:12px;">
        <label class="form-label"><?php echo $is_rtl ? 'یوزر نیم' : 'Username'; ?></label>
        <input type="text" id="editUserUsername" class="form-control" readonly style="background:var(--slate-100); font-weight:700; cursor:not-allowed;">
      </div>

      <div class="form-group" style="margin-bottom:12px;">
        <label class="form-label"><?php echo $is_rtl ? 'مکمل نام' : 'Full Name'; ?> *</label>
        <input type="text" name="name" id="editUserName" class="form-control" required>
      </div>

      <div class="grid grid-cols-2" style="gap:10px; margin-bottom:12px;">
        <div class="form-group">
          <label class="form-label"><?php echo h(__('email')); ?></label>
          <input type="email" name="email" id="editUserEmail" class="form-control">
        </div>
        <div class="form-group">
          <label class="form-label"><?php echo h(__('phone')); ?></label>
          <input type="text" name="phone" id="editUserPhone" class="form-control">
        </div>
      </div>

      <div class="form-group" style="margin-bottom:12px;">
        <label class="form-label"><?php echo $is_rtl ? 'کردار و عہدہ' : 'Role & Permission'; ?> *</label>
        <select name="role" id="editUserRole" class="form-control" required>
          <option value="cashier">Cashier</option>
          <option value="technician">Technician</option>
          <option value="manager">Operations Manager</option>
          <option value="business_owner">Business Owner</option>
          <?php if ($current_user['role'] === 'platform_admin'): ?>
            <option value="platform_admin">Platform Admin</option>
          <?php endif; ?>
        </select>
      </div>

      <div class="form-group" style="margin-bottom:12px;">
        <label class="form-label"><?php echo $is_rtl ? 'نیا پاس ورڈ سیٹ کریں (اختیاری)' : 'Reset Password (Optional)'; ?></label>
        <input type="password" name="new_password" class="form-control" minlength="6" placeholder="<?php echo $is_rtl ? 'پرانا رکھنے کے لیے خالی چھوڑیں' : 'Leave empty to keep existing password'; ?>" autocomplete="new-password">
      </div>

      <div class="form-group" style="margin-bottom:18px;">
        <label style="display:flex; align-items:center; gap:8px; cursor:pointer;">
          <input type="checkbox" name="is_active" id="editUserActive" value="1" style="width:18px; height:18px;">
          <span style="font-weight:600; font-size:13px;"><?php echo $is_rtl ? 'اکاؤنٹ فعال ہے (لاگ ان اجازت)' : 'Account is Active (Can Login)'; ?></span>
        </label>
      </div>

      <div style="display:flex; justify-content:flex-end; gap:10px;">
        <button type="button" onclick="closeEditUserModal()" class="btn btn-outline" style="padding:8px 16px;">
          <?php echo h(__('cancel')); ?>
        </button>
        <button type="submit" class="btn btn-primary" style="padding:8px 22px; font-weight:700;">
          💾 <?php echo $is_rtl ? 'تبدیلیاں محفوظ کریں' : 'Save Changes'; ?>
        </button>
      </div>
    </form>
  </div>
</div>

<script>
function openAddUserModal() {
  document.getElementById('addUserModal').style.display = 'flex';
}

function closeAddUserModal() {
  document.getElementById('addUserModal').style.display = 'none';
}

function openEditUserModal(data) {
  document.getElementById('editUserId').value = data.id;
  document.getElementById('editUserUsername').value = data.username;
  document.getElementById('editUserName').value = data.name;
  document.getElementById('editUserEmail').value = data.email || '';
  document.getElementById('editUserPhone').value = data.phone || '';
  document.getElementById('editUserRole').value = data.role;
  document.getElementById('editUserActive').checked = (parseInt(data.is_active) === 1);
  document.getElementById('editUserModal').style.display = 'flex';
}

function closeEditUserModal() {
  document.getElementById('editUserModal').style.display = 'none';
}

window.addEventListener('click', function(e) {
  var addModal = document.getElementById('addUserModal');
  var editModal = document.getElementById('editUserModal');
  if (addModal && e.target === addModal) closeAddUserModal();
  if (editModal && e.target === editModal) closeEditUserModal();
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
