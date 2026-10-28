<?php
require_once __DIR__ . '/config/init.php';
require_once __DIR__ . '/includes/header.php';

$bid = (int)$current_business['id'];
$pdo = getDB();
$msg = '';
$err = '';

// Add New Description
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    if (verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $desc = trim($_POST['description'] ?? '');
        $desc_ur = trim($_POST['description_ur'] ?? '');
        $rate = (float)($_POST['default_rate'] ?? 0);
        $type = $_POST['line_type'] ?? 'service';

        if (!empty($desc)) {
            $stmt = $pdo->prepare("INSERT INTO service_descriptions (business_id, description, description_ur, default_rate, line_type, usage_count) 
                                   VALUES (?, ?, ?, ?, ?, 1)");
            $stmt->execute([$bid, $desc, $desc_ur, $rate, $type]);
            $msg = $is_rtl ? 'سروس تفصیلات کامیابی سے شامل ہو گئیں۔' : 'Service description and rate saved.';
        } else {
            $err = $is_rtl ? 'سروس کی تفصیل درج کرنا لازمی ہے۔' : 'Description is required.';
        }
    } else {
        $err = "Security token mismatch.";
    }
}

// Edit / Update Description & Rate
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit') {
    if (verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $editId = (int)($_POST['id'] ?? 0);
        $desc = trim($_POST['description'] ?? '');
        $desc_ur = trim($_POST['description_ur'] ?? '');
        $rate = (float)($_POST['default_rate'] ?? 0);
        $type = $_POST['line_type'] ?? 'service';

        if ($editId > 0 && !empty($desc)) {
            $stmt = $pdo->prepare("UPDATE service_descriptions 
                                   SET description = ?, description_ur = ?, default_rate = ?, line_type = ? 
                                   WHERE id = ? AND business_id = ?");
            $stmt->execute([$desc, $desc_ur, $rate, $type, $editId, $bid]);
            $msg = $is_rtl ? 'سروس کا ریٹ اور تفصیلات کامیابی سے تبدیل ہو گئیں۔' : 'Service rate and description updated successfully.';
        } else {
            $err = $is_rtl ? 'سروس کی تفصیل درج کرنا لازمی ہے۔' : 'Description is required.';
        }
    } else {
        $err = "Security token mismatch.";
    }
}

// Delete Description
if (isset($_GET['delete'])) {
    $delId = (int)$_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM service_descriptions WHERE id = ? AND business_id = ?");
    $stmt->execute([$delId, $bid]);
    $msg = $is_rtl ? 'سروس تفصیلات حذف کر دی گئیں۔' : 'Service description removed.';
}

// Filter / Search
$search = trim($_GET['search'] ?? '');
$filterType = trim($_GET['type'] ?? '');

$sql = "SELECT * FROM service_descriptions WHERE business_id = ?";
$params = [$bid];

if ($search !== '') {
    $sql .= " AND (description LIKE ? OR description_ur LIKE ?)";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}

if ($filterType !== '') {
    $sql .= " AND line_type = ?";
    $params[] = $filterType;
}

$sql .= " ORDER BY usage_count DESC, description ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$services = $stmt->fetchAll();
?>

<div class="page-header">
  <div>
    <h1 class="page-title">
      ✨ <?php echo h(__('nav_service_catalog')); ?>
    </h1>
    <p style="color:var(--text-muted); font-size:0.9rem;">
      <?php echo $is_rtl ? 'انوائس آٹو کمپیلیٹ، ریٹس کنفیگریشن اور بار بار استعمال ہونے والی سروسز' : 'Configure default rates, descriptions, and autocomplete suggestions for repair and refill billing'; ?>
    </p>
  </div>
  <div style="display:flex; gap:10px;">
    <a href="repair_invoice_create.php" class="btn btn-outline btn-sm">🔧 <?php echo $is_rtl ? 'مرمت بل' : 'New Repair Invoice'; ?></a>
    <a href="refill_invoice_create.php" class="btn btn-outline btn-sm">🧪 <?php echo $is_rtl ? 'ریفلنگ بل' : 'New Refill Invoice'; ?></a>
  </div>
</div>

<?php if ($msg): ?>
  <div class="alert alert-success" style="margin-bottom:15px;">✅ <?php echo h($msg); ?></div>
<?php endif; ?>
<?php if ($err): ?>
  <div class="alert alert-danger" style="margin-bottom:15px;">❌ <?php echo h($err); ?></div>
<?php endif; ?>

