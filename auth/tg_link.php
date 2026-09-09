<?php
/**
 * Halaman penghubung akun Telegram ke akun PahamFin.
 *
 * Alur:
 * 1. Bot Telegram generate token dan kirim link: /auth/tg_link.php?token=xxx
 * 2. User klik link → halaman ini muncul
 * 3. Jika user belum login → tampilkan form login
 * 4. Setelah login (atau sudah login) → Telegram ID dihubungkan ke akun
 * 5. Tampilkan pesan sukses + instruksi kembali ke bot
 */
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/includes/config.php';

$token = trim((string) ($_GET['token'] ?? ''));
$error = '';
$success = false;
$botName = PahamFin_TELEGRAM_BOT_USERNAME;

// ── Validasi token ──────────────────────────────────────────────────────────
if ($token === '') {
    $error = 'Link tidak valid. Minta link baru dari bot Telegram.';
} else {
    // Cek token (max 10 menit)
    try {
        $stmt = $pdo->prepare("SELECT * FROM tg_link_tokens WHERE token = ? AND created_at > DATE_SUB(NOW(), INTERVAL 10 MINUTE) LIMIT 1");
        $stmt->execute([$token]);
        $linkToken = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        $linkToken = null;
    }

    if (!$linkToken) {
        $error = 'Link sudah kedaluwarsa atau tidak valid. Kirim pesan ke bot Telegram untuk mendapatkan link baru.';
    }
}

$telegramId = $linkToken['telegram_id'] ?? '';

// ── Handle POST (proses login + hubungkan) ──────────────────────────────────
if (!$error && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!PahamFin_csrf_verify()) {
        $error = 'Sesi tidak valid. Muat ulang halaman.';
    } else {
        $email    = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        if ($email === '' || $password === '') {
            $error = 'Email dan password wajib diisi.';
        } else {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['password'])) {
                // Cek apakah telegram_id sudah dipakai akun lain
                $stmtCheck = $pdo->prepare("SELECT id FROM users WHERE telegram_id = ? AND id != ? LIMIT 1");
                $stmtCheck->execute([$telegramId, $user['id']]);
                if ($stmtCheck->fetch()) {
                    $error = 'Akun Telegram ini sudah terhubung ke akun PahamFin lain.';
                } else {
                    // Hubungkan Telegram ID ke akun
                    $pdo->prepare("UPDATE users SET telegram_id = ? WHERE id = ?")
                        ->execute([$telegramId, $user['id']]);

                    // Hapus token yang sudah dipakai
                    $pdo->prepare("DELETE FROM tg_link_tokens WHERE token = ?")
                        ->execute([$token]);

                    // Set sesi login juga
                    session_regenerate_id(true);
                    $_SESSION['user_id']   = (int) $user['id'];
                    $_SESSION['user_role'] = PahamFin_user_role((array) $user);

                    $success = true;
                    $userName = $user['name'];
                }
            } else {
                $error = 'Email atau password salah.';
            }
        }
    }
}

// ── Jika sudah login session dan belum sukses ──
if (!$error && !$success && !empty($_SESSION['user_id'])) {
    $userId = (int) $_SESSION['user_id'];
    
    // Cek apakah telegram_id sudah dipakai akun lain
    $stmtCheck = $pdo->prepare("SELECT id FROM users WHERE telegram_id = ? AND id != ? LIMIT 1");
    $stmtCheck->execute([$telegramId, $userId]);
    if ($stmtCheck->fetch()) {
        $error = 'Akun Telegram ini sudah terhubung ke akun PahamFin lain.';
    } else {
        $pdo->prepare("UPDATE users SET telegram_id = ? WHERE id = ?")
            ->execute([$telegramId, $userId]);
        $pdo->prepare("DELETE FROM tg_link_tokens WHERE token = ?")
            ->execute([$token]);

        $stmt = $pdo->prepare("SELECT name FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$userId]);
        $userName = $stmt->fetchColumn() ?: 'Pengguna';
        $success = true;
    }
}
?>
<!DOCTYPE html>
<html lang="id" x-data="appRoot" :class="{ 'dark': darkMode }">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hubungkan Telegram - PahamFin</title>
    <link rel="icon" type="image/png" href="<?= PahamFin_URL_LOGO ?>">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: { extend: { colors: { primary: '#0A58A5' }, fontFamily: { display: ['Figtree','sans-serif'] } } }
        }
    </script>
    <?php require_once __DIR__ . '/../app/includes/theme.php'; ?>
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('appRoot', () => ({
                darkMode: localStorage.getItem('PahamFin_dark') === '1',
                toggleDark() { this.darkMode = !this.darkMode; localStorage.setItem('PahamFin_dark', this.darkMode ? '1' : '0'); }
            }));
        });
    </script>
