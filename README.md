# SiDiPa — Siswa Digital Pasport

Sistem penilaian karakter siswa berbasis poin, kehadiran barcode (QR), dan sertifikat digital untuk satu SMK. Dibuat untuk **shared hosting cPanel**: PHP 8.1+ tanpa framework, MySQL/MariaDB lewat PDO, HTML/CSS/JavaScript vanilla tanpa build step, tanpa Composer. Seluruh antarmuka berbahasa Indonesia dan bergaya **neo brutalism** buatan sendiri.

> Dokumen ini mengikuti instruksi `Prompt_Generate_Website_SiDiPa.pdf`. Pembangunan dilakukan bertahap sesuai **bagian 15** PDF. Status saat ini: **Tahap 1, 2, 3, 4, dan 5 selesai**, ditambah **PWA penuh**, pustaka QR lokal untuk cetak/scan tanpa CDN, serta pembaruan: guru menilai seluruh siswa tanpa penugasan kelas, login cukup sekali (tersimpan 30 hari), semua laporan diunduh sebagai **XLSX** (bagian 15), dan **beranda siswa + sertifikat + foto profil** diperbarui (bagian 16), serta **skala predikat 8 tingkat + username siswa untuk login** (bagian 17), **QR kartu berisi username + saran otomatis + bunyi bip pada scan** (bagian 18), **username bebas + saran siswa di seluruh halaman + Rincian angka disederhanakan** (bagian 19), **pengurangan poin tanpa batas + daftar catatan per tanggal untuk mencegah pencatatan ganda** (bagian 20), dan **sertifikat siswa menjadi 2 halaman: surat pernyataan + deskripsi penilaian** (bagian 21), serta **level penanganan siswa bermasalah untuk Guru BK** (bagian 22). Semua kartu beranda guru sudah menuju halaman yang benar-benar berfungsi.

---

## 1. Yang sudah tersedia

| Bagian | Isi |
| --- | --- |
| Struktur | `index.php`, `config.php`, `database.sql`, `install.php`, `verifikasi.php`, `app/`, `pages/`, `api/`, `cron/`, `public/`, `contoh/` |
| Database | 22 tabel InnoDB utf8mb4 + kunci asing + indeks (termasuk `ingat_login`); 6 aspek, 36 katalog poin bawaan, 2 jenis ibadah, 55 pengaturan, 1 semester contoh, akun admin |
| Login & peran | `password_hash`/`password_verify`, regenerasi ID sesi, cookie `HttpOnly` + `SameSite=Lax` + `secure` otomatis, **login sekali, 30 hari sejak aktivitas terakhir** (cookie `SIDIPAINGAT`, hanya hash token di DB, rotasi tiap pakai, batas bergulir otomatis saat aplikasi dibuka, dicabut saat logout/ganti sandi), opsi **Jangan simpan login di perangkat ini**, pembatasan 5 percobaan gagal / 15 menit, wajib ganti kata sandi saat login pertama, pemeriksaan peran di server |
| Data master | CRUD siswa + akun siswa otomatis, CRUD kelas + wali kelas + guru pengampu, CRUD pengguna, CRUD katalog poin, impor CSV dengan pratinjau |
| Pengaturan | Semua jam, bobot poin otomatis, target aspek, ambang predikat/pembinaan, poin penilaian bulanan, identitas sekolah, tanda tangan, dan konfigurasi WA lewat satu halaman admin |
| Skor | `skor_per_aspek()` mulai dari 0 per semester, tanpa batas atas, boleh minus; predikat **per aspek** (A+/A/B+/B/B-/C+/C/D) terhadap target aspek, plus predikat **keseluruhan** di beranda siswa (bagian 16 dan 17) |
| Kerahasiaan pencatat | `riwayat_catatan()` dan `catatan_saya()` memilih kolom secara eksplisit; `pencatat_id` tidak pernah dikirim ke siswa, wali kelas, BK, kepala sekolah, guru lain, atau wali murid. Hanya `lihat_pencatat()` (khusus admin) yang membukanya dan **selalu** menulis `log_akses` |
| Tema | Krem `#FFF3E4` + pola titik 1,5 px / 18 px, border hitam 3 px, bayangan keras, 7 warna datar, Space Grotesk, efek tekan `translate(4px,4px)`, ikon SVG inline, target sentuh 44 px, navigasi bawah mobile + sidebar desktop |
| Cron | `cron/harian.php`, `cron/mingguan.php`, `cron/wa.php` + tombol manual admin. Idempoten lewat `kunci_unik`; WA masuk antrian, jeda acak 3–8 detik, maksimal 20 pesan, 3 percobaan |
| Verifikasi publik | `verifikasi.php?kode=...` tanpa login, hanya status, nama, kelas, semester, dan skor per aspek; admin yang login mendapat form pembatalan beralasan |
| Contoh data | `contoh/siswa_contoh.csv` dan `contoh/data_contoh.sql` (3 kelas, 20 siswa fiktif, 3 guru) |
| PWA | `manifest.webmanifest`, `sw.js`, `offline.html`, `public/js/pwa.js`, 5 ikon PNG, tombol Pasang, badge Offline, `.htaccess` (lihat bagian 12) |
| **Tahap 2 — Beranda guru** | `pages/guru/beranda.php` persis bagian 3: avatar ungu, sapaan, lonceng, **satu** stiker kuning −1,5° `SELASA, 3 NOV 2026`, kartu CATAT POIN koral ±150 px, enam kartu ±118 px grid 2 kolom, tanpa strip kalender/banner kehadiran. Kini **semua guru** mendapat kartu Banding, Izin dan Sakit, Penilaian Bulanan, dan Catatan Saya dengan cakupan **seluruh siswa aktif**, tanpa perlu ditugaskan sebagai pengampu/wali kelas (bagian 15) |
| **Tahap 2 — Catat poin** | `pages/guru/catat_poin.php`: katalog manual, deskripsi wajib untuk sedang/berat, cap pengurangan 10/aspek/hari, unggah bukti JPG/PNG/WEBP ≤2 MB nama acak, notifikasi pelanggaran berat ke BK/kepsek/wali kelas, evaluasi ambang pembinaan |
| **Tahap 2 — Riwayat & privasi** | `pages/guru/riwayat.php` (pembatalan mandiri ≤24 jam dengan alasan, catatan tidak dihapus), `pages/siswa/beranda.php`, `pages/siswa/riwayat.php` (hanya kategori, tanggal tanpa jam, poin, deskripsi; tanpa pencatat) |
| **Tautan kartu lain** | `pages/guru/scan.php` + `api/scan.php` (QR berkelanjutan, fallback NIS, waktu server, jendela ibadah, tolak duplikat), `pages/guru/pembinaan.php`, `pages/guru/izin.php`, `pages/guru/penilaian.php`, `pages/guru/banding.php`, `pages/siswa/banding.php` |
| **Catat banyak siswa (§10)** | `pages/guru/catat_banyak.php`: satu kejadian + satu tanggal + satu deskripsi untuk banyak siswa sekaligus, dalam **satu transaksi**. Tidak ada lagi cap harian (bagian 20); panel daftar catatan per tanggal membantu mencegah pencatatan ganda |
| **Grafik mingguan siswa (§10)** | `grafik_mingguan_siswa()` di `app/laporan.php` + SVG inline pada `pages/siswa/beranda.php`: akumulasi poin 8 minggu terakhir, tanpa pustaka grafik eksternal |
| **Tahap 3 — Kartu QR** | `pages/admin/kartu.php` dan `pages/siswa/kartu.php`: kartu **85,6 × 54 mm** memuat nama, NIS, **username login**, kelas, dan QR berisi **username siswa** (bukan NIS, bukan lagi `qr_token`). Cetak per kelas atau per siswa lewat `window.print()`; kartu lama wajib dicetak ulang (bagian 18) |
| **Tahap 3 — Hari libur** | `pages/admin/hari_libur.php`: tambah satu tanggal atau rentang (maks. 93 hari), ubah keterangan, hapus. `cron/harian.php`, `cron/mingguan.php`, dan `api/scan.php` melewati tanggal ini |
| **Tahap 3 — Rekap kehadiran** | `app/laporan.php` + `pages/umum/rekap_kehadiran.php`: harian, bulanan, dan semester per siswa **dan** per kelas (hadir, hadir awal, terlambat, izin, sakit, alpa, % kehadiran) dengan **unduh XLSX** 3 lembar (Rekap Siswa, Ringkasan Kelas, Keterangan). Hak akses di server: siswa/wali hanya anaknya, guru menjangkau seluruh siswa aktif |
| **Tahap 4 — Semester** | `pages/admin/sertifikat.php`: tambah semester, aktifkan (skor kembali 0), **tutup periode input**, buka kembali. `cek_input_semester()` membekukan catat poin, scan, izin, dan penilaian bulanan saat periode tertutup |
| **Tahap 4 — Sertifikat** | Penerbitan per kelas/semua, kode **`SDP-TAHUNSEM-NIS-ACAK4`**, snapshot `data_json` yang tidak berubah, antrean WA, pembatalan dengan alasan + pelaku + waktu. `pages/umum/cetak_sertifikat.php` kini menghasilkan **2 halaman A4 landscape**: halaman 1 **surat pernyataan** *"Raport Penilaian Karakter Siswa ini diberikan kepada … dengan nilai rata-rata karakter …"* (logo, QR verifikasi, tanda tangan), halaman 2 **deskripsi penilaian** (tabel 6 aspek + keterangan), lihat **bagian 21**. Tombol cetak siswa/wali hanya aktif saat status `terbit` **dan** semester sudah berganti; sebelumnya sertifikat hanya bisa dilihat (bagian 16). `verifikasi.php` kini menyediakan pembatalan khusus admin |
| **Tahap 4 — BK & kepsek** | `pages/bk/beranda.php`, `pages/kepsek/beranda.php` (**hanya baca**), `pages/bk/tindak_lanjut.php` (tindak lanjut + poin positif opsional), `pages/umum/laporan.php` (ringkasan per kelas/per aspek + unduh XLSX 3 lembar) |
| **BK — Siswa bermasalah (§22)** | `pages/bk/penanganan.php` + `pages/bk/penanganan_detail.php`: daftar siswa berdasar riwayat pelanggaran, **level L1–L4** otomatis/manual, status `baru/proses/selesai`, tahap & penanggung jawab, jejak perubahan level, dan halaman detail per siswa |
| **Tahap 5 — Portal wali** | `pages/wali/beranda.php`: hanya baca ringkasan anaknya (skor 6 aspek, kehadiran bulan ini, sertifikat, kartu QR). Admin menghubungkan akun lewat **Data siswa → Akun portal wali murid** |
| **Pustaka QR lokal** | `public/js/qrcode.min.js` (qrcode-generator, MIT), `public/js/html5-qrcode.min.js` (Apache-2.0), `public/js/qr-sidipa.js`. Disertakan sebagai berkas statis, di-precache service worker, sehingga cetak dan scan tetap berfungsi tanpa akses CDN |
| **Ekspor XLSX murni PHP** | `app/xlsx.php` menulis OOXML/ZIP sendiri (tanpa Composer, tanpa `ZipArchive`): header tebal + beku, filter, lebar kolom, banyak lembar, angka sebagai sel numerik. `csv_keluar()` sudah dihapus; tidak ada lagi unduhan CSV pada laporan |

**Status akhir:** kelima tahap PDF terlaksana. Tidak ada placeholder, halaman "coming soon", atau tombol mati. Dua hal yang memang menjadi kewajiban sekolah saat deploy: menyesuaikan array `$payload`/endpoint OpenWA di `app/wa.php` (bagian 11 PDF) dan **menghapus `install.php`** setelah pemeriksaan server hijau.

---

## 2. Persyaratan server

- PHP **8.1 atau lebih baru** dengan `pdo_mysql`, `curl`, `fileinfo`, `mbstring`, `json`, `session`.
- MySQL 5.7+ / MariaDB 10.3+, database + user cPanel.
- **HTTPS aktif** — kamera HP menolak `getUserMedia` pada HTTP. Pustaka `html5-qrcode` dan `qrcode-generator` **disertakan lokal** di `public/js/`, jadi server tidak perlu Composer dan QR tetap berfungsi saat CDN tidak tersedia.
- Zona waktu aplikasi `Asia/Jakarta`; waktu scan selalu memakai jam server (`SET time_zone = '+07:00'`).

---

## 3. Instalasi di cPanel

