<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';

$achievements = get_public_achievements(24);

function achievement_page_excerpt(string $value, int $limit = 220): string
{
    $text = trim(strip_tags($value));
    if ($text === '') {
        return 'Informasi prestasi akan diperbarui secara berkala oleh sekolah.';
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
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Daftar prestasi siswa SD Cahaya Harapan Bekasi yang dikelola dari admin sekolah.">
    <title>Prestasi - SD Cahaya Harapan Bekasi</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css?v=20260711-prestasi-program-layout">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script src="assets/js/tailwind-config.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="activity-page program-page bg-slate-50 font-sans text-slate-700 antialiased">
    <div id="navbar"></div>

    <header class="prestasi-hero program-hero relative overflow-hidden bg-slate-950 px-4 pb-12 pt-[6.5rem] text-center text-white sm:px-6 lg:pt-28">
        <div class="container relative z-10 mx-auto max-w-5xl">
            <div class="hero-badge inline-flex rounded-full border border-white/15 bg-white/10 px-4 py-2 text-xs font-extrabold uppercase tracking-[0.22em] text-blue-100 backdrop-blur">Prestasi Sekolah</div>
            <h1 class="hero-title program-hero__title mt-5 text-3xl font-black leading-tight tracking-tight sm:text-5xl">Galeri Prestasi Anak <br> SD Cahaya Harapan Bekasi</h1>
            <p class="hero-subtitle program-hero__subtitle mx-auto mt-4 max-w-3xl text-sm leading-7 text-slate-300">
                Kumpulan capaian siswa yang membanggakan, tersusun ringkas agar mudah dibaca oleh orang tua dan calon siswa.
            </p>
            <div class="hero-decorator pointer-events-none absolute right-4 top-16 text-7xl font-black text-white/10">+</div>
        </div>
    </header>

    <main>
        <section class="activity-section extracurricular-section achievements-program-section program-section bg-slate-50 px-4 py-14 sm:px-6 lg:py-20" id="prestasi">
            <div class="container mx-auto max-w-6xl">
                <div class="gallery-header mb-8 flex items-center gap-3">
                    <div class="icon-box grid h-11 w-11 place-items-center rounded-2xl bg-blue-50 text-blue-700">
                        <i class="fas fa-trophy"></i>
                    </div>
                    <h2 class="text-2xl font-black tracking-tight text-slate-950 sm:text-3xl">Prestasi Sekolah</h2>
                </div>

                <div class="extracurricular-list achievement-zigzag-list mt-7">
                    <?php foreach ($achievements as $achievement): ?>
                        <?php
                            $image = build_upload_url('achievements', (string)($achievement['image'] ?? ''));
                            $image = $image !== '' ? $image : BASE_URL . '/assets/img/logo.png';
                        ?>
                        <article class="extracurricular-card achievement-zigzag-card group">
                            <div class="extracurricular-card__image achievement-zigzag-card__image">
                                <img src="<?= escape($image) ?>" alt="<?= escape((string)($achievement['title'] ?? 'Prestasi siswa')) ?>" class="transition duration-700 group-hover:scale-105" loading="lazy" decoding="async" data-fallback-src="assets/img/logo.png">
                            </div>
                            <div class="extracurricular-card__body achievement-zigzag-card__body">
                                <span class="extracurricular-card__tag achievement-zigzag-card__tag"><i class="fa-solid fa-medal" aria-hidden="true"></i><?= escape((string)($achievement['level'] ?? 'Prestasi')) ?></span>
                                <h3 class="extracurricular-card__title achievement-zigzag-card__title"><?= escape((string)($achievement['title'] ?? 'Prestasi Siswa')) ?></h3>
                                <p class="extracurricular-card__desc achievement-zigzag-card__desc"><?= escape(achievement_page_excerpt((string)($achievement['description'] ?? ''))) ?></p>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>

                <?php if (empty($achievements)): ?>
                    <div class="mt-10 rounded-3xl border border-dashed border-slate-300 bg-white p-8 text-center font-bold text-slate-500">Belum ada prestasi aktif yang dipublikasikan.</div>
                <?php endif; ?>
            </div>
        </section>
    </main>

    <div id="footer"></div>
    <script type="module" src="assets/js/main.js?v=20260711-prestasi-program-layout"></script>
    <script src="assets/js/seo.js"></script>
    <script src="assets/js/maintenance.js"></script>
</body>
</html>
