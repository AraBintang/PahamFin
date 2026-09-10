<?php require_once __DIR__ . '/../app/includes/header.php'; ?>
<?php require_once __DIR__ . '/../app/includes/sidebar.php'; ?>

<?php
$msg = '';
$msgType = 'green';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!PahamFin_csrf_verify()) {
        $msg = 'Sesi tidak valid, coba lagi.';
        $msgType = 'red';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'create') {
            $name    = trim((string) ($_POST['name'] ?? ''));
            $balance = (float) ($_POST['starting_balance'] ?? 0);
            if ($name !== '') {
                $pdo->prepare("INSERT INTO wallets (user_id, name, starting_balance) VALUES (?, ?, ?)")
                    ->execute([$user_id, $name, $balance]);
                $msg = 'Dompet berhasil ditambahkan.';
            } else {
                $msg = 'Nama dompet wajib diisi.';
                $msgType = 'red';
            }
        } elseif ($action === 'edit') {
            $id      = (int) ($_POST['id'] ?? 0);
            $name    = trim((string) ($_POST['name'] ?? ''));
            $balance = (float) ($_POST['starting_balance'] ?? 0);
            if ($id > 0 && $name !== '') {
                $pdo->prepare("UPDATE wallets SET name = ?, starting_balance = ? WHERE id = ? AND user_id = ?")
                    ->execute([$name, $balance, $id, $user_id]);
                $msg = 'Dompet berhasil diperbarui.';
            }
        } elseif ($action === 'delete') {
            $id = (int) ($_POST['id'] ?? 0);
            if ($id > 0) {
                // Hapus wallet_id dari transaksi yang terkait agar tidak rusak
                $pdo->prepare("UPDATE transactions SET wallet_id = NULL WHERE wallet_id = ? AND user_id = ?")
                    ->execute([$id, $user_id]);
                $pdo->prepare("DELETE FROM wallets WHERE id = ? AND user_id = ?")
                    ->execute([$id, $user_id]);
                $msg = 'Dompet berhasil dihapus.';
                $_SESSION['flash_msg'] = $msg;
                header('Location: wallets.php'); exit;
            }
        } elseif ($action === 'topup') {
            $id       = (int) ($_POST['id'] ?? 0);
            $addAmt   = (float) ($_POST['topup_amount'] ?? 0);
            if ($id > 0 && $addAmt > 0) {
                $pdo->prepare("UPDATE wallets SET starting_balance = starting_balance + ? WHERE id = ? AND user_id = ?")
                    ->execute([$addAmt, $id, $user_id]);
                $_SESSION['flash_msg'] = 'Saldo dompet berhasil ditambahkan.';
                header('Location: wallets.php'); exit;
            }
        } elseif ($action === 'create') {
            // already handled above, just in case no redirect
        } elseif ($action === 'edit') {
            // already handled above
        }
        if ($msg !== '' && empty($_SESSION['flash_msg'])) {
            $_SESSION['flash_msg'] = $msg;
            header('Location: wallets.php'); exit;
        }
    }
}

