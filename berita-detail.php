<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$news = null;
$newsId = null;

/*
|--------------------------------------------------------------------------
| GET NEWS DATA
|--------------------------------------------------------------------------
*/

if (!empty($_GET['id'])) {

    $newsId = (int) $_GET['id'];
    $news = get_news_by_id($newsId);

}

if (empty($news) && !empty($_GET['slug'])) {

    $slug = clean($_GET['slug']);
    $news = get_news_by_slug($slug);

    $newsId = $news['id'] ?? null;
}

/*
|--------------------------------------------------------------------------
| 404 HANDLER
|--------------------------------------------------------------------------
*/

if (empty($news)) {

    http_response_code(404);

    header('Content-Type: text/html; charset=utf-8');

    ?>
    <!DOCTYPE html>
    <html lang="id">
    <head>
    <base href="<?= escape(public_site_path()) ?>">

        <meta charset="UTF-8">

        <meta
            name="viewport"
            content="width=device-width, initial-scale=1.0"
        >

        <title>
            Berita Tidak Ditemukan - SD Cahaya Harapan
        </title>

        <link rel="stylesheet" href="assets/css/style.css?v=20260709-scroll-icon-only">

        <style>

            .news-detail-404{
                min-height:100vh;
                display:flex;
                align-items:center;
                justify-content:center;
                background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);
                color:#fff;
                text-align:center;
                padding:20px;
            }

            .news-detail-404__content{
                max-width:600px;
            }

            .news-detail-404__code{
                font-size:5rem;
                font-weight:800;
                margin-bottom:10px;
            }

            .news-detail-404__title{
                font-size:2rem;
                margin-bottom:12px;
            }

            .news-detail-404__text{
                font-size:1.05rem;
                opacity:.9;
                margin-bottom:28px;
            }

            .news-detail-404__link{
                display:inline-block;
                padding:14px 32px;
                background:#fff;
                color:#667eea;
                text-decoration:none;
                border-radius:12px;
                font-weight:700;
                transition:.3s ease;
            }

            .news-detail-404__link:hover{
                transform:translateY(-3px);
            }

        </style>

    </head>

    <body>

        <div class="news-detail-404">

            <div class="news-detail-404__content">

                <div class="news-detail-404__code">
                    404
                </div>

                <h1 class="news-detail-404__title">
                    Berita Tidak Ditemukan
                </h1>

                <p class="news-detail-404__text">
                    Maaf, berita yang Anda cari tidak tersedia
                    atau mungkin telah dihapus.
                </p>

                <a
                    class="news-detail-404__link"
                    href="news"
                >
                    Kembali ke Berita
                </a>

            </div>

        </div>

    </body>
    </html>
    <?php

    exit;
}

/*
|--------------------------------------------------------------------------
| ACTIVE VALIDATION
|--------------------------------------------------------------------------
*/

if ((int) $news['is_active'] !== 1) {

    http_response_code(403);

    header('Location: ' . public_site_path('news'));

    exit;
}

/*
|--------------------------------------------------------------------------
| INCREMENT VIEW
|--------------------------------------------------------------------------
*/

increment_news_views($newsId);

/*
|--------------------------------------------------------------------------
| RELATED NEWS
|--------------------------------------------------------------------------
*/

$relatedNewsPayload = get_related_news_payload($newsId, 3);
$relatedNews = $relatedNewsPayload['items'];
$relatedSidebarTitle = $relatedNewsPayload['is_fallback'] ? 'Berita Lainnya' : 'Berita Terkait';
$galleryImages = get_news_gallery($newsId);

/*
|--------------------------------------------------------------------------
| SEO
|--------------------------------------------------------------------------
*/

$seoTitle = $news['seo_title'] ?: $news['title'];

$seoDescription =
    $news['seo_description']
    ?: $news['excerpt']
    ?: substr(strip_tags($news['content'] ?? ''), 0, 155);

$featuredImage =
    $news['featured_image']
    ?: ($news['thumbnail'] ?? '');

/*
|--------------------------------------------------------------------------
| URL
|--------------------------------------------------------------------------
*/

