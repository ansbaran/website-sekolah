-- Migration version 005: Parent feedback moderation

CREATE TABLE IF NOT EXISTS `parent_feedback` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `parent_name` VARCHAR(120) NOT NULL,
  `relation_label` VARCHAR(120) NOT NULL DEFAULT 'Orang tua siswa',
  `student_label` VARCHAR(120) DEFAULT NULL,
  `message` TEXT NOT NULL,
  `rating` DECIMAL(2,1) NOT NULL DEFAULT 5.0,
  `display_order` INT NOT NULL DEFAULT 0,
  `is_approved` TINYINT(1) NOT NULL DEFAULT 0,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `approved_at` TIMESTAMP NULL DEFAULT NULL,
  `approved_by` INT UNSIGNED DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `parent_feedback_public_index` (`is_approved`, `is_active`, `display_order`, `created_at`),
  KEY `parent_feedback_created_index` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
