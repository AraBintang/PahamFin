<?php
require_once __DIR__ . '/../../app/db.php';
require_once __DIR__ . '/../../app/includes/auth.php';
require_admin($pdo);

require_once __DIR__ . '/../../app/includes/header.php';
require_once __DIR__ . '/../../app/includes/sidebar.php';
?>

<?php
$flash = '';
$flashType = 'ok';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!PahamFin_csrf_verify()) {
        $flash = 'Sesi tidak valid, silakan muat ulang halaman.';
        $flashType = 'error';
    } else {
        $targetId = (int) ($_POST['user_id'] ?? 0);
        $action   = (string) ($_POST['action'] ?? '');

        if ($targetId > 0) {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
            $stmt->execute([$targetId]);
            $target = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$target) {
                $flash = 'Pengguna tidak ditemukan.';
                $flashType = 'error';
            } else {
                if ($action === 'change_password') {
                    $newPass = trim($_POST['new_password'] ?? '');
                    if (strlen($newPass) < 6) {
                        $flash = 'Password minimal 6 karakter.';
                        $flashType = 'error';
                    } else {
                        $hash = password_hash($newPass, PASSWORD_DEFAULT);
                        $upd = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                        $upd->execute([$hash, $targetId]);
                        $flash = 'Password pengguna "' . htmlspecialchars($target['name']) . '" berhasil diubah!';
                    }
                } elseif ($action === 'manage_subscription') {
                    $planId = (int) ($_POST['plan_id'] ?? 0);
                    $startsAt = trim($_POST['starts_at'] ?? date('Y-m-d H:i:s'));
                    $expiresAt = trim($_POST['expires_at'] ?? date('Y-m-d H:i:s', strtotime('+30 days')));

                    if (strlen($startsAt) === 10) $startsAt .= ' 00:00:00';
                    if (strlen($expiresAt) === 10) $expiresAt .= ' 23:59:59';

                    $res = PahamFin_admin_set_user_subscription($pdo, $targetId, $planId, $startsAt, $expiresAt, 'ADMIN_MANUAL');
                    if ($res['success']) {
                        $flash = 'Layanan langganan "' . htmlspecialchars($target['name']) . '" berhasil diperbarui (s/d ' . date('d M Y', strtotime($expiresAt)) . ')!';
                    } else {
                        $flash = $res['message'];
                        $flashType = 'error';
                    }
                } elseif (in_array($action, ['promote', 'demote', 'delete'], true)) {
                    if ($targetId === (int) current_user_id()) {
                        $flash = 'Tidak bisa mengubah status user Anda sendiri.';
                        $flashType = 'error';
                    } else {
                        if ($action === 'promote') {
                            $upd = $pdo->prepare("UPDATE users SET role = 'admin' WHERE id = ?");
                            $upd->execute([$targetId]);
                            $flash = 'User "' . htmlspecialchars($target['name']) . '" dijadikan admin.';
                        } elseif ($action === 'demote') {
                            if (PahamFin_user_role(['email' => $target['email'], 'role' => '']) === 'admin') {
                                $flash = 'Email ini tercantum di PahamFin_ADMIN_EMAILS (config), tidak bisa dicabut status adminnya.';
                                $flashType = 'error';
                            } else {
                                $upd = $pdo->prepare("UPDATE users SET role = 'user' WHERE id = ?");
                                $upd->execute([$targetId]);
                                $flash = 'Status admin "' . htmlspecialchars($target['name']) . '" dicabut.';
                            }
                        } elseif ($action === 'delete') {
                            $del = $pdo->prepare("DELETE FROM users WHERE id = ?");
                            $del->execute([$targetId]);
                            $flash = 'User "' . htmlspecialchars($target['name']) . '" beserta datanya dihapus.';
                        }
                    }
                }
            }
        }
    }
}

// Ambil paket langganan untuk modal tambah layanan
$plans = PahamFin_get_subscription_plans($pdo, false);

