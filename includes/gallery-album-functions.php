<?php

declare(strict_types=1);

function gallery_album_normalize_pagination(array $input): array
{
    $page = filter_var($input['page'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['default' => 1]]);
    $limit = filter_var($input['limit'] ?? 12, FILTER_VALIDATE_INT, ['options' => ['default' => 12]]);

    $page = max(1, (int) $page);
    $limit = max(1, min(48, (int) $limit));

    return [
        'page' => $page,
        'limit' => $limit,
        'offset' => ($page - 1) * $limit,
    ];
}

function gallery_album_normalize_category(?string $category): ?string
{
    $category = trim((string) $category);
    if ($category === '' || strtolower($category) === 'semua') {
        return null;
    }
    if (strlen($category) > 120 || preg_match('/[\x00-\x1F\x7F]/', $category)) {
        return '__invalid__';
    }
    return $category;
}

function gallery_album_validate_slug(?string $slug): ?string
{
    $slug = trim((string) $slug);
    if ($slug === '' || strlen($slug) > 220) {
        return null;
    }
    return preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug) === 1 ? $slug : null;
}

function gallery_album_public_image_path(?string $path): ?string
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

    $path = ltrim($path, '/');
    if (str_starts_with($path, 'uploads/gallery/')) {
        $file = basename($path);
    } else {
        $file = basename($path);
    }

    if ($file === '' || preg_match('/[\x00-\x1F\x7F]/', $file)) {
        return null;
    }

    return 'uploads/gallery/' . rawurlencode($file);
}

function gallery_album_format_album_row(array $row): array
{
    return [
        'id' => (int) $row['id'],
        'title' => (string) $row['title'],
        'slug' => (string) $row['slug'],
        'category' => (string) $row['category'],
        'description' => $row['description'] !== null ? (string) $row['description'] : null,
        'event_date' => $row['event_date'] !== null ? (string) $row['event_date'] : null,
        'cover_image' => gallery_album_public_image_path($row['cover_image'] ?? null),
        'cover_alt' => $row['cover_alt'] !== null ? (string) $row['cover_alt'] : (string) $row['title'],
        'photo_count' => (int) $row['photo_count'],
    ];
}

function count_public_gallery_albums(?string $category = null): int
{
    global $pdo;

    $sql = 'SELECT COUNT(*) FROM gallery_albums WHERE status = 1';
    $params = [];
    if ($category !== null) {
        $sql .= ' AND category = :category';
        $params['category'] = $category;
    }

    $stmt = $pdo->prepare($sql);
    foreach ($params as $name => $value) {
        $stmt->bindValue(':' . $name, $value, PDO::PARAM_STR);
    }
    $stmt->execute();
    return (int) $stmt->fetchColumn();
}

function get_public_gallery_albums(array $options = []): array
{
    global $pdo;

    $pagination = gallery_album_normalize_pagination($options);
    $category = gallery_album_normalize_category(isset($options['category']) ? (string) $options['category'] : null);
    if ($category === '__invalid__') {
        return [];
    }

    $sql = 'SELECT a.id, a.title, a.slug, a.category, a.description, a.event_date,
                   cp.image_path AS cover_image, cp.alt_text AS cover_alt,
                   COUNT(p.id) AS photo_count
            FROM gallery_albums a
            LEFT JOIN gallery_photos cp ON cp.id = a.cover_photo_id AND cp.album_id = a.id
            LEFT JOIN gallery_photos p ON p.album_id = a.id
            WHERE a.status = 1';
    $params = [];
    if ($category !== null) {
        $sql .= ' AND a.category = :category';
        $params['category'] = $category;
    }
    $sql .= ' GROUP BY a.id, a.title, a.slug, a.category, a.description, a.event_date, cp.image_path, cp.alt_text, a.sort_order, a.created_at
              ORDER BY a.sort_order ASC, a.event_date DESC, a.created_at DESC, a.id DESC
              LIMIT :limit OFFSET :offset';

    $stmt = $pdo->prepare($sql);
    foreach ($params as $name => $value) {
        $stmt->bindValue(':' . $name, $value, PDO::PARAM_STR);
    }
    $stmt->bindValue(':limit', $pagination['limit'], PDO::PARAM_INT);
    $stmt->bindValue(':offset', $pagination['offset'], PDO::PARAM_INT);
    $stmt->execute();

    return array_map('gallery_album_format_album_row', $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
}

function get_public_gallery_album_by_slug(string $slug): ?array
{
    global $pdo;

    $slug = gallery_album_validate_slug($slug);
    if ($slug === null) {
        return null;
    }

    $sql = 'SELECT a.id, a.title, a.slug, a.category, a.description, a.event_date,
                   cp.image_path AS cover_image, cp.alt_text AS cover_alt,
                   COUNT(p.id) AS photo_count
            FROM gallery_albums a
            LEFT JOIN gallery_photos cp ON cp.id = a.cover_photo_id AND cp.album_id = a.id
            LEFT JOIN gallery_photos p ON p.album_id = a.id
            WHERE a.status = 1 AND a.slug = :slug
            GROUP BY a.id, a.title, a.slug, a.category, a.description, a.event_date, cp.image_path, cp.alt_text
            LIMIT 1';
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['slug' => $slug]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ? gallery_album_format_album_row($row) : null;
}

function get_public_gallery_album_photos(int $albumId): array
{
    global $pdo;

    $stmt = $pdo->prepare(
        'SELECT p.id, p.image_path, p.caption, p.alt_text, p.sort_order
         FROM gallery_photos p
         INNER JOIN gallery_albums a ON a.id = p.album_id
         WHERE p.album_id = :album_id AND a.status = 1
         ORDER BY p.sort_order ASC, p.id ASC'
    );
    $stmt->execute(['album_id' => $albumId]);

    $photos = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
        $photos[] = [
            'id' => (int) $row['id'],
            'image_path' => gallery_album_public_image_path($row['image_path'] ?? null),
            'caption' => $row['caption'] !== null ? (string) $row['caption'] : null,
            'alt_text' => $row['alt_text'] !== null ? (string) $row['alt_text'] : null,
            'sort_order' => (int) $row['sort_order'],
        ];
    }

    return $photos;
}

function get_public_gallery_categories(): array
{
    global $pdo;

    $stmt = $pdo->query("SELECT DISTINCT category FROM gallery_albums WHERE status = 1 AND category <> '' ORDER BY category ASC");
    return array_map(static fn($row): string => (string) $row['category'], $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
}