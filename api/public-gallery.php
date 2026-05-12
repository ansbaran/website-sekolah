<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/functions.php';

$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
if ($limit < 1 || $limit > 100) {
    $limit = 20;
}

try {
    $statement = $pdo->prepare('SELECT id, title, category, filename FROM gallery ORDER BY created_at DESC LIMIT :limit');
    $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
    $statement->execute();
    $gallery = $statement->fetchAll();

    $data = [];
    foreach ($gallery as $item) {
        $data[] = [
            'id' => $item['id'],
            'title' => htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8'),
            'category' => htmlspecialchars($item['category'], ENT_QUOTES, 'UTF-8'),
            'image' => build_upload_url('gallery', $item['filename']),
        ];
    }

    echo json_encode(['status' => 'success', 'data' => $data]);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Database error']);
}
