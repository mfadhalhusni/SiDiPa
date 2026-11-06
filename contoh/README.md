# Paket data contoh SiDiPa (opsional)

Berkas di folder ini **hanya untuk uji coba**. Semua nama, NIS, dan nomor WhatsApp adalah data fiktif.

| Berkas | Isi |
| --- | --- |
| `siswa_contoh.csv` | 20 baris siswa dengan header `nis,username,nama,jenis_kelamin,kelas,nama_wali,no_wa_wali`; dipakai untuk mencoba menu **Admin → Impor CSV**. Kolom `username` opsional: bila kosong dibuat otomatis dari nama |
| `data_contoh.sql` | 3 kelas (`X TKJ 1`, `XI TKJ 1`, `XII TKJ 1`), 3 akun guru sebagai wali kelas, 20 akun siswa + data siswa, dan pemetaan `guru_kelas` |
| `migrasi_username_siswa.sql` | `UPDATE` 20 akun siswa contoh dari username NIS menjadi username dari nama. Untuk data asli, pakai tombol **Buatkan username massal** di menu Data siswa |

## Cara pakai

1. Impor `database.sql` terlebih dahulu.
2. Impor `data_contoh.sql` lewat phpMyAdmin **hanya** bila ingin data uji coba. `INSERT IGNORE` dipakai sehingga aman bila dijalankan ulang.
3. Atau impor `siswa_contoh.csv` lewat menu **Admin → Impor CSV** untuk mencoba alur pratinjau → simpan.

## Akun contoh

| Peran | Username | Kata sandi sementara |
| --- | --- | --- |
| Guru / wali kelas | `guru.tkj1`, `guru.tkj2`, `guru.tkj3` | `Guru@2026` |
| Siswa | sesuai nama, mis. `ahmad.rizky` (NIS `20260100`) | `Siswa@2026` |

Semua akun contoh bertanda `wajib_ganti_password = 1`, jadi kata sandi harus diganti pada login pertama.
Siswa masuk memakai **username siswa** (bukan NIS otomatis). Username boleh angka semua, huruf semua, maupun simbol sesuai yang dibuat admin (bagian 19 README); paket contoh ini memakai username dari nama. **QR kartu berisi username siswa**, NIS tetap tercetak di kartu dan dipakai untuk kode sertifikat serta pencarian manual. Daftar lengkap 20 username ada di `migrasi_username_siswa.sql`.

> Jangan mengimpor paket ini ke instalasi produksi yang sudah berisi data siswa asli.
