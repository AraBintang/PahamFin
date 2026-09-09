<?php
/**
 * Endpoint untuk auto-register user dari Telegram.
 * Dipanggil oleh bot Telegram jika user belum terdaftar.
 *
 * POST /api/tg_auto_register.php
 * Body: { "telegram_id": "123456", "name": "Budi" }
 */
header('Content-Type: application/json; charset=utf-8');
require __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/includes/config.php';

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$telegramId = trim((string) ($input['telegram_id'] ?? ''));
$name = trim((string) ($input['name'] ?? 'Pengguna Baru'));

if ($telegramId === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'telegram_id wajib diisi.']);
    exit;
}

// Cek apakah sudah ada (untuk amannya)
$stmt = $pdo->prepare("SELECT id, email FROM users WHERE telegram_id = ? LIMIT 1");
$stmt->execute([$telegramId]);
$existing = $stmt->fetch(PDO::FETCH_ASSOC);

if ($existing) {
    echo json_encode([
        'success' => true,
        'email' => $existing['email'] ?? 'Belum diset',
        'message' => 'Sudah terdaftar'
    ]);
    exit;
}

// Generate random email dan password
$randomPass = substr(str_shuffle('abcdefghjkmnpqrstuvwxyz23456789'), 0, 6);
$generatedEmail = 'tg' . $telegramId . '@PahamFin.com';
$hashedPass = password_hash($randomPass, PASSWORD_DEFAULT);
$phone = '628' . preg_replace('/[^0-9]/', '', $telegramId);

// Simpan ke database
$insert = $pdo->prepare("INSERT INTO users (name, phone_number, telegram_id, email, password) VALUES (?, ?, ?, ?, ?)");
$insert->execute([$name, $phone, $telegramId, $generatedEmail, $hashedPass]);

$userId = (int) $pdo->lastInsertId();

// Buat 5 kategori default otomatis
$defaultCategories = [
    ['Makanan & Minuman', 'makan,minum,kopi,kfc,mcd,warteg,sate,bakso,nasi,beli makan', 'PENGELUARAN'],
    ['Transportasi',      'bensin,gojek,grab,toll,tol,parkir,ongkir,ojek,bus',           'PENGELUARAN'],
    ['Belanja',           'belanja,shopee,tokopedia,baju,sepatu,grocery',                  'PENGELUARAN'],
    ['Tagihan',           'listrik,air,wifi,internet,pulsa,token',                         'PENGELUARAN'],
    ['Gaji & Pemasukan',  'gaji,salary,transfer masuk,bonus,freelance',                   'PEMASUKAN'],
];
$stmtCat = $pdo->prepare("INSERT INTO categories (user_id, name, keyword, type) VALUES (?, ?, ?, ?)");
foreach ($defaultCategories as $cat) {
    $stmtCat->execute([$userId, $cat[0], $cat[1], $cat[2]]);
}

echo json_encode([
    'success'  => true,
    'email'    => $generatedEmail,
    'password' => $randomPass,
    'login_url'=> PahamFin_BASE_URL . '/auth/login.php'
]);
