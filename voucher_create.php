<?php
require_once __DIR__ . '/config/init.php';
require_once __DIR__ . '/includes/accounting_helper.php';

$bid = (int)$current_business['id'];
$pdo = getDB();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = "Security token mismatch.";
    } else {
        $v_type = $_POST['voucher_type'] ?? 'JV';
        $v_date = $_POST['voucher_date'] ?? date('Y-m-d');
        $desc = trim($_POST['narration'] ?? '');
        $raw_lines = $_POST['lines'] ?? [];

        $lines = [];
        foreach ($raw_lines as $l) {
            $accId = (int)($l['account_id'] ?? 0);
            $deb = (float)($l['debit'] ?? 0);
            $crd = (float)($l['credit'] ?? 0);
            $narr = trim($l['narration'] ?? $desc);

            if ($accId > 0 && ($deb > 0 || $crd > 0)) {
                $lines[] = [
                    'account_id' => $accId,
                    'debit' => $deb,
                    'credit' => $crd,
                    'narration' => $narr
                ];
            }
        }

        if (count($lines) < 2) {
            $error = $is_rtl ? 'واؤچر میں کم از کم ایک ڈیبٹ اور ایک کریڈٹ لائن کا ہونا لازمی ہے۔' : 'At least one debit and one credit line is required.';
        } else {
            $lastNo = $pdo->query("SELECT MAX(id) FROM vouchers WHERE business_id = {$bid}")->fetchColumn() ?: 100;
            $voucher_number = $v_type . '-' . ($lastNo + 1);

            $pdo->beginTransaction();
            try {
                // Post balanced entry via accounting engine
                $entryId = AccountingEngine::postJournalEntry(
                    $bid,
                    $voucher_number,
                    $v_date,
                    'voucher',
                    null,
                    $desc,
                    $lines,
                    $current_user['id']
                );

                $pdo->commit();
                log_audit('CREATE_VOUCHER', 'journal_entry', $entryId, "Created voucher {$voucher_number}");
                header("Location: vouchers.php");
                exit;

            } catch (Exception $e) {
                $pdo->rollBack();
                $error = "Voucher failed: " . $e->getMessage();
            }
        }
    }
}

$accounts = $pdo->query("SELECT id, account_code, account_name, account_name_ur, account_type FROM chart_of_accounts WHERE business_id = {$bid} AND status = 'active' ORDER BY account_code ASC")->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
  <div>
    <h1 class="page-title">
      ➕ <?php echo $is_rtl ? 'نیا ڈبل انٹری جنرل واؤچر' : 'New Double-Entry Journal Voucher'; ?>
    </h1>
    <p style="color:var(--text-muted); font-size:0.9rem;">
      <?php echo $is_rtl ? 'بنام (ڈیبٹ) اور جمع (کریڈٹ) کا لازمی توازن (Debits = Credits)' : 'Enforces strict double-entry balance where total debits equal total credits'; ?>
    </p>
  </div>
  <a href="vouchers.php" class="btn btn-outline">&larr; <?php echo h(__('cancel')); ?></a>
</div>

<?php if (!empty($error)): ?>
  <div class="alert alert-danger"><?php echo h($error); ?></div>
<?php endif; ?>

