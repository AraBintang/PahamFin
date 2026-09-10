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

        if ($action === 'create') {
            $name = trim((string) ($_POST['name'] ?? ''));
            $target = (float) ($_POST['target_amount'] ?? 0);
            $saved = (float) ($_POST['saved_amount'] ?? 0);
            $deadline = trim((string) ($_POST['deadline'] ?? ''));
            if ($deadline !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $deadline)) {
                $deadline = '';
            }
            if ($name !== '' && $target > 0) {
                $stmt = $pdo->prepare("INSERT INTO savings_goals (user_id, name, target_amount, saved_amount, deadline) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$user_id, $name, $target, $saved, $deadline !== '' ? $deadline : null]);
                $_SESSION['flash_msg'] = 'Target tabungan berhasil dibuat.';
            } else {
                $_SESSION['flash_msg'] = 'Nama dan target nominal wajib diisi.';
                $_SESSION['flash_type'] = 'error';
            }
        } elseif ($action === 'deposit') {
            $id = (int) ($_POST['id'] ?? 0);
            $amount = (float) ($_POST['deposit'] ?? 0);
            if ($id > 0 && $amount > 0) {
                $goal = $pdo->prepare("SELECT * FROM savings_goals WHERE id = ? AND user_id = ? LIMIT 1");
                $goal->execute([$id, $user_id]);
                $g = $goal->fetch(PDO::FETCH_ASSOC);
                if ($g) {
                    $pdo->prepare("UPDATE savings_goals SET saved_amount = saved_amount + ? WHERE id = ? AND user_id = ?")
                        ->execute([$amount, $id, $user_id]);

                    // Sinkronkan dengan catatan pemasukan (kategori "Tabungan").
                    $cat = $pdo->prepare("SELECT id FROM categories WHERE user_id = ? AND LOWER(name) = 'tabungan' LIMIT 1");
                    $cat->execute([$user_id]);
                    $catId = $cat->fetchColumn();
                    if (!$catId) {
                        $pdo->prepare("INSERT INTO categories (user_id, name, keyword, type) VALUES (?, 'Tabungan', 'tabungan, nabung, menabung, deposito', 'PEMASUKAN')")
                            ->execute([$user_id]);
                        $catId = $pdo->lastInsertId();
                    }
                    $pdo->prepare("INSERT INTO transactions (user_id, category_id, amount, description, type, transaction_date) VALUES (?, ?, ?, ?, 'PEMASUKAN', ?)")
                        ->execute([$user_id, (int) $catId, $amount, 'Menabung ke: ' . $g['name'], date('Y-m-d')]);

                    try {
                        PahamFin_notify($pdo, $user_id, 'income', '💾 Menambah tabungan "' . $g['name'] . '" sebesar Rp ' . number_format($amount, 0, ',', '.'), (float) $amount);
                        if ((float) $g['saved_amount'] + $amount >= (float) $g['target_amount']) {
                            PahamFin_notify($pdo, $user_id, 'goal_achieved', '🎉 Target tabungan "' . $g['name'] . '" tercapai!', (float) $g['target_amount']);
                        }
                    } catch (Throwable $e) {
                        // Notifikasi opsional.
                    }

                    $_SESSION['flash_msg'] = 'Dana tabungan berhasil ditambahkan & dicatat sebagai pemasukan.';
                } else {
                    $_SESSION['flash_msg'] = 'Target tidak ditemukan.';
                    $_SESSION['flash_type'] = 'error';
                }
            } else {
                $_SESSION['flash_msg'] = 'Nominal simpanan wajib diisi.';
                $_SESSION['flash_type'] = 'error';
            }
        } elseif ($action === 'delete') {
            $id = (int) ($_POST['id'] ?? 0);
            $pdo->prepare("DELETE FROM savings_goals WHERE id = ? AND user_id = ?")->execute([$id, $user_id]);
            $_SESSION['flash_msg'] = 'Target tabungan dihapus.';
        }
    }
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}
?>
<?php require_once __DIR__ . '/../app/includes/header.php'; ?>
<?php require_once __DIR__ . '/../app/includes/sidebar.php'; ?>

