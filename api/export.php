<?php
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/includes/auth.php';
require_login();

$user_id = current_user_id();

$where = ["t.user_id = ?"];
$params = [$user_id];

$filter = [
    'type' => trim((string) ($_GET['type'] ?? '')),
    'category' => (int) ($_GET['category'] ?? 0),
    'date_from' => trim((string) ($_GET['date_from'] ?? '')),
    'date_to' => trim((string) ($_GET['date_to'] ?? '')),
];

// Dukungan periode harian/mingguan/bulanan bila tanggal tidak ditentukan.
$period = trim((string) ($_GET['period'] ?? ''));
if (in_array($period, ['harian', 'mingguan', 'bulanan'], true) && $filter['date_from'] === '' && $filter['date_to'] === '') {
    if ($period === 'harian') {
        $filter['date_from'] = date('Y-m-d');
        $filter['date_to'] = date('Y-m-d');
    } elseif ($period === 'mingguan') {
        $filter['date_from'] = date('Y-m-d', strtotime('monday this week'));
        $filter['date_to'] = date('Y-m-d', strtotime('sunday this week'));
    } else {
        $filter['date_from'] = date('Y-m-01');
        $filter['date_to'] = date('Y-m-t');
    }
}

if ($filter['type'] !== '') {
    $where[] = "t.type = ?";
    $params[] = $filter['type'];
}
if ($filter['category'] > 0) {
    $where[] = "c.id = ?";
    $params[] = $filter['category'];
}
if ($filter['date_from'] !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $filter['date_from'])) {
    $where[] = "t.transaction_date >= ?";
    $params[] = $filter['date_from'];
}
if ($filter['date_to'] !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $filter['date_to'])) {
    $where[] = "t.transaction_date <= ?";
    $params[] = $filter['date_to'];
}

$sql = "SELECT t.transaction_date, t.type, c.name AS category, t.description, t.amount
        FROM transactions t
        JOIN categories c ON c.id = t.category_id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY t.transaction_date DESC, t.id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Periode laporan
$dateFrom = $filter['date_from'] ?: ($rows ? $rows[count($rows)-1]['transaction_date'] : date('Y-m-d'));
$dateTo = $filter['date_to'] ?: ($rows ? $rows[0]['transaction_date'] : date('Y-m-d'));

