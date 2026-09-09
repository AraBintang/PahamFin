<?php
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/includes/config.php';
$isLoggedIn = !empty($_SESSION['user_id']);
?>
<!DOCTYPE html>
<html lang="id" x-data="appRoot" :class="{ 'dark': darkMode }">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#0ea5e9">
    <title>PahamFin | Your Automatic Financial Assistant via Telegram</title>
    <link rel="icon" type="image/png" href="<?= PahamFin_URL_LOGO ?>">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Figtree:wght@400;500;600;700;800&family=Manrope:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <script>
        tailwind.config = {
            darkMode: 'class', 
            theme: {
                extend: {
                    colors: {
                        primary: '#0A58A5',
                        dark: '#2d2d2d'
                    },
                    fontFamily: {
                        sans: ['Manrope', 'sans-serif'],
                        display: ['Figtree', 'sans-serif']
                    }
                }
            }
        }
    </script>
    <?php require_once __DIR__ . '/../app/includes/theme.php'; ?>
    <style>
        html { scroll-behavior: smooth; }
        section[id] { scroll-margin-top: 80px; }
        
        .hero-bg {
            /* Removing old gradient so bg-canvas can show through */
            background: transparent;
        }
        .wave {
            background: #0284c7;
            border-top-left-radius: 50% 100%;
            border-top-right-radius: 50% 100%;
        }
        .orb {
            background: linear-gradient(180deg, rgba(234,247,255,0.8) 0%, rgba(50,149,248,0.8) 100%);
        }
    </style>
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('appRoot', () => ({
                menuOpen: false,
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
                }
            }));
        });
    </script>
</head>
<body class="bg-canvas text-dark dark:text-slate-100 antialiased font-sans">

<!-- ===================== NAVBAR ===================== -->
<header id="navbar" class="fixed top-0 left-0 right-0 z-40 h-16 transition-all duration-300 bg-white/70 dark:bg-slate-900/80 backdrop-blur-md shadow-sm border-b border-white/20">
    <div class="max-w-[1440px] mx-auto px-4 lg:px-10 xl:px-40 h-full flex items-center justify-between">
        <a href="index.php" class="flex items-center gap-2">
            <div class="w-12 h-12 md:w-14 md:h-14 flex items-center justify-center">
                <img src="<?= PahamFin_URL_LOGO ?>" alt="PahamFin" class="w-12 h-12 md:w-14 md:h-14 object-contain">
            </div>
            <span class="font-display text-2xl font-extrabold text-dark dark:text-white">Paham<span class="text-primary dark:text-blue-400">Fin</span></span>
        </a>
        <nav class="hidden lg:flex items-center gap-[42px]">
            <a href="#fitur" class="text-sm font-medium hover:opacity-60 transition-opacity">Fitur</a>
            <a href="#keunggulan" class="text-sm font-medium hover:opacity-60 transition-opacity">Keunggulan</a>
            <a href="#cara-pakai" class="text-sm font-medium hover:opacity-60 transition-opacity">Cara Pakai</a>
            <a href="#testimoni" class="text-sm font-medium hover:opacity-60 transition-opacity">Testimoni</a>
            <a href="#harga" class="text-sm font-medium hover:opacity-60 transition-opacity">Harga</a>
            <a href="#faq" class="text-sm font-medium hover:opacity-60 transition-opacity">FAQ</a>
        </nav>
        <div class="hidden lg:flex items-center gap-3">
            <?= PahamFin_theme_toggle('landing') ?>
            <?php if ($isLoggedIn): ?>
                <a href="index.php" class="inline-flex items-center justify-center w-[110px] h-[40px] bg-primary text-white border border-primary font-semibold rounded-lg transition-colors">Dashboard</a>
            <?php else: ?>
                <a href="<?= PahamFin_URL_AUTH ?>/login.php" class="inline-flex items-center justify-center w-[110px] h-[40px] border-primary border-2 text-primary dark:text-blue-400 font-semibold rounded hover:bg-[#3A519D] hover:text-white transition-colors">Masuk</a>
                <a href="<?= PahamFin_URL_AUTH ?>/register.php" class="inline-flex items-center justify-center w-[110px] h-[40px] bg-primary text-white border border-primary font-semibold rounded-lg transition-colors hover:opacity-90">Daftar</a>
            <?php endif; ?>
        </div>
        <button @click="menuOpen = !menuOpen" class="lg:hidden text-2xl text-dark dark:text-white">
            <i class="ph" :class="menuOpen ? 'ph-x' : 'ph-list'"></i>
        </button>
    </div>
    <!-- Mobile menu -->
    <div x-show="menuOpen" x-transition class="lg:hidden bg-white dark:bg-slate-900/80 shadow-lg border-t border-gray-100 dark:border-slate-700">
        <div class="px-6 py-4 flex flex-col gap-1">
            <a href="#fitur" class="py-2 text-sm font-medium">Fitur</a>
            <a href="#keunggulan" class="py-2 text-sm font-medium">Keunggulan</a>
            <a href="#cara-pakai" class="py-2 text-sm font-medium">Cara Pakai</a>
            <a href="#testimoni" class="py-2 text-sm font-medium">Testimoni</a>
            <a href="#harga" class="py-2 text-sm font-medium">Harga</a>
            <a href="#faq" class="py-2 text-sm font-medium">FAQ</a>
            <div class="pt-3 border-t border-gray-100 dark:border-slate-700">
                <button @click="toggleDark()" class="w-full flex items-center justify-center gap-2 py-2.5 rounded-lg text-sm font-medium text-gray-700 dark:text-slate-200 hover:bg-gray-100 dark:hover:bg-slate-800 transition-colors">
                    <i class="ph text-lg" :class="darkMode ? 'ph-sun' : 'ph-moon'"></i> <span x-text="darkMode ? 'Mode Terang' : 'Mode Gelap'"></span>
                </button>
                <?php if ($isLoggedIn): ?><a href="index.php" class="block text-center py-2.5 bg-primary text-white font-semibold rounded-lg">Dashboard</a>
                <?php else: ?>
                    <a href="<?= PahamFin_URL_AUTH ?>/login.php" class="block text-center py-2.5 border-2 border-primary text-primary dark:text-blue-400 font-semibold rounded-lg">Masuk</a>
                    <a href="<?= PahamFin_URL_AUTH ?>/register.php" class="mt-2 block text-center py-2.5 bg-primary text-white font-semibold rounded-lg">Daftar Gratis</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</header>

