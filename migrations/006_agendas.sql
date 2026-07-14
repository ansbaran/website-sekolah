-- Migration version 006: Add school agendas

CREATE TABLE IF NOT EXISTS `agendas` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title` VARCHAR(255) NOT NULL,
  `slug` VARCHAR(255) NOT NULL,
  `event_date` DATE NOT NULL,
  `event_time` VARCHAR(50) NOT NULL DEFAULT '',
  `location` VARCHAR(180) NOT NULL DEFAULT '',
  `contact` VARCHAR(180) NOT NULL DEFAULT '',
  `summary` TEXT DEFAULT NULL,
  `description` TEXT DEFAULT NULL,
  `points` TEXT DEFAULT NULL,
  `closing` TEXT DEFAULT NULL,
  `views` INT UNSIGNED NOT NULL DEFAULT 0,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `agendas_slug_unique` (`slug`),
  KEY `agendas_event_date_index` (`event_date`),
  KEY `agendas_is_active_index` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;