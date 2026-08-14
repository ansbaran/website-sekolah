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
if (!enforce_rate_limit('public-gallery')) {
    echo json_encode(['status' => 'error', 'message' => 'Too many requests'], JSON_UNESCAPED_UNICODE);
    exit;
}

$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
if ($limit < 1 || $limit > 100) {
    $limit = 20;
}

try {
    $category = strtolower(trim((string)($_GET['category'] ?? '')));
    $allowedCategories = array_map(static fn($item) => strtolower($item), gallery_categories());
    $isSpecificCategory = $category !== '' && $category !== 'semua' && in_array($category, $allowedCategories, true);
    $includeAchievements = $category === '' || $category === 'semua' || $category === 'prestasi';

    $queries = [];
    $params = [];

    if (!$isSpecificCategory || $category !== 'prestasi') {
        $galleryWhere = '';
        if ($isSpecificCategory) {
            $galleryWhere = ' WHERE LOWER(category) = :category';
            $params['category'] = $category;
        }

        $queries[] = "SELECT CONCAT('gallery-', id) AS public_id, title, category, filename AS image_file, 'gallery' AS upload_dir, created_at FROM gallery" . $galleryWhere;
    }

    if ($includeAchievements) {
        $queries[] = "SELECT CONCAT('achievement-', id) AS public_id, title, 'Prestasi' AS category, image AS image_file, 'achievements' AS upload_dir, created_at FROM achievements WHERE image IS NOT NULL AND image != ''";
    }

    if (empty($queries)) {
        echo json_encode(['status' => 'success', 'data' => []]);
        exit;
    }

    $sql = implode(' UNION ALL ', $queries) . ' ORDER BY created_at DESC LIMIT :limit';
    $statement = $pdo->prepare($sql);
    foreach ($params as $key => $value) {
        $statement->bindValue(':' . $key, $value, PDO::PARAM_STR);
    }
    $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
    $statement->execute();
    $gallery = $statement->fetchAll();

    $data = [];
    foreach ($gallery as $item) {
        $data[] = [
            'id' => $item['public_id'],
            'title' => htmlspecialchars((string)$item['title'], ENT_QUOTES, 'UTF-8'),
            'category' => htmlspecialchars((string)$item['category'], ENT_QUOTES, 'UTF-8'),
            'image' => build_upload_url((string)$item['upload_dir'], (string)$item['image_file']),
        ];
    }

    echo json_encode(['status' => 'success', 'data' => $data]);
} catch (Throwable $e) {
    log_exception($e);
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database error'], JSON_UNESCAPED_UNICODE);
}
