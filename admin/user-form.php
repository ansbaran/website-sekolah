<?php

declare(strict_types=1);

define('ADMIN_CONTEXT', true);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/user-management-support.php';
require_login();
require_permission('manage_user');

$editing = isset($_GET['id']) && ctype_digit((string)$_GET['id']);
$userId = $editing ? (int)$_GET['id'] : 0;
$account = $editing ? admin_user_fetch($userId) : null;

if ($editing && !$account) {
    flash('error', 'Akun pengguna tidak ditemukan.');
    redirect('users.php');
}

$roleOptions = admin_role_options();
$permissionGroups = admin_permission_grouped_options();
$accountGrants = $editing ? admin_user_fetch_grants($userId) : [];
$currentRoleBasePermissions = admin_role_base_permissions((string)($account['role'] ?? 'guru_staff'));
$currentEffectivePermissionCount = count(array_unique(array_merge($currentRoleBasePermissions, $accountGrants)));
$isSelfEdit = $editing && (int)($account['id'] ?? 0) === (int)(current_user()['id'] ?? 0);
$form = [
    'name' => $account['name'] ?? '',
    'email' => $account['email'] ?? '',
    'role' => $account['role'] ?? 'guru_staff',
    'is_active' => (int)($account['is_active'] ?? 1),
    'permissions' => $accountGrants,
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        flash('error', 'Token keamanan tidak valid.');
        redirect($editing ? 'user-form.php?id=' . $userId : 'user-form.php');
    }

    $name = clean((string)($_POST['name'] ?? ''));
    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    $role = admin_user_normalize_role((string)($_POST['role'] ?? 'operator'));
    $isActive = isset($_POST['is_active']) ? 1 : 0;
    $permissions = admin_user_normalize_permissions(is_array($_POST['permissions'] ?? null) ? $_POST['permissions'] : []);
    $password = (string)($_POST['password'] ?? '');
    $passwordConfirm = (string)($_POST['password_confirm'] ?? '');

    if ($name === '' || $email === '') {
        flash('error', 'Nama dan email wajib diisi.');
        redirect($editing ? 'user-form.php?id=' . $userId : 'user-form.php');
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        flash('error', 'Format email belum valid.');
        redirect($editing ? 'user-form.php?id=' . $userId : 'user-form.php');
    }

    if ($editing) {
        $emailCheck = $pdo->prepare('SELECT id FROM users WHERE email = :email AND id <> :id LIMIT 1');
        $emailCheck->execute(['email' => $email, 'id' => $userId]);
    } else {
        $emailCheck = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
        $emailCheck->execute(['email' => $email]);
    }
    if ($emailCheck->fetchColumn()) {
        flash('error', 'Email tersebut sudah digunakan akun lain.');
        redirect($editing ? 'user-form.php?id=' . $userId : 'user-form.php');
    }

    if (!$editing) {
        if (strlen($password) < 8) {
            flash('error', 'Password minimal 8 karakter.');
            redirect('user-form.php');
        }

        if (!preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
            flash('error', 'Password sebaiknya berisi kombinasi huruf dan angka.');
            redirect('user-form.php');
        }

        if ($password !== $passwordConfirm) {
            flash('error', 'Konfirmasi password tidak sama.');
            redirect('user-form.php');
        }
    }

    if (!admin_user_preserves_manage_user_access($editing ? $userId : null, $role, $isActive, $permissions)) {
        flash('error', 'Perubahan ditolak agar tetap ada minimal satu akun aktif dengan akses kelola akun.');
        redirect($editing ? 'user-form.php?id=' . $userId : 'user-form.php');
    }

    try {
        $pdo->beginTransaction();

        if ($editing) {
            $stmt = $pdo->prepare('UPDATE users SET name = :name, email = :email, role = :role, is_active = :is_active WHERE id = :id');
            $stmt->execute([
                'name' => $name,
                'email' => $email,
                'role' => $role,
                'is_active' => $isActive,
                'id' => $userId,
            ]);
        } else {
            $stmt = $pdo->prepare('INSERT INTO users (name, email, password, role, is_active, created_at) VALUES (:name, :email, :password, :role, :is_active, NOW())');
            $stmt->execute([
                'name' => $name,
                'email' => $email,
                'password' => password_hash($password, PASSWORD_DEFAULT),
                'role' => $role,
                'is_active' => $isActive,
            ]);
            $userId = (int)$pdo->lastInsertId();
        }

        admin_user_replace_grants($userId, $permissions);
        log_activity($editing ? 'Edit akun' : 'Tambah akun', 'Akun pengguna disimpan: ' . $email, 'users:' . $userId);
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo instanceof PDO && $pdo->inTransaction()) {
            $pdo->rollBack();
        }

        log_exception($exception);
        flash('error', 'Akun pengguna gagal disimpan. Silakan coba lagi.');
        redirect($editing ? 'user-form.php?id=' . $userId : 'user-form.php');
    }

    admin_user_sync_session_if_current($userId, $name, $email, $role, $isActive, $account['profile_photo'] ?? null);
    flash('success', 'Akun pengguna berhasil disimpan.');
    redirect('users.php');
}

