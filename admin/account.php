<?php

declare(strict_types=1);

define('ADMIN_CONTEXT', true);

require_once __DIR__ . '/../includes/auth.php';
require_login();

$pageTitle = 'Akun Saya';
$sessionUser = current_user() ?? [];
$userId = (int)($sessionUser['id'] ?? 0);

if ($userId <= 0) {
    logout_user();
    redirect('login.php');
}

function admin_account_fetch_user(int $userId): ?array
{
    global $pdo;
    $stmt = $pdo->prepare('SELECT id, name, email, password, role, profile_photo, is_active, created_at FROM users WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $userId]);
    $user = $stmt->fetch();

    return $user ?: null;
}

function admin_account_is_placeholder_email(string $email): bool
{
    $domain = strtolower((string) substr(strrchr($email, '@') ?: '', 1));
    if ($domain === '') {
        return true;
    }

    $blockedDomains = [
        'sekolah.local',
        'localhost',
        'example.com',
        'example.org',
        'example.net',
        'test.com',
        'mail.test',
    ];

    return in_array($domain, $blockedDomains, true)
        || str_ends_with($domain, '.local')
        || str_ends_with($domain, '.test')
        || str_ends_with($domain, '.invalid');
}

function admin_account_verify_password(array $account, string $password): bool
{
    return $password !== '' && password_verify($password, (string)($account['password'] ?? ''));
}

$account = admin_account_fetch_user($userId);
if (!$account || (int)($account['is_active'] ?? 0) !== 1) {
    logout_user();
    redirect('login.php?expired=1');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        flash('error', 'Token keamanan tidak valid. Silakan coba lagi.');
        redirect('account.php');
    }

    $action = (string)($_POST['action'] ?? '');

    if ($action === 'update_profile') {
        $name = clean((string)($_POST['name'] ?? ''));
        $email = strtolower(trim((string)($_POST['email'] ?? '')));
        $currentPassword = (string)($_POST['current_password'] ?? '');

        if ($name === '' || $email === '') {
            flash('error', 'Nama dan email wajib diisi.');
            redirect('account.php');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('error', 'Format email belum valid. Gunakan email admin yang aktif.');
            redirect('account.php');
        }

        if (admin_account_is_placeholder_email($email)) {
            flash('error', 'Gunakan email aktif/terdaftar, bukan email lokal atau contoh seperti .local, .test, atau example.com.');
            redirect('account.php');
        }

        if (!admin_account_verify_password($account, $currentPassword)) {
            flash('error', 'Password saat ini tidak sesuai. Perubahan email dibatalkan.');
            redirect('account.php');
        }

        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = :email AND id <> :id LIMIT 1');
        $stmt->execute(['email' => $email, 'id' => $userId]);
        if ($stmt->fetchColumn()) {
            flash('error', 'Email tersebut sudah digunakan oleh akun admin lain.');
            redirect('account.php');
        }

        $stmt = $pdo->prepare('UPDATE users SET name = :name, email = :email WHERE id = :id');
        $stmt->execute(['name' => $name, 'email' => $email, 'id' => $userId]);

        $_SESSION['admin_user']['name'] = $name;
        $_SESSION['admin_user']['email'] = $email;

        log_activity('account_update', 'Admin memperbarui nama/email akun.', 'users:' . $userId);
        flash('success', 'Profil akun berhasil diperbarui. Email ini sekarang digunakan untuk login dan lupa password.');
        redirect('account.php');
    }

    if ($action === 'update_avatar') {
        $uploadError = null;
        $newPhotoPath = upload_profile_photo($_FILES['profile_photo'] ?? [], $userId, $uploadError);
        if ($newPhotoPath === null) {
            flash('error', $uploadError ?: 'Foto profil belum dapat diunggah.');
            redirect('account.php');
        }

        $oldPhotoPath = (string)($account['profile_photo'] ?? '');

        try {
            $stmt = $pdo->prepare('UPDATE users SET profile_photo = :profile_photo WHERE id = :id');
            $stmt->execute(['profile_photo' => $newPhotoPath, 'id' => $userId]);
        } catch (Throwable $exception) {
            $newFile = managed_profile_photo_file_path($newPhotoPath);
            if ($newFile !== null) {
                delete_file($newFile);
            }

            log_exception($exception);
            flash('error', 'Foto profil gagal disimpan. Silakan coba lagi.');
            redirect('account.php');
        }

        $oldFile = managed_profile_photo_file_path($oldPhotoPath);
        if ($oldFile !== null) {
            delete_file($oldFile);
        }

        $_SESSION['admin_user']['profile_photo'] = $newPhotoPath;
        log_activity('profile_photo_update', 'Admin memperbarui foto profil.', 'users:' . $userId);
        flash('success', 'Foto profil berhasil diperbarui.');
        redirect('account.php');
    }

    if ($action === 'delete_avatar') {
        $oldPhotoPath = (string)($account['profile_photo'] ?? '');
        if ($oldPhotoPath === '') {
            flash('success', 'Akun sudah menggunakan avatar inisial.');
            redirect('account.php');
        }

        try {
            $stmt = $pdo->prepare('UPDATE users SET profile_photo = NULL WHERE id = :id');
            $stmt->execute(['id' => $userId]);
        } catch (Throwable $exception) {
            log_exception($exception);
            flash('error', 'Foto profil gagal dihapus. Silakan coba lagi.');
            redirect('account.php');
        }

        $oldFile = managed_profile_photo_file_path($oldPhotoPath);
        if ($oldFile !== null) {
            delete_file($oldFile);
        }

        $_SESSION['admin_user']['profile_photo'] = null;
        log_activity('profile_photo_remove', 'Admin menghapus foto profil.', 'users:' . $userId);
        flash('success', 'Foto profil dihapus. Avatar inisial kembali digunakan.');
        redirect('account.php');
    }

    if ($action === 'change_password') {
        $currentPassword = (string)($_POST['password_current'] ?? '');
        $newPassword = (string)($_POST['password_new'] ?? '');
        $confirmPassword = (string)($_POST['password_confirm'] ?? '');

        if (!admin_account_verify_password($account, $currentPassword)) {
            flash('error', 'Password saat ini tidak sesuai. Password belum diubah.');
            redirect('account.php');
        }

        if (strlen($newPassword) < 8) {
            flash('error', 'Password baru minimal 8 karakter.');
            redirect('account.php');
        }

        if (!preg_match('/[A-Za-z]/', $newPassword) || !preg_match('/\d/', $newPassword)) {
            flash('error', 'Password baru sebaiknya berisi kombinasi huruf dan angka.');
            redirect('account.php');
        }

        if ($newPassword !== $confirmPassword) {
            flash('error', 'Konfirmasi password baru tidak sama.');
            redirect('account.php');
        }

        if (password_verify($newPassword, (string)$account['password'])) {
            flash('error', 'Password baru tidak boleh sama dengan password saat ini.');
            redirect('account.php');
        }

        $stmt = $pdo->prepare('UPDATE users SET password = :password, remember_token = NULL WHERE id = :id');
        $stmt->execute([
            'password' => password_hash($newPassword, PASSWORD_DEFAULT),
            'id' => $userId,
        ]);

        try {
            $stmt = $pdo->prepare('UPDATE password_resets SET used_at = NOW() WHERE user_id = :id AND used_at IS NULL');
            $stmt->execute(['id' => $userId]);
        } catch (PDOException $exception) {
            // Table reset password mungkin belum dibuat pada instalasi lama.
        }

        clear_remember_cookie();
        log_activity('password_change', 'Admin mengubah password akun sendiri.', 'users:' . $userId);
        flash('success', 'Password berhasil diubah. Silakan gunakan password baru untuk login berikutnya.');
        redirect('account.php');
    }

    flash('error', 'Aksi akun tidak dikenali.');
    redirect('account.php');
}

