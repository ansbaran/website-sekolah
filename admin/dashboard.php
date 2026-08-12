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
    <section class="card stat-card stat-card--news">
        <div class="stat-card__content">
            <span class="stat-card__label">Total Berita</span>
            <div class="stat-value"><?= $totalNews ?></div>
        </div>
        <span class="stat-card__icon" aria-hidden="true">
            <svg viewBox="0 0 24 24"><path d="M5 5.5A2.5 2.5 0 0 1 7.5 3h9A2.5 2.5 0 0 1 19 5.5v13A2.5 2.5 0 0 1 16.5 21h-9A2.5 2.5 0 0 1 5 18.5v-13Z"/><path d="M8.5 8h7M8.5 12h7M8.5 16h4"/></svg>
        </span>
        <span class="stat-card__sparkline" aria-hidden="true"></span>
    </section>
    <section class="card stat-card stat-card--gallery">
        <div class="stat-card__content">
            <span class="stat-card__label">Total Galeri</span>
            <div class="stat-value"><?= $totalGallery ?></div>
        </div>
        <span class="stat-card__icon" aria-hidden="true">
            <svg viewBox="0 0 24 24"><path d="M4 7a3 3 0 0 1 3-3h10a3 3 0 0 1 3 3v10a3 3 0 0 1-3 3H7a3 3 0 0 1-3-3V7Z"/><path d="m5 16 4.2-4.2a1.4 1.4 0 0 1 2 0L16 16.6"/><path d="m14 14 1.1-1.1a1.4 1.4 0 0 1 2 0L20 15.8"/><path d="M15.5 8.5h.01"/></svg>
        </span>
        <span class="stat-card__sparkline" aria-hidden="true"></span>
    </section>
    <section class="card stat-card stat-card--announcement">
        <div class="stat-card__content">
            <span class="stat-card__label">Total Pengumuman</span>
            <div class="stat-value"><?= $totalAnnouncements ?></div>
        </div>
        <span class="stat-card__icon" aria-hidden="true">
            <svg viewBox="0 0 24 24"><path d="M4 13V9a2 2 0 0 1 2-2h3l8-3v14l-8-3H6a2 2 0 0 1-2-2Z"/><path d="M9 15v4M18 9.5a3.5 3.5 0 0 1 0 5"/></svg>
        </span>
        <span class="stat-card__sparkline" aria-hidden="true"></span>
    </section>
    <section class="card stat-card stat-card--staff">
        <div class="stat-card__content">
            <span class="stat-card__label">Total Guru & Staff</span>
            <div class="stat-value"><?= $totalStaff ?></div>
        </div>
        <span class="stat-card__icon" aria-hidden="true">
            <svg viewBox="0 0 24 24"><path d="M8.5 11.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7ZM2.8 19.5a5.7 5.7 0 0 1 11.4 0"/><path d="M17 11a2.8 2.8 0 1 0 0-5.6"/><path d="M15.8 14.3a4.8 4.8 0 0 1 5.4 4.8"/></svg>
        </span>
        <span class="stat-card__sparkline" aria-hidden="true"></span>
    </section>
    <section class="card stat-card stat-card--achievement">
        <div class="stat-card__content">
            <span class="stat-card__label">Total Prestasi</span>
            <div class="stat-value"><?= $totalAchievements ?></div>
        </div>
        <span class="stat-card__icon" aria-hidden="true">
            <svg viewBox="0 0 24 24"><path d="M12 14.5a5.5 5.5 0 1 0 0-11 5.5 5.5 0 0 0 0 11Z"/><path d="m9.2 13.8-1.1 6.7 3.9-2.3 3.9 2.3-1.1-6.7"/><path d="m10 9 1.3 1.3L14.2 7"/></svg>
        </span>
        <span class="stat-card__sparkline" aria-hidden="true"></span>
    </section>
    <section class="card stat-card stat-card--slider">
        <div class="stat-card__content">
            <span class="stat-card__label">Total Slider</span>
            <div class="stat-value"><?= $totalSlides ?></div>
        </div>
        <span class="stat-card__icon" aria-hidden="true">
            <svg viewBox="0 0 24 24"><path d="M4 7a3 3 0 0 1 3-3h10a3 3 0 0 1 3 3v10a3 3 0 0 1-3 3H7a3 3 0 0 1-3-3V7Z"/><path d="m9 8 4 4-4 4"/></svg>
        </span>
        <span class="stat-card__sparkline" aria-hidden="true"></span>
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
        <?php endif; ?>
        <?php if (can('upload')): ?>
            <a class="btn-secondary" href="gallery-form.php">Tambah Galeri</a>
            <a class="btn-tertiary" href="media.php">Media Manager</a>
        <?php endif; ?>
        <?php if (can('maintenance')): ?>
            <a class="btn-tertiary" href="system.php">Pengaturan PPDB</a>
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
