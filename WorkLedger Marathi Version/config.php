<?php
/* WorkLedger by CodInt Digitech - configuration and shared helpers */
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
    session_start();
}
date_default_timezone_set('Asia/Kolkata');

const APP_NAME = 'WorkLedger';
const COMPANY_NAME = 'CodInt Digitech';

/* ---- Database (change these on cPanel) ---- */
const DB_HOST = 'localhost';
const DB_PORT = '8080';      // XAMPP local MySQL port; use '3306' on cPanel
const DB_USER = 'root';
const DB_PASS = '';
const DB_NAME = 'workledger';

/* ---- Behaviour ---- */
const SESSION_TIMEOUT = 1800;        // auto logout after 30 minutes idle
const MAIL_FROM = 'no-reply@yourdomain.com'; // sender for password reset mails (live server)
const MANAGER_CAN_APPROVE = true;    // false = only admin can approve work entries
const DEFAULT_LANG = 'mr';          // en, hi or mr: language shown first
const LANG_LOCK = 'mr';                // set to 'mr' for a Marathi-only edition (hides the language switch)
const FIX_KEY = '';                  // optional secret for fix_login.php on a live server
const UNITS = ['Per piece', 'Per hour', 'Per day', 'Per job', 'Per kg', 'Per metre'];
const PAY_MODES = ['Cash', 'UPI', 'Bank transfer', 'Cheque'];

require_once __DIR__ . '/includes/i18n.php';
/* ---- Database helpers ---- */
function db(): PDO {
    static $pdo = null;
    if (!$pdo) {
        try {
            $pdo = new PDO('mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
            $pdo->exec("SET time_zone = '+05:30'");
        } catch (PDOException $ex) {
            http_response_code(500);
            exit('Database connection failed. Check the DB settings in config.php and import database.sql.');
        }
    }
    return $pdo;
}
function q(string $sql, array $p = []): PDOStatement { $s = db()->prepare($sql); $s->execute($p); return $s; }
function one(string $sql, array $p = []) { return q($sql, $p)->fetch(); }
function all(string $sql, array $p = []): array { return q($sql, $p)->fetchAll(); }
function val(string $sql, array $p = []) { $r = q($sql, $p)->fetchColumn(); return $r === false || $r === null ? 0 : $r; }

/* ---- Output helpers ---- */
function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function inr($n): string {
    $n = (float)$n; $neg = $n < 0; $n = abs($n);
    [$int, $dec] = explode('.', number_format($n, 2, '.', ''));
    if (strlen($int) > 3) {
        $last3 = substr($int, -3);
        $rest = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', substr($int, 0, -3));
        $int = $rest . ',' . $last3;
    }
    return ($neg ? '-' : '') . '₹' . $int . '.' . $dec;
}
function qty($n): string { return rtrim(rtrim(number_format((float)$n, 2, '.', ''), '0'), '.'); }
function dmy($d): string { return $d ? date('d M Y', strtotime($d)) : '—'; }
function redirect(string $url) { header('Location: ' . $url); exit; }
function flash(string $type, string $msg): void { $_SESSION['flash'][] = [$type, $msg]; }
function badge(string $text, string $kind = 'muted'): string { return '<span class="badge ' . e($kind) . '">' . e($text) . '</span>'; }
function valid_date($d): bool { $x = DateTime::createFromFormat('Y-m-d', (string)$d); return $x && $x->format('Y-m-d') === $d; }

/* ---- CSRF ---- */
function csrf_token(): string { if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32)); return $_SESSION['csrf']; }
function csrf_field(): string { return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">'; }
function csrf_verify(): void {
    if (!hash_equals($_SESSION['csrf'] ?? '', (string)($_POST['csrf'] ?? ''))) {
        http_response_code(419);
        exit('This page expired. Go back, refresh it and try again.');
    }
}

/* ---- Authentication ---- */
function current_user() {
    static $u = false;
    if ($u === false) {
        $u = null;
        if (!empty($_SESSION['uid'])) {
            $r = one('SELECT id, name, username, email, role, status FROM users WHERE id = ?', [$_SESSION['uid']]);
            if ($r && $r['status'] === 'active') $u = $r;
        }
    }
    return $u;
}
function require_login(): void {
    $u = current_user();
    $expired = isset($_SESSION['last']) && (time() - $_SESSION['last'] > SESSION_TIMEOUT);
    if (!$u || $expired) {
        $_SESSION = [];
        flash('warn', 'Please sign in to continue.');
        redirect('login.php');
    }
    $_SESSION['last'] = time();
}
function is_admin(): bool { $u = current_user(); return $u && $u['role'] === 'admin'; }
function require_admin(): void {
    require_login();
    if (!is_admin()) { flash('bad', 'Only the administrator can open that page.'); redirect('index.php'); }
}
function can_approve(): bool { return is_admin() || MANAGER_CAN_APPROVE; }
function password_ok(string $p): bool { return strlen($p) >= 8 && preg_match('/[A-Za-z]/', $p) && preg_match('/\d/', $p); }
function base_url(): string {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['SERVER_PORT'] ?? '') == 443;
    return ($https ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? 'localhost') . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
}
function is_local(): bool { return in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true); }