$emailLooksPlaceholder = admin_account_is_placeholder_email((string)$account['email']);
$accountRoleLabel = function_exists('admin_role_label') ? admin_role_label((string)$account['role']) : strtoupper((string)$account['role']);

require_once __DIR__ . '/includes/header.php';
?>
<section class="panel account-hero-panel">
    <?= user_avatar_html($account, 'large', 'account-hero-avatar') ?>
    <div class="account-hero-copy">
        <span class="account-eyebrow">Keamanan Akun Admin</span>
        <h2><?= htmlspecialchars($account['name']) ?></h2>
        <p>Email yang tersimpan di sini digunakan untuk login admin dan fitur lupa password. Gunakan email aktif yang benar-benar dapat diakses.</p>
        <div class="account-meta-list">
            <span><?= htmlspecialchars($accountRoleLabel) ?></span>
            <span><?= htmlspecialchars((string)$account['email']) ?></span>
            <span><?= (int)$account['is_active'] === 1 ? 'Aktif' : 'Nonaktif' ?></span>
        </div>
    </div>
</section>

<?php if ($emailLooksPlaceholder): ?>
    <div class="alert alert-danger">Email akun saat ini masih terlihat seperti email lokal/contoh. Ganti dengan email aktif agar fitur lupa password dapat dipakai dengan benar.</div>
