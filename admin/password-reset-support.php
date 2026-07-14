<?php

declare(strict_types=1);

function ensure_password_resets_table(): void
{
    global $pdo;

    $pdo->exec("CREATE TABLE IF NOT EXISTS `password_resets` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `user_id` INT UNSIGNED NOT NULL,
        `email` VARCHAR(160) NOT NULL,
        `token_hash` CHAR(64) NOT NULL,
        `expires_at` DATETIME NOT NULL,
        `used_at` DATETIME DEFAULT NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `password_resets_email_index` (`email`),
        KEY `password_resets_token_index` (`token_hash`),
        KEY `password_resets_user_index` (`user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

function admin_absolute_url(string $path): string
{
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $isHttps = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on')
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
    $scheme = $isHttps ? 'https' : 'http';
    return $scheme . '://' . $host . rtrim(APP_URL, '/') . '/' . ltrim($path, '/');
}

function create_password_reset_link(array $user): string
{
    global $pdo;
    ensure_password_resets_table();

    $token = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $token);

    $cleanup = $pdo->prepare('UPDATE password_resets SET used_at = NOW() WHERE user_id = :user_id AND used_at IS NULL');
    $cleanup->execute(['user_id' => $user['id']]);

    $stmt = $pdo->prepare('INSERT INTO password_resets (user_id, email, token_hash, expires_at, created_at) VALUES (:user_id, :email, :token_hash, DATE_ADD(NOW(), INTERVAL 30 MINUTE), NOW())');
    $stmt->execute([
        'user_id' => $user['id'],
        'email' => $user['email'],
        'token_hash' => $tokenHash,
    ]);

    return admin_absolute_url('reset-password.php?token=' . urlencode($token));
}

function password_reset_mail_identity(): array
{
    $fromAddress = defined('MAIL_FROM_ADDRESS') ? (string) MAIL_FROM_ADDRESS : 'no-reply@sd-cahaya-harapan.sch.id';
    $fromName = defined('MAIL_FROM_NAME') ? (string) MAIL_FROM_NAME : 'SD Cahaya Harapan Bekasi';
    $safeFromName = str_replace(["\r", "\n"], '', $fromName);
    $safeFromAddress = filter_var($fromAddress, FILTER_VALIDATE_EMAIL) ? $fromAddress : 'no-reply@sd-cahaya-harapan.sch.id';

    return [$safeFromAddress, $safeFromName];
}

function password_reset_headers(string $email, string $subject): array
{
    [$fromAddress, $fromName] = password_reset_mail_identity();

    return [
        'From: ' . $fromName . ' <' . $fromAddress . '>',
        'Reply-To: ' . $fromAddress,
        'To: ' . $email,
        'Subject: ' . $subject,
        'Date: ' . date(DATE_RFC2822),
        'Message-ID: <' . bin2hex(random_bytes(12)) . '@sd-cahaya-harapan>',
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
    ];
}

function smtp_read_response($socket): array
{
    $response = '';

    while (!feof($socket)) {
        $line = fgets($socket, 515);
        if ($line === false) {
            break;
        }

        $response .= $line;
        if (strlen($line) >= 4 && $line[3] === ' ') {
            break;
        }
    }

    $code = (int) substr($response, 0, 3);
    return [$code, $response];
}

function smtp_command($socket, string $command, array $expectedCodes): array
{
    fwrite($socket, $command . "\r\n");
    [$code, $response] = smtp_read_response($socket);

    if (!in_array($code, $expectedCodes, true)) {
        throw new RuntimeException(trim($response) ?: 'SMTP response tidak valid.');
    }

    return [$code, $response];
}

function smtp_dot_stuff(string $message): string
{
    $normalized = str_replace(["\r\n", "\r"], "\n", $message);
    $lines = explode("\n", $normalized);

    foreach ($lines as &$line) {
        if (isset($line[0]) && $line[0] === '.') {
            $line = '.' . $line;
        }
    }
    unset($line);

    return implode("\r\n", $lines);
}

function send_smtp_password_reset_message(string $email, string $subject, string $message): bool
{
    if (!defined('SMTP_HOST') || trim((string) SMTP_HOST) === '') {
        return false;
    }

    [$fromAddress] = password_reset_mail_identity();
    $host = (string) SMTP_HOST;
    $port = defined('SMTP_PORT') ? (int) SMTP_PORT : 587;
    $timeout = defined('SMTP_TIMEOUT') ? (int) SMTP_TIMEOUT : 10;
    $encryption = defined('SMTP_ENCRYPTION') ? (string) SMTP_ENCRYPTION : 'tls';
    $remote = $encryption === 'ssl' ? 'ssl://' . $host . ':' . $port : $host . ':' . $port;

    $socket = @stream_socket_client($remote, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT);
    if ($socket === false) {
        throw new RuntimeException('Tidak dapat terhubung ke SMTP: ' . $errstr);
    }

    stream_set_timeout($socket, $timeout);

    try {
        [$code, $response] = smtp_read_response($socket);
        if ($code !== 220) {
            throw new RuntimeException(trim($response) ?: 'SMTP greeting tidak valid.');
        }

        $serverName = $_SERVER['SERVER_NAME'] ?? 'localhost';
        smtp_command($socket, 'EHLO ' . $serverName, [250]);

        if ($encryption === 'tls') {
            smtp_command($socket, 'STARTTLS', [220]);
            $cryptoEnabled = @stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            if ($cryptoEnabled !== true) {
                throw new RuntimeException('STARTTLS gagal diaktifkan.');
            }
            smtp_command($socket, 'EHLO ' . $serverName, [250]);
        }

        $username = defined('SMTP_USERNAME') ? (string) SMTP_USERNAME : '';
        $password = defined('SMTP_PASSWORD') ? (string) SMTP_PASSWORD : '';
        if ($username !== '' || $password !== '') {
            smtp_command($socket, 'AUTH LOGIN', [334]);
            smtp_command($socket, base64_encode($username), [334]);
            smtp_command($socket, base64_encode($password), [235]);
        }

        smtp_command($socket, 'MAIL FROM:<' . $fromAddress . '>', [250]);
        smtp_command($socket, 'RCPT TO:<' . $email . '>', [250, 251]);
        smtp_command($socket, 'DATA', [354]);

        $payload = implode("\r\n", password_reset_headers($email, $subject)) . "\r\n\r\n" . smtp_dot_stuff($message) . "\r\n.";
        smtp_command($socket, $payload, [250]);
        smtp_command($socket, 'QUIT', [221]);
        fclose($socket);

        return true;
    } catch (Throwable $exception) {
        fclose($socket);
        throw $exception;
    }
}

function send_password_reset_message(string $email, string $resetLink): bool
{
    $subject = 'Reset Password Admin SD Cahaya Harapan';
    $message = "Halo,\n\nKami menerima permintaan reset password untuk akun admin SD Cahaya Harapan Bekasi.\n\nGunakan tautan berikut dalam 30 menit:\n" . $resetLink . "\n\nJika Anda tidak meminta reset password, abaikan pesan ini.\n";
    $sent = false;
    $deliveryError = '';

    try {
        $sent = send_smtp_password_reset_message($email, $subject, $message);
    } catch (Throwable $exception) {
        $deliveryError = $exception->getMessage();
        log_exception($exception);
    }

    if (!$sent && function_exists('mail')) {
        $headers = password_reset_headers($email, $subject);
        $mailHeaders = array_values(array_filter($headers, static fn ($header) => stripos($header, 'To:') !== 0 && stripos($header, 'Subject:') !== 0));
        $sent = @mail($email, $subject, $message, implode("\r\n", $mailHeaders));
    }

    if (!$sent) {
        $logDir = defined('LOG_DIR') ? LOG_DIR : dirname(__DIR__, 2) . '/logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }
        $suffix = $deliveryError !== '' ? ' error=' . $deliveryError : '';
        @file_put_contents($logDir . '/password-reset.log', '[' . date('c') . '] ' . $email . ' ' . $resetLink . $suffix . PHP_EOL, FILE_APPEND);
    }

    return $sent;
}

function find_password_reset_by_token(string $token): ?array
{
    global $pdo;
    ensure_password_resets_table();

    if (!ctype_xdigit($token) || strlen($token) !== 64) {
        return null;
    }

    $stmt = $pdo->prepare('SELECT pr.id AS reset_id, pr.user_id, pr.email, u.name, u.is_active FROM password_resets pr INNER JOIN users u ON u.id = pr.user_id WHERE pr.token_hash = :token_hash AND pr.used_at IS NULL AND pr.expires_at >= NOW() LIMIT 1');
    $stmt->execute(['token_hash' => hash('sha256', $token)]);
    $reset = $stmt->fetch();

    if (!$reset || (int)($reset['is_active'] ?? 0) !== 1) {
        return null;
    }

    return $reset;
}

function complete_password_reset(int $resetId, int $userId, string $password): void
{
    global $pdo;
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $pdo->prepare('UPDATE users SET password = :password, remember_token = NULL WHERE id = :id');
    $stmt->execute(['password' => $passwordHash, 'id' => $userId]);

    $used = $pdo->prepare('UPDATE password_resets SET used_at = NOW() WHERE id = :id');
    $used->execute(['id' => $resetId]);
}