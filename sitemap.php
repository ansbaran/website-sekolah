<?php

declare(strict_types=1);

define('SKIP_DB_BOOTSTRAP', true);

ob_start();
require_once __DIR__ . '/includes/functions.php';
if (ob_get_level() > 0) {
    ob_end_clean();
}

ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');

header('Content-Type: application/xml; charset=UTF-8');

const SITEMAP_CANONICAL_BASE_URL = 'https://sdcahayaharapanbekasi.sch.id';

function sitemap_xml_escape(string $value): string
{
    return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function sitemap_valid_lastmod($value): ?string
{
    $value = trim((string) $value);
    if ($value === '' || $value === '0000-00-00' || $value === '0000-00-00 00:00:00') {
        return null;
    }

    $timestamp = strtotime($value);
    if ($timestamp === false) {
        return null;
    }

    return date('Y-m-d', $timestamp);
}

function sitemap_static_lastmod(string $file): ?string
{
    $fullPath = __DIR__ . '/' . ltrim($file, '/');
    if (!is_file($fullPath)) {
        return null;
    }

    return date('Y-m-d', (int) filemtime($fullPath));
}

function sitemap_add_url(array &$urls, string $path, ?string $lastmod, string $changefreq, string $priority): void
{
    $loc = SITEMAP_CANONICAL_BASE_URL . ($path === '/' ? '/' : '/' . ltrim($path, '/'));
    $urls[$loc] = [
        'loc' => $loc,
        'lastmod' => $lastmod,
        'changefreq' => $changefreq,
        'priority' => $priority,
    ];
}

function sitemap_log_error(Throwable $exception): void
{
    if (function_exists('log_exception')) {
        log_exception($exception);
        return;
    }

    error_log('[sitemap] ' . $exception->getMessage());
}

function sitemap_database_candidates(): array
{
    $hosts = defined('APP_ENV') && APP_ENV === 'production'
        ? [DB_HOST]
        : array_unique([DB_HOST === 'localhost' ? '127.0.0.1' : DB_HOST, '127.0.0.1']);
    $ports = defined('APP_ENV') && APP_ENV === 'production'
        ? [DB_PORT]
        : array_unique([DB_PORT, DB_FALLBACK_PORT]);
    $databases = defined('APP_ENV') && APP_ENV === 'production'
        ? [DB_NAME]
        : array_unique([DB_NAME, DB_FALLBACK_NAME]);

    $candidates = [];
    foreach ($hosts as $host) {
        foreach ($ports as $port) {
            foreach ($databases as $database) {
                $candidates[] = [
                    'host' => (string) $host,
                    'port' => (string) $port,
                    'database' => (string) $database,
                ];
            }
        }
    }

    return $candidates;
}

function sitemap_connect_database(): ?PDO
{
    $timeout = defined('DB_CONNECT_TIMEOUT') ? max(1, min(10, (int) DB_CONNECT_TIMEOUT)) : 3;
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_TIMEOUT => $timeout,
    ];

    if (defined('PDO::MYSQL_ATTR_INIT_COMMAND')) {
        $options[PDO::MYSQL_ATTR_INIT_COMMAND] = 'SET NAMES ' . DB_CHARSET;
    }

    $lastException = null;
    foreach (sitemap_database_candidates() as $candidate) {
        try {
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=%s',
                $candidate['host'],
                $candidate['port'],
                $candidate['database'],
                DB_CHARSET
            );

            return new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (Throwable $exception) {
            $lastException = $exception;
        }
    }

    if ($lastException !== null) {
        sitemap_log_error($lastException);
    }

    return null;
}

function sitemap_table_has_columns(PDO $pdo, string $table, array $columns): bool
{
    try {
        $statement = $pdo->prepare(
            'SELECT COLUMN_NAME
             FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table'
        );
        $statement->execute(['table' => $table]);
        $availableColumns = $statement->fetchAll(PDO::FETCH_COLUMN);
        if (!is_array($availableColumns)) {
            return false;
        }

        return empty(array_diff($columns, $availableColumns));
    } catch (Throwable $exception) {
        sitemap_log_error($exception);
        return false;
    }
}

function sitemap_fetch_news_urls(PDO $pdo, array &$urls): int
{
    if (!is_file(__DIR__ . '/berita-detail.php')) {
        return 0;
    }

    if (!sitemap_table_has_columns($pdo, 'news', ['slug', 'updated_at', 'published_at', 'created_at', 'is_active'])) {
        return 0;
    }

    try {
        $statement = $pdo->query(
            'SELECT slug, updated_at, published_at, created_at
             FROM news
             WHERE is_active = 1 AND slug IS NOT NULL AND slug != ""
             ORDER BY published_at DESC, id DESC'
        );

        $count = 0;
        foreach ($statement->fetchAll() as $news) {
            sitemap_add_url(
                $urls,
                '/news/' . rawurlencode((string) $news['slug']),
                sitemap_valid_lastmod($news['updated_at'] ?: $news['published_at'] ?: $news['created_at'] ?: null),
                'monthly',
                '0.6'
            );
            $count++;
        }

        return $count;
    } catch (Throwable $exception) {
        sitemap_log_error($exception);
        return 0;
    }
}

