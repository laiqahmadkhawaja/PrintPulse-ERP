<?php
require_once __DIR__ . '/../config/init.php';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/accounting_helper.php';

$bid = (int)$current_business['id'];
$as_of_date = $_GET['as_of_date'] ?? date('Y-m-d');

$tb = AccountingEngine::getTrialBalance($bid, $as_of_date);
?>

<div class="page-header">
  <div>
    <h1 class="page-title">
      ⚖️ <?php echo h(__('nav_trial_balance')); ?> &mdash; <?php echo $is_rtl ? 'میزان پڑتال' : 'Trial Balance'; ?>
    </h1>
    <p style="color:var(--text-muted); font-size:0.9rem;">
      <?php echo $is_rtl ? 'ڈبل انٹری کے تحت کل نام (ڈیبٹ) اور کل جمع (کریڈٹ) کا تصدیق شدہ توازن' : 'Double-entry proof of ledger accounts balance as of specified date'; ?>
    </p>
  </div>
  <div>
    <form method="GET" action="trial_balance.php" style="display:flex; gap:10px;">
      <input type="date" name="as_of_date" class="form-control" value="<?php echo h($as_of_date); ?>">
      <button type="submit" class="btn btn-outline btn-sm">🔍 <?php echo h(__('filter')); ?></button>
    </form>
  </div>
</div>

<div class="card">
  <div class="card-header">
    <span><?php echo h($current_business['name']); ?> &bull; As of <?php echo format_date($as_of_date); ?></span>
    <?php if ($tb['is_balanced']): ?>
      <span class="badge badge-success" style="font-size:13px;">✓ Trial Balance Reconciled (Debits = Credits)</span>
    <?php else: ?>
      <span class="badge badge-danger">⚠️ Unbalanced Out of Equilibrium</span>
    <?php endif; ?>
  </div>

  <div class="table-responsive">
    <table class="table">
      <thead>
        <tr>
          <th style="width:100px;"><?php echo h(__('account_code')); ?></th>
          <th><?php echo h(__('account_name')); ?></th>
          <th><?php echo h(__('account_type')); ?></th>
          <th class="text-right" style="width:180px;"><?php echo h(__('debit')); ?> (Rs)</th>
          <th class="text-right" style="width:180px;"><?php echo h(__('credit')); ?> (Rs)</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($tb['accounts'] as $acc): ?>
          <tr>
            <td><code><?php echo h($acc['account_code']); ?></code></td>
            <td>
              <strong><?php echo h($acc['account_name']); ?></strong>
              <?php if ($acc['account_name_ur']): ?>
                <div style="font-size:12px; color:#64748b;"><?php echo h($acc['account_name_ur']); ?></div>
              <?php endif; ?>
            </td>
            <td><span class="badge badge-primary"><?php echo strtoupper($acc['account_type']); ?></span></td>
            <td class="text-right"><?php echo ($acc['closing_debit'] > 0) ? format_currency($acc['closing_debit'], false) : '-'; ?></td>
            <td class="text-right"><?php echo ($acc['closing_credit'] > 0) ? format_currency($acc['closing_credit'], false) : '-'; ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
      <tfoot>
        <tr style="background:#f8fafc; font-size:1.15rem; font-weight:800; border-top:2px solid #000;">
          <td colspan="3" class="text-right"><?php echo $is_rtl ? 'کل میزان (Grand Totals):' : 'Grand Totals:'; ?></td>
          <td class="text-right"><?php echo format_currency($tb['total_debit']); ?></td>
          <td class="text-right"><?php echo format_currency($tb['total_credit']); ?></td>
        </tr>
      </tfoot>
    </table>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
