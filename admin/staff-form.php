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
$staffCategoryOptions = [
    'pimpinan' => 'Tim Kepemimpinan',
    'guru' => 'Guru Pengajar',
    'staf' => 'Staff dan Karyawan',
];
$normalizeStaffCategory = static function ($value) use ($staffCategoryOptions): ?string {
    $value = (string)$value;
    return array_key_exists($value, $staffCategoryOptions) ? $value : null;
};
$nama = '';
$kategori = 'guru';
$jabatan = '';
$deskripsi = '';
$urutan = 0;
$aktif = 1;
$foto = '';
$email = '';

if ($isEdit) {
    $statement = $pdo->prepare('SELECT * FROM staff WHERE id = :id LIMIT 1');
    $statement->execute(['id' => $id]);
    $staff = $statement->fetch();

    if (!$staff) {
        flash('error', 'Data staff tidak ditemukan.');
        redirect('staff.php');
    }

    $nama = html_entity_decode((string)$staff['nama'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $kategori = $normalizeStaffCategory($staff['kategori'] ?? 'guru') ?? 'guru';
    $jabatan = html_entity_decode((string)$staff['jabatan'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $deskripsi = html_entity_decode((string)($staff['deskripsi'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $urutan = (int)$staff['urutan'];
    $aktif = (int)$staff['aktif'];
    $foto = (string)($staff['foto'] ?? '');
    $email = (string)($staff['email'] ?? '');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $error = 'Token keamanan tidak valid. Silakan muat ulang halaman.';
    } else {
        $nama = trim(strip_tags((string)($_POST['nama'] ?? '')));
        $kategori = $normalizeStaffCategory($_POST['kategori'] ?? null);
        $jabatan = trim(strip_tags((string)($_POST['jabatan'] ?? '')));
        $deskripsi = trim(strip_tags((string)($_POST['deskripsi'] ?? '')));
        $urutan = max(0, (int)($_POST['urutan'] ?? 0));
        $aktif = normalize_staff_status($_POST['aktif'] ?? null);
        $email = trim((string)($_POST['email'] ?? ''));

        $normalizedEmail = normalize_optional_email($email);

        if ($nama === '' || $jabatan === '') {
            $error = 'Nama dan jabatan wajib diisi.';
        } elseif ($kategori === null) {
            $error = 'Kategori wajib dipilih.';
        } elseif ($normalizedEmail === false) {
            $error = 'Format email tidak valid.';
        } else {
            $email = $normalizedEmail ?? '';

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
                    $stmt = $pdo->prepare('UPDATE staff SET nama = :nama, kategori = :kategori, jabatan = :jabatan, foto = :foto, deskripsi = :deskripsi, email = :email, urutan = :urutan, aktif = :aktif WHERE id = :id');
                    $stmt->execute([
                        'nama' => $nama,
                        'kategori' => $kategori,
                        'jabatan' => $jabatan,
                        'foto' => $fileName,
                        'deskripsi' => $deskripsi,
                        'email' => $email,
                        'urutan' => $urutan,
                        'aktif' => $aktif,
                        'id' => $id,
                    ]);
                    log_activity('Edit staff', 'Data staff diperbarui: ' . $nama, 'staff:' . $id);
                    flash('success', 'Data Guru & Staff berhasil diperbarui.');
                } else {
                    $stmt = $pdo->prepare('INSERT INTO staff (nama, kategori, jabatan, foto, deskripsi, email, urutan, aktif, created_at) VALUES (:nama, :kategori, :jabatan, :foto, :deskripsi, :email, :urutan, :aktif, NOW())');
                    $stmt->execute([
                        'nama' => $nama,
                        'kategori' => $kategori,
                        'jabatan' => $jabatan,
                        'foto' => $fileName,
                        'deskripsi' => $deskripsi,
                        'email' => $email,
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
                <label for="kategori">Kategori</label>
                <select id="kategori" name="kategori" required>
                    <?php foreach ($staffCategoryOptions as $categoryValue => $categoryLabel): ?>
                        <option value="<?= escape($categoryValue) ?>" <?= $kategori === $categoryValue ? 'selected' : '' ?>>
                            <?= escape($categoryLabel) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
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
        </div>
        <div class="form-actions">
            <button type="submit" class="btn-primary">Simpan</button>
            <a class="btn-secondary" href="staff.php">Batal</a>
        </div>
    </form>
</section>

<?php require_once __DIR__ . '/includes/footer.php';
