<?php
require_once __DIR__ . '/../../app/db.php';
require_once __DIR__ . '/../../app/includes/config.php';
require_once __DIR__ . '/../../app/includes/auth.php';
require_admin($pdo);

$current_page = 'plans';
$adminPage = true;

// Handle Form Submissions (Create, Edit, Delete, Toggle, Manual Grant)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!PahamFin_csrf_verify()) {
        $_SESSION['flash_msg'] = 'Sesi tidak valid, coba lagi.';
        $_SESSION['flash_type'] = 'error';
        header('Location: plans.php');
        exit;
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'create' || $action === 'update') {
        $planId = isset($_POST['plan_id']) ? (int) $_POST['plan_id'] : 0;
        $name = trim((string) ($_POST['name'] ?? ''));
        $price = (float) ($_POST['price'] ?? 0);
        $durationDays = max(1, (int) ($_POST['duration_days'] ?? 30));
        $description = trim((string) ($_POST['description'] ?? ''));
        $features = trim((string) ($_POST['features'] ?? ''));
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        if ($name === '') {
            $_SESSION['flash_msg'] = 'Nama paket wajib diisi.';
            $_SESSION['flash_type'] = 'error';
            header('Location: plans.php');
            exit;
        }

        if ($action === 'create') {
            $stmt = $pdo->prepare("
                INSERT INTO subscription_plans (name, price, duration_days, description, features, is_active)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$name, $price, $durationDays, $description, $features, $isActive]);
            $_SESSION['flash_msg'] = 'Paket langganan baru berhasil ditambahkan!';
            $_SESSION['flash_type'] = 'success';
        } else {
            $stmt = $pdo->prepare("
                UPDATE subscription_plans
                SET name = ?, price = ?, duration_days = ?, description = ?, features = ?, is_active = ?
                WHERE id = ?
            ");
            $stmt->execute([$name, $price, $durationDays, $description, $features, $isActive, $planId]);
            $_SESSION['flash_msg'] = 'Paket langganan berhasil diperbarui!';
            $_SESSION['flash_type'] = 'success';
        }
        header('Location: plans.php');
        exit;
    }

    if ($action === 'toggle_active') {
        $planId = (int) ($_POST['plan_id'] ?? 0);
        $stmt = $pdo->prepare("UPDATE subscription_plans SET is_active = CASE WHEN is_active = 1 THEN 0 ELSE 1 END WHERE id = ?");
        $stmt->execute([$planId]);
        $_SESSION['flash_msg'] = 'Status paket berhasil diubah.';
        $_SESSION['flash_type'] = 'success';
        header('Location: plans.php');
        exit;
    }

    if ($action === 'delete') {
        $planId = (int) ($_POST['plan_id'] ?? 0);
        $stmt = $pdo->prepare("DELETE FROM subscription_plans WHERE id = ?");
        $stmt->execute([$planId]);
        $_SESSION['flash_msg'] = 'Paket langganan berhasil dihapus.';
        $_SESSION['flash_type'] = 'success';
        header('Location: plans.php');
        exit;
    }

    if ($action === 'grant_user') {
        $targetUserId = (int) ($_POST['target_user_id'] ?? 0);
        $planId = (int) ($_POST['grant_plan_id'] ?? 0);

        if ($targetUserId > 0 && $planId > 0) {
            $res = PahamFin_activate_user_subscription($pdo, $targetUserId, $planId, 'ADMIN_MANUAL');
            $_SESSION['flash_msg'] = $res['message'];
            $_SESSION['flash_type'] = $res['success'] ? 'success' : 'error';
        } else {
            $_SESSION['flash_msg'] = 'Pilih pengguna dan paket yang valid.';
            $_SESSION['flash_type'] = 'error';
        }
        header('Location: plans.php');
        exit;
    }
}

// Fetch Data
$plans = PahamFin_get_subscription_plans($pdo, false);

