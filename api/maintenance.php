<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/functions.php';

$status = [
    'maintenance' => is_maintenance_mode(),
];

echo json_encode(['status' => 'success', 'data' => $status]);
