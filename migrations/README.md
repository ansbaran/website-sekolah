# Migration Versioning - SD Cahaya Harapan Bekasi

## Tujuan

File migrasi membuat dan memperbarui struktur database yang dibutuhkan untuk CMS, logging, media, backup, agenda, program sekolah, dan reset password.

## Cara Pakai

Jalankan dari command line di folder proyek:

```bash
php migrations/migrate.php status
php migrations/migrate.php apply
```

## Struktur Migrasi

- Semua file `.sql` di folder `migrations/` diproses berurutan berdasarkan nama file.
- Status migrasi disimpan di tabel `schema_migrations`.
- Migration runner hanya menjalankan file yang belum tercatat.
- Setiap statement SQL dieksekusi berurutan. Jika satu file gagal, proses berhenti agar bisa diperiksa sebelum lanjut.

## Safe Upgrade

Sebelum menjalankan migrasi di production:

1. Backup database.
2. Backup folder `uploads/`.
3. Jalankan `php migrations/migrate.php status`.
4. Jalankan `php migrations/migrate.php apply`.
5. Cek `admin/system-health.php`.

## Rollback Note

Rollback database dilakukan dari backup SQL yang valid. Jangan mencoba rollback langsung dari script migrasi tanpa backup.