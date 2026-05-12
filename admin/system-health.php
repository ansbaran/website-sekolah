<?php

declare(strict_types=1);

define('ADMIN_CONTEXT', true);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_login();
require_role('super_admin', 'admin');

function check_database_status(): array
{
    global $pdo;
    try {
        $pdo->query('SELECT 1');
        return ['status' => 'OK', 'message' => 'Koneksi database stabil'];
    } catch (PDOException $exception) {
        return ['status' => 'Gagal', 'message' => 'Koneksi database gagal: ' . $exception->getMessage()];
    }
}

$pageTitle = 'Health Sistem';
$status = [
    'php_version' => PHP_VERSION,
    'upload_max_filesize' => ini_get('upload_max_filesize'),
    'post_max_size' => ini_get('post_max_size'),
    'memory_limit' => ini_get('memory_limit'),
    'maintenance_mode' => is_maintenance_mode() ? 'Aktif' : 'Nonaktif',
    'upload_writable' => is_writable(UPLOAD_BASE) ? 'Ya' : 'Tidak',
    'backup_writable' => is_dir(BACKUP_DIR) ? is_writable(BACKUP_DIR) ? 'Ya' : 'Tidak' : 'Tidak tersedia',
    'cache_writable' => is_dir(CACHE_DIR) ? is_writable(CACHE_DIR) ? 'Ya' : 'Tidak' : 'Tidak tersedia',
    'backup_files' => count(get_backup_files()),
    'cache_files' => count(get_cache_files()),
];

$dbStatus = check_database_status();

require_once __DIR__ . '/includes/header.php';
?>
<section class="panel">
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:16px;">
        <div>
            <h2>System Health</h2>
            <p class="footer-note">Memeriksa status server, database, backup, dan cache untuk deployment produksi.</p>
        </div>
    </div>
</section>

<section class="panel table-wrapper">
    <table>
        <thead>
            <tr>
                <th>Item</th>
                <th>Status</th>
                <th>Detail</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>PHP version</td>
                <td>OK</td>
                <td><?= escape($status['php_version']) ?></td>
            </tr>
            <tr>
                <td>Upload limit</td>
                <td>OK</td>
                <td><?= escape($status['upload_max_filesize']) ?> / <?= escape($status['post_max_size']) ?></td>
            </tr>
            <tr>
                <td>Memory limit</td>
                <td>OK</td>
                <td><?= escape($status['memory_limit']) ?></td>
            </tr>
            <tr>
                <td>Folder uploads writable</td>
                <td><?= escape($status['upload_writable']) ?></td>
                <td>Folder <?= escape(UPLOAD_BASE) ?></td>
            </tr>
            <tr>
                <td>Folder backup writable</td>
                <td><?= escape($status['backup_writable']) ?></td>
                <td>Folder <?= escape(BACKUP_DIR) ?></td>
            </tr>
            <tr>
                <td>Folder cache writable</td>
                <td><?= escape($status['cache_writable']) ?></td>
                <td>Folder <?= escape(CACHE_DIR) ?></td>
            </tr>
            <tr>
                <td>Backup files</td>
                <td>OK</td>
                <td><?= escape((string)$status['backup_files']) ?> file</td>
            </tr>
            <tr>
                <td>Cache files</td>
                <td>OK</td>
                <td><?= escape((string)$status['cache_files']) ?> file</td>
            </tr>
            <tr>
                <td>Maintenance mode</td>
                <td><?= escape($status['maintenance_mode']) ?></td>
                <td>Flag file <?= escape(MAINTENANCE_TOGGLE_FILE) ?></td>
            </tr>
            <tr>
                <td>Database</td>
                <td><?= escape($dbStatus['status']) ?></td>
                <td><?= escape($dbStatus['message']) ?></td>
            </tr>
        </tbody>
    </table>
</section>

<?php require_once __DIR__ . '/includes/footer.php';
