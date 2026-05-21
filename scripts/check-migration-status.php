<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'localhost';
$_SERVER['REMOTE_ADDR'] = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
$_SERVER['DOCUMENT_ROOT'] = $_SERVER['DOCUMENT_ROOT'] ?? dirname(__DIR__);

$sessionPath = dirname(__DIR__) . '/cache/sessions';
if (!is_dir($sessionPath)) {
    @mkdir($sessionPath, 0755, true);
}
if (is_dir($sessionPath) && is_writable($sessionPath)) {
    ini_set('session.save_path', $sessionPath);
}

require_once __DIR__ . '/../config/config.php';

function check_query_value(PDO $pdo, string $sql, array $params = []): int
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (int)$stmt->fetchColumn();
}

function check_line(string $status, string $name, string $detail = ''): void
{
    echo '[' . $status . '] ' . $name;
    if ($detail !== '') {
        echo ' - ' . $detail;
    }
    echo PHP_EOL;
}

$database = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
check_line('INFO', 'database', $database);

$schemaMigrationColumns = [
    'version' => ['type' => 'varchar(100)', 'null' => 'NO', 'key' => 'PRI'],
    'applied_at' => ['type' => 'datetime', 'null' => 'NO'],
];

foreach ($schemaMigrationColumns as $column => $expectation) {
    $stmt = $pdo->prepare('SELECT COLUMN_TYPE, IS_NULLABLE, COLUMN_KEY FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = "schema_migrations" AND COLUMN_NAME = :column');
    $stmt->execute(['column' => $column]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $ok = $row
        && strtolower((string)$row['COLUMN_TYPE']) === $expectation['type']
        && (string)$row['IS_NULLABLE'] === $expectation['null']
        && (!isset($expectation['key']) || (string)$row['COLUMN_KEY'] === $expectation['key']);
    check_line($ok ? 'OK' : 'FAIL', 'schema_migrations.' . $column, $row ? json_encode($row, JSON_UNESCAPED_SLASHES) : 'missing');
}

$requiredTables001 = ['schema_migrations', 'users', 'settings', 'activity_logs', 'login_attempts', 'media'];
$requiredTables002 = ['news', 'news_gallery'];

foreach (array_merge($requiredTables001, $requiredTables002) as $table) {
    $exists = check_query_value($pdo, 'SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table', ['table' => $table]) === 1;
    check_line($exists ? 'OK' : 'FAIL', 'table:' . $table);
}

$requiredNewsColumns = [
    'slug',
    'excerpt',
    'content_long',
    'featured_image',
    'category',
    'published_at',
    'updated_at',
    'views',
    'is_featured',
    'seo_title',
    'seo_description',
];

foreach ($requiredNewsColumns as $column) {
    $exists = check_query_value($pdo, 'SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = "news" AND COLUMN_NAME = :column', ['column' => $column]) === 1;
    check_line($exists ? 'OK' : 'FAIL', 'news.' . $column);
}

$requiredIndexes = [
    'users.users_email_unique',
    'settings.settings_name_unique',
    'activity_logs.activity_logs_user_index',
    'activity_logs.activity_logs_type_index',
    'activity_logs.activity_logs_date_index',
    'login_attempts.login_attempts_ip_created',
    'media.media_subdir_index',
    'media.media_uploaded_by_index',
    'news.news_published_at_index',
    'news.news_category_index',
    'news.news_is_featured_index',
    'news_gallery.news_gallery_news_id_index',
];

foreach ($requiredIndexes as $target) {
    [$table, $index] = explode('.', $target, 2);
    $exists = check_query_value(
        $pdo,
        'SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND INDEX_NAME = :index',
        ['table' => $table, 'index' => $index]
    ) > 0;
    check_line($exists ? 'OK' : 'WARN', 'index:' . $target, $exists ? '' : 'missing or represented by another index');
}

$slugIndexed = check_query_value(
    $pdo,
    'SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = "news" AND COLUMN_NAME = "slug"',
    []
) > 0;
check_line($slugIndexed ? 'OK' : 'FAIL', 'news.slug indexed');

foreach (['001_initial_schema.sql', '002_news_detail_upgrade.sql'] as $version) {
    $applied = check_query_value($pdo, 'SELECT COUNT(*) FROM schema_migrations WHERE version = :version', ['version' => $version]) === 1;
    check_line($applied ? 'OK' : 'WARN', 'metadata:' . $version, $applied ? 'applied' : 'not recorded');
}
