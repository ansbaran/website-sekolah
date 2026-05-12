# Migration Versioning — SD Cahaya Harapan Bekasi

## Tujuan

File migrasi ini membuat struktur database yang dibutuhkan untuk fitur CMS, logging, media, dan backup.

## Cara pakai

1. Pastikan koneksi database sudah dikonfigurasi di `config/config.php`.
2. Jalankan dari command line di folder proyek:
   ```bash
   php migrations/migrate.php status
   php migrations/migrate.php apply
   ```

## Struktur migrasi

- Semua file `.sql` di folder `migrations/` akan diproses berurutan.
- Status migrasi disimpan di tabel `schema_migrations`.

## Safe upgrade

- Script hanya menjalankan migrasi yang belum ada di tabel `schema_migrations`.
- Setiap file `.sql` diproses satu per satu di dalam transaction.
- Jika sebuah migrasi gagal, transaction dibatalkan dan eksekusi dihentikan.

## Rollback note

Rollback database harus dilakukan dari backup SQL yang valid.
Langkah aman rollback:

1. Hentikan akses admin jika perlu.
2. Pulihkan database dari file SQL backup terbaru.
3. Cek kembali `admin/system-health.php` dan `admin/system.php`.

> Jangan mencoba rollback langsung dengan script migrasi. Gunakan backup untuk restore produksi.
