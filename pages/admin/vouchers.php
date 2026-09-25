<?php
require_once __DIR__ . '/../../app/db.php';
require_once __DIR__ . '/../../app/includes/auth.php';
require_admin($pdo);

$successMsg = null;
$errorMsg = null;
$generatedCodes = [];

// Handle Generate Voucher
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'generate_vouchers') {
    if (!PahamFin_csrf_verify()) {
        $errorMsg = 'Sesi CSRF tidak valid.';
    } else {
        $planId = (int) ($_POST['plan_id'] ?? 0);
        $count  = max(1, min(100, (int) ($_POST['count'] ?? 1)));
        $note   = trim($_POST['note'] ?? '');

        $res = PahamFin_generate_vouchers($pdo, $planId, $count, $note);
        if ($res['success']) {
            $generatedCodes = $res['codes'];
            $successMsg = "Berhasil membuat {$count} kode voucher baru!";
        } else {
            $errorMsg = $res['message'];
        }
    }
}

// Ambil paket langganan aktif
$plans = PahamFin_get_subscription_plans($pdo, false);

// Ambil daftar voucher
$vouchers = $pdo->query("
    SELECT v.*, p.name AS plan_name, u.name AS user_name, u.email AS user_email
    FROM voucher_codes v
    JOIN subscription_plans p ON v.plan_id = p.id
    LEFT JOIN users u ON v.used_by = u.id
    ORDER BY v.id DESC
")->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/../../app/includes/header.php';
require_once __DIR__ . '/../../app/includes/sidebar.php';
?>

<div class="space-y-6">

    <!-- Header Section -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-xl lg:text-2xl font-display font-extrabold text-ink dark:text-slate-100 flex items-center gap-2">
                <i class="ph ph-ticket text-amber-500 text-2xl lg:text-3xl"></i> Kelola Kode Voucher / Shopee Redeem
            </h1>
            <p class="text-xs lg:text-sm text-gray-500 dark:text-slate-400 mt-1">
                Buat kode voucher untuk dijual di Shopee atau marketplace lain. Pengguna bisa melakukan redeem kode saat aktivasi akun.
            </p>
        </div>
    </div>

    <?php if ($successMsg): ?>
        <div class="p-4 rounded-2xl bg-emerald-50 dark:bg-emerald-900/30 border border-emerald-200 dark:border-emerald-800 text-emerald-800 dark:text-emerald-200 text-sm flex items-start gap-3">
            <i class="ph ph-check-circle text-xl text-emerald-600 dark:text-emerald-400 shrink-0 mt-0.5"></i>
            <div>
                <p class="font-bold"><?= htmlspecialchars($successMsg) ?></p>
                <?php if (!empty($generatedCodes)): ?>
                    <p class="text-xs text-emerald-700 dark:text-emerald-300 mt-1">Kode baru yang dibuat:</p>
                    <div class="mt-2 flex flex-wrap gap-2">
                        <?php foreach ($generatedCodes as $c): ?>
                            <span class="px-2.5 py-1 bg-white dark:bg-slate-800 border border-emerald-300 dark:border-emerald-700 rounded-lg text-xs font-mono font-bold text-emerald-900 dark:text-emerald-100 select-all">
                                <?= htmlspecialchars($c) ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($errorMsg): ?>
        <div class="p-4 rounded-2xl bg-rose-50 dark:bg-rose-900/30 border border-rose-200 dark:border-rose-800 text-rose-700 dark:text-rose-300 text-sm flex items-center gap-3">
            <i class="ph ph-warning-circle text-xl shrink-0"></i>
            <div><?= htmlspecialchars($errorMsg) ?></div>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- Form Generate Voucher -->
        <div class="lg:col-span-1">
            <div class="glass-card rounded-2xl border border-white/60 dark:border-slate-700/50 shadow-sm overflow-hidden">
                <div class="p-4 lg:p-5 border-b border-gray-100 dark:border-slate-700/50 bg-white/60 dark:bg-slate-800/60 flex items-center gap-3">
                    <div class="p-2 bg-amber-50 dark:bg-amber-900/30 text-amber-600 dark:text-amber-400 rounded-xl">
                        <i class="ph ph-plus-circle text-xl"></i>
                    </div>
                    <div>
                        <h3 class="font-bold text-sm text-ink dark:text-slate-100">Buat Voucher Baru</h3>
                        <p class="text-[11px] text-gray-400">Generate voucher satuan atau batch</p>
                    </div>
                </div>

                <form method="POST" class="p-4 lg:p-5 space-y-4">
                    <?= PahamFin_csrf_field() ?>
                    <input type="hidden" name="action" value="generate_vouchers">

                    <div>
                        <label class="block text-xs font-semibold text-gray-700 dark:text-slate-300 mb-1.5">Pilih Paket Langganan</label>
                        <select name="plan_id" required class="w-full px-3.5 py-2.5 bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-xl text-xs font-medium">
                            <?php foreach ($plans as $p): ?>
                                <option value="<?= $p['id'] ?>">
                                    <?= htmlspecialchars($p['name']) ?> (Rp <?= number_format($p['price'], 0, ',', '.') ?> - <?= $p['duration_days'] ?> Hari)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-700 dark:text-slate-300 mb-1.5">Jumlah Kode Dibuat</label>
                        <input type="number" name="count" value="1" min="1" max="100" required
                               class="w-full px-3.5 py-2.5 bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-xl text-xs font-medium">
                        <p class="text-[10px] text-gray-400 mt-1">Maksimal 100 kode sekali generate.</p>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-700 dark:text-slate-300 mb-1.5">Catatan / Label Batch (Opsional)</label>
                        <input type="text" name="note" placeholder="Misal: Batch Shopee Promo 10.10"
                               class="w-full px-3.5 py-2.5 bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-xl text-xs">
                    </div>

                    <button type="submit" class="w-full py-2.5 bg-amber-500 hover:bg-amber-600 text-white font-bold text-xs rounded-xl transition shadow-md shadow-amber-500/20 flex items-center justify-center gap-2">
                        <i class="ph ph-ticket text-base"></i> Generate Voucher
                    </button>
                </form>
            </div>
        </div>

        <!-- Tabel Daftar Voucher -->
        <div class="lg:col-span-2">
            <div class="glass-card rounded-2xl border border-white/60 dark:border-slate-700/50 shadow-sm overflow-hidden">
                <div class="p-4 lg:p-5 border-b border-gray-100 dark:border-slate-700/50 bg-white/60 dark:bg-slate-800/60 flex items-center justify-between">
                    <div>
                        <h3 class="font-bold text-sm text-ink dark:text-slate-100">Daftar Kode Voucher</h3>
                        <p class="text-[11px] text-gray-400">Total <?= count($vouchers) ?> voucher terdaftar</p>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="border-b border-gray-100 dark:border-slate-700/50 text-[11px] font-bold uppercase tracking-wider text-gray-400">
                                <th class="p-3.5 pl-5">Kode Voucher</th>
                                <th class="p-3.5">Paket</th>
                                <th class="p-3.5">Status</th>
                                <th class="p-3.5">Digunakan Oleh</th>
                                <th class="p-3.5 pr-5">Catatan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-slate-700/50 text-xs">
                            <?php if (empty($vouchers)): ?>
                                <tr>
                                    <td colspan="5" class="p-8 text-center text-gray-400">
                                        Belum ada kode voucher yang dibuat.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($vouchers as $v): 
                                    $isUsed = (int)$v['is_used'] === 1;
                                ?>
                                    <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/30 transition">
                                        <td class="p-3.5 pl-5">
                                            <span class="font-mono font-bold text-slate-900 dark:text-slate-100 bg-slate-100 dark:bg-slate-800 px-2 py-1 rounded select-all border border-slate-200 dark:border-slate-700">
                                                <?= htmlspecialchars($v['code']) ?>
                                            </span>
                                        </td>
                                        <td class="p-3.5 font-medium text-slate-700 dark:text-slate-300">
                                            <?= htmlspecialchars($v['plan_name']) ?>
                                        </td>
                                        <td class="p-3.5">
                                            <?php if ($isUsed): ?>
                                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-extrabold bg-rose-100 dark:bg-rose-900/40 text-rose-700 dark:text-rose-300">
                                                    <i class="ph ph-check-circle"></i> Terpakai
                                                </span>
                                            <?php else: ?>
                                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-extrabold bg-emerald-100 dark:bg-emerald-900/40 text-emerald-700 dark:text-emerald-300">
                                                    <i class="ph ph-tag"></i> Ready
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="p-3.5 text-gray-500 dark:text-slate-400">
                                            <?php if ($isUsed && !empty($v['user_name'])): ?>
                                                <div>
                                                    <span class="font-semibold text-slate-800 dark:text-slate-200"><?= htmlspecialchars($v['user_name']) ?></span>
                                                    <div class="text-[10px] text-gray-400"><?= htmlspecialchars(date('d M Y H:i', strtotime($v['used_at']))) ?></div>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-gray-400">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="p-3.5 pr-5 text-gray-500 dark:text-slate-400 max-w-[150px] truncate" title="<?= htmlspecialchars($v['note'] ?? '') ?>">
                                            <?= htmlspecialchars($v['note'] ?: '-') ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>

</div>

<?php require_once __DIR__ . '/../../app/includes/footer.php'; ?>
