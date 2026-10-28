<?php
/**
 * Invoice Edit Management
 * Universal editor for Sales, POS, Repair, and Refill invoices with full accounting and stock recalculation.
 */

require_once __DIR__ . '/config/init.php';
require_once __DIR__ . '/includes/accounting_helper.php';
require_once __DIR__ . '/includes/inventory_helper.php';
require_once __DIR__ . '/includes/invoice_helper.php';

$bid = (int)$current_business['id'];
$pdo = getDB();
$invoice_id = (int)($_GET['id'] ?? ($_POST['invoice_id'] ?? 0));
$error = '';

if ($invoice_id <= 0) {
    $_SESSION['flash_error'] = $is_rtl ? 'غلط بل نمبر منتخب کیا گیا ہے۔' : 'Invalid invoice ID.';
    header("Location: sales.php");
    exit;
}

// Fetch invoice and items with business isolation
try {
    $data = InvoiceHelper::getInvoiceWithItems($invoice_id, $bid);
    $inv = $data['invoice'];
    $items = $data['items'];
} catch (Exception $e) {
    $_SESSION['flash_error'] = $e->getMessage();
    header("Location: sales.php");
    exit;
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = $is_rtl ? 'سیکورٹی ٹوکن غلط ہے۔ دوبارہ کوشش کریں۔' : 'Security token mismatch. Please try again.';
    } else {
        try {
            InvoiceHelper::saveInvoiceEdit($invoice_id, $bid, $current_user['id'], $_POST);
            $_SESSION['flash_success'] = $is_rtl 
                ? "بل نمبر #{$inv['invoice_number']} کامیابی سے تبدیل ہو گیا ہے۔" 
                : "Invoice #{$inv['invoice_number']} updated successfully with accounting & inventory refreshed.";
            header("Location: print_invoice.php?id={$invoice_id}");
            exit;
        } catch (Exception $e) {
            $error = ($is_rtl ? 'بل محفوظ کرنے میں ناکامی: ' : 'Failed to save invoice: ') . $e->getMessage();
            // Refresh data from POST for re-rendering form
            $inv['customer_name'] = $_POST['customer_name'] ?? $inv['customer_name'];
            $inv['customer_phone'] = $_POST['customer_phone'] ?? $inv['customer_phone'];
            $inv['invoice_date'] = $_POST['invoice_date'] ?? $inv['invoice_date'];
            $inv['payment_method'] = $_POST['payment_method'] ?? $inv['payment_method'];
            $inv['discount'] = (float)($_POST['discount'] ?? $inv['discount']);
            $inv['paid_amount'] = (float)($_POST['paid_amount'] ?? $inv['paid_amount']);
            $inv['notes'] = $_POST['notes'] ?? $inv['notes'];
            if (!empty($_POST['items']) && is_array($_POST['items'])) {
                $items = $_POST['items'];
            }
        }
    }
}

// Lookup data for auto-completion
$stmtCust = $pdo->prepare("SELECT id, name, phone FROM customers WHERE business_id = ? ORDER BY name ASC");
$stmtCust->execute([$bid]);
$savedCustomers = $stmtCust->fetchAll();

// Product catalogue for sales / pos
$products = [];
if (in_array($inv['invoice_type'], ['sale', 'pos'])) {
    $stmtProds = $pdo->prepare("SELECT id, name, selling_price, current_stock FROM products WHERE business_id = ? AND status = 'active' ORDER BY name ASC");
    $stmtProds->execute([$bid]);
    $products = $stmtProds->fetchAll();
}

// Service descriptions for repair / refill autocomplete
$serviceCatalog = [];
if (in_array($inv['invoice_type'], ['repair', 'refill'])) {
    $sqlSrv = ($inv['invoice_type'] === 'refill') 
        ? "SELECT description, default_rate, line_type FROM service_descriptions WHERE business_id = ? AND line_type = 'refill' ORDER BY usage_count DESC"
        : "SELECT description, default_rate, line_type FROM service_descriptions WHERE business_id = ? ORDER BY usage_count DESC";
    $stmtSrv = $pdo->prepare($sqlSrv);
    $stmtSrv->execute([$bid]);
    $serviceCatalog = $stmtSrv->fetchAll();
}

