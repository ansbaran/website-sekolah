<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';

$agendas = get_public_agendas(24);

function agenda_page_date_parts(string $date): array
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
        12 => 'Desember',
    ];

    return [
        'day' => date('d', $timestamp),
        'month' => $months[(int) date('n', $timestamp)],
        'year' => date('Y', $timestamp),
    ];
}

function agenda_page_excerpt(string $value, int $limit = 220): string
{
    $text = trim(strip_tags($value));
    if ($text === '') {
        return 'Informasi agenda sekolah akan diperbarui secara berkala.';
    }

    if (mb_strlen($text) <= $limit) {
        return $text;
    }

    return rtrim(mb_substr($text, 0, $limit), " \t\n\r\0\x0B.,") . '...';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<script src="assets/js/google-tag.js?v=20260731-ga4-1"></script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Daftar agenda dan jadwal kegiatan SD Cahaya Harapan Bekasi untuk orang tua dan siswa.">
    <link rel="canonical" href="https://sdcahayaharapanbekasi.sch.id/agenda">
    <meta property="og:url" content="https://sdcahayaharapanbekasi.sch.id/agenda">
    <title>Agenda - SD Cahaya Harapan Bekasi</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css?v=20260711-agenda-program-layout">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script src="assets/js/tailwind-config.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="activity-page program-page bg-slate-50 font-sans text-slate-700 antialiased">
    <div id="navbar"></div>

    <header class="prestasi-hero program-hero relative overflow-hidden bg-slate-950 px-4 pb-12 pt-[6.5rem] text-center text-white sm:px-6 lg:pt-28">
        <div class="container relative z-10 mx-auto max-w-5xl">
            <div class="hero-badge inline-flex rounded-full border border-white/15 bg-white/10 px-4 py-2 text-xs font-extrabold uppercase tracking-[0.22em] text-blue-100 backdrop-blur">Agenda Kegiatan</div>
            <h1 class="hero-title program-hero__title mt-5 text-3xl font-black leading-tight tracking-tight sm:text-5xl">Agenda Sekolah <br> SD Cahaya Harapan Bekasi</h1>
            <p class="hero-subtitle program-hero__subtitle mx-auto mt-4 max-w-3xl text-sm leading-7 text-slate-300">
                Informasi jadwal kegiatan sekolah, waktu pelaksanaan, lokasi, dan ringkasan penting untuk orang tua.
            </p>
            <div class="hero-decorator pointer-events-none absolute right-4 top-16 text-7xl font-black text-white/10">+</div>
        </div>
    </header>

    <main>
        <section class="activity-section extracurricular-section agenda-program-section program-section bg-slate-50 px-4 py-14 sm:px-6 lg:py-20" id="agenda">
            <div class="container mx-auto max-w-6xl">
                <div class="gallery-header mb-8 flex items-center gap-3">
                    <div class="icon-box grid h-11 w-11 place-items-center rounded-2xl bg-blue-50 text-blue-700">
                        <i class="far fa-calendar-days"></i>
                    </div>
                    <h2 class="text-2xl font-black tracking-tight text-slate-950 sm:text-3xl">Agenda Sekolah</h2>
                </div>

                <div class="extracurricular-list agenda-zigzag-list mt-7">
                    <?php foreach ($agendas as $agenda): ?>
                        <?php $dateParts = agenda_page_date_parts((string)($agenda['event_date'] ?? '')); ?>
                        <article class="extracurricular-card agenda-zigzag-card group">
                            <a class="agenda-zigzag-card__link" href="agenda/<?= urlencode((string)($agenda['slug'] ?? '')) ?>">
                                <time class="extracurricular-card__image agenda-zigzag-card__date" datetime="<?= escape((string)($agenda['event_date'] ?? '')) ?>">
                                    <span><?= escape(strtoupper($dateParts['month'])) ?></span>
                                    <strong><?= escape($dateParts['day']) ?></strong>
                                    <small><?= escape($dateParts['year']) ?></small>
                                </time>
                                <div class="extracurricular-card__body agenda-zigzag-card__body">
                                    <span class="extracurricular-card__tag agenda-zigzag-card__tag"><i class="fa-regular fa-calendar-check" aria-hidden="true"></i>Agenda</span>
                                    <h3 class="extracurricular-card__title agenda-zigzag-card__title"><?= escape((string)($agenda['title'] ?? 'Agenda Sekolah')) ?></h3>
                                    <div class="agenda-zigzag-card__meta">
                                        <span><i class="fa-regular fa-clock" aria-hidden="true"></i><?= escape((string)($agenda['event_time'] ?? '-')) ?></span>
                                        <span><i class="fa-solid fa-location-dot" aria-hidden="true"></i><?= escape((string)($agenda['location'] ?? 'Sekolah')) ?></span>
                                    </div>
                                    <p class="extracurricular-card__desc agenda-zigzag-card__desc"><?= escape(agenda_page_excerpt((string)($agenda['summary'] ?? $agenda['description'] ?? ''))) ?></p>
                                </div>
                            </a>
                        </article>
                    <?php endforeach; ?>
                </div>

                <?php if (empty($agendas)): ?>
                    <div class="mt-10 rounded-3xl border border-dashed border-slate-300 bg-white p-8 text-center font-bold text-slate-500">Belum ada agenda aktif yang dipublikasikan.</div>
                <?php endif; ?>
            </div>
        </section>
    </main>

    <div id="footer"></div>
    <script type="module" src="assets/js/main.js?v=20260826-clean-url1"></script>
    <script src="assets/js/seo.js"></script>
    <script src="assets/js/maintenance.js?v=20260827-maintenance-api-root-1"></script>
</body>
</html>
