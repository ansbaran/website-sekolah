<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit;
}

$target = $_POST['target'] ?? 'misc';
if (empty($_FILES['file'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'File tidak ditemukan.']);
    exit;
}

$file = $_FILES['file'];

$uploadName = upload_image($file, $target, $error);
if ($uploadName === null) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => $error]);
    exit;
}

echo json_encode([
    'status' => 'success',
    'file' => $uploadName,
    'url' => build_upload_url($target, $uploadName),
]);
