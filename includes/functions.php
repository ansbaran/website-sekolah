<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/error.php';

function clean(string $value): string
{
    return trim(htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
}

function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

function flash(string $key, string $message = null): ?string
{
    if ($message !== null) {
        $_SESSION[$key] = $message;
        return null;
    }

    if (!empty($_SESSION[$key])) {
        $value = $_SESSION[$key];
        unset($_SESSION[$key]);
        return $value;
    }

    return null;
}

function old(string $key, $default = '')
{
    return $_POST[$key] ?? $default;
}

function sanitize_file_name(string $name): string
{
    $fileName = preg_replace('/[^a-zA-Z0-9._-]/', '-', $name);
    $fileName = preg_replace('/-+/', '-', $fileName);
    return trim($fileName, '.-') ?: 'file';
}

function unique_file_name(string $fileName): string
{
    return time() . '_' . bin2hex(random_bytes(6)) . '_' . sanitize_file_name($fileName);
}

function build_upload_path(string $subDir, string $fileName): string
{
    $safeSubDir = normalize_upload_subdir($subDir);
    $uploadDir = UPLOAD_BASE . '/' . $safeSubDir;
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    return $uploadDir . '/' . ltrim($fileName, '/\\');
}

function normalize_upload_subdir(string $subDir): string
{
    $subDir = trim(strtolower(str_replace('\\', '/', $subDir)), '/');
    if (!in_array($subDir, ALLOWED_UPLOAD_DIRS, true)) {
        return 'misc';
    }

    return $subDir;
}

function uploaded_file_path(string $subDir, string $fileName): ?string
{
    $safeSubDir = normalize_upload_subdir($subDir);
    $safeFileName = basename(str_replace('\\', '/', $fileName));
    if ($safeFileName === '' || $safeFileName !== sanitize_file_name($safeFileName)) {
        return null;
    }

    $path = UPLOAD_BASE . '/' . $safeSubDir . '/' . $safeFileName;
    $base = realpath(UPLOAD_BASE);
    $dir = realpath(dirname($path));
    if ($base === false || $dir === false || ($dir !== $base && strpos($dir, $base . DIRECTORY_SEPARATOR) !== 0)) {
        return null;
    }

    return $path;
}

function normalize_legacy_asset_path(string $path): string
{
    $path = ltrim(str_replace('\\', '/', trim($path)), '/');

    if (defined('BASE_URL') && BASE_URL !== '') {
        $basePath = trim(BASE_URL, '/') . '/';
        if (strpos($path, $basePath) === 0) {
            $path = substr($path, strlen($basePath));
        }
    }

    $legacyMap = [
        'assets/img/berita1.jpeg' => 'assets/img/berita/berita1.jpeg',
        'assets/img/berita5.jpeg' => 'assets/img/berita/berita5.jpeg',
        'assets/img/sekolah.jpg' => 'assets/img/sekolah.jpg',
    ];

    return $legacyMap[$path] ?? $path;
}

function build_upload_url($type, $filename) {
    if (!$filename) return "";

    $type = normalize_upload_subdir((string) $type);
    $filename = normalize_legacy_asset_path((string) $filename);
    $filename = ltrim($filename, '/');

    if (preg_match('#^https?://#i', $filename)) {
        return $filename;
    }

    if (strpos($filename, 'assets/') === 0 || strpos($filename, 'uploads/') === 0) {
        return BASE_URL . '/' . $filename;
    }

    return UPLOAD_URL . '/' . $type . '/' . rawurlencode(basename($filename));
}

function escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function check_session_activity(): bool
{
    if (empty($_SESSION['last_activity'])) {
        return true;
    }

    return (time() - (int)$_SESSION['last_activity']) <= SESSION_TIMEOUT_SECONDS;
}

function update_session_activity(): void
{
    $_SESSION['last_activity'] = time();
}

function get_session_time_remaining(): int
{
    if (empty($_SESSION['last_activity'])) {
        return SESSION_TIMEOUT_SECONDS;
    }

    $remaining = SESSION_TIMEOUT_SECONDS - (time() - (int)$_SESSION['last_activity']);
    return max(0, $remaining);
}

function send_secure_headers(): void
{
    if (headers_sent()) {
        return;
    }

    $serverSoftware = $_SERVER['SERVER_SOFTWARE'] ?? '';
    if (stripos($serverSoftware, 'Apache') !== false) {
        return;
    }

    $isHttps = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on')
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=(), payment=(), usb=(), interest-cohort=()');
    if ($isHttps) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.tailwindcss.com https://cdnjs.cloudflare.com https://unpkg.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdnjs.cloudflare.com https://unpkg.com; img-src 'self' data: https:; font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com; connect-src 'self'; frame-src 'self' https://www.google.com https://maps.google.com; child-src 'self' https://www.google.com https://maps.google.com; frame-ancestors 'self'; base-uri 'self'; form-action 'self';");
}

function cache_file_path(string $key): string
{
    return CACHE_DIR . '/' . sha1($key) . '.cache';
}

function cache_get(string $key, int $ttl = CACHE_DURATION)
{
    $cacheFile = cache_file_path($key);
    if (!is_file($cacheFile)) {
        return null;
    }

    $modified = filemtime($cacheFile);
    if ($modified === false || time() - $modified > $ttl) {
        @unlink($cacheFile);
        return null;
    }

    $content = file_get_contents($cacheFile);
    return $content === false ? null : json_decode($content, true);
}

function cache_set(string $key, $value): bool
{
    if (!maybe_create_directory(CACHE_DIR)) {
        return false;
    }

    $cacheFile = cache_file_path($key);
    return file_put_contents($cacheFile, json_encode($value, JSON_UNESCAPED_UNICODE)) !== false;
}

function cache_clear(string $key): bool
{
    $cacheFile = cache_file_path($key);
    return is_file($cacheFile) ? @unlink($cacheFile) : true;
}

function cache_flush(): bool
{
    if (!is_dir(CACHE_DIR)) {
        return true;
    }

    $entries = glob(CACHE_DIR . '/*.cache');
    if ($entries === false) {
        return false;
    }

    foreach ($entries as $entry) {
        @unlink($entry);
    }

    return true;
}

function csrf_refresh(): string
{
    $_SESSION['_csrf_token'] = bin2hex(random_bytes(24));
    return $_SESSION['_csrf_token'];
}

function get_client_ip(): string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    if (!filter_var($ip, FILTER_VALIDATE_IP)) {
        return '127.0.0.1';
    }
    return $ip;
}

