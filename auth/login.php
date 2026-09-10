<?php
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/includes/config.php';

if (empty($_SESSION['user_id']) === false) {
    $role = ($_SESSION['user_role'] ?? 'user') === 'admin' ? 'admin' : 'user';
    header('Location: ../' . ($role === 'admin' ? 'pages/admin/index.php' : 'pages/index.php'));
    exit;
}

// Simpan tujuan redirect setelah login (wa / tg)
if (!empty($_GET['next'])) {
    $_SESSION['login_next'] = $_GET['next'];
}

$error = '';
if (isset($_GET['error'])) {
    if ($_GET['error'] === 'google_denied') $error = 'Akses Google ditolak atau gagal.';
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!PahamFin_csrf_verify()) $error = 'Sesi tidak valid.';
    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if ($error === '' && ($email === '' || $password === '')) {
        $error = 'Email dan password wajib diisi.';
    } elseif ($error === '') {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            session_regenerate_id(true);
            $role = PahamFin_user_role((array) $user);
            $_SESSION['user_id'] = (int) $user['id'];
            $_SESSION['user_role'] = $role;
            if (empty($_POST['remember']) === false) PahamFin_set_remember_cookie((int) $user['id'], $pdo);

            // Cek apakah ada redirect tujuan
            $next = $_SESSION['login_next'] ?? '';
            unset($_SESSION['login_next']);

            if ($next === 'wa') {
                header('Location: ' . PahamFin_BASE_URL . '/pages/landing.php?open=wa');
                exit;
            } elseif ($next === 'tg') {
                header('Location: ' . PahamFin_BASE_URL . '/pages/landing.php?open=tg');
                exit;
            }

            header('Location: ../' . ($role === 'admin' ? 'pages/admin/index.php' : 'pages/index.php'));
            exit;
        }
        $error = 'Email atau password salah.';
    }
}
?>
<!DOCTYPE html>
<html lang="id" x-data="appRoot" :class="{ 'dark': darkMode }">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Masuk - PahamFin</title>
    <link rel="icon" type="image/png" href="<?= PahamFin_URL_LOGO ?>">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://cdn.jsdelivr.net"><script src="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.1/src/index.js"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: { extend: { colors: { primary: '#0A58A5', ink: '#0f172a' }, fontFamily: { display: ['Figtree', 'sans-serif'] } } }
        }
    </script>
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('appRoot', () => ({
                darkMode: localStorage.getItem('PahamFin_dark') === '1',
                init() {
                    window.addEventListener('storage', (e) => {
                        if (e.key === 'PahamFin_dark') {
                            this.darkMode = e.newValue === '1';
                        }
                    });
                },
                toggleDark() {
                    this.darkMode = !this.darkMode;
                    localStorage.setItem('PahamFin_dark', this.darkMode ? '1' : '0');
                }
            }));
        });
    </script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Figtree:wght@400;600;700;800&display=swap" rel="stylesheet">
    <?php require_once __DIR__ . '/../app/includes/theme.php'; ?>
