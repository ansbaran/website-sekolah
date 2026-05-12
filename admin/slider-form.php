<?php

declare(strict_types=1);

define('ADMIN_CONTEXT', true);

require_once __DIR__ . '/../includes/auth.php';
require_login();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$editing = $id > 0;
$error = '';
$slide = [
    'title' => '',
    'subtitle' => '',
    'background' => '',
    'is_active' => 1,
];

if ($editing) {
    $statement = $pdo->prepare('SELECT * FROM slider WHERE id = :id LIMIT 1');
    $statement->execute(['id' => $id]);
    $slide = $statement->fetch() ?: $slide;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $error = 'Token keamanan tidak valid. Silakan muat ulang halaman.';
    } else {
        $title = clean($_POST['title'] ?? '');
        $subtitle = clean($_POST['subtitle'] ?? '');
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        if ($title === '' || empty($_FILES['background']['name']) && !$editing) {
            $error = 'Judul dan gambar latar wajib diisi.';
        } else {
            $background = $slide['background'];
            if (!empty($_FILES['background']['name'])) {
                $uploadName = upload_image($_FILES['background'], 'slider', $uploadError);
                if ($uploadName === null) {
                    $error = $uploadError;
                } else {
                    if (!empty($background)) {
                        delete_file(UPLOAD_BASE . '/slider/' . $background);
                    }
                    $background = $uploadName;
                }
            }

            if ($error === '') {
                if ($editing) {
                    $stmt = $pdo->prepare('UPDATE slider SET title = :title, subtitle = :subtitle, background = :background, is_active = :is_active WHERE id = :id');
                    $stmt->execute([
                        'title' => $title,
                        'subtitle' => $subtitle,
                        'background' => $background,
                        'is_active' => $isActive,
                        'id' => $id,
                    ]);
                    log_activity('Edit slider', 'Slider diperbarui: ' . $title, 'slider:' . $id);
                    flash('success', 'Slide berhasil diperbarui.');
                } else {
                    $stmt = $pdo->prepare('INSERT INTO slider (title, subtitle, background, is_active, created_at) VALUES (:title, :subtitle, :background, :is_active, NOW())');
                    $stmt->execute([
                        'title' => $title,
                        'subtitle' => $subtitle,
                        'background' => $background,
                        'is_active' => $isActive,
                    ]);
                    log_activity('Tambah slider', 'Slider baru ditambahkan: ' . $title, 'slider:new');
                    flash('success', 'Slide baru berhasil ditambahkan.');
                }
                redirect('slider.php');
            }
        }
    }
}

$pageTitle = $editing ? 'Edit Slide Hero' : 'Tambah Slide Hero';
require_once __DIR__ . '/includes/header.php';
?>
<section class="panel">
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:16px;">
        <div>
            <h2><?= htmlspecialchars($pageTitle) ?></h2>
            <p class="footer-note">Kelola slide hero untuk halaman utama website.</p>
        </div>
        <a class="btn-secondary" href="slider.php">Kembali ke slider</a>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data" class="form-card">
        <?= csrf_field() ?>
        <div class="form-grid">
            <div>
                <label for="title">Judul Slide</label>
                <input type="text" id="title" name="title" value="<?= htmlspecialchars($slide['title']) ?>" required>
            </div>
            <div>
                <label for="subtitle">Subtitle</label>
                <input type="text" id="subtitle" name="subtitle" value="<?= htmlspecialchars($slide['subtitle']) ?>">
            </div>
            <div>
                <label for="background">Gambar Latar</label>
                <input type="file" id="background" name="background" accept="image/*" <?= $editing ? '' : 'required' ?>>
                <?php if (!empty($slide['background'])) : ?>
                    <p class="footer-note">Gambar saat ini: <?= htmlspecialchars($slide['background']) ?></p>
                <?php endif; ?>
            </div>
            <div style="grid-column:1/-1; display:flex; align-items:center; gap:12px;">
                <label>
                    <input type="checkbox" name="is_active" <?= $slide['is_active'] ? 'checked' : '' ?>> Aktifkan slide
                </label>
            </div>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn-primary"><?= $editing ? 'Simpan Perubahan' : 'Tambah Slide' ?></button>
            <a class="btn-secondary" href="slider.php">Batal</a>
        </div>
    </form>
</section>

<?php require_once __DIR__ . '/includes/footer.php';
