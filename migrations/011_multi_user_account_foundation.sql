-- Migration version 011: Multi-user account foundation

ALTER TABLE `users`
  MODIFY COLUMN `role` ENUM('super_admin','admin','editor','operator','kepala_sekolah','guru_staff') NOT NULL DEFAULT 'operator';

ALTER TABLE `users`
  ADD COLUMN IF NOT EXISTS `last_login_at` DATETIME DEFAULT NULL AFTER `remember_token`,
  ADD COLUMN IF NOT EXISTS `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER `created_at`;

CREATE TABLE IF NOT EXISTS `user_permissions` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `permission` VARCHAR(64) NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_permissions_user_permission_unique` (`user_id`, `permission`),
  KEY `user_permissions_permission_index` (`permission`),
  CONSTRAINT `user_permissions_user_fk`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
