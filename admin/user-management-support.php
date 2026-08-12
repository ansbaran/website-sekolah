<?php

declare(strict_types=1);

if (!defined('ADMIN_CONTEXT')) {
    exit('Direct access not allowed.');
}

function admin_user_allowed_roles(): array
{
    return array_keys(admin_role_options());
}

function admin_user_normalize_role(string $role): string
{
    return in_array($role, admin_user_allowed_roles(), true) ? $role : 'operator';
}

function admin_user_normalize_permissions(array $permissions): array
{
    $allowed = array_keys(admin_permission_options());
    $cleanPermissions = [];

    foreach ($permissions as $permission) {
        $permission = (string)$permission;
        if (in_array($permission, $allowed, true)) {
            $cleanPermissions[] = $permission;
        }
    }

    return array_values(array_unique($cleanPermissions));
}

function admin_user_fetch(int $userId): ?array
{
    global $pdo;

    if ($userId <= 0) {
        return null;
    }

    $stmt = $pdo->prepare('SELECT id, name, email, role, profile_photo, is_active, last_login_at, created_at, updated_at FROM users WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $userId]);
    $user = $stmt->fetch();

    return $user ?: null;
}

function admin_user_fetch_grants(int $userId): array
{
    global $pdo;

    if ($userId <= 0) {
        return [];
    }

    $stmt = $pdo->prepare('SELECT permission FROM user_permissions WHERE user_id = :id ORDER BY permission ASC');
    $stmt->execute(['id' => $userId]);

    return admin_user_normalize_permissions($stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
}

function admin_user_replace_grants(int $userId, array $permissions): void
{
    global $pdo;

    $permissions = admin_user_normalize_permissions($permissions);
    $delete = $pdo->prepare('DELETE FROM user_permissions WHERE user_id = :id');
    $delete->execute(['id' => $userId]);

    if (empty($permissions)) {
        return;
    }

    $insert = $pdo->prepare('INSERT INTO user_permissions (user_id, permission, created_at) VALUES (:user_id, :permission, NOW())');
    foreach ($permissions as $permission) {
        $insert->execute(['user_id' => $userId, 'permission' => $permission]);
    }
}

function admin_user_candidate_has_manage_user(string $role, array $permissions, int $isActive): bool
{
    if ($isActive !== 1) {
        return false;
    }

    return in_array('manage_user', array_unique(array_merge(
        admin_role_base_permissions($role),
        admin_user_normalize_permissions($permissions)
    )), true);
}

function admin_user_preserves_manage_user_access(?int $targetUserId, string $targetRole, int $targetIsActive, array $targetPermissions): bool
{
    global $pdo;

    if ($targetUserId === null) {
        $stmt = $pdo->query('SELECT id, role FROM users WHERE is_active = 1');
    } else {
        $stmt = $pdo->prepare('SELECT id, role FROM users WHERE is_active = 1 AND id <> :target_id');
        $stmt->execute(['target_id' => $targetUserId]);
    }
    foreach ($stmt->fetchAll() as $user) {
        if (admin_user_has_permission($user, 'manage_user')) {
            return true;
        }
    }

    return admin_user_candidate_has_manage_user($targetRole, $targetPermissions, $targetIsActive);
}

function admin_user_sync_session_if_current(int $userId, string $name, string $email, string $role, int $isActive, ?string $profilePhoto = null): void
{
    $sessionUser = current_user();
    if (!$sessionUser || (int)($sessionUser['id'] ?? 0) !== $userId) {
        return;
    }

    if ($isActive !== 1) {
        logout_user();
        redirect('login.php?inactive=1');
    }

    $_SESSION['admin_user']['name'] = $name;
    $_SESSION['admin_user']['email'] = $email;
    $_SESSION['admin_user']['role'] = $role;
    $_SESSION['admin_user']['profile_photo'] = $profilePhoto;
}
