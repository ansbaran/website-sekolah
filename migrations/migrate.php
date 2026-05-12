<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit('This migration script must be run from the command line.');
}

require_once __DIR__ . '/../config/config.php';

try {
    $dsn = sprintf('mysql:host=%s;charset=%s', DB_HOST, DB_CHARSET);
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
} catch (PDOException $exception) {
    echo 'Database connection error: ' . $exception->getMessage() . "\n";
    exit(1);
}

function parse_sql_script(string $sql): array
{
    $commands = array_filter(array_map('trim', explode(';', $sql)));
    return $commands;
}

function applied_versions(PDO $pdo): array
{
    try {
        $stmt = $pdo->query('SELECT version FROM schema_migrations ORDER BY version');
        return $stmt ? $stmt->fetchAll(PDO::FETCH_COLUMN, 0) : [];
    } catch (PDOException $exception) {
        return [];
    }
}

function migration_files(string $folder): array
{
    $files = glob($folder . '/*.sql');
    sort($files, SORT_STRING);
    return $files ?: [];
}

function ensure_schema_migrations(PDO $pdo): void
{
    $sql = 'CREATE DATABASE IF NOT EXISTS `' . DB_NAME . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;';
    $pdo->exec($sql);
    $pdo->exec('USE `' . DB_NAME . '`;');
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS `schema_migrations` (
            `version` VARCHAR(100) NOT NULL,
            `applied_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`version`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;'
    );
}

function run_migration(PDO $pdo, string $filePath): bool
{
    $sql = file_get_contents($filePath);
    if ($sql === false) {
        echo "Gagal membaca: $filePath\n";
        return false;
    }

    $commands = parse_sql_script($sql);
    if (empty($commands)) {
        return false;
    }

    try {
        $pdo->beginTransaction();
        foreach ($commands as $command) {
            $pdo->exec($command);
        }
        $version = basename($filePath);
        $stmt = $pdo->prepare('INSERT INTO schema_migrations (version) VALUES (:version)');
        $stmt->execute(['version' => $version]);
        $pdo->commit();
        echo "Migrasi diterapkan: $version\n";
        return true;
    } catch (PDOException $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo "Gagal menerapkan migrasi $filePath: " . $exception->getMessage() . "\n";
        return false;
    }
}

function print_help(): void
{
    echo "Usage:\n";
    echo "  php migrate.php status   # lihat migrasi yang sudah diterapkan dan yang pending\n";
    echo "  php migrate.php apply    # jalankan migrasi baru\n";
    echo "\nRollback hanya melalui restore backup SQL. Pastikan backup dibuat sebelum update.\n";
}

$command = $argv[1] ?? 'status';
$folder = __DIR__;

ensure_schema_migrations($pdo);
$applied = applied_versions($pdo);
$files = migration_files($folder);

if ($command === 'status') {
    echo "Applied migrations:\n";
    foreach ($applied as $version) {
        echo "  - $version\n";
    }

    echo "\nPending migrations:\n";
    foreach ($files as $file) {
        $version = basename($file);
        if (!in_array($version, $applied, true)) {
            echo "  - $version\n";
        }
    }
    exit;
}

if ($command === 'apply') {
    foreach ($files as $file) {
        $version = basename($file);
        if (in_array($version, $applied, true)) {
            continue;
        }
        if (!run_migration($pdo, $file)) {
            exit(1);
        }
    }
    echo "Semua migrasi telah diterapkan.\n";
    exit;
}

print_help();
