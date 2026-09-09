<?php
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/includes/config.php';
require_once __DIR__ . '/../app/includes/auth.php';
require_login();
$user_id = current_user_id();

$msg = '';
$msgType = 'green';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!PahamFin_csrf_verify()) {
        $msg = 'Sesi tidak valid, coba lagi.';
        $msgType = 'red';
    } else {
        $action  = $_POST['action'] ?? '';
        $id      = (int)($_POST['id'] ?? 0);
        $title   = trim((string)($_POST['title'] ?? ''));
        $content = trim((string)($_POST['content'] ?? ''));
        $color   = in_array($_POST['color'] ?? '', ['yellow','blue','green','pink','purple','orange','red','cyan','slate']) ? $_POST['color'] : 'yellow';

        if ($action === 'create' && $content !== '') {
            $pdo->prepare("INSERT INTO notes (user_id, title, content, color) VALUES (?, ?, ?, ?)")
                ->execute([$user_id, $title, $content, $color]);
            $_SESSION['flash_msg'] = 'Catatan berhasil ditambahkan.';
            header("Location: notes.php"); exit;
        } elseif ($action === 'edit' && $id > 0 && $content !== '') {
            $pdo->prepare("UPDATE notes SET title=?, content=?, color=? WHERE id=? AND user_id=?")
                ->execute([$title, $content, $color, $id, $user_id]);
            $_SESSION['flash_msg'] = 'Catatan berhasil diperbarui.';
            header("Location: notes.php"); exit;
        } elseif ($action === 'delete' && $id > 0) {
            $pdo->prepare("DELETE FROM notes WHERE id=? AND user_id=?")->execute([$id, $user_id]);
            $_SESSION['flash_msg'] = 'Catatan berhasil dihapus.';
            header("Location: notes.php"); exit;
        }
    }
}

require_once __DIR__ . '/../app/includes/header.php';
require_once __DIR__ . '/../app/includes/sidebar.php';
?>
<?php
$editId = isset($_GET['edit_id']) ? (int)$_GET['edit_id'] : 0;
$editNote = null;
if ($editId > 0) {
    $s = $pdo->prepare("SELECT * FROM notes WHERE id=? AND user_id=? LIMIT 1");
    $s->execute([$editId, $user_id]);
    $editNote = $s->fetch(PDO::FETCH_ASSOC);
}

$stmt = $pdo->prepare("SELECT * FROM notes WHERE user_id=? ORDER BY id DESC");
$stmt->execute([$user_id]);
$notes = $stmt->fetchAll(PDO::FETCH_ASSOC);

$colorMap = [
    'yellow' => ['bg' => 'bg-amber-50 dark:bg-amber-900/20',    'border' => 'border-amber-200 dark:border-amber-700/50',   'dot' => 'bg-amber-400',    'btn' => 'bg-amber-400'],
    'blue'   => ['bg' => 'bg-blue-50 dark:bg-blue-900/20',      'border' => 'border-blue-200 dark:border-blue-700/50',     'dot' => 'bg-blue-400',     'btn' => 'bg-blue-500'],
    'green'  => ['bg' => 'bg-emerald-50 dark:bg-emerald-900/20','border' => 'border-emerald-200 dark:border-emerald-700/50','dot' => 'bg-emerald-400',  'btn' => 'bg-emerald-500'],
    'pink'   => ['bg' => 'bg-pink-50 dark:bg-pink-900/20',      'border' => 'border-pink-200 dark:border-pink-700/50',     'dot' => 'bg-pink-400',     'btn' => 'bg-pink-500'],
    'purple' => ['bg' => 'bg-violet-50 dark:bg-violet-900/20',  'border' => 'border-violet-200 dark:border-violet-700/50', 'dot' => 'bg-violet-400',   'btn' => 'bg-violet-500'],
    'orange' => ['bg' => 'bg-orange-50 dark:bg-orange-900/20',  'border' => 'border-orange-200 dark:border-orange-700/50', 'dot' => 'bg-orange-400',   'btn' => 'bg-orange-500'],
    'red'    => ['bg' => 'bg-red-50 dark:bg-red-900/20',        'border' => 'border-red-200 dark:border-red-700/50',       'dot' => 'bg-red-400',      'btn' => 'bg-red-500'],
    'cyan'   => ['bg' => 'bg-cyan-50 dark:bg-cyan-900/20',      'border' => 'border-cyan-200 dark:border-cyan-700/50',     'dot' => 'bg-cyan-400',     'btn' => 'bg-cyan-500'],
    'slate'  => ['bg' => 'bg-slate-100 dark:bg-slate-700/40',   'border' => 'border-slate-200 dark:border-slate-600/50',   'dot' => 'bg-slate-500',    'btn' => 'bg-slate-500'],
];
?>

