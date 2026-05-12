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

        $title = clean($_POST['title'] ?? '');
        $altText = clean($_POST['alt_text'] ?? '');
        $targetSubdir = clean($_POST['subdir'] ?? 'misc');
        $files = $_FILES['images'] ?? null;
        $uploadedCount = 0;

        if (empty($files) || empty($files['name'][0])) {
            flash('error', 'Silakan pilih setidaknya satu gambar.');
            redirect('media.php');
        }

        foreach ($files['name'] as $index => $name) {
            if (empty($name) || $files['error'][$index] !== UPLOAD_ERR_OK) {
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
                flash('error', $uploadError);
                redirect('media.php');
            }

            $filePath = build_upload_path($targetSubdir, $fileName);
            $imageInfo = @getimagesize($filePath);
            $meta = [
                'filename' => $fileName,
                'subdir' => $targetSubdir,
                'title' => $title ?: pathinfo($fileName, PATHINFO_FILENAME),
                'alt_text' => $altText,
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

        if ($uploadedCount > 0) {
            log_activity('Upload media', "Mengunggah $uploadedCount gambar ke $targetSubdir", $targetSubdir);
            flash('success', "Berhasil mengunggah $uploadedCount gambar.");
        }

        redirect('media.php');
    }
}

$mediaItems = scan_media_items($filters, $limit, $offset);
$totalItems = count_media_items($filters);
$totalPages = max(1, (int)ceil($totalItems / $limit));

$pageTitle = 'Media Manager';
require_once __DIR__ . '/includes/header.php';
?>
<section class="panel">
    <div style="display:flex; justify-content:space-between; flex-wrap:wrap; gap:16px; align-items:center;">
        <div>
            <h2>Media Manager</h2>
            <p class="footer-note">Upload, pratinjau, cari, dan hapus semua file gambar dari folder uploads.</p>
        </div>
        <a class="btn-secondary" href="media.php">Muat ulang</a>
    </div>

    <form method="get" class="form-grid" style="margin-bottom:18px;">
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
                <option value="misc"<?= $subdir === 'misc' ? ' selected' : '' ?>>misc</option>
            </select>
        </div>
        <div style="align-self:end;">
            <button type="submit" class="btn-primary">Filter</button>
        </div>
    </form>
</section>

<?php if (can('upload')): ?>
<section class="panel form-card">
    <h3>Unggah Gambar</h3>
    <form method="post" enctype="multipart/form-data" class="form-grid" id="media-upload-form">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="upload">
        <div>
            <label for="title">Judul / Label</label>
            <input type="text" id="title" name="title" placeholder="Judul singkat untuk media">
        </div>
        <div>
            <label for="alt_text">Alt Text</label>
            <input type="text" id="alt_text" name="alt_text" placeholder="Deskripsi singkat untuk aksesibilitas">
        </div>
        <div>
            <label for="subdir">Upload ke folder</label>
            <select id="subdir" name="subdir">
                <option value="gallery">gallery</option>
                <option value="news">news</option>
                <option value="achievements">achievements</option>
                <option value="slider">slider</option>
                <option value="misc">misc</option>
            </select>
        </div>
        <div style="grid-column:1/-1;">
            <label for="images">Pilih gambar</label>
            <div id="media-drop-zone" class="drag-drop">Tarik dan letakkan gambar di sini atau klik untuk memilih.</div>
            <input type="file" id="images" name="images[]" accept="image/*" multiple required>
            <small class="footer-note">JPG, PNG, WEBP. Maks 4MB per file. Upload multiple.</small>
        </div>
        <div class="form-actions" style="grid-column:1/-1;">
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
                    <td colspan="6">Tidak ada media yang ditemukan. Unggah file baru di atas.</td>
                </tr>
            <?php endif; ?>
            <?php foreach ($mediaItems as $item): ?>
                <tr>
                    <td><img loading="lazy" src="<?= escape(build_upload_url($item['subdir'], $item['filename'])) ?>" alt="<?= escape($item['title']) ?>" style="width:120px; height:80px; object-fit:cover; border-radius:14px;"></td>
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
                            <form method="post" style="display:inline-block; margin:0;" onsubmit="return confirm('Hapus file ini dari media?');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= $item['id'] ?>">
                                <button type="submit" class="btn-secondary">Hapus</button>
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
        <button type="button" class="btn-tertiary" id="close-media-modal">Tutup</button>
        <img id="media-modal-image" src="" alt="Preview" loading="lazy">
        <p id="media-modal-title"></p>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php';
