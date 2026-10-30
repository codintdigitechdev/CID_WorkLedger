<?php
require_once __DIR__ . '/../config.php';

function icon(string $n, string $cls = ''): string {
    $p = [
        'home'   => '<path d="M3 10.5 12 3l9 7.5V20a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z"/>',
        'users'  => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c0-3.6 2.9-6 6.5-6s6.5 2.4 6.5 6"/><path d="M16 4.6a3.5 3.5 0 0 1 0 6.8M18 14.3c2.2.7 3.5 2.6 3.5 5.7"/>',
        'tag'    => '<path d="M3 12V4h8l10 10-8 8z"/><circle cx="7.5" cy="8.5" r="1.2"/>',
        'clip'   => '<rect x="5" y="4" width="14" height="17" rx="2"/><path d="M9 4h6v3H9zM9 12h6M9 16h4"/>',
        'wallet' => '<rect x="3" y="6" width="18" height="14" rx="2"/><path d="M3 10h18M16 15h2"/>',
        'rupee'  => '<circle cx="12" cy="12" r="9"/><path d="M9 8h6M9 11h6M9 8c4 0 4 5 0 5l4 4"/>',
        'chart'  => '<path d="M4 20V4M4 20h16M8 16v-5M13 16V8M18 16v-3"/>',
        'user'   => '<circle cx="12" cy="8" r="3.5"/><path d="M5 20c0-3.5 3-6 7-6s7 2.5 7 6"/>',
        'chev'   => '<path d="m6 9 6 6 6-6"/>',
        'menu'   => '<path d="M4 6h16M4 12h16M4 18h16"/>',
        'out'    => '<path d="M9 4H5a1 1 0 0 0-1 1v14a1 1 0 0 0 1 1h4M16 8l4 4-4 4M20 12H9"/>',
    ];
    return '<svg class="ic ' . $cls . '" viewBox="0 0 24 24" aria-hidden="true">' . ($p[$n] ?? '') . '</svg>';
}

function url_is(string $href): bool {
    $parts = parse_url($href);
    if (basename($_SERVER['PHP_SELF']) !== $parts['path']) return false;
    if (!empty($parts['query'])) {
        parse_str($parts['query'], $qq);
        foreach ($qq as $k => $v) if (($_GET[$k] ?? '') !== $v) return false;
    }
    return true;
}

function nav_items(): array {
    $rep = [];
    foreach (report_list() as $k => $t) $rep[] = [$t, 'reports.php?r=' . $k];
    $n = [
        ['label' => 'Dashboard', 'icon' => 'home', 'href' => 'index.php', 'scripts' => ['index.php']],
        ['label' => 'Staff', 'icon' => 'users', 'scripts' => ['staff.php', 'staff_form.php', 'staff_view.php'],
         'children' => [['All staff', 'staff.php'], ['Add staff', 'staff_form.php']]],
        ['label' => 'Jobs & rates', 'icon' => 'tag', 'href' => 'tasks.php', 'scripts' => ['tasks.php']],
        ['label' => 'Work done', 'icon' => 'clip', 'scripts' => ['work.php', 'work_form.php'],
         'children' => [['Work entries', 'work.php'], ['Add work entry', 'work_form.php']]],
        ['label' => 'Wages', 'icon' => 'wallet', 'href' => 'wages.php', 'scripts' => ['wages.php']],
        ['label' => 'Payments', 'icon' => 'rupee', 'scripts' => ['payments.php', 'payment_form.php', 'payment_slip.php'],
         'children' => [['All payments', 'payments.php'], ['Add payment', 'payment_form.php']]],
        ['label' => 'Reports', 'icon' => 'chart', 'scripts' => ['reports.php'], 'children' => $rep],
    ];
    if (is_admin()) $n[] = ['label' => 'Users', 'icon' => 'user', 'href' => 'users.php', 'scripts' => ['users.php']];
    return $n;
}

function layout_top(string $title, string $actions = ''): void {
    $u = current_user();
    $script = basename($_SERVER['PHP_SELF']);
    $initial = strtoupper(substr($u['name'] ?? 'U', 0, 1));
    ?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?> · <?= APP_NAME ?></title>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="overlay" id="overlay"></div>
<aside class="sidebar" id="sidebar">
  <div class="brand"><div class="mark">W</div><div><b><?= APP_NAME ?></b><small>by <?= COMPANY_NAME ?></small></div></div>
  <nav class="nav" aria-label="Main menu">
<?php foreach (nav_items() as $it):
    $active = in_array($script, $it['scripts'], true);
    if (!empty($it['children'])): ?>
    <div class="nav-group<?= $active ? ' open' : '' ?>">
      <button type="button" class="nav-link nav-toggle<?= $active ? ' active' : '' ?>" aria-expanded="<?= $active ? 'true' : 'false' ?>"><?= icon($it['icon']) ?><span><?= e($it['label']) ?></span><?= icon('chev', 'chev') ?></button>
      <div class="nav-sub">
<?php foreach ($it['children'] as $c): ?>
        <a class="nav-sublink<?= url_is($c[1]) ? ' current' : '' ?>" href="<?= e($c[1]) ?>"><?= e($c[0]) ?></a>
<?php endforeach; ?>
      </div>
    </div>
<?php else: ?>
    <a class="nav-link<?= $active ? ' active' : '' ?>" href="<?= e($it['href']) ?>"><?= icon($it['icon']) ?><span><?= e($it['label']) ?></span></a>
<?php endif; endforeach; ?>
  </nav>
  <div class="side-foot">
    <a class="who" href="profile.php"><span class="avatar"><?= e($initial) ?></span><span><b><?= e($u['name'] ?? '') ?></b><small><?= e(ucfirst($u['role'] ?? '')) ?></small></span></a>
    <a class="btn btn-ghost btn-sm" href="logout.php"><?= icon('out') ?>Sign out</a>
  </div>
</aside>
<div class="main">
  <header class="topbar"><button type="button" class="icon-btn" id="menuBtn" aria-label="Open menu"><?= icon('menu') ?></button><b><?= e($title) ?></b></header>
  <div class="content">
<?php if (!empty($_SESSION['flash'])): foreach ($_SESSION['flash'] as $f): ?>
    <div class="alert <?= e($f[0]) ?>" role="status"><?= e($f[1]) ?></div>
<?php endforeach; unset($_SESSION['flash']); endif; ?>
    <div class="page-head"><h1><?= e($title) ?></h1><div class="page-actions"><?= $actions ?></div></div>
<?php
}

function layout_bottom(): void {
    ?>
  </div>
  <footer class="foot"><?= APP_NAME ?> · <?= COMPANY_NAME ?></footer>
</div>
<script src="assets/js/app.js"></script>
</body>
</html>
<?php
}

function auth_top(string $title): void {
    ?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?> · <?= APP_NAME ?></title>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="auth">
<div class="auth-side">
  <div class="brand"><div class="mark">W</div><div><b><?= APP_NAME ?></b><small>by <?= COMPANY_NAME ?></small></div></div>
  <h2>Staff work, wages and payments in one ledger.</h2>
  <p>Record what each person did, pay them what they earned, and see what is still due.</p>
</div>
<div class="auth-form">
  <div class="auth-card">
<?php if (!empty($_SESSION['flash'])): foreach ($_SESSION['flash'] as $f): ?>
    <div class="alert <?= e($f[0]) ?>" role="status"><?= e($f[1]) ?></div>
<?php endforeach; unset($_SESSION['flash']); endif; ?>
<?php
}

function auth_bottom(): void {
    ?>
  </div>
</div>
<script src="assets/js/app.js"></script>
</body>
</html>
<?php
}
