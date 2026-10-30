<?php
require_once 'includes/layout.php';
require_login();
$m1 = date('Y-m-01'); $today = date('Y-m-d');
$active = (int)val("SELECT COUNT(*) FROM staff WHERE status='Active'");
$earned = (float)val("SELECT COALESCE(SUM(amount),0) FROM work_entries WHERE status='Approved' AND work_date BETWEEN ? AND ?", [$m1, $today]);
$paid = (float)val('SELECT COALESCE(SUM(amount),0) FROM payments WHERE pay_date BETWEEN ? AND ?', [$m1, $today]);
$pendingN = (int)val("SELECT COUNT(*) FROM work_entries WHERE status='Pending'");
$pendingA = (float)val("SELECT COALESCE(SUM(amount),0) FROM work_entries WHERE status='Pending'");
$dues = array_values(array_filter(wage_rows($m1, $today), function ($x) { return $x['balance'] > 0.004; }));
usort($dues, function ($a, $b) { return $b['balance'] <=> $a['balance']; });
$totalDue = 0; foreach ($dues as $d) $totalDue += $d['balance'];
$recent = all('SELECT w.*, s.name AS staff, t.name AS task FROM work_entries w JOIN staff s ON s.id=w.staff_id JOIN tasks t ON t.id=w.task_id ORDER BY w.work_date DESC, w.id DESC LIMIT 8');
layout_top('Dashboard', '<a class="btn btn-ghost" href="work_form.php">Add work entry</a><a class="btn btn-primary" href="payment_form.php">Add payment</a>');
?>
<div class="kpis">
  <div class="kpi"><span>Active staff</span><b><?= $active ?></b></div>
  <div class="kpi"><span>Earned this month</span><b><?= inr($earned) ?></b></div>
  <div class="kpi"><span>Paid this month</span><b><?= inr($paid) ?></b></div>
  <div class="kpi"><span>Total balance due</span><b><?= inr($totalDue) ?></b></div>
</div>
<?php if ($pendingN): ?><div class="alert warn"><?= $pendingN ?> work <?= $pendingN === 1 ? 'entry is' : 'entries are' ?> waiting for approval (<?= inr($pendingA) ?>). <a href="work.php?status=Pending&from=2000-01-01&to=<?= $today ?>">Review now</a></div><?php endif; ?>
<div class="two">
  <div class="card flush"><div class="card-head"><h2>Latest work entries</h2><a href="work.php">View all</a></div><div class="table-wrap"><table class="stack">
    <thead><tr><th>Date</th><th>Staff</th><th>Job / task</th><th class="num">Amount</th><th>Status</th></tr></thead><tbody>
    <?php foreach ($recent as $w): ?><tr><td><?= dmy($w['work_date']) ?></td><td><?= e($w['staff']) ?></td><td><?= e($w['task']) ?></td><td class="num"><?= inr($w['amount']) ?></td><td><?= badge($w['status'], $w['status'] === 'Approved' ? 'ok' : 'warn') ?></td></tr>
    <?php endforeach; if (!$recent): ?><tr><td colspan="5" class="empty">No work recorded yet. Add jobs and staff first, then record work.</td></tr><?php endif; ?></tbody></table></div></div>
  <div class="card flush"><div class="card-head"><h2>Highest balances due</h2><a href="wages.php">Wages</a></div><div class="table-wrap"><table class="stack">
    <thead><tr><th>Staff</th><th class="num">Balance due</th><th></th></tr></thead><tbody>
    <?php foreach (array_slice($dues, 0, 6) as $d): ?><tr><td><?= e($d['name']) ?></td><td class="num"><?= inr($d['balance']) ?></td><td class="actions"><a class="btn btn-primary btn-sm" href="payment_form.php?staff_id=<?= $d['id'] ?>">Pay</a></td></tr>
    <?php endforeach; if (!$dues): ?><tr><td colspan="3" class="empty">Nobody is owed anything right now.</td></tr><?php endif; ?></tbody></table></div></div>
</div>
<?php layout_bottom();