$totalIn  = 0;
$totalOut = 0;
foreach ($rows as $r) {
    if ($r['type'] === 'PEMASUKAN') $totalIn  += (float) $r['amount'];
    else                            $totalOut += (float) $r['amount'];
}
$totalBalance = $totalIn - $totalOut;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan PahamFin</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap');
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', sans-serif;
            background: #f0f4f8;
            color: #1e293b;
            padding: 24px;
        }
        .report-container {
            max-width: 960px;
            margin: 0 auto;
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 4px 24px rgba(0,0,0,0.08);
            overflow: hidden;
        }
        /* Header */
        .report-header {
            background: linear-gradient(135deg, #0A58A5 0%, #0284c7 100%);
            color: #fff;
            text-align: center;
            padding: 32px 24px 24px;
        }
        .report-header h1 {
            font-size: 28px;
            font-weight: 800;
            letter-spacing: -0.5px;
            margin-bottom: 6px;
        }
        .report-header .tagline {
            font-size: 14px;
            font-weight: 400;
            opacity: 0.85;
            line-height: 1.6;
        }
        .report-period {
            background: rgba(255,255,255,0.15);
            display: inline-block;
            padding: 8px 20px;
            border-radius: 8px;
            margin-top: 16px;
            font-size: 14px;
            font-weight: 600;
        }
        /* Summary cards */
        .summary-row {
            display: flex;
            gap: 16px;
            padding: 24px;
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
        }
        .summary-card {
            flex: 1;
            padding: 16px;
            border-radius: 12px;
            text-align: center;
        }
        .summary-card.income { background: #ecfdf5; border: 1px solid #a7f3d0; }
        .summary-card.expense { background: #fef2f2; border: 1px solid #fecaca; }
        .summary-card.balance { background: #eff6ff; border: 1px solid #bfdbfe; }
        .summary-card .label { font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px; }
        .summary-card.income .label { color: #059669; }
        .summary-card.expense .label { color: #dc2626; }
        .summary-card.balance .label { color: #2563eb; }
        .summary-card .value { font-size: 20px; font-weight: 800; }
        .summary-card.income .value { color: #047857; }
        .summary-card.expense .value { color: #b91c1c; }
        .summary-card.balance .value { color: #1d4ed8; }
        /* Table */
        .report-body { padding: 0; }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        thead th {
            padding: 14px 16px;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            text-align: left;
            border-bottom: 2px solid #e2e8f0;
        }
        thead th:nth-child(1) { background: #eff6ff; color: #1e40af; } /* Tanggal */
        thead th:nth-child(2) { background: #f5f3ff; color: #6d28d9; } /* Kategori */
        thead th:nth-child(3) { background: #fefce8; color: #a16207; } /* Tipe */
        thead th:nth-child(4) { background: #f0fdf4; color: #15803d; } /* Deskripsi */
        thead th:nth-child(5) { background: #fef2f2; color: #b91c1c; text-align: right; } /* Nominal */
        tbody td {
            padding: 12px 16px;
            font-size: 14px;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
        }
        tbody tr:hover { background: #f8fafc; }
        tbody td:nth-child(1) { color: #64748b; font-weight: 500; white-space: nowrap; }
        tbody td:nth-child(2) { font-weight: 600; color: #1e293b; }
        tbody td:nth-child(5) { text-align: right; font-weight: 700; white-space: nowrap; }
        .type-pemasukan {
            display: inline-flex; align-items: center; gap: 4px;
            background: #dcfce7; color: #15803d;
            padding: 4px 10px; border-radius: 6px;
            font-size: 12px; font-weight: 700;
        }
        .type-pengeluaran {
            display: inline-flex; align-items: center; gap: 4px;
            background: #fee2e2; color: #b91c1c;
            padding: 4px 10px; border-radius: 6px;
            font-size: 12px; font-weight: 700;
        }
        .amount-in { color: #047857; }
        .amount-out { color: #dc2626; }
        /* Footer */
        .report-footer {
            padding: 20px 24px;
            background: #f8fafc;
            border-top: 2px solid #e2e8f0;
            text-align: center;
            font-size: 12px;
            color: #94a3b8;
        }
        /* Print & actions */
        .actions {
            display: flex;
            justify-content: center;
            gap: 12px;
            padding: 20px;
        }
        .btn {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 10px 20px;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            border: none;
            cursor: pointer;
            text-decoration: none;
            transition: opacity 0.2s;
        }
        .btn:hover { opacity: 0.85; }
        .btn-print { background: #0A58A5; color: #fff; }
        .btn-back { background: #e2e8f0; color: #475569; }
        .empty-state {
            padding: 48px 24px;
            text-align: center;
            color: #94a3b8;
            font-size: 15px;
        }

        @media print {
            body { background: #fff; padding: 0; }
            .report-container { box-shadow: none; border-radius: 0; }
            .actions { display: none; }
        }
        @media (max-width: 640px) {
            .summary-row { flex-direction: column; }
            .summary-card .value { font-size: 16px; }
            .report-header h1 { font-size: 22px; }
        }
    </style>
</head>
<body>
    <div class="report-container">
        <!-- HEADER -->
        <div class="report-header">
            <h1>Laporan PahamFin</h1>
            <p class="tagline">PahamFin membantu Anda melacak setiap pemasukan dan pengeluaran secara akurat.</p>
            <div class="report-period">
                Periode Laporan: <?= htmlspecialchars($dateFrom) ?> s/d <?= htmlspecialchars($dateTo) ?>
            </div>
        </div>

        <!-- SUMMARY -->
        <div class="summary-row">
            <div class="summary-card income">
                <div class="label">Total Pemasukan</div>
                <div class="value">Rp <?= number_format($totalIn, 0, ',', '.') ?></div>
            </div>
            <div class="summary-card expense">
                <div class="label">Total Pengeluaran</div>
                <div class="value">Rp <?= number_format($totalOut, 0, ',', '.') ?></div>
            </div>
            <div class="summary-card balance">
                <div class="label">Saldo</div>
                <div class="value">Rp <?= number_format($totalBalance, 0, ',', '.') ?></div>
            </div>
        </div>

        <!-- TABLE -->
        <div class="report-body">
            <?php if (count($rows) === 0): ?>
                <div class="empty-state">Tidak ada transaksi untuk periode ini.</div>
            <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th>Kategori</th>
                        <th>Tipe</th>
                        <th>Deskripsi</th>
                        <th>Nominal (Rp)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['transaction_date']) ?></td>
                        <td><?= htmlspecialchars($row['category']) ?></td>
                        <td>
                            <?php if ($row['type'] === 'PEMASUKAN'): ?>
                                <span class="type-pemasukan">&#9650; PEMASUKAN</span>
                            <?php else: ?>
                                <span class="type-pengeluaran">&#9660; PENGELUARAN</span>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($row['description']) ?></td>
                        <td class="<?= $row['type'] === 'PEMASUKAN' ? 'amount-in' : 'amount-out' ?>">
                            <?= $row['type'] === 'PEMASUKAN' ? '+' : '-' ?> <?= number_format((float) $row['amount'], 0, ',', '.') ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>

        <!-- FOOTER -->
        <div class="report-footer">
            Dicetak pada <?= date('d F Y H:i') ?> &mdash; PahamFin &copy; <?= date('Y') ?>
        </div>
    </div>

    <!-- ACTIONS -->
    <div class="actions">
        <button class="btn btn-print" onclick="window.print()">&#128424; Cetak / Simpan PDF</button>
        <a href="javascript:history.back()" class="btn btn-back">&#8592; Kembali</a>
    </div>
</body>
</html>
