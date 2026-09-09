<?php
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/includes/config.php';
require_once __DIR__ . '/../app/includes/auth.php';
require_login();

$user_id = current_user_id();
header('Content-Type: application/json');

$q = trim($_GET['q'] ?? '');
if (strlen($q) < 2) {
    echo json_encode(['results' => []]);
    exit;
}

$like = '%' . $q . '%';
$results = [];

// 1. Transactions
$stmt = $pdo->prepare("SELECT t.id, t.description, t.amount, t.type, t.transaction_date, c.name as cat
    FROM transactions t JOIN categories c ON c.id = t.category_id
    WHERE t.user_id = ? AND (t.description LIKE ? OR c.name LIKE ?)
    ORDER BY t.transaction_date DESC LIMIT 5");
$stmt->execute([$user_id, $like, $like]);
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $results[] = [
        'id'       => $r['id'],
        'type'     => 'transaction',
        'title'    => $r['description'] ?: $r['cat'],
        'subtitle' => 'Rp ' . number_format($r['amount'], 0, ',', '.') . ' · ' . $r['transaction_date'],
        'icon'     => $r['type'] === 'PEMASUKAN' ? 'ph-arrow-circle-up' : 'ph-arrow-circle-down',
        'url'      => PahamFin_URL_PAGES . '/transactions.php',
    ];
}

// 2. Reminders
$stmt = $pdo->prepare("SELECT id, title, remind_date FROM reminders WHERE user_id = ? AND done = 0 AND title LIKE ? LIMIT 3");
$stmt->execute([$user_id, $like]);
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $results[] = [
        'id'       => $r['id'],
        'type'     => 'reminder',
        'title'    => $r['title'],
        'subtitle' => 'Pengingat · ' . $r['remind_date'],
        'icon'     => 'ph-bell-ringing',
        'url'      => PahamFin_URL_PAGES . '/reminders.php',
    ];
}

// 3. Savings Goals
$stmt = $pdo->prepare("SELECT id, name, saved_amount FROM savings_goals WHERE user_id = ? AND name LIKE ? LIMIT 3");
$stmt->execute([$user_id, $like]);
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $results[] = [
        'id'       => $r['id'],
        'type'     => 'saving',
        'title'    => $r['name'],
        'subtitle' => 'Tabungan · Rp ' . number_format($r['saved_amount'], 0, ',', '.'),
        'icon'     => 'ph-piggy-bank',
        'url'      => PahamFin_URL_PAGES . '/savings.php',
    ];
}

// 4. Debts
$stmt = $pdo->prepare("SELECT id, person_name, amount, type FROM debts WHERE user_id = ? AND status = 'UNPAID' AND person_name LIKE ? LIMIT 3");
$stmt->execute([$user_id, $like]);
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $results[] = [
        'id'       => $r['id'],
        'type'     => 'debt',
        'title'    => $r['person_name'],
        'subtitle' => ($r['type'] === 'OWE' ? 'Hutang ke ' : 'Piutang dari ') . $r['person_name'] . ' · Rp ' . number_format($r['amount'], 0, ',', '.'),
        'icon'     => 'ph-handshake',
        'url'      => PahamFin_URL_PAGES . '/debts.php',
    ];
}

echo json_encode(['results' => $results]);
