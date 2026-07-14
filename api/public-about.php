<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

if (!enforce_rate_limit('public-about')) {
    echo json_encode(['status' => 'error', 'message' => 'Terlalu banyak permintaan.'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $settings = get_school_profile_settings();
    $settings['principal']['image_url'] = public_content_image_url($settings['principal']['image'], 'assets/img/tim-kep/kepsek.jpg?v=1');
    foreach ($settings['leadership_team'] as &$member) {
        $member['image_url'] = public_content_image_url($member['image'], 'assets/img/logo.png');
    }
    unset($member);

    echo json_encode(['status' => 'success', 'data' => $settings], JSON_UNESCAPED_UNICODE);
} catch (Throwable $exception) {
    log_exception($exception);
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Konten profil sekolah belum dapat dimuat.'], JSON_UNESCAPED_UNICODE);
}