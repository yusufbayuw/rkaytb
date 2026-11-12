# Rilis RKA pada aaPanel

Lingkungan yang digunakan: lokal untuk pengujian, aaPanel untuk production, tanpa staging terpisah. Panduan ini menyiapkan langkah rilis; perubahan kode ini tidak mengakses atau melakukan deployment ke production.

## Konfigurasi situs

Pasang PHP 8.4 beserta ekstensi pada README, Composer 2, MySQL 8, dan Nginx/Apache melalui aaPanel. Pilih PHP 8.4 untuk situs dan CLI; periksa keduanya karena pemilihan PHP situs tidak selalu mengganti PHP terminal atau Cron. Tetapkan `memory_limit` minimal 512M, `upload_max_filesize=12M`, `post_max_size=16M`, dan batas unggahan web server sedikit lebih besar dari 10 MB.

Untuk penyuntingan tabel sampai 500 baris, gunakan `memory_limit=1024M` dan `max_execution_time=180` pada PHP situs. Sesuaikan timeout PHP-FPM serta web server agar tidak memutus request sebelum 180 detik (pada Nginx: `fastcgi_read_timeout 180s` dalam konfigurasi FastCGI situs yang aktif). Ukuran halaman 50–100 lebih ringan untuk penyuntingan sehari-hari.

Di Website → PHP Project, atur direktori proyek sebagai Site directory dan `/public` sebagai Running directory. Aktifkan HTTPS dan gunakan preset rewrite Laravel. Jika preset tidak tersedia pada Nginx, aturan routing yang diperlukan adalah:

```nginx
location / {
    try_files $uri $uri/ /index.php?$query_string;
}
```

Terapkan pada konfigurasi situs yang sesuai tanpa membuat blok `location /` ganda. aaPanel mendokumentasikan pilihan PHP dan Running directory di [PHP Project](https://www.aapanel.com/docs/Function/php.html).

## Persiapan release

Di lokal, jalankan suite SQLite/MySQL, tes browser, `composer validate --strict`, audit dependensi, dan build produksi. Gunakan `composer.lock` dan `package-lock.json` yang sama. Node 24 diperlukan pada mesin build; bila aset dibangun lokal, server tidak perlu menjalankan Vite atau server Node.

Simpan kode release lama beserta asetnya. Cadangkan database dan seluruh `storage/app`, `.env`, serta `APP_KEY` sebelum beralih. Unggah kode release baru dan `public/build` hasil build. Jangan unggah `.tools`, `node_modules`, data browser, atau runtime PHP Windows.

Untuk instalasi pertama, salin `.env.production.example` menjadi `.env` dan isi URL HTTPS, database serta kredensialnya. Buat database/user melalui aaPanel. Untuk upgrade, pertahankan `.env`, `APP_KEY`, dan berkas privat yang sudah ada. Direktori `storage` dan `bootstrap/cache` harus dapat ditulis oleh user situs dan user CLI yang melakukan rilis.

## Instalasi dan perpindahan release

Jalankan dari terminal dengan path PHP 8.4 dan Composer yang benar pada server. Ganti ketiga nilai contoh berikut dengan lokasi sebenarnya:

```bash
RKA_ROOT=/absolute/path/to/release
RKA_PHP=/absolute/path/to/php84
RKA_COMPOSER=/absolute/path/to/composer.phar
cd "$RKA_ROOT"
"$RKA_PHP" -v
"$RKA_PHP" "$RKA_COMPOSER" install --no-dev --prefer-dist --optimize-autoloader --no-interaction
"$RKA_PHP" "$RKA_COMPOSER" check-platform-reqs --no-dev
```

Hanya pada instalasi pertama dengan key kosong, jalankan `php artisan key:generate` memakai PHP 8.4 di atas. Akun administrator dibuat melalui `php artisan rka:install` sesudah migrasi, dengan kata sandi pilihan sendiri. Jangan membuat key baru ketika upgrade.

Dalam jendela pemeliharaan, arahkan situs ke release baru setelah kode, dependensi, konfigurasi, dan aset siap. Jika situs lama sudah berjalan, aktifkan maintenance mode sebelum perpindahan. Gunakan PHP yang sama untuk seluruh perintah:

```bash
"$RKA_PHP" artisan down
"$RKA_PHP" artisan optimize:clear
"$RKA_PHP" artisan migrate --force
"$RKA_PHP" artisan rka:sync-permissions
"$RKA_PHP" artisan permission:cache-reset
"$RKA_PHP" artisan config:cache
"$RKA_PHP" artisan route:cache
"$RKA_PHP" artisan view:cache
"$RKA_PHP" artisan schedule:list
"$RKA_PHP" artisan up
```

Periksa login/logout, profil, akses unit/organisasi, satu penyimpanan anggaran, unggahan privat, unduhan buku, serta angka pada ekspor. Session lama dapat memerlukan login ulang karena serialisasi session berubah ke JSON. File bukti tetap memakai disk `local` dengan root `storage/app`.

## Scheduler dan cache

Gunakan cache `file` persisten untuk satu server. Jika ada beberapa server, gunakan cache bersama seperti Redis. `QUEUE_CONNECTION=sync` dapat dipertahankan; worker hanya diperlukan bila queue diubah ke layanan asinkron.

Tambahkan satu tugas Shell Script setiap menit pada aaPanel Cron. Isi perintah dengan direktori proyek dan executable PHP 8.4 yang telah diverifikasi, misalnya `cd /absolute/path/to/release && /absolute/path/to/php84 artisan schedule:run`. Jalankan memakai user situs yang memiliki akses ke direktori aplikasi. Pastikan tidak ada Cron kedua untuk scheduler yang sama. Petunjuk panel tersedia pada [aaPanel Cron](https://www.aapanel.com/docs/Function/Cron.html).

Pengingat dijadwalkan pukul 07.30 menurut `RKA_TIMEZONE`. `RKA_TASK_REMINDER_MAIL_ENABLED=false` dan `RKA_ENABLE_OFFICIAL_PUBLICATION=false` tetap dipertahankan sampai langkah aktivasi tersendiri.

Plugin health menggunakan scheduler yang sama: pemeriksaan setiap lima menit dan pembersihan riwayat health setiap hari. Jangan menambah Cron health kedua. Menu Sistem hanya untuk superadmin; audit aplikasi tidak dipangkas otomatis. Gunakan `LOG_CHANNEL=daily` untuk log teknis dengan retensi 14 hari. Rincian konfigurasi dan pembatasan akses tersedia di [panduan plugin](FILAMENT_PLUGINS.md).

## Rollback

Jika pemeriksaan production gagal, aktifkan maintenance mode, kembalikan kode dan aset ke release sebelumnya beserta runtime PHP yang cocok, lalu bersihkan/bangun ulang cache release tersebut. Pertahankan key dan berkas privat. Gunakan migrasi maju untuk koreksi skema; jangan menjalankan `migrate:fresh` atau rollback migrasi bisnis pada production. Pemulihan database dari cadangan hanya dilakukan bila memang diperlukan dan telah diverifikasi. Uji login, unduhan, dan angka kembali sebelum membuka akses.