function validate_image_upload(array $file, ?string &$error = null): bool
{
    if ($file['error'] === UPLOAD_ERR_NO_FILE) {
        $error = 'Silakan pilih file gambar.';
        return false;
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error = match ($file['error']) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Ukuran file maksimal ' . (MAX_IMAGE_SIZE / 1024 / 1024) . 'MB.',
            UPLOAD_ERR_PARTIAL => 'Upload belum selesai. Silakan pilih file dan coba lagi.',
            default => 'Terjadi kesalahan saat mengunggah file.',
        };
        return false;
    }

    if ($file['size'] > MAX_IMAGE_SIZE) {
        $error = 'Ukuran file maksimal ' . (MAX_IMAGE_SIZE / 1024 / 1024) . 'MB.';
        return false;
    }

    $fileType = mime_content_type($file['tmp_name']);
    if (!in_array($fileType, ALLOWED_IMAGE_TYPES, true)) {
        $error = 'Format gambar tidak didukung. Gunakan JPG, PNG, atau WEBP.';
        return false;
    }

    $originalName = str_replace('\\', '/', (string)$file['name']);
    if (basename($originalName) !== $originalName || strpos($originalName, '..') !== false) {
        $error = 'Nama file tidak valid.';
        return false;
    }


    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, ALLOWED_IMAGE_EXT, true)) {
        $error = 'Ekstensi file tidak valid.';
        return false;
    }

    if (preg_match('/\.(php|phtml|phps|php3|php4|php5|phar|cgi|pl|asp|aspx|exe|sh|bat|cmd|js|html|svg)(\.|$)/i', $originalName)) {
        $error = 'Nama file tidak boleh mengandung ekstensi berbahaya.';
        return false;
    }

    $imageInfo = @getimagesize($file['tmp_name']);
    if ($imageInfo === false) {
        $error = 'File bukan gambar yang valid.';
        return false;
    }

    [$width, $height] = $imageInfo;
    if ($width < UPLOAD_DIMENSION_MIN_WIDTH || $height < UPLOAD_DIMENSION_MIN_HEIGHT) {
        $error = 'Resolusi gambar terlalu kecil. Minimal ' . UPLOAD_DIMENSION_MIN_WIDTH . 'x' . UPLOAD_DIMENSION_MIN_HEIGHT . ' piksel.';
        return false;
    }

    if ($width > MAX_IMAGE_WIDTH || $height > MAX_IMAGE_HEIGHT) {
        $error = 'Resolusi gambar terlalu besar. Maksimal ' . MAX_IMAGE_WIDTH . 'x' . MAX_IMAGE_HEIGHT . ' piksel.';
        return false;
    }

    return true;
}

function optimize_image(string $sourcePath, string $targetPath, string $mimeType): bool
{
    $quality = 82;

    switch ($mimeType) {
        case 'image/jpeg':
            $image = @imagecreatefromjpeg($sourcePath);
            if (!$image) {
                return false;
            }
            imagejpeg($image, $targetPath, $quality);
            imagedestroy($image);
            return true;
        case 'image/png':
            $image = @imagecreatefrompng($sourcePath);
            if (!$image) {
                return false;
            }
            imagepng($image, $targetPath, 6);
            imagedestroy($image);
            return true;
        case 'image/webp':
            $image = @imagecreatefromwebp($sourcePath);
            if (!$image) {
                return false;
            }
            imagewebp($image, $targetPath, $quality);
            imagedestroy($image);
            return true;
    }

    return false;
}

function create_webp_variant(string $sourcePath, string $targetPath): bool
{
    if (!function_exists('imagewebp')) {
        return false;
    }

    $imageInfo = @getimagesize($sourcePath);
    if ($imageInfo === false) {
        return false;
    }

    [$width, $height, $type] = $imageInfo;
    switch ($type) {
        case IMAGETYPE_JPEG:
            $image = @imagecreatefromjpeg($sourcePath);
            break;
        case IMAGETYPE_PNG:
            $image = @imagecreatefrompng($sourcePath);
            break;
        case IMAGETYPE_WEBP:
            $image = @imagecreatefromwebp($sourcePath);
            break;
        default:
            return false;
    }

    if (!$image) {
        return false;
    }

    $result = imagewebp($image, $targetPath, 82);
    imagedestroy($image);
    return $result;
}

function strip_image_metadata(string $sourcePath, string $mimeType): bool
{
    switch ($mimeType) {
        case 'image/jpeg':
            $image = @imagecreatefromjpeg($sourcePath);
            if (!$image) {
                return false;
            }
            imagejpeg($image, $sourcePath, 82);
            imagedestroy($image);
            return true;
        case 'image/png':
            $image = @imagecreatefrompng($sourcePath);
            if (!$image) {
                return false;
            }
            imagepng($image, $sourcePath, 6);
            imagedestroy($image);
            return true;
        case 'image/webp':
            $image = @imagecreatefromwebp($sourcePath);
            if (!$image) {
                return false;
            }
            imagewebp($image, $sourcePath, 82);
            imagedestroy($image);
            return true;
        default:
            return false;
    }
}

function upload_image(array $file, string $subDir, ?string &$error = null): ?string
{
    $subDir = normalize_upload_subdir($subDir);
    if (!validate_image_upload($file, $error)) {
        return null;
    }

    $storedName = unique_file_name($file['name']);
    $targetDir = build_upload_path($subDir, '');
    $targetPath = rtrim($targetDir, '/') . '/' . $storedName;

    if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
        $error = 'Gagal membuat direktori upload.';
        return null;
    }

    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        $error = 'Gagal menyimpan file ke server.';
        return null;
    }

    $mimeType = mime_content_type($targetPath) ?: $file['type'];
    if (!optimize_image($targetPath, $targetPath, $mimeType)) {
        // Fallback to original file when optimization fails.
    }

    strip_image_metadata($targetPath, $mimeType);

    $webpFile = preg_replace('/\.[^.]+$/', '.webp', $targetPath);
    if (strpos($webpFile, '.') !== false && create_webp_variant($targetPath, $webpFile)) {
        // Safe webp variant produced.
    }

    return $storedName;
}

