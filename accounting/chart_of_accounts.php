<?php
require_once __DIR__ . '/../config/init.php';
require_once __DIR__ . '/../includes/header.php';

$bid = (int)$current_business['id'];
$pdo = getDB();

$stmt = $pdo->prepare("SELECT * FROM chart_of_accounts WHERE business_id = ? ORDER BY account_code ASC");
$stmt->execute([$bid]);
$accounts = $stmt->fetchAll();

// Group by account type
$grouped = [
    'asset' => ['title' => 'Assets (اثاثہ جات)', 'items' => []],
    'liability' => ['title' => 'Liabilities (واجبات)', 'items' => []],
    'equity' => ['title' => 'Equity (سرمایہ)', 'items' => []],
    'income' => ['title' => 'Revenue / Income (آمدن)', 'items' => []],
    'cogs' => ['title' => 'Cost of Goods Sold (لاگت)', 'items' => []],
    'expense' => ['title' => 'Operating Expenses (اخراجات)', 'items' => []],
];

foreach ($accounts as $acc) {
    if (isset($grouped[$acc['account_type']])) {
        $grouped[$acc['account_type']]['items'][] = $acc;
    }
}
?>

<div class="page-header">
  <div>
    <h1 class="page-title">
      📖 <?php echo h(__('nav_chart_of_accounts')); ?>
    </h1>
    <p style="color:var(--text-muted); font-size:0.9rem;">
      <?php echo $is_rtl ? 'ڈبل انٹری کھاتہ داری کا مکمل چارٹ آف اکاؤنٹس' : 'Hierarchical Chart of Accounts with real-time balances'; ?>
    </p>
  </div>
</div>

<div class="grid grid-cols-2">
  <?php foreach ($grouped as $type => $group): ?>
    <div class="card">
      <div class="card-header">
        <span><?php echo h($group['title']); ?></span>
        <span class="badge badge-primary"><?php echo count($group['items']); ?> Accounts</span>
      </div>
      <div class="table-responsive">
        <table class="table">
          <thead>
            <tr>
              <th style="width:70px;"><?php echo h(__('account_code')); ?></th>
              <th><?php echo h(__('account_name')); ?></th>
              <th class="text-right"><?php echo $is_rtl ? 'موجودہ بیلنس' : 'Current Balance'; ?></th>
              <th class="text-center"><?php echo h(__('action')); ?></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($group['items'] as $item): ?>
              <tr>
                <td><code><?php echo h($item['account_code']); ?></code></td>
                <td>
                  <strong><?php echo h($item['account_name']); ?></strong>
                  <?php if ($item['account_name_ur']): ?>
                    <div style="font-size:12px; color:#64748b;"><?php echo h($item['account_name_ur']); ?></div>
                  <?php endif; ?>
                </td>
                <td class="text-right">
                  <strong><?php echo format_currency($item['current_balance']); ?></strong>
                </td>
                <td class="text-center">
                  <a href="general_ledger.php?account_id=<?php echo $item['id']; ?>" class="btn btn-outline btn-sm">
                    📜
                  </a>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
