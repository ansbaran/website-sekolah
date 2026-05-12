<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/functions.php';

$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
if ($limit < 1 || $limit > 50) {
    $limit = 10;
}

try {
    $statement = $pdo->prepare('SELECT id, title, level, description, image FROM achievements ORDER BY created_at DESC LIMIT :limit');
    $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
    $statement->execute();
    $achievements = $statement->fetchAll();

    $data = [];
    foreach ($achievements as $item) {
        $data[] = [
            'id' => $item['id'],
            'title' => htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8'),
            'level' => htmlspecialchars($item['level'], ENT_QUOTES, 'UTF-8'),
            'description' => htmlspecialchars($item['description'] ?? '', ENT_QUOTES, 'UTF-8'),
            'image' => $item['image'] ? build_upload_url('achievements', $item['image']) : null,
        ];
    }

    echo json_encode(['status' => 'success', 'data' => $data]);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Database error']);
}
