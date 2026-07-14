<?php

declare(strict_types=1);

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/csrf.php';

function is_logged_in(): bool
{
    return !empty($_SESSION['admin_user']);
}

function current_user(): ?array
{
    return $_SESSION['admin_user'] ?? null;
}

function restore_remembered(): void
{
    global $pdo;
    if (is_logged_in() || empty($_COOKIE['remember_me'])) {
        return;
    }

    [$userId, $token] = explode('|', $_COOKIE['remember_me'] ?? '', 2) + [null, null];
    if (!$userId || !$token || !ctype_digit((string)$userId) || !ctype_xdigit((string)$token) || strlen((string)$token) !== 64) {
        clear_remember_cookie();
        return;
    }

    $statement = $pdo->prepare('SELECT id, name, email, role, remember_token, is_active FROM users WHERE id = :id LIMIT 1');
    $statement->execute(['id' => $userId]);
    $user = $statement->fetch();

    $storedToken = (string)($user['remember_token'] ?? '');
    $tokenHash = hash('sha256', $token);

    if ($user && (int)($user['is_active'] ?? 1) === 1 && $storedToken !== '' && hash_equals($storedToken, $tokenHash)) {
        session_regenerate_id(true);
        $_SESSION['admin_user'] = [
            'id' => $user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'role' => $user['role'],
        ];
        $_SESSION['last_activity'] = time();
    }
}

function clear_remember_cookie(): void
{
    setcookie('remember_me', '', [
        'expires' => time() - 3600,
        'path' => '/',
        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function ensure_current_user_is_active(): bool
{
    global $pdo;

    $user = current_user();
    if (!$user || empty($user['id'])) {
        return false;
    }

    $statement = $pdo->prepare('SELECT id, name, email, role, is_active FROM users WHERE id = :id LIMIT 1');
    $statement->execute(['id' => $user['id']]);
    $freshUser = $statement->fetch();

    if (!$freshUser || (int)($freshUser['is_active'] ?? 0) !== 1) {
        return false;
    }

    $_SESSION['admin_user'] = [
        'id' => $freshUser['id'],
        'name' => $freshUser['name'],
        'email' => $freshUser['email'],
        'role' => $freshUser['role'],
    ];

    return true;
}
function require_login(): void
{
    restore_remembered();

    if (!is_logged_in()) {
        redirect('login.php');
    }

    if (!check_session_activity()) {
        logout_user();
        redirect('login.php?expired=1');
    }

    if (!ensure_current_user_is_active()) {
        logout_user();
        redirect('login.php?inactive=1');
    }

    update_session_activity();
}

function login_user(array $user, bool $remember = false): void
{
    global $pdo;

    $_SESSION['admin_user'] = [
        'id' => $user['id'],
        'name' => $user['name'],
        'email' => $user['email'],
        'role' => $user['role'],
    ];
    $_SESSION['last_activity'] = time();

    if ($remember) {
        $token = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);
        $statement = $pdo->prepare('UPDATE users SET remember_token = :token WHERE id = :id');
        $statement->execute(['token' => $tokenHash, 'id' => $user['id']]);

        setcookie('remember_me', $user['id'] . '|' . $token, [
            'expires' => time() + 60 * 60 * 24 * 30,
            'path' => '/',
            'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    session_regenerate_id(true);
    csrf_refresh();
}

function logout_user(): void
{
    global $pdo;

    $user = current_user();
    if ($user) {
        $statement = $pdo->prepare('UPDATE users SET remember_token = NULL WHERE id = :id');
        $statement->execute(['id' => $user['id']]);
    }

    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }

    clear_remember_cookie();
    session_destroy();
}

function require_role(string ...$roles): void
{
    $user = current_user();
    if (!$user || !in_array($user['role'], $roles, true)) {
        redirect('dashboard.php');
    }
}

function require_permission(string $permission): void
{
    if (!can($permission)) {
        if (!headers_sent()) {
            http_response_code(403);
        }
        flash('error', 'Anda tidak memiliki izin untuk mengakses fitur tersebut.');
        redirect('dashboard.php');
    }
}
