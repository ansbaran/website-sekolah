<?php

declare(strict_types=1);

define('ADMIN_CONTEXT', true);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_login();
require_role('super_admin', 'admin');

$search = clean($_GET['search'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 20;
$activities = get_activity_log_entries($search, $page, $limit);
$totalEntries = count_activity_entries($search);
$totalPages = max(1, (int)ceil($totalEntries / $limit));
$pageTitle = 'Activity Log';

require_once __DIR__ . '/includes/header.php';
?>
<section class="panel">
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:16px;">
        <div>
            <h2>Log Aktivitas Admin</h2>
            <p class="footer-note">Lihat riwayat login, perubahan konten, upload, dan operasi penting.</p>
        </div>
        <form method="get" class="form-actions" style="margin:0;">
            <label for="search">Cari Aktivitas</label>
            <input type="text" id="search" name="search" value="<?= escape($search) ?>" placeholder="Cari aktivitas, pengguna, atau tipe" autocomplete="off">
            <button type="submit" class="btn-secondary">Cari</button>
        </form>
    </div>
</section>

<section class="panel table-wrapper">
    <table>
        <thead>
            <tr>
                <th>Waktu</th>
                <th>Pengguna</th>
                <th>Role</th>
                <th>Aktivitas</th>
                <th>Detail</th>
                <th>IP</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($activities)): ?>
                <tr>
                    <td colspan="6">Tidak ada aktivitas yang cocok.</td>
                </tr>
            <?php endif; ?>
            <?php foreach ($activities as $activity): ?>
                <tr>
                    <td><?= escape($activity['created_at']) ?></td>
                    <td><?= escape($activity['user_name']) ?></td>
                    <td><?= escape($activity['user_role']) ?></td>
                    <td><?= escape($activity['activity_type']) ?></td>
                    <td><?= escape($activity['description'] ?: $activity['reference'] ?? '') ?></td>
                    <td><?= escape($activity['ip_address']) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</section>

<?php if ($totalPages > 1): ?>
    <section class="panel">
        <div class="form-actions">
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <a class="btn-secondary<?= $i === $page ? ' active' : '' ?>" href="?search=<?= urlencode($search) ?>&page=<?= $i ?>"><?= $i ?></a>
            <?php endfor; ?>
        </div>
    </section>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php';
