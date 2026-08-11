<?php

declare(strict_types=1);

/**
 * Candidate Phase 1 gallery album validator.
 * Read-only by design. Modes:
 *   --mode=pre-schema
 *   --mode=schema-ready
 *   --mode=post-migration
 *   --mode=auto (default)
 *
 * Exit codes:
 *   0 PASS
 *   1 WARNING / manual attention
 *   2 FAIL
 *   3 invalid argument / blocked
 */

const LEGACY_UPLOAD_SUBDIR = 'gallery';
const EXIT_PASS = 0;
const EXIT_WARNING = 1;
const EXIT_FAIL = 2;
const EXIT_INVALID = 3;

function cli_option(array $argv, string $name): ?string
{
    $prefix = '--' . $name . '=';
    foreach ($argv as $arg) {
        if (strpos($arg, $prefix) === 0) {
            return substr($arg, strlen($prefix));
        }
    }

    return null;
}

function fail_now(string $message, int $code = EXIT_FAIL): void
{
    fwrite(STDERR, '[FAIL] ' . $message . PHP_EOL);
    exit($code);
}

function resolve_application_bootstrap(array $argv): string
{
    foreach ($argv as $arg) {
        if (in_array($arg, ['--apply', '--write', '--force'], true)) {
            fail_now('This validator is read-only and does not accept write-like flags.', EXIT_INVALID);
        }
    }

    $root = cli_option($argv, 'root') ?: getenv('WEBSITE_SEKOLAH_ROOT');
    if (!is_string($root) || trim($root) === '') {
        fail_now('Provide --root=/path/to/repository or WEBSITE_SEKOLAH_ROOT. Connection details are not printed.', EXIT_INVALID);
    }

    $root = rtrim(strtr($root, '\\', '/'), '/');
    $bootstrap = $root . '/includes/functions.php';
    if (!is_file($bootstrap)) {
        fail_now('Application bootstrap not found at provided root.', EXIT_INVALID);
    }

    return $bootstrap;
}

function use_database_if_requested(PDO $pdo, array $argv): ?string
{
    $database = cli_option($argv, 'database');
    if ($database === null || trim($database) === '') {
        return null;
    }

    if (!preg_match('/^website_sekolah_gallery_album_binding_test_[a-z0-9_]+$/', $database)) {
        fail_now('Refusing database name outside isolated gallery album binding-test pattern.', EXIT_INVALID);
    }

    $pdo->exec('USE `' . str_replace('`', '``', $database) . '`');
    return $database;
}

function table_exists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare('SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table LIMIT 1');
    $stmt->execute(['table' => $table]);
    return $stmt->fetchColumn() !== false;
}

function scalar(PDO $pdo, string $sql): int
{
    return (int) $pdo->query($sql)->fetchColumn();
}

function value(PDO $pdo, string $sql): string
{
    $result = $pdo->query($sql)->fetchColumn();
    return $result === false ? '' : (string) $result;
}

function rows(PDO $pdo, string $sql): array
{
    return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
}

function gallery_file_exists(string $filename): bool
{
    if (!defined('UPLOAD_BASE')) {
        return false;
    }
    $safeName = basename(strtr($filename, '\\', '/'));
    return $safeName !== '' && is_file(rtrim(UPLOAD_BASE, '/\\') . '/' . LEGACY_UPLOAD_SUBDIR . '/' . $safeName);
}

function print_section(string $title, array $data): void
{
    echo '[' . $title . ']' . PHP_EOL;
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
}

function legacy_snapshot(PDO $pdo): array
{
    if (!table_exists($pdo, 'gallery')) {
        return ['exists' => false];
    }

    $legacyRows = rows($pdo, 'SELECT id, title, category, filename, created_at FROM gallery ORDER BY id ASC');
    $missingFiles = [];
    foreach ($legacyRows as $row) {
        if (!gallery_file_exists((string) $row['filename'])) {
            $missingFiles[] = ['id' => (int) $row['id'], 'filename' => (string) $row['filename']];
        }
    }

    return [
        'exists' => true,
        'legacy_count' => count($legacyRows),
        'logical_checksum' => value($pdo, "SELECT SHA2(COALESCE(GROUP_CONCAT(CONCAT_WS('|', id, title, category, filename, created_at) ORDER BY id SEPARATOR '~'), ''), 256) FROM gallery"),
        'empty_image_path' => scalar($pdo, "SELECT COUNT(*) FROM gallery WHERE filename IS NULL OR filename = ''"),
        'missing_files' => $missingFiles,
        'duplicate_image_path' => rows($pdo, 'SELECT filename, COUNT(*) AS total FROM gallery GROUP BY filename HAVING COUNT(*) > 1 ORDER BY total DESC, filename ASC'),
        'duplicate_title' => rows($pdo, 'SELECT title, COUNT(*) AS total FROM gallery GROUP BY title HAVING COUNT(*) > 1 ORDER BY total DESC, title ASC'),
        'category_distribution' => rows($pdo, 'SELECT category, COUNT(*) AS total FROM gallery GROUP BY category ORDER BY total DESC, category ASC'),
        'schema_columns' => rows($pdo, 'SHOW COLUMNS FROM gallery'),
        'indexes' => rows($pdo, 'SHOW INDEX FROM gallery'),
    ];
}

