<?php

declare(strict_types=1);

define('ADMIN_CONTEXT', true);

require_once __DIR__ . '/../includes/auth.php';
require_login();
require_permission('publish');
ensure_agendas_table();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$editing = $id > 0;
$error = '';
$item = [
    'title' => '',
    'slug' => '',
    'event_date' => date('Y-m-d'),
    'event_time' => '08.00 WIB',
    'location' => '',
    'contact' => 'Panitia / Tata Usaha',
    'summary' => '',
    'description' => '',
    'points' => '',
    'closing' => '',
    'views' => 0,
    'is_active' => 1,
];

if ($editing) {
    $statement = $pdo->prepare('SELECT * FROM agendas WHERE id = :id LIMIT 1');
    $statement->execute(['id' => $id]);
    $item = normalize_agenda_record($statement->fetch() ?: $item);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $error = 'Token keamanan tidak valid. Silakan muat ulang halaman.';
    } else {
        $title = normalize_agenda_input($_POST['title'] ?? '');
        $rawSlug = trim((string)($_POST['slug'] ?? ''));
        $baseSlug = generate_slug($rawSlug !== '' ? $rawSlug : $title);
        $slug = ensure_unique_agenda_slug($baseSlug, $editing ? $id : null);
        $eventDate = $_POST['event_date'] ?? date('Y-m-d');
        $eventTime = normalize_agenda_input($_POST['event_time'] ?? '');
        $location = normalize_agenda_input($_POST['location'] ?? '');
        $contact = normalize_agenda_input($_POST['contact'] ?? '');
        $summary = normalize_agenda_input($_POST['summary'] ?? '');
        $description = normalize_agenda_input($_POST['description'] ?? '');
        $points = normalize_agenda_points((string)($_POST['points'] ?? ''));
        $closing = normalize_agenda_input($_POST['closing'] ?? '');
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        $item = array_merge($item, [
            'title' => $title,
            'slug' => $slug,
            'event_date' => $eventDate,
            'event_time' => $eventTime,
            'location' => $location,
            'contact' => $contact,
            'summary' => $summary,
            'description' => $description,
            'points' => $points,
            'closing' => $closing,
            'is_active' => $isActive,
        ]);

        if ($title === '' || $eventDate === '' || $eventTime === '' || $location === '' || $summary === '') {
            $error = 'Judul, tanggal, waktu, lokasi, dan ringkasan wajib diisi.';
        } else {
            if ($editing) {
                $stmt = $pdo->prepare('UPDATE agendas SET title = :title, slug = :slug, event_date = :event_date, event_time = :event_time, location = :location, contact = :contact, summary = :summary, description = :description, points = :points, closing = :closing, is_active = :is_active, updated_at = NOW() WHERE id = :id');
                $stmt->execute([
                    'title' => $title,
                    'slug' => $slug,
                    'event_date' => $eventDate,
                    'event_time' => $eventTime,
                    'location' => $location,
                    'contact' => $contact,
                    'summary' => $summary,
                    'description' => $description,
                    'points' => $points,
                    'closing' => $closing,
                    'is_active' => $isActive,
                    'id' => $id,
                ]);
                log_activity('Edit agenda', 'Agenda diperbarui: ' . $title, 'agenda:' . $id);
                flash('success', 'Agenda berhasil diperbarui.');
            } else {
                $stmt = $pdo->prepare('INSERT INTO agendas (title, slug, event_date, event_time, location, contact, summary, description, points, closing, is_active, created_at) VALUES (:title, :slug, :event_date, :event_time, :location, :contact, :summary, :description, :points, :closing, :is_active, NOW())');
                $stmt->execute([
                    'title' => $title,
                    'slug' => $slug,
                    'event_date' => $eventDate,
                    'event_time' => $eventTime,
                    'location' => $location,
                    'contact' => $contact,
                    'summary' => $summary,
                    'description' => $description,
                    'points' => $points,
                    'closing' => $closing,
                    'is_active' => $isActive,
                ]);
                log_activity('Tambah agenda', 'Agenda baru ditambahkan: ' . $title, 'agenda:new');
                flash('success', 'Agenda baru berhasil ditambahkan.');
            }
            redirect('agendas.php');
        }
    }
}

$pageTitle = $editing ? 'Edit Agenda' : 'Tambah Agenda';
require_once __DIR__ . '/includes/header.php';
?>
<section class="panel">
    <div class="panel-header">
        <div>
            <h2><?= htmlspecialchars($pageTitle) ?></h2>
            <p class="footer-note">Isi agenda kegiatan yang akan tampil di homepage dan halaman detail agenda.</p>
        </div>
        <a class="btn-secondary" href="agendas.php">Kembali ke agenda</a>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="post" class="form-card">
        <?= csrf_field() ?>
        <div class="form-grid">
            <div>
                <label for="title">Judul Agenda</label>
                <input type="text" id="title" name="title" value="<?= htmlspecialchars($item['title']) ?>" required>
            </div>
            <div>
                <label for="slug">Slug URL</label>
                <input type="text" id="slug" name="slug" value="<?= htmlspecialchars($item['slug']) ?>" placeholder="Kosongkan untuk otomatis">
            </div>
            <div>
                <label for="event_date">Tanggal</label>
                <input type="date" id="event_date" name="event_date" value="<?= htmlspecialchars($item['event_date']) ?>" required>
            </div>
            <div>
                <label for="event_time">Waktu</label>
                <input type="text" id="event_time" name="event_time" value="<?= htmlspecialchars($item['event_time']) ?>" placeholder="Contoh: 08.00 WIB" required>
            </div>
            <div>
                <label for="location">Lokasi</label>
                <input type="text" id="location" name="location" value="<?= htmlspecialchars($item['location']) ?>" required>
            </div>
            <div>
                <label for="contact">Kontak</label>
                <input type="text" id="contact" name="contact" value="<?= htmlspecialchars($item['contact']) ?>" placeholder="Contoh: Panitia / Tata Usaha">
            </div>
            <div class="full-span">
                <label for="summary">Ringkasan</label>
                <textarea id="summary" name="summary" required><?= htmlspecialchars($item['summary']) ?></textarea>
            </div>
            <div class="full-span">
                <label for="description">Deskripsi Kegiatan</label>
                <textarea id="description" name="description" placeholder="Pisahkan paragraf dengan baris kosong."><?= htmlspecialchars($item['description']) ?></textarea>
            </div>
            <div class="full-span">
                <label for="points">Agenda Kegiatan Meliputi</label>
                <textarea id="points" name="points" placeholder="Satu poin per baris."><?= htmlspecialchars($item['points']) ?></textarea>
            </div>
            <div class="full-span">
                <label for="closing">Kalimat Penutup</label>
                <textarea id="closing" name="closing"><?= htmlspecialchars($item['closing']) ?></textarea>
            </div>
            <div class="full-span field-inline">
                <label>
                    <input type="checkbox" name="is_active" <?= $item['is_active'] ? 'checked' : '' ?>> Aktifkan agenda
                </label>
            </div>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn-primary"><?= $editing ? 'Simpan Perubahan' : 'Tambah Agenda' ?></button>
            <a class="btn-secondary" href="agendas.php">Batal</a>
        </div>
    </form>
</section>

<?php require_once __DIR__ . '/includes/footer.php';