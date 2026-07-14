<?php
declare(strict_types=1);

function render_database_error(string $message): void
{
    error_log('[database] ' . $message);

    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, 'Database connection tidak tersedia: ' . $message . PHP_EOL);
        exit(1);
    }

    if (!headers_sent()) {
        http_response_code(500);
    }

    $serverHost = $_SERVER['HTTP_HOST'] ?? '';
    $isLocalRequest = preg_match('/^(localhost|127\.0\.0\.1)(:\d+)?$/', $serverHost) === 1;

    if ((defined('APP_DEBUG') && APP_DEBUG) || $isLocalRequest) {
        $safeMessage = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
        echo '<!doctype html><html lang="id"><head><meta charset="UTF-8">';
        echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
        echo '<title>Database Tidak Tersedia</title>';
        echo '<style>body{margin:0;font-family:Arial,sans-serif;background:#f8fafc;color:#1f2937}main{max-width:760px;margin:8vh auto;padding:32px;background:#fff;border-radius:16px;box-shadow:0 18px 45px rgba(15,23,42,.08)}h1{margin:0 0 12px;font-size:28px;color:#111827}p,li{font-size:16px;line-height:1.65}code{background:#eef2ff;padding:2px 6px;border-radius:6px}.detail{margin-top:20px;padding:16px;border-radius:12px;background:#f1f5f9;color:#334155;word-break:break-word}</style>';
        echo '</head><body><main>';
        echo '<h1>Database MySQL belum siap</h1>';
        echo '<p>File perubahan website masih ada, tetapi halaman admin membutuhkan MySQL XAMPP. Jika MySQL macet setelah komputer/aplikasi ditutup, konten dari CMS tidak bisa dimuat dan halaman publik akan memakai data fallback/static.</p>';
        echo '<ol>';
        echo '<li>Buka XAMPP Control Panel sebagai Administrator.</li>';
        echo '<li>Stop MySQL sampai statusnya benar-benar berhenti.</li>';
        echo '<li>Jalankan <code>powershell -ExecutionPolicy Bypass -File scripts\\repair-xampp-mysql.ps1</code> dari folder proyek ini.</li>';
        echo '<li>Start MySQL lagi, lalu refresh halaman admin.</li>';
        echo '</ol>';
        echo '<div class="detail"><strong>Detail teknis:</strong><br>' . $safeMessage . '</div>';
        echo '</main></body></html>';
    } else {
        echo 'Database connection tidak tersedia. Silakan periksa konfigurasi server.';
    }

    exit;
}function quote_database_identifier(string $identifier): string
{
    return '`' . str_replace('`', '``', $identifier) . '`';
}

function mysql_server_has_greeting(string $host, string $port, int $timeout): bool
{
    $socket = @stream_socket_client('tcp://' . $host . ':' . $port, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT);
    if ($socket === false) {
        return false;
    }

    stream_set_timeout($socket, $timeout);
    $header = @fread($socket, 4);
    $meta = stream_get_meta_data($socket);
    fclose($socket);

    return !($meta['timed_out'] ?? false) && is_string($header) && strlen($header) === 4;
}
try {
    $connectTimeout = defined('DB_CONNECT_TIMEOUT') ? (int) DB_CONNECT_TIMEOUT : 3;
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_TIMEOUT => $connectTimeout,
    ];
    if (defined('PDO::MYSQL_ATTR_INIT_COMMAND')) {
        $options[PDO::MYSQL_ATTR_INIT_COMMAND] = 'SET NAMES ' . DB_CHARSET;
    }

    $hostCandidates = APP_ENV === 'production' ? [DB_HOST] : array_unique([DB_HOST === 'localhost' ? '127.0.0.1' : DB_HOST, '127.0.0.1']);
    $portCandidates = APP_ENV === 'production' ? [DB_PORT] : array_unique([DB_PORT, DB_FALLBACK_PORT]);
    $pdo = null;
    $lastException = null;
    $connectionErrors = [];

    foreach ($hostCandidates as $host) {
        foreach ($portCandidates as $port) {
            try {
                if (!mysql_server_has_greeting((string) $host, (string) $port, $connectTimeout)) {
                    throw new RuntimeException(sprintf('MySQL tidak merespons handshake di %s:%s dalam %d detik.', $host, $port, $connectTimeout));
                }

                $dsn = sprintf('mysql:host=%s;port=%s;charset=%s;connect_timeout=%d', $host, $port, DB_CHARSET, $connectTimeout);
                $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
                break 2;
            } catch (Throwable $exception) {
                $lastException = $exception;
                $connectionErrors[] = sprintf('%s:%s - %s', $host, $port, $exception->getMessage());
            }
        }
    }

    if ($pdo === null) {
        $detail = !empty($connectionErrors) ? implode(' | ', $connectionErrors) : ($lastException?->getMessage() ?? '');
        throw new RuntimeException('Tidak dapat terhubung ke MySQL. Pastikan service MySQL XAMPP aktif dan port benar. Percobaan: ' . $detail);
    }

    $candidateDatabases = APP_ENV === 'production' ? [DB_NAME] : array_unique([DB_NAME, DB_FALLBACK_NAME]);
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

    $pdo->exec('USE ' . quote_database_identifier($connectedDatabase));

    $requiredTables = [
        'news',
        'news_gallery',
        'achievements',
        'gallery',
        'slider',
        'users',
        'announcements',
        'settings',
        'media',
        'activity_logs',
        'login_attempts',
        'staff',
        'parent_feedback',
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
    $requiredNewsColumns = [
        'slug',
        'excerpt',
        'content',
        'content_long',
        'featured_image',
        'thumbnail',
        'category',
        'published_at',
        'updated_at',
        'views',
        'is_active',
        'is_featured',
        'seo_title',
        'seo_description',
    ];
    $missingNewsColumns = array_diff($requiredNewsColumns, $newsColumns);
    if (!empty($missingNewsColumns)) {
        throw new RuntimeException('Kolom news yang dibutuhkan tidak ditemukan: ' . implode(', ', $missingNewsColumns));
    }

    $galleryColumns = $pdo->query('DESCRIBE gallery')->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('category', $galleryColumns, true)) {
        throw new RuntimeException('Kolom gallery.category yang dibutuhkan tidak ditemukan.');
    }
} catch (Throwable $exception) {
    render_database_error($exception->getMessage());
}
