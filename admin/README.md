# Admin CMS Setup

Sistem admin ini dibangun terpisah dari frontend agar visual public tetap aman.

## Struktur folder

- `admin/`
  - `login.php`
  - `dashboard.php`
  - `news.php`, `news-form.php`
  - `gallery.php`, `gallery-form.php`
  - `achievements.php`, `achievement-form.php`
  - `slider.php`, `slider-form.php`
  - `announcements.php`, `announcement-form.php`
  - `logout.php`
  - `assets/css/admin.css`
  - `assets/js/admin.js`
  - `includes/header.php`, `sidebar.php`, `footer.php`
- `config/`
  - `config.php`
  - `db.php`
- `includes/`
  - `functions.php`
  - `auth.php`
  - `csrf.php`
- `api/`
  - `upload.php`
- `uploads/`
  - `news/`, `gallery/`, `achievements/`, `slider/`
- `admin-schema.sql`

## Langkah pemasangan di hosting / cPanel

1. Upload seluruh folder ke root website (`public_html` atau folder publik).
2. Pastikan folder `uploads/` dapat ditulis oleh web server.
3. Buka `config/config.php` dan sesuaikan:
   - `DB_HOST`
   - `DB_NAME`
   - `DB_USER`
   - `DB_PASS`
4. Import file SQL `admin-schema.sql` ke database MySQL.
5. Ganti nilai `REPLACE_WITH_PASSWORD_HASH` di `users` dengan hash password aman dari PHP.
   - Contoh di server: `<?php echo password_hash('Admin123!', PASSWORD_DEFAULT); ?>`
6. Akses admin melalui `https://domainanda.com/admin/login.php`.

## Koneksi frontend dengan database admin

Sistem admin saat ini tidak mengubah frontend existing. Untuk menampilkan konten database di halaman publik, gunakan salah satu pendekatan berikut:

1. Ubah halaman yang ingin terintegrasi menjadi PHP dan ambil data dari tabel seperti `news`, `gallery`, `announcements`, dsb.
2. Buat endpoint API tambahan (misalnya `api/public-news.php`) yang mengembalikan JSON, kemudian panggil dari frontend.
3. Biarkan halaman public tetap statis, lalu export/refresh data dari admin secara manual jika diperlukan.

## Best practice pemeliharaan

- Jangan hapus folder `config/` dan `includes/`.
- Pastikan `uploads/` hanya dapat menulis file gambar.
- Gunakan HTTPS dan aktifkan `session.cookie_secure` di `config/config.php` jika tersedia.
- Buat akun operator terpisah untuk staff yang tidak perlu akses superuser.
