<?php

declare(strict_types=1);

/**
 * Candidate Phase 1 legacy gallery migration runner.
 * Default mode is DRY-RUN. It only writes when both flags are provided:
 *   --apply --confirm=I_UNDERSTAND_PHASE1_WRITES
 *
 * This runner plans deterministic, unique album slugs by reserving existing
 * database slugs and slugs planned earlier in the same run.
 */

const CONFIRM_PHRASE = 'I_UNDERSTAND_PHASE1_WRITES';
const LEGACY_UPLOAD_SUBDIR = 'gallery';

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

function cli_has_flag(array $argv, string $flag): bool
{
    return in_array('--' . $flag, $argv, true);
}

function fail(string $message, int $code = 1): void
{
    fwrite(STDERR, '[FAIL] ' . $message . PHP_EOL);
    exit($code);
}

function line(string $message = ''): void
{
    echo $message . PHP_EOL;
}

function resolve_application_bootstrap(array $argv): string
{
    $root = cli_option($argv, 'root') ?: getenv('WEBSITE_SEKOLAH_ROOT');
    if (!is_string($root) || trim($root) === '') {
        fail('Provide --root=/path/to/repository or WEBSITE_SEKOLAH_ROOT. Connection details are not printed.');
    }

    $root = rtrim(str_replace('\\', '/', $root), '/');
    $bootstrap = $root . '/includes/functions.php';
    if (!is_file($bootstrap)) {
        fail('Application bootstrap not found at provided root.');
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
        fail('Refusing database name outside isolated gallery album binding-test pattern.');
    }

    $pdo->exec('USE `' . str_replace('`', '``', $database) . '`');
    return $database;
}

function table_exists(PDO $pdo, string $table): bool
{
    $sql = 'SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table LIMIT 1';
    $stmt = prepare_checked($pdo, $sql, 'table_exists');
    execute_checked($stmt, 'table_exists', $sql, ['table' => $table]);
    return $stmt->fetchColumn() !== false;
}

function extract_named_placeholders(string $sql): array
{
    preg_match_all('/(?<!:):([a-zA-Z_][a-zA-Z0-9_]*)/', $sql, $matches);
    return $matches[1] ?? [];
}

function assert_statement_bindings(string $label, string $sql, array $params = []): void
{
    $placeholders = extract_named_placeholders($sql);
    $counts = array_count_values($placeholders);
    $duplicates = array_keys(array_filter($counts, static fn(int $count): bool => $count > 1));
    if ($duplicates) {
        fail('Duplicate named placeholder in ' . $label . ': ' . implode(', ', $duplicates));
    }

    if (strpos($sql, '?') !== false && $placeholders) {
        fail('Mixed positional and named placeholders in ' . $label . '.');
    }

    if ($params === []) {
        return;
    }

    $expected = array_values(array_unique($placeholders));
    sort($expected);
    $actual = array_map(static fn($key): string => ltrim((string) $key, ':'), array_keys($params));
    sort($actual);

    $missing = array_values(array_diff($expected, $actual));
    $extra = array_values(array_diff($actual, $expected));
    if ($missing) {
        fail('Missing execute parameter in ' . $label . ': ' . implode(', ', $missing));
    }
    if ($extra) {
        fail('Extra execute parameter in ' . $label . ': ' . implode(', ', $extra));
    }
}

function prepare_checked(PDO $pdo, string $sql, string $label): PDOStatement
{
    assert_statement_bindings($label, $sql);
    return $pdo->prepare($sql);
}

function execute_checked(PDOStatement $stmt, string $label, string $sql, array $params): bool
{
    assert_statement_bindings($label, $sql, $params);
    return $stmt->execute($params);
}
function get_legacy_rows(PDO $pdo): array
{
    $stmt = $pdo->query('SELECT id, title, category, filename, created_at FROM gallery ORDER BY id ASC');
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function sanitize_slug(string $slug): string
{
    $slug = strtolower(trim($slug));
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';
    $slug = trim($slug, '-');
    return substr($slug, 0, 220);
}

function slug_base(string $title, int $legacyId): string
{
    if (function_exists('generate_slug')) {
        $slug = sanitize_slug((string) generate_slug($title));
    } else {
        $slug = sanitize_slug($title);
    }

    return $slug !== '' ? substr($slug, 0, 180) : 'gallery-' . $legacyId;
}

function existing_slug_map(PDO $pdo): array
{
    $slugs = [];
    $stmt = $pdo->query('SELECT slug FROM gallery_albums ORDER BY slug ASC');
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $slug) {
        $slug = (string) $slug;
        if ($slug !== '') {
            $slugs[$slug] = true;
        }
    }

    return $slugs;
}