<!-- ===================== HERO ===================== -->
<section class="hero-bg relative overflow-hidden text-neutral">
    <div class="max-w-[1440px] mx-auto px-4 lg:px-10 xl:px-40 relative z-10 flex flex-col items-center min-h-[760px] pt-[110px] pb-[120px] text-center">
        <h1 class="font-display font-bold text-[30px] lg:text-[44px] leading-[1.35] max-w-[860px] text-dark dark:text-white">
            Catat Keuangan <span class="text-primary dark:text-blue-400">Otomatis</span> Langsung dari Chat Telegram 🚀
        </h1>
        <p class="mt-6 text-base lg:text-lg leading-[170%] max-w-[760px] text-gray-600 dark:text-slate-300 dark:text-slate-300">
            PahamFin membantu kamu mencatat pemasukan dan pengeluaran hanya dengan mengirim pesan seperti
            <b>"makan 50000"</b> atau <b>"gaji 5jt"</b>. Belanja tercatat, laporan keuangan rapi, tanpa aplikasi tambahan.
        </p>
        <div class="mt-8 flex flex-col sm:flex-row items-center justify-center gap-4">
            <?php if ($isLoggedIn): ?>
                <a href="index.php" class="inline-flex items-center justify-center h-11 px-6 bg-primary text-white border border-primary font-semibold rounded-lg">
                    Buka Dashboard <i class="ph ph-arrow-right ml-2"></i>
                </a>
                <!-- Tombol Bot Telegram -->
                <button onclick="openTelegramPicker()" class="inline-flex items-center justify-center h-11 px-6 bg-sky-500 hover:bg-sky-600 text-white font-semibold rounded-lg transition-colors gap-2">
                    <i class="ph ph-telegram-logo text-xl"></i> Buka Bot Telegram
                </button>
            <?php else: ?>
                <a href="<?= PahamFin_URL_AUTH ?>/register.php" class="inline-flex items-center justify-center h-11 px-6 bg-primary text-white border border-primary font-semibold rounded-lg transition-colors hover:opacity-90">
                    Mulai Sekarang Gratis <i class="ph ph-arrow-right ml-2"></i>
                </a>
                <!-- Tombol Bot Telegram → Login dulu -->
                <a href="<?= PahamFin_URL_AUTH ?>/login.php?next=tg"
                   class="inline-flex items-center justify-center h-11 px-6 bg-sky-500 hover:bg-sky-600 text-white font-semibold rounded-lg transition-colors gap-2">
                    <i class="ph ph-telegram-logo text-xl"></i> Coba via Telegram
                </a>
            <?php endif; ?>
        </div>

        <?php
        // Setelah login sukses, ?open=tg akan auto-redirect ke bot
        $openBot = $_GET['open'] ?? '';
        ?>
        <script>
        (function(){
            const open = <?= json_encode($openBot) ?>;
            if (open === 'tg') {
                openTelegramPicker();
            }
        })();
        </script>

        <!-- Modal Telegram Picker -->
        <div id="tg-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4" onclick="if(event.target===this) closeTelegramPicker()">
            <div class="absolute inset-0 bg-black/50 backdrop-blur-sm"></div>
            <div class="relative bg-white dark:bg-slate-900/80 rounded-2xl shadow-2xl w-full max-w-sm p-6 z-10">
                <div class="flex items-center gap-3 mb-5">
                    <div class="w-12 h-12 rounded-xl bg-sky-100 flex items-center justify-center">
                        <i class="ph ph-telegram-logo text-sky-500 text-2xl"></i>
                    </div>
                    <div>
                        <h3 class="font-bold text-gray-900 dark:text-white text-lg">Buka Bot Telegram</h3>
                        <p class="text-sm text-gray-500 dark:text-slate-400">Pilih bagaimana kamu ingin membukanya</p>
                    </div>
                </div>

                <div class="space-y-3">
                    <!-- Buka di Aplikasi Telegram -->
                    <a id="tg-app-link" href="<?= htmlspecialchars(PahamFin_tg_link()) ?>" 
                       class="flex items-center gap-4 p-4 rounded-xl border-2 border-sky-100 hover:border-sky-400 hover:bg-sky-50 transition-colors group">
                        <div class="w-10 h-10 rounded-full bg-sky-500 flex items-center justify-center shrink-0">
                            <i class="ph ph-device-mobile text-white text-lg"></i>
                        </div>
                        <div class="flex-1 text-left">
                            <p class="font-semibold text-gray-900 dark:text-white group-hover:text-sky-700">Buka di Aplikasi</p>
                            <p class="text-xs text-gray-500 dark:text-slate-400">Langsung buka Telegram App di HP kamu</p>
                        </div>
                        <i class="ph ph-arrow-right text-gray-400 group-hover:text-sky-500"></i>
                    </a>

                    <!-- Buka di Browser / Web -->
                    <a id="tg-web-link" href="<?= htmlspecialchars('https://web.telegram.org/k/#@' . PahamFin_TELEGRAM_BOT_USERNAME) ?>" 
                       target="_blank" rel="noopener"
                       class="flex items-center gap-4 p-4 rounded-xl border-2 border-gray-100 dark:border-slate-700 hover:border-primary hover:bg-blue-50 dark:bg-slate-800/80 transition-colors group">
                        <div class="w-10 h-10 rounded-full bg-primary flex items-center justify-center shrink-0">
                            <i class="ph ph-globe text-white text-lg"></i>
                        </div>
                        <div class="flex-1 text-left">
                            <p class="font-semibold text-gray-900 dark:text-white group-hover:text-primary dark:text-blue-400">Buka di Browser</p>
                            <p class="text-xs text-gray-500 dark:text-slate-400">Gunakan Telegram Web tanpa install app</p>
                        </div>
                        <i class="ph ph-arrow-right text-gray-400 group-hover:text-primary dark:text-blue-400"></i>
                    </a>
                </div>

                <p class="text-xs text-center text-gray-400 mt-4">
                    Bot: <span class="font-mono text-gray-600 dark:text-slate-300">@<?= htmlspecialchars(PahamFin_TELEGRAM_BOT_USERNAME) ?></span>
                </p>

                <button onclick="closeTelegramPicker()" class="absolute top-4 right-4 p-1.5 text-gray-400 hover:text-gray-700 dark:text-slate-300 hover:bg-gray-100 rounded-lg transition-colors">
                    <i class="ph ph-x text-lg"></i>
                </button>
            </div>
        </div>

        <!-- Demo frame -->
        <div class="relative mt-14 w-full max-w-[967px]">
            <div class="h-[420px] lg:h-[528px] rounded-[18px] bg-white/80 dark:bg-slate-900/80 backdrop-blur-sm shadow-[0_10px_35px_rgba(18,83,132,0.14)] overflow-hidden flex items-center justify-center">
                <div class="w-full h-full p-6 lg:p-10">
                    <div class="w-full h-full rounded-xl border border-white/80 dark:border-slate-700 bg-white/40 dark:bg-slate-800/80 backdrop-blur-sm p-4 lg:p-6">
                        <div class="flex items-center justify-between mb-6">
                            <div class="flex items-center gap-2">
                                <div class="w-3 h-3 rounded-full bg-red-400"></div>
                                <div class="w-3 h-3 rounded-full bg-yellow-400"></div>
                                <div class="w-3 h-3 rounded-full bg-green-400"></div>
                            </div>
                            <span class="text-xs text-gray-400">PahamFin Dashboard</span>
                        </div>
                        <div class="grid grid-cols-3 gap-3 mb-6">
                            <div class="bg-white/90 dark:bg-slate-900/60 backdrop-blur-sm rounded-lg p-3 border border-white/80 dark:border-slate-700/50">
                                <p class="text-[10px] text-gray-400">Saldo</p>
                                <p class="text-sm font-bold text-gray-900 dark:text-white">Rp 1.250.000</p>
                            </div>
                            <div class="bg-white/90 dark:bg-slate-900/60 backdrop-blur-sm rounded-lg p-3 border border-white/80 dark:border-slate-700/50">
                                <p class="text-[10px] text-gray-400">Pemasukan</p>
                                <p class="text-sm font-bold text-green-600 dark:text-green-400">Rp 2.000.000</p>
                            </div>
                            <div class="bg-white/90 dark:bg-slate-900/60 backdrop-blur-sm rounded-lg p-3 border border-white/80 dark:border-slate-700/50">
                                <p class="text-[10px] text-gray-400">Pengeluaran</p>
                                <p class="text-sm font-bold text-red-600 dark:text-red-400">Rp 750.000</p>
                            </div>
                        </div>
                        <div class="flex flex-col gap-2">
                            <div class="flex items-center justify-between bg-white/90 dark:bg-slate-900/60 backdrop-blur-sm rounded-lg px-3 py-2 border border-white/80 dark:border-slate-700/50">
                                <span class="text-xs text-gray-600 dark:text-slate-300">Makan Siang</span>
                                <span class="text-xs font-bold text-red-600 dark:text-red-400">- Rp 50.000</span>
                            </div>
                            <div class="flex items-center justify-between bg-white/90 dark:bg-slate-900/60 backdrop-blur-sm rounded-lg px-3 py-2 border border-white/80 dark:border-slate-700/50">
                                <span class="text-xs text-gray-600 dark:text-slate-300">Gaji Bulanan</span>
                                <span class="text-xs font-bold text-green-600 dark:text-green-400">+ Rp 2.000.000</span>
                            </div>
                            <div class="flex items-center justify-between bg-white/90 dark:bg-slate-900/60 backdrop-blur-sm rounded-lg px-3 py-2 border border-white/80 dark:border-slate-700/50">
                                <span class="text-xs text-gray-600 dark:text-slate-300">Bensin / Ojek Online</span>
                                <span class="text-xs font-bold text-red-600 dark:text-red-400">- Rp 30.000</span>
                            </div>
                        </div>
                        <div class="mt-4 bg-white/90 dark:bg-slate-900/60 backdrop-blur-sm rounded-lg p-3 text-center border border-white/80 dark:border-slate-700/50">
                            <p class="text-[10px] text-gray-400 mb-1">Pengeluaran per Kategori</p>
                            <div class="w-full h-2 bg-gray-100 rounded-full overflow-hidden">
                                <div class="h-full w-2/3 bg-red-400 dark:bg-red-600 rounded-full"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="h-[120px] lg:h-[140px] w-full absolute bottom-0 left-0 bg-gradient-to-b from-transparent to-white/50 dark:to-slate-900/50 backdrop-blur-sm" style="border-top-left-radius: 50% 100%; border-top-right-radius: 50% 100%;"></div>