function sitemap_fetch_agenda_urls(PDO $pdo, array &$urls): int
{
    if (!is_file(__DIR__ . '/agenda-detail.php')) {
        return 0;
    }

    if (!sitemap_table_has_columns($pdo, 'agendas', ['slug', 'updated_at', 'created_at', 'is_active'])) {
        return 0;
    }

    try {
        $statement = $pdo->query(
            'SELECT slug, updated_at, created_at
             FROM agendas
             WHERE is_active = 1 AND slug IS NOT NULL AND slug != ""
             ORDER BY event_date ASC, id ASC'
        );

        $count = 0;
        foreach ($statement->fetchAll() as $agenda) {
            sitemap_add_url(
                $urls,
                '/agenda/' . rawurlencode((string) $agenda['slug']),
                sitemap_valid_lastmod($agenda['updated_at'] ?: $agenda['created_at'] ?: null),
                'weekly',
                '0.6'
            );
            $count++;
        }

        return $count;
    } catch (Throwable $exception) {
        sitemap_log_error($exception);
        return 0;
    }
}

function sitemap_fetch_announcement_urls(PDO $pdo, array &$urls): int
{
    if (!is_file(__DIR__ . '/pengumuman-detail.php')) {
        return 0;
    }

    if (!sitemap_table_has_columns($pdo, 'announcements', ['id', 'published_at', 'created_at', 'status'])) {
        return 0;
    }

    $hasUpdatedAt = sitemap_table_has_columns($pdo, 'announcements', ['updated_at']);
    $dateColumns = $hasUpdatedAt ? 'updated_at, published_at, created_at' : 'published_at, created_at';

    try {
        $statement = $pdo->query(
            'SELECT id, ' . $dateColumns . '
             FROM announcements
             WHERE status = 1
             ORDER BY published_at DESC, id DESC'
        );

        $count = 0;
        foreach ($statement->fetchAll() as $announcement) {
            $lastmodSource = $hasUpdatedAt
                ? ($announcement['updated_at'] ?: $announcement['published_at'] ?: $announcement['created_at'] ?: null)
                : ($announcement['published_at'] ?: $announcement['created_at'] ?: null);

            sitemap_add_url(
                $urls,
                '/announcements/' . rawurlencode((string) $announcement['id']),
                sitemap_valid_lastmod($lastmodSource),
                'monthly',
                '0.5'
            );
            $count++;
        }

        return $count;
    } catch (Throwable $exception) {
        sitemap_log_error($exception);
        return 0;
    }
}

$urls = [];
$staticCount = 0;
$publicPages = [
    ['path' => '/', 'file' => 'index.html', 'changefreq' => 'weekly', 'priority' => '1.0'],
    ['path' => '/about', 'file' => 'tentang.html', 'changefreq' => 'monthly', 'priority' => '0.8'],
    ['path' => '/program', 'file' => 'program.html', 'changefreq' => 'monthly', 'priority' => '0.8'],
    ['path' => '/news', 'file' => 'berita.html', 'changefreq' => 'weekly', 'priority' => '0.8'],
    ['path' => '/gallery', 'file' => 'galeri.html', 'changefreq' => 'weekly', 'priority' => '0.7'],
    ['path' => '/announcements', 'file' => 'pengumuman.php', 'changefreq' => 'weekly', 'priority' => '0.7'],
    ['path' => '/agenda', 'file' => 'agenda.php', 'changefreq' => 'weekly', 'priority' => '0.7'],
    ['path' => '/activity', 'file' => 'kegiatan.php', 'changefreq' => 'monthly', 'priority' => '0.7'],
    ['path' => '/extracurricular', 'file' => 'ekstrakurikuler.php', 'changefreq' => 'monthly', 'priority' => '0.7'],
    ['path' => '/achievements', 'file' => 'prestasi.php', 'changefreq' => 'monthly', 'priority' => '0.7'],
];

foreach ($publicPages as $page) {
    $lastmod = sitemap_static_lastmod($page['file']);
    if ($lastmod === null) {
        continue;
    }

    sitemap_add_url($urls, $page['path'], $lastmod, $page['changefreq'], $page['priority']);
    $staticCount++;
}

$pdo = sitemap_connect_database();
if ($pdo instanceof PDO) {
    sitemap_fetch_news_urls($pdo, $urls);
    sitemap_fetch_agenda_urls($pdo, $urls);
    sitemap_fetch_announcement_urls($pdo, $urls);
}

echo '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL;
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . PHP_EOL;
foreach ($urls as $url) {
    echo '  <url>' . PHP_EOL;
    echo '    <loc>' . sitemap_xml_escape($url['loc']) . '</loc>' . PHP_EOL;
    if ($url['lastmod'] !== null) {
        echo '    <lastmod>' . sitemap_xml_escape($url['lastmod']) . '</lastmod>' . PHP_EOL;
    }
    echo '    <changefreq>' . sitemap_xml_escape($url['changefreq']) . '</changefreq>' . PHP_EOL;
    echo '    <priority>' . sitemap_xml_escape($url['priority']) . '</priority>' . PHP_EOL;
    echo '  </url>' . PHP_EOL;
}
echo '</urlset>' . PHP_EOL;
