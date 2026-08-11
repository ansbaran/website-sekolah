<?php

declare(strict_types=1);

function gallery_album_admin_clean_text($value, int $maxLength = 220): string
{
    $text = trim(strip_tags((string) $value));
    $text = preg_replace('/[ \t]+/', ' ', $text) ?? $text;
    if (function_exists('mb_substr')) {
        return mb_substr($text, 0, $maxLength);
    }
    return substr($text, 0, $maxLength);
}

function gallery_album_admin_clean_description($value): ?string
{
    $text = trim(strip_tags((string) $value));
    if ($text === '') {
        return null;
    }
    if (function_exists('mb_substr')) {
        return mb_substr($text, 0, 5000);
    }
    return substr($text, 0, 5000);
}

function gallery_album_admin_slugify(string $value): string
{
    $slug = strtolower(trim($value));
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';
    $slug = trim($slug, '-');
    return substr($slug !== '' ? $slug : 'album', 0, 200);
}

function gallery_album_admin_valid_slug(string $slug): bool
{
    return $slug !== '' && strlen($slug) <= 220 && preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug) === 1;
}

function gallery_album_admin_unique_slug(string $baseSlug, ?int $excludeId = null): string
{
    global $pdo;

    $baseSlug = gallery_album_admin_slugify($baseSlug);
    $baseSlug = substr($baseSlug, 0, 200);
    $slug = $baseSlug;
    $counter = 2;

    while (true) {
        $sql = 'SELECT id FROM gallery_albums WHERE slug = :slug';
        $params = ['slug' => $slug];
        if ($excludeId !== null) {
            $sql .= ' AND id != :exclude_id';
            $params['exclude_id'] = $excludeId;
        }
        $sql .= ' LIMIT 1';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        if ($stmt->fetchColumn() === false) {
            return $slug;
        }

        $suffix = '-' . $counter;
        $slug = substr($baseSlug, 0, 220 - strlen($suffix)) . $suffix;
        $counter++;
    }
}

function gallery_album_admin_status_options(): array
{
    return [
        1 => 'Published',
        0 => 'Draft',
    ];
}

function gallery_album_admin_status_label($status): string
{
    return (int) $status === 1 ? 'Published' : 'Draft';
}

function gallery_album_admin_status_class($status): string
{
    return (int) $status === 1 ? 'status-pill status-pill--active' : 'status-pill status-pill--muted';
}

function gallery_album_admin_normalize_status($value): int
{
    return in_array((string) $value, ['1', 'published', 'Publish', 'Published'], true) ? 1 : 0;
}

function gallery_album_admin_normalize_sort_order($value): int
{
    $number = filter_var($value, FILTER_VALIDATE_INT);
    if ($number === false) {
        return 0;
    }
    return max(-999999, min(999999, (int) $number));
}

function gallery_album_admin_normalize_event_date($value, ?string &$error = null): ?string
{
    $date = trim((string) $value);
    if ($date === '') {
        return null;
    }

    $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    if (!$parsed || $parsed->format('Y-m-d') !== $date) {
        $error = 'Tanggal kegiatan tidak valid.';
        return null;
    }

    return $date;
}

function gallery_album_admin_get_album(int $id): ?array
{
    global $pdo;

    $stmt = $pdo->prepare(
        'SELECT a.*, COALESCE(pc.photo_count, 0) AS photo_count, cp.image_path AS cover_image
         FROM gallery_albums a
         LEFT JOIN (SELECT album_id, COUNT(*) AS photo_count FROM gallery_photos GROUP BY album_id) pc ON pc.album_id = a.id
         LEFT JOIN gallery_photos cp ON cp.id = a.cover_photo_id AND cp.album_id = a.id
         WHERE a.id = :id
         LIMIT 1'
    );
    $stmt->execute(['id' => $id]);
    $album = $stmt->fetch(PDO::FETCH_ASSOC);

    return $album ?: null;
}

