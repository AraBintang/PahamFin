<?php
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/includes/config.php';
require_once __DIR__ . '/../app/includes/auth.php';
require_login();
$user_id = current_user_id();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!PahamFin_csrf_verify()) {
        $_SESSION['flash_msg'] = 'Sesi tidak valid, coba lagi.';
        $_SESSION['flash_type'] = 'error';
        header('Location: settings.php');
        exit;
    }

    $name = trim((string) ($_POST['name'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $phone = trim((string) ($_POST['phone_number'] ?? ''));
    $telegramId = trim((string) ($_POST['telegram_id'] ?? ''));

    if ($name === '' || $email === '') {
        $_SESSION['flash_msg'] = 'Nama dan Email wajib diisi.';
        $_SESSION['flash_type'] = 'error';
        header('Location: settings.php');
        exit;
    }

    // Handle profile pic upload
    $profilePicPath = null;
    if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['profile_pic']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
            $uploadDir = __DIR__ . '/../image/profiles/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
            $fileName = 'user_' . $user_id . '_' . time() . '.' . $ext;
            if (move_uploaded_file($_FILES['profile_pic']['tmp_name'], $uploadDir . $fileName)) {
                $profilePicPath = $fileName;
            }
        }
    }

    // Handle Password Change
    $current = (string) ($_POST['current_password'] ?? '');
    $newPass = (string) ($_POST['new_password'] ?? '');
    $confirm = (string) ($_POST['confirm_password'] ?? '');
    
    $passwordChanged = false;
    if ($current !== '' || $newPass !== '' || $confirm !== '') {
        $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$user_id]);
        $hash = $stmt->fetchColumn();

        if (!password_verify($current, $hash)) {
            $_SESSION['flash_msg'] = 'Password saat ini salah.';
            $_SESSION['flash_type'] = 'error';
            header('Location: settings.php');
            exit;
        }
        if (strlen($newPass) < 6) {
            $_SESSION['flash_msg'] = 'Password baru minimal 6 karakter.';
            $_SESSION['flash_type'] = 'error';
            header('Location: settings.php');
            exit;
        }
        if ($newPass !== $confirm) {
            $_SESSION['flash_msg'] = 'Konfirmasi password tidak cocok.';
            $_SESSION['flash_type'] = 'error';
            header('Location: settings.php');
            exit;
        }
        $passwordChanged = true;
    }

    try {
        $phoneVal = ($phone !== '') ? $phone : null;
        $telegramVal = ($telegramId !== '') ? $telegramId : null;

        if ($profilePicPath) {
            $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, phone_number = ?, telegram_id = ?, profile_pic = ? WHERE id = ?");
            $stmt->execute([$name, $email, $phoneVal, $telegramVal, $profilePicPath, $user_id]);
        } else {
            $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, phone_number = ?, telegram_id = ? WHERE id = ?");
            $stmt->execute([$name, $email, $phoneVal, $telegramVal, $user_id]);
        }

        if ($passwordChanged) {
            $upd = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $upd->execute([password_hash($newPass, PASSWORD_DEFAULT), $user_id]);
        }

        $_SESSION['flash_msg'] = 'Pengaturan berhasil disimpan.';
        $_SESSION['flash_type'] = 'success';
    } catch (Throwable $e) {
        $errText = strtolower($e->getMessage());
        if (strpos($errText, '23000') !== false || strpos($errText, 'duplicate') !== false) {
            if (strpos($errText, 'telegram_id') !== false) {
                $_SESSION['flash_msg'] = '⚠️ ID Telegram ini sudah digunakan oleh akun lain.';
            } elseif (strpos($errText, 'email') !== false) {
                $_SESSION['flash_msg'] = '⚠️ Alamat Email ini sudah digunakan oleh akun lain.';
            } elseif (strpos($errText, 'phone') !== false) {
                $_SESSION['flash_msg'] = '⚠️ Nomor WhatsApp ini sudah digunakan oleh akun lain.';
            } else {
                $_SESSION['flash_msg'] = '⚠️ Email, Nomor WhatsApp, atau ID Telegram sudah digunakan oleh akun lain.';
            }
        } else {
            $_SESSION['flash_msg'] = '⚠️ Gagal menyimpan pengaturan: ' . $e->getMessage();
        }
        $_SESSION['flash_type'] = 'error';
    }

    header('Location: settings.php');
    exit;
}