$protocol =
    (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    ? 'https://'
    : 'http://';

$baseUrl = rtrim(public_site_url(), '/');

$canonicalUrl = public_canonical_url(
    'news/' . rawurlencode((string) $news['slug'])
);

$currentUrl = $canonicalUrl;

/*
|--------------------------------------------------------------------------
| CONTENT
|--------------------------------------------------------------------------
*/

$content =
    $news['content_long']
    ?: ($news['content'] ?? '');

/*
|--------------------------------------------------------------------------
| READING TIME
|--------------------------------------------------------------------------
*/

$wordCount = str_word_count(strip_tags($content));

$readingTime = max(1, ceil($wordCount / 200));

?>
<!DOCTYPE html>
<html lang="id">

<head>
    <base href="<?= escape(public_site_path()) ?>">
<script src="assets/js/google-tag.js?v=20260731-ga4-1"></script>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        <?= escape($seoTitle) ?> - SD Cahaya Harapan Bekasi
    </title>

    <meta
        name="description"
        content="<?= escape($seoDescription) ?>"
    >

    <link
        rel="canonical"
        href="<?= escape($canonicalUrl) ?>"
    >

    <!-- OPEN GRAPH -->

    <meta
        property="og:title"
        content="<?= escape($seoTitle) ?>"
    >

    <meta
        property="og:description"
        content="<?= escape($seoDescription) ?>"
    >

    <meta
        property="og:type"
        content="article"
    >

    <meta
        property="og:url"
        content="<?= escape($currentUrl) ?>"
    >

    <meta
        property="og:image"
        content="<?= escape(
            $featuredImage
                ? build_upload_url('news', $featuredImage)
                : ''
        ) ?>"
    >

    <!-- TWITTER -->

    <meta
        name="twitter:card"
        content="summary_large_image"
    >

    <meta
        name="twitter:title"
        content="<?= escape($seoTitle) ?>"
    >

    <meta
        name="twitter:description"
        content="<?= escape($seoDescription) ?>"
    >

    <!-- SCHEMA -->

    <script type="application/ld+json" nonce="<?= escape(CSP_NONCE) ?>">
    <?= json_encode([

        "@context" => "https://schema.org",

        "@type" => "NewsArticle",

        "headline" => $news['title'],

        "description" => $seoDescription,

        "image" => $featuredImage
            ? build_upload_url('news', $featuredImage)
            : '',

        "datePublished" =>
            $news['published_at']
            ?: date('Y-m-d'),

        "dateModified" =>
            $news['updated_at'],

        "author" => [

            "@type" => "Organization",

            "name" =>
                "SD Cahaya Harapan Bekasi"

        ]

    ],
    JSON_UNESCAPED_SLASHES
    | JSON_UNESCAPED_UNICODE
    | JSON_PRETTY_PRINT
    ); ?>
    </script>

    <!-- CSS -->

    <link
        rel="stylesheet"
        href="assets/css/style.css?v=20260709-scroll-icon-only"
    >

    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700;800&display=swap"
        rel="stylesheet"
    >
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <script src="assets/js/tailwind-config.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>

</head>

