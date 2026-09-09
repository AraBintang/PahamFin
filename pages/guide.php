<?php require_once __DIR__ . '/../app/includes/header.php'; ?>
<?php require_once __DIR__ . '/../app/includes/sidebar.php'; ?>
<?php require_once __DIR__ . '/../app/includes/config.php'; ?>

<?php
$tgLink = PahamFin_tg_link();
$tgUsername = trim(PahamFin_TELEGRAM_BOT_USERNAME);
?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Kolom kiri: links bot -->
    <div class="lg:col-span-1 space-y-6">
        <div class="glass-card border border-white/60 dark:border-slate-700/50 dark:bg-slate-800/80 rounded-2xl shadow-sm overflow-hidden p-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-slate-100 mb-4">Mulai Akses Bot</h3>
            <p class="text-sm text-gray-500 dark:text-slate-400 mb-4">Klik tombol berikut untuk langsung membuka chat dengan bot Telegram.</p>

            <a href="<?= htmlspecialchars($tgLink) ?>" target="_blank" rel="noopener"
               class="flex items-center justify-between w-full px-4 py-3 bg-sky-600 text-white text-sm font-semibold rounded-lg hover:bg-sky-700 transition-colors">
                <span class="flex items-center gap-3"><i class="ph ph-telegram-logo text-xl"></i>Chat Bot Telegram</span>
                <i class="ph ph-arrow-up-right"></i>
            </a>

            <p class="text-xs text-gray-400 mt-4">
                Pastikan bot sudah berjalan di server dan nomor / ID kamu sudah didaftarkan di profil.
            </p>
        </div>

        <div class="glass-card border border-white/60 dark:border-slate-700/50 dark:bg-slate-800/80 rounded-2xl shadow-sm overflow-hidden p-6">
            <h3 class="text-sm font-semibold text-gray-900 dark:text-slate-100 mb-3">Format Pesan Contoh</h3>
            <div class="space-y-2 text-sm">
                <div class="bg-gray-50 dark:bg-slate-700/50 rounded-lg px-3 py-2 font-mono text-gray-700 dark:text-slate-300">makan 50000</div>
                <div class="bg-gray-50 dark:bg-slate-700/50 rounded-lg px-3 py-2 font-mono text-gray-700 dark:text-slate-300">bensin 100rb</div>
                <div class="bg-gray-50 dark:bg-slate-700/50 rounded-lg px-3 py-2 font-mono text-gray-700 dark:text-slate-300">kopi 25k</div>
                <div class="bg-gray-50 dark:bg-slate-700/50 rounded-lg px-3 py-2 font-mono text-gray-700 dark:text-slate-300">gaji 5jt</div>
                <div class="bg-gray-50 dark:bg-slate-700/50 rounded-lg px-3 py-2 font-mono text-gray-700 dark:text-slate-300">50000 makan siang</div>
            </div>
        </div>
    </div>

    <!-- Kolom kanan: panduan langkah demi langkah -->
    <div class="lg:col-span-2 space-y-6">
        <div class="glass-card border border-white/60 dark:border-slate-700/50 dark:bg-slate-800/80 rounded-2xl shadow-sm overflow-hidden p-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-slate-100 mb-2">Panduan Penggunaan PahamFin</h3>
            <p class="text-sm text-gray-500 dark:text-slate-400">Ikuti langkah-langkah berikut untuk mulai mencatat keuangan otomatis lewat chat.</p>
        </div>

        <!-- Langkah-langkah ringkas -->
        <div class="glass-card border border-white/60 dark:border-slate-700/50 dark:bg-slate-800/80 rounded-2xl shadow-sm overflow-hidden p-6 divide-y divide-gray-100 dark:divide-slate-700/50">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-slate-100 mb-2">Panduan Ringkas</h3>
            <p class="text-sm text-gray-500 dark:text-slate-400 mb-4">Empat langkah untuk mulai mencatat keuangan lewat chat.</p>

            <div class="py-4 flex gap-4">
                <span class="shrink-0 w-8 h-8 rounded-full bg-primary text-white flex items-center justify-center font-bold text-sm">1</span>
                <div>
                    <h4 class="font-semibold text-gray-900 dark:text-slate-100">Daftar akun dan masuk ke dashboard</h4>
                    <p class="text-sm text-gray-600 dark:text-slate-400 mt-1">Daftar gratis dengan email kamu. Kategori default langsung siap.</p>
                </div>
            </div>

            <div class="py-4 flex gap-4">
                <span class="shrink-0 w-8 h-8 rounded-full bg-primary text-white flex items-center justify-center font-bold text-sm">2</span>
                <div>
                    <h4 class="font-semibold text-gray-900 dark:text-slate-100">Hubungkan bot</h4>
                    <p class="text-sm text-gray-600 dark:text-slate-400 mt-1">
                        Di <a href="settings.php" class="text-blue-600 dark:text-blue-400 font-medium hover:underline">Pengaturan</a>, isi ID Telegram kamu. Lalu kirim <span class="font-mono bg-gray-100 dark:bg-slate-700 dark:text-slate-300 px-1 rounded">/start</span> ke bot untuk menghubungkan akun.
                    </p>
                </div>
            </div>

            <div class="py-4 flex gap-4">
                <span class="shrink-0 w-8 h-8 rounded-full bg-primary text-white flex items-center justify-center font-bold text-sm">3</span>
                <div>
                    <h4 class="font-semibold text-gray-900 dark:text-slate-100">Kirim catatan lewat chat</h4>
                    <p class="text-sm text-gray-600 dark:text-slate-400 mt-1">Tulis <b>[keterangan] [nominal]</b>, misal <span class="font-mono bg-gray-100 dark:bg-slate-700 dark:text-slate-300 px-1 rounded">makan 50000</span>. Bot mendeteksi kategori lalu mencatatnya.</p>
                </div>
            </div>

            <div class="py-4 flex gap-4">
                <span class="shrink-0 w-8 h-8 rounded-full bg-primary text-white flex items-center justify-center font-bold text-sm">4</span>
                <div>
                    <h4 class="font-semibold text-gray-900 dark:text-slate-100">Pantau di dashboard</h4>
                    <p class="text-sm text-gray-600 dark:text-slate-400 mt-1">Lihat saldo, riwayat, dan pengeluaran per kategori di <b>Dashboard</b>, <b>Transaksi</b>, dan <b>Anggaran</b>. Selalu terbarui otomatis.</p>
                </div>
            </div>

            <div class="pt-4">
                <h4 class="font-semibold text-blue-700 dark:text-blue-300 mb-2 flex items-center gap-2"><i class="ph ph-lightbulb"></i> Tips</h4>
                <ul class="text-sm text-blue-800 dark:text-blue-200 space-y-1.5 leading-relaxed">
                    <li>• Pakai satuan <span class="font-mono bg-blue-100 dark:bg-blue-900/50 px-1 rounded">rb</span>, <span class="font-mono bg-blue-100 dark:bg-blue-900/50 px-1 rounded">k</span>, atau <span class="font-mono bg-blue-100 dark:bg-blue-900/50 px-1 rounded">jt</span> untuk nominal (contoh <span class="font-mono bg-blue-100 dark:bg-blue-900/50 px-1 rounded">100rb</span> = 100.000).</li>
                    <li>• Jika bot balas "kategori tidak ditemukan", cek keyword di menu <b>Kategori & Bot</b>.</li>
                </ul>
            </div>
        </div>

    </div>
</div>

<?php require_once __DIR__ . '/../app/includes/footer.php'; ?>








