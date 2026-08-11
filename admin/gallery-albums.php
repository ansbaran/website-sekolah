<?php

declare(strict_types=1);

define('ADMIN_CONTEXT', true);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/gallery-album-admin-functions.php';
require_login();
require_permission('upload');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        flash('error', 'Token keamanan tidak valid.');
        redirect('gallery-albums.php');
    }

    if (!can('delete')) {
        flash('error', 'Anda tidak memiliki izin untuk menghapus album galeri.');
        redirect('gallery-albums.php');
    }

    $id = (int) ($_POST['id'] ?? 0);
    $album = gallery_album_admin_get_album($id);
    if (!$album) {
        flash('error', 'Album galeri tidak ditemukan.');
        redirect('gallery-albums.php');
    }

    if ((int) $album['photo_count'] > 0) {
        flash('error', 'Album masih memiliki foto. Hapus foto terlebih dahulu melalui Kelola Foto.');
        redirect('gallery-albums.php');
    }

    try {
        $pdo->beginTransaction();
        $delete = $pdo->prepare('DELETE FROM gallery_albums WHERE id = :id');
        $delete->execute(['id' => $id]);
        $pdo->commit();
        log_activity('Hapus album galeri', 'Album galeri dihapus: ' . $album['title'], 'gallery_album:' . $id);
        flash('success', 'Album galeri berhasil dihapus.');
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        log_exception($exception);
        flash('error', 'Album galeri belum dapat dihapus.');
    }

    redirect('gallery-albums.php');
}

$albums = gallery_album_admin_list_albums();
$pageTitle = 'Album Galeri';

require_once __DIR__ . '/includes/header.php';
?>
<section class="panel">
    <div class="panel-header">
        <div>
            <h2>Album Galeri</h2>
            <p class="footer-note">Kelola metadata album yang tampil pada halaman galeri baru. Foto album dikelola pada fase berikutnya.</p>
        </div>
        <a class="btn-primary" href="gallery-album-form.php">+ Tambah Album</a>
    </div>
</section>

<section class="panel table-wrapper">
    <table>
        <thead>
            <tr>
                <th>Cover</th>
                <th>Album</th>
                <th>Kategori</th>
                <th>Foto</th>
                <th>Status</th>
                <th>Urutan</th>
                <th>Tanggal</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($albums)): ?>
                <tr>
                    <td colspan="8" class="empty-row">Belum ada album galeri.</td>
                </tr>
            <?php endif; ?>
            <?php foreach ($albums as $album): ?>
                <?php $coverUrl = gallery_album_admin_cover_url($album['cover_image'] ?? null); ?>
                <tr>
                    <td>
                        <?php if ($coverUrl !== ''): ?>
                            <img class="table-thumb" src="<?= escape($coverUrl) ?>" alt="<?= escape((string) $album['title']) ?>" loading="lazy" data-fallback-src="../assets/img/logo.png">
                        <?php else: ?>
                            <span class="status-pill status-pill--muted">Tanpa cover</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <strong><?= escape((string) $album['title']) ?></strong><br>
                        <small><?= escape((string) $album['slug']) ?></small>
                    </td>
                    <td><?= escape((string) $album['category']) ?></td>
                    <td><?= (int) $album['photo_count'] ?> foto</td>
                    <td><span class="<?= escape(gallery_album_admin_status_class($album['status'])) ?>"><?= escape(gallery_album_admin_status_label($album['status'])) ?></span></td>
                    <td><?= (int) $album['sort_order'] ?></td>
                    <td>
                        <?= $album['event_date'] ? escape((string) $album['event_date']) : '<span class="status-pill status-pill--muted">-</span>' ?><br>
                        <small>Dibuat: <?= escape((string) $album['created_at']) ?></small>
                    </td>
                    <td>
                        <div class="form-actions">
                            <a class="btn-tertiary" href="gallery-album-form.php?id=<?= (int) $album['id'] ?>">Edit</a>
                            <a class="btn-secondary" href="gallery-album-photos.php?id=<?= (int) $album['id'] ?>">Kelola Foto</a>
                            <?php if (can('delete')): ?>
                                <form method="post" class="inline-form">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= (int) $album['id'] ?>">
                                    <button type="submit" class="btn-danger" data-confirm="Hapus album kosong ini?" <?= (int) $album['photo_count'] > 0 ? 'disabled title="Album masih memiliki foto"' : '' ?>>Hapus</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</section>

<?php require_once __DIR__ . '/includes/footer.php';