<?php
require_once __DIR__ . '/../../app/db.php';
require_once __DIR__ . '/../../app/includes/auth.php';
require_admin($pdo);

require_once __DIR__ . '/../../app/includes/header.php';
require_once __DIR__ . '/../../app/includes/sidebar.php';
?>

<?php
// Ambil daftar email admin terkonfigurasi & admin aktif.
$configuredEmails = [];
if (defined('PahamFin_ADMIN_EMAILS')) {
    foreach (explode(',', (string) PahamFin_ADMIN_EMAILS) as $e) {
        $e = trim($e);
        if ($e !== '') {
            $configuredEmails[] = $e;
        }
    }
}

$adminRows = $pdo->query(
    "SELECT id, name, email, created_at FROM users WHERE LOWER(role) = 'admin' ORDER BY created_at ASC"
)->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-4 lg:gap-6">

    <!-- Cara menambah admin -->
    <div class="glass-card rounded-2xl shadow-md shadow-blue-900/5 border border-white/60 overflow-hidden">
        <div class="p-4 lg:p-6 border-b border-gray-100 dark:border-slate-700/50 bg-white/60 dark:bg-slate-800/60 flex items-center gap-3">
            <div class="p-2.5 bg-amber-50 dark:bg-amber-900/30 rounded-xl text-amber-600 dark:text-amber-400"><i class="ph ph-shield-check text-xl"></i></div>
            <div>
                <h3 class="text-base lg:text-lg font-display font-bold text-ink dark:text-slate-100">Cara Menambah Admin</h3>
                <p class="text-xs text-gray-400">Siapa pun yang emailnya terkonfirmasi admin</p>
            </div>
        </div>
        <div class="p-4 lg:p-6 space-y-4 text-sm text-gray-600 dark:text-slate-400">
            <div class="space-y-2">
                <p class="font-semibold text-ink dark:text-slate-100 flex items-center gap-1.5"><i class="ph ph-gear text-primary dark:text-blue-400"></i> Metode 1 — Lewat file konfigurasi</p>
                <p>Tambahkan email di <code class="text-xs bg-gray-100 px-1.5 py-0.5 rounded">app/includes/config.php</code> pada konstanta <code class="text-xs bg-gray-100 px-1.5 py-0.5 rounded">PahamFin_ADMIN_EMAILS</code> (dipisah koma). Saat user dengan email itu login atau daftar, statusnya otomatis menjadi admin.</p>
                <pre class="text-xs bg-[#0f172a] text-emerald-300 p-3 rounded-xl overflow-x-auto"><code>define('PahamFin_ADMIN_EMAILS', 'admin@PahamFin.dev,bos@PahamFin.dev');</code></pre>
            </div>
            <div class="space-y-2">
                <p class="font-semibold text-ink dark:text-slate-100 flex items-center gap-1.5"><i class="ph ph-sliders-horizontal text-primary dark:text-blue-400"></i> Metode 2 — Lewat panel pengguna</p>
                <p>Buka halaman <a href="users.php" class="text-primary dark:text-blue-400 font-semibold hover:underline">Pengguna</a>, lalu klik tombol <span class="text-[10px] font-bold uppercase bg-amber-100 dark:bg-amber-900/50 text-amber-700 dark:text-amber-300 px-1.5 py-0.5 rounded-full">Jadikan Admin</span> pada akun yang diinginkan.</p>
            </div>
            <div class="flex items-start gap-2 p-3 rounded-xl bg-blue-50 dark:bg-blue-900/30 border border-blue-100 text-blue-800 dark:text-blue-200 text-xs leading-relaxed">
                <i class="ph ph-info mt-0.5"></i>
                <span>Seorang admin tidak bisa mencabut status admin dirinya sendiri (anti-lockout). Admin yang emailnya terdaftar di <b>PahamFin_ADMIN_EMAILS</b> juga tidak bisa dicabut lewat panel.</span>
            </div>
        </div>
    </div>

    <!-- Daftar admin aktif -->
    <div class="glass-card rounded-2xl shadow-md shadow-blue-900/5 border border-white/60 overflow-hidden">
        <div class="p-4 lg:p-6 border-b border-gray-100 dark:border-slate-700/50 bg-white/60 dark:bg-slate-800/60 flex items-center gap-3">
            <div class="p-2.5 bg-emerald-50 dark:bg-emerald-900/30 rounded-xl text-emerald-600 dark:text-emerald-400"><i class="ph ph-users-three text-xl"></i></div>
            <div>
                <h3 class="text-base lg:text-lg font-display font-bold text-ink dark:text-slate-100">Admin Aktif</h3>
                <p class="text-xs text-gray-400"><?= count($adminRows) ?> akun berstatus admin di database</p>
            </div>
        </div>

        <?php if (count($configuredEmails) > 0): ?>
        <div class="p-4 lg:p-6 border-b border-gray-100 dark:border-slate-700/50">
            <p class="text-xs font-semibold uppercase tracking-wider text-gray-400 mb-2">Email terkonfigurasi (config)</p>
            <div class="flex flex-wrap gap-2">
                <?php foreach ($configuredEmails as $ce): ?>
                    <span class="inline-flex items-center gap-1.5 text-xs font-semibold bg-indigo-50 text-indigo-700 px-2.5 py-1 rounded-full">
                        <i class="ph ph-envelope-simple"></i> <?= htmlspecialchars($ce) ?>
                    </span>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <div class="p-4 lg:p-6">
            <?php if (count($adminRows) === 0): ?>
                <p class="text-sm text-gray-400">Belum ada admin di database.</p>
            <?php else: ?>
                <div class="space-y-2.5">
                    <?php foreach ($adminRows as $a): ?>
                    <div class="flex items-center gap-3 p-3 rounded-xl bg-white/70 dark:bg-slate-800/70 border border-gray-100 dark:border-slate-700/50">
                        <span class="w-9 h-9 rounded-full bg-amber-100 dark:bg-amber-900/50 text-amber-600 dark:text-amber-400 flex items-center justify-center shrink-0">
                            <i class="ph ph-shield-check"></i>
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-semibold text-ink dark:text-slate-100 truncate flex items-center gap-1.5">
                                <?= htmlspecialchars($a['name']) ?>
                                <?php if ((int) $a['id'] === (int) current_user_id()): ?>
                                    <span class="text-[9px] font-bold uppercase bg-blue-100 dark:bg-blue-900/50 text-primary dark:text-blue-400 px-1.5 py-0.5 rounded-full">Anda</span>
                                <?php endif; ?>
                            </p>
                            <p class="text-xs text-gray-400 truncate"><?= htmlspecialchars($a['email']) ?></p>
                        </div>
                        <span class="text-xs text-gray-400 whitespace-nowrap"><?= htmlspecialchars(date('d M Y', strtotime($a['created_at']))) ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <a href="users.php" class="mt-4 inline-flex items-center justify-center gap-2 w-full py-2.5 border border-gray-200 dark:border-slate-700 rounded-xl text-sm font-semibold text-gray-600 dark:text-slate-400 hover:bg-gray-50 dark:bg-slate-700/50 transition">
                <i class="ph ph-arrow-right"></i> Kelola Pengguna
            </a>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../app/includes/footer.php'; ?>






