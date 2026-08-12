<?php

declare(strict_types=1);

define('ADMIN_CONTEXT', true);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/user-management-support.php';
require_login();
require_permission('manage_user');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        flash('error', 'Token keamanan tidak valid.');
        redirect('users.php');
    }

    $action = (string)($_POST['action'] ?? '');
    $userId = (int)($_POST['id'] ?? 0);
    $targetUser = admin_user_fetch($userId);

    if (!$targetUser) {
        flash('error', 'Akun pengguna tidak ditemukan.');
        redirect('users.php');
    }

    if ($action === 'toggle_status') {
        $nextStatus = (int)($targetUser['is_active'] ?? 0) === 1 ? 0 : 1;
        $grants = admin_user_fetch_grants($userId);

        if (!admin_user_preserves_manage_user_access($userId, (string)$targetUser['role'], $nextStatus, $grants)) {
            flash('error', 'Tidak dapat menonaktifkan akun terakhir yang memiliki akses kelola akun.');
            redirect('users.php');
        }

        $stmt = $pdo->prepare('UPDATE users SET is_active = :is_active WHERE id = :id');
        $stmt->execute(['is_active' => $nextStatus, 'id' => $userId]);

        log_activity(
            $nextStatus === 1 ? 'Aktifkan akun' : 'Nonaktifkan akun',
            'Status akun diperbarui: ' . $targetUser['email'],
            'users:' . $userId
        );

        if ($nextStatus !== 1) {
            admin_user_sync_session_if_current($userId, (string)$targetUser['name'], (string)$targetUser['email'], (string)$targetUser['role'], $nextStatus);
        }

        flash('success', 'Status akun berhasil diperbarui.');
        redirect('users.php');
    }

    flash('error', 'Aksi akun tidak dikenali.');
    redirect('users.php');
}

$stmt = $pdo->query(
    "SELECT u.id, u.name, u.email, u.role, u.profile_photo, u.is_active, u.last_login_at, u.created_at,
            COUNT(up.id) AS permission_count
     FROM users u
     LEFT JOIN user_permissions up ON up.user_id = u.id
     GROUP BY u.id, u.name, u.email, u.role, u.profile_photo, u.is_active, u.last_login_at, u.created_at
     ORDER BY u.created_at DESC, u.id DESC"
);
$users = $stmt->fetchAll();
$pageTitle = 'Kelola Akun';

require_once __DIR__ . '/includes/header.php';
?>
<section class="panel">
    <div class="panel-header">
        <div>
            <h2>Kelola Akun Pengguna</h2>
            <p class="footer-note">Fondasi multi-user CMS untuk Admin Sekolah, Kepala Sekolah, serta Guru / Staff.</p>
        </div>
        <a class="btn-primary" href="user-form.php">+ Tambah Akun</a>
    </div>
</section>

<section class="panel table-wrapper">
    <table>
        <thead>
            <tr>
                <th>Pengguna</th>
                <th>Email</th>
                <th>Role</th>
                <th>Status</th>
                <th>Last Login</th>
                <th>Permission</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($users)): ?>
                <tr>
                    <td colspan="7" class="empty-row">Belum ada akun pengguna.</td>
                </tr>
            <?php endif; ?>
            <?php foreach ($users as $item): ?>
                <tr>
                    <td>
                        <div class="user-cell">
                            <?= user_avatar_html($item, 'small') ?>
                            <div>
                                <strong><?= escape((string)$item['name']) ?></strong>
                                <small><?= escape(admin_role_label((string)$item['role'])) ?></small>
                            </div>
                        </div>
                    </td>
                    <td><?= escape((string)$item['email']) ?></td>
                    <td>
                        <span class="role-badge"><?= escape(admin_role_label((string)$item['role'])) ?></span>
                    </td>
                    <td>
                        <span class="status-pill <?= (int)$item['is_active'] === 1 ? 'status-pill--active' : 'status-pill--muted' ?>">
                            <?= (int)$item['is_active'] === 1 ? 'Aktif' : 'Nonaktif' ?>
                        </span>
                    </td>
                    <td><?= $item['last_login_at'] ? escape((string)$item['last_login_at']) : '<span class="status-pill status-pill--muted">Belum login</span>' ?></td>
                    <td>
                        <span class="admin-soft-badge"><?= escape(admin_permission_summary_label((int)$item['permission_count'])) ?></span>
                        <small class="permission-summary-note"><?= count(admin_user_effective_permissions($item)) ?> akses efektif</small>
                    </td>
                    <td>
                        <a class="btn-tertiary" href="user-form.php?id=<?= (int)$item['id'] ?>">Edit</a>
                        <form method="post" class="inline-form">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="toggle_status">
                            <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
                            <button type="submit" class="btn-secondary" data-confirm="<?= (int)$item['is_active'] === 1 ? 'Nonaktifkan akun ini?' : 'Aktifkan akun ini?' ?>">
                                <?= (int)$item['is_active'] === 1 ? 'Nonaktifkan' : 'Aktifkan' ?>
                            </button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</section>

<?php require_once __DIR__ . '/includes/footer.php';
