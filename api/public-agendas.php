<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed'], JSON_UNESCAPED_UNICODE);
    exit;
}

define('SKIP_SESSION_BOOTSTRAP', true);

require_once __DIR__ . '/../includes/functions.php';
header('Cache-Control: no-store, no-cache, must-revalidate');

if (!enforce_rate_limit('public-agendas')) {
    echo json_encode(['status' => 'error', 'message' => 'Too many requests'], JSON_UNESCAPED_UNICODE);
    exit;
}

$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 3;
if ($limit < 1 || $limit > 12) {
    $limit = 3;
}

try {
    $agendas = get_public_agendas($limit);
    $data = [];

    foreach ($agendas as $item) {
        $data[] = [
            'id' => (int) ($item['id'] ?? 0),
            'title' => (string) ($item['title'] ?? ''),
            'slug' => (string) ($item['slug'] ?? ''),
            'event_date' => (string) ($item['event_date'] ?? ''),
            'event_time' => (string) ($item['event_time'] ?? ''),
            'location' => (string) ($item['location'] ?? ''),
            'summary' => (string) ($item['summary'] ?? ''),
        ];
    }

    echo json_encode(['status' => 'success', 'data' => $data], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    log_exception($e);
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database error'], JSON_UNESCAPED_UNICODE);
}
