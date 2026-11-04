-- ==========================================================================
-- SiDiPa - MIGRASI USERNAME SISWA (pembaruan bagian 17)
-- --------------------------------------------------------------------------
-- Untuk paket data contoh (20 siswa fiktif). Aman dijalankan berulang:
-- setiap UPDATE hanya berjalan bila username masih berupa NIS lama.
--
-- Untuk DATA ASLI (jumlah/nama siswa berbeda), JANGAN memakai daftar ini.
-- Pakai menu Admin -> Data siswa -> "Buatkan username massal". Tombol itu
-- membaca seluruh akun siswa yang username-nya masih angka, membuat username
-- dari nama, menangani bentrok nama, dan menampilkan daftar hasilnya.
--
-- Kata sandi TIDAK berubah. Setelah migrasi, NIS/NISN tidak bisa dipakai
-- untuk login. NIS tetap dipakai untuk kartu QR dan kode sertifikat.
-- ==========================================================================
SET NAMES utf8mb4;

UPDATE pengguna SET username = 'ahmad.rizky'       WHERE id = 5  AND username = '20260100';
UPDATE pengguna SET username = 'alya.putri'        WHERE id = 6  AND username = '20260101';
UPDATE pengguna SET username = 'bagas.pratama'     WHERE id = 7  AND username = '20260102';
UPDATE pengguna SET username = 'dinda.maharani'    WHERE id = 8  AND username = '20260103';
UPDATE pengguna SET username = 'dimas.aditya'      WHERE id = 9  AND username = '20260104';
UPDATE pengguna SET username = 'nabila.aulia'      WHERE id = 10 AND username = '20260105';
UPDATE pengguna SET username = 'fajar.nugroho'     WHERE id = 11 AND username = '20260106';
UPDATE pengguna SET username = 'salsabila.rahma'   WHERE id = 12 AND username = '20260107';
UPDATE pengguna SET username = 'ilham.maulana'     WHERE id = 13 AND username = '20260108';
UPDATE pengguna SET username = 'citra.amelia'      WHERE id = 14 AND username = '20260109';
UPDATE pengguna SET username = 'kevin.saputra'     WHERE id = 15 AND username = '20260110';
UPDATE pengguna SET username = 'nadia.kusuma'      WHERE id = 16 AND username = '20260111';
UPDATE pengguna SET username = 'rafi.ramadhan'     WHERE id = 17 AND username = '20260112';
UPDATE pengguna SET username = 'alya.putri2'       WHERE id = 18 AND username = '20260113';
UPDATE pengguna SET username = 'yusuf.mardiansyah' WHERE id = 19 AND username = '20260114';
UPDATE pengguna SET username = 'dinda.maharani2'   WHERE id = 20 AND username = '20260115';
UPDATE pengguna SET username = 'ahmad.rizky2'      WHERE id = 21 AND username = '20260116';
UPDATE pengguna SET username = 'nabila.aulia2'     WHERE id = 22 AND username = '20260117';
UPDATE pengguna SET username = 'bagas.pratama2'    WHERE id = 23 AND username = '20260118';
UPDATE pengguna SET username = 'salsabila.rahma2'  WHERE id = 24 AND username = '20260119';

-- Daftar NIS -> username (bagikan ke siswa):
--  20260100 ahmad.rizky        20260110 kevin.saputra
--  20260101 alya.putri         20260111 nadia.kusuma
--  20260102 bagas.pratama      20260112 rafi.ramadhan
--  20260103 dinda.maharani     20260113 alya.putri2
--  20260104 dimas.aditya       20260114 yusuf.mardiansyah
--  20260105 nabila.aulia       20260115 dinda.maharani2
--  20260106 fajar.nugroho      20260116 ahmad.rizky2
--  20260107 salsabila.rahma    20260117 nabila.aulia2
--  20260108 ilham.maulana      20260118 bagas.pratama2
--  20260109 citra.amelia       20260119 salsabila.rahma2

-- Pemeriksaan: seharusnya 0 baris (tidak ada lagi siswa ber-username angka).
-- SELECT p.id, p.username, s.nis FROM pengguna p JOIN siswa s ON s.pengguna_id = p.id
--  WHERE p.peran = 'siswa' AND p.username REGEXP '^[0-9]+$';
