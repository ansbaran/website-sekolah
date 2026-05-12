<?php

declare(strict_types=1);

define('ADMIN_CONTEXT', true);

require_once __DIR__ . '/../includes/auth.php';
require_login();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$editing = $id > 0;
$error = '';
$item = [
    'title' => '',
    'content' => '',
    'published_at' => date('Y-m-d'),
    'status' => 1,
];

if ($editing) {
    $statement = $pdo->prepare('SELECT * FROM announcements WHERE id = :id LIMIT 1');
    $statement->execute(['id' => $id]);
    $item = $statement->fetch() ?: $item;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $error = 'Token keamanan tidak valid. Silakan muat ulang halaman.';
    } else {
        $title = clean($_POST['title'] ?? '');
        $content = clean($_POST['content'] ?? '');
        $publishedAt = $_POST['published_at'] ?? date('Y-m-d');
        $status = isset($_POST['status']) ? 1 : 0;

        if ($title === '' || $content === '') {
            $error = 'Judul dan isi pengumuman wajib diisi.';
        } else {
            if ($editing) {
                $stmt = $pdo->prepare('UPDATE announcements SET title = :title, content = :content, published_at = :published_at, status = :status WHERE id = :id');
                $stmt->execute([
                    'title' => $title,
                    'content' => $content,
                    'published_at' => $publishedAt,
                    'status' => $status,
                    'id' => $id,
                ]);
                log_activity('Edit pengumuman', 'Pengumuman diperbarui: ' . $title, 'announcement:' . $id);
                flash('success', 'Pengumuman berhasil diperbarui.');
            } else {
                $stmt = $pdo->prepare('INSERT INTO announcements (title, content, published_at, status, created_at) VALUES (:title, :content, :published_at, :status, NOW())');
                $stmt->execute([
                    'title' => $title,
                    'content' => $content,
                    'published_at' => $publishedAt,
                    'status' => $status,
                ]);
                log_activity('Tambah pengumuman', 'Pengumuman baru ditambahkan: ' . $title, 'announcement:new');
                flash('success', 'Pengumuman baru berhasil ditambahkan.');
            }
            redirect('announcements.php');
        }
    }
}

$pageTitle = $editing ? 'Edit Pengumuman' : 'Tambah Pengumuman';
require_once __DIR__ . '/includes/header.php';
?>
<section class="panel">
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:16px;">
        <div>
            <h2><?= htmlspecialchars($pageTitle) ?></h2>
            <p class="footer-note">Kelola pengumuman untuk tampilkan informasi penting ke publik.</p>
        </div>
        <a class="btn-secondary" href="announcements.php">Kembali ke pengumuman</a>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="post" class="form-card">
        <?= csrf_field() ?>
        <div class="form-grid">
            <div>
                <label for="title">Judul Pengumuman</label>
                <input type="text" id="title" name="title" value="<?= htmlspecialchars($item['title']) ?>" required>
            </div>
            <div>
                <label for="published_at">Tanggal</label>
                <input type="date" id="published_at" name="published_at" value="<?= htmlspecialchars($item['published_at']) ?>" required>
            </div>
            <div style="grid-column:1/-1;">
                <label for="content">Isi Pengumuman</label>
                <textarea id="content" name="content" required><?= htmlspecialchars($item['content']) ?></textarea>
            </div>
            <div style="grid-column:1/-1; display:flex; align-items:center; gap:12px;">
                <label>
                    <input type="checkbox" name="status" <?= $item['status'] ? 'checked' : '' ?>> Aktifkan pengumuman
                </label>
            </div>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn-primary"><?= $editing ? 'Simpan Perubahan' : 'Tambah Pengumuman' ?></button>
            <a class="btn-secondary" href="announcements.php">Batal</a>
        </div>
    </form>
</section>

<?php require_once __DIR__ . '/includes/footer.php';
