<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';
require_login();

$user_id = current_user_id();

$userProfileStmt = $pdo->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
$userProfileStmt->execute([$user_id]);
$userProfile = $userProfileStmt->fetch(PDO::FETCH_ASSOC);

if (!$userProfile) {
    // User telah dihapus di database, log out segera
    session_destroy();
    header('Location: ' . PahamFin_URL_AUTH . '/login.php?msg=account_deleted');
    exit;
}

$isAdmin = PahamFin_is_admin((array) $userProfile);
if (!isset($_SESSION['user_role'])) {
    $_SESSION['user_role'] = $isAdmin ? 'admin' : 'user';
}

$current_page = basename($_SERVER['PHP_SELF'], '.php');
$adminPage = (basename(dirname($_SERVER['PHP_SELF'])) === 'admin');

function get_page_title($page, $isAdmin, $adminPage) {
    if ($adminPage) {
        switch ($page) {
            case 'index': return 'Dashboard Admin';
            case 'users': return 'Manajemen Pengguna';
            case 'transactions': return 'Semua Transaksi (Admin)';
            case 'categories': return 'Kategori (Admin)';
            case 'budgets': return 'Anggaran (Admin)';
            case 'savings': return 'Tabungan (Admin)';
            case 'reminders': return 'Pengingat (Admin)';
            case 'history': return 'Riwayat (Admin)';
            case 'guide': return 'Panduan (Admin)';
            case 'settings': return 'Pengaturan Admin';
            default: return 'Admin PahamFin';
        }
    }
    switch($page) {
        case 'index': return 'Dashboard';
        case 'transactions': return 'Semua Transaksi';
        case 'categories': return 'Kategori & Bot';
        case 'budgets': return 'Anggaran Bulanan';
        case 'savings': return 'Target Tabungan';
        case 'wallets': return 'Dompet';
        case 'debts': return 'Hutang & Piutang';
        case 'notes': return 'Catatan';
        case 'reminders': return 'Notifikasi & Pengingat';
        case 'history': return 'Riwayat Bulanan';
        case 'guide': return 'Panduan Penggunaan';
        case 'settings': return 'Pengaturan Profil';
        default: return 'PahamFin';
    }
}

$notifUnread = PahamFin_unread_notifications($pdo, $user_id);
$notifList = PahamFin_recent_notifications($pdo, $user_id, 8);
$searchApiUrl = PahamFin_URL_API . '/search.php';
?>
<!DOCTYPE html>
<html lang="id" x-data="appRoot" :class="{ 'dark': darkMode }">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars(get_page_title($current_page, $isAdmin, $adminPage)) ?> - PahamFin</title>
    <link rel="icon" type="image/png" href="<?= PahamFin_URL_LOGO ?>">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>

    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        primary: '#0A58A5',
                        ink: '#0f172a',
                        'dark-base': '#0f172a',
                        'dark-card': '#1e293b',
                        'dark-border': '#334155',
                    },
                    fontFamily: {
                        display: ['Figtree', 'sans-serif'],
                    },
                }
            }
        }
    </script>

    <!-- Alpine store must come BEFORE defer Alpine -->
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('appRoot', () => ({
                sidebarOpen: false,
                collapsed: true,
                notifOpen: false,
                darkMode: localStorage.getItem('PahamFin_dark') === '1',
                init() {
                    window.addEventListener('storage', (e) => {
                        if (e.key === 'PahamFin_dark') {
                            this.darkMode = e.newValue === '1';
                        }
                    });
                },
                toggleDark() {
                    this.darkMode = !this.darkMode;
                    localStorage.setItem('PahamFin_dark', this.darkMode ? '1' : '0');
                },
                
            }));
        });
    </script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Figtree:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <?php require_once __DIR__ . '/theme.php'; ?>
</head>
<body class="bg-canvas text-ink dark:text-slate-100 antialiased font-sans">

    <div class="flex h-screen overflow-hidden">
        <div x-show="sidebarOpen"
             x-transition.opacity
             @click="sidebarOpen = false"
             class="fixed inset-0 z-20 bg-black bg-opacity-60 lg:hidden"></div>


