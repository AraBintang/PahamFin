<?php
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/includes/config.php';

header('Content-Type: application/json');

// Keamanan - pakai shared secret yang sama
$secret = PahamFin_webhook_secret();
if ($secret !== '') {
    $sentKey = $_SERVER['HTTP_X_PahamFin_KEY'] ?? ($_GET['key'] ?? ($_POST['key'] ?? ''));
    if (!is_string($sentKey) || !hash_equals($secret, $sentKey)) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }
}

// Pastikan kolom tracking notifikasi ada di tabel reminders
try {
    $pdo->exec("ALTER TABLE reminders ADD COLUMN notified_h1 TINYINT(1) NOT NULL DEFAULT 0");
} catch (Throwable $e) {}
try {
    $pdo->exec("ALTER TABLE reminders ADD COLUMN notified_today TINYINT(1) NOT NULL DEFAULT 0");
} catch (Throwable $e) {}

$today = date('Y-m-d');
$tomorrow = date('Y-m-d', strtotime('+1 day'));
$messagesToSend = [];

// 1. Cek Pengingat H-1 (Besok)
$stmt = $pdo->prepare("SELECT r.*, u.telegram_id, u.phone_number FROM reminders r JOIN users u ON r.user_id = u.id WHERE r.done = 0 AND r.notified_h1 = 0 AND r.remind_date = ?");
$stmt->execute([$tomorrow]);
$h1 = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($h1 as $r) {
    // Masukkan ke notifikasi dashboard
    $pdo->prepare("INSERT INTO notifications (user_id, type, message) VALUES (?, 'reminder', ?)")
        ->execute([$r['user_id'], "Pengingat H-1: {$r['title']}"]);
    
    // Tandai sudah dinotif
    $pdo->prepare("UPDATE reminders SET notified_h1 = 1 WHERE id = ?")
        ->execute([$r['id']]);
    
    if ($r['telegram_id']) {
        $messagesToSend[] = [
            'telegram_id' => $r['telegram_id'], 
            'message' => "🔔 *Pengingat Besok!*\n\n📝: {$r['title']}\n⏳ Jatuh Tempo: Besok"
        ];
    }
}

// 2. Cek Pengingat HARI INI atau yang kelewat
$stmt = $pdo->prepare("SELECT r.*, u.telegram_id, u.phone_number FROM reminders r JOIN users u ON r.user_id = u.id WHERE r.done = 0 AND r.notified_today = 0 AND r.remind_date <= ?");
$stmt->execute([$today]);
$todayReminders = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($todayReminders as $r) {
    // Masukkan ke notifikasi dashboard
    $pdo->prepare("INSERT INTO notifications (user_id, type, message) VALUES (?, 'reminder', ?)")
        ->execute([$r['user_id'], "Jatuh Tempo Hari Ini: {$r['title']}"]);
    
    $pdo->prepare("UPDATE reminders SET notified_today = 1 WHERE id = ?")
        ->execute([$r['id']]);
        
    if ($r['telegram_id']) {
        $messagesToSend[] = [
            'telegram_id' => $r['telegram_id'], 
            'message' => "🚨 *PENGINGAT JATUH TEMPO HARI INI!*\n\n📝: {$r['title']}\n\n_Ketik \`/done {$r['id']}\` jika sudah diselesaikan._"
        ];
    }
    }
}

// 3. Financial Wrapped (Rekap Otomatis Awal Bulan)
try {
    $pdo->exec("ALTER TABLE users ADD COLUMN last_wrapped_month VARCHAR(10) NULL");
} catch (Throwable $e) {}

if (date('d') === '01') {
    $lastMonth = date('Y-m', strtotime('-1 month'));
    $stmt = $pdo->prepare("SELECT id, telegram_id, last_wrapped_month FROM users WHERE telegram_id IS NOT NULL AND (last_wrapped_month IS NULL OR last_wrapped_month != ?)");
    $stmt->execute([$lastMonth]);
    $usersToWrap = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $startLastMonth = date('Y-m-01', strtotime('-1 month'));
    $endLastMonth = date('Y-m-t', strtotime('-1 month'));

    foreach ($usersToWrap as $u) {
        $uId = $u['id'];
        
        // Total Pengeluaran & Pemasukan
        $st = $pdo->prepare("SELECT type, SUM(amount) as total FROM transactions WHERE user_id = ? AND transaction_date BETWEEN ? AND ? GROUP BY type");
        $st->execute([$uId, $startLastMonth, $endLastMonth]);
        $totals = $st->fetchAll(PDO::FETCH_KEY_PAIR);
        $exp = (float)($totals['PENGELUARAN'] ?? 0);
        $inc = (float)($totals['PEMASUKAN'] ?? 0);
        
        if ($exp > 0 || $inc > 0) {
            // Kategori pengeluaran terbesar
            $st2 = $pdo->prepare("SELECT c.name, SUM(t.amount) as total FROM transactions t JOIN categories c ON t.category_id = c.id WHERE t.user_id = ? AND t.type = 'PENGELUARAN' AND t.transaction_date BETWEEN ? AND ? GROUP BY c.id ORDER BY total DESC LIMIT 1");
            $st2->execute([$uId, $startLastMonth, $endLastMonth]);
            $topCat = $st2->fetch(PDO::FETCH_ASSOC);
            
            $msg = "🎉 *PahamFin WRAPPED: " . date('F Y', strtotime('-1 month')) . "* 🎉\n\n";
            $msg .= "Bulan lalu kamu telah mencatat keuanganmu dengan baik! Berikut rangkumannya:\n\n";
            $msg .= "📥 *Pemasukan:* Rp " . number_format($inc, 0, ',', '.') . "\n";
            $msg .= "📤 *Pengeluaran:* Rp " . number_format($exp, 0, ',', '.') . "\n\n";
            
            if ($topCat) {
                $msg .= "🔥 *Pengeluaran Terbesar:* \n" . $topCat['name'] . " (Rp " . number_format($topCat['total'], 0, ',', '.') . ")\n\n";
            }
            
            if ($inc > $exp) {
                $msg .= "💡 *Status:* Sehat! Kamu berhasil menyisihkan Rp " . number_format($inc - $exp, 0, ',', '.') . " bulan lalu. Pertahankan!";
            } else if ($exp > $inc) {
                $msg .= "⚠️ *Status:* Defisit! Pengeluaranmu lebih besar Rp " . number_format($exp - $inc, 0, ',', '.') . " dari pemasukan. Yuk lebih hemat bulan ini!";
            }
            
            $messagesToSend[] = [
                'telegram_id' => $u['telegram_id'],
                'message' => $msg
            ];
        }
        
        $pdo->prepare("UPDATE users SET last_wrapped_month = ? WHERE id = ?")->execute([$lastMonth, $uId]);
    }
}

echo json_encode(['success' => true, 'send' => $messagesToSend]);