</head>
<body class="bg-canvas text-dark dark:text-slate-100 antialiased min-h-screen flex items-center justify-center p-4">
<div class="w-full max-w-sm">
    <!-- Logo -->
    <div class="text-center mb-6">
        <img src="<?= PahamFin_URL_LOGO ?>" class="w-14 h-14 mx-auto mb-3" alt="PahamFin">
        <h1 class="text-2xl font-bold font-display text-dark dark:text-white">Paham<span class="text-primary dark:text-blue-400">Fin</span></h1>
        <p class="text-sm text-gray-500 dark:text-slate-400 mt-1">Hubungkan akun Telegram ke PahamFin</p>
    </div>

    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-lg p-6">

        <?php if ($success): ?>
        <!-- ── SUKSES ── -->
        <div class="text-center">
            <div class="w-16 h-16 bg-green-100 dark:bg-green-900/30 rounded-full flex items-center justify-center mx-auto mb-4">
                <i class="ph ph-check-circle text-4xl text-green-500"></i>
            </div>
            <h2 class="text-lg font-bold text-gray-900 dark:text-white mb-2">Berhasil Terhubung! 🎉</h2>
            <p class="text-sm text-gray-600 dark:text-slate-300">
                Halo <strong><?= htmlspecialchars($userName ?? '') ?></strong>, akun Telegram kamu sudah berhasil dihubungkan ke PahamFin!
            </p>
            <div class="mt-5 p-4 bg-sky-50 dark:bg-sky-900/20 rounded-xl text-sm text-sky-800 dark:text-sky-300 text-left">
                <p class="font-semibold mb-2">📱 Langkah selanjutnya:</p>
                <p>Kembali ke bot Telegram <strong>@<?= htmlspecialchars($botName) ?></strong> dan kirim pesan seperti:</p>
                <code class="block mt-2 bg-white dark:bg-slate-700 px-3 py-2 rounded-lg text-xs">makan 50000</code>
            </div>
            <a href="https://t.me/<?= htmlspecialchars($botName) ?>" target="_blank"
               class="mt-5 flex items-center justify-center gap-2 h-11 bg-sky-500 hover:bg-sky-600 text-white font-semibold rounded-xl transition-colors">
                <i class="ph ph-telegram-logo text-xl"></i> Kembali ke Bot Telegram
            </a>
            <a href="<?= PahamFin_BASE_URL ?>/pages/index.php" class="block mt-3 text-sm text-center text-gray-500 dark:text-slate-400 hover:underline">
                Buka Dashboard →
            </a>
        </div>

        <?php elseif ($error): ?>
        <!-- ── ERROR ── -->
        <div class="text-center">
            <div class="w-16 h-16 bg-red-100 dark:bg-red-900/30 rounded-full flex items-center justify-center mx-auto mb-4">
                <i class="ph ph-warning-circle text-4xl text-red-500"></i>
            </div>
            <h2 class="text-lg font-bold text-gray-900 dark:text-white mb-2">Link Tidak Valid</h2>
            <p class="text-sm text-red-600 dark:text-red-400"><?= htmlspecialchars($error) ?></p>
            <a href="https://t.me/<?= htmlspecialchars($botName) ?>" target="_blank"
               class="mt-5 flex items-center justify-center gap-2 h-11 bg-sky-500 hover:bg-sky-600 text-white font-semibold rounded-xl transition-colors">
                <i class="ph ph-telegram-logo text-xl"></i> Minta Link Baru dari Bot
            </a>
        </div>

        <?php else: ?>
        <!-- ── FORM LOGIN ── -->
        <div class="flex items-center gap-3 mb-5">
            <div class="w-10 h-10 bg-sky-100 dark:bg-sky-900/30 rounded-xl flex items-center justify-center text-sky-500">
                <i class="ph ph-telegram-logo text-xl"></i>
            </div>
            <div>
                <p class="text-sm font-semibold text-gray-900 dark:text-white">Hubungkan Telegram</p>
                <p class="text-xs text-gray-500 dark:text-slate-400">ID: <code class="bg-gray-100 dark:bg-slate-700 px-1.5 py-0.5 rounded text-xs"><?= htmlspecialchars($telegramId) ?></code></p>
            </div>
        </div>

        <?php if (!empty($_SESSION['user_id'])): ?>
        <!-- Sudah login, konfirmasi langsung -->
        <p class="text-sm text-gray-600 dark:text-slate-300 mb-4">
            Kamu sudah login. Klik tombol di bawah untuk menghubungkan akun Telegram kamu.
        </p>
        <form method="POST">
            <?= PahamFin_csrf_field() ?>
            <input type="hidden" name="confirm" value="1">
            <button type="submit" class="w-full h-11 bg-primary hover:bg-blue-700 text-white font-semibold rounded-xl transition-colors">
                Hubungkan ke Akun Ini
            </button>
        </form>
        <a href="<?= PahamFin_URL_AUTH ?>/logout.php" class="block mt-3 text-center text-xs text-gray-400 hover:underline">Gunakan akun lain? Logout dulu</a>

        <?php else: ?>
        <!-- Form login -->
        <p class="text-sm text-gray-600 dark:text-slate-300 mb-4">
            Masuk ke akun PahamFin kamu untuk menghubungkan Telegram ID ini.
        </p>
        <form method="POST" class="space-y-4">
            <?= PahamFin_csrf_field() ?>
            <div>
                <label class="block text-xs font-medium text-gray-700 dark:text-slate-300 mb-1">Email</label>
                <input type="email" name="email" required autocomplete="email"
                    class="w-full border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-gray-900 dark:text-white rounded-xl px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-700 dark:text-slate-300 mb-1">Password</label>
                <input type="password" name="password" required autocomplete="current-password"
                    class="w-full border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-gray-900 dark:text-white rounded-xl px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
            </div>
            <?php if ($error): ?>
            <p class="text-xs text-red-500"><?= htmlspecialchars($error) ?></p>
            <?php endif; ?>
            <button type="submit" class="w-full h-11 bg-primary hover:bg-blue-700 text-white font-semibold rounded-xl transition-colors">
                Masuk & Hubungkan
            </button>
        </form>
        <p class="text-xs text-center text-gray-400 dark:text-slate-500 mt-4">
            Belum punya akun? <a href="<?= PahamFin_URL_AUTH ?>/register.php" class="text-primary dark:text-blue-400 hover:underline">Daftar gratis</a>
        </p>
        <?php endif; ?>
        <?php endif; ?>

    </div>
</div>
</body>
</html>

