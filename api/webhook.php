<?php
header('Content-Type: application/json; charset=utf-8');
require __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/includes/config.php';

function PahamFin_normalize_phone(?string $phone): ?string
{
    if (!$phone) {
        return null;
    }

    $normalized = preg_replace('/[^0-9]/', '', $phone);
    if ($normalized === '') {
        return null;
    }

    if (strpos($normalized, '62') !== 0) {
        $normalized = '62' . ltrim($normalized, '0');
    }

    return $normalized;
}

function PahamFin_parse_amount_from_text(string $text): ?float
{
    if ($text === '') {
        return null;
    }

    $lower = strtolower($text);
    $units = $GLOBALS['PahamFin_AMOUNT_UNITS'] ?? [];

    // Prioritaskan satuan dengan nama lebih panjang (misal "juta" sebelum "k").
    uksort($units, function ($a, $b) {
        return strlen($b) <=> strlen($a);
    });
    foreach ($units as $suffix => $multiplier) {
        $pattern = '/(\d+(?:[.,]\d+)?)\s*' . preg_quote($suffix, '/') . '\b/i';
        if (preg_match($pattern, $lower, $match)) {
            $value = (float) str_replace(',', '.', $match[1]);
            return $value * $multiplier;
        }
    }

    if (preg_match('/\b(\d+(?:[.,]\d+)?)\b/', $lower, $numberMatch)) {
        $value = (float) str_replace(',', '.', $numberMatch[1]);
        return $value;
    }

    return null;
}

function PahamFin_extract_description(string $text): string
{
    $units = array_keys($GLOBALS['PahamFin_AMOUNT_UNITS'] ?? []);
    $joined = implode('|', array_map(function ($u) { return preg_quote($u, '/'); }, $units));
    $cleaned = preg_replace('/\b\d+(?:[.,]\d+)?\s*(?:' . $joined . ')\b/i', '', $text);
    $cleaned = preg_replace('/\b\d+(?:[.,]\d+)?\b/', '', $cleaned);
    $cleaned = trim(preg_replace('/\s+/', ' ', $cleaned));

    return $cleaned !== '' ? $cleaned : 'Transaksi';
}

function PahamFin_find_or_create_user(PDO $pdo, ?string $phone, ?string $telegramId, ?string $senderName): array
{
    $phoneNumber = PahamFin_normalize_phone($phone);
    $telegramId  = $telegramId ? trim((string) $telegramId) : null;
    $name        = trim((string) ($senderName ?: 'Pengguna'));

    // ── Langkah 1: Cari berdasarkan telegram_id (paling akurat) ──
    if ($telegramId) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE telegram_id = ? LIMIT 1");
        $stmt->execute([$telegramId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user) {
            // Jika sekarang ada phone juga, update sekalian
            if ($phoneNumber && empty($user['phone_number'])) {
                $pdo->prepare("UPDATE users SET phone_number = ? WHERE id = ?")->execute([$phoneNumber, $user['id']]);
            }
            return $user;
        }
    }

    // ── Langkah 2: Cari berdasarkan phone_number ──
    if ($phoneNumber) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE phone_number = ? LIMIT 1");
        $stmt->execute([$phoneNumber]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user) {
            // AUTO-LINK: Akun web ditemukan → hubungkan telegram_id otomatis
            if ($telegramId && empty($user['telegram_id'])) {
                $pdo->prepare("UPDATE users SET telegram_id = ? WHERE id = ?")->execute([$telegramId, $user['id']]);
                $user['telegram_id'] = $telegramId;
            }
            return $user;
        }
    }

    // ── Langkah 3: Tidak ditemukan → buat akun baru ──
    if (!$phoneNumber && !$telegramId) {
        throw new RuntimeException('Nomor HP atau Telegram ID wajib diisi.');
    }

    // Generate phone dari telegram ID jika tidak ada phone
    if (!$phoneNumber) {
        $phoneNumber = '628' . preg_replace('/[^0-9]/', '', (string) $telegramId);
    }

    $insert = $pdo->prepare("INSERT INTO users (name, phone_number, telegram_id, email, password) VALUES (?, ?, ?, NULL, '')");
    $insert->execute([
        $name !== '' ? $name : 'Pengguna Baru',
        $phoneNumber,
        $telegramId,
    ]);

    $userId = (int) $pdo->lastInsertId();

    // Seed kategori default otomatis (lengkap) untuk user baru
    PahamFin_seed_default_categories($pdo, $userId);

    $userStmt = $pdo->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
    $userStmt->execute([$userId]);
    return $userStmt->fetch(PDO::FETCH_ASSOC);
}

