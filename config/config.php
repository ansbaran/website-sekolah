<?php
declare(strict_types=1);

// General application config
define('APP_NAME', 'SD Cahaya Harapan Admin');

$serverHost = $_SERVER['HTTP_HOST'] ?? '';
$isLocalhost = preg_match('/^(localhost|127\.0\.0\.1)(:\d+)?$/', $serverHost) === 1;
$defaultEnv = getenv('APP_ENV') ?: ($isLocalhost ? 'development' : 'production');
define('APP_ENV', $defaultEnv);
define('APP_DEBUG', filter_var(getenv('APP_DEBUG') ?: '', FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? (APP_ENV !== 'production'));

$documentRoot = realpath($_SERVER['DOCUMENT_ROOT'] ?? '') ?: '';
$basePath = dirname(__DIR__);
$basePathReal = realpath($basePath) ?: $basePath;
$baseUrl = '/';
if ($documentRoot && strpos($basePathReal, $documentRoot) === 0) {
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
define('LOG_DIR', BASE_PATH . '/logs');
define('ERROR_LOG_FILE', LOG_DIR . '/php-error.log');
define('BACKUP_DIR', BASE_PATH . '/backups');
define('CACHE_DIR', BASE_PATH . '/cache');
define('MAINTENANCE_TOGGLE_FILE', BASE_PATH . '/maintenance.flag');
define('SESSION_TIMEOUT_SECONDS', 1800);
define('CACHE_DURATION', 300);
define('LOGIN_RATE_LIMIT_ATTEMPTS', 5);
define('LOGIN_RATE_LIMIT_WINDOW', 900); // 15 minutes

define('UPLOAD_DIMENSION_MIN_WIDTH', 120);
define('UPLOAD_DIMENSION_MIN_HEIGHT', 120);

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
define('DB_HOST', 'localhost');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_FALLBACK_PORT', getenv('DB_FALLBACK_PORT') ?: '3307');
define('DB_NAME', getenv('DB_NAME') ?: 'school_admin');
define('DB_FALLBACK_NAME', getenv('DB_FALLBACK_NAME') ?: 'website_sekolah');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Session and auth configuration
ini_set('session.cookie_httponly', '1');
ini_set('session.use_strict_mode', '1');
ini_set('session.cookie_samesite', 'Lax');
if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
    ini_set('session.cookie_secure', '1');
}
session_name('shb_admin_session');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/db.php';