// Back URL based on invoice type
$backUrl = 'sales.php';
$typeTitle = $is_rtl ? 'سیلز بل' : 'Sales Invoice';
$typeIcon = '🛒';

if ($inv['invoice_type'] === 'repair') {
    $backUrl = 'repair_invoices.php';
    $typeTitle = $is_rtl ? 'پرنٹر مرمت بل' : 'Repair Invoice';
    $typeIcon = '🔧';
} elseif ($inv['invoice_type'] === 'refill') {
    $backUrl = 'refill_invoices.php';
    $typeTitle = $is_rtl ? 'ٹونر ریفلنگ بل' : 'Refill Invoice';
    $typeIcon = '🧪';
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
  <div>
    <div style="display:flex; align-items:center; gap:8px; margin-bottom:4px;">
      <a href="<?php echo $backUrl; ?>" style="text-decoration:none; color:var(--text-muted); font-size:0.9rem;">&larr; <?php echo h(__('back')); ?></a>
      <span style="color:var(--text-muted);">&bull;</span>
      <span class="badge badge-primary"><?php echo strtoupper($inv['invoice_type']); ?></span>
    </div>
    <h1 class="page-title">
      ✏️ <?php echo $is_rtl ? 'بل میں ترمیم کریں' : 'Edit Invoice'; ?> #<?php echo h($inv['invoice_number']); ?>
    </h1>
    <p style="color:var(--text-muted); font-size:0.9rem;">
      <?php echo $typeIcon . ' ' . $typeTitle; ?> &bull; <?php echo $is_rtl ? 'تمام تبدیلیاں خودکار ڈبل انٹری اکاؤنٹنگ اور اسٹاک کو درست رکھیں گی' : 'Modifications will automatically adjust double-entry ledgers, customer balances, and stock'; ?>
    </p>
  </div>
  <div style="display:flex; gap:10px; align-items:center;">
    <a href="print_invoice.php?id=<?php echo $inv['id']; ?>" target="_blank" class="btn btn-outline">
      🖨️ <?php echo h(__('print')); ?>
    </a>
    <button type="button" class="btn btn-danger" onclick="confirmDeleteInvoice(<?php echo $inv['id']; ?>, '<?php echo h($inv['invoice_number']); ?>', '<?php echo $backUrl; ?>')">
      🗑️ <?php echo h(__('delete')); ?>
    </button>
  </div>
</div>

<?php if (!empty($error)): ?>
  <div class="alert alert-danger" style="margin-bottom:20px;">
    <strong>❌ <?php echo h($error); ?></strong>
  </div>
<?php endif; ?>

<form method="POST" action="invoice_edit.php?id=<?php echo $inv['id']; ?>" id="editInvoiceForm">
  <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
  <input type="hidden" name="invoice_id" value="<?php echo $inv['id']; ?>">

  <!-- 1. Customer & Invoice Meta Details -->
  <div class="card" style="margin-bottom:20px;">
    <div class="card-header">
      <span>👤 <?php echo $is_rtl ? 'گاہک و بل کی بنیادی معلومات' : 'Customer & Invoice Details'; ?></span>
      <?php if (!empty($inv['reference_job_id'])): ?>
        <span class="badge badge-primary">
          🔗 <?php echo $is_rtl ? 'جاب کارڈ منسلک #' : 'Linked Job Card #'; ?><?php echo (int)$inv['reference_job_id']; ?>
        </span>
      <?php endif; ?>
    </div>
    <div class="card-body">
      <div class="grid grid-cols-4">
        <div class="form-group">
          <label class="form-label"><?php echo h(__('customer_name')); ?> *</label>
          <input type="text" name="customer_name" id="custNameInput" list="customerDatalist" class="form-control" value="<?php echo h($inv['customer_name']); ?>" required placeholder="<?php echo $is_rtl ? 'گاہک کا نام درج کریں' : 'Customer Name'; ?>" onchange="autoFillCustPhone(this)">
          <datalist id="customerDatalist">
            <?php foreach ($savedCustomers as $sc): ?>
              <option value="<?php echo h($sc['name']); ?>" data-phone="<?php echo h($sc['phone']); ?>"><?php echo h($sc['phone'] ? $sc['name'] . ' (' . $sc['phone'] . ')' : $sc['name']); ?></option>
            <?php endforeach; ?>
          </datalist>
        </div>

        <div class="form-group">
          <label class="form-label"><?php echo h(__('phone')); ?></label>
          <input type="text" name="customer_phone" id="custPhoneInput" class="form-control" value="<?php echo h($inv['customer_phone'] ?? ''); ?>" placeholder="0300-1234567">
        </div>

        <div class="form-group">
          <label class="form-label"><?php echo h(__('date')); ?> *</label>
          <input type="date" name="invoice_date" class="form-control" value="<?php echo h($inv['invoice_date']); ?>" required>
        </div>

        <div class="form-group">
          <label class="form-label"><?php echo h(__('payment_method')); ?></label>
          <select name="payment_method" class="form-control">
            <option value="cash" <?php echo ($inv['payment_method'] === 'cash') ? 'selected' : ''; ?>><?php echo $is_rtl ? 'نقد (کیش)' : 'Cash'; ?></option>
            <option value="bank" <?php echo ($inv['payment_method'] === 'bank') ? 'selected' : ''; ?>><?php echo $is_rtl ? 'بینک ٹرانسفر' : 'Bank Transfer'; ?></option>
            <option value="credit" <?php echo ($inv['payment_method'] === 'credit') ? 'selected' : ''; ?>><?php echo $is_rtl ? 'ادھار (کریڈٹ)' : 'Credit / Outstanding'; ?></option>
            <option value="split" <?php echo ($inv['payment_method'] === 'split') ? 'selected' : ''; ?>><?php echo $is_rtl ? 'جزوی ادائیگی' : 'Split / Partial'; ?></option>
          </select>
        </div>
      </div>
    </div>
  </div>

  <!-- 2. Line Items Table -->
  <div class="card card-table-overflow" style="margin-bottom:20px;">
    <div class="card-header">
      <span>📦 <?php echo $is_rtl ? 'بل کے آئٹمز اور سروسز کی فہرست' : 'Billed Items & Services'; ?></span>
      <div style="display:flex; gap:8px;">
        <?php if (in_array($inv['invoice_type'], ['repair', 'refill'])): ?>
          <a href="service_descriptions.php" target="_blank" class="btn btn-outline btn-sm" title="Manage catalog default rates">⚙️ <?php echo $is_rtl ? 'ریٹس کی فہرست' : 'Rates Catalog'; ?></a>
        <?php endif; ?>
        <button type="button" id="btnAddRow" class="btn btn-primary btn-sm">➕ <?php echo $is_rtl ? 'نیا آئٹم شامل کریں' : 'Add Item Line'; ?></button>
      </div>
    </div>

    <div class="table-responsive table-overflow-visible">
      <table class="table" id="itemsTable">
        <thead>
          <tr>
            <th style="width:45px;" class="text-center">#</th>
            <?php if (in_array($inv['invoice_type'], ['repair', 'refill'])): ?>
              <th style="width:130px;"><?php echo $is_rtl ? 'قسم' : 'Line Type'; ?></th>
            <?php endif; ?>
            <th><?php echo h(__('description')); ?> *</th>
            <th style="width:110px;" class="text-center"><?php echo h(__('qty')); ?></th>
            <th style="width:140px;" class="text-right"><?php echo h(__('rate')); ?></th>
            <th style="width:150px;" class="text-right"><?php echo h(__('amount')); ?></th>
            <th style="width:50px;" class="text-center"><?php echo h(__('action')); ?></th>
          </tr>
        </thead>
        <tbody id="itemsTableBody">
          <?php foreach ($items as $idx => $it): ?>
            <tr>
              <td class="text-center row-num"><?php echo $idx + 1; ?></td>

              <?php if (in_array($inv['invoice_type'], ['repair', 'refill'])): ?>
                <td>
                  <select name="items[<?php echo $idx; ?>][line_type]" class="form-control" style="font-size:0.85rem; padding:4px 6px;">
                    <?php if ($inv['invoice_type'] === 'repair'): ?>
                      <option value="service" <?php echo ($it['line_type'] ?? '') === 'service' ? 'selected' : ''; ?>><?php echo $is_rtl ? 'سروس' : 'Service'; ?></option>
                      <option value="part" <?php echo ($it['line_type'] ?? '') === 'part' ? 'selected' : ''; ?>><?php echo $is_rtl ? 'پرزہ (پارٹ)' : 'Spare Part'; ?></option>
                      <option value="labour" <?php echo ($it['line_type'] ?? '') === 'labour' ? 'selected' : ''; ?>><?php echo $is_rtl ? 'مزدوری' : 'Labour'; ?></option>
                    <?php else: ?>
                      <option value="refill" <?php echo ($it['line_type'] ?? '') === 'refill' ? 'selected' : ''; ?>><?php echo $is_rtl ? 'ریفلنگ' : 'Refill'; ?></option>
                      <option value="part" <?php echo ($it['line_type'] ?? '') === 'part' ? 'selected' : ''; ?>><?php echo $is_rtl ? 'پرزہ (پارٹ)' : 'Spare Part'; ?></option>
                    <?php endif; ?>
                  </select>
                </td>
              <?php else: ?>
                <input type="hidden" name="items[<?php echo $idx; ?>][line_type]" value="product">
              <?php endif; ?>

              <td>
                <?php if (in_array($inv['invoice_type'], ['sale', 'pos'])): ?>
                  <div style="display:flex; flex-direction:column; gap:4px;">
                    <select class="form-control product-select" name="items[<?php echo $idx; ?>][product_id]" onchange="onProductSelect(this, <?php echo $idx; ?>)" style="font-size:0.85rem;">
                      <option value="">-- <?php echo $is_rtl ? 'مصنوعات منتخب کریں (اختیاری)' : 'Select Stock Product (Optional)'; ?> --</option>
                      <?php foreach ($products as $p): ?>
                        <option value="<?php echo $p['id']; ?>" data-price="<?php echo $p['selling_price']; ?>" data-name="<?php echo h($p['name']); ?>" <?php echo ((int)($it['product_id'] ?? 0) === (int)$p['id']) ? 'selected' : ''; ?>>
                          <?php echo h($p['name']); ?> (<?php echo format_currency($p['selling_price']); ?>) - Stock: <?php echo $p['current_stock']; ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                    <input type="text" name="items[<?php echo $idx; ?>][description]" class="form-control item-desc" required value="<?php echo h($it['description']); ?>" placeholder="<?php echo $is_rtl ? 'تفصیل درج کریں' : 'Custom Description'; ?>" style="font-size:0.9rem;">
                  </div>
                <?php else: ?>
                  <input type="text" name="items[<?php echo $idx; ?>][description]" list="servicesDatalist" class="form-control item-desc" required value="<?php echo h($it['description']); ?>" placeholder="<?php echo $is_rtl ? 'سروس یا پرزے کی تفصیل' : 'Description of service or part'; ?>" onchange="onServiceSelect(this)">
                <?php endif; ?>
              </td>

              <td>
                <input type="number" name="items[<?php echo $idx; ?>][quantity]" class="form-control text-center item-qty" step="1" min="1" value="<?php echo (float)$it['quantity']; ?>" required oninput="calculateInvoice()">
              </td>

              <td>
                <input type="number" name="items[<?php echo $idx; ?>][unit_price]" class="form-control text-right item-rate" step="0.01" min="0" value="<?php echo (float)$it['unit_price']; ?>" required oninput="calculateInvoice()">
              </td>

              <td>
                <input type="text" class="form-control text-right item-amount" readonly value="<?php echo number_format((float)$it['total_amount'], 2, '.', ''); ?>" style="background:var(--slate-100); font-weight:700;">
              </td>

              <td class="text-center">
                <button type="button" class="btn btn-outline btn-sm text-danger" onclick="removeRow(this)" title="Remove">&times;</button>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <?php if (!empty($serviceCatalog)): ?>
      <datalist id="servicesDatalist">
        <?php foreach ($serviceCatalog as $cat): ?>
          <option value="<?php echo h($cat['description']); ?>" data-rate="<?php echo $cat['default_rate']; ?>" data-type="<?php echo $cat['line_type']; ?>"><?php echo h($cat['description']); ?> (Rs <?php echo $cat['default_rate']; ?>)</option>
        <?php endforeach; ?>
      </datalist>
    <?php endif; ?>
  </div>

  <!-- 3. Totals & Notes Summary -->
  <div class="grid grid-cols-2" style="gap:20px; align-items:start;">
    <div class="card">
      <div class="card-header">
        <span>📝 <?php echo $is_rtl ? 'نوٹس اور شرائط' : 'Notes & Remarks'; ?></span>
      </div>
      <div class="card-body">
        <textarea name="notes" class="form-control" rows="5" placeholder="<?php echo $is_rtl ? 'بل پر پرنٹ کرنے کے لیے خصوصی ہدایات یا نوٹس...' : 'Invoice notes, terms, or technician remarks...'; ?>"><?php echo h($inv['notes'] ?? ''); ?></textarea>
      </div>
    </div>

    <div class="card">
      <div class="card-header">
        <span>💰 <?php echo $is_rtl ? 'بل کا حساب و ادائیگی' : 'Bill Calculation & Payment'; ?></span>
      </div>
      <div class="card-body" style="display:flex; flex-direction:column; gap:12px;">
        <div style="display:flex; justify-content:space-between; align-items:center; font-size:1rem;">
          <span style="color:var(--text-muted);"><?php echo h(__('subtotal')); ?>:</span>
          <span style="font-weight:700;" id="dispSubtotal"><?php echo format_currency($inv['subtotal']); ?></span>
          <input type="hidden" name="subtotal" id="inputSubtotal" value="<?php echo $inv['subtotal']; ?>">
        </div>

        <div style="display:flex; justify-content:space-between; align-items:center;">
          <span style="color:var(--text-muted);"><?php echo h(__('discount')); ?>:</span>
          <div style="max-width:160px;">
            <input type="number" name="discount" id="inputDiscount" class="form-control text-right" step="0.01" min="0" value="<?php echo (float)$inv['discount']; ?>" oninput="calculateInvoice()">
          </div>
        </div>

        <div style="display:flex; justify-content:space-between; align-items:center; border-top:2px solid var(--border-dark); padding-top:10px; font-size:1.2rem;">
          <strong style="color:var(--slate-800);"><?php echo h(__('total_amount')); ?>:</strong>
          <strong style="color:var(--primary-dark);" id="dispTotal"><?php echo format_currency($inv['total_amount']); ?></strong>
          <input type="hidden" name="total_amount" id="inputTotal" value="<?php echo $inv['total_amount']; ?>">
        </div>

        <div style="display:flex; justify-content:space-between; align-items:center; border-top:1px dashed var(--border-color); padding-top:8px;">
          <span style="color:var(--text-muted);"><?php echo h(__('paid')); ?>:</span>
          <div style="max-width:160px;">
            <input type="number" name="paid_amount" id="inputPaid" class="form-control text-right" step="0.01" min="0" value="<?php echo (float)$inv['paid_amount']; ?>" oninput="calculateInvoice()">
          </div>
        </div>

        <div style="display:flex; justify-content:space-between; align-items:center; background:#fef2f2; padding:10px 14px; border-radius:8px; border:1px solid #fee2e2;">
          <strong style="color:#991b1b;"><?php echo h(__('balance')); ?>:</strong>
          <strong style="color:#b91c1c; font-size:1.15rem;" id="dispBalance"><?php echo format_currency($inv['balance_amount']); ?></strong>
          <input type="hidden" name="balance_amount" id="inputBalance" value="<?php echo $inv['balance_amount']; ?>">
        </div>

        <div style="margin-top:15px; display:flex; gap:10px; justify-content:flex-end;">
          <a href="<?php echo $backUrl; ?>" class="btn btn-outline" style="padding:10px 20px;">
            <?php echo h(__('cancel')); ?>
          </a>
          <button type="submit" class="btn btn-success" style="padding:10px 28px; font-size:1rem; font-weight:700;">
            💾 <?php echo $is_rtl ? 'تبدیلیاں محفوظ کریں' : 'Save Changes'; ?>
          </button>
        </div>
      </div>
    </div>
  </div>
</form>

<script>
var invType = "<?php echo $inv['invoice_type']; ?>";
var rowIndex = <?php echo count($items); ?>;

function autoFillCustPhone(input) {
  var val = input.value.trim();
  var opts = document.getElementById('customerDatalist').options;
  for (var i = 0; i < opts.length; i++) {
    if (opts[i].value.toLowerCase() === val.toLowerCase()) {
      var ph = opts[i].getAttribute('data-phone');
      if (ph) {
        document.getElementById('custPhoneInput').value = ph;
      }
      break;
    }
  }
}

function onProductSelect(selectElem, idx) {
  var selected = selectElem.options[selectElem.selectedIndex];
  var price = selected.getAttribute('data-price');
  var name = selected.getAttribute('data-name');
  var tr = selectElem.closest('tr');
  if (name) {
    tr.querySelector('.item-desc').value = name;
  }
  if (price !== null && price !== '') {
    tr.querySelector('.item-rate').value = parseFloat(price).toFixed(2);
  }
  calculateInvoice();
}

function onServiceSelect(input) {
  var val = input.value.trim();
  var datalist = document.getElementById('servicesDatalist');
  if (!datalist) return;
  var opts = datalist.options;
  for (var i = 0; i < opts.length; i++) {
    if (opts[i].value.toLowerCase() === val.toLowerCase()) {
      var rate = opts[i].getAttribute('data-rate');
      var type = opts[i].getAttribute('data-type');
      var tr = input.closest('tr');
      if (rate && tr.querySelector('.item-rate')) {
        tr.querySelector('.item-rate').value = parseFloat(rate).toFixed(2);
      }
      if (type && tr.querySelector('select[name*="[line_type]"]')) {
        tr.querySelector('select[name*="[line_type]"]').value = type;
      }
      break;
    }
  }
  calculateInvoice();
}

function calculateInvoice() {
  var rows = document.querySelectorAll('#itemsTableBody tr');
  var subtotal = 0;

  rows.forEach(function(row) {
    var qty = parseFloat(row.querySelector('.item-qty').value) || 0;
    var rate = parseFloat(row.querySelector('.item-rate').value) || 0;
    var amount = (qty * rate);
    row.querySelector('.item-amount').value = amount.toFixed(2);
    subtotal += amount;
  });

  var discount = parseFloat(document.getElementById('inputDiscount').value) || 0;
  var total = Math.max(0, subtotal - discount);
  var paid = parseFloat(document.getElementById('inputPaid').value) || 0;
  var balance = Math.max(0, total - paid);

  var currencySym = "<?php echo addslashes($current_business['currency'] ?? 'Rs'); ?> ";
  document.getElementById('dispSubtotal').textContent = currencySym + subtotal.toFixed(2);
  document.getElementById('inputSubtotal').value = subtotal.toFixed(2);

  document.getElementById('dispTotal').textContent = currencySym + total.toFixed(2);
  document.getElementById('inputTotal').value = total.toFixed(2);

  document.getElementById('dispBalance').textContent = currencySym + balance.toFixed(2);
  document.getElementById('inputBalance').value = balance.toFixed(2);
}

function removeRow(btn) {
  var tbody = document.getElementById('itemsTableBody');
  if (tbody.rows.length <= 1) {
    alert("<?php echo $is_rtl ? 'کم از کم ایک آئٹم لائن کا ہونا ضروری ہے۔' : 'At least one item line is required.'; ?>");
    return;
  }
  btn.closest('tr').remove();
  reindexRows();
  calculateInvoice();
}

function reindexRows() {
  var rows = document.querySelectorAll('#itemsTableBody tr');
  rows.forEach(function(row, idx) {
    row.querySelector('.row-num').textContent = (idx + 1);
  });
}

document.getElementById('btnAddRow').addEventListener('click', function() {
  var tbody = document.getElementById('itemsTableBody');
  var idx = rowIndex++;
  var tr = document.createElement('tr');

  var html = '<td class="text-center row-num">' + (tbody.rows.length + 1) + '</td>';

  if (invType === 'repair') {
    html += '<td><select name="items[' + idx + '][line_type]" class="form-control" style="font-size:0.85rem; padding:4px 6px;">' +
            '<option value="service"><?php echo $is_rtl ? 'سروس' : 'Service'; ?></option>' +
            '<option value="part"><?php echo $is_rtl ? 'پرزہ (پارٹ)' : 'Spare Part'; ?></option>' +
            '<option value="labour"><?php echo $is_rtl ? 'مزدوری' : 'Labour'; ?></option>' +
            '</select></td>';
    html += '<td><input type="text" name="items[' + idx + '][description]" list="servicesDatalist" class="form-control item-desc" required placeholder="<?php echo $is_rtl ? 'سروس یا پرزے کی تفصیل' : 'Description of service or part'; ?>" onchange="onServiceSelect(this)"></td>';
  } else if (invType === 'refill') {
    html += '<td><select name="items[' + idx + '][line_type]" class="form-control" style="font-size:0.85rem; padding:4px 6px;">' +
            '<option value="refill"><?php echo $is_rtl ? 'ریفلنگ' : 'Refill'; ?></option>' +
            '<option value="part"><?php echo $is_rtl ? 'پرزہ (پارٹ)' : 'Spare Part'; ?></option>' +
            '</select></td>';
    html += '<td><input type="text" name="items[' + idx + '][description]" list="servicesDatalist" class="form-control item-desc" required placeholder="<?php echo $is_rtl ? 'سروس یا پرزے کی تفصیل' : 'Description of service or part'; ?>" onchange="onServiceSelect(this)"></td>';
  } else {
    // Sale or POS
    html += '<input type="hidden" name="items[' + idx + '][line_type]" value="product">';
    html += '<td><div style="display:flex; flex-direction:column; gap:4px;">' +
            '<select class="form-control product-select" name="items[' + idx + '][product_id]" onchange="onProductSelect(this, ' + idx + ')" style="font-size:0.85rem;">' +
            '<option value="">-- <?php echo $is_rtl ? 'مصنوعات منتخب کریں (اختیاری)' : 'Select Stock Product (Optional)'; ?> --</option>';
    <?php foreach ($products as $p): ?>
      html += '<option value="<?php echo $p['id']; ?>" data-price="<?php echo $p['selling_price']; ?>" data-name="<?php echo addslashes(h($p['name'])); ?>"><?php echo addslashes(h($p['name'])); ?> (<?php echo format_currency($p['selling_price']); ?>) - Stock: <?php echo $p['current_stock']; ?></option>';
    <?php endforeach; ?>
    html += '</select>' +
            '<input type="text" name="items[' + idx + '][description]" class="form-control item-desc" required placeholder="<?php echo $is_rtl ? 'تفصیل درج کریں' : 'Custom Description'; ?>" style="font-size:0.9rem;">' +
            '</div></td>';
  }

  html += '<td><input type="number" name="items[' + idx + '][quantity]" class="form-control text-center item-qty" step="1" min="1" value="1" required oninput="calculateInvoice()"></td>';
  html += '<td><input type="number" name="items[' + idx + '][unit_price]" class="form-control text-right item-rate" step="0.01" min="0" value="0.00" required oninput="calculateInvoice()"></td>';
  html += '<td><input type="text" class="form-control text-right item-amount" readonly value="0.00" style="background:var(--slate-100); font-weight:700;"></td>';
  html += '<td class="text-center"><button type="button" class="btn btn-outline btn-sm text-danger" onclick="removeRow(this)" title="Remove">&times;</button></td>';

  tr.innerHTML = html;
  tbody.appendChild(tr);
});

// Run initial calculate to ensure display consistency
calculateInvoice();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
