<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';

header('Content-Type: application/xml; charset=utf-8');

$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
$origin = ($isHttps ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? 'localhost');
$base = rtrim($origin . (defined('BASE_URL') ? BASE_URL : ''), '/');

$staticPages = [
    ['loc' => '/', 'priority' => '1.0'],
    ['loc' => '/tentang.html', 'priority' => '0.8'],
    ['loc' => '/program.html', 'priority' => '0.8'],
    ['loc' => '/guru-staff.html', 'priority' => '0.8'],
    ['loc' => '/berita.html', 'priority' => '0.8'],
    ['loc' => '/galeri.html', 'priority' => '0.7'],
    ['loc' => '/prestasi.html', 'priority' => '0.7'],
    ['loc' => '/kegiatan.html', 'priority' => '0.7'],
    ['loc' => '/ekstrakurikuler.html', 'priority' => '0.7'],
];

echo '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL;
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
<?php foreach ($staticPages as $page): ?>
  <url>
    <loc><?= escape($base . $page['loc']) ?></loc>
    <changefreq>weekly</changefreq>
    <priority><?= $page['priority'] ?></priority>
  </url>
<?php endforeach; ?>
<?php
$stmt = $pdo->query('SELECT slug, updated_at, published_at FROM news WHERE is_active = 1 AND slug IS NOT NULL AND slug != "" ORDER BY published_at DESC LIMIT 200');
foreach ($stmt->fetchAll() as $news):
?>
  <url>
    <loc><?= escape($base . '/berita-detail.php?slug=' . rawurlencode((string)$news['slug'])) ?></loc>
    <lastmod><?= escape(date('Y-m-d', strtotime((string)($news['updated_at'] ?: $news['published_at'] ?: 'now')))) ?></lastmod>
    <changefreq>monthly</changefreq>
    <priority>0.6</priority>
  </url>
<?php endforeach; ?>
</urlset>
