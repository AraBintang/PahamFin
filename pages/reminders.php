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
            $title = trim((string) ($_POST['title'] ?? ''));
            $type = trim((string) ($_POST['type'] ?? 'note'));
            if (!in_array($type, ['catat_transaksi', 'bayar_tagihan', 'note'], true)) {
                $type = 'note';
            }
            $date = trim((string) ($_POST['remind_date'] ?? ''));
            if ($date === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                $date = date('Y-m-d');
            }
            $time = trim((string) ($_POST['remind_time'] ?? ''));
            if ($time === '' || !preg_match('/^\d{2}:\d{2}$/', $time)) {
                $time = null;
            }
            if ($title !== '') {
                $pdo->prepare("INSERT INTO reminders (user_id, title, type, remind_date, remind_time) VALUES (?, ?, ?, ?, ?)")
                    ->execute([$user_id, $title, $type, $date, $time]);
                $_SESSION['flash_msg'] = 'Pengingat berhasil disimpan.';
            } else {
                $_SESSION['flash_msg'] = 'Judul pengingat wajib diisi.';
                $_SESSION['flash_type'] = 'error';
            }
        } elseif ($action === 'toggle') {
            $id = (int) ($_POST['id'] ?? 0);
            $pdo->prepare("UPDATE reminders SET done = 1 - done WHERE id = ? AND user_id = ?")->execute([$id, $user_id]);
        } elseif ($action === 'delete') {
            $id = (int) ($_POST['id'] ?? 0);
            $pdo->prepare("DELETE FROM reminders WHERE id = ? AND user_id = ?")->execute([$id, $user_id]);
            $_SESSION['flash_msg'] = 'Pengingat dihapus.';
        }
    }
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}
?>
<?php require_once __DIR__ . '/../app/includes/header.php'; ?>
<?php require_once __DIR__ . '/../app/includes/sidebar.php'; ?>

<?php
$stmt = $pdo->prepare("SELECT * FROM reminders WHERE user_id = ? ORDER BY done ASC, remind_date ASC, remind_time ASC");
$stmt->execute([$user_id]);
$reminders = $stmt->fetchAll(PDO::FETCH_ASSOC);

$typeLabels = [
    'catat_transaksi' => ['Pengingat Catat', 'ph-pencil-simple-line', 'text-blue-600 dark:text-blue-400 bg-blue-50 dark:bg-blue-900/30'],
    'bayar_tagihan' => ['Bayar Tagihan', 'ph-receipt', 'text-rose-600 dark:text-rose-400 bg-rose-50 dark:bg-rose-900/30'],
    'note' => ['Catatan', 'ph-note', 'text-amber-600 dark:text-amber-400 bg-amber-50 dark:bg-amber-900/30'],
];
?>



