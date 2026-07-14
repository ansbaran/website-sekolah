<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed'], JSON_UNESCAPED_UNICODE);
    exit;
}
header('Cache-Control: no-store');

$status = [
    'maintenance' => is_maintenance_mode(),
];

echo json_encode(['status' => 'success', 'data' => $status]);
