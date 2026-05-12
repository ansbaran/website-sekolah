<?php

declare(strict_types=1);

if (!defined('APP_ENV')) {
    exit('Environment not initialized.');
}

if (!is_dir(LOG_DIR)) {
    @mkdir(LOG_DIR, 0755, true);
}

function format_exception_message(Throwable $exception): string
{
    return sprintf(
        '[%s] %s in %s on line %d\n',
        date('Y-m-d H:i:s'),
        $exception->getMessage(),
        $exception->getFile(),
        $exception->getLine()
    );
}

function log_exception(Throwable $exception): void
{
    if (!is_dir(LOG_DIR)) {
        @mkdir(LOG_DIR, 0755, true);
    }

    $message = format_exception_message($exception);
    $message .= $exception->getTraceAsString() . "\n\n";
    error_log($message, 3, ERROR_LOG_FILE);
}

function exception_handler(Throwable $exception): void
{
    log_exception($exception);
    if (APP_DEBUG) {
        echo '<pre>' . htmlspecialchars($exception->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</pre>';
        return;
    }

    http_response_code(500);
    echo 'Terjadi kesalahan server. Silakan coba lagi nanti.';
    exit;
}

function error_handler(int $severity, string $message, string $file, int $line): bool
{
    if (!(error_reporting() & $severity)) {
        return false;
    }

    $exception = new ErrorException($message, 0, $severity, $file, $line);
    log_exception($exception);

    if (APP_DEBUG) {
        return false; // Default handler will show the error.
    }

    return true; // Prevent PHP default error output in production.
}

function shutdown_handler(): void
{
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE], true)) {
        $exception = new ErrorException($error['message'], 0, $error['type'], $error['file'], $error['line']);
        log_exception($exception);
        if (!headers_sent()) {
            http_response_code(500);
            echo 'Terjadi kesalahan server. Silakan coba lagi nanti.';
        }
    }
}

set_error_handler('error_handler');
set_exception_handler('exception_handler');
register_shutdown_function('shutdown_handler');
