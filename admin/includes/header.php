<?php
if (!defined('ADMIN_CONTEXT')) {
    exit('Direct access not allowed.');
}
$sessionTimeout = defined('SESSION_TIMEOUT_SECONDS') ? SESSION_TIMEOUT_SECONDS : 1800;
$sessionRemaining = function_exists('get_session_time_remaining') ? get_session_time_remaining() : $sessionTimeout;
$currentUser = current_user() ?? [];
$currentRoleLabel = function_exists('admin_role_label') ? admin_role_label((string)($currentUser['role'] ?? 'operator')) : strtoupper((string)($currentUser['role'] ?? 'operator'));
$publicSiteUrl = function_exists('public_site_url') ? public_site_url() : '../';
$adminTopbarIcon = static function (string $name): string {
    $icons = [
        'external' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M14 4h6v6"/><path d="m20 4-9 9"/><path d="M20 14v4a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h4"/></svg>',
        'logout' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M10 5H6a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h4"/><path d="M15 7l5 5-5 5"/><path d="M20 12H9"/></svg>',
        'user' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z"/><path d="M4 20a8 8 0 0 1 16 0"/></svg>',
        'user-plus' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z"/><path d="M4 20a8 8 0 0 1 10.5-7.6"/><path d="M18 14v6M15 17h6"/></svg>',
        'users' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z"/><path d="M2.8 20a6.2 6.2 0 0 1 12.4 0"/><path d="M17 11a3 3 0 1 0 0-6"/><path d="M15.8 14.2A5 5 0 0 1 21.2 20"/></svg>',
        'chevron' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>',
    ];

    return $icons[$name] ?? $icons['external'];
};
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#2563eb">
    <title><?= htmlspecialchars($pageTitle ?? 'Dashboard') ?> | <?= APP_NAME ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
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
            <form method="post" action="logout.php" class="logout-form">
                <?= csrf_field() ?>
                <button type="submit" class="btn-tertiary">Logout</button>
            </form>
        </div>
    </div>
</div>
<div id="loading-overlay" class="loading-overlay" hidden>
    <div class="loading-spinner"></div>
</div>
<div class="admin-layout">
    <?php require_once __DIR__ . '/sidebar.php'; ?>
    <div class="sidebar-backdrop" data-sidebar-close></div>
    <div class="admin-content">
        <header class="admin-topbar">
            <button class="sidebar-toggle" type="button" aria-label="Buka menu admin" aria-expanded="false">Menu</button>
            <div class="admin-topbar__title">
                <h1><?= htmlspecialchars($pageTitle ?? 'Dashboard') ?></h1>
                <p>Panel administrasi SD Cahaya Harapan Bekasi</p>
            </div>
            <a class="btn-secondary admin-topbar__site-link" href="<?= escape($publicSiteUrl) ?>" target="_blank" rel="noopener noreferrer"><span class="admin-button-icon" aria-hidden="true"><?= $adminTopbarIcon('external') ?></span>Lihat Website</a>
            <div class="admin-topbar__user">
                <div class="admin-account-menu">
                    <button class="admin-account-trigger" type="button" aria-haspopup="menu" aria-expanded="false" aria-controls="admin-account-dropdown" data-account-toggle>
                        <?= user_avatar_html($currentUser, 'small', 'admin-account-trigger__avatar') ?>
                        <span class="admin-account-trigger__copy">
                            <strong><?= htmlspecialchars($currentUser['name'] ?? 'Admin') ?></strong>
                            <small><?= htmlspecialchars($currentRoleLabel) ?></small>
                        </span>
                        <span class="admin-account-trigger__chevron" aria-hidden="true"><?= $adminTopbarIcon('chevron') ?></span>
                    </button>
                    <div class="admin-account-dropdown" id="admin-account-dropdown" role="menu" hidden>
                        <div class="admin-account-dropdown__header">
                            <?= user_avatar_html($currentUser, 'medium') ?>
                            <div>
                                <strong><?= htmlspecialchars($currentUser['name'] ?? 'Admin') ?></strong>
                                <small><?= htmlspecialchars($currentRoleLabel) ?></small>
                            </div>
                        </div>
                        <div class="admin-account-dropdown__section">
                            <a class="admin-account-dropdown__item" href="account.php" role="menuitem">
                                <span class="admin-account-dropdown__icon" aria-hidden="true"><?= $adminTopbarIcon('user') ?></span>
                                <span>Profil Saya</span>
                            </a>
                            <?php if (can('manage_user')): ?>
                                <a class="admin-account-dropdown__item" href="user-form.php" role="menuitem">
                                    <span class="admin-account-dropdown__icon" aria-hidden="true"><?= $adminTopbarIcon('user-plus') ?></span>
                                    <span>Tambah Akun</span>
                                </a>
                                <a class="admin-account-dropdown__item" href="users.php" role="menuitem">
                                    <span class="admin-account-dropdown__icon" aria-hidden="true"><?= $adminTopbarIcon('users') ?></span>
                                    <span>Kelola Akun</span>
                                </a>
                            <?php endif; ?>
                        </div>
                        <div class="admin-account-dropdown__section admin-account-dropdown__section--logout">
                            <form method="post" action="logout.php" class="logout-form">
                                <?= csrf_field() ?>
                                <button type="submit" class="admin-account-dropdown__item admin-account-dropdown__item--button" role="menuitem">
                                    <span class="admin-account-dropdown__icon" aria-hidden="true"><?= $adminTopbarIcon('logout') ?></span>
                                    <span>Logout</span>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
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