// Prepare UI data
$userProfileStmt = $pdo->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
$userProfileStmt->execute([$user_id]);
$userProfile = $userProfileStmt->fetch(PDO::FETCH_ASSOC) ?: ['name' => 'Pengguna', 'phone_number' => '', 'telegram_id' => ''];

require_once __DIR__ . '/../app/includes/header.php';
require_once __DIR__ . '/../app/includes/sidebar.php';
?>

<form method="POST" enctype="multipart/form-data" class="space-y-6">
    <?= PahamFin_csrf_field() ?>
    
    <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
        <div>
            <p class="text-xs font-semibold text-primary dark:text-blue-400 uppercase tracking-widest">Konfigurasi Akun</p>
            <h3 class="text-lg lg:text-xl font-display font-bold text-ink dark:text-slate-100 mt-1">Pengaturan Profil</h3>
        </div>
        <button type="submit" class="px-6 py-2.5 bg-gradient-to-r from-primary to-[#0e7ad6] text-white rounded-xl font-semibold shadow-lg shadow-blue-900/20 hover:opacity-90 flex items-center gap-2">
            <i class="ph ph-floppy-disk text-lg"></i> Simpan Semua
        </button>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-[0.9fr_1.1fr] gap-6">
        <!-- Kolom Kiri -->
        <div class="space-y-6">
            <div class="glass-card rounded-2xl shadow-md shadow-blue-900/5 border border-white/60 dark:border-slate-700/50 overflow-hidden">
                <div class="p-4 lg:p-6 border-b border-gray-100 dark:border-slate-700/50 flex justify-between items-center bg-white/60 dark:bg-slate-800/60">
                    <h3 class="text-base lg:text-lg font-display font-semibold text-ink dark:text-slate-100">Profil Pengguna</h3>
                </div>
                <div class="p-4 lg:p-6 space-y-5 bg-white/40 dark:bg-slate-800/40">
                    <div class="flex items-center gap-4">
                        <div class="w-16 h-16 rounded-2xl bg-gradient-to-br from-primary to-[#0e7ad6] text-white flex items-center justify-center text-2xl font-bold shadow-lg shadow-blue-900/10 shrink-0 overflow-hidden">
                            <?php if (!empty($userProfile['profile_pic'])): ?>
                                <img src="../image/profiles/<?= htmlspecialchars($userProfile['profile_pic']) ?>" alt="Profile" class="w-full h-full object-cover">
                            <?php else: ?>
                                <?= mb_strtoupper(mb_substr($userProfile['name'] ?? 'P', 0, 1)) ?>
                            <?php endif; ?>
                        </div>
                        <div class="overflow-hidden">
                            <p class="font-semibold text-ink dark:text-slate-100 truncate"><?= htmlspecialchars($userProfile['name'] ?? 'Pengguna') ?></p>
                            <p class="text-sm text-gray-500 dark:text-slate-400 truncate"><?= htmlspecialchars($userProfile['email'] ?? '') ?></p>
                        </div>
                    </div>

                    <div class="border-t border-gray-100 dark:border-slate-700/50 pt-4 space-y-4 text-sm text-gray-600 dark:text-slate-300">
                        <div class="flex items-center justify-between">
                            <span class="flex items-center gap-2 text-gray-500 dark:text-slate-400"><i class="ph ph-whatsapp-logo text-lg text-green-600 dark:text-green-500 dark:text-green-400"></i> WhatsApp</span>
                            <span class="font-medium text-ink dark:text-slate-200"><?= htmlspecialchars($userProfile['phone_number'] ?: 'Belum diisi') ?></span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="flex items-center gap-2 text-gray-500 dark:text-slate-400"><i class="ph ph-telegram-logo text-lg text-sky-600 dark:text-sky-500"></i> Telegram ID</span>
                            <span class="font-medium text-ink dark:text-slate-200"><?= htmlspecialchars($userProfile['telegram_id'] ?: 'Belum diisi') ?></span>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Ganti Password -->
            <div class="glass-card rounded-2xl shadow-md shadow-blue-900/5 border border-white/60 dark:border-slate-700/50 overflow-hidden">
                <div class="p-4 lg:p-6 border-b border-gray-100 dark:border-slate-700/50 bg-white/60 dark:bg-slate-800/60 flex justify-between items-center">
                    <h3 class="text-base lg:text-lg font-display font-semibold text-ink dark:text-slate-100">Ganti Password</h3>
                </div>
                <div class="p-4 lg:p-6 bg-white/40 dark:bg-slate-800/40 space-y-4">
                    <p class="text-xs text-gray-500 dark:text-slate-400 mb-2">Biarkan kosong jika tidak ingin mengganti password.</p>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1">Password Saat Ini</label>
                        <input type="password" name="current_password" class="w-full bg-white dark:bg-slate-900/50 border border-gray-300 dark:border-slate-600 rounded-xl px-3 py-2 focus:ring-primary focus:border-primary outline-none text-ink dark:text-slate-100">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1">Password Baru</label>
                        <input type="password" name="new_password" minlength="6" class="w-full bg-white dark:bg-slate-900/50 border border-gray-300 dark:border-slate-600 rounded-xl px-3 py-2 focus:ring-primary focus:border-primary outline-none text-ink dark:text-slate-100" placeholder="Minimal 6 karakter">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1">Konfirmasi Password Baru</label>
                        <input type="password" name="confirm_password" class="w-full bg-white dark:bg-slate-900/50 border border-gray-300 dark:border-slate-600 rounded-xl px-3 py-2 focus:ring-primary focus:border-primary outline-none text-ink dark:text-slate-100">
                    </div>
                </div>
            </div>
        </div>

        <!-- Kolom Kanan -->
        <div class="glass-card rounded-2xl shadow-md shadow-blue-900/5 border border-white/60 dark:border-slate-700/50 overflow-hidden h-fit">
            <div class="p-4 lg:p-6 border-b border-gray-100 dark:border-slate-700/50 flex justify-between items-center bg-white/60 dark:bg-slate-800/60">
                <h3 class="text-base lg:text-lg font-display font-semibold text-ink dark:text-slate-100">Edit Profil</h3>
            </div>
            <div class="p-4 lg:p-6 bg-white/40 dark:bg-slate-800/40 space-y-4">
                <div class="flex items-center gap-4 mb-4">
                    <div class="flex-1">
                        <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1">Ganti Foto (Opsional)</label>
                        <input type="file" name="profile_pic" accept="image/*" class="w-full text-sm text-gray-500 dark:text-slate-400 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 dark:file:bg-primary/20 file:text-primary dark:file:text-blue-300 hover:file:bg-blue-100 dark:hover:file:bg-primary/30 cursor-pointer">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1">Nama Lengkap</label>
                    <input type="text" name="name" value="<?= htmlspecialchars($userProfile['name'] ?? '') ?>" required class="w-full bg-white dark:bg-slate-900/50 border border-gray-300 dark:border-slate-600 rounded-xl px-3 py-2 focus:ring-primary focus:border-primary outline-none text-ink dark:text-slate-100">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1">Alamat Email</label>
                    <input type="email" name="email" value="<?= htmlspecialchars($userProfile['email'] ?? '') ?>" required class="w-full bg-white dark:bg-slate-900/50 border border-gray-300 dark:border-slate-600 rounded-xl px-3 py-2 focus:ring-primary focus:border-primary outline-none text-ink dark:text-slate-100" placeholder="nama@email.com">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1">Nomor WhatsApp</label>
                    <input type="text" name="phone_number" value="<?= htmlspecialchars($userProfile['phone_number'] ?? '') ?>" required class="w-full bg-white dark:bg-slate-900/50 border border-gray-300 dark:border-slate-600 rounded-xl px-3 py-2 focus:ring-primary focus:border-primary outline-none text-ink dark:text-slate-100" placeholder="6281234567890">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1">ID Telegram</label>
                    <input type="text" name="telegram_id" value="<?= htmlspecialchars($userProfile['telegram_id'] ?? '') ?>" class="w-full bg-white dark:bg-slate-900/50 border border-gray-300 dark:border-slate-600 rounded-xl px-3 py-2 focus:ring-primary focus:border-primary outline-none text-ink dark:text-slate-100" placeholder="123456789">
                </div>
            </div>
        </div>
    </div>
</form>

<?php require_once __DIR__ . '/../app/includes/footer.php'; ?>
