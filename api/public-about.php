<?php

declare(strict_types=1);

define('SKIP_SESSION_BOOTSTRAP', true);

require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

if (!enforce_rate_limit('public-about')) {
    echo json_encode(['status' => 'error', 'message' => 'Terlalu banyak permintaan.'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $missions = decode_json_setting('about_missions', [
        'Menyelenggarakan pendidikan yang menumbuhkan iman, karakter, dan prestasi.',
        'Membangun lingkungan belajar yang aman, disiplin, kreatif, dan penuh kasih.',
        'Menguatkan kerja sama sekolah, orang tua, dan masyarakat.',
    ]);
    $settings = [
        'principal' => [
            'badge' => normalize_public_text(get_setting('about_principal_badge', 'Sambutan Kepala Sekolah'), 'Sambutan Kepala Sekolah', 80),
            'title' => normalize_public_text(get_setting('about_principal_title', 'Iman Kuat, Karakter Hebat, Prestasi Bermartabat'), 'Iman Kuat, Karakter Hebat, Prestasi Bermartabat', 180),
            'message' => normalize_public_text(get_setting('about_principal_message', ''), '', 5000),
            'name' => normalize_public_text(get_setting('about_principal_name', 'Paulus Ngabur, S.Pd'), 'Paulus Ngabur, S.Pd', 120),
            'role' => normalize_public_text(get_setting('about_principal_role', 'Kepala Sekolah'), 'Kepala Sekolah', 120),
            'image' => normalize_public_image_value((string)get_setting('about_principal_image', 'assets/img/tim-kep/kepsek.jpg?v=1'), 'assets/img/tim-kep/kepsek.jpg?v=1'),
        ],
        'vision' => normalize_public_text(get_setting('about_vision', 'Menjadi sekolah Katolik yang unggul dalam iman, karakter, dan prestasi.'), 'Menjadi sekolah Katolik yang unggul dalam iman, karakter, dan prestasi.', 1000),
        'missions' => array_values(array_filter(array_map(static fn($item) => normalize_public_text($item, '', 700), $missions))),
    ];
    $settings['principal']['image_url'] = public_content_image_url($settings['principal']['image'], 'assets/img/tim-kep/kepsek.jpg?v=1');

    echo json_encode(['status' => 'success', 'data' => $settings], JSON_UNESCAPED_UNICODE);
} catch (Throwable $exception) {
    log_exception($exception);
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Konten profil sekolah belum dapat dimuat.'], JSON_UNESCAPED_UNICODE);
}
