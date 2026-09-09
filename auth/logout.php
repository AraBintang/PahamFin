<?php
require_once __DIR__ . '/../app/db.php';

if (!empty($_SESSION['user_id'])) {
    PahamFin_clear_remember_cookie((int) $_SESSION['user_id'], $pdo);
}

$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
}

session_destroy();
header('Location: login.php');
exit;
