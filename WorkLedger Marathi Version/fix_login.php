<?php
/* WorkLedger login helper. Open it once in the browser, press the button, then DELETE this file.
   Works on localhost. On a live server set FIX_KEY in config.php and open fix_login.php?key=YOURKEY */
require_once 'config.php';
while (ob_get_level()) ob_end_clean();   // keep this page in plain English
$allowed = is_local() || (FIX_KEY !== '' && hash_equals(FIX_KEY, (string)($_GET['key'] ?? '')));
if (!$allowed) { http_response_code(403); exit('Open this page on the local server (http://localhost/...), or set FIX_KEY in config.php for a live server.'); }
$out = []; $ok = true;
$out[] = ['PHP version', PHP_VERSION, version_compare(PHP_VERSION, '7.4.0', '>=')];
try {
    $pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $out[] = ['Database connection', 'Connected to ' . DB_NAME, true];
    try {
        $n = (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
        $out[] = ['Users table', $n . ' user(s) found', $n > 0];
        foreach ($pdo->query('SELECT username, role, status, password_hash FROM users')->fetchAll(PDO::FETCH_ASSOC) as $u) {
            $test = $u['role'] === 'admin' ? 'Admin@123' : 'Manager@123';
            $out[] = ['User "' . $u['username'] . '"', $u['role'] . ', ' . $u['status'] . ', default password ' . (password_verify($test, $u['password_hash']) ? 'works' : 'does not match'), $u['status'] === 'active'];
        }
    } catch (PDOException $e) { $out[] = ['Users table', 'Missing. Import database.sql first. (' . $e->getMessage() . ')', false]; }
} catch (PDOException $e) {
    $out[] = ['Database connection', 'FAILED: ' . $e->getMessage() . ' — check DB_HOST, DB_USER, DB_PASS, DB_NAME in config.php', false];
}
$msg = '';
if (isset($pdo) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $set = ['admin' => ['Administrator', 'Admin@123', 'admin'], 'manager' => ['Manager', 'Manager@123', 'manager']];
        foreach ($set as $user => [$name, $pw, $role]) {
            $h = password_hash($pw, PASSWORD_DEFAULT);
            $st = $pdo->prepare('UPDATE users SET password_hash=?, status=\'active\', failed_attempts=0, locked_until=NULL WHERE username=?');
            $st->execute([$h, $user]);
            if (!$st->rowCount() && !$pdo->query("SELECT COUNT(*) FROM users WHERE username='$user'")->fetchColumn())
                $pdo->prepare('INSERT INTO users (name, username, password_hash, role) VALUES (?,?,?,?)')->execute([$name, $user, $h, $role]);
        }
        $msg = 'Done. Sign in with admin / Admin@123 or manager / Manager@123, change the passwords, then delete fix_login.php.';
    } catch (PDOException $e) { $msg = 'Could not update: ' . $e->getMessage(); }
}
?><!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Login check</title>
<style>body{font:15px/1.5 Segoe UI,Arial,sans-serif;background:#F4F6F9;color:#1E2A38;margin:0;padding:24px}main{max-width:720px;margin:auto;background:#fff;border:1px solid #E1E6ED;border-radius:10px;padding:24px}
td,th{padding:8px 10px;border-bottom:1px solid #EEF1F5;text-align:left;vertical-align:top}.ok{color:#2E7D5B;font-weight:600}.no{color:#B23A3A;font-weight:600}button{background:#2C5F7F;color:#fff;border:0;border-radius:8px;padding:10px 18px;font:inherit;font-weight:600;cursor:pointer}.m{background:#E6F3EC;padding:10px 14px;border-radius:8px;margin:12px 0}</style></head><body><main>
<h1 style="margin-top:0">WorkLedger login check</h1>
<table style="width:100%;border-collapse:collapse"><?php foreach ($out as $r): ?><tr><th><?= htmlspecialchars($r[0]) ?></th><td><?= htmlspecialchars($r[1]) ?></td><td class="<?= $r[2] ? 'ok' : 'no' ?>"><?= $r[2] ? 'OK' : 'Problem' ?></td></tr><?php endforeach; ?></table>
<?php if ($msg): ?><div class="m"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
<?php if (isset($pdo)): ?><form method="post" style="margin-top:18px"><p>This sets the two default passwords again (admin / Admin@123, manager / Manager@123), unlocks both accounts and makes them active.</p><button>Reset default logins</button></form><?php endif; ?>
<p style="color:#637083">Delete this file after use.</p></main></body></html>
