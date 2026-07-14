<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/password-reset-support.php';

if (is_logged_in()) {
    redirect('dashboard.php');
}

$email = '';
$error = '';
$success = false;
$devResetLink = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = clean($_POST['email'] ?? '');

    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $error = 'Token keamanan tidak valid. Silakan muat ulang halaman.';
    } elseif ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Masukkan email admin yang valid.';
    } else {
        $stmt = $pdo->prepare('SELECT id, name, email, is_active FROM users WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();

        if ($user && (int)($user['is_active'] ?? 0) === 1) {
            $devResetLink = create_password_reset_link($user);
            send_password_reset_message((string)$user['email'], $devResetLink);
            log_activity('Minta reset password', 'Permintaan reset password admin', 'password-reset:' . $user['id']);
        }

        $success = true;
    }
}
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lupa Password | <?= APP_NAME ?></title>
    <link rel="stylesheet" href="assets/css/admin.css?v=20260712-split-login">
</head>
<body class="login-shell login-shell--premium login-shell--split login-shell--single-card">
    <main class="login-split login-split--single"><section class="login-card login-card--glass login-card--split login-card--compact" aria-labelledby="forgot-title">
        <div class="login-card__glow" aria-hidden="true"></div>
        <span class="login-eyebrow">Reset Akses</span>

        <div class="login-brand login-brand--premium">
            <img src="../assets/img/logo.png" alt="Logo SD Cahaya Harapan Bekasi" loading="lazy">
            <div>
                <h1 id="forgot-title">Lupa Password</h1>
                <p class="footer-note">SD Cahaya Harapan Bekasi</p>
            </div>
        </div>

        <p class="footer-note login-lead">Masukkan email admin. Jika terdaftar, instruksi reset password akan dikirimkan.</p>

        <?php if ($error): ?>
            <div class="alert alert-danger login-alert"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success login-alert">Jika email terdaftar, tautan reset password telah disiapkan. Periksa email atau hubungi admin utama.</div>
            <?php if (APP_ENV !== 'production' && $devResetLink !== ''): ?>
                <a class="login-reset-dev-link" href="<?= htmlspecialchars($devResetLink) ?>">Buka link reset untuk testing lokal</a>
            <?php endif; ?>
        <?php endif; ?>

        <form method="post" novalidate class="login-form">
            <?= csrf_field() ?>
            <div class="login-form-grid">
                <div class="login-field">
                    <label for="email">Email Admin</label>
                    <input type="email" id="email" name="email" value="<?= htmlspecialchars($email) ?>" autocomplete="email" required>
                </div>
            </div>
            <div class="form-actions login-actions">
                <button type="submit" class="btn-primary login-submit">Kirim Instruksi Reset</button>
            </div>
        </form>

        <p class="footer-note login-note"><a class="login-inline-link" href="login.php">Kembali ke login</a></p>
    </section>
</body>
</html>