<body class="news-detail-page bg-slate-50 font-sans text-slate-700 antialiased">

    <!-- NAVBAR -->

    <div id="navbar"></div>

    <!-- HERO -->

    <section class="news-detail-hero">

        <div class="news-detail-hero__image-wrapper">

            <?php if ($featuredImage): ?>

                <img
                    class="news-detail-hero__image"

                    src="<?= escape(
                        $featuredImage
                            ? build_upload_url('news', $featuredImage)
                            : 'assets/img/berita/berita1.jpeg'
                    ) ?>"


                    alt="<?= escape($news['title']) ?>"

                    loading="eager"

                    decoding="async"

                    fetchpriority="high"
                >

            <?php else: ?>

                <div class="news-detail-hero__placeholder">

                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        width="64"
                        height="64"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                    >
                        <rect
                            x="3"
                            y="3"
                            width="18"
                            height="18"
                            rx="2"
                        />

                        <circle
                            cx="8.5"
                            cy="8.5"
                            r="1.5"
                        />

                        <path d="M21 15l-5-5L5 21"/>
                    </svg>

                </div>

            <?php endif; ?>

        </div>

    </section>

    <!-- CONTENT -->

    <div class="news-detail-container">

        <!-- MAIN -->

        <main class="news-detail-main">

            <!-- BREADCRUMB -->

            <nav class="news-breadcrumb">

                <a href="./">
                    Beranda
                </a>

                <span>/</span>

                <a href="news">
                    Berita
                </a>

                <span>/</span>

                <span>
                    <?= escape($news['title']) ?>
                </span>

            </nav>

            <!-- META -->

            <div class="news-detail-meta">

                <span class="news-detail-meta__date">

                    <?= escape(
                        date(
                            'd M Y',
                            strtotime(
                                $news['published_at']
                                ?: $news['created_at']
                            )
                        )
                    ) ?>

                </span>

                <span
                    class="news-detail-meta__category"
                    style="
                        background-color:rgba(71,139,141,.12);
                        color:#0D0B61;
                    "
                >
                    <?= escape(
                        $news['category'] ?? 'Umum'
                    ) ?>
                </span>

                <span class="news-detail-meta__views">
                    👁
                    <?= escape((string) $news['views']) ?>
                    Pembaca
                </span>

                <span class="news-detail-meta__reading-time">
                    ⏱ <?= $readingTime ?> menit baca
                </span>

            </div>

            <!-- TITLE -->

            <h1 class="news-detail-title">
                <?= escape($news['title']) ?>
            </h1>

            <!-- EXCERPT -->

            <?php if (!empty($news['excerpt'])): ?>

                <p class="news-detail-excerpt">
                    <?= escape($news['excerpt']) ?>
                </p>

            <?php endif; ?>

            <!-- CONTENT -->

            <div class="news-detail-content">

                <?= nl2br(strip_tags(

                    $content,

                    '<p><br><strong><b><em><i><ul><ol><li><a><h2><h3><blockquote>'

                )) ?>

            </div>

            <!-- GALLERY -->

            <?php if (!empty($galleryImages)): ?>

                <section class="news-detail-gallery">

                    <h3 class="news-detail-gallery__title">
                        Galeri Kegiatan
                    </h3>

                    <div class="news-detail-gallery__grid">

                        <?php foreach ($galleryImages as $image): ?>

                            <div class="news-detail-gallery__item">

                                <img
                                    loading="lazy"

                                    decoding="async"

                                    src="<?= escape(
                                        build_upload_url(
                                            'news',
                                            $image['image']
                                        )
                                    ) ?>"

                                    alt="<?= escape(
                                        $image['caption']
                                        ?? 'Galeri'
                                    ) ?>"
                                >

                                <?php if (!empty($image['caption'])): ?>

                                    <p class="news-detail-gallery__caption">

                                        <?= escape(
                                            $image['caption']
                                        ) ?>

                                    </p>

                                <?php endif; ?>

                            </div>

                        <?php endforeach; ?>

                    </div>

                </section>

            <?php endif; ?>

            <!-- SHARE -->

            <div class="news-detail-share">

                <h4 class="news-detail-share__title">
                    Bagikan Berita
                </h4>

                <div class="news-detail-share__buttons">

                    <!-- WA -->

                    <a
                        class="news-detail-share__button news-detail-share__button--whatsapp"

                        href="https://wa.me/?text=<?= urlencode(
                            $news['title']
                            . ' - '
                            . $currentUrl
                        ) ?>"

                        target="_blank"

                        rel="noopener noreferrer"
                    >
                        WhatsApp
                    </a>

                    <!-- FB -->

                    <a
                        class="news-detail-share__button news-detail-share__button--facebook"

                        href="https://www.facebook.com/sharer/sharer.php?u=<?= urlencode($currentUrl) ?>"

                        target="_blank"

                        rel="noopener noreferrer"
                    >
                        Facebook
                    </a>

                    <!-- COPY -->

                    <button
                        class="news-detail-share__button news-detail-share__button--copy"
                        type="button"
                        data-copy-current-url
                    >
                        Salin Link
                    </button>

                </div>

            </div>

            <!-- BACK -->

            <div class="news-detail-navigation">

                <a
                    class="news-detail-navigation__back"
                    href="news"
                >
                    ← Kembali ke Berita
                </a>

            </div>

        </main>

        <!-- SIDEBAR -->

        <aside class="news-detail-sidebar">

            <div class="news-detail-sidebar__box">

                <h4 class="news-detail-sidebar__title">
                    <?= escape($relatedSidebarTitle) ?>
                </h4>

                <div class="news-detail-sidebar__list">

                    <?php if (!empty($relatedNews)): ?>

                        <?php foreach (
                            array_slice($relatedNews, 0, 3)
                            as $related
                        ): ?>

                            <a
                                class="news-detail-sidebar__item"

                                href="news/<?= rawurlencode(
                                    $related['slug']
                                ) ?>"
                            >

                                <div class="news-detail-sidebar__item-image">

                                    <?php
                                        $relatedImage = $related['featured_image'] ?: ($related['thumbnail'] ?? '');
                                    ?>

                                    <img

                                        loading="lazy"

                                        decoding="async"

                                        src="<?= escape(

                                            $relatedImage

                                            ? build_upload_url(
                                                'news',
                                                $relatedImage
                                            )

                                            : 'assets/img/berita/berita1.jpeg'

                                        ) ?>"

                                        alt="<?= escape(
                                            $related['title']
                                        ) ?>"

                                        onerror="
                                            this.src='assets/img/berita/berita1.jpeg';
                                        "

                                    >

                                </div>

                                <div class="news-detail-sidebar__item-content">

                                    <span class="news-detail-sidebar__item-title">

                                        <?= escape(
                                            substr(
                                                $related['title'],
                                                0,
                                                70
                                            )
                                        ) ?>

                                    </span>

                                    <span class="news-detail-sidebar__item-date">

                                        <?= escape(
                                            date(
                                                'd M Y',
                                                strtotime(
                                                    $related['published_at']
                                                    ?: $related['created_at']
                                                )
                                            )
                                        ) ?>

                                    </span>

                                    <span class="news-detail-sidebar__item-link">
                                        Baca Selengkapnya →
                                    </span>

                                </div>

                            </a>

                        <?php endforeach; ?>

                    <?php else: ?>

                        <p class="news-detail-sidebar__empty">
                            Tidak ada berita terkait.
                        </p>

                    <?php endif; ?>

                </div>

            </div>

        </aside>

    </div>

    <!-- FOOTER -->

    <div id="footer"></div>

    <!-- JS -->

    <script
        type="module"
        src="assets/js/main.js?v=20260826-clean-url1"
    ></script>

    <script src="assets/js/seo.js"></script>

    <script src="assets/js/maintenance.js?v=20260827-maintenance-api-root-1"></script>

    <script src="assets/js/news-detail.js"></script>

</body>
</html>
