<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Allow: GET, HEAD');

if (!in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['GET', 'HEAD'], true)) {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed'], JSON_UNESCAPED_UNICODE);
    exit;
}

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/gallery-album-functions.php';

if (!enforce_rate_limit('public-gallery-albums')) {
    echo json_encode(['success' => false, 'message' => 'Too many requests'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $pagination = gallery_album_normalize_pagination($_GET);
    $category = gallery_album_normalize_category(isset($_GET['category']) ? (string) $_GET['category'] : null);
    if ($category === '__invalid__') {
        $category = '__no_match__';
    }

    $total = count_public_gallery_albums($category);
    $albums = get_public_gallery_albums([
        'page' => $pagination['page'],
        'limit' => $pagination['limit'],
        'category' => $category,
    ]);
    $totalPages = max(1, (int) ceil($total / $pagination['limit']));

    $payload = [
        'success' => true,
        'data' => $albums,
        'pagination' => [
            'page' => $pagination['page'],
            'limit' => $pagination['limit'],
            'total' => $total,
            'total_pages' => $totalPages,
            'has_previous' => $pagination['page'] > 1,
            'has_next' => $pagination['page'] < $totalPages,
        ],
        'categories' => get_public_gallery_categories(),
    ];

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'HEAD') {
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
} catch (Throwable $exception) {
    log_exception($exception);
    http_response_code(500);
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'HEAD') {
        echo json_encode(['success' => false, 'message' => 'Database error'], JSON_UNESCAPED_UNICODE);
    }
}