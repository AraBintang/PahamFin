<?php
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/includes/config.php';
require_once __DIR__ . '/../app/includes/auth.php';
require_login();
$user_id = current_user_id();

/* ==================== DELETE ==================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    if (!PahamFin_csrf_verify()) {
        header('Location: transactions.php?error=csrf');
        exit;
    }
    $id = (int) ($_POST['id'] ?? 0);
    $stmt = $pdo->prepare("DELETE FROM transactions WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, $user_id]);
    if ($stmt->rowCount() === 0) {
        $stmt = $pdo->prepare("DELETE FROM transaction_archive WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, $user_id]);
    }
    header('Location: transactions.php?success=deleted');
    exit;
}

/* ==================== ADD / EDIT ==================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_POST['action'] ?? '', ['add', 'edit'], true)) {
    if (!PahamFin_csrf_verify()) {
        header('Location: transactions.php?error=csrf');
        exit;
    }

    $id = (int) ($_POST['id'] ?? 0);
    $categoryId = (int) ($_POST['category_id'] ?? 0);
    $amount = (float) ($_POST['amount'] ?? 0);
    $description = trim((string) ($_POST['description'] ?? ''));
    $type = ($_POST['type'] ?? 'PENGELUARAN') === 'PEMASUKAN' ? 'PEMASUKAN' : 'PENGELUARAN';
    $date = trim((string) ($_POST['transaction_date'] ?? ''));
    if ($date === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        $date = date('Y-m-d');
    }

    if ($categoryId > 0 && $amount > 0 && $amount <= 9999999999) {
        $catStmt = $pdo->prepare("SELECT type FROM categories WHERE id = ? AND user_id = ? LIMIT 1");
        $catStmt->execute([$categoryId, $user_id]);
        $categoryType = $catStmt->fetchColumn();
        $finalType = $categoryType ?: $type;
        $finalDesc = $description !== '' ? $description : 'Transaksi';

        if ($_POST['action'] === 'edit' && $id > 0) {
            $stmt = $pdo->prepare("UPDATE transactions SET category_id = ?, amount = ?, description = ?, type = ?, transaction_date = ? WHERE id = ? AND user_id = ?");
            $stmt->execute([$categoryId, $amount, $finalDesc, $finalType, $date, $id, $user_id]);
            if ($stmt->rowCount() === 0) {
                $stmt = $pdo->prepare("UPDATE transaction_archive SET category_id = ?, amount = ?, description = ?, type = ?, transaction_date = ? WHERE id = ? AND user_id = ?");
                $stmt->execute([$categoryId, $amount, $finalDesc, $finalType, $date, $id, $user_id]);
            }
            header('Location: transactions.php?success=updated');
            exit;
        } else {
            $stmt = $pdo->prepare("INSERT INTO transactions (user_id, category_id, amount, description, type, transaction_date) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$user_id, $categoryId, $amount, $finalDesc, $finalType, $date]);
            if ($finalType === 'PEMASUKAN') {
                PahamFin_notify($pdo, $user_id, 'income', 'Uang masuk: ' . $finalDesc . ' sebesar Rp ' . number_format($amount, 0, ',', '.'), (float) $amount);
            }
            try {
                PahamFin_check_budget_alerts($pdo, $user_id, $categoryId);
            } catch (Throwable $e) {
                // Notifikasi anggaran opsional.
            }
            header('Location: transactions.php?success=added');
            exit;
        }
    } else {
        header('Location: transactions.php?error=invalid');
        exit;
    }
}





/* ==================== DATA ==================== */
$editId = isset($_GET['edit_id']) ? (int) $_GET['edit_id'] : 0;
$editTransaction = null;
if ($editId > 0) {
    $editStmt = $pdo->prepare("SELECT * FROM transactions WHERE id = ? AND user_id = ? LIMIT 1");
    $editStmt->execute([$editId, $user_id]);
    $editTransaction = $editStmt->fetch(PDO::FETCH_ASSOC);
    if (!$editTransaction) {
        $editStmt = $pdo->prepare("SELECT * FROM transaction_archive WHERE id = ? AND user_id = ? LIMIT 1");
        $editStmt->execute([$editId, $user_id]);
        $editTransaction = $editStmt->fetch(PDO::FETCH_ASSOC);
    }
}

