<?php

declare(strict_types=1);

define('ADMIN_CONTEXT', true);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_login();

if (!can('backup') && !can('maintenance')) {
    if (!headers_sent()) {
        http_response_code(403);
    }
    flash('error', 'Anda tidak memiliki izin untuk mengakses fitur tersebut.');
    redirect('dashboard.php');
}

$canBackup = can('backup');
$canMaintenance = can('maintenance');

$backupFiles = get_backup_files();
$pageTitle = 'Sistem';
$ppdbSettings = get_ppdb_settings();
$publicStatsSettings = get_public_stats_settings();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        flash('error', 'Token keamanan tidak valid.');
        redirect('system.php');
    }

    if ($_POST['action'] === 'update_ppdb') {
        if (!$canMaintenance) {
            flash('error', 'Anda tidak memiliki izin maintenance sistem.');
            redirect('system.php');
        }

        $status = normalize_ppdb_status((string)($_POST['ppdb_status'] ?? 'open'));
        $year = (int)($_POST['ppdb_year'] ?? date('Y'));
        $startDate = normalize_ppdb_date((string)($_POST['ppdb_start_date'] ?? ''), '');
        $endDate = normalize_ppdb_date((string)($_POST['ppdb_end_date'] ?? ''), '');
        $whatsappNumber = normalize_whatsapp_number((string)($_POST['ppdb_whatsapp_number'] ?? ''));
        $whatsappMessage = trim(strip_tags((string)($_POST['ppdb_whatsapp_message'] ?? '')));
        $ppdbDescription = normalize_public_text($_POST['ppdb_description'] ?? '', '', 700);

        if ($year < 2024 || $year > 2100) {
            flash('error', 'Tahun PPDB tidak valid.');
            redirect('system.php');
        }

        if ($startDate === '' || $endDate === '') {
            flash('error', 'Tanggal mulai dan akhir PPDB wajib valid.');
            redirect('system.php');
        }

        if (strtotime($endDate) < strtotime($startDate)) {
            flash('error', 'Tanggal akhir PPDB tidak boleh lebih awal dari tanggal mulai.');
            redirect('system.php');
        }

        if ($whatsappNumber === false || $whatsappNumber === null) {
            flash('error', 'Nomor WhatsApp PPDB tidak valid.');
            redirect('system.php');
        }

        if ($whatsappMessage === '') {
            flash('error', 'Pesan WhatsApp PPDB wajib diisi.');
            redirect('system.php');
        }

        try {
            $pdo->beginTransaction();
            set_setting('ppdb_status', $status);
            set_setting('ppdb_year', (string)$year);
            set_setting('ppdb_start_date', $startDate);
            set_setting('ppdb_end_date', $endDate);
            set_setting('ppdb_whatsapp_number', $whatsappNumber);
            set_setting('ppdb_whatsapp_message', substr($whatsappMessage, 0, 500));
            set_setting('ppdb_description', $ppdbDescription);
            log_activity('Update PPDB', 'Pengaturan PPDB publik diperbarui', 'ppdb_settings');
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo instanceof PDO && $pdo->inTransaction()) {
                $pdo->rollBack();
            }

            log_exception($exception);
            flash('error', 'Pengaturan PPDB gagal disimpan. Silakan coba lagi.');
            redirect('system.php');
        }

        flash('success', 'Pengaturan PPDB berhasil disimpan.');
        redirect('system.php');
    }


    if ($_POST['action'] === 'update_public_stats') {
        if (!$canMaintenance) {
            flash('error', 'Anda tidak memiliki izin maintenance sistem.');
            redirect('system.php');
        }

        $counts = [
            'stat_students_count' => (int)($_POST['stat_students_count'] ?? 0),
            'stat_achievements_year_count' => (int)($_POST['stat_achievements_year_count'] ?? 0),
            'stat_educators_count' => (int)($_POST['stat_educators_count'] ?? 0),
            'stat_featured_programs_count' => (int)($_POST['stat_featured_programs_count'] ?? 0),
            'stat_teachers_count' => (int)($_POST['stat_teachers_count'] ?? 0),
            'stat_staff_count' => (int)($_POST['stat_staff_count'] ?? 0),
        ];

        foreach ($counts as $value) {
            if ($value < 0 || $value > 99999) {
                flash('error', 'Nilai statistik harus berada di antara 0 sampai 99999.');
                redirect('system.php');
            }
        }

        $accreditationLabel = normalize_public_stat_label($_POST['stat_accreditation_label'] ?? '', 'A', 16);
        $professionalLabel = normalize_public_stat_label($_POST['stat_professional_label'] ?? '', 'A+', 16);

        try {
            $pdo->beginTransaction();
            foreach ($counts as $name => $value) {
                set_setting($name, (string)$value);
            }
            set_setting('stat_accreditation_label', $accreditationLabel);
            set_setting('stat_professional_label', $professionalLabel);
            log_activity('Update statistik publik', 'Statistik siswa, prestasi, dan tenaga pendidik diperbarui', 'public_stats');
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo instanceof PDO && $pdo->inTransaction()) {
                $pdo->rollBack();
            }

            log_exception($exception);
            flash('error', 'Statistik publik gagal disimpan. Silakan coba lagi.');
            redirect('system.php');
        }

        flash('success', 'Statistik publik berhasil disimpan.');
        redirect('system.php');
    }
    if ($_POST['action'] === 'backup_db') {
        if (!$canBackup) {
            flash('error', 'Anda tidak memiliki izin backup sistem.');
            redirect('system.php');
        }

        $sql = export_database_sql();
        if ($sql === null) {
            flash('error', 'Gagal membuat file backup database.');
            redirect('system.php');
        }

        $fileName = 'db-backup-' . date('Ymd-His') . '.sql';
        $sqlPath = write_backup_file($fileName, $sql);
        if ($sqlPath === null) {
            flash('error', 'Gagal menyimpan backup database.');
            redirect('system.php');
        }

        $zipName = 'db-backup-' . date('Ymd-His') . '.zip';
        $zipPath = BACKUP_DIR . '/' . $zipName;
        create_zip_archive($zipPath, [$sqlPath]);
        log_activity('Backup database', 'Ekspor database berhasil', $zipName);
        flash('success', 'Backup database dibuat dan siap untuk diunduh.');
        redirect('system.php');
    }

    if ($_POST['action'] === 'backup_uploads') {
        if (!$canBackup) {
            flash('error', 'Anda tidak memiliki izin backup sistem.');
            redirect('system.php');
        }

        $uploadFiles = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(UPLOAD_BASE, RecursiveDirectoryIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $uploadFiles[] = $file->getPathname();
            }
        }

        $zipName = 'uploads-backup-' . date('Ymd-His') . '.zip';
        $zipPath = BACKUP_DIR . '/' . $zipName;
        if (create_zip_archive($zipPath, $uploadFiles) === null) {
            flash('error', 'Gagal membuat backup uploads.');
            redirect('system.php');
        }

        log_activity('Backup uploads', 'Backup folder uploads berhasil', $zipName);
        flash('success', 'Backup uploads berhasil dibuat.');
        redirect('system.php');
    }

    if ($_POST['action'] === 'toggle_maintenance') {
        if (!$canMaintenance) {
            flash('error', 'Anda tidak memiliki izin maintenance sistem.');
            redirect('system.php');
        }

        $enabled = isset($_POST['maintenance']) && $_POST['maintenance'] === '1';
        if ($enabled) {
            file_put_contents(MAINTENANCE_TOGGLE_FILE, 'maintenance');
            log_activity('Maintenance mode', 'Maintenance mode diaktifkan');
            flash('success', 'Maintenance mode diaktifkan.');
        } else {
            if (file_exists(MAINTENANCE_TOGGLE_FILE)) {
                unlink(MAINTENANCE_TOGGLE_FILE);
            }
            log_activity('Maintenance mode', 'Maintenance mode dinonaktifkan');
            flash('success', 'Maintenance mode dinonaktifkan.');
        }
        redirect('system.php');
    }
}

