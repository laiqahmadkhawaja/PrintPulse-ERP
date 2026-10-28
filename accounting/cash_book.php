<?php
require_once __DIR__ . '/../config/init.php';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/accounting_helper.php';

$bid = (int)$current_business['id'];
$cashAccId = AccountingEngine::getAccountIdByCode($bid, '1010');

$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-d');

$ledger = AccountingEngine::getAccountLedger($bid, $cashAccId, $start_date, $end_date);
?>

<div class="page-header">
  <div>
    <h1 class="page-title">
      💵 <?php echo h(__('nav_cash_book')); ?> &mdash; <?php echo $is_rtl ? 'روکڑ بہی (کیش بک)' : 'Cash in Hand Book'; ?>
    </h1>
    <p style="color:var(--text-muted); font-size:0.9rem;">
      <?php echo h($current_business['name']); ?> &bull; GL Account Code [1010] &bull; <?php echo format_date($start_date); ?> to <?php echo format_date($end_date); ?>
    </p>
  </div>
  <div>
    <form method="GET" action="cash_book.php" style="display:flex; gap:10px;">
      <input type="date" name="start_date" class="form-control" value="<?php echo h($start_date); ?>">
      <input type="date" name="end_date" class="form-control" value="<?php echo h($end_date); ?>">
      <button type="submit" class="btn btn-outline btn-sm">🔍 <?php echo h(__('filter')); ?></button>
    </form>
  </div>
</div>

<div class="card">
  <div class="card-header">
    <span>Transactions &amp; Running Cash Balance</span>
    <span style="font-weight:800; font-size:1.15rem; color:var(--primary);">
      <?php echo $is_rtl ? 'موجودہ نقد رقم:' : 'Closing Cash:'; ?> <?php echo format_currency($ledger['closing_balance']); ?>
    </span>
  </div>
  <div class="table-responsive">
    <table class="table">
      <thead>
        <tr>
          <th>Date</th>
          <th>Entry #</th>
          <th>Description</th>
          <th>Narration</th>
          <th class="text-right">Cash In (Debit)</th>
          <th class="text-right">Cash Out (Credit)</th>
          <th class="text-right">Running Balance</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($ledger['entries'])): ?>
          <tr><td colspan="7" class="text-center text-muted" style="padding:25px;"><?php echo $is_rtl ? 'کوئی نقد لین دین موجود نہیں' : 'No cash transactions found.'; ?></td></tr>
        <?php else: ?>
          <?php foreach ($ledger['entries'] as $e): ?>
            <tr>
              <td><?php echo format_date($e['entry_date']); ?></td>
              <td><strong><?php echo h($e['entry_number']); ?></strong></td>
              <td><?php echo h($e['description']); ?></td>
              <td><?php echo h($e['narration']); ?></td>
              <td class="text-right" style="color:var(--success); font-weight:700;">
                <?php echo ($e['debit'] > 0) ? format_currency($e['debit'], false) : '-'; ?>
              </td>
              <td class="text-right" style="color:var(--danger); font-weight:700;">
                <?php echo ($e['credit'] > 0) ? format_currency($e['credit'], false) : '-'; ?>
              </td>
              <td class="text-right"><strong><?php echo format_currency($e['running_balance']); ?></strong></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
