<?php

declare(strict_types=1);

define('ADMIN_CONTEXT', true);

require_once __DIR__ . '/../includes/auth.php';
require_login();
require_permission('upload');

$error = '';
$success = false;
$title = '';
$category = 'Kegiatan';
$id = (int)($_GET['id'] ?? 0);
$isEdit = $id > 0;
$galleryItem = null;

if ($isEdit) {
    $statement = $pdo->prepare('SELECT * FROM gallery WHERE id = :id LIMIT 1');
    $statement->execute(['id' => $id]);
    $galleryItem = $statement->fetch();

    if (!$galleryItem) {
        flash('error', 'Data galeri tidak ditemukan.');
        redirect('gallery.php');
    }

    $title = (string)$galleryItem['title'];
    $category = (string)$galleryItem['category'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $error = 'Token keamanan tidak valid. Silakan muat ulang halaman.';
    } else {
        $title = clean($_POST['title'] ?? '');
        $category = normalize_gallery_category((string)($_POST['category'] ?? ''));

        if ($title === '' || $category === null) {
            $error = 'Judul galeri dan kategori valid wajib diisi.';
        } elseif (!$isEdit && empty($_FILES['images']['name'][0])) {
            $error = 'Judul galeri dan setidaknya satu gambar wajib diisi.';
        } else {
            $files = $_FILES['images'];
            $uploadedCount = 0;

            if ($isEdit) {
                $fileToDeleteAfterSave = null;
                $fileName = (string)$galleryItem['filename'];
                if (!empty($files['name'][0])) {
                    $file = [
                        'name' => $files['name'][0],
                        'type' => $files['type'][0],
                        'tmp_name' => $files['tmp_name'][0],
                        'error' => $files['error'][0],
                        'size' => $files['size'][0],
                    ];

                    $newFileName = upload_image($file, 'gallery', $uploadError, image_upload_policy('gallery'));
                    if ($newFileName === null) {
                        $error = $uploadError;
                    } else {
                        $fileToDeleteAfterSave = UPLOAD_BASE . '/gallery/' . $fileName;
                        $fileName = $newFileName;
                    }
                }

                if ($error === '') {
                    $update = $pdo->prepare('UPDATE gallery SET title = :title, category = :category, filename = :filename WHERE id = :id');
                    $update->execute([
                        'title' => $title,
                        'category' => $category,
                        'filename' => $fileName,
                        'id' => $id,
                    ]);
                    log_activity('Edit galeri', 'Data galeri diperbarui: ' . $title, 'gallery:' . $id);
                    flash('success', 'Galeri berhasil diperbarui.');
                    if ($fileToDeleteAfterSave !== null) {
                        delete_file($fileToDeleteAfterSave);
                    }
                    redirect('gallery.php');
                }
            }

            if (!$isEdit) {
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

                    $fileName = upload_image($file, 'gallery', $uploadError, image_upload_policy('gallery'));
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
}

$pageTitle = $isEdit ? 'Edit Galeri' : 'Tambah Galeri';
require_once __DIR__ . '/includes/header.php';
?>
<section class="panel">
    <div class="panel-header">
        <div>
            <h2><?= htmlspecialchars($pageTitle) ?></h2>
            <p class="footer-note"><?= $isEdit ? 'Perbarui judul, kategori, dan foto galeri.' : 'Unggah beberapa foto sekaligus untuk galeri sekolah.' ?></p>
        </div>
        <a class="btn-secondary" href="gallery.php">Kembali ke galeri</a>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data" class="form-card">
        <?= csrf_field() ?>
        <input type="hidden" name="MAX_FILE_SIZE" value="<?= MAX_IMAGE_SIZE ?>">
        <div class="form-grid">
            <div>
                <label for="title">Nama Album / Judul</label>
                <input type="text" id="title" name="title" value="<?= htmlspecialchars($title) ?>" required>
            </div>
            <div>
                <label for="category">Kategori</label>
                <select id="category" name="category" required>
                    <?php foreach (gallery_categories() as $option): ?>
                        <option value="<?= escape($option) ?>" <?= $category === $option ? 'selected' : '' ?>><?= escape($option) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="full-span">
                <label for="images"><?= $isEdit ? 'Ganti Gambar' : 'Pilih Gambar' ?></label>
                <input type="file" id="images" name="images[]" accept="image/*" data-max-size="4194304" <?= $isEdit ? '' : 'multiple required' ?>>
                <div class="image-upload-guide" role="note">
                    <div class="image-upload-guide__title"><span class="image-upload-guide__icon" aria-hidden="true"><i class="fa-solid fa-images"></i></span><span>Fleksibel</span></div>
                    <p class="image-upload-guide__specs"><span>Aman untuk kartu 1500 x 1000 px</span><span>Rasio 3:2</span><span>Minimal sisi panjang 1200 px</span><span>JPG/PNG/WebP</span></p>
                    <p class="image-upload-guide__note">Orientasi asli tetap boleh. Target file &lt; 900 KB. Maksimal upload <?= (int)(MAX_IMAGE_SIZE / 1024 / 1024) ?>MB per file.</p>
                    <p class="image-upload-guide__note">Letakkan objek utama di tengah untuk tampilan grid. <?= $isEdit ? 'Kosongkan jika tidak ingin mengganti foto.' : '' ?></p>
                </div>
            </div>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn-primary"><?= $isEdit ? 'Simpan Perubahan' : 'Unggah Galeri' ?></button>
            <a class="btn-secondary" href="gallery.php">Batal</a>
        </div>
    </form>
</section>

<?php require_once __DIR__ . '/includes/footer.php';
