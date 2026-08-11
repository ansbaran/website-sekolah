<?php

declare(strict_types=1);

define('ADMIN_CONTEXT', true);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/gallery-album-admin-functions.php';
require_login();
require_permission('upload');

$id = (int) ($_GET['id'] ?? 0);
$editing = $id > 0;
$error = '';
$album = [
    'id' => 0,
    'title' => '',
    'slug' => '',
    'category' => 'Kegiatan',
    'description' => '',
    'event_date' => '',
    'status' => 1,
    'sort_order' => 0,
    'photo_count' => 0,
];

if ($editing) {
    $found = gallery_album_admin_get_album($id);
    if (!$found) {
        flash('error', 'Album galeri tidak ditemukan.');
        redirect('gallery-albums.php');
    }
    $album = array_merge($album, $found);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $error = 'Token keamanan tidak valid. Silakan muat ulang halaman.';
    } else {
        $result = gallery_album_admin_validate_metadata($_POST, $editing ? $album : null);
        $data = $result['data'];
        $errors = $result['errors'];
        $album = array_merge($album, $data);

        if (!empty($errors)) {
            $error = implode(' ', $errors);
        } else {
            try {
                if ($editing) {
                    $stmt = $pdo->prepare('UPDATE gallery_albums SET title = :title, slug = :slug, category = :category, description = :description, event_date = :event_date, status = :status, sort_order = :sort_order, updated_at = NOW() WHERE id = :id');
                    $stmt->execute([
                        'title' => $data['title'],
                        'slug' => $data['slug'],
                        'category' => $data['category'],
                        'description' => $data['description'],
                        'event_date' => $data['event_date'],
                        'status' => $data['status'],
                        'sort_order' => $data['sort_order'],
                        'id' => $id,
                    ]);
                    log_activity('Edit album galeri', 'Album galeri diperbarui: ' . $data['title'], 'gallery_album:' . $id);
                    flash('success', 'Album galeri berhasil diperbarui.');
                } else {
                    $user = current_user() ?? [];
                    $stmt = $pdo->prepare('INSERT INTO gallery_albums (title, slug, category, description, event_date, status, sort_order, cover_photo_id, created_by, created_at, updated_at) VALUES (:title, :slug, :category, :description, :event_date, :status, :sort_order, NULL, :created_by, NOW(), NOW())');
                    $stmt->execute([
                        'title' => $data['title'],
                        'slug' => $data['slug'],
                        'category' => $data['category'],
                        'description' => $data['description'],
                        'event_date' => $data['event_date'],
                        'status' => $data['status'],
                        'sort_order' => $data['sort_order'],
                        'created_by' => !empty($user['id']) ? (int) $user['id'] : null,
                    ]);
                    $newId = (int) $pdo->lastInsertId();
                    log_activity('Tambah album galeri', 'Album galeri baru ditambahkan: ' . $data['title'], 'gallery_album:' . $newId);
                    flash('success', 'Album galeri baru berhasil ditambahkan. Foto dapat ditambahkan pada Fase 3C.');
                }

                redirect('gallery-albums.php');
            } catch (Throwable $exception) {
                log_exception($exception);
                $error = 'Album galeri belum dapat disimpan.';
            }
        }
    }
}

$pageTitle = $editing ? 'Edit Album Galeri' : 'Tambah Album Galeri';
require_once __DIR__ . '/includes/header.php';
?>
<section class="panel">
    <div class="panel-header">
        <div>
            <h2><?= escape($pageTitle) ?></h2>
            <p class="footer-note">Kelola metadata album saja. Upload dan pengaturan foto akan tersedia pada Fase 3C.</p>
        </div>
        <a class="btn-secondary" href="gallery-albums.php">Kembali ke Album Galeri</a>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= escape($error) ?></div>
    <?php endif; ?>

    <form method="post" class="form-card">
        <?= csrf_field() ?>
        <div class="form-grid">
            <div>
                <label for="title">Judul Album</label>
                <input type="text" id="title" name="title" maxlength="220" value="<?= escape((string) $album['title']) ?>" required>
                <small id="slug-preview" class="footer-note">Slug: <?= escape((string) ($album['slug'] ?: '-')) ?></small>
            </div>
            <div>
                <label for="slug">Slug</label>
                <input type="text" id="slug" name="slug" maxlength="220" pattern="[a-z0-9]+(-[a-z0-9]+)*" value="<?= escape((string) $album['slug']) ?>" placeholder="Otomatis dari judul jika kosong">
                <small class="footer-note">Biarkan slug lama agar tautan publik tetap stabil.</small>
            </div>
            <div>
                <label for="category">Kategori</label>
                <select id="category" name="category" required>
                    <?php foreach (gallery_categories() as $category): ?>
                        <option value="<?= escape($category) ?>" <?= (string) $album['category'] === $category ? 'selected' : '' ?>><?= escape($category) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="event_date">Tanggal Kegiatan</label>
                <input type="date" id="event_date" name="event_date" value="<?= escape((string) ($album['event_date'] ?? '')) ?>">
            </div>
            <div>
                <label for="status">Status</label>
                <select id="status" name="status">
                    <?php foreach (gallery_album_admin_status_options() as $value => $label): ?>
                        <option value="<?= (int) $value ?>" <?= (int) $album['status'] === (int) $value ? 'selected' : '' ?>><?= escape($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="sort_order">Urutan</label>
                <input type="number" id="sort_order" name="sort_order" min="-999999" max="999999" value="<?= (int) $album['sort_order'] ?>">
            </div>
            <div class="full-span">
                <label for="description">Deskripsi</label>
                <textarea id="description" name="description" maxlength="5000" placeholder="Opsional"><?= escape((string) ($album['description'] ?? '')) ?></textarea>
            </div>
            <?php if ($editing): ?>
                <div class="full-span">
                    <span class="status-pill status-pill--muted">Foto saat ini: <?= (int) $album['photo_count'] ?></span>
                    <p class="footer-note">Kelola foto album akan dibuka pada Fase 3C. Form ini tidak mengubah foto atau cover.</p>
                </div>
            <?php endif; ?>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn-primary"><?= $editing ? 'Simpan Perubahan' : 'Tambah Album' ?></button>
            <a class="btn-secondary" href="gallery-albums.php">Batal</a>
        </div>
    </form>
</section>

<?php require_once __DIR__ . '/includes/footer.php';