$q = trim((string) ($_GET['q'] ?? ''));
$roleFilter = trim((string) ($_GET['role'] ?? ''));
$params = [];
$where = [];
if ($q !== '') {
    $where[] = "(u.name LIKE ? OR u.email LIKE ? OR u.phone_number LIKE ?)";
    $like = '%' . $q . '%';
    $params[] = $like; $params[] = $like; $params[] = $like;
}
if (in_array($roleFilter, ['admin', 'user'], true)) {
    $where[] = "LOWER(u.role) = ?";
    $params[] = $roleFilter;
}

$nowStr = date('Y-m-d H:i:s');
$sql = "
    SELECT u.*, 
           s.starts_at AS sub_starts, 
           s.expires_at AS sub_expires, 
           s.status AS sub_status, 
           s.payment_method AS sub_payment,
           p.id AS sub_plan_id,
           p.name AS sub_plan_name
    FROM users u
    LEFT JOIN user_subscriptions s ON s.user_id = u.id AND s.status = 'ACTIVE' AND s.expires_at >= '$nowStr'
    LEFT JOIN subscription_plans p ON s.plan_id = p.id
";
if (count($where) > 0) {
    $sql .= " WHERE " . implode(' AND ', $where);
}
$sql .= " ORDER BY u.created_at DESC, u.id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalFiltered = count($users);
$adminTotal = (int) ($pdo->query("SELECT COUNT(*) FROM users WHERE LOWER(role) = 'admin'")->fetchColumn() ?? 0);
?>

<div class="flex flex-wrap items-center justify-between gap-3">
    <div>
        <h3 class="text-lg lg:text-xl font-display font-bold text-ink dark:text-slate-100">Manajemen Pengguna</h3>
        <p class="text-sm text-gray-400">Kelola akun pengguna PahamFin, jadikan admin, atau hapus akun.</p>
    </div>
    <div class="flex items-center gap-2 text-sm">
        <span class="text-xs text-gray-400"><?= $adminTotal ?> admin · <?= $totalFiltered ?> pengguna</span>
    </div>
</div>

<?php if ($flash !== ''): ?>
    <div class="flex items-start gap-2 p-3 rounded-xl text-sm <?= $flashType === 'error' ? 'bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-800/50 text-red-700 dark:text-red-300' : 'bg-emerald-50 dark:bg-emerald-900/30 border border-emerald-200 dark:border-emerald-800/50 text-emerald-700 dark:text-emerald-300' ?>">
        <i class="ph <?= $flashType === 'error' ? 'ph-warning-circle mt-0.5' : 'ph-check-circle mt-0.5' ?>"></i>
        <span><?= $flash ?></span>
    </div>
<?php endif; ?>

<!-- Filter & pencarian -->
<div class="glass-card rounded-2xl shadow-md shadow-blue-900/5 border border-white/60 p-4">
    <form method="GET" class="flex flex-wrap items-center gap-3">
        <div class="relative flex-1 min-w-[220px]">
            <i class="ph ph-magnifying-glass absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400"></i>
            <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Cari nama, email, atau nomor WA..."
                   class="w-full border border-gray-200 dark:border-slate-700 rounded-xl pl-11 pr-4 py-2.5 text-sm focus:ring-2 focus:ring-primary/30 focus:border-primary outline-none transition">
        </div>
        <select name="role" class="border border-gray-200 dark:border-slate-700 rounded-xl px-3 py-2.5 text-sm text-gray-600 dark:text-slate-400 focus:outline-none focus:ring-2 focus:ring-primary/30">
            <option value="">Semua status</option>
            <option value="admin" <?= $roleFilter === 'admin' ? 'selected' : '' ?>>Admin</option>
            <option value="user" <?= $roleFilter === 'user' ? 'selected' : '' ?>>User</option>
        </select>
        <button type="submit" class="px-4 py-2.5 bg-primary text-white rounded-xl text-sm font-semibold hover:bg-[#0e7ad6] transition shadow-md shadow-blue-900/10">Cari</button>
        <a href="users.php" class="px-4 py-2.5 border border-gray-200 dark:border-slate-700 rounded-xl text-sm font-semibold text-gray-500 dark:text-slate-400 hover:bg-gray-50 dark:bg-slate-700/50 transition">Reset</a>
    </form>