</section>

<!-- ===================== FITUR ===================== -->
<section id="fitur" class="py-16 lg:py-24">
    <div class="max-w-[1440px] mx-auto px-4 lg:px-10 xl:px-40 text-center">
        <h2 class="font-display text-[26px] lg:text-[36px] font-bold text-dark dark:text-white">Kenapa Memakai PahamFin?</h2>
        <p class="mt-4 text-gray-600 dark:text-slate-300 max-w-[640px] mx-auto leading-[170%]">Dirancang agar pencatatan keuangan jadi lebih praktis, cepat, dan otomatis.</p>

        <div class="mt-12 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            <div class="rounded-2xl p-6 text-left bg-gradient-to-b from-white/90 to-blue-100/80 dark:from-slate-800/80 dark:to-slate-700/60 shadow-sm border border-blue-100/50 dark:border-slate-700/50">
                <div class="w-12 h-12 rounded-xl bg-primary text-white flex items-center justify-center mb-4"><i class="ph ph-telegram-logo text-2xl"></i></div>
                <h3 class="font-semibold text-lg text-dark dark:text-white">Catat via Chat</h3>
                <p class="mt-2 text-sm text-gray-600 dark:text-slate-300 leading-relaxed">Kirim pesan ke bot Telegram. Transaksi langsung tercatat otomatis.</p>
            </div>
            <div class="rounded-2xl p-6 text-left bg-gradient-to-b from-white/90 to-green-100/80 dark:from-slate-800/80 dark:to-slate-700/60 shadow-sm border border-green-100/50 dark:border-slate-700/50">
                <div class="w-12 h-12 rounded-xl bg-green-600 text-white flex items-center justify-center mb-4"><i class="ph ph-magic-wand text-2xl"></i></div>
                <h3 class="font-semibold text-lg text-dark dark:text-white">Keyword Otomatis</h3>
                <p class="mt-2 text-sm text-gray-600 dark:text-slate-300 leading-relaxed">Atur keyword tiap kategori. "makan 50000" langsung masuk kategori Makanan.</p>
            </div>
            <div class="rounded-2xl p-6 text-left bg-gradient-to-b from-white/90 to-cyan-100/80 dark:from-slate-800/80 dark:to-slate-700/60 shadow-sm border border-cyan-100/50 dark:border-slate-700/50">
                <div class="w-12 h-12 rounded-xl bg-cyan-600 text-white flex items-center justify-center mb-4"><i class="ph ph-chart-line-up text-2xl"></i></div>
                <h3 class="font-semibold text-lg text-dark dark:text-white">Laporan & Dashboard</h3>
                <p class="mt-2 text-sm text-gray-600 dark:text-slate-300 leading-relaxed">Pantau saldo, pemasukan, pengeluaran, dan anggaran per kategori dalam satu dashboard.</p>
            </div>
            <div class="rounded-2xl p-6 text-left bg-gradient-to-b from-white/90 to-rose-100/80 dark:from-slate-800/80 dark:to-slate-700/60 shadow-sm border border-rose-100/50 dark:border-slate-700/50">
                <div class="w-12 h-12 rounded-xl bg-rose-600 text-white flex items-center justify-center mb-4"><i class="ph ph-shield-check text-2xl"></i></div>
                <h3 class="font-semibold text-lg text-dark dark:text-white">Data Terpusat & Aman</h3>
                <p class="mt-2 text-sm text-gray-600 dark:text-slate-300 leading-relaxed">Semua catatan tersimpan terpusat untuk akun kamu dan hanya bisa diakses oleh kamu.</p>
            </div>
        </div>
    </div>
