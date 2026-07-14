# SD Cahaya Harapan Bekasi

Website profil sekolah dengan CMS admin untuk mengelola berita, pengumuman, agenda, prestasi, galeri, kegiatan, ekstrakurikuler, guru/staff, dan informasi sekolah.

## Struktur Folder

```text
website-sekolah/
|-- index.html
|-- admin/
|-- api/
|-- assets/
|   |-- css/
|   |-- js/
|   `-- img/
|-- components/
|-- config/
|-- includes/
|-- migrations/
|-- uploads/
|-- backups/
|-- cache/
`-- logs/
```

## CSS Architecture

- `assets/css/base.css`: reset dan design tokens.
- `assets/css/layout.css`: container, section, grid, split layout.
- `assets/css/components.css`: navbar, button, card, stats, gallery, contact, footer.
- `assets/css/hero.css`: hero slider dan typing area.
- `assets/css/utilities.css`: helper classes.
- `assets/css/responsive.css`: breakpoint responsif.

## JavaScript Architecture

- `assets/js/main.js`: entry point.
- `assets/js/navbar.js`: navbar dan dropdown.
- `assets/js/typing.js`: typing animation.
- `assets/js/cms-connector.js`: integrasi data publik dari CMS/API.

## Cara Menjalankan Lokal

1. Letakkan folder di `htdocs` XAMPP.
2. Jalankan Apache dan MySQL.
3. Import database atau jalankan migrasi:

```bash
php migrations/migrate.php apply
```

4. Buka `http://localhost/website-sekolah/`.

## Environment

Konfigurasi production dapat dilihat di `.env.example`. Untuk deployment lengkap, ikuti `DEPLOYMENT.md`.