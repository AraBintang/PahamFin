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
        $_SESSION['flash_msg'] = 'Sesi tidak valid, coba lagi.';
        $_SESSION['flash_type'] = 'error';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'create') {
            $person  = trim((string) ($_POST['person_name'] ?? ''));
            $amount  = (float) ($_POST['amount'] ?? 0);
            $type    = $_POST['type'] ?? 'OWE';
            $due     = trim((string) ($_POST['due_date'] ?? ''));
            if ($due !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $due)) $due = '';
            if ($person !== '' && $amount > 0) {
                $pdo->prepare("INSERT INTO debts (user_id, person_name, amount, type, due_date) VALUES (?, ?, ?, ?, ?)")
                    ->execute([$user_id, $person, $amount, $type, $due !== '' ? $due : null]);
                $_SESSION['flash_msg'] = 'Hutang/piutang berhasil ditambahkan.';
            } else {
                $_SESSION['flash_msg'] = 'Nama orang dan nominal wajib diisi.';
                $_SESSION['flash_type'] = 'error';
            }
        } elseif ($action === 'edit') {
            $id     = (int) ($_POST['id'] ?? 0);
            $person = trim((string) ($_POST['person_name'] ?? ''));
            $amount = (float) ($_POST['amount'] ?? 0);
            $type   = $_POST['type'] ?? 'OWE';
            $due    = trim((string) ($_POST['due_date'] ?? ''));
            if ($due !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $due)) $due = '';
            if ($id > 0 && $person !== '' && $amount > 0) {
                $pdo->prepare("UPDATE debts SET person_name=?, amount=?, type=?, due_date=? WHERE id=? AND user_id=?")
                    ->execute([$person, $amount, $type, $due !== '' ? $due : null, $id, $user_id]);
                $_SESSION['flash_msg'] = 'Data berhasil diperbarui.';
            }
        } elseif ($action === 'lunas') {
            $id = (int) ($_POST['id'] ?? 0);
            if ($id > 0) {
                $pdo->prepare("UPDATE debts SET status='PAID' WHERE id=? AND user_id=?")->execute([$id, $user_id]);
                $_SESSION['flash_msg'] = 'Berhasil ditandai lunas!';
            }
        } elseif ($action === 'delete') {
            $id = (int) ($_POST['id'] ?? 0);
            if ($id > 0) {
                $pdo->prepare("DELETE FROM debts WHERE id=? AND user_id=?")->execute([$id, $user_id]);
                $_SESSION['flash_msg'] = 'Data berhasil dihapus.';
            }
        }
    }
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// Ambil hutang & piutang
$filter = $_GET['status'] ?? 'UNPAID';
$filter = in_array($filter, ['UNPAID','PAID','ALL']) ? $filter : 'UNPAID';
$whereSql = $filter === 'ALL' ? '' : "AND status = '$filter'";

