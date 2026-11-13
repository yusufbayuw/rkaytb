# Implementasi upgrade Laravel dan Filament

Tanggal pemeriksaan: 12 November 2026.

Pembaruan 13 November 2026: modul Employee KPI Score, Employee, KPI Target, dan Position dihapus atas permintaan pengguna. Resource aktif kini 13, setelah penambahan audit Sistem dan penghapusan empat resource tersebut. Model, halaman, event/listener pembentukan skor, dan fixture pegawai sudah dilepas. Migrasi/tabel historis dipertahankan agar data existing tidak hilang; eksportir audit memakai type/ID dan snapshot tersimpan tanpa memuat model yang sudah dihapus. Catatan checkpoint dan hasil pengujian bertanggal 12 November di bawah merupakan riwayat sebelum penghapusan ini. KPI keuangan pada Workspace tetap menjadi bagian alur RKA.

Verifikasi penghapusan: seluruh 118 tes SQLite lulus dengan 897 assertions; tes khusus audit historis lulus dengan 27 assertions. Build produksi, sintaks tujuh berkas PHP yang berubah/ditambahkan, serta kompilasi Blade/config/route dan keunikan route lulus. Browser pada situs lokal membuktikan login/logout superadmin, hilangnya empat menu, status 404 untuk keempat URL lama, serta akses normal ke RKA Investasi, Workspace, dan audit. Log tersedia di `.tools/remove-hr-tests.txt`, `.tools/remove-hr-audit-test.txt`, `.tools/remove-hr-browser.txt`, `.tools/remove-hr-build.txt`, dan `.tools/remove-hr-release-check.txt`.

## Target

Laravel 13.42.0, Filament 5.11.2, Livewire 4, PHP 8.4, Tailwind 4, Vite 8, Node 24. Versi lengkap yang dipasang tercatat di composer.lock dan package-lock.json. Pekerjaan mencakup fitur existing dan perbaikan fungsi yang ditemukan, tanpa menambahkan fitur roadmap yang belum diimplementasikan.

## Checkpoint yang dijalankan

| Checkpoint | Bukti |
|---|---|
| Laravel 10 / Filament 2 / PHP 8.2 | 84 tes, 383 assertions; build frontend berhasil |
| Laravel 10 / Filament 3 | 89 tes, 444 assertions; build berhasil |
| Laravel 11 / Filament 3 | Suite existing dan smoke 89 tes lulus; 2 tes aksi enam modul legacy dengan 204 assertions lulus; build berhasil |
| Laravel 12 / Filament 3 / PHP 8.4 | 96 tes lulus, ditambah 3 tes master/Sanctum; build berhasil |
| Laravel 12 / Filament 4 | 100 tes, 682 assertions; build Tailwind 4/Vite 8 berhasil |
| Laravel 12 / Filament 5 / Livewire 4 | 100 tes, 682 assertions; build berhasil |
| Laravel 13 / Filament 5 | Suite awal 101 tes, 692 assertions lulus pada SQLite dan MySQL 8.4; pengujian tambahan dicatat pada hasil verifikasi akhir |

## Perubahan utama

