<?php require_once __DIR__ . '/../app/includes/header.php'; ?>
<?php require_once __DIR__ . '/../app/includes/sidebar.php'; ?>

<?php
$selectedPeriod = trim((string) ($_GET['period'] ?? ''));

// Daftar periode yang punya arsip (bulan).
$stmt = $pdo->prepare("SELECT period, 
    COALESCE(SUM(CASE WHEN type = 'PEMASUKAN' THEN amount ELSE 0 END), 0) AS inc,
    COALESCE(SUM(CASE WHEN type = 'PENGELUARAN' THEN amount ELSE 0 END), 0) AS exp
    FROM transaction_archive WHERE user_id = ? GROUP BY period ORDER BY period DESC");
$stmt->execute([$user_id]);
$periods = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Jika belum pilih, ambil periode terbaru.
if ($selectedPeriod === '' && count($periods) > 0) {
    $selectedPeriod = $periods[0]['period'];
}

$rows = [];
if ($selectedPeriod !== '') {
    $stmt = $pdo->prepare(
        "SELECT a.transaction_date, a.type, COALESCE(c.name, 'Tanpa kategori') AS category, a.description, a.amount
         FROM transaction_archive a
         LEFT JOIN categories c ON c.id = a.category_id
         WHERE a.user_id = ? AND a.period = ?
         ORDER BY a.transaction_date DESC, a.id DESC"
    );
    $stmt->execute([$user_id, $selectedPeriod]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<div class="flex items-center justify-between gap-4 flex-wrap">
    <div>
        <h3 class="text-lg lg:text-xl font-display font-bold text-ink dark:text-slate-100">Riwayat Bulanan</h3>
        <p class="text-sm text-gray-400">Pantau transaksi dari bulan-bulan sebelumnya (sudah diarsipkan).</p>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-[280px_1fr] gap-6">
    <!-- Daftar bulan -->
    <div class="glass-card rounded-2xl shadow-md shadow-blue-900/5 border border-white/60 h-fit p-4">
        <h4 class="text-sm font-semibold text-ink dark:text-slate-100 mb-3 px-1">Pilih Bulan</h4>
        <?php if (count($periods) === 0): ?>
            <p class="text-sm text-gray-400 px-1">Belum ada arsip bulanan. Data dari bulan lalu otomatis masuk ke sini saat bulan berganti.</p>
        <?php else: ?>
            <div class="space-y-1.5">
                <?php foreach ($periods as $p):
                    $label = date('F Y', strtotime($p['period'] . '-01'));
                    $isActive = ($p['period'] === $selectedPeriod);
                    $net = $p['inc'] - $p['exp'];
                ?>
                <a href="history.php?period=<?= htmlspecialchars($p['period']) ?>"
                   class="flex items-center justify-between px-3 py-2.5 rounded-xl text-sm transition-all <?= $isActive ? 'bg-primary text-white shadow-md shadow-blue-900/10' : 'bg-white/70 dark:bg-slate-800/70 text-gray-700 dark:text-slate-300 hover:bg-white dark:bg-slate-800' ?>">
                    <span class="font-medium"><?= htmlspecialchars($label) ?></span>
                    <span class="text-xs font-bold <?= $isActive ? 'text-amber-300' : ($net >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400') ?>">Rp <?= number_format($net, 0, ',', '.') ?></span>
                </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Detail bulan terpilih -->
    <div class="glass-card rounded-2xl shadow-md shadow-blue-900/5 border border-white/60 overflow-hidden">
        <div class="p-4 lg:p-6 border-b border-gray-100 dark:border-slate-700/50 bg-white/60 dark:bg-slate-800/60 flex justify-between items-center flex-wrap gap-3">
            <div>
                <h4 class="text-base lg:text-lg font-display font-bold text-ink dark:text-slate-100">
                    <?= $selectedPeriod ? htmlspecialchars(date('F Y', strtotime($selectedPeriod . '-01'))) : 'Pilih bulan terlebih dahulu' ?>
                </h4>
                <?php if ($selectedPeriod !== ''): ?>
                    <?php
                    $selInc = 0; $selExp = 0;
                    foreach ($rows as $r) { if ($r['type'] === 'PEMASUKAN') $selInc += $r['amount']; else $selExp += $r['amount']; }
                    ?>
                    <p class="text-xs text-gray-400 mt-1">
                        Pemasukan <span class="text-emerald-600 dark:text-emerald-400 font-semibold">Rp <?= number_format($selInc, 0, ',', '.') ?></span>
                        · Pengeluaran <span class="text-rose-600 dark:text-rose-400 font-semibold">Rp <?= number_format($selExp, 0, ',', '.') ?></span>
                    </p>
                <?php endif; ?>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse min-w-[520px]">
                <thead>
                    <tr class="text-gray-400 text-xs uppercase tracking-wider">
                        <th class="p-4 font-medium">Tanggal</th>
                        <th class="p-4 font-medium">Kategori</th>
                        <th class="p-4 font-medium">Keterangan</th>
                        <th class="p-4 font-medium text-right">Nominal</th>
                    </tr>
                </thead>
                <tbody class="text-sm divide-y divide-gray-50 dark:divide-slate-700/50">
                    <?php if (count($rows) === 0): ?>
                        <tr><td colspan="4" class="p-8 text-center text-gray-400">Tidak ada transaksi untuk bulan ini.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($rows as $r): ?>
                    <tr class="hover:bg-white/80 dark:bg-slate-800/80 transition-colors">
                        <td class="p-4 text-gray-500 dark:text-slate-400 whitespace-nowrap"><?= htmlspecialchars($r['transaction_date']) ?></td>
                        <td class="p-4">
                            <span class="inline-flex items-center gap-2 font-semibold text-ink dark:text-slate-100">
                                <span class="w-8 h-8 rounded-lg <?= $r['type'] == 'PEMASUKAN' ? 'bg-emerald-50 dark:bg-emerald-900/30 text-emerald-600 dark:text-emerald-400' : 'bg-rose-50 dark:bg-rose-900/30 text-rose-500 dark:text-rose-400' ?> flex items-center justify-center">
                                    <i class="ph <?= $r['type'] == 'PEMASUKAN' ? 'ph-trend-up' : 'ph-trend-down' ?>"></i>
                                </span>
                                <?= htmlspecialchars($r['category']) ?>
                            </span>
                        </td>
                        <td class="p-4 text-gray-500 dark:text-slate-400"><?= htmlspecialchars($r['description']) ?></td>
                        <td class="p-4 text-right font-bold <?= $r['type'] == 'PEMASUKAN' ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400' ?>">
                            <?= $r['type'] == 'PEMASUKAN' ? '+' : '-' ?> Rp <?= number_format($r['amount'], 0, ',', '.') ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../app/includes/footer.php'; ?>







