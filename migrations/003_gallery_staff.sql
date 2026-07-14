-- Migration version 003: Gallery category support and Guru & Staff module

ALTER TABLE `gallery`
  ADD COLUMN IF NOT EXISTS `category` VARCHAR(120) NOT NULL DEFAULT 'Kegiatan' AFTER `title`;

CREATE TABLE IF NOT EXISTS `staff` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nama` VARCHAR(100) NOT NULL,
  `jabatan` VARCHAR(100) NOT NULL,
  `foto` VARCHAR(255) DEFAULT NULL,
  `deskripsi` TEXT NULL,
  `urutan` INT NOT NULL DEFAULT 0,
  `aktif` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `staff_aktif_urutan_index` (`aktif`, `urutan`),
  KEY `staff_created_index` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
