<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/gallery-album-functions.php';

function album_detail_text($value, string $fallback = ''): string
{
    $text = trim(preg_replace('/\s+/', ' ', strip_tags((string) $value)) ?? '');
    return $text !== '' ? $text : $fallback;
}

function album_detail_excerpt(string $text, int $limit = 155): string
{
    $text = album_detail_text($text);
    if ($text === '') {
        return '';
    }

    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
        return mb_strlen($text, 'UTF-8') > $limit
            ? rtrim(mb_substr($text, 0, $limit - 1, 'UTF-8')) . '...'
            : $text;
    }

    return strlen($text) > $limit ? rtrim(substr($text, 0, $limit - 1)) . '...' : $text;
}

function album_detail_date(?string $value): ?string
{
    if ($value === null || trim($value) === '') {
        return null;
    }

    $timestamp = strtotime($value);
    if ($timestamp === false) {
        return null;
    }

    $months = [
        1 => 'Januari',
        2 => 'Februari',
        3 => 'Maret',
        4 => 'April',
        5 => 'Mei',
        6 => 'Juni',
        7 => 'Juli',
        8 => 'Agustus',
        9 => 'September',
        10 => 'Oktober',
        11 => 'November',
        12 => 'Desember',
    ];

    return date('j', $timestamp) . ' ' . $months[(int) date('n', $timestamp)] . ' ' . date('Y', $timestamp);
}

function album_detail_page_url(string $slug): string
{
    return '/galeri-detail.php?slug=' . rawurlencode($slug);
}

function album_detail_render_error(int $statusCode, string $title, string $message): void
{
    http_response_code($statusCode);
    header('Content-Type: text/html; charset=utf-8');

    $pageTitle = $title . ' | Galeri SD Cahaya Harapan Bekasi';
    $description = 'Album galeri tidak dapat ditampilkan.';
    $robots = 'noindex, follow';
    ?>
<!DOCTYPE html>
<html lang="id">
<head>
    <script src="assets/js/google-tag.js?v=20260731-ga4-1"></script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="<?= escape($robots) ?>">
    <title><?= escape($pageTitle) ?></title>
    <meta name="description" content="<?= escape($description) ?>">
    <link rel="stylesheet" href="assets/css/style.css?v=20260709-public-fixes">
    <link rel="stylesheet" href="assets/css/pages/gallery-album-detail.css">
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script src="assets/js/tailwind-config.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="gallery-album-detail-page bg-white font-sans text-slate-700 antialiased">
    <div id="navbar"></div>
    <main id="main-content" class="gallery-album-detail">
        <section class="album-detail-error" aria-labelledby="album-error-title">
            <div class="album-detail-shell">
                <nav class="album-breadcrumb" aria-label="Breadcrumb">
                    <ol>
                        <li><a href="index.html">Beranda</a></li>
                        <li><a href="galeri.html">Galeri</a></li>
                        <li aria-current="page"><?= escape($title) ?></li>
                    </ol>
                </nav>
                <div class="album-error-panel">
                    <p class="album-error-code"><?= (int) $statusCode ?></p>
                    <h1 id="album-error-title"><?= escape($title) ?></h1>
                    <p><?= escape($message) ?></p>
                    <a class="album-detail-back" href="galeri.html">
                        <span aria-hidden="true">&larr;</span>
                        Kembali ke Galeri
                    </a>
                </div>
            </div>
        </section>
    </main>
    <div id="footer"></div>
    <script type="module" src="assets/js/main.js"></script>
    <script src="assets/js/seo.js"></script>
    <script src="assets/js/maintenance.js"></script>
</body>
</html>
    <?php
}

$rawSlug = isset($_GET['slug']) ? (string) $_GET['slug'] : '';
$slug = gallery_album_validate_slug($rawSlug);

if ($slug === null) {
    album_detail_render_error(400, 'Album Tidak Valid', 'Tautan album galeri tidak valid atau belum lengkap.');
    exit;
}

try {
    $album = get_public_gallery_album_by_slug($slug);
    if ($album === null) {
        album_detail_render_error(404, 'Album Tidak Ditemukan', 'Album galeri yang Anda cari tidak tersedia atau belum dipublikasikan.');
        exit;
    }

    $photos = get_public_gallery_album_photos((int) $album['id']);
} catch (Throwable $exception) {
    log_exception($exception);
    album_detail_render_error(500, 'Album Belum Dapat Ditampilkan', 'Terjadi kendala saat memuat album galeri. Silakan coba lagi nanti.');
    exit;
}

