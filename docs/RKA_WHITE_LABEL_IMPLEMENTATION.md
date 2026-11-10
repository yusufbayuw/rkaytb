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
| Sharing Budget | Cost pool equal/weighted, perhitungan ulang terkunci, persetujuan independen, serta tanda tangan digital-logis seluruh unit penerima sesuai versi/hash pada siklus baru; sengketa pihak tak terdampak ditolak | Siklus existing mempertahankan approval historis, driver selain equal/weighted dan revisi alokasi approved masih perlu workflow |
| Jurnal XLSX | Mapping header, staging, idempotensi hash file dan source line ID, pemeriksaan format dan peringatan balance | Tidak mem-post jurnal asli; konektor API belum ada |
| COA Mapping | Aturan per sumber, akun, tanggal efektif, unit default, klasifikasi P&L/internal/clearing dan kategori pendapatan, maker-checker | Multi-entity dan jurnal koreksi/reversal kompleks belum seluruhnya dicakup |
| Rekonsiliasi | Sumber verified, saldo jurnal, klasifikasi akun, clearing, internal transfer dipasangkan berdasar source/reference; blocker, snapshot dan SHA | Kesalahan struktural tidak dapat diberi pengecualian |
| KPI Budget | Per unit dan konsolidasi, budget vs actual, surplus dan deviasi, dengan/tanpa kategori pendapatan khusus sesuai profil organisasi, kebijakan sharing yang sudah/ belum termasuk biaya | Forecast, multi-year comparison dan skenario multi-entity belum lengkap |
| Materialitas | Batas **nominal dan persentase secara bersamaan**; policy dan pengecualian maker-checker; total pengecualian tidak bisa melampaui limit; jumlah/nominal yang dikecualikan ditampilkan dalam rekonsiliasi dan Buku RKA | Hanya kategori budget approved yang belum termapping, bukan exception jurnal |
| Approval | 2–5 tingkat, berdasarkan role organisasi dan risiko, snapshot policy, pemohon dan approver di setiap tingkat berbeda | SLA eskalasi, delegasi sementara, persetujuan unit terdampak yang lebih kompleks belum lengkap |
| Closing | Rekonsiliasi verified versi terbaru dengan nol blocker, multi-step approval, snapshot immutable, hash sumber, reopen berjenjang | Pembukuan koreksi tetap dilakukan pada sistem sumber |
| Narasi | Bank pertanyaan, penugasan reviewer per pertanyaan, undangan, kontribusi versi, sintesis, konflik, evidence privat/verified | Kontrol kualitas semantik dan virus scan belum lengkap |
| Buku RKA | Template judul sendiri, blok NARASI/KONSOLIDASI/KPI/SHARING/REKONSILIASI, editor/reviewer, snapshot angka hanya dari closing sah | Drag-and-drop, daftar isi otomatis, styling profesional kompleks belum lengkap |
| Ekspor | DOCX/PDF/XLSX dari snapshot identik, tabel konsolidasi, KPI per unit, Sharing Budget, rekonsiliasi dan grafik | QA visual dokumen panjang masih diperlukan |
| RKA existing | **Preview baca-saja oleh superadmin**; tabel RKA Investasi tidak lagi menulis data saat memformat sel; akses panel hanya untuk pemilik peran, unit, atau keanggotaan RKA aktif | Pemetaan legacy-ID/periode/parent-child dan pemeriksaan write-time calculated totals pada fitur lama masih perlu UAT |

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

Buat organisasi dan siklus sendiri, lalu tetapkan unit, membership dan role/jabatan khusus. **Siklus baru yang dibuat melalui Workspace RKA memakai kebijakan persetujuan Sharing Budget per unit terdampak secara default**, sedangkan siklus existing tidak dipaksa berubah. Penanggung jawab unit (`unit_manager`) harus ditugaskan pada unitnya dan menyetujui alokasi sebelum disahkan keuangan. Persetujuan menjadi usang bila hasil alokasi dihitung ulang. Role kustom dipakai sebagai approver tanpa mewarisi izin admin/finance. Masukkan anggaran dan cost pool, serta periksa hasil alokasinya. Impor jurnal sumber XLSX ke staging, minta reviewer berbeda memverifikasi batch, lalu atur mapping COA sesuai sumber akuntansi.

