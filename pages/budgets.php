<?php
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/includes/config.php';
require_once __DIR__ . '/../app/includes/auth.php';
require_login();
$user_id = current_user_id();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!PahamFin_csrf_verify()) {
        $_SESSION['flash_msg'] = 'Sesi tidak valid, coba lagi.';
        $_SESSION['flash_type'] = 'error';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'save') {
            $categoryId = (int) ($_POST['category_id'] ?? 0);
            $amount = (float) ($_POST['amount'] ?? 0);
            $period = ($_POST['period'] ?? 'monthly') === 'weekly' ? 'weekly' : 'monthly';

            if ($categoryId > 0 && $amount > 0 && $amount <= 9999999999) {
                $check = $pdo->prepare("SELECT id FROM budgets WHERE user_id = ? AND category_id = ? AND period = ? LIMIT 1");
                $check->execute([$user_id, $categoryId, $period]);
                if ($check->fetchColumn()) {
                    $stmt = $pdo->prepare("UPDATE budgets SET amount = ? WHERE user_id = ? AND category_id = ? AND period = ?");
                    $stmt->execute([$amount, $user_id, $categoryId, $period]);
                } else {
                    $stmt = $pdo->prepare("INSERT INTO budgets (user_id, category_id, amount, period) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$user_id, $categoryId, $amount, $period]);
                }
                $_SESSION['flash_msg'] = 'Anggaran berhasil disimpan.';
            } else {
                $_SESSION['flash_msg'] = 'Kategori / nominal anggaran tidak valid.';
                $_SESSION['flash_type'] = 'error';
            }
        } elseif ($action === 'delete') {
            $id = (int) ($_POST['id'] ?? 0);
            $stmt = $pdo->prepare("DELETE FROM budgets WHERE id = ? AND user_id = ?");
            $stmt->execute([$id, $user_id]);
            $_SESSION['flash_msg'] = 'Anggaran dihapus.';
        }
    }
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// ---- Data summary bulan ini ----
$summaryStmt = $pdo->prepare("SELECT
    COALESCE(SUM(CASE WHEN type = 'PEMASUKAN' THEN amount ELSE 0 END),0) as total_income,
    COALESCE(SUM(CASE WHEN type = 'PENGELUARAN' THEN amount ELSE 0 END),0) as total_expense
    FROM transactions
    WHERE user_id = ? AND transaction_date BETWEEN ? AND ?");
$summaryStmt->execute([$user_id, date('Y-m-01'), date('Y-m-t')]);
$summary = $summaryStmt->fetch(PDO::FETCH_ASSOC);
$income = (float) $summary['total_income'];
$expense = (float) $summary['total_expense'];
$balance = $income - $expense;

// ---- Semua kategori pengeluaran ----
$catStmt = $pdo->prepare("SELECT * FROM categories WHERE user_id = ? AND type = 'PENGELUARAN' ORDER BY name");
$catStmt->execute([$user_id]);
$categories = $catStmt->fetchAll(PDO::FETCH_ASSOC);

// ---- Anggaran yang sudah diset + pemakaian aktual ----
$budgetStmt = $pdo->prepare("SELECT b.id, b.category_id, b.amount, b.period, c.name AS category_name
    FROM budgets b JOIN categories c ON c.id = b.category_id
    WHERE b.user_id = ? AND c.type = 'PENGELUARAN'
    ORDER BY c.name");
$budgetStmt->execute([$user_id]);
$budgets = $budgetStmt->fetchAll(PDO::FETCH_ASSOC);

$spentStmt = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM transactions WHERE user_id = ? AND category_id = ? AND type='PENGELUARAN' AND transaction_date BETWEEN ? AND ?");
$budgetRows = [];
$totalBudget = 0;
$totalSpent = 0;
foreach ($budgets as $b) {
    $spentStmt->execute([$user_id, (int) $b['category_id'], date('Y-m-01'), date('Y-m-t')]);
    $spent = (float) $spentStmt->fetchColumn();
    $limit = (float) $b['amount'];
    $budgetRows[] = [
        'id' => (int) $b['id'],
        'category_id' => (int) $b['category_id'],
        'category_name' => $b['category_name'],
        'limit' => $limit,
        'spent' => $spent,
        'remaining' => $limit - $spent,
        'percent' => $limit > 0 ? min(($spent / $limit) * 100, 100) : 0,
        'over' => $spent > $limit,
    ];
    $totalBudget += $limit;
    $totalSpent += $spent;
}

// ---- Kategori tanpa budget ----
$budgetedCatIds = array_map(fn($b) => $b['category_id'], $budgetRows);
$unbudgeted = [];
foreach ($categories as $c) {
    if (!in_array((int) $c['id'], $budgetedCatIds, true)) {
        $spentStmt->execute([$user_id, (int) $c['id'], date('Y-m-01'), date('Y-m-t')]);
        $spent = (float) $spentStmt->fetchColumn();
        if ($spent > 0) {
            $unbudgeted[] = ['category_id' => (int) $c['id'], 'category_name' => $c['name'], 'spent' => $spent];
        }
    }
}
?>
<?php require_once __DIR__ . '/../app/includes/header.php'; ?>
<?php require_once __DIR__ . '/../app/includes/sidebar.php'; ?>

<?php
// ---- Simpan / update budget ----

$totalExpenseBudgeted = array_sum(array_column($budgetRows, 'spent')) + array_sum(array_column($unbudgeted, 'spent'));
?>



<div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4 lg:gap-6">
    <div class="glass-card border border-white/60 dark:border-slate-700/50 dark:bg-slate-800/80 rounded-2xl shadow-sm overflow-hidden p-5">
        <p class="text-sm text-gray-500 dark:text-slate-400">Saldo Bulan Ini</p>
        <h3 class="mt-2 text-2xl font-bold text-gray-900 dark:text-slate-100">Rp <?= number_format($balance, 0, ',', '.') ?></h3>
    </div>
    <div class="glass-card border border-white/60 dark:border-slate-700/50 dark:bg-slate-800/80 rounded-2xl shadow-sm overflow-hidden p-5">
        <p class="text-sm text-gray-500 dark:text-slate-400">Pemasukan</p>
        <h3 class="mt-2 text-2xl font-bold text-green-600 dark:text-green-400">Rp <?= number_format($income, 0, ',', '.') ?></h3>
    </div>
    <div class="glass-card border border-white/60 dark:border-slate-700/50 dark:bg-slate-800/80 rounded-2xl shadow-sm overflow-hidden p-5">
        <p class="text-sm text-gray-500 dark:text-slate-400">Pengeluaran</p>
        <h3 class="mt-2 text-2xl font-bold text-red-600 dark:text-red-400">Rp <?= number_format($expense, 0, ',', '.') ?></h3>
    </div>
</div>

<!-- Ringkasan Anggaran -->
<div class="glass-card border border-white/60 dark:border-slate-700/50 dark:bg-slate-800/80 rounded-2xl shadow-sm overflow-hidden p-5">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <p class="text-sm text-gray-500 dark:text-slate-400">Total Anggaran <?= $totalBudget > 0 ? 'Terpakai' : 'Bulan Ini' ?></p>
            <h3 class="mt-1 text-2xl font-bold <?= $totalBudget > 0 && $totalSpent > $totalBudget ? 'text-red-600 dark:text-red-400' : 'text-gray-900 dark:text-slate-100' ?>">
                <?= $totalBudget > 0 ? 'Rp ' . number_format($totalSpent, 0, ',', '.') . ' / Rp ' . number_format($totalBudget, 0, ',', '.') : 'Rp ' . number_format($expense, 0, ',', '.') ?>
            </h3>
        </div>
        <?php if ($totalBudget > 0): ?>
            <div class="w-40">
                <p class="text-xs text-gray-500 dark:text-slate-400 mb-1 text-right"><?= number_format(($totalSpent / $totalBudget) * 100, 0) ?>%</p>
                <div class="w-full h-2.5 bg-gray-100 dark:bg-slate-700/50 rounded-full overflow-hidden">
                    <div class="h-full rounded-full transition-all <?= $totalSpent > $totalBudget ? 'bg-red-500' : 'bg-blue-600' ?>" style="width: <?= min(($totalSpent / $totalBudget) * 100, 100) ?>%"></div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<div x-data="{ showForm: false, showDelete: false, deleteId: null }">
    <div class="grid grid-cols-1 gap-6">
        <!-- Daftar anggaran -->
        <div class="glass-card border border-white/60 dark:border-slate-700/50 dark:bg-slate-800/80 rounded-2xl shadow-sm overflow-hidden">
            <div class="p-4 lg:p-6 border-b border-gray-100 dark:border-slate-700/50 flex justify-between items-center">
                <h3 class="text-base lg:text-lg font-semibold text-gray-900 dark:text-slate-100">Anggaran per Kategori</h3>
                <button @click="showForm = true" class="px-5 py-2 bg-blue-600 text-white rounded-xl font-semibold shadow hover:bg-blue-700 flex items-center gap-2">
                    <i class="ph ph-plus-circle text-lg"></i> Atur Anggaran
                </button>
            </div>
            <div class="p-4 lg:p-6 space-y-6">
                <?php if (count($budgetRows) === 0): ?>
                    <p class="text-sm text-gray-500 dark:text-slate-400">Belum ada anggaran yang diset. Klik tombol Atur Anggaran untuk menetapkan limit per kategori.</p>
                <?php endif; ?>

                <?php foreach ($budgetRows as $b): ?>
                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <span class="font-medium text-gray-800 dark:text-slate-200 flex items-center gap-2">
                                <?= htmlspecialchars($b['category_name']) ?>
                                <?php if ($b['over']): ?>
                                    <span class="text-[10px] text-red-600 dark:text-red-400 bg-red-50 dark:bg-red-900/30 rounded-full px-2 py-0.5 font-semibold">Lewati</span>
                                <?php endif; ?>
                            </span>
                            <span class="text-sm <?= $b['over'] ? 'text-red-600 dark:text-red-400 font-semibold' : 'text-gray-500 dark:text-slate-400' ?>">
                                Rp <?= number_format($b['spent'], 0, ',', '.') ?> / Rp <?= number_format($b['limit'], 0, ',', '.') ?>
                            </span>
                        </div>
                        <div class="w-full h-2.5 bg-gray-100 dark:bg-slate-700/50 rounded-full overflow-hidden">
                            <div class="h-full rounded-full transition-all <?= $b['over'] ? 'bg-red-500' : ($b['percent'] > 70 ? 'bg-amber-400' : 'bg-blue-600') ?>" style="width: <?= $b['percent'] ?>%"></div>
                        </div>
                        <div class="flex items-center justify-between mt-1">
                            <span class="text-xs <?= $b['remaining'] >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' ?>">
                                <?= $b['remaining'] >= 0 ? 'Sisa Rp ' . number_format($b['remaining'], 0, ',', '.') : 'Melebihi Rp ' . number_format(abs($b['remaining']), 0, ',', '.') ?>
                            </span>
                            <button type="button" @click="deleteId = <?= $b['id'] ?>; showDelete = true" class="w-7 h-7 flex items-center justify-center rounded-lg text-gray-400 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-900/30 transition">
                                <i class="ph ph-trash text-base"></i>
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>

                <?php if (count($budgetRows) === 0 && count($categories) === 0): ?>
                    <p class="text-sm text-gray-500 dark:text-slate-400">Tambahkan kategori pengeluaran dulu di menu <b>Kategori & Bot</b>.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Section Pengeluaran Tanpa Anggaran -->
        <?php if (count($unbudgeted) > 0): ?>
        <div class="glass-card border border-white/60 dark:border-slate-700/50 dark:bg-slate-800/80 rounded-2xl shadow-sm overflow-hidden">
            <div class="p-4 lg:p-6 border-b border-gray-100 dark:border-slate-700/50">
                <h3 class="text-base lg:text-lg font-semibold text-gray-900 dark:text-slate-100">Pengeluaran Tanpa Anggaran</h3>
            </div>
            <div class="p-4 lg:p-6 space-y-3">
                <?php foreach ($unbudgeted as $u): ?>
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-gray-700 dark:text-slate-300"><?= htmlspecialchars($u['category_name']) ?></span>
                        <span class="text-gray-500 dark:text-slate-400">Rp <?= number_format($u['spent'], 0, ',', '.') ?></span>
                    </div>
                <?php endforeach; ?>
                <p class="text-xs text-gray-400 mt-2">Kategori dengan pengeluaran tetapi belum punya limit anggaran.</p>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Modal Delete -->
    <div x-show="showDelete" style="display: none;" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div x-show="showDelete" x-transition.opacity @click="showDelete = false" class="absolute inset-0 bg-black/50 backdrop-blur-sm"></div>
        <div x-show="showDelete" x-transition class="relative glass-card border border-white/60 dark:border-slate-700/50 dark:bg-slate-800/90 rounded-2xl shadow-2xl backdrop-blur-xl w-full max-w-sm p-6 z-10 text-center">
            <i class="ph ph-warning-circle text-5xl text-red-500 dark:text-red-400 mb-4 inline-block"></i>
            <h4 class="font-bold text-ink dark:text-slate-100 text-lg mb-2">Hapus Anggaran?</h4>
            <p class="text-sm text-gray-500 dark:text-slate-400 mb-6">Limit anggaran untuk kategori ini akan dihapus.</p>
            <div class="flex gap-3 justify-center">
                <button @click="showDelete = false" class="px-4 py-2 text-gray-600 dark:text-slate-400 bg-gray-100 hover:bg-gray-200 rounded-lg font-medium transition">Batal</button>
                <form method="POST" class="inline">
                    <?= PahamFin_csrf_field() ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" :value="deleteId">
                    <button type="submit" class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg font-medium transition">Ya, Hapus</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Form Atur Anggaran -->
    <div x-show="showForm" style="display: none;" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div x-show="showForm" x-transition.opacity @click="showForm = false" class="absolute inset-0 bg-black/50 backdrop-blur-sm"></div>
        
        <div x-show="showForm" x-transition class="relative glass-card border border-white/60 dark:border-slate-700/50 dark:bg-slate-800/90 rounded-2xl shadow-2xl backdrop-blur-xl w-full max-w-md p-6 z-10">
            <div class="flex items-center justify-between mb-5 border-b border-gray-100 dark:border-slate-700/50 pb-4">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-slate-100">Atur Anggaran</h3>
                <button @click="showForm = false" class="text-gray-400 hover:text-red-500 dark:text-red-400"><i class="ph ph-x text-xl"></i></button>
            </div>
            
            <form method="POST" class="space-y-4">
                <?= PahamFin_csrf_field() ?>
                <input type="hidden" name="action" value="save">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1">Kategori</label>
                    <select name="category_id" required class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-blue-500 outline-none dark:bg-slate-900/50 dark:border-slate-700">
                        <option value="">Pilih kategori</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?= $category['id'] ?>"><?= htmlspecialchars($category['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1">Limit Anggaran</label>
                    <input type="number" name="amount" min="1" max="9999999999" step="1" required class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-blue-500 outline-none dark:bg-slate-900/50 dark:border-slate-700" placeholder="500000">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1">Periode</label>
                    <select name="period" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-blue-500 outline-none dark:bg-slate-900/50 dark:border-slate-700">
                        <option value="monthly">Bulanan</option>
                        <option value="weekly">Mingguan</option>
                    </select>
                </div>
                <div class="pt-2">
                    <button type="submit" class="w-full py-2.5 bg-blue-600 text-white rounded-lg font-medium hover:bg-blue-700 transition">Simpan Anggaran</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../app/includes/footer.php'; ?>











