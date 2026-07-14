<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once 'config/config.php';

try {
    $images = [
        'assets/img/berita/berita1.jpeg',
        'assets/img/berita/berita2.jpeg',
        'assets/img/berita/berita3.jpeg',
    ];

    $stmt = $pdo->query("SELECT id, title FROM news WHERE thumbnail IS NULL OR thumbnail = '' ORDER BY id ASC");
    $newsList = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($newsList)) {
        echo "Semua berita sudah memiliki thumbnail\n";
        exit;
    }

    foreach ($newsList as $index => $news) {
        $image = $images[$index % count($images)];
        $updateStmt = $pdo->prepare('UPDATE news SET thumbnail = ? WHERE id = ?');
        $updateStmt->execute([$image, $news['id']]);
        echo 'Updated: ' . $news['title'] . " -> {$image}\n";
    }

    echo "\nTotal updated: " . count($newsList) . " berita\n";
} catch (Exception $e) {
    fwrite(STDERR, 'Error: ' . $e->getMessage() . "\n");
    exit(1);
}