<form method="POST" action="voucher_create.php" id="voucherForm">
  <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">

  <div class="card">
    <div class="card-header">
      <span>📜 <?php echo $is_rtl ? 'واؤچر کی بنیادی معلومات' : 'Voucher Header'; ?></span>
    </div>
    <div class="card-body">
      <div class="grid grid-cols-3">
        <div class="form-group">
          <label class="form-label"><?php echo $is_rtl ? 'واؤچر کی قسم' : 'Voucher Type'; ?></label>
          <select name="voucher_type" class="form-control">
            <option value="JV">JV - Journal Voucher (روزنامچہ)</option>
            <option value="CPV">CPV - Cash Payment Voucher (نقد ادائیگی)</option>
            <option value="CRV">CRV - Cash Receipt Voucher (نقد وصولی)</option>
            <option value="BPV">BPV - Bank Payment Voucher (بینک ادائیگی)</option>
            <option value="BRV">BRV - Bank Receipt Voucher (بینک وصولی)</option>
            <option value="CONTRA">CONTRA - Cash/Bank Transfer (کنٹرا واؤچر)</option>
          </select>
        </div>

        <div class="form-group">
          <label class="form-label"><?php echo h(__('date')); ?> *</label>
          <input type="date" name="voucher_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
        </div>

        <div class="form-group">
          <label class="form-label"><?php echo $is_rtl ? 'بنیادی تفصیل / وجہ' : 'Narration / Remarks'; ?> *</label>
          <input type="text" name="narration" class="form-control" placeholder="Purpose of transaction" required>
        </div>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-header">
      <span>⚖️ <?php echo $is_rtl ? 'کھاتہ اندراجات (ڈیبٹ و کریڈٹ)' : 'Debit & Credit Lines'; ?></span>
    </div>
    <div class="card-body">
      <div class="table-responsive">
        <table class="table" id="voucherTable">
          <thead>
            <tr>
              <th>Account *</th>
              <th style="width:200px;" class="text-right"><?php echo h(__('debit')); ?> (Rs)</th>
              <th style="width:200px;" class="text-right"><?php echo h(__('credit')); ?> (Rs)</th>
              <th>Line Narration</th>
            </tr>
          </thead>
          <tbody>
            <!-- Row 1: Debit Line -->
            <tr>
              <td>
                <select name="lines[1][account_id]" class="form-control" required>
                  <option value="">-- Select Account --</option>
                  <?php foreach ($accounts as $a): ?>
                    <option value="<?php echo $a['id']; ?>">
                      [<?php echo $a['account_code']; ?>] <?php echo h($a['account_name']); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </td>
              <td><input type="number" step="0.01" name="lines[1][debit]" class="form-control text-right v-deb" value="0.00" oninput="calcVoucher()"></td>
              <td><input type="number" step="0.01" name="lines[1][credit]" class="form-control text-right v-crd" value="0.00" oninput="calcVoucher()"></td>
              <td><input type="text" name="lines[1][narration]" class="form-control" placeholder="Line memo"></td>
            </tr>
            <!-- Row 2: Credit Line -->
            <tr>
              <td>
                <select name="lines[2][account_id]" class="form-control" required>
                  <option value="">-- Select Account --</option>
                  <?php foreach ($accounts as $a): ?>
                    <option value="<?php echo $a['id']; ?>">
                      [<?php echo $a['account_code']; ?>] <?php echo h($a['account_name']); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </td>
              <td><input type="number" step="0.01" name="lines[2][debit]" class="form-control text-right v-deb" value="0.00" oninput="calcVoucher()"></td>
              <td><input type="number" step="0.01" name="lines[2][credit]" class="form-control text-right v-crd" value="0.00" oninput="calcVoucher()"></td>
              <td><input type="text" name="lines[2][narration]" class="form-control" placeholder="Line memo"></td>
            </tr>
          </tbody>
          <tfoot>
            <tr style="background:#f8fafc; font-weight:800;">
              <td>Totals:</td>
              <td class="text-right" id="vTotalDebit">Rs 0.00</td>
              <td class="text-right" id="vTotalCredit">Rs 0.00</td>
              <td id="vBalanceNotice" style="color:var(--danger);">Debits != Credits</td>
            </tr>
          </tfoot>
        </table>
      </div>

      <button type="submit" class="btn btn-primary" style="margin-top:15px; font-weight:700;">
        💾 <?php echo $is_rtl ? 'ڈبل انٹری واؤچر پوسٹ کریں' : 'Post Journal Voucher'; ?>
      </button>
    </div>
  </div>
</form>

<script>
function calcVoucher() {
  let totDeb = 0;
  let totCrd = 0;

  document.querySelectorAll('.v-deb').forEach(inp => { totDeb += parseFloat(inp.value) || 0; });
  document.querySelectorAll('.v-crd').forEach(inp => { totCrd += parseFloat(inp.value) || 0; });

  document.getElementById('vTotalDebit').textContent = 'Rs ' + totDeb.toFixed(2);
  document.getElementById('vTotalCredit').textContent = 'Rs ' + totCrd.toFixed(2);

  const diff = Math.abs(totDeb - totCrd);
  const notice = document.getElementById('vBalanceNotice');
  if (diff < 0.01 && totDeb > 0) {
    notice.textContent = 'Balanced ✓';
    notice.style.color = 'var(--success)';
  } else {
    notice.textContent = 'Difference: Rs ' + diff.toFixed(2);
    notice.style.color = 'var(--danger)';
  }
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
