<?php
// Default timezone aplikasi (WIB / Indonesia Barat).
if (function_exists('date_default_timezone_set')) {
    date_default_timezone_set('Asia/Jakarta');
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function PahamFin_schema(PDO $pdo, string $driver): void
{
    $autoIncrement = $driver === 'sqlite' ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'INT AUTO_INCREMENT PRIMARY KEY';
    $timestamp     = $driver === 'sqlite' ? "DATETIME DEFAULT CURRENT_TIMESTAMP" : "TIMESTAMP DEFAULT CURRENT_TIMESTAMP";
    $categoryType  = $driver === 'sqlite'
        ? "TEXT NOT NULL CHECK(type IN ('PEMASUKAN','PENGELUARAN','TABUNGAN','HUTANG','PIUTANG'))"
        : "ENUM('PEMASUKAN','PENGELUARAN','TABUNGAN','HUTANG','PIUTANG') NOT NULL";
    $oweType = $driver === 'sqlite'
        ? "TEXT NOT NULL CHECK(type IN ('OWE','LENT'))"
        : "ENUM('OWE','LENT') NOT NULL";
    $paidStatus = $driver === 'sqlite'
        ? "TEXT NOT NULL DEFAULT 'UNPAID' CHECK(status IN ('UNPAID','PAID'))"
        : "ENUM('UNPAID','PAID') NOT NULL DEFAULT 'UNPAID'";
    $fkCategories  = $driver === 'sqlite' ? '' : ', FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE';
    $fkTransactions = $driver === 'sqlite' ? '' :
        ', FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE, FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE';
    $fkBudgets = $driver === 'sqlite' ? '' :
        ', FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE, FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE';

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id $autoIncrement,
            name VARCHAR(100) NOT NULL,
            email VARCHAR(150) NULL UNIQUE,
            phone_number VARCHAR(20) NULL UNIQUE,
            password VARCHAR(255) NOT NULL DEFAULT '',
            role VARCHAR(20) NOT NULL DEFAULT 'user',
            telegram_id VARCHAR(50) NULL UNIQUE,
            created_at $timestamp
        )
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS categories (
            id $autoIncrement,
            user_id INT NOT NULL,
            name VARCHAR(50) NOT NULL,
            keyword VARCHAR(255) NOT NULL,
            type $categoryType,
            created_at $timestamp
            $fkCategories
        )
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS transactions (
            id $autoIncrement,
            user_id INT NOT NULL,
            category_id INT NOT NULL,
            amount DECIMAL(15,2) NOT NULL,
            description TEXT,
            type $categoryType,
            transaction_date DATE NOT NULL DEFAULT (CURRENT_DATE),
            created_at $timestamp
            $fkTransactions
        )
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS budgets (
            id $autoIncrement,
            user_id INT NOT NULL,
            category_id INT NOT NULL,
            amount DECIMAL(15,2) NOT NULL,
            period VARCHAR(20) NOT NULL DEFAULT 'monthly',
            created_at $timestamp
            $fkBudgets
        )
    ");

    // Tabel arsip transaksi bulanan (untuk mereset bulan aktif tanpa menghapus data).
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS transaction_archive (
            id $autoIncrement,
            user_id INT NOT NULL,
            category_id INT NOT NULL,
            amount DECIMAL(15,2) NOT NULL,
            description TEXT,
            type $categoryType,
            transaction_date DATE NOT NULL,
            period VARCHAR(7) NOT NULL,
            created_at $timestamp
        )
    ");

    // Tabel notifikasi dalam aplikasi.
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS notifications (
            id $autoIncrement,
            user_id INT NOT NULL,
            type VARCHAR(30) NOT NULL,
            message VARCHAR(255) NOT NULL,
            amount DECIMAL(15,2) NULL,
            is_read TINYINT(1) NOT NULL DEFAULT 0,
            created_at $timestamp
        )
    ");

    // Tabel target tabungan.
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS savings_goals (
            id $autoIncrement,
            user_id INT NOT NULL,
            name VARCHAR(100) NOT NULL,
            target_amount DECIMAL(15,2) NOT NULL,
            saved_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
            deadline DATE NULL,
            created_at $timestamp
        )
    ");

    // Tabel pengingat / reminder.
    // Tabel pengingat / reminder.
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS reminders (
            id $autoIncrement,
            user_id INT NOT NULL,
            title VARCHAR(150) NOT NULL,
            type VARCHAR(30) NOT NULL DEFAULT 'note',
            remind_date DATE NOT NULL,
            remind_time VARCHAR(5) NULL,
            done TINYINT(1) NOT NULL DEFAULT 0,
            created_at $timestamp
        )
    ");

    // Tabel hutang piutang (debts).
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS debts (
            id $autoIncrement,
            user_id INT NOT NULL,
            person_name VARCHAR(100) NOT NULL,
            amount DECIMAL(15,2) NOT NULL,
            type $oweType, 
            status $paidStatus,
            due_date DATE NULL,
            created_at $timestamp
        )
    ");
    try {
        $pdo->exec("ALTER TABLE debts ADD COLUMN status $paidStatus");
    } catch (Throwable $e) {}
    try {
        $pdo->exec("ALTER TABLE debts ADD COLUMN due_date DATE NULL");
    } catch (Throwable $e) {}

    // Tabel dompet (wallets).
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS wallets (
            id $autoIncrement,
            user_id INT NOT NULL,
            name VARCHAR(100) NOT NULL,
            starting_balance DECIMAL(15,2) NOT NULL DEFAULT 0,
            created_at $timestamp
        )
    ");

    try {
        $pdo->exec("ALTER TABLE transactions ADD COLUMN wallet_id INT NULL");
    } catch (Throwable $e) {}

    try {
        $pdo->exec("ALTER TABLE users ADD COLUMN profile_pic VARCHAR(255) NULL");
    } catch (Throwable $e) {}

    // Tabel catatan keuangan (notes).
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS notes (
            id $autoIncrement,
            user_id INT NOT NULL,
            title VARCHAR(150) NOT NULL DEFAULT '',
            content TEXT NOT NULL,
            color VARCHAR(20) NOT NULL DEFAULT 'yellow',
            created_at $timestamp
        )
    ");

    // Indeks (guard untuk MySQL <= 5.7 yang tidak mendukung ADD ... IF NOT EXISTS)
    $indexes = [
        "CREATE INDEX IF NOT EXISTS idx_categories_user ON categories (user_id)",
        "CREATE INDEX IF NOT EXISTS idx_transactions_user ON transactions (user_id)",
        "CREATE INDEX IF NOT EXISTS idx_transactions_created ON transactions (created_at)",
        "CREATE INDEX IF NOT EXISTS idx_transactions_date ON transactions (transaction_date)",
        "CREATE INDEX IF NOT EXISTS idx_budgets_user ON budgets (user_id)",
        "CREATE UNIQUE INDEX IF NOT EXISTS idx_budgets_cat_period ON budgets (user_id, category_id, period)",
        "CREATE INDEX IF NOT EXISTS idx_archive_user ON transaction_archive (user_id)",
        "CREATE INDEX IF NOT EXISTS idx_archive_period ON transaction_archive (user_id, period)",
        "CREATE INDEX IF NOT EXISTS idx_notif_user ON notifications (user_id)",
        "CREATE INDEX IF NOT EXISTS idx_notes_user ON notes (user_id)",
        "CREATE INDEX IF NOT EXISTS idx_savings_user ON savings_goals (user_id)",
        "CREATE INDEX IF NOT EXISTS idx_reminders_user ON reminders (user_id)",
        "CREATE INDEX IF NOT EXISTS idx_debts_user ON debts (user_id)",
    ];
    foreach ($indexes as $sql) {
        try {
            $pdo->exec($sql);
        } catch (PDOException $e) {
            // Abaikan jika indeks sudah ada / sintaks tidak didukung
        }
    }
}

