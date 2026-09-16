<?php
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/includes/config.php';
require_once __DIR__ . '/../app/includes/auth.php';
require_login();

$user_id = current_user_id();
$current_page = 'pricing';

// Handle Pembayaran Otomatis Instan
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!PahamFin_csrf_verify()) {
        $_SESSION['flash_msg'] = 'Sesi tidak valid, coba lagi.';
        $_SESSION['flash_type'] = 'error';
        header('Location: pricing.php');
        exit;
    }

    $planId = (int) ($_POST['plan_id'] ?? 0);
    $paymentMethod = trim((string) ($_POST['payment_method'] ?? 'QRIS_OTOMATIS'));

    if ($planId > 0) {
        $result = PahamFin_activate_user_subscription($pdo, $user_id, $planId, $paymentMethod);
        $_SESSION['flash_msg'] = $result['message'];
        $_SESSION['flash_type'] = $result['success'] ? 'success' : 'error';
    } else {
        $_SESSION['flash_msg'] = 'Pilih paket langganan yang valid.';
        $_SESSION['flash_type'] = 'error';
    }

    header('Location: pricing.php');
    exit;
}

// Fetch Active Subscription & Available Plans
$activeSub = PahamFin_get_user_active_subscription($pdo, $user_id);
$plans = PahamFin_get_subscription_plans($pdo, true);

require_once __DIR__ . '/../app/includes/header.php';
require_once __DIR__ . '/../app/includes/sidebar.php';
?>