1. **Buat database**: cPanel → *MySQL Databases* → buat database dan user, berikan *ALL PRIVILEGES*. Catat nama database, user, dan kata sandi.
2. **Impor skema**: phpMyAdmin → pilih database → *Import* → unggah `database.sql`. Berkas ini membuat seluruh tabel (termasuk `ingat_login` untuk login persisten) dan mengisi data awal. **Instalasi lama**: jalankan perintah SQL pada **bagian 15** untuk menambah tabel `ingat_login` tanpa mengubah data yang ada.
3. **(Opsional) Data contoh**: impor `contoh/data_contoh.sql` untuk 3 kelas dan 20 siswa fiktif. Lewati bila langsung memakai data asli.
4. **Unggah berkas**: unggah seluruh isi repositori ke `public_html/sidipa/` (atau sesuaikan), **termasuk berkas tersembunyi** `.htaccess`, `manifest.webmanifest`, `sw.js`, dan `offline.html` di root. Pertahankan struktur folder; jangan letakkan `app/`, `cron/`, atau `storage/` di dalam folder yang bisa dieksekusi bebas.
5. **Isi `config.php`**: sunting `SIDIPA_DB_HOST`, `SIDIPA_DB_NAME`, `SIDIPA_DB_USER`, `SIDIPA_DB_PASS`. Jika aplikasi di subdirektori/domain khusus, isi `SIDIPA_BASE_URL` (mis. `https://sidipa.smk.sch.id`). Ganti `SIDIPA_KUNCI` dengan nilai acak baru bila perlu. Alternatif: pakai environment variable `SIDIPA_DB_*` lewat `.htaccess` cPanel.
6. **Hak akses folder**: `public/uploads` → 755 dan dapat ditulis; `storage/logs` → 750 dan dapat ditulis. `public/uploads/.htaccess` sudah melarang eksekusi PHP; `storage/.htaccess` dan `cron/.htaccess` sudah menolak akses browser.
7. **Aktifkan HTTPS**: cPanel → *AutoSSL* / *SSL/TLS Status*, lalu arahkan semua traffic ke HTTPS. HTTPS wajib untuk service worker, kamera pemindai QR, dan tombol pasang PWA (lihat bagian 12).
8. **Jalankan `install.php`** dari browser. Periksa setiap baris pemeriksaan. Setelah semua hijau, **HAPUS `install.php`** dari File Manager.
9. **Login pertama**: `https://domain/sidipa/pages/login.php` → user `admin`, kata sandi sementara **`SiDiPa@2026`**. Sistem memaksa penggantian kata sandi sebelum masuk ke menu lain. Setelah itu login tersimpan dan batas 30 hari otomatis diperpanjang setiap aplikasi dibuka (bagian 15.2); centang **Jangan simpan login di perangkat ini** bila memakai komputer bersama.
10. **Isi data sekolah** di menu *Pengaturan* (nama sekolah, logo, tanda tangan), lalu tambah kelas, guru, dan siswa (manual atau impor CSV). Untuk portal wali: buat akun berperan **Wali murid** di menu *Pengguna*, lalu pilih akun itu pada kolom **Akun portal wali murid** di form *Data siswa*.
11. **Alur akhir semester**: *Semester & Sertifikat* → **Tutup** periode input → periksa ringkasan → **Terbitkan sertifikat** (per kelas atau semua) → siswa **melihat** sertifikat lewat *Sertifikat Saya* dan baru dapat mencetak setelah semester baru diaktifkan. Kode sertifikat dapat diverifikasi publik tanpa login.

---

## 4. Cron job cPanel

cPanel → *Cron Jobs* → tambahkan tiga job (ganti `USER` dan jalur sesuai akun Anda):

```bash
# Harian: Senin–Jumat pukul 15.00
0 15 * * 1-5 /usr/bin/php -q /home/USER/public_html/sidipa/cron/harian.php >/dev/null 2>&1

# Mingguan: Jumat pukul 15.30
30 15 * * 5 /usr/bin/php -q /home/USER/public_html/sidipa/cron/mingguan.php >/dev/null 2>&1

# Antrean WhatsApp: setiap 5 menit
*/5 * * * * /usr/bin/php -q /home/USER/public_html/sidipa/cron/wa.php >/dev/null 2>&1
```

Untuk memproses tanggal tertentu (misal saat uji coba):

```bash
/usr/bin/php -q /home/USER/public_html/sidipa/cron/harian.php --tanggal=2026-11-03
/usr/bin/php -q /home/USER/public_html/sidipa/cron/mingguan.php --tanggal=2026-11-03
```

Setiap job juga punya tombol manual di **Admin → Proses Manual Cron**. Cron harian dan mingguan aman dijalankan dua kali: catatan poin memakai `kunci_unik`, baris kehadiran memakai `INSERT ... ON DUPLICATE KEY UPDATE`.

---

## 5. Impor siswa dari CSV

Menu **Admin → Impor CSV**, atau unggah `contoh/siswa_contoh.csv`. Format header:

```csv
nis,username,nama,jenis_kelamin,kelas,nama_wali,no_wa_wali
20260100,ahmad.rizky,Ahmad Rizky Prasetyo,L,X TKJ 1,Bapak Hidayat,628123457000
```

- Kolom **wajib**: `nis`, `nama`, `kelas`. Opsional: **`username`**, `jenis_kelamin` (L/P), `nama_wali`, `no_wa_wali`.
- Pemisah `,` atau `;` dan BOM dideteksi otomatis; variasi nama kolom seperti `nama_lengkap`, `rombel`, `hp_wali` diterima.
- Sistem menampilkan **pratinjau** lebih dulu: baris dengan NIS ganda, NIS kosong, NIS/username yang sudah terdaftar, username ganda, atau username tidak valid ditandai dan dilewati. Kolom `username` yang kosong diisi otomatis dari nama (lihat bagian 17).
- Kelas yang belum ada dibuat otomatis (tingkat dari kata pertama, jurusan dari kata kedua).
- Setiap siswa mendapat akun dengan **username siswa** (bukan NIS), kata sandi sementara **`Siswa@2026`**, dan wajib ganti kata sandi saat login pertama. Nomor wali dinormalisasi ke format `62`.
- Impor berjalan dalam satu transaksi: bila ada galat, seluruh perubahan dibatalkan.

Paket data contoh: guru `guru.tkj1` / `guru.tkj2` / `guru.tkj3` dengan kata sandi `Guru@2026`, siswa dengan `Siswa@2026` dan username seperti `ahmad.rizky` (bukan NIS `20260100`).

---

## 6. Struktur folder

```
sidipa/
├── index.php                 # Arahkan ke login / beranda sesuai peran
├── config.php                # Database, base URL, kunci rahasia
├── database.sql              # Skema + data awal
├── install.php               # Cek persyaratan, HAPUS setelah dipakai
├── verifikasi.php            # Verifikasi sertifikat publik tanpa login
├── .htaccess                 # MIME manifest + no-cache sw.js (PWA)
├── manifest.webmanifest      # Nama, warna, ikon, shortcut PWA
├── sw.js                     # Service worker: cache aset + halaman offline
├── offline.html              # Halaman fallback saat tanpa koneksi
├── public/js/pwa.js          # Daftar SW, tombol pasang, status jaringan
├── public/js/qrcode.min.js   # Pembuat QR SVG (qrcode-generator, MIT)
├── public/js/html5-qrcode.min.js  # Pemindai kamera (Apache-2.0)
├── public/js/qr-sidipa.js    # Render [data-qr] + tombol cetak
├── public/css/kartu.css      # @page 85,6 × 54 mm
├── public/css/cetak.css      # @page A4 landscape, 2 halaman (bagian 21)
├── public/img/icon-*.png     # Ikon 192/512/maskable/apple/favicon
├── app/
│   ├── bootstrap.php         # Muat konfigurasi, sesi, penangan galat
│   ├── db.php                # PDO + prepared statements + transaksi
│   ├── auth.php              # Login, login persisten, peran, throttle, ganti sandi
│   ├── csrf.php              # Token CSRF untuk form dan API
│   ├── helpers.php           # Escape, URL, flash, pengaturan, tanggal
│   ├── skor.php              # Skor per aspek, predikat, kerahasiaan pencatat
│   ├── laporan.php           # Rekap kehadiran, ringkasan per kelas, grafik mingguan
│   ├── xlsx.php              # Penulis XLSX/OOXML murni PHP (tanpa Composer)
│   ├── sertifikat.php        # Penutupan periode, snapshot, terbit/batal sertifikat
│   ├── penanganan.php        # Level L1-L4 penanganan siswa bermasalah (bagian 22)
│   ├── wa.php                # WaClient adapter OpenWA + antrean
│   ├── ikon.php              # Pustaka SVG inline (tanpa emoji)
│   └── layout.php            # Header, sidebar, navigasi bawah, flash
├── pages/
│   ├── admin/                # dashboard, siswa, kelas, kartu, hari_libur, sertifikat, pengguna, katalog, impor, cron, WA, log
│   ├── guru/                 # beranda, catat_poin, catat_banyak, riwayat, scan, pembinaan, izin, penilaian, banding
│   ├── siswa/                # beranda, riwayat, banding, kartu, sertifikat
│   ├── bk/                   # beranda, tindak_lanjut, penanganan, penanganan_detail
│   ├── kepsek/               # beranda (hanya baca)
│   ├── wali/                 # beranda (hanya baca ringkasan anak)
│   └── umum/                 # beranda, siswa, rekap, rekap_kehadiran, laporan, cetak_sertifikat, notifikasi
├── api/                      # cari_siswa.php, scan.php (endpoint JSON + CSRF)
├── cron/                     # harian.php, mingguan.php, wa.php
├── public/                   # css/, js/, img/, uploads/ (dilarang eksekusi PHP)
├── storage/logs/             # Log galat di luar folder publik
└── contoh/                   # CSV + SQL data fiktif
```

---

## 7. Aturan poin ringkas

- Skor dihitung **per aspek dan per semester**, mulai **0**, tanpa batas atas, boleh minus tanpa batas bawah. Semester baru kembali dari 0; data lama tetap tersimpan (diarsipkan lewat `semester_id`).
- Skor aspek = jumlah `catatan_poin` berstatus `aktif`. Catatan dibatalkan tidak dihitung tetapi **tidak pernah dihapus** (jejak audit: alasan, pelaku, waktu).
- Persentase = `skor / target_semester × 100`. Target bawaan 100 per aspek, dapat diubah admin.
- Predikat per aspek: `>100` → **A+**, `90–100` → **A**, `75–89` → **B**, `60–74` → **C**, `0–59` → **D**, `<0` → **E**. Semua ambang ada di tabel `pengaturan`. **Beranda siswa** kini juga menampilkan **rata-rata keseluruhan + predikat keseluruhan** dari rata-rata persentase 6 aspek; sertifikat tetap memakai predikat per aspek (bagian 16).
- Sumber otomatis (`sumber = sistem`): terlambat −2, alpa −5, tidak sholat −3 per sholat; bonus pagi +1 setiap 5 hari awal (maks +10/semester) dari cron mingguan; minggu bersih +2, minggu penuh dhuha +2, minggu penuh dhuhur +2. Besar poin diambil dari **Pengaturan → Bobot poin otomatis**; katalog poin dipakai untuk identitas kejadian dan untuk catatan manual.
- **Pengurangan poin tidak dibatasi** (bagian 20): pagar harian lama dihapus sehingga pelanggaran berat −15/−20/−25 dapat dicatat. Penggantinya adalah panel **Catatan siswa pada tanggal ini** yang menampilkan seluruh catatan (manual maupun otomatis) tepat saat siswa & tanggal dipilih. Izin, sakit, dan udzur tidak pernah mengurangi poin.
- **Dilarang** menampilkan peringkat siswa dengan skor terendah. `siswa_teladan()` hanya menampilkan peringkat positif.

---

## 8. Keamanan

- `password_hash` / `password_verify`, regenerasi ID sesi saat login, cookie `HttpOnly` + `SameSite=Lax`, blokir 5 percobaan gagal / 15 menit. Cookie `SIDIPAINGAT` juga `HttpOnly` dan `Secure` saat HTTPS; token mentah tidak pernah disimpan (hanya SHA-256), diputar setiap dipakai, kedaluwarsa 30 hari, dan dicabut saat logout atau ganti kata sandi.
- Token CSRF pada **semua** form dan permintaan POST, termasuk endpoint API (`X-CSRF-Token` atau `csrf_token`).
- Semua output melalui `e()` (`htmlspecialchars`), semua query memakai prepared statements, nama tabel/kolom divalidasi sebelum disisipkan.
- Pemeriksaan peran di server pada setiap halaman dan endpoint tetap dipertahankan; siswa hanya membaca datanya sendiri. **Kebijakan baru:** guru dapat menilai seluruh siswa aktif tanpa penugasan `guru_kelas`; siswa/wali murid tetap dibatasi pada anaknya.
- Unggah bukti gambar JPG/PNG/WebP maksimal 2 MB dengan nama acak; `public/uploads/.htaccess` mematikan eksekusi PHP.
- Transaksi DB untuk operasi multi-tabel; galat dicatat ke `storage/logs/sidipa.log` di luar folder publik.
- `log_akses` mencatat login, perubahan data, pembatalan, cron, dan **setiap** pembukaan identitas pencatat oleh admin.

---

## 9. Integrasi WhatsApp (OpenWA)

Satu-satunya bagian yang boleh disesuaikan setelah kode jadi adalah **`app/wa.php`**: array `$payload`, `$headers`, dan pengaturan `wa_endpoint` / `wa_base_url` / `wa_api_key` / `wa_session_id` di menu Pengaturan. Sesuaikan dengan dokumentasi OpenWA di VPS sekolah. Semua pemanggil tetap memakai `send($nomor, $pesan)`.

Pesan **tidak dikirim saat scan**; scan memasukkan baris ke `notifikasi_wa` dengan status `antri`. `cron/wa.php` mengirim bertahap dengan jeda acak 3–8 detik, maksimal 20 pesan per jalan, 3 percobaan. Template mendukung `{nama}`, `{kelas}`, `{tanggal}`, `{jam}`, `{aspek}`, `{poin}`. Pemicu dapat diaktifkan/dinonaktifkan per jenis; **Admin → Antrian WA** untuk melihat status dan mengulang pesan gagal. Pemicu **sertifikat terbit** kini terhubung ke `terbitkan_sertifikat()`: setiap penerbitan memasukkan pesan ke antrian bila `wa_pemicu_sertifikat` aktif dan nomor wali tersedia.

