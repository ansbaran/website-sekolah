<?php
if (!defined('ADMIN_CONTEXT')) {
    exit('Direct access not allowed.');
}

$currentPage = basename($_SERVER['SCRIPT_NAME'] ?? '');
$currentProgramType = isset($_GET['type']) && function_exists('normalize_school_program_type') ? normalize_school_program_type((string) $_GET['type']) : '';
$isActive = static function (array $files) use ($currentPage): string {
    return in_array($currentPage, $files, true) ? 'active' : '';
};

$user = current_user();

$adminIcon = static function (string $name): string {
    $icons = [
        'brand' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3 4 6.4v5.7c0 4.6 3.3 7.4 8 8.9 4.7-1.5 8-4.3 8-8.9V6.4L12 3Z"/><path d="M8.2 12.1h7.6M12 8.3v7.6"/></svg>',
        'dashboard' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5.8A1.8 1.8 0 0 1 5.8 4h4.4A1.8 1.8 0 0 1 12 5.8v4.4a1.8 1.8 0 0 1-1.8 1.8H5.8A1.8 1.8 0 0 1 4 10.2V5.8Zm8 8a1.8 1.8 0 0 1 1.8-1.8h4.4a1.8 1.8 0 0 1 1.8 1.8v4.4a1.8 1.8 0 0 1-1.8 1.8h-4.4a1.8 1.8 0 0 1-1.8-1.8v-4.4ZM14 4h4.2A1.8 1.8 0 0 1 20 5.8V10M4 14v4.2A1.8 1.8 0 0 0 5.8 20H10"/></svg>',
        'account' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z"/><path d="M4 20a8 8 0 0 1 16 0"/><path d="M18.5 14.5l1 1 2-2"/></svg>',
        'news' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 5.5A2.5 2.5 0 0 1 7.5 3h9A2.5 2.5 0 0 1 19 5.5v13A2.5 2.5 0 0 1 16.5 21h-9A2.5 2.5 0 0 1 5 18.5v-13Z"/><path d="M8.5 8h7M8.5 12h7M8.5 16h4"/></svg>',
        'gallery' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7a3 3 0 0 1 3-3h10a3 3 0 0 1 3 3v10a3 3 0 0 1-3 3H7a3 3 0 0 1-3-3V7Z"/><path d="m5 16 4.2-4.2a1.4 1.4 0 0 1 2 0L16 16.6"/><path d="m14 14 1.1-1.1a1.4 1.4 0 0 1 2 0L20 15.8"/><path d="M15.5 8.5h.01"/></svg>',
        'staff' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8.5 11.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7ZM2.8 19.5a5.7 5.7 0 0 1 11.4 0"/><path d="M17 11a2.8 2.8 0 1 0 0-5.6"/><path d="M15.8 14.3a4.8 4.8 0 0 1 5.4 4.8"/></svg>',
        'about' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 20V7.5L12 4l8 3.5V20"/><path d="M8 20v-7h8v7M9 9h.01M12 9h.01M15 9h.01"/></svg>',
        'award' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 14.5a5.5 5.5 0 1 0 0-11 5.5 5.5 0 0 0 0 11Z"/><path d="m9.2 13.8-1.1 6.7 3.9-2.3 3.9 2.3-1.1-6.7"/><path d="m10 9 1.3 1.3L14.2 7"/></svg>',
        'slider' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7a3 3 0 0 1 3-3h10a3 3 0 0 1 3 3v10a3 3 0 0 1-3 3H7a3 3 0 0 1-3-3V7Z"/><path d="m9 8 4 4-4 4"/></svg>',
        'announcement' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 13V9a2 2 0 0 1 2-2h3l8-3v14l-8-3H6a2 2 0 0 1-2-2Z"/><path d="M9 15v4M18 9.5a3.5 3.5 0 0 1 0 5"/></svg>',
        'agenda' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 3v3M17 3v3M4 8h16"/><path d="M6.5 5h11A2.5 2.5 0 0 1 20 7.5v10A2.5 2.5 0 0 1 17.5 20h-11A2.5 2.5 0 0 1 4 17.5v-10A2.5 2.5 0 0 1 6.5 5Z"/><path d="M8 12h.01M12 12h.01M16 12h.01M8 16h.01M12 16h.01"/></svg>',
        'feedback' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 18.5A3 3 0 0 1 3 15.7V7a3 3 0 0 1 3-3h12a3 3 0 0 1 3 3v8.7a3 3 0 0 1-3 3H9l-4 2.8v-3Z"/><path d="M8 9h8M8 13h5"/></svg>',
        'media' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 6.5A2.5 2.5 0 0 1 7.5 4h9A2.5 2.5 0 0 1 19 6.5v11a2.5 2.5 0 0 1-2.5 2.5h-9A2.5 2.5 0 0 1 5 17.5v-11Z"/><path d="M8.5 4.5v15M15.5 4.5v15M5.5 9h13M5.5 15h13"/></svg>',
        'activity' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 12h4l2-6 4 12 2-6h4"/><path d="M4 19h16"/></svg>',
        'system' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 15.2a3.2 3.2 0 1 0 0-6.4 3.2 3.2 0 0 0 0 6.4Z"/><path d="M19.4 15a1.8 1.8 0 0 0 .4 2l.1.1-2.2 2.2-.1-.1a1.8 1.8 0 0 0-2-.4 1.8 1.8 0 0 0-1.1 1.7V21h-5v-.5a1.8 1.8 0 0 0-1.1-1.7 1.8 1.8 0 0 0-2 .4l-.1.1-2.2-2.2.1-.1a1.8 1.8 0 0 0 .4-2 1.8 1.8 0 0 0-1.7-1.1H3v-3h.5a1.8 1.8 0 0 0 1.7-1.1 1.8 1.8 0 0 0-.4-2l-.1-.1 2.2-2.2.1.1a1.8 1.8 0 0 0 2 .4A1.8 1.8 0 0 0 10.1 3.5V3h3.8v.5A1.8 1.8 0 0 0 15 5.2a1.8 1.8 0 0 0 2-.4l.1-.1 2.2 2.2-.1.1a1.8 1.8 0 0 0-.4 2 1.8 1.8 0 0 0 1.7 1.1h.5v3h-.5a1.8 1.8 0 0 0-1.1 1.9Z"/></svg>',
        'health' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 21s-8-4.6-8-11.2A4.8 4.8 0 0 1 12 6a4.8 4.8 0 0 1 8 3.8C20 16.4 12 21 12 21Z"/><path d="M8 12h2.5l1-2.5 2 5 1-2.5H16"/></svg>',
    ];

    return $icons[$name] ?? $icons['dashboard'];
};
?>
<aside class="admin-sidebar" aria-label="Navigasi admin">
    <div class="sidebar-brand">
        <span class="sidebar-brand__mark" aria-hidden="true"><?= $adminIcon('brand') ?></span>
        <div>
            <strong>SD Cahaya Harapan</strong>
            <small>Admin Panel</small>
        </div>
    </div>

    <nav class="sidebar-nav">
        <span class="sidebar-nav__label">Utama</span>
        <a class="sidebar-link <?= $isActive(['dashboard.php']) ?>" href="dashboard.php" <?= $currentPage === 'dashboard.php' ? 'aria-current="page"' : '' ?>>
            <span class="sidebar-link__icon" aria-hidden="true"><?= $adminIcon('dashboard') ?></span>
            <span>Dashboard</span>
        </a>
        <a class="sidebar-link <?= $isActive(['account.php']) ?>" href="account.php" <?= $currentPage === 'account.php' ? 'aria-current="page"' : '' ?>>
            <span class="sidebar-link__icon" aria-hidden="true"><?= $adminIcon('account') ?></span>
            <span>Akun Saya</span>
        </a>

        <?php if (can('publish')): ?>
            <span class="sidebar-nav__label">Konten</span>
            <a class="sidebar-link <?= $isActive(['news.php', 'news-form.php']) ?>" href="news.php" <?= in_array($currentPage, ['news.php', 'news-form.php'], true) ? 'aria-current="page"' : '' ?>>
                <span class="sidebar-link__icon" aria-hidden="true"><?= $adminIcon('news') ?></span>
                <span>Berita</span>
            </a>
        <?php endif; ?>

        <?php if (can('upload')): ?>
            <a class="sidebar-link <?= $isActive(['gallery.php', 'gallery-form.php']) ?>" href="gallery.php" <?= in_array($currentPage, ['gallery.php', 'gallery-form.php'], true) ? 'aria-current="page"' : '' ?>>
                <span class="sidebar-link__icon" aria-hidden="true"><?= $adminIcon('gallery') ?></span>
                <span>Galeri</span>
            </a>
        <?php endif; ?>

        <?php if (can('publish')): ?>
            <a class="sidebar-link <?= $isActive(['staff.php', 'staff-form.php']) ?>" href="staff.php" <?= in_array($currentPage, ['staff.php', 'staff-form.php'], true) ? 'aria-current="page"' : '' ?>>
                <span class="sidebar-link__icon" aria-hidden="true"><?= $adminIcon('staff') ?></span>
                <span>Guru & Staff</span>
            </a>
            <a class="sidebar-link <?= $isActive(['about.php']) ?>" href="about.php" <?= $currentPage === 'about.php' ? 'aria-current="page"' : '' ?>>
                <span class="sidebar-link__icon" aria-hidden="true"><?= $adminIcon('about') ?></span>
                <span>Tentang Sekolah</span>
            </a>
            <a class="sidebar-link <?= $isActive(['achievements.php', 'achievement-form.php']) ?>" href="achievements.php" <?= in_array($currentPage, ['achievements.php', 'achievement-form.php'], true) ? 'aria-current="page"' : '' ?>>
                <span class="sidebar-link__icon" aria-hidden="true"><?= $adminIcon('award') ?></span>
                <span>Prestasi</span>
            </a>
            <a class="sidebar-link <?= in_array($currentPage, ['programs.php', 'program-form.php'], true) && $currentProgramType === 'kegiatan' ? 'active' : '' ?>" href="programs.php?type=kegiatan" <?= in_array($currentPage, ['programs.php', 'program-form.php'], true) && $currentProgramType === 'kegiatan' ? 'aria-current="page"' : '' ?>>
                <span class="sidebar-link__icon" aria-hidden="true"><?= $adminIcon('activity') ?></span>
                <span>Kegiatan</span>
            </a>
            <a class="sidebar-link <?= in_array($currentPage, ['programs.php', 'program-form.php'], true) && $currentProgramType === 'ekstrakurikuler' ? 'active' : '' ?>" href="programs.php?type=ekstrakurikuler" <?= in_array($currentPage, ['programs.php', 'program-form.php'], true) && $currentProgramType === 'ekstrakurikuler' ? 'aria-current="page"' : '' ?>>
                <span class="sidebar-link__icon" aria-hidden="true"><?= $adminIcon('activity') ?></span>
                <span>Ekstrakurikuler</span>
            </a>
            <a class="sidebar-link <?= $isActive(['slider.php', 'slider-form.php']) ?>" href="slider.php" <?= in_array($currentPage, ['slider.php', 'slider-form.php'], true) ? 'aria-current="page"' : '' ?>>
                <span class="sidebar-link__icon" aria-hidden="true"><?= $adminIcon('slider') ?></span>
                <span>Slider Hero</span>
            </a>
            <a class="sidebar-link <?= $isActive(['announcements.php', 'announcement-form.php']) ?>" href="announcements.php" <?= in_array($currentPage, ['announcements.php', 'announcement-form.php'], true) ? 'aria-current="page"' : '' ?>>
                <span class="sidebar-link__icon" aria-hidden="true"><?= $adminIcon('announcement') ?></span>
                <span>Pengumuman</span>
            </a>
            <a class="sidebar-link <?= $isActive(['agendas.php', 'agenda-form.php']) ?>" href="agendas.php" <?= in_array($currentPage, ['agendas.php', 'agenda-form.php'], true) ? 'aria-current="page"' : '' ?>>
                <span class="sidebar-link__icon" aria-hidden="true"><?= $adminIcon('agenda') ?></span>
                <span>Agenda</span>
            </a>
            <a class="sidebar-link <?= $isActive(['feedback.php', 'feedback-form.php']) ?>" href="feedback.php" <?= in_array($currentPage, ['feedback.php', 'feedback-form.php'], true) ? 'aria-current="page"' : '' ?>>
                <span class="sidebar-link__icon" aria-hidden="true"><?= $adminIcon('feedback') ?></span>
                <span>Feedback Orang Tua</span>
            </a>
        <?php endif; ?>

        <span class="sidebar-nav__label">Media</span>
        <a class="sidebar-link <?= $isActive(['media.php']) ?>" href="media.php" <?= $currentPage === 'media.php' ? 'aria-current="page"' : '' ?>>
            <span class="sidebar-link__icon" aria-hidden="true"><?= $adminIcon('media') ?></span>
            <span>Media Manager</span>
        </a>

        <?php if (can('backup')): ?>
            <span class="sidebar-nav__label">Sistem</span>
            <a class="sidebar-link <?= $isActive(['activity-log.php']) ?>" href="activity-log.php" <?= $currentPage === 'activity-log.php' ? 'aria-current="page"' : '' ?>>
                <span class="sidebar-link__icon" aria-hidden="true"><?= $adminIcon('activity') ?></span>
                <span>Aktivitas</span>
            </a>
            <a class="sidebar-link <?= $isActive(['system.php']) ?>" href="system.php" <?= $currentPage === 'system.php' ? 'aria-current="page"' : '' ?>>
                <span class="sidebar-link__icon" aria-hidden="true"><?= $adminIcon('system') ?></span>
                <span>Sistem</span>
            </a>
            <a class="sidebar-link <?= $isActive(['system-health.php']) ?>" href="system-health.php" <?= $currentPage === 'system-health.php' ? 'aria-current="page"' : '' ?>>
                <span class="sidebar-link__icon" aria-hidden="true"><?= $adminIcon('health') ?></span>
                <span>Health Sistem</span>
            </a>
        <?php endif; ?>
    </nav>

    <div class="sidebar-footer">
        <div class="sidebar-user">
            <span><?= htmlspecialchars($user['name'] ?? 'Administrator') ?></span>
            <small><?= strtoupper(htmlspecialchars($user['role'] ?? 'operator')) ?></small>
        </div>
        <a class="sidebar-account-link" href="account.php">Kelola akun</a>
    </div>
</aside>