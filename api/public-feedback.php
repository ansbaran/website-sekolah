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
header('Cache-Control: no-store, max-age=0');

if (!enforce_rate_limit('public-feedback')) {
    echo json_encode(['status' => 'error', 'message' => 'Too many requests'], JSON_UNESCAPED_UNICODE);
    exit;
}

$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 6;
if ($limit < 1 || $limit > 20) {
    $limit = 6;
}

try {
    $statement = $pdo->prepare(
        'SELECT parent_name, parent_email, relation_label, student_label, message, rating
         FROM parent_feedback
         WHERE is_approved = 1 AND is_active = 1 AND consent_given = 1
         ORDER BY display_order ASC, created_at DESC
         LIMIT :limit'
    );
    $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
    $statement->execute();
    $items = $statement->fetchAll();

    $data = [];
    foreach ($items as $item) {
        $name = trim((string)$item['parent_name']);
        $relation = trim((string)$item['relation_label']);
        $student = trim((string)($item['student_label'] ?? ''));
        $role = $student !== '' ? $relation . ' ' . $student : $relation;

        $data[] = [
            'name' => $name,
            'role' => $role,
            'message' => trim((string)$item['message']),
            'rating' => (float)$item['rating'],
            'initials' => feedback_initials($name),
            'avatar_url' => feedback_avatar_url((string)($item['parent_email'] ?? '')),
        ];
    }

    echo json_encode(['status' => 'success', 'data' => $data], JSON_UNESCAPED_UNICODE);
} catch (Throwable $exception) {
    log_exception($exception);
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database error'], JSON_UNESCAPED_UNICODE);
}
