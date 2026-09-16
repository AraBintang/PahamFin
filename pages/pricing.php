<?php
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/includes/config.php';
require_once __DIR__ . '/../app/includes/auth.php';
require_once __DIR__ . '/../app/includes/tripay.php';
require_login();

$user_id = current_user_id();
$current_page = 'pricing';
$tripayData = null;
$checkoutError = null;

// User Profile
$stmtUser = $pdo->prepare("SELECT name, email, phone_number FROM users WHERE id = ? LIMIT 1");
$stmtUser->execute([$user_id]);
$currentUser = $stmtUser->fetch(PDO::FETCH_ASSOC) ?: ['name' => 'Pengguna', 'email' => 'user@pahamfin.com', 'phone_number' => '081234567890'];

// Handle Pembayaran (Tripay & Instan)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!PahamFin_csrf_verify()) {
        $_SESSION['flash_msg'] = 'Sesi tidak valid, coba lagi.';
        $_SESSION['flash_type'] = 'error';
        header('Location: pricing.php');
        exit;
    }

    $planId = (int) ($_POST['plan_id'] ?? 0);
    $paymentMethod = trim((string) ($_POST['payment_method'] ?? 'QRIS'));
    $action = $_POST['action'] ?? 'checkout';

    if ($planId > 0) {
        $stmtPlan = $pdo->prepare("SELECT * FROM subscription_plans WHERE id = ? LIMIT 1");
        $stmtPlan->execute([$planId]);
        $targetPlan = $stmtPlan->fetch(PDO::FETCH_ASSOC);

        if ($targetPlan) {
            if ($action === 'instant_activate') {
                // Aktivasi Instan Langsung
                $result = PahamFin_activate_user_subscription($pdo, $user_id, $planId, $paymentMethod);
                $_SESSION['flash_msg'] = $result['message'];
                $_SESSION['flash_type'] = $result['success'] ? 'success' : 'error';
                header('Location: pricing.php');
                exit;
            } else {
                // Buat Transaksi Pembayaran Tripay
                $merchantRef = 'SUB-' . $user_id . '-' . $planId . '-' . time();
                $tripayRes = PahamFin_tripay_create_transaction($merchantRef, (float)$targetPlan['price'], $paymentMethod, $currentUser, $targetPlan);

                if ($tripayRes['success']) {
                    $tripayData = $tripayRes['data'];
                } else {
                    // Fallback jika API error -> langsung aktifkan instan agar user tidak terkendala
                    $result = PahamFin_activate_user_subscription($pdo, $user_id, $planId, $paymentMethod);
                    $_SESSION['flash_msg'] = $result['message'];
                    $_SESSION['flash_type'] = 'success';
                    header('Location: pricing.php');
                    exit;
                }
            }
        }
    }
}

// Fetch Active Subscription & Available Plans
$activeSub = PahamFin_get_user_active_subscription($pdo, $user_id);
$plans = PahamFin_get_subscription_plans($pdo, true);

require_once __DIR__ . '/../app/includes/header.php';
require_once __DIR__ . '/../app/includes/sidebar.php';
?>

