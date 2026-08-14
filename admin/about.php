<?php

declare(strict_types=1);

define('ADMIN_CONTEXT', true);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_login();
require_permission('publish');

$pageTitle = 'Tentang Sekolah';
$profileSettings = get_school_profile_settings();

function about_image_preview(string $value): string
{
    return public_content_image_url($value, 'assets/img/logo.png');
}

function about_upload_optional(string $fieldName, string $currentImage, ?string &$error): string
{
    if (empty($_FILES[$fieldName]) || ($_FILES[$fieldName]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return $currentImage;
    }

    $uploaded = upload_image($_FILES[$fieldName], 'misc', $error, image_upload_policy('profile_portrait'));
    return $uploaded !== null ? $uploaded : $currentImage;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        flash('error', 'Token keamanan tidak valid.');
        redirect('about.php');
    }

    if ($_POST['action'] === 'update_principal') {
        $uploadError = null;
        $image = about_upload_optional('principal_image', (string)($_POST['principal_image_current'] ?? $profileSettings['principal']['image']), $uploadError);
        if ($uploadError !== null) {
            flash('error', $uploadError);
            redirect('about.php');
        }

        set_setting('about_principal_badge', normalize_public_text($_POST['principal_badge'] ?? '', 'Sambutan Kepala Sekolah', 80));
        set_setting('about_principal_title', normalize_public_text($_POST['principal_title'] ?? '', 'Iman Kuat, Karakter Hebat, Prestasi Bermartabat', 180));
        set_setting('about_principal_message', normalize_public_text($_POST['principal_message'] ?? '', '', 5000));
        set_setting('about_principal_name', normalize_public_text($_POST['principal_name'] ?? '', 'Nama Kepala Sekolah', 120));
        set_setting('about_principal_role', normalize_public_text($_POST['principal_role'] ?? '', 'Kepala Sekolah', 120));
        set_setting('about_principal_image', $image);
        log_activity('Update tentang sekolah', 'Sambutan kepala sekolah diperbarui', 'about_principal');
        flash('success', 'Sambutan kepala sekolah berhasil disimpan.');
        redirect('about.php');
    }

    if ($_POST['action'] === 'update_vision_mission') {
        $missionLines = preg_split('/\r\n|\r|\n/', (string)($_POST['mission_text'] ?? '')) ?: [];
        $missions = array_values(array_filter(array_map(static fn($line) => normalize_public_text($line, '', 700), $missionLines)));
        if (empty($missions)) {
            flash('error', 'Minimal satu poin misi wajib diisi.');
            redirect('about.php');
        }

        set_setting('about_vision', normalize_public_text($_POST['vision_text'] ?? '', '', 1000));
        set_setting('about_missions', json_encode($missions, JSON_UNESCAPED_UNICODE));
        log_activity('Update tentang sekolah', 'Visi dan misi sekolah diperbarui', 'about_vision_mission');
        flash('success', 'Visi dan misi berhasil disimpan.');
        redirect('about.php');
    }

    if ($_POST['action'] === 'update_leadership') {
        flash('error', 'Tim kepemimpinan sekarang dikelola melalui menu Guru & Staf.');
        redirect('about.php');
    }

    flash('error', 'Aksi tidak valid.');
    redirect('about.php');
}

$profileSettings = get_school_profile_settings();
$principal = $profileSettings['principal'];
$missionText = implode("\n", $profileSettings['missions']);

require_once __DIR__ . '/includes/header.php';
?>
<section class="panel">
    <div class="panel-header">
        <div>
            <h2>Tentang Sekolah</h2>
            <p class="footer-note">Atur konten dinamis yang tampil di halaman Tentang tanpa mengubah kode website.</p>
            <p class="footer-note">Tim kepemimpinan publik sekarang ditampilkan dari data Guru & Staf modern.</p>
        </div>
        <a class="btn-secondary" href="../tentang.html" target="_blank" rel="noopener noreferrer">Lihat Halaman</a>
    </div>
</section>

