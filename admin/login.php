<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

if (is_logged_in()) {
    redirect('dashboard.php');
}

$error = '';
$email = '';
$expired = isset($_GET['expired']) && $_GET['expired'] === '1';

if ($expired) {
    flash('error', 'Sesi Anda telah habis. Silakan login ulang.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = clean($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);
    $token = $_POST['csrf_token'] ?? '';

    if (!csrf_verify($token)) {
        $error = 'Token keamanan tidak valid. Silakan muat ulang halaman.';
    } elseif (is_login_blocked(get_client_ip(), $email)) {
        $error = 'Terlalu banyak percobaan login. Silakan tunggu beberapa menit dan coba lagi.';
    } elseif ($email === '' || $password === '') {
        $error = 'Email dan password wajib diisi.';
    } else {
        $statement = $pdo->prepare('SELECT id, name, email, password, role, is_active FROM users WHERE email = :email LIMIT 1');
        $statement->execute(['email' => $email]);
        $user = $statement->fetch();

        if (!$user || !password_verify($password, $user['password'])) {
            record_login_attempt($email, false);
            $error = 'Email atau password salah.';
        } elseif ((int)($user['is_active'] ?? 1) !== 1) {
            record_login_attempt($email, false);
            $error = 'Akun tidak aktif. Silakan hubungi administrator.';
        } else {
            record_login_attempt($email, true);
            login_user($user, $remember);
            log_activity('Login', 'Login admin berhasil', 'login');
            flash('success', 'Selamat datang, ' . $user['name'] . '!');
            redirect('dashboard.php');
        }
    }
}
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Admin | <?= APP_NAME ?></title>
    <link rel="stylesheet" href="assets/css/admin.css?v=20260712-split-login">
</head>
<body class="login-shell login-shell--premium login-shell--split">
    <main class="login-split" aria-labelledby="login-title">
        <section class="login-split__intro" aria-label="Informasi admin sekolah">
            <div class="login-split__brand">
                <img src="../assets/img/logo.png" alt="Logo SD Cahaya Harapan Bekasi" loading="lazy">
                <strong>SD Cahaya Harapan</strong>
            </div>
            <h1>Kelola Website Sekolah dengan Aman</h1>
            <p>Panel administrasi untuk memperbarui berita, agenda, prestasi, dan informasi sekolah secara rapi.</p>
            <div class="login-split__line" aria-hidden="true"></div>
            <a class="login-split__cta" href="../index.html">Lihat Website</a>
        </section>

        <section class="login-card login-card--glass login-card--split" aria-labelledby="login-title">
            <div class="login-card__glow" aria-hidden="true"></div>
            <span class="login-eyebrow">Admin Panel</span>

            <div class="login-card__header">
                <h2 id="login-title">Login</h2>
                <p class="footer-note">SD Cahaya Harapan Bekasi</p>
            </div>

            <?php $flashError = flash('error'); ?>
            <?php if ($error || $flashError): ?>
                <div class="alert alert-danger login-alert"><?= htmlspecialchars($error ?: $flashError) ?></div>
            <?php endif; ?>

            <form method="post" novalidate class="login-form">
                <?= csrf_field() ?>
                <div class="login-form-grid">
                    <div class="login-field">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" value="<?= htmlspecialchars($email) ?>" autocomplete="email" required>
                    </div>
                    <div class="login-field">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password" autocomplete="current-password" required>
                    </div>
                    <div class="login-form-row">
                        <label class="login-remember">
                            <input type="checkbox" name="remember"> <span>Ingat sesi</span>
                        </label>
                        <a class="login-inline-link" href="forgot-password.php">Lupa password?</a>
                    </div>
                </div>
                <div class="form-actions login-actions">
                    <button type="submit" class="btn-primary login-submit">Masuk</button>
                </div>
            </form>
        </section>
    </main></body>
</html>
