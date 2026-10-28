<?php
require_once __DIR__ . '/../config/init.php';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/accounting_helper.php';

$bid = (int)$current_business['id'];
$start_date = $_GET['start_date'] ?? date('Y-01-01');
$end_date = $_GET['end_date'] ?? date('Y-m-d');

$pnl = AccountingEngine::getProfitAndLoss($bid, $start_date, $end_date);
?>

<div class="page-header">
  <div>
    <h1 class="page-title">
      📈 <?php echo h(__('nav_profit_loss')); ?> &mdash; <?php echo $is_rtl ? 'نفع و نقصان کی رپورٹ' : 'Income Statement'; ?>
    </h1>
    <p style="color:var(--text-muted); font-size:0.9rem;">
      <?php echo h($current_business['name']); ?> &bull; <?php echo format_date($start_date); ?> to <?php echo format_date($end_date); ?>
    </p>
  </div>
  <div>
    <form method="GET" action="profit_loss.php" style="display:flex; gap:10px;">
      <input type="date" name="start_date" class="form-control" value="<?php echo h($start_date); ?>">
      <input type="date" name="end_date" class="form-control" value="<?php echo h($end_date); ?>">
      <button type="submit" class="btn btn-outline btn-sm">🔍 <?php echo h(__('filter')); ?></button>
    </form>
  </div>
</div>

<div class="card" style="max-width:900px; margin:0 auto;">
  <div class="card-header">
    <span>📊 Statement of Profit &amp; Loss</span>
    <span class="badge badge-success">Accrual Basis</span>
  </div>
  <div class="card-body">
    <!-- 1. Revenue Section -->
    <h3 style="font-size:1.1rem; color:var(--primary); border-bottom:1px solid #e2e8f0; padding-bottom:6px; margin-bottom:10px;">
      1. Revenue &amp; Service Income (آمدنی)
    </h3>
    <table class="table" style="margin-bottom:20px;">
      <tbody>
        <?php foreach ($pnl['incomes'] as $inc): ?>
          <tr>
            <td><code>[<?php echo $inc['account_code']; ?>]</code> <?php echo h($inc['account_name']); ?></td>
            <td class="text-right" style="width:180px; font-weight:600;"><?php echo format_currency($inc['amount']); ?></td>
          </tr>
        <?php endforeach; ?>
        <tr style="background:#f8fafc; font-weight:800;">
          <td>Total Operating Revenue (کل آمدنی):</td>
          <td class="text-right"><?php echo format_currency($pnl['total_income']); ?></td>
        </tr>
      </tbody>
    </table>

    <!-- 2. COGS Section -->
    <h3 style="font-size:1.1rem; color:var(--danger); border-bottom:1px solid #e2e8f0; padding-bottom:6px; margin-bottom:10px;">
      2. Cost of Goods Sold &amp; Materials (لاگت فروخت)
    </h3>
    <table class="table" style="margin-bottom:20px;">
      <tbody>
        <?php foreach ($pnl['cogs'] as $cg): ?>
          <tr>
            <td><code>[<?php echo $cg['account_code']; ?>]</code> <?php echo h($cg['account_name']); ?></td>
            <td class="text-right" style="width:180px; font-weight:600;"><?php echo format_currency($cg['amount']); ?></td>
          </tr>
        <?php endforeach; ?>
        <tr style="background:#f8fafc; font-weight:800;">
          <td>Total Cost of Goods Sold:</td>
          <td class="text-right"><?php echo format_currency($pnl['total_cogs']); ?></td>
        </tr>
      </tbody>
    </table>

    <!-- Gross Profit Summary -->
    <div style="background:#e0f2fe; padding:12px 18px; border-radius:8px; display:flex; justify-content:space-between; align-items:center; margin-bottom:25px; font-size:1.15rem; font-weight:800; color:var(--primary-dark);">
      <span><?php echo h(__('gross_profit')); ?> (مجموعی منافع):</span>
      <span><?php echo format_currency($pnl['gross_profit']); ?></span>
    </div>

    <!-- 3. Operating Expenses Section -->
    <h3 style="font-size:1.1rem; color:var(--warning); border-bottom:1px solid #e2e8f0; padding-bottom:6px; margin-bottom:10px;">
      3. Operating Expenses (دکان کے اخراجات)
    </h3>
    <table class="table" style="margin-bottom:20px;">
      <tbody>
        <?php foreach ($pnl['expenses'] as $exp): ?>
          <tr>
            <td><code>[<?php echo $exp['account_code']; ?>]</code> <?php echo h($exp['account_name']); ?></td>
            <td class="text-right" style="width:180px; font-weight:600;"><?php echo format_currency($exp['amount']); ?></td>
          </tr>
        <?php endforeach; ?>
        <tr style="background:#f8fafc; font-weight:800;">
          <td>Total Operating Expenses (کل اخراجات):</td>
          <td class="text-right"><?php echo format_currency($pnl['total_expenses']); ?></td>
        </tr>
      </tbody>
    </table>

    <!-- Net Profit Result -->
    <div style="background:<?php echo ($pnl['net_profit'] >= 0) ? '#dcfce7' : '#fee2e2'; ?>; padding:16px 20px; border-radius:8px; display:flex; justify-content:space-between; align-items:center; font-size:1.35rem; font-weight:900; color:<?php echo ($pnl['net_profit'] >= 0) ? '#15803d' : '#b91c1c'; ?>;">
      <span><?php echo h(__('net_profit')); ?> (خالص منافع):</span>
      <span><?php echo format_currency($pnl['net_profit']); ?></span>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
