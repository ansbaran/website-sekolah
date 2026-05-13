<?php
declare(strict_types=1);

function render_database_error(string $message): void
{
    if (!headers_sent()) {
        http_response_code(500);
    }

    if (defined('APP_ENV') && APP_ENV === 'development') {
        echo '<pre>' . htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</pre>';
    } else {
        echo 'Database connection tidak tersedia. Silakan periksa konfigurasi server.';
    }

    exit;
}

try {
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_TIMEOUT => 5,
    ];

    $hostCandidates = array_unique([DB_HOST, '127.0.0.1']);
    $portCandidates = array_unique([DB_PORT, DB_FALLBACK_PORT]);
    $pdo = null;
    $lastException = null;

    foreach ($hostCandidates as $host) {
        foreach ($portCandidates as $port) {
            try {
                $dsn = sprintf('mysql:host=%s;port=%s;charset=%s', $host, $port, DB_CHARSET);
                $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
                break 2;
            } catch (Throwable $exception) {
                $lastException = $exception;
            }
        }
    }

    if ($pdo === null) {
        throw new RuntimeException('Tidak dapat terhubung ke MySQL. Pastikan service MySQL XAMPP aktif dan port benar. ' . ($lastException?->getMessage() ?? '')); 
    }

    $candidateDatabases = array_unique([DB_NAME, DB_FALLBACK_NAME]);
    $connectedDatabase = null;

    foreach ($candidateDatabases as $candidateDatabase) {
        $databaseExists = $pdo->prepare('SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = :database');
        $databaseExists->execute(['database' => $candidateDatabase]);
        if ($databaseExists->fetchColumn() !== false) {
            $connectedDatabase = $candidateDatabase;
            break;
        }
    }

    if ($connectedDatabase === null) {
        throw new RuntimeException(sprintf('Database tidak ditemukan di host %s. Dicari: %s', DB_HOST, implode(', ', $candidateDatabases)));
    }

    $pdo->exec(sprintf('USE `%s`', $connectedDatabase));

    $requiredTables = [
        'news', 'achievements', 'gallery', 'slider', 'users', 'announcements', 'settings', 'media', 'activity_logs'
    ];

    $missingTables = [];
    foreach ($requiredTables as $table) {
        $stmt = $pdo->prepare('SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table LIMIT 1');
        $stmt->execute(['table' => $table]);
        if ($stmt->fetchColumn() === false) {
            $missingTables[] = $table;
        }
    }

    if (!empty($missingTables)) {
        throw new RuntimeException('Tabel database tidak lengkap: ' . implode(', ', $missingTables));
    }

    $newsColumns = $pdo->query('DESCRIBE news')->fetchAll(PDO::FETCH_COLUMN);
    $missingNewsColumns = array_diff(['slug', 'featured_image'], $newsColumns);
    if (!empty($missingNewsColumns)) {
        throw new RuntimeException('Kolom news yang dibutuhkan tidak ditemukan: ' . implode(', ', $missingNewsColumns));
    }
} catch (Throwable $exception) {
    render_database_error($exception->getMessage());
}
