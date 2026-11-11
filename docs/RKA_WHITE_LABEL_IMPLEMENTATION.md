# RKA White-Label Platform — Implementasi, Pengujian, dan Kebijakan Rilis

## Prinsip mutlak

- Dua dokumen RKA yang diunggah pengguna adalah **template referensi format**, bukan acuan organisasi, unit, jabatan, COA, ketentuan anggaran, maupun data master. Tidak ada seeder nama sekolah, unit, program, akun, persentase alokasi, judul bab atau pertanyaan dari contoh.
- Fitur baru berada dalam namespace tabel **rka_*** dan dirancang secara additive. Tabel, histori serta resource Filament RKA existing tetap dipertahankan. Belum ada konversi otomatis data legacy menjadi angka resmi.
- **Sistem pembukuan asal tetap menjadi otoritas realisasi.** Impor jurnal ke rkaytb adalah salinan untuk rekonsiliasi, bukan pembuatan atau pembaruan transaksi akuntansi.
- Akses dibatasi oleh keanggotaan organisasi aktif, siklus, unit, tugas, dan peran. Role global Spatie tidak otomatis memberi akses ke organisasi lain.

## Inventaris fitur yang telah diimplementasikan

| Modul | Kemampuan | Batasan penting |
|---|---|---|
| Organisasi | Identitas, warna, mata uang, locale, zona waktu, unit, keanggotaan, role standar dan role approval khusus organisasi | Logo dan identitas dokumen lanjutan belum lengkap |
| Budget | Draft budget → pemeriksa independen; penambahan/persetujuan ditolak setelah periode closed | Revisi dan pembandingan versi budget belum berupa modul lengkap |
| Sharing Budget | Cost pool equal/weighted dan penilaian dampak per versi/hash: informasi rutin dari baseline terdahulu yang closed, review parameter dengan respons unit, baseline/perpindahan tanggung jawab dengan persetujuan formal; eskalasi review lewat dua pejabat setelah tenggat, tanpa auto-approve | Siklus existing tetap memakai approval sebelumnya; revisi alokasi approved, notifikasi otomatis, dan driver tambahan belum lengkap |
| Jurnal XLSX | Mapping header, staging, idempotensi hash file dan source line ID, pemeriksaan format dan peringatan balance | Tidak mem-post jurnal asli; konektor API belum ada |
| COA Mapping | Aturan per sumber, akun, tanggal efektif, unit default, klasifikasi P&L/internal/clearing dan kategori pendapatan, maker-checker | Multi-entity dan jurnal koreksi/reversal kompleks belum seluruhnya dicakup |
| Rekonsiliasi | Sumber verified, saldo jurnal, klasifikasi akun, clearing, internal transfer dipasangkan berdasar source/reference; blocker, snapshot dan SHA | Kesalahan struktural tidak dapat diberi pengecualian |
| KPI Budget | Per unit dan konsolidasi, budget vs actual, surplus dan deviasi, dengan/tanpa kategori pendapatan khusus sesuai profil organisasi, kebijakan sharing yang sudah/ belum termasuk biaya | Forecast, multi-year comparison dan skenario multi-entity belum lengkap |
| Materialitas | Batas **nominal dan persentase secara bersamaan**; policy dan pengecualian maker-checker; total pengecualian tidak bisa melampaui limit; jumlah/nominal yang dikecualikan ditampilkan dalam rekonsiliasi dan Buku RKA | Hanya kategori budget approved yang belum termapping, bukan exception jurnal |
| Approval | 2–5 tingkat berdasarkan role organisasi/risiko; kebijakan impact sharing approval/review/inform diaudit, independen dan terikat hash; eskalasi manual untuk tier review dengan tenggat dan dua pengambil keputusan | Pengingat otomatis, delegasi sementara, eskalasi persetujuan formal dan hierarki jabatan lanjut belum lengkap |
| Closing | Rekonsiliasi verified versi terbaru dengan nol blocker, multi-step approval, snapshot immutable, hash sumber, reopen berjenjang | Pembukuan koreksi tetap dilakukan pada sistem sumber |
| Narasi | Bank pertanyaan, penugasan reviewer per pertanyaan, undangan, kontribusi versi, sintesis, konflik, evidence privat/verified | Kontrol kualitas semantik dan virus scan belum lengkap |
| Buku RKA | Template judul sendiri, blok NARASI/KONSOLIDASI/KPI/SHARING/REKONSILIASI, editor/reviewer, snapshot angka hanya dari closing sah | Drag-and-drop, daftar isi otomatis, styling profesional kompleks belum lengkap |
| Ekspor | DOCX/PDF/XLSX dari snapshot identik, tabel konsolidasi, KPI per unit, Sharing Budget, rekonsiliasi dan grafik | QA visual dokumen panjang masih diperlukan |
| RKA existing | **Preview legacy baca-saja oleh superadmin**; investasi/maintenance/consumable/operasional/pendapatan memakai transaksi dan perhitungan ulang parent pada save/delete; **56 penyimpanan dari formatter tabel dihilangkan**; enam resource dibatasi aksesnya termasuk view/edit/delete per record | Gaji tetap memakai isian total manual. Data historis tidak ditulis ulang otomatis; setiap modul perlu UAT browser dengan data nyata |