- Panel dipusatkan pada AdminPanelProvider; 16 resource, dashboard, profil, Workspace, ikon, schema, action dan widget mengikuti Filament 5.
- Alamat panel, slug resource, profil, endpoint Sanctum, dan unduhan privat tetap dipertahankan. Guard akses panel memakai canAccessPanel.
- Plugin ekspor/import lama diganti layanan aplikasi dengan validasi dan otorisasi. Ekspor mendukung XLSX/CSV/PDF, kolom tambahan, nama berkas, preview, cetak, dan query terfilter. Impor permission mendukung CSV/XLS/XLSX, pemetaan kolom, duplikat, dan laporan kesalahan baris.
- Ekspor memakai label tampilan untuk kolom nonangka (termasuk nama unit), sambil mempertahankan angka keuangan sebagai angka dan kode akun sebagai teks.
- Dompdf dan PhpSpreadsheet menjadi dependensi langsung. Plugin settings/workflow/menu-scroll serta fast-paginate yang tidak lagi diperlukan dilepas.
- Mode edit memakai nama action yang stabil, memperbarui tabel langsung, dan menghormati penguncian pada server. Edit inline, duplikasi, edit, dan delete dibatasi unit dan kewenangan. Duplikasi tidak lagi menyalin atribut sementara CTE depth/path ke database.
- Pengaturan kosong dibaca sebagai false tanpa menulis database. Cache pengaturan berlaku per request dan dibatalkan saat model berubah, sehingga pemeriksaan per baris tidak menghasilkan query pengaturan berulang.
- Administrator dan pengguna tanpa unit tidak lagi menyebabkan kegagalan dashboard/widget atau tabel pengguna. Pengguna Workspace dapat dibuat tanpa unit legacy.
- Pembuatan pegawai menghasilkan skor KPI awal nol sekali per target; relasi balik target/jabatan menggunakan pivot yang tepat.
- Pergantian organisasi/siklus membersihkan draf formulir, pilihan record, dan unggahan sementara. Form yang membutuhkan perubahan langsung menggunakan binding Livewire yang sesuai.
- Bagian Workspace yang dibuka pengguna tetap terbuka setelah pembaruan Livewire. Email pengguna duplikat menghasilkan pesan validasi, sedangkan menyimpan email record yang sama tetap diperbolehkan.
- Impor workbook hanya membaca lembar pertama yang sudah diperiksa batas baris/selnya; lembar aktif lain tidak dapat melewati pembatasan ini.
- Proteksi request mengikuti Laravel 13; session menggunakan JSON dan cache menolak deserialisasi objek arbitrer.
- Ekspor Word memiliki styles, lebar tabel yang sesuai A4, grid kolom, dan header berulang. PDF memberi ruang lebih besar untuk nama unit pada tabel lebar dan menghindari penggandaan jeda baris.

Migrasi permission 2023 hanya disesuaikan cara membaca nama kolom dari konfigurasi, karena properti statis Spatie lama telah dihapus. Struktur tabelnya tetap sama. Migrasi bisnis lain tidak ditulis ulang, dan tidak ada backfill angka atau perubahan hash/snapshot historis.

## Matriks penerimaan

| Area | Bukti otomatis |
|---|---|
| Instalasi kosong dan panel | FilamentUpgradeTest membuka seluruh 16 resource, dashboard, profil dan Workspace; menguji installer idempotent serta larangan promosi akun existing |
| Anggaran legacy | LegacyUpgradeActionsTest menguji create/edit/duplikasi/delete enam modul, edit inline, akses lintas unit, kunci anggaran, mode edit dan pagination 500 baris |
| Perhitungan dan hierarki | Suite LegacyFinanceRegression, LegacyAllResources, LegacyReadSafety, LegacyAuditCommand serta LegacyPreview menjaga agregasi, pemindahan/hapus cabang, loop, unit dan read-only |
| Impor dan ekspor | LegacyImportExportTest menguji mapping, duplikat, baris gagal, berkas rusak, otorisasi, kode akun nol di depan, formula injection dan angka XLSX |
| Master data dan API | MasterDataUpgradeTest menguji relasi role/permission, pengguna tanpa unit, pegawai/KPI dan autentikasi Sanctum |
| Organisasi dan keamanan | Suite PanelAccess, Security, HttpIsolation dan CustomRoles menguji hak akses dan pencabutan membership |
| Alur keuangan | Suite FinancialApproval, SharedUnitConsent, SharingImpact, Materiality dan AccountingClose menguji maker-checker, sharing, mapping, rekonsiliasi, closing/reopen dan perubahan sumber |
| Narasi dan buku | Suite Contribution, Book, BookExport, PublicationGate dan AutoAssignment menguji kontribusi, review, evidence, publikasi serta satu snapshot lintas format |
| Otomatisasi | Suite Automation dan ReminderCommand menguji draf, inbox serta pengingat opt-in |
| Migrasi | FrameworkUpgradeMigrationTest menguji schema legacy → upgrade → rollback seluruh paket platform → reapply dengan sentinel keuangan tetap identik |
| Browser | Playwright menjalankan login/logout, seluruh resource, profil/notifikasi, organisasi/siklus, binding langsung, unggahan permission, ekspor/preview/cetak dan akses negatif pada desktop/mobile |