$maintenanceEnabled = is_maintenance_mode();
require_once __DIR__ . '/includes/header.php';
?>
<?php if ($canMaintenance): ?>
<section class="panel">
    <div class="panel-header">
        <div>
            <h2>Pengaturan Sistem</h2>
            <p class="footer-note">Ekspor database, backup uploads, dan toggle mode maintenance tanpa mengubah website publik.</p>
        </div>
    </div>
</section>

<section class="panel">
    <div class="form-grid">
        <div>
            <h3>Pengaturan PPDB Publik</h3>
            <p class="footer-note">Mengatur status, periode countdown, dan CTA WhatsApp pada homepage.</p>
        </div>
        <form method="post" class="form-grid full-span">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="update_ppdb">
            <div>
                <label for="ppdb_status">Status PPDB</label>
                <select id="ppdb_status" name="ppdb_status" required>
                    <option value="open" <?= $ppdbSettings['status'] === 'open' ? 'selected' : '' ?>>Dibuka</option>
                    <option value="upcoming" <?= $ppdbSettings['status'] === 'upcoming' ? 'selected' : '' ?>>Segera Dibuka</option>
                    <option value="closed" <?= $ppdbSettings['status'] === 'closed' ? 'selected' : '' ?>>Ditutup</option>
                </select>
            </div>
            <div>
                <label for="ppdb_year">Tahun PPDB</label>
                <input type="number" id="ppdb_year" name="ppdb_year" value="<?= escape($ppdbSettings['year']) ?>" min="2024" max="2100" required>
            </div>
            <div>
                <label for="ppdb_start_date">Tanggal Mulai</label>
                <input type="date" id="ppdb_start_date" name="ppdb_start_date" value="<?= escape($ppdbSettings['start_date']) ?>" required>
            </div>
            <div>
                <label for="ppdb_end_date">Tanggal Akhir</label>
                <input type="date" id="ppdb_end_date" name="ppdb_end_date" value="<?= escape($ppdbSettings['end_date']) ?>" required>
            </div>
            <div>
                <label for="ppdb_whatsapp_number">Nomor WhatsApp</label>
                <input type="text" id="ppdb_whatsapp_number" name="ppdb_whatsapp_number" value="<?= escape($ppdbSettings['whatsapp_number']) ?>" maxlength="32" required>
            </div>
            <div class="full-span">
                <label for="ppdb_whatsapp_message">Pesan WhatsApp Otomatis</label>
                <textarea id="ppdb_whatsapp_message" name="ppdb_whatsapp_message" rows="3" maxlength="500" required><?= escape($ppdbSettings['whatsapp_message']) ?></textarea>
            </div>            <div class="full-span">
                <label for="ppdb_description">Teks Keterangan PPDB</label>
                <textarea id="ppdb_description" name="ppdb_description" rows="3" maxlength="700" placeholder="Contoh: Gelombang pertama dibuka untuk calon peserta didik baru. Tim sekolah siap membantu konsultasi dan pendaftaran."><?= escape((string)($ppdbSettings['description'] ?? '')) ?></textarea>
                <small class="footer-note">Kosongkan jika ingin website otomatis membuat teks dari tanggal mulai dan akhir.</small>
            </div>
            <div class="form-actions full-span">
                <button type="submit" class="btn-primary">Simpan Pengaturan PPDB</button>
            </div>
        </form>
    </div>