## Aturan perhitungan dan kontrol data

1. Rencana menggunakan hanya baris anggaran dan alokasi approved pada organisasi/siklus.
2. Realisasi menggunakan baris jurnal berstatus posted dari batch impor verified; approval batch terpisah dari posting pembukuan.
3. Setiap baris yang diperlukan harus mempunyai tepat satu mapping akun aktif pada sumber dan tanggal terkait. Mapping ambigu atau kosong memblokir rekonsiliasi.
4. Debit dan kredit harus seimbang dalam satu sumber, nomor jurnal, dan tanggal. Akun clearing terbuka dan reversal tanpa referensi valid adalah blocker.
5. Dalam tampilan **unit manajerial**, biaya dan pendapatan internal tetap muncul sebagai charge yang dibukukan. Pada **konsolidasi organisasi**, pasangan internal income/internal expense dengan sumber dan reference sama serta jumlah identik dieliminasi satu kali. Reclassification biasa tidak dieliminasi kembali.
6. Kategori pendapatan/biaya, inclusion of Sharing Budget, dan jenis pendapatan khusus diatur **per profil organisasi**, tanpa kode sekolah atau Dana Pembangunan yang di-hardcode.
7. Materialitas tidak menghapus/mengabaikan jurnal, clearing, duplicate, atau eliminasi yang bermasalah. Pengecualian administratif yang diizinkan hanya unmapped budget category yang disahkan oleh pihak berbeda, ditelusuri per budget line, dan diperiksa limit absolut sekaligus basis poin kumulatif.
8. Selisih antara realisasi dan anggaran adalah deviasi manajerial, bukan otomatis error rekonsiliasi. Kecacatan sumber berbeda dari varians anggaran.
9. Closing mensyaratkan snapshot rekonsiliasi **terbaru**, disahkan independen, masih cocok dengan source hash, dan tanpa blocker termasuk status persetujuan tiap unit Sharing Budget sesuai kebijakan siklus. Persetujuan closing berjenjang disimpan sebagai jejak audit. Reopen tidak menimpa snapshot lama.
10. Penerbitan Buku RKA resmi mensyaratkan closed cycle dan hash laporan/sumber yang masih cocok; semua teks/review harus lengkap. Ekspor menggunakan snapshot yang sudah diterbitkan, tidak menghitung ulang data hidup.

## Operasional melalui Filament

Buat organisasi dan siklus sendiri, lalu tetapkan unit, membership dan role/jabatan khusus. **Siklus baru di Workspace RKA menggunakan kebijakan impact-based**, sedangkan siklus existing tetap memakai pengaturan historisnya. Keuangan membuat penilaian dampak per pool: **baseline awal dan perpindahan tanggung jawab harus approval formal**; perubahan parameter membutuhkan review atau approval; hanya biaya rutin dengan metode, bobot, penerima yang sama pada baseline sebelumnya yang approved/closed dapat memakai tier informasi. Pengusul dan pengesah klasifikasi wajib berbeda, termasuk pembuat alokasi. Penanggung jawab unit (`unit_manager`) merespons biaya unitnya pada versi/hash yang tepat; perubahan versi memaksa penilaian dan persetujuan ulang. Untuk tier review, tenggat dan eskalasi dua pejabat merupakan tindakan eksplisit; diamnya unit tidak pernah dianggap menyetujui. Sengketa terbuka selalu memblokir. Role kustom dipakai sebagai approver tanpa mewarisi izin admin/finance. Masukkan anggaran dan cost pool, serta periksa hasil alokasinya. Impor jurnal sumber XLSX ke staging, minta reviewer berbeda memverifikasi batch, lalu atur mapping COA sesuai sumber akuntansi.

