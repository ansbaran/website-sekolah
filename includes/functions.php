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

function format_file_size_mb(int $bytes): string
{
    $mb = $bytes / 1024 / 1024;
    return rtrim(rtrim(number_format($mb, 1, '.', ''), '0'), '.') . 'MB';
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
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.tailwindcss.com https://cdnjs.cloudflare.com https://unpkg.com https://www.googletagmanager.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdnjs.cloudflare.com https://unpkg.com; img-src 'self' data: https: https://*.google-analytics.com https://www.googletagmanager.com; font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com; connect-src 'self' https://*.google-analytics.com https://*.analytics.google.com https://www.googletagmanager.com; frame-src 'self' https://www.google.com https://maps.google.com; child-src 'self' https://www.google.com https://maps.google.com; frame-ancestors 'self'; base-uri 'self'; form-action 'self';");
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

function image_upload_policy(string $policy = 'default'): array
{
    $base = [
        'max_size' => MAX_IMAGE_SIZE,
        'min_width' => UPLOAD_DIMENSION_MIN_WIDTH,
        'min_height' => UPLOAD_DIMENSION_MIN_HEIGHT,
        'max_width' => MAX_IMAGE_WIDTH,
        'max_height' => MAX_IMAGE_HEIGHT,
        'max_pixels' => UPLOAD_MAX_PIXELS,
        'resize_max_long_edge' => UPLOAD_GENERAL_MAX_LONG_EDGE,
    ];

    $policies = [
        'default' => [],
        'media' => [],
        'slider' => [
            'min_width' => 1600,
            'min_height' => 900,
            'aspect_ratio' => 16 / 9,
            'aspect_tolerance' => 0.05,
            'resize_max_long_edge' => UPLOAD_SLIDER_MAX_LONG_EDGE,
            'min_error' => 'Gambar terlalu kecil. Gunakan minimal 1600 x 900 px.',
            'aspect_error' => 'Rasio gambar slider harus mendekati 16:9.',
        ],
        'news_featured' => [
            'min_width' => 1200,
            'min_height' => 675,
        ],
        'news_thumbnail' => [
            'min_width' => 960,
            'min_height' => 600,
        ],
        'gallery' => [
            'min_long_edge' => 1200,
            'resize_max_long_edge' => UPLOAD_GALLERY_MAX_LONG_EDGE,
            'long_edge_error' => 'Resolusi gambar galeri terlalu kecil. Gunakan sisi panjang minimal 1200 px.',
        ],
        'staff_portrait' => [
            'min_width' => 900,
            'min_height' => 1125,
            'orientation' => 'portrait',
            'resize_max_long_edge' => UPLOAD_GENERAL_MAX_LONG_EDGE,
            'min_error' => 'Foto terlalu kecil. Gunakan minimal 900 x 1125 px.',
            'orientation_error' => 'Gunakan foto portrait. Rekomendasi 1200 x 1500 px.',
        ],
        'achievement' => [
            'min_width' => 1200,
            'min_height' => 900,
        ],
        'program' => [
            'min_width' => 840,
            'min_height' => 552,
        ],
        'profile_portrait' => [
            'min_width' => 900,
            'min_height' => 1125,
            'orientation' => 'portrait',
            'resize_max_long_edge' => UPLOAD_GENERAL_MAX_LONG_EDGE,
            'min_error' => 'Foto terlalu kecil. Gunakan minimal 900 x 1125 px.',
            'orientation_error' => 'Gunakan foto portrait untuk profil sekolah.',
        ],
        'avatar' => [
            'max_size' => PROFILE_AVATAR_MAX_SIZE,
            'min_width' => 400,
            'min_height' => 400,
            'resize_max_long_edge' => 800,
            'min_error' => 'Foto terlalu kecil. Gunakan minimal 400 x 400 px.',
        ],
    ];

    return array_merge($base, $policies[$policy] ?? []);
}

function validate_image_upload(array $file, ?string &$error = null, ?int $maxSize = null, array $options = []): bool
{
    $options = array_merge(image_upload_policy('default'), $options);
    $maxSize = $maxSize ?? (int)($options['max_size'] ?? MAX_IMAGE_SIZE);

    if (!isset($file['error'])) {
        $error = 'Silakan pilih file gambar.';
        return false;
    }

    if ($file['error'] === UPLOAD_ERR_NO_FILE) {
        $error = 'Silakan pilih file gambar.';
        return false;
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error = match ($file['error']) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Ukuran file maksimal ' . format_file_size_mb($maxSize) . '.',
            UPLOAD_ERR_PARTIAL => 'Upload belum selesai. Silakan pilih file dan coba lagi.',
            default => 'Terjadi kesalahan saat mengunggah file.',
        };
        return false;
    }

    if ($file['size'] > $maxSize) {
        $error = 'Ukuran file maksimal ' . format_file_size_mb($maxSize) . '.';
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
    $minWidth = (int)($options['min_width'] ?? UPLOAD_DIMENSION_MIN_WIDTH);
    $minHeight = (int)($options['min_height'] ?? UPLOAD_DIMENSION_MIN_HEIGHT);
    if ($width < $minWidth || $height < $minHeight) {
        $error = (string)($options['min_error'] ?? ('Resolusi gambar terlalu kecil. Minimal ' . $minWidth . 'x' . $minHeight . ' piksel.'));
        return false;
    }

    $minLongEdge = (int)($options['min_long_edge'] ?? 0);
    if ($minLongEdge > 0 && max($width, $height) < $minLongEdge) {
        $error = (string)($options['long_edge_error'] ?? ('Resolusi gambar terlalu kecil. Gunakan sisi panjang minimal ' . $minLongEdge . ' px.'));
        return false;
    }

    $orientation = (string)($options['orientation'] ?? '');
    if ($orientation === 'portrait' && $height < $width) {
        $error = (string)($options['orientation_error'] ?? 'Gunakan foto portrait.');
        return false;
    }

    $aspectRatio = (float)($options['aspect_ratio'] ?? 0);
    if ($aspectRatio > 0 && $height > 0) {
        $actualRatio = $width / $height;
        $tolerance = (float)($options['aspect_tolerance'] ?? 0.05);
        if (abs($actualRatio - $aspectRatio) / $aspectRatio > $tolerance) {
            $error = (string)($options['aspect_error'] ?? 'Rasio gambar tidak sesuai.');
            return false;
        }
    }

    $maxPixels = (int)($options['max_pixels'] ?? UPLOAD_MAX_PIXELS);
    if ($maxPixels > 0 && ($width * $height) > $maxPixels) {
        $error = 'Resolusi gambar terlalu besar. Gunakan gambar dengan total piksel lebih kecil.';
        return false;
    }

    $maxWidth = (int)($options['max_width'] ?? MAX_IMAGE_WIDTH);
    $maxHeight = (int)($options['max_height'] ?? MAX_IMAGE_HEIGHT);
    if ($width > $maxWidth || $height > $maxHeight) {
        $error = 'Resolusi gambar terlalu besar. Maksimal ' . $maxWidth . 'x' . $maxHeight . ' piksel.';
        return false;
    }

    return true;
}

function optimize_image(string $sourcePath, string $targetPath, string $mimeType, int $maxLongEdge = 0): bool
{
    $quality = $mimeType === 'image/webp' ? 84 : 85;
    $imageInfo = @getimagesize($sourcePath);
    $resize = false;
    $newWidth = 0;
    $newHeight = 0;

    if ($imageInfo !== false && $maxLongEdge > 0) {
        [$width, $height] = $imageInfo;
        $longEdge = max((int)$width, (int)$height);
        if ($width > 0 && $height > 0 && $longEdge > $maxLongEdge) {
            $scale = $maxLongEdge / $longEdge;
            $newWidth = max(1, (int)round($width * $scale));
            $newHeight = max(1, (int)round($height * $scale));
            $resize = true;
        }
    }

    switch ($mimeType) {
        case 'image/jpeg':
            $image = @imagecreatefromjpeg($sourcePath);
            if (!$image) {
                return false;
            }
            if ($resize) {
                $canvas = imagecreatetruecolor($newWidth, $newHeight);
                imagecopyresampled($canvas, $image, 0, 0, 0, 0, $newWidth, $newHeight, imagesx($image), imagesy($image));
                $result = imagejpeg($canvas, $targetPath, $quality);
                imagedestroy($canvas);
            } else {
                $result = imagejpeg($image, $targetPath, $quality);
            }
            imagedestroy($image);
            return $result;
        case 'image/png':
            $image = @imagecreatefrompng($sourcePath);
            if (!$image) {
                return false;
            }
            if ($resize) {
                $canvas = imagecreatetruecolor($newWidth, $newHeight);
                imagealphablending($canvas, false);
                imagesavealpha($canvas, true);
                $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
                imagefilledrectangle($canvas, 0, 0, $newWidth, $newHeight, $transparent);
                imagecopyresampled($canvas, $image, 0, 0, 0, 0, $newWidth, $newHeight, imagesx($image), imagesy($image));
                $result = imagepng($canvas, $targetPath, 6);
                imagedestroy($canvas);
            } else {
                $result = imagepng($image, $targetPath, 6);
            }
            imagedestroy($image);
            return $result;
        case 'image/webp':
            $image = @imagecreatefromwebp($sourcePath);
            if (!$image) {
                return false;
            }
            if ($resize) {
                $canvas = imagecreatetruecolor($newWidth, $newHeight);
                imagealphablending($canvas, false);
                imagesavealpha($canvas, true);
                $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
                imagefilledrectangle($canvas, 0, 0, $newWidth, $newHeight, $transparent);
                imagecopyresampled($canvas, $image, 0, 0, 0, 0, $newWidth, $newHeight, imagesx($image), imagesy($image));
                $result = imagewebp($canvas, $targetPath, $quality);
                imagedestroy($canvas);
            } else {
                $result = imagewebp($image, $targetPath, $quality);
            }
            imagedestroy($image);
            return $result;
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
    $quality = $mimeType === 'image/webp' ? 84 : 85;

    switch ($mimeType) {
        case 'image/jpeg':
            $image = @imagecreatefromjpeg($sourcePath);
            if (!$image) {
                return false;
            }
            imagejpeg($image, $sourcePath, $quality);
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
            imagewebp($image, $sourcePath, $quality);
            imagedestroy($image);
            return true;
        default:
            return false;
    }
}

function upload_image(array $file, string $subDir, ?string &$error = null, array $options = []): ?string
{
    $subDir = normalize_upload_subdir($subDir);
    $options = array_merge(image_upload_policy('default'), $options);
    if (!validate_image_upload($file, $error, (int)($options['max_size'] ?? MAX_IMAGE_SIZE), $options)) {
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
    if (!optimize_image($targetPath, $targetPath, $mimeType, (int)($options['resize_max_long_edge'] ?? 0))) {
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

function user_initials(string $name): string
{
    $name = trim(strip_tags($name));
    if ($name === '') {
        return 'U';
    }

    $parts = preg_split('/\s+/', $name) ?: [];
    $letters = '';
    foreach ($parts as $part) {
        $part = trim($part);
        if ($part !== '') {
            $letters .= substr($part, 0, 1);
        }

        if (strlen($letters) >= 2) {
            break;
        }
    }

    return strtoupper($letters ?: substr($name, 0, 1));
}

function normalize_profile_photo_path(?string $path): ?string
{
    $path = ltrim(str_replace('\\', '/', trim((string)$path)), '/');
    if ($path === '') {
        return null;
    }

    if (strpos($path, 'uploads/profile/') === 0) {
        $fileName = basename($path);
    } elseif (strpos($path, '/') === false) {
        $fileName = basename($path);
    } else {
        return null;
    }

    if ($fileName === '' || $fileName !== sanitize_file_name($fileName)) {
        return null;
    }

    if (!preg_match('/\.(jpe?g|png|webp)$/i', $fileName)) {
        return null;
    }

    return 'uploads/profile/' . $fileName;
}

function user_profile_photo_url(array $user): ?string
{
    $path = normalize_profile_photo_path($user['profile_photo'] ?? null);
    if ($path === null) {
        return null;
    }

    $filePath = managed_profile_photo_file_path($path);
    if ($filePath === null || !is_file($filePath)) {
        return null;
    }

    return BASE_URL . '/uploads/profile/' . rawurlencode(basename($path));
}

function user_avatar_html(array $user, string $size = 'small', string $extraClass = ''): string
{
    $allowedSizes = ['small', 'medium', 'large'];
    $size = in_array($size, $allowedSizes, true) ? $size : 'small';
    $classes = trim('user-avatar user-avatar--' . $size . ' ' . $extraClass);
    $name = (string)($user['name'] ?? '');
    $photoUrl = user_profile_photo_url($user);

    if ($photoUrl !== null) {
        return '<span class="' . escape($classes) . '"><img src="' . escape($photoUrl) . '" alt="' . escape($name !== '' ? 'Foto profil ' . $name : 'Foto profil') . '" loading="lazy"></span>';
    }

    return '<span class="' . escape($classes) . '" aria-hidden="true">' . escape(user_initials($name)) . '</span>';
}

function managed_profile_photo_file_path(?string $path): ?string
{
    $relativePath = normalize_profile_photo_path($path);
    if ($relativePath === null) {
        return null;
    }

    $target = BASE_PATH . '/' . $relativePath;
    $profileDir = realpath(UPLOAD_BASE . '/profile');
    $targetDir = realpath(dirname($target));
    if ($profileDir === false || $targetDir === false || $targetDir !== $profileDir) {
        return null;
    }

    return $target;
}

function resize_profile_photo(string $targetPath, string $mimeType, int $maxDimension = 800): bool
{
    if (!function_exists('imagecopyresampled')) {
        return optimize_image($targetPath, $targetPath, $mimeType);
    }

    $imageInfo = @getimagesize($targetPath);
    if ($imageInfo === false) {
        return false;
    }

    [$width, $height] = $imageInfo;
    if ($width <= 0 || $height <= 0) {
        return false;
    }

    switch ($mimeType) {
        case 'image/jpeg':
            $source = @imagecreatefromjpeg($targetPath);
            break;
        case 'image/png':
            $source = @imagecreatefrompng($targetPath);
            break;
        case 'image/webp':
            $source = @imagecreatefromwebp($targetPath);
            break;
        default:
            return false;
    }

    if (!$source) {
        return false;
    }

    $scale = min(1, $maxDimension / max($width, $height));
    $newWidth = max(1, (int)round($width * $scale));
    $newHeight = max(1, (int)round($height * $scale));
    $canvas = imagecreatetruecolor($newWidth, $newHeight);

    if ($mimeType === 'image/png' || $mimeType === 'image/webp') {
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
        imagefilledrectangle($canvas, 0, 0, $newWidth, $newHeight, $transparent);
    }

    imagecopyresampled($canvas, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

    $result = match ($mimeType) {
        'image/jpeg' => imagejpeg($canvas, $targetPath, 84),
        'image/png' => imagepng($canvas, $targetPath, 6),
        'image/webp' => imagewebp($canvas, $targetPath, 84),
        default => false,
    };

    imagedestroy($source);
    imagedestroy($canvas);

    return $result;
}

function upload_profile_photo(array $file, int $userId, ?string &$error = null): ?string
{
    if ($userId <= 0) {
        $error = 'Akun pengguna tidak valid.';
        return null;
    }

    if (!validate_image_upload($file, $error, PROFILE_AVATAR_MAX_SIZE, image_upload_policy('avatar'))) {
        return null;
    }

    $mimeType = mime_content_type($file['tmp_name']) ?: '';
    $extension = match ($mimeType) {
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        default => '',
    };

    if ($extension === '') {
        $error = 'Format gambar tidak didukung. Gunakan JPG, PNG, atau WEBP.';
        return null;
    }

    $targetDir = build_upload_path('profile', '');
    if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
        $error = 'Gagal membuat direktori foto profil.';
        return null;
    }

    $storedName = 'user-' . $userId . '-' . bin2hex(random_bytes(6)) . '.' . $extension;
    $targetPath = rtrim($targetDir, '/\\') . DIRECTORY_SEPARATOR . $storedName;

    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        $error = 'Gagal menyimpan foto profil.';
        return null;
    }

    $storedMimeType = mime_content_type($targetPath) ?: $mimeType;
    if (!resize_profile_photo($targetPath, $storedMimeType)) {
        optimize_image($targetPath, $targetPath, $storedMimeType);
    }
    strip_image_metadata($targetPath, $storedMimeType);

    return 'uploads/profile/' . $storedName;
}

function admin_role_options(): array
{
    return [
        'super_admin' => 'Super Admin',
        'admin' => 'Admin Sekolah',
        'kepala_sekolah' => 'Kepala Sekolah',
        'guru_staff' => 'Guru / Staff',
        'editor' => 'Editor',
        'operator' => 'Operator',
    ];
}

function admin_role_label(string $role): string
{
    return admin_role_options()[$role] ?? ucfirst(str_replace('_', ' ', $role));
}

function admin_role_description(string $role): string
{
    $descriptions = [
        'super_admin' => 'Akses sistem tingkat tertinggi.',
        'admin' => 'Akses administrasi utama sekolah.',
        'kepala_sekolah' => 'Akses dashboard dan modul yang diberikan.',
        'guru_staff' => 'Akses terbatas sesuai tugas dan hak akses tambahan.',
        'editor' => 'Fokus pada pengelolaan dan publikasi konten.',
        'operator' => 'Fokus pada upload dan pengelolaan media sesuai akses.',
    ];

    return $descriptions[$role] ?? 'Akses mengikuti pengaturan role.';
}

function admin_permission_definitions(): array
{
    return [
        'publish' => [
            'label' => 'Kelola Konten Publik',
            'description' => 'Membuat, mengedit, dan menerbitkan konten yang diizinkan.',
            'group' => 'Konten',
            'sensitive' => '',
        ],
        'upload' => [
            'label' => 'Upload & Kelola Media',
            'description' => 'Mengunggah dan mengelola gambar atau media pada modul yang diizinkan.',
            'group' => 'Konten',
            'sensitive' => '',
        ],
        'delete' => [
            'label' => 'Hapus Konten',
            'description' => 'Menghapus konten pada modul yang dapat diakses.',
            'group' => 'Konten',
            'sensitive' => 'Permission ini memberikan akses sensitif.',
        ],
        'backup' => [
            'label' => 'Backup & Log Sistem',
            'description' => 'Mengakses fitur backup serta informasi sistem yang relevan.',
            'group' => 'Sistem',
            'sensitive' => 'Permission ini memberikan akses sensitif.',
        ],
        'maintenance' => [
            'label' => 'Maintenance Sistem',
            'description' => 'Mengakses fitur pemeliharaan dan konfigurasi teknis sistem.',
            'group' => 'Sistem',
            'sensitive' => 'Permission ini memberikan akses sensitif.',
        ],
        'manage_user' => [
            'label' => 'Kelola Akun Pengguna',
            'description' => 'Menambah, mengedit, mengaktifkan, dan mengatur hak akses pengguna.',
            'group' => 'Pengguna',
            'sensitive' => 'Pengguna dapat mengelola akun dan hak akses pengguna lain.',
        ],
    ];
}

function admin_permission_options(): array
{
    return array_map(static fn(array $definition): string => $definition['label'], admin_permission_definitions());
}

function admin_permission_label(string $permission): string
{
    return admin_permission_definitions()[$permission]['label'] ?? $permission;
}

function admin_permission_description(string $permission): string
{
    return admin_permission_definitions()[$permission]['description'] ?? '';
}

function admin_permission_grouped_options(): array
{
    $groups = [];
    foreach (admin_permission_definitions() as $permission => $definition) {
        $groups[$definition['group']][$permission] = $definition;
    }

    return $groups;
}

function admin_permission_summary_label(int $grantCount): string
{
    return $grantCount > 0 ? $grantCount . ' akses tambahan' : 'Akses sesuai role';
}

function admin_role_base_permissions(string $role): array
{
    $permissions = [
        'super_admin' => ['delete', 'publish', 'upload', 'manage_user', 'backup', 'maintenance'],
        'admin' => ['delete', 'publish', 'upload', 'manage_user', 'backup', 'maintenance'],
        'editor' => ['publish', 'upload'],
        'operator' => ['upload'],
        'kepala_sekolah' => [],
        'guru_staff' => [],
    ];

    return $permissions[$role] ?? [];
}

function admin_user_granted_permissions(int $userId): array
{
    global $pdo;

    static $cache = [];
    if ($userId <= 0) {
        return [];
    }

    if (array_key_exists($userId, $cache)) {
        return $cache[$userId];
    }

    try {
        $stmt = $pdo->prepare('SELECT permission FROM user_permissions WHERE user_id = :user_id ORDER BY permission ASC');
        $stmt->execute(['user_id' => $userId]);
        $cache[$userId] = array_values(array_intersect(
            $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [],
            array_keys(admin_permission_options())
        ));
    } catch (Throwable $exception) {
        $cache[$userId] = [];
    }

    return $cache[$userId];
}

function admin_user_effective_permissions(array $user): array
{
    $role = (string)($user['role'] ?? 'operator');
    $userId = (int)($user['id'] ?? 0);

    return array_values(array_unique(array_merge(
        admin_role_base_permissions($role),
        admin_user_granted_permissions($userId)
    )));
}

function admin_user_has_permission(array $user, string $permission): bool
{
    return in_array($permission, admin_user_effective_permissions($user), true);
}

function can(string $permission): bool
{
    $user = current_user();
    if (!$user) {
        return false;
    }

    return admin_user_has_permission($user, $permission);
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

function public_site_base_path(): string
{
    static $basePath = null;

    if ($basePath !== null) {
        return $basePath;
    }

    $applicationRoot = realpath(__DIR__ . '/..');
    $documentRoot = isset($_SERVER['DOCUMENT_ROOT'])
        ? realpath((string) $_SERVER['DOCUMENT_ROOT'])
        : false;

    if (
        $applicationRoot !== false
        && $documentRoot !== false
    ) {
        $applicationRoot = str_replace(
            '\\',
            '/',
            rtrim($applicationRoot, '/\\')
        );

        $documentRoot = str_replace(
            '\\',
            '/',
            rtrim($documentRoot, '/\\')
        );

        $applicationCompare = PHP_OS_FAMILY === 'Windows'
            ? strtolower($applicationRoot)
            : $applicationRoot;

        $documentCompare = PHP_OS_FAMILY === 'Windows'
            ? strtolower($documentRoot)
            : $documentRoot;

        if ($applicationCompare === $documentCompare) {
            $basePath = '';
            return $basePath;
        }

        if (
            strpos(
                $applicationCompare,
                $documentCompare . '/'
            ) === 0
        ) {
            $relative = substr(
                $applicationRoot,
                strlen($documentRoot)
            );

            $basePath = '/' . trim($relative, '/');

            if ($basePath === '/') {
                $basePath = '';
            }

            return $basePath;
        }
    }

    $scriptName = str_replace(
        '\\',
        '/',
        (string) ($_SERVER['SCRIPT_NAME'] ?? '')
    );

    $directory = str_replace(
        '\\',
        '/',
        dirname($scriptName)
    );

    if (
        $directory === '.'
        || $directory === '/'
        || $directory === '\\'
    ) {
        $basePath = '';
        return $basePath;
    }

    $basePath = '/' . trim($directory, '/');

    return $basePath;
}

function public_site_path(string $path = ''): string
{
    $basePath = public_site_base_path();
    $cleanPath = ltrim(trim($path), '/');

    if ($cleanPath === '') {
        return $basePath === ''
            ? '/'
            : $basePath . '/';
    }

    return (
        $basePath === ''
            ? ''
            : $basePath
    ) . '/' . $cleanPath;
}

function public_site_url(string $path = ''): string
{
    $forwardedProtocol = strtolower(
        trim(
            explode(
                ',',
                (string) (
                    $_SERVER['HTTP_X_FORWARDED_PROTO']
                    ?? ''
                )
            )[0]
        )
    );

    $httpsEnabled = (
        isset($_SERVER['HTTPS'])
        && $_SERVER['HTTPS'] !== ''
        && strtolower((string) $_SERVER['HTTPS']) !== 'off'
    ) || $forwardedProtocol === 'https';

    $host = preg_replace(
        '/[^A-Za-z0-9.:\-\[\]]/',
        '',
        (string) (
            $_SERVER['HTTP_HOST']
            ?? 'sdcahayaharapanbekasi.sch.id'
        )
    );

    if ($host === '') {
        $host = 'sdcahayaharapanbekasi.sch.id';
    }

    return (
        $httpsEnabled
            ? 'https://'
            : 'http://'
    ) . $host . public_site_path($path);
}

function public_canonical_url(string $path = ''): string
{
    $baseUrl = 'https://sdcahayaharapanbekasi.sch.id';
    $cleanPath = ltrim(trim($path), '/');

    return $cleanPath === ''
        ? $baseUrl . '/'
        : $baseUrl . '/' . $cleanPath;
}
