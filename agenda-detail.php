<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';

$slug = clean($_GET['slug'] ?? 'pertemuan-orang-tua-siswa');
$agenda = get_agenda_by_slug($slug);

if ($agenda !== null) {
    increment_agenda_views($slug);
    $agenda['views'] = (int) ($agenda['views'] ?? 0) + 1;
}

if ($agenda === null) {
    http_response_code(404);
}

function agenda_date_parts(string $date): array
{
    $timestamp = strtotime($date);
    if (!$timestamp) {
        return ['day' => '--', 'month' => 'Agenda', 'year' => ''];
    }

    $months = [
        1 => 'Januari',
        2 => 'Februari',
        3 => 'Maret',
        4 => 'April',
        5 => 'Mei',
        6 => 'Juni',
        7 => 'Juli',
        8 => 'Agustus',
        9 => 'September',
        10 => 'Oktober',
        11 => 'November',
        12 => 'Desember'
    ];

    return [
        'day' => date('d', $timestamp),
        'month' => $months[(int) date('n', $timestamp)],
        'year' => date('Y', $timestamp)
    ];
}

$descriptionParagraphs = [];
$agendaPoints = [];

if ($agenda !== null) {
    $descriptionParagraphs = array_values(array_filter(array_map('trim', preg_split('/\R{2,}/', (string) ($agenda['description'] ?? '')) ?: [])));
    if (empty($descriptionParagraphs) && !empty($agenda['description'])) {
        $descriptionParagraphs = [(string) $agenda['description']];
    }
    $agendaPoints = agenda_points_to_array($agenda['points'] ?? '');
}