<?php
$flashMsg = $_SESSION['flash_msg'] ?? '';
unset($_SESSION['flash_msg']);
?>
<?php if ($flashMsg): ?>
<div class="mb-4 p-3 rounded-lg bg-green-50 dark:bg-green-900/30 border border-green-200 dark:border-green-700 text-green-700 dark:text-green-300 text-sm flex items-center gap-2">
    <i class="ph ph-check-circle text-lg"></i> <?= htmlspecialchars($flashMsg) ?>
</div>
<?php elseif ($msg): ?>
<div class="mb-4 p-3 rounded-lg bg-<?= $msgType ?>-50 border border-<?= $msgType ?>-200 text-<?= $msgType ?>-700 text-sm"><?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

<div x-data="{ showForm: <?= $editNote ? 'true' : 'false' ?>, showDelete: false, deleteId: null }">
    <div class="flex items-center justify-between mb-4">
        <div>
            <h2 class="font-display text-lg font-bold text-gray-900 dark:text-slate-100">Catatan Keuangan 📝</h2>
            <p class="text-xs text-gray-400 mt-0.5">Catat rencana, target, atau apapun yang penting</p>
        </div>
        <button @click="showForm = true" class="px-4 py-2.5 bg-blue-600 text-white rounded-xl font-semibold shadow hover:bg-blue-700 flex items-center gap-2 text-sm">
            <i class="ph ph-plus-circle text-lg"></i> Catatan Baru
        </button>
    </div>

    <?php if (empty($notes)): ?>
    <div class="glass-card border border-white/60 dark:border-slate-700/50 dark:bg-slate-800/80 rounded-2xl p-12 text-center shadow-sm">
        <i class="ph ph-notepad text-5xl text-gray-300 mb-3 block"></i>
        <p class="text-gray-500 dark:text-slate-400 text-sm">Belum ada catatan. Tambahkan catatan keuangan pertamamu!</p>
        <p class="text-gray-400 text-xs mt-2">Contoh: Target dana darurat, rencana investasi, dsb.</p>
    </div>
    <?php else: ?>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        <?php foreach ($notes as $n):
            $c = $colorMap[$n['color']] ?? $colorMap['yellow'];
        ?>
        <div class="<?= $c['bg'] ?> <?= $c['border'] ?> border rounded-2xl p-4 shadow-sm hover:shadow-md transition flex flex-col gap-3">
            <div class="flex items-start justify-between gap-2">
                <div class="flex-1 min-w-0">
                    <?php if ($n['title'] !== ''): ?>
                    <h4 class="font-bold text-gray-800 dark:text-slate-200 text-sm leading-snug mb-1"><?= htmlspecialchars($n['title']) ?></h4>
                    <?php endif; ?>
                    <p class="text-sm text-gray-700 dark:text-slate-300 whitespace-pre-wrap leading-relaxed"><?= htmlspecialchars($n['content']) ?></p>
                </div>
                <span class="w-3 h-3 rounded-full <?= $c['dot'] ?> shrink-0 mt-0.5"></span>
            </div>
            <div class="flex items-center justify-between pt-2 border-t border-black/5">
                <span class="text-[11px] text-gray-400"><?= date('d M Y', strtotime($n['created_at'])) ?></span>
                <div class="flex gap-2">
                    <a href="notes.php?edit_id=<?= $n['id'] ?>" class="text-xs text-blue-600 dark:text-blue-400 hover:underline font-medium">Edit</a>
                    <button @click="deleteId = <?= $n['id'] ?>; showDelete = true" class="text-xs text-red-500 dark:text-red-400 hover:underline font-medium">Hapus</button>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Modal Hapus -->
    <div x-show="showDelete" style="display:none;" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div x-show="showDelete" x-transition.opacity @click="showDelete=false" class="absolute inset-0 bg-black/50 backdrop-blur-sm"></div>
        <div x-show="showDelete" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 scale-90" x-transition:enter-end="opacity-100 scale-100"
             class="relative glass-card border border-white/60 dark:border-slate-700/50 dark:bg-slate-800/90 rounded-2xl shadow-2xl backdrop-blur-xl w-full max-w-sm p-6 z-10 text-center">
            <div class="w-14 h-14 bg-red-100 dark:bg-red-900/50 rounded-full flex items-center justify-center mx-auto mb-4">
                <i class="ph ph-trash text-2xl text-red-600 dark:text-red-400"></i>
            </div>
            <h3 class="text-lg font-bold">Hapus Catatan?</h3>
            <p class="text-sm text-gray-500 dark:text-slate-400 mt-2 mb-5">Catatan ini akan dihapus permanen.</p>
            <div class="flex gap-3 justify-center">
                <button @click="showDelete=false" class="px-4 py-2 text-gray-600 dark:text-slate-400 bg-gray-100 hover:bg-gray-200 rounded-lg font-medium">Batal</button>
                <form method="POST" class="inline">
                    <?= PahamFin_csrf_field() ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" :value="deleteId">
                    <button type="submit" class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg font-medium">Ya, Hapus</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Tambah/Edit -->
    <div x-show="showForm" style="display:none;" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div x-show="showForm" x-transition.opacity @click="showForm=false" class="absolute inset-0 bg-black/50 backdrop-blur-sm"></div>
        <div x-show="showForm"
             x-data="{ noteColor: '<?= $editNote['color'] ?? 'yellow' ?>', cm: <?= htmlspecialchars(json_encode($colorMap)) ?> }"
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0 translate-y-8 scale-95"
             x-transition:enter-end="opacity-100 translate-y-0 scale-100"
             class="relative rounded-2xl shadow-2xl backdrop-blur-xl w-full max-w-lg p-6 z-10 transition-colors duration-300 border"
             :class="cm[noteColor].bg + ' ' + cm[noteColor].border">
            <div class="flex items-center justify-between mb-5">
                <h3 class="text-lg font-bold text-gray-900 dark:text-slate-100"><?= $editNote ? 'Edit Catatan' : 'Catatan Baru' ?></h3>
                <button @click="showForm=false" class="text-gray-400 hover:text-gray-600 dark:text-slate-400"><i class="ph ph-x text-xl"></i></button>
            </div>
            <form method="POST" class="space-y-4">
                <?= PahamFin_csrf_field() ?>
                <input type="hidden" name="action" value="<?= $editNote ? 'edit' : 'create' ?>">
                <?php if ($editNote): ?><input type="hidden" name="id" value="<?= $editNote['id'] ?>"><?php endif; ?>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1.5">Judul (opsional)</label>
                    <input type="text" name="title" placeholder="Judul catatan..." value="<?= htmlspecialchars($editNote['title'] ?? '') ?>"
                           class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1.5">Isi Catatan *</label>
                    <textarea name="content" rows="5" placeholder="Tulis catatan keuanganmu di sini..." required
                              class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-blue-500 resize-none"><?= htmlspecialchars($editNote['content'] ?? '') ?></textarea>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-2">Warna Catatan</label>
                    <div class="flex gap-2">
                        <?php foreach ($colorMap as $cKey => $cVal): ?>
                        <label class="cursor-pointer">
                            <input type="radio" name="color" value="<?= $cKey ?>" class="peer sr-only" x-model="noteColor">
                            <span class="w-7 h-7 rounded-full <?= $cVal['btn'] ?> block ring-2 ring-offset-2 ring-transparent peer-checked:ring-gray-400 hover:scale-110 transition-transform"></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="flex gap-3 pt-2">
                    <button type="button" @click="showForm=false" class="flex-1 px-4 py-2.5 text-gray-600 dark:text-slate-400 bg-gray-100 hover:bg-gray-200 rounded-xl font-medium">Batal</button>
                    <button type="submit" class="flex-1 px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl font-semibold shadow">
                        <?= $editNote ? 'Simpan' : 'Tambah Catatan' ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../app/includes/footer.php'; ?>
