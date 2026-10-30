<?php
require_once 'includes/layout.php';
if (current_user()) redirect('index.php');
$sent = false; $link = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $id = trim($_POST['login'] ?? '');
    $u = one("SELECT * FROM users WHERE (username = ? OR email = ?) AND status = 'active' LIMIT 1", [$id, $id]);
    if ($u) {
        $tok = bin2hex(random_bytes(32));
        q('UPDATE users SET reset_token = ?, reset_expires = ? WHERE id = ?', [hash('sha256', $tok), date('Y-m-d H:i:s', time() + 1800), $u['id']]);
        $url = base_url() . '/reset.php?token=' . $tok;
        if (is_local()) {
            $link = $url; // local XAMPP has no mail server, so show the link on screen
        } elseif ($u['email']) {
            @mail($u['email'], APP_NAME . ' password reset', "Hello " . $u['name'] . ",\n\nUse this link within 30 minutes to set a new password:\n" . $url . "\n\nIf you did not ask for this, ignore this email.", 'From: ' . MAIL_FROM);
        }
    }
    $sent = true;
}
auth_top('Reset password');
?>
<h1>Reset password</h1>
<?php if ($sent): ?>
  <p class="lead">If that account exists, a reset link is on its way. The link works for 30 minutes.</p>
  <?php if ($link): ?><div class="alert info">Local server: open this link to continue.<br><a href="<?= e($link) ?>"><?= e($link) ?></a></div><?php endif; ?>
  <div class="auth-links"><a href="login.php">Back to sign in</a></div>
<?php else: ?>
  <p class="lead">Enter your username or email.</p>
  <form method="post">
    <?= csrf_field() ?>
    <div class="field"><label for="login">Username or email</label><input id="login" name="login" required autofocus></div>
    <button class="btn btn-primary" type="submit">Send reset link</button>
  </form>
  <div class="auth-links"><a href="login.php">Back to sign in</a></div>
<?php endif; auth_bottom();