</section>

<!-- ===================== KEUNGGULAN ===================== -->
<section id="keunggulan" class="py-16 lg:py-24 bg-gradient-to-b from-blue-50/80 to-indigo-50/60 dark:from-slate-900 dark:to-slate-800/60">
    <div class="max-w-[1440px] mx-auto px-4 lg:px-10 xl:px-40">
        <div class="text-center mb-12">
            <h2 class="font-display text-[26px] lg:text-[36px] font-bold text-dark dark:text-white">Keunggulan PahamFin</h2>
            <p class="mt-4 text-gray-600 dark:text-slate-300 max-w-[640px] mx-auto leading-[170%]">Semua yang kamu butuhkan untuk mengendalikan keuangan, otomatis.</p>
        </div>
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                <div class="bg-white/80 dark:bg-slate-800/60 backdrop-blur-sm rounded-2xl p-6 shadow-sm border border-white/80 dark:border-slate-700/50">
                    <div class="w-10 h-10 rounded-lg bg-blue-100 dark:bg-blue-900/50 text-primary dark:text-blue-400 flex items-center justify-center mb-3"><i class="ph ph-chat-circle-text text-xl"></i></div>
                    <h3 class="font-semibold text-dark dark:text-white">Rekap AI via Chat</h3>
                    <p class="mt-2 text-sm text-gray-600 dark:text-slate-300 leading-relaxed">Cukup tulis nominal beserta keterangan, bot yang menyelesaikan pencatatannya.</p>
                </div>
                <div class="bg-white/80 dark:bg-slate-800/60 backdrop-blur-sm rounded-2xl p-6 shadow-sm border border-white/80 dark:border-slate-700/50">
                    <div class="w-10 h-10 rounded-lg bg-green-100 dark:bg-green-900/50 text-green-700 dark:text-green-300 flex items-center justify-center mb-3"><i class="ph ph-currency-circle-dollar text-xl"></i></div>
                    <h3 class="font-semibold text-dark dark:text-white">Dukungan rb / ribu / k</h3>
                    <p class="mt-2 text-sm text-gray-600 dark:text-slate-300 leading-relaxed">Tulis "100rb" atau "50k" saja, PahamFin mengubahnya jadi nominal yang benar.</p>
                </div>
                <div class="bg-white/80 dark:bg-slate-800/60 backdrop-blur-sm rounded-2xl p-6 shadow-sm border border-white/80 dark:border-slate-700/50">
                    <div class="w-10 h-10 rounded-lg bg-cyan-100 text-cyan-700 flex items-center justify-center mb-3"><i class="ph ph-graph text-xl"></i></div>
                    <h3 class="font-semibold text-dark dark:text-white">Grafik Anggaran</h3>
                    <p class="mt-2 text-sm text-gray-600 dark:text-slate-300 leading-relaxed">Lihat pengeluaran per kategori dengan progres dan tips pengelolaan.</p>
                </div>
                <div class="bg-white/80 dark:bg-slate-800/60 backdrop-blur-sm rounded-2xl p-6 shadow-sm border border-white/80 dark:border-slate-700/50">
                    <div class="w-10 h-10 rounded-lg bg-amber-100 dark:bg-amber-900/50 text-amber-700 dark:text-amber-300 flex items-center justify-center mb-3"><i class="ph ph-bell-ringing text-xl"></i></div>
                    <h3 class="font-semibold text-dark dark:text-white">Bot Telegram</h3>
                    <p class="mt-2 text-sm text-gray-600 dark:text-slate-300 leading-relaxed">Gunakan lewat Telegram, langsung tersambung ke akun kamu di dashboard.</p>
                </div>
            </div>
            <div class="bg-white/80 dark:bg-slate-800/60 backdrop-blur-sm rounded-2xl p-8 flex flex-col justify-center shadow-[0_10px_35px_rgba(18,83,132,0.08)]">
                <h3 class="font-display text-2xl font-bold text-dark dark:text-white">Solusi untuk Manajemen Keuanganmu</h3>
                <p class="mt-3 text-gray-600 dark:text-slate-300 leading-relaxed">Tidak perlu aplikasi tambahan, tidak perlu input manual satu per satu. Mulai dari mencatat uang jajan hingga mengelola anggaran bulanan keluarga atau usaha kecil.</p>
                <div class="mt-6 grid grid-cols-3 gap-4 text-center">
                    <div>
                        <p class="font-display text-2xl lg:text-3xl font-bold text-primary dark:text-blue-400">82+</p>
                        <p class="text-xs text-gray-500 dark:text-slate-400 mt-1">Catatan Keuangan</p>
                    </div>
                    <div>
                        <p class="font-display text-2xl lg:text-3xl font-bold text-primary dark:text-blue-400">100%</p>
                        <p class="text-xs text-gray-500 dark:text-slate-400 mt-1">Otomatis</p>
                    </div>
                    <div>
                        <p class="font-display text-2xl lg:text-3xl font-bold text-primary dark:text-blue-400">4.5/5</p>
                        <p class="text-xs text-gray-500 dark:text-slate-400 mt-1">Kepuasan</p>
                    </div>
                </div>
                <?php if (!$isLoggedIn): ?>
                <a href="<?= PahamFin_URL_AUTH ?>/register.php" class="mt-8 inline-flex items-center justify-center h-11 bg-primary text-white border border-primary font-semibold rounded-lg transition-colors hover:opacity-90">
                    Coba PahamFin Gratis
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<!-- ===================== CARA PAKAI ===================== -->
<section id="cara-pakai" class="py-16 lg:py-24 bg-white/60 dark:bg-slate-900/80 backdrop-blur-sm">
    <div class="max-w-[1440px] mx-auto px-4 lg:px-10 xl:px-40">
        <div class="text-center mb-12">
            <h2 class="font-display text-[26px] lg:text-[36px] font-bold text-dark dark:text-white">Cara Penggunaan PahamFin</h2>
            <p class="mt-4 text-gray-600 dark:text-slate-300 max-w-[640px] mx-auto leading-[170%]">Empat langkah mudah untuk mulai mencatat keuangan otomatis.</p>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            <div class="text-center p-6 rounded-2xl bg-gradient-to-b from-white/90 to-blue-100/80 dark:from-slate-800/80 dark:to-slate-700/60 shadow-sm border border-blue-100/50 dark:border-slate-700/50">
                <div class="w-12 h-12 mx-auto rounded-full bg-primary text-white flex items-center justify-center font-display font-bold text-xl mb-4">1</div>
                <h3 class="font-semibold text-dark dark:text-white">Buat Akun</h3>
                <p class="mt-2 text-sm text-gray-600 dark:text-slate-300 leading-relaxed">Daftar gratis dengan email kamu.</p>
            </div>
            <div class="text-center p-6 rounded-2xl bg-gradient-to-b from-white/90 to-green-100/80 dark:from-slate-800/80 dark:to-slate-700/60 shadow-sm border border-green-100/50 dark:border-slate-700/50">
                <div class="w-12 h-12 mx-auto rounded-full bg-green-600 text-white flex items-center justify-center font-display font-bold text-xl mb-4">2</div>
                <h3 class="font-semibold text-dark dark:text-white">Hubungkan Bot</h3>
                <p class="mt-2 text-sm text-gray-600 dark:text-slate-300 leading-relaxed">Hubungkan ID Telegram kamu di menu Pengaturan.</p>
            </div>
            <div class="text-center p-6 rounded-2xl bg-gradient-to-b from-white/90 to-cyan-100/80 dark:from-slate-800/80 dark:to-slate-700/60 shadow-sm border border-cyan-100/50 dark:border-slate-700/50">
                <div class="w-12 h-12 mx-auto rounded-full bg-cyan-600 text-white flex items-center justify-center font-display font-bold text-xl mb-4">3</div>
                <h3 class="font-semibold text-dark dark:text-white">Kirim Catatan</h3>
                <p class="mt-2 text-sm text-gray-600 dark:text-slate-300 leading-relaxed">Tulis contoh "makan 50000" ke bot, transaksi tercatat otomatis.</p>
            </div>
            <div class="text-center p-6 rounded-2xl bg-gradient-to-b from-white/90 to-orange-100/80 dark:from-slate-800/80 dark:to-slate-700/60 shadow-sm border border-orange-100/50 dark:border-slate-700/50">
                <div class="w-12 h-12 mx-auto rounded-full bg-orange-500 text-white flex items-center justify-center font-display font-bold text-xl mb-4">4</div>
                <h3 class="font-semibold text-dark dark:text-white">Analisa Dashboard</h3>
                <p class="mt-2 text-sm text-gray-600 dark:text-slate-300 leading-relaxed">Pantau laporan dan anggaran kamu secara realtime di dashboard.</p>
            </div>
        </div>
    </div>
