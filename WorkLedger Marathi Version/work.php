<?php
require_once 'includes/layout.php';
require_login();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $ra = $_POST['row_action'] ?? ''; $bulk = $_POST['bulk'] ?? '';
    if ($ra !== '') {
        [$a, $id] = array_pad(explode(':', $ra, 2), 2, ''); $id = (int)$id;
        if ($a === 'approve' && can_approve()) { q("UPDATE work_entries SET status='Approved' WHERE id=?", [$id]); flash('ok', 'Work entry approved.'); }
        elseif ($a === 'unapprove' && is_admin()) { q("UPDATE work_entries SET status='Pending' WHERE id=?", [$id]); flash('ok', 'Entry moved back to pending.'); }
        elseif ($a === 'delete' && is_admin()) { q('DELETE FROM work_entries WHERE id=?', [$id]); flash('ok', 'Work entry deleted.'); }
    } elseif ($bulk === 'approve' && can_approve()) {
        $ids = array_map('intval', $_POST['ids'] ?? []);
        if ($ids) { q("UPDATE work_entries SET status='Approved' WHERE status='Pending' AND id IN (" . implode(',', array_fill(0, count($ids), '?')) . ')', $ids); flash('ok', 'Selected entries approved.'); }
        else flash('warn', 'Select at least one entry first.');
    }
    redirect('work.php' . ($_SERVER['QUERY_STRING'] ? '?' . $_SERVER['QUERY_STRING'] : ''));
}
$from = valid_date($_GET['from'] ?? '') ? $_GET['from'] : date('Y-m-01');
$to = valid_date($_GET['to'] ?? '') ? $_GET['to'] : date('Y-m-d');
$sid = (int)($_GET['staff_id'] ?? 0); $tid = (int)($_GET['task_id'] ?? 0); $st = $_GET['status'] ?? '';
$where = ['w.work_date BETWEEN ? AND ?']; $p = [$from, $to];
if ($sid) { $where[] = 'w.staff_id = ?'; $p[] = $sid; }
if ($tid) { $where[] = 'w.task_id = ?'; $p[] = $tid; }
if (in_array($st, ['Pending', 'Approved'], true)) { $where[] = 'w.status = ?'; $p[] = $st; }
$rows = all('SELECT w.*, s.name AS staff, s.staff_code, t.name AS task, t.unit FROM work_entries w JOIN staff s ON s.id=w.staff_id JOIN tasks t ON t.id=w.task_id WHERE ' . implode(' AND ', $where) . ' ORDER BY w.work_date DESC, w.id DESC', $p);
$tot = 0; $pend = 0; foreach ($rows as $r) { if ($r['status'] === 'Approved') $tot += $r['amount']; else $pend += $r['amount']; }
$tasks = all('SELECT id, name FROM tasks ORDER BY name');
layout_top('Work entries', '<a class="btn btn-primary" href="work_form.php">Add work entry</a>');
?>
<form class="filters" method="get">
  <div><label for="from">From</label><input id="from" type="date" name="from" value="<?= e($from) ?>"></div>
  <div><label for="to">To</label><input id="to" type="date" name="to" value="<?= e($to) ?>"></div>
  <div><label>Staff</label><?= staff_select('staff_id', $sid, false, true) ?></div>
  <div><label for="task_id">Job / task</label><select id="task_id" name="task_id"><option value="0">All jobs</option><?php foreach ($tasks as $t): ?><option value="<?= $t['id'] ?>"<?= $t['id'] == $tid ? ' selected' : '' ?>><?= e($t['name']) ?></option><?php endforeach; ?></select></div>
  <div><label for="status">Status</label><select id="status" name="status"><option value="">All</option><option value="Pending"<?= $st === 'Pending' ? ' selected' : '' ?>>Pending</option><option value="Approved"<?= $st === 'Approved' ? ' selected' : '' ?>>Approved</option></select></div>
  <div class="btns"><button class="btn btn-primary">Filter</button><a class="btn btn-ghost" href="work.php">Reset</a></div>
</form>
<form method="post"><?= csrf_field() ?>
<div class="card flush">
<?php if (can_approve()): ?><div class="card-head"><span class="muted"><?= count($rows) ?> entries · Approved <?= inr($tot) ?> · Pending <?= inr($pend) ?></span><button class="btn btn-ok btn-sm" name="bulk" value="approve">Approve selected</button></div><?php endif; ?>
<div class="table-wrap"><table class="stack">
<thead><tr><?php if (can_approve()): ?><th><input type="checkbox" id="checkAll" aria-label="Select all"></th><?php endif; ?><th>Date</th><th>Staff</th><th>Job / task</th><th class="num">Qty</th><th class="num">Rate</th><th class="num">Amount</th><th>Status</th><th></th></tr></thead>
<tbody>
<?php foreach ($rows as $r): $locked = $r['status'] === 'Approved' && !is_admin(); ?>
<tr><?php if (can_approve()): ?><td class="chk"><?php if ($r['status'] === 'Pending'): ?><input type="checkbox" name="ids[]" value="<?= $r['id'] ?>" aria-label="Select entry"><?php endif; ?></td><?php endif; ?>
<td><?= dmy($r['work_date']) ?></td><td><?= e($r['staff']) ?></td><td><?= e($r['task']) ?><?php if ($r['remarks']): ?><span class="sub"><?= e($r['remarks']) ?></span><?php endif; ?></td>
<td class="num"><?= qty($r['quantity']) ?> <span class="muted"><?= e(strtolower(str_replace('Per ', '', $r['unit']))) ?></span></td><td class="num"><?= inr($r['rate']) ?></td><td class="num"><?= inr($r['amount']) ?></td>
<td><?= badge($r['status'], $r['status'] === 'Approved' ? 'ok' : 'warn') ?></td>
<td class="actions"><?php if ($r['status'] === 'Pending' && can_approve()): ?><button class="btn btn-ok btn-sm" name="row_action" value="approve:<?= $r['id'] ?>">Approve</button> <?php endif; ?>
<?php if (!$locked): ?><a class="btn btn-ghost btn-sm" href="work_form.php?id=<?= $r['id'] ?>">Edit</a><?php endif; ?>
<?php if (is_admin()): ?><?php if ($r['status'] === 'Approved'): ?> <button class="btn btn-ghost btn-sm" name="row_action" value="unapprove:<?= $r['id'] ?>" data-confirm="Move this entry back to pending?">Unapprove</button><?php endif; ?> <button class="btn btn-danger btn-sm" name="row_action" value="delete:<?= $r['id'] ?>" data-confirm="Delete this work entry?">Delete</button><?php endif; ?></td></tr>
<?php endforeach; if (!$rows): ?><tr><td colspan="9" class="empty">No work entries for these filters. Change the dates or add a work entry.</td></tr><?php endif; ?>
</tbody></table></div></div></form>
<?php layout_bottom();