function delete_file(string $path): void
{
    $base = realpath(BASE_PATH);
    if ($base === false || $path === '') {
        return;
    }

    $targets = [$path];
    $webpPath = preg_replace('/\.[^.]+$/', '.webp', $path);
    if (is_string($webpPath) && $webpPath !== $path) {
        $targets[] = $webpPath;
    }

    foreach (array_unique($targets) as $target) {
        $realPath = realpath($target);
        if ($realPath === false || ($realPath !== $base && strpos($realPath, $base . DIRECTORY_SEPARATOR) !== 0)) {
            continue;
        }

        if (is_file($realPath)) {
            @unlink($realPath);
        }
    }
}

function count_table(string $table): int
{
    global $pdo;
    $statement = $pdo->query('SELECT COUNT(*) AS total FROM `' . $table . '`');
    return (int) $statement->fetchColumn();
}

send_secure_headers();

function get_recent_activities(int $limit = 5): array
{
    global $pdo;

    $statement = $pdo->prepare('SELECT user_name, user_role, activity_type, description, reference, ip_address, created_at FROM activity_logs ORDER BY created_at DESC LIMIT :limit');
    $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
    $statement->execute();

    return $statement->fetchAll();
}

function get_activity_log_entries(string $search = '', int $page = 1, int $limit = 20): array
{
    global $pdo;
    $offset = max(0, ($page - 1) * $limit);
    $query = 'SELECT user_name, user_role, activity_type, description, reference, ip_address, created_at FROM activity_logs';
    $params = [];

    if ($search !== '') {
        $query .= ' WHERE user_name LIKE :search OR activity_type LIKE :search OR description LIKE :search OR reference LIKE :search';
        $params['search'] = '%' . $search . '%';
    }

    $query .= ' ORDER BY created_at DESC LIMIT :limit OFFSET :offset';
    $stmt = $pdo->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue(':' . $key, $value, PDO::PARAM_STR);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}

function count_activity_entries(string $search = ''): int
{
    global $pdo;
    $query = 'SELECT COUNT(*) FROM activity_logs';
    $params = [];

    if ($search !== '') {
        $query .= ' WHERE user_name LIKE :search OR activity_type LIKE :search OR description LIKE :search OR reference LIKE :search';
        $params['search'] = '%' . $search . '%';
    }

    $stmt = $pdo->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue(':' . $key, $value, PDO::PARAM_STR);
    }
    $stmt->execute();
    return (int) $stmt->fetchColumn();
}

function log_activity(string $type, string $description = '', ?string $reference = null): void
{
    global $pdo;
    $user = current_user();

    $stmt = $pdo->prepare('INSERT INTO activity_logs (user_id, user_name, user_role, activity_type, description, reference, ip_address, created_at) VALUES (:user_id, :user_name, :user_role, :activity_type, :description, :reference, :ip_address, NOW())');
    $stmt->execute([
        'user_id' => $user['id'] ?? null,
        'user_name' => $user['name'] ?? 'Guest',
        'user_role' => $user['role'] ?? 'operator',
        'activity_type' => $type,
        'description' => $description,
        'reference' => $reference,
        'ip_address' => get_client_ip(),
    ]);
}

function record_login_attempt(?string $email, bool $success): void
{
    global $pdo;
    $stmt = $pdo->prepare('INSERT INTO login_attempts (email, ip_address, success, created_at) VALUES (:email, :ip_address, :success, NOW())');
    $stmt->execute([
        'email' => $email,
        'ip_address' => get_client_ip(),
        'success' => $success ? 1 : 0,
    ]);
}

function is_login_blocked(string $ipAddress, ?string $email = null): bool
{
    global $pdo;
    $interval = date('Y-m-d H:i:s', time() - LOGIN_RATE_LIMIT_WINDOW);
    $query = 'SELECT COUNT(*) FROM login_attempts WHERE ip_address = :ip_address AND success = 0 AND created_at >= :interval';
    $params = ['ip_address' => $ipAddress, 'interval' => $interval];

    if ($email !== null) {
        $query .= ' AND email = :email';
        $params['email'] = $email;
    }

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);

    return (int)$stmt->fetchColumn() >= LOGIN_RATE_LIMIT_ATTEMPTS;
}

function current_role(): string
{
    return current_user()['role'] ?? 'operator';
}

function can(string $permission): bool
{
    $role = current_role();
    $permissions = [
        'super_admin' => ['delete', 'publish', 'upload', 'manage_user', 'backup', 'maintenance'],
        'admin' => ['delete', 'publish', 'upload', 'backup', 'maintenance'],
        'editor' => ['publish', 'upload'],
        'operator' => ['upload'],
    ];

    return in_array($permission, $permissions[$role] ?? [], true);
}

function get_setting(string $name, $default = null)
{
    global $pdo;
    $stmt = $pdo->prepare('SELECT value FROM settings WHERE name = :name LIMIT 1');
    $stmt->execute(['name' => $name]);
    $row = $stmt->fetchColumn();

    return $row !== false ? $row : $default;
}

function set_setting(string $name, string $value): bool
{
    global $pdo;
    $stmt = $pdo->prepare('INSERT INTO settings (name, value, updated_at) VALUES (:name, :value_insert, NOW()) ON DUPLICATE KEY UPDATE value = :value_update, updated_at = NOW()');
    return $stmt->execute([
        'name' => $name,
        'value_insert' => $value,
        'value_update' => $value,
    ]);
}

function is_maintenance_mode(): bool
{
    return file_exists(MAINTENANCE_TOGGLE_FILE);
}

function maybe_create_directory(string $path): bool
{
    if (is_dir($path)) {
        return true;
    }
    return mkdir($path, 0755, true);
}

function ensure_backup_environment(): bool
{
    if (!maybe_create_directory(BACKUP_DIR)) {
        return false;
    }
    return maybe_create_directory(CACHE_DIR);
}

