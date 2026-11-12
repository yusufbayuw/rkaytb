# RKA Platform

Aplikasi Rencana Kerja dan Anggaran dengan Laravel 13, Filament 5, dan Livewire 4. Modul RKA existing tetap tersedia bersama Workspace RKA untuk organisasi, anggaran, sharing budget, jurnal, approval, rekonsiliasi, closing, narasi, bukti privat, dan Buku RKA.

## Kebutuhan

- PHP 8.4 dan Composer 2.
- Ekstensi PHP: bcmath, ctype, curl, dom, fileinfo, filter, gd, iconv, intl, json, mbstring, openssl, pdo_mysql/pdo_sqlite, simplexml, tokenizer, xml, xmlreader, xmlwriter, zip, zlib.
- Node.js 24 LTS dan npm untuk membangun aset.
- SQLite untuk instalasi lokal atau MySQL 8 untuk server. CI memeriksa MySQL 8.0 dan 8.4.
- Untuk unggahan 10 MB: `upload_max_filesize=12M`, `post_max_size=16M`; rekomendasi `memory_limit=512M` atau lebih.
- Untuk tabel 500 baris dalam mode edit, gunakan `memory_limit=1024M`, `max_execution_time=180`, dan timeout request web server minimal 180 detik. Pilih ukuran halaman lebih kecil untuk penyuntingan yang lebih ringan.

## Instalasi baru di Windows dan Herd

Gunakan PHP 8.4 untuk proyek ini. Dari direktori proyek, `herd isolate 8.4` memilih PHP proyek tanpa mengganti versi aplikasi lain.

```powershell
composer install
if (!(Test-Path .env)) { Copy-Item .env.example .env }
if (!(Test-Path database/database.sqlite)) { New-Item -ItemType File database/database.sqlite }
```

Isi `APP_URL` sesuai alamat lokal. Pada instalasi baru dengan `APP_KEY` kosong, jalankan `php artisan key:generate`. Simpan key yang sudah ada saat melakukan upgrade.

```powershell
php artisan migrate
php artisan rka:install
npm ci
npm run build
```

`rka:install` meminta nama, email, dan kata sandi administrator minimal 12 karakter. Perintah menyiapkan dua pengaturan teknis bernilai false, tanpa organisasi atau akun contoh. Pengulangan tidak mengubah administrator yang sudah ada dan tidak menaikkan hak akses akun biasa.

Buka `/admin/login` pada alamat Herd, atau jalankan `php artisan serve`. Administrator dapat membuat unit lama untuk modul legacy dan membuat organisasi serta membership sendiri melalui Workspace RKA. Anggota Workspace tidak wajib memiliki unit legacy.

## Pengujian

```powershell
php artisan test
npm run build
npx playwright install chromium
npm run test:browser
```

PHPUnit memakai SQLite memory secara default. Untuk MySQL, gunakan database **khusus tes** bernama `rka_test`, lalu set `DB_CONNECTION=mysql`, `DB_HOST`, `DB_PORT`, `DB_DATABASE=rka_test`, `DB_USERNAME`, dan `DB_PASSWORD` pada environment proses pengujian. Suite migrasi memang menghapus dan membangun ulang database tes yang dibatasi tersebut.

Pengujian browser memakai `.tools/rka_browser.sqlite`, cache file persisten, environment `browser-testing`, dan server sementara port 8771. Runner membangun ulang hanya database browser ini. Akun contoh dibuat hanya oleh `BrowserTestSeeder` yang menolak database dan environment lain. Jangan gunakan akun fixture pada instalasi aplikasi.

Untuk menyimpan contoh ekspor panjang bagi QA:

```powershell
$env:RKA_EXPORT_QA='1'
php artisan test --filter RkaPlatformBookExportTest
Remove-Item Env:RKA_EXPORT_QA
```

Artefak tersimpan di `.tools/export-qa`, dan tidak masuk Git.

## Konfigurasi operasional

Enam plugin pelengkap (Shield, audit, health, pembaca log, sticky header, dan drag scroll) dijelaskan dalam [panduan plugin](docs/FILAMENT_PLUGINS.md). Sesudah upgrade, jalankan `php artisan rka:sync-permissions`; instalasi pertama melalui `rka:install` sudah menjalankannya. Permission lama tetap dipertahankan dan perubahan akses hanya untuk superadmin.

- `FILAMENT_PATH`, `FILAMENT_DOMAIN`, dan `FILAMENT_AUTH_GUARD` mempertahankan kontrak panel existing. Default: `/admin` dan guard `web`.
- Profil tersedia di `/admin/profile`. Endpoint Sanctum `/api/user` serta unduhan buku/bukti tetap dipertahankan.
- Bukti privat tetap berada di disk `local` dengan root `storage/app`.
- Gunakan cache persisten `file` pada satu server atau Redis bersama pada beberapa server. Notifikasi Filament 5 dan deduplikasi email membutuhkan cache yang bertahan lintas request; `array` hanya cocok untuk tes backend.
- Session memakai serialisasi JSON. Pengguna perlu login kembali ketika berpindah dari rilis lama.
- Publikasi resmi dan pengingat email dimatikan secara default. Aktifkan hanya setelah konfigurasi dan verifikasi alur bisnis yang relevan.
- Scheduler: jalankan `php artisan schedule:run` setiap menit. Pengingat berjalan pukul 07.30 menurut `RKA_TIMEZONE`; simulasi tersedia melalui `php artisan rka:remind-tasks --dry-run`.

## Deployment dan rollback

Lingkungan proyek ini adalah lokal untuk pengujian dan aaPanel untuk production. Bangun serta uji release secara lokal menggunakan lockfile. Cadangkan database, seluruh `storage/app`, `.env`, dan `APP_KEY`; siapkan PHP 8.4 serta ekstensi yang sama pada aaPanel.

Template `.env.production.example` disediakan untuk satu server HTTPS dengan MySQL 8. Panduan langkah rilis dan rollback tersedia di [deployment aaPanel](docs/AAPANEL_DEPLOYMENT.md). Template ini tidak mengaktifkan pengiriman email/publikasi resmi.

Pada release produksi, gunakan `composer install --no-dev --optimize-autoloader` dan aset dari `npm ci && npm run build`. Jalankan migrasi maju dengan `php artisan migrate --force`, bersihkan cache permission (`php artisan permission:cache-reset`), lalu bangun cache konfigurasi, route, dan view. Pastikan server dapat menulis `storage` dan `bootstrap/cache`, dan arahkan document root ke `public`.

Selesaikan login, akses peran/unit/organisasi, unggahan, ekspor, dan pengecekan angka secara lokal sebelum beralih ke release baru, lalu lakukan pemeriksaan singkat di production dalam jendela pemeliharaan. Jika perlu rollback, kembalikan pasangan kode, aset, konfigurasi, dan runtime PHP release sebelumnya; jangan menjalankan rollback migrasi atau `migrate:fresh` pada data produksi. Pulihkan database hanya melalui prosedur pemulihan yang telah diverifikasi.

Rincian perubahan dan matriks penerimaan tersedia di [catatan upgrade](docs/LARAVEL_FILAMENT_UPGRADE.md). Aturan dan batas fungsi RKA tersedia di [dokumentasi platform](docs/RKA_WHITE_LABEL_IMPLEMENTATION.md).
