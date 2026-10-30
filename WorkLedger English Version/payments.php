<?php
require_once 'includes/layout.php';
require_login();
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    csrf_verify(); require_admin();
    q('DELETE FROM payments WHERE id = ?', [(int)$_POST['id']]);
    flash('ok', 'Payment deleted.');
    redirect('payments.php' . ($_SERVER['QUERY_STRING'] ? '?' . $_SERVER['QUERY_STRING'] : ''));
}
$from = valid_date($_GET['from'] ?? '') ? $_GET['from'] : date('Y-m-01');
$to = valid_date($_GET['to'] ?? '') ? $_GET['to'] : date('Y-m-d');
$sid = (int)($_GET['staff_id'] ?? 0); $type = $_GET['type'] ?? ''; $mode = $_GET['mode'] ?? '';
$where = ['p.pay_date BETWEEN ? AND ?']; $p = [$from, $to];
if ($sid) { $where[] = 'p.staff_id = ?'; $p[] = $sid; }
if (in_array($type, ['Wage', 'Advance'], true)) { $where[] = 'p.type = ?'; $p[] = $type; }
if (in_array($mode, PAY_MODES, true)) { $where[] = 'p.mode = ?'; $p[] = $mode; }
$rows = all('SELECT p.*, s.name AS staff FROM payments p JOIN staff s ON s.id = p.staff_id WHERE ' . implode(' AND ', $where) . ' ORDER BY p.pay_date DESC, p.id DESC', $p);
$total = 0; foreach ($rows as $r) $total += $r['amount'];
layout_top('Payments', '<a class="btn btn-primary" href="payment_form.php">Add payment</a>');
?>
<form class="filters" method="get">
  <div><label for="from">From</label><input id="from" type="date" name="from" value="<?= e($from) ?>"></div>
  <div><label for="to">To</label><input id="to" type="date" name="to" value="<?= e($to) ?>"></div>
  <div><label>Staff</label><?= staff_select('staff_id', $sid, false, true) ?></div>
  <div><label for="type">Type</label><select id="type" name="type"><option value="">All</option><option<?= $type === 'Wage' ? ' selected' : '' ?>>Wage</option><option<?= $type === 'Advance' ? ' selected' : '' ?>>Advance</option></select></div>
  <div><label for="mode">Mode</label><select id="mode" name="mode"><option value="">All</option><?php foreach (PAY_MODES as $m): ?><option<?= $mode === $m ? ' selected' : '' ?>><?= e($m) ?></option><?php endforeach; ?></select></div>
  <div class="btns"><button class="btn btn-primary">Filter</button><a class="btn btn-ghost" href="payments.php">Reset</a></div>
</form>
<div class="card flush"><div class="table-wrap"><table class="stack">
<thead><tr><th>Slip no.</th><th>Date</th><th>Staff</th><th>Type</th><th>Mode</th><th>Reference</th><th class="num">Amount</th><th></th></tr></thead><tbody>
<?php foreach ($rows as $r): ?>
<tr><td>PAY-<?= str_pad($r['id'], 4, '0', STR_PAD_LEFT) ?></td><td><?= dmy($r['pay_date']) ?></td><td><?= e($r['staff']) ?></td><td><?= badge($r['type'], $r['type'] === 'Wage' ? 'ok' : 'warn') ?></td><td><?= e($r['mode']) ?></td><td><?= e($r['reference'] ?: '—') ?></td><td class="num"><?= inr($r['amount']) ?></td>
<td class="actions"><a class="btn btn-ghost btn-sm" href="payment_slip.php?id=<?= $r['id'] ?>">Slip</a><?php if (is_admin()): ?> <a class="btn btn-ghost btn-sm" href="payment_form.php?id=<?= $r['id'] ?>">Edit</a>
<form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $r['id'] ?>"><button class="btn btn-danger btn-sm" data-confirm="Delete this payment? The staff balance will increase by this amount.">Delete</button></form><?php endif; ?></td></tr>
<?php endforeach; if (!$rows): ?><tr><td colspan="8" class="empty">No payments for these filters.</td></tr><?php endif; ?>
</tbody><?php if ($rows): ?><tfoot><tr><td>Total</td><td></td><td></td><td></td><td></td><td></td><td class="num"><?= inr($total) ?></td><td></td></tr></tfoot><?php endif; ?></table></div></div>
<?php layout_bottom();