</div>

<div x-data="{
    showConfirm: false,
    confirmAction: '',
    confirmUserId: null,
    confirmTitle: '',
    confirmDesc: '',
    confirmBtnText: '',
    confirmBtnClass: '',
    confirmIconClass: '',
    confirmIconBg: '',

    showPasswordModal: false,
    passUserId: null,
    passUserName: '',

    showSubModal: false,
    subUserId: null,
    subUserName: '',
    subPlanId: '',
    subStartsAt: '<?= date('Y-m-d') ?>',
    subExpiresAt: '<?= date('Y-m-d', strtotime('+30 days')) ?>',

    openConfirm(action, userId, title, desc, btnText, btnClass, iconClass, iconBg) {
        this.confirmAction = action;
        this.confirmUserId = userId;
        this.confirmTitle = title;
        this.confirmDesc = desc;
        this.confirmBtnText = btnText;
        this.confirmBtnClass = btnClass;
        this.confirmIconClass = iconClass;
        this.confirmIconBg = iconBg;
        this.showConfirm = true;
    },

    openPasswordModal(userId, userName) {
        this.passUserId = userId;
        this.passUserName = userName;
        this.showPasswordModal = true;
    },

    openSubModal(userId, userName, currentPlanId, currentExpires) {
        this.subUserId = userId;
        this.subUserName = userName;
        this.subPlanId = currentPlanId || '1';
        if (currentExpires) {
            this.subExpiresAt = currentExpires.substring(0, 10);
        } else {
            let d = new Date();
            d.setDate(d.getDate() + 30);
            this.subExpiresAt = d.toISOString().substring(0, 10);
        }
        this.showSubModal = true;
    }
}">