// Fetch recent active user subscriptions
$subRows = $pdo->query("
    SELECT s.*, u.name as user_name, u.email as user_email, p.name as plan_name
    FROM user_subscriptions s
    JOIN users u ON s.user_id = u.id
    JOIN subscription_plans p ON s.plan_id = p.id
    ORDER BY s.created_at DESC LIMIT 30
")->fetchAll(PDO::FETCH_ASSOC);

// Fetch users for manual grant dropdown
$allUsers = $pdo->query("SELECT id, name, email FROM users ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/../../app/includes/header.php';
require_once __DIR__ . '/../../app/includes/sidebar.php';
?>

<div x-data="{ modalOpen: false, modalMode: 'create', editData: { id: 0, name: '', price: 0, duration_days: 30, description: '', features: '', is_active: 1 } }">

    <!-- Header & Action -->
    <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
        <div>
            <span class="text-xs font-semibold text-amber-500 uppercase tracking-widest flex items-center gap-1.5">
                <i class="ph ph-crown text-base"></i> Manajemen Layanan
            </span>
            <h2 class="text-xl lg:text-2xl font-display font-extrabold text-gray-900 dark:text-slate-100 mt-1">Kelola Paket Langganan & Pembayaran</h2>
            <p class="text-xs text-gray-500 dark:text-slate-400 mt-0.5">Atur harga paket, durasi, daftar fitur, dan berikan akses langganan ke pengguna.</p>
        </div>
        <div class="flex items-center gap-2">
            <button @click="modalMode = 'create'; editData = { id: 0, name: '', price: 17000, duration_days: 30, description: '', features: '', is_active: 1 }; modalOpen = true;"
                    class="px-4 py-2.5 bg-primary text-white rounded-xl text-xs font-semibold hover:bg-[#0e7ad6] transition shadow-lg shadow-blue-900/20 flex items-center gap-2">
                <i class="ph ph-plus-circle text-base"></i> Tambah Paket Baru
            </button>
        </div>
    </div>

    <!-- Cards Grid Paket Langganan -->
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6 mb-8">
        <?php foreach ($plans as $plan): ?>
        <div class="glass-card rounded-2xl border border-white/60 dark:border-slate-700/50 overflow-hidden shadow-sm flex flex-col justify-between relative">
            <div class="p-5">
                <div class="flex items-center justify-between gap-2 mb-3">
                    <span class="px-2.5 py-1 text-[10px] font-bold uppercase tracking-wider rounded-full <?= $plan['is_active'] ? 'bg-emerald-100 dark:bg-emerald-900/50 text-emerald-700 dark:text-emerald-300' : 'bg-rose-100 dark:bg-rose-900/50 text-rose-700 dark:text-rose-300' ?>">
                        <?= $plan['is_active'] ? '🟢 Aktif' : '🔴 Non-Aktif' ?>
                    </span>
                    <span class="text-xs font-semibold text-gray-400"><i class="ph ph-clock text-sm"></i> <?= (int)$plan['duration_days'] ?> Hari</span>
                </div>

                <h3 class="text-lg font-display font-bold text-ink dark:text-slate-100"><?= htmlspecialchars($plan['name']) ?></h3>
                <p class="text-xs text-gray-500 dark:text-slate-400 mt-1 line-clamp-2"><?= htmlspecialchars($plan['description'] ?: 'Tidak ada deskripsi.') ?></p>

                <div class="my-4">
                    <span class="text-2xl font-extrabold text-primary dark:text-blue-400">Rp <?= number_format($plan['price'], 0, ',', '.') ?></span>
                    <span class="text-xs text-gray-400 font-normal">/ <?= (int)$plan['duration_days'] ?> hari</span>
                </div>
            </div>

            <div class="p-3 bg-gray-50/70 dark:bg-slate-800/60 border-t border-gray-100 dark:border-slate-700/50 flex items-center justify-between gap-2">
                <form method="POST" class="inline">
                    <?= PahamFin_csrf_field() ?>
                    <input type="hidden" name="action" value="toggle_active">
                    <input type="hidden" name="plan_id" value="<?= $plan['id'] ?>">
                    <button type="submit" class="text-xs font-semibold px-3 py-1.5 rounded-lg border border-gray-200 dark:border-slate-600 text-gray-600 dark:text-slate-300 hover:bg-gray-100 dark:hover:bg-slate-700 transition">
                        <?= $plan['is_active'] ? 'Nonaktifkan' : 'Aktifkan' ?>
                    </button>
                </form>

                <div class="flex items-center gap-1">
                    <button @click="modalMode = 'edit'; editData = <?= htmlspecialchars(json_encode($plan), ENT_QUOTES, 'UTF-8') ?>; modalOpen = true;"
                            class="p-2 text-blue-600 dark:text-blue-400 hover:bg-blue-50 dark:hover:bg-slate-700 rounded-lg transition" title="Edit Paket">
                        <i class="ph ph-note-pencil text-lg"></i>
                    </button>

                    <form method="POST" onsubmit="return confirm('Yakin ingin menghapus paket ini?');" class="inline">
                        <?= PahamFin_csrf_field() ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="plan_id" value="<?= $plan['id'] ?>">
                        <button type="submit" class="p-2 text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-slate-700 rounded-lg transition" title="Hapus Paket">
                            <i class="ph ph-trash text-lg"></i>
                        </button>
                    </form>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- Form Berikan Langganan Manual ke User -->
        <div class="glass-card rounded-2xl border border-white/60 dark:border-slate-700/50 overflow-hidden p-5 shadow-sm h-fit">
            <h3 class="text-base font-display font-bold text-ink dark:text-slate-100 mb-1 flex items-center gap-2">
                <i class="ph ph-user-plus text-primary dark:text-blue-400 text-lg"></i> Berikan Akses Langganan
            </h3>
            <p class="text-xs text-gray-500 dark:text-slate-400 mb-4">Aktifkan langganan untuk pengguna secara manual dari Admin.</p>

            <form method="POST" class="space-y-4">
                <?= PahamFin_csrf_field() ?>
                <input type="hidden" name="action" value="grant_user">

                <div>
                    <label class="block text-xs font-semibold text-gray-700 dark:text-slate-300 mb-1">Pilih Pengguna</label>
                    <select name="target_user_id" required class="w-full bg-white dark:bg-slate-900 border border-gray-200 dark:border-slate-700 rounded-xl px-3 py-2 text-xs focus:ring-primary focus:border-primary">
                        <option value="">-- Pilih User --</option>
                        <?php foreach ($allUsers as $u): ?>
                            <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['name']) ?> (<?= htmlspecialchars($u['email']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-gray-700 dark:text-slate-300 mb-1">Pilih Paket Langganan</label>
                    <select name="grant_plan_id" required class="w-full bg-white dark:bg-slate-900 border border-gray-200 dark:border-slate-700 rounded-xl px-3 py-2 text-xs focus:ring-primary focus:border-primary">
                        <option value="">-- Pilih Paket --</option>
                        <?php foreach ($plans as $p): ?>
                            <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['name']) ?> (Rp <?= number_format($p['price'], 0, ',', '.') ?> - <?= (int)$p['duration_days'] ?> Hari)</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <button type="submit" class="w-full py-2.5 bg-gradient-to-r from-primary to-[#0e7ad6] text-white rounded-xl text-xs font-semibold hover:opacity-90 transition shadow-md shadow-blue-900/10">
                    <i class="ph ph-check-circle text-sm"></i> Aktifkan Langganan Sekarang
                </button>
            </form>
        </div>

        <!-- Tabel Riwayat Pembayaran & Langganan User -->
        <div class="lg:col-span-2 glass-card rounded-2xl border border-white/60 dark:border-slate-700/50 overflow-hidden shadow-sm">
            <div class="p-4 border-b border-gray-100 dark:border-slate-700/50 bg-white/60 dark:bg-slate-800/60 flex items-center justify-between">
                <div>
                    <h3 class="text-base font-display font-bold text-ink dark:text-slate-100">Riwayat Langganan & Pembayaran User</h3>
                    <p class="text-xs text-gray-400">Daftar transaksi pembayaran otomatis pengguna terbaru</p>
                </div>
                <span class="text-xs font-semibold px-2.5 py-1 bg-blue-50 text-primary dark:bg-blue-900/40 dark:text-blue-300 rounded-full">
                    <?= count($subRows) ?> Transaksi
                </span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs text-gray-600 dark:text-slate-300">
                    <thead class="bg-gray-50/50 dark:bg-slate-800/40 text-[11px] font-bold text-gray-400 uppercase tracking-wider border-b border-gray-100 dark:border-slate-700/50">
                        <tr>
                            <th class="p-3">Pengguna</th>
                            <th class="p-3">Paket</th>
                            <th class="p-3">Nominal</th>
                            <th class="p-3">Metode</th>
                            <th class="p-3">Masa Berlaku</th>
                            <th class="p-3">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-slate-700/50">
                        <?php if (empty($subRows)): ?>
                        <tr>
                            <td colspan="6" class="p-6 text-center text-gray-400">Belum ada transaksi langganan pengguna.</td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($subRows as $sub): 
                            $isStillActive = ($sub['status'] === 'ACTIVE' && strtotime($sub['expires_at']) > time());
                        ?>
                        <tr class="hover:bg-gray-50/50 dark:hover:bg-slate-800/30">
                            <td class="p-3 font-semibold text-ink dark:text-slate-100">
                                <div><?= htmlspecialchars($sub['user_name']) ?></div>
                                <div class="text-[10px] text-gray-400 font-normal"><?= htmlspecialchars($sub['user_email']) ?></div>
                            </td>
                            <td class="p-3 font-medium"><?= htmlspecialchars($sub['plan_name']) ?></td>
                            <td class="p-3 font-bold text-primary dark:text-blue-400">Rp <?= number_format($sub['amount'], 0, ',', '.') ?></td>
                            <td class="p-3"><span class="px-2 py-0.5 rounded bg-gray-100 dark:bg-slate-700 font-mono text-[10px]"><?= htmlspecialchars($sub['payment_method']) ?></span></td>
                            <td class="p-3 whitespace-nowrap">
                                <div><?= date('d M Y', strtotime($sub['starts_at'])) ?> - <?= date('d M Y', strtotime($sub['expires_at'])) ?></div>
                            </td>
                            <td class="p-3">
                                <?php if ($isStillActive): ?>
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-700 dark:bg-emerald-900/50 dark:text-emerald-300">AKTIF</span>
                                <?php else: ?>
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-gray-100 text-gray-500 dark:bg-slate-700 dark:text-slate-400">EXPIRED</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <!-- Modal Form Tambah / Edit Paket -->
    <div x-show="modalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm" x-cloak>
        <div @click.away="modalOpen = false" class="glass-card w-full max-w-lg rounded-2xl border border-white/60 dark:border-slate-700 shadow-2xl overflow-hidden bg-white dark:bg-slate-900">
            <div class="p-4 border-b border-gray-100 dark:border-slate-700/50 flex items-center justify-between">
                <h3 class="font-display font-bold text-ink dark:text-slate-100 text-base" x-text="modalMode === 'create' ? 'Tambah Paket Langganan Baru' : 'Edit Paket Langganan'"></h3>
                <button @click="modalOpen = false" class="text-gray-400 hover:text-gray-600 dark:hover:text-slate-200">
                    <i class="ph ph-x text-xl"></i>
                </button>
            </div>

            <form method="POST" class="p-5 space-y-4 text-xs">
                <?= PahamFin_csrf_field() ?>
                <input type="hidden" name="action" :value="modalMode === 'create' ? 'create' : 'update'">
                <input type="hidden" name="plan_id" :value="editData.id">

                <div>
                    <label class="block font-semibold text-gray-700 dark:text-slate-300 mb-1">Nama Paket</label>
                    <input type="text" name="name" x-model="editData.name" required class="w-full bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-xl px-3 py-2 text-xs focus:ring-primary focus:border-primary" placeholder="contoh: Paket Pro 1 Bulan">
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block font-semibold text-gray-700 dark:text-slate-300 mb-1">Harga (Rp)</label>
                        <input type="number" name="price" x-model="editData.price" min="0" required class="w-full bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-xl px-3 py-2 text-xs focus:ring-primary focus:border-primary" placeholder="17000">
                    </div>
                    <div>
                        <label class="block font-semibold text-gray-700 dark:text-slate-300 mb-1">Durasi Masa Aktif (Hari)</label>
                        <input type="number" name="duration_days" x-model="editData.duration_days" min="1" required class="w-full bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-xl px-3 py-2 text-xs focus:ring-primary focus:border-primary" placeholder="30">
                    </div>
                </div>

                <div>
                    <label class="block font-semibold text-gray-700 dark:text-slate-300 mb-1">Keterangan Ringkas (Opsional)</label>
                    <input type="text" name="description" x-model="editData.description" class="w-full bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-xl px-3 py-2 text-xs focus:ring-primary focus:border-primary" placeholder="Akses penuh selama durasi langganan">
                </div>

                <div class="flex items-center gap-2 pt-2">
                    <input type="checkbox" name="is_active" id="chk_active" :checked="editData.is_active == 1" class="rounded border-gray-300 text-primary focus:ring-primary">
                    <label for="chk_active" class="font-semibold text-gray-700 dark:text-slate-300">Aktifkan & Tampilkan Paket Ini ke Pengguna</label>
                </div>

                <div class="flex justify-end gap-2 pt-4 border-t border-gray-100 dark:border-slate-700/50">
                    <button type="button" @click="modalOpen = false" class="px-4 py-2 text-gray-500 rounded-xl hover:bg-gray-100 dark:hover:bg-slate-800 transition">Batal</button>
                    <button type="submit" class="px-5 py-2 bg-primary text-white font-semibold rounded-xl hover:bg-[#0e7ad6] transition shadow-md shadow-blue-900/10" x-text="modalMode === 'create' ? 'Simpan Paket' : 'Perbarui Paket'"></button>
                </div>
            </form>
        </div>
    </div>

</div>

<?php require_once __DIR__ . '/../../app/includes/footer.php'; ?>
