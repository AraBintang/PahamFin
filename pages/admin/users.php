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

        if ($targetId > 0 && in_array($action, ['promote', 'demote', 'delete'], true)) {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
            $stmt->execute([$targetId]);
            $target = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$target) {
                $flash = 'Pengguna tidak ditemukan.';
                $flashType = 'error';
            } elseif ($targetId === (int) current_user_id()) {
                $flash = 'Tidak bisa mengubah status user Anda sendiri.';
                $flashType = 'error';
            } else {
                if ($action === 'promote') {
                    $upd = $pdo->prepare("UPDATE users SET role = 'admin' WHERE id = ?");
                    $upd->execute([$targetId]);
                    $flash = 'User "' . htmlspecialchars($target['name']) . '" dijadikan admin.';
                } elseif ($action === 'demote') {
                    // Cegah demote jika email admin tercantum di config.
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

$q = trim((string) ($_GET['q'] ?? ''));
$roleFilter = trim((string) ($_GET['role'] ?? ''));
$params = [];
$where = [];
if ($q !== '') {
    $where[] = "(name LIKE ? OR email LIKE ? OR phone_number LIKE ?)";
    $like = '%' . $q . '%';
    $params[] = $like; $params[] = $like; $params[] = $like;
}
if (in_array($roleFilter, ['admin', 'user'], true)) {
    $where[] = "LOWER(role) = ?";
    $params[] = $roleFilter;
}
$sql = "SELECT * FROM users";
if (count($where) > 0) {
    $sql .= " WHERE " . implode(' AND ', $where);
}
$sql .= " ORDER BY created_at DESC, id DESC";

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

<!-- Tabel pengguna -->
<div class="glass-card rounded-2xl shadow-md shadow-blue-900/5 border border-white/60 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse min-w-[760px]">
            <thead>
                <tr class="text-gray-400 text-xs uppercase tracking-wider bg-white/60 dark:bg-slate-800/60">
                    <th class="p-4 font-medium">Pengguna</th>
                    <th class="p-4 font-medium">Nomor WA</th>
                    <th class="p-4 font-medium">Telegram</th>
                    <th class="p-4 font-medium">Terdaftar</th>
                    <th class="p-4 font-medium">Status</th>
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
                ?>
                <tr class="hover:bg-white/80 dark:bg-slate-800/80 transition-colors">
                    <td class="p-4">
                        <span class="flex items-center gap-3">
                            <span class="w-9 h-9 rounded-full <?= $isAdminRole ? 'bg-amber-100 dark:bg-amber-900/50 text-amber-600 dark:text-amber-400' : 'bg-blue-50 dark:bg-blue-900/30 text-primary dark:text-blue-400' ?> flex items-center justify-center">
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
                    <td class="p-4 text-gray-500 dark:text-slate-400"><?= htmlspecialchars($u['telegram_id'] ?: '-') ?></td>
                    <td class="p-4 text-gray-500 dark:text-slate-400 whitespace-nowrap"><?= htmlspecialchars(date('d M Y', strtotime($u['created_at']))) ?></td>
                    <td class="p-4">
                        <?php if ($isAdminRole): ?>
                            <span class="text-[10px] font-bold uppercase bg-amber-100 dark:bg-amber-900/50 text-amber-700 dark:text-amber-300 px-2 py-1 rounded-full">Admin</span>
                        <?php else: ?>
                            <span class="text-[10px] font-bold uppercase bg-gray-100 dark:bg-slate-700 text-gray-500 dark:text-slate-400 px-2 py-1 rounded-full">User</span>
                        <?php endif; ?>
                    </td>
                    <td class="p-4">
                        <div class="flex items-center justify-end gap-2">
                            <?php if (!$isSelf): ?>
                                <?php if ($isAdminRole): ?>
                                    <form method="POST" onsubmit="return confirm('Cabut status admin dari <?= htmlspecialchars(addslashes($u['name'])) ?>?');">
                                        <?= PahamFin_csrf_field() ?>
                                        <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                                        <input type="hidden" name="action" value="demote">
                                        <button class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg text-xs font-semibold border border-gray-200 dark:border-slate-700 text-gray-500 dark:text-slate-400 hover:bg-gray-50 dark:bg-slate-700/50 transition">
                                            <i class="ph ph-user-minus"></i> User-kan
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <form method="POST" onsubmit="return confirm('Jadikan ' + this.getAttribute('data-name') + ' sebagai admin?');" data-name="<?= htmlspecialchars(addslashes($u['name'])) ?>">
                                        <?= PahamFin_csrf_field() ?>
                                        <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                                        <input type="hidden" name="action" value="promote">
                                        <button class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg text-xs font-semibold border border-amber-200 dark:border-amber-800/50 text-amber-700 dark:text-amber-300 bg-amber-50 dark:bg-amber-900/30 hover:bg-amber-100 dark:bg-amber-900/50 transition">
                                            <i class="ph ph-shield-plus"></i> Jadikan Admin
                                        </button>
                                    </form>
                                <?php endif; ?>
                                <form method="POST" onsubmit="return confirm('Hapus <?= htmlspecialchars(addslashes($u['name'])) ?> beserta semua data transaksinya? Aksi ini tidak bisa dibatalkan.');">
                                    <?= PahamFin_csrf_field() ?>
                                    <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <button class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg text-xs font-semibold border border-rose-200 dark:border-rose-800/50 text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:bg-rose-900/30 transition">
                                        <i class="ph ph-trash"></i> Hapus
                                    </button>
                                </form>
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

<?php require_once __DIR__ . '/../../app/includes/footer.php'; ?>







