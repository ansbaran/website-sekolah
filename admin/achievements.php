<?php

declare(strict_types=1);

define('ADMIN_CONTEXT', true);

require_once __DIR__ . '/../includes/auth.php';
require_login();
require_permission('publish');

function admin_achievement_excerpt(string $value, int $limit = 120): string
{
    $text = trim(strip_tags($value));
    if ($text === '') {
        return 'Belum ada deskripsi.';
    }

    if (mb_strlen($text) <= $limit) {
        return $text;
    }

    return rtrim(mb_substr($text, 0, $limit), " \t\n\r\0\x0B.,") . '...';
}

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
    <div class="panel-header">
        <div>
            <h2>Kelola Prestasi</h2>
            <p class="footer-note">Tambahkan capaian siswa yang tampil di halaman Prestasi publik.</p>
        </div>
        <a class="btn-primary" href="achievement-form.php"><span class="admin-button-icon"><i class="fa-solid fa-plus" aria-hidden="true"></i></span> Tambah Prestasi</a>
    </div>
</section>

<section class="panel table-wrapper">
    <table>
        <thead>
            <tr>
                <th>Preview</th>
                <th>Prestasi</th>
                <th>Tingkat</th>
                <th>Deskripsi</th>
                <th>Tanggal</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($achievementsList)): ?>
                <tr>
                    <td colspan="6" class="empty-row">Belum ada data prestasi.</td>
                </tr>
            <?php endif; ?>
            <?php foreach ($achievementsList as $item): ?>
                <tr>
                    <td><img class="achievement-admin-thumb" src="<?= escape(build_upload_url('achievements', $item['image'])) ?>" alt="<?= escape($item['title']) ?>" loading="lazy" data-fallback-src="../assets/img/logo.png"></td>
                    <td><strong><?= htmlspecialchars($item['title']) ?></strong></td>
                    <td><span class="admin-soft-badge"><i class="fa-solid fa-medal" aria-hidden="true"></i><?= htmlspecialchars($item['level']) ?></span></td>
                    <td class="admin-table-summary"><?= htmlspecialchars(admin_achievement_excerpt((string)($item['description'] ?? ''))) ?></td>
                    <td><?= htmlspecialchars(date('d M Y', strtotime((string)$item['created_at']))) ?></td>
                    <td>
                        <a class="btn-tertiary" href="achievement-form.php?id=<?= $item['id'] ?>">Edit</a>
                        <?php if (can('delete')): ?>
                            <form method="post" class="inline-form">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= $item['id'] ?>">
                                <button type="submit" class="btn-danger" data-confirm="Hapus prestasi ini?">Hapus</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</section>

<?php require_once __DIR__ . '/includes/footer.php';
