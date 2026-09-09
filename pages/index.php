<?php
require_once __DIR__ . '/../app/db.php';

if (empty($_SESSION['user_id'])) {
    require_once __DIR__ . '/landing.php';
    exit;
}

require_once __DIR__ . '/../app/includes/header.php';
require_once __DIR__ . '/../app/includes/sidebar.php';
?>

<?php
// Periode laporan: harian / mingguan / bulanan (default bulanan).
$reportPeriod = $_GET['period'] ?? 'bulanan';
if (!in_array($reportPeriod, ['harian', 'mingguan', 'bulanan'], true)) {
    $reportPeriod = 'bulanan';
}
if ($reportPeriod === 'harian') {
    $periodStart = date('Y-m-d');
    $periodEnd   = date('Y-m-d');
    $periodLabel = 'Hari Ini';
} elseif ($reportPeriod === 'mingguan') {
    $periodStart = date('Y-m-d', strtotime('monday this week'));
    $periodEnd   = date('Y-m-d', strtotime('sunday this week'));
    $periodLabel = 'Minggu Ini';
} else {
    $periodStart = date('Y-m-01');
    $periodEnd   = date('Y-m-t');
    $periodLabel = 'Bulan Ini';
}

// Fetch statistics (sesuai periode terpilih)
$stmt = $pdo->prepare("SELECT 
    SUM(CASE WHEN type = 'PEMASUKAN' THEN amount ELSE 0 END) as total_income,
    SUM(CASE WHEN type = 'PENGELUARAN' THEN amount ELSE 0 END) as total_expense
    FROM transactions WHERE user_id = ? AND transaction_date BETWEEN ? AND ?");
$stmt->execute([$user_id, $periodStart, $periodEnd]);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

$income = $stats['total_income'] ?? 0;
$expense = $stats['total_expense'] ?? 0;

// Saldo akumulatif seumur hidup: pemasukan seluruh waktu dikurangi pengeluaran.
$stmt = $pdo->prepare("SELECT
    COALESCE((SELECT SUM(amount) FROM transactions WHERE user_id = ? AND type = 'PEMASUKAN'), 0)
    + COALESCE((SELECT SUM(amount) FROM transaction_archive WHERE user_id = ? AND type = 'PEMASUKAN'), 0)
    - COALESCE((SELECT SUM(amount) FROM transactions WHERE user_id = ? AND type = 'PENGELUARAN'), 0)
    - COALESCE((SELECT SUM(amount) FROM transaction_archive WHERE user_id = ? AND type = 'PENGELUARAN'), 0) AS lifetime_balance");
$stmt->execute([$user_id, $user_id, $user_id, $user_id]);
$balance = (float) ($stmt->fetchColumn() ?? 0);

// Fetch recent transactions
$stmt = $pdo->prepare("SELECT t.*, c.name as category_name 
    FROM transactions t 
    JOIN categories c ON t.category_id = c.id 
    WHERE t.user_id = ? 
    ORDER BY t.transaction_date DESC, t.created_at DESC LIMIT 5");
$stmt->execute([$user_id]);
$recent_transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Pengingat yang jatuh tempo hari ini (belum selesai).
$stmt = $pdo->prepare("SELECT * FROM reminders WHERE user_id = ? AND done = 0 AND remind_date <= ? ORDER BY remind_date DESC, remind_time ASC LIMIT 8");
$stmt->execute([$user_id, date('Y-m-d')]);
$due_reminders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ---- Data grafik: 6 bulan terakhir (income vs expense) ----
$chartLabels = [];
$chartIncome = [];
$chartExpense = [];
for ($i = 5; $i >= 0; $i--) {
    $start = date('Y-m-01', strtotime("-$i months"));
    $end = date('Y-m-t', strtotime("-$i months"));
    $label = date('M', strtotime("-$i months"));
    $stmt = $pdo->prepare("SELECT
        COALESCE(SUM(CASE WHEN type='PEMASUKAN' THEN amount ELSE 0 END),0) AS inc,
        COALESCE(SUM(CASE WHEN type='PENGELUARAN' THEN amount ELSE 0 END),0) AS exp
        FROM transactions
        WHERE user_id = ? AND transaction_date BETWEEN ? AND ?");
    $stmt->execute([$user_id, $start, $end]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $chartLabels[] = $label;
    $chartIncome[] = (float) $row['inc'];
    $chartExpense[] = (float) $row['exp'];
}

// ---- Data grafik: breakdown pengeluaran per kategori bulan ini ----
$catChartLabels = [];
$catChartValues = [];
$stmt = $pdo->prepare("SELECT c.name AS cat, SUM(t.amount) AS total
    FROM transactions t JOIN categories c ON c.id = t.category_id
    WHERE t.user_id = ? AND t.type = 'PENGELUARAN'
      AND t.transaction_date BETWEEN ? AND ?
    GROUP BY c.name ORDER BY total DESC LIMIT 6");
$stmt->execute([$user_id, date('Y-m-01'), date('Y-m-t')]);
$catRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($catRows as $cr) {
    $catChartLabels[] = $cr['cat'];
    $catChartValues[] = (float) $cr['total'];
}

// ── Net Worth: total saldo semua dompet ──
$stmtNW = $pdo->prepare("
    SELECT COALESCE(SUM(w.starting_balance + COALESCE(tx.net,0)), 0) AS net_worth
    FROM wallets w
    LEFT JOIN (
        SELECT wallet_id, SUM(CASE WHEN type='PEMASUKAN' THEN amount ELSE -amount END) AS net
        FROM transactions WHERE user_id = ? GROUP BY wallet_id
    ) tx ON tx.wallet_id = w.id
    WHERE w.user_id = ?
");
$stmtNW->execute([$user_id, $user_id]);
$netWorth = (float)($stmtNW->fetchColumn() ?? 0);

// ── Budget progress bulan ini ──
$stmtBP = $pdo->prepare("SELECT b.id, b.amount AS limit_amount, c.name AS cat_name,
    COALESCE((SELECT SUM(t2.amount) FROM transactions t2 WHERE t2.category_id = b.category_id
        AND t2.user_id = b.user_id AND t2.type = 'PENGELUARAN'
        AND t2.transaction_date BETWEEN ? AND ?), 0) AS spent
    FROM budgets b JOIN categories c ON c.id = b.category_id
    WHERE b.user_id = ? ORDER BY (spent/b.amount) DESC LIMIT 5");
$stmtBP->execute([date('Y-m-01'), date('Y-m-t'), $user_id]);
$budgetProgress = $stmtBP->fetchAll(PDO::FETCH_ASSOC);

// ── Hutang aktif ──
$stmtDebt = $pdo->prepare("SELECT COUNT(*) AS cnt, COALESCE(SUM(CASE WHEN type='OWE' THEN amount ELSE 0 END),0) AS total_owe
    FROM debts WHERE user_id = ? AND status = 'UNPAID'");
$stmtDebt->execute([$user_id]);
$debtSummary = $stmtDebt->fetch(PDO::FETCH_ASSOC);

// ── Pengingat mendatang (3 hari ke depan) ──
$stmtRem = $pdo->prepare("SELECT * FROM reminders WHERE user_id = ? AND done = 0
    AND remind_date BETWEEN ? AND ? ORDER BY remind_date ASC LIMIT 4");
$stmtRem->execute([$user_id, date('Y-m-d'), date('Y-m-d', strtotime('+3 days'))]);
$upcomingReminders = $stmtRem->fetchAll(PDO::FETCH_ASSOC);
?>

<!-- Pemilih periode laporan -->
<div class="flex flex-wrap items-center justify-between gap-3">
    <div>
        <h2 class="font-display text-lg lg:text-xl font-bold text-ink dark:text-slate-100">Ringkasan Keuangan</h2>
        <p class="text-xs text-gray-400"><?= $periodLabel ?> · <?= htmlspecialchars($periodStart) ?> → <?= htmlspecialchars($periodEnd) ?></p>
    </div>
    <div class="flex gap-1 p-1 bg-white/80 dark:bg-slate-800/80 border border-white/60 rounded-xl shadow-sm">
        <?php $periods = ['harian' => 'Hari Ini', 'mingguan' => 'Minggu Ini', 'bulanan' => 'Bulan Ini'];
        foreach ($periods as $key => $plabel): ?>
        <a href="?period=<?= $key ?>"
           class="px-3 py-1.5 text-xs font-semibold rounded-lg transition-all <?= $reportPeriod === $key ? 'bg-primary text-white shadow-md shadow-blue-900/10' : 'text-gray-500 dark:text-slate-400 hover:text-primary dark:text-blue-400' ?>">
            <?= $plabel ?>
        </a>
        <?php endforeach; ?>
    </div>
</div>

<!-- Kartu Statistik -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 lg:gap-6">
    <!-- Saldo -->
    <div class="relative overflow-hidden bg-gradient-to-br from-primary via-[#0e7ad6] to-[#4ec3f2] dark:from-slate-800/80 dark:via-slate-800/80 dark:to-slate-800/80 dark:border dark:border-slate-700/50 text-white p-5 lg:p-6 rounded-2xl shadow-xl shadow-blue-900/20">
        <div class="absolute -right-6 -top-6 w-32 h-32 bg-white/15 rounded-full"></div>
        <div class="absolute -right-2 bottom-0 w-24 h-24 bg-white/10 rounded-full"></div>
        <div class="relative flex justify-between items-start mb-5">
            <div>
                <p class="text-xs lg:text-sm font-medium text-blue-100 mb-1 flex items-center gap-1.5"><i class="ph ph-wallet"></i> Total Saldo Keseluruhan</p>
                <h3 class="text-2xl lg:text-[28px] font-display font-extrabold tracking-tight">Rp <?= number_format($balance, 0, ',', '.') ?></h3>
            </div>
            <div class="p-3 bg-white/20 rounded-2xl text-white backdrop-blur shadow-inner">
                <i class="ph ph-wallet text-2xl"></i>
            </div>
        </div>
        <div class="relative flex items-center gap-2 text-xs text-blue-50/90">
            <span class="inline-flex items-center gap-1 bg-white/20 px-2 py-1 rounded-full"><i class="ph ph-infinity"></i> Saldo akumulatif seluruh waktu</span>
        </div>
    </div>

    <!-- Pemasukan -->
    <div class="relative overflow-hidden glass-card rounded-2xl shadow-md shadow-blue-900/5 p-5 lg:p-6 border border-white/60 dark:border-slate-700/50 dark:bg-slate-800/80">
        <div class="absolute -right-8 -top-8 w-28 h-28 bg-emerald-400/10 dark:bg-emerald-400/5 rounded-full blur-2xl"></div>
        <div class="relative flex justify-between items-start mb-5">
            <div>
                <p class="text-sm font-medium text-gray-500 dark:text-slate-400 mb-1 flex items-center gap-1.5"><i class="ph ph-trend-up"></i> Pemasukan</p>
                <h3 class="text-2xl lg:text-[28px] font-display font-extrabold tracking-tight text-emerald-600 dark:text-emerald-400">Rp <?= number_format($income, 0, ',', '.') ?></h3>
            </div>
            <div class="p-3 bg-emerald-100 dark:bg-emerald-900/30 rounded-2xl text-emerald-600 dark:text-emerald-400">
                <i class="ph ph-trend-up text-2xl"></i>
            </div>
        </div>
        <div class="relative flex items-center gap-2 text-xs text-emerald-600 dark:text-emerald-400/90">
            <span class="inline-flex items-center gap-1 bg-emerald-50 dark:bg-emerald-900/20 px-2 py-1 rounded-full font-medium"><i class="ph ph-arrow-up-right"></i> Total masuk <?= $periodLabel ?></span>
        </div>
    </div>

    <!-- Pengeluaran -->
    <div class="relative overflow-hidden glass-card rounded-2xl shadow-md shadow-blue-900/5 p-5 lg:p-6 border border-white/60 dark:border-slate-700/50 dark:bg-slate-800/80 sm:col-span-2 lg:col-span-1">
        <div class="absolute -right-8 -top-8 w-28 h-28 bg-rose-400/10 dark:bg-rose-400/5 rounded-full blur-2xl"></div>
        <div class="relative flex justify-between items-start mb-5">
            <div>
                <p class="text-sm font-medium text-gray-500 dark:text-slate-400 mb-1 flex items-center gap-1.5"><i class="ph ph-trend-down"></i> Pengeluaran</p>
                <h3 class="text-2xl lg:text-[28px] font-display font-extrabold tracking-tight text-rose-600 dark:text-rose-400">Rp <?= number_format($expense, 0, ',', '.') ?></h3>
            </div>
            <div class="p-3 bg-rose-100 dark:bg-rose-900/30 rounded-2xl text-rose-600 dark:text-rose-400">
                <i class="ph ph-trend-down text-2xl"></i>
            </div>
        </div>
        <div class="relative flex items-center gap-2 text-xs text-rose-600 dark:text-rose-400/90">
            <span class="inline-flex items-center gap-1 bg-rose-50 dark:bg-rose-900/20 px-2 py-1 rounded-full font-medium"><i class="ph ph-arrow-down-right"></i> Total keluar <?= $periodLabel ?></span>
        </div>
    </div>
</div>

<!-- Grafik -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-4 lg:gap-6">
    <!-- Tren Pemasukan vs Pengeluaran -->
    <div class="lg:col-span-2 glass-card rounded-2xl shadow-md shadow-blue-900/5 p-4 lg:p-6 border border-white/60">
        <div class="flex items-center justify-between mb-5">
            <div>
                <h3 class="text-base lg:text-lg font-display font-bold text-gray-900 dark:text-slate-100">Tren Pemasukan vs Pengeluaran</h3>
                <p class="text-xs text-gray-400 dark:text-slate-500">6 bulan terakhir</p>
            </div>
            <div class="p-2.5 bg-blue-50 dark:bg-blue-900/30 rounded-xl text-primary dark:text-blue-400"><i class="ph ph-chart-bar text-xl"></i></div>
        </div>
        <?php if (array_sum($chartIncome) === 0 && array_sum($chartExpense) === 0): ?>
            <p class="text-sm text-gray-500 dark:text-slate-400 py-10 text-center">Belum ada data transaksi untuk ditampilkan.</p>
        <?php else: ?>
            <div class="relative h-64">
                <canvas id="trendChart"></canvas>
            </div>
        <?php endif; ?>
    </div>

    <!-- Breakdown Pengeluaran -->
    <div class="glass-card rounded-2xl shadow-md shadow-blue-900/5 p-4 lg:p-6 border border-white/60">
        <div class="flex items-center justify-between mb-5">
            <div>
                <h3 class="text-base lg:text-lg font-display font-bold text-gray-900 dark:text-slate-100">Pengeluaran per Kategori</h3>
                <p class="text-xs text-gray-400 dark:text-slate-500">Bulan ini</p>
            </div>
            <div class="p-2.5 bg-violet-50 dark:bg-violet-900/30 rounded-xl text-violet-600 dark:text-violet-400"><i class="ph ph-chart-donut text-xl"></i></div>
        </div>
        <?php if (count($catChartValues) === 0): ?>
            <p class="text-sm text-gray-500 dark:text-slate-400 py-10 text-center">Belum ada pengeluaran bulan ini.</p>
        <?php else: ?>
            <div class="relative h-64">
                <canvas id="categoryChart"></canvas>
            </div>
        <?php endif; ?>
    </div>
</div>

    <!-- 🌟🌟 NEW: Widget row: Budget Progress + Debt Alert + Upcoming Reminders 🌟🌟 -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 lg:gap-6">
    
        <!-- Budget Progress -->
        <div class="lg:col-span-2 relative overflow-hidden glass-card border border-white/60 dark:border-slate-700/50 dark:bg-slate-800/80 rounded-2xl shadow-sm p-5">
            <div class="absolute -left-8 -bottom-8 w-28 h-28 bg-violet-100/70 dark:bg-violet-900/20 rounded-full blur-2xl"></div>
        <div class="relative flex items-center justify-between mb-4">
            <div class="flex items-center gap-2">
                <div class="p-2 bg-violet-100 dark:bg-violet-900/30 rounded-xl text-violet-600 dark:text-violet-400"><i class="ph ph-chart-pie-slice text-lg"></i></div>
                <div>
                    <h3 class="text-sm font-bold text-gray-900 dark:text-slate-100">Progres Anggaran Bulan Ini</h3>
                    <p class="text-xs text-gray-400 dark:text-slate-400">Pemakaian per kategori</p>
                </div>
            </div>
            <a href="budgets.php" class="text-xs text-primary dark:text-blue-400 font-medium hover:underline">Atur →</a>
        </div>
        <?php if (empty($budgetProgress)): ?>
        <p class="text-sm text-gray-400 text-center py-6">Belum ada anggaran diatur. <a href="budgets.php" class="text-primary dark:text-blue-400 hover:underline">Atur sekarang</a></p>
        <?php else: ?>
        <div class="space-y-3 relative">
            <?php foreach ($budgetProgress as $bp):
                $pct = $bp['limit_amount'] > 0 ? min(100, round(($bp['spent'] / $bp['limit_amount']) * 100)) : 0;
                $barColor = $pct >= 90 ? 'bg-rose-50 dark:bg-rose-900/300' : ($pct >= 70 ? 'bg-amber-400' : 'bg-emerald-50 dark:bg-emerald-900/300');
                $textColor = $pct >= 90 ? 'text-rose-600 dark:text-rose-400' : ($pct >= 70 ? 'text-amber-600 dark:text-amber-400' : 'text-emerald-600 dark:text-emerald-400');
            ?>
            <div>
                <div class="flex justify-between text-xs mb-1">
                    <span class="font-medium text-gray-700 dark:text-slate-300"><?= htmlspecialchars($bp['cat_name']) ?></span>
                    <span class="<?= $textColor ?> font-semibold"><?= $pct ?>% · Rp <?= number_format($bp['spent'],0,',','.') ?> / <?= number_format($bp['limit_amount'],0,',','.') ?></span>
                </div>
                <div class="w-full bg-gray-200/50 dark:bg-slate-700/50 rounded-full h-2.5 overflow-hidden">
                    <div class="<?= $barColor ?> h-2.5 rounded-full transition-all" style="width: <?= $pct ?>%"></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- Right column: Debt Alert + Upcoming Reminders -->
    <div class="space-y-4">
        <!-- Debt Alert -->
        <?php if ((int)($debtSummary['cnt'] ?? 0) > 0): ?>
        <a href="debts.php" class="block bg-gradient-to-br from-amber-50 to-orange-50 dark:from-amber-900/20 dark:to-orange-900/20 border border-amber-200 dark:border-amber-700/50 rounded-2xl p-4 hover:shadow-md transition group">
            <div class="flex items-center gap-3">
                <div class="p-2.5 bg-amber-100 dark:bg-amber-900/40 rounded-xl text-amber-600 dark:text-amber-400 group-hover:scale-110 transition-transform">
                    <i class="ph ph-warning text-xl"></i>
                </div>
                <div>
                    <p class="text-sm font-bold text-amber-800 dark:text-amber-400"><?= $debtSummary['cnt'] ?> Hutang Aktif</p>
                    <p class="text-xs text-amber-600 dark:text-amber-500 dark:text-amber-400">Total: Rp <?= number_format($debtSummary['total_owe'],0,',','.') ?></p>
                </div>
                <i class="ph ph-arrow-right ml-auto text-amber-500 dark:text-amber-400 group-hover:translate-x-1 transition-transform"></i>
            </div>
        </a>
        <?php endif; ?>

        <!-- Upcoming Reminders -->
        <div class="glass-card border border-white/60 dark:border-slate-700/50 dark:bg-slate-800/80 rounded-2xl p-4 shadow-sm">
            <div class="flex items-center justify-between mb-3">
                <div class="flex items-center gap-2">
                    <div class="p-2 bg-blue-50 dark:bg-blue-900/30 rounded-xl text-primary dark:text-blue-400"><i class="ph ph-clock text-base"></i></div>
                    <p class="text-sm font-bold text-gray-900 dark:text-slate-100">Pengingat Mendatang</p>
                </div>
                <a href="reminders.php" class="text-xs text-primary dark:text-blue-400 hover:underline">Lihat semua →</a>
            </div>
            <?php if (empty($upcomingReminders)): ?>
            <p class="text-xs text-gray-400 dark:text-slate-500 text-center py-3">Tidak ada pengingat dalam 3 hari ke depan.</p>
            <?php else: ?>
            <div class="space-y-2">
                <?php foreach ($upcomingReminders as $r):
                    $isToday = $r['remind_date'] === date('Y-m-d');
                    $isTomorrow = $r['remind_date'] === date('Y-m-d', strtotime('+1 day'));
                    $dayLabel = $isToday ? '🔴 Hari ini' : ($isTomorrow ? '🟡 Besok' : '📅 ' . $r['remind_date']);
                ?>
                <div class="flex items-center gap-2 p-2 rounded-xl bg-gray-50 dark:bg-slate-700/50">
                    <div class="flex-1 min-w-0">
                        <p class="text-xs font-medium text-gray-800 dark:text-slate-200 truncate"><?= htmlspecialchars($r['title']) ?></p>
                        <p class="text-[11px] text-gray-400 dark:text-slate-500"><?= $dayLabel ?></p>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Pengingat Hari Ini -->
<?php if (count($due_reminders) > 0): ?>
<div class="glass-card rounded-2xl shadow-md shadow-blue-900/5 border border-white/60 p-4 lg:p-6">
    <div class="flex items-center justify-between mb-4">
        <div class="flex items-center gap-2">
            <div class="p-2.5 bg-amber-50 dark:bg-amber-900/30 rounded-xl text-amber-600 dark:text-amber-400"><i class="ph ph-bell-ringing text-xl"></i></div>
            <div>
                <h3 class="text-base lg:text-lg font-display font-bold text-ink dark:text-slate-100">Pengingat Hari Ini</h3>
                <p class="text-xs text-gray-400">Jangan sampai terlewat</p>
            </div>
        </div>
        <a href="reminders.php" class="inline-flex items-center gap-1 text-sm text-primary dark:text-blue-400 font-medium hover:gap-2 transition-all">Atur <i class="ph ph-arrow-right"></i></a>
    </div>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
        <?php $remTypeBadge = ['catat_transaksi' => 'text-blue-600 dark:text-blue-400 bg-blue-50 dark:bg-blue-900/30 ph-pencil-simple-line', 'bayar_tagihan' => 'text-rose-600 dark:text-rose-400 bg-rose-50 dark:bg-rose-900/30 ph-receipt', 'note' => 'text-amber-600 dark:text-amber-400 bg-amber-50 dark:bg-amber-900/30 ph-note'];
        foreach ($due_reminders as $rm): $rb = $remTypeBadge[$rm['type']] ?? $remTypeBadge['note']; ?>
        <div class="flex items-center gap-3 p-3 rounded-xl bg-white/70 dark:bg-slate-800/70 border border-gray-100 dark:border-slate-700/50">
            <span class="w-9 h-9 rounded-lg flex items-center justify-center text-base <?= $rb ?>"><i class="ph <?= explode(' ', $rb)[2] ?>"></i></span>
            <div class="min-w-0 flex-1">
                <p class="text-sm font-semibold text-ink dark:text-slate-100 truncate"><?= htmlspecialchars($rm['title']) ?></p>
                <p class="text-[11px] text-gray-400"><?= htmlspecialchars(date('d M', strtotime($rm['remind_date']))) ?><?= $rm['remind_time'] ? ' · ' . htmlspecialchars($rm['remind_time']) : '' ?></p>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- Bagian Bawah: Tabel & Info Bot (Responsive Grid) -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-4 lg:gap-6">
    
    <!-- Transaksi Terbaru -->
    <div class="lg:col-span-2 glass-card rounded-2xl shadow-md shadow-blue-900/5 border border-white/60 overflow-hidden">
        <div class="p-4 lg:p-6 border-b border-gray-100 dark:border-slate-700/50 flex justify-between items-center bg-white/60 dark:bg-slate-800/60">
            <div class="flex items-center gap-2">
                <h3 class="text-base lg:text-lg font-display font-bold text-ink dark:text-slate-100">Transaksi Terbaru</h3>
            </div>
            <a href="transactions.php" class="inline-flex items-center gap-1 text-sm text-primary dark:text-blue-400 font-medium hover:gap-2 transition-all">
                Lihat Semua <i class="ph ph-arrow-right"></i>
            </a>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse min-w-[500px]">
                <thead>
                    <tr class="text-gray-400 text-xs uppercase tracking-wider">
                        <th class="p-4 font-medium">Kategori</th>
                        <th class="p-4 font-medium">Keterangan</th>
                        <th class="p-4 font-medium text-right">Nominal</th>
                    </tr>
                </thead>
                <tbody class="text-sm divide-y divide-gray-50 dark:divide-slate-700/50">
                    <?php if (count($recent_transactions) === 0): ?>
                    <tr><td colspan="3" class="p-8 text-center text-gray-400">Belum ada transaksi.</td></tr>
                    <?php endif; ?>
                    <?php foreach($recent_transactions as $t): ?>
                    <tr class="hover:bg-white/80 dark:bg-slate-800/80 transition-colors">
                        <td class="p-4">
                            <span class="inline-flex items-center gap-2 font-semibold text-ink dark:text-slate-100">
                                <span class="w-8 h-8 rounded-lg <?= $t['type'] == 'PEMASUKAN' ? 'bg-emerald-50 dark:bg-emerald-900/30 text-emerald-600 dark:text-emerald-400' : 'bg-rose-50 dark:bg-rose-900/30 text-rose-500 dark:text-rose-400' ?> flex items-center justify-center">
                                    <i class="ph <?= $t['type'] == 'PEMASUKAN' ? 'ph-trend-up' : 'ph-trend-down' ?>"></i>
                                </span>
                                <?= htmlspecialchars($t['category_name']) ?>
                            </span>
                        </td>
                        <td class="p-4 text-gray-500 dark:text-slate-400">
                            <?= htmlspecialchars($t['description']) ?><br>
                            <span class="text-xs text-gray-400"><?= date('d M Y', strtotime($t['transaction_date'])) ?></span>
                        </td>
                        <td class="p-4 text-right font-bold <?= $t['type'] == 'PEMASUKAN' ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400' ?>">
                            <?= $t['type'] == 'PEMASUKAN' ? '+' : '-' ?> Rp <?= number_format($t['amount'], 0, ',', '.') ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Info Bot -->
    <div class="relative overflow-hidden glass-card border border-white/60 dark:border-slate-700/50 bg-gradient-to-br from-[#0A58A5] via-[#0e7ad6] to-[#4ec3f2] dark:from-slate-800/80 dark:via-slate-800/80 dark:to-slate-800/80 rounded-2xl shadow-xl shadow-blue-900/20 flex flex-col text-white dark:text-slate-100">
        <div class="absolute -right-10 -top-10 w-44 h-44 bg-white/15 rounded-full"></div>
        <div class="absolute -left-6 bottom-10 w-28 h-28 bg-white/10 rounded-full"></div>
        <div class="p-4 lg:p-6 border-b border-white/15 flex items-center gap-3">
            <div class="w-11 h-11 rounded-xl bg-white/20 flex items-center justify-center backdrop-blur shadow-inner">
                <i class="ph ph-telegram-logo text-xl"></i>
            </div>
            <div>
                <h3 class="text-base lg:text-lg font-display font-bold">Bot Financial Assistant</h3>
                <p class="text-xs text-blue-100/80">Catat keuangan lewat chat</p>
            </div>
        </div>
        <div class="p-4 lg:p-6 flex-1 flex flex-col gap-4">
            <div class="space-y-3 text-sm">
                <p class="flex items-center gap-2.5 text-blue-50"><i class="ph ph-chat-circle-dots text-lg text-amber-300"></i> Kirim pesan seperti <b class="text-white">"makan 50000"</b></p>
                <p class="flex items-center gap-2.5 text-blue-50"><i class="ph ph-magnifying-glass text-lg text-amber-300"></i> Ketik <b class="text-white">/saldo</b> atau <b class="text-white">/riwayat</b> kapan saja.</p>
            </div>
            <a href="<?= htmlspecialchars(PahamFin_tg_link()) ?>" target="_blank" rel="noopener"
               class="mt-auto inline-flex items-center justify-center gap-2 w-full py-3 bg-white dark:bg-primary text-[#0A58A5] dark:text-white rounded-xl font-bold hover:bg-blue-50 dark:hover:bg-blue-600 hover:gap-3 transition-all shadow-lg">
                <i class="ph ph-paper-plane-tilt"></i> Buka Bot Telegram
            </a>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script>
(function () {
    const isDark = document.documentElement.classList.contains('dark');
    const textColor = isDark ? '#94a3b8' : '#64748b';
    const gridColor = isDark ? 'rgba(255,255,255,0.05)' : 'rgba(0,0,0,0.05)';
    Chart.defaults.color = textColor;

    <?php if (array_sum($chartIncome) > 0 || array_sum($chartExpense) > 0): ?>
    var labels = <?= json_encode($chartLabels) ?>;
    var income = <?= json_encode($chartIncome) ?>;
    var expense = <?= json_encode($chartExpense) ?>;
    new Chart(document.getElementById('trendChart'), {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [
                { label: 'Pemasukan', data: income, backgroundColor: 'rgba(16, 185, 129, 0.85)', borderRadius: 8, maxBarThickness: 28 },
                { label: 'Pengeluaran', data: expense, backgroundColor: 'rgba(244, 63, 94, 0.85)', borderRadius: 8, maxBarThickness: 28 }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom', labels: { usePointStyle: true, boxWidth: 8, color: textColor } }
            },
            scales: {
                y: { grid: { color: gridColor }, ticks: { color: textColor, callback: function (v) { return 'Rp' + (v / 1000) + 'k'; } } },
                x: { grid: { display: false }, ticks: { color: textColor } }
            }
        }
    });
    <?php endif; ?>

    <?php if (count($catChartValues) > 0): ?>
    var catLabels = <?= json_encode($catChartLabels) ?>;
    var vals = <?= json_encode($catChartValues) ?>;
    var colors = ['#0ea5e9', '#10b981', '#f43f5e', '#f59e0b', '#8b5cf6', '#06b6d4'];
    new Chart(document.getElementById('categoryChart'), {
        type: 'doughnut',
        data: {
            labels: catLabels,
            datasets: [{ data: vals, backgroundColor: colors, borderWidth: isDark ? 2 : 0, borderColor: isDark ? '#1e293b' : '#ffffff' }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '70%',
            plugins: {
                legend: { position: 'bottom', labels: { usePointStyle: true, boxWidth: 8, color: textColor } }
            }
        }
    });
    <?php endif; ?>

    // Handle dark mode toggle dynamically for charts by listening to the alpine dark mode toggle
    // A simple reload is used to redraw the charts with correct theme colors
    const originalToggle = window.appRoot ? window.appRoot.toggleDark : null;
    window.addEventListener('storage', (e) => {
        if (e.key === 'PahamFin_dark') {
            window.location.reload();
        }
    });
})();
</script>







<?php require_once __DIR__ . '/../app/includes/footer.php'; ?>

