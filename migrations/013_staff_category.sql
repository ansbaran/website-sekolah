-- Migration version 013: Official category for Guru & Staff records

ALTER TABLE `staff`
  ADD COLUMN IF NOT EXISTS `kategori` ENUM('pimpinan','guru','staf') NOT NULL DEFAULT 'guru' AFTER `nama`;
