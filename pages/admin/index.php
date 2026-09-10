<?php
require_once __DIR__ . '/../../app/db.php';
require_once __DIR__ . '/../../app/includes/auth.php';
require_admin($pdo);

require_once __DIR__ . '/../../app/includes/header.php';
require_once __DIR__ . '/../../app/includes/sidebar.php';

// ---- Statistik Inti Sistem (Agregat Anonim Tanpa Rincian Privasi User) ----
$totalUsers = (int) ($pdo->query("SELECT COUNT(*) FROM users")->fetchColumn() ?? 0);
$adminUsers = (int) ($pdo->query("SELECT COUNT(*) FROM users WHERE LOWER(role) = 'admin'")->fetchColumn() ?? 0);

$newUsersStmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE created_at >= ?");
$newUsersStmt->execute([date('Y-m-01') . ' 00:00:00']);
$newUsers = (int) $newUsersStmt->fetchColumn();

// Stat Telegram Integration
$tgUsers = (int) ($pdo->query("SELECT COUNT(*) FROM users WHERE telegram_id IS NOT NULL AND telegram_id != ''")->fetchColumn() ?? 0);
$tgPct = $totalUsers > 0 ? round(($tgUsers / $totalUsers) * 100, 1) : 0;

// Total Catatan Transaksi Sistem (Agregat Jumlah)
$totalTxCount = (int) ($pdo->query("SELECT COUNT(*) FROM transactions")->fetchColumn() ?? 0);

// ---- Pertumbuhan Pengguna (6 bulan) ----
$regLabels = [];
$regValues = [];
for ($i = 5; $i >= 0; $i--) {
    $start = date('Y-m-01 00:00:00', strtotime("-$i months"));
    $end = date('Y-m-t 23:59:59', strtotime("-$i months"));
    $regLabels[] = date('M', strtotime("-$i months"));
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE created_at BETWEEN ? AND ?");
    $stmt->execute([$start, $end]);
    $regValues[] = (int) $stmt->fetchColumn();
}

// ---- Tabel: pengguna terbaru ----
$recentUsers = $pdo->query(
    "SELECT id, name, email, phone_number, role, telegram_id, created_at
     FROM users ORDER BY created_at DESC, id DESC LIMIT 8"
)->fetchAll(PDO::FETCH_ASSOC);
?>

<!-- ===================== HEADER & BREADCRUMB ===================== -->
<div class="flex flex-wrap items-center justify-between gap-4 mb-6">
    <div>
        <span class="text-xs font-semibold text-amber-500 uppercase tracking-widest flex items-center gap-1.5">
            <i class="ph ph-shield-check text-base"></i> System Administration
        </span>
        <h2 class="text-xl lg:text-2xl font-display font-extrabold text-gray-900 dark:text-slate-100 mt-1">Admin Command Center</h2>
    </div>
    <div class="flex items-center gap-2">
        <a href="users.php" class="px-4 py-2 bg-primary text-white rounded-xl text-xs font-semibold hover:bg-[#0e7ad6] transition shadow-md shadow-blue-900/10 flex items-center gap-1.5">
            <i class="ph ph-users-three text-sm"></i> Kelola Pengguna
        </a>
        <a href="settings.php" class="px-4 py-2 border border-gray-200 dark:border-slate-700 rounded-xl text-xs font-semibold text-gray-600 dark:text-slate-300 hover:bg-gray-50 dark:hover:bg-slate-700/50 transition flex items-center gap-1.5">
            <i class="ph ph-gear text-sm"></i> Pengaturan System
        </a>
    </div>
</div>

