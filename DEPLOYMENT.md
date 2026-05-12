# Deployment Guide — SD Cahaya Harapan Bekasi

## Persiapan Hosting

1. Pastikan paket hosting mendukung:
   - PHP 8.0 atau lebih tinggi
   - MySQL 5.7 / MariaDB 10.x atau lebih tinggi
   - Ekstensi PHP: PDO, PDO_MySQL, GD, Zip

2. Unggah seluruh folder proyek ke server produksi.
3. Pastikan file dan folder berikut berada di luar akses publik jika memungkinkan:
   - `config/`
   - `includes/`
   - `migrations/`

## Setup Database

1. Buat database baru, misalnya `school_admin`.
2. Buat user database dengan password kuat dan berikan hak akses SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER.
3. Import file SQL migrasi awal jika perlu:
   - `migrations/001_initial_schema.sql`

4. Atau jalankan script migrasi:
   ```bash
   php migrations/migrate.php
   ```

## Permissions Folder

Pastikan folder berikut dapat ditulis oleh PHP:

- `uploads/`
- `backups/`
- `cache/`
- `logs/`

Contoh Unix:
```bash
chown -R www-data:www-data uploads backups cache logs
chmod -R 755 uploads backups cache logs
```

## Environment dan Konfigurasi

1. Pastikan `APP_ENV` di-setup ke `production` di environment server.
2. Jika perlu mode development lokal, set `APP_ENV=development` dan `APP_DEBUG=1`.
3. Pastikan `session.cookie_secure` aktif di HTTPS.
4. Pastikan `upload_max_filesize` dan `post_max_size` di PHP set minimal `8M`.

## SSL Recommendation

- Gunakan sertifikat SSL/TLS valid.
- Paksa HTTPS pada seluruh situs publik dan admin.
- Aktifkan HSTS jika tersedia.

## Backup Recommendation

- Jalankan backup database dan uploads secara berkala.
- Simpan backup di lokasi terpisah dari server aplikasi.
- Pastikan `backups/` tidak bisa diakses dari web.
- Gunakan sistem backup otomatis atau cron jika memungkinkan.

## Production Checklist

- [ ] `APP_ENV=production`
- [ ] `APP_DEBUG=0`
- [ ] `uploads/`, `backups/`, `cache/`, `logs/` writable
- [ ] `config/` dan `includes/` tidak dapat diakses publik
- [ ] `uploads/.htaccess` melarang eksekusi PHP
- [ ] `admin/` memiliki `Options -Indexes`
- [ ] `maintenance.flag` bekerja dan admin masih dapat login
- [ ] Backup zip database dan uploads berhasil dibuat
- [ ] Health check `admin/system-health.php` menampilkan status OK
- [ ] Error logging menulis ke `logs/php-error.log`
- [ ] Login throttling aktif dan session regenerate bekerja
- [ ] Cache folder tersedia dan dapat ditulis

## Rollback Note

- Untuk rollback database, pulihkan dari file backup SQL yang valid.
- Jangan jalankan migration baru di produksi tanpa mengambil snapshot backup terlebih dahulu.
- Gunakan `migrations/README.md` untuk catatan versi dan prosedur rollback.
