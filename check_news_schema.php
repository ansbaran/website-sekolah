<?php
require 'config/config.php';

// Check if news table exists and get schema
echo "=== NEWS TABLE SCHEMA ===\n";
$result = $pdo->query("DESCRIBE news")->fetchAll();
foreach ($result as $row) {
    echo $row['Field'] . " - " . $row['Type'] . " (Null: " . $row['Null'] . ")\n";
}

// Check total number of records
echo "\n=== TOTAL NEWS RECORDS ===\n";
$count = $pdo->query("SELECT COUNT(*) as cnt FROM news")->fetch()['cnt'];
echo "Total records: $count\n";

// Check records with NULL slug values
echo "\n=== NEWS RECORDS WITH NULL SLUG ===\n";
$nullSlugs = $pdo->query("SELECT COUNT(*) as cnt FROM news WHERE slug IS NULL")->fetch()['cnt'];
echo "Records with NULL slug: $nullSlugs\n";

// Show a few records with NULL slug values (if any)
if ($nullSlugs > 0) {
    echo "\nSample records with NULL slug values:\n";
    $samples = $pdo->query("SELECT id, title, slug, created_at FROM news WHERE slug IS NULL LIMIT 5")->fetchAll();
    foreach ($samples as $record) {
        echo "ID: " . $record['id'] . " | Title: " . $record['title'] . " | Slug: " . ($record['slug'] ?? 'NULL') . " | Created: " . $record['created_at'] . "\n";
    }
}
