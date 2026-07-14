<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/password-reset-support.php';

if (is_logged_in()) {
    redirect('dashboard.php');
}

$token = (string)($_GET['token'] ?? $_POST['token'] ?? '');
$reset = find_password_reset_by_token($token);
$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = (string)($_POST['password'] ?? '');
    $confirm = (string)($_POST['password_confirm'] ?? '');

    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $error = 'Token keamanan tidak valid. Silakan muat ulang halaman.';
    } elseif (!$reset) {
        $error = 'Tautan reset password tidak valid atau sudah kedaluwarsa.';
    } elseif (strlen($password) < 8) {
        $error = 'Password baru minimal 8 karakter.';
    } elseif ($password !== $confirm) {
        $error = 'Konfirmasi password tidak sama.';
    } else {
        complete_password_reset((int)$reset['reset_id'], (int)$reset['user_id'], $password);
        log_activity('Reset password', 'Password admin berhasil direset', 'password-reset:' . $reset['user_id']);
        $success = true;
    }
}
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password | <?= APP_NAME ?></title>
    <link rel="stylesheet" href="assets/css/admin.css?v=20260712-split-login">
</head>
<body class="login-shell login-shell--premium login-shell--split login-shell--single-card">
    <main class="login-split login-split--single"><section class="login-card login-card--glass login-card--split login-card--compact" aria-labelledby="reset-title">
        <div class="login-card__glow" aria-hidden="true"></div>
        <span class="login-eyebrow">Password Baru</span>

        <div class="login-brand login-brand--premium">
            <img src="../assets/img/logo.png" alt="Logo SD Cahaya Harapan Bekasi" loading="lazy">
            <div>
                <h1 id="reset-title">Reset Password</h1>
                <p class="footer-note">SD Cahaya Harapan Bekasi</p>
            </div>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success login-alert">Password berhasil diperbarui. Silakan login dengan password baru.</div>
            <p class="footer-note login-note"><a class="login-inline-link" href="login.php">Masuk ke halaman login</a></p>
        <?php else: ?>
            <?php if ($error || !$reset): ?>
                <div class="alert alert-danger login-alert"><?= htmlspecialchars($error ?: 'Tautan reset password tidak valid atau sudah kedaluwarsa.') ?></div>
            <?php endif; ?>

            <?php if ($reset): ?>
                <p class="footer-note login-lead">Buat password baru untuk akun <?= htmlspecialchars((string)$reset['email']) ?>.</p>
                <form method="post" novalidate class="login-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                    <div class="login-form-grid">
                        <div class="login-field">
                            <label for="password">Password Baru</label>
                            <input type="password" id="password" name="password" autocomplete="new-password" required>
                        </div>
                        <div class="login-field">
                            <label for="password_confirm">Konfirmasi Password</label>
                            <input type="password" id="password_confirm" name="password_confirm" autocomplete="new-password" required>
                        </div>
                    </div>
                    <div class="form-actions login-actions">
                        <button type="submit" class="btn-primary login-submit">Simpan Password Baru</button>
                    </div>
                </form>
            <?php endif; ?>

            <p class="footer-note login-note"><a class="login-inline-link" href="forgot-password.php">Minta tautan baru</a></p>
        <?php endif; ?>
    </section>
</body>
</html>