$dateParts = $agenda ? agenda_date_parts((string) $agenda['event_date']) : ['day' => '404', 'month' => 'Agenda', 'year' => ''];
$pageTitle = $agenda ? (string) $agenda['title'] : 'Agenda Tidak Ditemukan';
$pageDescription = $agenda ? (string) $agenda['summary'] : 'Agenda yang Anda cari tidak tersedia.';
$currentUrl = ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];?>
<!DOCTYPE html>
<html lang="id">
<head>
<script src="assets/js/google-tag.js?v=20260731-ga4-1"></script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= escape($pageTitle) ?> - SD Cahaya Harapan Bekasi</title>
    <meta name="description" content="<?= escape($pageDescription) ?>">
    <link rel="canonical" href="<?= escape($currentUrl) ?>">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css?v=20260709-agenda-detail-compact">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script src="assets/js/tailwind-config.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="agenda-detail-page bg-white font-sans text-slate-700 antialiased">
    <div id="navbar"></div>

    <main class="agenda-detail-main">
        <article class="agenda-detail" data-agenda-detail data-share-title="<?= escape($pageTitle) ?>">
            <div class="agenda-detail__shell">
                <a class="agenda-detail__back" href="index.html#info-sekolah">
                    <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
                    <span>Kembali</span>
                </a>

                <?php if ($agenda === null): ?>
                    <section class="agenda-detail__not-found" aria-labelledby="agenda-not-found-title">
                        <span class="agenda-detail__badge"><i class="fa-regular fa-calendar-xmark" aria-hidden="true"></i> Agenda</span>
                        <h1 id="agenda-not-found-title">Agenda Tidak Ditemukan</h1>
                        <p>Maaf, agenda yang Anda cari belum tersedia atau alamatnya tidak sesuai.</p>
                        <a class="agenda-detail__primary-link" href="index.html#info-sekolah">Lihat Agenda Lainnya</a>
                    </section>
                <?php else: ?>
                    <header class="agenda-detail__hero">
                        <div class="agenda-detail__meta" aria-label="Informasi singkat agenda">
                            <span class="agenda-detail__badge"><i class="fa-regular fa-calendar-days" aria-hidden="true"></i> Agenda</span>
                            <span><i class="fa-regular fa-clock" aria-hidden="true"></i> <?= escape($agenda['event_time']) ?></span>
                            <span><i class="fa-solid fa-location-dot" aria-hidden="true"></i> <?= escape($agenda['location']) ?></span>
                            <span><i class="fa-regular fa-eye" aria-hidden="true"></i> <?= escape((string) $agenda['views']) ?> kali</span>
                        </div>

                        <h1><?= escape($agenda['title']) ?></h1>
                        <p><?= escape($agenda['summary']) ?></p>
                    </header>

                    <section class="agenda-detail__quick-info" aria-label="Detail agenda">
                        <div class="agenda-detail__date-card">
                            <span><?= escape($dateParts['month']) ?></span>
                            <strong><?= escape($dateParts['day']) ?></strong>
                            <small><?= escape($dateParts['year']) ?></small>
                        </div>

                        <div class="agenda-detail__info-card">
                            <span class="agenda-detail__info-icon"><i class="fa-solid fa-location-dot" aria-hidden="true"></i></span>
                            <div>
                                <p>Lokasi</p>
                                <strong><?= escape($agenda['location']) ?></strong>
                            </div>
                        </div>

                        <div class="agenda-detail__info-card">
                            <span class="agenda-detail__info-icon"><i class="fa-regular fa-user" aria-hidden="true"></i></span>
                            <div>
                                <p>Kontak</p>
                                <strong><?= escape($agenda['contact']) ?></strong>
                            </div>
                        </div>
                    </section>

                    <section class="agenda-detail__content" aria-labelledby="agenda-description-title">
                        <h2 id="agenda-description-title">Deskripsi Kegiatan</h2>
                        <?php foreach ($descriptionParagraphs as $paragraph): ?>
                            <p><?= escape($paragraph) ?></p>
                        <?php endforeach; ?>

                        <div class="agenda-detail__point-block">
                            <h3>Agenda kegiatan meliputi:</h3>
                            <ul class="agenda-detail__point-list">
                            <?php foreach ($agendaPoints as $point): ?>
                                <li><span aria-hidden="true"><i class="fa-solid fa-check"></i></span><p><?= escape($point) ?></p></li>
                            <?php endforeach; ?>
                        </ul>
                        </div>

                        <p><?= escape($agenda['closing']) ?></p>
                    </section>

                    <footer class="agenda-detail__share" aria-label="Bagikan agenda">
                        <span class="agenda-detail__share-label"><i class="fa-solid fa-share-nodes" aria-hidden="true"></i> Bagikan:</span>
                        <a class="agenda-detail__share-button agenda-detail__share-button--wa" href="https://wa.me/?text=<?= urlencode($pageTitle . ' - ' . $currentUrl) ?>" target="_blank" rel="noopener noreferrer" aria-label="Bagikan ke WhatsApp"><i class="fa-brands fa-whatsapp" aria-hidden="true"></i></a>
                        <a class="agenda-detail__share-button agenda-detail__share-button--fb" href="https://www.facebook.com/sharer/sharer.php?u=<?= urlencode($currentUrl) ?>" target="_blank" rel="noopener noreferrer" aria-label="Bagikan ke Facebook"><i class="fa-brands fa-facebook-f" aria-hidden="true"></i></a>
                        <button class="agenda-detail__share-button agenda-detail__share-button--copy" type="button" data-copy-link aria-label="Salin link"><i class="fa-solid fa-link" aria-hidden="true"></i></button>
                        <button class="agenda-detail__print" type="button" data-print-agenda><i class="fa-solid fa-print" aria-hidden="true"></i> Simpan PDF</button>
                    </footer>
                <?php endif; ?>
            </div>
        </article>
    </main>

    <div id="footer"></div>
    <script type="module" src="assets/js/main.js?v=20260709-agenda-detail-compact"></script>
    <script src="assets/js/agenda-detail.js?v=20260709-agenda-detail-compact"></script>
    <script src="assets/js/seo.js"></script>
    <script src="assets/js/maintenance.js"></script>
</body>
</html>