function export_database_sql(): ?string
{
    global $pdo;
    $tables = ['users', 'news', 'gallery', 'achievements', 'slider', 'announcements', 'media', 'activity_logs', 'login_attempts', 'settings'];
    $sql = "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n";

    foreach ($tables as $table) {
        $schema = $pdo->query('SHOW CREATE TABLE `' . $table . '`')->fetch(PDO::FETCH_ASSOC);
        if (empty($schema['Create Table'])) {
            continue;
        }

        $sql .= '-- Table structure for `' . $table . '`\n';
        $sql .= $schema['Create Table'] . ";\n\n";

        $rows = $pdo->query('SELECT * FROM `' . $table . '`')->fetchAll(PDO::FETCH_ASSOC);
        if (empty($rows)) {
            continue;
        }

        $columns = array_map(static fn($name) => '`' . str_replace('`', '``', $name) . '`', array_keys($rows[0]));
        $sql .= 'INSERT INTO `' . $table . '` (' . implode(', ', $columns) . ') VALUES\n';
        $values = [];

        foreach ($rows as $row) {
            $escaped = array_map(static fn($value) => $value === null ? 'NULL' : "'" . str_replace("'", "''", $value) . "'", $row);
            $values[] = '(' . implode(', ', $escaped) . ')';
        }

        $sql .= implode(',\n', $values) . ";\n\n";
    }

    return $sql;
}

function write_backup_file(string $fileName, string $content): ?string
{
    if (!ensure_backup_environment()) {
        return null;
    }

    $path = BACKUP_DIR . '/' . $fileName;
    return file_put_contents($path, $content) !== false ? $path : null;
}

function create_zip_archive(string $filePath, array $files): ?string
{
    if (!ensure_backup_environment()) {
        return null;
    }

    $zip = new ZipArchive();
    if ($zip->open($filePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        return null;
    }

    foreach ($files as $file) {
        if (is_file($file)) {
            $zip->addFile($file, basename($file));
        }
    }

    $zip->close();
    return $filePath;
}

function get_backup_files(): array
{
    if (!is_dir(BACKUP_DIR)) {
        return [];
    }

    $items = scandir(BACKUP_DIR);
    if ($items === false) {
        return [];
    }

    return array_values(array_filter($items, static fn($name) => is_file(BACKUP_DIR . '/' . $name)));
}

function get_cache_files(): array
{
    if (!is_dir(CACHE_DIR)) {
        return [];
    }

    $items = scandir(CACHE_DIR);
    if ($items === false) {
        return [];
    }

    return array_values(array_filter($items, static fn($name) => is_file(CACHE_DIR . '/' . $name)));
}

function scan_media_items(array $filters = [], int $limit = 50, int $offset = 0): array
{
    global $pdo;
    $query = 'SELECT * FROM media';
    $params = [];
    $conditions = [];

    if (!empty($filters['search'])) {
        $conditions[] = '(title LIKE :search OR filename LIKE :search OR subdir LIKE :search OR uploaded_by LIKE :search)';
        $params['search'] = '%' . $filters['search'] . '%';
    }
    if (!empty($filters['subdir'])) {
        $conditions[] = 'subdir = :subdir';
        $params['subdir'] = $filters['subdir'];
    }

    if ($conditions) {
        $query .= ' WHERE ' . implode(' AND ', $conditions);
    }

    $query .= ' ORDER BY created_at DESC LIMIT :limit OFFSET :offset';
    $stmt = $pdo->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue(':' . $key, $value, PDO::PARAM_STR);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}

function count_media_items(array $filters = []): int
{
    global $pdo;
    $query = 'SELECT COUNT(*) FROM media';
    $params = [];
    $conditions = [];

    if (!empty($filters['search'])) {
        $conditions[] = '(title LIKE :search OR filename LIKE :search OR subdir LIKE :search OR uploaded_by LIKE :search)';
        $params['search'] = '%' . $filters['search'] . '%';
    }
    if (!empty($filters['subdir'])) {
        $conditions[] = 'subdir = :subdir';
        $params['subdir'] = $filters['subdir'];
    }

    if ($conditions) {
        $query .= ' WHERE ' . implode(' AND ', $conditions);
    }

    $stmt = $pdo->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue(':' . $key, $value, PDO::PARAM_STR);
    }
    $stmt->execute();

    return (int)$stmt->fetchColumn();
}

function create_media_record(array $metadata): bool
{
    global $pdo;
    $stmt = $pdo->prepare('INSERT INTO media (filename, subdir, title, alt_text, mime_type, width, height, size_bytes, uploaded_by, uploaded_by_role, metadata, created_at) VALUES (:filename, :subdir, :title, :alt_text, :mime_type, :width, :height, :size_bytes, :uploaded_by, :uploaded_by_role, :metadata, NOW())');
    return $stmt->execute([
        'filename' => $metadata['filename'],
        'subdir' => $metadata['subdir'],
        'title' => $metadata['title'] ?? '',
        'alt_text' => $metadata['alt_text'] ?? '',
        'mime_type' => $metadata['mime_type'],
        'width' => $metadata['width'],
        'height' => $metadata['height'],
        'size_bytes' => $metadata['size_bytes'],
        'uploaded_by' => $metadata['uploaded_by'],
        'uploaded_by_role' => $metadata['uploaded_by_role'],
        'metadata' => json_encode($metadata['metadata'] ?? []),
    ]);
}

function delete_media_record(int $id): void
{
    global $pdo;
    $stmt = $pdo->prepare('DELETE FROM media WHERE id = :id');
    $stmt->execute(['id' => $id]);
}

