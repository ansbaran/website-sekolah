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
    if (!$userId || !$token) {
        return;
    }

    $statement = $pdo->prepare('SELECT id, name, email, role, remember_token FROM users WHERE id = :id LIMIT 1');
    $statement->execute(['id' => $userId]);
    $user = $statement->fetch();

    if ($user && hash_equals($user['remember_token'] ?? '', $token)) {
        $_SESSION['admin_user'] = [
            'id' => $user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'role' => $user['role'],
        ];
    }
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
        $statement = $pdo->prepare('UPDATE users SET remember_token = :token WHERE id = :id');
        $statement->execute(['token' => $token, 'id' => $user['id']]);

        setcookie('remember_me', $user['id'] . '|' . $token, time() + 60 * 60 * 24 * 30, '/', '', isset($_SERVER['HTTPS']), true);
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

    setcookie('remember_me', '', time() - 3600, '/');
    session_destroy();
}

function require_role(string ...$roles): void
{
    $user = current_user();
    if (!$user || !in_array($user['role'], $roles, true)) {
        redirect('dashboard.php');
    }
}