<section class="panel">
    <div class="panel-header">
        <div>
            <h3>Sambutan Kepala Sekolah</h3>
            <p class="footer-note">Ubah judul, isi sambutan, nama kepala sekolah, dan foto utama.</p>
        </div>
    </div>
    <form method="post" enctype="multipart/form-data" class="form-card">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="update_principal">
        <input type="hidden" name="MAX_FILE_SIZE" value="<?= MAX_IMAGE_SIZE ?>">
        <input type="hidden" name="principal_image_current" value="<?= escape($principal['image']) ?>">
        <div class="form-grid">
            <div>
                <label for="principal_badge">Label Kecil</label>
                <input type="text" id="principal_badge" name="principal_badge" value="<?= escape($principal['badge']) ?>" maxlength="80" required>
            </div>
            <div>
                <label for="principal_name">Nama Kepala Sekolah</label>
                <input type="text" id="principal_name" name="principal_name" value="<?= escape($principal['name']) ?>" maxlength="120" required>
            </div>
            <div class="full-span">
                <label for="principal_title">Judul Sambutan</label>
                <input type="text" id="principal_title" name="principal_title" value="<?= escape($principal['title']) ?>" maxlength="180" required>
            </div>
            <div>
                <label for="principal_role">Jabatan</label>
                <input type="text" id="principal_role" name="principal_role" value="<?= escape($principal['role']) ?>" maxlength="120" required>
            </div>
            <div>
                <label for="principal_image">Foto Kepala Sekolah</label>
                <input type="file" id="principal_image" name="principal_image" accept="image/*">
                <div class="image-upload-guide" role="note">
                    <div class="image-upload-guide__title"><span class="image-upload-guide__icon" aria-hidden="true"><i class="fa-solid fa-user"></i></span><span>Rekomendasi</span></div>
                    <p class="image-upload-guide__specs"><span>1200 x 1500 px</span><span>Rasio 4:5</span><span>Minimal 900 x 1125 px</span><span>JPG/PNG/WebP</span></p>
                    <p class="image-upload-guide__note">Target file &lt; 500 KB. Maksimal upload <?= (int)(MAX_IMAGE_SIZE / 1024 / 1024) ?>MB.</p>
                    <p class="image-upload-guide__note">Posisikan wajah di tengah-atas dengan ruang di sekitar kepala. Kosongkan jika tidak mengganti foto.</p>
                </div>
            </div>
            <div class="full-span admin-about-preview">
                <img src="<?= escape(about_image_preview($principal['image'])) ?>" alt="Preview kepala sekolah" loading="lazy">
                <span>Foto saat ini</span>
            </div>
            <div class="full-span">
                <label for="principal_message">Isi Sambutan</label>
                <textarea id="principal_message" name="principal_message" rows="10" maxlength="5000" required><?= escape($principal['message']) ?></textarea>
                <small class="footer-note">Pisahkan paragraf dengan enter kosong agar tampil rapi di website.</small>
            </div>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn-primary">Simpan Sambutan</button>
        </div>
    </form>
</section>

<section class="panel">
    <div class="panel-header">
        <div>
            <h3>Visi & Misi</h3>
            <p class="footer-note">Isi visi dalam satu paragraf. Tulis setiap poin misi pada baris baru.</p>
        </div>
    </div>
    <form method="post" class="form-card">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="update_vision_mission">
        <div class="form-grid">
            <div class="full-span">
                <label for="vision_text">Visi Sekolah</label>
                <textarea id="vision_text" name="vision_text" rows="4" maxlength="1000" required><?= escape($profileSettings['vision']) ?></textarea>
            </div>
            <div class="full-span">
                <label for="mission_text">Misi Sekolah</label>
                <textarea id="mission_text" name="mission_text" rows="8" maxlength="5000" required><?= escape($missionText) ?></textarea>
                <small class="footer-note">Contoh: satu baris untuk misi nomor 1, baris berikutnya untuk nomor 2, dan seterusnya.</small>
            </div>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn-primary">Simpan Visi & Misi</button>
        </div>
    </form>
</section>

<?php require_once __DIR__ . '/includes/footer.php';
