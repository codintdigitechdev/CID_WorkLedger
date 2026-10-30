<?php
require_once 'includes/layout.php';
require_login();
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
if ($id) require_admin();
$pay = $id ? one('SELECT * FROM payments WHERE id = ?', [$id]) : null;
if ($id && !$pay) { flash('bad', 'Payment not found.'); redirect('payments.php'); }
$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $sid = (int)($_POST['staff_id'] ?? 0); $date = $_POST['pay_date'] ?? ''; $amt = $_POST['amount'] ?? '';
    $type = ($_POST['type'] ?? 'Wage') === 'Advance' ? 'Advance' : 'Wage'; $mode = $_POST['mode'] ?? '';
    $ref = trim($_POST['reference'] ?? ''); $rem = trim($_POST['remarks'] ?? '');
    if (!$sid || !one('SELECT id FROM staff WHERE id = ?', [$sid])) $err = 'Select a staff member.';
    elseif (!valid_date($date)) $err = 'Enter a valid payment date.';
    elseif (!is_numeric($amt) || $amt <= 0) $err = 'Amount must be more than zero.';
    elseif (!in_array($mode, PAY_MODES, true)) $err = 'Choose a payment mode.';
    else {
        if ($pay) { q('UPDATE payments SET staff_id=?, pay_date=?, amount=?, type=?, mode=?, reference=?, remarks=? WHERE id=?', [$sid, $date, $amt, $type, $mode, $ref, $rem, $id]); $nid = $id; }
        else { q('INSERT INTO payments (staff_id, pay_date, amount, type, mode, reference, remarks, created_by) VALUES (?,?,?,?,?,?,?,?)', [$sid, $date, $amt, $type, $mode, $ref, $rem, current_user()['id']]); $nid = (int)db()->lastInsertId(); }
        flash('ok', 'Payment saved.');
        redirect('payment_slip.php?id=' . $nid);
    }
    $pay = ['staff_id' => $sid, 'pay_date' => $date, 'amount' => $amt, 'type' => $type, 'mode' => $mode, 'reference' => $ref, 'remarks' => $rem];
}
$selStaff = (int)($pay['staff_id'] ?? $_GET['staff_id'] ?? 0);
$balances = [];
foreach (wage_rows('1970-01-01', date('Y-m-d')) as $r) $balances[$r['id']] = round((float)$r['balance'], 2);
$prefill = $pay['amount'] ?? ($selStaff && ($balances[$selStaff] ?? 0) > 0 ? $balances[$selStaff] : '');
layout_top($id ? 'Edit payment' : 'Add payment');
if ($err) echo '<div class="alert bad">' . e($err) . '</div>';
?>
<div class="card"><form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>">
  <div class="form-grid">
    <div><label for="f_staff_id">Staff <span class="req">*</span></label><?= staff_select('staff_id', $selStaff) ?><div class="hint" id="balHint"></div></div>
    <div><label for="pay_date">Payment date <span class="req">*</span></label><input id="pay_date" type="date" name="pay_date" value="<?= e($pay['pay_date'] ?? date('Y-m-d')) ?>" required></div>
    <div><label for="type">Type</label><select id="type" name="type"><option value="Wage">Wage (against approved work)</option><option value="Advance"<?= ($pay['type'] ?? '') === 'Advance' ? ' selected' : '' ?>>Advance</option></select></div>
    <div><label for="amount">Amount (₹) <span class="req">*</span></label><input id="amount" type="number" step="0.01" min="0.01" name="amount" value="<?= e($prefill) ?>" required></div>
    <div><label for="mode">Mode <span class="req">*</span></label><select id="mode" name="mode"><?php foreach (PAY_MODES as $m): ?><option value="<?= e($m) ?>"<?= ($pay['mode'] ?? '') === $m ? ' selected' : '' ?>><?= e($m) ?></option><?php endforeach; ?></select></div>
    <div><label for="reference">Reference / UTR / cheque no.</label><input id="reference" name="reference" value="<?= e($pay['reference'] ?? '') ?>"></div>
    <div class="full"><label for="remarks">Remarks</label><input id="remarks" name="remarks" value="<?= e($pay['remarks'] ?? '') ?>"></div>
  </div>
  <div class="form-actions"><button class="btn btn-primary">Save payment</button><a class="btn btn-ghost" href="payments.php">Cancel</a></div>
</form></div>
<script>
(function(){
  var L={bal:<?= json_encode(__('Balance due'), JSON_UNESCAPED_UNICODE) ?>,adv:<?= json_encode(__('Already paid in advance'), JSON_UNESCAPED_UNICODE) ?>,none:<?= json_encode(__('Nothing due.'), JSON_UNESCAPED_UNICODE) ?>},bal=<?= json_encode($balances) ?>,s=document.getElementById('f_staff_id'),h=document.getElementById('balHint'),a=document.getElementById('amount');
  function show(){var b=bal[s.value];if(b===undefined){h.textContent='';return;}
    h.textContent=b>0?L.bal+': ₹'+b.toFixed(2):(b<0?L.adv+': ₹'+Math.abs(b).toFixed(2):L.none);}
  s.addEventListener('change',function(){show();var b=bal[s.value];if(b>0&&!<?= $id ? 'true' : 'false' ?>)a.value=b.toFixed(2);});
  show();
})();
</script>
<?php layout_bottom();
