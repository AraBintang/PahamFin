        <!-- Sidebar -->
        <aside :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
               class="fixed inset-y-0 left-0 z-30 w-72 sidebar-grad text-white transition-all duration-300 lg:static lg:translate-x-0 flex flex-col shadow-2xl shadow-blue-900/30"
               :style="collapsed ? 'width: 80px' : 'width: 288px'"
               style="transition: width 0.3s ease, transform 0.3s ease;">

            <!-- Logo -->
            <div class="flex items-center justify-between p-4 border-b border-white/10 shrink-0" :class="collapsed ? 'lg:justify-center lg:p-3' : ''">
                <div class="flex items-center gap-3" :class="collapsed ? 'lg:hidden' : ''">
                    <div class="w-12 h-12 flex items-center justify-center overflow-hidden drop-shadow-sm">
                        <img src="<?= PahamFin_URL_LOGO ?>" alt="PahamFin" class="w-full h-full object-contain scale-110">
                    </div>
                    <div>
                        <h1 class="font-display font-extrabold text-xl tracking-tight leading-none">Paham<span class="text-amber-300">Fin</span></h1>
                        <p class="text-[10px] text-blue-200/70 font-medium tracking-wider mt-0.5">FINANCE MANAGER</p>
                    </div>
                </div>
                <div class="flex items-center gap-1">
                    <button @click="collapsed = !collapsed" class="hidden lg:flex w-9 h-9 items-center justify-center rounded-xl text-white/70 hover:bg-white/15 hover:text-white transition-colors" title="Sembunyikan / tampilkan menu">
                        <i class="ph text-xl" :class="collapsed ? 'ph-sidebar-simple' : 'ph-sidebar'"></i>
                    </button>
                    <button @click="sidebarOpen = false" class="lg:hidden text-white/70 hover:text-white p-2">
                        <i class="ph ph-x text-2xl"></i>
                    </button>
                </div>
            </div>

            <!-- Nav -->
            <nav class="flex-1 p-3 space-y-0.5 overflow-y-auto no-scrollbar" :class="collapsed ? 'lg:p-2' : ''">
                <?php if ($isAdmin): ?>
                <p x-show="!collapsed" class="px-3 pt-2 pb-1.5 text-[10px] font-bold uppercase tracking-widest text-amber-300/80">Admin Panel</p>
                <?php
                $admin_items = [
                    ['href' => PahamFin_URL_PAGES . '/admin/index.php',       'page' => 'index',        'icon' => 'ph-gauge',             'label' => 'Dashboard Admin'],
                    ['href' => PahamFin_URL_PAGES . '/admin/users.php',       'page' => 'users',        'icon' => 'ph-users-three',       'label' => 'Pengguna'],
                    ['href' => PahamFin_URL_PAGES . '/admin/settings.php',    'page' => 'settings',     'icon' => 'ph-shield-check',      'label' => 'Pengaturan Admin'],
                ];
                foreach ($admin_items as $ai):
                    $aiActive = ($adminPage && $current_page === $ai['page']);
                    $aiActiveClass = $aiActive ? 'bg-white/20 text-white shadow' : 'text-blue-100/80 hover:bg-white/10 hover:text-white';
                ?>
                <a href="<?= $ai['href'] ?>" class="flex items-center gap-3 px-3 py-2.5 rounded-xl font-medium transition-all <?= $aiActiveClass ?>" :class="collapsed ? 'lg:justify-center lg:px-0' : ''" title="<?= $ai['label'] ?>">
                    <i class="ph <?= $ai['icon'] ?> text-xl shrink-0 <?= $aiActive ? 'text-amber-300' : '' ?>"></i>
                    <span x-show="!collapsed" class="truncate text-sm"><?= $ai['label'] ?></span>
                    <?php if ($aiActive): ?><i class="ph ph-caret-right ml-auto text-amber-300/70 text-sm" x-show="!collapsed"></i><?php endif; ?>
                </a>
                <?php endforeach; ?>
                <div class="my-2 border-t border-white/10" x-show="!collapsed"></div>
                <?php endif; ?>

                <!-- Group: Keuangan -->
                <p x-show="!collapsed" class="px-3 pt-2 pb-1.5 text-[10px] font-bold uppercase tracking-widest text-blue-200/50">Keuangan</p>
                <?php
                $group_keuangan = [
                    'index'        => ['icon' => 'ph-squares-four',         'label' => 'Dashboard'],
                    'transactions' => ['icon' => 'ph-list-dashes',           'label' => 'Transaksi'],
                    'budgets'      => ['icon' => 'ph-chart-pie-slice',        'label' => 'Anggaran'],
                    'savings'      => ['icon' => 'ph-piggy-bank',             'label' => 'Tabungan'],
                    'wallets'      => ['icon' => 'ph-wallet',                 'label' => 'Dompet'],
                ];
                foreach ($group_keuangan as $page => $data):
                    $isActive = (!$adminPage && $current_page === $page);
                    $activeClass = $isActive ? 'bg-white/20 text-white shadow' : 'text-blue-100/80 hover:bg-white/10 hover:text-white';
                ?>
                <a href="<?= PahamFin_URL_PAGES ?>/<?= $page ?>.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl font-medium transition-all <?= $activeClass ?>" :class="collapsed ? 'lg:justify-center lg:px-0' : ''" title="<?= $data['label'] ?>">
                    <i class="ph <?= $data['icon'] ?> text-xl shrink-0 <?= $isActive ? 'text-amber-300' : '' ?>"></i>
                    <span x-show="!collapsed" class="truncate text-sm"><?= $data['label'] ?></span>
                    <?php if ($isActive): ?><i class="ph ph-caret-right ml-auto text-amber-300/70 text-sm" x-show="!collapsed"></i><?php endif; ?>
                </a>
                <?php endforeach; ?>

                <!-- Group: Manajemen -->
                <p x-show="!collapsed" class="px-3 pt-3 pb-1.5 text-[10px] font-bold uppercase tracking-widest text-blue-200/50">Manajemen</p>
                <?php
                $group_manajemen = [
                    'categories' => ['icon' => 'ph-tag',        'label' => 'Kategori & Bot'],
                    'debts'      => ['icon' => 'ph-handshake',  'label' => 'Hutang & Piutang'],
                    'reminders'  => ['icon' => 'ph-bell-ringing','label' => 'Pengingat'],
                    'notes'      => ['icon' => 'ph-notepad',    'label' => 'Catatan'],
                ];
                foreach ($group_manajemen as $page => $data):
                    $isActive = (!$adminPage && $current_page === $page);
                    $activeClass = $isActive ? 'bg-white/20 text-white shadow' : 'text-blue-100/80 hover:bg-white/10 hover:text-white';
                ?>
                <a href="<?= PahamFin_URL_PAGES ?>/<?= $page ?>.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl font-medium transition-all <?= $activeClass ?>" :class="collapsed ? 'lg:justify-center lg:px-0' : ''" title="<?= $data['label'] ?>">
                    <i class="ph <?= $data['icon'] ?> text-xl shrink-0 <?= $isActive ? 'text-amber-300' : '' ?>"></i>
                    <span x-show="!collapsed" class="truncate text-sm"><?= $data['label'] ?></span>
                    <?php if ($isActive): ?><i class="ph ph-caret-right ml-auto text-amber-300/70 text-sm" x-show="!collapsed"></i><?php endif; ?>
                </a>
                <?php endforeach; ?>

                <!-- Group: Lainnya -->
                <p x-show="!collapsed" class="px-3 pt-3 pb-1.5 text-[10px] font-bold uppercase tracking-widest text-blue-200/50">Lainnya</p>
                <?php
                $group_lainnya = [
                    'guide'    => ['icon' => 'ph-book-open-text',           'label' => 'Panduan'],
                    'settings' => ['icon' => 'ph-gear',                     'label' => 'Pengaturan'],
                ];
                foreach ($group_lainnya as $page => $data):
                    $isActive = (!$adminPage && $current_page === $page);
                    $activeClass = $isActive ? 'bg-white/20 text-white shadow' : 'text-blue-100/80 hover:bg-white/10 hover:text-white';
                ?>
                <a href="<?= PahamFin_URL_PAGES ?>/<?= $page ?>.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl font-medium transition-all <?= $activeClass ?>" :class="collapsed ? 'lg:justify-center lg:px-0' : ''" title="<?= $data['label'] ?>">
                    <i class="ph <?= $data['icon'] ?> text-xl shrink-0 <?= $isActive ? 'text-amber-300' : '' ?>"></i>
                    <span x-show="!collapsed" class="truncate text-sm"><?= $data['label'] ?></span>
                    <?php if ($isActive): ?><i class="ph ph-caret-right ml-auto text-amber-300/70 text-sm" x-show="!collapsed"></i><?php endif; ?>
                </a>
                <?php endforeach; ?>
            </nav>

            <!-- Bottom: Bots + User -->
            <div class="p-3 border-t border-white/10 space-y-2 shrink-0" :class="collapsed ? 'lg:p-2' : ''">
                <?php
                require_once __DIR__ . '/config.php';
                ?>
                <div class="flex" x-show="!collapsed">
                    <a href="<?= htmlspecialchars('https://web.telegram.org/k/#@' . PahamFin_TELEGRAM_BOT_USERNAME) ?>" target="_blank" rel="noopener"
                       class="flex items-center justify-center gap-1.5 w-full px-3 py-2 rounded-xl bg-sky-500/20 text-sky-200 text-xs font-semibold hover:bg-sky-500/30 transition-colors"
                       title="Chat Bot Telegram">
                        <i class="ph ph-telegram-logo text-base"></i> Buka Bot Telegram
                    </a>
                </div>
                <!-- User info -->
                <div class="flex items-center gap-3 px-2 py-2 rounded-xl bg-white/10 hover:bg-white/15 transition-colors cursor-default" :class="collapsed ? 'lg:justify-center lg:px-0' : ''">
                    <?php if (!empty($userProfile['profile_pic'])): ?>
                        <div class="w-9 h-9 rounded-full overflow-hidden border-2 border-white/20 shrink-0 shadow-inner">
                            <img src="<?= PahamFin_BASE_URL ?>/image/profiles/<?= htmlspecialchars($userProfile['profile_pic']) ?>" alt="Profile" class="w-full h-full object-cover">
                        </div>
                    <?php else: ?>
                        <div class="w-9 h-9 rounded-full bg-gradient-to-br from-amber-300 to-amber-500 flex items-center justify-center border-2 border-white/20 shrink-0 font-bold text-sm text-white shadow-inner">
                            <?= mb_strtoupper(mb_substr($userProfile['name'] ?? 'P', 0, 1)) ?>
                        </div>
                    <?php endif; ?>
                    <div class="flex-1 min-w-0" x-show="!collapsed">
                        <p class="text-sm font-semibold truncate flex items-center gap-1.5"><?= htmlspecialchars($userProfile['name'] ?? 'Pengguna') ?><?php if ($isAdmin): ?><span class="text-[9px] font-extrabold uppercase tracking-wide bg-amber-300 text-[#0A58A5] px-1.5 py-0.5 rounded-full">Admin</span><?php endif; ?></p>
                        <p class="text-[11px] text-blue-100/60 truncate"><?= htmlspecialchars($userProfile['email'] ?? '') ?></p>
                    </div>
                </div>
                <a href="<?= PahamFin_URL_AUTH ?>/logout.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium text-rose-300/80 hover:bg-rose-500/15 hover:text-rose-200 transition-colors" :class="collapsed ? 'lg:justify-center lg:px-0' : ''" title="Keluar">
                    <i class="ph ph-sign-out text-xl shrink-0"></i>
                    <span x-show="!collapsed">Keluar</span>
                </a>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="flex-1 flex flex-col h-screen overflow-hidden min-w-0">

            <!-- Navbar -->
            <header class="h-16 glass-card z-50 border-b border-white/40 dark:border-slate-700/50 flex items-center justify-between px-4 lg:px-6 shrink-0 gap-3">
                <!-- Left: hamburger + title -->
                <div class="flex items-center gap-3 min-w-0">
                    <button @click="sidebarOpen = true" class="lg:hidden p-2 text-gray-500 dark:text-slate-400 hover:text-primary dark:text-blue-400 hover:bg-blue-50 dark:hover:bg-slate-700 rounded-xl transition">
                        <i class="ph ph-list text-2xl"></i>
                    </button>
                    <div class="min-w-0 hidden sm:block">
                        <h2 class="text-base lg:text-lg font-display font-bold text-ink dark:text-slate-100 truncate"><?= get_page_title($current_page, $isAdmin, $adminPage) ?></h2>
                        <p class="text-xs text-gray-400 dark:text-slate-500 hidden lg:block">Selamat datang, <?= htmlspecialchars($userProfile['name'] ?? 'Pengguna') ?> <?= $isAdmin ? '· Admin' : '' ?> 👋</p>
                    </div>
                </div>

                <!-- Right: actions -->
                <div class="flex items-center gap-2">
                    <!-- Date -->
                    <span class="hidden lg:inline-flex items-center gap-1.5 text-xs font-medium bg-white/70 dark:bg-slate-700/70 text-gray-600 dark:text-slate-400 rounded-full px-3 py-1.5 border border-gray-100 dark:border-slate-600">
                        <i class="ph ph-calendar-blank text-primary dark:text-blue-400"></i><?= date('d F Y') ?>
                    </span>

                    <!-- Dark mode toggle -->
                    <?= PahamFin_theme_toggle('navbar') ?>

                    <!-- Notification bell -->
                    <div class="relative">
                        <button @click="notifOpen = !notifOpen" class="relative w-9 h-9 flex items-center justify-center bg-white/70 dark:bg-slate-700 rounded-xl shadow-sm hover:bg-white dark:hover:bg-slate-600 transition-colors">
                            <i class="ph ph-bell text-xl text-gray-600 dark:text-slate-400"></i>
                            <?php if ($notifUnread > 0): ?>
                            <span class="absolute -top-1 -right-1 min-w-[18px] h-[18px] px-1 rounded-full bg-rose-500 text-white text-[10px] font-bold flex items-center justify-center shadow"><?= $notifUnread > 9 ? '9+' : $notifUnread ?></span>
                            <?php endif; ?>
                        </button>
                        <div x-show="notifOpen" @click.away="notifOpen = false" x-transition
                             class="absolute right-0 mt-2 w-80 max-w-[90vw] bg-white dark:bg-slate-800 rounded-2xl shadow-2xl shadow-blue-900/10 border border-gray-100 dark:border-slate-700 overflow-hidden z-[200]" style="display:none;">
                            <div class="flex items-center justify-between px-4 py-3 border-b border-gray-100 dark:border-slate-700 bg-white/80 dark:bg-slate-800/80">
                                <h4 class="font-semibold text-sm">Notifikasi</h4>
                                <?php if (count($notifList) > 0): ?>
                                <a href="<?= PahamFin_URL_API ?>/notifications.php?mark=read" class="text-[11px] font-medium text-primary dark:text-blue-400 hover:underline">Tandai dibaca</a>
                                <?php endif; ?>
                            </div>
                            <div class="max-h-80 overflow-y-auto no-scrollbar">
                                <?php if (count($notifList) === 0): ?>
                                    <p class="p-6 text-center text-sm text-gray-400 dark:text-slate-500">Belum ada notifikasi.</p>
                                <?php endif; ?>
                                <?php foreach ($notifList as $n): ?>
                                <div class="flex items-start gap-3 px-4 py-3 border-b border-gray-50 dark:border-slate-700/50 last:border-0 hover:bg-blue-50/40 dark:hover:bg-slate-700/40">
                                    <span class="w-8 h-8 rounded-lg flex items-center justify-center shrink-0 <?= $n['type'] == 'income' ? 'bg-emerald-50 dark:bg-emerald-900/30 text-emerald-600 dark:text-emerald-400' : 'bg-blue-50 dark:bg-blue-900/30 text-primary dark:text-blue-400' ?>">
                                        <i class="ph <?= $n['type'] == 'income' ? 'ph-trend-up' : 'ph-bell' ?>"></i>
                                    </span>
                                    <div class="min-w-0 flex-1">
                                        <p class="text-sm leading-snug"><?= htmlspecialchars($n['message']) ?></p>
                                        <p class="text-[11px] text-gray-400 dark:text-slate-500 mt-0.5"><?= htmlspecialchars($n['created_at']) ?></p>
                                    </div>
                                    <?php if (!$n['is_read']): ?><span class="w-2 h-2 rounded-full bg-rose-500 shrink-0 mt-1.5"></span><?php endif; ?>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Scrollable Page Content Area -->
            <div class="flex-1 overflow-y-auto">
                <div class="max-w-screen-xl mx-auto p-4 lg:p-6 space-y-6">





