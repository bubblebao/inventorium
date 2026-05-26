<?php
require_once __DIR__ . '/config/auth.php';

if (is_logged_in()) {
    audit_log('LOGOUT');
}

$_SESSION = [];
session_destroy();
setcookie(session_name(), '', time() - 3600, '/');

header('Location: /login.php');
exit;