---

## 10. Checklist pengujian (dari bagian 16 PDF)

- [x] Skor mulai 0, boleh negatif; catatan dibatalkan tidak dihitung; semester baru kembali 0 — `skor_per_aspek()`
- [x] Predikat per aspek; skor di atas target menghasilkan A+ — `predikat_dari_persentase()`
- [x] Nama pencatat tidak pernah terkirim ke peran non-admin (termasuk JSON) — `KOLOM_CATATAN_AMAN`, `api/cari_siswa.php`
- [x] Admin membuka pencatat dan tercatat di `log_akses` — `lihat_pencatat()`
- [x] Cron harian/mingguan idempoten — `kunci_unik` + `ON DUPLICATE KEY UPDATE`; **diuji runtime dua kali** pada tanggal sama: 20 catatan / 20 kunci unik / 20 baris kehadiran, tanpa duplikasi
- [x] Bonus datang pagi mengikuti 5 hari dan batas +10/semester — `cron_mingguan()`
- [x] Verifikasi publik hanya menampilkan data skor dan status — `verifikasi.php`
- [x] Beranda guru persis bagian 3: satu stiker, tanpa kalender/banner, 6 kartu + 2 kartu wali kelas — `pages/guru/beranda.php`
- [x] Guru membatalkan catatannya sendiri ≤24 jam dengan alasan; baris tidak dihapus — `batalkan_catatan_sendiri()`
- [x] Cap pengurangan manual 10 poin/aspek/hari, deskripsi wajib sedang/berat — `pages/guru/catat_poin.php`
- [x] Pelanggaran berat memberi tahu BK/kepsek/wali kelas; skor <0 memberi tahu BK/wali murid — `notifikasi_pelanggaran_berat()`, `evaluasi_ambang_siswa()`
- [x] Siswa hanya melihat kategori, tanggal (tanpa jam/mapel), poin, deskripsi — `pages/siswa/riwayat.php`, `KOLOM_CATATAN_AMAN`
- [x] Scan 06.45 awal / 06.55 hadir / 07.05 −2, duplikat ditolak, izin-sakit netral, jendela ibadah — `api/scan.php`
- [x] PWA terpasang di semua halaman; halaman PHP/API tidak pernah di-cache — `sw.js`, `pwa_meta()`
- [x] Layout 360–430 px, CSRF, dan prepared statements di seluruh halaman yang ada
- [x] Cookie sesi `Path` selalu berupa path (bug URL penuh diperbaiki), `SameSite`/`Partitioned` dapat dikonfigurasi untuk preview lintas-situs, login pulih otomatis saat token CSRF kedaluwarsa (**bagian 14**), dan **login persisten 30 hari** dengan rotasi token (**bagian 15**)
- [x] Kartu siswa 85,6 × 54 mm berisi **username siswa** pada QR, cetak per kelas dan per siswa — `pages/admin/kartu.php`, `pages/siswa/kartu.php`
- [x] Hari libur dikelola admin dan dilewati cron/scan — `pages/admin/hari_libur.php`
- [x] Rekap kehadiran harian/bulanan/semester per siswa dan per kelas + unduh XLSX — `app/laporan.php`, `app/xlsx.php`
- [x] Admin menutup periode input; catat poin, scan, izin, dan penilaian bulanan dibekukan — `cek_input_semester()`
- [x] Sertifikat siswa/wali hanya dapat dicetak setelah `terbit` **dan** semester berganti; kode `SDP-...`, snapshot JSON, QR verifikasi, tanda tangan, **2 halaman A4 landscape** (pernyataan + deskripsi penilaian) — `pages/umum/cetak_sertifikat.php` (bagian 21)
- [x] Admin membatalkan sertifikat (dari menu admin maupun verifikasi publik) dengan alasan, pelaku, dan waktu; verifikasi publik berubah menjadi "dibatalkan"
- [x] Dashboard BK & kepala sekolah (kepsek hanya baca), laporan per kelas/aspek + XLSX, tindak lanjut dengan poin positif opsional
- [x] Portal wali murid hanya menampilkan ringkasan anaknya; pencatat dan riwayat pelanggaran tidak pernah terkirim
- [x] QR dibuat dan dipindai tanpa CDN, tersedia offline lewat service worker — `public/js/qr*.js`
- [x] Mode **catat banyak siswa sekaligus untuk satu kejadian** (§10) dengan transaksi all-or-nothing + daftar catatan per tanggal (tanpa cap harian, bagian 20) — `pages/guru/catat_banyak.php`
- [x] Dashboard siswa memuat **grafik perkembangan mingguan** SVG inline 8 minggu (§10), hanya angka agregat tanpa nama pencatat
- [x] **Level penanganan siswa bermasalah untuk Guru BK (§22)**: daftar berbasis riwayat pelanggaran, L1–L4 otomatis & manual, status/tahap/penanggung jawab, jejak perubahan, dan detail per siswa — `pages/bk/penanganan.php`, `pages/bk/penanganan_detail.php`
- [x] Uji runtime bagian 22: **314/314** pemeriksaan (level otomatis, naik/turun level, jejak riwayat, detail siswa, hak akses, privasi, dan simulasi tabel belum dimigrasi)
- [x] Uji runtime menyeluruh: **126/126** halaman/alur Tahap 3–5 + **13/13** uji lanjutan + **18/18** uji tambahan + **55/55** uji pembaruan (termasuk sliding 30 hari) + **35/35** uji fitur siswa + **52/52** uji predikat & username + **61/61** uji scan/QR/saran + **92/92** uji username bebas & saran siswa + **51/51** uji tanpa batas & riwayat harian = **503/503** lulus pada preview PHP 8.3 + MySQL 5.7

---

## 11. Catatan pengembangan

Semua angka operasional (jam, bobot, target, ambang) tersimpan di tabel `pengaturan`, bukan ditulis mati di kode. Perubahan kalibrasi setelah semester pertama cukup lewat menu Admin → Pengaturan.

Keputusan desain yang perlu diketahui saat merawat sistem:

- `semester_aktif()` memprioritaskan semester `aktif = 1 AND status = 'berjalan'`; bila admin menutup periode, fungsi ini jatuh ke semester terbaru untuk keperluan **laporan saja**. Penulisan poin/kehadiran dicek terpisah lewat `semester_input_terbuka()`.
- Sertifikat yang dibatalkan tidak dihapus. `alasan_batal`, `dibatalkan_oleh`, `dibatalkan_pada` tersimpan di tabel `sertifikat`, dan `data_json` tetap utuh sebagai snapshot.
- Penerbitan ulang setelah pembatalan mengganti kode dan snapshot lama, bukan menimpa sebagian, sehingga satu siswa tetap punya paling banyak satu sertifikat per semester (`uq_sertifikat_siswa_semester`).
- `rekap_kehadiran()` hanya mengambil kolom agregat; tidak ada `pencatat_id`, `qr_token`, atau nomor WhatsApp dalam hasil maupun berkas XLSX. Impor data master siswa masih memakai CSV (`contoh/siswa_contoh.csv`) — itu **bukan** laporan.
- Portal wali mengandalkan `siswa.wali_pengguna_id`. Akun wali tanpa tautan tidak melihat data siswa mana pun (bukan menampilkan semua siswa).

---

## 12. PWA (Progressive Web App)

Seluruh sistem sekarang installable: ada `manifest.webmanifest`, `sw.js`, ikon 192/512/maskable/Apple, halaman `offline.html`, tombol **Pasang**, badge **Offline**, dan pemberitahuan versi baru. Tag PWA disuntikkan lewat `pwa_meta()`/`pwa_skrip()` di `app/layout.php`, sehingga **semua halaman** (layout umum, login, verifikasi publik, installer) memakai manifest dan pendaftaran service worker yang sama — tidak ada halaman yang tertinggal.

### Cara pasang di perangkat

1. Buka aplikasi lewat **HTTPS** (atau `localhost` saat uji coba) di Chrome/Edge Android atau Safari iOS.
2. Android: tombol **Pasang** muncul di header (dan di halaman login/verifikasi) saat browser mengirim `beforeinstallprompt`; atau menu browser → *Install app* / *Add to Home Screen*.
3. iOS: tombol **Bagikan** → *Tambahkan ke Layar Utama*. Ikon `apple-touch-icon.png` dan `apple-mobile-web-app-*` sudah disiapkan.
4. Setelah terpasang, aplikasi terbuka `display: standalone` tanpa bilah alamat; warna tema `#FFF3E4` mengikuti desain neo-brutalism. Shortcut di ikon aplikasi langsung menuju *Verifikasi Sertifikat* dan *Masuk*.

### Strategi cache (penting untuk keamanan)

| Jenis permintaan | Perlakuan |
| --- | --- |
| `public/css/*`, `public/js/*`, `public/img/*`, manifest, `offline.html` | **Stale-while-revalidate**: langsung dari cache, diperbarui di latar |
| Navigasi halaman (`*.php`, `index.php`, `verifikasi.php`) | **Network-first**, fallback ke `offline.html` saat putus |
| `POST`, `api/*`, `cron/*`, `pages/*`, `install.php`, `public/uploads/*`, CDN, WA API | **Selalu jaringan, tidak pernah di-cache** |

Halaman PHP **sengaja tidak disimpan** karena memuat token CSRF terikat sesi dan data berbeda per peran (admin/guru/BK/kepsek/siswa/wali). Menyimpannya berisiko membocorkan data antar-akun dan membuat CSRF tidak valid. Halaman cetak kartu/sertifikat dan halaman scan tetap memakai jaringan untuk data PHP-nya, tetapi **pustaka QR-nya diambil dari cache lokal**, bukan CDN.

Saat tanpa koneksi, `offline.html` menjelaskan bahwa login, scan QR, catat poin, dan rekap perlu internet; cukup tekan **Coba lagi** setelah online. Badge merah **Offline — data belum tersinkron** muncul otomatis lewat event `online`/`offline`.

### Pembaruan versi

`sw.js` memakai `VERSI_CACHE = 'sidipa-pwa-v2'`. Setiap kali Anda mengubah CSS/JS/manifest/ikon, **naikkan angka versi** (mis. `sidipa-pwa-v3`) supaya cache lama dihapus pada event `activate`. Pengguna akan melihat kotak **"Versi baru SiDiPa tersedia → Muat ulang"**. Root `.htaccess` mengirim `sw.js` dan `manifest.webmanifest` dengan `Cache-Control: no-cache, no-store` plus `Service-Worker-Allowed: /`, dan mendaftarkan MIME `application/manifest+json` untuk `.webmanifest`. Bila `mod_headers` tidak aktif di cPanel, aktifkan lewat *Select PHP Version* / minta penyedia hosting; `install.php` memeriksanya.

Aset yang di-precache kini mencakup `public/css/cetak.css`, `public/css/kartu.css`, `public/js/qrcode.min.js`, `public/js/qr-sidipa.js`, dan `public/js/html5-qrcode.min.js`. Karena pustaka QR tidak lagi diambil dari CDN, halaman cetak kartu/sertifikat dan pemindai dapat membuka QR tanpa koneksi ke pihak ketiga (data PHP/API/POST tetap tidak pernah di-cache). `install.php` memeriksa **14 berkas** PWA/aset statis.

### Dukungan subdirektori cPanel

Semua URL PWA relatif terhadap lokasi aplikasi: manifest memakai `scope: "./"`, `start_url: "./?sumber=pwa"`, dan `sw.js` menghitung `self.registration.scope`. `pwa_meta()` menulis `<meta name="sidipa-base">` dari `basis_url()`, lalu `pwa.js` mendaftarkan `sw.js` pada scope yang tepat. Jadi pemasangan di `https://domain/sidipa/` tetap benar tanpa menyunting berkas. Jika `SIDIPA_BASE_URL` di `config.php` diisi domain utuh, nilainya tetap dipakai bersamaan.

### Uji cepat

- Chrome DevTools → **Application → Manifest**: nama, ikon, warna, dan shortcut terbaca tanpa peringatan.
- **Application → Service Workers**: status *activated and is running*; centang *Offline* lalu muat ulang → muncul `offline.html`.
- **Lighthouse → PWA**: *Installable*, *Service worker*, *Themed address bar* lolos (pastikan HTTPS aktif).
- `install.php` mencantumkan pemeriksaan keberadaan 14 berkas PWA/aset statis + HTTPS + `mod_headers`.

---

## 13. Alur Tahap 3–5 (ringkasan operator)

