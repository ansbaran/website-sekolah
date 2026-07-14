-- Migration version 008: Add active flag to admin users

ALTER TABLE `users`
  ADD COLUMN IF NOT EXISTS `is_active` TINYINT(1) NOT NULL DEFAULT 1 AFTER `role`;

CREATE INDEX IF NOT EXISTS `users_is_active_role_index`
  ON `users` (`is_active`, `role`);