<div x-data="{ checkoutModal: false, selectedPlan: { id: 0, name: '', price: 0, duration_days: 30 }, method: 'QRIS_OTOMATIS' }">

    <!-- Header Banner -->
    <div class="mb-8 text-center max-w-2xl mx-auto">
        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold uppercase tracking-widest bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300 mb-3">
            <i class="ph ph-crown text-sm"></i> Layanan Premium PahamFin
        </span>
        <h1 class="text-2xl lg:text-3xl font-display font-extrabold text-gray-900 dark:text-slate-100">
            Pilih Paket Langganan Terbaik
        </h1>
        <p class="text-sm text-gray-500 dark:text-slate-400 mt-2 leading-relaxed">
            Nikmati akses penuh pencatatan keuangan pintar lewat Bot Telegram AI, Tabungan, Manajemen Hutang, dan laporan lengkap tanpa batas.
        </p>
    </div>

    <!-- Status Langganan Aktif User (Jika Ada) -->
    <?php if ($activeSub): 
        $daysLeft = max(0, ceil((strtotime($activeSub['expires_at']) - time()) / 86400));
    ?>
    <div class="max-w-4xl mx-auto mb-8 p-5 rounded-2xl bg-gradient-to-r from-blue-900 via-primary to-blue-700 text-white shadow-xl shadow-blue-900/20 flex flex-wrap items-center justify-between gap-4">
        <div class="flex items-center gap-4">
            <div class="w-12 h-12 rounded-2xl bg-white/10 backdrop-blur border border-white/20 flex items-center justify-center shrink-0">
                <i class="ph ph-crown text-2xl text-amber-300"></i>
            </div>
            <div>
                <div class="flex items-center gap-2">
                    <span class="text-xs font-bold uppercase tracking-wider text-blue-200">Status Langganan Anda</span>
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-extrabold bg-amber-300 text-blue-950">AKTIF</span>
                </div>
                <h3 class="text-lg font-bold mt-0.5"><?= htmlspecialchars($activeSub['plan_name']) ?></h3>
                <p class="text-xs text-blue-100/80">Aktif hingga <?= date('d F Y', strtotime($activeSub['expires_at'])) ?> (Tersisa <?= $daysLeft ?> hari)</p>
            </div>
        </div>
        <div>
            <span class="text-xs bg-white/15 px-3 py-1.5 rounded-xl border border-white/20 font-medium text-white flex items-center gap-1.5">
                <i class="ph ph-check-circle text-amber-300"></i> Pembayaran Terverifikasi
            </span>
        </div>
    </div>
    <?php endif; ?>

    <!-- Grid Cards Paket -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 max-w-6xl mx-auto mb-12">
        <?php foreach ($plans as $index => $plan): 
            $isBestSeller = ($index === 0 || stripos($plan['name'], 'Pro') !== false);
            $isCurrentActive = ($activeSub && (int)$activeSub['plan_id'] === (int)$plan['id']);
        ?>
        <div class="glass-card rounded-3xl border border-white/60 dark:border-slate-700/50 overflow-hidden shadow-lg hover:shadow-2xl transition-all duration-300 flex flex-col justify-between relative <?= $isBestSeller ? 'ring-2 ring-primary dark:ring-blue-500 scale-[1.02]' : '' ?>">
            
            <?php if ($isBestSeller): ?>
            <div class="bg-gradient-to-r from-amber-400 to-amber-500 text-slate-900 text-[11px] font-extrabold uppercase tracking-widest text-center py-1">
                ⭐ Paling Populer & Recommended
            </div>
            <?php endif; ?>

            <div class="p-6">
                <div class="flex justify-between items-start mb-2">
                    <h3 class="text-xl font-display font-bold text-ink dark:text-slate-100"><?= htmlspecialchars($plan['name']) ?></h3>
                    <?php if ($isCurrentActive): ?>
                        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold bg-emerald-100 text-emerald-800 dark:bg-emerald-900/60 dark:text-emerald-300">Paket Aktif</span>
                    <?php endif; ?>
                </div>

                <p class="text-xs text-gray-500 dark:text-slate-400 mb-6 leading-relaxed"><?= htmlspecialchars($plan['description'] ?: 'Nikmati fitur keuangan PahamFin.') ?></p>

                <div class="mb-6 pb-6 border-b border-gray-100 dark:border-slate-700/50">
                    <div class="flex items-baseline gap-1">
                        <span class="text-3xl lg:text-4xl font-extrabold text-ink dark:text-slate-100">Rp <?= number_format($plan['price'], 0, ',', '.') ?></span>
                    </div>
                    <span class="text-xs text-gray-400 font-medium">Masa aktif <?= (int)$plan['duration_days'] ?> Hari (Otomatis Aktif)</span>
                </div>

                <!-- Feature list -->
                <div class="space-y-3 mb-6">
                    <p class="text-[11px] font-bold uppercase tracking-wider text-gray-400">Fitur yang didapat:</p>
                    <ul class="space-y-2.5 text-xs text-gray-700 dark:text-slate-300">
                        <?php 
                        $featuresList = array_filter(array_map('trim', explode("\n", (string)$plan['features'])));
                        foreach ($featuresList as $feat):
                        ?>
                            <li class="flex items-start gap-2.5">
                                <div class="w-4 h-4 rounded-full bg-emerald-100 dark:bg-emerald-900/50 text-emerald-600 dark:text-emerald-400 flex items-center justify-center shrink-0 mt-0.5">
                                    <i class="ph ph-check text-xs font-bold"></i>
                                </div>
                                <span><?= htmlspecialchars($feat) ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>

            <div class="p-6 pt-0">
                <button @click="selectedPlan = <?= htmlspecialchars(json_encode($plan), ENT_QUOTES, 'UTF-8') ?>; checkoutModal = true;"
                        class="w-full py-3 px-4 rounded-2xl font-bold text-xs text-white bg-gradient-to-r from-primary to-[#0e7ad6] hover:opacity-95 transition-all shadow-lg shadow-blue-900/20 flex items-center justify-center gap-2">
                    <i class="ph ph-lightning text-base text-amber-300"></i>
                    <span><?= $isCurrentActive ? 'Perpanjang Langganan' : 'Beli & Aktifkan Sekarang' ?></span>
                </button>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Modal Pembayaran Otomatis -->
    <div x-show="checkoutModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm" x-cloak>
        <div @click.away="checkoutModal = false" class="glass-card w-full max-w-md rounded-3xl border border-white/60 dark:border-slate-700 shadow-2xl overflow-hidden bg-white dark:bg-slate-900">
            
            <div class="p-5 border-b border-gray-100 dark:border-slate-700/50 flex items-center justify-between bg-gray-50/50 dark:bg-slate-800/50">
                <div class="flex items-center gap-2">
                    <i class="ph ph-credit-card text-primary text-xl"></i>
                    <h3 class="font-display font-bold text-ink dark:text-slate-100 text-sm">Pembayaran Instan</h3>
                </div>
                <button @click="checkoutModal = false" class="text-gray-400 hover:text-gray-600 dark:hover:text-slate-200">
                    <i class="ph ph-x text-xl"></i>
                </button>
            </div>

            <form method="POST" class="p-6 space-y-5">
                <?= PahamFin_csrf_field() ?>
                <input type="hidden" name="plan_id" :value="selectedPlan.id">

                <!-- Ringkasan Paket -->
                <div class="p-4 rounded-2xl bg-blue-50/70 dark:bg-blue-950/40 border border-blue-100 dark:border-blue-900/40 flex items-center justify-between">
                    <div>
                        <p class="text-[11px] font-semibold text-primary dark:text-blue-300">Paket Yang Dipilih</p>
                        <h4 class="font-bold text-ink dark:text-slate-100 text-sm mt-0.5" x-text="selectedPlan.name"></h4>
                        <p class="text-[11px] text-gray-500 dark:text-slate-400" x-text="selectedPlan.duration_days + ' Hari Masa Aktif'"></p>
                    </div>
                    <div class="text-right">
                        <p class="text-xs text-gray-400">Total Bayar</p>
                        <p class="font-extrabold text-primary dark:text-blue-400 text-base" x-text="'Rp ' + new Intl.NumberFormat('id-ID').format(selectedPlan.price)"></p>
                    </div>
                </div>

                <!-- Metode Pembayaran -->
                <div>
                    <label class="block text-xs font-bold text-gray-700 dark:text-slate-300 mb-2">Pilih Metode Pembayaran</label>
                    <div class="space-y-2">
                        <label class="flex items-center justify-between p-3 rounded-xl border cursor-pointer transition-all"
                               :class="method === 'QRIS_OTOMATIS' ? 'border-primary bg-blue-50/50 dark:bg-slate-800 dark:border-blue-500' : 'border-gray-200 dark:border-slate-700'">
                            <div class="flex items-center gap-3">
                                <input type="radio" name="payment_method" value="QRIS_OTOMATIS" x-model="method" class="text-primary focus:ring-primary">
                                <div>
                                    <p class="text-xs font-bold text-ink dark:text-slate-100">QRIS Instant (Otomatis)</p>
                                    <p class="text-[10px] text-gray-400">Gopay, OVO, Dana, ShopeePay, BCA, Mandiri</p>
                                </div>
                            </div>
                            <span class="text-[10px] font-bold uppercase bg-emerald-100 text-emerald-700 px-2 py-0.5 rounded-full">Instan</span>
                        </label>

                        <label class="flex items-center justify-between p-3 rounded-xl border cursor-pointer transition-all"
                               :class="method === 'TRANSFER_BANK' ? 'border-primary bg-blue-50/50 dark:bg-slate-800 dark:border-blue-500' : 'border-gray-200 dark:border-slate-700'">
                            <div class="flex items-center gap-3">
                                <input type="radio" name="payment_method" value="TRANSFER_BANK" x-model="method" class="text-primary focus:ring-primary">
                                <div>
                                    <p class="text-xs font-bold text-ink dark:text-slate-100">Transfer Virtual Account</p>
                                    <p class="text-[10px] text-gray-400">Verifikasi Pembayaran Otomatis</p>
                                </div>
                            </div>
                            <span class="text-[10px] font-bold uppercase bg-emerald-100 text-emerald-700 px-2 py-0.5 rounded-full">Instan</span>
                        </label>
                    </div>
                </div>

                <!-- Info aktivasi otomatis -->
                <div class="p-3 rounded-xl bg-amber-50 dark:bg-amber-900/30 border border-amber-200 dark:border-amber-800/40 text-amber-800 dark:text-amber-200 text-[11px] flex items-start gap-2">
                    <i class="ph ph-lightning text-base shrink-0 mt-0.5"></i>
                    <span>Setelah tombol diklik, pembayaran akan langsung diproses dan paket akun Anda **otomatis aktif seketika** tanpa waktu tunggu!</span>
                </div>

                <button type="submit" class="w-full py-3 bg-gradient-to-r from-primary to-[#0e7ad6] text-white font-bold rounded-xl text-xs hover:opacity-90 transition shadow-lg shadow-blue-900/20 flex items-center justify-center gap-2">
                    <i class="ph ph-check-circle text-base"></i> Selesaikan Pembayaran & Aktifkan
                </button>
            </form>
        </div>
    </div>

</div>

<?php require_once __DIR__ . '/../app/includes/footer.php'; ?>
