</main>

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
        <input type="text" name="reason" class="form-control" placeholder="<?php echo $is_rtl ? 'مثلاً: غلط اندراج، گاہک نے منسوخ کر دیا' : 'e.g. Created by mistake, Customer cancelled'; ?>" style="width:100%; font-size:13px;">
      </div>

      <div style="display:flex; justify-content:flex-end; gap:10px;">
        <button type="button" onclick="closeDeleteInvoiceModal()" class="btn btn-outline" style="padding:8px 16px;">
          <?php echo h(__('cancel')); ?>
        </button>
        <button type="submit" class="btn btn-danger" style="padding:8px 18px; font-weight:700;">
          🗑️ <?php echo h(__('delete')); ?>
        </button>
      </div>
    </form>
  </div>
</div>

<script>
function confirmDeleteInvoice(id, number, returnTo) {
  var modal = document.getElementById('deleteInvoiceModal');
  if (!modal) return;
  document.getElementById('modalDeleteInvoiceId').value = id;
  document.getElementById('modalDeleteInvoiceNumber').textContent = '#' + number;
  document.getElementById('modalDeleteReturnTo').value = returnTo || window.location.href;
  modal.style.display = 'flex';
}

function closeDeleteInvoiceModal() {
  var modal = document.getElementById('deleteInvoiceModal');
  if (modal) modal.style.display = 'none';
}

window.addEventListener('click', function(e) {
  var modal = document.getElementById('deleteInvoiceModal');
  if (modal && e.target === modal) {
    closeDeleteInvoiceModal();
  }
});
</script>

<footer class="app-footer">
  <div>
    <strong><?php echo h(__('app_name')); ?></strong> &copy; <?php echo date('Y'); ?> &mdash; 
    <?php echo h($current_business['name'] ?? __('app_subtitle')); ?>
    <span style="margin: 0 8px;">|</span>
    <span>A5 / A4 / 80mm Multi-Business Printing &amp; Accounting System</span>
  </div>
</footer>

<script src="<?php echo $base_url; ?>assets/js/app.js?v=<?php echo filemtime(__DIR__ . '/../assets/js/app.js'); ?>"></script>
</body>
</html>
