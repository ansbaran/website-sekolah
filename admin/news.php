<?php

declare(strict_types=1);

define('ADMIN_CONTEXT', true);

require_once __DIR__ . '/../includes/auth.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        flash('error', 'Token keamanan tidak valid.');
    } elseif (!can('delete')) {
        flash('error', 'Anda tidak memiliki izin untuk menghapus berita.');
    } else {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $pdo->prepare('SELECT thumbnail, title FROM news WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $item = $stmt->fetch();

        if ($item) {
            if (!empty($item['thumbnail'])) {
                delete_file(UPLOAD_BASE . '/news/' . $item['thumbnail']);
            }
            $delete = $pdo->prepare('DELETE FROM news WHERE id = :id');
            $delete->execute(['id' => $id]);
            log_activity('Hapus berita', 'Berita dihapus: ' . ($item['title'] ?? 'ID ' . $id), 'news:' . $id);
            flash('success', 'Berita berhasil dihapus.');
        }
    }
    redirect('news.php');
}

$statement = $pdo->query('SELECT * FROM news ORDER BY published_at DESC');
$newsItems = $statement->fetchAll();
$pageTitle = 'Berita';

require_once __DIR__ . '/includes/header.php';
?>
<section class="panel">
    <div style="display:flex; justify-content:space-between; gap:16px; flex-wrap:wrap; align-items:center;">
        <div>
            <h2>Kelola Berita</h2>
            <p class="footer-note">Tambahkan, edit, dan hapus berita sekolah.</p>
        </div>
        <a class="btn-primary" href="news-form.php">+ Tambah Berita</a>
    </div>
</section>

<section class="panel table-wrapper">
    <table>
        <thead>
            <tr>
                <th>Judul</th>
                <th>Kategori</th>
                <th>Tanggal</th>
                <th>Status</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($newsItems)): ?>
                <tr>
                    <td colspan="5">Belum ada berita.</td>
                </tr>
            <?php endif; ?>
            <?php foreach ($newsItems as $news): ?>
                <tr>
                    <td><?= htmlspecialchars($news['title']) ?></td>
                    <td><?= htmlspecialchars($news['category']) ?></td>
                    <td><?= htmlspecialchars($news['published_at']) ?></td>
                    <td><?= $news['is_active'] ? 'Aktif' : 'Nonaktif' ?></td>
                    <td>
                        <a class="btn-tertiary" href="news-form.php?id=<?= $news['id'] ?>">Edit</a>
                        <?php if (can('delete')): ?>
                            <form method="post" style="display:inline-block; margin:0;" onsubmit="return confirm('Hapus berita ini?');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= $news['id'] ?>">
                                <button type="submit" class="btn-secondary">Hapus</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</section>

<?php require_once __DIR__ . '/includes/footer.php';
