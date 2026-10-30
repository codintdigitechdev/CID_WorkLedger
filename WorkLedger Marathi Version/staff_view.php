<?php
require_once 'includes/layout.php';
require_login();
$id = (int)($_GET['id'] ?? 0);
$s = one('SELECT * FROM staff WHERE id = ?', [$id]);
if (!$s) { flash('bad', 'Staff member not found.'); redirect('staff.php'); }
$earned = (float)val("SELECT COALESCE(SUM(amount),0) FROM work_entries WHERE staff_id=? AND status='Approved'", [$id]);
$pending = (float)val("SELECT COALESCE(SUM(amount),0) FROM work_entries WHERE staff_id=? AND status='Pending'", [$id]);
$paid = (float)val('SELECT COALESCE(SUM(amount),0) FROM payments WHERE staff_id=?', [$id]);
$work = all('SELECT w.*, t.name AS task, t.unit FROM work_entries w JOIN tasks t ON t.id=w.task_id WHERE w.staff_id=? ORDER BY w.work_date DESC, w.id DESC LIMIT 10', [$id]);
$pays = all('SELECT * FROM payments WHERE staff_id=? ORDER BY pay_date DESC, id DESC LIMIT 10', [$id]);
layout_top($s['name'], '<a class="btn btn-ghost" href="staff_form.php?id=' . $id . '">Edit</a><a class="btn btn-ghost" href="work_form.php?staff_id=' . $id . '">Add work</a><a class="btn btn-primary" href="payment_form.php?staff_id=' . $id . '">Add payment</a>');
?>
<div class="kpis">
  <div class="kpi"><span>Earned (approved)</span><b><?= inr($earned) ?></b></div>
  <div class="kpi"><span>Paid</span><b><?= inr($paid) ?></b></div>
  <div class="kpi"><span>Balance due</span><b class="<?= $earned - $paid > 0 ? '' : 'pos' ?>"><?= inr($earned - $paid) ?></b></div>
  <div class="kpi"><span>Awaiting approval</span><b><?= inr($pending) ?></b></div>
</div>
<div class="card"><h2>Details</h2><dl class="profile">
  <div><dt>Staff code</dt><dd><?= e($s['staff_code']) ?></dd></div><div><dt>Designation</dt><dd><?= e($s['designation'] ?: '—') ?></dd></div><div><dt>Status</dt><dd><?= badge($s['status'], $s['status'] === 'Active' ? 'ok' : 'muted') ?></dd></div>
  <div><dt>Phone</dt><dd><?= e($s['phone'] ?: '—') ?></dd></div><div><dt>Email</dt><dd><?= e($s['email'] ?: '—') ?></dd></div><div><dt>Joined</dt><dd><?= dmy($s['joining_date']) ?></dd></div>
  <div><dt>Address</dt><dd><?= nl2br(e($s['address'] ?: '—')) ?></dd></div><div><dt>Bank / UPI</dt><dd><?= nl2br(e($s['bank_details'] ?: '—')) ?></dd></div><div><dt>Notes</dt><dd><?= nl2br(e($s['notes'] ?: '—')) ?></dd></div>
</dl></div>
<div class="two">
  <div class="card flush"><div class="card-head"><h2>Recent work</h2><a href="work.php?staff_id=<?= $id ?>&from=2000-01-01&to=<?= date('Y-m-d') ?>">View all</a></div><div class="table-wrap"><table class="stack">
    <thead><tr><th>Date</th><th>Task</th><th class="num">Qty</th><th class="num">Amount</th><th>Status</th></tr></thead><tbody>
    <?php foreach ($work as $w): ?><tr><td><?= dmy($w['work_date']) ?></td><td><?= e($w['task']) ?></td><td class="num"><?= qty($w['quantity']) ?></td><td class="num"><?= inr($w['amount']) ?></td><td><?= badge($w['status'], $w['status'] === 'Approved' ? 'ok' : 'warn') ?></td></tr>
    <?php endforeach; if (!$work): ?><tr><td colspan="5" class="empty">No work recorded yet.</td></tr><?php endif; ?></tbody></table></div></div>
  <div class="card flush"><div class="card-head"><h2>Recent payments</h2><a href="payments.php?staff_id=<?= $id ?>&from=2000-01-01&to=<?= date('Y-m-d') ?>">View all</a></div><div class="table-wrap"><table class="stack">
    <thead><tr><th>Date</th><th>Type</th><th class="num">Amount</th></tr></thead><tbody>
    <?php foreach ($pays as $y): ?><tr><td><?= dmy($y['pay_date']) ?></td><td><?= e($y['type']) ?> · <?= e($y['mode']) ?></td><td class="num"><?= inr($y['amount']) ?></td></tr>
    <?php endforeach; if (!$pays): ?><tr><td colspan="3" class="empty">No payments yet.</td></tr><?php endif; ?></tbody></table></div></div>
</div>
<?php layout_bottom();