<div x-data="{ checkoutModal: false, selectedPlan: { id: 0, name: '', price: 0, duration_days: 30 }, method: 'QRIS' }">

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

    <!-- Banner Fitur Lengkap PahamFin -->
    <div class="max-w-4xl mx-auto mb-10 glass-card p-6 rounded-3xl border border-white/60 dark:border-slate-700/50 shadow-md">
        <h3 class="text-center text-sm font-bold uppercase tracking-wider text-primary dark:text-blue-400 mb-4 flex items-center justify-center gap-2">
            <i class="ph ph-sparkle text-amber-500 text-lg"></i> SEMUA PAKET MENDAPATKAN AKSES PENUH SELURUH FITUR PAHAMFIN
        </h3>
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-3 text-center text-xs">
            <div class="p-3 rounded-2xl bg-blue-50/50 dark:bg-slate-800/60 border border-blue-100 dark:border-slate-700/50">
                <i class="ph ph-telegram-logo text-2xl text-sky-500 mb-1 block mx-auto"></i>
                <span class="font-semibold text-ink dark:text-slate-200">Bot Telegram 24/7</span>
            </div>
            <div class="p-3 rounded-2xl bg-blue-50/50 dark:bg-slate-800/60 border border-blue-100 dark:border-slate-700/50">
                <i class="ph ph-camera text-2xl text-emerald-500 mb-1 block mx-auto"></i>
                <span class="font-semibold text-ink dark:text-slate-200">Scan Struk AI</span>
            </div>
            <div class="p-3 rounded-2xl bg-blue-50/50 dark:bg-slate-800/60 border border-blue-100 dark:border-slate-700/50">
                <i class="ph ph-piggy-bank text-2xl text-amber-500 mb-1 block mx-auto"></i>
                <span class="font-semibold text-ink dark:text-slate-200">Tabungan & Target</span>
            </div>
            <div class="p-3 rounded-2xl bg-blue-50/50 dark:bg-slate-800/60 border border-blue-100 dark:border-slate-700/50">
                <i class="ph ph-handshake text-2xl text-purple-500 mb-1 block mx-auto"></i>
                <span class="font-semibold text-ink dark:text-slate-200">Hutang & Piutang</span>
            </div>
            <div class="p-3 rounded-2xl bg-blue-50/50 dark:bg-slate-800/60 border border-blue-100 dark:border-slate-700/50 col-span-2 md:col-span-1">
                <i class="ph ph-file-pdf text-2xl text-rose-500 mb-1 block mx-auto"></i>
                <span class="font-semibold text-ink dark:text-slate-200">Cetak Laporan PDF</span>
            </div>
        </div>
    </div>

    <!-- Modal Hasil Pembayaran Tripay (Jika Ada Respon Transaksi) -->
    <?php if ($tripayData): ?>
    <div class="max-w-xl mx-auto mb-10 glass-card p-6 rounded-3xl border border-emerald-300 dark:border-emerald-700 shadow-2xl bg-white dark:bg-slate-900 text-center">
        <div class="w-12 h-12 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center mx-auto mb-3 text-2xl">
            <i class="ph ph-check-circle"></i>
        </div>
        <h3 class="text-lg font-bold text-ink dark:text-slate-100">Instruksi Pembayaran Tripay</h3>
        <p class="text-xs text-gray-500 dark:text-slate-400 mt-1">Kode Transaksi: <code class="font-mono bg-gray-100 dark:bg-slate-800 px-2 py-0.5 rounded"><?= htmlspecialchars($tripayData['reference']) ?></code></p>

        <div class="my-6 p-4 rounded-2xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700">
            <p class="text-xs text-gray-400 uppercase font-bold tracking-wider mb-1">Total Pembayaran</p>
            <p class="text-3xl font-extrabold text-primary dark:text-blue-400">Rp <?= number_format($tripayData['amount'], 0, ',', '.') ?></p>

            <?php if (!empty($tripayData['qr_url'])): ?>
                <!-- Tampilan QRIS -->
                <div class="mt-4 p-3 bg-white rounded-2xl inline-block shadow-md">
                    <img src="<?= htmlspecialchars($tripayData['qr_url']) ?>" alt="QRIS Code" class="w-48 h-48 mx-auto object-contain">
                </div>
                <p class="text-xs text-gray-500 dark:text-slate-400 mt-2 font-medium">Scan QRIS menggunakan GoPay, OVO, Dana, ShopeePay, atau Mobile Banking Anda.</p>
            <?php elseif (!empty($tripayData['pay_code'])): ?>
                <!-- Tampilan Kode VA / Pay Code -->
                <div class="mt-4">
                    <p class="text-xs text-gray-400 mb-1">Nomor Virtual Account (<?= htmlspecialchars($tripayData['payment_name']) ?>):</p>
                    <div class="flex items-center justify-center gap-2">
                        <span class="text-2xl font-mono font-bold text-ink dark:text-slate-100 tracking-wider bg-white dark:bg-slate-900 px-4 py-2 rounded-xl border border-gray-200 dark:border-slate-700 select-all"><?= htmlspecialchars($tripayData['pay_code']) ?></span>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <div class="flex flex-wrap items-center justify-center gap-3">
            <?php if (!empty($tripayData['checkout_url'])): ?>
                <a href="<?= htmlspecialchars($tripayData['checkout_url']) ?>" target="_blank" class="px-5 py-2.5 bg-primary text-white font-bold rounded-xl text-xs hover:bg-[#0e7ad6] transition flex items-center gap-2">
                    <i class="ph ph-arrow-square-out text-base"></i> Buka Halaman Bayar Tripay
                </a>
            <?php endif; ?>

            <form method="POST" class="inline">
                <?= PahamFin_csrf_field() ?>
                <input type="hidden" name="action" value="instant_activate">
                <input type="hidden" name="plan_id" value="<?= (int)str_replace('PLAN-', '', $tripayData['order_items'][0]['sku'] ?? 0) ?>">
                <input type="hidden" name="payment_method" value="<?= htmlspecialchars($tripayData['payment_method']) ?>">
                <button type="submit" class="px-5 py-2.5 bg-emerald-600 text-white font-bold rounded-xl text-xs hover:bg-emerald-700 transition flex items-center gap-2">
                    <i class="ph ph-lightning text-base text-amber-300"></i> Sudah Bayar / Konfirmasi Instan
                </button>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <!-- Grid Cards Paket (Mingguan, Bulanan, Tahunan) -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 max-w-6xl mx-auto mb-12">
        <?php foreach ($plans as $index => $plan): 
            $isBestSeller = (stripos($plan['name'], 'Bulan') !== false || (int)$plan['duration_days'] === 30);
            $isCurrentActive = ($activeSub && (int)$activeSub['plan_id'] === (int)$plan['id']);
        ?>
        <div class="glass-card rounded-3xl border border-white/60 dark:border-slate-700/50 overflow-hidden shadow-lg hover:shadow-2xl transition-all duration-300 flex flex-col justify-between relative <?= $isBestSeller ? 'ring-2 ring-primary dark:ring-blue-500 scale-[1.02]' : '' ?>">
            
            <?php if ($isBestSeller): ?>
            <div class="bg-gradient-to-r from-amber-400 to-amber-500 text-slate-900 text-[11px] font-extrabold uppercase tracking-widest text-center py-1">
                ⭐ Paling Populer & Hemat
            </div>
            <?php endif; ?>

            <div class="p-6 text-center">
                <div class="flex justify-center items-center gap-2 mb-2">
                    <h3 class="text-xl font-display font-bold text-ink dark:text-slate-100"><?= htmlspecialchars($plan['name']) ?></h3>
                    <?php if ($isCurrentActive): ?>
                        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold bg-emerald-100 text-emerald-800 dark:bg-emerald-900/60 dark:text-emerald-300">Paket Aktif</span>
                    <?php endif; ?>
                </div>

                <p class="text-xs text-gray-500 dark:text-slate-400 mb-6 leading-relaxed"><?= htmlspecialchars($plan['description'] ?: 'Akses penuh seluruh fitur PahamFin.') ?></p>

                <div class="mb-4">
                    <div class="flex items-baseline justify-center gap-1">
                        <span class="text-3xl lg:text-4xl font-extrabold text-ink dark:text-slate-100">Rp <?= number_format($plan['price'], 0, ',', '.') ?></span>
                    </div>
                    <span class="text-xs text-gray-400 font-medium">Masa aktif <?= (int)$plan['duration_days'] ?> Hari</span>
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

    <!-- Modal Pembayaran Tripay & Instan -->
    <div x-show="checkoutModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm" x-cloak>
        <div @click.away="checkoutModal = false" class="glass-card w-full max-w-md rounded-3xl border border-white/60 dark:border-slate-700 shadow-2xl overflow-hidden bg-white dark:bg-slate-900">
            
            <div class="p-5 border-b border-gray-100 dark:border-slate-700/50 flex items-center justify-between bg-gray-50/50 dark:bg-slate-800/50">
                <div class="flex items-center gap-2">
                    <i class="ph ph-credit-card text-primary text-xl"></i>
                    <h3 class="font-display font-bold text-ink dark:text-slate-100 text-sm">Pembayaran Tripay Payment Gateway</h3>
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

                <!-- Metode Pembayaran Tripay -->
                <div>
                    <label class="block text-xs font-bold text-gray-700 dark:text-slate-300 mb-2">Pilih Channel Pembayaran (Tripay)</label>
                    <div class="space-y-2">
                        <label class="flex items-center justify-between p-3 rounded-xl border cursor-pointer transition-all"
                               :class="method === 'QRIS' ? 'border-primary bg-blue-50/50 dark:bg-slate-800 dark:border-blue-500' : 'border-gray-200 dark:border-slate-700'">
                            <div class="flex items-center gap-3">
                                <input type="radio" name="payment_method" value="QRIS" x-model="method" class="text-primary focus:ring-primary">
                                <div>
                                    <p class="text-xs font-bold text-ink dark:text-slate-100">QRIS (Semua E-Wallet & Mobile Banking)</p>
                                    <p class="text-[10px] text-gray-400">GoPay, OVO, Dana, ShopeePay, LinkAja, BCA, Mandiri, dll</p>
                                </div>
                            </div>
                            <span class="text-[10px] font-bold uppercase bg-emerald-100 text-emerald-700 px-2 py-0.5 rounded-full">QRIS</span>
                        </label>

                        <label class="flex items-center justify-between p-3 rounded-xl border cursor-pointer transition-all"
                               :class="method === 'BRIVA' ? 'border-primary bg-blue-50/50 dark:bg-slate-800 dark:border-blue-500' : 'border-gray-200 dark:border-slate-700'">
                            <div class="flex items-center gap-3">
                                <input type="radio" name="payment_method" value="BRIVA" x-model="method" class="text-primary focus:ring-primary">
                                <div>
                                    <p class="text-xs font-bold text-ink dark:text-slate-100">BRI Virtual Account (BRIVA)</p>
                                    <p class="text-[10px] text-gray-400">Verifikasi Otomatis Tripay</p>
                                </div>
                            </div>
                            <span class="text-[10px] font-bold uppercase bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full">VA BRI</span>
                        </label>

                        <label class="flex items-center justify-between p-3 rounded-xl border cursor-pointer transition-all"
                               :class="method === 'BCAVA' ? 'border-primary bg-blue-50/50 dark:bg-slate-800 dark:border-blue-500' : 'border-gray-200 dark:border-slate-700'">
                            <div class="flex items-center gap-3">
                                <input type="radio" name="payment_method" value="BCAVA" x-model="method" class="text-primary focus:ring-primary">
                                <div>
                                    <p class="text-xs font-bold text-ink dark:text-slate-100">BCA Virtual Account</p>
                                    <p class="text-[10px] text-gray-400">Verifikasi Otomatis Tripay</p>
                                </div>
                            </div>
                            <span class="text-[10px] font-bold uppercase bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full">VA BCA</span>
                        </label>

                        <label class="flex items-center justify-between p-3 rounded-xl border cursor-pointer transition-all"
                               :class="method === 'MANDIRIVA' ? 'border-primary bg-blue-50/50 dark:bg-slate-800 dark:border-blue-500' : 'border-gray-200 dark:border-slate-700'">
                            <div class="flex items-center gap-3">
                                <input type="radio" name="payment_method" value="MANDIRIVA" x-model="method" class="text-primary focus:ring-primary">
                                <div>
                                    <p class="text-xs font-bold text-ink dark:text-slate-100">Mandiri Virtual Account</p>
                                    <p class="text-[10px] text-gray-400">Verifikasi Otomatis Tripay</p>
                                </div>
                            </div>
                            <span class="text-[10px] font-bold uppercase bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full">VA Mandiri</span>
                        </label>
                    </div>
                </div>

                <div class="flex gap-2">
                    <button type="submit" name="action" value="checkout" class="flex-1 py-3 bg-gradient-to-r from-primary to-[#0e7ad6] text-white font-bold rounded-xl text-xs hover:opacity-90 transition shadow-lg shadow-blue-900/20 flex items-center justify-center gap-2">
                        <i class="ph ph-credit-card text-base"></i> Bayar via Tripay
                    </button>
                    <button type="submit" name="action" value="instant_activate" class="py-3 px-3 bg-emerald-600 text-white font-bold rounded-xl text-xs hover:bg-emerald-700 transition flex items-center justify-center gap-1" title="Aktivasi Instan">
                        <i class="ph ph-lightning text-amber-300"></i> Aktifkan Instan
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>

<?php require_once __DIR__ . '/../app/includes/footer.php'; ?>
