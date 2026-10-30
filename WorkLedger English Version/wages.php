<?php
require_once 'includes/layout.php';
require_login();
$from = valid_date($_GET['from'] ?? '') ? $_GET['from'] : date('Y-m-01');
$to = valid_date($_GET['to'] ?? '') ? $_GET['to'] : date('Y-m-d');
$sid = (int)($_GET['staff_id'] ?? 0);
$rows = array_filter(wage_rows($from, $to, $sid), function ($r) { return $r['status'] === 'Active' || abs($r['balance']) > 0.004 || $r['earned'] > 0; });
$t = ['entries' => 0, 'earned' => 0, 'pending' => 0, 'paid' => 0, 'balance' => 0];
foreach ($rows as $r) foreach ($t as $k => $_) $t[$k] += $r[$k];
layout_top('Wages');
?>
<form class="filters" method="get">
  <div><label for="from">From</label><input id="from" type="date" name="from" value="<?= e($from) ?>"></div>
  <div><label for="to">To</label><input id="to" type="date" name="to" value="<?= e($to) ?>"></div>
  <div><label>Staff</label><?= staff_select('staff_id', $sid, false, true) ?></div>
  <div class="btns"><button class="btn btn-primary">Show</button><a class="btn btn-ghost" href="wages.php">This month</a></div>
</form>
<div class="kpis">
  <div class="kpi"><span>Earned in period</span><b><?= inr($t['earned']) ?></b></div>
  <div class="kpi"><span>Paid in period</span><b><?= inr($t['paid']) ?></b></div>
  <div class="kpi"><span>Awaiting approval</span><b><?= inr($t['pending']) ?></b></div>
  <div class="kpi"><span>Total balance due</span><b><?= inr($t['balance']) ?></b></div>
</div>
<div class="card flush"><div class="table-wrap"><table class="stack">
<thead><tr><th>Staff</th><th class="num">Approved entries</th><th class="num">Earned</th><th class="num">Awaiting approval</th><th class="num">Paid</th><th class="num">Balance due</th><th></th></tr></thead><tbody>
<?php foreach ($rows as $r): ?>
<tr><td><a href="staff_view.php?id=<?= $r['id'] ?>"><b><?= e($r['name']) ?></b></a><span class="sub"><?= e($r['staff_code']) ?></span></td><td class="num"><?= (int)$r['entries'] ?></td><td class="num"><?= inr($r['earned']) ?></td><td class="num"><?= inr($r['pending']) ?></td><td class="num"><?= inr($r['paid']) ?></td>
<td class="num <?= $r['balance'] < -0.004 ? 'neg' : '' ?>"><?= inr($r['balance']) ?><?php if ($r['balance'] < -0.004): ?><span class="sub">paid in advance</span><?php endif; ?></td>
<td class="actions"><a class="btn btn-primary btn-sm" href="payment_form.php?staff_id=<?= $r['id'] ?>">Pay</a></td></tr>
<?php endforeach; if (!$rows): ?><tr><td colspan="7" class="empty">No wages to show for this period.</td></tr><?php endif; ?>
</tbody>
<?php if ($rows): ?><tfoot><tr><td>Total</td><td class="num"><?= (int)$t['entries'] ?></td><td class="num"><?= inr($t['earned']) ?></td><td class="num"><?= inr($t['pending']) ?></td><td class="num"><?= inr($t['paid']) ?></td><td class="num"><?= inr($t['balance']) ?></td><td></td></tr></tfoot><?php endif; ?>
</table></div></div>
<p class="hint">Earned, awaiting approval and paid follow the dates above. Balance due is the running total: all approved work minus all payments.</p>
<?php layout_bottom();