$pageTitle = $editing ? 'Edit Akun' : 'Tambah Akun';
require_once __DIR__ . '/includes/header.php';
?>
<section class="panel">
    <div class="panel-header">
        <div>
            <h2><?= $editing ? 'Edit Akun Pengguna' : 'Tambah Akun Pengguna' ?></h2>
            <p class="footer-note">Role memberi hak akses dasar. Tambahkan hak akses khusus hanya jika pengguna membutuhkan akses lebih.</p>
        </div>
        <a class="btn-secondary" href="users.php">Kembali ke Kelola Akun</a>
    </div>
</section>

<form method="post" class="form-card">
    <?= csrf_field() ?>
    <div class="form-grid">
        <section class="account-form-section full-span">
            <div class="account-form-section__header">
                <span>Informasi Akun</span>
                <small>Identitas dasar pengguna CMS.</small>
            </div>
            <div class="form-grid account-form-section__grid">
                <div>
                    <label for="name">Nama Lengkap</label>
                    <input type="text" id="name" name="name" value="<?= escape((string)$form['name']) ?>" autocomplete="name" required>
                </div>
                <div>
                    <label for="email">Email Login</label>
                    <input type="email" id="email" name="email" value="<?= escape((string)$form['email']) ?>" autocomplete="email" required>
                </div>
                <div class="align-end">
                    <label class="field-inline">
                        <input type="checkbox" name="is_active" value="1" <?= (int)$form['is_active'] === 1 ? 'checked' : '' ?>> Akun aktif
                    </label>
                </div>
            </div>
        </section>

        <section class="account-form-section full-span">
            <div class="account-form-section__header">
                <span>Role</span>
                <small>Akses dasar yang otomatis mengikuti role akun.</small>
            </div>
            <div class="form-grid account-form-section__grid">
                <div>
                    <label for="role">Role Pengguna</label>
                    <select id="role" name="role" required>
                        <?php foreach ($roleOptions as $value => $label): ?>
                            <?php
                            $roleBasePermissions = admin_role_base_permissions($value);
                            $roleBaseLabels = array_map(static fn(string $permission): string => admin_permission_label($permission), $roleBasePermissions);
                            ?>
                            <option
                                value="<?= escape($value) ?>"
                                data-description="<?= escape(admin_role_description($value)) ?>"
                                data-baseline="<?= escape(implode(',', $roleBasePermissions)) ?>"
                                data-baseline-labels="<?= escape(implode('|', $roleBaseLabels)) ?>"
                                <?= (string)$form['role'] === $value ? 'selected' : '' ?>
                            ><?= escape($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="role-context-card" data-role-summary>
                    <strong data-role-summary-label><?= escape(admin_role_label((string)$form['role'])) ?></strong>
                    <p data-role-summary-description><?= escape(admin_role_description((string)$form['role'])) ?></p>
                    <span data-role-summary-count><?= $currentEffectivePermissionCount ?> akses efektif saat ini</span>
                </div>
            </div>

            <div class="role-baseline-panel" data-role-baseline-panel>
                <div>
                    <strong>Akses Dasar Role</strong>
                    <p data-role-baseline-copy>
                        <?php if (empty($currentRoleBasePermissions)): ?>
                            Tidak ada permission sistem secara otomatis. Tambahkan hak akses sesuai kebutuhan di bawah.
                        <?php else: ?>
                            Role ini otomatis membawa akses berikut. Checkbox tambahan di bawah tidak perlu dicentang ulang.
                        <?php endif; ?>
                    </p>
                </div>
                <div class="baseline-chip-list" data-role-baseline-list <?= empty($currentRoleBasePermissions) ? 'hidden' : '' ?>>
                    <?php if (!empty($currentRoleBasePermissions)): ?>
                        <?php foreach ($currentRoleBasePermissions as $permission): ?>
                            <span class="baseline-chip"><?= escape(admin_permission_label($permission)) ?></span>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($isSelfEdit): ?>
                <div class="account-note account-note--soft">Anda sedang mengedit akun sendiri. Sistem tetap menjaga agar selalu ada minimal satu akun aktif yang dapat mengelola pengguna.</div>
            <?php endif; ?>
        </section>

        <section class="account-form-section full-span">
            <div class="account-form-section__header">
                <span>Tambahan Hak Akses</span>
                <small>Hak akses berikut ditambahkan khusus untuk akun ini di luar akses dasar role.</small>
            </div>
            <div class="permission-group-list">
                <?php foreach ($permissionGroups as $groupLabel => $permissions): ?>
                    <div class="permission-group">
                        <div class="permission-group__label"><?= escape($groupLabel) ?></div>
                        <div class="permission-grid">
                            <?php foreach ($permissions as $permission => $definition): ?>
                                <?php
                                $isInherited = in_array($permission, $currentRoleBasePermissions, true);
                                $isGranted = in_array($permission, $form['permissions'], true);
                                ?>
                                <label class="permission-option <?= $isInherited ? 'permission-option--inherited' : '' ?>" data-permission-option data-permission="<?= escape($permission) ?>">
                                    <input type="checkbox" name="permissions[]" value="<?= escape($permission) ?>" <?= $isGranted ? 'checked' : '' ?> <?= $isInherited ? 'disabled' : '' ?>>
                                    <?php if ($isInherited && $isGranted): ?>
                                        <input type="hidden" name="permissions[]" value="<?= escape($permission) ?>" data-inherited-preserve>
                                    <?php endif; ?>
                                    <span>
                                        <strong><?= escape($definition['label']) ?></strong>
                                        <small><?= escape($definition['description']) ?></small>
                                        <span class="permission-meta">
                                            <?php if ($isInherited): ?>
                                                <span class="permission-badge permission-badge--inherited" data-permission-state>Bawaan Role</span>
                                            <?php elseif ($isGranted): ?>
                                                <span class="permission-badge" data-permission-state>Akses Tambahan</span>
                                            <?php else: ?>
                                                <span class="permission-badge permission-badge--muted" data-permission-state>Opsional</span>
                                            <?php endif; ?>
                                            <?php if ($definition['sensitive'] !== ''): ?>
                                                <span class="permission-warning"><?= escape($definition['sensitive']) ?></span>
                                            <?php endif; ?>
                                        </span>
                                    </span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="account-note">
                Hak akses tambahan tidak menggantikan hak akses bawaan role. Model akses tetap additive: role memberi akses dasar, checkbox hanya menambah akses khusus akun.
            </div>
        </section>

        <?php if (!$editing): ?>
        <section class="account-form-section full-span">
            <div class="account-form-section__header">
                <span>Password Awal</span>
                <small>Hanya diperlukan saat membuat akun baru.</small>
            </div>
            <div class="form-grid account-form-section__grid">
                <div>
                    <label for="password">Password Awal</label>
                    <input type="password" id="password" name="password" autocomplete="new-password" minlength="8" required>
                </div>
                <div>
                    <label for="password_confirm">Konfirmasi Password</label>
                    <input type="password" id="password_confirm" name="password_confirm" autocomplete="new-password" minlength="8" required>
                </div>
            </div>
        </section>
        <?php endif; ?>

        <div class="form-actions full-span">
            <button type="submit" class="btn-primary"><?= $editing ? 'Simpan Perubahan' : 'Tambah Akun' ?></button>
            <a class="btn-secondary" href="users.php">Batal</a>
        </div>
    </div>
</form>

<?php require_once __DIR__ . '/includes/footer.php';