function schema_snapshot(PDO $pdo): array
{
    $hasAlbums = table_exists($pdo, 'gallery_albums');
    $hasPhotos = table_exists($pdo, 'gallery_photos');
    $snapshot = [
        'gallery_albums_exists' => $hasAlbums,
        'gallery_photos_exists' => $hasPhotos,
        'album_count' => $hasAlbums ? scalar($pdo, 'SELECT COUNT(*) FROM gallery_albums') : null,
        'photo_count' => $hasPhotos ? scalar($pdo, 'SELECT COUNT(*) FROM gallery_photos') : null,
    ];

    if ($hasAlbums) {
        $snapshot['album_indexes'] = rows($pdo, 'SHOW INDEX FROM gallery_albums');
    }
    if ($hasPhotos) {
        $snapshot['photo_indexes'] = rows($pdo, 'SHOW INDEX FROM gallery_photos');
    }
    if ($hasAlbums || $hasPhotos) {
        $snapshot['foreign_keys'] = rows($pdo, "SELECT CONSTRAINT_NAME, TABLE_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND REFERENCED_TABLE_NAME IS NOT NULL AND TABLE_NAME IN ('gallery_albums','gallery_photos') ORDER BY TABLE_NAME, CONSTRAINT_NAME, ORDINAL_POSITION");
    }

    return $snapshot;
}

function determine_effective_mode(PDO $pdo, string $requested): string
{
    $allowed = ['pre-schema', 'schema-ready', 'post-migration', 'auto'];
    if (!in_array($requested, $allowed, true)) {
        fail_now('Invalid mode. Use pre-schema, schema-ready, post-migration, or auto.', EXIT_INVALID);
    }
    if ($requested !== 'auto') {
        return $requested;
    }

    $hasAlbums = table_exists($pdo, 'gallery_albums');
    $hasPhotos = table_exists($pdo, 'gallery_photos');
    if (!$hasAlbums && !$hasPhotos) {
        return 'pre-schema';
    }
    if ($hasAlbums && $hasPhotos) {
        $albumCount = scalar($pdo, 'SELECT COUNT(*) FROM gallery_albums');
        $photoCount = scalar($pdo, 'SELECT COUNT(*) FROM gallery_photos');
        return ($albumCount === 0 && $photoCount === 0) ? 'schema-ready' : 'post-migration';
    }

    return 'post-migration';
}

function post_migration_snapshot(PDO $pdo): array
{
    $legacyCount = scalar($pdo, 'SELECT COUNT(*) FROM gallery');
    $migratedPhotos = scalar($pdo, 'SELECT COUNT(*) FROM gallery_photos WHERE legacy_gallery_id IS NOT NULL');
    $post = [
        'album_count' => scalar($pdo, 'SELECT COUNT(*) FROM gallery_albums'),
        'photo_count' => scalar($pdo, 'SELECT COUNT(*) FROM gallery_photos'),
        'legacy_count' => $legacyCount,
        'legacy_checksum' => value($pdo, "SELECT SHA2(COALESCE(GROUP_CONCAT(CONCAT_WS('|', id, title, category, filename, created_at) ORDER BY id SEPARATOR '~'), ''), 256) FROM gallery"),
        'migrated_photo_count' => $migratedPhotos,
        'legacy_without_mapping' => rows($pdo, 'SELECT g.id, g.title, g.category, g.filename FROM gallery g LEFT JOIN gallery_photos p ON p.legacy_gallery_id = g.id WHERE p.id IS NULL ORDER BY g.id ASC'),
        'duplicate_legacy_mapping' => rows($pdo, 'SELECT legacy_gallery_id, COUNT(*) AS total FROM gallery_photos WHERE legacy_gallery_id IS NOT NULL GROUP BY legacy_gallery_id HAVING COUNT(*) > 1'),
        'photo_without_album' => rows($pdo, 'SELECT p.id, p.album_id FROM gallery_photos p LEFT JOIN gallery_albums a ON a.id = p.album_id WHERE a.id IS NULL'),
        'album_without_photo' => rows($pdo, 'SELECT a.id, a.slug, a.title FROM gallery_albums a LEFT JOIN gallery_photos p ON p.album_id = a.id GROUP BY a.id, a.slug, a.title HAVING COUNT(p.id) = 0'),
        'cover_not_in_album' => rows($pdo, 'SELECT a.id, a.slug, a.cover_photo_id FROM gallery_albums a LEFT JOIN gallery_photos p ON p.id = a.cover_photo_id AND p.album_id = a.id WHERE a.cover_photo_id IS NOT NULL AND p.id IS NULL'),
        'image_path_mismatch' => rows($pdo, 'SELECT g.id AS legacy_id, g.filename AS legacy_filename, p.image_path FROM gallery g INNER JOIN gallery_photos p ON p.legacy_gallery_id = g.id WHERE p.image_path <> g.filename'),
        'duplicate_slug' => rows($pdo, 'SELECT slug, COUNT(*) AS total FROM gallery_albums GROUP BY slug HAVING COUNT(*) > 1'),
        'legacy_schema_columns' => rows($pdo, 'SHOW COLUMNS FROM gallery'),
    ];

    $photoMissingFiles = [];
    foreach (rows($pdo, 'SELECT id, image_path FROM gallery_photos ORDER BY id ASC') as $photo) {
        if (!gallery_file_exists((string) $photo['image_path'])) {
            $photoMissingFiles[] = ['id' => (int) $photo['id'], 'image_path' => (string) $photo['image_path']];
        }
    }
    $post['photo_missing_files'] = $photoMissingFiles;

    return $post;
}

