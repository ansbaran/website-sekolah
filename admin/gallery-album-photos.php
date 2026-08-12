<?php

declare(strict_types=1);

define('ADMIN_CONTEXT', true);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/gallery-album-admin-functions.php';
require_login();
require_permission('upload');

$id = (int) ($_GET['id'] ?? $_POST['album_id'] ?? 0);
$album = gallery_album_admin_get_album($id);
if (!$album) {
    flash('error', 'Album galeri tidak ditemukan.');
    redirect('gallery-albums.php');
}

$formError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        flash('error', 'Token keamanan tidak valid.');
        redirect('gallery-album-photos.php?id=' . (int) $album['id']);
    }

    $action = (string) ($_POST['action'] ?? '');
    try {
        if ($action === 'upload') {
            if (!can('upload')) {
                flash('error', 'Anda tidak memiliki izin untuk mengunggah foto album.');
                redirect('gallery-album-photos.php?id=' . (int) $album['id']);
            }

            $files = gallery_album_admin_uploaded_file_items($_FILES['images'] ?? null);
            $uploadedCount = gallery_album_admin_insert_uploaded_photos($album, $files);
            log_activity('Upload foto album', 'Mengunggah ' . $uploadedCount . ' foto ke album: ' . $album['title'], 'gallery_album:' . (int) $album['id']);
            flash('success', $uploadedCount . ' foto berhasil ditambahkan.');
            redirect('gallery-album-photos.php?id=' . (int) $album['id']);
        }

        if ($action === 'set_cover') {
            $photoId = (int) ($_POST['photo_id'] ?? 0);
            gallery_album_admin_set_cover((int) $album['id'], $photoId);
            log_activity('Set cover album galeri', 'Cover album diperbarui: ' . $album['title'], 'gallery_album:' . (int) $album['id']);
            flash('success', 'Cover album berhasil diperbarui.');
            redirect('gallery-album-photos.php?id=' . (int) $album['id']);
        }

        if ($action === 'update_photo') {
            $photoId = (int) ($_POST['photo_id'] ?? 0);
            gallery_album_admin_update_photo_metadata($album, $photoId, $_POST);
            log_activity('Edit foto album', 'Metadata foto album diperbarui: ' . $album['title'], 'gallery_photo:' . $photoId);
            flash('success', 'Metadata foto berhasil disimpan.');
            redirect('gallery-album-photos.php?id=' . (int) $album['id']);
        }

        if ($action === 'delete_photo') {
            if (!can('delete')) {
                flash('error', 'Anda tidak memiliki izin untuk menghapus foto album.');
                redirect('gallery-album-photos.php?id=' . (int) $album['id']);
            }

            $photoId = (int) ($_POST['photo_id'] ?? 0);
            gallery_album_admin_delete_photo($album, $photoId);
            log_activity('Hapus foto album', 'Foto album dihapus: ' . $album['title'], 'gallery_photo:' . $photoId);
            flash('success', 'Foto album berhasil dihapus.');
            redirect('gallery-album-photos.php?id=' . (int) $album['id']);
        }

        flash('error', 'Aksi foto album tidak dikenali.');
        redirect('gallery-album-photos.php?id=' . (int) $album['id']);
    } catch (Throwable $exception) {
        log_exception($exception);
        $formError = $exception->getMessage() !== '' ? $exception->getMessage() : 'Aksi foto album belum dapat diproses.';
    }
}

$album = gallery_album_admin_get_album((int) $album['id']) ?: $album;
$photos = gallery_album_admin_list_photos((int) $album['id']);
$pageTitle = 'Kelola Foto Album';
require_once __DIR__ . '/includes/header.php';
?>
<section class="panel">
    <div class="panel-header">
        <div>
            <h2>Kelola Foto Album</h2>
            <p class="footer-note">Upload dan kelola foto untuk album galeri baru tanpa mengubah galeri legacy.</p>
        </div>
        <div class="form-actions">
            <a class="btn-tertiary" href="gallery-album-form.php?id=<?= (int) $album['id'] ?>">Edit Metadata</a>
            <a class="btn-secondary" href="gallery-albums.php">Kembali ke Album Galeri</a>
        </div>
    </div>
</section>

<section class="panel">
    <div class="panel-header">
        <div>
            <h3><?= escape((string) $album['title']) ?></h3>
            <p class="footer-note">
                <?= escape((string) $album['category']) ?> &middot;
                <?= escape(gallery_album_admin_status_label($album['status'])) ?> &middot;
                <?= count($photos) ?> foto
            </p>
        </div>
        <?php if (!empty($album['cover_photo_id'])): ?>
            <span class="status-pill status-pill--active">Cover ID #<?= (int) $album['cover_photo_id'] ?></span>
        <?php else: ?>
            <span class="status-pill status-pill--muted">Belum ada cover</span>
        <?php endif; ?>
    </div>
</section>

