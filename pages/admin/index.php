<?php
require_once __DIR__ . '/../../app/db.php';
require_once __DIR__ . '/../../app/includes/auth.php';
require_admin($pdo);

require_once __DIR__ . '/../../app/includes/header.php';
require_once __DIR__ . '/../../app/includes/sidebar.php';

// ---- Statistik inti untuk seluruh aplikasi ----
$totalUsers = (int) ($pdo->query("SELECT COUNT(*) FROM users")->fetchColumn() ?? 0);
$adminUsers = (int) ($pdo->query("SELECT COUNT(*) FROM users WHERE LOWER(role) = 'admin'")->fetchColumn() ?? 0);

$newUsersStmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE created_at >= ?");
$newUsersStmt->execute([date('Y-m-01') . ' 00:00:00']);
$newUsers = (int) $newUsersStmt->fetchColumn();

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
    "SELECT id, name, email, phone_number, role, created_at
     FROM users ORDER BY created_at DESC, id DESC LIMIT 8"
)->fetchAll(PDO::FETCH_ASSOC);
?>

<!-- Statistik Admin -->
<div class="grid grid-cols-1 sm:grid-cols-2 gap-4 lg:gap-6">
    <!-- Total Pengguna -->
    <div class="relative overflow-hidden bg-gradient-to-br from-primary via-[#0e7ad6] to-[#4ec3f2] dark:from-slate-800/80 dark:via-slate-800/80 dark:to-slate-800/80 dark:border dark:border-slate-700/50 text-white p-5 lg:p-6 rounded-2xl shadow-xl shadow-blue-900/20">
        <div class="absolute -right-6 -top-6 w-32 h-32 bg-white/15 rounded-full"></div>
        <div class="relative">
            <p class="text-xs lg:text-sm font-medium text-blue-100 mb-1 flex items-center gap-1.5"><i class="ph ph-users-three"></i> Total Pengguna</p>
            <h3 class="text-2xl lg:text-[28px] font-display font-extrabold tracking-tight"><?= number_format($totalUsers, 0, ',', '.') ?></h3>
            <p class="text-[11px] text-blue-100/80 mt-2 font-medium bg-white/10 inline-block px-2 py-1 rounded-full"><i class="ph ph-trend-up"></i> <?= $newUsers ?> pendaftar bulan ini</p>
        </div>
    </div>

    <!-- Total Admin -->
    <div class="relative overflow-hidden glass-card rounded-2xl shadow-md shadow-blue-900/5 p-5 lg:p-6 border border-white/60 dark:border-slate-700/50 dark:bg-slate-800/80">
        <div class="absolute -right-8 -top-8 w-28 h-28 bg-amber-400/10 dark:bg-amber-400/5 rounded-full blur-2xl"></div>
        <div class="relative">
            <p class="text-xs lg:text-sm font-medium text-gray-500 dark:text-slate-400 mb-1 flex items-center gap-1.5"><i class="ph ph-shield-check"></i> Administrator</p>
            <h3 class="text-2xl lg:text-[28px] font-display font-extrabold text-amber-600 dark:text-amber-500 dark:text-amber-400"><?= number_format($adminUsers, 0, ',', '.') ?></h3>
            <p class="text-[11px] text-gray-400 dark:text-slate-500 mt-2">Staf dengan akses admin penuh</p>
        </div>
    </div>
</div>

<!-- Grafik -->
<div class="grid grid-cols-1 mt-6 gap-4 lg:gap-6">
    <!-- Pertumbuhan Pengguna -->
    <div class="glass-card rounded-2xl shadow-md shadow-blue-900/5 p-4 lg:p-6 border border-white/60 dark:border-slate-700/50 dark:bg-slate-800/80">
        <div class="flex items-center justify-between mb-5">
            <div>
                <h3 class="text-base lg:text-lg font-display font-bold text-ink dark:text-slate-100">Registrasi Pengguna Baru</h3>
                <p class="text-xs text-gray-400 dark:text-slate-500">6 bulan terakhir</p>
            </div>
            <div class="p-2.5 bg-emerald-50 dark:bg-emerald-50 dark:bg-emerald-900/300/10 rounded-xl text-emerald-600 dark:text-emerald-400"><i class="ph ph-chart-line-up text-xl"></i></div>
        </div>
        <?php if (array_sum($regValues) === 0): ?>
            <p class="text-sm text-gray-500 dark:text-slate-400 py-10 text-center">Belum ada registrasi pengguna.</p>
        <?php else: ?>
            <div class="relative h-64">
                <canvas id="adminUsersChart"></canvas>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Pengguna Terbaru -->
<div class="grid grid-cols-1 gap-4 lg:gap-6 mt-6">
    <div class="glass-card rounded-2xl shadow-md shadow-blue-900/5 border border-white/60 dark:border-slate-700/50 overflow-hidden">
        <div class="p-4 lg:p-6 border-b border-gray-100 dark:border-slate-700/50 flex justify-between items-center bg-white/60 dark:bg-slate-800/60">
            <div>
                <h3 class="text-base lg:text-lg font-display font-bold text-ink dark:text-slate-100">Pengguna Terbaru</h3>
                <p class="text-xs text-gray-400 dark:text-slate-500">8 pendaftar terakhir</p>
            </div>
            <a href="users.php" class="text-sm font-medium text-primary dark:text-blue-400 hover:underline flex items-center gap-1">Lihat semua <i class="ph ph-arrow-right"></i></a>
        </div>
        <div class="overflow-x-auto bg-white/40 dark:bg-slate-800/40">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-gray-50/50 dark:bg-slate-900/50">
                        <th class="py-3 px-4 text-xs font-semibold text-gray-500 dark:text-slate-400 uppercase tracking-wider border-b border-gray-100 dark:border-slate-700/50">Nama</th>
                        <th class="py-3 px-4 text-xs font-semibold text-gray-500 dark:text-slate-400 uppercase tracking-wider border-b border-gray-100 dark:border-slate-700/50">Kontak</th>
                        <th class="py-3 px-4 text-xs font-semibold text-gray-500 dark:text-slate-400 uppercase tracking-wider border-b border-gray-100 dark:border-slate-700/50">Role</th>
                        <th class="py-3 px-4 text-xs font-semibold text-gray-500 dark:text-slate-400 uppercase tracking-wider border-b border-gray-100 dark:border-slate-700/50">Terdaftar</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50 dark:divide-slate-700/50">
                    <?php if(count($recentUsers) === 0): ?>
                    <tr><td colspan="4" class="py-8 text-center text-gray-400 text-sm">Tidak ada pengguna.</td></tr>
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
                        <td class="py-3 px-4">
                            <?php if (strtolower($u['role']) === 'admin'): ?>
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 dark:bg-amber-50 dark:bg-amber-900/300/20 text-amber-700 dark:text-amber-400 uppercase tracking-wider">Admin</span>
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
});
</script>

<?php require_once __DIR__ . '/../../app/includes/footer.php'; ?>


