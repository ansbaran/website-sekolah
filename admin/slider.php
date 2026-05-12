<?php

declare(strict_types=1);

define('ADMIN_CONTEXT', true);

require_once __DIR__ . '/../includes/auth.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        flash('error', 'Token keamanan tidak valid.');
    } elseif (!can('delete')) {
        flash('error', 'Anda tidak memiliki izin untuk menghapus slide.');
    } else {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $pdo->prepare('SELECT background, title FROM slider WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $item = $stmt->fetch();
        if ($item) {
            delete_file(UPLOAD_BASE . '/slider/' . $item['background']);
            $delete = $pdo->prepare('DELETE FROM slider WHERE id = :id');
            $delete->execute(['id' => $id]);
            log_activity('Hapus slider', 'Slider dihapus: ' . ($item['title'] ?? $item['background']), 'slider:' . $id);
            flash('success', 'Slide berhasil dihapus.');
        }
    }
    redirect('slider.php');
}

$statement = $pdo->query('SELECT * FROM slider ORDER BY created_at DESC');
$sliderItems = $statement->fetchAll();
$pageTitle = 'Slider Hero';
require_once __DIR__ . '/includes/header.php';
?>
<section class="panel">
    <div style="display:flex; justify-content:space-between; gap:16px; flex-wrap:wrap; align-items:center;">
        <div>
            <h2>Kelola Slider Hero</h2>
            <p class="footer-note">Atur slide utama, judul, subtitle, dan status aktif.</p>
        </div>
        <a class="btn-primary" href="slider-form.php">+ Tambah Slide</a>
    </div>
</section>

<section class="panel table-wrapper">
    <table>
        <thead>
            <tr>
                <th>Preview</th>
                <th>Judul</th>
                <th>Status</th>
                <th>Tanggal</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($sliderItems)): ?>
                <tr>
                    <td colspan="5">Belum ada slide hero.</td>
                </tr>
            <?php endif; ?>
            <?php foreach ($sliderItems as $item): ?>
                <tr>
                    <td><img src="<?= build_upload_url('slider', $item['background']) ?>" style="width: 120px; height: 80px; object-fit: cover; border-radius: 12px;"></td>
                    <td><?= htmlspecialchars($item['title']) ?></td>
                    <td><?= $item['is_active'] ? 'Aktif' : 'Nonaktif' ?></td>
                    <td><?= htmlspecialchars($item['created_at']) ?></td>
                    <td>
                        <a class="btn-tertiary" href="slider-form.php?id=<?= $item['id'] ?>">Edit</a>
                        <?php if (can('delete')): ?>
                            <form method="post" style="display:inline-block; margin:0;" onsubmit="return confirm('Hapus slide ini?');">
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
