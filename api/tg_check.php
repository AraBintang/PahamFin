<?php
/**
 * Endpoint untuk verifikasi apakah Telegram ID sudah terdaftar.
 * Dipanggil oleh bot Telegram sebelum memproses pesan.
 *
 * GET  /api/tg_check.php?telegram_id=123456
 * Response: { "registered": true/false, "name": "...", "login_url": "..." }
 */
header('Content-Type: application/json; charset=utf-8');
require __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/includes/config.php';

$telegramId = trim((string) ($_GET['telegram_id'] ?? ''));

if ($telegramId === '') {
    http_response_code(400);
    echo json_encode(['error' => 'telegram_id wajib diisi.']);
    exit;
}

$stmt = $pdo->prepare("SELECT id, name FROM users WHERE telegram_id = ? LIMIT 1");
$stmt->execute([$telegramId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if ($user) {
    echo json_encode(['registered' => true, 'name' => $user['name']]);
} else {
    // Buat token deep-link unik untuk menghubungkan akun
    $token = bin2hex(random_bytes(16));
    
    // Simpan token sementara ke tabel tg_link_tokens (auto-expire 10 menit)
    // Buat tabel jika belum ada, kompatibel SQLite & MySQL
    try {
        if ($databaseDriver === 'sqlite') {
            $pdo->exec("CREATE TABLE IF NOT EXISTS tg_link_tokens (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                telegram_id VARCHAR(50) NOT NULL,
                token VARCHAR(64) NOT NULL UNIQUE,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )");
        } else {
            $pdo->exec("CREATE TABLE IF NOT EXISTS tg_link_tokens (
                id INT AUTO_INCREMENT PRIMARY KEY,
                telegram_id VARCHAR(50) NOT NULL,
                token VARCHAR(64) NOT NULL UNIQUE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )");
        }
    } catch (Throwable $e) {}
    
    // Hapus token lama milik telegram_id ini
    $pdo->prepare("DELETE FROM tg_link_tokens WHERE telegram_id = ?")->execute([$telegramId]);
    
    // Simpan token baru
    $pdo->prepare("INSERT INTO tg_link_tokens (telegram_id, token) VALUES (?, ?)")
        ->execute([$telegramId, $token]);

    $loginUrl = PahamFin_BASE_URL . '/auth/tg_link.php?token=' . $token;

    echo json_encode([
        'registered' => false,
        'login_url'  => $loginUrl,
        'token'      => $token,
    ]);
}