// Ambil semua dompet beserta total saldo (saldo awal - pengeluaran + pemasukan dari transaksi)
$stmt = $pdo->prepare("
    SELECT w.id, w.name, w.starting_balance,
        COALESCE(SUM(CASE WHEN t.type='PEMASUKAN' THEN t.amount ELSE -t.amount END), 0) AS tx_net
    FROM wallets w
    LEFT JOIN transactions t ON t.wallet_id = w.id
    WHERE w.user_id = ?
    GROUP BY w.id, w.name, w.starting_balance
    ORDER BY w.id ASC
");
$stmt->execute([$user_id]);
$wallets = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Edit mode
$editId = isset($_GET['edit_id']) ? (int) $_GET['edit_id'] : 0;
$editWallet = null;
if ($editId > 0) {
    $s = $pdo->prepare("SELECT * FROM wallets WHERE id = ? AND user_id = ? LIMIT 1");
    $s->execute([$editId, $user_id]);
    $editWallet = $s->fetch(PDO::FETCH_ASSOC);
}
?>

<?php
$flashMsg = $_SESSION['flash_msg'] ?? '';
unset($_SESSION['flash_msg']);
?>
<?php if ($flashMsg): ?>
<div class="p-3 rounded-lg bg-green-50 dark:bg-green-900/30 border border-green-200 dark:border-green-700 text-green-700 dark:text-green-300 text-sm"><?= htmlspecialchars($flashMsg) ?></div>
<?php elseif ($msg): ?>
<div class="p-3 rounded-lg bg-<?= $msgType ?>-50 border border-<?= $msgType ?>-200 text-<?= $msgType ?>-700 text-sm"><?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

<div x-data="{ 
    showForm: <?= $editWallet ? 'true' : 'false' ?>, 
    isEdit: <?= $editWallet ? 'true' : 'false' ?>,
    editId: <?= (int)($editWallet['id'] ?? 0) ?>,
    editName: '<?= htmlspecialchars($editWallet['name'] ?? '', ENT_QUOTES) ?>',
    editBalance: '<?= $editWallet ? (float)$editWallet['starting_balance'] : '' ?>',
    showDelete: false, 
    deleteId: null, 
    showTopup: false, 
    topupId: null,
    openAdd() {
        this.isEdit = false;
        this.editId = 0;
        this.editName = '';
        this.editBalance = '';
        this.showForm = true;
    },
    openEdit(w) {
        this.isEdit = true;
        this.editId = w.id;
        this.editName = w.name;
        this.editBalance = w.starting_balance;
        this.showForm = true;
    }
}">
    <div class="grid grid-cols-1 gap-6">
        <!-- Kartu-kartu dompet -->
        <div class="glass-card border border-white/60 dark:border-slate-700/50 dark:bg-slate-800/80 rounded-2xl shadow-sm overflow-hidden">
            <div class="p-4 lg:p-6 border-b border-gray-100 dark:border-slate-700/50 flex flex-wrap justify-between items-center gap-3">
                <div>
                    <h3 class="text-base lg:text-lg font-semibold text-gray-900 dark:text-slate-100">Dompet Saya 💳</h3>
                    <p class="text-xs text-gray-500 dark:text-slate-400 mt-1">Lacak saldo di setiap dompet (Tunai, BCA, Gopay, dll)</p>
                </div>
                <button @click="openAdd()" class="px-5 py-2.5 bg-blue-600 text-white rounded-xl font-semibold shadow hover:bg-blue-700 flex items-center gap-2 transition">
                    <i class="ph ph-plus-circle text-lg"></i> Tambah Dompet
                </button>
            </div>

            <?php if (empty($wallets)): ?>
            <div class="p-10 text-center">
                <i class="ph ph-wallet text-5xl text-gray-300 dark:text-slate-600 mb-3 block"></i>
                <p class="text-gray-500 dark:text-slate-400 text-sm">Belum ada dompet. Tambahkan dompet seperti Tunai, BCA, Gopay, dll.</p>
            </div>
            <?php else: ?>
            <div class="p-4 lg:p-6 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                <?php
                $colors = [
                    ['gradient' => 'from-blue-600 to-blue-800',    'dark_from' => 'dark:from-blue-900/60',    'dark_to' => 'dark:to-blue-950/80',    'border' => 'dark:border-blue-700/50',    'icon' => 'text-blue-400',    'ring' => 'bg-blue-500/20'],
                    ['gradient' => 'from-emerald-500 to-emerald-700', 'dark_from' => 'dark:from-emerald-900/60', 'dark_to' => 'dark:to-emerald-950/80', 'border' => 'dark:border-emerald-700/50', 'icon' => 'text-emerald-400', 'ring' => 'bg-emerald-500/20'],
                    ['gradient' => 'from-violet-600 to-violet-800', 'dark_from' => 'dark:from-violet-900/60',  'dark_to' => 'dark:to-violet-950/80',  'border' => 'dark:border-violet-700/50',  'icon' => 'text-violet-400',  'ring' => 'bg-violet-500/20'],
                    ['gradient' => 'from-amber-500 to-amber-700',   'dark_from' => 'dark:from-amber-900/60',   'dark_to' => 'dark:to-amber-950/80',   'border' => 'dark:border-amber-700/50',   'icon' => 'text-amber-400',   'ring' => 'bg-amber-500/20'],
                    ['gradient' => 'from-rose-500 to-rose-700',     'dark_from' => 'dark:from-rose-900/60',    'dark_to' => 'dark:to-rose-950/80',    'border' => 'dark:border-rose-700/50',    'icon' => 'text-rose-400',    'ring' => 'bg-rose-500/20'],
                    ['gradient' => 'from-cyan-500 to-cyan-700',     'dark_from' => 'dark:from-cyan-900/60',    'dark_to' => 'dark:to-cyan-950/80',    'border' => 'dark:border-cyan-700/50',    'icon' => 'text-cyan-400',    'ring' => 'bg-cyan-500/20'],
                ];
                foreach ($wallets as $i => $w):
                    $color = $colors[$i % count($colors)];
                    $balance = $w['starting_balance'] + $w['tx_net'];
                    $balanceFmt = number_format($balance, 0, ',', '.');
                    $startFmt = number_format($w['starting_balance'], 0, ',', '.');
                    $wJson = htmlspecialchars(json_encode([
                        'id' => (int)$w['id'],
                        'name' => $w['name'],
                        'starting_balance' => (float)$w['starting_balance']
                    ]), ENT_QUOTES, 'UTF-8');
                ?>
                <div class="relative overflow-hidden rounded-2xl border <?= $color['border'] ?> border-white/20
                    bg-gradient-to-br <?= $color['gradient'] ?> <?= $color['dark_from'] ?> <?= $color['dark_to'] ?>
                    text-white backdrop-blur-sm p-5 shadow-lg transition-all hover:scale-[1.02] hover:shadow-xl">
                    <!-- Dekorasi latar -->
                    <div class="absolute top-0 right-0 w-28 h-28 rounded-full <?= $color['ring'] ?> -translate-y-8 translate-x-8 pointer-events-none"></div>
                    <div class="absolute -bottom-4 -left-4 w-20 h-20 rounded-full <?= $color['ring'] ?> pointer-events-none"></div>

                    <!-- Header: ikon + aksi -->
                    <div class="flex justify-between items-start mb-4 relative z-10">
                        <div class="w-10 h-10 rounded-xl bg-white/20 dark:bg-white/10 backdrop-blur flex items-center justify-center">
                            <i class="ph ph-wallet text-xl text-white"></i>
                        </div>
                        <div class="flex gap-1">
                            <button @click='openEdit(<?= $wJson ?>)'
                                class="w-7 h-7 flex items-center justify-center rounded-lg bg-white/20 dark:bg-white/10 hover:bg-white/30 dark:hover:bg-white/20 text-white transition" title="Edit">
                                <i class="ph ph-pencil-simple text-sm"></i>
                            </button>
                            <button @click="deleteId = <?= $w['id'] ?>; showDelete = true"
                                class="w-7 h-7 flex items-center justify-center rounded-lg bg-white/20 dark:bg-white/10 hover:bg-rose-500/40 dark:hover:bg-rose-500/30 text-white transition" title="Hapus">
                                <i class="ph ph-trash text-sm"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Nama dompet -->
                    <p class="text-sm font-semibold text-white/80 relative z-10 tracking-wide uppercase"><?= htmlspecialchars($w['name']) ?></p>

                    <!-- Saldo -->
                    <p class="text-2xl font-bold mt-1 relative z-10 tracking-tight">Rp <?= $balanceFmt ?></p>

                    <!-- Saldo awal + Tombol Top Up -->
                    <div class="mt-3 pt-3 border-t border-white/20 dark:border-white/10 relative z-10 flex justify-between items-center">
                        <span class="text-xs text-white/60">Saldo Awal: Rp <?= $startFmt ?></span>
                        <button @click="topupId = <?= $w['id'] ?>; showTopup = true"
                            class="flex items-center gap-1 text-xs bg-white/20 hover:bg-white/30 text-white px-2.5 py-1 rounded-lg transition font-medium">
                            <i class="ph ph-plus-circle"></i> Top Up
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

        </div>

        <!-- Info cara pakai -->
        <div class="bg-blue-50 dark:bg-blue-900/30 border border-blue-200 dark:border-blue-800/50 rounded-xl p-5">
            <h4 class="font-semibold text-blue-800 dark:text-blue-200 mb-2 flex items-center gap-2">
                <i class="ph ph-lightbulb text-xl"></i> Cara Pakai Dompet di Bot Telegram
            </h4>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm text-blue-700 dark:text-blue-300">
                <div class="bg-white/70 dark:bg-slate-800/70 rounded-lg p-3">
                    <p class="font-semibold">💸 Catat pengeluaran via dompet</p>
                    <code class="text-xs block mt-1 bg-blue-100 dark:bg-blue-900/50 px-2 py-1 rounded">makan 50000 gopay</code>
                    <code class="text-xs block mt-1 bg-blue-100 dark:bg-blue-900/50 px-2 py-1 rounded">bensin 100rb tunai</code>
                </div>
                <div class="bg-white/70 dark:bg-slate-800/70 rounded-lg p-3">
                    <p class="font-semibold">💰 Catat pemasukan ke dompet</p>
                    <code class="text-xs block mt-1 bg-blue-100 dark:bg-blue-900/50 px-2 py-1 rounded">gaji 5000000 bca</code>
                    <code class="text-xs block mt-1 bg-blue-100 dark:bg-blue-900/50 px-2 py-1 rounded">transfer masuk 200rb gopay</code>
                </div>
                <div class="bg-white/70 dark:bg-slate-800/70 rounded-lg p-3">
                    <p class="font-semibold">📊 Lihat semua dompet di Telegram</p>
                    <code class="text-xs block mt-1 bg-blue-100 dark:bg-blue-900/50 px-2 py-1 rounded">/dompet</code>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Hapus -->
    <div x-show="showDelete" x-transition.opacity class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div @click="showDelete = false" class="absolute inset-0 bg-black/50 backdrop-blur-sm"></div>
        <div class="relative glass-card border border-white/60 dark:border-slate-700/50 dark:bg-slate-800/90 rounded-2xl shadow-2xl backdrop-blur-xl w-full max-w-sm p-6 z-10 text-center">
            <div class="w-14 h-14 bg-red-100 dark:bg-red-900/50 rounded-full flex items-center justify-center mx-auto mb-4">
                <i class="ph ph-trash text-2xl text-red-600 dark:text-red-400"></i>
            </div>
            <h3 class="text-lg font-bold text-gray-900 dark:text-slate-100">Hapus Dompet?</h3>
            <p class="text-sm text-gray-500 dark:text-slate-400 mt-2 mb-5">Dompet akan dihapus. Transaksi yang sudah tercatat tetap ada namun tidak terhubung ke dompet ini.</p>
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

    <!-- Modal Top Up Saldo -->
    <div x-show="showTopup" x-transition.opacity class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div @click="showTopup = false" class="absolute inset-0 bg-black/50 backdrop-blur-sm"></div>
        <div class="relative glass-card border border-white/60 dark:border-slate-700/50 dark:bg-slate-800/90 rounded-2xl shadow-2xl backdrop-blur-xl w-full max-w-sm p-6 z-10">
            <div class="flex items-center justify-between mb-5">
                <h3 class="text-lg font-bold text-gray-900 dark:text-slate-100">Tambah Saldo Dompet</h3>
                <button @click="showTopup = false" class="text-gray-400 hover:text-gray-600 dark:text-slate-400"><i class="ph ph-x text-xl"></i></button>
            </div>
            <form method="POST" class="space-y-4">
                <?= PahamFin_csrf_field() ?>
                <input type="hidden" name="action" value="topup">
                <input type="hidden" name="id" :value="topupId">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1.5">Jumlah yang Ditambahkan (Rp)</label>
                    <input type="number" name="topup_amount" placeholder="0" min="1" required
                           class="w-full border border-gray-300 dark:border-slate-700 rounded-xl px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-blue-500 dark:bg-slate-900/50 dark:text-slate-100">
                    <p class="text-xs text-gray-400 mt-1">Jumlah ini akan langsung ditambahkan ke saldo dompet.</p>
                </div>
                <div class="flex gap-3 pt-2">
                    <button type="button" @click="showTopup = false" class="flex-1 px-4 py-2.5 text-gray-600 dark:text-slate-400 bg-gray-100 hover:bg-gray-200 dark:bg-slate-700 rounded-xl font-medium">Batal</button>
                    <button type="submit" class="flex-1 px-4 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl font-semibold shadow">Tambah Saldo</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Tambah / Edit Dompet -->
    <div x-show="showForm" x-transition.opacity class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div @click="showForm = false" class="absolute inset-0 bg-black/50 backdrop-blur-sm"></div>
        <div class="relative glass-card border border-white/60 dark:border-slate-700/50 dark:bg-slate-800/90 rounded-2xl shadow-2xl backdrop-blur-xl w-full max-w-md p-6 z-10">
            <div class="flex items-center justify-between mb-5">
                <h3 class="text-lg font-bold text-gray-900 dark:text-slate-100" x-text="isEdit ? 'Edit Dompet' : 'Tambah Dompet Baru'"></h3>
                <button @click="showForm = false" class="text-gray-400 hover:text-gray-600 dark:text-slate-400">
                    <i class="ph ph-x text-xl"></i>
                </button>
            </div>
            <form method="POST" class="space-y-4">
                <?= PahamFin_csrf_field() ?>
                <input type="hidden" name="action" :value="isEdit ? 'edit' : 'create'">
                <input type="hidden" name="id" :value="editId">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1.5">Nama Dompet</label>
                    <input type="text" name="name" placeholder="Tunai, BCA, Gopay, OVO, DANA..." required
                           x-model="editName"
                           class="w-full border border-gray-300 dark:border-slate-700 dark:bg-slate-900/50 dark:text-white rounded-xl px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1.5">Saldo Awal (Rp)</label>
                    <input type="number" name="starting_balance" placeholder="0" min="0"
                           x-model="editBalance"
                           class="w-full border border-gray-300 dark:border-slate-700 dark:bg-slate-900/50 dark:text-white rounded-xl px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-blue-500">
                    <p class="text-xs text-gray-400 mt-1">Isi sesuai saldo awal saat ini (bisa dikosongi jika mulai dari 0)</p>
                </div>
                <div class="flex gap-3 pt-2">
                    <button type="button" @click="showForm = false" class="flex-1 px-4 py-2.5 text-gray-600 dark:text-slate-400 bg-gray-100 hover:bg-gray-200 dark:bg-slate-700 rounded-xl font-medium transition">Batal</button>
                    <button type="submit" class="flex-1 px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl font-semibold shadow transition"
                            x-text="isEdit ? 'Simpan Perubahan' : 'Tambah Dompet'">
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../app/includes/footer.php'; ?>