| Tujuan | Langkah |
| --- | --- |
| Cetak kartu QR siswa | **Admin → Cetak Kartu QR** → pilih kelas → **Cetak / Simpan PDF** (kertas 85,6 × 54 mm, skala 100%). Siswa juga dapat mencetak kartunya sendiri lewat **Kartu QR saya** |
| Kartu hilang | **Admin → Data siswa** → tombol **QR** pada baris siswa → token lama langsung tidak berlaku → cetak ulang kartu |
| Tandai libur sekolah | **Admin → Hari Libur** → isi tanggal/rentang + keterangan. Cron harian/mingguan dan scan melewati tanggal tersebut |
| Rekap kehadiran | **Rekap Kehadiran** → pilih Harian / Bulanan / Semester, kelas (bila berhak), lalu **Unduh XLSX** |
| Akhir semester | **Admin → Semester & Sertifikat** → **Tutup** semester berjalan (input poin/scan/izin/penilaian langsung dibekukan) → **Terbitkan sertifikat** per kelas atau semua |
| Cetak sertifikat | Siswa/wali: **Sertifikat saya → Lihat** selama semester masih berjalan; setelah admin mengaktifkan semester baru muncul tombol **Cetak** yang menghasilkan **2 halaman A4 landscape** (halaman 1 surat pernyataan, halaman 2 deskripsi penilaian). Cetak dengan skala 100% dan header/footer browser dimatikan. Admin/BK/kepsek tetap dapat mencetak kapan saja |
| Verifikasi eksternal | Pindai QR di sertifikat atau buka `verifikasi.php?kode=...`; tanpa login, hanya status + identitas + skor per aspek |
| Sertifikat salah | Admin membatalkan lewat daftar sertifikat **atau** halaman verifikasi publik, wajib alasan. Status publik berubah jadi *dibatalkan* dan tombol cetak nonaktif |
| Semester baru | **Tambah semester** → **Aktifkan**. Skor kembali 0 karena semua perhitungan memakai `semester_id`; data lama tetap tersimpan |
| Pendampingan BK | **BK → Tindak Lanjut**: pilih siswa, isi pemicu/tindakan/status; centang poin positif bila ada kegiatan perbaikan |
| Siswa bermasalah | **BK → Siswa Bermasalah**: lihat daftar berbasis pelanggaran, tetapkan/ubah **level L1–L4**, status, tahap & penanggung jawab; buka **Detail** untuk riwayat pelanggaran dan jejak perubahan level (bagian 22) |
| Wali murid | Buat akun peran **Wali murid** di *Pengguna*, tautkan di *Data siswa*. Portal hanya menampilkan ringkasan anaknya |

**Uji coba yang sudah dijalankan** pada preview PHP 8.3.33 + MySQL 5.7.29: **67 file PHP tanpa galat parsing** (termasuk 3 berkas baru bagian 22), seluruh halaman 200 untuk enam peran, ekspor XLSX (divalidasi `openpyxl`: 3 lembar, 20 siswa, sel numerik), tutup/buka semester, terbitkan/batalkan sertifikat, cetak A4, QR verifikasi, idempotensi cron dua kali, mode catat banyak, serta **503 pemeriksaan fungsional lulus (126 + 13 + 18 + 55 + 35 + 52 + 61 + 92 + 51)**, termasuk uji degradasi saat kolom `foto` belum ada, uji 8 batas predikat, uji QR username/saran otomatis/bunyi, uji username bebas + saran siswa di delapan halaman, dan uji pelanggaran −25 tanpa batas + privasi endpoint riwayat harian. **Bagian 22** divalidasi dengan **314/314 pemeriksaan** pada preview yang sama (level otomatis, penetapan manual naik/turun, jejak riwayat, detail siswa, filter, hak akses, validasi CSRF/level/tanggal, privasi, dan ketahanan instalasi lama tanpa migrasi). **Bagian 21** divalidasi terpisah dengan **81/81 pemeriksaan** pada preview PHP 8.3 + MySQL 5.7 yang dibangun ulang: struktur 2 halaman, kalimat pernyataan, nilai rata-rata karakter, fallback sertifikat lama, penahanan cetak siswa/wali, pembukaan cetak setelah semester berganti, sertifikat dibatalkan, verifikasi publik, dan snapshot versi 2 dari database nyata.

---

## 14. Troubleshooting: "Token keamanan tidak valid"

Token CSRF disimpan di dalam sesi PHP. Sesi itu diidentifikasi cookie `SIDIPASES`; bila cookie tidak ikut terkirim saat tombol **Masuk** ditekan, server membuat sesi baru yang belum punya token, sehingga token lama ditolak. Pesan ini **bukan** berarti kata sandi salah.

Penyebab paling umum, berurutan:

| Penyebab | Gejala | Penanganan |
| --- | --- | --- |
| Aplikasi dibuka sebagai **iframe lintas-situs** (panel preview) | Selalu gagal, berulang kali, di browser tertentu | Cookie `SameSite=Lax` memang tidak dikirim pada konteks lintas-situs. Aktifkan `SIDIPA_SAMESITE=None` **dan** `SIDIPA_COOKIE_PARTISI=1` lewat environment; keduanya hanya berlaku saat HTTPS. Buka juga preview di tab baru bila browser memblokir cookie pihak ketiga |
| Halaman login dipulihkan dari back/forward cache | Gagal setelah tombol Back atau tab lama dibuka lagi | Halaman login sudah memaksa `location.reload()` pada event `pageshow` yang `persisted` |
| HTTPS tidak terdeteksi (proxy memutus TLS) | Kadang gagal hanya lewat domain proxy | `https_aktif()` membaca `X-Forwarded-Proto`; pastikan reverse proxy meneruskan `X-Forwarded-Proto: https` |
| `SIDIPA_BASE_URL` diisi **URL penuh** | Cookie ditolak browser | Sudah diperbaiki: `jalur_cookie_sesi()` mengambil hanya komponen path untuk atribut `Path` cookie |
| Cookie sesi hilang karena alasan lain | Muncul sekilas | Halaman login **tidak lagi menampilkan 403**: sesi dan token baru otomatis disiapkan, form ditampilkan ulang dengan pesan, cukup tekan **Masuk** sekali lagi |

Konfigurasi terkait di `config.php` (bisa dioverride lewat environment tanpa menyunting berkas):

```php
define('SIDIPA_SAMESITE', getenv('SIDIPA_SAMESITE') ?: 'Lax');              // 'Lax' | 'None' | 'Strict'
define('SIDIPA_COOKIE_PARTISI', (bool) (getenv('SIDIPA_COOKIE_PARTISI') ?: false)); // CHIPS untuk iframe
```

Untuk produksi di hosting biasa (tab langsung, HTTPS) biarkan default `Lax` — paling aman dan tidak memerlukan `Partitioned`. Untuk preview yang disematkan, `sinkron.py` menetapkan `None` + `Partitioned` secara otomatis pada salinan `.preview/www` saja; berkas sumber tidak berubah. Atribut `Partitioned` diterapkan lewat `auth_pasang_cookie()` karena `session_set_cookie_params()` PHP 8.3 belum mendukungnya.

Catatan: di preview, paksaan ganti kata sandi pertama dimatikan agar akun contoh dapat langsung dicoba. Data `contoh/data_contoh.sql` yang dipakai saat instalasi tetap menetapkan `wajib_ganti_password = 1` sesuai spesifikasi.


---

## 15. Pembaruan: cakupan guru, login sekali, dan XLSX

Tiga perubahan perilaku yang sengaja menggantikan aturan lama:

### 15.1 Guru menilai seluruh siswa aktif

Sebelumnya `guru_kelas` dan `kelas.wali_kelas_id` membatasi daftar siswa. Sekarang **semua guru** dapat mencatat poin (tunggal maupun banyak siswa), memindai QR, mencatat izin/sakit, mengisi penilaian bulanan, memutus banding, melihat pembinaan, dan membuka rekap untuk **siswa aktif mana pun** tanpa penugasan. Pengaturan guru pengampu/wali kelas tetap ada di data master untuk keperluan administrasi, tetapi tidak lagi dipakai sebagai gerbang akses.

Implementasi: `daftar_siswa_untuk_guru()` dan `guru_boleh_siswa()` di `app/helpers.php` memakai `id_kelas_semua()`; semua halaman `pages/guru/*`, `api/scan.php`, `api/cari_siswa.php`, `pages/umum/{rekap,siswa,rekap_kehadiran}.php`, dan `pages/admin/kelas.php` mengikuti helper itu. **Yang tidak berubah:** `auth_wajib()` / pemeriksaan peran, token CSRF, serta pembatasan siswa dan wali murid hanya pada data anaknya.

### 15.2 Cukup login sekali — 30 hari sejak aktivitas terakhir

- Login **otomatis** menyimpan cookie sesi `SIDIPASES` dan cookie `SIDIPAINGAT` di memori HP/PC. Tidak perlu mencentang apa pun.
- **Batasnya bergulir (sliding / idle timeout), bukan 30 hari absolut sejak login pertama.** Setiap kali pengguna membuka halaman SiDiPa dalam keadaan sudah masuk, `auth_ingat_perpanjang()` menulis ulang `Max-Age` kedua cookie menjadi 30 hari penuh dan menggeser `ingat_login.kedaluwarsa` ke `NOW() + 30 hari`. Penulisan DB dibatasi paling sering sekali sehari agar tidak ada `UPDATE` pada setiap request.
- Akibatnya pengguna **hanya diminta login lagi bila benar-benar tidak membuka aplikasi selama 30 hari berturut-turut**. Pemakaian setiap hari tidak pernah memicu login ulang. Token yang sudah lewat batas sengaja **tidak** dihidupkan kembali.
- Bila sesi PHP hilang (browser ditutup, server restart), `auth_pengguna()` memulihkan login dari `SIDIPAINGAT` tanpa menampilkan halaman login, dan token langsung diputar dengan batas 30 hari yang baru.
- Form login menyediakan **Jangan simpan login di perangkat ini** (`jangan_ingat`) untuk komputer bersama; pilihannya disimpan di sesi sehingga request berikutnya tidak mengubah cookie menjadi persisten.
- **Logout** menghapus kedua cookie dan mencabut token. **Ganti kata sandi** mencabut semua perangkat lain, lalu memberi token baru hanya pada perangkat yang sedang dipakai.
- Durasi dapat diubah lewat `SIDIPA_INGAT_HARI` di `config.php` / environment (`SIDIPA_INGAT_HARI=7`); perilaku bergulirnya otomatis mengikuti nilai tersebut.
- Cookie `SIDIPAINGAT` bertanda `HttpOnly`, `SameSite` mengikuti `SIDIPA_SAMESITE`, `Secure` + `Partitioned` saat HTTPS/iframe (lihat bagian 14).

**Migrasi untuk instalasi yang sudah berjalan.** Impor ulang `database.sql` aman (semua `CREATE TABLE IF NOT EXISTS`), atau jalankan SQL berikut sekali di phpMyAdmin:

