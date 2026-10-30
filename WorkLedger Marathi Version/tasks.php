<?php
require_once 'includes/layout.php';
require_login();
$err = '';
$edit = isset($_GET['edit']) ? one('SELECT * FROM tasks WHERE id = ?', [(int)$_GET['edit']]) : null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify(); require_admin();
    if (($_POST['action'] ?? '') === 'delete') {
        $id = (int)$_POST['id'];
        if ((int)val('SELECT COUNT(*) FROM work_entries WHERE task_id = ?', [$id]) > 0) { q("UPDATE tasks SET status='Inactive' WHERE id=?", [$id]); flash('warn', 'This job is used in work entries, so it was marked Inactive instead of deleted.'); }
        else { q('DELETE FROM tasks WHERE id=?', [$id]); flash('ok', 'Job deleted.'); }
        redirect('tasks.php');
    }
    $id = (int)($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? ''); $unit = $_POST['unit'] ?? ''; $rate = $_POST['rate'] ?? '';
    $status = ($_POST['status'] ?? 'Active') === 'Inactive' ? 'Inactive' : 'Active'; $desc = trim($_POST['description'] ?? '');
    if ($name === '') $err = 'Job name is required.';
    elseif (!in_array($unit, UNITS, true)) $err = 'Choose a unit.';
    elseif (!is_numeric($rate) || $rate < 0) $err = 'Enter a valid rate.';
    elseif ((int)val('SELECT COUNT(*) FROM tasks WHERE name = ? AND id <> ?', [$name, $id]) > 0) $err = 'A job with this name already exists.';
    else {
        if ($id) q('UPDATE tasks SET name=?, unit=?, rate=?, description=?, status=? WHERE id=?', [$name, $unit, $rate, $desc, $status, $id]);
        else q('INSERT INTO tasks (name, unit, rate, description, status) VALUES (?,?,?,?,?)', [$name, $unit, $rate, $desc, $status]);
        flash('ok', $id ? 'Job updated. The new rate applies to future work entries only.' : 'Job added.');
        redirect('tasks.php');
    }
    $edit = ['id' => $id, 'name' => $name, 'unit' => $unit, 'rate' => $rate, 'description' => $desc, 'status' => $status];
}
$rows = all('SELECT t.*, (SELECT COUNT(*) FROM work_entries w WHERE w.task_id = t.id) AS used FROM tasks t ORDER BY t.status, t.name');
layout_top('Jobs & rates');
if ($err) echo '<div class="alert bad">' . e($err) . '</div>';
if (is_admin()): ?>
<div class="card"><h2><?= $edit ? 'Edit job' : 'Add a job or task' ?></h2>
<form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
  <div class="form-grid">
    <div><label for="name">Job / task name <span class="req">*</span></label><input id="name" name="name" value="<?= e($edit['name'] ?? '') ?>" required></div>
    <div><label for="unit">Charged <span class="req">*</span></label><select id="unit" name="unit"><?php foreach (UNITS as $u): ?><option value="<?= e($u) ?>"<?= ($edit['unit'] ?? 'Per piece') === $u ? ' selected' : '' ?>><?= e($u) ?></option><?php endforeach; ?></select></div>
    <div><label for="rate">Rate (₹) <span class="req">*</span></label><input id="rate" name="rate" type="number" step="0.01" min="0" value="<?= e($edit['rate'] ?? '') ?>" required><div class="hint">Changing a rate affects new work entries only. Past entries keep the rate they were saved with.</div></div>
    <div><label for="status">Status</label><select id="status" name="status"><option value="Active">Active</option><option value="Inactive"<?= ($edit['status'] ?? '') === 'Inactive' ? ' selected' : '' ?>>Inactive</option></select></div>
    <div class="full"><label for="description">Description</label><input id="description" name="description" value="<?= e($edit['description'] ?? '') ?>"></div>
  </div>
  <div class="form-actions"><button class="btn btn-primary"><?= $edit ? 'Save job' : 'Add job' ?></button><?php if ($edit): ?><a class="btn btn-ghost" href="tasks.php">Cancel</a><?php endif; ?></div>
</form></div>
<?php endif; ?>
<div class="card flush"><div class="table-wrap"><table class="stack">
<thead><tr><th>Job / task</th><th>Charged</th><th class="num">Rate</th><th>Status</th><th class="num">Entries</th><th></th></tr></thead><tbody>
<?php foreach ($rows as $r): ?>
<tr><td><b><?= e($r['name']) ?></b><?php if ($r['description']): ?><span class="sub"><?= e($r['description']) ?></span><?php endif; ?></td><td><?= e($r['unit']) ?></td><td class="num"><?= inr($r['rate']) ?></td>
<td><?= badge($r['status'], $r['status'] === 'Active' ? 'ok' : 'muted') ?></td><td class="num"><?= (int)$r['used'] ?></td>
<td class="actions"><?php if (is_admin()): ?><a class="btn btn-ghost btn-sm" href="tasks.php?edit=<?= $r['id'] ?>">Edit</a>
<form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $r['id'] ?>"><button class="btn btn-danger btn-sm" data-confirm="Delete this job? Jobs used in work entries are marked Inactive instead.">Delete</button></form><?php endif; ?></td></tr>
<?php endforeach; if (!$rows): ?><tr><td colspan="6" class="empty">No jobs yet. Add a job with its rate so work can be recorded against it.</td></tr><?php endif; ?>
</tbody></table></div></div>
<?php layout_bottom();
