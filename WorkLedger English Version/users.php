<?php
require_once 'includes/layout.php';
require_admin();
$err = '';
$edit = isset($_GET['id']) ? one('SELECT * FROM users WHERE id = ?', [(int)$_GET['id']]) : null;
$hasManager = (int)val("SELECT COUNT(*) FROM users WHERE role='manager'") > 0;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $id = (int)($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? ''); $username = trim($_POST['username'] ?? ''); $email = trim($_POST['email'] ?? '');
    $status = ($_POST['status'] ?? 'active') === 'disabled' ? 'disabled' : 'active';
    $pass = (string)($_POST['password'] ?? '');
    $target = $id ? one('SELECT * FROM users WHERE id = ?', [$id]) : null;
    if ($name === '' || $username === '') $err = 'Name and username are required.';
    elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) $err = 'Enter a valid email address.';
    elseif ((int)val('SELECT COUNT(*) FROM users WHERE (username = ? OR (email = ? AND email <> \'\')) AND id <> ?', [$username, $email, $id]) > 0) $err = 'That username or email is already used.';
    elseif (!$id && $hasManager) $err = 'A manager account already exists. Edit it instead.';
    elseif (!$id && !password_ok($pass)) $err = 'Set a password with at least 8 characters, letters and numbers.';
    elseif ($pass !== '' && !password_ok($pass)) $err = 'Password needs at least 8 characters, letters and numbers.';
    elseif ($target && (int)$target['id'] === (int)current_user()['id'] && $status === 'disabled') $err = 'You cannot disable your own account.';
    else {
        if ($id && $target) {
            q('UPDATE users SET name = ?, username = ?, email = ?, status = ? WHERE id = ?', [$name, $username, $email ?: null, $status, $id]);
            if ($pass !== '') q('UPDATE users SET password_hash = ?, failed_attempts = 0, locked_until = NULL WHERE id = ?', [password_hash($pass, PASSWORD_DEFAULT), $id]);
            flash('ok', 'User updated.');
        } else {
            q("INSERT INTO users (name, username, email, password_hash, role, status) VALUES (?,?,?,?, 'manager', ?)", [$name, $username, $email ?: null, password_hash($pass, PASSWORD_DEFAULT), $status]);
            flash('ok', 'Manager account created.');
        }
        redirect('users.php');
    }
    $edit = $id ? $target : null;
}
$users = all('SELECT * FROM users ORDER BY role, name');
layout_top('Users');
if ($err) echo '<div class="alert bad">' . e($err) . '</div>';
?>
<div class="card flush"><div class="table-wrap"><table class="stack">
  <thead><tr><th>Name</th><th>Username</th><th>Role</th><th>Status</th><th>Last sign in</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($users as $u): ?>
    <tr><td><b><?= e($u['name']) ?></b><span class="sub"><?= e($u['email']) ?></span></td><td><?= e($u['username']) ?></td><td><?= e(ucfirst($u['role'])) ?></td>
    <td><?= badge(ucfirst($u['status']), $u['status'] === 'active' ? 'ok' : 'bad') ?></td><td><?= $u['last_login'] ? e(date('d M Y, h:i A', strtotime($u['last_login']))) : '—' ?></td>
    <td class="actions"><a class="btn btn-ghost btn-sm" href="users.php?id=<?= $u['id'] ?>">Edit</a></td></tr>
  <?php endforeach; ?>
  </tbody></table></div></div>
<?php if ($edit || !$hasManager): ?>
<div class="card"><h2><?= $edit ? 'Edit ' . e($edit['name']) : 'Create manager account' ?></h2>
  <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
    <div class="form-grid">
      <div><label for="name">Name <span class="req">*</span></label><input id="name" name="name" value="<?= e($_POST['name'] ?? $edit['name'] ?? '') ?>" required></div>
      <div><label for="username">Username <span class="req">*</span></label><input id="username" name="username" value="<?= e($_POST['username'] ?? $edit['username'] ?? '') ?>" required></div>
      <div><label for="email">Email</label><input id="email" type="email" name="email" value="<?= e($_POST['email'] ?? $edit['email'] ?? '') ?>"></div>
      <div><label for="status">Status</label><select id="status" name="status"><option value="active">Active</option><option value="disabled"<?= ($edit['status'] ?? '') === 'disabled' ? ' selected' : '' ?>>Disabled</option></select></div>
      <div class="full"><label for="password"><?= $edit ? 'Set a new password' : 'Password' ?></label><input id="password" type="password" name="password" autocomplete="new-password"<?= $edit ? '' : ' required' ?>><div class="hint"><?= $edit ? 'Leave empty to keep the current password. ' : '' ?>At least 8 characters, letters and numbers.</div></div>
    </div>
    <div class="form-actions"><button class="btn btn-primary">Save user</button><?php if ($edit): ?><a class="btn btn-ghost" href="users.php">Cancel</a><?php endif; ?></div>
  </form></div>
<?php endif; layout_bottom();