Gunakan menu **Rekonsiliasi, Eliminasi Internal dan KPI Budget** untuk menyimpan run baru dan membaca blocker. Atur kebijakan materialitas dan pengecualian hanya untuk kategori anggaran belum terpetakan. Seluruh sengketa unit harus diselesaikan dan diaudit. Rekonsiliasi bersih disahkan oleh reviewer berbeda; closing diajukan dengan alasan, disetujui dalam 2–5 tahapan role, dan menyimpan snapshot terpisah.

Untuk Buku RKA, pengguna membuat daftar judul bagian bebas. Awalan **NARASI|, KONSOLIDASI|, KPI|, SHARING|, REKONSILIASI|** menentukan blok otomatis, misalnya **KPI|Ringkasan Anggaran dan Realisasi**; judul lainnya menjadi narasi. Seluruh publikasi resmi tetap dibatasi oleh konfigurasi **RKA_ENABLE_OFFICIAL_PUBLICATION=false** secara default. Jangan mengaktifkan flag di produksi tanpa validasi sumber, QA dan persetujuan release.

## Quality gates dan deployment

1. Cadangkan database, clone ke staging isolasi, cek semua migration tambahan dan izin user. Jangan jalankan migrate:fresh di production.
2. Jalankan instalasi menggunakan composer.lock, lakukan migrasi pada staging, dan verifikasi seluruh resource existing.
3. GitHub Actions menjalankan PHP lint, composer validate, PHPUnit filter RkaPlatform, Blade compilation, route:list, serta matriks SQLite dan MySQL 8. Lulus CI **bukan** jaminan produksi.
4. UAT akses negatif lintas organisasi, unit, pertanyaan, bukti, siklus, dan custom role; self-approval harus ditolak.
5. UAT tiap variasi jurnal dan eliminasi: source berbeda, transaksi reversal, clearing, internal charges, journal unbalanced, mapping account konflik, duplikasi berkas dan perubahan source setelah rekonsiliasi.
6. UAT materialitas dekat threshold: batas nominal, basis poin, agregasi bruto, sengketa unit, serta pelarangan exception untuk blocker struktural.
7. UAT closing, pembukaan kembali dan approval dinamis, termasuk identitas SHA snapshot sebelum/sesudah perubahan serta validasi angka lintas unit.
8. Lakukan **browser UAT** dan **visual QA DOCX/PDF/XLSX** pada banyak unit, bab panjang, tabel banyak halaman dan data organisasi riil. Bandingkan angka hingga sen, grafik, metadata, pagination dan kelengkapan bukti.
9. Tetapkan mekanisme expand → reconcile → switch dan rollback dengan menjaga histori data lama. Penggantian sistem akuntansi tidak termasuk scope.

## Kekurangan yang tetap harus ditangani sebelum produksi

- Konversi RKA existing dengan mapping unit/akun/tahun/parent-child dan koreksi nilai string yang diverifikasi, tanpa merusak histori.
- Multi badan hukum, eliminasi antar-legal entity, koreksi jurnal lintas periode, transaksi pajak dan rekonsiliasi source ERP/API yang terautentikasi.
- Pendorong biaya bersama tambahan, forecast, KPI multi-year, dan revisi anggaran/Sharing Budget approved dengan persetujuan ulang seluruh unit terdampak; penanganan sengketa lanjut yang mengharuskan versi baru.
- SLA eskalasi/delegasi, role hierarchy dinamis, kontrol risiko yang lebih luas, evidence antivirus, penetrasi keamanan.
- Buku RKA dengan cover profesional, logo, layout style, tabel dan grafik builder, daftar isi otomatis dan pengujian di Word/Excel pembaca asli.
- Callback RKA Investasi yang sebelumnya memanggil `save()` ketika rendering telah diubah menjadi baca-saja; panel Filament tidak lagi menerima user tanpa peran/unit/keanggotaan aktif. Tetap diperlukan pengujian browser/hak akses historis dan audit pada Resource lama lainnya, termasuk konsistensi penyimpanan nilai total saat edit sebenarnya.

**Batas finansial:** mesin angka saat ini memakai dua desimal untuk semua mata uang. Organisasi yang memakai presisi selain dua desimal (contohnya JPY atau KWD) memerlukan perluasan skema dan kalkulator sebelum digunakan untuk laporan resmi.

**Release policy: PR tetap Draft, tanpa merge ke main atau deploy.** Publikasi resmi dimatikan secara default sampai business UAT dan quality gate disetujui.
