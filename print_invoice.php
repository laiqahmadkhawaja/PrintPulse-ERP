<?php
/**
 * Universal Pixel-Perfect Invoice Printing Engine
 * Beautiful Executive Invoices (A5 Default & A4) & Dedicated Authentic POS Thermal Receipts (80mm)
 */

require_once __DIR__ . '/config/init.php';
require_once __DIR__ . '/includes/accounting_helper.php';

$invoice_id = (int)($_GET['id'] ?? 0);
$pdo = getDB();
$bid = (int)($current_business['id'] ?? ($_SESSION['current_business_id'] ?? 0));

if (empty($current_business)) {
    if ($bid === 0 && $invoice_id > 0) {
        // Determine business from invoice
        $invCheck = $pdo->prepare("SELECT business_id FROM invoices WHERE id = ?");
        $invCheck->execute([$invoice_id]);
        $bid = (int)$invCheck->fetchColumn();
    }
    if ($bid > 0) {
        $bStmt = $pdo->prepare("SELECT * FROM businesses WHERE id = ?");
        $bStmt->execute([$bid]);
        $current_business = $bStmt->fetch();
    }
}

if (!$current_business) {
    die("Unauthorized or business context missing.");
}

// Fetch invoice and strictly enforce business isolation
$stmt = $pdo->prepare("SELECT i.*, c.name AS cust_company, c.address AS cust_address 
                       FROM invoices i 
                       LEFT JOIN customers c ON c.id = i.customer_id 
                       WHERE i.id = ? AND i.business_id = ?");
$stmt->execute([$invoice_id, $bid]);
$inv = $stmt->fetch();

if (!$inv) {
    die("Invoice #{$invoice_id} not found or you are not authorized to view it.");
}

// Fetch invoice items
$itemStmt = $pdo->prepare("SELECT * FROM invoice_items WHERE invoice_id = ? ORDER BY line_number ASC, id ASC");
$itemStmt->execute([$invoice_id]);
$items = $itemStmt->fetchAll();

// Format selection (default from business settings or query param)
$format = $_GET['format'] ?? ($current_business['default_invoice_format'] ?: 'A5');
if (!in_array($format, ['A5', 'A4', '80mm'])) {
    $format = 'A5';
}

// Watermark configuration
$wm_enabled = (bool)($current_business['watermark_enabled'] ?? 1);
$wm_text = $current_business['watermark_text'] ?: $current_business['name'];
$wm_opacity = $current_business['watermark_opacity'] ?? 0.05;
$wm_rotation = $current_business['watermark_rotation'] ?? -25;

// Status calculation
$is_paid = ((float)$inv['balance_amount'] <= 0.001);
$is_partial = (!$is_paid && (float)$inv['paid_amount'] > 0);
?>
<!DOCTYPE html>
<html lang="<?php echo $current_lang; ?>" dir="<?php echo $is_rtl ? 'rtl' : 'ltr'; ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Invoice #<?php echo h($inv['invoice_number']); ?> - <?php echo h($current_business['name']); ?></title>
  <link rel="icon" type="image/svg+xml" href="<?php echo $base_url; ?>assets/images/favicon.svg">
  <link rel="stylesheet" href="<?php echo $base_url; ?>assets/css/invoice.css">
  <style>
    :root {
      --wm-opacity: <?php echo $wm_opacity; ?>;
      --wm-rotation: <?php echo $wm_rotation; ?>deg;
    }
  </style>
</head>
<body class="invoice-body format-mode-<?php echo $format; ?>">

<!-- Interactive On-Screen Toolbar (Hidden on Print) -->
<div class="print-actions-bar">
  <div class="bar-left">
    <a href="<?php echo $base_url; ?>dashboard.php" class="bar-back-link">
      &larr; <?php echo $is_rtl ? 'واپس ڈیش بورڈ' : 'Back to Dashboard'; ?>
    </a>
    <span class="bar-inv-meta">
      <strong>#<?php echo h($inv['invoice_number']); ?></strong> &bull; <?php echo h($inv['customer_name']); ?>
    </span>
  </div>

  <div class="bar-right">
    <span class="bar-format-label"><?php echo $is_rtl ? 'سائز:' : 'Format:'; ?></span>
    <a href="?id=<?php echo $inv['id']; ?>&format=A5" class="btn-format-pill <?php echo ($format==='A5') ? 'active' : ''; ?>">
      A5 (<?php echo $is_rtl ? 'معیاری' : 'Default'; ?>)
    </a>
    <a href="?id=<?php echo $inv['id']; ?>&format=A4" class="btn-format-pill <?php echo ($format==='A4') ? 'active' : ''; ?>">
      A4 (Full Page)
    </a>
    <a href="?id=<?php echo $inv['id']; ?>&format=80mm" class="btn-format-pill <?php echo ($format==='80mm') ? 'active' : ''; ?>">
      80mm POS Thermal
    </a>

    <button onclick="window.print();" class="btn-print-action">
      🖨️ <?php echo $is_rtl ? 'پرنٹ نکالیں' : 'Print Invoice'; ?>
    </button>

    <a href="invoice_edit.php?id=<?php echo $inv['id']; ?>" class="btn-format-pill" style="border-color:#0284c7; color:#0369a1; text-decoration:none; font-weight:600;">
      ✏️ <?php echo $is_rtl ? 'ترمیم' : 'Edit'; ?>
    </a>

    <button type="button" onclick="confirmDelete(<?php echo $inv['id']; ?>, '<?php echo h($inv['invoice_number']); ?>')" class="btn-format-pill" style="border-color:#fca5a5; color:#dc2626; background:#fff; cursor:pointer; font-weight:600;">
      🗑️ <?php echo $is_rtl ? 'حذف' : 'Delete'; ?>
    </button>
  </div>
</div>

<!-- Invoice Screen Canvas -->
<div class="invoice-preview-screen">

  <?php if ($format === '80mm'): ?>
    <!-- ============================================================== -->
    <!-- DEDICATED AUTHENTIC 80MM POS THERMAL RECEIPT LAYOUT           -->
    <!-- ============================================================== -->
    <div class="thermal-receipt-sheet">
      <!-- Header -->
      <div class="thermal-header">
        <?php if (!empty($current_business['logo_url']) && file_exists(__DIR__ . '/' . $current_business['logo_url'])): ?>
          <div style="text-align:center; margin-bottom:5px;">
            <img src="<?php echo $base_url . h($current_business['logo_url']); ?>?v=<?php echo filemtime(__DIR__ . '/' . $current_business['logo_url']); ?>" alt="<?php echo h($current_business['name']); ?>" style="max-height:42px; max-width:140px; object-fit:contain; filter:grayscale(100%);">
          </div>
        <?php endif; ?>
        <div class="thermal-biz-title"><?php echo h($current_business['name']); ?></div>
        <div class="thermal-biz-sub">PRINTER SALES, SERVICE &amp; TONER REFILLING</div>
        <div class="thermal-biz-contact">
          <?php echo h($current_business['address']); ?><br>
          Ph: <?php echo h($current_business['phone'] ?: $current_business['cell']); ?>
          <?php if (!empty($current_business['whatsapp'])): ?>
            | WA: <?php echo h($current_business['whatsapp']); ?>
          <?php endif; ?>
        </div>
      </div>

      <div class="thermal-divider-dashed"></div>

      <!-- Receipt Meta -->
      <div class="thermal-meta-grid">
        <div class="thermal-meta-row">
          <span>Receipt #:</span>
          <strong><?php echo h($inv['invoice_number']); ?></strong>
        </div>
        <div class="thermal-meta-row">
          <span>Date / Time:</span>
          <span><?php echo date('d-M-Y H:i', strtotime($inv['created_at'] ?: $inv['invoice_date'])); ?></span>
        </div>
        <div class="thermal-meta-row">
          <span>Customer:</span>
          <strong><?php echo h($inv['customer_name']); ?></strong>
        </div>
        <?php if (!empty($inv['customer_phone'])): ?>
          <div class="thermal-meta-row">
            <span>Contact:</span>
            <span><?php echo h($inv['customer_phone']); ?></span>
          </div>
        <?php endif; ?>
        <div class="thermal-meta-row">
          <span>Type:</span>
          <span style="text-transform:uppercase; font-weight:700;"><?php echo h($inv['invoice_type']); ?></span>
        </div>
      </div>

      <div class="thermal-divider-dashed"></div>

      <!-- Thermal Line Items (Authentic POS 2-line format) -->
      <div class="thermal-items-container">
        <div class="thermal-items-head">
          <span>ITEM DESCRIPTION</span>
          <span>AMOUNT</span>
        </div>
        <div class="thermal-divider-dotted"></div>

        <?php 
        $tIdx = 1;
        foreach ($items as $item): 
        ?>
          <div class="thermal-item-block">
            <div class="thermal-item-desc">
              <?php echo $tIdx++; ?>. <?php echo h($item['description']); ?>
            </div>
            <div class="thermal-item-calc-row">
              <span class="thermal-qty-rate">
                <?php echo (int)$item['quantity']; ?> pcs &times; Rs. <?php echo number_format($item['unit_price'], 0); ?>
              </span>
              <span class="thermal-item-amount">
                Rs. <?php echo number_format($item['total_amount'], 0); ?>
              </span>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

      <div class="thermal-divider-dashed"></div>

      <!-- Thermal Totals Block -->
      <div class="thermal-totals-grid">
        <div class="thermal-total-row">
          <span>Subtotal:</span>
          <span>Rs. <?php echo number_format($inv['subtotal'] ?: $inv['total_amount'], 0); ?></span>
        </div>
        <?php if ((float)$inv['discount'] > 0): ?>
          <div class="thermal-total-row">
            <span>Discount:</span>
            <span>- Rs. <?php echo number_format($inv['discount'], 0); ?></span>
          </div>
        <?php endif; ?>
        <?php if ((float)$inv['tax_amount'] > 0): ?>
          <div class="thermal-total-row">
            <span>Tax:</span>
            <span>+ Rs. <?php echo number_format($inv['tax_amount'], 0); ?></span>
          </div>
        <?php endif; ?>
        <div class="thermal-total-row thermal-grand-total">
          <span>NET TOTAL:</span>
          <span>Rs. <?php echo number_format($inv['total_amount'], 0); ?></span>
        </div>
        <div class="thermal-total-row">
          <span>Paid / Deposit:</span>
          <span>Rs. <?php echo number_format($inv['paid_amount'], 0); ?></span>
        </div>
        <div class="thermal-total-row">
          <span>Balance Due:</span>
          <strong>Rs. <?php echo number_format($inv['balance_amount'], 0); ?></strong>
        </div>
        <div class="thermal-total-row">
          <span>Payment Mode:</span>
          <span style="text-transform:uppercase;"><?php echo h($inv['payment_method'] ?: 'Cash'); ?></span>
        </div>
      </div>

      <div class="thermal-divider-dashed"></div>

      <!-- Thermal Policy & Barcode Footer -->
      <div class="thermal-footer">
        <?php if (!empty($inv['notes']) || !empty($current_business['invoice_notes'])): ?>
          <div class="thermal-terms">
            <?php echo nl2br(h($inv['notes'] ?: $current_business['invoice_notes'])); ?>
          </div>
        <?php endif; ?>

        <!-- Stylized CSS POS Barcode Representation -->
        <div class="thermal-barcode-wrap">
          <div class="thermal-barcode-lines">
            <span class="b-w2"></span><span class="b-w1"></span><span class="b-w3"></span><span class="b-w1"></span>
            <span class="b-w2"></span><span class="b-w4"></span><span class="b-w1"></span><span class="b-w2"></span>
            <span class="b-w1"></span><span class="b-w3"></span><span class="b-w2"></span><span class="b-w1"></span>
            <span class="b-w3"></span><span class="b-w1"></span><span class="b-w2"></span><span class="b-w4"></span>
          </div>
          <div class="thermal-barcode-num">* <?php echo h($inv['invoice_number']); ?> *</div>
        </div>

        <div class="thermal-thank-you">
          *** THANK YOU FOR YOUR BUSINESS ***
        </div>
        <div class="thermal-soft-meta">
          Powered by PrintPulse SaaS &bull; <?php echo date('d-m-Y H:i'); ?>
        </div>
      </div>
    </div>

  <?php else: ?>
    <!-- ============================================================== -->
    <!-- MODERN EXECUTIVE BEAUTIFUL INVOICE (A5 & A4 FORMATS)         -->
    <!-- ============================================================== -->
    <div class="invoice-sheet executive-format format-<?php echo $format; ?>">

      <!-- Header Section -->
      <div class="exec-header">
        <div class="exec-header-brand">
          <div class="exec-logo-group">
            <?php if (!empty($current_business['logo_url']) && file_exists(__DIR__ . '/' . $current_business['logo_url'])): ?>
              <div class="exec-custom-logo-wrap">
                <img src="<?php echo $base_url . h($current_business['logo_url']); ?>?v=<?php echo filemtime(__DIR__ . '/' . $current_business['logo_url']); ?>" alt="<?php echo h($current_business['name']); ?>" class="exec-brand-logo-img">
              </div>
            <?php else: ?>
              <div class="exec-logo-icon">
                <!-- Stylized Modern Printer Icon -->
                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <polyline points="6 9 6 2 18 2 18 9"></polyline>
                  <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path>
                  <rect x="6" y="14" width="12" height="8"></rect>
                </svg>
              </div>
            <?php endif; ?>
            <div>
              <h1 class="exec-business-name"><?php echo h($current_business['name']); ?></h1>
              <div class="exec-business-sub">PRINTER SALES, SERVICE &amp; TONER SOLUTIONS</div>
            </div>
          </div>

          <div class="exec-biz-details">
            <div class="exec-detail-line">
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
              <?php echo h($current_business['address']); ?>
            </div>
            <div class="exec-detail-line">
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path></svg>
              Ph: <?php echo h($current_business['phone'] ?: $current_business['cell']); ?>
              <?php if (!empty($current_business['email'])): ?>
                &bull; <?php echo h($current_business['email']); ?>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <!-- Right Side: Invoice Meta & Status -->
        <div class="exec-header-meta">
          <div class="exec-inv-badge-title">INVOICE</div>
          <div class="exec-inv-number">
            <span class="num-lbl">#</span><?php echo h($inv['invoice_number']); ?>
          </div>

          <div class="exec-status-pill <?php echo $is_paid ? 'status-paid' : ($is_partial ? 'status-partial' : 'status-unpaid'); ?>">
            <?php 
              if ($is_paid) {
                  echo $is_rtl ? '✓ مکمل ادا شدہ' : 'PAID';
              } elseif ($is_partial) {
                  echo $is_rtl ? 'جزوی ادائیگی' : 'PARTIALLY PAID';
              } else {
                  echo $is_rtl ? 'غیر ادا شدہ / واجب الادا' : 'PAYMENT DUE';
              }
            ?>
          </div>

          <div class="exec-date-row">
            <span class="date-lbl"><?php echo $is_rtl ? 'تاریخ:' : 'Date:'; ?></span>
            <span class="date-val"><?php echo date('d-M-Y', strtotime($inv['invoice_date'])); ?></span>
          </div>
        </div>
      </div>

      <!-- Customer & Bill Info Card -->
      <div class="exec-bill-card">
        <div class="bill-col bill-to">
          <div class="bill-section-title"><?php echo $is_rtl ? 'بل بنام / کسٹمر:' : 'BILLED TO:'; ?></div>
          <div class="customer-name"><?php echo h($inv['customer_name']); ?></div>
          <?php if (!empty($inv['customer_phone'])): ?>
            <div class="customer-sub-detail">
              <strong>Phone:</strong> <?php echo h($inv['customer_phone']); ?>
            </div>
          <?php endif; ?>
          <?php if (!empty($inv['cust_address'])): ?>
            <div class="customer-sub-detail">
              <strong>Address:</strong> <?php echo h($inv['cust_address']); ?>
            </div>
          <?php endif; ?>
        </div>

        <div class="bill-col bill-details">
          <div class="bill-section-title"><?php echo $is_rtl ? 'تفصیلات بل:' : 'TRANSACTION DETAILS:'; ?></div>
          <div class="meta-kv-grid">
            <div class="meta-k"><?php echo $is_rtl ? 'قسم:' : 'Category:'; ?></div>
            <div class="meta-v">
              <span class="tag-type"><?php echo ucfirst(h($inv['invoice_type'])); ?> Service</span>
            </div>

            <div class="meta-k"><?php echo $is_rtl ? 'طریقہ ادائیگی:' : 'Payment Mode:'; ?></div>
            <div class="meta-v" style="text-transform:uppercase; font-weight:600;">
              <?php echo h($inv['payment_method'] ?: 'Cash'); ?>
            </div>

            <?php if (!empty($inv['reference_job_id'])): ?>
              <div class="meta-k"><?php echo $is_rtl ? 'جاب کارڈ:' : 'Job Card Ref:'; ?></div>
              <div class="meta-v"><strong>#<?php echo h($inv['reference_job_id']); ?></strong></div>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- Clean Line Items Table with Centered Background Watermark -->
      <div class="exec-table-wrapper">
        <?php if ($wm_enabled): ?>
          <div class="exec-table-watermark">
            <?php echo h($wm_text); ?>
          </div>
        <?php endif; ?>
        <table class="exec-table">
          <thead>
            <tr>
              <th class="col-num">#</th>
              <th class="col-desc"><?php echo $is_rtl ? 'تفصیلات / ITEM &amp; DESCRIPTION' : 'ITEM &amp; DESCRIPTION'; ?></th>
              <th class="col-qty"><?php echo $is_rtl ? 'تعداد' : 'QTY'; ?></th>
              <th class="col-price"><?php echo $is_rtl ? 'قیمت (روپے)' : 'UNIT PRICE'; ?></th>
              <th class="col-total"><?php echo $is_rtl ? 'کل رقم' : 'TOTAL (PKR)'; ?></th>
            </tr>
          </thead>
          <tbody>
            <?php 
            $iCount = 1;
            foreach ($items as $item): 
            ?>
              <tr>
                <td class="col-num"><?php echo str_pad($iCount++, 2, '0', STR_PAD_LEFT); ?></td>
                <td class="col-desc">
                  <div class="item-title"><?php echo h($item['description']); ?></div>
                  <?php if (!empty($item['serial_number'])): ?>
                    <div class="item-serial">S/N: <?php echo h($item['serial_number']); ?></div>
                  <?php endif; ?>
                </td>
                <td class="col-qty"><?php echo number_format($item['quantity'], 0); ?></td>
                <td class="col-price"><?php echo number_format($item['unit_price'], 0); ?></td>
                <td class="col-total"><?php echo number_format($item['total_amount'], 0); ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <!-- Bottom Layout: Notes & Signatures on Left, Totals on Right -->
      <div class="exec-summary-section">
        <!-- Left: Notes & Signatures -->
        <div class="exec-notes-pane">
          <div class="exec-terms-box">
            <div class="terms-title">
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>
              <?php echo $is_rtl ? 'شرائط و ضوابط / نوٹس:' : 'TERMS &amp; IMPORTANT NOTES:'; ?>
            </div>
            <div class="terms-body">
              <?php echo nl2br(h($inv['notes'] ?: $current_business['invoice_notes'])); ?>
            </div>
          </div>

          <?php if (!empty($current_business['show_bank_on_invoice']) && (!empty($current_business['bank_name']) || !empty($current_business['account_number']))): ?>
            <div class="exec-bank-box" style="margin-top: 6px; padding: 6px 8px; background: #f8fafc; border: 1px dashed #cbd5e1; border-radius: 6px; font-size: 10px; color: #334155;">
              <div style="font-weight: 800; color: #0f172a; margin-bottom: 2px; display: flex; align-items: center; gap: 4px;">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 21h18M3 10h18M5 6l7-3 7 3M4 10v11M20 10v11M8 14v4M12 14v4M16 14v4"/></svg>
                <?php echo $is_rtl ? 'آن لائن ادائیگی / بینک تفصیلات:' : 'ONLINE PAYMENT / BANK DETAILS:'; ?>
              </div>
              <div style="display: flex; flex-wrap: wrap; gap: 3px 12px; font-size: 9.5px;">
                <?php if (!empty($current_business['bank_name'])): ?>
                  <span><strong>Bank:</strong> <?php echo h($current_business['bank_name']); ?></span>
                <?php endif; ?>
                <?php if (!empty($current_business['account_title'])): ?>
                  <span><strong>Title:</strong> <?php echo h($current_business['account_title']); ?></span>
                <?php endif; ?>
                <?php if (!empty($current_business['account_number'])): ?>
                  <span><strong>A/C #:</strong> <?php echo h($current_business['account_number']); ?></span>
                <?php endif; ?>
                <?php if (!empty($current_business['iban'])): ?>
                  <span><strong>IBAN:</strong> <?php echo h($current_business['iban']); ?></span>
                <?php endif; ?>
              </div>
            </div>
          <?php endif; ?>

          <!-- Signatures Block -->
          <div class="exec-signatures-block">
            <div class="sig-col">
              <div class="sig-line"></div>
              <div class="sig-lbl"><?php echo $is_rtl ? 'دستخط وصول کنندہ' : 'Customer / Receiver Signature'; ?></div>
            </div>
            <div class="sig-col">
              <div class="sig-line"></div>
              <div class="sig-lbl"><?php echo $is_rtl ? 'مجاز دستخط' : 'Authorized Signatory'; ?></div>
            </div>
          </div>
        </div>

        <!-- Right: Modern Financial Totals Card -->
        <div class="exec-totals-pane">
          <div class="exec-totals-card">
            <div class="t-row">
              <span class="t-lbl"><?php echo $is_rtl ? 'میزان / Subtotal:' : 'Subtotal:'; ?></span>
              <span class="t-val">Rs. <?php echo number_format($inv['subtotal'] ?: $inv['total_amount'], 0); ?></span>
            </div>

            <?php if ((float)$inv['discount'] > 0): ?>
              <div class="t-row discount">
                <span class="t-lbl"><?php echo $is_rtl ? 'رعایت / Discount:' : 'Discount:'; ?></span>
                <span class="t-val">- Rs. <?php echo number_format($inv['discount'], 0); ?></span>
              </div>
            <?php endif; ?>

            <?php if ((float)$inv['tax_amount'] > 0): ?>
              <div class="t-row tax">
                <span class="t-lbl"><?php echo $is_rtl ? 'ٹیکس / Tax:' : 'Tax:'; ?></span>
                <span class="t-val">+ Rs. <?php echo number_format($inv['tax_amount'], 0); ?></span>
              </div>
            <?php endif; ?>

            <div class="t-row grand-total">
              <span class="t-lbl"><?php echo $is_rtl ? 'کل قابل ادائیگی:' : 'GRAND TOTAL:'; ?></span>
              <span class="t-val">Rs. <?php echo number_format($inv['total_amount'], 0); ?></span>
            </div>

            <div class="t-row paid">
              <span class="t-lbl"><?php echo $is_rtl ? 'ادا شدہ / Deposit:' : 'Paid / Advance:'; ?></span>
              <span class="t-val">Rs. <?php echo number_format($inv['paid_amount'], 0); ?></span>
            </div>

            <div class="t-row balance <?php echo ($inv['balance_amount'] > 0) ? 'has-balance' : 'zero-balance'; ?>">
              <span class="t-lbl"><?php echo $is_rtl ? 'بقایا رقم / Balance:' : 'Balance Due:'; ?></span>
              <span class="t-val">
                <?php echo ($inv['balance_amount'] > 0) ? 'Rs. ' . number_format($inv['balance_amount'], 0) : 'NIL (PAID)'; ?>
              </span>
            </div>
          </div>
        </div>
      </div>

      <!-- Modern Footer Bar -->
      <div class="exec-footer-bar">
        <div class="footer-thankyou">
          <?php echo $is_rtl ? 'ہمارے ساتھ کاروبار کرنے کا شکریہ!' : 'Thank you for your business!'; ?>
        </div>
        <div class="footer-meta">
          <span><?php echo h($current_business['name']); ?></span> &bull; 
          <span><?php echo h($current_business['address']); ?></span> &bull; 
          <span>Ph: <?php echo h($current_business['phone'] ?: $current_business['cell']); ?></span>
        </div>
      </div>

    </div>
  <?php endif; ?>

</div>

<!-- Global Invoice Delete Confirmation Modal -->
<div id="deleteInvoiceModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15,23,42,0.6); z-index:999999; align-items:center; justify-content:center; padding:15px; backdrop-filter:blur(3px);">
  <div style="background:#ffffff; border-radius:12px; max-width:440px; width:100%; box-shadow:0 20px 25px -5px rgba(0,0,0,0.25); overflow:hidden; border:1px solid #e2e8f0;">
    <div style="padding:16px 20px; background:#fef2f2; border-bottom:1px solid #fee2e2; display:flex; align-items:center; gap:12px;">
      <span style="font-size:24px;">⚠️</span>
      <div>
        <h3 style="margin:0; font-size:16px; font-weight:700; color:#991b1b;"><?php echo $is_rtl ? 'بل حذف کرنے کی تصدیق' : 'Confirm Invoice Deletion'; ?></h3>
        <p style="margin:2px 0 0; font-size:12px; color:#b91c1c;"><?php echo $is_rtl ? 'لیجر اندراجات، کسٹمر بیلنس اور اسٹاک بحال ہو جائیں گے' : 'Ledgers, customer balance, and inventory will be safely reversed'; ?></p>
      </div>
    </div>
    <form method="POST" action="<?php echo $base_url; ?>invoice_delete.php" id="deleteInvoiceForm" style="padding:20px;">
      <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
      <input type="hidden" name="invoice_id" id="modalDeleteInvoiceId" value="">
      <input type="hidden" name="return_to" id="modalDeleteReturnTo" value="">
      
      <p style="margin:0 0 15px; font-size:14px; color:#334155; line-height:1.5;">
        <?php echo $is_rtl ? 'کیا آپ واقعی بل نمبر' : 'Are you sure you want to permanently delete invoice'; ?> 
        <strong id="modalDeleteInvoiceNumber" style="color:#0f172a; font-weight:700;"></strong>?
      </p>

      <div style="margin-bottom:18px;">
        <label style="display:block; font-size:12px; font-weight:600; color:#475569; margin-bottom:5px;">
          <?php echo $is_rtl ? 'حذف کرنے کی وجہ (اختیاری)' : 'Reason for Deletion (Optional)'; ?>:
        </label>
        <input type="text" name="reason" style="width:100%; padding:8px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:13px;" placeholder="<?php echo $is_rtl ? 'مثلاً: غلط اندراج، گاہک نے منسوخ کر دیا' : 'e.g. Created by mistake, Customer cancelled'; ?>">
      </div>

      <div style="display:flex; justify-content:flex-end; gap:10px;">
        <button type="button" onclick="closeDeleteModal()" style="padding:8px 16px; border:1px solid #cbd5e1; background:#fff; border-radius:6px; cursor:pointer; font-weight:600;">
          <?php echo h(__('cancel')); ?>
        </button>
        <button type="submit" style="padding:8px 18px; border:none; background:#dc2626; color:#fff; border-radius:6px; cursor:pointer; font-weight:700;">
          🗑️ <?php echo h(__('delete')); ?>
        </button>
      </div>
    </form>
  </div>
</div>

<script>
function confirmDelete(id, number) {
  var modal = document.getElementById('deleteInvoiceModal');
  if (!modal) return;
  document.getElementById('modalDeleteInvoiceId').value = id;
  document.getElementById('modalDeleteInvoiceNumber').textContent = '#' + number;
  document.getElementById('modalDeleteReturnTo').value = '<?php echo $base_url; ?>sales.php';
  modal.style.display = 'flex';
}

function closeDeleteModal() {
  var modal = document.getElementById('deleteInvoiceModal');
  if (modal) modal.style.display = 'none';
}

window.addEventListener('click', function(e) {
  var modal = document.getElementById('deleteInvoiceModal');
  if (modal && e.target === modal) {
    closeDeleteModal();
  }
});
</script>

</body>
</html>
