<?php
require_once 'includes/layout.php';
require_login();
$me = current_user();
$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    if (($_POST['action'] ?? '') === 'info') {
        $name = trim($_POST['name'] ?? ''); $email = trim($_POST['email'] ?? '');
        if ($name === '') $err = 'Name is required.';
        elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) $err = 'Enter a valid email address.';
        else { q('UPDATE users SET name = ?, email = ? WHERE id = ?', [$name, $email ?: null, $me['id']]); flash('ok', 'Profile updated.'); redirect('profile.php'); }
    } else {
        $row = one('SELECT password_hash FROM users WHERE id = ?', [$me['id']]);
        $new = (string)$_POST['new']; 
        if (!password_verify((string)$_POST['current'], $row['password_hash'])) $err = 'Current password is incorrect.';
        elseif ($new !== (string)$_POST['confirm']) $err = 'The new passwords do not match.';
        elseif (!password_ok($new)) $err = 'Use at least 8 characters with letters and numbers.';
        else { q('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($new, PASSWORD_DEFAULT), $me['id']]); flash('ok', 'Password changed.'); redirect('profile.php'); }
    }
}
layout_top('My profile');
if ($err) echo '<div class="alert bad">' . e($err) . '</div>';
?>
<div class="two">
  <div class="card"><h2>Account details</h2>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="info">
      <div class="form-grid">
        <div><label>Username</label><input value="<?= e($me['username']) ?>" readonly></div>
        <div><label>Role</label><input value="<?= e(ucfirst($me['role'])) ?>" readonly></div>
        <div><label for="name">Name <span class="req">*</span></label><input id="name" name="name" value="<?= e($me['name']) ?>" required></div>
        <div><label for="email">Email</label><input id="email" type="email" name="email" value="<?= e($me['email']) ?>"><div class="hint">Used for password reset mails.</div></div>
      </div>
      <div class="form-actions"><button class="btn btn-primary">Save details</button></div>
    </form>
  </div>
  <div class="card"><h2>Change password</h2>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="password">
      <div class="form-grid">
        <div class="full"><label for="current">Current password</label><input id="current" type="password" name="current" required></div>
        <div class="full"><label for="new">New password</label><input id="new" type="password" name="new" required><div class="hint">At least 8 characters, letters and numbers.</div></div>
        <div class="full"><label for="confirm">Confirm new password</label><input id="confirm" type="password" name="confirm" required></div>
      </div>
      <div class="form-actions"><button class="btn btn-primary">Change password</button></div>
    </form>
  </div>
</div>
<?php layout_bottom();