<div class="grid grid-cols-3" style="gap:20px; align-items:start;">
  <!-- Add New Form -->
  <div class="card">
    <div class="card-header">
      <span>➕ <?php echo $is_rtl ? 'نئی سروس و ریٹ شامل کریں' : 'Add New Description & Rate'; ?></span>
    </div>
    <div class="card-body">
      <form method="POST" action="service_descriptions.php">
        <input type="hidden" name="action" value="add">
        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">

        <div class="form-group">
          <label class="form-label"><?php echo h(__('description')); ?> (English) *</label>
          <input type="text" name="description" class="form-control" required placeholder="e.g. HP 85A Toner Refill">
        </div>

        <div class="form-group">
          <label class="form-label"><?php echo h(__('description')); ?> (Urdu)</label>
          <input type="text" name="description_ur" class="form-control" placeholder="مثال: پرنٹر جنرل سروس">
        </div>

        <div class="form-group">
          <label class="form-label"><?php echo h(__('rate')); ?> (<?php echo h($current_business['currency'] ?? 'Rs'); ?>) *</label>
          <input type="number" step="0.01" min="0" name="default_rate" class="form-control" value="0.00" required>
        </div>

        <div class="form-group">
          <label class="form-label"><?php echo $is_rtl ? 'لائن کی قسم' : 'Line Classification'; ?> *</label>
          <select name="line_type" class="form-control">
            <option value="service">Repair Service (سروس)</option>
            <option value="refill">Cartridge Refill (ریفلنگ)</option>
            <option value="part">Spare Part (پرزہ)</option>
            <option value="labour">Labour Charges (مزدوری)</option>
          </select>
        </div>

        <button type="submit" class="btn btn-primary" style="width:100%; margin-top:10px; font-weight:700;">
          💾 <?php echo h(__('save')); ?>
        </button>
      </form>
    </div>
  </div>

  <!-- Reusable Descriptions Table -->
  <div class="card" style="grid-column: span 2;">
    <div class="card-header" style="flex-wrap:wrap; gap:10px;">
      <div style="display:flex; align-items:center; gap:8px;">
        <span>📋 <?php echo $is_rtl ? 'محفوظ شدہ سروسز و ریٹس' : 'Configured Rates & Autocomplete Catalog'; ?></span>
        <span class="badge badge-primary"><?php echo count($services); ?></span>
      </div>

      <form method="GET" action="service_descriptions.php" style="display:flex; gap:8px; margin:0; flex-wrap:wrap;">
        <input type="text" name="search" class="form-control" style="font-size:12px; padding:4px 8px; width:160px;" placeholder="<?php echo $is_rtl ? 'تلاش کریں...' : 'Search service...'; ?>" value="<?php echo h($search); ?>">
        <select name="type" class="form-control" style="font-size:12px; padding:4px 8px; width:130px;" onchange="this.form.submit()">
          <option value="">-- <?php echo $is_rtl ? 'تمام اقسام' : 'All Types'; ?> --</option>
          <option value="refill" <?php echo ($filterType === 'refill') ? 'selected' : ''; ?>>Refill</option>
          <option value="service" <?php echo ($filterType === 'service') ? 'selected' : ''; ?>>Service</option>
          <option value="part" <?php echo ($filterType === 'part') ? 'selected' : ''; ?>>Part</option>
          <option value="labour" <?php echo ($filterType === 'labour') ? 'selected' : ''; ?>>Labour</option>
        </select>
        <button type="submit" class="btn btn-outline btn-sm">🔍</button>
        <?php if ($search || $filterType): ?>
          <a href="service_descriptions.php" class="btn btn-outline btn-sm"><?php echo h(__('reset')); ?></a>
        <?php endif; ?>
      </form>
    </div>

    <div class="table-responsive">
      <table class="table">
        <thead>
          <tr>
            <th><?php echo h(__('description')); ?></th>
            <th>Urdu</th>
            <th><?php echo $is_rtl ? 'قسم' : 'Type'; ?></th>
            <th class="text-right"><?php echo h(__('rate')); ?></th>
            <th class="text-center"><?php echo $is_rtl ? 'استعمال' : 'Used'; ?></th>
            <th class="text-center" style="width:90px;"><?php echo h(__('action')); ?></th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($services)): ?>
            <tr><td colspan="6" class="text-center text-muted" style="padding:20px;"><?php echo $is_rtl ? 'کوئی سروس ریکارڈ نہیں ملا' : 'No service descriptions found.'; ?></td></tr>
          <?php else: ?>
            <?php foreach ($services as $srv): ?>
              <tr>
                <td><strong><?php echo h($srv['description']); ?></strong></td>
                <td><?php echo h($srv['description_ur'] ?: '-'); ?></td>
                <td>
                  <?php 
                    $badgeClass = 'badge-primary';
                    if ($srv['line_type'] === 'refill') $badgeClass = 'badge-success';
                    elseif ($srv['line_type'] === 'part') $badgeClass = 'badge-secondary';
                  ?>
                  <span class="badge <?php echo $badgeClass; ?>"><?php echo strtoupper($srv['line_type']); ?></span>
                </td>
                <td class="text-right" style="font-weight:700; color:var(--primary-dark);">
                  <?php echo format_currency($srv['default_rate']); ?>
                </td>
                <td class="text-center"><span class="badge badge-secondary"><?php echo (int)$srv['usage_count']; ?></span></td>
                <td class="text-center">
                  <div style="display:inline-flex; gap:4px; align-items:center;">
                    <button type="button" class="btn btn-outline btn-sm" title="<?php echo h(__('edit')); ?>" style="padding:3px 7px;" onclick='openEditRateModal(<?php echo json_encode($srv, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>)'>
                      ✏️
                    </button>
                    <a href="service_descriptions.php?delete=<?php echo $srv['id']; ?>" class="btn btn-danger btn-sm" style="padding:3px 7px;" title="<?php echo h(__('delete')); ?>" onclick="return confirm('<?php echo $is_rtl ? 'کیا آپ واقعی اس سروس کو حذف کرنا چاہتے ہیں؟' : 'Delete this service description?'; ?>');">
                      🗑️
                    </a>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Modal for Editing Service Description & Rate -->
