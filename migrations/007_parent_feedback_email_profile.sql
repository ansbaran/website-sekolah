-- Migration version 007: Parent feedback email identity and profile avatar

ALTER TABLE `parent_feedback`
  ADD COLUMN IF NOT EXISTS `parent_email` VARCHAR(190) DEFAULT NULL AFTER `parent_name`;

CREATE INDEX IF NOT EXISTS `parent_feedback_email_index`
  ON `parent_feedback` (`parent_email`);
