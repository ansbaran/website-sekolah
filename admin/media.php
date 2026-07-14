<?php

declare(strict_types=1);

define('ADMIN_CONTEXT', true);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_login();

$search = clean($_GET['search'] ?? '');
$subdir = clean($_GET['subdir'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 18;
$offset = ($page - 1) * $limit;

$mediaUploadTitle = '';
$mediaUploadAltText = '';
$mediaUploadSubdir = 'gallery';
$uploadFormError = '';
$filters = ['search' => $search];
if ($subdir !== '') {
    $filters['subdir'] = $subdir;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        flash('error', 'Token keamanan tidak valid.');
        redirect('media.php');
    }

    if (isset($_POST['action']) && $_POST['action'] === 'delete') {
        if (!can('delete')) {
            flash('error', 'Anda tidak memiliki izin untuk menghapus media.');
            redirect('media.php');
        }

        $id = (int)($_POST['id'] ?? 0);
        $media = get_media_item($id);
        if ($media) {
            delete_file(UPLOAD_BASE . '/' . trim($media['subdir'], '/') . '/' . $media['filename']);
            delete_media_record($id);
            log_activity('Hapus media', 'Media dihapus: ' . $media['filename'], $media['subdir']);
            flash('success', 'File media berhasil dihapus.');
        }
        redirect('media.php');
    }

    if (isset($_POST['action']) && $_POST['action'] === 'upload') {
        if (!can('upload')) {
            flash('error', 'Anda tidak memiliki izin untuk mengunggah media.');
            redirect('media.php');
        }

        $mediaUploadTitle = clean($_POST['title'] ?? '');
        $mediaUploadAltText = clean($_POST['alt_text'] ?? '');
        $mediaUploadSubdir = clean($_POST['subdir'] ?? 'misc');
        $targetSubdir = $mediaUploadSubdir;
        $files = $_FILES['images'] ?? null;
        $uploadedCount = 0;

        if (empty($files) || empty($files['name'][0])) {
            $uploadFormError = 'Silakan pilih setidaknya satu gambar.';
        } else {
            foreach ($files['name'] as $index => $name) {
                if (empty($name)) {
                    continue;
                }

                $fileData = [
                    'name' => $files['name'][$index],
                    'type' => $files['type'][$index],
                    'tmp_name' => $files['tmp_name'][$index],
                    'error' => $files['error'][$index],
                    'size' => $files['size'][$index],
                ];

                $fileName = upload_image($fileData, $targetSubdir, $uploadError);
                if ($fileName === null) {
                    $uploadFormError = $uploadError;
                    break;
                }

                $filePath = build_upload_path($targetSubdir, $fileName);
                $imageInfo = @getimagesize($filePath);
                $meta = [
                    'filename' => $fileName,
                    'subdir' => $targetSubdir,
                    'title' => $mediaUploadTitle ?: pathinfo($fileName, PATHINFO_FILENAME),
                    'alt_text' => $mediaUploadAltText,
                    'mime_type' => mime_content_type($filePath) ?: 'image/jpeg',
                    'width' => $imageInfo[0] ?? null,
                    'height' => $imageInfo[1] ?? null,
                    'size_bytes' => filesize($filePath),
                    'uploaded_by' => current_user()['name'] ?? 'Admin',
                    'uploaded_by_role' => current_user()['role'] ?? 'operator',
                    'metadata' => [
                        'original_name' => $name,
                    ],
                ];

                create_media_record($meta);
                $uploadedCount++;
            }
        }

        if ($uploadFormError === '' && $uploadedCount > 0) {
            log_activity('Upload media', "Mengunggah $uploadedCount gambar ke $targetSubdir", $targetSubdir);
            flash('success', "Berhasil mengunggah $uploadedCount gambar.");
            redirect('media.php');
        }

        if ($uploadFormError === '') {
            $uploadFormError = 'Tidak ada file valid yang berhasil diunggah.';
        }
    }
}

$mediaItems = scan_media_items($filters, $limit, $offset);
$totalItems = count_media_items($filters);
$totalPages = max(1, (int)ceil($totalItems / $limit));

$pageTitle = 'Media Manager';
require_once __DIR__ . '/includes/header.php';
?>
<section class="panel">
    <div class="panel-header">
        <div>
            <h2>Media Manager</h2>
            <p class="footer-note">Upload, pratinjau, cari, dan hapus semua file gambar dari folder uploads.</p>
        </div>
        <a class="btn-secondary" href="media.php">Muat ulang</a>
    </div>

    <form method="get" class="form-grid">
        <div>
            <label for="search">Cari media</label>
            <input id="search" name="search" value="<?= escape($search) ?>" placeholder="Cari nama file, judul, atau subfolder">
        </div>
        <div>
            <label for="subdir">Filter folder</label>
            <select id="subdir" name="subdir">
                <option value="">Semua folder</option>
                <option value="news"<?= $subdir === 'news' ? ' selected' : '' ?>>news</option>
                <option value="gallery"<?= $subdir === 'gallery' ? ' selected' : '' ?>>gallery</option>
                <option value="achievements"<?= $subdir === 'achievements' ? ' selected' : '' ?>>achievements</option>
                <option value="slider"<?= $subdir === 'slider' ? ' selected' : '' ?>>slider</option>
                <option value="staff"<?= $subdir === 'staff' ? ' selected' : '' ?>>staff</option>
                <option value="misc"<?= $subdir === 'misc' ? ' selected' : '' ?>>misc</option>
            </select>
        </div>
        <div class="align-end">
            <button type="submit" class="btn-primary">Filter</button>
        </div>
    </form>
</section>

<?php if (can('upload')): ?>
<section class="panel form-card">
    <h3>Unggah Gambar</h3>
    <form method="post" enctype="multipart/form-data" class="form-grid" id="media-upload-form">
        <?php if ($uploadFormError): ?>
            <div class="alert alert-danger full-span"><?= escape($uploadFormError) ?></div>
        <?php endif; ?>
        <?= csrf_field() ?>
        <input type="hidden" name="MAX_FILE_SIZE" value="<?= MAX_IMAGE_SIZE ?>">
        <input type="hidden" name="action" value="upload">
        <div>
            <label for="title">Judul / Label</label>
            <input type="text" id="title" name="title" value="<?= escape($mediaUploadTitle) ?>" placeholder="Judul singkat untuk media">
        </div>
        <div>
            <label for="alt_text">Alt Text</label>
            <input type="text" id="alt_text" name="alt_text" value="<?= escape($mediaUploadAltText) ?>" placeholder="Deskripsi singkat untuk aksesibilitas">
        </div>
        <div>
            <label for="upload_subdir">Upload ke folder</label>
            <select id="upload_subdir" name="subdir">
                <option value="gallery"<?= $mediaUploadSubdir === 'gallery' ? ' selected' : '' ?>>gallery</option>
                <option value="news"<?= $mediaUploadSubdir === 'news' ? ' selected' : '' ?>>news</option>
                <option value="achievements"<?= $mediaUploadSubdir === 'achievements' ? ' selected' : '' ?>>achievements</option>
                <option value="slider"<?= $mediaUploadSubdir === 'slider' ? ' selected' : '' ?>>slider</option>
                <option value="staff"<?= $mediaUploadSubdir === 'staff' ? ' selected' : '' ?>>staff</option>
                <option value="misc"<?= $mediaUploadSubdir === 'misc' ? ' selected' : '' ?>>misc</option>
            </select>
        </div>
        <div class="full-span">
            <label for="images">Pilih gambar</label>
            <div id="media-drop-zone" class="drag-drop">Tarik dan letakkan gambar di sini atau klik untuk memilih.</div>
            <input type="file" id="images" name="images[]" accept="image/*" data-max-size="4194304" multiple required>
            <small class="footer-note">JPG, PNG, WEBP. Maks 4MB per file. Upload multiple.</small>
        </div>
        <div class="form-actions full-span">
            <button type="submit" class="btn-primary">Unggah Media</button>
        </div>
    </form>
    <div id="media-preview-list" class="list-preview"></div>
</section>
<?php else: ?>
<section class="panel">
    <div>
        <h3>Akses upload tidak tersedia</h3>
        <p class="footer-note">Anda tidak memiliki izin untuk mengunggah media. Hubungi administrator jika perlu akses.</p>
    </div>
</section>
<?php endif; ?>

<section class="panel table-wrapper">
    <table>
        <thead>
            <tr>
                <th>Preview</th>
                <th>File</th>
                <th>Folder</th>
                <th>Ukuran</th>
                <th>Diperbarui</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($mediaItems)): ?>
                <tr>
                    <td colspan="6" class="empty-row">Tidak ada media yang ditemukan. Unggah file baru di atas.</td>
                </tr>
            <?php endif; ?>
            <?php foreach ($mediaItems as $item): ?>
                <tr>
                    <td><img class="media-thumb" loading="lazy" src="<?= escape(build_upload_url($item['subdir'], $item['filename'])) ?>" alt="<?= escape($item['title']) ?>" data-fallback-src="../assets/img/logo.png"></td>
                    <td>
                        <strong><?= escape($item['title']) ?></strong><br>
                        <small><?= escape($item['filename']) ?></small>
                    </td>
                    <td><?= escape($item['subdir']) ?></td>
                    <td><?= number_format((int)$item['size_bytes'] / 1024, 0) ?> KB</td>
                    <td><?= escape($item['created_at']) ?></td>
                    <td>
                        <button type="button" class="btn-tertiary" data-copy-url="<?= escape(build_upload_url($item['subdir'], $item['filename'])) ?>">Copy URL</button>
                        <button type="button" class="btn-secondary preview-button" data-image-src="<?= escape(build_upload_url($item['subdir'], $item['filename'])) ?>" data-image-title="<?= escape($item['title']) ?>">Preview</button>
                        <?php if (can('delete')): ?>
                            <form method="post" class="inline-form">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= $item['id'] ?>">
                                <button type="submit" class="btn-danger" data-confirm="Hapus file ini dari media?">Hapus</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</section>

<?php if ($totalPages > 1): ?>
    <section class="panel">
        <div class="form-actions">
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <a class="btn-secondary<?= $i === $page ? ' active' : '' ?>" href="?search=<?= urlencode($search) ?>&subdir=<?= urlencode($subdir) ?>&page=<?= $i ?>"><?= $i ?></a>
            <?php endfor; ?>
        </div>
    </section>
<?php endif; ?>

<div id="media-preview-modal" class="media-modal" hidden>
    <div class="media-modal__overlay"></div>
    <div class="media-modal__card">
        <div class="media-modal__top">
            <strong id="media-modal-title">Preview media</strong>
            <button type="button" class="media-modal__close" id="close-media-modal" aria-label="Tutup preview">X</button>
        </div>
        <img id="media-modal-image" alt="Preview" loading="lazy" data-fallback-src="../assets/img/logo.png">
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php';