Gunakan menu **Rekonsiliasi, Eliminasi Internal dan KPI Budget** untuk menyimpan run baru dan membaca blocker. Atur kebijakan materialitas dan pengecualian hanya untuk kategori anggaran belum terpetakan. Seluruh sengketa unit harus diselesaikan dan diaudit. Rekonsiliasi bersih disahkan oleh reviewer berbeda; closing diajukan dengan alasan, disetujui dalam 2–5 tahapan role, dan menyimpan snapshot terpisah.

Untuk Buku RKA, pengguna membuat daftar judul bagian bebas. Awalan **NARASI|, KONSOLIDASI|, KPI|, SHARING|, REKONSILIASI|** menentukan blok otomatis, misalnya **KPI|Ringkasan Anggaran dan Realisasi**; judul lainnya menjadi narasi. Seluruh publikasi resmi tetap dibatasi oleh konfigurasi **RKA_ENABLE_OFFICIAL_PUBLICATION=false** secara default. Jangan mengaktifkan flag di produksi tanpa validasi sumber, QA dan persetujuan release.

## Perlindungan tambahan pada RKA legacy

- **Tulis-saat-edit, bukan tulis-saat-baca.** Pada modul Investasi, Maintenance, Consumable, Operasional, dan Pendapatan, formulir/table inline menyimpan angka input; `HasLegacyRkaTotals` menyinkronkan total rincian, total induk hingga akar, dan bulan pada satu transaksi.
- Parent harus masih merupakan induk, berada dalam unit legacy yang sama, dan tidak membuat hubungan melingkar. Parent tidak boleh dipindah ke unit lain saat masih memiliki anak, dihapus sebelum anak dipindah, atau dikonversi ke leaf secara sepihak. Penyusunan total manual yang bertentangan dengan volume × harga diganti dengan nilai formula saat penyimpanan.
- Permission `canViewAny/canCreate/canView/canEdit/canDelete` ditambahkan pada enam resource RKA lama untuk mencegah akses record lintas unit. Daftar **RKA Gaji** juga kini dibatasi berdasarkan unit untuk pengguna biasa; peran `admin-keuangan` dan `superadmin` tetap dapat melihat seluruh data Gaji sesuai hak akses lama.
- **Tanpa migrasi massal**: perubahan tidak menjalankan backfill total historis, penggantian kode unit, atau pemetaan angka buku ke modul baru. Data yang dahulu salah tetap membutuhkan penilaian/rekonsiliasi terkontrol.
- Operasi SQL langsung, query-builder mass update/delete, dan integrasi pihak ketiga yang tidak memakai `Model::save/delete` dapat melewati hook agregasi. Audit integrasi produksi diperlukan sebelum GO LIVE.

## Hardening terakhir dan pengujian otomatis (11 November 2026)

### Audit data historis tanpa mengubah data

Perintah `php artisan rka:audit-legacy --json` membaca lima tabel legacy berhierarki dan melaporkan:
- total leaf tidak sama dengan volume × harga;
- total induk dan kolom Januari–Desember tidak sesuai penjumlahan anak;
- induk hilang, induk bukan parent, loop parent, hubungan lintas unit;
- status `incomplete_scan` saat jumlah baris melebihi `--limit` (default 200000).

Perintah ini **hanya SELECT** dan mengeluarkan jumlah temuan serta maksimal 30 sampel **ID record**, tanpa nama atau isi data pribadi. Gunakan `--strict` untuk exit code tidak nol bila terdapat anomali. Pada data historis yang berpotensi besar, jalankan pada salinan database terisolasi. Tidak ada pemutakhiran massal atau koreksi otomatis. Anomali pada data NULL/format angka lama dapat tetap memerlukan pemeriksaan bisnis.