<div id="editRateModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15,23,42,0.6); z-index:999999; align-items:center; justify-content:center; padding:15px; backdrop-filter:blur(3px);">
  <div style="background:#ffffff; border-radius:12px; max-width:440px; width:100%; box-shadow:0 20px 25px -5px rgba(0,0,0,0.25); overflow:hidden; border:1px solid #e2e8f0;">
    <div style="padding:16px 20px; background:#f8fafc; border-bottom:1px solid #e2e8f0; display:flex; justify-content:space-between; align-items:center;">
      <h3 style="margin:0; font-size:16px; font-weight:700; color:#0f172a;">✏️ <?php echo $is_rtl ? 'سروس اور ریٹ تبدیل کریں' : 'Edit Service & Default Rate'; ?></h3>
      <button type="button" onclick="closeEditRateModal()" style="background:none; border:none; font-size:18px; cursor:pointer; color:#64748b;">&times;</button>
    </div>
    <form method="POST" action="service_descriptions.php" style="padding:20px;">
      <input type="hidden" name="action" value="edit">
      <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
      <input type="hidden" name="id" id="editServiceId" value="">

      <div class="form-group" style="margin-bottom:12px;">
        <label class="form-label"><?php echo h(__('description')); ?> (English) *</label>
        <input type="text" name="description" id="editServiceDesc" class="form-control" required>
      </div>

      <div class="form-group" style="margin-bottom:12px;">
        <label class="form-label"><?php echo h(__('description')); ?> (Urdu)</label>
        <input type="text" name="description_ur" id="editServiceDescUr" class="form-control">
      </div>

      <div class="form-group" style="margin-bottom:12px;">
        <label class="form-label"><?php echo h(__('rate')); ?> (<?php echo h($current_business['currency'] ?? 'Rs'); ?>) *</label>
        <input type="number" step="0.01" min="0" name="default_rate" id="editServiceRate" class="form-control" required style="font-weight:700;">
      </div>

      <div class="form-group" style="margin-bottom:18px;">
        <label class="form-label"><?php echo $is_rtl ? 'لائن کی قسم' : 'Line Classification'; ?> *</label>
        <select name="line_type" id="editServiceType" class="form-control">
          <option value="service">Repair Service (سروس)</option>
          <option value="refill">Cartridge Refill (ریفلنگ)</option>
          <option value="part">Spare Part (پرزہ)</option>
          <option value="labour">Labour Charges (مزدوری)</option>
        </select>
      </div>

      <div style="display:flex; justify-content:flex-end; gap:10px;">
        <button type="button" onclick="closeEditRateModal()" class="btn btn-outline" style="padding:8px 16px;">
          <?php echo h(__('cancel')); ?>
        </button>
        <button type="submit" class="btn btn-primary" style="padding:8px 22px; font-weight:700;">
          💾 <?php echo h(__('save')); ?>
        </button>
      </div>
    </form>
  </div>
</div>

<script>
function openEditRateModal(data) {
  document.getElementById('editServiceId').value = data.id;
  document.getElementById('editServiceDesc').value = data.description || '';
  document.getElementById('editServiceDescUr').value = data.description_ur || '';
  document.getElementById('editServiceRate').value = parseFloat(data.default_rate || 0).toFixed(2);
  document.getElementById('editServiceType').value = data.line_type || 'service';
  document.getElementById('editRateModal').style.display = 'flex';
}

function closeEditRateModal() {
  document.getElementById('editRateModal').style.display = 'none';
}

window.addEventListener('click', function(e) {
  var modal = document.getElementById('editRateModal');
  if (modal && e.target === modal) {
    closeEditRateModal();
  }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
