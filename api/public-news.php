<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/functions.php';

$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
if ($limit < 1 || $limit > 50) {
    $limit = 10;
}

try {
    $statement = $pdo->prepare('SELECT id, title, category, excerpt, published_at, thumbnail FROM news WHERE is_active = 1 ORDER BY published_at DESC LIMIT :limit');
    $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
    $statement->execute();
    $news = $statement->fetchAll();

    $data = [];
    foreach ($news as $item) {
        $data[] = [
            'id' => $item['id'],
            'title' => htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8'),
            'category' => htmlspecialchars($item['category'], ENT_QUOTES, 'UTF-8'),
            'excerpt' => htmlspecialchars($item['excerpt'] ?? '', ENT_QUOTES, 'UTF-8'),
            'published_at' => $item['published_at'],
            'thumbnail' => $item['thumbnail'] ? build_upload_url('news', $item['thumbnail']) : null,
        ];
    }

    echo json_encode(['status' => 'success', 'data' => $data]);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Database error']);
}