### Perlindungan akses HTTP dan ekspor

Tes HTTP benar-benar memanggil route unduhan: bukti terikat izin per pertanyaan, organisasi lain dan keanggotaan nonaktif ditolak. Buku RKA resmi hanya dapat diunduh oleh **owner/admin/editor/publisher** organisasi; role reviewer pertanyaan saja tidak dapat mengunduh keseluruhan buku. Pengunjung tanpa login diarahkan ke login Filament (bukan gagal karena route Laravel `login` tidak tersedia). Perubahan status membership harus langsung menghilangkan hak unduh.

### CI migrasi dan batas rollback

GitHub Actions pada **setiap PR update** menjalankan lint PHP penuh, Composer, full suite SQLite/MySQL 8, Blade compile, route list dan pemeriksaan pendaftaran perintah audit.

Rehearsal CI secara eksklusif menggunakan database temporer `ci_upgrade_test.sqlite` atau server MySQL service lokal `rka_test` dan mensyaratkan `CI=true`, `GITHUB_ACTIONS=true`, `APP_ENV=testing`. Prosedur: bangun schema legacy 2014/2019/2023 → sisipkan sentinel finansial legacy → jalankan **9 migrasi additive** → verifikasi nilainya tidak berubah → rollback **seluruh 9 migrasi** → verifikasi tabel baru hilang dan legacy tetap sama → reapply 9 migrasi.

**Penting:** SQLite hanya mendukung rollback **seluruh paket migrasi platform** dalam rehearsal ini, bukan rollback parsial di tengah rentang migrasi yang punya kolom foreign key. SQLite tidak mendukung `DROP FOREIGN KEY` sendiri, sehingga down beberapa migrasi sengaja membiarkan kolom yang akan hilang ketika migrasi awal menghapus tabel platform. MySQL mendukung penurunan foreign key secara eksplisit. Operasi rollback yang sama tidak boleh dijalankan di produksi; penanganan rollback produksi berupa rencana terverifikasi dengan backup dan migrasi maju yang tidak merusak histori.

### Cakupan tes baru

- Reconciliation → verified approval → close berjenjang → Buku RKA official → GET ekspor PDF/DOCX/XLSX, termasuk pemeriksaan hash satu snapshot.
- Perubahan sumber jurnal di tengah approval harus memblokir closing.
- HTTP cross-tenant dan unauthorized evidence/book export; pencabutan membership menolak export baru.
- Audit historis baca-saja tidak boleh menjalankan write SQL, mendeteksi stale total/cross-unit, dan menandai partial scan.
- Upgrade, rollback, reapply secara otomatis pada SQLite dan MySQL sementara.

**Batas yang belum bisa dibuktikan dengan tool GitHub saja:** browser UAT pada database organisasi nyata, kebenaran nominal sumber akuntansi yang diotorisasi, penilaian dokumen panjang oleh aplikasi Word/Excel, dan penetrasi keamanan operasional. Kelulusan full CI dapat mengizinkan **merge kode terkontrol**, tetapi tidak berarti deployment produksi atau aktivasi flag penerbitan.

## Quality gates dan deployment

1. Cadangkan database, clone ke staging isolasi, cek semua migration tambahan dan izin user. Jangan jalankan migrate:fresh di production.
2. Jalankan instalasi menggunakan composer.lock, lakukan migrasi pada staging, dan verifikasi seluruh resource existing.
3. GitHub Actions menjalankan PHP lint, composer validate, **seluruh test suite Laravel** (termasuk regresi legacy), Blade compilation, route:list, serta matriks SQLite dan MySQL 8. Lulus CI **bukan** jaminan produksi.
4. UAT akses negatif lintas organisasi, unit, pertanyaan, bukti, siklus, dan custom role; self-approval harus ditolak.
5. UAT tiap variasi jurnal dan eliminasi: source berbeda, transaksi reversal, clearing, internal charges, journal unbalanced, mapping account konflik, duplikasi berkas dan perubahan source setelah rekonsiliasi.
6. UAT materialitas dekat threshold: batas nominal, basis poin, agregasi bruto, sengketa unit, serta pelarangan exception untuk blocker struktural.
7. UAT closing, pembukaan kembali dan approval dinamis, termasuk identitas SHA snapshot sebelum/sesudah perubahan serta validasi angka lintas unit.
8. Lakukan **browser UAT** dan **visual QA DOCX/PDF/XLSX** pada banyak unit, bab panjang, tabel banyak halaman dan data organisasi riil. Bandingkan angka hingga sen, grafik, metadata, pagination dan kelengkapan bukti.
9. Tetapkan mekanisme expand → reconcile → switch dan rollback dengan menjaga histori data lama. Penggantian sistem akuntansi tidak termasuk scope.