function PahamFin_match_category(PDO $pdo, int $userId, string $message, ?string $description = null): ?array
{
    $searchText = strtolower(trim($message . ' ' . ($description ?? '')));
    $searchText = preg_replace('/[^a-z0-9\s]/i', ' ', $searchText);
    $searchText = preg_replace('/\s+/', '', $searchText);

    $stmt = $pdo->prepare("SELECT id, type, keyword, name FROM categories WHERE user_id = ? ORDER BY name");
    $stmt->execute([$userId]);
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($categories as $category) {
        $keywords = array_filter(array_map('trim', explode(',', (string) $category['keyword'])), fn($value) => $value !== '');
        foreach ($keywords as $keyword) {
            $candidate = strtolower(trim((string) $keyword));
            if ($candidate === '') {
                continue;
            }

            $candidate = preg_replace('/[^a-z0-9]/i', '', $candidate);
            if ($candidate === '') {
                continue;
            }

            if (strpos($searchText, $candidate) !== false) {
                return $category;
            }
        }
    }

    return null;
}

/**
 * Menangani perintah bot: /saldo, /laporan, /kategori, /riwayat.
 * Mengembalikan array respons (success, message, dll) untuk dijSON-kan.
 */
function PahamFin_bot_command(PDO $pdo, int $userId, string $action, string $description = ''): array
{
    $monthStart = date('Y-m-01');
    $monthEnd = date('Y-m-t');

    if ($action === 'saldo') {
        $stmt = $pdo->prepare("SELECT
            COALESCE(SUM(CASE WHEN type='PEMASUKAN' THEN amount ELSE 0 END),0) AS income,
            COALESCE(SUM(CASE WHEN type='PENGELUARAN' THEN amount ELSE 0 END),0) AS expense
            FROM transactions WHERE user_id = ? AND transaction_date BETWEEN ? AND ?");
        $stmt->execute([$userId, $monthStart, $monthEnd]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $income = (float) $row['income'];
        $expense = (float) $row['expense'];
        $balance = $income - $expense;
        $msg = "*Saldo Bulan Ini*\n"
             . "📥 Pemasukan : Rp " . number_format($income, 0, ',', '.') . "\n"
             . "📤 Pengeluaran: Rp " . number_format($expense, 0, ',', '.') . "\n"
             . "💰 Saldo      : Rp " . number_format($balance, 0, ',', '.');
             
        $stmtW = $pdo->prepare("SELECT w.name, w.starting_balance + COALESCE(SUM(CASE WHEN t.type='PEMASUKAN' THEN t.amount ELSE -t.amount END), 0) AS tx_net
            FROM wallets w
            LEFT JOIN transactions t ON t.wallet_id = w.id
            WHERE w.user_id = ?
            GROUP BY w.id, w.name, w.starting_balance
            ORDER BY w.id ASC");
        $stmtW->execute([$userId]);
        $wallets = $stmtW->fetchAll(PDO::FETCH_ASSOC);
        if (count($wallets) > 0) {
            $msg .= "\n\n*Saldo Dompet:*\n";
            foreach ($wallets as $w) {
                $msg .= "👛 " . $w['name'] . " : Rp " . number_format($w['tx_net'], 0, ',', '.') . "\n";
            }
        }
             
        return ['success' => true, 'message' => $msg];
    }

    if ($action === 'laporan') {
        $stmt = $pdo->prepare("SELECT c.name, t.type, COALESCE(SUM(t.amount),0) AS total
            FROM transactions t JOIN categories c ON c.id = t.category_id
            WHERE t.user_id = ? AND t.transaction_date BETWEEN ? AND ?
            GROUP BY c.name, t.type ORDER BY total DESC LIMIT 10");
        $stmt->execute([$userId, $monthStart, $monthEnd]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!$rows) {
            return ['success' => true, 'message' => 'Belum ada transaksi bulan ini.'];
        }
        $lines = ["*Laporan Bulan Ini*"];
        foreach ($rows as $r) {
            $sign = $r['type'] === 'PEMASUKAN' ? '📥' : '📤';
            $lines[] = "{$sign} {$r['name']}: Rp " . number_format((float) $r['total'], 0, ',', '.');
        }
        return ['success' => true, 'message' => implode("\n", $lines)];
    }

    if ($action === 'kategori') {
        $stmt = $pdo->prepare("SELECT name, type, keyword FROM categories WHERE user_id = ? ORDER BY type, name");
        $stmt->execute([$userId]);
        $cats = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!$cats) {
            return ['success' => true, 'message' => 'Belum ada kategori. Tambahkan di dashboard.'];
        }
        $lines = ["*Daftar Kategori*"];
        foreach ($cats as $c) {
            $label = $c['type'] === 'PEMASUKAN' ? '📥' : '📤';
            $lines[] = "{$label} {$c['name']}  (keyword: {$c['keyword']})";
        }
        return ['success' => true, 'message' => implode("\n", $lines)];
    }

    if ($action === 'riwayat') {
        $stmt = $pdo->prepare("SELECT t.amount, t.type, t.description, t.transaction_date, c.name AS cat
            FROM transactions t JOIN categories c ON c.id = t.category_id
            WHERE t.user_id = ? ORDER BY t.transaction_date DESC, t.id DESC LIMIT 5");
        $stmt->execute([$userId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!$rows) {
            return ['success' => true, 'message' => 'Belum ada transaksi.'];
        }
        $lines = ["*Riwayat Transaksi Terbaru*"];
        foreach ($rows as $r) {
            $sign = $r['type'] === 'PEMASUKAN' ? '+' : '-';
            $lines[] = "({$r['transaction_date']}) {$r['cat']} - {$sign}Rp " . number_format((float) $r['amount'], 0, ',', '.');
        }
        return ['success' => true, 'message' => implode("\n", $lines)];
    }

    if ($action === 'tabungan') {
        $stmt = $pdo->prepare("SELECT * FROM savings_goals WHERE user_id = ? ORDER BY id ASC");
        $stmt->execute([$userId]);
        $goals = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!$goals) {
            return ['success' => true, 'message' => 'Belum ada target tabungan. Buat dulu di Dashboard.'];
        }
        $lines = ["*Progres Tabungan* 🎯"];
        foreach ($goals as $g) {
            $target = number_format((float) $g['target_amount'], 0, ',', '.');
            $saved = number_format((float) $g['saved_amount'], 0, ',', '.');
            $pct = $g['target_amount'] > 0 ? round(($g['saved_amount'] / $g['target_amount']) * 100) : 0;
            $lines[] = "• *{$g['name']}*\n  Terkumpul: Rp {$saved} / Rp {$target} ({$pct}%)";
        }
        $lines[] = "\n_Ketik `nabung [nama tabungan] [nominal]` untuk menambah._";
        return ['success' => true, 'message' => implode("\n", $lines)];
    }

    if ($action === 'hutang') {
        $stmt = $pdo->prepare("SELECT * FROM debts WHERE user_id = ? AND status = 'UNPAID' ORDER BY id ASC");
        $stmt->execute([$userId]);
        $debts = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!$debts) {
            return ['success' => true, 'message' => 'Tidak ada hutang/piutang aktif. Bersih!'];
        }
        $lines = ["*Daftar Hutang & Piutang* 💸"];
        foreach ($debts as $d) {
            $amount = number_format((float) $d['amount'], 0, ',', '.');
            if ($d['type'] === 'OWE') {
                $lines[] = "🔴 Kamu ngutang ke *{$d['person_name']}* sebesar Rp {$amount}";
            } else {
                $lines[] = "🟢 *{$d['person_name']}* ngutang ke kamu sebesar Rp {$amount}";
            }
        }
        $lines[] = "\n_Ketik `lunas [nama]` untuk menandai lunas._";
        return ['success' => true, 'message' => implode("\n", $lines)];
    }

    if ($action === 'dompet') {
        $stmt = $pdo->prepare("
            SELECT w.id, w.name, w.starting_balance,
                COALESCE(SUM(CASE WHEN t.type='PEMASUKAN' THEN t.amount ELSE -t.amount END), 0) AS tx_net
            FROM wallets w
            LEFT JOIN transactions t ON t.wallet_id = w.id
            WHERE w.user_id = ?
            GROUP BY w.id, w.name, w.starting_balance
            ORDER BY w.id ASC
        ");
        $stmt->execute([$userId]);
        $ws = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!$ws) {
            return ['success' => true, 'message' => 'Belum ada dompet. Tambahkan dulu di Dashboard → Dompet.'];
        }
        $lines = ["*Saldo Dompet Kamu* 💳"];
        foreach ($ws as $w) {
            $bal = number_format($w['starting_balance'] + $w['tx_net'], 0, ',', '.');
            $lines[] = "• *{$w['name']}*: Rp {$bal}";
        }
        $lines[] = "\n_Transaksi via dompet: `makan 50000 gopay`_";
        return ['success' => true, 'message' => implode("\n", $lines)];
    }

    if ($action === 'pengingat') {
        $stmt = $pdo->prepare("SELECT * FROM reminders WHERE user_id = ? AND done = 0 ORDER BY remind_date ASC LIMIT 10");
        $stmt->execute([$userId]);
        $reminders = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!$reminders) {
            return ['success' => true, 'message' => 'Tidak ada pengingat aktif.'];
        }
        $lines = ["*Daftar Pengingat Aktif* 📝"];
        foreach ($reminders as $r) {
            $lines[] = "ID: `{$r['id']}` | ⏳ {$r['remind_date']} | {$r['title']}";
        }
        $lines[] = "\n_Ketik `/done [id]` untuk tandai selesai._";
        return ['success' => true, 'message' => implode("\n", $lines)];
    }

    if ($action === 'done') {
        $id = (int) $description;
        if ($id <= 0) return ['success' => false, 'message' => 'Format salah. Gunakan /done [id]'];
        
        $stmt = $pdo->prepare("UPDATE reminders SET done = 1 WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, $userId]);
        
        if ($stmt->rowCount() > 0) {
            return ['success' => true, 'message' => "✅ Pengingat ID {$id} berhasil diselesaikan!"];
        }
        return ['success' => false, 'message' => "Pengingat ID {$id} tidak ditemukan atau sudah selesai."];
    }

    if ($action === 'help' || $action === 'start') {
        $msg  = "🤖 *Panduan PahamFin Bot*\n\n";
        $msg .= "*📊 Lihat Info:*\n";
        $msg .= "`/saldo` — Cek saldo & ringkasan\n";
        $msg .= "`/laporan` — Laporan per kategori\n";
        $msg .= "`/riwayat` — 5 transaksi terakhir\n";
        $msg .= "`/kategori` — Daftar kategori & keyword\n";
        $msg .= "`/tabungan` — Progres target tabungan\n";
        $msg .= "`/hutang` — Hutang & piutang aktif\n";
        $msg .= "`/dompet` — Saldo semua dompet\n";
        $msg .= "`/pengingat` — Pengingat aktif\n\n";
        $msg .= "*✍️ Catat Transaksi:*\n";
        $msg .= "`makan 50000` — Catat pengeluaran\n";
        $msg .= "`makan 50000 gopay` — Catat + via dompet\n";
        $msg .= "`gaji 5000000` — Catat pemasukan\n\n";
        $msg .= "*💰 Tabungan:*\n";
        $msg .= "`nabung rumah 200000` — Tambah ke tabungan\n";
        $msg .= "`target liburan 5000000` — Buat target baru\n\n";
        $msg .= "*🤝 Hutang & Piutang:*\n";
        $msg .= "`utang budi 50000` — Catat kamu berhutang\n";
        $msg .= "`piutang andi 50000` — Catat orang berhutang\n";
        $msg .= "`lunas budi` — Tandai lunas\n\n";
        $msg .= "*💼 Anggaran:*\n";
        $msg .= "`anggaran makan 1000000` — Set anggaran bulanan\n\n";
        $msg .= "*📝 Pengingat:*\n";
        $msg .= "`/done 3` — Tandai pengingat ID 3 selesai\n\n";
        $msg .= "_Ketik `/help` kapan saja untuk melihat ini._";
        return ['success' => true, 'message' => $msg];
    }

    return ['success' => false, 'message' => 'Perintah tidak dikenali. Ketik /help untuk panduan.'];
}

try {
    // Otentikasi webhook (shared secret)
    $secret = PahamFin_webhook_secret();
    if ($secret !== '') {
        $sentKey = $_SERVER['HTTP_X_PahamFin_KEY'] ?? ($_GET['key'] ?? ($_POST['key'] ?? ''));
        if (!is_string($sentKey) || !hash_equals($secret, $sentKey)) {
            http_response_code(401);
            echo json_encode(['success' => false, 'status' => 'error', 'message' => 'Unauthorized.']);
            exit;
        }
    }

    $payload = json_decode(file_get_contents('php://input'), true);
    if (!$payload || !is_array($payload)) {
        $payload = $_POST;
    }

    $message = trim((string) ($payload['message'] ?? ''));
    $description = trim((string) ($payload['description'] ?? ''));
    $action = trim((string) ($payload['action'] ?? ''));
    $amount = isset($payload['amount']) ? (float) $payload['amount'] : PahamFin_parse_amount_from_text($message . ' ' . $description);

    $phone = $payload['phone'] ?? null;
    $telegramId = $payload['telegram_id'] ?? null;

    if (!$phone && !$telegramId && !empty($payload['from'])) {
        $phone = $payload['from'];
    }

    $user = PahamFin_find_or_create_user($pdo, $phone, $telegramId, $payload['sender'] ?? null);
    $userId = (int) $user['id'];

    // Jika berupa perintah bot → proses command
    if ($action !== '' || strpos($message, '/') === 0) {
        $cmdAction = $action !== '' ? $action : strtolower(trim(explode(' ', $message)[0], '/'));
        $result = PahamFin_bot_command($pdo, $userId, $cmdAction, $description);
        $result['status'] = $result['success'] ? 'success' : 'error';
        echo json_encode($result);
        exit;
    }

    if ($message === '' && $description !== '') {
        $message = $description;
    }

    $finalDescription = $description !== '' ? $description : PahamFin_extract_description($message);

    // —— Logika Tambah Dompet ——
    if (preg_match('/^add\s+dompet\s+(.+?)(?:\s+saldo\s+(\d+(?:[kKmbB]?)))?$/i', $message, $m)) {
        $walletName = trim($m[1]);
        $startBalStr = $m[2] ?? '0';
        $startBalStr = str_ireplace(['k','m','b'], ['000','000000','000000000'], $startBalStr);
        $startBal = (float) $startBalStr;

        $pdo->prepare("INSERT INTO wallets (user_id, name, starting_balance) VALUES (?, ?, ?)")
            ->execute([$userId, $walletName, $startBal]);
        
        echo json_encode([
            'success' => true,
            'status' => 'success',
            'message' => "✅ Dompet *{$walletName}* berhasil ditambahkan dengan saldo awal Rp " . number_format($startBal, 0, ',', '.')
        ]);
        exit;
    }
    
    // ── Logika Bayar Lunas Hutang/Piutang ──
    if (stripos($message, 'lunas ') === 0) {
        $personName = trim(str_ireplace('lunas ', '', $message));
        $stmt = $pdo->prepare("UPDATE debts SET status = 'PAID' WHERE user_id = ? AND LOWER(person_name) = LOWER(?) AND status = 'UNPAID'");
        $stmt->execute([$userId, $personName]);
        if ($stmt->rowCount() > 0) {
            echo json_encode(['success' => true, 'status' => 'success', 'message' => "✅ Hutang/Piutang atas nama *{$personName}* berhasil dilunasi!"]);
        } else {
            throw new RuntimeException("Tidak ada hutang/piutang yang belum lunas atas nama '{$personName}'.");
        }
        exit;
    }

    if ($amount === null || $amount <= 0) {
        throw new RuntimeException('Format nominal tidak valid. Gunakan contoh: makan 50000 atau bensin 100rb');
    }

    // ── Logika Hutang Piutang ──
    if (stripos($message, 'utang ') === 0 || stripos($message, 'hutang ') === 0) {
        $personName = trim(str_ireplace(['utang ', 'hutang '], '', PahamFin_extract_description($message)));
        if ($personName === '') throw new RuntimeException("Format salah. Contoh: utang budi 50000");
        $pdo->prepare("INSERT INTO debts (user_id, person_name, amount, type) VALUES (?, ?, ?, 'OWE')")->execute([$userId, $personName, $amount]);
        $fmt = number_format($amount, 0, ',', '.');
        echo json_encode(['success' => true, 'status' => 'success', 'message' => "📝 Kamu berhutang ke *{$personName}* sebesar Rp {$fmt}."]);
        exit;
    }
    if (stripos($message, 'piutang ') === 0 || stripos($message, 'pinjemin ') === 0) {
        $personName = trim(str_ireplace(['piutang ', 'pinjemin '], '', PahamFin_extract_description($message)));
        if ($personName === '') throw new RuntimeException("Format salah. Contoh: piutang andi 50000");
        $pdo->prepare("INSERT INTO debts (user_id, person_name, amount, type) VALUES (?, ?, ?, 'LENT')")->execute([$userId, $personName, $amount]);
        $fmt = number_format($amount, 0, ',', '.');
        echo json_encode(['success' => true, 'status' => 'success', 'message' => "📝 *{$personName}* berhutang ke kamu sebesar Rp {$fmt}."]);
        exit;
    }
    
    // ── 💰 Logika Set Target Tabungan Baru 💰
    if (stripos($message, 'target ') === 0 || stripos($message, 'buat tabungan ') === 0) {
        $goalName = trim(str_ireplace(['target ', 'buat tabungan '], '', PahamFin_extract_description($message)));
        if ($goalName === '') {
            throw new RuntimeException("Format salah. Contoh: target liburan bali 5000000");
        }
        $pdo->prepare("INSERT INTO savings_goals (user_id, name, target_amount) VALUES (?, ?, ?)")
            ->execute([$userId, $goalName, $amount]);
        $fmtAmount = number_format($amount, 0, ',', '.');
        echo json_encode([
            'success' => true, 'status' => 'success',
            'message' => "🎯 Target tabungan baru *{$goalName}* sebesar Rp {$fmtAmount} berhasil dibuat!"
        ]);
        exit;
    }

    // 💰 Logika Menabung 💰
    if (stripos($finalDescription, 'nabung') !== false || stripos($message, 'nabung') !== false || stripos($message, 'tabungan') !== false) {
        $stmt = $pdo->prepare("SELECT * FROM savings_goals WHERE user_id = ?");
        $stmt->execute([$userId]);
        $goals = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $matchedGoal = null;
        foreach ($goals as $g) {
            if (stripos($message, $g['name']) !== false || stripos($g['name'], $finalDescription) !== false) {
                $matchedGoal = $g;
                break;
            }
        }
        
        if ($matchedGoal) {
            $pdo->prepare("UPDATE savings_goals SET saved_amount = saved_amount + ? WHERE id = ?")->execute([$amount, $matchedGoal['id']]);
            $fmtAmount = number_format($amount, 0, ',', '.');
            echo json_encode([
                'success' => true,
                'status' => 'success',
                'message' => "🎯 Tabungan berhasil ditambahkan ke *{$matchedGoal['name']}* sebesar Rp {$fmtAmount}!",
                'type' => 'TABUNGAN',
                'category' => $matchedGoal['name'],
                'amount' => $amount,
                'description' => $finalDescription,
            ]);
            exit;
        } else {
            // Jika tidak ketemu targetnya, buat otomatis!
            $goalName = trim(str_ireplace(['tabungan ke ', 'tabungan ', 'nabung '], '', PahamFin_extract_description($message)));
            $pdo->prepare("INSERT INTO savings_goals (user_id, name, target_amount, saved_amount) VALUES (?, ?, ?, ?)")
                ->execute([$userId, $goalName, 0, $amount]);
            $fmtAmount = number_format($amount, 0, ',', '.');
            echo json_encode([
                'success' => true, 'status' => 'success',
                'message' => "🎯 Target tabungan baru *{$goalName}* berhasil dibuat otomatis dan diisi sebesar Rp {$fmtAmount}!"
            ]);
            exit;
        }
    }
    
    // ── Logika Isi/Top-Up Dompet ──
    // Contoh: "isi dompet gopay 50k" atau "topup gopay 50k"
    if (preg_match('/^(?:isi\s+dompet|topup|top.up)\s+(.+?)\s+(\d+(?:[kKmMbBrR]{0,2}))$/i', $message, $m)) {
        $targetWalletName = trim($m[1]);
        $addStr = strtolower(str_replace(['.', ' '], '', $m[2]));
        $addStr = preg_replace_callback('/(\d+)(rb|ribu)/', fn($x) => (int)$x[1] * 1000, $addStr);
        $addStr = preg_replace_callback('/(\d+)(jt|juta)/', fn($x) => (int)$x[1] * 1000000, $addStr);
        $addStr = str_ireplace(['k'], ['000'], $addStr);
        $addAmount = (float) preg_replace('/[^0-9]/', '', $addStr);
        if ($addAmount > 0) {
            $stmtW = $pdo->prepare("SELECT id, name, starting_balance FROM wallets WHERE user_id = ? AND LOWER(name) LIKE LOWER(?) LIMIT 1");
            $stmtW->execute([$userId, "%$targetWalletName%"]);
            $foundWallet = $stmtW->fetch(PDO::FETCH_ASSOC);
            if ($foundWallet) {
                $pdo->prepare("UPDATE wallets SET starting_balance = starting_balance + ? WHERE id = ?")
                    ->execute([$addAmount, $foundWallet['id']]);
                echo json_encode([
                    'success' => true, 'status' => 'success',
                    'message' => "✅ Saldo dompet *{$foundWallet['name']}* berhasil ditambah sebesar Rp " . number_format($addAmount, 0, ',', '.') . ".\nSaldo baru: Rp " . number_format($foundWallet['starting_balance'] + $addAmount, 0, ',', '.')
                ]);
                exit;
            } else {
                throw new RuntimeException("Dompet '{$targetWalletName}' tidak ditemukan. Cek nama dompet di halaman Dompet.");
            }
        }
    }

    // ── Logika Set Anggaran Bulanan ──
    if (stripos($message, 'anggaran ') === 0 || stripos($message, 'budget ') === 0) {
        $catDesc = trim(str_ireplace(['anggaran ', 'budget '], '', PahamFin_extract_description($message)));
        $category = PahamFin_match_category($pdo, $userId, $catDesc, $catDesc);
        if (!$category) {
            throw new RuntimeException("Kategori tidak ditemukan untuk anggaran: \"{$catDesc}\".");
        }
        
        // Cek apakah sudah ada budget untuk kategori ini
        $stmt = $pdo->prepare("SELECT id FROM budgets WHERE user_id = ? AND category_id = ? LIMIT 1");
        $stmt->execute([$userId, $category['id']]);
        $exist = $stmt->fetch();
        
        if ($exist) {
            $pdo->prepare("UPDATE budgets SET amount = ?, period = 'monthly' WHERE id = ?")->execute([$amount, $exist['id']]);
        } else {
            $pdo->prepare("INSERT INTO budgets (user_id, category_id, amount, period) VALUES (?, ?, ?, 'monthly')")->execute([$userId, $category['id'], $amount]);
        }
        
        $fmtAmount = number_format($amount, 0, ',', '.');
        echo json_encode([
            'success' => true, 'status' => 'success',
            'message' => "💼 Anggaran bulanan untuk kategori *{$category['name']}* berhasil diatur sebesar Rp {$fmtAmount}!"
        ]);
        exit;
    }
    
    $category = PahamFin_match_category($pdo, $userId, $message, $finalDescription);


    if (!$category) {
        if (stripos($finalDescription, 'uang masuk') !== false || stripos($finalDescription, 'pemasukan') !== false || stripos($finalDescription, 'terima') !== false) {
            $catStmt = $pdo->prepare("SELECT * FROM categories WHERE user_id = ? AND type = 'PEMASUKAN' LIMIT 1");
            $catStmt->execute([$userId]);
            $category = $catStmt->fetch(PDO::FETCH_ASSOC);
        }
    }
    if (!$category) {
        if (stripos($finalDescription, 'uang keluar') !== false || stripos($finalDescription, 'pengeluaran') !== false || stripos($finalDescription, 'bayar') !== false || stripos($finalDescription, 'beli') !== false) {
            $catStmt = $pdo->prepare("SELECT * FROM categories WHERE user_id = ? AND type = 'PENGELUARAN' LIMIT 1");
            $catStmt->execute([$userId]);
            $category = $catStmt->fetch(PDO::FETCH_ASSOC);
        }
    }

    if (!$category) {
        throw new RuntimeException("Kategori tidak ditemukan untuk: \"{$finalDescription}\". Atur keyword di dashboard.");
    }


    // Ekstrak dompet jika ada
    $walletId = null;
    $walletName = '';
    $stmt = $pdo->prepare("SELECT id, name FROM wallets WHERE user_id = ?");
    $stmt->execute([$userId]);
    $wallets = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($wallets as $w) {
        if (stripos($finalDescription, $w['name']) !== false || stripos($message, $w['name']) !== false) {
            $walletId = $w['id'];
            $walletName = $w['name'];
            // Hapus nama dompet dari deskripsi akhir agar lebih rapi
            $finalDescription = trim(str_ireplace($w['name'], '', $finalDescription));
            break;
        }
    }

    $stmt = $pdo->prepare("INSERT INTO transactions (user_id, category_id, amount, description, type, transaction_date, wallet_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$userId, (int) $category['id'], $amount, $finalDescription, $category['type'], date('Y-m-d'), $walletId]);

    if ($category['type'] === 'PEMASUKAN') {
        try {
            PahamFin_notify($pdo, (int) $userId, 'income', 'Uang masuk: ' . $finalDescription . ' sebesar Rp ' . number_format($amount, 0, ',', '.'), (float) $amount);
        } catch (Throwable $e) {
            // Notifikasi opsional; jangan gagalkan catatan transaksi.
        }
    }

    try {
        PahamFin_check_budget_alerts($pdo, (int) $userId, (int) $category['id']);
    } catch (Throwable $e) {
        // Notifikasi anggaran opsional.
    }

    $fmtAmount = number_format($amount, 0, ',', '.');
    $walletText = $walletName ? " (via {$walletName})" : "";
    echo json_encode([
        'success' => true,
        'status' => 'success',
        'message' => "Transaksi berhasil dicatat: {$category['name']} - Rp {$fmtAmount} {$walletText}",
        'type' => $category['type'],
        'category' => $category['name'],
        'amount' => $amount,
        'description' => $finalDescription,
    ]);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'status' => 'error',
        'message' => $e->getMessage(),
    ]);
}



