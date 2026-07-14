<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$announcement = $id > 0 ? get_public_announcement_by_id($id) : null;

if ($announcement === null) {
    http_response_code(404);
}

function announcement_detail_date(string $date): string
{
    $timestamp = strtotime($date);
    if (!$timestamp) {
        return 'Tanggal belum tersedia';
    }

    $months = [1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'];
    return date('d', $timestamp) . ' ' . $months[(int) date('n', $timestamp)] . ' ' . date('Y', $timestamp);
}


function announcement_detail_excerpt(string $value, int $limit = 155): string
{
    $text = trim(strip_tags($value));
    if (mb_strlen($text) <= $limit) {
        return $text;
    }

    return rtrim(mb_substr($text, 0, $limit), " \t\n\r\0\x0B.,") . '...';
}
$contentParagraphs = [];
if ($announcement !== null) {
    $contentParagraphs = array_values(array_filter(array_map('trim', preg_split('/\R{2,}/', (string)($announcement['content'] ?? '')) ?: [])));
    if (empty($contentParagraphs) && !empty($announcement['content'])) {
        $contentParagraphs = [(string) $announcement['content']];
    }
}

$pageTitle = $announcement ? (string) $announcement['title'] : 'Pengumuman Tidak Ditemukan';
$pageDescription = $announcement ? announcement_detail_excerpt((string) $announcement['content'], 155) : 'Pengumuman yang Anda cari tidak tersedia.';
$currentUrl = ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
?>
<!doctype html>
<html lang="id">
  <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= escape($pageTitle) ?> - SD Cahaya Harapan Bekasi</title>
    <meta name="description" content="<?= escape($pageDescription) ?>">
    <link rel="canonical" href="<?= escape($currentUrl) ?>">
    <script src="assets/js/tailwind-config.js"></script>
    <link rel="stylesheet" href="assets/css/style.css?v=20260709-announcement-flow">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
  </head>
  <body class="announcement-detail-page bg-white font-sans text-slate-700 antialiased">
    <div id="navbar"></div>

    <main class="announcement-detail-main">
      <article class="announcement-detail">
        <div class="announcement-detail__shell">
          <a class="announcement-detail__back" href="pengumuman.php">
            <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
            <span>Kembali ke Pengumuman</span>
          </a>

          <?php if ($announcement === null): ?>
            <section class="announcement-detail__empty">
              <span class="announcement-detail__badge"><i class="fa-solid fa-bullhorn" aria-hidden="true"></i> Pengumuman</span>
              <h1>Pengumuman Tidak Ditemukan</h1>
              <p>Maaf, pengumuman yang Anda cari tidak tersedia atau sudah tidak aktif.</p>
              <a class="announcement-detail__button" href="pengumuman.php">Lihat Pengumuman Lain</a>
            </section>
          <?php else: ?>
            <header class="announcement-detail__hero">
              <span class="announcement-detail__badge"><i class="fa-solid fa-bullhorn" aria-hidden="true"></i> Pengumuman</span>
              <p class="announcement-detail__date"><i class="fa-regular fa-calendar" aria-hidden="true"></i> <?= escape(announcement_detail_date((string)$announcement['published_at'])) ?></p>
              <h1><?= escape((string)$announcement['title']) ?></h1>
            </header>

            <section class="announcement-detail__content" aria-label="Isi pengumuman">
              <?php foreach ($contentParagraphs as $paragraph): ?>
                <p><?= escape($paragraph) ?></p>
              <?php endforeach; ?>
            </section>

            <footer class="announcement-detail__share" aria-label="Bagikan pengumuman">
              <span><i class="fa-solid fa-share-nodes" aria-hidden="true"></i> Bagikan:</span>
              <a class="announcement-detail__share-button announcement-detail__share-button--wa" href="https://wa.me/?text=<?= urlencode($pageTitle . ' - ' . $currentUrl) ?>" target="_blank" rel="noopener noreferrer" aria-label="Bagikan ke WhatsApp"><i class="fa-brands fa-whatsapp" aria-hidden="true"></i></a>
              <a class="announcement-detail__share-button announcement-detail__share-button--fb" href="https://www.facebook.com/sharer/sharer.php?u=<?= urlencode($currentUrl) ?>" target="_blank" rel="noopener noreferrer" aria-label="Bagikan ke Facebook"><i class="fa-brands fa-facebook-f" aria-hidden="true"></i></a>
            </footer>
          <?php endif; ?>
        </div>
      </article>
    </main>

    <div id="footer"></div>
    <script type="module" src="assets/js/main.js?v=20260709-announcement-flow"></script>
    <script src="assets/js/seo.js"></script>
    <script src="assets/js/maintenance.js"></script>
  </body>
</html>