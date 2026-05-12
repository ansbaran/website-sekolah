<?php

declare(strict_types=1);

define('ADMIN_CONTEXT', true);

require_once __DIR__ . '/../includes/auth.php';
require_login();

$error = '';
$success = false;
$title = '';
$category = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $error = 'Token keamanan tidak valid. Silakan muat ulang halaman.';
    } else {
        $title = clean($_POST['title'] ?? '');
        $category = clean($_POST['category'] ?? 'Galeri');

        if ($title === '' || empty($_FILES['images']['name'][0])) {
            $error = 'Judul galeri dan setidaknya satu gambar wajib diisi.';
        } else {
            $files = $_FILES['images'];
            $uploadedCount = 0;

            foreach ($files['name'] as $index => $name) {
                if (empty($name)) {
                    continue;
                }
                $file = [
                    'name' => $files['name'][$index],
                    'type' => $files['type'][$index],
                    'tmp_name' => $files['tmp_name'][$index],
                    'error' => $files['error'][$index],
                    'size' => $files['size'][$index],
                ];

                $fileName = upload_image($file, 'gallery', $uploadError);
                if ($fileName === null) {
                    $error = $uploadError;
                    break;
                }

                $insert = $pdo->prepare('INSERT INTO gallery (title, category, filename, created_at) VALUES (:title, :category, :filename, NOW())');
                $insert->execute([
                    'title' => $title,
                    'category' => $category,
                    'filename' => $fileName,
                ]);
                log_activity('Upload galeri', 'Gambar galeri ditambahkan: ' . $fileName, 'gallery:new');
                $uploadedCount++;
            }

            if ($error === '' && $uploadedCount > 0) {
                flash('success', "Berhasil mengunggah $uploadedCount file galeri.");
                redirect('gallery.php');
            }
        }
    }
}

$pageTitle = 'Tambah Galeri';
require_once __DIR__ . '/includes/header.php';
?>
<section class="panel">
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:16px;">
        <div>
            <h2><?= htmlspecialchars($pageTitle) ?></h2>
            <p class="footer-note">Unggah beberapa foto sekaligus untuk galeri sekolah.</p>
        </div>
        <a class="btn-secondary" href="gallery.php">Kembali ke galeri</a>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data" class="form-card">
        <?= csrf_field() ?>
        <div class="form-grid">
            <div>
                <label for="title">Nama Album / Judul</label>
                <input type="text" id="title" name="title" value="<?= htmlspecialchars($title) ?>" required>
            </div>
            <div>
                <label for="category">Kategori</label>
                <input type="text" id="category" name="category" value="<?= htmlspecialchars($category) ?>" placeholder="Contoh: Kegiatan Sekolah">
            </div>
            <div style="grid-column:1/-1;">
                <label for="images">Pilih Gambar</label>
                <input type="file" id="images" name="images[]" accept="image/*" multiple required>
                <small class="footer-note">Format JPG, PNG, WEBP. Maks 4MB per file.</small>
            </div>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn-primary">Unggah Galeri</button>
            <a class="btn-secondary" href="gallery.php">Batal</a>
        </div>
    </form>
</section>

<?php require_once __DIR__ . '/includes/footer.php';