<!-- ===================== 4 CARDS COMMAND CENTER ===================== -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 lg:gap-6">
    <!-- Total Pengguna -->
    <div class="relative overflow-hidden bg-gradient-to-br from-primary via-[#0e7ad6] to-[#4ec3f2] text-white p-5 rounded-2xl shadow-xl shadow-blue-900/20">
        <div class="absolute -right-6 -top-6 w-28 h-28 bg-white/15 rounded-full pointer-events-none"></div>
        <div class="relative">
            <p class="text-xs font-medium text-blue-100 mb-1 flex items-center gap-1.5"><i class="ph ph-users-three text-base"></i> Total Pengguna</p>
            <h3 class="text-2xl lg:text-[28px] font-display font-extrabold tracking-tight"><?= number_format($totalUsers, 0, ',', '.') ?></h3>
            <p class="text-[11px] text-blue-100 mt-2 font-medium bg-white/15 inline-block px-2.5 py-1 rounded-full"><i class="ph ph-trend-up"></i> <?= $newUsers ?> pendaftar bulan ini</p>
        </div>
    </div>

    <!-- Total Admin -->
    <div class="relative overflow-hidden glass-card rounded-2xl shadow-md shadow-blue-900/5 p-5 border border-white/60 dark:border-slate-700/50 dark:bg-slate-800/80">
        <div class="absolute -right-8 -top-8 w-28 h-28 bg-amber-400/10 dark:bg-amber-400/5 rounded-full blur-2xl pointer-events-none"></div>
        <div class="relative">
            <p class="text-xs font-medium text-gray-500 dark:text-slate-400 mb-1 flex items-center gap-1.5"><i class="ph ph-shield-check text-base text-amber-500"></i> Administrator</p>
            <h3 class="text-2xl lg:text-[28px] font-display font-extrabold text-amber-600 dark:text-amber-400"><?= number_format($adminUsers, 0, ',', '.') ?></h3>
            <p class="text-[11px] text-gray-400 dark:text-slate-500 mt-2">Akses pengelolaan penuh</p>
        </div>
    </div>

    <!-- Telegram Integration -->
    <div class="relative overflow-hidden glass-card rounded-2xl shadow-md shadow-blue-900/5 p-5 border border-white/60 dark:border-slate-700/50 dark:bg-slate-800/80">
        <div class="absolute -right-8 -top-8 w-28 h-28 bg-sky-400/10 dark:bg-sky-400/5 rounded-full blur-2xl pointer-events-none"></div>
        <div class="relative">
            <p class="text-xs font-medium text-gray-500 dark:text-slate-400 mb-1 flex items-center gap-1.5"><i class="ph ph-telegram-logo text-base text-sky-500"></i> Telegram Bot Link</p>
            <h3 class="text-2xl lg:text-[28px] font-display font-extrabold text-sky-600 dark:text-sky-400"><?= $tgUsers ?> <span class="text-xs font-semibold text-gray-400">(<?= $tgPct ?>%)</span></h3>
            <p class="text-[11px] text-sky-700 dark:text-sky-400 mt-2 font-medium bg-sky-50 dark:bg-sky-900/30 inline-block px-2.5 py-1 rounded-full">@<?= htmlspecialchars(PahamFin_TELEGRAM_BOT_USERNAME) ?></p>
        </div>
    </div>

    <!-- Total Catatan Sistem -->
    <div class="relative overflow-hidden glass-card rounded-2xl shadow-md shadow-blue-900/5 p-5 border border-white/60 dark:border-slate-700/50 dark:bg-slate-800/80">
        <div class="absolute -right-8 -top-8 w-28 h-28 bg-emerald-400/10 dark:bg-emerald-400/5 rounded-full blur-2xl pointer-events-none"></div>
        <div class="relative">
            <p class="text-xs font-medium text-gray-500 dark:text-slate-400 mb-1 flex items-center gap-1.5"><i class="ph ph-database text-base text-emerald-500"></i> Transaksi Sistem</p>
            <h3 class="text-2xl lg:text-[28px] font-display font-extrabold text-emerald-600 dark:text-emerald-400"><?= number_format($totalTxCount, 0, ',', '.') ?></h3>
            <p class="text-[11px] text-gray-400 dark:text-slate-500 mt-2">Total entri tercatat di sistem</p>
        </div>
    </div>
</div>

