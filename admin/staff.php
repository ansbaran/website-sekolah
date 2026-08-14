<?php

declare(strict_types=1);

define('ADMIN_CONTEXT', true);

require_once __DIR__ . '/../includes/auth.php';
require_login();
require_permission('publish');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        flash('error', 'Token keamanan tidak valid.');
    } elseif (!can('delete')) {
        flash('error', 'Anda tidak memiliki izin untuk menghapus data staff.');
    } else {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $pdo->prepare('SELECT nama, foto FROM staff WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $item = $stmt->fetch();
        if ($item) {
            if (!empty($item['foto'])) {
                delete_file(UPLOAD_BASE . '/staff/' . $item['foto']);
            }
            $delete = $pdo->prepare('DELETE FROM staff WHERE id = :id');
            $delete->execute(['id' => $id]);
            log_activity('Hapus staff', 'Data staff dihapus: ' . $item['nama'], 'staff:' . $id);
            flash('success', 'Data Guru & Staff berhasil dihapus.');
        }
    }
    redirect('staff.php');
}

$statement = $pdo->query('SELECT * FROM staff ORDER BY urutan ASC, nama ASC');
$staffItems = $statement->fetchAll();
$pageTitle = 'Guru & Staff';
$staffCategoryOptions = [
    'pimpinan' => 'Tim Kepemimpinan',
    'guru' => 'Guru Pengajar',
    'staf' => 'Staff dan Karyawan',
];
$normalizeStaffCategory = static function ($value) use ($staffCategoryOptions): string {
    $value = (string)$value;
    return array_key_exists($value, $staffCategoryOptions) ? $value : 'guru';
};

$staffAdminIcon = static function (string $name): string {
    $icons = [
        'plus' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>',
        'edit' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 20h4l10.5-10.5a2.1 2.1 0 0 0-3-3L5 17v3Z"/><path d="m14 8 2 2"/></svg>',
        'trash' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16"/><path d="M10 11v6M14 11v6"/><path d="M6 7l1 13h10l1-13"/><path d="M9 7V4h6v3"/></svg>',
    ];

    return $icons[$name] ?? $icons['plus'];
};

require_once __DIR__ . '/includes/header.php';
?>
<section class="panel">
    <div class="panel-header">
        <div>
            <h2>Guru & Staff</h2>
            <p class="footer-note">Kelola tenaga pendidik dan kependidikan yang tampil di website publik.</p>
        </div>
        <a class="btn-primary" href="staff-form.php"><span class="admin-button-icon" aria-hidden="true"><?= $staffAdminIcon('plus') ?></span>Tambah Data</a>
    </div>
</section>

<section class="panel">
    <div class="admin-card-grid">
        <?php if (empty($staffItems)): ?>
            <div class="empty-state">Belum ada data Guru & Staff.</div>
        <?php endif; ?>
        <?php foreach ($staffItems as $item): ?>
            <?php $category = $normalizeStaffCategory($item['kategori'] ?? 'guru'); ?>
            <article class="admin-staff-card">
                <img src="<?= escape($item['foto'] ? build_upload_url('staff', $item['foto']) : '../assets/img/logo.png') ?>" alt="<?= escape(html_entity_decode((string)$item['nama'], ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?>" loading="lazy">
                <div>
                    <span class="status-pill <?= (int)$item['aktif'] === 1 ? 'status-pill--active' : 'status-pill--muted' ?>">
                        <?= (int)$item['aktif'] === 1 ? 'Aktif' : 'Nonaktif' ?>
                    </span>
                    <span class="status-pill status-pill--muted">
                        <?= escape($staffCategoryOptions[$category]) ?>
                    </span>
                    <h3><?= escape(html_entity_decode((string)$item['nama'], ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?></h3>
                    <p><?= escape(html_entity_decode((string)$item['jabatan'], ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?></p>
                    <small>Urutan: <?= (int)$item['urutan'] ?></small>
                    <div class="form-actions">
                        <a class="btn-tertiary" href="staff-form.php?id=<?= (int)$item['id'] ?>"><span class="admin-button-icon" aria-hidden="true"><?= $staffAdminIcon('edit') ?></span>Edit</a>
                        <?php if (can('delete')): ?>
                            <form method="post" class="inline-form">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
                                <button type="submit" class="btn-danger" data-confirm="Hapus data staff ini?"><span class="admin-button-icon" aria-hidden="true"><?= $staffAdminIcon('trash') ?></span>Hapus</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php';