CI mencakup PHP 8.4, SQLite, MySQL 8.0/8.4, audit dependensi, lint PHP, build, cache route/config/view, scheduler, rehearsal migrasi, serta browser. Matriks CI adalah konfigurasi pemeriksaan; hasil CI hosted tidak diasumsikan hanya dari verifikasi lokal.

## Verifikasi dokumen

Fixture panjang memuat 48 unit, narasi beberapa halaman, tabel konsolidasi/KPI/sharing/rekonsiliasi dan grafik. Ketiga format diekspor dari snapshot yang sama dan tes memastikan snapshot tidak berubah. DOCX berhasil dibuka dan diekspor ke PDF melalui Microsoft Word; XLSX berhasil dibuka read-only di Microsoft Excel dengan enam lembar dan satu grafik pada KPI Budget. PDF dan pratinjau Word dirender untuk pemeriksaan visual.

Berkas dan gambar QA tersimpan lokal di .tools/export-qa dan bukan data organisasi nyata. Keterbatasan fitur yang sudah dicatat pada dokumentasi platform tetap berlaku, termasuk fitur roadmap dan presisi mata uang dua desimal.

## Hasil verifikasi akhir

- Hasil di bawah merupakan checkpoint upgrade sebelum penambahan plugin. Hasil integrasi terbaru tercatat dalam [panduan plugin](FILAMENT_PLUGINS.md#bukti-pengujian).
- SQLite: 105 tes, 772 assertions lulus, tanpa tes dilewati.
- MySQL 8.4.13: 105 tes, 772 assertions lulus, tanpa tes dilewati. Perbandingan snapshot menggunakan baseline JSON yang sudah tersimpan karena MySQL menormalkan urutan kunci objek; nilai dan hash tetap diuji.
- Validasi Composer strict dan simulasi pemasangan ulang dari lockfile berhasil. Autoloader optimasi memuat 10.307 kelas.
- Audit Composer dan npm tidak menemukan advisori keamanan pada lockfile akhir.
- Sintaks 232 berkas PHP dan build produksi Vite berhasil.
- Browser Chromium: 10 skenario utama lulus (lima alur × desktop/mobile), ditambah dua tes logout/pencabutan sesi lulus. Seluruh 12 skenario yang terdaftar telah diuji tanpa pengecualian. Keduanya dijalankan sebagai suite utama dan filter logout terpisah; `npm run test:browser` menjalankan semuanya pada CI.
- Kompilasi Blade, cache route/config, pemeriksaan route bernama serta endpoint unik, pendaftaran perintah RKA, dan scheduler berhasil. Cache pemeriksaan ditempatkan terpisah dan dibersihkan sesudahnya.
- Rehearsal migrasi/sentinel termasuk dalam kedua suite database, dengan jumlah migrasi platform dihitung dari berkas yang tersedia.
- PDF panjang 14 halaman dan hasil render Microsoft Word 10 halaman diperiksa secara visual, termasuk tabel lintas halaman dan grafik. Workbook berhasil dibuka pada Microsoft Excel, enam lembar dengan satu grafik.
- Instalasi lokal Herd memakai PHP 8.4; migrasi database aplikasi kosong berhasil dan `/admin/login` merespons HTTP 200. Tidak ada akun atau organisasi contoh pada database aplikasi ini.

Sesuai keputusan pengguna, verifikasi dilakukan di lokal dan target production adalah aaPanel; tidak ada staging terpisah. Template `.env.production.example` dan panduan `docs/AAPANEL_DEPLOYMENT.md` disediakan untuk rilis. Konfigurasi server aaPanel belum diperiksa secara langsung. Rilis produksi dan pengaktifan publikasi resmi tidak dijalankan oleh perubahan ini.

Log lokal tersedia pada `.tools/final-sqlite-release.txt`, `.tools/final-mysql-release.txt`, `.tools/browser-release.txt`, `.tools/browser-logout-release.txt`, `.tools/release-check.txt`, `.tools/build-final.txt`, `.tools/composer-audit-final.json`, dan `.tools/npm-audit.json`. Artefak ini tidak masuk Git; CI menyimpan hasil browser sebagai artifact. Server database/browser sementara sudah dihentikan.
