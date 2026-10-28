<?php
require_once __DIR__ . '/config/init.php';
require_once __DIR__ . '/includes/accounting_helper.php';
require_once __DIR__ . '/includes/inventory_helper.php';

$bid = (int)($current_business['id'] ?? ($_SESSION['current_business_id'] ?? 1));
$pdo = getDB();
$error = '';

// Handle POS Checkout
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['action']) && $_POST['action'] === 'checkout') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = "Security token mismatch.";
    } else {
        $customerIdVal = $_POST['customer_id'] ?? 'walkin';
        $customer_name = trim($_POST['customer_name'] ?? 'Walk-in Customer / کاؤنٹر گاہک');
        $customer_phone = trim($_POST['customer_phone'] ?? '');
        $payment_method = $_POST['payment_method'] ?? 'cash';
        $paid_amount = (float)($_POST['paid_amount'] ?? 0);
        $cart_items = json_decode($_POST['cart_json'] ?? '[]', true);

        if (empty($cart_items)) {
            $error = $is_rtl ? 'ٹوکری خالی ہے! براہ کرم آئٹم شامل کریں۔' : 'Cart is empty! Please add products.';
        } else {
            $customerId = null;

            // If a saved customer was chosen from the dropdown
            if ($customerIdVal !== 'walkin' && is_numeric($customerIdVal) && (int)$customerIdVal > 0) {
                $cStmt = $pdo->prepare("SELECT id, name, phone FROM customers WHERE id = ? AND business_id = ?");
                $cStmt->execute([(int)$customerIdVal, $bid]);
                $custRow = $cStmt->fetch();
                if ($custRow) {
                    $customerId = (int)$custRow['id'];
                    $customer_name = $custRow['name'];
                    $customer_phone = $custRow['phone'];
                }
            }

            // If Walk-in customer or new customer name
            if (!$customerId) {
                $custStmt = $pdo->prepare("SELECT id FROM customers WHERE business_id = ? AND name = ? LIMIT 1");
                $custStmt->execute([$bid, $customer_name]);
                $customerId = $custStmt->fetchColumn();
                if (!$customerId) {
                    $ins = $pdo->prepare("INSERT INTO customers (business_id, name, phone) VALUES (?, ?, ?)");
                    $ins->execute([$bid, $customer_name, $customer_phone]);
                    $customerId = $pdo->lastInsertId();
                }
            }

            $lastInv = $pdo->query("SELECT MAX(id) FROM invoices WHERE business_id = {$bid}")->fetchColumn() ?: 1000;
            $invoice_number = ($current_settings['invoice_prefix'] ?? 'INV-') . ($lastInv + 1);

            $subtotal = 0.0;
            foreach ($cart_items as $ci) {
                $subtotal += ((float)$ci['qty'] * (float)$ci['price']);
            }
            $discount = (float)($_POST['discount'] ?? 0);
            $total_amount = max(0, $subtotal - $discount);
            if ($paid_amount == 0 && $payment_method === 'cash') {
                $paid_amount = $total_amount;
            }
            $balance_amount = max(0, $total_amount - $paid_amount);

            $pdo->beginTransaction();
            try {
                $stmtInv = $pdo->prepare("INSERT INTO invoices 
                    (business_id, invoice_type, invoice_number, customer_id, customer_name, customer_phone, invoice_date, subtotal, discount, total_amount, paid_amount, balance_amount, payment_method, notes, created_by) 
                    VALUES (?, 'pos', ?, ?, ?, ?, CURDATE(), ?, ?, ?, ?, ?, ?, 'POS Counter Sale', ?)");
                $stmtInv->execute([
                    $bid,
                    $invoice_number,
                    $customerId,
                    $customer_name,
                    $customer_phone,
                    $subtotal,
                    $discount,
                    $total_amount,
                    $paid_amount,
                    $balance_amount,
                    $payment_method,
                    $current_user['id']
                ]);
                $invoice_id = $pdo->lastInsertId();

                $stmtItem = $pdo->prepare("INSERT INTO invoice_items 
                    (invoice_id, line_number, line_type, product_id, description, quantity, unit_price, total_amount) 
                    VALUES (?, ?, 'product', ?, ?, ?, ?, ?)");

                $lno = 1;
                $cogs_total = 0.0;

                foreach ($cart_items as $item) {
                    $pid = (int)($item['id'] ?? 0);
                    $qty = (float)($item['qty'] ?? 1);
                    $price = (float)($item['price'] ?? 0);
                    $line_total = $qty * $price;
                    $name = $item['name'] ?? 'Item';

                    $stmtItem->execute([$invoice_id, $lno++, $pid ?: null, $name, $qty, $price, $line_total]);

                    if ($pid > 0) {
                        // Stock movement & COGS
                        $pQuery = $pdo->prepare("SELECT purchase_price FROM products WHERE id = ? AND business_id = ?");
                        $pQuery->execute([$pid, $bid]);
                        $cost = (float)$pQuery->fetchColumn();
                        $cogs_total += ($qty * $cost);

                        InventoryHelper::recordMovement($bid, $pid, 'sale', $qty, $cost, $invoice_id, "POS {$invoice_number}");
                    }
                }

                // Double-Entry Journal Posting:
                $cashAccId = AccountingEngine::getAccountIdByCode($bid, '1010');
                $arAccId = AccountingEngine::getAccountIdByCode($bid, '1030');
                $salesRevAccId = AccountingEngine::getAccountIdByCode($bid, '4010');
                $invAccId = AccountingEngine::getAccountIdByCode($bid, '1040');
                $cogsAccId = AccountingEngine::getAccountIdByCode($bid, '5010');

                $journalLines = [];
                if ($paid_amount > 0) {
                    $journalLines[] = [
                        'account_id' => $cashAccId,
                        'debit' => $paid_amount,
                        'credit' => 0.00,
                        'narration' => "Cash received for POS {$invoice_number}"
                    ];
                }
                if ($balance_amount > 0) {
                    $journalLines[] = [
                        'account_id' => $arAccId,
                        'debit' => $balance_amount,
                        'credit' => 0.00,
                        'narration' => "Receivable for POS {$invoice_number}"
                    ];
                }
                $journalLines[] = [
                    'account_id' => $salesRevAccId,
                    'debit' => 0.00,
                    'credit' => $total_amount,
                    'narration' => "Sales Revenue POS {$invoice_number}"
                ];

                // Inventory & COGS accounting lines if inventory tracked
                if ($cogs_total > 0 && !empty($current_settings['inventory_enabled'])) {
                    $journalLines[] = [
                        'account_id' => $cogsAccId,
                        'debit' => $cogs_total,
                        'credit' => 0.00,
                        'narration' => "COGS for POS {$invoice_number}"
                    ];
                    $journalLines[] = [
                        'account_id' => $invAccId,
                        'debit' => 0.00,
                        'credit' => $cogs_total,
                        'narration' => "Inventory reduced for POS {$invoice_number}"
                    ];
                }

                AccountingEngine::postJournalEntry(
                    $bid,
                    "JV-{$invoice_number}",
                    date('Y-m-d'),
                    'sale',
                    $invoice_id,
                    "POS Sale {$invoice_number} to {$customer_name}",
                    $journalLines,
                    $current_user['id']
                );

                $pdo->commit();
                log_audit('POS_SALE', 'invoice', $invoice_id, "POS sale {$invoice_number}");

                header("Location: print_invoice.php?id={$invoice_id}");
                exit;

            } catch (Exception $e) {
                $pdo->rollBack();
                $error = "POS Checkout failed: " . $e->getMessage();
            }
        }
    }
}

// Fetch fast products for quick selection
$stmtProds = $pdo->prepare("SELECT * FROM products WHERE business_id = ? AND status = 'active' ORDER BY name ASC LIMIT 25");
$stmtProds->execute([$bid]);
$quickProds = $stmtProds->fetchAll();

// Fetch saved customers for customer dropdown
$stmtCust = $pdo->prepare("SELECT id, name, phone FROM customers WHERE business_id = ? ORDER BY name ASC");
$stmtCust->execute([$bid]);
$savedCustomers = $stmtCust->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
  <div>
    <h1 class="page-title">
      ⚡ <?php echo h(__('nav_pos')); ?>
    </h1>
    <p style="color:var(--text-muted); font-size:0.9rem;">
      <?php echo $is_rtl ? 'فوری کاؤنٹر سیل، بارکوڈ اسکینگ اور پرنٹنگ' : 'Fast counter billing for printers, toners, parts, and accessories'; ?>
    </p>
  </div>
</div>

<?php if (!empty($error)): ?>
  <div class="alert alert-danger"><?php echo h($error); ?></div>
<?php endif; ?>

<div class="grid grid-cols-3" style="align-items: flex-start;">
  <!-- Left Side: Product Selector Grid -->
  <div class="card" style="grid-column: span 2;">
    <div class="card-header">
      <input type="text" id="posSearchInput" class="form-control" placeholder="<?php echo $is_rtl ? '🔍 پروڈکٹ تلاش کریں یا بارکوڈ اسکین کریں...' : '🔍 Search product or scan barcode...'; ?>" autofocus>
    </div>
    <div class="card-body" style="max-height: 520px; overflow-y: auto;">
      <div class="grid grid-cols-3" id="posProductGrid">
        <?php foreach ($quickProds as $qp): ?>
          <div class="card pos-prod-card" style="cursor:pointer; margin-bottom:0; padding:12px; border:1px solid #cbd5e1; transition:all 0.15s;" 
               onclick="addToCart(<?php echo $qp['id']; ?>, '<?php echo addslashes(h($qp['name'])); ?>', <?php echo (float)$qp['selling_price']; ?>, <?php echo (float)$qp['current_stock']; ?>)">
            <div style="font-weight:700; font-size:13.5px; margin-bottom:4px;"><?php echo h($qp['name']); ?></div>
            <div style="font-size:11px; color:#64748b;"><?php echo h($qp['code_or_barcode']); ?></div>
            <div style="display:flex; justify-content:space-between; align-items:center; margin-top:8px;">
              <span style="font-weight:800; color:var(--primary); font-size:14px;"><?php echo format_currency($qp['selling_price']); ?></span>
              <span class="badge <?php echo ($qp['current_stock'] <= $qp['reorder_level']) ? 'badge-danger' : 'badge-success'; ?>">
                <?php echo $is_rtl ? 'اسٹاک' : 'Stock'; ?>: <?php echo (int)$qp['current_stock']; ?>
              </span>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <!-- Right Side: POS Cart / Checkout Panel -->
  <div class="card">
    <div class="card-header">
      <span>🛒 <?php echo $is_rtl ? 'ٹوکری و بل' : 'Current Order'; ?></span>
      <button type="button" class="btn btn-outline btn-sm" onclick="clearCart()">🗑️ <?php echo $is_rtl ? 'خالی کریں' : 'Clear'; ?></button>
    </div>
    <div class="card-body" style="padding:10px;">
      <form method="POST" action="pos.php" id="posForm">
        <input type="hidden" name="action" value="checkout">
        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
        <input type="hidden" name="cart_json" id="cartJsonInput" value="[]">

        <div class="form-group" style="margin-bottom:8px;">
          <label style="font-size:11.5px; font-weight:700; margin-bottom:3px; display:flex; justify-content:space-between; align-items:center;">
            <span>👤 <?php echo $is_rtl ? 'گاہک / Customer:' : 'Customer:'; ?></span>
            <a href="javascript:void(0)" onclick="toggleCustomCustomer()" style="font-size:11px; color:var(--primary); text-decoration:none;">
              + <?php echo $is_rtl ? 'نیا گاہک / نام درج کریں' : 'Custom Name'; ?>
            </a>
          </label>
          <select name="customer_id" id="posCustomerSelect" class="form-control" onchange="handlePosCustomerChange(this)" style="font-size:12.5px; font-weight:600;">
            <option value="walkin" selected data-name="Walk-in Customer / کاؤنٹر گاہک" data-phone="">
              🚶 Walk-in Customer / کاؤنٹر گاہک (Default)
            </option>
            <?php if (!empty($savedCustomers)): ?>
              <optgroup label="<?php echo $is_rtl ? 'محفوظ شدہ کسٹمرز' : 'Saved Customers'; ?>">
                <?php foreach ($savedCustomers as $sc): ?>
                  <?php if (stripos($sc['name'], 'walk-in') !== false) continue; ?>
                  <option value="<?php echo $sc['id']; ?>" data-name="<?php echo h($sc['name']); ?>" data-phone="<?php echo h($sc['phone']); ?>">
                    <?php echo h($sc['name']); ?><?php echo !empty($sc['phone']) ? ' (' . h($sc['phone']) . ')' : ''; ?>
                  </option>
                <?php endforeach; ?>
              </optgroup>
            <?php endif; ?>
          </select>

          <!-- Hidden inputs holding selected customer name and phone -->
          <input type="hidden" name="customer_name" id="posCustomerName" value="Walk-in Customer / کاؤنٹر گاہک">
          <input type="hidden" name="customer_phone" id="posCustomerPhone" value="">

          <!-- Expandable custom customer name input -->
          <div id="customCustomerBox" style="display:none; margin-top:6px; padding:6px 8px; background:#f1f5f9; border:1px solid #cbd5e1; border-radius:4px;">
            <input type="text" id="customCustNameInput" class="form-control" style="font-size:12px; height:30px; margin-bottom:4px;" placeholder="<?php echo $is_rtl ? 'کسٹمر کا نام درج کریں...' : 'Enter customer name...'; ?>" oninput="onCustomCustNameInput(this.value)">
            <input type="text" id="customCustPhoneInput" class="form-control" style="font-size:12px; height:30px;" placeholder="<?php echo $is_rtl ? 'فون نمبر (اختیاری)...' : 'Phone (optional)...'; ?>" oninput="onCustomCustPhoneInput(this.value)">
          </div>
        </div>

        <div style="max-height: 250px; overflow-y: auto; border: 1px solid #e2e8f0; border-radius: 6px; margin-bottom: 10px;">
          <table class="table" style="font-size:12.5px;">
            <thead>
              <tr>
                <th>Item</th>
                <th style="width:50px;">Qty</th>
                <th style="width:70px;" class="text-right">Total</th>
                <th style="width:30px;"></th>
              </tr>
            </thead>
            <tbody id="cartTableBody">
              <tr><td colspan="4" class="text-center text-muted" style="padding:15px;"><?php echo $is_rtl ? 'ٹوکری خالی ہے' : 'Cart is empty'; ?></td></tr>
            </tbody>
          </table>
        </div>

        <!-- Checkout Summaries -->
        <div style="background:#f8fafc; padding:10px; border-radius:6px; margin-bottom:10px;">
          <div style="display:flex; justify-content:space-between; margin-bottom:4px;">
            <span><?php echo h(__('subtotal')); ?>:</span>
            <strong id="cartSubtotal">Rs 0.00</strong>
          </div>
          <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:4px;">
            <span><?php echo h(__('discount')); ?>:</span>
            <input type="number" step="0.01" name="discount" id="posDiscount" class="form-control" style="width:100px; height:28px; text-align:right;" value="0.00" oninput="renderCart()">
          </div>
          <div style="display:flex; justify-content:space-between; font-size:1.15rem; font-weight:800; border-top:1px solid #cbd5e1; padding-top:6px; margin-top:6px;">
            <span><?php echo h(__('total')); ?>:</span>
            <span id="cartGrandTotal" style="color:var(--primary);">Rs 0.00</span>
          </div>
        </div>

        <div class="form-group" style="margin-bottom:8px;">
          <label class="form-label"><?php echo $is_rtl ? 'طریقہ ادائیگی' : 'Payment Method'; ?></label>
          <select name="payment_method" class="form-control">
            <option value="cash">💵 Cash (نقد)</option>
            <option value="bank">🏦 Bank Transfer</option>
            <option value="credit">📝 Credit (کھاتہ)</option>
          </select>
        </div>

        <button type="submit" class="btn btn-success" style="width:100%; padding:10px; font-size:1.1rem; font-weight:800;">
          ⚡ <?php echo $is_rtl ? 'مکمل کریں اور بل نکالیں' : 'Complete & Print A5 Bill'; ?> &rarr;
        </button>
      </form>
    </div>
  </div>
</div>

<script>
let cart = [];

function addToCart(id, name, price, stock) {
  const existing = cart.find(i => i.id === id);
  if (existing) {
    existing.qty += 1;
  } else {
    cart.push({ id, name, price, qty: 1 });
  }
  renderCart();
}

function updateQty(idx, qty) {
  qty = parseFloat(qty) || 0;
  if (qty <= 0) {
    cart.splice(idx, 1);
  } else {
    cart[idx].qty = qty;
  }
  renderCart();
}

function removeFromCart(idx) {
  cart.splice(idx, 1);
  renderCart();
}

function clearCart() {
  cart = [];
  renderCart();
}

function renderCart() {
  const tbody = document.getElementById('cartTableBody');
  const cartJson = document.getElementById('cartJsonInput');
  const subtotalEl = document.getElementById('cartSubtotal');
  const grandTotalEl = document.getElementById('cartGrandTotal');
  const discount = parseFloat(document.getElementById('posDiscount')?.value) || 0;

  cartJson.value = JSON.stringify(cart);

  if (cart.length === 0) {
    tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted" style="padding:15px;"><?php echo $is_rtl ? 'ٹوکری خالی ہے' : 'Cart is empty'; ?></td></tr>';
    subtotalEl.textContent = 'Rs 0.00';
    grandTotalEl.textContent = 'Rs 0.00';
    return;
  }

  let subtotal = 0;
  let html = '';
  cart.forEach((item, idx) => {
    const itemTotal = item.qty * item.price;
    subtotal += itemTotal;
    html += `
      <tr>
        <td><strong>${item.name}</strong><br><small class="text-muted">Rs ${item.price.toFixed(2)}</small></td>
        <td><input type="number" min="1" step="1" style="width:50px; text-align:center; padding:2px;" value="${item.qty}" onchange="updateQty(${idx}, this.value)"></td>
        <td class="text-right"><strong>${itemTotal.toFixed(2)}</strong></td>
        <td><button type="button" class="btn btn-danger btn-sm" style="padding:0 5px;" onclick="removeFromCart(${idx})">&times;</button></td>
      </tr>
    `;
  });

  tbody.innerHTML = html;
  subtotalEl.textContent = 'Rs ' + subtotal.toFixed(2);
  const total = Math.max(0, subtotal - discount);
  grandTotalEl.textContent = 'Rs ' + total.toFixed(2);
}

// Live search in product grid
document.getElementById('posSearchInput').addEventListener('input', function() {
  const q = this.value.toLowerCase().trim();
  document.querySelectorAll('.pos-prod-card').forEach(card => {
    const text = card.textContent.toLowerCase();
    card.style.display = text.includes(q) ? 'block' : 'none';
  });
});

// POS Customer Dropdown & Custom Walk-in handlers
function handlePosCustomerChange(sel) {
  const opt = sel.options[sel.selectedIndex];
  const name = opt.getAttribute('data-name') || opt.text;
  const phone = opt.getAttribute('data-phone') || '';
  document.getElementById('posCustomerName').value = name;
  document.getElementById('posCustomerPhone').value = phone;

  if (sel.value !== 'walkin') {
    document.getElementById('customCustomerBox').style.display = 'none';
  }
}

function toggleCustomCustomer() {
  const box = document.getElementById('customCustomerBox');
  const isHidden = (box.style.display === 'none' || box.style.display === '');
  box.style.display = isHidden ? 'block' : 'none';
  if (isHidden) {
    document.getElementById('posCustomerSelect').value = 'walkin';
    document.getElementById('customCustNameInput').focus();
  }
}

function onCustomCustNameInput(val) {
  document.getElementById('posCustomerName').value = val.trim() || 'Walk-in Customer / کاؤنٹر گاہک';
}

function onCustomCustPhoneInput(val) {
  document.getElementById('posCustomerPhone').value = val.trim();
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
