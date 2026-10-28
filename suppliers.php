<?php
require_once __DIR__ . '/config/init.php';
require_once __DIR__ . '/includes/header.php';

$bid = (int)$current_business['id'];
$pdo = getDB();
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_supplier') {
    if (verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $name = trim($_POST['name'] ?? '');
        $contact = trim($_POST['contact_person'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $opening = (float)($_POST['opening_balance'] ?? 0);

        if (!empty($name)) {
            $stmt = $pdo->prepare("INSERT INTO suppliers (business_id, name, contact_person, phone, email, address, opening_balance, current_balance) 
                                   VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$bid, $name, $contact, $phone, $email, $address, $opening, $opening]);
            $msg = $is_rtl ? 'سپلائر کامیابی سے شامل ہو گیا۔' : 'Supplier added successfully.';
        }
    }
}

$suppliers = $pdo->query("SELECT * FROM suppliers WHERE business_id = {$bid} ORDER BY name ASC")->fetchAll();
?>

<div class="page-header">
  <div>
    <h1 class="page-title">
      🏢 <?php echo h(__('nav_suppliers')); ?>
    </h1>
    <p style="color:var(--text-muted); font-size:0.9rem;">
      <?php echo $is_rtl ? 'سپلائرز کی ڈائرکٹری اور واجب الادا رقوم' : 'Suppliers directory, purchase ledgers, and payables'; ?>
    </p>
  </div>
</div>

<?php if ($msg): ?>
  <div class="alert alert-success"><?php echo h($msg); ?></div>
<?php endif; ?>

<div class="grid grid-cols-3">
  <!-- Add Supplier Form -->
  <div class="card">
    <div class="card-header">
      <span>➕ <?php echo $is_rtl ? 'نیا سپلائر شامل کریں' : 'Add New Supplier'; ?></span>
    </div>
    <div class="card-body">
      <form method="POST" action="suppliers.php">
        <input type="hidden" name="action" value="add_supplier">
        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">

        <div class="form-group">
          <label class="form-label"><?php echo $is_rtl ? 'کمپنی / سپلائر کا نام' : 'Supplier / Firm Name'; ?> *</label>
          <input type="text" name="name" class="form-control" required placeholder="e.g. National Toner Importers">
        </div>
        <div class="form-group">
          <label class="form-label"><?php echo $is_rtl ? 'رابطہ کار شخص' : 'Contact Person'; ?></label>
          <input type="text" name="contact_person" class="form-control" placeholder="Sheikh Tariq">
        </div>
        <div class="form-group">
          <label class="form-label"><?php echo h(__('phone')); ?> / <?php echo h(__('cell')); ?></label>
          <input type="text" name="phone" class="form-control" placeholder="0300-1234567">
        </div>
        <div class="form-group">
          <label class="form-label"><?php echo h(__('address')); ?></label>
          <input type="text" name="address" class="form-control" placeholder="Market, City">
        </div>
        <div class="form-group">
          <label class="form-label"><?php echo $is_rtl ? 'ابتدائی واجب الادا بقایا' : 'Opening Balance'; ?> (Rs)</label>
          <input type="number" step="0.01" name="opening_balance" class="form-control" value="0.00">
        </div>

        <button type="submit" class="btn btn-primary" style="width:100%; margin-top:10px;">
          💾 <?php echo h(__('save')); ?>
        </button>
      </form>
    </div>
  </div>

  <!-- Suppliers List -->
  <div class="card" style="grid-column: span 2;">
    <div class="card-header">
      <span>📋 <?php echo $is_rtl ? 'سپلائرز کی فہرست' : 'Suppliers List'; ?></span>
    </div>
    <div class="table-responsive">
      <table class="table">
        <thead>
          <tr>
            <th><?php echo h(__('nav_suppliers')); ?></th>
            <th><?php echo $is_rtl ? 'رابطہ کار' : 'Contact Person'; ?></th>
            <th><?php echo h(__('phone')); ?></th>
            <th class="text-right"><?php echo $is_rtl ? 'واجب الادا رقم' : 'Balance Payable'; ?></th>
            <th class="text-center"><?php echo h(__('actions')); ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($suppliers as $s): ?>
            <tr>
              <td><strong><?php echo h($s['name']); ?></strong></td>
              <td><?php echo h($s['contact_person'] ?: '-'); ?></td>
              <td><?php echo h($s['phone'] ?: '-'); ?></td>
              <td class="text-right">
                <?php if ($s['current_balance'] > 0): ?>
                  <span class="badge badge-danger"><?php echo format_currency($s['current_balance']); ?></span>
                <?php else: ?>
                  <span class="badge badge-success"><?php echo format_currency(0); ?></span>
                <?php endif; ?>
              </td>
              <td class="text-center">
                <a href="supplier_view.php?id=<?php echo $s['id']; ?>" class="btn btn-outline btn-sm">
                  📜 <?php echo $is_rtl ? 'کھاتہ' : 'Statement'; ?>
                </a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
