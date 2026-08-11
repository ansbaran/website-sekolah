-- ================================================================
-- LOCAL DEVELOPMENT ONLY
-- DO NOT RUN IN PRODUCTION WITHOUT EXPLICIT APPROVAL
-- ================================================================
-- Idempotent rollback candidate for Phase 1 gallery album schema experiments.
-- This file intentionally never drops or changes the legacy `gallery` table.
-- It also never touches achievements, users/admin tables, or upload files.
-- It must only be run against an isolated local/staging test database.

SET @sql = IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'gallery_albums' AND CONSTRAINT_NAME = 'fk_gallery_albums_cover_photo' AND CONSTRAINT_TYPE = 'FOREIGN KEY') = 1, 'ALTER TABLE `gallery_albums` DROP FOREIGN KEY `fk_gallery_albums_cover_photo`', 'SELECT ''skip fk_gallery_albums_cover_photo''');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

DROP TABLE IF EXISTS `gallery_photos`;
DROP TABLE IF EXISTS `gallery_albums`;