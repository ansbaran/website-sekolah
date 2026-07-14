<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once 'config/config.php';

try {
    $mapping = [
        1 => 'assets/img/berita/berita1.jpeg',
        2 => 'assets/img/berita/berita2.jpeg',
        3 => 'assets/img/berita/berita3.jpeg',
    ];

    foreach ($mapping as $id => $image) {
        $stmt = $pdo->prepare('UPDATE news SET thumbnail = ? WHERE id = ?');
        $stmt->execute([$image, $id]);

        $newsStmt = $pdo->prepare('SELECT title FROM news WHERE id = ?');
        $newsStmt->execute([$id]);
        $news = $newsStmt->fetch(PDO::FETCH_ASSOC);

        echo "Updated ID {$id}: " . ($news['title'] ?? 'Unknown') . " -> {$image}\n";
    }

    echo "\nSemua thumbnail berhasil diupdate\n";
} catch (Exception $e) {
    fwrite(STDERR, 'Error: ' . $e->getMessage() . "\n");
    exit(1);
}
