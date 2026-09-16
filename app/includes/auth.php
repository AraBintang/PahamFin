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

function require_subscription(PDO $pdo): void
{
    require_login();
    $userId = current_user_id();
    if (!$userId) return;

    $user = current_user($pdo);
    if (!$user) return;

    // Admin selalu diizinkan masuk dashboard tanpa perlu langganan
    if (PahamFin_is_admin($user)) {
        return;
    }

    $activeSub = PahamFin_get_user_active_subscription($pdo, $userId);
    if (!$activeSub) {
        $currentPage = basename($_SERVER['PHP_SELF']);
        if (!in_array($currentPage, ['payment.php', 'pricing.php', 'logout.php'], true)) {
            header('Location: ' . PahamFin_URL_PAGES . '/payment.php');
            exit;
        }
    }
}

function require_admin(PDO $pdo): void
{
    require_login();
    if (!current_user_is_admin($pdo)) {
        header('Location: ' . PahamFin_URL_PAGES . '/index.php');
        exit;
    }
}