```sql
CREATE TABLE IF NOT EXISTS ingat_login (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  pengguna_id INT UNSIGNED NOT NULL,
  token_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  dibuat_pada DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  kedaluwarsa DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_ingat_token (token_hash),
  KEY idx_ingat_pengguna (pengguna_id),
  KEY idx_ingat_kedaluwarsa (kedaluwarsa),
  CONSTRAINT fk_ingat_pengguna FOREIGN KEY (pengguna_id) REFERENCES pengguna (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

`install.php` kini memeriksa keberadaan tabel ini. Tanpa tabel, login tetap berfungsi normal tetapi **tidak** bertahan 30 hari (galat dicatat diam-diam ke `storage/logs/sidipa.log`).

### 15.3 Semua laporan berformat XLSX

`csv_keluar()` dihapus. `app/xlsx.php` menulis berkas OOXML/ZIP sendiri memakai PHP murni (`crc32()` bawaan PHP, metode ZIP STORE), **tanpa Composer dan tanpa ekstensi `ZipArchive`**, jadi tetap jalan di shared hosting cPanel. Fitur: sel `inlineStr` aman terhadap rumus/injeksi CSV, header tebal + baris beku, `autoFilter`, lebar kolom, angka sebagai sel numerik, dan beberapa lembar kerja.

| Laporan | Tombol | Isi berkas |
| --- | --- | --- |
| `pages/umum/rekap_kehadiran.php` | **Unduh XLSX** | `Rekap Siswa`, `Ringkasan Kelas`, `Keterangan` |
| `pages/umum/laporan.php` | **Unduh XLSX (skor + ringkasan)** | `Skor per Siswa`, `Ringkasan per Kelas`, `Keterangan` |

Log aktivitas memakai `ekspor_xlsx_kehadiran` / `ekspor_xlsx_laporan`. Berkas diuji dengan `openpyxl`: ZIP valid, nama lembar sesuai, 20 baris siswa, dan kolom angka terbaca sebagai angka. **Impor data master siswa tetap CSV** (`contoh/siswa_contoh.csv`, menu *Impor CSV*) karena itu berkas masukan, bukan laporan.


### 15.4 Berkas & database untuk pembaruan "30 hari bergulir"

| Yang diganti | Wajib? | Isi perubahan |
| --- | --- | --- |
| `app/auth.php` | **WAJIB** | Fungsi baru `auth_ingat_perpanjang()`; dipanggil dari `auth_pengguna()` hanya saat sesi masih ada; penanda `$_SESSION['waktu_aktif']` di `auth_mulai()` agar berkas sesi ikut tersegar |
| `pages/login.php` | Disarankan | Teks bantuan form diubah menjelaskan bahwa batas diperpanjang tiap kali aplikasi dibuka |
| `database.sql` | Tidak | Hanya **komentar** bagian 22 yang diperjelas. Tidak ada perubahan struktur |
| `config.php` | Tidak | `SIDIPA_INGAT_HARI` sudah ada sejak pembaruan sebelumnya; nilainya otomatis dipakai sebagai lama idle. Ubah hanya bila ingin durasi selain 30 hari |
| `README.md` | Tidak | Dokumentasi bagian 15.2 dan tabel ini |

**Database: TIDAK ADA MIGRASI / `ALTER TABLE`.** Kolom `ingat_login.kedaluwarsa` yang sudah ada kini dipakai sebagai penanda "aktivitas terakhir + 30 hari"; nilainya digeser, bukan ditambah kolom baru. Cukup unggah `app/auth.php` (dan `pages/login.php` bila ingin teks bantuan ikut berubah).

> Catatan: bila server Anda **belum** punya tabel `ingat_login` (belum menerapkan pembaruan login persisten sebelumnya), jalankan dulu `CREATE TABLE` pada bagian 15.2. `install.php` akan menandainya sebagai belum tersedia.


---

## 16. Pembaruan beranda siswa, menu, sertifikat, dan foto profil

### 16.1 Nilai rata-rata keseluruhan + predikat di beranda siswa

`pages/siswa/beranda.php` kini punya kartu **"Nilai keseluruhan"** di atas grid skor per aspek, berisi:

| Kolom | Isi |
| --- | --- |
| Rata-rata keseluruhan poin | Rata-rata **persentase capaian** dari 6 aspek terhadap target semester (tiap aspek berbobot sama), ditampilkan dalam persen |
| Predikat keseluruhan | `A+ / A / B+ / B / B- / C+ / C / D` + keterangan, dari `predikat_dari_persentase()` pada nilai rata-rata tersebut (bagian 17) |
| Rincian angka | Total poin, total target, dan rata-rata poin mentah per aspek |

Helper baru: `ringkasan_skor_siswa(array $skor)` di `app/skor.php`. **Sertifikat tetap memakai predikat per aspek** — agregat ini khusus untuk tampilan beranda siswa, sesuai permintaan.

### 16.2 Kaji ulang menu bawah akun siswa

Item **"Siswa"** pada navigasi bawah sebelumnya menuju `pages/umum/siswa.php`, padahal siswa hanya boleh melihat datanya sendiri — sehingga hampir tidak berguna. Untuk peran `siswa`, item itu **diganti menjadi "Sertifikat"** (`pages/siswa/sertifikat.php`, ikon sertifikat) lewat `menu_bawah_peran()` di `app/layout.php`. Menu bawah siswa sekarang: **Beranda · Sertifikat · Rekap · Profil**. Peran lain tidak berubah.

### 16.3 Siswa hanya bisa lihat sertifikat sebelum semester berganti

- Selama semester sertifikat masih menjadi semester aktif, siswa **dan wali murid** hanya mendapat tautan **Lihat**: sertifikat tampil di `pages/umum/cetak_sertifikat.php`, tetapi tombol cetak tidak ada dan `@media print` menyembunyikan kertas sertifikat (Ctrl+P tidak menghasilkan sertifikat).
- Cetak baru tersedia setelah **admin mengaktifkan semester baru** yang berbeda dari semester sertifikat. Penentunya: `sertifikat_sudah_ganti_semester()` di `app/sertifikat.php` mencari baris `semester.aktif = 1` dengan `id` berbeda.
- Admin, BK, dan kepala sekolah **tidak terkena** penahanan ini. Sertifikat berstatus `dibatalkan` tetap tidak dapat dicetak oleh siapa pun.
- Daftar sertifikat (`sertifikat_siswa()`) sekarang ikut mengembalikan `semester_id`.

### 16.4 Upload foto profil

- Kolom baru **`pengguna.foto`** menyimpan path relatif; formulir **Profil → Foto saya** menerima JPG/PNG/WEBP (validasi `getimagesize()`, nama acak, maksimal 2 MB lewat pengaturan `maks_ukuran_foto`), lengkap dengan tombol **Hapus foto**. Foto lama otomatis dihapus dari `public/uploads/` saat diganti.
- `public/uploads/.htaccess` sudah melarang eksekusi PHP. Foto tampil di kartu sapaan **beranda siswa**, avatar header, dan halaman Profil; bila belum ada, dipakai inisial nama.
- Form tersedia untuk **semua peran** (termasuk akun siswa), karena kolomnya berada di tabel `pengguna`.

### 16.5 Berkas & database yang perlu diganti

| Berkas | Wajib? | Isi perubahan |
| --- | --- | --- |
| `app/skor.php` | **WAJIB** | `ringkasan_skor_siswa()` |
| `app/sertifikat.php` | **WAJIB** | `sertifikat_sudah_ganti_semester()`, `sertifikat_peran_terbatas()`, `semester_id` pada `sertifikat_siswa()` |
| `app/helpers.php` | **WAJIB** | `unggah_foto_profil()`, `hapus_berkas_foto()`, `foto_pengguna()` |
| `app/layout.php` | **WAJIB** | `menu_bawah_peran()`, avatar foto, penanda menu aktif |
| `pages/siswa/beranda.php` | **WAJIB** | kartu ringkasan + foto |
| `pages/profil.php` | **WAJIB** | form unggah/hapus foto |
| `pages/siswa/sertifikat.php`, `pages/wali/beranda.php`, `pages/umum/cetak_sertifikat.php` | **WAJIB** | penahanan cetak |
| `pages/umum/cetak_sertifikat.php` | (sudah termasuk) | pesan status + `cetak-terkunci` |
| `public/css/sidipa.css`, `public/css/cetak.css` | Disarankan | gaya kartu ringkasan/foto + blokir Ctrl+P |
| `database.sql`, `install.php`, `README.md` | Disarankan | skema & pemeriksaan & dokumentasi |

**Database: ADA SATU MIGRASI.** Untuk instalasi yang sudah berjalan, jalankan sekali di phpMyAdmin (aman diulang dengan pengecekan kolom terlebih dahulu):

```sql
ALTER TABLE pengguna
  ADD COLUMN foto VARCHAR(255) DEFAULT NULL AFTER nama;
```

`install.php` kini memeriksa kolom ini. **Bila ALTER belum dijalankan, aplikasi tidak rusak**: `foto_pengguna()` menangani kegagalan query dengan kembali ke inisial nama, sehingga semua halaman tetap dapat dibuka; hanya penyimpanan foto yang belum berfungsi. `database.sql` yang baru sudah memuat kolom ini untuk instalasi baru. Tidak ada perubahan pada tabel `sertifikat`, `semester`, atau `siswa`.

---

## 17. Pembaruan: predikat 8 tingkat & username siswa untuk login

### 17.1 Skala predikat baru (berlaku di seluruh aplikasi)

Skala lama `A+ / A / B / C / D / E` **diganti total** menjadi delapan tingkat. Rank/nilai ambang ditetapkan sebagai berikut, dihitung dari **persentase capaian target** (`skor aspek ÷ target × 100`, boleh lebih dari 100, boleh minus):

| Predikat | Mulai persentase | Keterangan |
| --- | --- | --- |
| **A+** | ≥ 95 % | Sangat istimewa |
| **A** | ≥ 90 % | Sangat baik |
| **B+** | ≥ 85 % | Baik sekali |
| **B** | ≥ 80 % | Baik |
| **B-** | ≥ 75 % | Cukup baik |
| **C+** | ≥ 70 % | Cukup, perlu ditingkatkan |
| **C** | ≥ 60 % | Cukup |
| **D** | < 60 % | Perlu pembinaan (nilai minus: *Pembinaan intensif*) |

Batas 60 % sengaja disamakan dengan `ambang_pembinaan_persen`, supaya predikat **D** selalu sejalan dengan status perlu pembinaan. Predikat **E sudah tidak ada**: seluruh nilai di bawah ambang `C` menjadi `D`.

Perubahan berlaku **di semua tempat** yang memakai `predikat_dari_persentase()` — kartu skor per aspek (beranda siswa, wali, rekap, pembinaan, BK, kepsek), **predikat keseluruhan** beranda siswa, tabel sertifikat, laporan XLSX, dan data baru yang dibekukan ke `data_json`. Sertifikat lama yang sudah terbit **tidak diubah** (snapshot bersifat permanen sesuai desain awal).

Kedelapan ambang tetap dapat dikalibrasi lewat **Admin → Pengaturan → Ambang predikat (8 tingkat)**; halaman itu kini menampilkan tabel legenda skala yang sedang berlaku. `ambang_predikat()` menurunkan otomatis ambang yang tumpang tindih, sehingga instalasi lama yang belum menjalankan migrasi tetap menghasilkan tangga predikat yang valid.

### 17.2 Login siswa memakai username, bukan NIS/NISN

Sebelumnya `pengguna.username` untuk akun siswa **diisi otomatis dengan NIS**, baik lewat Data siswa, impor CSV, maupun data contoh. Sekarang username siswa adalah **nilai terpisah**:

- **Data siswa** → field baru **Username siswa (untuk login)**. *Sejak bagian 19 **karakter benar-benar bebas*** (angka semua, huruf semua, huruf besar, spasi, atau simbol; lihat §19.1). Saat bagian ini dibuat masih dibatasi huruf kecil/angka/`.`/`-`/`_`, minimal 3 karakter dan wajib ada huruf. Bila dikosongkan, sistem membuat dari **dua kata pertama nama** — `Ahmad Rizky Prasetyo` → `ahmad.rizky` — dan menambahkan angka (`ahmad.rizky2`, `ahmad.rizky3`, …) bila bentrok. Mengubah NIS **tidak lagi** mengubah username.
- **Impor CSV** → kolom opsional `username` (alias: `nama_pengguna`, `username_siswa`, `user`, `login`). Kosong = dibuat otomatis dari nama; pratinjau menampilkan username final dan menolak username ganda (aturan "hanya angka" dihapus pada §19.1).
- **Pengguna** → validasi yang sama untuk akun berperan `siswa` (sejak §19.1: karakter bebas).
- **Halaman login** → label **Username** dan petunjuk memakai username akun. Petunjuk "bukan NIS/NISN" serta pesan *"NIS/NISN tidak dipakai untuk login lagi…"* berlaku hingga bagian 18; **keduanya diganti pada §19.1** karena username angka kini sah. Kata sandi, kebijakan 30 hari bergulir, CSRF, throttle, dan wajib ganti sandi **tidak berubah**.
- **Kartu QR** (`pages/admin/kartu.php` dan `pages/siswa/kartu.php`) kini mencetak baris **Login** berisi username siswa, di samping NIS dan kelas. **Isi QR kemudian diganti menjadi username siswa pada bagian 18**; kode sertifikat tetap memakai NIS.
- **Data contoh**: 20 akun siswa memakai username dari nama, mis. `20260100` → `ahmad.rizky`, `20260113` → `alya.putri2`. Daftar lengkap ada di `contoh/migrasi_username_siswa.sql` dan `contoh/README.md`.

**Migrasi massal untuk instalasi lama:** buka **Admin → Data siswa → Buatkan username massal**. Tombol ini membuat username dari nama (unik terhadap semua akun) dan menampilkan tabel **NIS → username baru**. Aman dijalankan berulang dan **kata sandi tetap**. *Sejak §19.1* yang dipilih **hanya akun warisan yang `username`-nya sama persis dengan NIS** — username angka pilihan admin tidak lagi ditimpa. Untuk paket data contoh bisa juga mengimpor `contoh/migrasi_username_siswa.sql`. `install.php` kini memeriksa **username siswa terisi**, bukan lagi "bukan NIS".

### 17.3 Berkas & database yang perlu diganti

| Berkas | Wajib? | Isi perubahan |
| --- | --- | --- |
| `app/skor.php` | **WAJIB** | `URUTAN_PREDIKAT`, `keterangan_predikat()`, `ambang_predikat()` 8 tingkat, `predikat_dari_persentase()` tanpa E |
| `app/helpers.php` | **WAJIB** | `username_siswa_bersih()`, `username_siswa_sah()`, `username_siswa_dari_nama()`, `username_siswa_unik()` |
| `pages/admin/siswa.php` | **WAJIB** | field username, validasi, **Buatkan username massal**, kolom tabel |
| `pages/admin/impor_siswa.php` | **WAJIB** | kolom `username`, generator otomatis, deteksi duplikat |
| `pages/admin/pengguna.php` | **WAJIB** | validasi username siswa |
| `app/auth.php` | **WAJIB** | label "Username" + pesan gagal-login (petunjuk NIS diperbarui di §19.1) |
| `pages/admin/pengaturan.php` | **WAJIB** | 8 ambang + legenda skala |
| `pages/admin/kartu.php`, `pages/siswa/kartu.php` | **WAJIB** | baris **Login** berisi username |
| `database.sql`, `contoh/data_contoh.sql`, `contoh/siswa_contoh.csv`, `contoh/migrasi_username_siswa.sql` | Disarankan | nilai bawaan & data contoh |
| `pages/login.php`, `pages/profil.php`, `public/css/kartu.css`, `install.php`, `contoh/README.md`, `README.md` | Disarankan | label, bantuan, preflight, dokumentasi |

**Database: TIDAK ADA tabel atau kolom baru.** Yang berubah hanya (a) **nilai** 8 baris di tabel `pengaturan` dan (b) **nilai** `pengguna.username` untuk akun siswa. Untuk instalasi lama jalankan sekali SQL berikut **dan** tombol migrasi username di atas:

```sql
-- 1. Tiga ambang baru (aman diulang).
INSERT IGNORE INTO pengaturan (kunci, nilai, grup) VALUES
 ('predikat_bplus',  '85', 'predikat'),
 ('predikat_bminus', '75', 'predikat'),
 ('predikat_cplus',  '70', 'predikat');

