# Admin CMS Setup

Sistem admin mengelola konten website publik: berita, pengumuman, agenda, prestasi, galeri, kegiatan, ekstrakurikuler, guru/staff, tentang sekolah, media, dan pengaturan akun.

## Struktur Utama

- `admin/`: halaman dashboard dan form pengelolaan konten.
- `api/`: endpoint publik dan endpoint internal admin.
- `config/`: konfigurasi aplikasi dan database.
- `includes/`: helper, auth, CSRF, upload, dan fungsi data.
- `uploads/`: file gambar yang diunggah dari admin.
- `migrations/`: migrasi database production.
- `admin-schema.sql`: schema referensi untuk instalasi awal.

## Setup Hosting

1. Upload proyek ke hosting.
2. Isi environment production sesuai `.env.example`.
3. Pastikan folder berikut writable: `uploads/`, `backups/`, `cache/`, `logs/`.
4. Jalankan migrasi:

```bash
php migrations/migrate.php status
php migrations/migrate.php apply
```

5. Buat akun admin pertama dengan email aktif sekolah. Untuk membuat hash password:

```bash
php reset-admin.php "PasswordBaruYangKuat"
```

6. Jalankan contoh `INSERT` yang sudah disesuaikan di `admin-schema.sql`, atau buat akun langsung dari database dengan hash tersebut.
7. Login melalui `/admin/login.php`.
8. Buka menu `Akun Saya` untuk memastikan email dan password admin sudah benar.

## Email dan Lupa Password

Fitur lupa password memakai email admin yang tersimpan di tabel `users`. Untuk production, isi SMTP di environment:

```env
MAIL_FROM_ADDRESS=no-reply@domain-sekolah.sch.id
MAIL_FROM_NAME="SD Cahaya Harapan Bekasi"
SMTP_HOST=smtp.domain-sekolah.sch.id
SMTP_PORT=587
SMTP_USERNAME=no-reply@domain-sekolah.sch.id
SMTP_PASSWORD=password_email_atau_app_password
SMTP_ENCRYPTION=tls
```

Jika SMTP belum diatur, aplikasi akan mencoba `mail()` PHP dan menulis fallback ke `logs/password-reset.log` saat pengiriman gagal.

## Best Practice

- Gunakan HTTPS.
- Jangan commit file `.env`.
- Gunakan email admin yang benar-benar aktif.
- Ganti password secara berkala lewat menu `Akun Saya`.
- Backup database dan `uploads/` sebelum update besar.
- Pastikan `uploads/.htaccess` tetap melarang eksekusi script.