<?php
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/includes/config.php';
require_once __DIR__ . '/../app/includes/auth.php';
require_login();
$user_id = current_user_id();

/* ── POST handlers (must be before any HTML output) ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!PahamFin_csrf_verify()) {
        header('Location: categories.php?error=csrf'); exit;
    }
    $action = $_POST['action'] ?? '';

    if ($action === 'add' || $action === 'edit') {
        $name    = trim((string) ($_POST['name']    ?? ''));
        $keyword = trim((string) ($_POST['keyword'] ?? ''));
        $type    = $_POST['type'] ?? 'PENGELUARAN';
        $id      = (int) ($_POST['id'] ?? 0);
        if ($name !== '' && $keyword !== '') {
            if ($action === 'edit' && $id > 0) {
                $pdo->prepare("UPDATE categories SET name=?, keyword=?, type=? WHERE id=? AND user_id=?")
                    ->execute([$name, $keyword, $type, $id, $user_id]);
            } else {
                $pdo->prepare("INSERT INTO categories (user_id, name, keyword, type) VALUES (?,?,?,?)")
                    ->execute([$user_id, $name, $keyword, $type]);
            }
        }
        header('Location: categories.php?success=1'); exit;

    } elseif ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        $pdo->prepare("DELETE FROM categories WHERE id=? AND user_id=?")->execute([$id, $user_id]);
        header('Location: categories.php?success=1'); exit;

    } elseif ($action === 'add_preset') {
        $presetName = trim((string) ($_POST['preset'] ?? ''));
        $presets = [
            'daily' => [
                ['name' => 'Makan',           'keyword' => 'makan, nasi, sarapan, makan siang, makan malam, jajan', 'type' => 'PENGELUARAN'],
                ['name' => 'Transportasi',     'keyword' => 'ojek, grab, gojek, bensin, tol, travel, bus, krl',      'type' => 'PENGELUARAN'],
                ['name' => 'Belanja',          'keyword' => 'belanja, beli, minimarket, indomaret, alfamart',         'type' => 'PENGELUARAN'],
                ['name' => 'Internet & Pulsa', 'keyword' => 'pulsa, kuota, wifi, internet, listrik',                  'type' => 'PENGELUARAN'],
                ['name' => 'Gaji',             'keyword' => 'gaji, honor, upah, transfer masuk, uang masuk',          'type' => 'PEMASUKAN'],
            ],
            'food' => [
                ['name' => 'Makan',   'keyword' => 'makan, nasi, sarapan, makan siang, makan malam, jajan', 'type' => 'PENGELUARAN'],
                ['name' => 'Minuman', 'keyword' => 'kopi, teh, minum, jus, boba, susu',                     'type' => 'PENGELUARAN'],
                ['name' => 'Snack',   'keyword' => 'snack, camilan, keripik, coklat, roti',                  'type' => 'PENGELUARAN'],
            ],
            'others' => [
                ['name' => 'Tabungan',  'keyword' => 'tabungan, simpanan, celengan, deposito',                    'type' => 'TABUNGAN'],
                ['name' => 'Investasi', 'keyword' => 'investasi, saham, reksadana, crypto, emas, sbn',            'type' => 'PENGELUARAN'],
                ['name' => 'Hiburan',   'keyword' => 'nonton, main, game, bioskop, piknik, liburan',              'type' => 'PENGELUARAN'],
                ['name' => 'Tagihan',   'keyword' => 'tagihan, cicilan, asuransi, bpjs, kartu kredit, paylater',  'type' => 'PENGELUARAN'],
                ['name' => 'Lain-lain', 'keyword' => 'lainnya, tak terduga, donasi, sedekah, parkir, sumbangan', 'type' => 'PENGELUARAN'],
            ],
        ];
        $list  = $presets[$presetName] ?? [];
        $check = $pdo->prepare("SELECT COUNT(*) FROM categories WHERE user_id=? AND LOWER(name)=LOWER(?)");
        $ins   = $pdo->prepare("INSERT INTO categories (user_id, name, keyword, type) VALUES (?,?,?,?)");
        $added = 0;
        foreach ($list as $p) {
            $check->execute([$user_id, $p['name']]);
            if ((int) $check->fetchColumn() > 0) continue;
            $ins->execute([$user_id, $p['name'], $p['keyword'], $p['type']]);
            $added++;
        }
        header('Location: categories.php?success=' . $added); exit;
    }
}

/* ── Data ── */
$stmt = $pdo->prepare("SELECT * FROM categories WHERE user_id=? ORDER BY type, name");
$stmt->execute([$user_id]);
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/../app/includes/header.php';
require_once __DIR__ . '/../app/includes/sidebar.php';
?>

