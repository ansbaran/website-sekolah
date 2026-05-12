<?php
declare(strict_types=1);

// General application config
define('APP_NAME', 'SD Cahaya Harapan Admin');
define('APP_ENV', getenv('APP_ENV') ?: 'production');
define('APP_DEBUG', filter_var(getenv('APP_DEBUG') ?: '', FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? (APP_ENV !== 'production'));
define('APP_URL', '/admin');
define('BASE_PATH', dirname(__DIR__));
define('UPLOAD_BASE', BASE_PATH . '/uploads');
define('UPLOAD_URL', '/uploads');
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
define('DB_NAME', 'school_admin');
define('DB_USER', 'dbuser');
define('DB_PASS', 'dbpass');
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