<!-- ===================== SYSTEM HEALTH STATUS BAR ===================== -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-6">
    <div class="glass-card border border-white/60 dark:border-slate-700/50 dark:bg-slate-800/80 rounded-2xl p-4 flex items-center gap-3">
        <div class="w-10 h-10 rounded-xl bg-emerald-100 dark:bg-emerald-900/40 text-emerald-600 dark:text-emerald-400 flex items-center justify-center shrink-0">
            <i class="ph ph-hard-drives text-xl"></i>
        </div>
        <div class="min-w-0">
            <p class="text-xs text-gray-400 dark:text-slate-400">Database Driver</p>
            <p class="text-sm font-bold text-gray-900 dark:text-slate-100 uppercase tracking-wide"><?= strtoupper($databaseDriver ?? 'sqlite') ?> ACTIVE</p>
        </div>
    </div>

    <div class="glass-card border border-white/60 dark:border-slate-700/50 dark:bg-slate-800/80 rounded-2xl p-4 flex items-center gap-3">
        <div class="w-10 h-10 rounded-xl bg-sky-100 dark:bg-sky-900/40 text-sky-600 dark:text-sky-400 flex items-center justify-center shrink-0">
            <i class="ph ph-cpu text-xl"></i>
        </div>
        <div class="min-w-0">
            <p class="text-xs text-gray-400 dark:text-slate-400">Server Runtime</p>
            <p class="text-sm font-bold text-gray-900 dark:text-slate-100">PHP <?= PHP_VERSION ?> · Hostinger</p>
        </div>
    </div>

    <div class="glass-card border border-white/60 dark:border-slate-700/50 dark:bg-slate-800/80 rounded-2xl p-4 flex items-center gap-3">
        <div class="w-10 h-10 rounded-xl bg-amber-100 dark:bg-amber-900/40 text-amber-600 dark:text-amber-400 flex items-center justify-center shrink-0">
            <i class="ph ph-clock text-xl"></i>
        </div>
        <div class="min-w-0">
            <p class="text-xs text-gray-400 dark:text-slate-400">System Timezone</p>
            <p class="text-sm font-bold text-gray-900 dark:text-slate-100"><?= date_default_timezone_get() ?> (WIB)</p>
        </div>
    </div>
</div>

<!-- ===================== VISUAL CHARTS SECTION ===================== -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-4 lg:gap-6 mt-6">
    <!-- Pertumbuhan Pengguna (2 Cols) -->
    <div class="lg:col-span-2 glass-card rounded-2xl shadow-md shadow-blue-900/5 p-4 lg:p-6 border border-white/60 dark:border-slate-700/50 dark:bg-slate-800/80">
        <div class="flex items-center justify-between mb-5">
            <div>
                <h3 class="text-base lg:text-lg font-display font-bold text-ink dark:text-slate-100">Pertumbuhan Pendaftar Pengguna</h3>
                <p class="text-xs text-gray-400 dark:text-slate-500">6 bulan terakhir</p>
            </div>
            <div class="p-2 bg-emerald-50 dark:bg-emerald-900/30 rounded-xl text-emerald-600 dark:text-emerald-400"><i class="ph ph-chart-line-up text-lg"></i></div>
        </div>
        <?php if (array_sum($regValues) === 0): ?>
            <p class="text-sm text-gray-500 dark:text-slate-400 py-10 text-center">Belum ada data pendaftaran pengguna.</p>
        <?php else: ?>
            <div class="relative h-64">
                <canvas id="adminUsersChart"></canvas>
            </div>
        <?php endif; ?>
    </div>

    <!-- Integrasi Telegram Chart (1 Col) -->
    <div class="glass-card rounded-2xl shadow-md shadow-blue-900/5 p-4 lg:p-6 border border-white/60 dark:border-slate-700/50 dark:bg-slate-800/80 flex flex-col justify-between">
        <div>
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h3 class="text-base font-display font-bold text-ink dark:text-slate-100">Status Telegram</h3>
                    <p class="text-xs text-gray-400 dark:text-slate-500">Persentase Akun Terhubung</p>
                </div>
                <div class="p-2 bg-sky-50 dark:bg-sky-900/30 rounded-xl text-sky-500"><i class="ph ph-telegram-logo text-lg"></i></div>
            </div>
            <div class="relative h-48 flex items-center justify-center">
                <canvas id="adminTgChart"></canvas>
            </div>
        </div>
        <div class="mt-4 pt-3 border-t border-gray-100 dark:border-slate-700/50 flex justify-around text-xs text-center">
            <div>
                <span class="inline-block w-2.5 h-2.5 rounded-full bg-sky-500 mr-1"></span>
                <span class="text-gray-500 dark:text-slate-400">Terhubung</span>
                <p class="font-bold text-gray-900 dark:text-slate-100 text-sm mt-0.5"><?= $tgUsers ?></p>
            </div>
            <div>
                <span class="inline-block w-2.5 h-2.5 rounded-full bg-slate-300 dark:bg-slate-600 mr-1"></span>
                <span class="text-gray-500 dark:text-slate-400">Belum</span>
                <p class="font-bold text-gray-900 dark:text-slate-100 text-sm mt-0.5"><?= max(0, $totalUsers - $tgUsers) ?></p>
            </div>
        </div>
    </div>
