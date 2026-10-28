<?php
require_once __DIR__ . '/../config/init.php';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/accounting_helper.php';

$bid = (int)$current_business['id'];
$as_of_date = $_GET['as_of_date'] ?? date('Y-m-d');

$bs = AccountingEngine::getBalanceSheet($bid, $as_of_date);
?>

<div class="page-header">
  <div>
    <h1 class="page-title">
      🏛️ <?php echo h(__('nav_balance_sheet')); ?> &mdash; <?php echo $is_rtl ? 'بیلنس شیٹ' : 'Balance Sheet'; ?>
    </h1>
    <p style="color:var(--text-muted); font-size:0.9rem;">
      <?php echo h($current_business['name']); ?> &bull; As of <?php echo format_date($as_of_date); ?>
    </p>
  </div>
  <div>
    <form method="GET" action="balance_sheet.php" style="display:flex; gap:10px;">
      <input type="date" name="as_of_date" class="form-control" value="<?php echo h($as_of_date); ?>">
      <button type="submit" class="btn btn-outline btn-sm">🔍 <?php echo h(__('filter')); ?></button>
    </form>
  </div>
</div>

<div class="grid grid-cols-2">
  <!-- Assets Side -->
  <div class="card">
    <div class="card-header" style="background:#f0fdf4;">
      <span style="font-weight:700; color:#166534;">1. <?php echo h(__('total_assets')); ?> (اثاثہ جات)</span>
    </div>
    <div class="table-responsive">
      <table class="table">
        <tbody>
          <?php foreach ($bs['assets'] as $asset): ?>
            <tr>
              <td><code>[<?php echo $asset['account_code']; ?>]</code> <?php echo h($asset['account_name']); ?></td>
              <td class="text-right" style="font-weight:600;"><?php echo format_currency($asset['balance']); ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr style="background:#dcfce7; font-size:1.1rem; font-weight:800; color:#15803d;">
            <td>Total Assets (کل اثاثہ جات):</td>
            <td class="text-right"><?php echo format_currency($bs['total_assets']); ?></td>
          </tr>
        </tfoot>
      </table>
    </div>
  </div>

  <!-- Liabilities & Equity Side -->
  <div>
    <div class="card" style="margin-bottom:1.5rem;">
      <div class="card-header" style="background:#fef2f2;">
        <span style="font-weight:700; color:#991b1b;">2. <?php echo h(__('total_liabilities')); ?> (واجبات)</span>
      </div>
      <div class="table-responsive">
        <table class="table">
          <tbody>
            <?php foreach ($bs['liabilities'] as $liab): ?>
              <tr>
                <td><code>[<?php echo $liab['account_code']; ?>]</code> <?php echo h($liab['account_name']); ?></td>
                <td class="text-right" style="font-weight:600;"><?php echo format_currency($liab['balance']); ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
          <tfoot>
            <tr style="background:#fee2e2; font-weight:800; color:#b91c1c;">
              <td>Total Liabilities:</td>
              <td class="text-right"><?php echo format_currency($bs['total_liabilities']); ?></td>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>

    <div class="card">
      <div class="card-header" style="background:#eff6ff;">
        <span style="font-weight:700; color:#1e40af;">3. <?php echo h(__('total_equity')); ?> &amp; Reserves (سرمایہ)</span>
      </div>
      <div class="table-responsive">
        <table class="table">
          <tbody>
            <?php foreach ($bs['equity'] as $eq): ?>
              <tr>
                <td><code>[<?php echo $eq['account_code']; ?>]</code> <?php echo h($eq['account_name']); ?></td>
                <td class="text-right" style="font-weight:600;"><?php echo format_currency($eq['balance']); ?></td>
              </tr>
            <?php endforeach; ?>
            <tr>
              <td><em>Current Period Net Profit (موجودہ منافع)</em></td>
              <td class="text-right" style="font-weight:600; color:var(--success);"><?php echo format_currency($bs['current_period_profit']); ?></td>
            </tr>
          </tbody>
          <tfoot>
            <tr style="background:#dbeafe; font-weight:800; color:#1e40af;">
              <td>Total Equity &amp; Retained Earnings:</td>
              <td class="text-right"><?php echo format_currency($bs['total_equity']); ?></td>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>

    <!-- Equation Reconciled Bar -->
    <div style="background:#f8fafc; border:2px solid #0f172a; padding:12px 16px; border-radius:8px; display:flex; justify-content:space-between; align-items:center; font-weight:800; margin-top:15px;">
      <span>Total Liabilities + Equity:</span>
      <span style="font-size:1.15rem;"><?php echo format_currency($bs['total_liabilities'] + $bs['total_equity']); ?></span>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
