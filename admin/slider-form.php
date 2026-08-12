<?php

declare(strict_types=1);

define('ADMIN_CONTEXT', true);

require_once __DIR__ . '/../includes/auth.php';
require_login();
require_permission('publish');

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

        $slide = array_merge($slide, [
            'title' => $title,
            'subtitle' => $subtitle,
            'is_active' => $isActive,
        ]);

        if ($title === '' || empty($_FILES['background']['name']) && !$editing) {
            $error = 'Judul dan gambar latar wajib diisi.';
        } else {
            $fileToDeleteAfterSave = null;
            $background = $slide['background'];
            if (!empty($_FILES['background']['name'])) {
                $uploadName = upload_image($_FILES['background'], 'slider', $uploadError, image_upload_policy('slider'));
                if ($uploadName === null) {
                    $error = $uploadError;
                } else {
                    if (!empty($background)) {
                        $fileToDeleteAfterSave = UPLOAD_BASE . '/slider/' . $background;
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
                if ($fileToDeleteAfterSave !== null) {
                    delete_file($fileToDeleteAfterSave);
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
    <div class="panel-header">
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
        <input type="hidden" name="MAX_FILE_SIZE" value="<?= MAX_IMAGE_SIZE ?>">
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
                <input type="file" id="background" name="background" accept="image/*" data-max-size="4194304" <?= $editing ? '' : 'required' ?>>
                <div class="image-upload-guide" role="note">
                    <div class="image-upload-guide__title"><span class="image-upload-guide__icon" aria-hidden="true"><i class="fa-solid fa-image"></i></span><span>Sangat Disarankan</span></div>
                    <p class="image-upload-guide__specs"><span>1920 x 1080 px</span><span>Rasio 16:9</span><span>Minimal 1600 x 900 px</span><span>JPG/PNG/WebP</span></p>
                    <p class="image-upload-guide__note">Target file &lt; 700 KB. Maksimal upload <?= (int)(MAX_IMAGE_SIZE / 1024 / 1024) ?>MB.</p>
                    <p class="image-upload-guide__note">Area aman: simpan teks, wajah, atau logo penting di 70% bagian tengah karena hero dapat terpotong.</p>
                </div>
                <?php if (!empty($slide['background'])) : ?>
                    <p class="footer-note">Gambar saat ini: <?= htmlspecialchars($slide['background']) ?></p>
                <?php endif; ?>
            </div>
            <div class="full-span field-inline">
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
