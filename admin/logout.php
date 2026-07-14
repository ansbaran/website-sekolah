<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        echo 'Token keamanan tidak valid.';
        exit;
    }

    logout_user();
    redirect('login.php');
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    header('Allow: GET, POST');
    echo 'Method not allowed';
    exit;
}
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logout | <?= APP_NAME ?></title>
    <link rel="stylesheet" href="assets/css/admin.css">
</head>
<body class="login-shell">
    <section class="login-card">
        <h1>Logout Admin</h1>
        <p class="footer-note">Konfirmasi untuk mengakhiri sesi admin Anda.</p>
        <form method="post">
            <?= csrf_field() ?>
            <div class="form-actions">
                <button type="submit" class="btn-primary">Logout</button>
                <a href="dashboard.php" class="btn-secondary">Batal</a>
            </div>
        </form>
    </section>
</body>
</html>
