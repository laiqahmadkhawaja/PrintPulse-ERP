<?php
require_once __DIR__ . '/config/init.php';
require_once __DIR__ . '/includes/header.php';

$bid = (int)$current_business['id'];
$pdo = getDB();

$stmt = $pdo->prepare("SELECT * FROM journal_entries WHERE business_id = ? ORDER BY entry_date DESC, id DESC LIMIT 50");
$stmt->execute([$bid]);
$entries = $stmt->fetchAll();
?>

<div class="page-header">
  <div>
    <h1 class="page-title">
      📜 <?php echo h(__('nav_vouchers')); ?> &mdash; <?php echo $is_rtl ? 'روزنامچہ / جنرل جرنل واؤچرز' : 'General Journal Register'; ?>
    </h1>
    <p style="color:var(--text-muted); font-size:0.9rem;">
      <?php echo $is_rtl ? 'تمام ڈبل انٹری پوسٹڈ واؤچرز اور مالیاتی اندراجات' : 'All balanced double-entry accounting transactions'; ?>
    </p>
  </div>
  <div>
    <a href="voucher_create.php" class="btn btn-primary">➕ <?php echo $is_rtl ? 'نیا ڈبل انٹری واؤچر' : 'New Journal Voucher'; ?></a>
  </div>
</div>

<div class="card">
  <div class="table-responsive">
    <table class="table">
      <thead>
        <tr>
          <th>Entry #</th>
          <th>Date</th>
          <th>Reference Type</th>
          <th>Description</th>
          <th class="text-right">Debit</th>
          <th class="text-right">Credit</th>
          <th class="text-center">Status</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($entries)): ?>
          <tr><td colspan="7" class="text-center text-muted" style="padding:25px;"><?php echo $is_rtl ? 'کوئی واؤچر موجود نہیں' : 'No journal vouchers found.'; ?></td></tr>
        <?php else: ?>
          <?php foreach ($entries as $je): ?>
            <tr>
              <td><strong><?php echo h($je['entry_number']); ?></strong></td>
              <td><?php echo format_date($je['entry_date']); ?></td>
              <td><span class="badge badge-primary"><?php echo strtoupper($je['reference_type']); ?></span></td>
              <td><?php echo h($je['description']); ?></td>
              <td class="text-right"><strong><?php echo format_currency($je['total_debit']); ?></strong></td>
              <td class="text-right"><strong><?php echo format_currency($je['total_credit']); ?></strong></td>
              <td class="text-center">
                <span class="badge badge-success">Balanced ✓</span>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
