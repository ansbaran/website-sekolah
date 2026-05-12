<?php

declare(strict_types=1);

define('ADMIN_CONTEXT', true);

require_once __DIR__ . '/../includes/auth.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        flash('error', 'Token keamanan tidak valid.');
    } elseif (!can('delete')) {
        flash('error', 'Anda tidak memiliki izin untuk menghapus media galeri.');
    } else {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $pdo->prepare('SELECT filename, title FROM gallery WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $item = $stmt->fetch();
        if ($item) {
            delete_file(UPLOAD_BASE . '/gallery/' . $item['filename']);
            $delete = $pdo->prepare('DELETE FROM gallery WHERE id = :id');
            $delete->execute(['id' => $id]);
            log_activity('Hapus galeri', 'Galeri dihapus: ' . ($item['title'] ?? $item['filename']), 'gallery:' . $id);
            flash('success', 'Gambar galeri berhasil dihapus.');
        }
    }
    redirect('gallery.php');
}

$statement = $pdo->query('SELECT * FROM gallery ORDER BY created_at DESC');
$galleryItems = $statement->fetchAll();
$pageTitle = 'Galeri';

require_once __DIR__ . '/includes/header.php';
?>
<section class="panel">
    <div style="display:flex; justify-content:space-between; gap:16px; flex-wrap:wrap; align-items:center;">
        <div>
            <h2>Kelola Galeri</h2>
            <p class="footer-note">Upload foto sekolah, preview gambar, dan hapus file yang sudah tidak diperlukan.</p>
        </div>
        <a class="btn-primary" href="gallery-form.php">+ Tambah Galeri</a>
    </div>
</section>

<section class="panel table-wrapper">
    <table>
        <thead>
            <tr>
                <th>Preview</th>
                <th>Judul</th>
                <th>Kategori</th>
                <th>Tanggal</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($galleryItems)): ?>
                <tr>
                    <td colspan="5">Belum ada foto galeri.</td>
                </tr>
            <?php endif; ?>
            <?php foreach ($galleryItems as $item): ?>
                <tr>
                    <td><img src="<?= build_upload_url('gallery', $item['filename']) ?>" style="width: 120px; height: 80px; object-fit: cover; border-radius: 12px;"></td>
                    <td><?= htmlspecialchars($item['title']) ?></td>
                    <td><?= htmlspecialchars($item['category']) ?></td>
                    <td><?= htmlspecialchars($item['created_at']) ?></td>
                    <td>
                        <form method="post" style="display:inline-block; margin:0;" onsubmit="return confirm('Hapus foto galeri ini?');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $item['id'] ?>">
                            <button type="submit" class="btn-secondary">Hapus</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</section>

<?php require_once __DIR__ . '/includes/footer.php';
