<?php
require_once __DIR__ . '/config.php';

function require_login(): void
{
    if (empty($_SESSION['user_id'])) {
        header('Location: ' . PahamFin_URL_AUTH . '/login.php');
        exit;
    }
}

function current_user_id(): ?int
{
    return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
}

function current_user(PDO $pdo): ?array
{
    $id = current_user_id();
    if ($id === null) {
        return null;
    }
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    return $user !== false ? $user : null;
}

function current_user_is_admin(PDO $pdo): bool
{
    $user = current_user($pdo);
    return $user !== null && PahamFin_is_admin($user);
}

function require_admin(PDO $pdo): void
{
    require_login();
    if (!current_user_is_admin($pdo)) {
        header('Location: ' . PahamFin_URL_PAGES . '/index.php');
        exit;
    }
}