$filters = [
    'type' => trim((string) ($_GET['type'] ?? '')),
    'category' => (int) ($_GET['category'] ?? 0),
    'date_from' => trim((string) ($_GET['date_from'] ?? '')),
    'date_to' => trim((string) ($_GET['date_to'] ?? '')),
    'search' => trim((string) ($_GET['search'] ?? '')),
];

$catStmt = $pdo->prepare("SELECT * FROM categories WHERE user_id = ? ORDER BY type, name");
$catStmt->execute([$user_id]);
$categories = $catStmt->fetchAll(PDO::FETCH_ASSOC);

/* --- Query dengan filter + pencarian + pagination --- */
$where = ["t.user_id = ?"];
$params = [$user_id];

if ($filters['type'] !== '') {
    $where[] = "t.type = ?";
    $params[] = $filters['type'];
}
if ($filters['category'] > 0) {
    $where[] = "c.id = ?";
    $params[] = $filters['category'];
}
if ($filters['date_from'] !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $filters['date_from'])) {
    $where[] = "t.transaction_date >= ?";
    $params[] = $filters['date_from'];
}
if ($filters['date_to'] !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $filters['date_to'])) {
    $where[] = "t.transaction_date <= ?";
    $params[] = $filters['date_to'] . ' 23:59:59';
}
if ($filters['search'] !== '') {
    $where[] = "(t.description LIKE ? OR c.name LIKE ?)";
    $params[] = '%' . $filters['search'] . '%';
    $params[] = '%' . $filters['search'] . '%';
}

$whereSql = implode(' AND ', $where);

/* Definisi tabel gabungan untuk riwayat (termasuk arsip) */
$unionTable = "(
    SELECT id, user_id, category_id, amount, description, type, transaction_date, created_at FROM transactions 
    UNION ALL 
    SELECT id, user_id, category_id, amount, description, type, transaction_date, created_at FROM transaction_archive
) t";

/* Hitung total untuk pagination */
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM $unionTable JOIN categories c ON c.id = t.category_id WHERE $whereSql");
$countStmt->execute($params);
$totalRows = (int) $countStmt->fetchColumn();

