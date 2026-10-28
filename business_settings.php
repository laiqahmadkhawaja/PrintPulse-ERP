<?php
require_once __DIR__ . '/config/init.php';
require_once __DIR__ . '/includes/header.php';

$bid = (int)$current_business['id'];
$pdo = getDB();
$error = '';
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = "Security token mismatch.";
    } else {
        // Business Profile & Invoice Branding
        $name = trim($_POST['name'] ?? $current_business['name']);
        $city = trim($_POST['city'] ?? $current_business['city']);
        $address = trim($_POST['address'] ?? $current_business['address']);
        $cell = trim($_POST['cell'] ?? $current_business['cell']);
        $phone = trim($_POST['phone'] ?? $current_business['phone']);
        $email = trim($_POST['email'] ?? $current_business['email']);
        $currency = trim($_POST['currency'] ?? $current_business['currency']);
        $default_format = $_POST['default_invoice_format'] ?? 'A5';

        // Logo Upload Handling
        $logo_url = $current_business['logo_url'] ?? null;
        if (!empty($_POST['remove_logo'])) {
            $logo_url = null;
        } elseif (isset($_FILES['business_logo']) && $_FILES['business_logo']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['business_logo'];
            $allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/svg+xml'];
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);

            if (in_array($mime, $allowedTypes) || in_array($file['type'], $allowedTypes)) {
                $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                if (empty($ext) || $ext === 'tmp') {
                    $ext = ($mime === 'image/png') ? 'png' : (($mime === 'image/webp') ? 'webp' : (($mime === 'image/svg+xml') ? 'svg' : 'jpg'));
                }
                $targetDir = __DIR__ . '/uploads/logos/';
                if (!is_dir($targetDir)) {
                    mkdir($targetDir, 0777, true);
                }
                $fileName = 'logo_biz_' . $bid . '_' . time() . '.' . preg_replace('/[^a-zA-Z0-9]/', '', $ext);
                $targetPath = $targetDir . $fileName;

                if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                    $logo_url = 'uploads/logos/' . $fileName;
                }
            } else {
                $error = "Invalid logo format. Please upload PNG, JPG, WebP, or SVG.";
            }
        }

        // Bank Details
        $bank_name = trim($_POST['bank_name'] ?? '');
        $account_title = trim($_POST['account_title'] ?? '');
        $account_number = trim($_POST['account_number'] ?? '');
        $iban = trim($_POST['iban'] ?? '');
        $branch_code = trim($_POST['branch_code'] ?? '');
        $show_bank_on_invoice = isset($_POST['show_bank_on_invoice']) ? 1 : 0;

        // Watermark settings
        $wm_enabled = isset($_POST['watermark_enabled']) ? 1 : 0;
        $wm_text = trim($_POST['watermark_text'] ?? $name);
        $wm_opacity = (float)($_POST['watermark_opacity'] ?? 0.08);
        $wm_rotation = (int)($_POST['watermark_rotation'] ?? -25);
        $invoice_notes = trim($_POST['invoice_notes'] ?? '');

        // Feature Toggles
        $toggle_repair = isset($_POST['repair_job_management_enabled']) ? 1 : 0;
        $toggle_refill = isset($_POST['refilling_job_management_enabled']) ? 1 : 0;
        $toggle_inv = isset($_POST['inventory_enabled']) ? 1 : 0;
        $toggle_bar = isset($_POST['barcode_enabled']) ? 1 : 0;
        $toggle_closing = isset($_POST['daily_closing_enabled']) ? 1 : 0;

        $pdo->beginTransaction();
        try {
            // Update Business table
            $updBiz = $pdo->prepare("UPDATE businesses SET 
                name = ?, city = ?, address = ?, cell = ?, phone = ?, email = ?, currency = ?, default_invoice_format = ?, 
                logo_url = ?,
                bank_name = ?, account_title = ?, account_number = ?, iban = ?, branch_code = ?, show_bank_on_invoice = ?,
                watermark_enabled = ?, watermark_text = ?, watermark_opacity = ?, watermark_rotation = ?, invoice_notes = ? 
                WHERE id = ?");
            $updBiz->execute([
                $name, $city, $address, $cell, $phone, $email, $currency, $default_format,
                $logo_url,
                $bank_name, $account_title, $account_number, $iban, $branch_code, $show_bank_on_invoice,
                $wm_enabled, $wm_text, $wm_opacity, $wm_rotation, $invoice_notes, $bid
            ]);

            // Update Business Settings table
            $updSet = $pdo->prepare("UPDATE business_settings SET 
                repair_job_management_enabled = ?, 
                refilling_job_management_enabled = ?, 
                inventory_enabled = ?, 
                barcode_enabled = ?, 
                daily_closing_enabled = ? 
                WHERE business_id = ?");
            $updSet->execute([
                $toggle_repair, $toggle_refill, $toggle_inv, $toggle_bar, $toggle_closing, $bid
            ]);

            $pdo->commit();
            log_audit('UPDATE_SETTINGS', 'business_settings', $bid, "Updated business settings and feature toggles");
            $msg = $is_rtl ? 'بزنس سیٹنگز، فیچر ٹوگلز اور انوائس برانڈنگ کامیابی سے محفوظ ہو گئی۔' : 'Business settings, feature toggles, and invoice branding saved successfully.';

            // Reload business and settings
            $stmt = $pdo->prepare("SELECT * FROM businesses WHERE id = ?");
            $stmt->execute([$bid]);
            $current_business = $stmt->fetch();

            $stmt2 = $pdo->prepare("SELECT * FROM business_settings WHERE business_id = ?");
            $stmt2->execute([$bid]);
            $current_settings = $stmt2->fetch();

        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Failed to update settings: " . $e->getMessage();
        }
    }
}
?>

