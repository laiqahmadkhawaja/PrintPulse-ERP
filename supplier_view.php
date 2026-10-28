<?php
require_once __DIR__ . '/config/init.php';

$sup_id = (int)($_GET['id'] ?? 0);
$bid = (int)$current_business['id'];
$pdo = getDB();

$stmt = $pdo->prepare("SELECT * FROM suppliers WHERE id = ? AND business_id = ?");
$stmt->execute([$sup_id, $bid]);
$supplier = $stmt->fetch();

if (!$supplier) {
    die("Supplier not found.");
}

$stmtPur = $pdo->prepare("SELECT * FROM purchases WHERE supplier_id = ? AND business_id = ? ORDER BY purchase_date ASC");
$stmtPur->execute([$sup_id, $bid]);
$purchases = $stmtPur->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
  <div>
    <h1 class="page-title">
      🏢 <?php echo h($supplier['name']); ?> &mdash; <?php echo $is_rtl ? 'سپلائر لیجر کھاتہ' : 'Supplier Ledger'; ?>
    </h1>
    <p style="color:var(--text-muted); font-size:0.9rem;">
      👤 <?php echo h($supplier['contact_person'] ?: 'N/A'); ?> &bull; 📞 <?php echo h($supplier['phone'] ?: 'N/A'); ?>
    </p>
  </div>
  <div style="display:flex; gap:10px;">
    <a href="suppliers.php" class="btn btn-outline">&larr; <?php echo h(__('nav_suppliers')); ?></a>
    <a href="payments.php?supplier_id=<?php echo $supplier['id']; ?>" class="btn btn-primary">
      💳 <?php echo $is_rtl ? 'سپلائر کو ادائیگی کریں' : 'Make Payment'; ?>
    </a>
  </div>
</div>

<div class="card" style="margin-bottom:1.5rem;">
  <div class="card-body" style="display:flex; justify-content:space-around; align-items:center;">
    <div style="text-align:center;">
      <div style="font-size:0.85rem; color:#64748b;"><?php echo $is_rtl ? 'ابتدائی بقایا' : 'Opening Balance'; ?></div>
      <div style="font-size:1.3rem; font-weight:700;"><?php echo format_currency($supplier['opening_balance']); ?></div>
    </div>
    <div style="text-align:center;">
      <div style="font-size:0.85rem; color:#64748b;"><?php echo $is_rtl ? 'کل بلز کی تعداد' : 'Total Bills'; ?></div>
      <div style="font-size:1.3rem; font-weight:700;"><?php echo count($purchases); ?></div>
    </div>
    <div style="text-align:center;">
      <div style="font-size:0.85rem; color:#64748b;"><?php echo $is_rtl ? 'کل واجب الادا رقم' : 'Current Balance Payable'; ?></div>
      <div style="font-size:1.5rem; font-weight:800; color:var(--danger);"><?php echo format_currency($supplier['current_balance']); ?></div>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-header">
    <span>📦 <?php echo $is_rtl ? 'خریداری بلز کی تاریخ' : 'Purchases History'; ?></span>
  </div>
  <div class="table-responsive">
    <table class="table">
      <thead>
        <tr>
          <th>Purchase #</th>
          <th>Date</th>
          <th>Supplier Bill #</th>
          <th class="text-right">Total Amount</th>
          <th class="text-right">Paid</th>
          <th class="text-right">Balance</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($purchases)): ?>
          <tr><td colspan="6" class="text-center text-muted" style="padding:20px;"><?php echo $is_rtl ? 'کوئی خریداری ریکارڈ نہیں ہے' : 'No purchases found.'; ?></td></tr>
        <?php else: ?>
          <?php foreach ($purchases as $p): ?>
            <tr>
              <td><strong><?php echo h($p['purchase_number']); ?></strong></td>
              <td><?php echo format_date($p['purchase_date']); ?></td>
              <td><?php echo h($p['supplier_bill_no'] ?: '-'); ?></td>
              <td class="text-right"><strong><?php echo format_currency($p['total_amount']); ?></strong></td>
              <td class="text-right"><?php echo format_currency($p['paid_amount']); ?></td>
              <td class="text-right">
                <?php if ($p['balance_amount'] > 0): ?>
                  <span class="badge badge-danger"><?php echo format_currency($p['balance_amount']); ?></span>
                <?php else: ?>
                  <span class="badge badge-success"><?php echo $is_rtl ? 'ادا شدہ' : 'Paid'; ?></span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