<?php endif; ?>

<section class="panel account-photo-panel">
    <div class="account-photo-preview">
        <?= user_avatar_html($account, 'large') ?>
    </div>
    <div class="account-photo-copy">
        <span class="account-eyebrow">Foto Profil</span>
        <h2><?= htmlspecialchars($account['name']) ?></h2>
        <p><?= htmlspecialchars($accountRoleLabel) ?></p>
        <small>Format JPG, PNG, atau WEBP. Maksimal 2 MB.</small>
    </div>
    <div class="account-photo-actions">
        <form method="post" enctype="multipart/form-data" class="avatar-upload-form">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="update_avatar">
            <input type="hidden" name="MAX_FILE_SIZE" value="<?= (int)PROFILE_AVATAR_MAX_SIZE ?>">
            <label for="profile_photo">Pilih Foto Profil</label>
            <input type="file" id="profile_photo" name="profile_photo" accept="image/jpeg,image/png,image/webp" data-max-size="<?= (int)PROFILE_AVATAR_MAX_SIZE ?>" required>
            <button type="submit" class="btn-primary">Ganti Foto</button>
        </form>
        <?php if (normalize_profile_photo_path($account['profile_photo'] ?? null) !== null): ?>
            <form method="post" class="inline-form">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete_avatar">
                <button type="submit" class="btn-secondary" data-confirm="Hapus foto profil ini?">Hapus Foto</button>
            </form>
        <?php endif; ?>
    </div>
</section>

<div class="account-settings-grid">
    <section class="panel account-form-panel">
        <div class="panel-header">
            <div>
                <h2>Email Login</h2>
                <p class="footer-note">Perbarui nama dan email akun admin. Konfirmasi password diperlukan untuk menjaga keamanan.</p>
            </div>
        </div>
        <form method="post" class="form-grid">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="update_profile">
            <div>
                <label for="name">Nama Admin</label>
                <input type="text" id="name" name="name" value="<?= htmlspecialchars((string)$account['name']) ?>" autocomplete="name" required>
            </div>
            <div>
                <label for="email">Email Aktif</label>
                <input type="email" id="email" name="email" value="<?= htmlspecialchars((string)$account['email']) ?>" autocomplete="email" required>
            </div>
            <div class="full-span">
                <label for="current_password">Password Saat Ini</label>
                <input type="password" id="current_password" name="current_password" autocomplete="current-password" required>
            </div>
            <div class="full-span account-note">
                Hindari email contoh seperti <strong>admin@sekolah.local</strong>. Gunakan email yang benar-benar terdaftar dan bisa menerima instruksi reset password.
            </div>
            <div class="form-actions full-span">
                <button type="submit" class="btn-primary">Simpan Email</button>
            </div>
        </form>
    </section>

    <section class="panel account-form-panel">
        <div class="panel-header">
            <div>
                <h2>Ubah Password</h2>
                <p class="footer-note">Gunakan password yang kuat agar akses admin tetap aman.</p>
            </div>
        </div>
        <form method="post" class="form-grid">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="change_password">
            <div class="full-span">
                <label for="password_current">Password Saat Ini</label>
                <input type="password" id="password_current" name="password_current" autocomplete="current-password" required>
            </div>
            <div>
                <label for="password_new">Password Baru</label>
                <input type="password" id="password_new" name="password_new" autocomplete="new-password" minlength="8" required>
            </div>
            <div>
                <label for="password_confirm">Konfirmasi Password</label>
                <input type="password" id="password_confirm" name="password_confirm" autocomplete="new-password" minlength="8" required>
            </div>
            <div class="full-span account-note account-note--soft">
                Minimal 8 karakter dan gunakan kombinasi huruf serta angka. Setelah password diganti, sesi “ingat saya” akan dinonaktifkan.
            </div>
            <div class="form-actions full-span">
                <button type="submit" class="btn-primary">Ubah Password</button>
            </div>
        </form>
    </section>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
