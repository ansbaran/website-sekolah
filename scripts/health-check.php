<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'localhost';
$_SERVER['REMOTE_ADDR'] = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
$_SERVER['DOCUMENT_ROOT'] = $_SERVER['DOCUMENT_ROOT'] ?? dirname(__DIR__);

$localSessionPath = dirname(__DIR__) . '/cache/sessions';
if (!is_dir($localSessionPath)) {
    @mkdir($localSessionPath, 0755, true);
}
if (is_dir($localSessionPath) && is_writable($localSessionPath)) {
    ini_set('session.save_path', $localSessionPath);
}

require_once __DIR__ . '/../config/config.php';

$checks = [];

function health_check_add(array &$checks, string $name, bool $ok, string $detail = ''): void
{
    $checks[] = [
        'name' => $name,
        'ok' => $ok,
        'detail' => $detail,
    ];
}

function health_check_http(string $url): array
{
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'ignore_errors' => true,
            'timeout' => 5,
        ],
    ]);

    $content = @file_get_contents($url, false, $context);
    $statusLine = $http_response_header[0] ?? '';
    preg_match('/\s(\d{3})\s/', $statusLine, $matches);
    $status = isset($matches[1]) ? (int)$matches[1] : 0;

    return [
        'status' => $status,
        'ok' => $content !== false && $status < 500,
    ];
}

$requiredConstants = [
    'BASE_PATH',
    'UPLOAD_BASE',
    'CACHE_DIR',
    'BACKUP_DIR',
    'MAX_IMAGE_SIZE',
    'ALLOWED_IMAGE_TYPES',
    'ALLOWED_IMAGE_EXT',
    'ALLOWED_UPLOAD_DIRS',
    'SESSION_TIMEOUT_SECONDS',
    'LOGIN_RATE_LIMIT_ATTEMPTS',
];

foreach ($requiredConstants as $constant) {
    health_check_add($checks, 'constant:' . $constant, defined($constant));
}

$databaseName = $pdo->query('SELECT DATABASE()')->fetchColumn();
health_check_add($checks, 'database:connection', is_string($databaseName) && $databaseName !== '', 'database=' . (string)$databaseName);

$requiredTables = ['users', 'news', 'achievements', 'gallery', 'slider', 'announcements', 'settings', 'media', 'activity_logs', 'login_attempts', 'staff', 'parent_feedback'];
foreach ($requiredTables as $table) {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table');
    $stmt->execute(['table' => $table]);
    health_check_add($checks, 'table:' . $table, (int)$stmt->fetchColumn() === 1);
}

$userColumns = ['is_active'];
foreach ($userColumns as $column) {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND COLUMN_NAME = :column');
    $stmt->execute(['table' => 'users', 'column' => $column]);
    health_check_add($checks, 'users-column:' . $column, (int)$stmt->fetchColumn() === 1);
}

$adminCount = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role IN ('super_admin', 'admin')")->fetchColumn();
health_check_add($checks, 'login:admin-account', $adminCount > 0, 'admin_count=' . $adminCount);

$activeAdminCount = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role IN ('super_admin', 'admin') AND is_active = 1")->fetchColumn();
health_check_add($checks, 'login:active-admin-account', $activeAdminCount > 0, 'active_admin_count=' . $activeAdminCount);

$validAdminHashCount = (int) $pdo->query(
    "SELECT COUNT(*) FROM users WHERE role IN ('super_admin', 'admin') AND is_active = 1 "
    . "AND password <> '' AND password <> 'REPLACE_WITH_PASSWORD_HASH' "
    . "AND (password LIKE '\$2y\$%' OR password LIKE '\$argon2i\$%' OR password LIKE '\$argon2id\$%')"
)->fetchColumn();
health_check_add($checks, 'login:admin-password-hash', $validAdminHashCount > 0, 'valid_hash_count=' . $validAdminHashCount);

$settingsColumns = ['name', 'value', 'updated_at'];
foreach ($settingsColumns as $column) {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND COLUMN_NAME = :column');
    $stmt->execute(['table' => 'settings', 'column' => $column]);
    health_check_add($checks, 'settings-column:' . $column, (int)$stmt->fetchColumn() === 1);
}

$stmt = $pdo->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND COLUMN_NAME = :column AND NON_UNIQUE = 0');
$stmt->execute(['table' => 'settings', 'column' => 'name']);
health_check_add($checks, 'settings-index:name-unique', (int)$stmt->fetchColumn() >= 1);

$feedbackColumns = ['consent_given', 'submitted_ip_hash', 'submitted_user_agent'];
foreach ($feedbackColumns as $column) {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND COLUMN_NAME = :column');
    $stmt->execute(['table' => 'parent_feedback', 'column' => $column]);
    health_check_add($checks, 'parent-feedback-column:' . $column, (int)$stmt->fetchColumn() === 1);
}

$writableDirs = [
    'uploads' => UPLOAD_BASE,
    'cache' => CACHE_DIR,
    'backups' => BACKUP_DIR,
    'logs' => LOG_DIR,
];

foreach ($writableDirs as $name => $path) {
    health_check_add($checks, 'writable:' . $name, is_dir($path) && is_writable($path), $path);
}

health_check_add($checks, 'session:active', session_status() === PHP_SESSION_ACTIVE, 'save_path=' . session_save_path());

$lintTargets = [
    'admin/login.php',
    'admin/dashboard.php',
    'admin/system.php',
    'admin/feedback.php',
    'admin/feedback-form.php',
    'admin/download-backup.php',
    'api/maintenance.php',
    'api/public-news.php',
    'api/public-gallery.php',
    'api/public-staff.php',
    'api/public-announcements.php',
    'api/public-ppdb.php',
    'api/public-feedback.php',
    'api/submit-feedback.php',
    'api/upload.php',
];

foreach ($lintTargets as $target) {
    $file = BASE_PATH . '/' . $target;
    $output = [];
    $exitCode = 0;
    exec(PHP_BINARY . ' -l ' . escapeshellarg($file), $output, $exitCode);
    health_check_add($checks, 'endpoint-syntax:' . $target, $exitCode === 0);
}

$baseUrl = getenv('HEALTHCHECK_BASE_URL') ?: '';
if ($baseUrl !== '') {
    $baseUrl = rtrim($baseUrl, '/');
    foreach ([
        'api/maintenance.php',
        'api/public-slider.php?limit=1',
        'api/public-news.php?limit=1',
        'api/public-gallery.php?limit=1',
        'api/public-staff.php?limit=1',
        'api/public-achievements.php?limit=1',
        'api/public-announcements.php?limit=1',
        'api/public-ppdb.php',
        'api/public-feedback.php?limit=1',
        'api/submit-feedback.php',
        'admin/login.php',
    ] as $endpoint) {
        $result = health_check_http($baseUrl . '/' . $endpoint);
        health_check_add($checks, 'endpoint-http:' . $endpoint, $result['ok'], 'status=' . $result['status']);
    }
}

$failed = array_filter($checks, static fn(array $check): bool => !$check['ok']);

foreach ($checks as $check) {
    echo ($check['ok'] ? '[OK] ' : '[FAIL] ') . $check['name'];
    if ($check['detail'] !== '') {
        echo ' - ' . $check['detail'];
    }
    echo PHP_EOL;
}

exit(empty($failed) ? 0 : 1);