<!-- Tabel pengguna -->
<div class="glass-card rounded-2xl shadow-md shadow-blue-900/5 border border-white/60 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse min-w-[880px]">
            <thead>
                <tr class="text-gray-400 text-xs uppercase tracking-wider bg-white/60 dark:bg-slate-800/60">
                    <th class="p-4 font-medium">Pengguna</th>
                    <th class="p-4 font-medium">Nomor WA</th>
                    <th class="p-4 font-medium">Layanan / Langganan</th>
                    <th class="p-4 font-medium">Terdaftar</th>
                    <th class="p-4 font-medium">Role</th>
                    <th class="p-4 font-medium text-right">Aksi</th>
                </tr>
            </thead>
            <tbody class="text-sm divide-y divide-gray-50 dark:divide-slate-700/50">
                <?php if (count($users) === 0): ?>
                    <tr><td colspan="6" class="p-10 text-center text-gray-400">Tidak ada pengguna yang cocok.</td></tr>
                <?php endif; ?>
                <?php foreach ($users as $u):
                    $isSelf = ((int) $u['id'] === (int) current_user_id());
                    $isAdminRole = strtolower((string) ($u['role'] ?? 'user')) === 'admin';
                    $isConfigAdmin = PahamFin_user_role(['email' => $u['email'] ?? '', 'role' => '']) === 'admin';
                    $escapedName = htmlspecialchars($u['name'], ENT_QUOTES, 'UTF-8');

                    $hasSub = !empty($u['sub_plan_name']) && !empty($u['sub_expires']) && strtotime($u['sub_expires']) >= time();
                    $daysLeft = $hasSub ? ceil((strtotime($u['sub_expires']) - time()) / 86400) : 0;
                ?>
                <tr class="hover:bg-white/80 dark:bg-slate-800/80 transition-colors">
                    <td class="p-4">
                        <span class="flex items-center gap-3">
                            <span class="w-9 h-9 rounded-full <?= $isAdminRole ? 'bg-amber-100 dark:bg-amber-900/50 text-amber-600 dark:text-amber-400' : 'bg-blue-50 dark:bg-blue-900/30 text-primary dark:text-blue-400' ?> flex items-center justify-center shrink-0">
                                <i class="ph <?= $isAdminRole ? 'ph-shield-check' : 'ph-user' ?>"></i>
                            </span>
                            <span>
                                <span class="font-semibold text-ink dark:text-slate-100 flex items-center gap-1.5">
                                    <?= htmlspecialchars($u['name']) ?>
                                    <?php if ($isSelf): ?><span class="text-[9px] font-bold uppercase bg-blue-100 dark:bg-blue-900/50 text-primary dark:text-blue-400 px-1.5 py-0.5 rounded-full">Anda</span><?php endif; ?>
                                    <?php if ($isConfigAdmin && !$isAdminRole): ?><span class="text-[9px] font-bold uppercase bg-indigo-100 dark:bg-indigo-900/30 text-indigo-600 dark:text-indigo-400 px-1.5 py-0.5 rounded-full">Email admin</span><?php endif; ?>
                                </span>
                                <span class="block text-xs text-gray-400"><?= htmlspecialchars($u['email']) ?></span>
                            </span>
                        </span>
                    </td>
                    <td class="p-4 text-gray-500 dark:text-slate-400"><?= htmlspecialchars($u['phone_number'] ?: '-') ?></td>

                    <!-- Kolom Layanan & Jatuh Tempo -->
                    <td class="p-4">
                        <?php if ($isAdminRole): ?>
                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-bold bg-amber-100 dark:bg-amber-900/40 text-amber-800 dark:text-amber-300">
                                <i class="ph ph-crown"></i> Admin (Bypass)
                            </span>
                        <?php elseif ($hasSub): ?>
                            <div>
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-extrabold bg-emerald-100 dark:bg-emerald-900/40 text-emerald-800 dark:text-emerald-300">
                                    <i class="ph ph-check-circle"></i> <?= htmlspecialchars($u['sub_plan_name']) ?>
                                </span>
                                <div class="text-[11px] text-gray-500 dark:text-slate-400 mt-1">
                                    Jatuh tempo: <b><?= date('d M Y', strtotime($u['sub_expires'])) ?></b>
                                    <span class="text-emerald-600 dark:text-emerald-400 font-bold">(sisa <?= $daysLeft ?> hari)</span>
                                </div>
                            </div>
                        <?php else: ?>
                            <div>
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-rose-100 dark:bg-rose-900/40 text-rose-700 dark:text-rose-300">
                                    <i class="ph ph-x-circle"></i> Non-Aktif / Kedaluwarsa
                                </span>
                            </div>
                        <?php endif; ?>
                    </td>

                    <td class="p-4 text-gray-500 dark:text-slate-400 whitespace-nowrap"><?= htmlspecialchars(date('d M Y', strtotime($u['created_at']))) ?></td>
                    <td class="p-4">
                        <?php if ($isAdminRole): ?>
                            <span class="text-[10px] font-bold uppercase bg-amber-100 dark:bg-amber-900/50 text-amber-700 dark:text-amber-300 px-2 py-1 rounded-full">Admin</span>
                        <?php else: ?>
                            <span class="text-[10px] font-bold uppercase bg-gray-100 dark:bg-slate-700 text-gray-500 dark:text-slate-400 px-2 py-1 rounded-full">User</span>
                        <?php endif; ?>
                    </td>
                    <td class="p-4">
                        <div class="flex items-center justify-end gap-1.5 flex-wrap">
                            <!-- Tombol Kelola Layanan / Langganan -->
                            <button type="button"
                                @click="openSubModal(<?= (int)$u['id'] ?>, <?= htmlspecialchars(json_encode($u['name']), ENT_QUOTES, 'UTF-8') ?>, '<?= (int)($u['sub_plan_id'] ?? 1) ?>', '<?= htmlspecialchars($u['sub_expires'] ?? '') ?>')"
                                class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg text-xs font-semibold bg-sky-50 dark:bg-sky-900/30 text-sky-700 dark:text-sky-300 border border-sky-200 dark:border-sky-800/50 hover:bg-sky-100 transition"
                                title="Tambah / Atur Masa Langganan">
                                <i class="ph ph-calendar-plus"></i> Layanan
                            </button>

                            <!-- Tombol Ganti Password -->
                            <button type="button"
                                @click="openPasswordModal(<?= (int)$u['id'] ?>, <?= htmlspecialchars(json_encode($u['name']), ENT_QUOTES, 'UTF-8') ?>)"
                                class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg text-xs font-semibold bg-amber-50 dark:bg-amber-900/30 text-amber-700 dark:text-amber-300 border border-amber-200 dark:border-amber-800/50 hover:bg-amber-100 transition"
                                title="Ubah Password User">
                                <i class="ph ph-key"></i> Password
                            </button>
                            <?php if (!$isSelf): ?>
                                <?php if ($isAdminRole): ?>
                                    <button type="button" 
                                        @click="openConfirm('demote', <?= (int)$u['id'] ?>, 'Cabut Status Admin?', <?= htmlspecialchars(json_encode('Cabut hak akses admin dari ' . $u['name'] . '?'), ENT_QUOTES, 'UTF-8') ?>, 'Ya, Cabut Admin', 'bg-slate-700 hover:bg-slate-800 text-white', 'ph-user-minus text-slate-600 dark:text-slate-300', 'bg-slate-100 dark:bg-slate-700')"
                                        class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg text-xs font-semibold border border-gray-200 dark:border-slate-700 text-gray-500 dark:text-slate-400 hover:bg-gray-50 dark:bg-slate-700/50 transition">
                                        <i class="ph ph-user-minus"></i> User-kan
                                    </button>
                                <?php else: ?>
                                    <button type="button"
                                        @click="openConfirm('promote', <?= (int)$u['id'] ?>, 'Jadikan Admin?', <?= htmlspecialchars(json_encode('Jadikan ' . $u['name'] . ' sebagai admin dengan akses penuh ke Panel Admin PahamFin?'), ENT_QUOTES, 'UTF-8') ?>, 'Ya, Jadikan Admin', 'bg-amber-600 hover:bg-amber-700 text-white', 'ph-shield-plus text-amber-600 dark:text-amber-400', 'bg-amber-100 dark:bg-amber-900/40')"
                                        class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg text-xs font-semibold border border-amber-200 dark:border-amber-800/50 text-amber-700 dark:text-amber-300 bg-amber-50 dark:bg-amber-900/30 hover:bg-amber-100 dark:bg-amber-900/50 transition">
                                        <i class="ph ph-shield-plus"></i> Jadikan Admin
                                    </button>
                                <?php endif; ?>
                                <button type="button"
                                    @click="openConfirm('delete', <?= (int)$u['id'] ?>, <?= htmlspecialchars(json_encode('Hapus ' . $u['name'] . '?'), ENT_QUOTES, 'UTF-8') ?>, <?= htmlspecialchars(json_encode('Hapus ' . $u['name'] . ' beserta semua data transaksinya? Aksi ini tidak dapat dibatalkan.'), ENT_QUOTES, 'UTF-8') ?>, 'Ya, Hapus Pengguna', 'bg-rose-600 hover:bg-rose-700 text-white', 'ph-trash text-rose-600 dark:text-rose-400', 'bg-rose-100 dark:bg-rose-900/40')"
                                    class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg text-xs font-semibold border border-rose-200 dark:border-rose-800/50 text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:bg-rose-900/30 transition">
                                    <i class="ph ph-trash"></i> Hapus
                                </button>
                            <?php else: ?>
                                <span class="text-xs text-gray-300">—</span>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Pop-Up Konfirmasi UI (Replaces Browser Confirm) -->