<div x-data="{ showModal: false, showDelete: false, deleteId: null }">
    <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
        <div>
            <p class="text-xs font-semibold text-primary dark:text-blue-400 uppercase tracking-widest">Jangan lupa catat & bayar</p>
            <h3 class="text-lg lg:text-xl font-display font-bold text-ink dark:text-slate-100 mt-1">Notifikasi & Pengingat</h3>
            <p class="text-sm text-gray-400">Atur pengingat mencatat transaksi atau membayar tagihan.</p>
        </div>
        <button @click="showModal = true" class="px-5 py-2.5 bg-primary text-white rounded-xl font-semibold shadow-lg shadow-blue-900/20 hover:opacity-90 flex items-center gap-2">
            <i class="ph ph-plus-circle text-lg"></i> Tambah Pengingat
        </button>
    </div>

    <!-- Daftar pengingat (Single Column) -->
    <div class="glass-card rounded-2xl shadow-md shadow-blue-900/5 border border-white/60 overflow-hidden">
        <div class="p-4 lg:p-5 border-b border-gray-100 dark:border-slate-700/50 bg-white/60 dark:bg-slate-800/60 flex items-center justify-between">
            <h4 class="font-semibold text-ink dark:text-slate-100">Daftar Pengingat</h4>
            <span class="text-xs text-gray-400"><?= count(array_filter($reminders, fn($r) => !$r['done'])) ?> belum selesai</span>
        </div>
        <div class="divide-y divide-gray-50 dark:divide-slate-700/50">
            <?php if (count($reminders) === 0): ?>
                <p class="p-8 text-center text-sm text-gray-400">Belum ada pengingat. Tambahkan pengingat pertamamu.</p>
            <?php endif; ?>
            <?php foreach ($reminders as $r):
                $meta = $typeLabels[$r['type']] ?? $typeLabels['note'];
                $isDue = !$r['done'] && $r['remind_date'] === date('Y-m-d');
                $isLate = !$r['done'] && $r['remind_date'] < date('Y-m-d');
            ?>
            <div class="p-4 lg:p-5 flex items-center gap-4 <?= $r['done'] ? 'opacity-50' : '' ?>">
                <span class="w-10 h-10 rounded-xl flex items-center justify-center text-base shrink-0 <?= $meta[2] ?>">
                    <i class="ph <?= $meta[1] ?>"></i>
                </span>
                <div class="flex-1 min-w-0">
                    <p class="font-semibold text-ink dark:text-slate-100 truncate"><?= htmlspecialchars($r['title']) ?></p>
                    <p class="text-xs text-gray-400 mt-0.5 flex items-center gap-1.5">
                        <span class="<?= $r['done'] ? 'text-gray-300' : ($isLate ? 'text-rose-500 dark:text-rose-400 font-semibold' : ($isDue ? 'text-amber-600 dark:text-amber-400 font-semibold' : 'text-gray-400')) ?>">
                            <i class="ph ph-calendar-blank mr-0.5"></i><?= htmlspecialchars(date('d M Y', strtotime($r['remind_date']))) ?>
                        </span>
                        <?php if ($r['remind_time']): ?><span><i class="ph ph-clock mr-0.5"></i><?= htmlspecialchars($r['remind_time']) ?></span><?php endif; ?>
                        <span class="text-gray-300">·</span> <?= $meta[0] ?>
                        <?php if ($isDue): ?><span class="text-[10px] text-amber-600 dark:text-amber-400 bg-amber-50 dark:bg-amber-900/30 rounded-full px-2 py-0.5 font-bold">Hari Ini</span><?php endif; ?>
                        <?php if ($isLate): ?><span class="text-[10px] text-rose-600 dark:text-rose-400 bg-rose-50 dark:bg-rose-900/30 rounded-full px-2 py-0.5 font-bold">Terlewat</span><?php endif; ?>
                    </p>
                </div>
                <div class="flex items-center gap-1.5 shrink-0">
                    <form method="POST" class="inline">
                        <?= PahamFin_csrf_field() ?>
                        <input type="hidden" name="action" value="toggle">
                        <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                        <button type="submit" title="<?= $r['done'] ? 'Tandai belum selesai' : 'Tandai selesai' ?>" class="w-8 h-8 flex items-center justify-center rounded-lg text-gray-400 hover:text-emerald-600 dark:text-emerald-400 hover:bg-emerald-50 dark:bg-emerald-900/30 transition">
                            <i class="ph <?= $r['done'] ? 'ph-check-circle-fill text-emerald-500 dark:text-emerald-400' : 'ph-circle' ?>"></i>
                        </button>
                    </form>
                    <button type="button" @click="deleteId = <?= (int) $r['id'] ?>; showDelete = true" class="w-8 h-8 flex items-center justify-center rounded-lg text-gray-300 hover:text-rose-500 dark:text-rose-400 hover:bg-rose-50 dark:bg-rose-900/30 transition">
                        <i class="ph ph-trash"></i>
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Modal Delete -->
    <div x-show="showDelete" style="display: none;" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div x-show="showDelete" x-transition.opacity @click="showDelete = false" class="absolute inset-0 bg-black/50 backdrop-blur-sm"></div>
        <div x-show="showDelete" x-transition class="relative glass-card border border-white/60 dark:border-slate-700/50 dark:bg-slate-800/90 rounded-2xl shadow-2xl backdrop-blur-xl w-full max-w-sm p-6 z-10 text-center">
            <i class="ph ph-warning-circle text-5xl text-red-500 dark:text-red-400 mb-4 inline-block"></i>
            <h4 class="font-bold text-ink dark:text-slate-100 text-lg mb-2">Hapus Pengingat?</h4>
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

    <!-- Modal Form (Pop-Up) -->
    <div x-show="showModal" style="display: none;" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <!-- Overlay -->
        <div x-show="showModal" x-transition.opacity @click="showModal = false" class="absolute inset-0 bg-black/50 backdrop-blur-sm"></div>
        
        <!-- Modal Content -->
        <div x-show="showModal" 
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0 translate-y-8 scale-95"
             x-transition:enter-end="opacity-100 translate-y-0 scale-100"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100 translate-y-0 scale-100"
             x-transition:leave-end="opacity-0 translate-y-8 scale-95"
             class="relative glass-card border border-white/60 dark:border-slate-700/50 dark:bg-slate-800/90 rounded-2xl shadow-2xl backdrop-blur-xl w-full max-w-md p-6 z-10">
             
            <div class="flex items-center justify-between mb-5">
                <h4 class="font-bold text-ink dark:text-slate-100 text-lg flex items-center gap-2"><i class="ph ph-bell-ringing text-primary dark:text-blue-400"></i> Tambah Pengingat</h4>
                <button @click="showModal = false" class="text-gray-400 hover:text-rose-500 dark:text-rose-400"><i class="ph ph-x text-xl"></i></button>
            </div>
            
            <form method="POST" class="space-y-4">
                <?= PahamFin_csrf_field() ?>
                <input type="hidden" name="action" value="create">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1">Judul</label>
                    <input type="text" name="title" required class="w-full border border-gray-200 dark:border-slate-700 rounded-xl px-3 py-2.5 focus:ring-2 focus:ring-primary/30 outline-none dark:bg-slate-900/50" placeholder="Misal: Bayar listrik bulanan">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1">Jenis</label>
                    <select name="type" class="w-full border border-gray-200 dark:border-slate-700 rounded-xl px-3 py-2.5 focus:ring-2 focus:ring-primary/30 outline-none dark:bg-slate-900/50">
                        <option value="catat_transaksi">Catat Transaksi</option>
                        <option value="bayar_tagihan">Bayar Tagihan</option>
                        <option value="note">Catatan Umum</option>
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1">Tanggal</label>
                        <input type="date" name="remind_date" value="<?= date('Y-m-d') ?>" class="w-full border border-gray-200 dark:border-slate-700 rounded-xl px-3 py-2.5 focus:ring-2 focus:ring-primary/30 outline-none dark:bg-slate-900/50">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1">Jam (opsional)</label>
                        <input type="time" name="remind_time" class="w-full border border-gray-200 dark:border-slate-700 rounded-xl px-3 py-2.5 focus:ring-2 focus:ring-primary/30 outline-none dark:bg-slate-900/50">
                    </div>
                </div>
                <div class="pt-2">
                    <button type="submit" class="w-full py-3 bg-gradient-to-r from-primary to-[#0e7ad6] text-white rounded-xl font-bold hover:opacity-95 transition shadow-lg shadow-blue-900/20">
                        Simpan Pengingat
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../app/includes/footer.php'; ?>