<section class="panel form-card">
    <h3>Tambah Foto</h3>
    <?php if ($formError): ?>
        <div class="alert alert-danger"><?= escape($formError) ?></div>
    <?php endif; ?>
    <form method="post" enctype="multipart/form-data" class="form-grid">
        <?= csrf_field() ?>
        <input type="hidden" name="MAX_FILE_SIZE" value="<?= MAX_IMAGE_SIZE ?>">
        <input type="hidden" name="action" value="upload">
        <input type="hidden" name="album_id" value="<?= (int) $album['id'] ?>">
        <div class="full-span">
            <label for="images">Pilih foto album</label>
            <input type="file" id="images" name="images[]" accept="image/jpeg,image/png,image/webp" data-max-size="<?= (int) MAX_IMAGE_SIZE ?>" multiple required>
            <div class="image-upload-guide" role="note">
                <div class="image-upload-guide__title"><span class="image-upload-guide__icon" aria-hidden="true"><i class="fa-solid fa-images"></i></span><span>Fleksibel</span></div>
                <p class="image-upload-guide__specs"><span>Cover/kartu 1500 x 1000 px</span><span>Rasio 3:2</span><span>Minimal sisi panjang 1200 px</span><span>JPG/PNG/WebP</span></p>
                <p class="image-upload-guide__note">Orientasi asli tetap boleh. Target file &lt; 900 KB. Maksimal upload <?= (int) (MAX_IMAGE_SIZE / 1024 / 1024) ?>MB per file, maksimal <?= gallery_album_admin_max_upload_files() ?> foto per upload.</p>
                <p class="image-upload-guide__note">Grid album dapat melakukan crop; posisikan objek utama di tengah.</p>
            </div>
        </div>
        <div class="form-actions full-span">
            <button type="submit" class="btn-primary">+ Tambah Foto</button>
        </div>
    </form>
</section>

<section class="panel">
    <div class="panel-header">
        <div>
            <h3>Foto Album</h3>
            <p class="footer-note">Caption opsional. Alt text kosong akan memakai fallback aksesibilitas otomatis.</p>
        </div>
    </div>

    <?php if (empty($photos)): ?>
        <div class="empty-row">Album ini belum memiliki foto. Tambahkan foto pertama untuk membuat cover otomatis.</div>
    <?php else: ?>
        <div class="admin-card-grid">
            <?php foreach ($photos as $photo): ?>
                <?php
                $photoId = (int) $photo['id'];
                $photoUrl = gallery_album_admin_photo_url($photo['image_path'] ?? '');
                $isCover = !empty($album['cover_photo_id']) && (int) $album['cover_photo_id'] === $photoId;
                ?>
                <article class="form-card">
                    <?php if ($photoUrl !== ''): ?>
                        <img class="media-thumb" src="<?= escape($photoUrl) ?>" alt="<?= escape((string) ($photo['alt_text'] ?: $album['title'])) ?>" loading="lazy" data-fallback-src="../assets/img/logo.png">
                    <?php else: ?>
                        <span class="status-pill status-pill--muted">Path foto tidak valid</span>
                    <?php endif; ?>
                    <div class="form-actions">
                        <?php if ($isCover): ?>
                            <span class="status-pill status-pill--active">Cover</span>
                        <?php else: ?>
                            <form method="post" class="inline-form">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="set_cover">
                                <input type="hidden" name="album_id" value="<?= (int) $album['id'] ?>">
                                <input type="hidden" name="photo_id" value="<?= $photoId ?>">
                                <button type="submit" class="btn-secondary">Set as Cover</button>
                            </form>
                        <?php endif; ?>
                    </div>

                    <form method="post" class="form-grid">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="update_photo">
                        <input type="hidden" name="album_id" value="<?= (int) $album['id'] ?>">
                        <input type="hidden" name="photo_id" value="<?= $photoId ?>">
                        <div class="full-span">
                            <label for="caption-<?= $photoId ?>">Caption</label>
                            <input type="text" id="caption-<?= $photoId ?>" name="caption" maxlength="255" value="<?= escape((string) ($photo['caption'] ?? '')) ?>" placeholder="Opsional">
                        </div>
                        <div class="full-span">
                            <label for="alt-<?= $photoId ?>">Alt Text</label>
                            <input type="text" id="alt-<?= $photoId ?>" name="alt_text" maxlength="255" value="<?= escape((string) ($photo['alt_text'] ?? '')) ?>" placeholder="<?= escape(gallery_album_admin_default_alt_text($album, (int) $photo['sort_order'])) ?>">
                        </div>
                        <div>
                            <label for="sort-<?= $photoId ?>">Urutan</label>
                            <input type="number" id="sort-<?= $photoId ?>" name="sort_order" min="-999999" max="999999" value="<?= (int) $photo['sort_order'] ?>">
                        </div>
                        <div class="form-actions full-span">
                            <button type="submit" class="btn-primary">Simpan</button>
                        </div>
                    </form>

                    <?php if (can('delete')): ?>
                        <form method="post" class="inline-form">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="delete_photo">
                            <input type="hidden" name="album_id" value="<?= (int) $album['id'] ?>">
                            <input type="hidden" name="photo_id" value="<?= $photoId ?>">
                            <button type="submit" class="btn-danger" data-confirm="Hapus foto ini dari album?">Delete</button>
                        </form>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<?php require_once __DIR__ . '/includes/footer.php';
