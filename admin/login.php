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
        $statement = $pdo->prepare('SELECT id, name, email, password, role FROM users WHERE email = :email LIMIT 1');
        $statement->execute(['email' => $email]);
        $user = $statement->fetch();

        if (!$user || !password_verify($password, $user['password'])) {
            record_login_attempt($email, false);
            $error = 'Email atau password salah.';
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
    <link rel="stylesheet" href="assets/css/admin.css">
</head>
<body class="login-shell">
    <section class="login-card">
        <h1>Login Admin</h1>
        <p class="footer-note">Masuk untuk mengelola konten sekolah tanpa mengubah frontend.</p>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="post" novalidate>
            <?= csrf_field() ?>
            <div class="form-grid">
                <div>
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" value="<?= htmlspecialchars($email) ?>" required>
                </div>
                <div>
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required>
                </div>
                <div>
                    <label>
                        <input type="checkbox" name="remember"> Ingat sesi saya
                    </label>
                </div>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn-primary">Masuk</button>
            </div>
        </form>
        <p class="footer-note">Gunakan akun admin atau operator yang telah didaftarkan.</p>
    </section>
</body>
</html>
