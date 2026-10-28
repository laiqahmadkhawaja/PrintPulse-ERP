<?php
require_once __DIR__ . '/../config/init.php';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/accounting_helper.php';

$bid = (int)$current_business['id'];
$pdo = getDB();

$accounts = $pdo->query("SELECT id, account_code, account_name FROM chart_of_accounts WHERE business_id = {$bid} ORDER BY account_code ASC")->fetchAll();

$acc_id = (int)($_GET['account_id'] ?? ($accounts[0]['id'] ?? 0));
$start_date = $_GET['start_date'] ?? date('Y-01-01');
$end_date = $_GET['end_date'] ?? date('Y-m-d');

$ledger = AccountingEngine::getAccountLedger($bid, $acc_id, $start_date, $end_date);
?>

<div class="page-header">
  <div>
    <h1 class="page-title">
      📜 <?php echo h(__('nav_general_ledger')); ?> &mdash; [<?php echo h($ledger['account']['account_code']); ?>] <?php echo h($ledger['account']['account_name']); ?>
    </h1>
  </div>
  <div>
    <form method="GET" action="general_ledger.php" style="display:flex; gap:10px; flex-wrap:wrap;">
      <select name="account_id" class="form-control" style="max-width:240px;" onchange="this.form.submit()">
        <?php foreach ($accounts as $a): ?>
          <option value="<?php echo $a['id']; ?>" <?php echo ($a['id'] == $acc_id) ? 'selected' : ''; ?>>
            [<?php echo $a['account_code']; ?>] <?php echo h($a['account_name']); ?>
          </option>
        <?php endforeach; ?>
      </select>
      <input type="date" name="start_date" class="form-control" value="<?php echo h($start_date); ?>">
      <input type="date" name="end_date" class="form-control" value="<?php echo h($end_date); ?>">
      <button type="submit" class="btn btn-outline btn-sm">🔍 <?php echo h(__('filter')); ?></button>
    </form>
  </div>
</div>

<div class="card">
  <div class="card-header">
    <span>Account Ledger Transactions</span>
    <span style="font-weight:800; font-size:1.15rem; color:var(--primary);">
      Balance: <?php echo format_currency($ledger['closing_balance']); ?>
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
          <th class="text-right">Debit</th>
          <th class="text-right">Credit</th>
          <th class="text-right">Running Balance</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($ledger['entries'])): ?>
          <tr><td colspan="7" class="text-center text-muted" style="padding:25px;"><?php echo $is_rtl ? 'کوئی ٹرانزیکشن موجود نہیں ہے' : 'No transactions found.'; ?></td></tr>
        <?php else: ?>
          <?php foreach ($ledger['entries'] as $e): ?>
            <tr>
              <td><?php echo format_date($e['entry_date']); ?></td>
              <td><strong><?php echo h($e['entry_number']); ?></strong></td>
              <td><?php echo h($e['description']); ?></td>
              <td><?php echo h($e['narration']); ?></td>
              <td class="text-right"><?php echo ($e['debit'] > 0) ? format_currency($e['debit'], false) : '-'; ?></td>
              <td class="text-right"><?php echo ($e['credit'] > 0) ? format_currency($e['credit'], false) : '-'; ?></td>
              <td class="text-right"><strong><?php echo format_currency($e['running_balance']); ?></strong></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
