<?php

declare(strict_types=1);

define('ADMIN_CONTEXT', true);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_login();
require_role('super_admin', 'admin');

$backupFiles = get_backup_files();
$pageTitle = 'Sistem';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        flash('error', 'Token keamanan tidak valid.');
        redirect('system.php');
    }

    if ($_POST['action'] === 'backup_db') {
        $sql = export_database_sql();
        if ($sql === null) {
            flash('error', 'Gagal membuat file backup database.');
            redirect('system.php');
        }

        $fileName = 'db-backup-' . date('Ymd-His') . '.sql';
        $sqlPath = write_backup_file($fileName, $sql);
        if ($sqlPath === null) {
            flash('error', 'Gagal menyimpan backup database.');
            redirect('system.php');
        }

        $zipName = 'db-backup-' . date('Ymd-His') . '.zip';
        $zipPath = BACKUP_DIR . '/' . $zipName;
        create_zip_archive($zipPath, [$sqlPath]);
        log_activity('Backup database', 'Ekspor database berhasil', $zipName);
        flash('success', 'Backup database dibuat dan siap untuk diunduh.');
        redirect('system.php');
    }

    if ($_POST['action'] === 'backup_uploads') {
        $uploadFiles = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(UPLOAD_BASE, RecursiveDirectoryIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $uploadFiles[] = $file->getPathname();
            }
        }

        $zipName = 'uploads-backup-' . date('Ymd-His') . '.zip';
        $zipPath = BACKUP_DIR . '/' . $zipName;
        if (create_zip_archive($zipPath, $uploadFiles) === null) {
            flash('error', 'Gagal membuat backup uploads.');
            redirect('system.php');
        }

        log_activity('Backup uploads', 'Backup folder uploads berhasil', $zipName);
        flash('success', 'Backup uploads berhasil dibuat.');
        redirect('system.php');
    }

    if ($_POST['action'] === 'toggle_maintenance') {
        $enabled = isset($_POST['maintenance']) && $_POST['maintenance'] === '1';
        if ($enabled) {
            file_put_contents(MAINTENANCE_TOGGLE_FILE, 'maintenance');
            log_activity('Maintenance mode', 'Maintenance mode diaktifkan');
            flash('success', 'Maintenance mode diaktifkan.');
        } else {
            if (file_exists(MAINTENANCE_TOGGLE_FILE)) {
                unlink(MAINTENANCE_TOGGLE_FILE);
            }
            log_activity('Maintenance mode', 'Maintenance mode dinonaktifkan');
            flash('success', 'Maintenance mode dinonaktifkan.');
        }
        redirect('system.php');
    }
}

$maintenanceEnabled = is_maintenance_mode();
require_once __DIR__ . '/includes/header.php';
?>
<section class="panel">
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:16px;">
        <div>
            <h2>Pengaturan Sistem</h2>
            <p class="footer-note">Ekspor database, backup uploads, dan toggle mode maintenance tanpa mengubah website publik.</p>
        </div>
    </div>
</section>

<section class="panel">
    <div class="form-grid">
        <div>
            <h3>Backup Database</h3>
            <p class="footer-note">Simpan snapshot database dalam file ZIP yang aman.</p>
        </div>
        <form method="post" class="form-actions">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="backup_db">
            <button type="submit" class="btn-primary">Buat Backup Database</button>
        </form>
    </div>
</section>

<section class="panel">
    <div class="form-grid">
        <div>
            <h3>Backup Uploads</h3>
            <p class="footer-note">Cadangkan seluruh folder uploads sebagai ZIP.</p>
        </div>
        <form method="post" class="form-actions">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="backup_uploads">
            <button type="submit" class="btn-primary">Buat Backup Uploads</button>
        </form>
    </div>
</section>

<section class="panel">
    <div class="form-grid">
        <div>
            <h3>Mode Maintenance</h3>
            <p class="footer-note">Aktifkan untuk menampilkan halaman maintenance. Admin tetap dapat login.</p>
        </div>
        <form method="post" class="form-actions">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="toggle_maintenance">
            <label>
                <input type="checkbox" name="maintenance" value="1" <?= $maintenanceEnabled ? 'checked' : '' ?>> Aktifkan maintenance mode
            </label>
            <button type="submit" class="btn-secondary">Simpan Pengaturan</button>
        </form>
    </div>
</section>

<section class="panel table-wrapper">
    <h3>Backup Tersedia</h3>
    <table>
        <thead>
            <tr>
                <th>Nama File</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($backupFiles)): ?>
                <tr>
                    <td colspan="2">Belum ada backup tersedia.</td>
                </tr>
            <?php endif; ?>
            <?php foreach ($backupFiles as $fileName): ?>
                <tr>
                    <td><?= escape($fileName) ?></td>
                    <td><a class="btn-tertiary" href="<?= escape('download-backup.php?file=' . rawurlencode($fileName)) ?>">Unduh</a></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</section>

<?php require_once __DIR__ . '/includes/footer.php';