-- 2. Sesuaikan lima ambang lama dengan skala 8 tingkat.
--    PERHATIAN: ini menimpa kalibrasi manual admin pada kunci predikat_*.
UPDATE pengaturan SET nilai = '95' WHERE kunci = 'predikat_aplus';
UPDATE pengaturan SET nilai = '90' WHERE kunci = 'predikat_a';
UPDATE pengaturan SET nilai = '80' WHERE kunci = 'predikat_b';
UPDATE pengaturan SET nilai = '60' WHERE kunci = 'predikat_c';
UPDATE pengaturan SET nilai = '0'  WHERE kunci = 'predikat_d';
```

`install.php` kini memeriksa **kelengkapan 8 ambang** dan **tidak adanya siswa ber-username angka**. Bila SQL di atas belum dijalankan, aplikasi tetap berfungsi: kunci yang belum ada memakai bawaan dari kode, dan `ambang_predikat()` merapikan tangga yang tumpang tindih.

### 17.4 Uji coba

Suite `.preview/tmpjs/uji_predikat_username.py` (**52/52**) memeriksa 8 batas predikat lewat beranda siswa (termasuk nilai > 100 %, 59 %, dan minus), ketiadaan predikat E, **8 ambang** di Pengaturan, legenda skala, login username vs penolakan NIS, field/tabel username di Data siswa, tombol migrasi massal, impor CSV dengan & tanpa kolom `username`, serta baris **Login** pada kartu QR. Regresi lengkap **saat bagian itu**: 352/352; setelah bagian 19 **451/451** (126 + 13 + 17 + 55 + 35 + 52 + 61 + 92); setelah bagian 20 **503/503** pada preview PHP 8.3.33 + MySQL 5.7.29, dengan 64 berkas PHP tanpa galat parsing.

---

## 18. Pembaruan: scan QR, bunyi bip, saran siswa, dan QR berisi username

### 18.1 Jawaban: apakah scan QR langsung tercatat?

**Ya, langsung tercatat — tidak perlu menekan tombol "Catat Scan Masuk".** Alurnya:

```
Kamera membaca QR → callback Html5Qrcode → POST api/scan.php
  → tulis kehadiran (masuk/pulang) atau kehadiran_ibadah (Dhuha/Dhuhur)
  → kartu hasil + bunyi → tabel 10 scan terakhir dimuat ulang ~2,2 detik
