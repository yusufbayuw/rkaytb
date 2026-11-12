# Pelengkap plugin RKA

Pemeriksaan katalog: 12 November 2026. Dari 1.114 entri gratis yang unik pada 124 halaman, 785 tercantum dalam filter Filament 5 pada 88 halaman. Inventaris tersedia di [CSV katalog](PLUGIN_CATALOG_2026-11-12.csv). Seluruh entri disaring melalui metadata katalog; pemeriksaan manifest dan kode difokuskan pada kandidat terpilih, bukan audit seluruh source code 1.114 paket. Kolom health score merupakan informasi katalog saat pemeriksaan, bukan jaminan keamanan.

## Paket yang digunakan

| Paket | Versi terpasang | Peran |
|---|---|---|
| bezhansalleh/filament-shield | 4.3.1 | Matriks permission Role |
| mradder/filament-logger | 1.7.1 | Audit resource dan autentikasi |
| shuvroroy/filament-spatie-laravel-health | 3.3.3 | Pemeriksaan kesehatan |
| achyutn/filament-log-viewer | 2.5.0 | Pembaca log teknis |
| majid-shafik/filament-table-sticky-header | 1.0.1 | Header tabel tetap terlihat |
| majid-shafik/filament-table-drag-scroll | 1.0.1 | Geser horizontal dengan mouse |
| spatie/laravel-activitylog | 5.1.4 | Penyimpanan audit, dependensi langsung |
| spatie/laravel-health | 1.41.0 | Check dan riwayat kesehatan, dependensi langsung |

