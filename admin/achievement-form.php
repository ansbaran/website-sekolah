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
    'level' => '',
    'description' => '',
    'image' => '',
];

if ($editing) {
    $statement = $pdo->prepare('SELECT * FROM achievements WHERE id = :id LIMIT 1');
    $statement->execute(['id' => $id]);
    $item = $statement->fetch() ?: $item;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $error = 'Token keamanan tidak valid. Silakan muat ulang halaman.';
    } else {
        $title = clean($_POST['title'] ?? '');
        $level = clean($_POST['level'] ?? '');
        $description = clean($_POST['description'] ?? '');

        if ($title === '' || $level === '') {
            $error = 'Nama prestasi dan tingkat wajib diisi.';
        } else {
            $imageName = $item['image'];
            if (!empty($_FILES['image']['name'])) {
                $uploadName = upload_image($_FILES['image'], 'achievements', $uploadError);
                if ($uploadName === null) {
                    $error = $uploadError;
                } else {
                    if (!empty($imageName)) {
                        delete_file(UPLOAD_BASE . '/achievements/' . $imageName);
                    }
                    $imageName = $uploadName;
                }
            }

            if ($error === '') {
                if ($editing) {
                    $stmt = $pdo->prepare('UPDATE achievements SET title = :title, level = :level, description = :description, image = :image WHERE id = :id');
                    $stmt->execute([
                        'title' => $title,
                        'level' => $level,
                        'description' => $description,
                        'image' => $imageName,
                        'id' => $id,
                    ]);
                    log_activity('Edit prestasi', 'Prestasi diubah: ' . $title, 'achievements:' . $id);
                    flash('success', 'Data prestasi berhasil diperbarui.');
                } else {
                    $stmt = $pdo->prepare('INSERT INTO achievements (title, level, description, image, created_at) VALUES (:title, :level, :description, :image, NOW())');
                    $stmt->execute([
                        'title' => $title,
                        'level' => $level,
                        'description' => $description,
                        'image' => $imageName,
                    ]);
                    log_activity('Tambah prestasi', 'Prestasi baru ditambahkan: ' . $title, 'achievements:new');
                    flash('success', 'Prestasi baru berhasil ditambahkan.');
                }
                redirect('achievements.php');
            }
        }
    }
}

$pageTitle = $editing ? 'Edit Prestasi' : 'Tambah Prestasi';
require_once __DIR__ . '/includes/header.php';
?>
<section class="panel">
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:16px;">
        <div>
            <h2><?= htmlspecialchars($pageTitle) ?></h2>
            <p class="footer-note">Simpan informasi prestasi dan foto pendukung.</p>
        </div>
        <a class="btn-secondary" href="achievements.php">Kembali ke prestasi</a>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data" class="form-card">
        <?= csrf_field() ?>
        <div class="form-grid">
            <div>
                <label for="title">Nama Prestasi</label>
                <input type="text" id="title" name="title" value="<?= htmlspecialchars($item['title']) ?>" required>
            </div>
            <div>
                <label for="level">Tingkat Lomba</label>
                <input type="text" id="level" name="level" value="<?= htmlspecialchars($item['level']) ?>" required>
            </div>
            <div style="grid-column:1/-1;">
                <label for="description">Deskripsi</label>
                <textarea id="description" name="description"><?= htmlspecialchars($item['description']) ?></textarea>
            </div>
            <div style="grid-column:1/-1;">
                <label for="image">Foto Prestasi</label>
                <input type="file" id="image" name="image" accept="image/*">
                <?php if (!empty($item['image'])) : ?>
                    <p class="footer-note">Foto saat ini: <?= htmlspecialchars($item['image']) ?></p>
                <?php endif; ?>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn-primary"><?= $editing ? 'Simpan Perubahan' : 'Tambah Prestasi' ?></button>
            <a class="btn-secondary" href="achievements.php">Batal</a>
        </div>
    </form>
</section>

<?php require_once __DIR__ . '/includes/footer.php';
