<?php
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/includes/config.php';

if (empty($_SESSION['user_id']) === false) {
    header('Location: ../pages/index.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!PahamFin_csrf_verify()) {
        $error = 'Sesi tidak valid.';
    } else {
    $name  = trim((string) ($_POST['name'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $phone = preg_replace('/[^0-9+]/', '', trim((string) ($_POST['phone'] ?? '')));

        if ($name === '' || $email === '' || $password === '') {
            $error = 'Nama, email, dan password wajib diisi.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Format email tidak valid.';
        } elseif (strlen($password) < 6) {
            $error = 'Password minimal 6 karakter.';
        } else {
            $chk = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $chk->execute([$email]);
            if ($chk->fetch()) {
                $error = 'Email sudah terdaftar. Silakan login.';
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                // Smart OTP: Jangan buat baru jika masih ada sesi valid
                $otp = (isset($_SESSION['pending_reg']['otp']) && $_SESSION['pending_reg']['email'] === $email && $_SESSION['pending_reg']['expires'] > time()) ? $_SESSION['pending_reg']['otp'] : (string) random_int(100000, 999999);
                
                $_SESSION['pending_reg'] = [
                    'name'     => $name,
                    'email'    => $email,
                    'password' => $hash,
                    'phone'    => $phone,
                    'otp'      => $otp,
                    'expires'  => time() + 600
                ];
                
                require_once __DIR__ . '/../app/includes/mailer.php';
                if (PahamFin_send_otp_email($email, $otp)) {
                    header('Location: verify-otp.php');
                    exit;
                } else {
                    $error = 'Gagal mengirim email OTP. Pastikan SMTP di config.php (App Password) sudah benar.';
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id" x-data="appRoot" :class="{ 'dark': darkMode }">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar - PahamFin</title>
    <link rel="icon" type="image/png" href="<?= PahamFin_URL_LOGO ?>">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.13.0/dist/cdn.min.js"></script>
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('appRoot', () => ({
                darkMode: localStorage.getItem('PahamFin_dark') === '1',
                toggleDark() {
                    this.darkMode = !this.darkMode;
                    localStorage.setItem('PahamFin_dark', this.darkMode ? '1' : '0');
                }
            }));
        });
        tailwind.config = {
            darkMode: 'class',
            theme: { extend: { colors: { primary: '#0A58A5', ink: '#0f172a' }, fontFamily: { display: ['Figtree', 'sans-serif'] } } }
        }
    </script>
    <style>
        .bg-canvas { background: #f8fafc; }
        .dark .bg-canvas { background: #0f172a; }
    </style>
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
            <h2 class="text-2xl font-display font-extrabold text-ink dark:text-white">Daftar Akun Baru</h2>
            <p class="text-sm text-gray-500 dark:text-slate-400 mt-1">Lengkapi data untuk membuat akun PahamFin.</p>
        </div>

        <?php if ($error !== ''): ?>
            <div class="mb-6 p-3 bg-red-50 dark:bg-red-900/30 text-red-600 dark:text-red-400 text-sm rounded-xl border border-red-100 dark:border-red-800/50 flex items-center gap-2">
                <i class="ph ph-warning-circle text-lg shrink-0"></i> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <div class="mb-6">
            <a href="../pages/index.php" class="text-sm text-gray-500 hover:text-primary flex items-center gap-2"><i class="ph ph-arrow-left"></i> Kembali ke Dashboard</a>
        </div>

        <form method="POST" class="space-y-4">
            <?= PahamFin_csrf_field() ?>
            <div>
                <label class="block text-sm font-semibold text-gray-700 dark:text-slate-300 mb-1.5 ml-1">Nama Lengkap</label>
                <div class="relative">
                    <i class="ph ph-user absolute left-4 top-1/2 -translate-y-1/2 text-gray-400"></i>
                    <input type="text" name="name" required placeholder="John Doe" class="w-full pl-11 pr-4 py-3 rounded-xl border border-gray-200 dark:border-slate-700 bg-white/50 dark:bg-slate-900/50 focus:ring-2 focus:ring-primary outline-none transition-shadow text-sm">
                </div>
            </div>
            
            <div>
                <label class="block text-sm font-semibold text-gray-700 dark:text-slate-300 mb-1.5 ml-1">Email</label>
                <div class="relative">
                    <i class="ph ph-envelope absolute left-4 top-1/2 -translate-y-1/2 text-gray-400"></i>
                    <input type="email" name="email" required placeholder="nama@email.com" class="w-full pl-11 pr-4 py-3 rounded-xl border border-gray-200 dark:border-slate-700 bg-white/50 dark:bg-slate-900/50 focus:ring-2 focus:ring-primary outline-none transition-shadow text-sm">
                </div>
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 dark:text-slate-300 mb-1.5 ml-1">Nomor HP <span class="text-gray-400 font-normal">(Opsional)</span></label>
                <div class="relative">
                    <i class="ph ph-phone absolute left-4 top-1/2 -translate-y-1/2 text-gray-400"></i>
                    <input type="tel" name="phone" placeholder="08xxxxxxxxxx" class="w-full pl-11 pr-4 py-3 rounded-xl border border-gray-200 dark:border-slate-700 bg-white/50 dark:bg-slate-900/50 focus:ring-2 focus:ring-primary outline-none transition-shadow text-sm">
                </div>
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 dark:text-slate-300 mb-1.5 ml-1">Password</label>
                <div class="relative" x-data="{ show: false }">
                    <i class="ph ph-lock absolute left-4 top-1/2 -translate-y-1/2 text-gray-400"></i>
                    <input :type="show ? 'text' : 'password'" name="password" minlength="6" required placeholder="Minimal 6 karakter" class="w-full pl-11 pr-11 py-3 rounded-xl border border-gray-200 dark:border-slate-700 bg-white/50 dark:bg-slate-900/50 focus:ring-2 focus:ring-primary outline-none transition-shadow text-sm">
                    <button type="button" @click="show = !show" class="absolute right-4 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                        <i class="ph" :class="show ? 'ph-eye-slash' : 'ph-eye'"></i>
                    </button>
                </div>
            </div>

            <button type="submit" class="w-full py-3 px-4 bg-primary hover:bg-[#0e7ad6] text-white font-bold rounded-xl shadow-lg shadow-blue-900/20 transition-all flex items-center justify-center gap-2 mt-2">
                Daftar Sekarang <i class="ph ph-arrow-right font-bold"></i>
            </button>
        </form>

        <?php if (defined('PahamFin_GOOGLE_CLIENT_ID') && PahamFin_GOOGLE_CLIENT_ID): ?>
        <div class="relative my-8">
            <div class="absolute inset-0 flex items-center"><div class="w-full border-t border-gray-200 dark:border-slate-700"></div></div>
            <div class="relative flex justify-center text-sm"><span class="px-4 bg-white dark:bg-slate-800 text-gray-500">Atau daftar dengan</span></div>
        </div>
        
        <a href="google-auth.php?action=login" class="w-full py-2.5 px-4 bg-white dark:bg-slate-900 border border-gray-200 dark:border-slate-700 hover:bg-gray-50 text-gray-700 dark:text-slate-200 font-semibold rounded-xl shadow-sm transition-all flex items-center justify-center gap-3">
            <img src="https://www.svgrepo.com/show/475656/google-color.svg" alt="Google" class="w-5 h-5">
            Google
        </a>
        <?php endif; ?>

        <p class="text-center text-sm text-gray-600 dark:text-slate-400 mt-8">
            Sudah punya akun? <a href="login.php" class="text-primary font-bold hover:underline">Masuk</a>
        </p>
    </div>
</body>
</html>



