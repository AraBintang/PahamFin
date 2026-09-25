<?php
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/includes/config.php';
require_once __DIR__ . '/../app/includes/auth.php';
require_once __DIR__ . '/../app/includes/tripay.php';

require_login();
$user_id = current_user_id();

// User profile
$stmtUser = $pdo->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
$stmtUser->execute([$user_id]);
$currentUser = $stmtUser->fetch(PDO::FETCH_ASSOC);

if (!$currentUser) {
    session_destroy();
    header('Location: ' . PahamFin_URL_AUTH . '/login.php');
    exit;
}

$isAdmin = PahamFin_is_admin($currentUser);
$activeSub = PahamFin_get_user_active_subscription($pdo, $user_id);

// Jika user sudah memiliki langganan aktif ATAU admin, langsung arahkan ke Dashboard
if ($activeSub || $isAdmin) {
    header('Location: ' . PahamFin_URL_PAGES . '/index.php');
    exit;
}

$tripayData = null;
$errorMsg = null;
$successMsg = null;

// Handle Form Post (Pembayaran QRIS Tripay / Redeem Voucher / Aktivasi Instan)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!PahamFin_csrf_verify()) {
        $errorMsg = 'Sesi tidak valid, silakan muat ulang halaman.';
    } else {
        $action = $_POST['action'] ?? 'checkout';

        if ($action === 'redeem_voucher') {
            $voucherCode = trim($_POST['voucher_code'] ?? '');
            $redeemRes = PahamFin_redeem_voucher($pdo, $user_id, $voucherCode);
            if ($redeemRes['success']) {
                header('Location: ' . PahamFin_URL_PAGES . '/index.php?success=voucher_redeemed');
                exit;
            } else {
                $errorMsg = $redeemRes['message'];
            }
        } else {
            $planId = (int) ($_POST['plan_id'] ?? 0);

            if ($planId > 0) {
                $stmtPlan = $pdo->prepare("SELECT * FROM subscription_plans WHERE id = ? LIMIT 1");
                $stmtPlan->execute([$planId]);
                $targetPlan = $stmtPlan->fetch(PDO::FETCH_ASSOC);

                if ($targetPlan) {
                    if ($action === 'instant_activate') {
                        // Fallback / Tes Aktivasi Langsung
                        $result = PahamFin_activate_user_subscription($pdo, $user_id, $planId, 'QRIS_TEST');
                        if ($result['success']) {
                            header('Location: ' . PahamFin_URL_PAGES . '/index.php?success=activated');
                            exit;
                        } else {
                            $errorMsg = $result['message'];
                        }
                    } elseif ($action === 'check_status') {
                        // Cek Ulang Status Langganan
                        $checkSub = PahamFin_get_user_active_subscription($pdo, $user_id);
                        if ($checkSub) {
                            header('Location: ' . PahamFin_URL_PAGES . '/index.php?success=activated');
                            exit;
                        } else {
                            $errorMsg = 'Pembayaran belum terdeteksi. Silakan selesaikan pembayaran QRIS Anda atau gunakan tombol Aktivasi Instan.';
                        }
                    } else {
                        // Buat Transaksi QRIS Tripay
                        $merchantRef = 'SUB-' . $user_id . '-' . $planId . '-' . time();
                        $tripayRes = PahamFin_tripay_create_transaction($merchantRef, (float)$targetPlan['price'], 'QRIS', $currentUser, $targetPlan);

                        if ($tripayRes['success']) {
                            $tripayData = $tripayRes['data'];
                        } else {
                            $errorMsg = 'Gagal membuat QRIS Tripay: ' . ($tripayRes['message'] ?? 'Error API') . '. Anda dapat menggunakan Redeem Kode Voucher atau Aktivasi Instan di bawah.';
                        }
                    }
                }
            }
        }
    }
}

