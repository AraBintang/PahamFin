<?php
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/includes/config.php';

// Jika tidak ada pendaftaran tertunda, arahkan kembali
if (empty($_SESSION['pending_reg'])) {
    header('Location: register.php');
    exit;
}

$error = '';
$success = '';

$pending = $_SESSION['pending_reg'];

// Cek apakah expired
if (time() > $pending['expires']) {
    unset($_SESSION['pending_reg']);
    header('Location: register.php?error=otp_expired');
    exit;
}

// Proses Resend
if (isset($_GET['resend'])) {
    if (time() > ($pending['expires'] - 540)) { // Cuma bisa resend setelah 1 menit (600 - 60 = 540)
        $error = 'Tunggu 1 menit sebelum meminta kode baru.';
    } else {
        $otp = (string) random_int(100000, 999999);
        $_SESSION['pending_reg']['otp'] = $otp;
        $_SESSION['pending_reg']['expires'] = time() + 600; // Reset waktu
        
        require_once __DIR__ . '/../app/includes/mailer.php';
        if (PahamFin_send_otp_email($pending['email'], $otp)) {
            $success = 'Kode OTP baru telah dikirim.';
        } else {
            $error = 'Gagal mengirim email OTP.';
        }
    }
}

// Proses Submit OTP
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!PahamFin_csrf_verify()) {
        $error = 'Sesi tidak valid.';
    } else {
        $inputOtp = trim($_POST['otp'] ?? '');
        
        if ($inputOtp === $pending['otp']) {
            // Berhasil! Simpan ke database
            $phoneInsert = empty($pending['phone']) ? null : $pending['phone'];
            $stmt = $pdo->prepare("INSERT INTO users (name, email, phone_number, password) VALUES (?, ?, ?, ?)");
            if ($stmt->execute([$pending['name'], $pending['email'], $phoneInsert, $pending['password']])) {
                $newId = (int) $pdo->lastInsertId();
                
                // Seed default categories (lengkap)
                PahamFin_seed_default_categories($pdo, $newId);
                
                unset($_SESSION['pending_reg']);
                session_regenerate_id(true);
                $_SESSION['user_id'] = $newId;
                
                // Cek apakah dia admin berdasarkan daftar email
                $role = PahamFin_user_role(['email' => $pending['email'], 'role' => 'user']);
                if ($role === 'admin') {
                    $upd = $pdo->prepare("UPDATE users SET role = 'admin' WHERE id = ?");
                    $upd->execute([$newId]);
                }
                $_SESSION['user_role'] = $role;
                
                header('Location: ../pages/index.php');
                exit;
            } else {
                $error = 'Gagal menyimpan akun ke database.';
            }
        } else {
            $error = 'Kode OTP salah.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verifikasi OTP - PahamFin</title>
    <link rel="icon" type="image/png" href="<?= PahamFin_URL_LOGO ?>">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.13.0/dist/cdn.min.js"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: { extend: { colors: { primary: '#0A58A5', ink: '#0f172a' }, fontFamily: { display: ['Figtree', 'sans-serif'] } } }
        }
    </script>
    <style>
        .bg-canvas { background: #f8fafc; }
    </style>
</head>
<body class="bg-canvas text-ink antialiased font-sans min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-md bg-white rounded-3xl shadow-[0_8px_30px_rgb(0,0,0,0.04)] border border-gray-100 p-8">
        <div class="text-center mb-8 relative">
            <div class="w-20 h-20 mx-auto mb-4 flex items-center justify-center rounded-2xl bg-blue-50 text-primary">
                <i class="ph ph-envelope-open text-4xl"></i>
            </div>
            <h2 class="text-2xl font-display font-extrabold text-ink">Periksa Email Anda</h2>
            <p class="text-sm text-gray-500 mt-2">Kami telah mengirimkan 6 digit kode OTP ke <br><strong class="text-ink"><?= htmlspecialchars($pending['email']) ?></strong></p>
        </div>

        <?php if ($error !== ''): ?>
            <div class="mb-6 p-3 bg-red-50 text-red-600 text-sm rounded-xl border border-red-100 flex items-center gap-2">
                <i class="ph ph-warning-circle text-lg shrink-0"></i> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
        <?php if ($success !== ''): ?>
            <div class="mb-6 p-3 bg-emerald-50 text-emerald-600 text-sm rounded-xl border border-emerald-100 flex items-center gap-2">
                <i class="ph ph-check-circle text-lg shrink-0"></i> <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>

        <div class="mb-6"><a href="register.php" class="text-sm text-gray-500 hover:text-primary flex items-center gap-2"><i class="ph ph-arrow-left"></i> Kembali (Ubah Data)</a></div><form method="POST" class="space-y-6">
            <?= PahamFin_csrf_field() ?>
            
            <div>
                <input type="text" name="otp" required maxlength="6" pattern="[0-9]{6}" autocomplete="one-time-code" class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-4 text-center text-3xl font-bold tracking-[0.5em] focus:ring-primary focus:border-primary outline-none text-ink transition-colors" placeholder="------">
            </div>

            <button type="submit" class="w-full py-3 px-4 bg-primary hover:bg-blue-700 text-white font-bold rounded-xl shadow-lg shadow-blue-900/20 transition-transform active:scale-95 flex justify-center items-center gap-2">
                Verifikasi Akun <i class="ph ph-check-circle text-lg"></i>
            </button>
        </form>

        <p class="mt-8 text-center text-sm text-gray-500">
            Belum menerima email? <a href="verify-otp.php?resend=1" class="text-primary font-bold hover:underline">Kirim Ulang</a>
        </p>
        <p class="mt-4 text-center text-xs text-gray-400">
            Salah email? <a href="register.php" class="hover:underline">Daftar ulang dari awal</a>
        </p>
    </div>
</body>
</html>

