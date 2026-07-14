<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed'], JSON_UNESCAPED_UNICODE);
    exit;
}

require_once __DIR__ . '/../includes/functions.php';

header('Cache-Control: no-store, no-cache, must-revalidate');
if (!enforce_rate_limit('public-ppdb')) {
    echo json_encode(['status' => 'error', 'message' => 'Too many requests'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $settings = get_ppdb_settings();
    $settings['whatsapp_url'] = 'https://wa.me/'
        . preg_replace('/\D/', '', $settings['whatsapp_number'])
        . '?text=' . rawurlencode($settings['whatsapp_message']);

    echo json_encode(['status' => 'success', 'data' => $settings], JSON_UNESCAPED_UNICODE);
} catch (Throwable $exception) {
    log_exception($exception);
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database error'], JSON_UNESCAPED_UNICODE);
}
