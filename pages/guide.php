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
            <p class="text-sm text-gray-500 dark:text-slate-400 mb-4">Klik tombol berikut untuk langsung membuka chat dengan bot Telegram PahamFin.</p>

            <a href="<?= htmlspecialchars($tgLink) ?>" target="_blank" rel="noopener"
               class="flex items-center justify-between w-full px-4 py-3 bg-sky-600 text-white text-sm font-semibold rounded-xl hover:bg-sky-700 transition-all shadow-md shadow-sky-600/20 mb-3">
                <span class="flex items-center gap-3"><i class="ph ph-telegram-logo text-xl"></i>Chat Bot Telegram</span>
                <i class="ph ph-arrow-up-right"></i>
            </a>

            <a href="https://t.me/userinfotg2bot" target="_blank" rel="noopener"
               class="flex items-center justify-between w-full px-4 py-2.5 bg-sky-50 dark:bg-sky-900/30 text-sky-700 dark:text-sky-300 border border-sky-200 dark:border-sky-800/50 text-xs font-semibold rounded-xl hover:bg-sky-100 dark:hover:bg-sky-900/50 transition-colors">
                <span class="flex items-center gap-2"><i class="ph ph-user-focus text-base"></i>Cek ID Telegram Kamu (@userinfotg2bot)</span>
                <i class="ph ph-arrow-up-right text-xs"></i>
            </a>

            <p class="text-xs text-gray-400 mt-4">
                Pastikan bot sudah berjalan dan ID Telegram kamu sudah dimasukkan di halaman profil/pengaturan.
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

    <!-- Kolom kanan: Video Tutorial & Panduan Langkah -->
    <div class="lg:col-span-2 space-y-6">
        <!-- Card Video Tutorial YouTube (Siap Diisi Link Embed) -->
        <div class="glass-card border border-white/60 dark:border-slate-700/50 dark:bg-slate-800/80 rounded-2xl shadow-sm overflow-hidden p-6">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-slate-100 flex items-center gap-2">
                        <i class="ph ph-youtube-logo text-red-600 text-2xl"></i> Video Tutorial Penggunaan
                    </h3>
                    <p class="text-sm text-gray-500 dark:text-slate-400">Tonton panduan visual lengkap cara menggunakan bot & dashboard PahamFin.</p>
                </div>
            </div>

            <!-- Wadah Video YouTube Responsive 16:9 -->
            <div class="relative w-full aspect-video rounded-xl overflow-hidden bg-slate-900 border border-slate-700 shadow-inner flex items-center justify-center group">
                <iframe class="w-full h-full rounded-xl"
                        src="https://www.youtube.com/embed/fHL5qk2-0xI"
                        title="Video Tutorial PahamFin"
                        frameborder="0"
                        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                        allowfullscreen>
                </iframe>
            </div>
        </div>

        <!-- Panduan langkah demi langkah -->
        <div class="glass-card border border-white/60 dark:border-slate-700/50 dark:bg-slate-800/80 rounded-2xl shadow-sm overflow-hidden p-6 divide-y divide-gray-100 dark:divide-slate-700/50">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-slate-100 mb-2">Panduan Ringkas</h3>
            <p class="text-sm text-gray-500 dark:text-slate-400 mb-4">Empat langkah mudah untuk mulai mencatat keuangan lewat chat Telegram.</p>

            <div class="py-4 flex gap-4">
                <span class="shrink-0 w-8 h-8 rounded-full bg-primary text-white flex items-center justify-center font-bold text-sm">1</span>
                <div>
                    <h4 class="font-semibold text-gray-900 dark:text-slate-100">Daftar akun dan masuk ke dashboard</h4>
                    <p class="text-sm text-gray-600 dark:text-slate-400 mt-1">Daftar gratis dengan email kamu. Kategori default langsung siap diakses.</p>
                </div>
            </div>

            <div class="py-4 flex gap-4">
                <span class="shrink-0 w-8 h-8 rounded-full bg-primary text-white flex items-center justify-center font-bold text-sm">2</span>
                <div>
                    <h4 class="font-semibold text-gray-900 dark:text-slate-100">Cek & Hubungkan ID Telegram</h4>
                    <p class="text-sm text-gray-600 dark:text-slate-400 mt-1">
                        Cek ID Telegram kamu secara manual melalui bot <a href="https://t.me/userinfotg2bot" target="_blank" rel="noopener" class="text-sky-600 dark:text-sky-400 font-semibold hover:underline inline-flex items-center gap-1">@userinfotg2bot <i class="ph ph-arrow-up-right text-xs"></i></a>, lalu masukkan angka ID tersebut di menu <a href="settings.php" class="text-blue-600 dark:text-blue-400 font-medium hover:underline">Pengaturan Profil</a>. Setelah itu kirim pesan <span class="font-mono bg-gray-100 dark:bg-slate-700 dark:text-slate-300 px-1 rounded">/start</span> ke bot PahamFin.
                    </p>
                </div>
            </div>

            <div class="py-4 flex gap-4">
                <span class="shrink-0 w-8 h-8 rounded-full bg-primary text-white flex items-center justify-center font-bold text-sm">3</span>
                <div>
                    <h4 class="font-semibold text-gray-900 dark:text-slate-100">Kirim catatan lewat chat</h4>
                    <p class="text-sm text-gray-600 dark:text-slate-400 mt-1">Tulis <b>[keterangan] [nominal]</b>, misal <span class="font-mono bg-gray-100 dark:bg-slate-700 dark:text-slate-300 px-1 rounded">makan 50000</span> atau <span class="font-mono bg-gray-100 dark:bg-slate-700 dark:text-slate-300 px-1 rounded">bensin 20k</span>. Bot akan otomatis mengategori dan mencatatnya.</p>
                </div>
            </div>

            <div class="py-4 flex gap-4">
                <span class="shrink-0 w-8 h-8 rounded-full bg-primary text-white flex items-center justify-center font-bold text-sm">4</span>
                <div>
                    <h4 class="font-semibold text-gray-900 dark:text-slate-100">Pantau di dashboard real-time</h4>
                    <p class="text-sm text-gray-600 dark:text-slate-400 mt-1">Lihat saldo, grafik pengeluaran, dan riwayat di <b>Dashboard</b>, <b>Transaksi</b>, dan <b>Anggaran</b>. Data selalu terupdate otomatis.</p>
                </div>
            </div>

            <div class="pt-4">
                <h4 class="font-semibold text-blue-700 dark:text-blue-300 mb-2 flex items-center gap-2"><i class="ph ph-lightbulb"></i> Tips & Triks</h4>
                <ul class="text-sm text-blue-800 dark:text-blue-200 space-y-1.5 leading-relaxed">
                    <li>• Pakai satuan <span class="font-mono bg-blue-100 dark:bg-blue-900/50 px-1 rounded">rb</span>, <span class="font-mono bg-blue-100 dark:bg-blue-900/50 px-1 rounded">k</span>, atau <span class="font-mono bg-blue-100 dark:bg-blue-900/50 px-1 rounded">jt</span> untuk nominal (contoh <span class="font-mono bg-blue-100 dark:bg-blue-900/50 px-1 rounded">100rb</span> = 100.000).</li>
                    <li>• Jika bot membalas "kategori tidak ditemukan", Anda bisa menambah kata kunci di menu <b>Kategori & Bot</b>.</li>
                </ul>
            </div>
        </div>

    </div>
</div>

<?php require_once __DIR__ . '/../app/includes/footer.php'; ?>








