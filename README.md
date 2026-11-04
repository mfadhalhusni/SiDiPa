# SiDiPa — Siswa Digital Pasport

Sistem penilaian karakter siswa berbasis poin, kehadiran barcode (QR), dan sertifikat digital untuk satu SMK. Dibuat untuk **shared hosting cPanel**: PHP 8.1+ tanpa framework, MySQL/MariaDB lewat PDO, HTML/CSS/JavaScript vanilla tanpa build step, tanpa Composer. Seluruh antarmuka berbahasa Indonesia dan bergaya **neo brutalism** buatan sendiri.

> Dokumen ini mengikuti instruksi `Prompt_Generate_Website_SiDiPa.pdf`. Pembangunan dilakukan bertahap sesuai **bagian 15** PDF. Status saat ini: **Tahap 1, 2, 3, 4, dan 5 selesai**, ditambah **PWA penuh**, pustaka QR lokal untuk cetak/scan tanpa CDN, serta pembaruan: guru menilai seluruh siswa tanpa penugasan kelas, login cukup sekali (tersimpan 30 hari), dan semua laporan diunduh sebagai **XLSX** (lihat bagian 15). Semua kartu beranda guru sudah menuju halaman yang benar-benar berfungsi.

---

## 1. Yang sudah tersedia

| Bagian | Isi |
| --- | --- |
| Struktur | `index.php`, `config.php`, `database.sql`, `install.php`, `verifikasi.php`, `app/`, `pages/`, `api/`, `cron/`, `public/`, `contoh/` |
| Database | 22 tabel InnoDB utf8mb4 + kunci asing + indeks (termasuk `ingat_login`); 6 aspek, 36 katalog poin bawaan, 2 jenis ibadah, 52 pengaturan, 1 semester contoh, akun admin |
| Login & peran | `password_hash`/`password_verify`, regenerasi ID sesi, cookie `HttpOnly` + `SameSite=Lax` + `secure` otomatis, **login sekali, 30 hari sejak aktivitas terakhir** (cookie `SIDIPAINGAT`, hanya hash token di DB, rotasi tiap pakai, batas bergulir otomatis saat aplikasi dibuka, dicabut saat logout/ganti sandi), opsi **Jangan simpan login di perangkat ini**, pembatasan 5 percobaan gagal / 15 menit, wajib ganti kata sandi saat login pertama, pemeriksaan peran di server |
| Data master | CRUD siswa + akun siswa otomatis, CRUD kelas + wali kelas + guru pengampu, CRUD pengguna, CRUD katalog poin, impor CSV dengan pratinjau |
| Pengaturan | Semua jam, bobot poin otomatis, target aspek, ambang predikat/pembinaan, poin penilaian bulanan, identitas sekolah, tanda tangan, dan konfigurasi WA lewat satu halaman admin |
| Skor | `skor_per_aspek()` mulai dari 0 per semester, tanpa batas atas, boleh minus; predikat **per aspek** (A+/A/B/C/D/E) terhadap target aspek |
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
| **Catat banyak siswa (§10)** | `pages/guru/catat_banyak.php`: satu kejadian + satu tanggal + satu deskripsi untuk banyak siswa sekaligus, dalam **satu transaksi**. Bila satu siswa melampaui cap harian, seluruh batch dibatalkan |
| **Grafik mingguan siswa (§10)** | `grafik_mingguan_siswa()` di `app/laporan.php` + SVG inline pada `pages/siswa/beranda.php`: akumulasi poin 8 minggu terakhir, tanpa pustaka grafik eksternal |
| **Tahap 3 — Kartu QR** | `pages/admin/kartu.php` dan `pages/siswa/kartu.php`: kartu **85,6 × 54 mm** memuat nama, NIS, kelas, dan QR berisi `qr_token` (bukan NIS). Cetak per kelas atau per siswa lewat `window.print()`. Token dapat dibuat ulang di Data siswa |
| **Tahap 3 — Hari libur** | `pages/admin/hari_libur.php`: tambah satu tanggal atau rentang (maks. 93 hari), ubah keterangan, hapus. `cron/harian.php`, `cron/mingguan.php`, dan `api/scan.php` melewati tanggal ini |
| **Tahap 3 — Rekap kehadiran** | `app/laporan.php` + `pages/umum/rekap_kehadiran.php`: harian, bulanan, dan semester per siswa **dan** per kelas (hadir, hadir awal, terlambat, izin, sakit, alpa, % kehadiran) dengan **unduh XLSX** 3 lembar (Rekap Siswa, Ringkasan Kelas, Keterangan). Hak akses di server: siswa/wali hanya anaknya, guru menjangkau seluruh siswa aktif |
| **Tahap 4 — Semester** | `pages/admin/sertifikat.php`: tambah semester, aktifkan (skor kembali 0), **tutup periode input**, buka kembali. `cek_input_semester()` membekukan catat poin, scan, izin, dan penilaian bulanan saat periode tertutup |
| **Tahap 4 — Sertifikat** | Penerbitan per kelas/semua, kode **`SDP-TAHUNSEM-NIS-ACAK4`**, snapshot `data_json` yang tidak berubah, antrean WA, pembatalan dengan alasan + pelaku + waktu, `pages/umum/cetak_sertifikat.php` (**A4 landscape**, tabel 6 aspek, logo, tanda tangan, QR verifikasi). Tombol cetak siswa hanya aktif saat status `terbit`. `verifikasi.php` kini menyediakan pembatalan khusus admin |
| **Tahap 4 — BK & kepsek** | `pages/bk/beranda.php`, `pages/kepsek/beranda.php` (**hanya baca**), `pages/bk/tindak_lanjut.php` (tindak lanjut + poin positif opsional), `pages/umum/laporan.php` (ringkasan per kelas/per aspek + unduh XLSX 3 lembar) |
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
11. **Alur akhir semester**: *Semester & Sertifikat* → **Tutup** periode input → periksa ringkasan → **Terbitkan sertifikat** (per kelas atau semua) → siswa mencetak lewat *Sertifikat Saya*. Kode sertifikat dapat diverifikasi publik tanpa login.

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
nis,nama,jenis_kelamin,kelas,nama_wali,no_wa_wali
20260100,Ahmad Rizky Prasetyo,L,X TKJ 1,Bapak Hidayat,628123457000
```

- Kolom **wajib**: `nis`, `nama`, `kelas`. Opsional: `jenis_kelamin` (L/P), `nama_wali`, `no_wa_wali`.
- Pemisah `,` atau `;` dan BOM dideteksi otomatis; variasi nama kolom seperti `nama_lengkap`, `rombel`, `hp_wali` diterima.
- Sistem menampilkan **pratinjau** lebih dulu: baris dengan NIS ganda, NIS kosong, atau NIS yang sudah terdaftar ditandai dan dilewati.
- Kelas yang belum ada dibuat otomatis (tingkat dari kata pertama, jurusan dari kata kedua).
- Setiap siswa mendapat akun dengan `username = NIS`, kata sandi sementara **`Siswa@2026`**, dan wajib ganti kata sandi saat login pertama. Nomor wali dinormalisasi ke format `62`.
- Impor berjalan dalam satu transaksi: bila ada galat, seluruh perubahan dibatalkan.

Paket data contoh: guru `guru.tkj1` / `guru.tkj2` / `guru.tkj3` dengan kata sandi `Guru@2026`, siswa dengan `Siswa@2026`.

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
├── public/css/cetak.css      # @page A4 landscape
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
│   ├── wa.php                # WaClient adapter OpenWA + antrean
│   ├── ikon.php              # Pustaka SVG inline (tanpa emoji)
│   └── layout.php            # Header, sidebar, navigasi bawah, flash
├── pages/
│   ├── admin/                # dashboard, siswa, kelas, kartu, hari_libur, sertifikat, pengguna, katalog, impor, cron, WA, log
│   ├── guru/                 # beranda, catat_poin, catat_banyak, riwayat, scan, pembinaan, izin, penilaian, banding
│   ├── siswa/                # beranda, riwayat, banding, kartu, sertifikat
│   ├── bk/                   # beranda, tindak_lanjut
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
- Predikat per aspek: `>100` → **A+**, `90–100` → **A**, `75–89` → **B**, `60–74` → **C**, `0–59` → **D**, `<0` → **E**. Semua ambang ada di tabel `pengaturan`. **Tidak ada predikat keseluruhan.**
- Sumber otomatis (`sumber = sistem`): terlambat −2, alpa −5, tidak sholat −3 per sholat; bonus pagi +1 setiap 5 hari awal (maks +10/semester) dari cron mingguan; minggu bersih +2, minggu penuh dhuha +2, minggu penuh dhuhur +2. Besar poin diambil dari **Pengaturan → Bobot poin otomatis**; katalog poin dipakai untuk identitas kejadian dan untuk catatan manual.
- Pembatas pengurangan manual per aspek per hari (bawaan 10) tersedia lewat `pengurangan_manual_harian()`. Izin, sakit, dan udzur tidak pernah mengurangi poin.
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
- [x] Kartu siswa 85,6 × 54 mm berisi `qr_token`, cetak per kelas dan per siswa — `pages/admin/kartu.php`, `pages/siswa/kartu.php`
- [x] Hari libur dikelola admin dan dilewati cron/scan — `pages/admin/hari_libur.php`
- [x] Rekap kehadiran harian/bulanan/semester per siswa dan per kelas + unduh XLSX — `app/laporan.php`, `app/xlsx.php`
- [x] Admin menutup periode input; catat poin, scan, izin, dan penilaian bulanan dibekukan — `cek_input_semester()`
- [x] Sertifikat hanya dapat dicetak setelah `terbit`; kode `SDP-...`, snapshot JSON, QR verifikasi, tanda tangan, A4 landscape — `pages/umum/cetak_sertifikat.php`
- [x] Admin membatalkan sertifikat (dari menu admin maupun verifikasi publik) dengan alasan, pelaku, dan waktu; verifikasi publik berubah menjadi "dibatalkan"
- [x] Dashboard BK & kepala sekolah (kepsek hanya baca), laporan per kelas/aspek + XLSX, tindak lanjut dengan poin positif opsional
- [x] Portal wali murid hanya menampilkan ringkasan anaknya; pencatat dan riwayat pelanggaran tidak pernah terkirim
- [x] QR dibuat dan dipindai tanpa CDN, tersedia offline lewat service worker — `public/js/qr*.js`
- [x] Mode **catat banyak siswa sekaligus untuk satu kejadian** (§10) dengan cap harian per siswa dan transaksi all-or-nothing — `pages/guru/catat_banyak.php`
- [x] Dashboard siswa memuat **grafik perkembangan mingguan** SVG inline 8 minggu (§10), hanya angka agregat tanpa nama pencatat
- [x] Uji runtime menyeluruh: **126/126** halaman/alur Tahap 3–5 + **13/13** uji lanjutan + **17/17** uji tambahan + **55/55** uji pembaruan (termasuk sliding 30 hari) = **211/211** lulus pada preview PHP 8.3 + MySQL 5.7

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
| Cetak sertifikat | Siswa: **Sertifikat saya → Cetak** (A4 landscape). Admin/BK/kepsek: tautan **Cetak** pada daftar sertifikat. Wali murid: tautan cetak pada beranda portal |
| Verifikasi eksternal | Pindai QR di sertifikat atau buka `verifikasi.php?kode=...`; tanpa login, hanya status + identitas + skor per aspek |
| Sertifikat salah | Admin membatalkan lewat daftar sertifikat **atau** halaman verifikasi publik, wajib alasan. Status publik berubah jadi *dibatalkan* dan tombol cetak nonaktif |
| Semester baru | **Tambah semester** → **Aktifkan**. Skor kembali 0 karena semua perhitungan memakai `semester_id`; data lama tetap tersimpan |
| Pendampingan BK | **BK → Tindak Lanjut**: pilih siswa, isi pemicu/tindakan/status; centang poin positif bila ada kegiatan perbaikan |
| Wali murid | Buat akun peran **Wali murid** di *Pengguna*, tautkan di *Data siswa*. Portal hanya menampilkan ringkasan anaknya |

**Uji coba yang sudah dijalankan** pada preview PHP 8.3.33 + MySQL 5.7.29: **63 file PHP tanpa galat parsing**, seluruh halaman 200 untuk enam peran, ekspor XLSX (divalidasi `openpyxl`: 3 lembar, 20 siswa, sel numerik), tutup/buka semester, terbitkan/batalkan sertifikat, cetak A4, QR verifikasi, idempotensi cron dua kali, mode catat banyak (termasuk penolakan cap harian), serta **211 pemeriksaan fungsional lulus (126 + 13 + 17 + 55)**.

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
