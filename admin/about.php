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

    $uploaded = upload_image($_FILES[$fieldName], 'misc', $error);
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
        $team = [];
        for ($i = 1; $i <= 6; $i++) {
            $uploadError = null;
            $currentImage = (string)($_POST['leader_image_current'][$i] ?? '');
            $image = about_upload_optional('leader_image_' . $i, $currentImage, $uploadError);
            if ($uploadError !== null) {
                flash('error', 'Foto tim kepemimpinan slot ' . $i . ': ' . $uploadError);
                redirect('about.php');
            }

            $name = normalize_public_text($_POST['leader_name'][$i] ?? '', '', 120);
            $role = normalize_public_text($_POST['leader_role'][$i] ?? '', '', 140);
            $description = normalize_public_text($_POST['leader_description'][$i] ?? '', '', 500);
            $email = normalize_public_text($_POST['leader_email'][$i] ?? '', '', 160);
            $phone = normalize_public_text($_POST['leader_phone'][$i] ?? '', '', 60);
            $active = isset($_POST['leader_active'][$i]) && $_POST['leader_active'][$i] === '1';

            if ($name === '' && $role === '' && $description === '' && $image === '') {
                continue;
            }

            $team[] = [
                'active' => $active,
                'name' => $name,
                'role' => $role,
                'description' => $description,
                'email' => $email,
                'phone' => $phone,
                'image' => normalize_public_image_value($image, 'assets/img/logo.png'),
            ];
        }

        set_setting('about_leadership_team', json_encode($team, JSON_UNESCAPED_UNICODE));
        log_activity('Update tentang sekolah', 'Tim kepemimpinan sekolah diperbarui', 'about_leadership');
        flash('success', 'Tim kepemimpinan berhasil disimpan.');
        redirect('about.php');
    }
}

$profileSettings = get_school_profile_settings();
$principal = $profileSettings['principal'];
$missionText = implode("\n", $profileSettings['missions']);
$leaderSlots = $profileSettings['leadership_team'];
while (count($leaderSlots) < 6) {
    $leaderSlots[] = ['active' => false, 'name' => '', 'role' => '', 'description' => '', 'email' => '', 'phone' => '', 'image' => ''];
}
$leaderSlots = array_slice($leaderSlots, 0, 6);

require_once __DIR__ . '/includes/header.php';
?>
<section class="panel">
    <div class="panel-header">
        <div>
            <h2>Tentang Sekolah</h2>
            <p class="footer-note">Atur konten dinamis yang tampil di halaman Tentang tanpa mengubah kode website.</p>
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
                <small class="footer-note">Kosongkan jika tidak mengganti foto.</small>
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

<section class="panel">
    <div class="panel-header">
        <div>
            <h3>Tim Kepemimpinan</h3>
            <p class="footer-note">Aktifkan slot yang ingin ditampilkan. Slot kosong tidak akan muncul di website.</p>
        </div>
    </div>
    <form method="post" enctype="multipart/form-data" class="form-card">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="update_leadership">
        <input type="hidden" name="MAX_FILE_SIZE" value="<?= MAX_IMAGE_SIZE ?>">
        <div class="admin-leader-slots">
            <?php foreach ($leaderSlots as $index => $leader): $slot = $index + 1; ?>
                <article class="admin-leader-slot">
                    <div class="admin-leader-slot__top">
                        <strong>Slot <?= $slot ?></strong>
                        <label class="field-inline">
                            <input type="checkbox" name="leader_active[<?= $slot ?>]" value="1" <?= !empty($leader['active']) ? 'checked' : '' ?>> Tampilkan
                        </label>
                    </div>
                    <input type="hidden" name="leader_image_current[<?= $slot ?>]" value="<?= escape($leader['image']) ?>">
                    <div class="form-grid">
                        <div>
                            <label for="leader_name_<?= $slot ?>">Nama</label>
                            <input type="text" id="leader_name_<?= $slot ?>" name="leader_name[<?= $slot ?>]" value="<?= escape($leader['name']) ?>" maxlength="120">
                        </div>
                        <div>
                            <label for="leader_role_<?= $slot ?>">Jabatan</label>
                            <input type="text" id="leader_role_<?= $slot ?>" name="leader_role[<?= $slot ?>]" value="<?= escape($leader['role']) ?>" maxlength="140">
                        </div>
                        <div>
                            <label for="leader_email_<?= $slot ?>">Email</label>
                            <input type="email" id="leader_email_<?= $slot ?>" name="leader_email[<?= $slot ?>]" value="<?= escape($leader['email']) ?>" maxlength="160">
                        </div>
                        <div>
                            <label for="leader_phone_<?= $slot ?>">Telepon</label>
                            <input type="text" id="leader_phone_<?= $slot ?>" name="leader_phone[<?= $slot ?>]" value="<?= escape($leader['phone']) ?>" maxlength="60">
                        </div>
                        <div>
                            <label for="leader_image_<?= $slot ?>">Foto</label>
                            <input type="file" id="leader_image_<?= $slot ?>" name="leader_image_<?= $slot ?>" accept="image/*">
                            <small class="footer-note">Kosongkan jika tidak mengganti foto.</small>
                        </div>
                        <div class="admin-about-preview">
                            <img src="<?= escape(about_image_preview($leader['image'])) ?>" alt="Preview <?= escape($leader['name'] ?: 'slot ' . $slot) ?>" loading="lazy">
                            <span>Foto saat ini</span>
                        </div>
                        <div class="full-span">
                            <label for="leader_description_<?= $slot ?>">Deskripsi Peran</label>
                            <textarea id="leader_description_<?= $slot ?>" name="leader_description[<?= $slot ?>]" rows="3" maxlength="500"><?= escape($leader['description']) ?></textarea>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn-primary">Simpan Tim Kepemimpinan</button>
        </div>
    </form>
</section>

<?php require_once __DIR__ . '/includes/footer.php';