<div x-show="showConfirm" x-transition.opacity class="fixed inset-0 z-50 flex items-center justify-center p-4">
    <div @click="showConfirm = false" class="absolute inset-0 bg-black/50 backdrop-blur-sm"></div>
    <div class="relative glass-card border border-white/60 dark:border-slate-700/50 dark:bg-slate-800/90 rounded-2xl shadow-2xl backdrop-blur-xl w-full max-w-sm p-6 z-10 text-center">
        <div :class="confirmIconBg" class="w-14 h-14 rounded-full flex items-center justify-center mx-auto mb-4">
            <i class="ph text-2xl" :class="[confirmIconClass]"></i>
        </div>
        <h3 class="text-lg font-bold text-gray-900 dark:text-slate-100 mb-2" x-text="confirmTitle"></h3>
        <p class="text-sm text-gray-500 dark:text-slate-400 mb-6 leading-relaxed" x-text="confirmDesc"></p>
        <div class="flex gap-3 justify-center">
            <button type="button" @click="showConfirm = false" class="flex-1 px-4 py-2.5 text-gray-600 dark:text-slate-400 bg-gray-100 hover:bg-gray-200 dark:bg-slate-700 rounded-xl font-medium transition">
                Batal
            </button>
            <form method="POST" class="flex-1">
                <?= PahamFin_csrf_field() ?>
                <input type="hidden" name="user_id" :value="confirmUserId">
                <input type="hidden" name="action" :value="confirmAction">
                <button type="submit" class="w-full px-4 py-2.5 rounded-xl font-semibold shadow transition" :class="confirmBtnClass" x-text="confirmBtnText">
                </button>
            </form>
        </div>
    </div>