<div x-data="{
    showForm: false,
    showDelete: false,
    deleteId: null,
    editMode: false,
    form: { id: null, name: '', keyword: '', type: 'PENGELUARAN' },
    openAdd() {
        this.editMode = false;
        this.form = { id: null, name: '', keyword: '', type: 'PENGELUARAN' };
        this.showForm = true;
    },
    openEdit(id, name, keyword, type) {
        this.editMode = true;
        this.form = { id, name, keyword, type };
        this.showForm = true;
    }
}">
    <div class="grid grid-cols-1 gap-6">

        <!-- Tabel Kategori -->
        <div class="glass-card border border-white/60 dark:border-slate-700/50 dark:bg-slate-800/60 rounded-2xl shadow-sm overflow-hidden">
            <div class="p-4 lg:p-6 border-b border-gray-100 dark:border-slate-700/50 flex flex-wrap justify-between items-center gap-3">
                <div>
                    <h3 class="text-base lg:text-lg font-semibold text-gray-900 dark:text-slate-100">Daftar Kategori</h3>
                    <p class="text-xs text-gray-500 dark:text-slate-400 mt-0.5"><?= count($categories) ?> kategori terdaftar</p>
                </div>
                <div class="flex items-center gap-3">
                    <?php if (!empty($_GET['success'])): ?>
                        <span class="text-xs text-emerald-600 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-900/30 rounded-full px-2 py-1">✓ Tersimpan</span>
                    <?php endif; ?>
                    <?php if (($_GET['error'] ?? '') === 'csrf'): ?>
                        <span class="text-xs text-red-600 dark:text-red-400 bg-red-50 dark:bg-red-900/30 rounded-full px-2 py-1">Sesi tidak valid</span>
                    <?php endif; ?>
                    <button @click="openAdd()" class="px-4 py-2 bg-blue-600 text-white rounded-xl font-semibold shadow hover:bg-blue-700 flex items-center gap-2 text-sm">
                        <i class="ph ph-plus-circle text-lg"></i> Tambah Kategori
                    </button>
                </div>
            </div>

            <div class="overflow-x-auto">
                <?php if (empty($categories)): ?>
                    <div class="p-12 text-center">
                        <i class="ph ph-tag text-5xl text-gray-300 dark:text-slate-600 mb-3 block"></i>
                        <p class="text-gray-500 dark:text-slate-400 text-sm">Belum ada kategori. Tambahkan atau gunakan preset di bawah.</p>
                    </div>
                <?php else: ?>
                <table class="w-full text-left border-collapse min-w-[560px]">
                    <thead>
                        <tr class="bg-gray-50 dark:bg-slate-700/40 text-gray-500 dark:text-slate-400 text-xs uppercase tracking-wider">
                            <th class="p-4 font-medium">Nama Kategori</th>
                            <th class="p-4 font-medium">Keyword Bot</th>
                            <th class="p-4 font-medium">Tipe</th>
                            <th class="p-4 font-medium text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="text-sm divide-y divide-gray-100 dark:divide-slate-700/40">
                        <?php foreach ($categories as $c):
                            $badge = match($c['type']) {
                                'PEMASUKAN'   => ['label' => 'Pemasukan',   'cls' => 'text-emerald-600 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-900/30'],
                                'PENGELUARAN' => ['label' => 'Pengeluaran', 'cls' => 'text-rose-600 dark:text-rose-400 bg-rose-50 dark:bg-rose-900/30'],
                                'TABUNGAN'    => ['label' => 'Tabungan',    'cls' => 'text-blue-600 dark:text-blue-400 bg-blue-50 dark:bg-blue-900/30'],
                                'HUTANG'      => ['label' => 'Hutang',      'cls' => 'text-amber-600 dark:text-amber-400 bg-amber-50 dark:bg-amber-900/30'],
                                'PIUTANG'     => ['label' => 'Piutang',     'cls' => 'text-purple-600 dark:text-purple-400 bg-purple-50 dark:bg-purple-900/30'],
                                default       => ['label' => $c['type'],    'cls' => 'text-gray-600 dark:text-slate-400 bg-gray-100 dark:bg-slate-700/50'],
                            };
                        ?>
                        <tr class="hover:bg-gray-50 dark:hover:bg-slate-700/30 transition-colors">
                            <td class="p-4 font-medium text-gray-900 dark:text-slate-100"><?= htmlspecialchars($c['name']) ?></td>
                            <td class="p-4">
                                <div class="flex flex-wrap gap-1">
                                    <?php foreach (explode(',', $c['keyword']) as $kw):
                                        $kw = trim($kw); if ($kw === '') continue; ?>
                                        <span class="bg-slate-100 dark:bg-slate-700/60 text-gray-600 dark:text-slate-300 px-2 py-0.5 rounded-md text-xs"><?= htmlspecialchars($kw) ?></span>
                                    <?php endforeach; ?>
                                </div>
                            </td>
                            <td class="p-4">
                                <span class="<?= $badge['cls'] ?> px-2 py-1 rounded-full text-xs font-semibold"><?= $badge['label'] ?></span>
                            </td>
                            <td class="p-4">
                                <div class="flex justify-center gap-2">
                                    <button type="button"
                                        @click="openEdit(<?= $c['id'] ?>, '<?= addslashes(htmlspecialchars($c['name'])) ?>', '<?= addslashes(htmlspecialchars($c['keyword'])) ?>', '<?= $c['type'] ?>')"
                                        class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg text-xs font-medium bg-blue-50 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 hover:bg-blue-100 dark:hover:bg-blue-900/50 transition">
                                        <i class="ph ph-pencil-simple"></i> Edit
                                    </button>
                                    <button type="button"
                                        @click="deleteId = <?= $c['id'] ?>; showDelete = true"
                                        class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg text-xs font-medium bg-red-50 dark:bg-red-900/30 text-red-600 dark:text-red-400 hover:bg-red-100 dark:hover:bg-red-900/50 transition">
                                        <i class="ph ph-trash"></i> Hapus
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>

        <!-- Preset Kategori -->
        <div class="glass-card border border-white/60 dark:border-slate-700/50 dark:bg-slate-800/60 rounded-2xl shadow-sm overflow-hidden">
            <div class="p-4 lg:p-6 border-b border-gray-100 dark:border-slate-700/50">
                <h3 class="text-base lg:text-lg font-semibold text-gray-900 dark:text-slate-100">⚡ Preset Kategori</h3>
                <p class="text-xs text-gray-500 dark:text-slate-400 mt-1">Tambah sekaligus kategori populer dengan sekali klik. Kategori yang sudah ada tidak akan ditambah dua kali.</p>
            </div>
            <div class="p-4 lg:p-6 grid grid-cols-1 lg:grid-cols-3 gap-4">
                <form method="POST">
                    <?= PahamFin_csrf_field() ?>
                    <input type="hidden" name="action" value="add_preset">
                    <input type="hidden" name="preset" value="daily">
                    <button type="submit" class="h-full w-full flex flex-col justify-center gap-2 px-5 py-4 border border-blue-200 dark:border-blue-800/50 bg-blue-50/50 dark:bg-blue-900/20 hover:bg-blue-50 dark:hover:bg-blue-900/30 rounded-xl text-left transition relative group">
                        <i class="ph ph-lightning text-2xl text-blue-500 dark:text-blue-400 absolute right-4 top-4 opacity-40 group-hover:opacity-70 transition"></i>
                        <span class="block font-semibold text-gray-800 dark:text-slate-200 text-sm">Kebutuhan Harian</span>
                        <span class="block text-xs text-gray-500 dark:text-slate-400">Makan, Transportasi, Belanja, Pulsa, Gaji</span>
                    </button>
                </form>
                <form method="POST">
                    <?= PahamFin_csrf_field() ?>
                    <input type="hidden" name="action" value="add_preset">
                    <input type="hidden" name="preset" value="food">
                    <button type="submit" class="h-full w-full flex flex-col justify-center gap-2 px-5 py-4 border border-amber-200 dark:border-amber-800/50 bg-amber-50/50 dark:bg-amber-900/20 hover:bg-amber-50 dark:hover:bg-amber-900/30 rounded-xl text-left transition relative group">
                        <i class="ph ph-bowl-food text-2xl text-amber-500 dark:text-amber-400 absolute right-4 top-4 opacity-40 group-hover:opacity-70 transition"></i>
                        <span class="block font-semibold text-gray-800 dark:text-slate-200 text-sm">Makan & Minum</span>
                        <span class="block text-xs text-gray-500 dark:text-slate-400">Makan, Minuman, Snack</span>
                    </button>
                </form>
                <form method="POST">
                    <?= PahamFin_csrf_field() ?>
                    <input type="hidden" name="action" value="add_preset">
                    <input type="hidden" name="preset" value="others">
                    <button type="submit" class="h-full w-full flex flex-col justify-center gap-2 px-5 py-4 border border-purple-200 dark:border-purple-800/50 bg-purple-50/50 dark:bg-purple-900/20 hover:bg-purple-50 dark:hover:bg-purple-900/30 rounded-xl text-left transition relative group">
                        <i class="ph ph-dots-three-circle text-2xl text-purple-500 dark:text-purple-400 absolute right-4 top-4 opacity-40 group-hover:opacity-70 transition"></i>
                        <span class="block font-semibold text-gray-800 dark:text-slate-200 text-sm">Lain-lain & Tabungan</span>
                        <span class="block text-xs text-gray-500 dark:text-slate-400">Tabungan, Investasi, Hiburan, Tagihan</span>
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Konfirmasi Hapus -->
    <div x-show="showDelete" style="display: none;" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div x-show="showDelete" x-transition.opacity @click="showDelete = false" class="absolute inset-0 bg-black/60 backdrop-blur-sm"></div>
        <div x-show="showDelete" x-transition class="relative glass-card border border-white/60 dark:border-slate-700/50 dark:bg-slate-800/90 rounded-2xl shadow-2xl backdrop-blur-xl w-full max-w-sm p-6 z-10 text-center">
            <div class="w-16 h-16 rounded-full bg-red-50 dark:bg-red-900/30 flex items-center justify-center mx-auto mb-4">
                <i class="ph ph-trash text-3xl text-red-500 dark:text-red-400"></i>
            </div>
            <h4 class="font-bold text-gray-900 dark:text-slate-100 text-lg mb-2">Hapus Kategori?</h4>
            <p class="text-sm text-gray-500 dark:text-slate-400 mb-6">Kategori akan dihapus permanen. Transaksi yang terkait juga akan ikut terhapus.</p>
            <div class="flex gap-3">
                <button @click="showDelete = false" class="flex-1 px-4 py-2.5 text-gray-600 dark:text-slate-300 bg-gray-100 dark:bg-slate-700 hover:bg-gray-200 dark:hover:bg-slate-600 rounded-xl font-medium transition">
                    <i class="ph ph-x mr-1"></i> Batal
                </button>
                <form method="POST" class="flex-1">
                    <?= PahamFin_csrf_field() ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" :value="deleteId">
                    <button type="submit" class="w-full px-4 py-2.5 bg-red-600 hover:bg-red-700 text-white rounded-xl font-medium transition">
                        <i class="ph ph-trash mr-1"></i> Ya, Hapus
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Tambah / Edit Kategori -->
    <div x-show="showForm" style="display: none;" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div x-show="showForm" x-transition.opacity @click="showForm = false" class="absolute inset-0 bg-black/60 backdrop-blur-sm"></div>
        <div x-show="showForm"
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0 translate-y-8 scale-95"
             x-transition:enter-end="opacity-100 translate-y-0 scale-100"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100 translate-y-0 scale-100"
             x-transition:leave-end="opacity-0 translate-y-8 scale-95"
             class="relative glass-card border border-white/60 dark:border-slate-700/50 dark:bg-slate-800/90 rounded-2xl shadow-2xl backdrop-blur-xl w-full max-w-md p-6 z-10">

            <div class="flex items-center justify-between mb-5 border-b border-gray-100 dark:border-slate-700/50 pb-4">
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 rounded-lg bg-blue-50 dark:bg-blue-900/30 flex items-center justify-center">
                        <i class="ph text-blue-600 dark:text-blue-400" :class="editMode ? 'ph-pencil-simple' : 'ph-tag'"></i>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-slate-100" x-text="editMode ? 'Edit Kategori' : 'Tambah Kategori'"></h3>
                </div>
                <button @click="showForm = false" class="w-8 h-8 flex items-center justify-center rounded-lg text-gray-400 hover:text-red-500 dark:hover:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/30 transition">
                    <i class="ph ph-x text-xl"></i>
                </button>
            </div>

            <form method="POST" class="space-y-4">
                <?= PahamFin_csrf_field() ?>
                <input type="hidden" name="action" :value="editMode ? 'edit' : 'add'">
                <input type="hidden" name="id" :value="form.id">

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1">Nama Kategori</label>
                    <input type="text" name="name" x-model="form.name" required
                        class="w-full border border-gray-300 dark:border-slate-600 dark:bg-slate-900/50 dark:text-slate-100 rounded-xl px-3 py-2.5 focus:ring-2 focus:ring-blue-500 outline-none placeholder-gray-400 dark:placeholder-slate-500"
                        placeholder="Misal: Makan Malam">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1">
                        Keyword Bot <span class="text-gray-400 dark:text-slate-500 font-normal">(pisahkan dengan koma)</span>
                    </label>
                    <input type="text" name="keyword" x-model="form.keyword" required
                        class="w-full border border-gray-300 dark:border-slate-600 dark:bg-slate-900/50 dark:text-slate-100 rounded-xl px-3 py-2.5 focus:ring-2 focus:ring-blue-500 outline-none placeholder-gray-400 dark:placeholder-slate-500"
                        placeholder="Misal: makan, kfc, bakso">
                    <p class="text-xs text-gray-500 dark:text-slate-500 mt-1.5">Bot akan mencocokkan pesan dengan kata kunci ini secara otomatis.</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1">Tipe Kategori</label>
                    <select name="type" x-model="form.type"
                        class="w-full border border-gray-300 dark:border-slate-600 dark:bg-slate-900/50 dark:text-slate-100 rounded-xl px-3 py-2.5 focus:ring-2 focus:ring-blue-500 outline-none">
                        <option value="PENGELUARAN">Pengeluaran</option>
                        <option value="PEMASUKAN">Pemasukan</option>
                        <option value="TABUNGAN">Tabungan</option>
                        <option value="HUTANG">Hutang (Bayar Utang)</option>
                        <option value="PIUTANG">Piutang (Terima Utang)</option>
                    </select>
                </div>

                <div class="flex gap-3 pt-2">
                    <button type="button" @click="showForm = false"
                        class="flex-1 py-2.5 text-gray-600 dark:text-slate-300 bg-gray-100 dark:bg-slate-700 hover:bg-gray-200 dark:hover:bg-slate-600 rounded-xl font-medium transition">
                        Batal
                    </button>
                    <button type="submit"
                        class="flex-1 py-2.5 bg-blue-600 text-white rounded-xl font-medium hover:bg-blue-700 transition flex items-center justify-center gap-2">
                        <i class="ph" :class="editMode ? 'ph-floppy-disk' : 'ph-tag'"></i>
                        <span x-text="editMode ? 'Simpan Perubahan' : 'Tambah Kategori'"></span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../app/includes/footer.php'; ?>
