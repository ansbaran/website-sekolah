-- Migration version 012: User profile avatar

ALTER TABLE `users`
  ADD COLUMN IF NOT EXISTS `profile_photo` VARCHAR(255) NULL AFTER `email`;
