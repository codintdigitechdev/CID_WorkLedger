<?php
require_once 'includes/layout.php';
$tok = (string)($_GET['token'] ?? $_POST['token'] ?? '');
$u = $tok !== '' ? one('SELECT * FROM users WHERE reset_token = ? AND reset_expires > ? AND status = \'active\'', [hash('sha256', $tok), date('Y-m-d H:i:s')]) : false;
$err = '';
if ($u && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $p1 = (string)$_POST['password']; $p2 = (string)$_POST['confirm'];
    if ($p1 !== $p2) $err = 'The two passwords do not match.';
    elseif (!password_ok($p1)) $err = 'Use at least 8 characters with letters and numbers.';
    else {
        q('UPDATE users SET password_hash = ?, reset_token = NULL, reset_expires = NULL, failed_attempts = 0, locked_until = NULL WHERE id = ?', [password_hash($p1, PASSWORD_DEFAULT), $u['id']]);
        flash('ok', 'Password changed. Sign in with your new password.');
        redirect('login.php');
    }
}
auth_top('New password');
if (!$u): ?>
<h1>Link expired</h1>
<p class="lead">This reset link is invalid or has expired. Request a new one.</p>
<a class="btn btn-primary" href="forgot.php">Request new link</a>
<?php else: ?>
<h1>Set a new password</h1>
<p class="lead">At least 8 characters, with letters and numbers.</p>
<?php if ($err): ?><div class="alert bad"><?= e($err) ?></div><?php endif; ?>
<form method="post">
  <?= csrf_field() ?><input type="hidden" name="token" value="<?= e($tok) ?>">
  <div class="field"><label for="password">New password</label><input id="password" type="password" name="password" required autofocus></div>
  <div class="field"><label for="confirm">Confirm password</label><input id="confirm" type="password" name="confirm" required></div>
  <button class="btn btn-primary" type="submit">Save password</button>
</form>
<?php endif; auth_bottom();
