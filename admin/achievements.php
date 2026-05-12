<?php

declare(strict_types=1);

define('ADMIN_CONTEXT', true);

require_once __DIR__ . '/../includes/auth.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        flash('error', 'Token keamanan tidak valid.');
    } elseif (!can('delete')) {
        flash('error', 'Anda tidak memiliki izin untuk menghapus prestasi.');
    } else {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $pdo->prepare('SELECT image, title FROM achievements WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $achievement = $stmt->fetch();
        if ($achievement) {
            delete_file(UPLOAD_BASE . '/achievements/' . $achievement['image']);
            $delete = $pdo->prepare('DELETE FROM achievements WHERE id = :id');
            $delete->execute(['id' => $id]);
            log_activity('Hapus prestasi', 'Prestasi dihapus: ' . ($achievement['title'] ?? $achievement['image']), 'achievements:' . $id);
            flash('success', 'Prestasi berhasil dihapus.');
        }
    }
    redirect('achievements.php');
}

$statement = $pdo->query('SELECT * FROM achievements ORDER BY created_at DESC');
$achievementsList = $statement->fetchAll();
$pageTitle = 'Prestasi';
require_once __DIR__ . '/includes/header.php';
?>
<section class="panel">
    <div style="display:flex; justify-content:space-between; gap:16px; flex-wrap:wrap; align-items:center;">
        <div>
            <h2>Kelola Prestasi</h2>
            <p class="footer-note">Tambahkan capaian siswa dan lomba secara rapi.</p>
        </div>
        <a class="btn-primary" href="achievement-form.php">+ Tambah Prestasi</a>
    </div>
</section>

<section class="panel table-wrapper">
    <table>
        <thead>
            <tr>
                <th>Preview</th>
                <th>Nama Prestasi</th>
                <th>Tingkat</th>
                <th>Tanggal</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($achievementsList)): ?>
                <tr>
                    <td colspan="5">Belum ada data prestasi.</td>
                </tr>
            <?php endif; ?>
            <?php foreach ($achievementsList as $item): ?>
                <tr>
                    <td><img src="<?= build_upload_url('achievements', $item['image']) ?>" style="width: 120px; height: 80px; object-fit: cover; border-radius: 12px;"></td>
                    <td><?= htmlspecialchars($item['title']) ?></td>
                    <td><?= htmlspecialchars($item['level']) ?></td>
                    <td><?= htmlspecialchars($item['created_at']) ?></td>
                    <td>
                        <a class="btn-tertiary" href="achievement-form.php?id=<?= $item['id'] ?>">Edit</a>
                        <?php if (can('delete')): ?>
                            <form method="post" style="display:inline-block; margin:0;" onsubmit="return confirm('Hapus prestasi ini?');">
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