<div class="page-header">
  <div>
    <h1 class="page-title">
      ⚙️ <?php echo h(__('nav_settings')); ?> &mdash; <?php echo h($current_business['name']); ?>
    </h1>
    <p style="color:var(--text-muted); font-size:0.9rem;">
      <?php echo $is_rtl ? 'فیچرز ٹوگلز (آن/آف)، بل سائز، واٹر مارک اور دکان کی تفصیلات' : 'Configure operational feature toggles, invoice format, and dynamic watermark'; ?>
    </p>
  </div>
</div>

<?php if (!empty($error)): ?>
  <div class="alert alert-danger"><?php echo h($error); ?></div>
<?php endif; ?>
<?php if (!empty($msg)): ?>
  <div class="alert alert-success"><?php echo h($msg); ?></div>
<?php endif; ?>

<form method="POST" action="business_settings.php" enctype="multipart/form-data">
  <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">

  <div class="grid grid-cols-2">
    <!-- Feature Toggles Panel (BRD Section 4) -->
    <div class="card">
      <div class="card-header" style="background:#f1f5f9;">
        <span>🎛️ <?php echo $is_rtl ? 'فیچر ٹوگلز (Feature Toggles)' : 'Configurable Workflows & Feature Toggles'; ?></span>
      </div>
      <div class="card-body">
        <p style="color:var(--text-muted); font-size:0.85rem; margin-bottom:15px;">
          <?php echo $is_rtl 
            ? 'بغیر کوڈ تبدیل کیے کسی بھی ماڈیول کو اس بزنس کے لیے فعال یا غیر فعال کریں۔ جب جاب مینیجمنٹ بند ہوتی ہے تب بھی ڈائریکٹ انوائسنگ اور اکاؤنٹنگ چلتی رہتی ہے۔' 
            : 'Toggle operational workflows on or off for this business without touching application code. Disabling a workflow will NOT break accounting or direct service billing.'; ?>
        </p>

        <div style="border-bottom:1px solid #e2e8f0; padding-bottom:12px; margin-bottom:12px;">
          <label style="display:flex; align-items:center; gap:10px; cursor:pointer;">
            <input type="checkbox" name="repair_job_management_enabled" value="1" <?php echo !empty($current_settings['repair_job_management_enabled']) ? 'checked' : ''; ?> style="width:18px; height:18px;">
            <div>
              <strong><?php echo h(__('toggle_repair_workflow')); ?></strong>
              <div style="font-size:12px; color:#64748b;">
                <?php echo $is_rtl ? 'پرنٹر وصولی جاب کارڈ، ٹیکنیشن معائنہ اور اسٹیٹس مینیجمنٹ' : 'Enable repair job cards, serial tracking, technician diagnosis & workflow'; ?>
              </div>
            </div>
          </label>
        </div>

        <div style="border-bottom:1px solid #e2e8f0; padding-bottom:12px; margin-bottom:12px;">
          <label style="display:flex; align-items:center; gap:10px; cursor:pointer;">
            <input type="checkbox" name="refilling_job_management_enabled" value="1" <?php echo !empty($current_settings['refilling_job_management_enabled']) ? 'checked' : ''; ?> style="width:18px; height:18px;">
            <div>
              <strong><?php echo h(__('toggle_refill_workflow')); ?></strong>
              <div style="font-size:12px; color:#64748b;">
                <?php echo $is_rtl ? 'کارٹریج ریفلنگ جاب کارڈ اور ٹیسٹ پرنٹ اسٹیٹس' : 'Enable dedicated cartridge refilling job registration and tracking'; ?>
              </div>
            </div>
          </label>
        </div>

        <div style="border-bottom:1px solid #e2e8f0; padding-bottom:12px; margin-bottom:12px;">
          <label style="display:flex; align-items:center; gap:10px; cursor:pointer;">
            <input type="checkbox" name="inventory_enabled" value="1" <?php echo !empty($current_settings['inventory_enabled']) ? 'checked' : ''; ?> style="width:18px; height:18px;">
            <div>
              <strong><?php echo h(__('toggle_inventory')); ?></strong>
              <div style="font-size:12px; color:#64748b;">
                <?php echo $is_rtl ? 'اسٹاک لیولز، کم اسٹاک الرٹس اور سامان کی خریداری' : 'Track stock levels, low-stock reorder warnings, and inventory intake'; ?>
              </div>
            </div>
          </label>
        </div>

        <div style="border-bottom:1px solid #e2e8f0; padding-bottom:12px; margin-bottom:12px;">
          <label style="display:flex; align-items:center; gap:10px; cursor:pointer;">
            <input type="checkbox" name="barcode_enabled" value="1" <?php echo !empty($current_settings['barcode_enabled']) ? 'checked' : ''; ?> style="width:18px; height:18px;">
            <div>
              <strong><?php echo h(__('toggle_barcode')); ?></strong>
              <div style="font-size:12px; color:#64748b;">
                <?php echo $is_rtl ? 'کاؤنٹر سیلز پر بارکوڈ اسکینر سپورٹ' : 'Fast barcode lookup on POS counter and products'; ?>
              </div>
            </div>
          </label>
        </div>

        <div>
          <label style="display:flex; align-items:center; gap:10px; cursor:pointer;">
            <input type="checkbox" name="daily_closing_enabled" value="1" <?php echo !empty($current_settings['daily_closing_enabled']) ? 'checked' : ''; ?> style="width:18px; height:18px;">
            <div>
              <strong><?php echo h(__('toggle_daily_closing')); ?></strong>
              <div style="font-size:12px; color:#64748b;">
                <?php echo $is_rtl ? 'روزانہ کیش کاؤنٹر دراز کا حساب اور تالا بندی' : 'Daily register cash in/out reconciliation and seal'; ?>
              </div>
            </div>
          </label>
        </div>
      </div>
    </div>

    <!-- Invoice Design & Watermark Settings (BRD Section 11) -->
    <div class="card">
      <div class="card-header" style="background:#f1f5f9;">
        <span>🖨️ <?php echo $is_rtl ? 'انوائس ڈیزائن، سائز اور واٹر مارک' : 'Invoice Layout & Watermark Settings'; ?></span>
      </div>
      <div class="card-body">
        <div class="form-group">
          <label class="form-label"><?php echo $is_rtl ? 'طے شدہ بل سائز' : 'Default Paper Format'; ?></label>
          <select name="default_invoice_format" class="form-control">
            <option value="A5" <?php echo ($current_business['default_invoice_format']==='A5')?'selected':''; ?>>
              A5 - Standard Printed Bill (Uploaded Reference Default)
            </option>
            <option value="A4" <?php echo ($current_business['default_invoice_format']==='A4')?'selected':''; ?>>
              A4 - Full Page Invoice
            </option>
            <option value="80mm" <?php echo ($current_business['default_invoice_format']==='80mm')?'selected':''; ?>>
              80mm - Thermal POS Slip
            </option>
          </select>
        </div>

        <div style="border-top:1px solid #e2e8f0; padding-top:12px; margin-top:12px;">
          <label style="display:flex; align-items:center; gap:10px; cursor:pointer; margin-bottom:10px;">
            <input type="checkbox" name="watermark_enabled" value="1" <?php echo !empty($current_business['watermark_enabled']) ? 'checked' : ''; ?> style="width:18px; height:18px;">
            <strong><?php echo h(__('toggle_watermark')); ?></strong>
          </label>

          <div class="form-group">
            <label class="form-label"><?php echo $is_rtl ? 'واٹر مارک متن' : 'Watermark Text'; ?></label>
            <input type="text" name="watermark_text" class="form-control" value="<?php echo h($current_business['watermark_text']); ?>" placeholder="Business Watermark Text">
          </div>

          <div class="grid grid-cols-2">
            <div class="form-group">
              <label class="form-label"><?php echo $is_rtl ? 'شفافیت (Opacity)' : 'Watermark Opacity'; ?></label>
              <input type="number" step="0.01" min="0.02" max="0.30" name="watermark_opacity" class="form-control" value="<?php echo $current_business['watermark_opacity']; ?>">
              <small class="text-muted">Default: 0.08 (Subtle)</small>
            </div>
            <div class="form-group">
              <label class="form-label"><?php echo $is_rtl ? 'زاویہ (Rotation Angle)' : 'Rotation (Degrees)'; ?></label>
              <input type="number" step="5" min="-90" max="90" name="watermark_rotation" class="form-control" value="<?php echo $current_business['watermark_rotation']; ?>">
              <small class="text-muted">Default: -25 deg</small>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Business Contact & Terms Header -->
  <div class="card">
    <div class="card-header" style="background:#f1f5f9;">
      <span>🏢 <?php echo $is_rtl ? 'دکان کی تفصیلات اور بل کی شرائط' : 'Business Identity & Invoice Terms'; ?></span>
    </div>
    <div class="card-body">
      <!-- Business Logo Upload & Preview -->
      <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px 16px; margin-bottom: 16px;">
        <label class="form-label" style="font-weight: 700; margin-bottom: 6px;">
          🖼️ <?php echo $is_rtl ? 'بزنس لوگو (انوائس و بل پر پرنٹ ہوگا):' : 'Business Logo (Printed on Invoices & Receipts):'; ?>
        </label>
        <div style="display: flex; align-items: center; gap: 16px; flex-wrap: wrap;">
          <?php if (!empty($current_business['logo_url']) && file_exists(__DIR__ . '/' . $current_business['logo_url'])): ?>
            <div style="background:#ffffff; border:1px solid #cbd5e1; border-radius:6px; padding:6px; display:flex; align-items:center; justify-content:center; max-width:140px; max-height:60px;">
              <img src="<?php echo h($current_business['logo_url']); ?>?v=<?php echo time(); ?>" alt="Current Logo" style="max-height:48px; max-width:120px; object-fit:contain;">
            </div>
            <label style="display:flex; align-items:center; gap:6px; font-size:12.5px; color:#ef4444; cursor:pointer;">
              <input type="checkbox" name="remove_logo" value="1">
              <?php echo $is_rtl ? 'موجودہ لوگو ہٹا دیں' : 'Remove current logo'; ?>
            </label>
          <?php else: ?>
            <div style="font-size:12px; color:#64748b;">
              <?php echo $is_rtl ? 'کوئی کسٹم لوگو موجود نہیں۔ بل پر معیاری آئیکن ظاہر ہوگا۔' : 'No custom logo uploaded. Default modern printer icon will be displayed.'; ?>
            </div>
          <?php endif; ?>
          <div style="flex: 1; min-width: 220px;">
            <input type="file" name="business_logo" accept="image/png, image/jpeg, image/jpg, image/webp, image/svg+xml" class="form-control" style="padding: 5px;">
            <small class="text-muted"><?php echo $is_rtl ? 'فارمیٹس: PNG, JPG, WebP, SVG (تجویز کردہ سائز: 250x80px)' : 'Supported: PNG, JPG, WebP, SVG (Recommended size: 250x80px)'; ?></small>
          </div>
        </div>
      </div>

      <div class="grid grid-cols-3">
        <div class="form-group">
          <label class="form-label"><?php echo $is_rtl ? 'بزنس کا نام' : 'Business Name'; ?> *</label>
          <input type="text" name="name" class="form-control" value="<?php echo h($current_business['name']); ?>" required>
        </div>
        <div class="form-group">
          <label class="form-label"><?php echo $is_rtl ? 'شہر' : 'City'; ?></label>
          <input type="text" name="city" class="form-control" value="<?php echo h($current_business['city']); ?>">
        </div>
        <div class="form-group">
          <label class="form-label"><?php echo $is_rtl ? 'کرنسی سمبل' : 'Currency Symbol'; ?></label>
          <input type="text" name="currency" class="form-control" value="<?php echo h($current_business['currency']); ?>">
        </div>
      </div>

      <div class="grid grid-cols-3">
        <div class="form-group">
          <label class="form-label"><?php echo h(__('address')); ?> *</label>
          <input type="text" name="address" class="form-control" value="<?php echo h($current_business['address']); ?>" required>
        </div>
        <div class="form-group">
          <label class="form-label"><?php echo h(__('cell')); ?> / Mobile *</label>
          <input type="text" name="cell" class="form-control" value="<?php echo h($current_business['cell']); ?>" required>
        </div>
        <div class="form-group">
          <label class="form-label"><?php echo h(__('email')); ?></label>
          <input type="email" name="email" class="form-control" value="<?php echo h($current_business['email']); ?>">
        </div>
      </div>

      <div class="form-group">
        <label class="form-label"><?php echo h(__('notes')); ?> (Printed on Invoice Bottom-Left)</label>
        <textarea name="invoice_notes" class="form-control" rows="2"><?php echo h($current_business['invoice_notes']); ?></textarea>
      </div>
    </div>
  </div>

  <!-- Bank Account Details for Customer Online Transfers -->
  <div class="card">
    <div class="card-header" style="background:#f1f5f9;">
      <span>🏦 <?php echo $is_rtl ? 'بینک اکاؤنٹ کی تفصیلات (آن لائن ادائیگی و انوائس پرنٹنگ)' : 'Bank Account Details (For Customer Online Transfers & Invoice Printing)'; ?></span>
    </div>
    <div class="card-body">
      <p style="color:var(--text-muted); font-size:0.85rem; margin-bottom:15px;">
        <?php echo $is_rtl 
          ? 'اپنے بزنس کا بینک اکاؤنٹ درج کریں تاکہ گاہک آن لائن، راست (Raast) یا بینک ایپ کے ذریعے بل ادا کر سکیں۔ یہ تفصیلات بل پر نمایاں پرنٹ ہوں گی۔' 
          : 'Configure your company bank accounts so customers can transfer invoice payments via online banking, mobile apps, or Raast. These details are printed on modern executive invoices.'; ?>
      </p>

      <div class="grid grid-cols-3">
        <div class="form-group">
          <label class="form-label"><?php echo $is_rtl ? 'بینک کا نام' : 'Bank Name'; ?></label>
          <input type="text" name="bank_name" class="form-control" value="<?php echo h($current_business['bank_name'] ?? ''); ?>" placeholder="e.g. Meezan Bank / HBL / Alfalah">
        </div>
        <div class="form-group">
          <label class="form-label"><?php echo $is_rtl ? 'اکاؤنٹ کا عنوان (Title)' : 'Account Title'; ?></label>
          <input type="text" name="account_title" class="form-control" value="<?php echo h($current_business['account_title'] ?? ''); ?>" placeholder="e.g. <?php echo h($current_business['name']); ?>">
        </div>
        <div class="form-group">
          <label class="form-label"><?php echo $is_rtl ? 'اکاؤنٹ نمبر' : 'Account Number'; ?></label>
          <input type="text" name="account_number" class="form-control" value="<?php echo h($current_business['account_number'] ?? ''); ?>" placeholder="e.g. 01020304050607">
        </div>
      </div>

      <div class="grid grid-cols-2">
        <div class="form-group">
          <label class="form-label"><?php echo $is_rtl ? 'آئی بین (IBAN)' : 'IBAN (International Bank Account Number)'; ?></label>
          <input type="text" name="iban" class="form-control" value="<?php echo h($current_business['iban'] ?? ''); ?>" placeholder="e.g. PK36MEZN0001020304050607">
        </div>
        <div class="form-group">
          <label class="form-label"><?php echo $is_rtl ? 'برانچ کوڈ و نام' : 'Branch Code & City'; ?></label>
          <input type="text" name="branch_code" class="form-control" value="<?php echo h($current_business['branch_code'] ?? ''); ?>" placeholder="e.g. 0124 - Saddar Branch">
        </div>
      </div>

      <div style="margin-top:10px; padding:10px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:6px;">
        <label style="display:flex; align-items:center; gap:10px; cursor:pointer; margin:0;">
          <input type="checkbox" name="show_bank_on_invoice" value="1" <?php echo !empty($current_business['show_bank_on_invoice']) ? 'checked' : ''; ?> style="width:18px; height:18px;">
          <div>
            <strong><?php echo $is_rtl ? 'بل پر بینک اکاؤنٹ کی تفصیلات پرنٹ کریں' : 'Print Bank Details on Invoices'; ?></strong>
            <div style="font-size:12px; color:#64748b;">
              <?php echo $is_rtl ? 'انوائس کے نچلے حصے پر آن لائن ٹرانسفر کا باکس پرنٹ ہوگا' : 'Displays bank name, account number, and IBAN on modern A5/A4 executive invoices for quick customer wire transfers'; ?>
            </div>
          </div>
        </label>
      </div>

      <div style="margin-top:18px;">
        <button type="submit" class="btn btn-primary" style="padding:10px 24px; font-weight:800; font-size:1rem;">
          💾 <?php echo $is_rtl ? 'تمام ترتیبات و بینک تفصیلات محفوظ کریں' : 'Save All Settings & Bank Details'; ?>
        </button>
      </div>
    </div>
  </div>
</form>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
