<?php
if (!defined('ADMIN_CONTEXT')) {
    exit('Direct access not allowed.');
}
$sessionTimeout = defined('SESSION_TIMEOUT_SECONDS') ? SESSION_TIMEOUT_SECONDS : 1800;
$sessionRemaining = function_exists('get_session_time_remaining') ? get_session_time_remaining() : $sessionTimeout;
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#2563eb">
    <title><?= htmlspecialchars($pageTitle ?? 'Dashboard') ?> | <?= APP_NAME ?></title>
    <link rel="stylesheet" href="assets/css/admin.css">
</head>
<body class="admin-body" data-session-timeout="<?= $sessionTimeout ?>" data-session-remaining="<?= $sessionRemaining ?>">
<div id="session-expiry-modal" class="session-modal" hidden>
    <div class="session-modal__backdrop"></div>
    <div class="session-modal__card">
        <h2>Sesi Hampir Berakhir</h2>
        <p>Anda akan otomatis logout jika tidak ada aktivitas selama beberapa saat. Silakan perpanjang sesi jika masih bekerja.</p>
        <div class="form-actions">
            <button type="button" class="btn-primary" id="extend-session-button">Perpanjang Sesi</button>
            <a class="btn-tertiary" href="logout.php">Logout</a>
        </div>
    </div>
</div>
<div id="loading-overlay" class="loading-overlay" hidden>
    <div class="loading-spinner"></div>
</div>
<div class="admin-layout">
    <?php require_once __DIR__ . '/sidebar.php'; ?>
    <div class="admin-content">
        <header class="admin-topbar">
            <button class="sidebar-toggle" type="button" aria-label="Toggle sidebar">☰</button>
            <div class="admin-topbar__title">
                <h1><?= htmlspecialchars($pageTitle ?? 'Dashboard') ?></h1>
            </div>
            <div class="admin-topbar__user">
                <span class="admin-user-name"><?= htmlspecialchars(current_user()['name'] ?? 'Admin') ?></span>
                <a class="btn-secondary" href="logout.php">Logout</a>
            </div>
        </header>
        <main class="admin-main">
            <div class="toast-container"></div>
            <?php if ($message = flash('success')) : ?>
                <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>
            <?php if ($message = flash('error')) : ?>
                <div class="alert alert-danger"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>
