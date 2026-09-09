<?php
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/includes/auth.php';
require_login();

$user_id = (int) current_user_id();

if (isset($_GET['mark']) && $_GET['mark'] === 'read') {
    try {
        PahamFin_mark_notifications_read($pdo, $user_id);
    } catch (Throwable $e) {
        // abaikan
    }
    $ref = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : PahamFin_URL_PAGES . '/index.php';
    header('Location: ' . $ref);
    exit;
}

PahamFin_mark_notifications_read($pdo, $user_id);
header('Location: ' . PahamFin_URL_PAGES . '/index.php');
exit;