```

Yang perlu disiapkan sebelum siswa mengantre: **pilih mode** lewat empat tombol di atas halaman (Scan Masuk / Scan Pulang / Sholat Dhuha / Sholat Dhuhur — pilihan disimpan di `$_SESSION['scan_mode_terakhir']`) dan **klik "Aktifkan kamera"** sekali. Tombol **"Catat …"** hanya dipakai untuk **fallback input manual** bila kamera/HTTPS tidak tersedia. Perlindungan yang tetap berlaku: kunci 800 ms di sisi browser, penolakan duplikat hari + jenis di server, jendela waktu ibadah, hari libur, izin/sakit/udzur, dan `cek_input_semester()`.

### 18.2 Bunyi bip saat siswa scan

Bunyi **sudah ada** sebelumnya (satu nada 880 Hz pada hasil sukses), tetapi sering tidak terdengar karena setiap scan membuat `AudioContext` baru tanpa pernah menutupnya — browser membatasi jumlah konteks audio. Sekarang:

- **Satu `AudioContext` dipakai ulang** untuk seluruh sesi halaman, dibuka/dilanjutkan (`resume()`) pada interaksi pertama pengguna agar lolos kebijakan autoplay.
- **Berhasil**: dua nada 880 Hz → 1175 Hz. **Gagal / duplikat / siswa tidak ditemukan**: nada rendah 220 Hz. Getar (`navigator.vibrate`) tetap dipertahankan.
- Sakelar **Bunyi bip** dan tombol **Coba bunyi** tersedia di kartu pemindai; pilihan disimpan di `localStorage` per perangkat (bawaan: aktif).
- Bunyi juga berlaku untuk hasil input manual, bukan hanya kamera.

### 18.3 Satu input manual: NIS atau nama + saran otomatis

Input NIS manual diganti menjadi **"NIS atau nama siswa"** dengan saran otomatis:

- Mengetik ≥ 2 karakter memanggil `api/cari_siswa.php?q=…` (debounce 180 ms, permintaan lama diabaikan). Endpoint ini kini **diizinkan untuk peran `guru`** (sebelumnya 403) dan mencocokkan `siswa.nama` **atau** `siswa.nis`; sesuai keputusan, username **tidak** ikut dicari di kolom ini.
- Saran menampilkan **nama + NIS + kelas**, bisa dipilih dengan mouse, `↑`/`↓`, `Enter`, atau `Esc`. Memilih saran mengisi hidden field berisi **NIS pasti**, sehingga yang dikirim ke `api/scan.php` selalu identik dengan data siswa.
- Menekan **Enter tanpa memilih** tetap bekerja: bila pencarian menghasilkan tepat satu siswa, langsung dicatat; bila beberapa, daftar saran dibuka dan muncul pesan "Beberapa siswa cocok"; bila nol, muncul "Siswa tidak ditemukan". Tidak ada lagi tebakan nama yang ambigu.
- Aksesibilitas: `role="combobox"`, `aria-autocomplete="list"`, `aria-expanded`, `role="listbox"`/`option`, dan target sentuh ≥ 44 px.
- *Sejak bagian 19*, logika ini diekstrak menjadi komponen bersama `public/js/cari-siswa.js` + helper `kotak_cari_siswa()` dan dipakai **semua halaman** yang memilih/mencari siswa; halaman scan memakai komponen yang sama dengan opsi `data-validasi="0"` karena mengirim scan lewat AJAX.

### 18.4 QR kartu sekarang berisi username siswa

Sebelumnya QR memuat `siswa.qr_token` (32 karakter acak) — **bukan NIS**, sesuai aturan awal PDF. Sesuai permintaan, isi QR **diganti menjadi `pengguna.username` siswa**:

| Berkas | Perubahan |
| --- | --- |
| `pages/admin/kartu.php` | `data-qr` dari `qr_token` → `username`; petunjuk cetak ulang kartu |
| `pages/siswa/kartu.php` | `data-qr` dari `qr_token` → username siswa (bukan username wali yang membuka kartu) |
| `api/scan.php` | `token` dicocokkan ke `p.username` lewat `JOIN pengguna`; respons ikut mengirim `username` |
| `api/cari_siswa.php` | parameter `token` kini mencocokkan `p.username`, bukan `qr_token` |
| `pages/admin/siswa.php` | aksi/tombol **Reset QR dihapus** (token acak tidak lagi dipakai) |

**Bersifat ketat (sesuai pilihan):** kartu lama **tidak bisa dipindai lagi**. Bila `api/scan.php` menerima token yang ternyata `qr_token` lama, balasannya menjelaskan: *"Kartu QR lama sudah tidak berlaku. Cetak ulang kartu siswa; QR sekarang memuat username siswa."* Jadi **cetak ulang seluruh kartu** lewat Admin → Cetak Kartu setelah menerapkan pembaruan ini. Kolom `siswa.qr_token` sengaja **tidak dihapus** (masih `NOT NULL`, diisi nilai acak saat siswa dibuat) agar instalasi lama tidak perlu `ALTER TABLE`; kolom itu kini hanya data warisan yang tidak dipakai untuk pemindaian.

> Catatan privasi: username siswa memang sudah tercetak di baris **Login** kartu sejak bagian 17, sehingga memindahkannya ke QR tidak menambah data baru ke kartu. Kode sertifikat, NIS pada kartu, dan QR verifikasi sertifikat tetap tidak berubah.

### 18.5 Berkas & database yang perlu diganti

| Berkas | Wajib? | Isi perubahan |
| --- | --- | --- |
| `api/scan.php` | **WAJIB** | resolusi QR by username, pesan kartu lama, JOIN `pengguna` |
| `api/cari_siswa.php` | **WAJIB** | izinkan `guru`, resolusi `token` by username, JOIN `pengguna` |
| `pages/guru/scan.php` | **WAJIB** | saran otomatis, bunyi bip 2 nada + sakelar, penjelasan alur scan |
| `pages/admin/kartu.php`, `pages/siswa/kartu.php` | **WAJIB** | QR berisi username |
| `pages/admin/siswa.php` | **WAJIB** | hapus Reset QR, perbarui teks migrasi |
| `public/css/sidipa.css`, `public/js/sidipa.js` | Disarankan | gaya dropdown saran; `data-lewati-muat` agar form AJAX tidak menonaktifkan tombol 8 detik |
| `pages/admin/impor_siswa.php`, `database.sql`, `README.md` | Disarankan | komentar kolom warisan & dokumentasi |

**Database: TIDAK ADA MIGRASI / `ALTER TABLE`.** Tidak ada tabel, kolom, atau baris `pengaturan` baru. Cukup unggah berkas di atas lalu **cetak ulang kartu siswa**.

### 18.6 Uji coba

Suite `.preview/tmpjs/uji_scan_qr.py` (**61/61**) memeriksa: QR kartu admin & siswa benar-benar memuat username (bukan token), `api/scan.php` menerima username dan menolak token lama, fallback NIS tetap jalan, `api/cari_siswa.php` dapat diakses guru dan mencocokkan NIS maupun nama (serta pembatasan privasi akun siswa), markup saran otomatis + atribut ARIA, sakelar/dua nada/`AudioContext` tunggal pada halaman scan, opt-out form AJAX, serta tidak adanya tombol Reset QR. Regresi keseluruhan saat bagian itu **352/352**; setelah bagian 19 **451/451**.


---

## 19. Username bebas, saran siswa di semua halaman, Rincian angka

### 19.1 Username siswa: karakter bebas

Aturan lama (§17.2) mewajibkan huruf kecil, minimal 3 karakter, dan **wajib ada huruf** supaya username tidak kembali ke NIS. Sesuai permintaan, **semua pembatasan format itu dihapus**:

| Yang boleh | Contoh |
| --- | --- |
| Angka semua | `20260100`, `123` |
| Huruf semua, huruf besar | `ahmadrizky`, `AHMAD` |
| Campuran, spasi, simbol, non-ASCII | `Rina/Wijaya 2026`, `budi@smk`, `Siti Aisyah` |

`username_siswa_bersih()` kini hanya membuang **karakter kontrol** (baris baru/tab/dll.), memangkas spasi di pinggir, dan membatasi **60 karakter** sesuai `pengguna.username VARCHAR(60)`. `username_siswa_sah()` tidak lagi menolak "hanya angka", "huruf besar", atau kurang dari 3 karakter. Perbandingan keunikan memakai `username_siswa_kunci()` (unicode lower-case), dan generator otomatis tetap membuat nilai konservatif dari dua kata pertama nama (`ahmad.rizky`, `ahmad.rizky2`, …) bila kolom dikosongkan.

Penyesuaian ikutan:

- **NIS tetap bukan username otomatis.** NIS tetap identitas siswa, pencarian manual, dan kode sertifikat; admin yang memutuskan bila username dibuat sama dengan NIS.
- `app/auth.php` tidak lagi mengklaim *"NIS/NISN tidak dipakai untuk login lagi"*; pesan untuk username angka yang tidak terdaftar kini netral. `pages/login.php` menjelaskan bahwa username siswa boleh angka maupun huruf sesuai yang dibuat admin.
- `install.php` memeriksa **"Username siswa terisi"** (bukan "bukan NIS").
- **Buatkan username massal** hanya menyentuh akun warisan dengan `p.username = s.nis`, sehingga username angka pilihan admin **tidak tertimpa**.
- `public/js/qr-sidipa.js` memaksa `qrcode.stringToBytesFuncs['UTF-8']`: default pustaka memotong tiap karakter menjadi 1 byte, yang akan merusak QR berisi username bersimbol/non-ASCII. `api/scan.php` tetap mencocokkan `p.username` apa adanya.
- Petunjuk di Data siswa, Pengguna, Impor CSV, kartu siswa, dan `contoh/` diselaraskan.

> **Dampak operasional:** bila username siswa diubah, **QR kartu ikut berubah** — cetak ulang kartu siswa tersebut. Tidak ada perubahan skema database.

### 19.2 Pencarian siswa model Scan Masuk / Sholat Dhuha di semua halaman

Sebelumnya hanya halaman scan yang punya saran otomatis; halaman lain memakai `<select>` berisi seluruh siswa dengan batas `LIMIT 300`/`LIMIT 500` (siswa di luar batas tidak bisa dipilih). Sekarang **satu komponen dipakai bersama**:

- `public/js/cari-siswa.js` — debounce 180 ms, `api/cari_siswa.php`, `↑`/`↓`/`Enter`/`Esc`, ARIA combobox/listbox, penolakan balasan usang, validasi "pilih siswa dari saran", dan event `sidipa:siswa-terpilih`.
- `kotak_cari_siswa()` di `app/layout.php` — markup seragam; dimuat lewat `halaman_bawah()`.
- `public/css/sidipa.css` — gaya dropdown + `.teks-galat` / `.siswa-disorot`.

| Halaman | Perilaku baru |
| --- | --- |
| `pages/guru/scan.php` | Komponen bersama, `data-nilai="nis"`, `data-validasi="0"`; bunyi bip & kamera tidak berubah |
| `pages/guru/catat_poin.php` (**input poin**) | Cari NIS/nama → `siswa_id` pasti; validasi server `guru_boleh_siswa()` tetap |
| `pages/guru/izin.php` | Sama seperti catat poin |
| `pages/guru/penilaian.php` | Memilih siswa langsung membuka nilai bulan itu (`data-kirim="1"`); `$terpilih` diambil per-ID, bukan dari daftar 300 |
| `pages/bk/tindak_lanjut.php` | Saran otomatis menggantikan dropdown 500 baris |
| `pages/guru/catat_banyak.php` | Kolom cari mencentang siswa dari saran; siswa di luar daftar awal dibuatkan checkbox, dan validasi POST kini bertanya ke DB, bukan ke daftar tercetak |
| `pages/admin/siswa.php`, `pages/umum/siswa.php` | Kolom "Cari nama atau NIS" dapat saran; memilih mengisi NIS lalu menyaring tabel (filter `data-saring-tabel` tetap jalan) |

Kata kunci yang dicari tetap **NIS atau nama** (sesuai keputusan bagian 18); username tidak ikut dicari di kolom saran. Pembatasan privasi `api/cari_siswa.php` tetap: akun siswa/wali hanya melihat datanya sendiri, `guru`/`bk`/`kepsek`/`admin` melihat siswa aktif.

### 19.3 "Rincian angka" pada beranda siswa

Dua angka `0,5` muncul bersamaan dengan satuan berbeda dan menimbulkan kebingungan:

| Tulisan | Arti |
| --- | --- |
| `Total poin: 3 dari target 600` | `3` = total poin terkumpul di 6 aspek; `600` = jumlah target 6 aspek (6 × 100). |
| `Rata-rata keseluruhan poin: 0,5 %` | Rata-rata **persentase capaian** tiap aspek: `(3% + 0% + 0% + 0% + 0% + 0%) ÷ 6 = 0,5 %`. **Ini** yang menentukan predikat. |
| `Rata-rata poin per aspek: 0,5` | `3 ÷ 6 = 0,5` **poin mentah** — bukan persen dan bukan predikat. |

Sesuai pilihan, **baris "Rata-rata poin per aspek" dihapus** dari `pages/siswa/beranda.php` agar tidak ada dua angka `0,5` yang rancu. Label angka besar diperbaiki menjadi **"Rata-rata capaian keseluruhan"** karena isinya persentase. Perhitungan di `app/skor.php` **tidak diubah** — `rata_poin` tetap tersedia di array ringkasan untuk keperluan lain.

### 19.4 Berkas & database yang perlu diganti

| Berkas | Wajib? | Isi perubahan |
| --- | --- | --- |
| `app/helpers.php` | **WAJIB** | `username_siswa_bersih()`, `username_siswa_sah()`, `username_siswa_panjang()`, `username_siswa_kunci()`, `username_siswa_unik()` |
| `app/layout.php` | **WAJIB** | `kotak_cari_siswa()` + memuat `public/js/cari-siswa.js` |
| `public/js/cari-siswa.js` **(baru)** | **WAJIB** | komponen saran otomatis bersama |
| `public/js/qr-sidipa.js` | **WAJIB** | encode QR UTF-8 agar username bersimbol terbaca |
| `pages/admin/siswa.php`, `pages/admin/pengguna.php`, `pages/admin/impor_siswa.php` | **WAJIB** | petunjuk & validasi username; migrasi massal hanya `username = nis` |
| `app/auth.php`, `pages/login.php`, `install.php` | **WAJIB** | pesan login netral, petunjuk, preflight "Username siswa terisi" |
| `pages/guru/scan.php`, `pages/guru/catat_poin.php`, `pages/guru/catat_banyak.php`, `pages/guru/izin.php`, `pages/guru/penilaian.php`, `pages/bk/tindak_lanjut.php`, `pages/admin/siswa.php`, `pages/umum/siswa.php` | **WAJIB** | `<select>` / kolom cari diganti `kotak_cari_siswa()`; validasi ke DB |
| `public/css/sidipa.css`, `public/js/sidipa.js`, `pages/siswa/kartu.php`, `pages/siswa/beranda.php` | Disarankan | gaya dropdown, opt-out tombol 8 detik, teks kartu, hapus baris rata-rata poin |
| `contoh/README.md`, `contoh/migrasi_username_siswa.sql`, `README.md` | Disarankan | dokumentasi |

**Database: TIDAK ADA MIGRASI / `ALTER TABLE`.** Tidak ada tabel, kolom, atau baris `pengaturan` baru. Kolom `pengguna.username` sudah `VARCHAR(60)` dan unik sejak awal. Yang perlu dilakukan: unggah berkas di atas, lalu **cetak ulang kartu siswa yang username-nya berubah**.

### 19.5 Uji coba

Suite `.preview/tmpjs/uji_username_cari.py` (**92/92**) memeriksa username angka semua / huruf semua / huruf besar / spasi / simbol lewat form Data siswa, Pengguna, dan impor CSV; login memakai username angka; encode QR UTF-8; markup `kotak_cari_siswa()` pada delapan halaman; isi komponen JS; preflight `install.php`; fungsional pencarian tabel; dan tidak adanya baris "Rata-rata poin per aspek". Regresi lengkap **saat bagian itu 451/451**; setelah bagian 20 **503/503**, dengan 64 berkas PHP tanpa galat parsing dan `node --check` bersih untuk `sidipa.js`, `cari-siswa.js`, `qr-sidipa.js`. Suite lokal ini tidak di-commit (`.preview/` diabaikan Git).

---

## 20. Pengurangan poin tanpa batas + daftar catatan per tanggal

### 20.1 Mengapa pagar harian dihapus

Sejak awal ada pengaturan `batas_pengurangan_harian` (bawaan **10 poin per aspek per siswa per hari**) yang membatasi **hanya** catatan `sumber='manual'`. Sementara katalog bawaan `database.sql` memuat pelanggaran **berat** yang lebih besar dari itu:

| Katalog | Poin | Aspek |
| --- | --- | --- |
| `JUJ_AMBIL` Mengambil barang milik orang lain | −25 | Kejujuran |
| `TIM_BULLYING` Perundungan (bullying) | −25 | Kerja Sama Tim |
| `DIS_KELAHI` Berkelahi | −20 | Kedisiplinan |
| `DIS_ROKOK` Merokok / membawa rokok | −20 | Kedisiplinan |
| `DIS_RUSAK` Merusak fasilitas sekolah | −15 | Kedisiplinan |
| `JUJ_PALSU` Memalsukan tanda tangan | −15 | Kejujuran |
| `JUJ_MENYONTEK`, `SOL_ADU_DOMBA`, `SOL_HAKIM` | −10 | Kejujuran / Penyelesaian Masalah |

Pemeriksaan lama berbentuk `terpakai + |poin| > 10`, sehingga **−25 selalu ditolak walau kuota masih penuh**, dengan pesan yang kontradiktif (*"Sisa hari ini: 10 poin"*). Tiga katalog −10 juga hanya bisa dipakai bila kuota masih utuh. Sesuai permintaan, **pagar ini dihapus sepenuhnya**: tidak ada lagi batas jumlah/besar pengurangan, dan pelanggaran berat dapat dicatat apa adanya. Validasi lain tetap berjalan (katalog aktif & manual, poin bulat −100…100, deskripsi wajib untuk tingkat sedang/berat, CSRF, transaksi all-or-nothing).

### 20.2 Pengganti pagar: daftar catatan per tanggal

Pengaman terhadap **pencatatan ganda** bukan lagi angka, melainkan informasi yang muncul **tepat saat guru memilih siswa dan tanggal**:

- Panel **"Catatan siswa pada tanggal ini"** (`kartu_riwayat_harian()` di `app/layout.php`) ditambahkan di bawah form **Catat poin** dan **Catat banyak siswa**.
- `public/js/riwayat-harian.js` memuat ulang daftar secara otomatis saat: siswa dipilih dari saran, tanggal diubah, checkbox siswa berubah (termasuk **Pilih semua** / siswa dari saran), dan kejadian katalog diganti.
- Sumber datanya `api/riwayat_harian.php` → `riwayat_harian_siswa()` di `app/skor.php`: **seluruh** `catatan_poin` untuk siswa & tanggal itu — manual guru mana pun, otomatis (scan/cron), penilaian bulanan, dan catatan yang sudah **dibatalkan** (tetap ditampilkan agar tidak dicatat ulang).
- Kolom: jam, kejadian, aspek, poin, sumber, status; pada mode **Catat banyak** ditambah kolom **Siswa**. Ringkasan *"N catatan · total pengurangan aktif X poin"* bersifat **informasi, bukan batas**.
- Bila kejadian yang sedang dipilih di dropdown katalog **sudah tercatat** untuk siswa itu pada tanggal itu, barisnya **disorot** dan muncul peringatan *"kejadian yang sama sudah tercatat hari ini…"*. Peringatan ini **tidak memblokir** — guru tetap memutuskan.
- Siswa & tanggal terakhir disimpan di `sessionStorage`, sehingga saat guru kembali dari halaman Riwayat setelah menyimpan, entri yang baru saja dicatat langsung terlihat.

### 20.3 Privasi & keamanan

- Endpoint mewajibkan sesi, **CSRF**, metode GET, tanggal valid (`YYYY-MM-DD`), dan membatasi maksimal **60 ID siswa** per permintaan.
- Akun **siswa/wali** hanya bisa membaca `siswa_id` yang terhubung dengan akunnya; `admin`/`bk`/`kepsek`/`guru` boleh membaca siswa aktif. Peran lain ditolak 403.
- **Tidak pernah** mengirim `pencatat_id`, nama pencatat, `keterangan` bebas, bukti foto, atau nomor WA. Identitas pencatat tetap hanya bisa dilihat admin lewat `lihat_pencatat()`. Panel juga menampilkan catatan guru lain **tanpa** menyebut siapa yang menulis — cukup untuk tahu bahwa pelanggaran sudah tercatat.
- Baris `pengaturan` lama `batas_pengurangan_harian` sengaja **dibiarkan** di `database.sql` sebagai data warisan (ditandai komentar) dan **dihapus dari form Pengaturan** supaya admin tidak melihat kontrol yang sudah tidak berfungsi.

### 20.4 Berkas & database yang perlu diganti

| Berkas | Wajib? | Isi perubahan |
| --- | --- | --- |
| `app/skor.php` | **WAJIB** | `pengurangan_manual_harian()` → `riwayat_harian_siswa()` |
| `api/riwayat_harian.php` **(baru)** | **WAJIB** | endpoint JSON daftar catatan per siswa + tanggal |
| `public/js/riwayat-harian.js` **(baru)** | **WAJIB** | panel yang memperbarui diri + peringatan kejadian ganda |
| `pages/guru/catat_poin.php`, `pages/guru/catat_banyak.php` | **WAJIB** | hapus cabang penolakan pagar; pasang panel; perbarui teks bantuan |
| `app/layout.php` | **WAJIB** | `kartu_riwayat_harian()` + memuat `riwayat-harian.js` |
| `public/css/sidipa.css` | Disarankan | gaya panel, sorotan baris ganda, `[hidden]` |
| `pages/admin/pengaturan.php`, `database.sql`, `README.md` | Disarankan | hapus kontrol usang, tandai baris warisan, dokumentasi |

**Database: TIDAK ADA MIGRASI / `ALTER TABLE`.** Tidak ada tabel/kolom baru; `catatan_poin` sudah punya `tanggal`, `sumber`, `status`, `katalog_id`, dan indeks `idx_catatan_tanggal`. Instalasi lama cukup mengunggah berkas di atas — baris `pengaturan` lama boleh tetap ada (tidak berpengaruh).

### 20.5 Uji coba

Suite `.preview/tmpjs/uji_riwayat_harian.py` (**51/51**) memeriksa hilangnya pagar di sumber & halaman, markup panel pada kedua menu, isi komponen JS, **penyimpanan pelanggaran −25 yang kini berhasil**, pengelompokan beberapa siswa, ikut tampilnya catatan otomatis dan catatan dibatalkan, penolakan tanpa CSRF (403) / tanggal invalid (422), pembatasan privasi akun siswa, serta tidak adanya identitas pencatat dalam respons. Uji lama `uji_tambahan.py` diperbarui menjadi **18/18** (batch −20 kini tersimpan, bukan ditolak). **Regresi lengkap 503/503** = 126 + 13 + 18 + 55 + 35 + 52 + 61 + 92 + 51, dengan **64 berkas PHP tanpa galat parsing** dan `node --check` bersih untuk empat skrip JS aplikasi. Suite lokal tidak di-commit (`.preview/` diabaikan Git).

---

## 21. Sertifikat siswa menjadi 2 halaman

### 21.1 Isi kedua halaman

**Halaman 1 — surat pernyataan (A4 landscape, tetap berbingkai sertifikat)**

Kop sekolah → penanda *Halaman 1 dari 2 · Surat Pernyataan* → judul **Raport Penilaian Karakter Siswa** → kalimat:

> **Raport Penilaian Karakter Siswa** ini diberikan kepada:
> **NAMA SISWA** (ditengah, huruf besar tebal)
> NIS …, kelas …, pada Semester … tahun ajaran …, dengan **nilai rata-rata karakter 80,0%** dan predikat **B** dari 6 aspek penilaian karakter.
> Keterangan predikat: Baik.
> Deskripsi penilaian per aspek sebagaimana tercantum pada **halaman 2** yang merupakan bagian tidak terpisahkan dari sertifikat ini.

Di bawahnya tetap ada **QR verifikasi publik + kode sertifikat**, tanda tangan **wali kelas** dan **kepala sekolah**, serta tanggal terbit.

**Halaman 2 — deskripsi penilaian (A4 landscape)**

Kop sekolah → penanda *Halaman 2 dari 2 · Deskripsi Penilaian* → judul **Deskripsi Penilaian Karakter** → identitas siswa (nama, NIS, kelas, semester) → tabel **6 aspek** dengan kolom aspek, skor/target, persentase, predikat, dan **Keterangan / deskripsi** → ringkasan nilai rata-rata karakter + predikat keseluruhan + penjelasan bahwa predikat tabel dihitung per aspek → footer berisi **kode sertifikat** dan penanda halaman, supaya lembar ke-2 tetap dikenali bila terpisah. QR dan tanda tangan **cukup di halaman 1**.

### 21.2 Nilai rata-rata karakter

Sumbernya `ringkasan_skor_siswa()` di `app/skor.php`: **rata-rata persentase dari 6 aspek** (bobot sama, sesuai keputusan bagian 16), dibulatkan 1 desimal dengan pemisah koma, lalu dipasangkan dengan **predikat keseluruhan** dari 8 tingkat `A+ … D` dan keterangannya. Predikat **per aspek** tetap dipakai pada tabel halaman 2.

`snapshot_sertifikat()` kini membekukan agregat itu ke kunci **`ringkasan`** dalam `data_json` (`versi => 2`), sehingga nilai yang tercetak tidak berubah bila ambang predikat atau catatan poin diubah setelah penerbitan — prinsip snapshot permanen tetap dipertahankan. **Sertifikat lama (versi 1, tanpa `ringkasan`) tetap bisa dicetak**: `cetak_sertifikat.php` menghitung ulang agregat dari persentase per aspek yang sudah tersimpan di snapshot lama. Tidak ada perubahan data sertifikat yang sudah terbit.

### 21.3 Cetak & keamanan

- `public/css/cetak.css`: `@page { size: A4 landscape; margin: 10mm }`; saat cetak tiap `<article>` berukuran tetap `184mm`, `break-inside: avoid`, dan `.halaman-2` memakai `break-before: page`, sehingga hasilnya **tepat 2 halaman**. Di layar kedua lembar tampil bertumpuk.
- Petunjuk cetak diperbarui menjadi *"2 halaman A4 landscape, skala 100%, matikan header/footer browser"*; label tombol menjadi **"Cetak / Simpan PDF (2 halaman)"**.
- **Aturan lama tidak berubah:** hanya status `terbit` yang boleh dicetak; siswa/wali **hanya bisa melihat** sampai admin mengaktifkan semester baru (termasuk akses URL langsung dan Ctrl+P — `body.cetak-terkunci .kertas-sertifikat` menyembunyikan **kedua** halaman); admin/BK/kepsek dapat mencetak kapan saja. Tidak ada identitas pencatat, nomor WA, bukti, maupun keterangan bebas yang ikut tercetak. `verifikasi.php` publik tetap hanya membaca kunci `aspek`.

### 21.4 Berkas & database yang perlu diganti

| Berkas | Wajib? | Isi perubahan |
| --- | --- | --- |
| `pages/umum/cetak_sertifikat.php` | **WAJIB** | dua `<article>` (pernyataan + deskripsi), fallback `ringkasan`, petunjuk & label tombol |
| `app/sertifikat.php` | **WAJIB** | `snapshot_sertifikat()` menyimpan `ringkasan` dan menaikkan `versi` ke 2 |
| `public/css/cetak.css` | **WAJIB** | gaya halaman 1/2, ukuran lembar tetap, `break-before: page`, penahanan cetak untuk dua halaman |
| `pages/siswa/sertifikat.php`, `pages/admin/sertifikat.php`, `pages/wali/beranda.php` | Disarankan | petunjuk bahwa sertifikat terdiri dari 2 halaman |
| `README.md` | Disarankan | dokumentasi bagian 21 |

**Database: TIDAK ADA MIGRASI / `ALTER TABLE`.** Kolom `sertifikat.data_json` sudah `LONGTEXT`; cukup ganti berkas. Sertifikat yang sudah terbit **tidak perlu diterbitkan ulang** — fallback versi 1 menanganinya. Hanya sertifikat yang diterbitkan **setelah** pembaruan ini yang menyimpan `ringkasan` (versi 2).

### 21.5 Uji coba

Suite `.preview/tmpjs/uji_sertifikat_2hal.py` (**81/81**) memeriksa markup & CSS dua halaman, kalimat pernyataan berikut nama/nilai rata-rata/predikat, enam baris aspek pada halaman 2, QR dan dua tanda tangan hanya di halaman 1, fallback sertifikat versi 1 (80,0% / B), penahanan cetak siswa & wali, pembukaan cetak setelah semester aktif berganti, sertifikat dibatalkan tanpa kertas tercetak, verifikasi publik, halaman daftar sertifikat untuk tiga peran, dan `snapshot_sertifikat()` versi 2 yang dijalankan terhadap database nyata. **64 berkas PHP tanpa galat parsing**. Suite ini berada di `.preview/` yang diabaikan Git. Regresi 503/503 pada bagian 20 berasal dari lingkungan uji lama; lingkungan preview dibangun ulang pada bagian 21 sehingga regresi itu tidak diulang, namun halaman-halaman yang terdampak diverifikasi langsung lewat suite di atas.

---

## 22. Level penanganan siswa bermasalah (Guru BK)

### 22.1 Latar & keputusan

Sebelumnya BK sudah punya **Dashboard BK** (daftar *Siswa perlu pembinaan* berdasar **persentase aspek < 60%**) dan **Tindak Lanjut** (`pemicu`, `status baru/proses/selesai`, `tindak_lanjut`). Yang belum ada adalah **tingkatan/level kasus** dan **satu tempat untuk melihat siswa bermasalah beserta seluruh penanganannya**.

Sesuai pilihan pada sesi ini:

- **Level penanganan L1–L4** (bukan peran baru, bukan sekadar halaman kosong).
- Sumber *"siswa bermasalah"* = **riwayat pelanggaran** pada semester berjalan: akumulasi poin negatif aktif + pelanggaran tingkat sedang/berat. **Bukan** ambang skor aspek 60% (aturan lama tetap dipertahankan di menu *Siswa Pembinaan*), bukan peringkat skor terendah.
- Penanganan mencakup **status + catatan**, **tahap bertingkat + penanggung jawab**, **kenaikan/penurunan level berjejak**, dan **halaman detail per siswa**.

### 22.2 Aturan level

| Level | Nama | Penanggung jawab bawaan | Tahap bawaan | Masuk otomatis bila |
| --- | --- | --- | --- | --- |
| **L1** | Pembinaan awal | Wali kelas | Teguran dan bimbingan ringan | ada poin negatif (1–9) |
| **L2** | Konseling BK | Guru BK | Konseling individual + kontrak perilaku ringan | ≥10 poin negatif **atau** ada pelanggaran tingkat `sedang` |
| **L3** | Pendampingan intensif | Guru BK + wali murid | Panggilan wali murid + pemantauan mingguan | ≥25 poin negatif **atau** ≥1 pelanggaran tingkat `berat` |
| **L4** | Penanganan pimpinan | Kepala sekolah | Pembinaan pimpinan sesuai tata tertib | ≥50 poin negatif **atau** ≥2 pelanggaran `berat` |

Yang dihitung hanya `catatan_poin` **berpoin negatif dan berstatus `aktif`** pada semester berjalan (semua sumber: manual, sistem/scan, berkala). Catatan **dibatalkan tidak menaikkan level**, tetapi tetap ditampilkan di halaman detail agar tidak dicatat ulang. Pelanggaran berat bawaan −15/−20/−25 (`JUJ_AMBIL`, `TIM_BULLYING`, `DIS_KELAHI`, dll.) otomatis menaikkan ke L3; dua pelanggaran berat ke L4.

**Level efektif** = penetapan terakhir BK bila ada, selain itu **level otomatis**. BK bebas menaikkan **atau menurunkan** level lewat form; halaman menandai bila level efektif berbeda dari rekomendasi otomatis.

### 22.3 Tampilan & alur

- Menu samping BK bertambah **Siswa Bermasalah**; Dashboard BK mendapat 2 kartu angka (*Siswa bermasalah*, *Level L3–L4*), tautan cepat, dan tabel 10 kasus teratas.
- **`pages/bk/penanganan.php`**: 5 kartu ringkasan, penjelasan aturan level, form **Tetapkan / ubah level** (siswa, L1–L4, status, tanggal, tahap, penanggung jawab, catatan), filter level + pencarian nama/NIS/kelas, tabel daftar (poin negatif, jumlah pelanggaran berat, **level otomatis**, **level penanganan**, status, penanggung jawab, tautan Detail), dan **Riwayat perubahan penanganan** 15 entri terakhir.
- **`pages/bk/penanganan_detail.php`**: identitas siswa, 4 kartu angka, penanganan berjalan (tahap, penanggung jawab, catatan, alasan level otomatis), **ringkasan poin per aspek**, **seluruh riwayat pelanggaran** (tanggal, jam, kejadian, aspek, poin, tingkat, sumber, status), **riwayat level penanganan** (jejak naik/turun), dan **catatan tindak lanjut BK** dari tabel `pembinaan`.
- Setiap penyimpanan form menambah **satu baris baru** di `penanganan_siswa` — bukan menimpa — sehingga riwayat perubahan level selalu utuh. Status memakai enum yang sama dengan Tindak Lanjut (`baru/proses/selesai`).

### 22.4 Privasi & keamanan

- Kedua halaman memakai `auth_wajib(['bk','admin'])`; semua POST wajib **CSRF**, siswa harus aktif, level 1–4, status valid, tanggal `YYYY-MM-DD` valid.
- Helper di `app/penanganan.php` **tidak pernah** memilih `pencatat_id`, nama pencatat, `keterangan` bebas, `bukti_path`, atau nomor WA. Kolom `dicatat_oleh` hanya disimpan untuk jejak audit internal dan tidak ditampilkan ke BK. Identitas pencatat tetap hanya bisa dibaca admin lewat `lihat_pencatat()`.
- Instalasi lama yang belum menjalankan migrasi **tidak rusak**: `penanganan_tabel_ada()` menangani tabel yang belum ada, daftar siswa tetap tampil, dan muncul pesan berisi nama berkas migrasi. `install.php` menambah pemeriksaan tabel ini.

### 22.5 Berkas & database yang perlu diganti

| Berkas | Wajib? | Isi perubahan |
| --- | --- | --- |
| `app/penanganan.php` **(baru)**, `app/bootstrap.php` | **WAJIB** | helper level L1–L4 + pemuatan helper |
| `pages/bk/penanganan.php` **(baru)**, `pages/bk/penanganan_detail.php` **(baru)** | **WAJIB** | daftar, penetapan level, riwayat, detail per siswa |
| `database.sql`, **`contoh/migrasi_penanganan_bk.sql` (baru)** | **WAJIB untuk instalasi lama** | tabel `penanganan_siswa` |
| `app/layout.php`, `pages/bk/beranda.php`, `install.php` | Disarankan | menu BK, kartu/tabel dashboard, pemeriksaan install |
| `contoh/README.md`, `README.md` | Disarankan | dokumentasi |

> **Kali ini ADA perubahan database** (berbeda dengan bagian 19–21): satu tabel baru.
>
> - **Instalasi baru**: cukup impor `database.sql` terbaru — tabel sudah ada.
> - **Instalasi lama**: jalankan **`contoh/migrasi_penanganan_bk.sql`** satu kali lewat phpMyAdmin. Isinya `CREATE TABLE IF NOT EXISTS penanganan_siswa` — **tidak ada `ALTER TABLE`**, tidak mengubah tabel/data lama, aman dijalankan ulang (`IF NOT EXISTS`). Tanpa itu halaman tetap bisa dibuka tetapi penetapan level belum bisa disimpan.
>
> Kolom tabel: `id, siswa_id, semester_id, level, status, tahap, penanggung_jawab, catatan, dicatat_oleh, tanggal, dibuat_pada` + 3 indeks dan 3 kunci asing.

### 22.6 Uji coba

Suite `.preview/tmpjs/uji_penanganan_bk.py` (**314/314**) memeriksa matriks 12 kombinasi level otomatis (0, 1–9, ≥10, ≥25, ≥50 poin dan pelanggaran sedang/berat), daftar siswa bermasalah yang mengabaikan poin positif serta catatan dibatalkan, penetapan level manual **turun L4→L2 lalu naik L2→L4** berikut jejak riwayatnya (3 baris tidak saling menimpa), filter level/NIS/nama, halaman detail per siswa (riwayat pelanggaran termasuk yang dibatalkan, poin per aspek, tindak lanjut BK lama), 404 siswa tak ditemukan, penolakan akses siswa/guru/wali (403) dan tamu, validasi tanpa CSRF / level 0 & 9 / status asing / tanggal invalid / siswa asing, log audit, serta tidak adanya identitas pencatat, keterangan bebas, bukti, atau nomor WA dalam HTML. Bagian terakhir mensimulasikan **instalasi lama yang belum dimigrasi** dengan menghapus sementara tabel `penanganan_siswa`: halaman tetap 200, peringatan dan nama berkas migrasi tampil, POST ditolak dengan pesan yang jelas, lalu tabel dipulihkan. **67 berkas PHP tanpa galat parsing** dan seluruh skrip JS di `public/js/` lolos `node --check`. Suite berada di `.preview/` yang diabaikan Git. Suite regresi **bagian 21** (`uji_sertifikat_2hal.py`, **81/81**) dijalankan ulang setelah seluruh perubahan bagian 22 dan tetap lulus, sehingga sertifikat 2 halaman tidak terdampak. Regresi **503/503** bagian 20 berasal dari lingkungan uji lama dan tidak diulang di preview yang dibangun ulang ini.

