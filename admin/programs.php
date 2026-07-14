<?php

declare(strict_types=1);

define('ADMIN_CONTEXT', true);

require_once __DIR__ . '/../includes/auth.php';
require_login();
require_permission('publish');
ensure_school_programs_table();

$type = normalize_school_program_type((string)($_GET['type'] ?? 'kegiatan'));
$typeLabel = school_program_type_label($type);
$isExtracurricular = $type === 'ekstrakurikuler';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        flash('error', 'Token keamanan tidak valid.');
    } elseif (!can('delete')) {
        flash('error', 'Anda tidak memiliki izin untuk menghapus data ini.');
    } else {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $pdo->prepare('SELECT title, category FROM school_programs WHERE id = :id AND type = :type LIMIT 1');
        $stmt->execute(['id' => $id, 'type' => $type]);
        $item = $stmt->fetch();

        $delete = $pdo->prepare('DELETE FROM school_programs WHERE id = :id AND type = :type');
        $delete->execute(['id' => $id, 'type' => $type]);
        log_activity('Hapus ' . strtolower($typeLabel), $typeLabel . ' dihapus: ' . ($item['category'] ?? $item['title'] ?? 'ID ' . $id), 'program:' . $type . ':' . $id);
        flash('success', $typeLabel . ' berhasil dihapus.');
    }
    redirect('programs.php?type=' . urlencode($type));
}

if ($isExtracurricular) {
    $stmt = $pdo->prepare('SELECT * FROM school_programs WHERE type = :type AND is_active = 1 ORDER BY sort_order ASC, id ASC');
} else {
    $stmt = $pdo->prepare('SELECT * FROM school_programs WHERE type = :type ORDER BY sort_order ASC, id DESC');
}
$stmt->execute(['type' => $type]);
$programs = $stmt->fetchAll();

$pageTitle = $typeLabel;
require_once __DIR__ . '/includes/header.php';
?>
<section class="panel">
    <div class="panel-header">
        <div>
            <h2>Kelola <?= htmlspecialchars($typeLabel) ?></h2>
            <p class="footer-note">Atur data <?= htmlspecialchars(strtolower($typeLabel)) ?> yang tampil di website publik.</p>
        </div>
        <a class="btn-primary" href="program-form.php?type=<?= urlencode($type) ?>">+ Tambah Data</a>
    </div>
</section>

<section class="panel table-wrapper">
    <table>
        <thead>
            <tr>
                                <th>Ikon</th>
                <?php if (!$isExtracurricular): ?>
                    <th>Judul</th>
                <?php endif; ?>
                <th>Kategori</th>
                <th>Deskripsi</th>
                <th>Urutan</th>
                <th>Status</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($programs)): ?>
                <tr>
                    <td colspan="<?= $isExtracurricular ? 6 : 7 ?>" class="empty-row">Belum ada data <?= htmlspecialchars(strtolower($typeLabel)) ?>.</td>
                </tr>
            <?php endif; ?>
            <?php foreach ($programs as $item): ?>
                <tr>
                                        <td><span class="program-table-icon"><i class="<?= htmlspecialchars(normalize_school_program_icon((string)($item['icon'] ?? ''))) ?>" aria-hidden="true"></i></span></td>
                    <?php if (!$isExtracurricular): ?>
                        <td><?= htmlspecialchars($item['title']) ?></td>
                    <?php endif; ?>
                    <td><?= htmlspecialchars($item['category']) ?></td>
                    <td><?= htmlspecialchars(mb_strimwidth(strip_tags((string)$item['description']), 0, 90, '...')) ?></td>
                    <td><?= (int)$item['sort_order'] ?></td>
                    <td>
                        <span class="status-pill <?= $item['is_active'] ? 'status-pill--active' : 'status-pill--muted' ?>">
                            <?= $item['is_active'] ? 'Aktif' : 'Nonaktif' ?>
                        </span>
                    </td>
                    <td>
                        <a class="btn-tertiary" href="../<?= $type === 'ekstrakurikuler' ? 'ekstrakurikuler.php' : 'kegiatan.php' ?>" target="_blank" rel="noopener noreferrer">Lihat</a>
                        <a class="btn-tertiary" href="program-form.php?type=<?= urlencode($type) ?>&id=<?= (int)$item['id'] ?>">Edit</a>
                        <?php if (can('delete')): ?>
                            <form method="post" class="inline-form">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
                                <button type="submit" class="btn-danger" data-confirm="Hapus data ini?">Hapus</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</section>

<?php require_once __DIR__ . '/includes/footer.php';