## Kekurangan yang tetap harus ditangani sebelum produksi

- Konversi RKA existing dengan mapping unit/akun/tahun/parent-child dan koreksi nilai string yang diverifikasi, tanpa merusak histori.
- Multi badan hukum, eliminasi antar-legal entity, koreksi jurnal lintas periode, transaksi pajak dan rekonsiliasi source ERP/API yang terautentikasi.
- Pendorong biaya bersama tambahan, forecast, KPI multi-year, dan revisi anggaran/Sharing Budget approved yang memerlukan baseline dan versi formal baru. Eskalasi tier approval yang memindahkan tanggung jawab tetap diblokir tanpa mandat legal yang ditetapkan organisasi.
- SLA/pengingat otomatis (meskipun tenggat manual dan eskalasi dua pejabat sudah ada), delegasi, role hierarchy dinamis, evidence antivirus, penetrasi keamanan.
- Buku RKA dengan cover profesional, logo, layout style, tabel dan grafik builder, daftar isi otomatis dan pengujian di Word/Excel pembaca asli.
- Lima model RKA legacy yang memiliki volume/harga dan hirarki kini menghitung total dan dua belas bulan saat save/delete dalam transaksi. Semua formatter tabelnya hanya membaca, dan setiap akses record Filament membatasi unit sesuai kebijakan lama. Model Gaji memiliki total manual dan tidak diubah formulanya. Tetap perlu **audit nonmutatif** atas nilai historis yang sudah stale, uji browser, serta uji bulk import/query-builder yang secara alami dapat melewati Eloquent model hooks; operasi koreksi histori harus disahkan dan dilakukan terpisah.

**Batas finansial:** mesin angka saat ini memakai dua desimal untuk semua mata uang. Organisasi yang memakai presisi selain dua desimal (contohnya JPY atau KWD) memerlukan perluasan skema dan kalkulator sebelum digunakan untuk laporan resmi.

**Release policy: PR tetap Draft, tanpa merge ke main atau deploy.** Publikasi resmi dimatikan secara default sampai business UAT dan quality gate disetujui.


## Otomatisasi workflow (branch pengembangan)

- Tugas Saya berbasis kewenangan organisasi, dengan penanda tenggat lewat dan tautan cepat ke pekerjaan. Tidak menambahkan izin baru.
- Siapkan Penugasan memproses pertanyaan berdasarkan role, unit dan rules. Pemilik ambigu/reviewer tak tersedia dilewati; penugasan existing tidak ditimpa.
- Draf sintesis dibuat dari jawaban revisi terbaru, tetap perlu disunting dan diverifikasi independen.
- Perubahan keuangan menyiapkan rekonsiliasi draft hanya bila source hash berubah; tidak ada approval otomatis.
- Profil KPI valid dari periode terdahulu diwariskan sebagai konfigurasi editable; tidak ada auto-persist approval/jurnal yang telah disahkan.
- Input KPI/pertanyaan menggunakan formulir biasa dengan opsi JSON lanjutan.
- Email pengingat bersifat opt-in: RKA_TASK_REMINDER_MAIL_ENABLED=false secara default; RKA_TASK_REMINDER_DAYS_AHEAD=3. Pastikan konfigurasi MAIL_* serta cron Laravel schedule:run sudah aktif. Jalankan php artisan rka:remind-tasks --dry-run untuk simulasi. Jadwal 07.30 zona waktu konfigurasi RKA_TIMEZONE. Gunakan cache persisten/shared pada multi-server untuk deduplikasi sekali per user/siklus/hari. Isi email hanya judul tugas dan tenggat.
- QA browser, UAT real-data dan release approval tetap wajib sebelum production.
