-- Migration version 002: Add news detail fields and gallery support

ALTER TABLE `news` ADD COLUMN `slug` VARCHAR(255) UNIQUE DEFAULT NULL AFTER `title`;
ALTER TABLE `news` ADD COLUMN `excerpt` TEXT DEFAULT NULL AFTER `content`;
ALTER TABLE `news` ADD COLUMN `content_long` LONGTEXT DEFAULT NULL AFTER `excerpt`;
ALTER TABLE `news` ADD COLUMN `featured_image` VARCHAR(255) DEFAULT NULL AFTER `thumbnail`;
ALTER TABLE `news` ADD COLUMN `category` VARCHAR(120) DEFAULT 'Umum' AFTER `featured_image`;
ALTER TABLE `news` ADD COLUMN `published_at` DATETIME DEFAULT NULL AFTER `category`;
ALTER TABLE `news` ADD COLUMN `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER `published_at`;
ALTER TABLE `news` ADD COLUMN `views` INT UNSIGNED DEFAULT 0 AFTER `updated_at`;
ALTER TABLE `news` ADD COLUMN `is_featured` TINYINT(1) DEFAULT 0 AFTER `views`;
ALTER TABLE `news` ADD COLUMN `seo_title` VARCHAR(255) DEFAULT NULL AFTER `is_featured`;
ALTER TABLE `news` ADD COLUMN `seo_description` VARCHAR(255) DEFAULT NULL AFTER `seo_title`;

CREATE INDEX `news_slug_index` ON `news` (`slug`);
CREATE INDEX `news_category_index` ON `news` (`category`);
CREATE INDEX `news_published_at_index` ON `news` (`published_at`);
CREATE INDEX `news_is_featured_index` ON `news` (`is_featured`);

CREATE TABLE IF NOT EXISTS `news_gallery` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `news_id` INT UNSIGNED NOT NULL,
  `image` VARCHAR(255) NOT NULL,
  `caption` VARCHAR(255) DEFAULT NULL,
  `sort_order` INT DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `news_gallery_news_id_index` (`news_id`),
  FOREIGN KEY (`news_id`) REFERENCES `news` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