</section>

<section class="panel">
    <div class="form-grid">
        <div>
            <h3>Statistik Publik</h3>
            <p class="footer-note">Mengatur angka dinamis yang tampil pada hero, profil sekolah, dan halaman Guru & Staff.</p>
        </div>
        <form method="post" class="form-grid full-span">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="update_public_stats">
            <div>
                <label for="stat_students_count">Jumlah Siswa Aktif</label>
                <input type="number" id="stat_students_count" name="stat_students_count" value="<?= (int)$publicStatsSettings['students_count'] ?>" min="0" max="99999" required>
            </div>
            <div>
                <label for="stat_achievements_year_count">Prestasi Tahun Ini</label>
                <input type="number" id="stat_achievements_year_count" name="stat_achievements_year_count" value="<?= (int)$publicStatsSettings['achievements_year_count'] ?>" min="0" max="99999" required>
            </div>
            <div>
                <label for="stat_educators_count">Tenaga Pendidik</label>
                <input type="number" id="stat_educators_count" name="stat_educators_count" value="<?= (int)$publicStatsSettings['educators_count'] ?>" min="0" max="99999" required>
            </div>
            <div>
                <label for="stat_featured_programs_count">Program Unggulan</label>
                <input type="number" id="stat_featured_programs_count" name="stat_featured_programs_count" value="<?= (int)$publicStatsSettings['featured_programs_count'] ?>" min="0" max="99999" required>
            </div>
            <div>
                <label for="stat_teachers_count">Jumlah Guru</label>
                <input type="number" id="stat_teachers_count" name="stat_teachers_count" value="<?= (int)$publicStatsSettings['teachers_count'] ?>" min="0" max="99999" required>
            </div>
            <div>
                <label for="stat_staff_count">Jumlah Staff</label>
                <input type="number" id="stat_staff_count" name="stat_staff_count" value="<?= (int)$publicStatsSettings['staff_count'] ?>" min="0" max="99999" required>
            </div>
            <div>
                <label for="stat_accreditation_label">Nilai Akreditasi</label>
                <input type="text" id="stat_accreditation_label" name="stat_accreditation_label" value="<?= escape((string)$publicStatsSettings['accreditation_label']) ?>" maxlength="16" required>
            </div>
            <div>
                <label for="stat_professional_label">Label Profesional</label>
                <input type="text" id="stat_professional_label" name="stat_professional_label" value="<?= escape((string)$publicStatsSettings['professional_label']) ?>" maxlength="16" required>
            </div>
            <div class="form-actions full-span">
                <button type="submit" class="btn-primary">Simpan Statistik Publik</button>
            </div>
        </form>
    </div>
