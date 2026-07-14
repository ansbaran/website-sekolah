<?php

declare(strict_types=1);

define('ADMIN_CONTEXT', true);

require_once __DIR__ . '/../includes/auth.php';
require_login();
require_permission('publish');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        flash('error', 'Token keamanan tidak valid.');
        redirect('feedback.php');
    }

    $id = (int)($_POST['id'] ?? 0);
    $action = (string)($_POST['action'] ?? '');

    $stmt = $pdo->prepare('SELECT parent_name, consent_given FROM parent_feedback WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $id]);
    $item = $stmt->fetch();

    if (!$item) {
        flash('error', 'Data feedback tidak ditemukan.');
        redirect('feedback.php');
    }

    if ($action === 'approve') {
        if ((int)$item['consent_given'] !== 1) {
            flash('error', 'Feedback belum memiliki izin publikasi dari orang tua/wali. Edit data dan centang izin publikasi terlebih dahulu.');
            redirect('feedback.php');
        }

        $update = $pdo->prepare('UPDATE parent_feedback SET is_approved = 1, is_active = 1, approved_at = NOW(), approved_by = :user_id WHERE id = :id');
        $update->execute([
            'user_id' => current_user()['id'] ?? null,
            'id' => $id,
        ]);
        log_activity('Approve feedback', 'Feedback orang tua disetujui: ' . $item['parent_name'], 'parent_feedback:' . $id);
        flash('success', 'Feedback disetujui dan akan tampil di website.');
    } elseif ($action === 'unapprove') {
        $update = $pdo->prepare('UPDATE parent_feedback SET is_approved = 0, approved_at = NULL, approved_by = NULL WHERE id = :id');
        $update->execute(['id' => $id]);
        log_activity('Unapprove feedback', 'Feedback orang tua disembunyikan: ' . $item['parent_name'], 'parent_feedback:' . $id);
        flash('success', 'Feedback disembunyikan dari website.');
    } elseif ($action === 'toggle-active') {
        $update = $pdo->prepare('UPDATE parent_feedback SET is_active = IF(is_active = 1, 0, 1) WHERE id = :id');
        $update->execute(['id' => $id]);
        log_activity('Ubah status feedback', 'Status aktif feedback diubah: ' . $item['parent_name'], 'parent_feedback:' . $id);
        flash('success', 'Status feedback berhasil diubah.');
    } elseif ($action === 'delete') {
        if (!can('delete')) {
            flash('error', 'Anda tidak memiliki izin untuk menghapus feedback.');
        } else {
            $delete = $pdo->prepare('DELETE FROM parent_feedback WHERE id = :id');
            $delete->execute(['id' => $id]);
            log_activity('Hapus feedback', 'Feedback orang tua dihapus: ' . $item['parent_name'], 'parent_feedback:' . $id);
            flash('success', 'Feedback berhasil dihapus.');
        }
    }

    redirect('feedback.php');
}

$statement = $pdo->query('SELECT * FROM parent_feedback ORDER BY is_approved ASC, display_order ASC, created_at DESC');
$feedbackItems = $statement->fetchAll();
$pageTitle = 'Feedback Orang Tua';
require_once __DIR__ . '/includes/header.php';
?>
<section class="panel">
    <div class="panel-header">
        <div>
            <h2>Kelola Feedback Orang Tua</h2>
            <p class="footer-note">Feedback dari website masuk sebagai pending. Admin menyetujui hanya data asli yang punya izin publikasi.</p>
        </div>
        <a class="btn-primary" href="feedback-form.php">+ Tambah Feedback</a>
    </div>
</section>

<section class="panel table-wrapper">
    <table>
        <thead>
            <tr>
                <th>Nama</th>
                <th>Feedback</th>
                <th>Rating</th>
                <th>Izin</th>
                <th>Status</th>
                <th>Urutan</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($feedbackItems)): ?>
                <tr>
                    <td colspan="7" class="empty-row">Belum ada feedback orang tua.</td>
                </tr>
            <?php endif; ?>
            <?php foreach ($feedbackItems as $item): ?>
                <?php $hasConsent = (int)$item['consent_given'] === 1; ?>
                <tr>
                    <td>
                        <strong><?= escape($item['parent_name']) ?></strong><br>
                        <small><?= escape(trim($item['relation_label'] . ' ' . (string)$item['student_label'])) ?></small><?php if (!empty($item['parent_email'])): ?><br><small><?= escape((string)$item['parent_email']) ?></small><?php endif; ?>
                    </td>
                    <td><?= escape(mb_strimwidth((string)$item['message'], 0, 120, '...')) ?></td>
                    <td><?= number_format((float)$item['rating'], 1) ?></td>
                    <td>
                        <span class="status-pill <?= $hasConsent ? 'status-pill--active' : 'status-pill--muted' ?>">
                            <?= $hasConsent ? 'Ada izin' : 'Belum ada izin' ?>
                        </span>
                    </td>
                    <td>
                        <span class="status-pill <?= ((int)$item['is_approved'] === 1 && (int)$item['is_active'] === 1 && $hasConsent) ? 'status-pill--active' : 'status-pill--muted' ?>">
                            <?php if ((int)$item['is_approved'] === 1 && (int)$item['is_active'] === 1 && $hasConsent): ?>
                                Tampil
                            <?php elseif ((int)$item['is_approved'] === 1): ?>
                                Disetujui nonaktif
                            <?php else: ?>
                                Menunggu review
                            <?php endif; ?>
                        </span>
                    </td>
                    <td><?= (int)$item['display_order'] ?></td>
                    <td>
                        <a class="btn-tertiary" href="feedback-form.php?id=<?= (int)$item['id'] ?>">Edit</a>
                        <form method="post" class="inline-form">
                            <?= csrf_field() ?>
                            <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
                            <input type="hidden" name="action" value="<?= (int)$item['is_approved'] === 1 ? 'unapprove' : 'approve' ?>">
                            <button type="submit" class="btn-secondary" <?= (!$hasConsent && (int)$item['is_approved'] !== 1) ? 'disabled title="Butuh izin publikasi sebelum disetujui"' : '' ?>><?= (int)$item['is_approved'] === 1 ? 'Sembunyikan' : 'Setujui' ?></button>
                        </form>
                        <form method="post" class="inline-form">
                            <?= csrf_field() ?>
                            <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
                            <input type="hidden" name="action" value="toggle-active">
                            <button type="submit" class="btn-secondary"><?= (int)$item['is_active'] === 1 ? 'Nonaktif' : 'Aktifkan' ?></button>
                        </form>
                        <?php if (can('delete')): ?>
                            <form method="post" class="inline-form">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
                                <input type="hidden" name="action" value="delete">
                                <button type="submit" class="btn-danger" data-confirm="Hapus feedback ini?">Hapus</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</section>

<?php require_once __DIR__ . '/includes/footer.php';
