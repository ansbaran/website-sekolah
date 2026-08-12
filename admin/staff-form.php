<?php

declare(strict_types=1);

define('ADMIN_CONTEXT', true);

require_once __DIR__ . '/../includes/auth.php';
require_login();
require_permission('publish');

$id = (int)($_GET['id'] ?? 0);
$isEdit = $id > 0;
$error = '';
$staff = null;
$nama = '';
$jabatan = '';
$deskripsi = '';
$urutan = 0;
$aktif = 1;
$foto = '';
$email = '';
$whatsapp = '';
$instagram = '';
$facebook = '';
$tiktok = '';
$youtube = '';
$website = '';

if ($isEdit) {
    $statement = $pdo->prepare('SELECT * FROM staff WHERE id = :id LIMIT 1');
    $statement->execute(['id' => $id]);
    $staff = $statement->fetch();

    if (!$staff) {
        flash('error', 'Data staff tidak ditemukan.');
        redirect('staff.php');
    }

    $nama = html_entity_decode((string)$staff['nama'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $jabatan = html_entity_decode((string)$staff['jabatan'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $deskripsi = html_entity_decode((string)($staff['deskripsi'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $urutan = (int)$staff['urutan'];
    $aktif = (int)$staff['aktif'];
    $foto = (string)($staff['foto'] ?? '');
    $email = (string)($staff['email'] ?? '');
    $whatsapp = (string)($staff['whatsapp'] ?? '');
    $instagram = (string)($staff['instagram'] ?? '');
    $facebook = (string)($staff['facebook'] ?? '');
    $tiktok = (string)($staff['tiktok'] ?? '');
    $youtube = (string)($staff['youtube'] ?? '');
    $website = (string)($staff['website'] ?? '');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $error = 'Token keamanan tidak valid. Silakan muat ulang halaman.';
    } else {
        $nama = trim(strip_tags((string)($_POST['nama'] ?? '')));
        $jabatan = trim(strip_tags((string)($_POST['jabatan'] ?? '')));
        $deskripsi = trim(strip_tags((string)($_POST['deskripsi'] ?? '')));
        $urutan = max(0, (int)($_POST['urutan'] ?? 0));
        $aktif = normalize_staff_status($_POST['aktif'] ?? null);
        $email = trim((string)($_POST['email'] ?? ''));
        $whatsapp = trim((string)($_POST['whatsapp'] ?? ''));
        $instagram = trim((string)($_POST['instagram'] ?? ''));
        $facebook = trim((string)($_POST['facebook'] ?? ''));
        $tiktok = trim((string)($_POST['tiktok'] ?? ''));
        $youtube = trim((string)($_POST['youtube'] ?? ''));
        $website = trim((string)($_POST['website'] ?? ''));

        $normalizedEmail = normalize_optional_email($email);
        $normalizedWhatsapp = normalize_whatsapp_number($whatsapp);
        $normalizedInstagram = normalize_social_link($instagram, 'instagram');
        $normalizedFacebook = normalize_social_link($facebook, 'facebook');
        $normalizedTiktok = normalize_social_link($tiktok, 'tiktok');
        $normalizedYoutube = normalize_social_link($youtube, 'youtube');
        $normalizedWebsite = normalize_public_url($website);

        if ($nama === '' || $jabatan === '') {
            $error = 'Nama dan jabatan wajib diisi.';
        } elseif ($normalizedEmail === false) {
            $error = 'Format email tidak valid.';
        } elseif ($normalizedWhatsapp === false) {
            $error = 'Nomor WhatsApp hanya boleh berisi angka, spasi, tanda hubung, atau awalan + internasional.';
        } elseif (
            $normalizedInstagram === false ||
            $normalizedFacebook === false ||
            $normalizedTiktok === false ||
            $normalizedYoutube === false ||
            $normalizedWebsite === false
        ) {
            $error = 'Link sosial media harus berupa URL valid atau username yang aman.';
        } else {
            $email = $normalizedEmail ?? '';
            $whatsapp = $normalizedWhatsapp ?? '';
            $instagram = $normalizedInstagram ?? '';
            $facebook = $normalizedFacebook ?? '';
            $tiktok = $normalizedTiktok ?? '';
            $youtube = $normalizedYoutube ?? '';
            $website = $normalizedWebsite ?? '';

            $fileToDeleteAfterSave = null;
            $fileName = $foto;
            if (!empty($_FILES['foto']['name'])) {
                $fileName = upload_image($_FILES['foto'], 'staff', $uploadError, image_upload_policy('staff_portrait'));
                if ($fileName === null) {
                    $error = $uploadError;
                } elseif ($isEdit && $foto !== '') {
                    $fileToDeleteAfterSave = UPLOAD_BASE . '/staff/' . $foto;
                }
            }

            if ($error === '') {
                if ($isEdit) {
                    $stmt = $pdo->prepare('UPDATE staff SET nama = :nama, jabatan = :jabatan, foto = :foto, deskripsi = :deskripsi, email = :email, whatsapp = :whatsapp, instagram = :instagram, facebook = :facebook, tiktok = :tiktok, youtube = :youtube, website = :website, urutan = :urutan, aktif = :aktif WHERE id = :id');
                    $stmt->execute([
                        'nama' => $nama,
                        'jabatan' => $jabatan,
                        'foto' => $fileName,
                        'deskripsi' => $deskripsi,
                        'email' => $email,
                        'whatsapp' => $whatsapp,
                        'instagram' => $instagram,
                        'facebook' => $facebook,
                        'tiktok' => $tiktok,
                        'youtube' => $youtube,
                        'website' => $website,
                        'urutan' => $urutan,
                        'aktif' => $aktif,
                        'id' => $id,
                    ]);
                    log_activity('Edit staff', 'Data staff diperbarui: ' . $nama, 'staff:' . $id);
                    flash('success', 'Data Guru & Staff berhasil diperbarui.');
                } else {
                    $stmt = $pdo->prepare('INSERT INTO staff (nama, jabatan, foto, deskripsi, email, whatsapp, instagram, facebook, tiktok, youtube, website, urutan, aktif, created_at) VALUES (:nama, :jabatan, :foto, :deskripsi, :email, :whatsapp, :instagram, :facebook, :tiktok, :youtube, :website, :urutan, :aktif, NOW())');
                    $stmt->execute([
                        'nama' => $nama,
                        'jabatan' => $jabatan,
                        'foto' => $fileName,
                        'deskripsi' => $deskripsi,
                        'email' => $email,
                        'whatsapp' => $whatsapp,
                        'instagram' => $instagram,
                        'facebook' => $facebook,
                        'tiktok' => $tiktok,
                        'youtube' => $youtube,
                        'website' => $website,
                        'urutan' => $urutan,
                        'aktif' => $aktif,
                    ]);
                    log_activity('Tambah staff', 'Data staff ditambahkan: ' . $nama, 'staff:new');
                    flash('success', 'Data Guru & Staff berhasil ditambahkan.');
                }
                if ($fileToDeleteAfterSave !== null) {
                    delete_file($fileToDeleteAfterSave);
                }
                redirect('staff.php');
            }
        }
    }
}

$pageTitle = $isEdit ? 'Edit Guru & Staff' : 'Tambah Guru & Staff';
require_once __DIR__ . '/includes/header.php';
?>
<section class="panel">
    <div class="panel-header">
        <div>
            <h2><?= escape($pageTitle) ?></h2>
            <p class="footer-note">Lengkapi profil singkat yang akan ditampilkan pada halaman publik.</p>
        </div>
        <a class="btn-secondary" href="staff.php">Kembali</a>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= escape($error) ?></div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data" class="form-card">
        <?= csrf_field() ?>
        <input type="hidden" name="MAX_FILE_SIZE" value="<?= MAX_IMAGE_SIZE ?>">
        <div class="form-grid">
            <div>
                <label for="nama">Nama</label>
                <input type="text" id="nama" name="nama" value="<?= escape($nama) ?>" maxlength="100" required>
            </div>
            <div>
                <label for="jabatan">Jabatan</label>
                <input type="text" id="jabatan" name="jabatan" value="<?= escape($jabatan) ?>" maxlength="100" required>
            </div>
            <div>
                <label for="urutan">Urutan Tampil</label>
                <input type="number" id="urutan" name="urutan" value="<?= (int)$urutan ?>" min="0" step="1">
            </div>
            <div>
                <label for="aktif">Status</label>
                <select id="aktif" name="aktif">
                    <option value="1" <?= $aktif === 1 ? 'selected' : '' ?>>Aktif</option>
                    <option value="0" <?= $aktif === 0 ? 'selected' : '' ?>>Nonaktif</option>
                </select>
            </div>
            <div class="full-span">
                <label for="foto">Foto</label>
                <input type="file" id="foto" name="foto" accept="image/*" data-max-size="4194304">
                <div class="image-upload-guide" role="note">
                    <div class="image-upload-guide__title"><span class="image-upload-guide__icon" aria-hidden="true"><i class="fa-solid fa-user"></i></span><span>Rekomendasi</span></div>
                    <p class="image-upload-guide__specs"><span>1200 x 1500 px</span><span>Rasio 4:5</span><span>Minimal 900 x 1125 px</span><span>JPG/PNG/WebP</span></p>
                    <p class="image-upload-guide__note">Target file &lt; 500 KB. Maksimal upload <?= (int)(MAX_IMAGE_SIZE / 1024 / 1024) ?>MB.</p>
                    <p class="image-upload-guide__note">Gunakan foto portrait setengah badan; wajah di tengah-atas dengan ruang di sekitar kepala. <?= $isEdit ? 'Kosongkan jika tidak mengganti foto.' : '' ?></p>
                </div>
            </div>
            <div class="full-span">
                <label for="deskripsi">Deskripsi</label>
                <textarea id="deskripsi" name="deskripsi" rows="5" placeholder="Profil singkat, bidang yang diampu, atau peran di sekolah."><?= escape($deskripsi) ?></textarea>
            </div>
            <div>
                <label for="email">Email Publik</label>
                <input type="email" id="email" name="email" value="<?= escape($email) ?>" maxlength="160" placeholder="nama@sekolah.sch.id">
            </div>
            <div>
                <label for="whatsapp">WhatsApp Publik</label>
                <input type="text" id="whatsapp" name="whatsapp" value="<?= escape($whatsapp) ?>" maxlength="32" placeholder="6281234567890">
            </div>
            <div>
                <label for="instagram">Instagram</label>
                <input type="text" id="instagram" name="instagram" value="<?= escape($instagram) ?>" maxlength="255" placeholder="@username atau URL">
            </div>
            <div>
                <label for="facebook">Facebook</label>
                <input type="text" id="facebook" name="facebook" value="<?= escape($facebook) ?>" maxlength="255" placeholder="username atau URL">
            </div>
            <div>
                <label for="tiktok">TikTok</label>
                <input type="text" id="tiktok" name="tiktok" value="<?= escape($tiktok) ?>" maxlength="255" placeholder="@username atau URL">
            </div>
            <div>
                <label for="youtube">YouTube</label>
                <input type="text" id="youtube" name="youtube" value="<?= escape($youtube) ?>" maxlength="255" placeholder="@channel atau URL">
            </div>
            <div class="full-span">
                <label for="website">Website</label>
                <input type="text" id="website" name="website" value="<?= escape($website) ?>" maxlength="255" placeholder="https://contoh.sch.id">
                <small class="footer-note">Semua kontak bersifat opsional. Field kosong tidak akan tampil di website publik.</small>
            </div>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn-primary">Simpan</button>
            <a class="btn-secondary" href="staff.php">Batal</a>
        </div>
    </form>
</section>

<?php require_once __DIR__ . '/includes/footer.php';