function reserve_unique_slug(string $base, int $legacyId, array &$reserved): array
{
    $base = sanitize_slug($base) ?: 'gallery-' . $legacyId;
    if (!isset($reserved[$base])) {
        $reserved[$base] = true;
        return [$base, false];
    }

    $collision = true;
    $firstSuffix = '-' . $legacyId;
    $candidate = substr($base, 0, 220 - strlen($firstSuffix)) . $firstSuffix;
    if (!isset($reserved[$candidate])) {
        $reserved[$candidate] = true;
        return [$candidate, $collision];
    }

    $counter = 2;
    while (true) {
        $suffix = '-' . $legacyId . '-' . $counter;
        $candidate = substr($base, 0, 220 - strlen($suffix)) . $suffix;
        if (!isset($reserved[$candidate])) {
            $reserved[$candidate] = true;
            return [$candidate, $collision];
        }
        $counter++;
    }
}

function existing_mapping(PDO $pdo, int $legacyId): ?array
{
    $sql = 'SELECT a.id AS album_id, p.id AS photo_id, a.slug
         FROM gallery_photos p
         INNER JOIN gallery_albums a ON a.id = p.album_id
         WHERE p.legacy_gallery_id = :legacy_id
         LIMIT 1';
    $stmt = prepare_checked($pdo, $sql, 'existing_mapping');
    execute_checked($stmt, 'existing_mapping', $sql, ['legacy_id' => $legacyId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function physical_file_exists(string $filename): bool
{
    if (!defined('UPLOAD_BASE')) {
        return false;
    }

    $safeName = basename(str_replace('\\', '/', $filename));
    return $safeName !== '' && is_file(rtrim(UPLOAD_BASE, '/\\') . '/' . LEGACY_UPLOAD_SUBDIR . '/' . $safeName);
}

function find_orphan_gallery_files(array $legacyRows): array
{
    if (!defined('UPLOAD_BASE')) {
        return [];
    }

    $galleryDir = rtrim(UPLOAD_BASE, '/\\') . '/' . LEGACY_UPLOAD_SUBDIR;
    if (!is_dir($galleryDir)) {
        return [];
    }

    $referenced = [];
    foreach ($legacyRows as $row) {
        $name = basename(str_replace('\\', '/', (string) $row['filename']));
        if ($name === '') {
            continue;
        }
        $referenced[$name] = true;
        $base = preg_replace('/\.[^.]+$/', '', $name);
        if (is_string($base) && $base !== '' && $base !== $name) {
            $referenced[$base . '.webp'] = true;
        }
    }

    $orphans = [];
    foreach (scandir($galleryDir) ?: [] as $name) {
        if ($name === '.' || $name === '..' || is_dir($galleryDir . '/' . $name)) {
            continue;
        }
        if (!isset($referenced[$name])) {
            $orphans[] = $name;
        }
    }

    sort($orphans);
    return $orphans;
}

function validate_prerequisites(PDO $pdo): void
{
    foreach (['gallery', 'gallery_albums', 'gallery_photos'] as $table) {
        if (!table_exists($pdo, $table)) {
            fail('Required table is missing: ' . $table . '. Run and verify the schema candidate on a copied local database first.');
        }
    }
}

function duplicate_final_slugs(array $planned): array
{
    $seen = [];
    $duplicates = [];
    foreach ($planned as $item) {
        $slug = (string) $item['slug'];
        if (isset($seen[$slug])) {
            $duplicates[$slug] = true;
        }
        $seen[$slug] = true;
    }
    return array_keys($duplicates);
}

function planning_fingerprint(array $planned): string
{
    $parts = [];
    foreach ($planned as $item) {
        $parts[] = $item['legacy_id'] . '|' . $item['slug'] . '|' . $item['image_path'];
    }
    return hash('sha256', implode("\n", $parts));
}

$apply = cli_has_flag($argv, 'apply');
$confirm = cli_option($argv, 'confirm');

if ($apply && $confirm !== CONFIRM_PHRASE) {
    fail('Apply mode requires --confirm=' . CONFIRM_PHRASE . '. Dry-run remains the default.');
}

if (!$apply && $confirm !== null) {
    fail('Confirmation phrase is only accepted together with --apply. Dry-run remains the default.');
}

$bootstrap = resolve_application_bootstrap($argv);
require_once $bootstrap;

if (!isset($pdo) || !$pdo instanceof PDO) {
    fail('Application PDO connection is not available.');
}

use_database_if_requested($pdo, $argv);
validate_prerequisites($pdo);

$legacyRows = get_legacy_rows($pdo);
$missingFiles = [];
$planned = [];
$skipped = [];
$reservedSlugs = existing_slug_map($pdo);

foreach ($legacyRows as $row) {
    $legacyId = (int) $row['id'];
    $filename = basename(str_replace('\\', '/', (string) $row['filename']));
    $title = trim((string) $row['title']);
    $category = trim((string) $row['category']);
    $baseSlug = slug_base($title, $legacyId);

    if ($filename === '' || !physical_file_exists($filename)) {
        $missingFiles[] = ['id' => $legacyId, 'filename' => $filename];
        continue;
    }

    $existing = existing_mapping($pdo, $legacyId);
    if ($existing !== null) {
        $skipped[] = [
            'legacy_id' => $legacyId,
            'title' => $title !== '' ? $title : 'Galeri ' . $legacyId,
            'base_slug' => $baseSlug,
            'slug' => (string) $existing['slug'],
            'collision' => false,
            'album_action' => 'skip_existing',
            'photo_action' => 'skip_existing',
            'image_path' => $filename,
        ];
        continue;
    }

    [$slug, $collision] = reserve_unique_slug($baseSlug, $legacyId, $reservedSlugs);
    $createdAt = (string) $row['created_at'];

    $planned[] = [
        'legacy_id' => $legacyId,
        'title' => $title !== '' ? $title : 'Galeri ' . $legacyId,
        'base_slug' => $baseSlug,
        'slug' => $slug,
        'collision' => $collision,
        'category' => $category !== '' ? $category : 'Kegiatan',
        'image_path' => $filename,
        'sort_order' => $legacyId,
        'created_at' => $createdAt,
        'album_action' => 'create',
        'photo_action' => 'create',
        'warning' => $collision ? 'slug collision resolved deterministically' : '',
    ];
}

$duplicateSlugs = duplicate_final_slugs($planned);

line('[MODE] ' . ($apply ? 'APPLY' : 'DRY-RUN'));
line('[LEGACY_ROWS] ' . count($legacyRows));
line('[PLANNED_NEW_MAPPINGS] ' . count($planned));
line('[PLANNED_ALBUMS] ' . count($planned));
line('[PLANNED_PHOTOS] ' . count($planned));
line('[SKIPPED_EXISTING_MAPPINGS] ' . count($skipped));
line('[MISSING_FILES] ' . count($missingFiles));
line('[DUPLICATE_FINAL_SLUGS] ' . count($duplicateSlugs));
line('[PLANNING_FINGERPRINT] ' . planning_fingerprint($planned));

$orphans = find_orphan_gallery_files($legacyRows);
line('[ORPHAN_FILES_REVIEW_ONLY] ' . count($orphans));
foreach (array_slice($orphans, 0, 20) as $orphan) {
    line('  - uploads/gallery/' . $orphan);
}

if ($missingFiles) {
    foreach ($missingFiles as $missing) {
        line('  missing legacy id ' . $missing['id'] . ': uploads/gallery/' . $missing['filename']);
    }
    fail('Missing gallery files detected. No migration should be applied until paths are reviewed.');
}

if ($duplicateSlugs) {
    foreach ($duplicateSlugs as $slug) {
        line('  duplicate final slug: ' . $slug);
    }
    fail('Duplicate final slug detected in planning. No migration should be applied.');
}

line('[PLANNING]');
foreach ($planned as $item) {
    line(sprintf(
        'LEGACY %-3d | title="%s" | category="%s" | base=%s | final=%s | collision=%s | album=%s | photo=%s | image=uploads/gallery/%s%s',
        (int) $item['legacy_id'],
        str_replace('"', '\\"', (string) $item['title']),
        str_replace('"', '\\"', (string) $item['category']),
        (string) $item['base_slug'],
        (string) $item['slug'],
        $item['collision'] ? 'yes' : 'no',
        (string) $item['album_action'],
        (string) $item['photo_action'],
        (string) $item['image_path'],
        $item['warning'] !== '' ? ' | warning=' . $item['warning'] : ''
    ));
}

if ($skipped) {
    line('[SKIPPED]');
    foreach ($skipped as $item) {
        line(sprintf(
            'LEGACY %-3d | title="%s" | base=%s | final=%s | collision=no | album=%s | photo=%s | image=uploads/gallery/%s',
            (int) $item['legacy_id'],
            str_replace('"', '\\"', (string) $item['title']),
            (string) $item['base_slug'],
            (string) $item['slug'],
            (string) $item['album_action'],
            (string) $item['photo_action'],
            (string) $item['image_path']
        ));
    }
}

if (!$apply) {
    line('[RESULT] Dry-run complete. No database rows were changed.');
    exit(0);
}

line('[WARNING] Apply mode will create album/photo rows in new Phase 1 tables only. Legacy `gallery` and files remain untouched.');

try {
    $pdo->beginTransaction();

    $insertAlbumSql = 'INSERT INTO gallery_albums (title, slug, category, description, event_date, status, sort_order, cover_photo_id, created_by, created_at, updated_at)
         VALUES (:title, :slug, :category, NULL, NULL, 1, :sort_order, NULL, NULL, :created_at_insert, :updated_at_insert)';
    $insertAlbum = prepare_checked($pdo, $insertAlbumSql, 'insert gallery_albums');
    $insertPhotoSql = 'INSERT INTO gallery_photos (album_id, legacy_gallery_id, image_path, caption, alt_text, sort_order, created_at, updated_at)
         VALUES (:album_id, :legacy_gallery_id, :image_path, NULL, :alt_text, 0, :created_at_insert, :updated_at_insert)';
    $insertPhoto = prepare_checked($pdo, $insertPhotoSql, 'insert gallery_photos');
    $updateCoverSql = 'UPDATE gallery_albums SET cover_photo_id = :photo_id WHERE id = :album_id';
    $updateCover = prepare_checked($pdo, $updateCoverSql, 'update gallery_albums cover');

    $created = 0;
    foreach ($planned as $item) {
        if (existing_mapping($pdo, (int) $item['legacy_id']) !== null) {
            continue;
        }

        execute_checked($insertAlbum, 'insert gallery_albums', $insertAlbumSql, [
            'title' => $item['title'],
            'slug' => $item['slug'],
            'category' => $item['category'],
            'sort_order' => $item['sort_order'],
            'created_at_insert' => $item['created_at'],
            'updated_at_insert' => $item['created_at'],
        ]);
        $albumId = (int) $pdo->lastInsertId();

        execute_checked($insertPhoto, 'insert gallery_photos', $insertPhotoSql, [
            'album_id' => $albumId,
            'legacy_gallery_id' => $item['legacy_id'],
            'image_path' => $item['image_path'],
            'alt_text' => $item['title'],
            'created_at_insert' => $item['created_at'],
            'updated_at_insert' => $item['created_at'],
        ]);
        $photoId = (int) $pdo->lastInsertId();

        execute_checked($updateCover, 'update gallery_albums cover', $updateCoverSql, [
            'photo_id' => $photoId,
            'album_id' => $albumId,
        ]);
        $created++;
    }

    $pdo->commit();
    line('[RESULT] Apply complete. Created mappings: ' . $created);
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fail('Apply failed and transaction was rolled back: ' . $exception->getMessage());
}
