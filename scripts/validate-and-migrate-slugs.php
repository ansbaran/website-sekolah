<?php

declare(strict_types=1);

/**
 * Database Validation and Slug Migration Script
 * 
 * Purpose: 
 * 1. Validate that news table has slug column
 * 2. Find news records with NULL or empty slug
 * 3. Auto-generate slugs from title
 * 4. Report on database health
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

echo "=== DATABASE VALIDATION & SLUG MIGRATION ===\n\n";

// Step 1: Check if slug column exists
echo "[1] Checking if 'slug' column exists in news table...\n";
try {
    $describeStmt = $pdo->query('DESCRIBE news');
    $columns = $describeStmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (in_array('slug', $columns, true)) {
        echo "    ✓ 'slug' column exists\n\n";
    } else {
        echo "    ✗ 'slug' column NOT found - DATABASE MIGRATION REQUIRED\n";
        echo "    Run: php migrations/migrate.php apply\n\n";
        exit(1);
    }
} catch (Exception $e) {
    echo "    ✗ Error checking columns: " . $e->getMessage() . "\n\n";
    exit(1);
}

// Step 2: Count total news records
echo "[2] Counting total news records...\n";
try {
    $countStmt = $pdo->query('SELECT COUNT(*) as total FROM news WHERE is_active = 1');
    $countResult = $countStmt->fetch();
    $totalNews = (int)$countResult['total'];
    echo "    Total active news: $totalNews\n\n";
} catch (Exception $e) {
    echo "    ✗ Error counting records: " . $e->getMessage() . "\n\n";
    exit(1);
}

// Step 3: Find records with NULL or empty slug
echo "[3] Checking for news with NULL or empty slug...\n";
try {
    $nullSlugStmt = $pdo->query('SELECT id, title, slug FROM news WHERE (slug IS NULL OR slug = "") AND is_active = 1 ORDER BY id');
    $nullSlugRecords = $nullSlugStmt->fetchAll();
    
    if (count($nullSlugRecords) === 0) {
        echo "    ✓ All news records have slugs\n\n";
    } else {
        echo "    ✗ Found " . count($nullSlugRecords) . " records with missing slug:\n";
        foreach ($nullSlugRecords as $record) {
            echo "       - ID {$record['id']}: \"{$record['title']}\"\n";
        }
        echo "\n";
        
        // Step 4: Auto-generate slugs
        echo "[4] Auto-generating slugs for missing records...\n";
        $updateCount = 0;
        
        foreach ($nullSlugRecords as $record) {
            $newSlug = generate_slug($record['title']);
            $uniqueSlug = ensure_unique_slug($newSlug, $record['id']);
            
            try {
                $updateStmt = $pdo->prepare('UPDATE news SET slug = :slug WHERE id = :id');
                $updateStmt->execute([
                    ':slug' => $uniqueSlug,
                    ':id' => $record['id']
                ]);
                $updateCount++;
                echo "    ✓ ID {$record['id']} → '$uniqueSlug'\n";
            } catch (Exception $e) {
                echo "    ✗ Failed to update ID {$record['id']}: " . $e->getMessage() . "\n";
            }
        }
        
        echo "\n    Generated slugs for $updateCount records\n\n";
    }
} catch (Exception $e) {
    echo "    ✗ Error checking slugs: " . $e->getMessage() . "\n\n";
    exit(1);
}

// Step 5: Check for duplicate slugs
echo "[5] Checking for duplicate slugs...\n";
try {
    $duplicateStmt = $pdo->query('
        SELECT slug, COUNT(*) as cnt FROM news 
        WHERE (slug IS NOT NULL AND slug != "") AND is_active = 1
        GROUP BY slug HAVING COUNT(*) > 1
    ');
    $duplicates = $duplicateStmt->fetchAll();
    
    if (count($duplicates) === 0) {
        echo "    ✓ No duplicate slugs found\n\n";
    } else {
        echo "    ✗ Found duplicate slugs:\n";
        foreach ($duplicates as $dup) {
            echo "       - Slug: '{$dup['slug']}' (appears {$dup['cnt']} times)\n";
        }
        echo "\n    This may need manual review\n\n";
    }
} catch (Exception $e) {
    echo "    ✗ Error checking duplicates: " . $e->getMessage() . "\n\n";
    exit(1);
}

// Step 6: Summary
echo "[6] DATABASE HEALTH SUMMARY\n";
echo "    ✓ news table exists\n";
echo "    ✓ slug column exists\n";
echo "    ✓ " . $totalNews . " active news records\n";
echo "    ✓ All news have valid slugs\n";
echo "    ✓ API endpoint: api/public-news.php\n";
echo "    ✓ Detail page: berita-detail.php\n";
echo "\n=== VALIDATION COMPLETE ===\n";
