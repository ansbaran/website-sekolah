<?php

declare(strict_types=1);

define('ADMIN_CONTEXT', true);

require_once __DIR__ . '/../includes/auth.php';
require_login();
require_permission('publish');
ensure_school_programs_table();

$type = normalize_school_program_type((string)($_GET['type'] ?? $_POST['type'] ?? 'kegiatan'));
$typeLabel = school_program_type_label($type);
$isExtracurricular = $type === 'ekstrakurikuler';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$editing = $id > 0;
$error = '';
$iconOptions = function_exists('school_program_icon_options') ? school_program_icon_options() : ['fa-solid fa-star' => 'Umum'];
$item = [
    'type' => $type,
    'title' => '',
    'category' => '',
    'icon' => 'fa-solid fa-star',
    'description' => '',
    'image' => '',
    'sort_order' => 0,
    'is_active' => 1,
];

if ($editing) {
    $stmt = $pdo->prepare('SELECT * FROM school_programs WHERE id = :id AND type = :type LIMIT 1');
    $stmt->execute(['id' => $id, 'type' => $type]);
    $found = $stmt->fetch();
    if (!$found) {
        flash('error', 'Data tidak ditemukan.');
        redirect('programs.php?type=' . urlencode($type));
    }
    $item = array_merge($item, $found);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $error = 'Token keamanan tidak valid. Silakan muat ulang halaman.';
    } else {
        $category = clean($_POST['category'] ?? '');
        $title = $isExtracurricular ? $category : clean($_POST['title'] ?? '');
        $icon = normalize_school_program_icon((string)($_POST['icon'] ?? 'fa-solid fa-star'));
        $description = clean($_POST['description'] ?? '');
        $sortOrder = max(0, (int)($_POST['sort_order'] ?? 0));
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        $image = (string)($item['image'] ?? '');

        if (!empty($_FILES['image']['name'])) {
            $uploaded = upload_image($_FILES['image'], 'misc', $uploadError, image_upload_policy('program'));
            if ($uploaded === null) {
                $error = $uploadError ?: 'Gagal mengunggah gambar.';
            } else {
                $image = $uploaded;
            }
        }

        $item = array_merge($item, [
            'title' => $title,
            'category' => $category,
            'icon' => $icon,
            'description' => $description,
            'image' => $image,
            'sort_order' => $sortOrder,
            'is_active' => $isActive,
        ]);

        if ($error === '' && ($category === '' || $description === '' || (!$isExtracurricular && $title === ''))) {
            $error = $isExtracurricular ? 'Kategori dan deskripsi wajib diisi.' : 'Judul, kategori, dan deskripsi wajib diisi.';
        }

        if ($error === '') {
            if ($editing) {
                $stmt = $pdo->prepare('UPDATE school_programs SET title = :title, category = :category, icon = :icon, description = :description, image = :image, sort_order = :sort_order, is_active = :is_active, updated_at = NOW() WHERE id = :id AND type = :type');
                $stmt->execute([
                    'title' => $title,
                    'category' => $category,
                    'icon' => $icon,
                    'description' => $description,
                    'image' => $image,
                    'sort_order' => $sortOrder,
                    'is_active' => $isActive,
                    'id' => $id,
                    'type' => $type,
                ]);
                log_activity('Edit ' . strtolower($typeLabel), $typeLabel . ' diperbarui: ' . $category, 'program:' . $type . ':' . $id);
                flash('success', $typeLabel . ' berhasil diperbarui.');
            } else {
                $stmt = $pdo->prepare('INSERT INTO school_programs (type, title, category, icon, description, image, sort_order, is_active, created_at) VALUES (:type, :title, :category, :icon, :description, :image, :sort_order, :is_active, NOW())');
                $stmt->execute([
                    'type' => $type,
                    'title' => $title,
                    'category' => $category,
                    'icon' => $icon,
                    'description' => $description,
                    'image' => $image,
                    'sort_order' => $sortOrder,
                    'is_active' => $isActive,
                ]);
                log_activity('Tambah ' . strtolower($typeLabel), $typeLabel . ' baru ditambahkan: ' . $category, 'program:' . $type . ':new');
                flash('success', $typeLabel . ' baru berhasil ditambahkan.');
            }

            redirect('programs.php?type=' . urlencode($type));
        }
    }
}

