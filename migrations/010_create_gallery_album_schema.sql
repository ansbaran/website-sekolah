-- Gallery Album Phase 1 revised candidate schema
-- Source: Phase 1.5 isolated test findings.
-- Review/test-only candidate. Do not run directly on production.
-- This schema intentionally does not change legacy table `gallery`.
-- It also does not change achievements, users/admin tables, or upload files.

CREATE TABLE IF NOT EXISTS `gallery_albums` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(220) NOT NULL,
  `slug` varchar(220) NOT NULL,
  `category` varchar(120) NOT NULL,
  `description` text DEFAULT NULL,
  `event_date` date DEFAULT NULL,
  `status` tinyint(1) unsigned NOT NULL DEFAULT 1 COMMENT '1=published, 0=draft',
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `cover_photo_id` int(10) unsigned DEFAULT NULL,
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `gallery_albums_slug_unique` (`slug`),
  KEY `gallery_albums_category_index` (`category`),
  KEY `gallery_albums_status_index` (`status`),
  KEY `gallery_albums_sort_order_index` (`sort_order`),
  KEY `gallery_albums_event_date_index` (`event_date`),
  KEY `gallery_albums_cover_photo_index` (`cover_photo_id`),
  KEY `gallery_albums_public_index` (`status`, `category`, `sort_order`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `gallery_photos` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `album_id` int(10) unsigned NOT NULL,
  `legacy_gallery_id` int(10) unsigned DEFAULT NULL,
  `image_path` varchar(255) NOT NULL,
  `caption` varchar(255) DEFAULT NULL,
  `alt_text` varchar(255) DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `gallery_photos_legacy_gallery_unique` (`legacy_gallery_id`),
  KEY `gallery_photos_album_index` (`album_id`),
  KEY `gallery_photos_album_sort_index` (`album_id`, `sort_order`),
  KEY `gallery_photos_image_path_index` (`image_path`(191))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @sql = IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gallery_albums' AND INDEX_NAME = 'gallery_albums_slug_unique') = 0, 'ALTER TABLE `gallery_albums` ADD UNIQUE KEY `gallery_albums_slug_unique` (`slug`)', 'SELECT ''skip gallery_albums_slug_unique''');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gallery_albums' AND INDEX_NAME = 'gallery_albums_category_index') = 0, 'ALTER TABLE `gallery_albums` ADD KEY `gallery_albums_category_index` (`category`)', 'SELECT ''skip gallery_albums_category_index''');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gallery_albums' AND INDEX_NAME = 'gallery_albums_status_index') = 0, 'ALTER TABLE `gallery_albums` ADD KEY `gallery_albums_status_index` (`status`)', 'SELECT ''skip gallery_albums_status_index''');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gallery_albums' AND INDEX_NAME = 'gallery_albums_sort_order_index') = 0, 'ALTER TABLE `gallery_albums` ADD KEY `gallery_albums_sort_order_index` (`sort_order`)', 'SELECT ''skip gallery_albums_sort_order_index''');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gallery_albums' AND INDEX_NAME = 'gallery_albums_event_date_index') = 0, 'ALTER TABLE `gallery_albums` ADD KEY `gallery_albums_event_date_index` (`event_date`)', 'SELECT ''skip gallery_albums_event_date_index''');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gallery_albums' AND INDEX_NAME = 'gallery_albums_cover_photo_index') = 0, 'ALTER TABLE `gallery_albums` ADD KEY `gallery_albums_cover_photo_index` (`cover_photo_id`)', 'SELECT ''skip gallery_albums_cover_photo_index''');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gallery_albums' AND INDEX_NAME = 'gallery_albums_public_index') = 0, 'ALTER TABLE `gallery_albums` ADD KEY `gallery_albums_public_index` (`status`, `category`, `sort_order`, `created_at`)', 'SELECT ''skip gallery_albums_public_index''');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gallery_photos' AND INDEX_NAME = 'gallery_photos_legacy_gallery_unique') = 0, 'ALTER TABLE `gallery_photos` ADD UNIQUE KEY `gallery_photos_legacy_gallery_unique` (`legacy_gallery_id`)', 'SELECT ''skip gallery_photos_legacy_gallery_unique''');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gallery_photos' AND INDEX_NAME = 'gallery_photos_album_index') = 0, 'ALTER TABLE `gallery_photos` ADD KEY `gallery_photos_album_index` (`album_id`)', 'SELECT ''skip gallery_photos_album_index''');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gallery_photos' AND INDEX_NAME = 'gallery_photos_album_sort_index') = 0, 'ALTER TABLE `gallery_photos` ADD KEY `gallery_photos_album_sort_index` (`album_id`, `sort_order`)', 'SELECT ''skip gallery_photos_album_sort_index''');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gallery_photos' AND INDEX_NAME = 'gallery_photos_image_path_index') = 0, 'ALTER TABLE `gallery_photos` ADD KEY `gallery_photos_image_path_index` (`image_path`(191))', 'SELECT ''skip gallery_photos_image_path_index''');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'gallery_photos' AND CONSTRAINT_NAME = 'fk_gallery_photos_album' AND CONSTRAINT_TYPE = 'FOREIGN KEY') = 0, 'ALTER TABLE `gallery_photos` ADD CONSTRAINT `fk_gallery_photos_album` FOREIGN KEY (`album_id`) REFERENCES `gallery_albums` (`id`) ON DELETE CASCADE', 'SELECT ''skip fk_gallery_photos_album''');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'gallery_albums' AND CONSTRAINT_NAME = 'fk_gallery_albums_cover_photo' AND CONSTRAINT_TYPE = 'FOREIGN KEY') = 0, 'ALTER TABLE `gallery_albums` ADD CONSTRAINT `fk_gallery_albums_cover_photo` FOREIGN KEY (`cover_photo_id`) REFERENCES `gallery_photos` (`id`) ON DELETE SET NULL', 'SELECT ''skip fk_gallery_albums_cover_photo''');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;