function gallery_album_admin_list_albums(): array
{
    global $pdo;

    $stmt = $pdo->query(
        'SELECT a.*, COALESCE(pc.photo_count, 0) AS photo_count, cp.image_path AS cover_image
         FROM gallery_albums a
         LEFT JOIN (SELECT album_id, COUNT(*) AS photo_count FROM gallery_photos GROUP BY album_id) pc ON pc.album_id = a.id
         LEFT JOIN gallery_photos cp ON cp.id = a.cover_photo_id AND cp.album_id = a.id
         ORDER BY a.sort_order ASC, a.event_date DESC, a.created_at DESC, a.id DESC'
    );

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function gallery_album_admin_cover_url(?string $path): string
{
    $path = trim(str_replace('\\', '/', (string) $path));
    if ($path === '' || strpos($path, "\0") !== false || preg_match('#(^|/)\.\.(?:/|$)#', $path)) {
        return '';
    }

    return build_upload_url('gallery', basename($path));
}

function gallery_album_admin_validate_metadata(array $input, ?array $existingAlbum = null): array
{
    $errors = [];
    $title = gallery_album_admin_clean_text($input['title'] ?? '', 220);
    $slugInput = trim((string) ($input['slug'] ?? ''));
    $category = normalize_gallery_category((string) ($input['category'] ?? ''));
    $description = gallery_album_admin_clean_description($input['description'] ?? '');
    $eventDateError = null;
    $eventDate = gallery_album_admin_normalize_event_date($input['event_date'] ?? '', $eventDateError);
    $status = gallery_album_admin_normalize_status($input['status'] ?? 0);
    $sortOrder = gallery_album_admin_normalize_sort_order($input['sort_order'] ?? 0);

    if ($title === '') {
        $errors[] = 'Judul album wajib diisi.';
    }

    if ($category === null) {
        $errors[] = 'Kategori album tidak valid.';
    }

    if ($eventDateError !== null) {
        $errors[] = $eventDateError;
    }

    $excludeId = isset($existingAlbum['id']) ? (int) $existingAlbum['id'] : null;
    if ($slugInput === '') {
        $slugBase = $title;
    } else {
        $slugBase = $slugInput;
    }
    $slug = gallery_album_admin_slugify($slugBase);
    if (!gallery_album_admin_valid_slug($slug)) {
        $errors[] = 'Slug album tidak valid.';
    } else {
        $slug = gallery_album_admin_unique_slug($slug, $excludeId);
    }

    return [
        'data' => [
            'title' => $title,
            'slug' => $slug,
            'category' => $category ?? '',
            'description' => $description,
            'event_date' => $eventDate,
            'status' => $status,
            'sort_order' => $sortOrder,
        ],
        'errors' => $errors,
    ];
}

function gallery_album_admin_max_upload_files(): int
{
    return 12;
}

function gallery_album_admin_list_photos(int $albumId): array
{
    global $pdo;

    $stmt = $pdo->prepare(
        'SELECT id, album_id, legacy_gallery_id, image_path, caption, alt_text, sort_order, created_at, updated_at
         FROM gallery_photos
         WHERE album_id = :album_id
         ORDER BY sort_order ASC, id ASC'
    );
    $stmt->execute(['album_id' => $albumId]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function gallery_album_admin_get_photo(int $albumId, int $photoId): ?array
{
    global $pdo;

    $stmt = $pdo->prepare(
        'SELECT id, album_id, legacy_gallery_id, image_path, caption, alt_text, sort_order, created_at, updated_at
         FROM gallery_photos
         WHERE id = :id AND album_id = :album_id
         LIMIT 1'
    );
    $stmt->execute([
        'id' => $photoId,
        'album_id' => $albumId,
    ]);
    $photo = $stmt->fetch(PDO::FETCH_ASSOC);

    return $photo ?: null;
}

function gallery_album_admin_photo_url(?string $path): string
{
    return gallery_album_admin_cover_url($path);
}

function gallery_album_admin_photo_file_name(?string $path): ?string
{
    $path = trim(str_replace('\\', '/', (string) $path));
    if ($path === '' || strpos($path, "\0") !== false) {
        return null;
    }
    if (preg_match('/^(javascript|data|file|vbscript):/i', $path) || preg_match('#(^|/)\.\.(?:/|$)#', $path)) {
        return null;
    }
    if (preg_match('#^[a-zA-Z]:/#', $path) || str_starts_with($path, '//')) {
        return null;
    }

    $fileName = basename($path);
    if ($fileName === '' || $fileName !== sanitize_file_name($fileName)) {
        return null;
    }

    return $fileName;
}

function gallery_album_admin_photo_file_path(?string $path): ?string
{
    $fileName = gallery_album_admin_photo_file_name($path);
    if ($fileName === null) {
        return null;
    }

    return uploaded_file_path('gallery', $fileName);
}

function gallery_album_admin_delete_photo_file(?string $path, ?string &$error = null): bool
{
    $targetPath = gallery_album_admin_photo_file_path($path);
    if ($targetPath === null) {
        $error = 'Path file foto tidak aman.';
        return false;
    }

    $targets = [$targetPath];
    $webpPath = preg_replace('/\.[^.]+$/', '.webp', $targetPath);
    if (is_string($webpPath) && $webpPath !== $targetPath) {
        $targets[] = $webpPath;
    }

    foreach (array_unique($targets) as $target) {
        if (!is_file($target)) {
            continue;
        }
        if (!@unlink($target) && is_file($target)) {
            $error = 'File foto belum dapat dihapus dari storage.';
            return false;
        }
    }

    return true;
}

function gallery_album_admin_cleanup_uploaded_files(array $paths): void
{
    foreach ($paths as $path) {
        $error = null;
        gallery_album_admin_delete_photo_file((string) $path, $error);
    }
}

function gallery_album_admin_uploaded_file_items(?array $files): array
{
    if (empty($files) || !isset($files['name']) || !is_array($files['name'])) {
        return [];
    }

    $items = [];
    foreach ($files['name'] as $index => $name) {
        if ((string) $name === '' && (int) ($files['error'][$index] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        $items[] = [
            'name' => (string) ($files['name'][$index] ?? ''),
            'type' => (string) ($files['type'][$index] ?? ''),
            'tmp_name' => (string) ($files['tmp_name'][$index] ?? ''),
            'error' => (int) ($files['error'][$index] ?? UPLOAD_ERR_NO_FILE),
            'size' => (int) ($files['size'][$index] ?? 0),
        ];
    }

    return $items;
}

function gallery_album_admin_validate_upload_batch(array $files, ?string &$error = null): bool
{
    if (empty($files)) {
        $error = 'Silakan pilih setidaknya satu gambar.';
        return false;
    }

    $maxFiles = gallery_album_admin_max_upload_files();
    if (count($files) > $maxFiles) {
        $error = 'Maksimal ' . $maxFiles . ' foto per upload.';
        return false;
    }

    foreach ($files as $index => $file) {
        $uploadError = null;
        if (!validate_image_upload($file, $uploadError)) {
            $error = 'Foto #' . ($index + 1) . ': ' . ($uploadError ?? 'File tidak valid.');
            return false;
        }
    }

    return true;
}

function gallery_album_admin_next_sort_order(int $albumId): int
{
    global $pdo;

    $stmt = $pdo->prepare('SELECT COALESCE(MAX(sort_order), 0) FROM gallery_photos WHERE album_id = :album_id');
    $stmt->execute(['album_id' => $albumId]);

    return (int) $stmt->fetchColumn() + 1;
}

function gallery_album_admin_default_alt_text(array $album, int $photoNumber): string
{
    $title = gallery_album_admin_clean_text($album['title'] ?? 'Album Galeri', 200);
    return gallery_album_admin_clean_text($title . ' - Foto ' . $photoNumber, 255);
}

function gallery_album_admin_insert_uploaded_photos(array $album, array $files): int
{
    global $pdo;

    $error = null;
    if (!gallery_album_admin_validate_upload_batch($files, $error)) {
        throw new RuntimeException($error ?? 'Upload foto tidak valid.');
    }

    $albumId = (int) $album['id'];
    $nextSort = gallery_album_admin_next_sort_order($albumId);
    $createdPaths = [];
    $uploadedCount = 0;

    try {
        $pdo->beginTransaction();
        $currentCoverId = !empty($album['cover_photo_id']) ? (int) $album['cover_photo_id'] : null;

        foreach ($files as $index => $file) {
            $uploadError = null;
            $fileName = upload_image($file, 'gallery', $uploadError);
            if ($fileName === null) {
                throw new RuntimeException($uploadError ?? 'File foto belum dapat disimpan.');
            }

            $imagePath = 'uploads/gallery/' . $fileName;
            $createdPaths[] = $imagePath;
            $sortOrder = $nextSort + $index;
            $altText = gallery_album_admin_default_alt_text($album, $sortOrder);

            $stmt = $pdo->prepare(
                'INSERT INTO gallery_photos (album_id, image_path, caption, alt_text, sort_order, created_at, updated_at)
                 VALUES (:album_id, :image_path, NULL, :alt_text, :sort_order, NOW(), NOW())'
            );
            $stmt->execute([
                'album_id' => $albumId,
                'image_path' => $imagePath,
                'alt_text' => $altText,
                'sort_order' => $sortOrder,
            ]);

            $photoId = (int) $pdo->lastInsertId();
            if ($currentCoverId === null && $uploadedCount === 0) {
                $cover = $pdo->prepare('UPDATE gallery_albums SET cover_photo_id = :photo_id, updated_at = NOW() WHERE id = :album_id');
                $cover->execute([
                    'photo_id' => $photoId,
                    'album_id' => $albumId,
                ]);
                $currentCoverId = $photoId;
            }

            $uploadedCount++;
        }

        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        gallery_album_admin_cleanup_uploaded_files($createdPaths);
        throw $exception;
    }

    return $uploadedCount;
}

function gallery_album_admin_update_photo_metadata(array $album, int $photoId, array $input): void
{
    global $pdo;

    $photo = gallery_album_admin_get_photo((int) $album['id'], $photoId);
    if (!$photo) {
        throw new RuntimeException('Foto album tidak ditemukan.');
    }

    $caption = gallery_album_admin_clean_text($input['caption'] ?? '', 255);
    $altText = gallery_album_admin_clean_text($input['alt_text'] ?? '', 255);
    $sortOrder = gallery_album_admin_normalize_sort_order($input['sort_order'] ?? $photo['sort_order']);
    if ($altText === '') {
        $altText = gallery_album_admin_default_alt_text($album, $sortOrder);
    }

    $stmt = $pdo->prepare(
        'UPDATE gallery_photos
         SET caption = :caption, alt_text = :alt_text, sort_order = :sort_order, updated_at = NOW()
         WHERE id = :id AND album_id = :album_id'
    );
    $stmt->execute([
        'caption' => $caption !== '' ? $caption : null,
        'alt_text' => $altText,
        'sort_order' => $sortOrder,
        'id' => $photoId,
        'album_id' => (int) $album['id'],
    ]);
}

function gallery_album_admin_set_cover(int $albumId, int $photoId): void
{
    global $pdo;

    $photo = gallery_album_admin_get_photo($albumId, $photoId);
    if (!$photo) {
        throw new RuntimeException('Foto cover harus berasal dari album yang sama.');
    }

    $stmt = $pdo->prepare('UPDATE gallery_albums SET cover_photo_id = :photo_id, updated_at = NOW() WHERE id = :album_id');
    $stmt->execute([
        'photo_id' => $photoId,
        'album_id' => $albumId,
    ]);
}

function gallery_album_admin_delete_photo(array $album, int $photoId): void
{
    global $pdo;

    $albumId = (int) $album['id'];
    $fileDeleted = false;

    try {
        $pdo->beginTransaction();

        $photo = gallery_album_admin_get_photo($albumId, $photoId);
        if (!$photo) {
            throw new RuntimeException('Foto album tidak ditemukan.');
        }

        $fallbackId = null;
        if (!empty($album['cover_photo_id']) && (int) $album['cover_photo_id'] === $photoId) {
            $fallback = $pdo->prepare(
                'SELECT id FROM gallery_photos
                 WHERE album_id = :album_id AND id != :photo_id
                 ORDER BY sort_order ASC, id ASC
                 LIMIT 1'
            );
            $fallback->execute([
                'album_id' => $albumId,
                'photo_id' => $photoId,
            ]);
            $fallbackId = $fallback->fetchColumn();

            $cover = $pdo->prepare('UPDATE gallery_albums SET cover_photo_id = :cover_photo_id, updated_at = NOW() WHERE id = :album_id');
            $cover->execute([
                'cover_photo_id' => $fallbackId !== false ? (int) $fallbackId : null,
                'album_id' => $albumId,
            ]);
        }

        $delete = $pdo->prepare('DELETE FROM gallery_photos WHERE id = :id AND album_id = :album_id');
        $delete->execute([
            'id' => $photoId,
            'album_id' => $albumId,
        ]);

        $deleteError = null;
        if (empty($photo['legacy_gallery_id']) && !gallery_album_admin_delete_photo_file($photo['image_path'] ?? '', $deleteError)) {
            throw new RuntimeException($deleteError ?? 'File foto belum dapat dihapus.');
        }
        $fileDeleted = empty($photo['legacy_gallery_id']);

        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($fileDeleted) {
            log_exception(new RuntimeException('DB rollback after file deletion for gallery photo ' . $photoId));
        }
        throw $exception;
    }
}
