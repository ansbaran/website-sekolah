<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/functions.php';

$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 5;
if ($limit < 1 || $limit > 20) {
    $limit = 5;
}

try {
    $statement = $pdo->prepare('SELECT id, title, content, published_at FROM announcements WHERE status = 1 ORDER BY published_at DESC LIMIT :limit');
    $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
    $statement->execute();
    $announcements = $statement->fetchAll();

    $data = [];
    foreach ($announcements as $item) {
        $data[] = [
            'id' => $item['id'],
            'title' => htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8'),
            'content' => htmlspecialchars($item['content'], ENT_QUOTES, 'UTF-8'),
            'published_at' => $item['published_at'],
        ];
    }

    echo json_encode(['status' => 'success', 'data' => $data]);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Database error']);
}
