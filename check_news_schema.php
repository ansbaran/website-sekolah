<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require 'config/config.php';

echo "=== NEWS TABLE SCHEMA ===\n";
$result = $pdo->query('DESCRIBE news')->fetchAll();
foreach ($result as $row) {
    echo $row['Field'] . ' - ' . $row['Type'] . ' (Null: ' . $row['Null'] . ")\n";
}

echo "\n=== TOTAL NEWS RECORDS ===\n";
$count = $pdo->query('SELECT COUNT(*) AS cnt FROM news')->fetch()['cnt'];
echo "Total records: $count\n";

echo "\n=== NEWS RECORDS WITH NULL SLUG ===\n";
$nullSlugs = $pdo->query('SELECT COUNT(*) AS cnt FROM news WHERE slug IS NULL')->fetch()['cnt'];
echo "Records with NULL slug: $nullSlugs\n";

if ($nullSlugs > 0) {
    echo "\nSample records with NULL slug values:\n";
    $samples = $pdo->query('SELECT id, title, slug, created_at FROM news WHERE slug IS NULL LIMIT 5')->fetchAll();
    foreach ($samples as $record) {
        echo 'ID: ' . $record['id'] . ' | Title: ' . $record['title'] . ' | Slug: ' . ($record['slug'] ?? 'NULL') . ' | Created: ' . $record['created_at'] . "\n";
    }
}
