<?php

declare(strict_types=1);

define('ADMIN_CONTEXT', true);

require_once __DIR__ . '/../includes/auth.php';
require_login();
require_permission('publish');

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$editing = $id > 0;
$error = '';
$item = [
    'parent_name' => '',
    'parent_email' => '',
    'relation_label' => 'Orang tua siswa',
    'student_label' => '',
    'message' => '',
    'rating' => '5.0',
    'display_order' => 0,
    'is_approved' => 0,
    'is_active' => 1,
    'consent_given' => 0,
];

if ($editing) {
    $statement = $pdo->prepare('SELECT * FROM parent_feedback WHERE id = :id LIMIT 1');
    $statement->execute(['id' => $id]);
    $existing = $statement->fetch();
    if (!$existing) {
        flash('error', 'Data feedback tidak ditemukan.');
        redirect('feedback.php');
    }
    $item = array_merge($item, $existing);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $error = 'Token keamanan tidak valid. Silakan muat ulang halaman.';
    } else {
        $parentName = clean($_POST['parent_name'] ?? '');
        $parentEmail = strtolower(clean($_POST['parent_email'] ?? ''));
        $relationLabel = clean($_POST['relation_label'] ?? 'Orang tua siswa');
        $studentLabel = clean($_POST['student_label'] ?? '');
        $message = clean($_POST['message'] ?? '');
        $rating = (float)($_POST['rating'] ?? 5.0);
        $displayOrder = (int)($_POST['display_order'] ?? 0);
        $isApproved = isset($_POST['is_approved']) ? 1 : 0;
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        $consentGiven = isset($_POST['consent_given']) ? 1 : 0;

        if ($rating < 1 || $rating > 5) {
            $rating = 5.0;
        }
        $rating = round($rating, 1);

        $item = array_merge($item, [
            'parent_name' => $parentName,
            'parent_email' => $parentEmail,
            'relation_label' => $relationLabel,
            'student_label' => $studentLabel,
            'message' => $message,
            'rating' => number_format($rating, 1, '.', ''),
            'display_order' => $displayOrder,
            'is_approved' => $isApproved,
            'is_active' => $isActive,
            'consent_given' => $consentGiven,
        ]);

        if ($parentName === '' || $relationLabel === '' || $message === '') {
            $error = 'Nama, keterangan orang tua, dan isi feedback wajib diisi.';
        } elseif ($parentEmail !== '' && !filter_var($parentEmail, FILTER_VALIDATE_EMAIL)) {
            $error = 'Format email orang tua tidak valid.';
        } elseif (strlen($message) < 20) {
            $error = 'Isi feedback terlalu pendek. Minimal 20 karakter agar konteksnya jelas.';
        } elseif (strlen($message) > 700) {
            $error = 'Isi feedback maksimal 700 karakter.';
        } elseif ($isApproved && !$consentGiven) {
            $error = 'Feedback hanya boleh disetujui jika orang tua/wali sudah memberi izin publikasi.';
        } else {
            if ($editing) {
                $sql = 'UPDATE parent_feedback
                        SET parent_name = :parent_name,
                            parent_email = :parent_email,
                            relation_label = :relation_label,
                            student_label = :student_label,
                            message = :message,
                            rating = :rating,
                            display_order = :display_order,
                            is_approved = :is_approved,
                            is_active = :is_active,
                            consent_given = :consent_given,
                            approved_at = CASE WHEN :is_approved_case = 1 AND approved_at IS NULL THEN NOW() WHEN :is_approved_case2 = 0 THEN NULL ELSE approved_at END,
                            approved_by = CASE WHEN :is_approved_case3 = 1 AND approved_by IS NULL THEN :approved_by WHEN :is_approved_case4 = 0 THEN NULL ELSE approved_by END
                        WHERE id = :id';
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    'parent_name' => $parentName,
                    'parent_email' => $parentEmail === '' ? null : $parentEmail,
                    'relation_label' => $relationLabel,
                    'student_label' => $studentLabel === '' ? null : $studentLabel,
                    'message' => $message,
                    'rating' => $rating,
                    'display_order' => $displayOrder,
                    'is_approved' => $isApproved,
                    'is_active' => $isActive,
                    'consent_given' => $consentGiven,
                    'is_approved_case' => $isApproved,
                    'is_approved_case2' => $isApproved,
                    'is_approved_case3' => $isApproved,
                    'is_approved_case4' => $isApproved,
                    'approved_by' => current_user()['id'] ?? null,
                    'id' => $id,
                ]);
                log_activity('Edit feedback', 'Feedback orang tua diperbarui: ' . $parentName, 'parent_feedback:' . $id);
                flash('success', 'Feedback berhasil diperbarui.');
            } else {
                $stmt = $pdo->prepare(
                    'INSERT INTO parent_feedback (parent_name, parent_email, relation_label, student_label, message, rating, display_order, is_approved, is_active, consent_given, approved_at, approved_by, created_at)
                     VALUES (:parent_name, :parent_email, :relation_label, :student_label, :message, :rating, :display_order, :is_approved, :is_active, :consent_given, :approved_at, :approved_by, NOW())'
                );
                $stmt->execute([
                    'parent_name' => $parentName,
                    'parent_email' => $parentEmail === '' ? null : $parentEmail,
                    'relation_label' => $relationLabel,
                    'student_label' => $studentLabel === '' ? null : $studentLabel,
                    'message' => $message,
                    'rating' => $rating,
                    'display_order' => $displayOrder,
                    'is_approved' => $isApproved,
                    'is_active' => $isActive,
                    'consent_given' => $consentGiven,
                    'approved_at' => $isApproved ? date('Y-m-d H:i:s') : null,
                    'approved_by' => $isApproved ? (current_user()['id'] ?? null) : null,
                ]);
                log_activity('Tambah feedback', 'Feedback orang tua ditambahkan: ' . $parentName, 'parent_feedback:new');
                flash('success', $isApproved ? 'Feedback ditambahkan dan tampil di website.' : 'Feedback ditambahkan sebagai draft/menunggu persetujuan.');
            }

            redirect('feedback.php');
        }
    }
}