Semua paket ini memakai lisensi MIT. Versi lengkap dan referensi source tercatat dalam composer.lock. Laravel 13, Filament 5, Livewire 4, dan PHP 8.4 tetap digunakan. Sumber pemilihan: [katalog Filament](https://filamentphp.com/plugins?price=free), [Shield](https://filamentphp.com/plugins/bezhansalleh-shield), [Logger](https://filamentphp.com/plugins/daniel-green-logger), [Health](https://filamentphp.com/plugins/shuvroroy-spatie-laravel-health), [Log Viewer](https://filamentphp.com/plugins/achyutn-log-viewer), [Sticky Header](https://filamentphp.com/plugins/majid-shafik-al-nashri-table-sticky-header), dan [Drag Scroll](https://filamentphp.com/plugins/majid-shafik-al-nashri-table-drag-scroll).

## Shield dan pengelolaan akses

Daftar Role tetap berada di `/admin/roles`, dengan halaman create/edit/view Shield. Hanya ada satu menu dan satu resource Role. Guard ditentukan server; pengelola tidak perlu mengisi nama guard atau memahami nama kelas model.

Permission resource Pengguna, Role, Permission, Unit, Akun, dan Settings tetap memakai `read`, `create`, `update`, `delete` dengan nama existing, misalnya `read:user`. `viewAny` dan `view` memetakan ke permission `read` yang sama. Permission tambahan tetap terlihat dan disimpan tanpa perubahan nama. Policy bisnis enam modul legacy, membership organisasi, penguncian anggaran, dan maker-checker Workspace tetap menjadi batas aksesnya sendiri.

Gunakan `php artisan rka:sync-permissions --dry-run` untuk melihat permission standar yang belum tersedia, dan `php artisan rka:sync-permissions` untuk menambahkannya. Perintah bersifat idempotent: tidak mengubah ID, nama, role, atau assignment existing. `rka:install` memanggil sinkronisasi ini saat penyiapan awal.

Perubahan role, permission, impor permission, serta assignment hanya untuk superadmin. Hak baca existing tetap mengikuti policy. Pengecekan server melengkapi pembatasan formulir. Role superadmin tidak dapat dihapus atau diganti nama/guard. Penghapusan pengguna atau pencabutan role administrator terakhir ditolak; perubahan assignment diserialkan dengan kunci row role superadmin dalam transaksi.

Model Role aplikasi merupakan turunan model Spatie pada tabel yang sama, tanpa perubahan ID/skema role. Proteksi penghapusan dijalankan sebelum listener Spatie melepaskan pivot agar percobaan menghapus superadmin tidak mencabut membership lebih dahulu.

Gate superadmin existing tetap satu. Role otomatis Shield, panel_user, Spatie Teams, penggantian policy, dan generasi policy otomatis dinonaktifkan. Perintah setup/generate/publish/seeder/super-admin Shield dilarang pada production; gunakan installer dan sinkronisasi RKA yang terkendali.

## Menu Sistem

Alamat berikut mengikuti `FILAMENT_PATH` dan domain panel. Hanya superadmin yang dapat mengakses halaman maupun aksi Livewire-nya; pencabutan role berlaku pada request berikutnya walaupun halaman masih terbuka.

| Alamat default | Fungsi |
|---|---|
| `/admin/system/activities` | Audit hanya-baca, pencarian/filter, ekspor CSV/JSON |
| `/admin/system/health` | Hasil pemeriksaan dan tombol Periksa sekarang |
| `/admin/system/logs` | Log teknis hanya-baca beserta filter dan stack trace |

Audit memakai satu observer resource untuk menghindari pencatatan ganda. Duplikasi legacy dikenali sebagai Replicated dengan referensi record sumber. Assignment dicatat ketika benar-benar berubah, dan penggantian password hanya mencatat metadata tanpa password/hash. Token dan secret disamarkan/dikecualikan sebelum penyimpanan. Audit tetap dalam transaksi bisnis; buffer ditutup agar rollback tidak meninggalkan audit transaksi yang gagal.

Audit Workspace pada `rka_audit_events`, snapshot, serta hash existing tidak dipindahkan atau disalin otomatis. Tidak ada jadwal pruning audit, aksi hapus/pulihkan, webhook, email alert, maupun ekspor antrean yang diaktifkan.

Health check memeriksa database, cache, dan penyimpanan privat/cache. Pada production, debug mode dan cache optimasi juga diperiksa. Scheduler menjalankan `health:check` setiap lima menit dan memangkas hanya riwayat model health setiap hari dengan retensi tujuh hari. Hasil kosong atau lebih dari 15 menit ditampilkan sebagai belum diperiksa/kedaluwarsa. Notifikasi health dan endpoint monitoring publik nonaktif.

Pembaca log tidak mengimplementasikan kemampuan penghapusan. Pencarian berkas dibatasi melalui realpath ke `storage/logs`; berkas di luar direktori atau lebih dari 2 MB dilewati dan diberitahukan. Hasil parsing tidak disimpan dalam cache lintas request. Isi email dalam log ditampilkan sebagai teks yang di-escape. Parser mendukung nama environment bertanda hubung dan melewati level log yang tidak dikenali. Template production memakai `LOG_CHANNEL=daily` dengan retensi 14 hari.

## Tabel dan pengoperasian

Sticky header dan drag scroll berlaku pada tabel Filament, termasuk tabel anggaran bulanan yang lebar. Gestur drag mengecualikan tombol, tautan, input, checkbox, serta kontrol pengurutan. Header asli dipakai, sehingga tidak ada salinan kontrol sorting/selection. Tabel HTML Workspace mempertahankan perilaku existing.

Flag `RKA_STICKY_TABLE_HEADERS` dan `RKA_TABLE_DRAG_SCROLL` bernilai true secara default. Keduanya dapat dinonaktifkan secara terpisah lalu cache konfigurasi dibangun ulang. Aset plugin diterbitkan oleh `composer install` melalui `filament:upgrade`; build tema tetap memakai `npm ci` dan `npm run build`.

Untuk penggunaan sehari-hari, pilih 50–100 baris saat menyunting banyak kolom. Ukuran 500 baris tetap tersedia, tetapi menghasilkan halaman besar dan membutuhkan waktu serta memori lebih banyak. Konfigurasi PHP dan web server untuk ukuran ini tersedia di [panduan aaPanel](AAPANEL_DEPLOYMENT.md#konfigurasi-situs). Pengujian browser memeriksa pagination/drag/header dengan 500 baris dan penyimpanan desimal dengan 50 baris; suite aplikasi juga memeriksa pembukaan mode edit dengan 500 record.

Upgrade lokal atau aaPanel: pasang dependensi dari lockfile, jalankan `php artisan migrate --force`, `php artisan rka:sync-permissions`, `php artisan permission:cache-reset`, lalu bangun cache aplikasi. Tetap gunakan satu Cron `schedule:run` setiap menit; tidak perlu menambah Cron health terpisah. Backup tetap dikelola sesuai panduan aaPanel. Tidak ada deployment production atau pengaktifan publikasi/email oleh integrasi ini.

## Bukti pengujian

Verifikasi lokal pada 12 November 2026:

| Pemeriksaan | Hasil |
|---|---|
| SQLite | 117 tes / 871 assertions lulus, tanpa tes dilewati |
| MySQL 8.4.13 | 117 tes / 871 assertions lulus, tanpa tes dilewati |
| Browser Chromium | 16 skenario lulus: delapan alur pada desktop dan mobile, tanpa pengecualian |
| Penutupan runner Windows | Dua tes logout desktop/mobile lulus ulang; server PHP sementara dan metadata berhenti/dibersihkan otomatis |
| Rehearsal migrasi | Termasuk dalam kedua suite: sentinel, rollback, dan pemasangan ulang tabel platform serta audit/health |
| Sintaks PHP | 260 berkas lulus |
| Build produksi | Lulus |
| Composer | Validasi ketat dan simulasi instalasi lockfile lulus; audit tanpa advisory atau paket abandoned |
| npm | Audit tanpa kerentanan |
| Cache rilis | Blade, konfigurasi, route, keunikan route, pendaftaran perintah, dan scheduler lulus |

Seluruh tes existing tetap dijalankan; penyesuaian tes Role mengikuti perpindahan modal lama ke halaman Shield tanpa mengurangi pemeriksaan hasil penyimpanan dan assignment. Tambahan tes memeriksa matriks dan permission tambahan, guard, pencabutan akses saat halaman terbuka, proteksi administrator terakhir, audit tunggal dan rollback, metadata password, ekspor audit, health kosong/gagal/kedaluwarsa, pembaca log, dan larangan perintah Shield pada production.

Suite browser lengkap memeriksa seluruh resource/profil dan tema, perpindahan organisasi serta formulir reaktif, impor permission, pratinjau/unduhan/cetak ekspor, pembatasan akses dan unduhan privat, logout, matriks Shield serta menu Sistem, dan tabel lebar. Pemeriksaan tabel mencakup checkbox/sorting, drag mouse atau gestur sentuh, satu header asli yang tetap terlihat, pagination 500 baris, serta perubahan desimal yang tetap tersimpan setelah reload. Data browser hanya berada di database fixture terisolasi.

Log lokal tersimpan pada `.tools/plugins-final-sqlite.txt`, `.tools/plugins-final-mysql.txt`, `.tools/plugins-browser-final.txt`, `.tools/plugins-browser-cleanup.txt`, `.tools/plugins-lint.txt`, `.tools/plugins-build.txt`, `.tools/plugins-composer-audit.json`, `.tools/plugins-npm-audit.json`, dan `.tools/plugins-release-check.txt`. Suite browser menulis tangkapan layar ke `test-results`, yang dibangun ulang pada setiap run. Folder `.tools` dan `test-results` tidak disertakan dalam Git atau deployment. CI menjalankan pemeriksaan yang sama serta browser desktop/mobile dan menyimpan artefak browser; hasil hosted CI tidak diasumsikan dari hasil lokal. Server MySQL/browser sementara sudah dihentikan; instalasi Herd tetap tersedia.