// ---- CSRF protection ----
function PahamFin_csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function PahamFin_csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . PahamFin_csrf_token() . '">';
}

function PahamFin_csrf_verify(): bool
{
    $sent = $_POST['csrf_token'] ?? '';
    return is_string($sent) && $sent !== '' && hash_equals($_SESSION['csrf_token'] ?? '', $sent);
}

// ---- Webhook shared secret (mengambil dari config bila tersedia) ----
$PahamFin_WEBHOOK_SECRET = '';
if (!defined('PahamFin_WEBHOOK_SECRET')) {
    if (file_exists(__DIR__ . '/includes/config.php')) {
        require_once __DIR__ . '/includes/config.php';
    }
    $PahamFin_WEBHOOK_SECRET = defined('PahamFin_WEBHOOK_SECRET') ? PahamFin_WEBHOOK_SECRET : '';
}

function PahamFin_webhook_secret(): string
{
    global $PahamFin_WEBHOOK_SECRET;
    if ($PahamFin_WEBHOOK_SECRET === '' && defined('PahamFin_WEBHOOK_SECRET')) {
        $GLOBALS['PahamFin_WEBHOOK_SECRET'] = PahamFin_WEBHOOK_SECRET;
    }
    return $GLOBALS['PahamFin_WEBHOOK_SECRET'] ?? '';
}

