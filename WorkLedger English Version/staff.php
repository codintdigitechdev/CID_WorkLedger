<?php
require_once 'includes/layout.php';
require_login();
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    csrf_verify(); require_admin();
    $id = (int)$_POST['id'];
    $used = (int)val('SELECT (SELECT COUNT(*) FROM work_entries WHERE staff_id = ?) + (SELECT COUNT(*) FROM payments WHERE staff_id = ?)', [$id, $id]);
    if ($used > 0) { q("UPDATE staff SET status = 'Inactive' WHERE id = ?", [$id]); flash('warn', 'This person has work or payment records, so they were marked Inactive instead of deleted.'); }
    else { q('DELETE FROM staff WHERE id = ?', [$id]); flash('ok', 'Staff member deleted.'); }
    redirect('staff.php');
}
$qs = trim($_GET['q'] ?? ''); $st = $_GET['status'] ?? '';
$where = []; $p = [];
if ($qs !== '') { $where[] = '(s.name LIKE ? OR s.phone LIKE ? OR s.staff_code LIKE ? OR s.designation LIKE ?)'; $l = '%' . $qs . '%'; array_push($p, $l, $l, $l, $l); }
if (in_array($st, ['Active', 'Inactive'], true)) { $where[] = 's.status = ?'; $p[] = $st; }
$rows = all("SELECT s.*,
  (SELECT COALESCE(SUM(amount),0) FROM work_entries w WHERE w.staff_id = s.id AND w.status = 'Approved') AS earned,
  (SELECT COALESCE(SUM(amount),0) FROM payments x WHERE x.staff_id = s.id) AS paid
  FROM staff s" . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY s.name', $p);
layout_top('Staff', '<a class="btn btn-primary" href="staff_form.php">Add staff</a>');
?>
<form class="filters" method="get">
  <div><label for="q">Search</label><input id="q" name="q" value="<?= e($qs) ?>" placeholder="Name, phone, code, role"></div>
  <div><label for="status">Status</label><select id="status" name="status"><option value="">All</option><option<?= $st === 'Active' ? ' selected' : '' ?>>Active</option><option<?= $st === 'Inactive' ? ' selected' : '' ?>>Inactive</option></select></div>
  <div class="btns"><button class="btn btn-primary">Filter</button><a class="btn btn-ghost" href="staff.php">Reset</a></div>
</form>
<div class="card flush"><div class="table-wrap"><table class="stack">
<thead><tr><th>Code</th><th>Name</th><th>Phone</th><th>Designation</th><th>Joined</th><th>Status</th><th class="num">Balance due</th><th></th></tr></thead>
<tbody>
<?php foreach ($rows as $r): $bal = $r['earned'] - $r['paid']; ?>
<tr><td><?= e($r['staff_code']) ?></td><td><a href="staff_view.php?id=<?= $r['id'] ?>"><b><?= e($r['name']) ?></b></a></td><td><?= e($r['phone'] ?: '—') ?></td><td><?= e($r['designation'] ?: '—') ?></td><td><?= dmy($r['joining_date']) ?></td>
<td><?= badge($r['status'], $r['status'] === 'Active' ? 'ok' : 'muted') ?></td><td class="num <?= $bal > 0 ? '' : 'muted' ?>"><?= inr($bal) ?></td>
<td class="actions"><a class="btn btn-ghost btn-sm" href="staff_view.php?id=<?= $r['id'] ?>">View</a> <a class="btn btn-ghost btn-sm" href="staff_form.php?id=<?= $r['id'] ?>">Edit</a>
<?php if (is_admin()): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $r['id'] ?>"><button class="btn btn-danger btn-sm" data-confirm="Delete <?= e($r['name']) ?>? Staff with records are marked Inactive instead.">Delete</button></form><?php endif; ?></td></tr>
<?php endforeach; if (!$rows): ?><tr><td colspan="8" class="empty">No staff found. Add your first staff member to begin.</td></tr><?php endif; ?>
</tbody></table></div></div>
<?php layout_bottom();