function get_media_item(int $id): ?array
{
    global $pdo;
    $stmt = $pdo->prepare('SELECT * FROM media WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $id]);
    return $stmt->fetch() ?: null;
}

function sanitize_filename(string $name): string
{
    return preg_replace('/[^a-zA-Z0-9._-]/', '-', $name);
}

function generate_slug(string $title): string
{
    $slug = strtolower(trim($title));
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
    $slug = trim($slug, '-');
    return substr($slug, 0, 200);
}

function ensure_unique_slug(string $baseSlug, ?int $excludeId = null): string
{
    global $pdo;
    $slug = $baseSlug;
    $counter = 1;
    
    while (true) {
        $query = 'SELECT id FROM news WHERE slug = :slug';
        $params = ['slug' => $slug];
        
        if ($excludeId !== null) {
            $query .= ' AND id != :excludeId';
            $params['excludeId'] = $excludeId;
        }
        
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        
        if ($stmt->rowCount() === 0) {
            return $slug;
        }
        
        $slug = $baseSlug . '-' . (++$counter);
    }
}

function get_news_by_id(int $id): ?array
{
    global $pdo;
    $stmt = $pdo->prepare('SELECT * FROM news WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function get_news_by_slug(string $slug): ?array
{
    global $pdo;
    $stmt = $pdo->prepare('SELECT * FROM news WHERE slug = :slug LIMIT 1');
    $stmt->execute(['slug' => $slug]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function increment_news_views(int $id): void
{
    global $pdo;
    $stmt = $pdo->prepare('UPDATE news SET views = views + 1 WHERE id = :id');
    $stmt->execute(['id' => $id]);
}

function get_related_news(int $newsId, int $limit = 3): array
{
    global $pdo;
    $newsData = $pdo->prepare('SELECT category FROM news WHERE id = :id LIMIT 1');
    $newsData->execute(['id' => $newsId]);
    $news = $newsData->fetch();
    
    if (!$news) {
        return [];
    }

    $stmt = $pdo->prepare(
        'SELECT * FROM news WHERE id != :id AND category = :category AND is_active = 1 ORDER BY published_at DESC LIMIT :limit'
    );
    $stmt->bindValue(':id', $newsId, PDO::PARAM_INT);
    $stmt->bindValue(':category', $news['category'], PDO::PARAM_STR);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll() ?: [];
}

function get_featured_news(int $limit = 4): array
{
    global $pdo;
    $stmt = $pdo->prepare(
        'SELECT * FROM news WHERE is_active = 1 AND is_featured = 1 ORDER BY published_at DESC LIMIT :limit'
    );
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll() ?: [];
}

function get_recent_news(int $limit = 5): array
{
    global $pdo;
    $stmt = $pdo->prepare(
        'SELECT * FROM news WHERE is_active = 1 ORDER BY published_at DESC LIMIT :limit'
    );
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll() ?: [];
}

function get_news_gallery(int $newsId): array
{
    global $pdo;
    $stmt = $pdo->prepare('SELECT * FROM news_gallery WHERE news_id = :newsId ORDER BY sort_order, created_at');
    $stmt->execute(['newsId' => $newsId]);
    return $stmt->fetchAll() ?: [];
}

function add_gallery_image(int $newsId, string $image, ?string $caption = null, int $sortOrder = 0): bool
{
    global $pdo;
    $stmt = $pdo->prepare(
        'INSERT INTO news_gallery (news_id, image, caption, sort_order) VALUES (:newsId, :image, :caption, :sortOrder)'
    );
    return $stmt->execute([
        'newsId' => $newsId,
        'image' => $image,
        'caption' => $caption,
        'sortOrder' => $sortOrder,
    ]);
}

function delete_gallery_image(int $galleryId): void
{
    global $pdo;
    $stmt = $pdo->prepare('DELETE FROM news_gallery WHERE id = :id');
    $stmt->execute(['id' => $galleryId]);
}

function enforce_rate_limit(string $bucket, int $maxAttempts = 120, int $windowSeconds = 60): bool
{
    $bucket = preg_replace('/[^a-z0-9_-]/i', '-', $bucket) ?: 'public';
    $cacheDir = CACHE_DIR . '/rate-limit';
    if (!maybe_create_directory($cacheDir)) {
        return true;
    }

    $cacheFile = $cacheDir . '/' . $bucket . '-' . sha1(get_client_ip()) . '.json';
    $now = time();
    $hits = [];
    if (is_file($cacheFile)) {
        $decoded = json_decode((string)file_get_contents($cacheFile), true);
        if (is_array($decoded)) {
            $hits = array_values(array_filter($decoded, static fn($hit) => is_int($hit) && $hit > ($now - $windowSeconds)));
        }
    }

    if (count($hits) >= $maxAttempts) {
        http_response_code(429);
        if (!headers_sent()) {
            header('Retry-After: ' . $windowSeconds);
        }
        return false;
    }

    $hits[] = $now;
    file_put_contents($cacheFile, json_encode($hits), LOCK_EX);
    return true;
}

function gallery_categories(): array
{
    return ['Fasilitas', 'Kegiatan', 'Prestasi', 'Ekstrakurikuler', 'Akademik'];
}

function normalize_gallery_category(string $value): ?string
{
    $value = trim($value);
    if ($value === '' || in_array(strtolower($value), ['semua', 'all'], true)) {
        return null;
    }

    foreach (gallery_categories() as $category) {
        if (strcasecmp($value, $category) === 0) {
            return $category;
        }
    }

    return null;
}

function normalize_public_text($value, string $default = '', int $maxLength = 5000): string
{
    $text = trim(strip_tags((string)$value));
    $text = preg_replace('/[ \t]+/', ' ', $text) ?? $text;
    return $text === '' ? $default : mb_substr($text, 0, $maxLength);
}

function normalize_public_stat_count($value, int $default = 0): int
{
    $number = filter_var($value, FILTER_VALIDATE_INT);
    return $number === false ? $default : max(0, min(99999, (int)$number));
}

function normalize_public_stat_label($value, string $default, int $maxLength = 32): string
{
    return normalize_public_text($value, $default, $maxLength);
}

function decode_json_setting(string $name, array $default = []): array
{
    $decoded = json_decode((string)get_setting($name, ''), true);
    return is_array($decoded) ? $decoded : $default;
}

function normalize_public_image_value(string $value, string $default = ''): string
{
    $value = trim(str_replace('\\', '/', $value));
    if ($value === '') {
        return $default;
    }
    if (preg_match('/^(javascript|data|vbscript):/i', $value) || preg_match('/\.\./', $value)) {
        return $default;
    }
    return $value;
}

function public_content_image_url(string $value, string $fallback = 'assets/img/logo.png'): string
{
    $value = normalize_public_image_value($value, $fallback);
    if (preg_match('#^https?://#i', $value)) {
        return $value;
    }
    if (strpos($value, 'assets/') === 0 || strpos($value, 'uploads/') === 0) {
        return BASE_URL . '/' . ltrim($value, '/');
    }
    return build_upload_url('misc', $value);
}

function get_public_stats_settings(): array
{
    return [
        'students_count' => normalize_public_stat_count(get_setting('stat_students_count', '500'), 500),
        'achievements_year_count' => normalize_public_stat_count(get_setting('stat_achievements_year_count', '35'), 35),
        'educators_count' => normalize_public_stat_count(get_setting('stat_educators_count', '32'), 32),
        'featured_programs_count' => normalize_public_stat_count(get_setting('stat_featured_programs_count', '12'), 12),
        'teachers_count' => normalize_public_stat_count(get_setting('stat_teachers_count', '24'), 24),
        'staff_count' => normalize_public_stat_count(get_setting('stat_staff_count', '8'), 8),
        'accreditation_label' => normalize_public_stat_label(get_setting('stat_accreditation_label', 'A'), 'A', 16),
        'professional_label' => normalize_public_stat_label(get_setting('stat_professional_label', 'A+'), 'A+', 16),
    ];
}

function normalize_whatsapp_number(string $value)
{
    $value = trim($value);
    if ($value === '') {
        return null;
    }
    if (preg_match('/[a-z]/i', $value)) {
        return false;
    }
    $digits = preg_replace('/\D+/', '', $value);
    if ($digits === '') {
        return false;
    }
    if (strpos($digits, '0') === 0) {
        $digits = '62' . substr($digits, 1);
    }
    return strlen($digits) >= 8 && strlen($digits) <= 16 ? $digits : false;
}

function normalize_public_url(string $value)
{
    $value = trim($value);
    if ($value === '') {
        return null;
    }
    if (preg_match('/^(javascript|data|vbscript):/i', $value) || preg_match('/[\x00-\x1F\x7F]/', $value)) {
        return false;
    }
    if (!preg_match('#^https?://#i', $value)) {
        $value = 'https://' . ltrim($value, '/');
    }
    return filter_var($value, FILTER_VALIDATE_URL) ? $value : false;
}

function normalize_social_link(string $value, string $platform)
{
    $value = trim($value);
    if ($value === '') {
        return null;
    }
    if (preg_match('#^https?://#i', $value)) {
        return normalize_public_url($value);
    }
    $username = ltrim($value, '@');
    if (!preg_match('/^[A-Za-z0-9._-]{1,80}$/', $username)) {
        return false;
    }
    $baseUrls = [
        'instagram' => 'https://www.instagram.com/',
        'facebook' => 'https://www.facebook.com/',
        'tiktok' => 'https://www.tiktok.com/@',
        'youtube' => 'https://www.youtube.com/@',
    ];
    $platform = strtolower($platform);
    return isset($baseUrls[$platform]) ? $baseUrls[$platform] . rawurlencode($username) : false;
}

function normalize_ppdb_status(string $value): string
{
    $value = strtolower(trim($value));
    return in_array($value, ['open', 'upcoming', 'closed'], true) ? $value : 'open';
}

function normalize_ppdb_date(string $value, string $default = ''): string
{
    $value = trim($value);
    if ($value === '') {
        return $default;
    }
    foreach (['Y-m-d', 'd/m/Y', 'd-m-Y'] as $format) {
        $date = DateTimeImmutable::createFromFormat('!' . $format, $value);
        if ($date instanceof DateTimeImmutable && $date->format($format) === $value) {
            return $date->format('Y-m-d');
        }
    }
    return $default;
}

function get_ppdb_settings(): array
{
    $year = normalize_public_stat_count(get_setting('ppdb_year', (string)date('Y')), (int)date('Y'));
    if ($year < 2024 || $year > 2100) {
        $year = (int)date('Y');
    }
    $whatsapp = normalize_whatsapp_number((string)get_setting('ppdb_whatsapp_number', '6285692890015'));
    return [
        'status' => normalize_ppdb_status((string)get_setting('ppdb_status', 'open')),
        'year' => (string)$year,
        'start_date' => normalize_ppdb_date((string)get_setting('ppdb_start_date', ''), $year . '-01-01'),
        'end_date' => normalize_ppdb_date((string)get_setting('ppdb_end_date', ''), $year . '-07-31'),
        'whatsapp_number' => $whatsapp === false || $whatsapp === null ? '6285692890015' : $whatsapp,
        'whatsapp_message' => normalize_public_text(get_setting('ppdb_whatsapp_message', 'Halo, saya ingin mendaftar PPDB SD Cahaya Harapan Bekasi.'), 'Halo, saya ingin mendaftar PPDB SD Cahaya Harapan Bekasi.', 500),
        'description' => normalize_public_text(get_setting('ppdb_description', ''), '', 700),
    ];
}

function feedback_initials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    $initials = '';
    foreach ($parts as $part) {
        $initials .= strtoupper(substr($part, 0, 1));
        if (strlen($initials) >= 2) {
            break;
        }
    }
    return $initials !== '' ? $initials : 'OT';
}

function feedback_avatar_url(?string $email, int $size = 160): ?string
{
    $email = strtolower(trim((string)$email));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return null;
    }
    $size = max(40, min(240, $size));
    return 'https://www.gravatar.com/avatar/' . md5($email) . '?s=' . $size . '&d=initials';
}

function default_leadership_team(): array
{
    return [];
}

function get_school_profile_settings(): array
{
    $missions = decode_json_setting('about_missions', [
        'Menyelenggarakan pendidikan yang menumbuhkan iman, karakter, dan prestasi.',
        'Membangun lingkungan belajar yang aman, disiplin, kreatif, dan penuh kasih.',
        'Menguatkan kerja sama sekolah, orang tua, dan masyarakat.',
    ]);
    $team = decode_json_setting('about_leadership_team', default_leadership_team());
    $team = array_values(array_filter(array_map(static function ($item): array {
        $item = is_array($item) ? $item : [];
        return [
            'active' => !isset($item['active']) || filter_var($item['active'], FILTER_VALIDATE_BOOLEAN),
            'name' => normalize_public_text($item['name'] ?? '', '', 120),
            'role' => normalize_public_text($item['role'] ?? '', '', 140),
            'description' => normalize_public_text($item['description'] ?? '', '', 500),
            'email' => normalize_public_text($item['email'] ?? '', '', 160),
            'phone' => normalize_public_text($item['phone'] ?? '', '', 60),
            'image' => normalize_public_image_value((string)($item['image'] ?? ''), 'assets/img/logo.png'),
        ];
    }, $team), static fn($item) => $item['active'] && $item['name'] !== '' && $item['role'] !== ''));

    return [
        'principal' => [
            'badge' => normalize_public_text(get_setting('about_principal_badge', 'Sambutan Kepala Sekolah'), 'Sambutan Kepala Sekolah', 80),
            'title' => normalize_public_text(get_setting('about_principal_title', 'Iman Kuat, Karakter Hebat, Prestasi Bermartabat'), 'Iman Kuat, Karakter Hebat, Prestasi Bermartabat', 180),
            'message' => normalize_public_text(get_setting('about_principal_message', ''), '', 5000),
            'name' => normalize_public_text(get_setting('about_principal_name', 'Paulus Ngabur, S.Pd'), 'Paulus Ngabur, S.Pd', 120),
            'role' => normalize_public_text(get_setting('about_principal_role', 'Kepala Sekolah'), 'Kepala Sekolah', 120),
            'image' => normalize_public_image_value((string)get_setting('about_principal_image', 'assets/img/tim-kep/kepsek.jpg?v=1'), 'assets/img/tim-kep/kepsek.jpg?v=1'),
        ],
        'vision' => normalize_public_text(get_setting('about_vision', 'Menjadi sekolah Katolik yang unggul dalam iman, karakter, dan prestasi.'), 'Menjadi sekolah Katolik yang unggul dalam iman, karakter, dan prestasi.', 1000),
        'missions' => array_values(array_filter(array_map(static fn($item) => normalize_public_text($item, '', 700), $missions))),
        'leadership_team' => $team,
    ];
}

function ensure_agendas_table(): void
{
    global $pdo;
    $pdo->exec("CREATE TABLE IF NOT EXISTS `agendas` (`id` INT UNSIGNED NOT NULL AUTO_INCREMENT, `title` VARCHAR(255) NOT NULL, `slug` VARCHAR(255) NOT NULL, `event_date` DATE NOT NULL, `event_time` VARCHAR(50) NOT NULL DEFAULT '', `location` VARCHAR(180) NOT NULL DEFAULT '', `contact` VARCHAR(180) NOT NULL DEFAULT '', `summary` TEXT DEFAULT NULL, `description` TEXT DEFAULT NULL, `points` TEXT DEFAULT NULL, `closing` TEXT DEFAULT NULL, `views` INT UNSIGNED NOT NULL DEFAULT 0, `is_active` TINYINT(1) NOT NULL DEFAULT 1, `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, PRIMARY KEY (`id`), UNIQUE KEY `agendas_slug_unique` (`slug`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

function ensure_unique_agenda_slug(string $baseSlug, ?int $excludeId = null): string
{
    global $pdo;
    ensure_agendas_table();
    $slug = $baseSlug !== '' ? $baseSlug : 'agenda';
    $counter = 1;
    while (true) {
        $query = 'SELECT id FROM agendas WHERE slug = :slug';
        $params = ['slug' => $slug];
        if ($excludeId !== null) {
            $query .= ' AND id != :excludeId';
            $params['excludeId'] = $excludeId;
        }
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        if ($stmt->rowCount() === 0) {
            return $slug;
        }
        $slug = $baseSlug . '-' . (++$counter);
    }
}

function normalize_agenda_points(string $points): string
{
    return implode("\n", array_values(array_filter(array_map(static fn($line) => trim(strip_tags($line)), preg_split('/\R+/', $points) ?: []))));
}

function agenda_points_to_array(?string $points): array
{
    return array_values(array_filter(array_map('trim', preg_split('/\R+/', (string)$points) ?: [])));
}

function get_default_agendas(): array
{
    return [
        ['id' => 0, 'title' => 'Pertemuan Orang Tua Siswa', 'slug' => 'pertemuan-orang-tua-siswa', 'event_date' => date('Y-m-d'), 'event_time' => '08.00 WIB', 'location' => 'Aula Sekolah', 'contact' => 'Tata Usaha', 'summary' => 'Informasi akademik dan agenda sekolah.', 'description' => '', 'points' => '', 'closing' => '', 'views' => 0, 'is_active' => 1],
    ];
}

function get_public_agendas(int $limit = 3): array
{
    global $pdo;
    try {
        ensure_agendas_table();
        $stmt = $pdo->prepare('SELECT * FROM agendas WHERE is_active = 1 ORDER BY event_date ASC, id ASC LIMIT :limit');
        $stmt->bindValue(':limit', max(1, min(50, $limit)), PDO::PARAM_INT);
        $stmt->execute();
        $items = $stmt->fetchAll();
        return $items ?: array_slice(get_default_agendas(), 0, $limit);
    } catch (Throwable $exception) {
        log_exception($exception);
        return array_slice(get_default_agendas(), 0, $limit);
    }
}

function get_agenda_by_slug(string $slug): ?array
{
    global $pdo;
    try {
        ensure_agendas_table();
        $stmt = $pdo->prepare('SELECT * FROM agendas WHERE slug = :slug AND is_active = 1 LIMIT 1');
        $stmt->execute(['slug' => $slug]);
        return $stmt->fetch() ?: null;
    } catch (Throwable $exception) {
        log_exception($exception);
        foreach (get_default_agendas() as $agenda) {
            if ($agenda['slug'] === $slug) {
                return $agenda;
            }
        }
        return null;
    }
}

function increment_agenda_views(string $slug): void
{
    global $pdo;
    try {
        ensure_agendas_table();
        $stmt = $pdo->prepare('UPDATE agendas SET views = views + 1 WHERE slug = :slug');
        $stmt->execute(['slug' => $slug]);
    } catch (Throwable $exception) {
        log_exception($exception);
    }
}

function get_public_achievements(int $limit = 24): array
{
    global $pdo;
    try {
        $stmt = $pdo->prepare('SELECT * FROM achievements ORDER BY created_at DESC, id DESC LIMIT :limit');
        $stmt->bindValue(':limit', max(1, min(100, $limit)), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    } catch (Throwable $exception) {
        log_exception($exception);
        return [];
    }
}

function get_public_announcements(int $limit = 20): array
{
    global $pdo;
    try {
        $stmt = $pdo->prepare('SELECT * FROM announcements WHERE status = 1 ORDER BY published_at DESC, id DESC LIMIT :limit');
        $stmt->bindValue(':limit', max(1, min(100, $limit)), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    } catch (Throwable $exception) {
        log_exception($exception);
        return [];
    }
}

function get_public_announcement_by_id(int $id): ?array
{
    global $pdo;
    try {
        $stmt = $pdo->prepare('SELECT * FROM announcements WHERE id = :id AND status = 1 LIMIT 1');
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    } catch (Throwable $exception) {
        log_exception($exception);
        return null;
    }
}

function normalize_school_program_type(string $type): string
{
    $type = strtolower(trim($type));
    return in_array($type, ['kegiatan', 'ekstrakurikuler'], true) ? $type : 'kegiatan';
}

function school_program_type_label(string $type): string
{
    return normalize_school_program_type($type) === 'ekstrakurikuler' ? 'Ekstrakurikuler' : 'Kegiatan';
}

function ensure_school_programs_table(): void
{
    global $pdo;
    $pdo->exec("CREATE TABLE IF NOT EXISTS `school_programs` (`id` INT UNSIGNED NOT NULL AUTO_INCREMENT, `type` VARCHAR(32) NOT NULL DEFAULT 'kegiatan', `title` VARCHAR(180) NOT NULL, `category` VARCHAR(80) NOT NULL DEFAULT '', `icon` VARCHAR(80) NOT NULL DEFAULT 'fa-solid fa-star', `description` TEXT DEFAULT NULL, `image` VARCHAR(255) DEFAULT NULL, `sort_order` INT NOT NULL DEFAULT 0, `is_active` TINYINT(1) NOT NULL DEFAULT 1, `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, PRIMARY KEY (`id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

function get_default_school_programs(string $type): array
{
    $type = normalize_school_program_type($type);
    return [
        ['id' => 0, 'type' => $type, 'title' => $type === 'ekstrakurikuler' ? 'Pramuka Siaga' : 'Belajar Aktif di Kelas', 'category' => $type === 'ekstrakurikuler' ? 'Pramuka' : 'Pembelajaran', 'icon' => 'fa-solid fa-star', 'description' => 'Program sekolah untuk menumbuhkan karakter, kreativitas, dan prestasi siswa.', 'image' => 'assets/img/logo.png', 'sort_order' => 1, 'is_active' => 1],
    ];
}

function get_public_school_programs(string $type, int $limit = 24): array
{
    global $pdo;
    $type = normalize_school_program_type($type);
    try {
        ensure_school_programs_table();
        $stmt = $pdo->prepare('SELECT * FROM school_programs WHERE type = :type AND is_active = 1 ORDER BY sort_order ASC, id ASC LIMIT :limit');
        $stmt->bindValue(':type', $type, PDO::PARAM_STR);
        $stmt->bindValue(':limit', max(1, min(100, $limit)), PDO::PARAM_INT);
        $stmt->execute();
        $items = $stmt->fetchAll();
        return $items ?: array_slice(get_default_school_programs($type), 0, $limit);
    } catch (Throwable $exception) {
        log_exception($exception);
        return array_slice(get_default_school_programs($type), 0, $limit);
    }
}

function school_program_icon_options(): array
{
    return [
        'fa-solid fa-star' => 'Umum',
        'fa-solid fa-calendar-check' => 'Kegiatan Sekolah',
        'fa-solid fa-language' => 'Mandarin / Bahasa',
        'fa-solid fa-campground' => 'Pramuka',
        'fa-solid fa-futbol' => 'Sepak Bola / Futsal',
        'fa-solid fa-person-running' => 'Olahraga',
        'fa-solid fa-palette' => 'Seni',
        'fa-solid fa-music' => 'Musik',
        'fa-solid fa-book-open-reader' => 'Literasi',
        'fa-solid fa-laptop-code' => 'Komputer / Coding',
        'fa-solid fa-hands-praying' => 'Rohani',
    ];
}

function normalize_school_program_icon(string $icon): string
{
    return array_key_exists($icon, school_program_icon_options()) ? $icon : 'fa-solid fa-star';
}

function normalize_staff_status($value): int
{
    return in_array(strtolower((string)$value), ['1', 'aktif', 'active', 'on', 'true', 'yes'], true) ? 1 : 0;
}

function normalize_optional_email(string $value)
{
    $value = trim($value);
    if ($value === '') {
        return null;
    }
    return filter_var($value, FILTER_VALIDATE_EMAIL) ? $value : false;
}

function get_related_news_payload(int $newsId, int $limit = 3): array
{
    global $pdo;
    $limit = max(1, min(10, $limit));
    try {
        $categoryStatement = $pdo->prepare('SELECT category FROM news WHERE id = :id LIMIT 1');
        $categoryStatement->execute(['id' => $newsId]);
        $category = (string)($categoryStatement->fetchColumn() ?: '');
        $items = [];
        if ($category !== '') {
            $statement = $pdo->prepare('SELECT * FROM news WHERE category = :category AND id != :id AND is_active = 1 ORDER BY published_at DESC, created_at DESC LIMIT :limit');
            $statement->bindValue(':category', $category, PDO::PARAM_STR);
            $statement->bindValue(':id', $newsId, PDO::PARAM_INT);
            $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
            $statement->execute();
            $items = $statement->fetchAll() ?: [];
        }
        if (!empty($items)) {
            return ['items' => $items, 'is_fallback' => false];
        }
        $fallback = $pdo->prepare('SELECT * FROM news WHERE id != :id AND is_active = 1 ORDER BY published_at DESC, created_at DESC LIMIT :limit');
        $fallback->bindValue(':id', $newsId, PDO::PARAM_INT);
        $fallback->bindValue(':limit', $limit, PDO::PARAM_INT);
        $fallback->execute();
        return ['items' => $fallback->fetchAll() ?: [], 'is_fallback' => true];
    } catch (Throwable $exception) {
        log_exception($exception);
        return ['items' => [], 'is_fallback' => true];
    }
}