/* ---- Business helpers ---- */
function report_list(): array {
    return [
        'work_staff' => 'Work done by staff',
        'work_task'  => 'Work done by task',
        'work_detail'=> 'Work register',
        'wages'      => 'Wages summary',
        'payments'   => 'Payments register',
        'dues'       => 'Outstanding dues',
        'ledger'     => 'Staff ledger',
        'staff'      => 'Staff list',
        'tasks'      => 'Jobs and rates',
    ];
}
/* Per-staff totals. earned/paid/pending are for the period; balance is all-time (approved work minus payments). */
function wage_rows(string $from, string $to, int $sid = 0): array {
    $sql = "SELECT s.id, s.staff_code, s.name, s.designation, s.status,
      (SELECT COUNT(*) FROM work_entries w WHERE w.staff_id=s.id AND w.status='Approved' AND w.work_date BETWEEN ? AND ?) AS entries,
      (SELECT COALESCE(SUM(w.amount),0) FROM work_entries w WHERE w.staff_id=s.id AND w.status='Approved' AND w.work_date BETWEEN ? AND ?) AS earned,
      (SELECT COALESCE(SUM(w.amount),0) FROM work_entries w WHERE w.staff_id=s.id AND w.status='Pending' AND w.work_date BETWEEN ? AND ?) AS pending,
      (SELECT COALESCE(SUM(p.amount),0) FROM payments p WHERE p.staff_id=s.id AND p.pay_date BETWEEN ? AND ?) AS paid,
      (SELECT COALESCE(SUM(w.amount),0) FROM work_entries w WHERE w.staff_id=s.id AND w.status='Approved')
        - (SELECT COALESCE(SUM(p.amount),0) FROM payments p WHERE p.staff_id=s.id) AS balance
      FROM staff s";
    $p = [$from, $to, $from, $to, $from, $to, $from, $to];
    if ($sid) { $sql .= ' WHERE s.id = ?'; $p[] = $sid; }
    return all($sql . ' ORDER BY s.name', $p);
}
function staff_balance(int $sid): float {
    return (float)val("SELECT (SELECT COALESCE(SUM(amount),0) FROM work_entries WHERE staff_id=? AND status='Approved') - (SELECT COALESCE(SUM(amount),0) FROM payments WHERE staff_id=?)", [$sid, $sid]);
}
function staff_select(string $name, int $sel = 0, bool $activeOnly = true, bool $withAll = false): string {
    $rows = all('SELECT id, staff_code, name, status FROM staff ' . ($activeOnly ? "WHERE status='Active' OR id=" . (int)$sel : '') . ' ORDER BY name');
    $h = '<select name="' . e($name) . '" id="f_' . e($name) . '">' . ($withAll ? '<option value="0">All staff</option>' : '<option value="">Select staff</option>');
    foreach ($rows as $r) $h .= '<option value="' . $r['id'] . '"' . ((int)$r['id'] === $sel ? ' selected' : '') . '>' . e($r['name'] . ' (' . $r['staff_code'] . ')') . '</option>';
    return $h . '</select>';
}