<?php
require_once __DIR__ . '/../app/includes/config.php';
require_once __DIR__ . '/../app/includes/auth.php';
require_once __DIR__ . '/../app/db.php';

/**
 * Login dengan Google (OAuth 2.0)
 * ------------------------------------------------------------------
 * Endpoint tunggal dengan dua mode:
 *   ?action=login    -> mengarahkan (redirect) pengguna ke Google.
 *   (tanpa action)   -> callback: menerima ?code lalu mendaftarkan / masuk.
 *
 * Cara pakai:
 *   1. Isi PahamFin_GOOGLE_CLIENT_ID & PahamFin_GOOGLE_CLIENT_SECRET di
 *      includes/config.php (atau variabel lingkungan).
 *   2. Daftarkan redirect URI PahamFin_GOOGLE_REDIRECT_URI di Google
 *      Cloud Console (harus persis sama: .../google-auth.php).
 */

$clientId     = PahamFin_GOOGLE_CLIENT_ID;
$clientSecret = PahamFin_GOOGLE_CLIENT_SECRET;
$redirectUri  = PahamFin_GOOGLE_REDIRECT_URI;

if ($clientId === '' || $clientSecret === '') {
    header('Location: login.php?error=google_not_configured');
    exit;
}

// Sudah login -> langsung ke dashboard (atau admin dashboard).
if (!empty($_SESSION['user_id'])) {
    $role = ($_SESSION['user_role'] ?? 'user') === 'admin' ? 'admin' : 'user';
    header('Location: ../' . ($role === 'admin' ? 'pages/admin/index.php' : 'pages/index.php'));
    exit;
}

$action = $_GET['action'] ?? '';

// ---- Mode 1: mulai login, arahkan ke Google ----
if ($action === 'login') {
    $params = [
        'client_id'     => $clientId,
        'redirect_uri'  => $redirectUri,
        'response_type' => 'code',
        'scope'         => 'openid email profile',
        'access_type'   => 'online',
        'prompt'        => 'select_account',
    ];
    $url = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
    header('Location: ' . $url);
    exit;
}

// ---- Mode 2: callback dari Google ----
$error = $_GET['error'] ?? '';
if ($error !== '') {
    header('Location: login.php?error=google_denied');
    exit;
}

$code = $_GET['code'] ?? '';
if ($code === '') {
    header('Location: login.php?error=google_denied');
    exit;
}

// Tukar kode -> token akses.
$tokenData = [
    'code'          => $code,
    'client_id'     => $clientId,
    'client_secret' => $clientSecret,
    'redirect_uri'  => $redirectUri,
    'grant_type'    => 'authorization_code',
];

$tok = http_context_json('https://oauth2.googleapis.com/token', $tokenData);
if ($tok === null) {
    header('Location: login.php?error=google_denied');
    exit;
}

$accessToken = $tok['access_token'] ?? '';
if ($accessToken === '') {
    header('Location: login.php?error=google_denied');
    exit;
}

// Ambil info profil (email, nama, dll) dari Google.
$profile = http_get_with_token('https://openidconnect.googleapis.com/v1/userinfo', $accessToken);
if ($profile === null || empty($profile['email']) || empty($profile['sub'])) {
    header('Location: login.php?error=google_denied');
    exit;
}

$googleId = (string) $profile['sub'];
$email    = strtolower(trim((string) $profile['email']));
$name     = trim((string) ($profile['name'] ?? ''));
$name     = $name !== '' ? $name : explode('@', $email)[0];

$pdo = db_pdo();
PahamFin_google_login($pdo, $googleId, $email, $name, $redirectUri);

/**
 * Membuat koneksi PDO (mysql bila tersedia, beralih ke sqlite bila gagal).
 */
function db_pdo(): PDO
{
    global $pdo;
    if (isset($pdo) && $pdo instanceof PDO) {
        return $pdo;
    }
    throw new RuntimeException('Koneksi database tidak tersedia');
}

/**
 * Mencocokkan / membuat user dari akun Google lalu menetapkan sesi.
 */
function PahamFin_google_login(PDO $pdo, string $googleId, string $email, string $name, string $redirectUri): void
{
    // 1) Cari user berdasarkan google_id.
    $stmt = $pdo->prepare("SELECT id FROM users WHERE google_id = ? LIMIT 1");
    $stmt->execute([$googleId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // 2) Kalau belum ada, coba cocokkan lewat email (akun yang sudah ada).
    if (!$user) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user) {
            // Tautkan google_id ke akun yang sudah ada.
            $upd = $pdo->prepare("UPDATE users SET google_id = ? WHERE id = ?");
            $upd->execute([$googleId, $user['id']]);
        }
    }

    // 3) Belum ada sama sekali -> buat akun baru (tanpa password).
    if (!$user) {
        $insert = $pdo->prepare(
            "INSERT INTO users (name, email, password, google_id) VALUES (?, ?, ?, ?)"
        );
        $insert->execute([$name, $email, password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT), $googleId]);
        $newId = (int) $pdo->lastInsertId();

        // Seed kategori default.
        PahamFin_seed_default_categories($pdo, $newId);

        $user = ['id' => $newId];
    }

    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['id'];
    $role = PahamFin_user_role(['email' => $email, 'role' => $user['role'] ?? '']);
    if ($role === 'admin') {
        $upd = $pdo->prepare("UPDATE users SET role = 'admin' WHERE id = ? AND (role IS NULL OR LOWER(role) <> 'admin')");
        $upd->execute([(int) $user['id']]);
    }
    $_SESSION['user_role'] = $role;
    header('Location: ../' . ($role === 'admin' ? 'pages/admin/index.php' : 'pages/index.php'));
    exit;
}

/**
 * Helper: POST JSON ke sebuah URL, return array respon atau null.
 */
function http_context_json(string $url, array $data): ?array
{
    $ctx = stream_context_create([
        'http' => [
            'method'  => 'POST',
            'header'  => "Content-Type: application/x-www-form-urlencoded\r\n",
            'content' => http_build_query($data),
            'timeout' => 20,
        ],
    ]);
    $raw = @file_get_contents($url, false, $ctx);
    if ($raw === false) {
        return null;
    }
    $json = json_decode($raw, true);
    return is_array($json) ? $json : null;
}

/**
 * Helper: GET ke sebuah URL dengan bearer token, return array respon atau null.
 */
function http_get_with_token(string $url, string $accessToken): ?array
{
    $ctx = stream_context_create([
        'http' => [
            'method'  => 'GET',
            'header'  => "Authorization: Bearer " . $accessToken . "\r\n",
            'timeout' => 20,
        ],
    ]);
    $raw = @file_get_contents($url, false, $ctx);
    if ($raw === false) {
        return null;
    }
    $json = json_decode($raw, true);
    return is_array($json) ? $json : null;
}
