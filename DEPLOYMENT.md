# Deployment Guide - SD Cahaya Harapan Bekasi

Panduan ini dipakai saat memindahkan website dari lokal/XAMPP ke hosting production.

## 1. Persiapan Hosting

Pastikan hosting mendukung:

- PHP 8.0 atau lebih tinggi
- MySQL 5.7 / MariaDB 10.x atau lebih tinggi
- Ekstensi PHP: PDO, PDO_MySQL, GD, Zip, OpenSSL
- HTTPS aktif untuk domain utama

Folder yang perlu writable oleh PHP:

- `uploads/`
- `backups/`
- `cache/`
- `logs/`

Contoh permission di Linux:

```bash
chown -R www-data:www-data uploads backups cache logs
chmod -R 755 uploads backups cache logs
```

## 2. Environment Production

Salin nilai dari `.env.example` ke environment hosting atau konfigurasi web server. Jangan upload file `.env` berisi password ke repository.

Variabel wajib:

```env
APP_ENV=production
APP_DEBUG=false
BASE_URL=/

DB_HOST=host_database
DB_PORT=3306
DB_NAME=nama_database
DB_USER=user_database
DB_PASS=password_database_kuat
DB_CONNECT_TIMEOUT=3

MAIL_FROM_ADDRESS=no-reply@domain-sekolah.sch.id
MAIL_FROM_NAME="SD Cahaya Harapan Bekasi"
```

Jika website dipasang di subfolder, isi `BASE_URL`, contoh:

```env
BASE_URL=/website-sekolah
```

## 3. SMTP untuk Lupa Password

Agar fitur lupa password benar-benar mengirim email, isi SMTP dari email resmi sekolah atau layanan email hosting.

```env
SMTP_HOST=smtp.domain-sekolah.sch.id
SMTP_PORT=587
SMTP_USERNAME=no-reply@domain-sekolah.sch.id
SMTP_PASSWORD=password_email_atau_app_password
SMTP_ENCRYPTION=tls
SMTP_TIMEOUT=10
```

Jika `SMTP_HOST` kosong, aplikasi akan mencoba `mail()` PHP. Untuk production, SMTP lebih direkomendasikan.

## 4. Setup Database

1. Buat database baru.
2. Buat user database dengan password kuat.
3. Berikan akses minimal: SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, INDEX.
4. Import schema awal atau jalankan migrasi:

```bash
php migrations/migrate.php status
php migrations/migrate.php apply
```

Setelah migrasi selesai, pastikan tidak ada pending migration.

## 5. Admin Pertama

Buat password hash:

```bash
php reset-admin.php "PasswordBaruYangKuat"
```

Lalu buat akun admin dengan email asli sekolah memakai hash tersebut. Contoh ada di `admin-schema.sql` bagian paling bawah.

Setelah login pertama:

- Buka menu Akun Saya.
- Pastikan email admin adalah email asli.
- Ubah password dari panel admin.
- Uji fitur lupa password.

## 6. Keamanan Web

Checklist penting:

- `APP_ENV=production`
- `APP_DEBUG=false`
- HTTPS aktif
- `config/`, `includes/`, `migrations/`, `logs/`, dan `backups/` tidak dapat diakses publik
- `uploads/.htaccess` melarang eksekusi PHP
- `.env` tidak ikut repository
- `admin/` memakai login dan session timeout
- Error log masuk ke `logs/php-error.log`

## 7. Verifikasi Setelah Deploy

Cek halaman berikut:

- `/`
- `/berita.html`
- `/pengumuman.php`
- `/agenda.php`
- `/kegiatan.php`
- `/ekstrakurikuler.php`
- `/prestasi.php`
- `/admin/login.php`
- `/admin/forgot-password.php`
- `/admin/system-health.php`

Pastikan admin dapat tambah/edit/hapus konten dan gambar tampil di halaman publik.

## 8. Backup dan Rollback

Sebelum update besar:

- Backup database.
- Backup folder `uploads/`.
- Simpan backup di luar folder publik website.

Rollback database dilakukan dengan restore file backup SQL. Jangan mencoba rollback migrasi secara manual tanpa backup.