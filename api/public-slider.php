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
if (!enforce_rate_limit('public-slider')) {
    echo json_encode(['status' => 'error', 'message' => 'Too many requests'], JSON_UNESCAPED_UNICODE);
    exit;
}

$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 5;
if ($limit < 1 || $limit > 20) {
    $limit = 5;
}

try {
    $statement = $pdo->prepare('SELECT id, title, subtitle, background FROM slider WHERE is_active = 1 ORDER BY created_at DESC, id DESC LIMIT :limit');
    $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
    $statement->execute();
    $slides = $statement->fetchAll();

    $data = [];
    foreach ($slides as $item) {
        $data[] = [
            'id' => $item['id'],
            'title' => htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8'),
            'subtitle' => htmlspecialchars($item['subtitle'] ?? '', ENT_QUOTES, 'UTF-8'),
            'background' => build_upload_url('slider', $item['background']),
        ];
    }

    echo json_encode(['status' => 'success', 'data' => $data]);
} catch (Throwable $e) {
    log_exception($e);
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database error'], JSON_UNESCAPED_UNICODE);
}
