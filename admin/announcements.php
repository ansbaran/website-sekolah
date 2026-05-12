<?php

declare(strict_types=1);

define('ADMIN_CONTEXT', true);

require_once __DIR__ . '/../includes/auth.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        flash('error', 'Token keamanan tidak valid.');
    } elseif (!can('delete')) {
        flash('error', 'Anda tidak memiliki izin untuk menghapus pengumuman.');
    } else {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $pdo->prepare('SELECT title FROM announcements WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $item = $stmt->fetch();
        $delete = $pdo->prepare('DELETE FROM announcements WHERE id = :id');
        $delete->execute(['id' => $id]);
        log_activity('Hapus pengumuman', 'Pengumuman dihapus: ' . ($item['title'] ?? 'ID ' . $id), 'announcement:' . $id);
        flash('success', 'Pengumuman berhasil dihapus.');
    }
    redirect('announcements.php');
}

$statement = $pdo->query('SELECT * FROM announcements ORDER BY published_at DESC');
$announcements = $statement->fetchAll();
$pageTitle = 'Pengumuman';
require_once __DIR__ . '/includes/header.php';
?>
<section class="panel">
    <div style="display:flex; justify-content:space-between; gap:16px; flex-wrap:wrap; align-items:center;">
        <div>
            <h2>Kelola Pengumuman</h2>
            <p class="footer-note">Buat pesan penting untuk ditampilkan di website.</p>
        </div>
        <a class="btn-primary" href="announcement-form.php">+ Tambah Pengumuman</a>
    </div>
</section>

<section class="panel table-wrapper">
    <table>
        <thead>
            <tr>
                <th>Judul</th>
                <th>Tanggal</th>
                <th>Status</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($announcements)): ?>
                <tr>
                    <td colspan="4">Belum ada pengumuman.</td>
                </tr>
            <?php endif; ?>
            <?php foreach ($announcements as $item): ?>
                <tr>
                    <td><?= htmlspecialchars($item['title']) ?></td>
                    <td><?= htmlspecialchars($item['published_at']) ?></td>
                    <td><?= $item['status'] ? 'Aktif' : 'Tertunda' ?></td>
                    <td>
                        <a class="btn-tertiary" href="announcement-form.php?id=<?= $item['id'] ?>">Edit</a>
                        <?php if (can('delete')): ?>
                            <form method="post" style="display:inline-block; margin:0;" onsubmit="return confirm('Hapus pengumuman ini?');">
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

<?php require_once __DIR__ . '/includes/footer.php';
