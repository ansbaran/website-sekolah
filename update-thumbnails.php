<?php
/**
 * Update Thumbnails for News Articles
 * This script updates the thumbnail field for news articles with available images
 */

require_once 'config/config.php';
require_once 'config/db.php';

try {
    // Define available images
    $images = [
        'assets/img/berita1.jpeg',
        'assets/img/berita5.jpeg',
        'assets/img/sekolah.jpg'
    ];

    // Get all news that don't have thumbnails
    $stmt = $pdo->query("SELECT id, title FROM news WHERE thumbnail IS NULL OR thumbnail = '' ORDER BY id ASC");
    $newsList = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($newsList)) {
        echo "✓ Semua berita sudah memiliki thumbnail\n";
        exit;
    }

    // Update thumbnails with rotating images
    foreach ($newsList as $index => $news) {
        $image = $images[$index % count($images)];
        $updateStmt = $pdo->prepare("UPDATE news SET thumbnail = ? WHERE id = ?");
        $updateStmt->execute([$image, $news['id']]);
        echo "✓ Updated: {$news['title']} → {$image}\n";
    }

    echo "\n✓ Total updated: " . count($newsList) . " berita\n";

} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    http_response_code(500);
}
?>
