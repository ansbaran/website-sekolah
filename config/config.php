<?php
declare(strict_types=1);

// General application config
define('APP_NAME', 'SD Cahaya Harapan Admin');

if (!function_exists('load_application_env_file')) {
    function load_application_env_file(string $path): void
    {
        if (!is_file($path) || !is_readable($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            $separator = strpos($line, '=');
            if ($separator === false) {
                continue;
            }

            $key = trim(substr($line, 0, $separator));
            $value = trim(substr($line, $separator + 1));
            if ($key === '' || preg_match('/^[A-Z0-9_]+$/', $key) !== 1 || getenv($key) !== false) {
                continue;
            }

            if (
                strlen($value) >= 2
                && (($value[0] === '"' && substr($value, -1) === '"') || ($value[0] === "'" && substr($value, -1) === "'"))
            ) {
                $value = substr($value, 1, -1);
            }

            putenv($key . '=' . $value);
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }
}

load_application_env_file(dirname(__DIR__) . '/.env');

$serverHost = $_SERVER['HTTP_HOST'] ?? '';
$isLocalhost = preg_match('/^(localhost|127\.0\.0\.1)(:\d+)?$/', $serverHost) === 1;
$isCli = PHP_SAPI === 'cli';
$defaultEnv = getenv('APP_ENV') ?: (($isLocalhost || $isCli) ? 'development' : 'production');
define('APP_ENV', $defaultEnv);
define('APP_DEBUG', filter_var(getenv('APP_DEBUG') ?: '', FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? (APP_ENV !== 'production'));

if (APP_ENV === 'production') {
    $missingDatabaseEnv = [];
    foreach (['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS'] as $requiredKey) {
        $value = getenv($requiredKey);
        if ($value === false || trim((string)$value) === '') {
            $missingDatabaseEnv[] = $requiredKey;
        }
    }

    if (!empty($missingDatabaseEnv)) {
        ini_set('display_errors', '0');
        ini_set('display_startup_errors', '0');
        if (!headers_sent()) {
            http_response_code(500);
        }
        echo 'Konfigurasi server belum lengkap. Silakan hubungi administrator website.';
        exit;
    }
}

$documentRoot = realpath($_SERVER['DOCUMENT_ROOT'] ?? '') ?: '';
$basePath = dirname(__DIR__);
$basePathReal = realpath($basePath) ?: $basePath;
$baseUrl = '/';
$baseUrlEnv = getenv('BASE_URL');
if ($baseUrlEnv !== false) {
    $baseUrl = trim((string)$baseUrlEnv);
    $baseUrl = $baseUrl === '' || $baseUrl === '/' ? '/' : '/' . trim($baseUrl, '/');
} elseif ($documentRoot && strpos($basePathReal, $documentRoot) === 0) {
    $baseUrl = str_replace('\\', '/', substr($basePathReal, strlen($documentRoot)));
    if ($baseUrl === '') {
        $baseUrl = '/';
    } elseif ($baseUrl[0] !== '/') {
        $baseUrl = '/' . ltrim($baseUrl, '/');
    }
}

define('BASE_PATH', $basePathReal);
define('ROOT_PATH', BASE_PATH);
define('BASE_URL', $baseUrl === '/' ? '' : rtrim($baseUrl, '/'));
define('APP_URL', BASE_URL . '/admin');
define('UPLOAD_BASE', BASE_PATH . '/uploads');
define('UPLOAD_URL', BASE_URL . '/uploads');
$runtimeBase = APP_ENV === 'production' ? dirname(BASE_PATH) : BASE_PATH;
define('LOG_DIR', rtrim((string)(getenv('LOG_DIR') ?: ($runtimeBase . '/logs')), '/\\'));
define('ERROR_LOG_FILE', LOG_DIR . '/php-error.log');
define('BACKUP_DIR', rtrim((string)(getenv('BACKUP_DIR') ?: ($runtimeBase . '/backups')), '/\\'));
define('CACHE_DIR', rtrim((string)(getenv('CACHE_DIR') ?: ($runtimeBase . '/cache')), '/\\'));
define('MAINTENANCE_TOGGLE_FILE', BASE_PATH . '/maintenance.flag');
define('SESSION_TIMEOUT_SECONDS', 1800);
define('CACHE_DURATION', 300);
define('LOGIN_RATE_LIMIT_ATTEMPTS', 5);
define('LOGIN_RATE_LIMIT_WINDOW', 900); // 15 minutes
define('MAIL_FROM_ADDRESS', getenv('MAIL_FROM_ADDRESS') ?: 'no-reply@sd-cahaya-harapan.sch.id');
define('MAIL_FROM_NAME', getenv('MAIL_FROM_NAME') ?: 'SD Cahaya Harapan Bekasi');
define('SMTP_HOST', getenv('SMTP_HOST') ?: '');
$smtpPort = (int) (getenv('SMTP_PORT') ?: 587);
if ($smtpPort < 1 || $smtpPort > 65535) {
    $smtpPort = 587;
}
define('SMTP_PORT', $smtpPort);
define('SMTP_USERNAME', getenv('SMTP_USERNAME') ?: '');
define('SMTP_PASSWORD', getenv('SMTP_PASSWORD') ?: '');
$smtpEncryption = strtolower((string) (getenv('SMTP_ENCRYPTION') ?: 'tls'));
if (!in_array($smtpEncryption, ['tls', 'ssl', 'none'], true)) {
    $smtpEncryption = 'tls';
}
define('SMTP_ENCRYPTION', $smtpEncryption);
$smtpTimeout = (int) (getenv('SMTP_TIMEOUT') ?: 10);
if ($smtpTimeout < 3 || $smtpTimeout > 60) {
    $smtpTimeout = 10;
}
define('SMTP_TIMEOUT', $smtpTimeout);
if (!defined('CSP_NONCE')) {
    define('CSP_NONCE', base64_encode(random_bytes(16)));
}

define('UPLOAD_DIMENSION_MIN_WIDTH', 120);
define('UPLOAD_DIMENSION_MIN_HEIGHT', 120);
define('UPLOAD_OUTPUT_MAX_WIDTH', 1920);
define('UPLOAD_OUTPUT_MAX_HEIGHT', 1920);
define('UPLOAD_GENERAL_MAX_LONG_EDGE', 3000);
define('UPLOAD_GALLERY_MAX_LONG_EDGE', 2400);
define('UPLOAD_SLIDER_MAX_LONG_EDGE', 2560);
define('UPLOAD_MAX_PIXELS', 18000000);
define('MAX_IMAGE_SIZE', 4 * 1024 * 1024);
define('MAX_IMAGE_WIDTH', 4096);
define('MAX_IMAGE_HEIGHT', 4096);
define('PROFILE_AVATAR_MAX_SIZE', 2 * 1024 * 1024);
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/webp']);
define('ALLOWED_IMAGE_EXT', ['jpg', 'jpeg', 'png', 'webp']);
define('ALLOWED_UPLOAD_DIRS', ['news', 'gallery', 'achievements', 'slider', 'staff', 'misc', 'profile']);

if (!is_dir(LOG_DIR)) {
    @mkdir(LOG_DIR, 0755, true);
}

if (!is_dir(BACKUP_DIR)) {
    @mkdir(BACKUP_DIR, 0755, true);
}

if (!is_dir(CACHE_DIR)) {
    @mkdir(CACHE_DIR, 0755, true);
}

ini_set('display_errors', APP_DEBUG ? '1' : '0');
ini_set('display_startup_errors', APP_DEBUG ? '1' : '0');
ini_set('log_errors', '1');
ini_set('error_log', ERROR_LOG_FILE);
ini_set('session.use_only_cookies', '1');
ini_set('session.use_trans_sid', '0');
ini_set('session.cookie_lifetime', '0');
error_reporting(APP_DEBUG ? E_ALL : E_ALL & ~E_DEPRECATED & ~E_STRICT);

// Database connection settings
define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_FALLBACK_PORT', getenv('DB_FALLBACK_PORT') ?: '3307');
define('DB_NAME', getenv('DB_NAME') ?: 'school_admin');
define('DB_FALLBACK_NAME', getenv('DB_FALLBACK_NAME') ?: 'website_sekolah');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_CHARSET', 'utf8mb4');

$dbConnectTimeout = (int) (getenv('DB_CONNECT_TIMEOUT') ?: 3);
if ($dbConnectTimeout < 1 || $dbConnectTimeout > 10) {
    $dbConnectTimeout = 3;
}
define('DB_CONNECT_TIMEOUT', $dbConnectTimeout);

// Session and auth configuration
ini_set('session.cookie_httponly', '1');
ini_set('session.use_strict_mode', '1');
ini_set('session.cookie_samesite', 'Lax');
$isHttps = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on')
    || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
if ($isHttps) {
    ini_set('session.cookie_secure', '1');
}
$sessionPath = CACHE_DIR . '/sessions';
if (!is_dir($sessionPath)) {
    @mkdir($sessionPath, 0755, true);
}
if (is_dir($sessionPath) && is_writable($sessionPath)) {
    session_save_path($sessionPath);
}
session_name('shb_admin_session');
if (!defined('SKIP_DB_BOOTSTRAP') && session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!defined('SKIP_DB_BOOTSTRAP')) {
    require_once __DIR__ . '/db.php';
}