$requestedMode = cli_option($argv, 'mode') ?: 'auto';
$bootstrap = resolve_application_bootstrap($argv);
require_once $bootstrap;

if (!isset($pdo) || !$pdo instanceof PDO) {
    fail_now('Application PDO connection is not available.', EXIT_FAIL);
}

use_database_if_requested($pdo, $argv);
$effectiveMode = determine_effective_mode($pdo, $requestedMode);
$warnings = [];
$fails = [];
$phaseStatus = '';

$legacy = legacy_snapshot($pdo);
$schema = schema_snapshot($pdo);

print_section('VALIDATOR_MODE', [
    'requested_mode' => $requestedMode,
    'effective_mode' => $effectiveMode,
    'exit_codes' => [
        'PASS' => EXIT_PASS,
        'WARNING' => EXIT_WARNING,
        'FAIL' => EXIT_FAIL,
        'INVALID_ARGUMENT' => EXIT_INVALID,
    ],
]);
print_section('LEGACY', $legacy);
print_section('SCHEMA', $schema);

if (empty($legacy['exists'])) {
    $fails[] = 'Legacy table gallery is missing.';
} elseif (!empty($legacy['missing_files'])) {
    $fails[] = 'Some legacy gallery files are missing.';
}

if ($effectiveMode === 'pre-schema') {
    $phaseStatus = 'PRE_SCHEMA';
    if (!empty($schema['gallery_albums_exists']) || !empty($schema['gallery_photos_exists'])) {
        $fails[] = 'Pre-schema mode expected both Phase 1 tables to be absent.';
    }
} elseif ($effectiveMode === 'schema-ready') {
    $phaseStatus = 'SCHEMA_READY_NOT_MIGRATED';
    if (empty($schema['gallery_albums_exists']) || empty($schema['gallery_photos_exists'])) {
        $fails[] = 'Schema-ready mode expected gallery_albums and gallery_photos to exist.';
    }
    if (($schema['album_count'] ?? null) !== 0 || ($schema['photo_count'] ?? null) !== 0) {
        $fails[] = 'Schema-ready mode expected album/photo counts to be zero.';
    }
} elseif ($effectiveMode === 'post-migration') {
    $phaseStatus = 'POST_MIGRATION';
    if (empty($schema['gallery_albums_exists']) || empty($schema['gallery_photos_exists'])) {
        $fails[] = 'Post-migration mode expected gallery_albums and gallery_photos to exist.';
    } else {
        $post = post_migration_snapshot($pdo);
        print_section('POST_MIGRATION', $post);
        foreach (['legacy_without_mapping', 'duplicate_legacy_mapping', 'photo_without_album', 'album_without_photo', 'cover_not_in_album', 'image_path_mismatch', 'duplicate_slug', 'photo_missing_files'] as $key) {
            if (!empty($post[$key])) {
                $fails[] = 'Post-migration validation failed: ' . $key;
            }
        }
        if ((int) $post['migrated_photo_count'] !== (int) $post['legacy_count']) {
            $fails[] = 'Migrated legacy photo count does not match legacy gallery count.';
        }
        if ((int) $post['album_count'] !== (int) $post['legacy_count']) {
            $fails[] = 'Migrated album count does not match conservative one-album-per-legacy-row plan.';
        }
    }
}

$status = 'PASS';
$exitCode = EXIT_PASS;
if ($fails) {
    $status = 'FAIL';
    $exitCode = EXIT_FAIL;
} elseif ($warnings) {
    $status = 'WARNING';
    $exitCode = EXIT_WARNING;
}

print_section('SUMMARY', [
    'status' => $status,
    'phase_status' => $phaseStatus,
    'warnings' => $warnings,
    'fails' => $fails,
]);

exit($exitCode);
