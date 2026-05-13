<?php
/**
 * Fix Thumbnails - Point to existing images in assets/img/
 */

require_once 'config/config.php';
require_once 'config/db.php';

try {
    // Map news ID to available images
    $mapping = [
        1 => 'assets/img/berita1.jpeg',
        2 => 'assets/img/berita5.jpeg',
        3 => 'assets/img/sekolah.jpg'
    ];

    foreach ($mapping as $id => $image) {
        $stmt = $pdo->prepare("UPDATE news SET thumbnail = ? WHERE id = ?");
        $stmt->execute([$image, $id]);
        
        $newsStmt = $pdo->prepare("SELECT title FROM news WHERE id = ?");
        $newsStmt->execute([$id]);
        $news = $newsStmt->fetch(PDO::FETCH_ASSOC);
        
        echo "✓ ID {$id}: {$news['title']} → {$image}\n";
    }

    echo "\n✓ Semua thumbnail berhasil diupdate\n";

} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}
?>
