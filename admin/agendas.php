<?php

declare(strict_types=1);

define('ADMIN_CONTEXT', true);

require_once __DIR__ . '/../includes/auth.php';
require_login();
require_permission('publish');
ensure_agendas_table();

function admin_agenda_date_parts(string $date): array
{
    $timestamp = strtotime($date);
    if (!$timestamp) {
        return ['day' => '--', 'month' => 'AGD', 'year' => ''];
    }

    $months = [1 => 'JAN', 2 => 'FEB', 3 => 'MAR', 4 => 'APR', 5 => 'MEI', 6 => 'JUN', 7 => 'JUL', 8 => 'AGS', 9 => 'SEP', 10 => 'OKT', 11 => 'NOV', 12 => 'DES'];

    return ['day' => date('d', $timestamp), 'month' => $months[(int) date('n', $timestamp)], 'year' => date('Y', $timestamp)];
}

function admin_agenda_excerpt(string $value, int $limit = 120): string
{
    $text = trim(strip_tags($value));
    if ($text === '') {
        return 'Belum ada ringkasan.';
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
        flash('error', 'Anda tidak memiliki izin untuk menghapus agenda.');
    } else {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $pdo->prepare('SELECT title FROM agendas WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $item = $stmt->fetch();
        $delete = $pdo->prepare('DELETE FROM agendas WHERE id = :id');
        $delete->execute(['id' => $id]);
        log_activity('Hapus agenda', 'Agenda dihapus: ' . ($item['title'] ?? 'ID ' . $id), 'agenda:' . $id);
        flash('success', 'Agenda berhasil dihapus.');
    }
    redirect('agendas.php');
}

$statement = $pdo->query('SELECT * FROM agendas ORDER BY event_date ASC, id DESC');
$agendas = $statement->fetchAll();
$pageTitle = 'Agenda';
require_once __DIR__ . '/includes/header.php';
?>
<section class="panel">
    <div class="panel-header">
        <div>
            <h2>Kelola Agenda</h2>
            <p class="footer-note">Atur jadwal kegiatan yang tampil di website publik dan halaman detail agenda.</p>
        </div>
        <a class="btn-primary" href="agenda-form.php"><span class="admin-button-icon"><i class="fa-solid fa-plus" aria-hidden="true"></i></span> Tambah Agenda</a>
    </div>
</section>

<section class="panel table-wrapper">
    <table>
        <thead>
            <tr>
                <th>Tanggal</th>
                <th>Agenda</th>
                <th>Waktu</th>
                <th>Lokasi</th>
                <th>Status</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($agendas)): ?>
                <tr>
                    <td colspan="6" class="empty-row">Belum ada agenda.</td>
                </tr>
            <?php endif; ?>
            <?php foreach ($agendas as $item): ?>
                <?php $dateParts = admin_agenda_date_parts((string)$item['event_date']); ?>
                <tr>
                    <td>
                        <time class="agenda-admin-date" datetime="<?= htmlspecialchars($item['event_date']) ?>">
                            <span><?= htmlspecialchars($dateParts['month']) ?></span>
                            <strong><?= htmlspecialchars($dateParts['day']) ?></strong>
                            <small><?= htmlspecialchars($dateParts['year']) ?></small>
                        </time>
                    </td>
                    <td>
                        <strong><?= htmlspecialchars($item['title']) ?></strong>
                        <p class="admin-table-summary"><?= htmlspecialchars(admin_agenda_excerpt((string)($item['summary'] ?? $item['description'] ?? ''))) ?></p>
                    </td>
                    <td><span class="admin-soft-badge"><i class="fa-regular fa-clock" aria-hidden="true"></i><?= htmlspecialchars($item['event_time']) ?></span></td>
                    <td><span class="admin-soft-badge"><i class="fa-solid fa-location-dot" aria-hidden="true"></i><?= htmlspecialchars($item['location']) ?></span></td>
                    <td>
                        <span class="status-pill <?= $item['is_active'] ? 'status-pill--active' : 'status-pill--muted' ?>">
                            <?= $item['is_active'] ? 'Aktif' : 'Nonaktif' ?>
                        </span>
                    </td>
                    <td>
                        <a class="btn-tertiary" href="../agenda-detail.php?slug=<?= urlencode($item['slug']) ?>" target="_blank" rel="noopener noreferrer">Lihat</a>
                        <a class="btn-tertiary" href="agenda-form.php?id=<?= $item['id'] ?>">Edit</a>
                        <?php if (can('delete')): ?>
                            <form method="post" class="inline-form">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= $item['id'] ?>">
                                <button type="submit" class="btn-danger" data-confirm="Hapus agenda ini?">Hapus</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</section>

<?php require_once __DIR__ . '/includes/footer.php';
