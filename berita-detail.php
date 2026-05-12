<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$news = null;
$newsId = null;

if (!empty($_GET['id'])) {
    $newsId = (int)$_GET['id'];
    $news = get_news_by_id($newsId);
}

if (empty($news) && !empty($_GET['slug'])) {
    $slug = clean($_GET['slug']);
    $news = get_news_by_slug($slug);
    $newsId = $news['id'] ?? null;
}

if (empty($news)) {
    http_response_code(404);
    header('Content-Type: text/html; charset=utf-8');
    ?>
    <!DOCTYPE html>
    <html lang="id">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Berita Tidak Ditemukan - SD Cahaya Harapan</title>
        <link rel="stylesheet" href="assets/css/style.css">
        <style>
            .news-detail-404 {
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                text-align: center;
                padding: 20px;
            }
            .news-detail-404__content {
                max-width: 600px;
            }
            .news-detail-404__code {
                font-size: 4rem;
                font-weight: 700;
                margin: 0 0 20px 0;
            }
            .news-detail-404__title {
                font-size: 1.8rem;
                margin: 0 0 16px 0;
                font-weight: 600;
            }
            .news-detail-404__text {
                font-size: 1.1rem;
                margin: 0 0 32px 0;
                opacity: 0.9;
            }
            .news-detail-404__link {
                display: inline-block;
                padding: 12px 32px;
                background: white;
                color: #667eea;
                text-decoration: none;
                border-radius: 8px;
                font-weight: 600;
                transition: transform 0.3s ease;
            }
            .news-detail-404__link:hover {
                transform: translateY(-2px);
            }
        </style>
    </head>
    <body>
        <div class="news-detail-404">
            <div class="news-detail-404__content">
                <div class="news-detail-404__code">404</div>
                <h1 class="news-detail-404__title">Berita Tidak Ditemukan</h1>
                <p class="news-detail-404__text">Maaf, berita yang Anda cari tidak tersedia atau mungkin telah dihapus.</p>
                <a class="news-detail-404__link" href="berita.html">Kembali ke Berita</a>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

if ($news['is_active'] != 1) {
    http_response_code(403);
    header('Location: berita.html');
    exit;
}

increment_news_views($newsId);

$relatedNews = get_related_news($newsId, 3);
$galleryImages = get_news_gallery($newsId);

$seoTitle = $news['seo_title'] ?: $news['title'];
$seoDescription = $news['seo_description'] ?: $news['excerpt'] ?: substr(strip_tags($news['content'] ?? ''), 0, 155);
$featuredImage = $news['featured_image'] ?: ($news['thumbnail'] ?? '');

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= escape($seoTitle) ?> - SD Cahaya Harapan Bekasi</title>
    <meta name="description" content="<?= escape($seoDescription) ?>">
    <meta property="og:title" content="<?= escape($seoTitle) ?>">
    <meta property="og:description" content="<?= escape($seoDescription) ?>">
    <meta property="og:image" content="<?= escape($featuredImage ? build_upload_url('news', $featuredImage) : '') ?>">
    <meta property="og:type" content="article">
    <meta property="og:url" content="<?= escape($_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']) ?>">
    <link rel="canonical" href="<?= escape($_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . '/berita-detail.php?slug=' . urlencode($news['slug'])) ?>">
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "NewsArticle",
        "headline": "<?= json_encode($news['title']) ?>",
        "description": "<?= json_encode($seoDescription) ?>",
        "image": "<?= escape($featuredImage ? build_upload_url('news', $featuredImage) : '') ?>",
        "datePublished": "<?= $news['published_at'] ?: date('Y-m-d') ?>",
        "dateModified": "<?= $news['updated_at'] ?>",
        "author": {
            "@type": "Organization",
            "name": "SD Cahaya Harapan Bekasi"
        }
    }
    </script>
    <link rel="stylesheet" href="assets/css/components.css">
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/news-detail.css">
</head>
<body class="news-detail-page">
    <div id="navbar"></div>

    <article class="news-detail-hero">
        <div class="news-detail-hero__image-wrapper">
            <?php if ($featuredImage): ?>
                <img 
                    class="news-detail-hero__image" 
                    src="<?= escape(build_upload_url('news', $featuredImage)) ?>" 
                    alt="<?= escape($news['title']) ?>"
                    loading="eager"
                >
            <?php else: ?>
                <div class="news-detail-hero__placeholder">
                    <svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/></svg>
                </div>
            <?php endif; ?>
        </div>
    </article>

    <div class="news-detail-container">
        <div class="news-detail-main">
            <div class="news-detail-meta">
                <span class="news-detail-meta__date">
                    <?= escape(date('d M Y', strtotime($news['published_at'] ?: $news['created_at']))) ?>
                </span>
                <span class="news-detail-meta__category" style="background-color: rgba(37, 99, 235, 0.1); color: #2563eb;">
                    <?= escape($news['category'] ?? 'Umum') ?>
                </span>
                <span class="news-detail-meta__views">
                    👁 <?= escape((string)$news['views']) ?> Pembaca
                </span>
            </div>

            <h1 class="news-detail-title"><?= escape($news['title']) ?></h1>

            <?php if (!empty($news['excerpt'])): ?>
                <p class="news-detail-excerpt"><?= escape($news['excerpt']) ?></p>
            <?php endif; ?>

            <div class="news-detail-content">
                <?php 
                $content = $news['content_long'] ?: $news['content'] ?? '';
                echo nl2br(escape($content));
                ?>
            </div>

            <?php if (!empty($galleryImages)): ?>
                <div class="news-detail-gallery">
                    <h3 class="news-detail-gallery__title">Galeri Kegiatan</h3>
                    <div class="news-detail-gallery__grid">
                        <?php foreach ($galleryImages as $image): ?>
                            <div class="news-detail-gallery__item">
                                <img 
                                    loading="lazy"
                                    src="<?= escape(build_upload_url('news', $image['image'])) ?>" 
                                    alt="<?= escape($image['caption'] ?? 'Galeri') ?>"
                                >
                                <?php if (!empty($image['caption'])): ?>
                                    <p class="news-detail-gallery__caption"><?= escape($image['caption']) ?></p>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <div class="news-detail-share">
                <h4 class="news-detail-share__title">Bagikan Berita</h4>
                <div class="news-detail-share__buttons">
                    <a 
                        class="news-detail-share__button news-detail-share__button--whatsapp"
                        href="https://wa.me/?text=<?= urlencode($news['title'] . ' - ' . $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']) ?>"
                        target="_blank"
                        rel="noopener noreferrer"
                    >
                        WhatsApp
                    </a>
                    <a 
                        class="news-detail-share__button news-detail-share__button--facebook"
                        href="https://www.facebook.com/sharer/sharer.php?u=<?= urlencode($_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']) ?>"
                        target="_blank"
                        rel="noopener noreferrer"
                    >
                        Facebook
                    </a>
                    <button 
                        class="news-detail-share__button news-detail-share__button--copy"
                        onclick="copyLink()"
                    >
                        Salin Link
                    </button>
                </div>
            </div>

            <div class="news-detail-navigation">
                <a class="news-detail-navigation__back" href="berita.html">← Kembali ke Berita</a>
            </div>
        </div>

        <aside class="news-detail-sidebar">
            <div class="news-detail-sidebar__box">
                <h4 class="news-detail-sidebar__title">Berita Terkait</h4>
                <div class="news-detail-sidebar__list">
                    <?php if (!empty($relatedNews)): ?>
                        <?php foreach (array_slice($relatedNews, 0, 4) as $related): ?>
                            <a class="news-detail-sidebar__item" href="berita-detail.php?slug=<?= urlencode($related['slug']) ?>">
                                <span class="news-detail-sidebar__item-title">
                                    <?= escape(substr($related['title'], 0, 60)) ?>
                                </span>
                                <span class="news-detail-sidebar__item-date">
                                    <?= escape(date('d M Y', strtotime($related['published_at'] ?: $related['created_at']))) ?>
                                </span>
                            </a>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="news-detail-sidebar__empty">Tidak ada berita terkait.</p>
                    <?php endif; ?>
                </div>
            </div>
        </aside>
    </div>

    <div id="footer"></div>

    <script type="module" src="assets/js/main.js"></script>
    <script src="assets/js/seo.js"></script>
    <script src="assets/js/maintenance.js"></script>
    <script>
        function copyLink() {
            const url = window.location.href;
            navigator.clipboard.writeText(url).then(() => {
                const btn = event.target;
                const originalText = btn.textContent;
                btn.textContent = 'Berhasil disalin!';
                setTimeout(() => {
                    btn.textContent = originalText;
                }, 2000);
            }).catch(() => {
                window.prompt('Salin URL berikut:', url);
            });
        }
    </script>
</body>
</html>