</head>
<body class="bg-canvas text-gray-900 dark:text-slate-100 antialiased font-sans min-h-screen flex items-center justify-center p-4">

    <!-- Theme Toggle -->
    <?= PahamFin_theme_toggle('floating') ?>

    <div class="glass-card rounded-3xl shadow-2xl p-8 max-w-md w-full relative z-10 overflow-hidden">
        <div class="absolute -right-12 -top-12 w-32 h-32 bg-primary/10 dark:bg-blue-500/20 rounded-full blur-2xl"></div>
        
        <div class="text-center mb-8 relative">
            <a href="../pages/landing.php" class="inline-block mb-3">
                  <div class="w-20 h-20 mx-auto mb-2 flex items-center justify-center overflow-hidden">
                      <img src="<?= PahamFin_URL_LOGO ?>" alt="PahamFin" class="w-full h-full object-contain drop-shadow-md scale-110">
                  </div>
            </a>
            <h2 class="text-2xl font-display font-extrabold text-ink dark:text-white">Selamat Datang Kembali</h2>
            <p class="text-sm text-gray-500 dark:text-slate-400 mt-1">Masuk untuk melanjutkan ke dashboard.</p>
        </div>

        <?php if ($error !== ''): ?>
            <div class="mb-6 p-3 bg-red-50 dark:bg-red-900/30 text-red-600 dark:text-red-400 text-sm rounded-xl border border-red-100 dark:border-red-800/50 flex items-center gap-2">
                <i class="ph ph-warning-circle text-lg shrink-0"></i> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <div class="mb-6"><a href="../pages/index.php" class="text-sm text-gray-500 hover:text-primary flex items-center gap-2"><i class="ph ph-arrow-left"></i> Kembali ke Dashboard</a></div><form method="POST" class="space-y-4">
            <?= PahamFin_csrf_field() ?>
            <div>
                <label class="block text-sm font-semibold text-gray-700 dark:text-slate-300 mb-1.5 ml-1">Email</label>
                <div class="relative">
                    <i class="ph ph-envelope absolute left-4 top-1/2 -translate-y-1/2 text-gray-400"></i>
                    <input type="email" name="email" required placeholder="nama@email.com" class="w-full pl-11 pr-4 py-3 rounded-xl border border-gray-200 dark:border-slate-700 bg-white/50 dark:bg-slate-900/50 focus:ring-2 focus:ring-primary outline-none transition-shadow">
                </div>
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 dark:text-slate-300 mb-1.5 ml-1">Password</label>
                <div class="relative" x-data="{ show: false }">
                    <i class="ph ph-lock absolute left-4 top-1/2 -translate-y-1/2 text-gray-400"></i>
                    <input :type="show ? 'text' : 'password'" name="password" required placeholder="••••••••" class="w-full pl-11 pr-12 py-3 rounded-xl border border-gray-200 dark:border-slate-700 bg-white/50 dark:bg-slate-900/50 focus:ring-2 focus:ring-primary outline-none transition-shadow">
                    <button type="button" @click="show = !show" class="absolute right-4 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 focus:outline-none">
                        <i class="ph text-lg" :class="show ? 'ph-eye-slash' : 'ph-eye'"></i>
                    </button>
                </div>
            </div>
            <div class="flex items-center justify-between text-sm py-1">
                <label class="flex items-center gap-2 cursor-pointer text-gray-600 dark:text-slate-400">
                    <input type="checkbox" name="remember" class="rounded text-primary dark:text-blue-400 focus:ring-primary dark:bg-slate-700 dark:border-slate-600">
                    Ingat saya
                </label>
            </div>
            <button type="submit" class="w-full py-3 px-4 bg-primary hover:bg-blue-700 text-white font-bold rounded-xl shadow-lg shadow-blue-900/20 transition-transform active:scale-95 flex justify-center items-center gap-2">
                Masuk <i class="ph ph-sign-in text-lg"></i>
            </button>
        </form>

        <div class="relative flex items-center justify-center mt-6">
            <span class="absolute inset-x-0 h-px bg-gray-200 dark:bg-slate-700"></span>
            <span class="relative bg-white/50 dark:bg-slate-800/80 px-4 text-xs font-semibold text-gray-500 uppercase tracking-widest">ATAU</span>
        </div>
        <a href="google-auth.php?action=login" class="mt-6 w-full flex items-center justify-center gap-3 py-2.5 px-4 bg-white dark:bg-slate-900 border border-gray-300 dark:border-slate-700 hover:bg-gray-50 dark:hover:bg-slate-800 text-gray-700 dark:text-slate-200 font-semibold rounded-xl transition shadow-sm">
            <img src="https://www.svgrepo.com/show/475656/google-color.svg" alt="Google" class="w-5 h-5">
            Lanjutkan dengan Google
        </a>

        <p class="mt-8 text-center text-sm text-gray-500 dark:text-slate-400">
            Belum punya akun? <a href="register.php" class="text-primary dark:text-blue-400 font-bold hover:underline">Daftar sekarang</a>
        </p>
    </div>
</body>
</html>





