<?php
require_once 'includes/layout.php';
if (current_user()) redirect('index.php');
$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $login = trim($_POST['login'] ?? '');
    $pass = (string)($_POST['password'] ?? '');
    $u = one('SELECT * FROM users WHERE username = ? OR email = ? LIMIT 1', [$login, $login]);
    if (!$u) { $err = 'Incorrect username or password.'; usleep(300000); }
    elseif ($u['status'] !== 'active') { $err = 'This account is disabled. Contact the administrator.'; }
    elseif ($u['locked_until'] && strtotime($u['locked_until']) > time()) { $err = 'Too many attempts. Try again after ' . date('h:i A', strtotime($u['locked_until'])) . '.'; }
    elseif (!password_verify($pass, $u['password_hash'])) {
        $n = (int)$u['failed_attempts'] + 1;
        if ($n >= 5) q('UPDATE users SET failed_attempts = 0, locked_until = ? WHERE id = ?', [date('Y-m-d H:i:s', time() + 600), $u['id']]);
        else q('UPDATE users SET failed_attempts = ? WHERE id = ?', [$n, $u['id']]);
        $err = 'Incorrect username or password.';
    } else {
        session_regenerate_id(true);
        $_SESSION['uid'] = (int)$u['id'];
        $_SESSION['last'] = time();
        q('UPDATE users SET failed_attempts = 0, locked_until = NULL, last_login = ? WHERE id = ?', [date('Y-m-d H:i:s'), $u['id']]);
        redirect('index.php');
    }
}
auth_top('Sign in');
?>
<h1>Sign in</h1>
<p class="lead">Use your administrator or manager account.</p>
<?php if ($err): ?><div class="alert bad"><?= e($err) ?></div><?php endif; ?>
<form method="post" autocomplete="on">
  <?= csrf_field() ?>
  <div class="field"><label for="login">Username or email</label><input id="login" name="login" value="<?= e($_POST['login'] ?? '') ?>" required autofocus></div>
  <div class="field"><label for="password">Password</label><div class="pw"><input id="password" type="password" name="password" required></div>
  <button type="button" class="btn btn-ghost" data-toggle-pw="password">Show</button>
  </div>
  <button class="btn btn-primary" type="submit">Sign in</button>
</form>
<div class="auth-links"><a href="forgot.php">Forgot your password?</a></div>
<?php auth_bottom();