</section>

<!-- ===================== TESTIMONI ===================== -->
<section id="testimoni" class="py-16 lg:py-24 bg-gradient-to-b from-emerald-50/60 to-teal-50/40 dark:from-slate-900 dark:to-slate-800/60">
    <div class="max-w-[1440px] mx-auto px-4 lg:px-10 xl:px-40">
        <div class="text-center mb-12">
            <h2 class="font-display text-[26px] lg:text-[36px] font-bold text-dark dark:text-white">Kata Mereka Tentang PahamFin</h2>
            <p class="mt-4 text-gray-600 dark:text-slate-300 max-w-[640px] mx-auto leading-[170%]">Pengguna sudah merasakan kemudahan mencatat keuangan secara otomatis.</p>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="bg-white/80 dark:bg-slate-800/60 backdrop-blur-sm rounded-2xl p-6 shadow-sm border border-white/80 dark:border-slate-700/50">
                <div class="flex text-amber-400 mb-3">
                    <i class="ph ph-star-fill"></i><i class="ph ph-star-fill"></i><i class="ph ph-star-fill"></i><i class="ph ph-star-fill"></i><i class="ph ph-star-fill"></i>
                </div>
                <p class="text-sm text-gray-600 dark:text-slate-300 leading-relaxed">"Gak perlu buka aplikasi lagi. Kirim chat doang, uang jajan udah kekategori otomatis. Praktis banget!"</p>
                <div class="mt-4 flex items-center gap-3">
                    <div class="w-9 h-9 rounded-full bg-primary text-white flex items-center justify-center text-sm font-bold">R</div>
                    <div>
                        <p class="text-sm font-semibold text-dark dark:text-white">Rina</p>
                        <p class="text-xs text-gray-400">Mahasiswa</p>
                    </div>
                </div>
            </div>
            <div class="bg-white/80 dark:bg-slate-800/60 backdrop-blur-sm rounded-2xl p-6 shadow-sm border border-white/80 dark:border-slate-700/50">
                <div class="flex text-amber-400 mb-3">
                    <i class="ph ph-star-fill"></i><i class="ph ph-star-fill"></i><i class="ph ph-star-fill"></i><i class="ph ph-star-fill"></i><i class="ph ph-star-fill"></i>
                </div>
                <p class="text-sm text-gray-600 dark:text-slate-300 leading-relaxed">"Sekarang pengeluaran warung dan ojek langsung nyatet sendiri. Dashboard-nya jelas, tau duit ke mana aja."</p>
                <div class="mt-4 flex items-center gap-3">
                    <div class="w-9 h-9 rounded-full bg-green-600 text-white flex items-center justify-center text-sm font-bold">A</div>
                    <div>
                        <p class="text-sm font-semibold text-dark dark:text-white">Andi</p>
                        <p class="text-xs text-gray-400">Karyawan Swasta</p>
                    </div>
                </div>
            </div>
            <div class="bg-white/80 dark:bg-slate-800/60 backdrop-blur-sm rounded-2xl p-6 shadow-sm border border-white/80 dark:border-slate-700/50">
                <div class="flex text-amber-400 mb-3">
                    <i class="ph ph-star-fill"></i><i class="ph ph-star-fill"></i><i class="ph ph-star-fill"></i><i class="ph ph-star-fill"></i><i class="ph ph-star-fill"></i>
                </div>
                <p class="text-sm text-gray-600 dark:text-slate-300 leading-relaxed">"Saya pakai buat catat kas usaha kecil. Fitur keyword-nya ngebantu banget, laporan bulanan jadi rapi."</p>
                <div class="mt-4 flex items-center gap-3">
                    <div class="w-9 h-9 rounded-full bg-cyan-600 text-white flex items-center justify-center text-sm font-bold">S</div>
                    <div>
                        <p class="text-sm font-semibold text-dark dark:text-white">Sari</p>
                        <p class="text-xs text-gray-400">Pemilik UMKM</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ===================== HARGA ===================== -->
