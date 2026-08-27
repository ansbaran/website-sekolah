<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';

$announcements = get_public_announcements(24);

function announcement_date_parts(string $date): array
{
    $timestamp = strtotime($date);
    if (!$timestamp) {
        return ['day' => '--', 'month' => 'Info', 'year' => ''];
    }

    $months = [1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'Mei', 6 => 'Jun', 7 => 'Jul', 8 => 'Agu', 9 => 'Sep', 10 => 'Okt', 11 => 'Nov', 12 => 'Des'];

    return [
        'day' => date('d', $timestamp),
        'month' => $months[(int) date('n', $timestamp)],
        'year' => date('Y', $timestamp),
    ];
}

function announcement_excerpt(string $value, int $limit = 150): string
{
    $text = trim(strip_tags($value));
    if (mb_strlen($text) <= $limit) {
        return $text;
    }

    return rtrim(mb_substr($text, 0, $limit), " \t\n\r\0\x0B.,") . '...';
}
?>
<!doctype html>
<html lang="id">
  <head>
  <script src="assets/js/google-tag.js?v=20260731-ga4-1"></script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Daftar pengumuman resmi SD Cahaya Harapan Bekasi untuk orang tua dan siswa.">
    <link rel="canonical" href="https://sdcahayaharapanbekasi.sch.id/announcements">
    <meta property="og:url" content="https://sdcahayaharapanbekasi.sch.id/announcements">
    <title>Pengumuman - SD Cahaya Harapan Bekasi</title>
    <script src="assets/js/tailwind-config.js"></script>
    <link rel="stylesheet" href="assets/css/style.css?v=20260709-announcement-flow">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
  </head>
  <body class="announcement-board-page bg-white font-sans text-slate-700 antialiased">
    <div id="navbar"></div>

    <main class="announcement-board-main">
      <section class="announcement-board" aria-labelledby="announcement-board-title">
        <div class="announcement-board__inner">
          <header class="announcement-board__head">
            <a class="announcement-board__back" href="./#info-sekolah">
              <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
              <span>Kembali</span>
            </a>
            <span class="announcement-board__eyebrow"><i class="fa-solid fa-bullhorn" aria-hidden="true"></i> Pengumuman</span>
            <h1 id="announcement-board-title">Pengumuman Sekolah</h1>
            <p>Informasi resmi sekolah yang perlu diperhatikan orang tua dan siswa.</p>
          </header>

          <div class="announcement-board__list">
            <?php if (empty($announcements)): ?>
              <div class="announcement-board__empty">Belum ada pengumuman aktif yang dipublikasikan.</div>
            <?php endif; ?>

            <?php foreach ($announcements as $item): ?>
              <?php $dateParts = announcement_date_parts((string)($item['published_at'] ?? '')); ?>
              <article class="announcement-card">
                <a class="announcement-card__link" href="announcements/<?= urlencode((string)($item['id'] ?? '')) ?>">
                  <time class="announcement-card__date" datetime="<?= escape((string)($item['published_at'] ?? '')) ?>">
                    <strong><?= escape($dateParts['day']) ?></strong>
                    <span><?= escape($dateParts['month']) ?></span>
                    <small><?= escape($dateParts['year']) ?></small>
                  </time>
                  <div class="announcement-card__body">
                    <span class="announcement-card__badge">Info Sekolah</span>
                    <h2><?= escape((string)($item['title'] ?? 'Pengumuman Sekolah')) ?></h2>
                    <p><?= escape(announcement_excerpt((string)($item['content'] ?? ''))) ?></p>
                  </div>
                  <span class="announcement-card__arrow" aria-hidden="true"><i class="fa-solid fa-arrow-right"></i></span>
                </a>
              </article>
            <?php endforeach; ?>
          </div>
        </div>
      </section>
    </main>

    <div id="footer"></div>
    <script type="module" src="assets/js/main.js?v=20260826-clean-url1"></script>
    <script src="assets/js/seo.js"></script>
    <script src="assets/js/maintenance.js?v=20260827-maintenance-api-root-1"></script>
  </body>
</html>