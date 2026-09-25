<?php
require_once __DIR__ . '/../../app/db.php';
require_once __DIR__ . '/../../app/includes/auth.php';
require_admin($pdo);

$successMsg = null;
$errorMsg = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!PahamFin_csrf_verify()) {
        $errorMsg = 'Sesi CSRF tidak valid, silakan muat ulang halaman.';
    } else {
        $videoUrl = trim($_POST['tutorial_youtube_url'] ?? '');
        if (!empty($videoUrl)) {
            PahamFin_set_setting($pdo, 'tutorial_youtube_url', $videoUrl);
            $successMsg = 'URL Video tutorial berhasil disimpan & diperbarui!';
        } else {
            $errorMsg = 'URL Video tidak boleh kosong.';
        }
    }
}

// Ambil URL Video Tutorial dari Pengaturan DB
$currentTutorialUrl = PahamFin_get_setting($pdo, 'tutorial_youtube_url', 'https://youtu.be/fHL5qk2-0xI?si=Q-5TEHuggBmy4mJc');

// Ekstrak ID video untuk preview
preg_match('/(?:youtu\.be\/|youtube\.com\/(?:embed\/|v\/|watch\?v=|watch\?.+&v=))([\w-]{11})/', $currentTutorialUrl, $matches);
$previewVideoId = $matches[1] ?? 'fHL5qk2-0xI';
$previewEmbedUrl = "https://www.youtube.com/embed/" . $previewVideoId;

require_once __DIR__ . '/../../app/includes/header.php';
require_once __DIR__ . '/../../app/includes/sidebar.php';
?>

<div class="space-y-6">

    <!-- Header Section -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-xl lg:text-2xl font-display font-extrabold text-ink dark:text-slate-100 flex items-center gap-2">
                <i class="ph ph-youtube-logo text-red-600 text-2xl lg:text-3xl"></i> Pengaturan Video Tutorial
            </h1>
            <p class="text-xs lg:text-sm text-gray-500 dark:text-slate-400 mt-1">
                Kelola & ganti link video tutorial YouTube yang tampil di halaman Panduan pengguna secara praktis dari desktop.
            </p>
        </div>
    </div>

    <?php if ($successMsg): ?>
        <div class="p-4 rounded-2xl bg-emerald-50 dark:bg-emerald-900/30 border border-emerald-200 dark:border-emerald-800 text-emerald-800 dark:text-emerald-200 text-sm flex items-center gap-3">
            <i class="ph ph-check-circle text-xl text-emerald-600 dark:text-emerald-400 shrink-0"></i>
            <div><?= htmlspecialchars($successMsg) ?></div>
        </div>
    <?php endif; ?>

    <?php if ($errorMsg): ?>
        <div class="p-4 rounded-2xl bg-rose-50 dark:bg-rose-900/30 border border-rose-200 dark:border-rose-800 text-rose-700 dark:text-rose-300 text-sm flex items-center gap-3">
            <i class="ph ph-warning-circle text-xl shrink-0"></i>
            <div><?= htmlspecialchars($errorMsg) ?></div>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

        <!-- Form Edit URL Video -->
        <div class="glass-card rounded-2xl border border-white/60 dark:border-slate-700/50 shadow-sm overflow-hidden">
            <div class="p-4 lg:p-5 border-b border-gray-100 dark:border-slate-700/50 bg-white/60 dark:bg-slate-800/60 flex items-center gap-3">
                <div class="p-2.5 bg-red-50 dark:bg-red-900/30 rounded-xl text-red-600 dark:text-red-400">
                    <i class="ph ph-pencil-line text-xl"></i>
                </div>
                <div>
                    <h3 class="font-bold text-sm text-ink dark:text-slate-100">Ubah URL Video YouTube</h3>
                    <p class="text-[11px] text-gray-400">Masukkan link baru dari YouTube</p>
                </div>
            </div>

            <form method="POST" class="p-4 lg:p-6 space-y-4">
                <?= PahamFin_csrf_field() ?>

                <div>
                    <label class="block text-xs font-semibold text-gray-700 dark:text-slate-300 mb-1.5">Link / URL Video YouTube</label>
                    <input type="text" name="tutorial_youtube_url" value="<?= htmlspecialchars($currentTutorialUrl) ?>" 
                           placeholder="https://youtu.be/fHL5qk2-0xI" required
                           class="w-full px-3.5 py-2.5 bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-xl text-xs font-mono">
                    <p class="text-[11px] text-gray-400 mt-2 leading-relaxed">
                        Mendukung link share pendek (<code class="bg-gray-100 dark:bg-slate-700 px-1 py-0.5 rounded">https://youtu.be/xxx</code>), link browser (<code class="bg-gray-100 dark:bg-slate-700 px-1 py-0.5 rounded">https://www.youtube.com/watch?v=xxx</code>), atau embed URL.
                    </p>
                </div>

                <button type="submit" class="w-full py-2.5 bg-red-600 hover:bg-red-700 text-white font-bold text-xs rounded-xl transition shadow-md shadow-red-600/20 flex items-center justify-center gap-2">
                    <i class="ph ph-floppy-disk text-base"></i> Simpan Video Tutorial
                </button>
            </form>
        </div>

        <!-- Preview Video Live -->
        <div class="glass-card rounded-2xl border border-white/60 dark:border-slate-700/50 shadow-sm overflow-hidden p-4 lg:p-6">
            <h3 class="font-bold text-sm text-ink dark:text-slate-100 mb-2 flex items-center gap-2">
                <i class="ph ph-eye text-primary dark:text-sky-400 text-lg"></i> Preview Tampilan Video Saat Ini
            </h3>
            <p class="text-xs text-gray-400 mb-4">Video ini yang sedang aktif dan tampil di halaman Panduan pengguna.</p>

            <div class="relative w-full aspect-video rounded-xl overflow-hidden bg-slate-900 border border-slate-700 shadow-inner flex items-center justify-center">
                <iframe class="w-full h-full rounded-xl"
                        src="<?= htmlspecialchars($previewEmbedUrl) ?>"
                        title="Preview Video Tutorial"
                        frameborder="0"
                        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                        allowfullscreen>
                </iframe>
            </div>
        </div>

    </div>

</div>

<?php require_once __DIR__ . '/../../app/includes/footer.php'; ?>