<section id="harga" class="py-16 lg:py-24 bg-white/60 dark:bg-slate-900/80 backdrop-blur-sm">
    <div class="max-w-[1440px] mx-auto px-4 lg:px-10 xl:px-40">
        <div class="text-center mb-12">
            <h2 class="font-display text-[26px] lg:text-[36px] font-bold text-dark dark:text-white">Harga</h2>
            <p class="mt-4 text-gray-600 dark:text-slate-300 max-w-[640px] mx-auto leading-[170%]">Mulai gratis dan tetap gratis untuk penggunaan pribadi. Panel admin tanpa biaya tersembunyi.</p>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="rounded-2xl p-8 border border-blue-200 dark:border-slate-700 text-center bg-gradient-to-b from-white/90 to-blue-50/70 dark:from-slate-800/80 dark:to-slate-700/60 border border-blue-100 dark:border-slate-700">
                <h3 class="font-display text-lg font-bold text-dark dark:text-white">Personal</h3>
                <p class="text-xs text-gray-500 dark:text-slate-400 mt-1">Untuk keperluan pribadi</p>
                <p class="mt-5"><span class="text-3xl font-bold text-dark dark:text-white">Gratis</span></p>
                <ul class="mt-6 space-y-3 text-sm text-gray-600 dark:text-slate-300 text-left">
                    <li class="flex items-center gap-2"><i class="ph ph-check text-green-600 dark:text-green-400"></i> Catatan otomatis via chat</li>
                    <li class="flex items-center gap-2"><i class="ph ph-check text-green-600 dark:text-green-400"></i> Dashboard & riwayat transaksi</li>
                    <li class="flex items-center gap-2"><i class="ph ph-check text-green-600 dark:text-green-400"></i> 3 kategori default</li>
                    <li class="flex items-center gap-2"><i class="ph ph-check text-green-600 dark:text-green-400"></i> Bot Telegram</li>
                </ul>
                <?php if (!$isLoggedIn): ?>
                <a href="<?= PahamFin_URL_AUTH ?>/register.php" class="mt-8 inline-flex w-full justify-center h-11 items-center bg-primary text-white border border-primary font-semibold rounded-lg transition-colors hover:opacity-90">Mulai Gratis</a>
                <?php else: ?>
                <a href="index.php" class="mt-8 inline-flex w-full justify-center h-11 items-center bg-primary text-white border border-primary font-semibold rounded-lg">Buka Dashboard</a>
                <?php endif; ?>
            </div>
            <div class="rounded-2xl p-8 border-2 border-primary text-center scale-[1.02] shadow-[0_10px_35px_rgba(10,88,165,0.2)] bg-gradient-to-b from-blue-50/60 to-blue-100/80 dark:from-slate-800/80 dark:to-blue-900/30">
                <span class="inline-block bg-primary text-white text-xs font-semibold px-3 py-1 rounded-full mb-3">Populer</span>
                <h3 class="font-display text-lg font-bold text-dark dark:text-white">Keluarga</h3>
                <p class="text-xs text-gray-500 dark:text-slate-400 mt-1">Beberapa anggota satu rumah</p>
                <p class="mt-5"><span class="text-3xl font-bold text-dark dark:text-white">Gratis</span></p>
                <ul class="mt-6 space-y-3 text-sm text-gray-600 dark:text-slate-300 text-left">
                    <li class="flex items-center gap-2"><i class="ph ph-check text-green-600 dark:text-green-400"></i> Semua fitur Personal</li>
                    <li class="flex items-center gap-2"><i class="ph ph-check text-green-600 dark:text-green-400"></i> Kategori tak terbatas</li>
                    <li class="flex items-center gap-2"><i class="ph ph-check text-green-600 dark:text-green-400"></i> Anggaran per kategori</li>
                    <li class="flex items-center gap-2"><i class="ph ph-check text-green-600 dark:text-green-400"></i> Tips pengelolaan keuangan</li>
                </ul>
                <?php if (!$isLoggedIn): ?>
                <a href="<?= PahamFin_URL_AUTH ?>/register.php" class="mt-8 inline-flex w-full justify-center h-11 items-center bg-primary text-white border border-primary font-semibold rounded-lg">Daftar Sekarang</a>
                <?php else: ?>
                <a href="index.php" class="mt-8 inline-flex w-full justify-center h-11 items-center bg-primary text-white border border-primary font-semibold rounded-lg">Lanjut ke Dashboard</a>
                <?php endif; ?>
            </div>
            <div class="rounded-2xl p-8 border border-orange-200 dark:border-slate-700 text-center bg-gradient-to-b from-white/90 to-orange-50/70 dark:from-slate-800/80 dark:to-slate-700/60 border border-orange-100 dark:border-slate-700">
                <h3 class="font-display text-lg font-bold text-dark dark:text-white">UMKM</h3>
                <p class="text-xs text-gray-500 dark:text-slate-400 mt-1">Untuk usaha kecil & menengah</p>
                <p class="mt-5"><span class="text-3xl font-bold text-dark dark:text-white">Gratis</span></p>
                <ul class="mt-6 space-y-3 text-sm text-gray-600 dark:text-slate-300 text-left">
                    <li class="flex items-center gap-2"><i class="ph ph-check text-green-600 dark:text-green-400"></i> Semua fitur Keluarga</li>
                    <li class="flex items-center gap-2"><i class="ph ph-check text-green-600 dark:text-green-400"></i> Pencatatan kas usaha</li>
                    <li class="flex items-center gap-2"><i class="ph ph-check text-green-600 dark:text-green-400"></i> Laporan per kategori</li>
                    <li class="flex items-center gap-2"><i class="ph ph-check text-green-600 dark:text-green-400"></i> Kemudahan ekspor data</li>
                </ul>
                <?php if (!$isLoggedIn): ?>
                <a href="<?= PahamFin_URL_AUTH ?>/register.php" class="mt-8 inline-flex w-full justify-center h-11 items-center bg-primary text-white border border-primary font-semibold rounded-lg">Daftar Sekarang</a>
                <?php else: ?>
                <a href="index.php" class="mt-8 inline-flex w-full justify-center h-11 items-center bg-primary text-white border border-primary font-semibold rounded-lg">Buka Dashboard</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<!-- ===================== FAQ ===================== -->