$pageTitle = $editing ? 'Edit Feedback Orang Tua' : 'Tambah Feedback Orang Tua';
require_once __DIR__ . '/includes/header.php';
?>
<section class="panel">
    <div class="panel-header">
        <div>
            <h2><?= escape($pageTitle) ?></h2>
            <p class="footer-note">Gunakan hanya feedback asli yang sudah diizinkan untuk dipublikasikan.</p>
        </div>
        <a class="btn-secondary" href="feedback.php">Kembali ke feedback</a>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= escape($error) ?></div>
    <?php endif; ?>

    <form method="post" class="form-card">
        <?= csrf_field() ?>
        <div class="form-grid">
            <div>
                <label for="parent_name">Nama yang Ditampilkan</label>
                <input type="text" id="parent_name" name="parent_name" value="<?= escape($item['parent_name']) ?>" placeholder="Contoh: Ibu Angela R." required>
                <small class="footer-note">Boleh gunakan nama singkat/inisial jika orang tua meminta privasi.</small>
            </div>
            <div>
                <label for="parent_email">Email Orang Tua</label>
                <input type="email" id="parent_email" name="parent_email" value="<?= escape((string)$item['parent_email']) ?>" placeholder="nama@email.com">
                <small class="footer-note">Dipakai untuk avatar/profil publik. Email tidak ditampilkan sebagai teks di website.</small>
            </div>
            <div>
                <label for="relation_label">Keterangan</label>
                <input type="text" id="relation_label" name="relation_label" value="<?= escape($item['relation_label']) ?>" placeholder="Orang tua siswa" required>
            </div>
            <div>
                <label for="student_label">Kelas/Status Anak</label>
                <input type="text" id="student_label" name="student_label" value="<?= escape((string)$item['student_label']) ?>" placeholder="Contoh: kelas 3 / alumni">
            </div>
            <div>
                <label for="rating">Rating</label>
                <input type="number" id="rating" name="rating" value="<?= escape((string)$item['rating']) ?>" min="1" max="5" step="0.1" required>
            </div>
            <div>
                <label for="display_order">Urutan Tampil</label>
                <input type="number" id="display_order" name="display_order" value="<?= (int)$item['display_order'] ?>">
            </div>
            <div class="full-span">
                <label for="message">Isi Feedback</label>
                <textarea id="message" name="message" rows="6" maxlength="700" required><?= escape($item['message']) ?></textarea>
                <small class="footer-note">Maksimal 700 karakter. Pastikan feedback benar-benar berasal dari orang tua/wali.</small>
            </div>
            <div class="full-span field-inline">
                <label>
                    <input type="checkbox" name="consent_given" <?= (int)$item['consent_given'] === 1 ? 'checked' : '' ?>> Orang tua/wali sudah memberi izin feedback ini ditinjau dan ditampilkan setelah disetujui admin
                </label>
            </div>
            <div class="full-span field-inline">
                <label>
                    <input type="checkbox" name="is_approved" <?= (int)$item['is_approved'] === 1 ? 'checked' : '' ?>> Setujui untuk tampil di website
                </label>
            </div>
            <div class="full-span field-inline">
                <label>
                    <input type="checkbox" name="is_active" <?= (int)$item['is_active'] === 1 ? 'checked' : '' ?>> Aktif
                </label>
            </div>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn-primary"><?= $editing ? 'Simpan Perubahan' : 'Tambah Feedback' ?></button>
            <a class="btn-secondary" href="feedback.php">Batal</a>
        </div>
    </form>
</section>

<?php require_once __DIR__ . '/includes/footer.php';