$pageTitle = ($editing ? 'Edit ' : 'Tambah ') . $typeLabel;
require_once __DIR__ . '/includes/header.php';
?>
<section class="panel">
    <div class="panel-header">
        <div>
            <h2><?= htmlspecialchars($pageTitle) ?></h2>
            <p class="footer-note"><?= $isExtracurricular ? 'Kelola kategori, ikon, deskripsi, dan gambar ekskul.' : 'Data ini akan tampil di halaman ' . htmlspecialchars(strtolower($typeLabel)) . ' publik.' ?></p>
        </div>
        <a class="btn-secondary" href="programs.php?type=<?= urlencode($type) ?>">Kembali</a>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data" class="form-card">
        <?= csrf_field() ?>
        <input type="hidden" name="type" value="<?= htmlspecialchars($type) ?>">
        <input type="hidden" name="MAX_FILE_SIZE" value="<?= MAX_IMAGE_SIZE ?>">
        <div class="form-grid">
            <?php if (!$isExtracurricular): ?>
                <div>
                    <label for="title">Judul</label>
                    <input type="text" id="title" name="title" value="<?= htmlspecialchars((string)$item['title']) ?>" required>
                </div>
            <?php endif; ?>
            <div>
                <label for="category">Kategori <?= $isExtracurricular ? 'Ekskul' : '' ?></label>
                <input type="text" id="category" name="category" value="<?= htmlspecialchars((string)$item['category']) ?>" placeholder="Contoh: Pramuka, Badminton, Musik" required>
            </div>
                        <div>
                <label for="icon">Ikon Kategori</label>
                <select id="icon" name="icon">
                    <?php foreach ($iconOptions as $iconClass => $iconLabel): ?>
                        <option value="<?= htmlspecialchars($iconClass) ?>" <?= normalize_school_program_icon((string)$item['icon']) === $iconClass ? 'selected' : '' ?>><?= htmlspecialchars($iconLabel) ?></option>
                    <?php endforeach; ?>
                </select>
                <small class="footer-note">Pilih ikon yang paling dekat dengan kategori <?= htmlspecialchars(strtolower($typeLabel)) ?>.</small>
            </div>
            <div>
                <label for="sort_order">Urutan</label>
                <input type="number" id="sort_order" name="sort_order" min="0" value="<?= (int)$item['sort_order'] ?>">
            </div>
            <div>
                <label for="image">Gambar</label>
                <input type="file" id="image" name="image" accept="image/*" data-max-size="4194304">
                <div class="image-upload-guide" role="note">
                    <div class="image-upload-guide__title"><span class="image-upload-guide__icon" aria-hidden="true"><i class="fa-solid fa-shapes"></i></span><span>Rekomendasi</span></div>
                    <p class="image-upload-guide__specs"><span>1260 x 828 px</span><span>Rasio daftar compact</span><span>Minimal 840 x 552 px</span><span>JPG/PNG/WebP</span></p>
                    <p class="image-upload-guide__note">Untuk card umum, 1600 x 1200 px (4:3) juga aman. Target file &lt; 600 KB. Maksimal upload <?= (int)(MAX_IMAGE_SIZE / 1024 / 1024) ?>MB.</p>
                    <p class="image-upload-guide__note">Letakkan objek utama di tengah. Kosongkan jika tidak ingin mengganti.</p>
                </div>
            </div>
            <?php if (!empty($item['image'])): ?>
                <div class="full-span">
                    <label>Gambar Saat Ini</label>
                    <img src="<?= htmlspecialchars(public_content_image_url((string)$item['image'])) ?>" alt="Preview <?= htmlspecialchars($typeLabel) ?>" class="program-form-preview">
                </div>
            <?php endif; ?>
            <div class="full-span">
                <label for="description">Deskripsi</label>
                <textarea id="description" name="description" required><?= htmlspecialchars((string)$item['description']) ?></textarea>
            </div>
            <div class="full-span field-inline">
                <label>
                    <input type="checkbox" name="is_active" <?= $item['is_active'] ? 'checked' : '' ?>> Aktifkan di website
                </label>
            </div>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn-primary"><?= $editing ? 'Simpan Perubahan' : 'Tambah Data' ?></button>
            <a class="btn-secondary" href="programs.php?type=<?= urlencode($type) ?>">Batal</a>
        </div>
    </form>
</section>

<?php require_once __DIR__ . '/includes/footer.php';
