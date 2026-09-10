<?php
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/includes/config.php';
require_once __DIR__ . '/../app/includes/auth.php';
require_login();
$user_id = current_user_id();

$stmtU = $pdo->prepare("SELECT name, email FROM users WHERE id = ? LIMIT 1");
$stmtU->execute([$user_id]);
$userProfile = $stmtU->fetch(PDO::FETCH_ASSOC);

// Parameter Filter
$search = trim((string) ($_GET['search'] ?? ''));
$type = trim((string) ($_GET['type'] ?? ''));
$category = (int) ($_GET['category'] ?? 0);
$dateFrom = trim((string) ($_GET['date_from'] ?? ''));
$dateTo = trim((string) ($_GET['date_to'] ?? ''));

$where = ["t.user_id = ?"];
$params = [$user_id];

if ($search !== '') {
    $where[] = "(t.description LIKE ? OR c.name LIKE ?)";
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}
if (in_array($type, ['PEMASUKAN', 'PENGELUARAN'], true)) {
    $where[] = "t.type = ?";
    $params[] = $type;
}
if ($category > 0) {
    $where[] = "t.category_id = ?";
    $params[] = $category;
}
if ($dateFrom !== '') {
    $where[] = "t.transaction_date >= ?";
    $params[] = $dateFrom;
}
if ($dateTo !== '') {
    $where[] = "t.transaction_date <= ?";
    $params[] = $dateTo;
}

$whereClause = implode(" AND ", $where);
$sql = "SELECT t.transaction_date, t.type, t.amount, t.description, c.name as category_name
        FROM transactions t
        JOIN categories c ON t.category_id = c.id
        WHERE {$whereClause}
        ORDER BY t.transaction_date DESC, t.id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalPemasukan = 0;
$totalPengeluaran = 0;
foreach ($rows as $r) {
    if ($r['type'] === 'PEMASUKAN') $totalPemasukan += (float)$r['amount'];
    else $totalPengeluaran += (float)$r['amount'];
}
$saldoBersih = $totalPemasukan - $totalPengeluaran;

