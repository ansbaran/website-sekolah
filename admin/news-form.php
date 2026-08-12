<?php

declare(strict_types=1);

define('ADMIN_CONTEXT', true);

require_once __DIR__ . '/../includes/auth.php';
require_login();
require_permission('publish');

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$editing = $id > 0;
$error = '';
$news = [
    'title' => '',
    'slug' => '',
    'category' => '',
    'excerpt' => '',
    'content' => '',
    'content_long' => '',
    'featured_image' => '',
    'published_at' => date('Y-m-d'),
    'thumbnail' => '',
    'is_active' => 1,
    'is_featured' => 0,
    'seo_title' => '',
    'seo_description' => '',
];

if ($editing) {
    $statement = $pdo->prepare('SELECT * FROM news WHERE id = :id LIMIT 1');
    $statement->execute(['id' => $id]);
    $news = $statement->fetch() ?: $news;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $error = 'Token keamanan tidak valid. Silakan muat ulang halaman.';
    } else {
        $title = clean($_POST['title'] ?? '');
        $category = clean($_POST['category'] ?? 'Umum');
        $excerpt = clean($_POST['excerpt'] ?? '');
        $content = clean($_POST['content'] ?? '');
        $contentLong = clean($_POST['content_long'] ?? '');
        $publishedAt = $_POST['published_at'] ?? date('Y-m-d');
        $isFeatured = isset($_POST['is_featured']) ? 1 : 0;
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        $seoTitle = clean($_POST['seo_title'] ?? '');
        $seoDescription = clean($_POST['seo_description'] ?? '');

        $news = array_merge($news, [
            'title' => $title,
            'category' => $category,
            'excerpt' => $excerpt,
            'content' => $content,
            'content_long' => $contentLong,
            'published_at' => $publishedAt,
            'is_active' => $isActive,
            'is_featured' => $isFeatured,
            'seo_title' => $seoTitle,
            'seo_description' => $seoDescription,
        ]);

        if ($title === '') {
            $error = 'Judul berita wajib diisi.';
        } else {
            $contentFinal = $contentLong !== '' ? $contentLong : $content;
            if ($contentFinal === '') {
                $error = 'Konten berita wajib diisi.';
            }
        }

        if ($error === '') {
            $slug = generate_slug($title);
            if ($editing) {
                $slug = ensure_unique_slug($slug, $id);
            } else {
                $slug = ensure_unique_slug($slug);
            }

            $filesToDeleteAfterSave = [];
            $featuredImage = $news['featured_image'];
            if (!empty($_FILES['featured_image']['name'])) {
                $uploaded = upload_image($_FILES['featured_image'], 'news', $uploadError, image_upload_policy('news_featured'));
                if ($uploaded === null) {
                    $error = $uploadError;
                } else {
                    if (!empty($featuredImage)) {
                        $filesToDeleteAfterSave[] = UPLOAD_BASE . '/news/' . $featuredImage;
                    }
                    $featuredImage = $uploaded;
                }
            }

            $thumbnail = $news['thumbnail'];
            if (!empty($_FILES['thumbnail']['name'])) {
                $uploaded = upload_image($_FILES['thumbnail'], 'news', $uploadError, image_upload_policy('news_thumbnail'));
                if ($uploaded === null) {
                    $error = $uploadError;
                } else {
                    if (!empty($thumbnail)) {
                        $filesToDeleteAfterSave[] = UPLOAD_BASE . '/news/' . $thumbnail;
                    }
                    $thumbnail = $uploaded;
                }
            }

            if ($error === '') {
                if ($editing) {
                    $stmt = $pdo->prepare('UPDATE news SET title = :title, slug = :slug, category = :category, excerpt = :excerpt, content = :content, content_long = :content_long, featured_image = :featured_image, published_at = :published_at, thumbnail = :thumbnail, is_active = :is_active, is_featured = :is_featured, seo_title = :seo_title, seo_description = :seo_description, updated_at = NOW() WHERE id = :id');
                    $stmt->execute([
                        'title' => $title,
                        'slug' => $slug,
                        'category' => $category,
                        'excerpt' => $excerpt,
                        'content' => $content,
                        'content_long' => $contentFinal,
                        'featured_image' => $featuredImage,
                        'published_at' => $publishedAt,
                        'thumbnail' => $thumbnail,
                        'is_active' => $isActive,
                        'is_featured' => $isFeatured,
                        'seo_title' => $seoTitle,
                        'seo_description' => $seoDescription,
                        'id' => $id,
                    ]);
                    log_activity('Edit berita', 'Berita diperbarui: ' . $title, 'news:' . $id);
                    flash('success', 'Berita berhasil diperbarui.');
                } else {
                    $stmt = $pdo->prepare('INSERT INTO news (title, slug, category, excerpt, content, content_long, featured_image, published_at, thumbnail, is_active, is_featured, seo_title, seo_description, created_at) VALUES (:title, :slug, :category, :excerpt, :content, :content_long, :featured_image, :published_at, :thumbnail, :is_active, :is_featured, :seo_title, :seo_description, NOW())');
                    $stmt->execute([
                        'title' => $title,
                        'slug' => $slug,
                        'category' => $category,
                        'excerpt' => $excerpt,
                        'content' => $content,
                        'content_long' => $contentFinal,
                        'featured_image' => $featuredImage,
                        'published_at' => $publishedAt,
                        'thumbnail' => $thumbnail,
                        'is_active' => $isActive,
                        'is_featured' => $isFeatured,
                        'seo_title' => $seoTitle,
                        'seo_description' => $seoDescription,
                    ]);
                    log_activity('Tambah berita', 'Berita baru ditambahkan: ' . $title, 'news:new');
                    flash('success', 'Berita baru berhasil ditambahkan.');
                }

                foreach ($filesToDeleteAfterSave as $fileToDelete) {
                    delete_file($fileToDelete);
                }

                redirect('news.php');
            }
        }
    }
}