// Ambil daftar paket aktif dari DB
$plans = PahamFin_get_subscription_plans($pdo, true);
?>
<!DOCTYPE html>
<html lang="id" x-data="{ darkMode: localStorage.getItem('PahamFin_dark') === '1', toggleDark() { this.darkMode = !this.darkMode; localStorage.setItem('PahamFin_dark', this.darkMode ? '1' : '0'); document.documentElement.classList.toggle('dark', this.darkMode); } }">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Aktivasi & Pembayaran - PahamFin</title>
    <link rel="icon" type="image/png" href="<?= PahamFin_URL_LOGO ?>">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.1/src/index.js"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Figtree:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: { extend: { colors: { primary: '#0A58A5' }, fontFamily: { display: ['Figtree', 'sans-serif'] } } }
        }
    </script>
    <?php require_once __DIR__ . '/../app/includes/theme.php'; ?>
</head>
<body class="bg-canvas text-slate-900 dark:text-slate-100 font-sans antialiased min-h-screen flex flex-col justify-between">

    <!-- Header Standalone -->
    <header class="glass-card border-b border-slate-200 dark:border-slate-800 py-4 px-6 sticky top-0 z-30 shadow-sm">
        <div class="max-w-6xl mx-auto flex items-center justify-between">
            <div class="flex items-center gap-3">
                <img src="<?= PahamFin_URL_LOGO ?>" alt="PahamFin" class="w-10 h-10 object-contain">
                <span class="font-display font-extrabold text-xl tracking-tight text-primary dark:text-sky-400">Paham<span class="text-amber-500">Fin</span></span>
            </div>
            <div class="flex items-center gap-3">
                <span class="text-xs text-slate-500 dark:text-slate-400 hidden sm:inline">Halo, <b><?= htmlspecialchars($currentUser['name']) ?></b></span>
                
                <!-- Tombol Tema (Dark/Light Mode) -->
                <?= PahamFin_theme_toggle('navbar') ?>

                <a href="<?= PahamFin_URL_AUTH ?>/logout.php" class="px-3 py-1.5 text-xs font-semibold text-rose-600 dark:text-rose-400 border border-rose-200 dark:border-rose-900/50 hover:bg-rose-50 dark:hover:bg-rose-950/40 rounded-xl transition">
                    Keluar
                </a>
            </div>
        </div>
    </header>

    <!-- Main Container -->
    <main class="max-w-5xl mx-auto px-4 py-8 flex-1 w-full">

        <!-- Banner Informasi -->
        <div class="text-center max-w-2xl mx-auto mb-8">
            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold uppercase tracking-widest bg-amber-100 dark:bg-amber-900/40 text-amber-800 dark:text-amber-300 mb-3">
                <i class="ph ph-qr-code text-sm"></i> Aktivasi Langganan QRIS / Kode Voucher
            </span>
            <h1 class="text-2xl sm:text-3xl font-display font-extrabold text-slate-900 dark:text-slate-100">
                Pilih Paket atau Redeem Kode Voucher
            </h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-2 leading-relaxed">
                Akun Anda belum aktif. Pilih paket langganan QRIS di bawah ini atau masukkan kode voucher yang sudah Anda miliki untuk mengaktifkan akses penuh.
            </p>
        </div>

        <!-- Box Redeem Kode Voucher -->
        <div class="max-w-xl mx-auto mb-10 glass-card rounded-3xl p-6 border border-amber-200/60 dark:border-amber-900/50 shadow-lg">
            <div class="flex items-center gap-3 mb-4">
                <div class="w-10 h-10 rounded-2xl bg-amber-500 text-white flex items-center justify-center font-bold shadow-md">
                    <i class="ph ph-ticket text-xl"></i>
                </div>
                <div>
                    <h3 class="font-bold text-base text-slate-900 dark:text-slate-100">Punya Kode Voucher / Redeem Code?</h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Masukkan kode voucher untuk aktivasi instan tanpa bayar lagi.</p>
                </div>
            </div>

            <form method="POST" class="flex flex-col sm:flex-row gap-2.5">
                <?= PahamFin_csrf_field() ?>
                <input type="hidden" name="action" value="redeem_voucher">
                <div class="relative flex-1">
                    <input type="text" name="voucher_code" placeholder="Contoh: PHM-ABCD-1234" required
                           class="w-full px-4 py-3 bg-white/90 dark:bg-slate-800/90 border border-slate-200 dark:border-slate-700 rounded-2xl text-sm font-mono font-bold uppercase tracking-wider text-slate-900 dark:text-slate-100 focus:ring-2 focus:ring-amber-500">
                </div>
                <button type="submit" class="px-6 py-3 bg-amber-500 hover:bg-amber-600 text-white font-bold text-xs rounded-2xl transition shadow-md shadow-amber-500/20 shrink-0 flex items-center justify-center gap-2">
                    <i class="ph ph-check-circle text-base"></i> Redeem Kode
                </button>
        </div>

        <?php if ($errorMsg): ?>
            <div class="max-w-2xl mx-auto mb-6 p-4 rounded-2xl bg-rose-50 border border-rose-200 text-rose-700 text-sm flex items-start gap-3">
                <i class="ph ph-warning-circle text-xl shrink-0 mt-0.5"></i>
                <div><?= htmlspecialchars($errorMsg) ?></div>
            </div>
        <?php endif; ?>

        <!-- modal / view jika QRIS Tripay berhasil dibuat -->
        <?php if ($tripayData): ?>
            <div class="max-w-xl mx-auto bg-white border border-slate-200 rounded-3xl p-6 sm:p-8 shadow-xl text-center mb-10">
                <div class="w-14 h-14 bg-emerald-100 text-emerald-600 rounded-2xl flex items-center justify-center mx-auto mb-4">
                    <i class="ph ph-qr-code text-3xl"></i>
                </div>
                <h3 class="text-xl font-bold text-slate-900">Kode QRIS Pembayaran Diterbitkan</h3>
                <p class="text-xs text-slate-500 mt-1">Scan QRIS di bawah menggunakan GoPay, OVO, Dana, ShopeePay, BCA, Mandiri, atau Bank Apapun.</p>

                <!-- Tampilan QR Code -->
                <div class="my-6 p-4 bg-slate-50 rounded-2xl border border-slate-200 inline-block">
                    <?php if (!empty($tripayData['qr_url'])): ?>
                        <img src="<?= htmlspecialchars($tripayData['qr_url']) ?>" alt="QRIS Code" class="w-64 h-64 object-contain mx-auto rounded-lg">
                    <?php elseif (!empty($tripayData['qr_content'])): ?>
                        <img src="https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=<?= urlencode($tripayData['qr_content']) ?>" alt="QRIS Code" class="w-64 h-64 object-contain mx-auto rounded-lg">
                    <?php else: ?>
                        <div class="p-8 text-xs text-slate-400">Kode QRIS Siap. Silakan periksa detail transaksi.</div>
                    <?php endif; ?>
                    <div class="mt-3 text-lg font-extrabold text-primary">
                        Rp <?= number_format($tripayData['amount'] ?? 0, 0, ',', '.') ?>
                    </div>
                    <div class="text-[11px] text-slate-400 mt-0.5">Ref: <?= htmlspecialchars($tripayData['reference'] ?? '') ?></div>
                </div>

                <div class="space-y-3 max-w-md mx-auto">
                    <form method="POST">
                        <?= PahamFin_csrf_field() ?>
                        <input type="hidden" name="action" value="check_status">
                        <button type="submit" class="w-full py-3 bg-primary text-white rounded-xl font-bold hover:bg-[#0e7ad6] transition shadow-md flex items-center justify-center gap-2 text-sm">
                            <i class="ph ph-check-circle text-lg"></i> Saya Sudah Bayar / Cek Status
                        </button>
                    </form>

                    <form method="POST">
                        <?= PahamFin_csrf_field() ?>
                        <input type="hidden" name="plan_id" value="<?= (int)($_POST['plan_id'] ?? 0) ?>">
                        <input type="hidden" name="action" value="instant_activate">
                        <button type="submit" class="w-full py-2.5 bg-slate-100 text-slate-700 hover:bg-slate-200 rounded-xl font-semibold text-xs transition">
                            ⚡ Aktivasi Instan (Mode Pengujian)
                        </button>
                    </form>
                </div>
            </div>
        <?php else: ?>

            <!-- Grid Paket Langganan (Hanya QRIS Tripay) -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 max-w-5xl mx-auto">
                <?php foreach ($plans as $plan): 
                    $isPopular = strpos(strtolower($plan['name']), 'bulan') !== false || strpos(strtolower($plan['name']), 'pro') !== false;
                ?>
                <div class="bg-white rounded-3xl border <?= $isPopular ? 'border-primary ring-2 ring-primary/20 shadow-xl' : 'border-slate-200 shadow-sm' ?> p-6 flex flex-col justify-between relative">
                    <?php if ($isPopular): ?>
                        <span class="absolute -top-3.5 left-1/2 -translate-x-1/2 px-3 py-1 bg-primary text-white text-[10px] font-extrabold uppercase tracking-wider rounded-full shadow-md">
                            Paling Populer
                        </span>
                    <?php endif; ?>

                    <div>
                        <div class="mb-4">
                            <h3 class="text-lg font-bold text-slate-900"><?= htmlspecialchars($plan['name']) ?></h3>
                            <p class="text-xs text-slate-500 mt-1"><?= htmlspecialchars($plan['description'] ?: 'Masa aktif ' . $plan['duration_days'] . ' hari') ?></p>
                        </div>

                        <div class="mb-6">
                            <span class="text-3xl font-extrabold text-slate-900">Rp <?= number_format($plan['price'], 0, ',', '.') ?></span>
                            <span class="text-xs text-slate-400"> / <?= $plan['duration_days'] ?> hari</span>
                        </div>

                        <!-- Daftar Fitur -->
                        <ul class="space-y-2.5 text-xs text-slate-600 border-t border-slate-100 pt-4 mb-6">
                            <li class="flex items-center gap-2">
                                <i class="ph ph-check-circle text-emerald-500 text-base"></i> Akses Bot Telegram AI Unlimited
                            </li>
                            <li class="flex items-center gap-2">
                                <i class="ph ph-check-circle text-emerald-500 text-base"></i> Pencatatan Transaksi & Laporan PDF
                            </li>
                            <li class="flex items-center gap-2">
                                <i class="ph ph-check-circle text-emerald-500 text-base"></i> Fitur Anggaran Bulanan & Tabungan
                            </li>
                            <li class="flex items-center gap-2">
                                <i class="ph ph-check-circle text-emerald-500 text-base"></i> Notifikasi H-2 Pengingat Transaksi
                            </li>
                            <li class="flex items-center gap-2">
                                <i class="ph ph-check-circle text-emerald-500 text-base"></i> Pembayaran QRIS Otomatis Tripay
                            </li>
                        </ul>
                    </div>

                    <div class="space-y-2 pt-2 border-t border-slate-100">
                        <form method="POST">
                            <?= PahamFin_csrf_field() ?>
                            <input type="hidden" name="plan_id" value="<?= $plan['id'] ?>">
                            <input type="hidden" name="action" value="checkout">
                            <button type="submit" class="w-full py-3 <?= $isPopular ? 'bg-primary text-white hover:bg-[#0e7ad6]' : 'bg-slate-900 text-white hover:bg-slate-800' ?> rounded-xl font-bold shadow-md transition flex items-center justify-center gap-2 text-xs">
                                <i class="ph ph-qr-code text-base"></i> Bayar QRIS Tripay
                            </button>
                        </form>

                        <form method="POST">
                            <?= PahamFin_csrf_field() ?>
                            <input type="hidden" name="plan_id" value="<?= $plan['id'] ?>">
                            <input type="hidden" name="action" value="instant_activate">
                            <button type="submit" class="w-full py-2 bg-slate-100 text-slate-600 hover:bg-slate-200 rounded-xl font-semibold text-[11px] transition">
                                Aktivasi Instan (Mode Pengujian)
                            </button>
                        </form>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

        <?php endif; ?>

    </main>

    <!-- Footer Standalone -->
    <footer class="border-t border-slate-200 py-6 px-4 bg-white text-center text-xs text-slate-400">
        <p>&copy; <?= date('Y') ?> PahamFin — Pembayaran Aman Didukung oleh Tripay Payment Gateway (QRIS)</p>
    </footer>

</body>
</html>
