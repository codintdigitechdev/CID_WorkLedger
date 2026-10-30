<?php
require_once 'config.php';
$_SESSION = [];
if (ini_get('session.use_cookies')) { $c = session_get_cookie_params(); setcookie(session_name(), '', time() - 42000, $c['path'], $c['domain'], $c['secure'], $c['httponly']); }
session_destroy();
session_start();
flash('ok', 'You have been signed out.');
redirect('login.php');