$perPage = 20;
$page = max(1, (int) ($_GET['page'] ?? 1));
$totalPages = max(1, (int) ceil($totalRows / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$sql = "SELECT t.*, c.name as category_name FROM $unionTable
        JOIN categories c ON c.id = t.category_id
        WHERE $whereSql
        ORDER BY t.transaction_date DESC, t.id DESC
        LIMIT $perPage OFFSET $offset";
$txStmt = $pdo->prepare($sql);
$txStmt->execute($params);
$transactions = $txStmt->fetchAll(PDO::FETCH_ASSOC);

/* --- Query string untuk menjaga filter saat pindah halaman --- */
$queryExtra = [];
foreach ($filters as $k => $v) {
    if (is_array($v)) continue;
    if ($v !== '' && $v !== 0) {
        $queryExtra[$k] = $v;
    }
}
$filterQuery = http_build_query($queryExtra);

if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $csvSql = "SELECT t.transaction_date, c.name AS category_name, t.type, t.description, t.amount 
               FROM $unionTable 
               JOIN categories c ON c.id = t.category_id 
               WHERE $whereSql
               ORDER BY t.transaction_date DESC, t.id DESC";
    $csvStmt = $pdo->prepare($csvSql);
    $csvStmt->execute($params);
    $rows = $csvStmt->fetchAll(PDO::FETCH_ASSOC);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=Laporan_PahamFin_' . date('Y-m-d') . '.csv');
    $output = fopen('php://output', 'w');

    // Sisipkan UTF-8 BOM agar WPS Office & Microsoft Excel mengenali encoding
    fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

    $delimiter = ';';

    // Helper penulisan baris 5 kolom konsisten (kolom A, B, C, D, E)
    $writeRow = function($col1 = '', $col2 = '', $col3 = '', $col4 = '', $col5 = '') use ($output, $delimiter) {
        fputcsv($output, [$col1, $col2, $col3, $col4, $col5], $delimiter);
    };

    // Tentukan teks periode filter dinamis
    $periodText = "Semua Waktu";
    if ($filters['date_from'] !== '' && $filters['date_to'] !== '') {
        $periodText = date('d/m/Y', strtotime($filters['date_from'])) . " s/d " . date('d/m/Y', strtotime($filters['date_to']));
    } elseif ($filters['date_from'] !== '') {
        $periodText = "Sejak " . date('d/m/Y', strtotime($filters['date_from']));
    } elseif ($filters['date_to'] !== '') {
        $periodText = "Hingga " . date('d/m/Y', strtotime($filters['date_to']));
    }

    // Hitung Ringkasan
    $totalPemasukan = 0;
    $totalPengeluaran = 0;
    foreach ($rows as $row) {
        $amt = (float) $row['amount'];
        if ($row['type'] === 'PEMASUKAN') {
            $totalPemasukan += $amt;
        } else {
            $totalPengeluaran += $amt;
        }
    }
    $saldoBersih = $totalPemasukan - $totalPengeluaran;

    // Header Laporan Center (Ditempatkan di Kolom C agar Center seperti Foto 2)
    $writeRow('', '', '🚀 LAPORAN KEUANGAN PAHAMFIN');
    $writeRow('', '', 'Catat Otomatis, Laporan Rapi, Keuangan Makin Sehat & Terencana! ✨');
    $writeRow('', '', '📅 PERIODE LAPORAN    ' . $periodText);
    $writeRow();
    $writeRow('📊 RINGKASAN KEUANGAN');
    $writeRow('🟢 Total Pemasukan', 'Rp ' . number_format($totalPemasukan, 0, ',', '.'));
    $writeRow('🔴 Total Pengeluaran', 'Rp ' . number_format($totalPengeluaran, 0, ',', '.'));
    $writeRow('🟦 Saldo Bersih (Net)', 'Rp ' . number_format($saldoBersih, 0, ',', '.'));
    $writeRow();

    // Header Tabel Utama
    $writeRow('Tanggal', 'Kategori', 'Tipe', 'Deskripsi', 'Nominal (Rp)');

    // Isi Data Transaksi
    foreach ($rows as $row) {
        $amt = (float) $row['amount'];
        $writeRow(
            $row['transaction_date'],
            $row['category_name'],
            $row['type'],
            $row['description'],
            number_format($amt, 0, ',', '.')
        );
    }

    fclose($output);
    exit;
}
?>

<!-- Notifikasi -->
<?php
$flash = $_GET['success'] ?? '';
$flashMsg = [
    'added' => 'Transaksi berhasil ditambahkan.',
    'updated' => 'Transaksi berhasil diperbarui.',
    'deleted' => 'Transaksi berhasil dihapus.',
][$flash] ?? '';
$flashType = in_array($flash, ['added', 'updated', 'deleted'], true) ? 'green' : 'red';
$errorFlash = $_GET['error'] ?? '';
$errorMsg = ['csrf' => 'Sesi tidak valid, coba lagi.', 'invalid' => 'Data transaksi tidak valid.'][$errorFlash] ?? '';
?>

<?php if ($flashMsg !== ''): ?>
    <div class="p-3 rounded-lg bg-green-50 dark:bg-green-900/30 border border-green-200 dark:border-green-800/50 text-green-700 dark:text-green-300 text-sm"><?= htmlspecialchars($flashMsg) ?></div>
<?php endif; ?>
<?php if ($errorMsg !== ''): ?>
    <div class="p-3 rounded-lg bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-800/50 text-red-700 dark:text-red-300 text-sm"><?= htmlspecialchars($errorMsg) ?></div>
<?php endif; ?>

<?php
require_once __DIR__ . '/../app/includes/header.php';
require_once __DIR__ . '/../app/includes/sidebar.php';
?>
<div x-data="{
    showForm: false,
    showDelete: false,
    deleteId: null,
    editMode: false,
    form: {
        id: null,
        category_id: '<?= $editTransaction ? $editTransaction['category_id'] : '' ?>',
        type: '<?= $editTransaction ? $editTransaction['type'] : 'PENGELUARAN' ?>',
        amount: '<?= $editTransaction ? $editTransaction['amount'] : '' ?>',
        transaction_date: '<?= $editTransaction ? $editTransaction['transaction_date'] : date('Y-m-d') ?>',
        description: '<?= $editTransaction ? addslashes($editTransaction['description']) : '' ?>'
    },
    openAdd() {
        this.editMode = false;
        this.form = { id: null, category_id: '', type: 'PENGELUARAN', amount: '', transaction_date: '<?= date('Y-m-d') ?>', description: '' };
        this.showForm = true;
    },
    openEdit(id, category_id, type, amount, date, desc) {
        this.editMode = true;
        this.form = { id, category_id: String(category_id), type, amount, transaction_date: date, description: desc };
        this.showForm = true;
        this.$nextTick(() => {
            const sel = document.getElementById('categorySelect');
            if (sel) sel.value = String(category_id);
        });
    }
}" x-init="<?= $editTransaction ? "openEdit({$editTransaction['id']}, {$editTransaction['category_id']}, '{$editTransaction['type']}', {$editTransaction['amount']}, '{$editTransaction['transaction_date']}', '".addslashes($editTransaction['description'])."')" : '' ?>">
    <div class="grid grid-cols-1 gap-6">
        <div class="glass-card border border-white/60 dark:border-slate-700/50 dark:bg-slate-800/80 rounded-2xl shadow-sm overflow-hidden">
            <div class="p-4 lg:p-6 border-b border-gray-100 dark:border-slate-700/50 flex flex-wrap justify-between items-center gap-3">
                <div>
                    <h3 class="text-base lg:text-lg font-semibold text-gray-900 dark:text-slate-100">Riwayat Semua Transaksi</h3>
                    <span class="text-xs text-gray-500 dark:text-slate-400"><?= $totalRows ?> transaksi</span>
                </div>
                <div class="flex items-center gap-2">
                    <a href="print_report.php?<?= htmlspecialchars($filterQuery) ?>" target="_blank" class="px-4 py-2 bg-rose-600 text-white rounded-xl font-semibold shadow hover:bg-rose-700 flex items-center gap-2 text-sm transition">
                        <i class="ph ph-file-pdf text-lg"></i> Laporan PDF
                    </a>
                    <a href="transactions.php?export=csv&<?= htmlspecialchars($filterQuery) ?>" class="px-4 py-2 bg-emerald-600 text-white rounded-xl font-semibold shadow hover:bg-emerald-700 flex items-center gap-2 text-sm transition">
                        <i class="ph ph-file-csv text-lg"></i> CSV
                    </a>
                    <button @click="openAdd()" class="px-4 py-2 bg-blue-600 text-white rounded-xl font-semibold shadow hover:bg-blue-700 flex items-center gap-2 text-sm transition">
                        <i class="ph ph-plus-circle text-lg"></i> Tambah
                    </button>
                </div>
            </div>

            <!-- Filter -->
            <div class="p-4 lg:p-6 border-b border-gray-100 dark:border-slate-700/50">
                <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-3">
                    <input type="text" name="search" value="<?= htmlspecialchars($filters['search']) ?>" placeholder="Cari keterangan / kategori..." class="border border-gray-300 dark:border-slate-600 dark:bg-slate-900/50 dark:text-slate-100 rounded-lg px-3 py-2 outline-none focus:ring-2 focus:ring-blue-500 md:col-span-4">
                    <select name="type" class="border border-gray-300 dark:border-slate-600 dark:bg-slate-900/50 dark:text-slate-100 rounded-lg px-3 py-2 outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">Semua tipe</option>
                        <option value="PEMASUKAN" <?= $filters['type'] === 'PEMASUKAN' ? 'selected' : '' ?>>Pemasukan</option>
                        <option value="PENGELUARAN" <?= $filters['type'] === 'PENGELUARAN' ? 'selected' : '' ?>>Pengeluaran</option>
                    </select>
                    <select name="category" class="border border-gray-300 dark:border-slate-600 dark:bg-slate-900/50 dark:text-slate-100 rounded-lg px-3 py-2 outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="0">Semua kategori</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?= $category['id'] ?>" <?= $filters['category'] == $category['id'] ? 'selected' : '' ?>><?= htmlspecialchars($category['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="date" name="date_from" value="<?= htmlspecialchars($filters['date_from']) ?>" class="border border-gray-300 dark:border-slate-600 dark:bg-slate-900/50 dark:text-slate-100 rounded-lg px-3 py-2 outline-none focus:ring-2 focus:ring-blue-500" title="Dari tanggal">
                    <input type="date" name="date_to" value="<?= htmlspecialchars($filters['date_to']) ?>" class="border border-gray-300 dark:border-slate-600 dark:bg-slate-900/50 dark:text-slate-100 rounded-lg px-3 py-2 outline-none focus:ring-2 focus:ring-blue-500" title="Sampai tanggal">
                    <div class="md:col-span-4 flex justify-end gap-2 mt-2">
                        <a href="transactions.php" class="px-4 py-2 border border-gray-300 dark:border-slate-600 rounded-lg text-sm font-medium hover:bg-gray-50 dark:bg-slate-700/50 text-gray-700 dark:text-slate-300">Reset</a>
                        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700">Terapkan Filter</button>
                    </div>
                </form>
            </div>

            <!-- Tabel Transaksi -->
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse min-w-[820px]">
                    <thead>
                        <tr class="bg-gray-50 dark:bg-slate-700/50 text-gray-500 dark:text-slate-400 text-xs uppercase tracking-wider">
                            <th class="p-4 font-medium">Tanggal</th>
                            <th class="p-4 font-medium">Kategori</th>
                            <th class="p-4 font-medium">Keterangan</th>
                            <th class="p-4 font-medium text-right">Nominal</th>
                            <th class="p-4 font-medium text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="text-sm divide-y divide-gray-100 dark:divide-slate-700/50">
                        <?php if (count($transactions) === 0): ?>
                            <tr><td colspan="5" class="p-6 text-center text-gray-500 dark:text-slate-400">Belum ada transaksi sesuai filter.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($transactions as $t): ?>
                            <tr class="hover:bg-gray-50 dark:hover:bg-slate-700/30 transition-colors">
                                <td class="p-4 text-gray-500 dark:text-slate-400"><?= date('d M Y', strtotime($t['transaction_date'])) ?><br><span class="text-xs text-gray-400 dark:text-slate-500"><?= date('H:i', strtotime($t['created_at'])) ?></span></td>
                                <td class="p-4 font-medium text-gray-900 dark:text-slate-100">
                                    <span class="inline-flex items-center gap-1">
                                        <?= htmlspecialchars($t['category_name']) ?>
                                        <?= $t['type'] === 'PEMASUKAN' ? '<span class="text-[10px] text-green-600 dark:text-green-400 bg-green-50 dark:bg-green-900/30 rounded-full px-1.5">IN</span>' : '<span class="text-[10px] text-red-600 dark:text-red-400 bg-red-50 dark:bg-red-900/30 rounded-full px-1.5">OUT</span>' ?>
                                    </span>
                                </td>
                                <td class="p-4 text-gray-600 dark:text-slate-400"><?= htmlspecialchars($t['description'] ?: 'Transaksi') ?></td>
                                <td class="p-4 text-right font-semibold <?= $t['type'] === 'PEMASUKAN' ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' ?>">
                                    <?= $t['type'] === 'PEMASUKAN' ? '+' : '-' ?> Rp <?= number_format($t['amount'], 0, ',', '.') ?>
                                </td>
                                <td class="p-4">
                                    <div class="flex justify-center gap-2">
                                        <button type="button"
                                            @click="openEdit(<?= $t['id'] ?>, <?= $t['category_id'] ?>, '<?= $t['type'] ?>', <?= $t['amount'] ?>, '<?= $t['transaction_date'] ?>', '<?= addslashes($t['description']) ?>')"
                                            class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg text-xs font-medium bg-blue-50 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 hover:bg-blue-100 dark:hover:bg-blue-900/50 transition">
                                            <i class="ph ph-pencil-simple"></i> Edit
                                        </button>
                                        <button type="button"
                                            @click="deleteId = <?= $t['id'] ?>; showDelete = true"
                                            class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg text-xs font-medium bg-red-50 dark:bg-red-900/30 text-red-600 dark:text-red-400 hover:bg-red-100 dark:hover:bg-red-900/50 transition">
                                            <i class="ph ph-trash"></i> Hapus
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
            <div class="p-4 flex items-center justify-between border-t border-gray-100 dark:border-slate-700/50">
                <span class="text-xs text-gray-500 dark:text-slate-400">Halaman <?= $page ?> dari <?= $totalPages ?></span>
                <div class="flex gap-2">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?= $page - 1 ?>&<?= htmlspecialchars($filterQuery) ?>" class="px-3 py-1.5 border border-gray-300 dark:border-slate-600 rounded-lg text-sm text-gray-700 dark:text-slate-300 hover:bg-gray-50 dark:hover:bg-slate-700/50">Prev</a>
                    <?php endif; ?>
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <a href="?page=<?= $i ?>&<?= htmlspecialchars($filterQuery) ?>" class="px-3 py-1.5 border rounded-lg text-sm <?= $i === $page ? 'bg-blue-600 text-white border-blue-600' : 'border-gray-300 dark:border-slate-600 text-gray-700 dark:text-slate-300 hover:bg-gray-50 dark:hover:bg-slate-700/50' ?>"><?= $i ?></a>
                    <?php endfor; ?>
                    <?php if ($page < $totalPages): ?>
                        <a href="?page=<?= $page + 1 ?>&<?= htmlspecialchars($filterQuery) ?>" class="px-3 py-1.5 border border-gray-300 dark:border-slate-600 rounded-lg text-sm text-gray-700 dark:text-slate-300 hover:bg-gray-50 dark:hover:bg-slate-700/50">Next</a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal Konfirmasi Hapus -->
    <div x-show="showDelete" style="display: none;" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div x-show="showDelete" x-transition.opacity @click="showDelete = false" class="absolute inset-0 bg-black/60 backdrop-blur-sm"></div>
        <div x-show="showDelete" x-transition class="relative glass-card border border-white/60 dark:border-slate-700/50 dark:bg-slate-800/90 rounded-2xl shadow-2xl backdrop-blur-xl w-full max-w-sm p-6 z-10 text-center">
            <div class="w-16 h-16 rounded-full bg-red-50 dark:bg-red-900/30 flex items-center justify-center mx-auto mb-4">
                <i class="ph ph-trash text-3xl text-red-500 dark:text-red-400"></i>
            </div>
            <h4 class="font-bold text-gray-900 dark:text-slate-100 text-lg mb-2">Hapus Transaksi?</h4>
            <p class="text-sm text-gray-500 dark:text-slate-400 mb-6">Tindakan ini tidak dapat dibatalkan. Data transaksi akan hilang permanen.</p>
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

    <!-- Modal Tambah/Edit Transaksi -->
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
                        <i class="ph text-blue-600 dark:text-blue-400" :class="editMode ? 'ph-pencil-simple' : 'ph-plus-circle'"></i>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-slate-100" x-text="editMode ? 'Edit Transaksi' : 'Tambah Transaksi'"></h3>
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
                    <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1">Kategori</label>
                    <select id="categorySelect" name="category_id" required x-model="form.category_id"
                        @change="
                            const opt = $el.options[$el.selectedIndex];
                            if (opt && opt.dataset.type) form.type = opt.dataset.type;
                        "
                        class="w-full border border-gray-300 dark:border-slate-600 dark:bg-slate-900/50 dark:text-slate-100 rounded-lg px-3 py-2 outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">Pilih kategori</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?= $category['id'] ?>" data-type="<?= $category['type'] ?>"><?= htmlspecialchars($category['name']) ?> (<?= $category['type'] ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1">Tipe</label>
                    <select name="type" x-model="form.type" class="w-full border border-gray-300 dark:border-slate-600 dark:bg-slate-900/50 dark:text-slate-100 rounded-lg px-3 py-2 outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="PENGELUARAN">Pengeluaran</option>
                        <option value="PEMASUKAN">Pemasukan</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1">Nominal</label>
                    <input type="number" name="amount" min="1" max="9999999999" step="1" required
                        x-model="form.amount"
                        class="w-full border border-gray-300 dark:border-slate-600 dark:bg-slate-900/50 dark:text-slate-100 rounded-lg px-3 py-2 outline-none focus:ring-2 focus:ring-blue-500" placeholder="50000">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1">Tanggal</label>
                    <input type="date" name="transaction_date" x-model="form.transaction_date"
                        class="w-full border border-gray-300 dark:border-slate-600 dark:bg-slate-900/50 dark:text-slate-100 rounded-lg px-3 py-2 outline-none focus:ring-2 focus:ring-blue-500">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1">Keterangan</label>
                    <input type="text" name="description" x-model="form.description"
                        class="w-full border border-gray-300 dark:border-slate-600 dark:bg-slate-900/50 dark:text-slate-100 rounded-lg px-3 py-2 outline-none focus:ring-2 focus:ring-blue-500" placeholder="Contoh: Beli makan siang">
                </div>

                <div class="flex gap-3 pt-2">
                    <button type="button" @click="showForm = false" class="flex-1 py-2.5 text-gray-600 dark:text-slate-300 bg-gray-100 dark:bg-slate-700 hover:bg-gray-200 dark:hover:bg-slate-600 rounded-xl font-medium transition">Batal</button>
                    <button type="submit" class="flex-1 py-2.5 bg-blue-600 text-white rounded-xl font-medium hover:bg-blue-700 transition flex items-center justify-center gap-2">
                        <i class="ph" :class="editMode ? 'ph-floppy-disk' : 'ph-paper-plane-tilt'"></i>
                        <span x-text="editMode ? 'Simpan Perubahan' : 'Tambah Transaksi'"></span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../app/includes/footer.php'; ?>


