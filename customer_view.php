<?php
require_once __DIR__ . '/config/init.php';

$cust_id = (int)($_GET['id'] ?? 0);
$bid = (int)$current_business['id'];
$pdo = getDB();

$stmt = $pdo->prepare("SELECT * FROM customers WHERE id = ? AND business_id = ?");
$stmt->execute([$cust_id, $bid]);
$customer = $stmt->fetch();

if (!$customer) {
    die("Customer not found or unauthorized.");
}

// Fetch invoices
$stmtInv = $pdo->prepare("SELECT * FROM invoices WHERE customer_id = ? AND business_id = ? ORDER BY invoice_date ASC, id ASC");
$stmtInv->execute([$cust_id, $bid]);
$invoices = $stmtInv->fetchAll();

// Fetch payments
$stmtPay = $pdo->prepare("SELECT * FROM payments WHERE party_id = ? AND party_type = 'customer' AND business_id = ? ORDER BY payment_date ASC");
$stmtPay->execute([$cust_id, $bid]);
$payments = $stmtPay->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
  <div>
    <h1 class="page-title">
      📜 <?php echo h($customer['name']); ?> &mdash; <?php echo $is_rtl ? 'کسٹمر لیجر کھاتہ' : 'Customer Ledger Statement'; ?>
    </h1>
    <p style="color:var(--text-muted); font-size:0.9rem;">
      📞 <?php echo h($customer['phone'] ?: 'N/A'); ?> &bull; 📍 <?php echo h($customer['address'] ?: 'N/A'); ?>
    </p>
  </div>
  <div style="display:flex; gap:10px;">
    <a href="customers.php" class="btn btn-outline">&larr; <?php echo h(__('nav_customers')); ?></a>
    <a href="payments.php?customer_id=<?php echo $customer['id']; ?>" class="btn btn-success">
      💵 <?php echo $is_rtl ? 'رقم وصول کریں' : 'Receive Payment'; ?>
    </a>
  </div>
</div>

<div class="card" style="margin-bottom:1.5rem;">
  <div class="card-body" style="display:flex; justify-content:space-around; align-items:center;">
    <div style="text-align:center;">
      <div style="font-size:0.85rem; color:#64748b;"><?php echo $is_rtl ? 'ابتدائی بقایا' : 'Opening Balance'; ?></div>
      <div style="font-size:1.3rem; font-weight:700;"><?php echo format_currency($customer['opening_balance']); ?></div>
    </div>
    <div style="text-align:center;">
      <div style="font-size:0.85rem; color:#64748b;"><?php echo $is_rtl ? 'کل انوائسز کی تعداد' : 'Total Invoices'; ?></div>
      <div style="font-size:1.3rem; font-weight:700;"><?php echo count($invoices); ?></div>
    </div>
    <div style="text-align:center;">
      <div style="font-size:0.85rem; color:#64748b;"><?php echo $is_rtl ? 'موجودہ واجب الادا بقایا' : 'Current Outstanding'; ?></div>
      <div style="font-size:1.5rem; font-weight:800; color:var(--danger);"><?php echo format_currency($customer['current_balance']); ?></div>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-header">
    <span>📄 <?php echo $is_rtl ? 'انوائسز اور بلز کی تاریخ' : 'Invoices History'; ?></span>
  </div>
  <div class="table-responsive">
    <table class="table">
      <thead>
        <tr>
          <th><?php echo h(__('invoice_no')); ?></th>
          <th><?php echo h(__('date')); ?></th>
          <th><?php echo $is_rtl ? 'قسم' : 'Type'; ?></th>
          <th class="text-right"><?php echo h(__('total')); ?></th>
          <th class="text-right"><?php echo h(__('paid')); ?></th>
          <th class="text-right"><?php echo h(__('balance')); ?></th>
          <th class="text-center"><?php echo h(__('action')); ?></th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($invoices)): ?>
          <tr><td colspan="7" class="text-center text-muted" style="padding:20px;"><?php echo $is_rtl ? 'کوئی انوائس موجود نہیں ہے' : 'No invoices for this customer.'; ?></td></tr>
        <?php else: ?>
          <?php foreach ($invoices as $inv): ?>
            <tr>
              <td><strong><?php echo h($inv['invoice_number']); ?></strong></td>
              <td><?php echo format_date($inv['invoice_date']); ?></td>
              <td><span class="badge badge-primary"><?php echo strtoupper($inv['invoice_type']); ?></span></td>
              <td class="text-right"><strong><?php echo format_currency($inv['total_amount']); ?></strong></td>
              <td class="text-right"><?php echo format_currency($inv['paid_amount']); ?></td>
              <td class="text-right">
                <?php if ($inv['balance_amount'] > 0): ?>
                  <span class="badge badge-danger"><?php echo format_currency($inv['balance_amount']); ?></span>
                <?php else: ?>
                  <span class="badge badge-success"><?php echo $is_rtl ? 'ادا شدہ' : 'Paid'; ?></span>
                <?php endif; ?>
              </td>
              <td class="text-center">
                <div style="display:inline-flex; gap:6px; align-items:center; justify-content:center;">
                  <a href="print_invoice.php?id=<?php echo $inv['id']; ?>" target="_blank" class="btn btn-outline btn-sm" title="<?php echo h(__('print')); ?>">
                    🖨️ <?php echo h(__('print')); ?>
                  </a>
                  <a href="invoice_edit.php?id=<?php echo $inv['id']; ?>" class="btn btn-outline btn-sm" title="<?php echo h(__('edit')); ?>">
                    ✏️ <?php echo h(__('edit')); ?>
                  </a>
                  <button type="button" class="btn btn-danger btn-sm" onclick="confirmDeleteInvoice(<?php echo $inv['id']; ?>, '<?php echo h($inv['invoice_number']); ?>')" title="<?php echo h(__('delete')); ?>" style="padding:4px 8px;">
                    🗑️
                  </button>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
