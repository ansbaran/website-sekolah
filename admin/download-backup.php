<?php

declare(strict_types=1);

define('ADMIN_CONTEXT', true);

require_once __DIR__ . '/../includes/auth.php';
require_login();
require_role('super_admin', 'admin');

$fileName = basename((string)($_GET['file'] ?? ''));
$path = BACKUP_DIR . '/' . $fileName;
$realPath = realpath($path);
$backupDir = realpath(BACKUP_DIR);

if ($fileName === '' || $realPath === false || $backupDir === false || strpos($realPath, $backupDir) !== 0 || !is_file($realPath)) {
    http_response_code(404);
    echo 'File backup tidak ditemukan.';
    exit;
}

$allowedExtensions = ['sql', 'zip'];
$extension = strtolower(pathinfo($realPath, PATHINFO_EXTENSION));
if (!in_array($extension, $allowedExtensions, true)) {
    http_response_code(403);
    echo 'Tipe file backup tidak diizinkan.';
    exit;
}

header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . rawurlencode($fileName) . '"');
header('Content-Length: ' . (string)filesize($realPath));
header('X-Content-Type-Options: nosniff');
readfile($realPath);
exit;
