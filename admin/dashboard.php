<?php

declare(strict_types=1);

define('ADMIN_CONTEXT', true);

require_once __DIR__ . '/../includes/auth.php';
require_login();

$totalNews = count_table('news');
$totalGallery = count_table('gallery');
$totalAnnouncements = count_table('announcements');
$totalSlides = count_table('slider');
$totalStaff = count_table('staff');
$totalAchievements = count_table('achievements');
$activities = get_recent_activities();
$pageTitle = 'Dashboard';

require_once __DIR__ . '/includes/header.php';
?>
<div class="cards-grid">
    <section class="card stat-card">
        <span>Total Berita</span>
        <div class="stat-value"><?= $totalNews ?></div>
    </section>
    <section class="card stat-card">
        <span>Total Galeri</span>
        <div class="stat-value"><?= $totalGallery ?></div>
    </section>
    <section class="card stat-card">
        <span>Total Pengumuman</span>
        <div class="stat-value"><?= $totalAnnouncements ?></div>
    </section>
    <section class="card stat-card">
        <span>Total Guru & Staff</span>
        <div class="stat-value"><?= $totalStaff ?></div>
    </section>
    <section class="card stat-card">
        <span>Total Prestasi</span>
        <div class="stat-value"><?= $totalAchievements ?></div>
    </section>
    <section class="card stat-card">
        <span>Total Slider</span>
        <div class="stat-value"><?= $totalSlides ?></div>
    </section>
</div>

<section class="panel">
    <div class="panel-header">
        <div>
            <h2>Shortcut Cepat</h2>
            <p class="footer-note">Akses cepat ke modul utama CMS.</p>
        </div>
    </div>
    <div class="dashboard-actions">
        <?php if (can('publish')): ?>
            <a class="btn-primary" href="news-form.php">Tambah Berita</a>
            <a class="btn-secondary" href="staff-form.php">Tambah Guru & Staff</a>
            <a class="btn-secondary" href="achievement-form.php">Tambah Prestasi</a>
            <a class="btn-secondary" href="announcement-form.php">Tambah Pengumuman</a>
            <a class="btn-tertiary" href="system.php">Pengaturan PPDB</a>
        <?php endif; ?>
        <?php if (can('upload')): ?>
            <a class="btn-secondary" href="gallery-form.php">Tambah Galeri</a>
            <a class="btn-tertiary" href="media.php">Media Manager</a>
        <?php endif; ?>
    </div>
</section>

<section class="panel">
    <h2>Aktivitas Terbaru</h2>
    <div class="list-preview">
        <?php if (empty($activities)) : ?>
            <div class="preview-card">
                <div class="preview-content">
                    <h3>Tidak ada aktivitas terbaru</h3>
                    <small>Tambahkan konten baru dari menu di samping.</small>
                </div>
            </div>
        <?php else : ?>
            <?php foreach ($activities as $activity) : ?>
                <?php
                $activity['type'] = $activity['activity_type'] ?? '';
                $activity['date'] = $activity['created_at'] ?? '';
                ?>
                <div class="preview-card">
                    <div class="preview-content">
                        <h3><?= escape($activity['description'] ?: $activity['activity_type']) ?></h3>
                        <small><?= escape($activity['type']) ?> &bull; <?= escape($activity['date']) ?></small>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php';