<section id="faq" class="py-16 lg:py-24 bg-gradient-to-b from-sky-50/70 to-blue-50/50 dark:from-slate-900 dark:to-slate-800/60">
    <div class="max-w-[1440px] mx-auto px-4 lg:px-10 xl:px-40">
        <div class="text-center mb-12">
            <h2 class="font-display text-[26px] lg:text-[36px] font-bold text-dark dark:text-white">Frequently Asked Question</h2>
            <p class="mt-4 text-gray-600 dark:text-slate-300">Hal-hal yang paling sering ditanyakan tentang PahamFin.</p>
        </div>
        <div class="max-w-[760px] mx-auto space-y-4">
            <?php
            $faqs = [
                ['q' => 'Bagaimana cara mulai menggunakan PahamFin?', 'a' => 'Cukup daftar akun gratis, lalu hubungkan ID Telegram kamu di menu Pengaturan. Setelah itu kirim pesan format "keterangan nominal" ke bot Telegram.'],
                ['q' => 'Apa saja format pesan yang didukung bot?', 'a' => 'Kamu bisa menulis "makan 50000", "bensin 100rb", "kopi 25k", atau "gaji 5jt". PahamFin otomatis mengubah rb/k/jt menjadi nominal yang benar.'],
                ['q' => 'Apakah data keuangan saya aman?', 'a' => 'Ya. Data hanya bisa diakses oleh akun kamu sendiri dan setiap transaksi tersimpan terpusat di database aplikasi, hanya untuk keperluan pencatatan kamu.'],
                ['q' => 'Apakah PahamFin benar-benar gratis?', 'a' => 'Ya, untuk penggunaan pribadi, keluarga, hingga UMKM, seluruh fitur tersedia gratis saat ini.'],
                ['q' => 'Bagaimana cara menghubungkan Telegram?', 'a' => 'Setelah mendaftar, buka menu Pengaturan dan masukkan ID Telegram kamu. Lalu kirim /start ke bot Telegram untuk menghubungkan akun.'],
            ];
            ?>
            <?php foreach ($faqs as $i => $f): ?>
            <div class="bg-white/80 dark:bg-slate-800/60 backdrop-blur-sm rounded-xl border border-white/80 dark:border-slate-700/50 shadow-sm" x-data="{ open: false }">
                <button @click="open = !open" class="w-full flex items-center justify-between p-5 text-left">
                    <span class="font-semibold text-dark dark:text-white"><?= htmlspecialchars($f['q']) ?></span>
                    <i class="ph transition-transform" :class="open ? 'ph-minus' : 'ph-plus'"></i>
                </button>
                <div x-show="open" x-transition class="px-5 pb-5 text-sm text-gray-600 dark:text-slate-300 leading-relaxed"><?= htmlspecialchars($f['a']) ?></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ===================== FOOTER ===================== -->
