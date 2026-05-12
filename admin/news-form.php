<?php

declare(strict_types=1);

define('ADMIN_CONTEXT', true);

require_once __DIR__ . '/../includes/auth.php';
require_login();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$editing = $id > 0;
$error = '';
$news = [
    'title' => '',
    'category' => '',
    'excerpt' => '',
    'content' => '',
    'published_at' => date('Y-m-d'),
    'thumbnail' => '',
    'is_active' => 1,
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
        $publishedAt = $_POST['published_at'] ?? date('Y-m-d');
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        if ($title === '' || $content === '') {
            $error = 'Judul dan konten berita wajib diisi.';
        } else {
            $thumbnail = $news['thumbnail'];
            if (!empty($_FILES['thumbnail']['name'])) {
                $uploaded = upload_image($_FILES['thumbnail'], 'news', $uploadError);
                if ($uploaded === null) {
                    $error = $uploadError;
                } else {
                    if (!empty($thumbnail)) {
                        delete_file(UPLOAD_BASE . '/news/' . $thumbnail);
                    }
                    $thumbnail = $uploaded;
                }
            }

            if ($error === '') {
                if ($editing) {
                    $stmt = $pdo->prepare('UPDATE news SET title = :title, category = :category, excerpt = :excerpt, content = :content, published_at = :published_at, thumbnail = :thumbnail, is_active = :is_active WHERE id = :id');
                    $stmt->execute([
                        'title' => $title,
                        'category' => $category,
                        'excerpt' => $excerpt,
                        'content' => $content,
                        'published_at' => $publishedAt,
                        'thumbnail' => $thumbnail,
                        'is_active' => $isActive,
                        'id' => $id,
                    ]);
                    log_activity('Edit berita', 'Berita diperbarui: ' . $title, 'news:' . $id);
                    flash('success', 'Berita berhasil diperbarui.');
                } else {
                    $stmt = $pdo->prepare('INSERT INTO news (title, category, excerpt, content, published_at, thumbnail, is_active, created_at) VALUES (:title, :category, :excerpt, :content, :published_at, :thumbnail, :is_active, NOW())');
                    $stmt->execute([
                        'title' => $title,
                        'category' => $category,
                        'excerpt' => $excerpt,
                        'content' => $content,
                        'published_at' => $publishedAt,
                        'thumbnail' => $thumbnail,
                        'is_active' => $isActive,
                    ]);
                    log_activity('Tambah berita', 'Berita baru ditambahkan: ' . $title, 'news:new');
                    flash('success', 'Berita baru berhasil ditambahkan.');
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
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:16px;">
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

        <div class="form-grid">
            <div>
                <label for="title">Judul Berita</label>
                <input type="text" id="title" name="title" value="<?= htmlspecialchars($news['title']) ?>" required>
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
                <label for="thumbnail">Thumbnail Berita</label>
                <input type="file" id="thumbnail" name="thumbnail" accept="image/*">
                <?php if (!empty($news['thumbnail'])) : ?>
                    <small>Thumbnail saat ini: <?= htmlspecialchars($news['thumbnail']) ?></small>
                <?php endif; ?>
            </div>
            <div>
                <label for="excerpt">Ringkasan</label>
                <textarea id="excerpt" name="excerpt"><?= htmlspecialchars($news['excerpt']) ?></textarea>
            </div>
            <div style="grid-column:1/-1;">
                <label for="content">Konten Lengkap</label>
                <textarea id="content" name="content" required><?= htmlspecialchars($news['content']) ?></textarea>
            </div>
            <div style="grid-column:1/-1; display:flex; align-items:center; gap:12px;">
                <label>
                    <input type="checkbox" name="is_active" <?= $news['is_active'] ? 'checked' : '' ?>> Aktifkan berita
                </label>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn-primary"><?= $editing ? 'Update Berita' : 'Simpan Berita' ?></button>
            <a class="btn-secondary" href="news.php">Batal</a>
        </div>
    </form>
</section>

<?php require_once __DIR__ . '/includes/footer.php';
