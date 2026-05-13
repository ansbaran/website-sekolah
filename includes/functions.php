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
    return preg_replace('/-+/', '-', $fileName);
}

function unique_file_name(string $fileName): string
{
    return time() . '_' . bin2hex(random_bytes(6)) . '_' . sanitize_file_name($fileName);
}

function build_upload_path(string $subDir, string $fileName): string
{
    $uploadDir = UPLOAD_BASE . '/' . trim($subDir, '/');
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    return $uploadDir . '/' . $fileName;
}

function build_upload_url(string $subDir, string $fileName): string
{
    return rtrim(UPLOAD_URL, '/') . '/' . trim($subDir, '/') . '/' . $fileName;
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

    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
    header("Content-Security-Policy: default-src 'self'; script-src 'self' https://unpkg.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdnjs.cloudflare.com https://unpkg.com; img-src 'self' data:; font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com; connect-src 'self'; frame-ancestors 'self'; base-uri 'self';");
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
        $error = 'Terjadi kesalahan saat mengunggah file.';
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

    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, ALLOWED_IMAGE_EXT, true)) {
        $error = 'Ekstensi file tidak valid.';
        return false;
    }

    if (preg_match('/\.(php|phtml|phps|php3|php4|php5|cgi|pl|asp|aspx)$/i', $file['name'])) {
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
        return true;
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
    if ($path !== '' && file_exists($path) && is_file($path)) {
        @unlink($path);
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
    $stmt = $pdo->prepare('INSERT INTO settings (name, value, updated_at) VALUES (:name, :value, NOW()) ON DUPLICATE KEY UPDATE value = :value, updated_at = NOW()');
    return $stmt->execute(['name' => $name, 'value' => $value]);
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