<footer class="bg-[#0A1E33] text-gray-300">
    <div class="max-w-[1440px] mx-auto px-4 lg:px-10 xl:px-40 py-14">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-10">
            <div>
                <a href="index.php" class="flex items-center gap-2 mb-4">
                    <div class="w-12 h-12 md:w-14 md:h-14 flex items-center justify-center"><img src="<?= PahamFin_URL_LOGO ?>" alt="PahamFin" class="w-12 h-12 md:w-14 md:h-14 object-contain"></div>
                    <span class="font-display text-xl font-bold text-white">Paham<span class="text-sky-400">Fin</span></span>
                </a>
                <p class="text-sm leading-relaxed text-gray-400">Tool pencatatan keuangan otomatis berbasis chat. Rapikan keuanganmu tanpa ribet.</p>
            </div>
            <div>
                <h3 class="font-semibold text-white mb-4">Fitur</h3>
                <ul class="space-y-2 text-sm">
                    <li><a href="#fitur" class="hover:text-white">Catatan via Chat</a></li>
                    <li><a href="#fitur" class="hover:text-white">Keyword Otomatis</a></li>
                    <li><a href="#keunggulan" class="hover:text-white">Laporan & Dashboard</a></li>
                    <li><a href="#keunggulan" class="hover:text-white">Anggaran</a></li>
                </ul>
            </div>
            <div>
                <h3 class="font-semibold text-white mb-4">Bantuan</h3>
                <ul class="space-y-2 text-sm">
                    <li><a href="#cara-pakai" class="hover:text-white">Cara Pakai</a></li>
                    <li><a href="#faq" class="hover:text-white">FAQ</a></li>
                    <?php if (!$isLoggedIn): ?>
                        <li><a href="<?= PahamFin_URL_AUTH ?>/register.php" class="hover:text-white">Daftar</a></li>
                        <li><a href="<?= PahamFin_URL_AUTH ?>/login.php" class="hover:text-white">Masuk</a></li>
                    <?php endif; ?>
                </ul>
            </div>
            <div>
                <h3 class="font-semibold text-white mb-4">Kontak</h3>
                <ul class="space-y-2 text-sm text-gray-400">
                    <li class="flex items-center gap-2"><i class="ph ph-envelope"></i> dukungan@PahamFin.local</li>
                    <li class="flex items-center gap-2"><i class="ph ph-telegram-logo"></i> @Fin890Bot</li>
                </ul>
            </div>
        </div>
        <div class="mt-10 pt-6 border-t border-gray-700 text-center text-xs text-gray-500 dark:text-slate-400">
            &copy; <?= date('Y') ?> PahamFin. Dibuat untuk kemudahan pencatatan keuangan.
        </div>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
<script>
    // Navbar scroll effect
    const navbar = document.getElementById('navbar');
    window.addEventListener('scroll', () => {
        if (window.scrollY > 20) {
            navbar.classList.add('bg-white dark:bg-slate-900/80', 'shadow-md');
        } else {
            navbar.classList.remove('bg-white dark:bg-slate-900/80', 'shadow-md');
        }
    });

    // ── Telegram Picker Modal ──────────────────────────────────────────────────
    function openTelegramPicker() {
        const modal = document.getElementById('tg-modal');
        modal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }

    function closeTelegramPicker() {
        const modal = document.getElementById('tg-modal');
        modal.classList.add('hidden');
        document.body.style.overflow = '';
    }

    // Tutup modal dengan tombol Escape
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') closeTelegramPicker();
    });
</script>
</body>
</html>






