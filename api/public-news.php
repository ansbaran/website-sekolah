<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/functions.php';

error_log('[API] public-news.php called with limit=' . ($_GET['limit'] ?? 'default'));

$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
if ($limit < 1 || $limit > 100) {
    $limit = 10;
}

try {
    $statement = $pdo->prepare('SELECT id, title, slug, category, excerpt, content, featured_image, published_at, thumbnail, is_active FROM news WHERE is_active = 1 ORDER BY published_at DESC LIMIT :limit');
    $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
    $statement->execute();
    $news = $statement->fetchAll();

    error_log('[API] Query returned ' . count($news) . ' records');

    $data = [];
    foreach ($news as $item) {
        $slug = $item['slug'];
        
        // Generate slug if missing
        if (empty($slug)) {
            $slug = generate_slug($item['title']);
            error_log('[API] Generated slug for title "' . $item['title'] . '": ' . $slug);
        }
        
        $thumbnail = $item['thumbnail'] ?: $item['featured_image'];
        $thumbnailUrl = null;

        if ($thumbnail) {
            $thumbnail = normalize_legacy_asset_path((string) $thumbnail);
            $thumbnail = ltrim($thumbnail, '/');

            if (strpos($thumbnail, 'assets/') === 0) {
                $thumbnailUrl = build_upload_url('news', $thumbnail);
            } elseif (strpos($thumbnail, 'uploads/') === 0) {
                $thumbnailUrl = build_upload_url('news', $thumbnail);
            } else {
                // Security: prevent path traversal — allow filename only
                $safeFilename = basename($thumbnail);

                // Validate extension (whitelist)
                $ext = strtolower(pathinfo($safeFilename, PATHINFO_EXTENSION));
                if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
                    $safeFilename = ''; // reject invalid extension
                }

                if ($safeFilename !== '') {
                    $thumbnailPath = UPLOAD_BASE . '/news/' . $safeFilename;
                    if (is_file($thumbnailPath)) {
                        $thumbnailUrl = build_upload_url('news', $safeFilename);
                    }
                }
            }
        }
        
        $data[] = [
            'id' => (int)$item['id'],
            'title' => htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8'),
            'slug' => htmlspecialchars($slug, ENT_QUOTES, 'UTF-8'),
            'category' => htmlspecialchars($item['category'] ?? 'Umum', ENT_QUOTES, 'UTF-8'),
            'excerpt' => htmlspecialchars($item['excerpt'] ?: substr(strip_tags($item['content'] ?? ''), 0, 150), ENT_QUOTES, 'UTF-8'),
            'published_at' => $item['published_at'],
            'thumbnail' => $thumbnailUrl,
        ];
    }

    echo json_encode(['status' => 'success', 'data' => $data, 'count' => count($data)], JSON_UNESCAPED_UNICODE);
    error_log('[API] Response sent with ' . count($data) . ' items');
} catch (Exception $e) {
    error_log('[API] Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