$pageTitle = $editing ? 'Edit Berita' : 'Tambah Berita';
require_once __DIR__ . '/includes/header.php';
?>
<section class="panel">
    <div class="panel-header">
        <div>
            <h2><?= htmlspecialchars($pageTitle) ?></h2>
            <p class="footer-note">Kelola informasi berita secara cepat dan aman.</p>
        </div>
        <a class="btn-secondary" href="news.php">Kembali ke daftar</a>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data" class="form-card">
        <?= csrf_field() ?>
        <input type="hidden" name="MAX_FILE_SIZE" value="<?= MAX_IMAGE_SIZE ?>">

        <div class="form-grid">
            <div>
                <label for="title">Judul Berita</label>
                <input type="text" id="title" name="title" value="<?= htmlspecialchars($news['title']) ?>" required>
                <small id="slug-preview" class="footer-note">Slug: -</small>
            </div>
            <div>
                <label for="category">Kategori</label>
                <input type="text" id="category" name="category" value="<?= htmlspecialchars($news['category']) ?>" placeholder="Contoh: Kegiatan, Pengumuman">
            </div>
            <div>
                <label for="published_at">Tanggal Publish</label>
                <input type="date" id="published_at" name="published_at" value="<?= htmlspecialchars($news['published_at']) ?>" required>
            </div>
            <div>
                <label for="featured_image">Featured Image (Detail Page)</label>
                <input type="file" id="featured_image" name="featured_image" accept="image/*" data-max-size="4194304">
                <div class="image-upload-guide" role="note">
                    <div class="image-upload-guide__title"><span class="image-upload-guide__icon" aria-hidden="true"><i class="fa-solid fa-image"></i></span><span>Sangat Disarankan</span></div>
                    <p class="image-upload-guide__specs"><span>1600 x 900 px</span><span>Rasio 16:9</span><span>Minimal 1200 x 675 px</span><span>JPG/PNG/WebP</span></p>
                    <p class="image-upload-guide__note">Target file &lt; 500 KB. Maksimal upload <?= (int)(MAX_IMAGE_SIZE / 1024 / 1024) ?>MB.</p>
                    <p class="image-upload-guide__note">Letakkan objek utama di tengah karena gambar detail berita dapat tampil dengan crop.</p>
                </div>
                <?php if (!empty($news['featured_image'])): ?>
                    <small class="footer-note">Gambar saat ini: <?= htmlspecialchars($news['featured_image']) ?></small>
                <?php endif; ?>
            </div>
            <div>
                <label for="thumbnail">Thumbnail (Listing Page)</label>
                <input type="file" id="thumbnail" name="thumbnail" accept="image/*" data-max-size="4194304">
                <div class="image-upload-guide" role="note">
                    <div class="image-upload-guide__title"><span class="image-upload-guide__icon" aria-hidden="true"><i class="fa-solid fa-image"></i></span><span>Rekomendasi</span></div>
                    <p class="image-upload-guide__specs"><span>1600 x 1000 px</span><span>Rasio 16:10</span><span>Minimal 960 x 600 px</span><span>JPG/PNG/WebP</span></p>
                    <p class="image-upload-guide__note">Target file &lt; 400 KB. Maksimal upload <?= (int)(MAX_IMAGE_SIZE / 1024 / 1024) ?>MB.</p>
                    <p class="image-upload-guide__note">Posisikan subjek di tengah agar aman saat kartu berita melakukan crop.</p>
                </div>
                <?php if (!empty($news['thumbnail'])): ?>
                    <small class="footer-note">Thumbnail saat ini: <?= htmlspecialchars($news['thumbnail']) ?></small>
                <?php endif; ?>
            </div>
            <div>
                <label>
                    <input type="checkbox" name="is_featured" <?= $news['is_featured'] ? 'checked' : '' ?>> Featured News
                </label>
                <label>
                    <input type="checkbox" name="is_active" <?= $news['is_active'] ? 'checked' : '' ?>> Aktifkan berita
                </label>
            </div>
            <div class="full-span">
                <label for="excerpt">Ringkasan (Preview)</label>
                <textarea id="excerpt" name="excerpt" placeholder="Ringkasan singkat akan ditampilkan di listing dan preview. Jika kosong, akan di-auto generate."><?= htmlspecialchars($news['excerpt']) ?></textarea>
                <small class="footer-note">Opsional. Jika kosong, sistem akan auto-generate dari konten.</small>
            </div>
            <div class="full-span">
                <label for="content">Konten Singkat (Legacy)</label>
                <textarea id="content" name="content"><?= htmlspecialchars($news['content']) ?></textarea>
                <small class="footer-note">Untuk backward compatibility. Gunakan "Konten Lengkap" untuk fitur detail page.</small>
            </div>
            <div class="full-span">
                <label for="content_long">Konten Lengkap (Detail Page)</label>
                <textarea id="content_long" name="content_long" placeholder="Konten lengkap yang akan ditampilkan di halaman detail berita."><?= htmlspecialchars($news['content_long']) ?></textarea>
                <small class="footer-note">Konten lengkap untuk halaman detail. Jika kosong, akan pakai konten singkat.</small>
            </div>
            <div class="full-span">
                <label for="seo_title">SEO Title</label>
                <input type="text" id="seo_title" name="seo_title" value="<?= htmlspecialchars($news['seo_title']) ?>" placeholder="Jika kosong, akan pakai judul berita.">
                <small class="footer-note">Untuk meta tag title. Max 60 karakter optimal.</small>
            </div>
            <div class="full-span">
                <label for="seo_description">SEO Description</label>
                <input type="text" id="seo_description" name="seo_description" value="<?= htmlspecialchars($news['seo_description']) ?>" placeholder="Jika kosong, akan pakai ringkasan.">
                <small class="footer-note">Untuk meta tag description. Max 155 karakter optimal.</small>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn-primary"><?= $editing ? 'Update Berita' : 'Simpan Berita' ?></button>
            <a class="btn-secondary" href="news.php">Batal</a>
        </div>
    </form>
</section>

<?php require_once __DIR__ . '/includes/footer.php';