</div>

<!-- Modal Reset Password -->
<div x-show="showPasswordModal" x-transition.opacity class="fixed inset-0 z-50 flex items-center justify-center p-4" style="display:none;">
    <div @click="showPasswordModal = false" class="absolute inset-0 bg-black/50 backdrop-blur-sm"></div>
    <div class="relative glass-card border border-white/60 dark:border-slate-700/50 dark:bg-slate-800/95 rounded-3xl shadow-2xl backdrop-blur-xl w-full max-w-md p-6 z-10">
        <div class="flex items-center justify-between pb-4 border-b border-gray-100 dark:border-slate-700/50 mb-4">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-2xl bg-amber-100 dark:bg-amber-900/40 text-amber-600 dark:text-amber-400 flex items-center justify-center">
                    <i class="ph ph-key text-xl"></i>
                </div>
                <div>
                    <h3 class="font-bold text-base text-gray-900 dark:text-slate-100">Ganti Password User</h3>
                    <p class="text-xs text-gray-400" x-text="passUserName"></p>
                </div>
            </div>
            <button @click="showPasswordModal = false" class="text-gray-400 hover:text-gray-600 p-1">
                <i class="ph ph-x text-xl"></i>
            </button>
        </div>

        <form method="POST" class="space-y-4">
            <?= PahamFin_csrf_field() ?>
            <input type="hidden" name="user_id" :value="passUserId">
            <input type="hidden" name="action" value="change_password">

            <div>
                <label class="block text-xs font-semibold text-gray-700 dark:text-slate-300 mb-1.5">Password Baru</label>
                <input type="password" name="new_password" required minlength="6" placeholder="Masukkan password baru..."
                       class="w-full px-4 py-2.5 bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-xl text-sm">
                <p class="text-[11px] text-gray-400 mt-1">Minimal 6 karakter.</p>
            </div>

            <div class="flex gap-3 pt-2">
                <button type="button" @click="showPasswordModal = false" class="flex-1 px-4 py-2.5 bg-gray-100 dark:bg-slate-700 text-gray-600 dark:text-slate-300 rounded-xl text-xs font-bold hover:bg-gray-200 transition">
                    Batal
                </button>
                <button type="submit" class="flex-1 px-4 py-2.5 bg-amber-500 hover:bg-amber-600 text-white rounded-xl text-xs font-bold shadow-md shadow-amber-500/20 transition">
                    Simpan Password Baru
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Tambah / Kelola Layanan (Langganan & Jatuh Tempo) -->
<div x-show="showSubModal" x-transition.opacity class="fixed inset-0 z-50 flex items-center justify-center p-4" style="display:none;">
    <div @click="showSubModal = false" class="absolute inset-0 bg-black/50 backdrop-blur-sm"></div>
    <div class="relative glass-card border border-white/60 dark:border-slate-700/50 dark:bg-slate-800/95 rounded-3xl shadow-2xl backdrop-blur-xl w-full max-w-md p-6 z-10">
        <div class="flex items-center justify-between pb-4 border-b border-gray-100 dark:border-slate-700/50 mb-4">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-2xl bg-sky-100 dark:bg-sky-900/40 text-sky-600 dark:text-sky-400 flex items-center justify-center">
                    <i class="ph ph-calendar-plus text-xl"></i>
                </div>
                <div>
                    <h3 class="font-bold text-base text-gray-900 dark:text-slate-100">Atur Layanan Langganan</h3>
                    <p class="text-xs text-gray-400" x-text="subUserName"></p>
                </div>
            </div>
            <button @click="showSubModal = false" class="text-gray-400 hover:text-gray-600 p-1">
                <i class="ph ph-x text-xl"></i>
            </button>
        </div>

        <form method="POST" class="space-y-4">
            <?= PahamFin_csrf_field() ?>
            <input type="hidden" name="user_id" :value="subUserId">
            <input type="hidden" name="action" value="manage_subscription">

            <div>
                <label class="block text-xs font-semibold text-gray-700 dark:text-slate-300 mb-1.5">Pilih Paket Langganan</label>
                <select name="plan_id" x-model="subPlanId" required class="w-full px-4 py-2.5 bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-xl text-xs font-medium">
                    <?php foreach ($plans as $p): ?>
                        <option value="<?= $p['id'] ?>">
                            <?= htmlspecialchars($p['name']) ?> (Rp <?= number_format($p['price'], 0, ',', '.') ?> - <?= $p['duration_days'] ?> Hari)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-gray-700 dark:text-slate-300 mb-1.5">Tanggal Mulai</label>
                    <input type="date" name="starts_at" x-model="subStartsAt" required
                           class="w-full px-3 py-2 bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-xl text-xs font-medium">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-700 dark:text-slate-300 mb-1.5">Jatuh Tempo (Expired)</label>
                    <input type="date" name="expires_at" x-model="subExpiresAt" required
                           class="w-full px-3 py-2 bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-xl text-xs font-medium">
                </div>
            </div>

            <p class="text-[11px] text-gray-400 leading-relaxed">
                Menyimpan form ini akan mengaktifkan layanan untuk pengguna dan memperbarui tanggal jatuh tempo langganan.
            </p>

            <div class="flex gap-3 pt-2">
                <button type="button" @click="showSubModal = false" class="flex-1 px-4 py-2.5 bg-gray-100 dark:bg-slate-700 text-gray-600 dark:text-slate-300 rounded-xl text-xs font-bold hover:bg-gray-200 transition">
                    Batal
                </button>
                <button type="submit" class="flex-1 px-4 py-2.5 bg-sky-600 hover:bg-sky-700 text-white rounded-xl text-xs font-bold shadow-md shadow-sky-600/20 transition flex items-center justify-center gap-1.5">
                    <i class="ph ph-check-circle"></i> Simpan Layanan
                </button>
            </div>
        </form>
    </div>
</div>

</div>

<?php require_once __DIR__ . '/../../app/includes/footer.php'; ?>







