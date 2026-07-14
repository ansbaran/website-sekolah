-- Migration version 006: Parent feedback public submission metadata

ALTER TABLE `parent_feedback`
  ADD COLUMN IF NOT EXISTS `consent_given` TINYINT(1) NOT NULL DEFAULT 0 AFTER `is_active`,
  ADD COLUMN IF NOT EXISTS `submitted_ip_hash` CHAR(64) DEFAULT NULL AFTER `approved_by`,
  ADD COLUMN IF NOT EXISTS `submitted_user_agent` VARCHAR(255) DEFAULT NULL AFTER `submitted_ip_hash`;

DROP INDEX IF EXISTS `parent_feedback_public_index` ON `parent_feedback`;

CREATE INDEX `parent_feedback_public_index`
  ON `parent_feedback` (`is_approved`, `is_active`, `consent_given`, `display_order`, `created_at`);