$albumTitle = album_detail_text($album['title'], 'Album Galeri');
$albumDescription = album_detail_text($album['description'] ?? '');
$seoDescription = album_detail_excerpt(
    $albumDescription !== ''
        ? $albumDescription
        : 'Dokumentasi ' . $albumTitle . ' di SD Cahaya Harapan Bekasi.'
);
$canonicalUrl = album_detail_page_url((string) $album['slug']);
$coverImage = $album['cover_image'] ? '/' . ltrim((string) $album['cover_image'], '/') : '';
$formattedDate = album_detail_date($album['event_date'] ?? null);
$photoCount = count($photos);
$photoCountLabel = $photoCount . ' foto';
$jsonLd = [
    '@context' => 'https://schema.org',
    '@type' => 'BreadcrumbList',
    'itemListElement' => [
        [
            '@type' => 'ListItem',
            'position' => 1,
            'name' => 'Beranda',
            'item' => '/',
        ],
        [
            '@type' => 'ListItem',
            'position' => 2,
            'name' => 'Galeri',
            'item' => '/galeri.html',
        ],
        [
            '@type' => 'ListItem',
            'position' => 3,
            'name' => $albumTitle,
            'item' => $canonicalUrl,
        ],
    ],
];

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <script src="assets/js/google-tag.js?v=20260731-ga4-1"></script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="index, follow">
    <title><?= escape($albumTitle) ?> | Galeri SD Cahaya Harapan Bekasi</title>
    <meta name="description" content="<?= escape($seoDescription) ?>">
    <link rel="canonical" href="<?= escape($canonicalUrl) ?>">
    <meta property="og:type" content="website">
    <meta property="og:title" content="<?= escape($albumTitle) ?> | Galeri SD Cahaya Harapan Bekasi">
    <meta property="og:description" content="<?= escape($seoDescription) ?>">
    <meta property="og:url" content="<?= escape($canonicalUrl) ?>">
    <?php if ($coverImage !== ''): ?>
    <meta property="og:image" content="<?= escape($coverImage) ?>">
    <?php endif; ?>
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= escape($albumTitle) ?> | Galeri SD Cahaya Harapan Bekasi">
    <meta name="twitter:description" content="<?= escape($seoDescription) ?>">
    <?php if ($coverImage !== ''): ?>
    <meta name="twitter:image" content="<?= escape($coverImage) ?>">
    <?php endif; ?>
    <script type="application/ld+json" nonce="<?= escape(defined('CSP_NONCE') ? CSP_NONCE : '') ?>">
    <?= json_encode($jsonLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT); ?>
    </script>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css?v=20260709-public-fixes">
    <link rel="stylesheet" href="assets/css/pages/gallery-album-detail.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script src="assets/js/tailwind-config.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="gallery-album-detail-page bg-white font-sans text-slate-700 antialiased">
    <div id="navbar"></div>

    <main id="main-content" class="gallery-album-detail">
        <section class="album-detail-hero" aria-labelledby="album-detail-title">
            <div class="album-detail-shell">
                <nav class="album-breadcrumb" aria-label="Breadcrumb">
                    <ol>
                        <li><a href="index.html">Beranda</a></li>
                        <li><a href="galeri.html">Galeri</a></li>
                        <li aria-current="page"><?= escape($albumTitle) ?></li>
                    </ol>
                </nav>

                <div class="album-detail-heading">
                    <span class="album-detail-badge"><?= escape((string) $album['category']) ?></span>
                    <h1 id="album-detail-title"><?= escape($albumTitle) ?></h1>
                    <dl class="album-detail-meta" aria-label="Informasi album">
                        <?php if ($formattedDate !== null): ?>
                        <div>
                            <dt>Tanggal</dt>
                            <dd><?= escape($formattedDate) ?></dd>
                        </div>
                        <?php endif; ?>
                        <div>
                            <dt>Jumlah foto</dt>
                            <dd><?= escape($photoCountLabel) ?></dd>
                        </div>
                    </dl>
                    <?php if ($albumDescription !== ''): ?>
                    <p class="album-detail-description"><?= escape($albumDescription) ?></p>
                    <?php else: ?>
                    <p class="album-detail-description">Dokumentasi <?= escape($albumTitle) ?> di SD Cahaya Harapan Bekasi.</p>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <section class="album-photo-section" aria-labelledby="album-photo-title">
            <div class="album-detail-shell">
                <div class="album-photo-header">
                    <div>
                        <span class="album-section-kicker">Foto Album</span>
                        <h2 id="album-photo-title">Dokumentasi <?= escape($albumTitle) ?></h2>
                    </div>
                    <a class="album-detail-back" href="galeri.html">
                        <span aria-hidden="true">&larr;</span>
                        Kembali ke Galeri
                    </a>
                </div>

                <?php if ($photoCount > 0): ?>
                <div class="album-photo-grid" data-album-lightbox-grid>
                    <?php foreach ($photos as $index => $photo): ?>
                        <?php
                        $imagePath = $photo['image_path'] ? (string) $photo['image_path'] : '';
                        if ($imagePath === '') {
                            continue;
                        }
                        $caption = album_detail_text($photo['caption'] ?? '');
                        $altText = album_detail_text($photo['alt_text'] ?? '', $caption !== '' ? $caption : $albumTitle);
                        $lightboxTitle = $caption !== '' ? $caption : $albumTitle;
                        ?>
                    <figure class="album-photo-card">
                        <button
                            class="album-photo-button"
                            type="button"
                            data-album-lightbox-item
                            data-album-lightbox-index="<?= (int) $index ?>"
                            data-album-lightbox-src="<?= escape($imagePath) ?>"
                            data-album-lightbox-title="<?= escape($lightboxTitle) ?>"
                            data-album-lightbox-caption="<?= escape($caption !== '' ? $caption : (string) $album['category']) ?>"
                            aria-label="Lihat foto <?= escape($lightboxTitle) ?>"
                        >
                            <img
                                src="<?= escape($imagePath) ?>"
                                alt="<?= escape($altText) ?>"
                                loading="<?= $index === 0 ? 'eager' : 'lazy' ?>"
                                decoding="async"
                                <?= $index === 0 ? 'fetchpriority="high"' : '' ?>
                            >
                        </button>
                        <figcaption>
                            <?= escape($caption !== '' ? $caption : $altText) ?>
                        </figcaption>
                    </figure>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="album-empty-state">
                    <h2>Belum Ada Foto</h2>
                    <p>Album ini sudah tersedia, tetapi dokumentasi fotonya belum ditambahkan.</p>
                </div>
                <?php endif; ?>
            </div>
        </section>
    </main>

    <div id="footer"></div>
    <script type="module" src="assets/js/main.js"></script>
    <script src="assets/js/seo.js"></script>
    <script src="assets/js/maintenance.js"></script>
    <script type="module" src="assets/js/gallery-album-detail.js"></script>
</body>
</html>