</div>

<!-- ===================== TABEL PENGGUNA TERBARU ===================== -->
<div class="grid grid-cols-1 gap-4 lg:gap-6 mt-6">
    <div class="glass-card rounded-2xl shadow-md shadow-blue-900/5 border border-white/60 dark:border-slate-700/50 overflow-hidden">
        <div class="p-4 lg:p-6 border-b border-gray-100 dark:border-slate-700/50 flex justify-between items-center bg-white/60 dark:bg-slate-800/60">
            <div>
                <h3 class="text-base lg:text-lg font-display font-bold text-ink dark:text-slate-100">Pengguna Terbaru</h3>
                <p class="text-xs text-gray-400 dark:text-slate-500">8 pendaftar terakhir di sistem</p>
            </div>
            <a href="users.php" class="text-sm font-medium text-primary dark:text-blue-400 hover:underline flex items-center gap-1">Lihat semua <i class="ph ph-arrow-right"></i></a>
        </div>
        <div class="overflow-x-auto bg-white/40 dark:bg-slate-800/40">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-gray-50/50 dark:bg-slate-900/50">
                        <th class="py-3 px-4 text-xs font-semibold text-gray-500 dark:text-slate-400 uppercase tracking-wider border-b border-gray-100 dark:border-slate-700/50">Nama</th>
                        <th class="py-3 px-4 text-xs font-semibold text-gray-500 dark:text-slate-400 uppercase tracking-wider border-b border-gray-100 dark:border-slate-700/50">Kontak</th>
                        <th class="py-3 px-4 text-xs font-semibold text-gray-500 dark:text-slate-400 uppercase tracking-wider border-b border-gray-100 dark:border-slate-700/50">Telegram</th>
                        <th class="py-3 px-4 text-xs font-semibold text-gray-500 dark:text-slate-400 uppercase tracking-wider border-b border-gray-100 dark:border-slate-700/50">Role</th>
                        <th class="py-3 px-4 text-xs font-semibold text-gray-500 dark:text-slate-400 uppercase tracking-wider border-b border-gray-100 dark:border-slate-700/50">Terdaftar</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50 dark:divide-slate-700/50">
                    <?php if(count($recentUsers) === 0): ?>
                    <tr><td colspan="5" class="py-8 text-center text-gray-400 text-sm">Tidak ada pengguna.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($recentUsers as $u): ?>
                    <tr class="hover:bg-blue-50/30 dark:hover:bg-slate-700/30 transition-colors">
                        <td class="py-3 px-4">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-full bg-gradient-to-br from-primary to-blue-400 text-white flex items-center justify-center font-bold text-xs shrink-0">
                                    <?= mb_strtoupper(mb_substr($u['name'], 0, 1)) ?>
                                </div>
                                <span class="text-sm font-medium text-gray-900 dark:text-slate-200"><?= htmlspecialchars($u['name']) ?></span>
                            </div>
                        </td>
                        <td class="py-3 px-4 text-sm text-gray-600 dark:text-slate-400">
                            <div><?= htmlspecialchars($u['email'] ?: '-') ?></div>
                            <div class="text-xs text-gray-400 dark:text-slate-500"><?= htmlspecialchars($u['phone_number'] ?: '-') ?></div>
                        </td>
                        <td class="py-3 px-4 text-sm text-gray-600 dark:text-slate-400">
                            <?php if (!empty($u['telegram_id'])): ?>
                                <span class="inline-flex items-center gap-1 text-xs text-sky-600 dark:text-sky-400 bg-sky-50 dark:bg-sky-900/30 px-2 py-0.5 rounded-full font-medium"><i class="ph ph-check-circle"></i> Terhubung</span>
                            <?php else: ?>
                                <span class="text-xs text-gray-400 dark:text-slate-500">-</span>
                            <?php endif; ?>
                        </td>
                        <td class="py-3 px-4">
                            <?php if (strtolower($u['role']) === 'admin'): ?>
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-400 uppercase tracking-wider">Admin</span>
                            <?php else: ?>
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-gray-100 dark:bg-slate-700 text-gray-600 dark:text-slate-300 uppercase tracking-wider">User</span>
                            <?php endif; ?>
                        </td>
                        <td class="py-3 px-4 text-sm text-gray-500 dark:text-slate-400">
                            <?= date('d M Y', strtotime($u['created_at'])) ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // 1. Line Chart Pertumbuhan Pengguna
    <?php if (array_sum($regValues) > 0): ?>
    const ctx = document.getElementById('adminUsersChart').getContext('2d');
    const gradient = ctx.createLinearGradient(0, 0, 0, 300);
    gradient.addColorStop(0, 'rgba(16, 185, 129, 0.4)');
    gradient.addColorStop(1, 'rgba(16, 185, 129, 0)');

    new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?= json_encode($regLabels) ?>,
            datasets: [{
                label: 'Pengguna Baru',
                data: <?= json_encode($regValues) ?>,
                borderColor: '#10b981',
                backgroundColor: gradient,
                borderWidth: 3,
                tension: 0.4,
                fill: true,
                pointBackgroundColor: '#ffffff',
                pointBorderColor: '#10b981',
                pointBorderWidth: 2,
                pointRadius: 4,
                pointHoverRadius: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: 'rgba(15, 23, 42, 0.9)',
                    titleFont: { size: 13, family: 'Figtree' },
                    bodyFont: { size: 13, family: 'Figtree' },
                    padding: 10,
                    cornerRadius: 8,
                    displayColors: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { precision: 0, font: { family: 'Figtree' }, color: '#94a3b8' },
                    grid: { color: 'rgba(148, 163, 184, 0.1)', drawBorder: false }
                },
                x: {
                    ticks: { font: { family: 'Figtree' }, color: '#94a3b8' },
                    grid: { display: false, drawBorder: false }
                }
            },
            interaction: { intersect: false, mode: 'index' }
        }
    });
    <?php endif; ?>

    // 2. Doughnut Chart Telegram Integration Status
    const ctxTg = document.getElementById('adminTgChart').getContext('2d');
    new Chart(ctxTg, {
        type: 'doughnut',
        data: {
            labels: ['Terhubung', 'Belum Terhubung'],
            datasets: [{
                data: [<?= $tgUsers ?>, <?= max(0, $totalUsers - $tgUsers) ?>],
                backgroundColor: ['#0ea5e9', '#cbd5e1'],
                borderWidth: 0,
                hoverOffset: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: 'rgba(15, 23, 42, 0.9)',
                    bodyFont: { size: 12, family: 'Figtree' },
                    padding: 8,
                    cornerRadius: 8
                }
            },
            cutout: '72%'
        }
    });
});
</script>

<?php require_once __DIR__ . '/../../app/includes/footer.php'; ?>


