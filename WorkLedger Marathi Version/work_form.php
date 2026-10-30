<?php
require_once 'includes/layout.php';
require_login();
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$w = $id ? one('SELECT * FROM work_entries WHERE id = ?', [$id]) : null;
if ($id && !$w) { flash('bad', 'Work entry not found.'); redirect('work.php'); }
if ($w && $w['status'] === 'Approved' && !is_admin()) { flash('warn', 'Approved entries can only be edited by the administrator.'); redirect('work.php'); }
$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $sid = (int)($_POST['staff_id'] ?? 0); $tid = (int)($_POST['task_id'] ?? 0);
    $date = $_POST['work_date'] ?? ''; $qty = $_POST['quantity'] ?? ''; $rem = trim($_POST['remarks'] ?? '');
    $task = $tid ? one('SELECT * FROM tasks WHERE id = ?', [$tid]) : null;
    if (!$sid || !one('SELECT id FROM staff WHERE id = ?', [$sid])) $err = 'Select a staff member.';
    elseif (!$task) $err = 'Select a job or task.';
    elseif (!valid_date($date)) $err = 'Enter a valid date.';
    elseif (!is_numeric($qty) || $qty <= 0) $err = 'Quantity must be more than zero.';
    else {
        if (is_admin() && isset($_POST['rate']) && is_numeric($_POST['rate']) && $_POST['rate'] >= 0) $rate = (float)$_POST['rate'];
        elseif ($w && (int)$w['task_id'] === $tid) $rate = (float)$w['rate'];
        else $rate = (float)$task['rate'];
        $amount = round($qty * $rate, 2);
        $status = $w ? $w['status'] : (can_approve() && !empty($_POST['approve_now']) ? 'Approved' : 'Pending');
        if ($w) q('UPDATE work_entries SET staff_id=?, task_id=?, work_date=?, quantity=?, rate=?, amount=?, remarks=? WHERE id=?', [$sid, $tid, $date, $qty, $rate, $amount, $rem, $id]);
        else q('INSERT INTO work_entries (staff_id, task_id, work_date, quantity, rate, amount, status, remarks, created_by) VALUES (?,?,?,?,?,?,?,?,?)', [$sid, $tid, $date, $qty, $rate, $amount, $status, $rem, current_user()['id']]);
        flash('ok', $w ? 'Work entry saved.' : 'Work entry added (' . inr($amount) . ').');
        redirect(!empty($_POST['another']) ? 'work_form.php?staff_id=' . $sid . '&date=' . $date : 'work.php');
    }
    $w = ['staff_id' => $sid, 'task_id' => $tid, 'work_date' => $date, 'quantity' => $qty, 'rate' => $w['rate'] ?? 0, 'remarks' => $rem, 'status' => $w['status'] ?? 'Pending'];
}
$tasks = all("SELECT * FROM tasks WHERE status='Active'" . ($w ? ' OR id=' . (int)$w['task_id'] : '') . ' ORDER BY name');
$selStaff = (int)($w['staff_id'] ?? $_GET['staff_id'] ?? 0);
layout_top($id ? 'Edit work entry' : 'Add work entry');
if ($err) echo '<div class="alert bad">' . e($err) . '</div>';
?>
<div class="card"><form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>">
  <div class="form-grid">
    <div><label for="f_staff_id">Staff <span class="req">*</span></label><?= staff_select('staff_id', $selStaff) ?></div>
    <div><label for="work_date">Date <span class="req">*</span></label><input id="work_date" type="date" name="work_date" max="<?= date('Y-m-d') ?>" value="<?= e($w['work_date'] ?? $_GET['date'] ?? date('Y-m-d')) ?>" required></div>
    <div><label for="task_id">Job / task <span class="req">*</span></label>
      <select id="task_id" name="task_id" required><option value="">Select job</option>
      <?php foreach ($tasks as $t): ?><option value="<?= $t['id'] ?>" data-rate="<?= e($t['rate']) ?>" data-unit="<?= e($t['unit']) ?>"<?= (int)($w['task_id'] ?? 0) === (int)$t['id'] ? ' selected' : '' ?>><?= e($t['name']) ?> — <?= inr($t['rate']) ?> <?= e(strtolower($t['unit'])) ?></option><?php endforeach; ?></select></div>
    <div><label for="quantity">Quantity <span class="req">*</span></label><input id="quantity" type="number" step="0.01" min="0.01" name="quantity" value="<?= e($w['quantity'] ?? '') ?>" required><div class="hint" id="unitHint">Number of pieces, hours, days or jobs.</div></div>
    <div><label for="rate">Rate (₹)</label><input id="rate" name="rate" type="number" step="0.01" min="0" value="<?= e($w['rate'] ?? '') ?>" <?= is_admin() ? '' : 'readonly' ?>><div class="hint"><?= is_admin() ? 'Filled from the job. You can adjust it for this entry.' : 'Taken from the job rate.' ?></div></div>
    <div><label>Amount</label><input id="amount" readonly value="<?= isset($w['amount']) ? e($w['amount']) : '' ?>"></div>
    <div class="full"><label for="remarks">Remarks</label><input id="remarks" name="remarks" value="<?= e($w['remarks'] ?? '') ?>"></div>
    <?php if (!$id && can_approve()): ?><div class="full"><label><input type="checkbox" name="approve_now" value="1"> Approve this entry now</label><div class="hint">Only approved work counts towards wages.</div></div><?php endif; ?>
  </div>
  <div class="form-actions"><button class="btn btn-primary">Save entry</button><?php if (!$id): ?><button class="btn btn-ghost" name="another" value="1">Save and add another</button><?php endif; ?><a class="btn btn-ghost" href="work.php">Cancel</a></div>
</form></div>
<script>
(function(){
  var t=document.getElementById('task_id'),q=document.getElementById('quantity'),r=document.getElementById('rate'),a=document.getElementById('amount'),h=document.getElementById('unitHint');
  var U=<?= json_encode(array_combine(UNITS, array_map('__', UNITS)), JSON_UNESCAPED_UNICODE) ?>,C=<?= json_encode(__('Charged'), JSON_UNESCAPED_UNICODE) ?>;
  var editing=<?= $id ? 'true' : 'false' ?>;
  function calc(){var v=(parseFloat(q.value)||0)*(parseFloat(r.value)||0);a.value=v?v.toFixed(2):'';}
  t.addEventListener('change',function(){var o=t.options[t.selectedIndex];if(o&&o.dataset.rate){r.value=o.dataset.rate;h.textContent=C+': '+(U[o.dataset.unit]||o.dataset.unit)+'.';}calc();});
  q.addEventListener('input',calc);r.addEventListener('input',calc);
  if(!editing&&t.value){t.dispatchEvent(new Event('change'));}
})();
</script>
<?php layout_bottom();