$stmt = $pdo->prepare("SELECT * FROM debts WHERE user_id = ? $whereSql ORDER BY status ASC, id DESC");
$stmt->execute([$user_id]);
$debts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Ringkasan
$sumStmt = $pdo->prepare("SELECT 
    SUM(CASE WHEN type='OWE' AND status='UNPAID' THEN amount ELSE 0 END) AS total_owe,
    SUM(CASE WHEN type='LENT' AND status='UNPAID' THEN amount ELSE 0 END) AS total_lent
FROM debts WHERE user_id = ?");
$sumStmt->execute([$user_id]);
$summary = $sumStmt->fetch(PDO::FETCH_ASSOC);
$totalOwe  = (float)($summary['total_owe'] ?? 0);
$totalLent = (float)($summary['total_lent'] ?? 0);

?>
<?php require_once __DIR__ . '/../app/includes/header.php'; ?>
<?php require_once __DIR__ . '/../app/includes/sidebar.php'; ?>

<?php
?>

<?php if ($msg): ?>
<div class="p-3 rounded-lg bg-<?= $msgType ?>-50 border border-<?= $msgType ?>-200 text-<?= $msgType ?>-700 text-sm"><?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

<div x-data="{ 
    showForm: false, 
    showDelete: false, 
    deleteId: null, 
    lunasId: null, 
    showLunas: false,
    formMode: 'create',
    formData: { id: '', person_name: '', type: 'OWE', amount: '', due_date: '', description: '' },
    openEdit(id, name, type, amount, due, desc) {
        this.formMode = 'edit';
        this.formData.id = id;
        this.formData.person_name = name;
        this.formData.type = type;
        this.formData.amount = amount;
        this.formData.due_date = due || '';
        this.formData.description = desc || '';
        this.showForm = true;
    },
    openCreate() {
        this.formMode = 'create';
        this.formData.id = '';
        this.formData.person_name = '';
        this.formData.type = 'OWE';
        this.formData.amount = '';
        this.formData.due_date = '';
        this.formData.description = '';
        this.showForm = true;
    }
}">

    <!-- Ringkasan -->
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">
        <div class="bg-gradient-to-br from-rose-500 to-rose-700 text-white rounded-2xl p-5 shadow-lg">
            <p class="text-sm text-white/80">💸 Total Kamu Berhutang</p>
            <p class="text-2xl font-bold mt-1">Rp <?= number_format($totalOwe, 0, ',', '.') ?></p>
            <p class="text-xs text-white/60 mt-1">Belum dilunasi</p>
        </div>
        <div class="bg-gradient-to-br from-emerald-500 to-emerald-700 text-white rounded-2xl p-5 shadow-lg">
            <p class="text-sm text-white/80">💰 Total Orang Berhutang ke Kamu</p>
            <p class="text-2xl font-bold mt-1">Rp <?= number_format($totalLent, 0, ',', '.') ?></p>
            <p class="text-xs text-white/60 mt-1">Belum dilunasi</p>
        </div>
    </div>

    <!-- Tabel -->
    <div class="glass-card border border-white/60 dark:border-slate-700/50 dark:bg-slate-800/80 rounded-2xl shadow-sm overflow-hidden">
        <div class="p-4 lg:p-6 border-b border-gray-100 dark:border-slate-700/50 flex flex-wrap justify-between items-center gap-3">
            <div>
                <h3 class="text-base lg:text-lg font-semibold text-gray-900 dark:text-slate-100">Hutang & Piutang 🤝</h3>
                <div class="flex gap-2 mt-2">
                    <a href="debts.php?status=UNPAID" 
                       class="text-xs px-3.5 py-1.5 rounded-full font-semibold transition flex items-center gap-1.5 <?= $filter === 'UNPAID' ? 'bg-rose-600 text-white shadow-md shadow-rose-900/20' : 'bg-rose-50 text-rose-700 dark:bg-rose-900/30 dark:text-rose-300 hover:bg-rose-100 dark:hover:bg-rose-900/50' ?>">
                        <span class="w-2 h-2 rounded-full <?= $filter === 'UNPAID' ? 'bg-white' : 'bg-rose-500' ?>"></span> Belum Lunas
                    </a>
                    <a href="debts.php?status=PAID" 
                       class="text-xs px-3.5 py-1.5 rounded-full font-semibold transition flex items-center gap-1.5 <?= $filter === 'PAID' ? 'bg-emerald-600 text-white shadow-md shadow-emerald-900/20' : 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300 hover:bg-emerald-100 dark:hover:bg-emerald-900/50' ?>">
                        <span class="w-2 h-2 rounded-full <?= $filter === 'PAID' ? 'bg-white' : 'bg-emerald-500' ?>"></span> Lunas
                    </a>
                    <a href="debts.php?status=ALL" 
                       class="text-xs px-3.5 py-1.5 rounded-full font-semibold transition flex items-center gap-1.5 <?= $filter === 'ALL' ? 'bg-blue-600 text-white shadow-md shadow-blue-900/20' : 'bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300 hover:bg-blue-100 dark:hover:bg-blue-900/50' ?>">
                        <span class="w-2 h-2 rounded-full <?= $filter === 'ALL' ? 'bg-white' : 'bg-blue-500' ?>"></span> Semua
                    </a>
                </div>
            </div>
            <button @click="openCreate()" class="px-5 py-2.5 bg-blue-600 text-white rounded-xl font-semibold shadow hover:bg-blue-700 flex items-center gap-2">
                <i class="ph ph-plus-circle text-lg"></i> Tambah
            </button>
        </div>

        <?php if (empty($debts)): ?>
        <div class="p-10 text-center">
            <i class="ph ph-handshake text-5xl text-gray-300 mb-3 block"></i>
            <p class="text-gray-500 dark:text-slate-400 text-sm">
                <?= $filter === 'UNPAID' ? 'Tidak ada hutang/piutang yang belum lunas.' : 'Tidak ada data.' ?>
            </p>
        </div>
        <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-100 dark:border-slate-700/50 text-gray-500 dark:text-slate-400 font-medium text-xs uppercase tracking-wide">
                        <th class="p-4 text-left">Nama</th>
                        <th class="p-4 text-left">Tipe</th>
                        <th class="p-4 text-right">Nominal</th>
                        <th class="p-4 text-center">Jatuh Tempo</th>
                        <th class="p-4 text-center">Status</th>
                        <th class="p-4 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($debts as $d): 
                        $isOwe  = $d['type'] === 'OWE';
                        $isPaid = $d['status'] === 'PAID';
                        $isOverdue = !$isPaid && $d['due_date'] && $d['due_date'] < date('Y-m-d');
                    ?>
                    <tr class="border-b border-gray-50 hover:bg-gray-50/50 transition <?= $isPaid ? 'opacity-60' : '' ?> dark:bg-slate-900/50 dark:border-slate-700">
                        <td class="p-4 font-semibold text-gray-800 dark:text-slate-200"><?= htmlspecialchars($d['person_name']) ?></td>
                        <td class="p-4">
                            <?php if ($isOwe): ?>
                            <span class="inline-flex items-center gap-1 text-xs font-medium px-2.5 py-1 rounded-full bg-rose-100 dark:bg-rose-900/50 text-rose-700 dark:text-rose-300">
                                🔴 Kamu berhutang
                            </span>
                            <?php else: ?>
                            <span class="inline-flex items-center gap-1 text-xs font-medium px-2.5 py-1 rounded-full bg-emerald-100 dark:bg-emerald-900/50 text-emerald-700 dark:text-emerald-300">
                                🟢 Dia berhutang
                            </span>
                            <?php endif; ?>
                        </td>
                        <td class="p-4 text-right font-semibold text-gray-900 dark:text-slate-100">Rp <?= number_format((float)$d['amount'], 0, ',', '.') ?></td>
                        <td class="p-4 text-center">
                            <?php if ($d['due_date']): ?>
                            <span class="text-xs <?= $isOverdue ? 'text-red-600 dark:text-red-400 font-semibold' : 'text-gray-500 dark:text-slate-400' ?>">
                                <?= $isOverdue ? '⚠️ ' : '' ?><?= $d['due_date'] ?>
                            </span>
                            <?php else: ?>
                            <span class="text-xs text-gray-400">-</span>
                            <?php endif; ?>
                        </td>
                        <td class="p-4 text-center">
                            <span class="text-xs px-2 py-1 rounded-full font-medium <?= $isPaid ? 'bg-emerald-100 dark:bg-emerald-900/50 text-emerald-700 dark:text-emerald-300' : 'bg-amber-100 dark:bg-amber-900/50 text-amber-700 dark:text-amber-300' ?>">
                                <?= $isPaid ? '✅ Lunas' : '⏳ Belum' ?>
                            </span>
                        </td>
                        <td class="p-4 text-right">
                            <div class="flex justify-end gap-2 flex-wrap">
                                <?php if (!$isPaid): ?>
                                <button @click="lunasId = <?= $d['id'] ?>; showLunas = true" class="text-xs text-emerald-600 dark:text-emerald-400 hover:underline font-medium">Lunas</button>
                                <?php endif; ?>
                                <button @click="openEdit(<?= $d['id'] ?>, '<?= htmlspecialchars($d['person_name'] ?? '', ENT_QUOTES) ?>', '<?= $d['type'] ?>', <?= $d['amount'] ?>, '<?= $d['due_date'] ?>', '<?= htmlspecialchars($d['description'] ?? '', ENT_QUOTES) ?>')" class="w-7 h-7 flex items-center justify-center rounded-lg text-gray-400 hover:text-blue-500 hover:bg-blue-50 dark:hover:bg-blue-900/30 transition">
                                    <i class="ph ph-pencil-simple text-base"></i>
                                </button>
                                <button @click="deleteId = <?= $d['id'] ?>; showDelete = true" class="w-7 h-7 flex items-center justify-center rounded-lg text-gray-400 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-900/30 transition">
                                    <i class="ph ph-trash text-base"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <!-- Modal Lunas -->
    <div x-show="showLunas" style="display: none;" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div x-show="showLunas" x-transition.opacity @click="showLunas = false" class="absolute inset-0 bg-black/50 backdrop-blur-sm"></div>
        <div x-show="showLunas"
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0 scale-90"
             x-transition:enter-end="opacity-100 scale-100"
             class="relative glass-card border border-white/60 dark:border-slate-700/50 dark:bg-slate-800/90 rounded-2xl shadow-2xl backdrop-blur-xl w-full max-w-sm p-6 z-10 text-center">
            <div class="w-14 h-14 bg-emerald-100 dark:bg-emerald-900/50 rounded-full flex items-center justify-center mx-auto mb-4">
                <i class="ph ph-check-circle text-2xl text-emerald-600 dark:text-emerald-400"></i>
            </div>
            <h3 class="text-lg font-bold text-gray-900 dark:text-slate-100">Tandai Lunas?</h3>
            <p class="text-sm text-gray-500 dark:text-slate-400 mt-2 mb-5">Hutang/piutang ini akan ditandai sudah lunas.</p>
            <div class="flex gap-3 justify-center">
                <button @click="showLunas = false" class="px-4 py-2 text-gray-600 dark:text-slate-400 bg-gray-100 hover:bg-gray-200 rounded-lg font-medium">Batal</button>
                <form method="POST" class="inline">
                    <?= PahamFin_csrf_field() ?>
                    <input type="hidden" name="action" value="lunas">
                    <input type="hidden" name="id" :value="lunasId">
                    <button type="submit" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg font-medium">Ya, Lunas</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Hapus -->
    <div x-show="showDelete" style="display: none;" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div x-show="showDelete" x-transition.opacity @click="showDelete = false" class="absolute inset-0 bg-black/50 backdrop-blur-sm"></div>
        <div x-show="showDelete"
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0 scale-90"
             x-transition:enter-end="opacity-100 scale-100"
             class="relative glass-card border border-white/60 dark:border-slate-700/50 dark:bg-slate-800/90 rounded-2xl shadow-2xl backdrop-blur-xl w-full max-w-sm p-6 z-10 text-center">
            <div class="w-14 h-14 bg-red-100 dark:bg-red-900/50 rounded-full flex items-center justify-center mx-auto mb-4">
                <i class="ph ph-trash text-2xl text-red-600 dark:text-red-400"></i>
            </div>
            <h3 class="text-lg font-bold text-gray-900 dark:text-slate-100">Hapus Data?</h3>
            <p class="text-sm text-gray-500 dark:text-slate-400 mt-2 mb-5">Data hutang/piutang ini akan dihapus permanen.</p>
            <div class="flex gap-3 justify-center">
                <button @click="showDelete = false" class="px-4 py-2 text-gray-600 dark:text-slate-400 bg-gray-100 hover:bg-gray-200 rounded-lg font-medium">Batal</button>
                <form method="POST" class="inline">
                    <?= PahamFin_csrf_field() ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" :value="deleteId">
                    <button type="submit" class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg font-medium">Ya, Hapus</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Tambah / Edit -->
    <div x-show="showForm" style="display: none;" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div x-show="showForm" x-transition.opacity @click="showForm = false" class="absolute inset-0 bg-black/50 backdrop-blur-sm"></div>
        <div x-show="showForm"
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0 translate-y-8 scale-95"
             x-transition:enter-end="opacity-100 translate-y-0 scale-100"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100 translate-y-0 scale-100"
             x-transition:leave-end="opacity-0 translate-y-8 scale-95"
             class="relative glass-card border border-white/60 dark:border-slate-700/50 dark:bg-slate-800/90 rounded-2xl shadow-2xl backdrop-blur-xl w-full max-w-md p-6 z-10">
            <div class="flex items-center justify-between mb-5">
                <h3 class="text-lg font-bold text-gray-900 dark:text-slate-100"><span x-text="formMode === 'edit' ? 'Edit Hutang/Piutang' : 'Tambah Hutang/Piutang'"></span></h3>
                <button @click="showForm = false" class="text-gray-400 hover:text-gray-600 dark:text-slate-400"><i class="ph ph-x text-xl"></i></button>
            </div>
            <form method="POST" class="space-y-4">
                <?= PahamFin_csrf_field() ?>
                <input type="hidden" name="action" :value="formMode">
                <template x-if="formMode === 'edit'"><input type="hidden" name="id" :value="formData.id"></template>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1.5">Nama Orang</label>
                    <input type="text" name="person_name" placeholder="Nama teman / keluarga..." required
                           x-model="formData.person_name"
                           class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-blue-500 dark:bg-slate-900/50 dark:border-slate-700">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1.5">Tipe</label>
                        <select name="type" x-model="formData.type" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-blue-500 dark:bg-slate-900/50 dark:border-slate-700">
                                <option value="OWE">Saya berhutang (Pinjam Uang)</option>
                                <option value="LENT">Dia berhutang (Memberi Pinjaman)</option>
                            </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1.5">Nominal (Rp)</label>
                        <input type="number" name="amount" placeholder="0" min="1" required
                               x-model="formData.amount"
                               class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-blue-500 dark:bg-slate-900/50 dark:border-slate-700">
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1.5">Jatuh Tempo (opsional)</label>
                    <input type="date" name="due_date"
                           x-model="formData.due_date"
                           class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-blue-500 dark:bg-slate-900/50 dark:border-slate-700">
                </div>
                <div class="flex gap-3 pt-2">
                    <button type="button" @click="showForm = false" class="flex-1 px-4 py-2.5 text-gray-600 dark:text-slate-400 bg-gray-100 hover:bg-gray-200 rounded-xl font-medium">Batal</button>
                    <button type="submit" class="flex-1 px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl font-semibold shadow" x-text="formMode === 'edit' ? 'Simpan' : 'Tambah'">
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../app/includes/footer.php'; ?>