</section>
<?php endif; ?>

<?php if ($canBackup): ?>
<section class="panel">
    <div class="form-grid">
        <div>
            <h3>Backup Database</h3>
            <p class="footer-note">Simpan snapshot database dalam file ZIP yang aman.</p>
        </div>
        <form method="post" class="form-actions">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="backup_db">
            <button type="submit" class="btn-primary">Buat Backup Database</button>
        </form>
    </div>
</section>

<section class="panel">
    <div class="form-grid">
        <div>
            <h3>Backup Uploads</h3>
            <p class="footer-note">Cadangkan seluruh folder uploads sebagai ZIP.</p>
        </div>
        <form method="post" class="form-actions">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="backup_uploads">
            <button type="submit" class="btn-primary">Buat Backup Uploads</button>
        </form>
    </div>
</section>
<?php endif; ?>

<?php if ($canMaintenance): ?>
<section class="panel">
    <div class="form-grid">
        <div>
            <h3>Mode Maintenance</h3>
            <p class="footer-note">Aktifkan untuk menampilkan halaman maintenance. Admin tetap dapat login.</p>
        </div>
        <form method="post" class="form-actions">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="toggle_maintenance">
            <label class="field-inline">
                <input type="checkbox" name="maintenance" value="1" <?= $maintenanceEnabled ? 'checked' : '' ?>> Aktifkan maintenance mode
            </label>
            <button type="submit" class="btn-secondary">Simpan Pengaturan</button>
        </form>
    </div>
</section>
<?php endif; ?>

<?php if ($canBackup): ?>
<section class="panel table-wrapper">
    <h3>Backup Tersedia</h3>
    <table>
        <thead>
            <tr>
                <th>Nama File</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($backupFiles)): ?>
                <tr>
                    <td colspan="2">Belum ada backup tersedia.</td>
                </tr>
            <?php endif; ?>
            <?php foreach ($backupFiles as $fileName): ?>
                <tr>
                    <td><?= escape($fileName) ?></td>
                    <td><a class="btn-tertiary" href="<?= escape('download-backup.php?file=' . rawurlencode($fileName)) ?>">Unduh</a></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</section>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php';