$pdo = null;
$databaseDriver = getenv('PahamFin_DB_DRIVER') ?: 'mysql';

try {
    if ($databaseDriver === 'mysql') {
        $host = defined('PahamFin_DB_HOST') ? PahamFin_DB_HOST : (getenv('PahamFin_DB_HOST') ?: 'localhost');
        $db   = defined('PahamFin_DB_NAME') ? PahamFin_DB_NAME : (getenv('PahamFin_DB_NAME') ?: 'PahamFin_db');
        $user = defined('PahamFin_DB_USER') ? PahamFin_DB_USER : (getenv('PahamFin_DB_USER') ?: 'root');
        $pass = defined('PahamFin_DB_PASS') ? PahamFin_DB_PASS : (getenv('PahamFin_DB_PASS') ?: '');

        $pdo = new PDO("mysql:host={$host};dbname={$db};charset=utf8mb4", $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }
} catch (Throwable $e) {
    $pdo = null;
}

if (!$pdo) {
    try {
        $sqlitePath = __DIR__ . '/PahamFin.db';
        $pdo = new PDO("sqlite:{$sqlitePath}", null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    } catch (Throwable $e) {
        $pdo = null;
    }
}

if ($pdo) {
    try {
        $databaseDriver = strtolower((string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME));
        PahamFin_schema($pdo, $databaseDriver);
    } catch (Throwable $e) {
        error_log('PahamFin schema notice: ' . $e->getMessage());
    }
}

// ---- Migrasi: tambah kolom remember_token untuk "Remember Me" ----
function PahamFin_column_exists(PDO $pdo, string $table, string $column): bool
{
    try {
        $rows = $pdo->query(
            "SELECT column_name FROM information_schema.columns " .
            "WHERE table_schema = DATABASE() " .
            "AND table_name = " . $pdo->quote($table) . " " .
            "AND column_name = " . $pdo->quote($column)
        )->fetchAll();
        return count($rows) > 0;
    } catch (PDOException $e) {
        return false;
    }
}

if (PahamFin_column_exists($pdo, 'users', 'remember_token') === false) {
    try {
        $pdo->exec("ALTER TABLE users ADD COLUMN remember_token VARCHAR(64) NULL");
        $pdo->exec("CREATE INDEX idx_users_remember_token ON users (remember_token)");
    } catch (PDOException $e) {
        // SQLite / tabel belum ada
    }
}

// ---- Migrasi: tambah kolom google_id untuk "Login dengan Google" ----
if (PahamFin_column_exists($pdo, 'users', 'google_id') === false) {
    try {
        $pdo->exec("ALTER TABLE users ADD COLUMN google_id VARCHAR(100) NULL");
    } catch (PDOException $e) {
        // SQLite / tabel belum ada
    }
}

// ---- Migrasi: tambah kolom role untuk status admin ----
if (PahamFin_column_exists($pdo, 'users', 'role') === false) {
    try {
        $pdo->exec("ALTER TABLE users ADD COLUMN role VARCHAR(20) NOT NULL DEFAULT 'user'");
    } catch (PDOException $e) {
        // SQLite / tabel belum ada
    }
}

// ---- Migrasi: tambah kolom transaction_date pada tabel lama ----
if (PahamFin_column_exists($pdo, 'transactions', 'transaction_date') === false) {
    try {
        $pdo->exec("ALTER TABLE transactions ADD COLUMN transaction_date DATE");
        $pdo->exec("UPDATE transactions SET transaction_date = DATE(created_at) WHERE transaction_date IS NULL");
    } catch (PDOException $e) {
        // SQLite / tabel belum ada
    }
}

// ---- Status admin ----
// Admin bila role di database 'admin' ATAU emailnya terdaftar di
// PahamFin_ADMIN_EMAILS (config). Email dari daftar itu otomatis dinaikkan
// jadi admin saat login/daftar.
function PahamFin_user_role(array $user): string
{
    $role = strtolower(trim((string) ($user['role'] ?? '')));
    if ($role === 'admin') {
        return 'admin';
    }

    $email = strtolower(trim((string) ($user['email'] ?? '')));
    if ($email === '' || defined('PahamFin_ADMIN_EMAILS') === false) {
        return 'user';
    }
    $allowed = [];
    foreach (explode(',', PahamFin_ADMIN_EMAILS) as $e) {
        $e = strtolower(trim($e));
        if ($e !== '') {
            $allowed[] = $e;
        }
    }
    return in_array($email, $allowed, true) ? 'admin' : 'user';
}

function PahamFin_is_admin(array $user): bool
{
    return PahamFin_user_role($user) === 'admin';
}

// ---- "Remember Me": auto-login via cookie bila belum login lewat sesi ----
function PahamFin_remember_token(): string
{
    return bin2hex(random_bytes(32));
}

function PahamFin_set_remember_cookie(int $userId, PDO $pdo): void
{
    $token = PahamFin_remember_token();
    $hash  = hash('sha256', $token);
    $stmt  = $pdo->prepare("UPDATE users SET remember_token = ? WHERE id = ?");
    $stmt->execute([$hash, $userId]);
    setcookie('PahamFin_remember', $token, [
        'expires'  => time() + 30 * 24 * 3600,
        'path'     => '/',
        'secure'   => false,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function PahamFin_clear_remember_cookie(int $userId, PDO $pdo): void
{
    $stmt = $pdo->prepare("UPDATE users SET remember_token = NULL WHERE id = ?");
    $stmt->execute([$userId]);
    setcookie('PahamFin_remember', '', [
        'expires'  => time() - 42000,
        'path'     => '/',
        'secure'   => false,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

// ---- Arsip transaksi bulanan (auto-restart saat ganti bulan) ----
// Periode aktif = bulan berjalan (Y-m). Transaksi dari bulan sebelumnya
// otomatis dipindahkan ke tabel arsip lalu dihapus dari tampilan bulan aktif.
// Data tetap aman dan bisa dipantau lewat history per bulan.
function PahamFin_active_period(): string
{
    return date('Y-m');
}

/**
 * Memindahkan semua transaksi yang bukan bulan berjalan ke tabel arsip,
 * per bulan masing-masing. Dijalankan otomatis di setiap halaman.
 */
function PahamFin_auto_archive(PDO $pdo, int $userId): void
{
    $active = PahamFin_active_period();

    // Ambil transaksi yang bulan transaksinya berbeda dengan bulan aktif.
    $stmt = $pdo->prepare(
        "SELECT id, category_id, amount, description, type, transaction_date
         FROM transactions
         WHERE user_id = ?
           AND DATE_FORMAT(transaction_date, '%Y-%m') <> ?"
    );
    $stmt->execute([$userId, $active]);
    $toArchive = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($toArchive) === 0) {
        return;
    }

    $insert = $pdo->prepare(
        "INSERT INTO transaction_archive
            (user_id, category_id, amount, description, type, transaction_date, period, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $del = $pdo->prepare("DELETE FROM transactions WHERE id = ? AND user_id = ?");

    foreach ($toArchive as $t) {
        $period = substr((string) $t['transaction_date'], 0, 7);
        $insert->execute([
            $userId,
            $t['category_id'],
            $t['amount'],
            $t['description'],
            $t['type'],
            $t['transaction_date'],
            $period,
            $t['transaction_date'] . ' 00:00:00',
        ]);
        $del->execute([$t['id'], $userId]);
    }
}

// ---- Notifikasi dalam aplikasi ----
function PahamFin_notify(PDO $pdo, int $userId, string $type, string $message, ?float $amount = null): void
{
    $stmt = $pdo->prepare(
        "INSERT INTO notifications (user_id, type, message, amount) VALUES (?, ?, ?, ?)"
    );
    $stmt->execute([$userId, $type, $message, $amount]);
}

function PahamFin_unread_notifications(PDO $pdo, int $userId): int
{
    $stmt = $pdo->prepare("SELECT COUNT(*) AS n FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$userId]);
    return (int) $stmt->fetchColumn();
}

function PahamFin_recent_notifications(PDO $pdo, int $userId, int $limit = 10): array
{
    $stmt = $pdo->prepare(
        "SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC, id DESC LIMIT " . (int) $limit
    );
    $stmt->execute([$userId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function PahamFin_mark_notifications_read(PDO $pdo, int $userId): void
{
    $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$userId]);
}

// ---- Peringatan anggaran melebihi batas ----
// Mengecek seluruh anggaran bulanan; bila pemakaian bulan ini sudah melewati limit
// dan belum pernah dinotifikasikan untuk kategori+periode tersebut, buat notifikasi.
// Tanda signature digunakan untuk mencegah notifikasi berulang.
function PahamFin_check_budget_alerts(PDO $pdo, int $userId, ?int $onlyCategoryId = null): void
{
    $sql = "SELECT b.id, b.category_id, b.amount, b.period, c.name AS category_name
            FROM budgets b JOIN categories c ON c.id = b.category_id
            WHERE b.user_id = ? AND c.type = 'PENGELUARAN'";
    $params = [$userId];
    if ($onlyCategoryId !== null) {
        $sql .= " AND b.category_id = ?";
        $params[] = $onlyCategoryId;
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $budgets = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $spentStmt = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM transactions WHERE user_id = ? AND category_id = ? AND type='PENGELUARAN' AND transaction_date BETWEEN ? AND ?");
    $existsStmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND type = 'budget_over' AND is_read = 0 AND message LIKE ?");

    foreach ($budgets as $b) {
        $period = date('Y-m');
        $spentStmt->execute([$userId, (int) $b['category_id'], date('Y-m-01'), date('Y-m-t')]);
        $spent = (float) $spentStmt->fetchColumn();
        $limit = (float) $b['amount'];
        if ($spent <= $limit) {
            continue;
        }
        $signature = "[" . $b['category_name'] . "] (" . $period . ")";
        $existsStmt->execute([$userId, '%' . $signature . '%']);
        if ((int) $existsStmt->fetchColumn() > 0) {
            continue;
        }
        $msg = "⛔ Anggaran kategori \"{$b['category_name']}\" bulan ini sudah melebihi limit "
             . "Rp " . number_format($limit, 0, ',', '.') . " (terpakai Rp " . number_format($spent, 0, ',', '.') . "). " . $signature;
        PahamFin_notify($pdo, $userId, 'budget_over', $msg, (float) $spent);
    }
}

// ---- Target tabungan ----
function PahamFin_savings_goals(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare("SELECT * FROM savings_goals WHERE user_id = ? ORDER BY deadline ASC, id DESC");
    $stmt->execute([$userId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ---- Seed Kategori Lengkap Otomatis ----
function PahamFin_seed_default_categories(PDO $pdo, int $userId): void
{
    $defaultCategories = [
        ['Gaji & Pendapatan',     'gaji,bonus,pendapatan,thr,salary,upah,komisi,hasil,insentif,transfer masuk,uang saku,saku,sangu,uang jajan,kiriman,dapat,terima,diberi,amplop,uang makan,tunjangan,lembur,lemburan,tips,tipping,pensiun,bansos,bantuan', 'PEMASUKAN'],
        ['Usaha & UMKM',          'omset,omzet,jualan,dagang,laku,untung,freelance,projek,project,bisnis,profit,kasir,pelanggan,setoran,pembayaran,klien,client,orderan,pesanan,modal balik,hasil jualan,toko,warung,lapak,olshop,cod,reseller,dropship,komisi jualan,invoice,termin', 'PEMASUKAN'],
        ['Investasi & Pasif',     'dividen,bunga,investasi,saham,crypto,kripto,cashback,hibah,hadiah,reksadana,sewa,sewaan,kontrakan masuk,kosan masuk,yield,profit sharing,bunga bank,bunga deposito,cuan,airdrop', 'PEMASUKAN'],
        ['Makanan & Minuman',    'makan,minum,kopi,kfc,mcd,warteg,sate,bakso,nasi,beli makan,gofood,grabfood,shopeefood,cafe,jajan,snack,boba,sarapan,makan siang,makan malam,seblak,mie ayam,nasgor,gorengan,air mineral,galon,coffe,matcha,roti,es teh,ice cream,solaria,mixue,warmindo,angkringan,lauk,daging,sayur,pasar,bumbu,lauk pauk,ketoprak,pempek,martabak,kue', 'PENGELUARAN'],
        ['Transportasi',         'bensin,gojek,grab,toll,tol,parkir,ongkir,ojek,bus,kereta,tiket,servis,oli,pertalite,pertamax,krl,travel,maxim,indrive,tambal ban,cuci motor,cuci mobil,bengkel,angkot,taksi,flight,pesawat,tiptop,helm,perpanjang stnk,pajak motor,pajak mobil', 'PENGELUARAN'],
        ['Belanja & Harian',     'belanja,shopee,tokopedia,baju,sepatu,grocery,alfamart,indomaret,mall,skincare,kosmetik,minimarket,tiktok shop,lazada,pakaian,baju kerja,celana,tas,makeup,sabun,shampoo,odol,detergen,tisu,perlengkapan,pampers,susu anak,belanja bulanan,supermarket,superindo', 'PENGELUARAN'],
        ['Tagihan & Operasional', 'listrik,air,wifi,internet,pulsa,token,kuota,pdam,kost,kontrakan,sewa,asuransi,bpjs,cicilan,kartu kredit,paylater,spaylater,gopaylater,sewa tempat,sewa toko,gaji karyawan,gaji pegawai,operasional,pajak,domain,hosting,atk,kertas,cetak,plastik,packing,banner,iklan,ads,fb ads,google ads,tiktok ads', 'PENGELUARAN'],
        ['Hiburan & Lifestyle',  'nonton,bioskop,netflix,spotify,game,topup,jalan,liburan,rekreasi,party,nongkrong,piknik,voucher,skin,topup game,steam,playstation,mlbb,pubg,valorant,billiard,futsal,badminton,gym,fitnes,konser,tiket konser,staycation,kafe,hangout,movie,buku komik,manga,anime,cosplay', 'PENGELUARAN'],
        ['Kesehatan & Perawatan', 'obat,dokter,rumah sakit,klinik,vitamin,apotek,salon,potong rambut,barbershop,spa,skincare rutin,pemeriksaan,behel,gigi,kacamata,softlens,terapi,pijat,urut,konsultasi,kasa,perban', 'PENGELUARAN'],
        ['Pendidikan & Kursus',  'spp,kuliah,sekolah,buku,kursus,seminar,les,ukt,pendaftaran,seragam,alat tulis,pensil,pulpen,modul,sertifikasi,pelatihan,bootcamp,workshop,skripsi,fotokopi,print', 'PENGELUARAN'],
        ['Sedekah & Sosial',     'sedekah,infak,zakat,orang tua,ortu,angpao,kado,kirim ortu,donasi,sumbangan,kondangan,nasi kotak,arisan,thr saudara,amplop nikah,santunan,kas,iuran,patungan,iuran rt,iuran rw,keamanan,kebersihan', 'PENGELUARAN'],
        ['Stok & Kulakan UMKM',  'kulakan,bahan baku,stok,restok,belanja stok,grosir,supplier,distributor,pembelian bahan,kain,benang,kemasan,dus,botol,kantong,kardus,nota,belanja modal', 'PENGELUARAN'],
        ['Tabungan & Simpanan',  'tabungan,nabung,menabung,deposito,simpanan,celengan,dana darurat,reksa dana,emas,antam', 'TABUNGAN'],
    ];

    $check = $pdo->prepare("SELECT COUNT(*) FROM categories WHERE user_id = ? AND LOWER(name) = LOWER(?)");
    $stmt  = $pdo->prepare("INSERT INTO categories (user_id, name, keyword, type) VALUES (?, ?, ?, ?)");
    $updateStmt = $pdo->prepare("UPDATE categories SET keyword = ? WHERE user_id = ? AND LOWER(name) = LOWER(?)");

    foreach ($defaultCategories as $cat) {
        $check->execute([$userId, $cat[0]]);
        if ((int) $check->fetchColumn() === 0) {
            $stmt->execute([$userId, $cat[0], $cat[1], $cat[2]]);
        } else {
            // Update keywords jika kategori bawaan sudah ada agar kata kunci baru langsung aktif
            $updateStmt->execute([$cat[1], $userId, $cat[0]]);
        }
    }
}

$user_id = null;
if (isset($_SESSION['user_id'])) {
    $user_id = (int) $_SESSION['user_id'];
} elseif (isset($_COOKIE['PahamFin_remember'])) {
    $rememberToken = (string) $_COOKIE['PahamFin_remember'];
    $hash = hash('sha256', $rememberToken);
    $stmt = $pdo->prepare("SELECT * FROM users WHERE remember_token = ? LIMIT 1");
    $stmt->execute([$hash]);
    $remUser = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($remUser) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $remUser['id'];
        $_SESSION['user_role'] = PahamFin_user_role((array) $remUser);
        $user_id = (int) $remUser['id'];
    } else {
        setcookie('PahamFin_remember', '', ['expires' => time() - 42000, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax']);
    }
}

// Auto-arsip: pindahkan transaksi bulan sebelumnya dari tampilan bulan aktif
// ke history per bulan. Dijalankan ke tiap halaman saat user sudah login.
if ($user_id !== null) {
    try {
        PahamFin_auto_archive($pdo, $user_id);
    } catch (PDOException $e) {
        // Abaikan bila tabel/kolom belum siap (mis. SQLite).
    }
}

