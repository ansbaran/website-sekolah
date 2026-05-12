<?php
if (!defined('ADMIN_CONTEXT')) {
    exit('Direct access not allowed.');
}

$active = static function (string $fileName): string {
    return basename($_SERVER['SCRIPT_NAME']) === $fileName ? 'active' : '';
};

$user = current_user();
?>
<aside class="admin-sidebar">
    <div class="sidebar-brand">
        <div>
            <strong>SHB</strong>
            <small>Admin Panel</small>
        </div>
    </div>

    <nav class="sidebar-nav">
        <a class="sidebar-link <?= $active('dashboard.php') ?>" href="dashboard.php">Dashboard</a>
        <a class="sidebar-link <?= $active('news.php') ?>" href="news.php">Berita</a>
        <a class="sidebar-link <?= $active('gallery.php') ?>" href="gallery.php">Galeri</a>
        <a class="sidebar-link <?= $active('achievements.php') ?>" href="achievements.php">Prestasi</a>
        <a class="sidebar-link <?= $active('slider.php') ?>" href="slider.php">Slider Hero</a>
        <a class="sidebar-link <?= $active('announcements.php') ?>" href="announcements.php">Pengumuman</a>
        <a class="sidebar-link <?= $active('media.php') ?>" href="media.php">Media Manager</a>
        <a class="sidebar-link <?= $active('activity-log.php') ?>" href="activity-log.php">Aktivitas</a>
        <?php if (can('backup')): ?>
            <a class="sidebar-link <?= $active('system.php') ?>" href="system.php">Sistem</a>
        <a class="sidebar-link <?= $active('system-health.php') ?>" href="system-health.php">Health Sistem</a>
        <?php endif; ?>
    </nav>

    <div class="sidebar-footer">
        <div class="sidebar-user">
            <span><?= htmlspecialchars($user['name'] ?? 'Administrator') ?></span>
            <small><?= strtoupper(htmlspecialchars($user['role'] ?? 'operator')) ?></small>
        </div>
    </div>
</aside>
