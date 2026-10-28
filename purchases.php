<?php
require_once __DIR__ . '/config/init.php';
require_once __DIR__ . '/includes/header.php';

$bid = (int)$current_business['id'];
$pdo = getDB();

$stmt = $pdo->prepare("SELECT p.*, s.name as supplier_name, s.phone as supplier_phone 
                       FROM purchases p 
                       JOIN suppliers s ON s.id = p.supplier_id 
                       WHERE p.business_id = ? 
                       ORDER BY p.id DESC");
$stmt->execute([$bid]);
$purchases = $stmt->fetchAll();
?>

<div class="page-header">
  <div>
    <h1 class="page-title">
      📦 <?php echo h(__('nav_purchases')); ?>
    </h1>
    <p style="color:var(--text-muted); font-size:0.9rem;">
      <?php echo $is_rtl ? 'سپلائرز سے مال کی خریداری، بلز اور اسٹاک میں اضافہ' : 'Supplier bills, inventory intake, and accounts payable'; ?>
    </p>
  </div>
  <div>
    <a href="purchase_create.php" class="btn btn-primary">➕ <?php echo $is_rtl ? 'نیا خریداری بل' : 'New Purchase Bill'; ?></a>
  </div>
</div>

<div class="card">
  <div class="table-responsive">
    <table class="table">
      <thead>
        <tr>
          <th><?php echo $is_rtl ? 'بل نمبر' : 'Purchase #'; ?></th>
          <th><?php echo h(__('date')); ?></th>
          <th><?php echo h(__('nav_suppliers')); ?></th>
          <th><?php echo $is_rtl ? 'سپلائر بل نمبر' : 'Supplier Bill #'; ?></th>
          <th class="text-right"><?php echo h(__('total')); ?></th>
          <th class="text-right"><?php echo h(__('paid')); ?></th>
          <th class="text-right"><?php echo h(__('balance')); ?></th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($purchases)): ?>
          <tr><td colspan="7" class="text-center text-muted" style="padding:25px;"><?php echo $is_rtl ? 'کوئی خریداری ریکارڈ موجود نہیں' : 'No purchase bills found.'; ?></td></tr>
        <?php else: ?>
          <?php foreach ($purchases as $p): ?>
            <tr>
              <td><strong><?php echo h($p['purchase_number']); ?></strong></td>
              <td><?php echo format_date($p['purchase_date']); ?></td>
              <td><strong><?php echo h($p['supplier_name']); ?></strong></td>
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