$periodText = "Semua Waktu";
if ($dateFrom !== '' && $dateTo !== '') {
    $periodText = date('d M Y', strtotime($dateFrom)) . " - " . date('d M Y', strtotime($dateTo));
} elseif ($dateFrom !== '') {
    $periodText = "Sejak " . date('d M Y', strtotime($dateFrom));
} elseif ($dateTo !== '') {
    $periodText = "Hingga " . date('d M Y', strtotime($dateTo));
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Keuangan - PahamFin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Figtree:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Figtree', sans-serif; background: #f8fafc; color: #0f172a; }
        @media print {
            .no-print { display: none !important; }
            body { background: white; color: black; }
            .print-card { border: 1px solid #e2e8f0 !important; box-shadow: none !important; }
        }
    </style>
</head>
<body class="p-6 md:p-10 max-w-4xl mx-auto">

    <!-- Tombol Cetak / Simpan PDF (Hanya di Layar Web) -->
    <div class="no-print mb-6 flex justify-between items-center bg-blue-50 border border-blue-200 rounded-2xl p-4">
        <div>
            <h4 class="font-bold text-blue-900 text-sm">📄 Siap Cetak atau Simpan sebagai PDF</h4>
            <p class="text-xs text-blue-700 mt-0.5">Klik tombol di sebelah kanan untuk langsung mengunduh atau mencetak laporan ini.</p>
        </div>
        <button onclick="window.print()" class="px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-xl shadow-md text-sm transition">
            Cetak / Simpan PDF 🖨️
        </button>
    </div>

    <!-- Header Dokumen Laporan -->
    <div class="bg-white rounded-3xl p-8 shadow-sm border border-slate-200 print-card">
        <div class="flex justify-between items-start border-b border-slate-100 pb-6 mb-6">
            <div>
                <h1 class="text-2xl font-black tracking-tight text-blue-900">PahamFin</h1>
                <p class="text-xs text-slate-500 font-medium tracking-wider uppercase mt-0.5">Laporan Keuangan Resmi</p>
                <p class="text-xs text-slate-400 mt-2">Dibuat untuk: <b><?= htmlspecialchars($userProfile['name'] ?? 'Pengguna') ?></b> (<?= htmlspecialchars($userProfile['email'] ?? '') ?>)</p>
            </div>
            <div class="text-right">
                <span class="inline-block px-3 py-1 bg-slate-100 text-slate-700 rounded-full text-xs font-bold">PERIODE: <?= $periodText ?></span>
                <p class="text-xs text-slate-400 mt-2">Dicetak pada: <?= date('d F Y H:i') ?> WIB</p>
            </div>
        </div>

        <!-- Ringkasan Keuangan (3 Cards) -->
        <div class="grid grid-cols-3 gap-4 mb-8">
            <div class="bg-emerald-50/70 border border-emerald-200 rounded-2xl p-4">
                <p class="text-xs text-emerald-700 font-semibold">Total Pemasukan</p>
                <p class="text-xl font-extrabold text-emerald-700 mt-1">Rp <?= number_format($totalPemasukan, 0, ',', '.') ?></p>
            </div>
            <div class="bg-rose-50/70 border border-rose-200 rounded-2xl p-4">
                <p class="text-xs text-rose-700 font-semibold">Total Pengeluaran</p>
                <p class="text-xl font-extrabold text-rose-700 mt-1">Rp <?= number_format($totalPengeluaran, 0, ',', '.') ?></p>
            </div>
            <div class="bg-blue-50/70 border border-blue-200 rounded-2xl p-4">
                <p class="text-xs text-blue-700 font-semibold">Saldo Bersih (Net)</p>
                <p class="text-xl font-extrabold text-blue-900 mt-1">Rp <?= number_format($saldoBersih, 0, ',', '.') ?></p>
            </div>
        </div>

        <!-- Tabel Transaksi -->
        <h3 class="text-sm font-extrabold text-slate-900 uppercase tracking-wider mb-3">Rincian Transaksi (<?= count($rows) ?> Entri)</h3>
        <table class="w-full text-left text-xs border-collapse">
            <thead>
                <tr class="bg-slate-100 text-slate-600 uppercase font-bold border-y border-slate-200">
                    <th class="py-3 px-3">Tanggal</th>
                    <th class="py-3 px-3">Kategori</th>
                    <th class="py-3 px-3">Tipe</th>
                    <th class="py-3 px-3">Deskripsi</th>
                    <th class="py-3 px-3 text-right">Nominal (Rp)</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php if (count($rows) === 0): ?>
                <tr><td colspan="5" class="py-6 text-center text-slate-400">Tidak ada data transaksi.</td></tr>
                <?php endif; ?>
                <?php foreach ($rows as $r): ?>
                <tr>
                    <td class="py-2.5 px-3 whitespace-nowrap text-slate-600"><?= date('d/m/Y', strtotime($r['transaction_date'])) ?></td>
                    <td class="py-2.5 px-3 font-semibold text-slate-800"><?= htmlspecialchars($r['category_name']) ?></td>
                    <td class="py-2.5 px-3 font-bold <?= $r['type'] === 'PEMASUKAN' ? 'text-emerald-600' : 'text-rose-600' ?>">
                        <?= $r['type'] ?>
                    </td>
                    <td class="py-2.5 px-3 text-slate-600"><?= htmlspecialchars($r['description']) ?></td>
                    <td class="py-2.5 px-3 text-right font-extrabold <?= $r['type'] === 'PEMASUKAN' ? 'text-emerald-600' : 'text-rose-600' ?>">
                        <?= $r['type'] === 'PEMASUKAN' ? '+' : '-' ?>Rp <?= number_format((float)$r['amount'], 0, ',', '.') ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Footer Dokumen -->
        <div class="mt-10 pt-4 border-t border-slate-100 text-center text-xs text-slate-400">
            PahamFin (Paham Finansial) — Your Automatic Financial Assistant · pahamfin.softwaremahasiswa.com
        </div>
    </div>

</body>
</html>