<?php
$goals = PahamFin_savings_goals($pdo, $user_id);
?>



<div x-data="{ showModal: false, showDelete: false, deleteId: null }">
    <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
        <div>
            <p class="text-xs font-semibold text-primary dark:text-blue-400 uppercase tracking-widest">Menabung jadi nyata</p>
            <h3 class="text-lg lg:text-xl font-display font-bold text-ink dark:text-slate-100 mt-1">Target Tabungan</h3>
        </div>
        <button @click="showModal = true" class="px-5 py-2.5 bg-primary text-white rounded-xl font-semibold shadow-lg shadow-blue-900/20 hover:opacity-90 flex items-center gap-2">
            <i class="ph ph-plus-circle text-lg"></i> Buat Target
        </button>
    </div>

    <div class="grid grid-cols-1 gap-6 items-start">
        <!-- Daftar target -->
        <div class="space-y-4">
            <?php if (count($goals) === 0): ?>
                <div class="glass-card rounded-2xl shadow-md shadow-blue-900/5 border border-white/60 p-10 text-center">
                    <i class="ph ph-piggy-bank text-5xl text-blue-200"></i>
                    <p class="mt-3 font-semibold text-ink dark:text-slate-100">Belum ada target tabungan</p>
                    <p class="text-sm text-gray-400 mt-1">Buat target pertamamu, misal "Liburan Rp 5.000.000".</p>
                </div>
            <?php endif; ?>

            <?php foreach ($goals as $g):
                $target = (float) $g['target_amount'];
                $saved = (float) $g['saved_amount'];
                $percent = $target > 0 ? min(($saved / $target) * 100, 100) : 0;
                $achieved = $saved >= $target;
            ?>
            <div class="glass-card rounded-2xl shadow-md shadow-blue-900/5 border border-white/60 p-5">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h4 class="font-semibold text-ink dark:text-slate-100 flex items-center gap-2">
                            <?= htmlspecialchars($g['name']) ?>
                            <?php if ($achieved): ?><span class="text-[10px] text-emerald-600 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-900/30 rounded-full px-2 py-0.5 font-bold">Tercapai 🎉</span><?php endif; ?>
                        </h4>
                        <p class="text-xs text-gray-400 mt-0.5">
                            <?= $g['deadline'] ? 'Target: ' . htmlspecialchars(date('d M Y', strtotime($g['deadline']))) : 'Tanpa batas waktu' ?>
                        </p>
                    </div>
                    <button type="button" @click="deleteId = <?= (int) $g['id'] ?>; showDelete = true" class="w-7 h-7 flex items-center justify-center rounded-lg text-gray-400 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-900/30 transition">
                        <i class="ph ph-trash text-base"></i>
                    </button>
                </div>

                <div class="mt-4 flex items-center justify-between text-sm">
                    <span class="<?= $achieved ? 'text-emerald-600 dark:text-emerald-400 font-semibold' : 'text-ink dark:text-slate-100 font-bold' ?>">Rp <?= number_format($saved, 0, ',', '.') ?></span>
                    <span class="text-gray-400">dari Rp <?= number_format($target, 0, ',', '.') ?></span>
                </div>
                <div class="mt-1.5 w-full h-3 bg-gray-200 dark:bg-slate-700 rounded-full overflow-hidden">
                    <div class="h-full rounded-full transition-all <?= $achieved ? 'bg-emerald-500' : ($percent >= 80 ? 'bg-amber-400' : 'bg-blue-500') ?>" style="width: <?= $percent ?>%"></div>
                </div>
                <p class="text-xs text-gray-400 mt-1.5"><?= number_format($percent, 0) ?>% tercapai — sisa <b>Rp <?= number_format(max($target - $saved, 0), 0, ',', '.') ?></b></p>

                <form method="POST" class="mt-4 flex flex-wrap items-center gap-2">
                    <?= PahamFin_csrf_field() ?>
                    <input type="hidden" name="action" value="deposit">
                    <input type="hidden" name="id" value="<?= (int) $g['id'] ?>">
                    <input type="number" name="deposit" min="1" step="1" required placeholder="Tambah dana (Rp)" class="flex-1 min-w-[140px] border border-gray-200 dark:border-slate-700 dark:bg-slate-900/50 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-primary/30 outline-none">
                    <button type="submit" class="px-4 py-2 bg-gradient-to-r from-primary to-[#0e7ad6] text-white text-sm font-semibold rounded-xl hover:opacity-95 transition shadow-md shadow-blue-900/10">
                        <i class="ph ph-plus-circle mr-1"></i>Simpan
                    </button>
                </form>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Modal Delete -->
    <div x-show="showDelete" x-transition.opacity class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div @click="showDelete = false" class="absolute inset-0 bg-black/50 backdrop-blur-sm"></div>
        <div class="relative glass-card border border-white/60 dark:border-slate-700/50 dark:bg-slate-800/90 rounded-2xl shadow-2xl backdrop-blur-xl w-full max-w-sm p-6 z-10 text-center">
            <i class="ph ph-warning-circle text-5xl text-red-500 dark:text-red-400 mb-4 inline-block"></i>
            <h4 class="font-bold text-ink dark:text-slate-100 text-lg mb-2">Hapus Target Tabungan?</h4>
            <p class="text-sm text-gray-500 dark:text-slate-400 mb-6">Tindakan ini tidak dapat dibatalkan.</p>
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

    <!-- Modal Form buat target -->
    <div x-show="showModal" x-transition.opacity class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <!-- Overlay -->
        <div @click="showModal = false" class="absolute inset-0 bg-black/50 backdrop-blur-sm"></div>
        
        <!-- Modal Content -->
        <div class="relative glass-card border border-white/60 dark:border-slate-700/50 dark:bg-slate-800/90 rounded-2xl shadow-2xl backdrop-blur-xl w-full max-w-md p-6 z-10">
             
            <div class="flex items-center justify-between mb-5">
                <h4 class="font-bold text-ink dark:text-slate-100 text-lg flex items-center gap-2"><i class="ph ph-target text-primary dark:text-blue-400"></i> Buat Target Baru</h4>
                <button @click="showModal = false" class="text-gray-400 hover:text-rose-500 dark:text-rose-400"><i class="ph ph-x text-xl"></i></button>
            </div>
            
            <form method="POST" class="space-y-4">
                <?= PahamFin_csrf_field() ?>
                <input type="hidden" name="action" value="create">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1">Nama Target</label>
                    <input type="text" name="name" required class="w-full border border-gray-200 dark:border-slate-700 rounded-xl px-3 py-2.5 focus:ring-2 focus:ring-primary/30 outline-none dark:bg-slate-900/50" placeholder="Misal: Liburan tahun depan">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1">Nominal Target (Rp)</label>
                    <input type="number" name="target_amount" min="1" step="1" required class="w-full border border-gray-200 dark:border-slate-700 rounded-xl px-3 py-2.5 focus:ring-2 focus:ring-primary/30 outline-none dark:bg-slate-900/50" placeholder="5000000">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1">Dana Awal</label>
                        <input type="number" name="saved_amount" min="0" step="1" value="0" class="w-full border border-gray-200 dark:border-slate-700 rounded-xl px-3 py-2.5 focus:ring-2 focus:ring-primary/30 outline-none dark:bg-slate-900/50">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1">Batas Waktu</label>
                        <input type="date" name="deadline" class="w-full border border-gray-200 dark:border-slate-700 rounded-xl px-3 py-2.5 focus:ring-2 focus:ring-primary/30 outline-none dark:bg-slate-900/50">
                    </div>
                </div>
                <div class="pt-2">
                    <button type="submit" class="w-full py-3 bg-gradient-to-r from-primary to-[#0e7ad6] text-white rounded-xl font-bold hover:opacity-95 transition shadow-lg shadow-blue-900/20">
                        Buat Target
                    </button>
                </div>
                <p class="text-[11px] text-gray-400 mt-2 text-center leading-relaxed">
                    Dana tabungan otomatis tercatat di Pemasukan.
                </p>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../app/includes/footer.php'; ?>

