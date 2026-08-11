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

if (!enforce_rate_limit('public-gallery-album')) {
    echo json_encode(['success' => false, 'message' => 'Too many requests'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $slug = gallery_album_validate_slug(isset($_GET['slug']) ? (string) $_GET['slug'] : '');
    if ($slug === null) {
        http_response_code(400);
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'HEAD') {
            echo json_encode(['success' => false, 'message' => 'Invalid slug'], JSON_UNESCAPED_UNICODE);
        }
        exit;
    }

    $album = get_public_gallery_album_by_slug($slug);
    if ($album === null) {
        http_response_code(404);
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'HEAD') {
            echo json_encode(['success' => false, 'message' => 'Album not found'], JSON_UNESCAPED_UNICODE);
        }
        exit;
    }

    $photos = get_public_gallery_album_photos($album['id']);
    $payload = [
        'success' => true,
        'album' => $album,
        'photos' => $photos,
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