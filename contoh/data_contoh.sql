-- ==========================================================================
-- SiDiPa - PAKET DATA CONTOH OPSIONAL (3 kelas, 20 siswa fiktif)
-- Impor SETELAH database.sql. Semua nama dan nomor adalah data fiktif.
-- Kata sandi guru: Guru@2026 | Kata sandi siswa: Siswa@2026
-- Keduanya wajib diganti pada login pertama.
-- ==========================================================================
SET NAMES utf8mb4;

-- Tiga akun guru sekaligus wali kelas.
INSERT IGNORE INTO pengguna (id, username, password_hash, nama, peran, aktif, wajib_ganti_password) VALUES
 (2, 'guru.tkj1', '$2y$12$zGvYODmnC9xZ6BizYegcreUS.pWvrKGjux3IcCo1Jurcs5O/Xe5G6', 'Pak Suryono, S.Pd.', 'guru', 1, 1),
 (3, 'guru.tkj2', '$2y$12$zGvYODmnC9xZ6BizYegcreUS.pWvrKGjux3IcCo1Jurcs5O/Xe5G6', 'Ibu Retno Wulandari, S.Pd.', 'guru', 1, 1),
 (4, 'guru.tkj3', '$2y$12$zGvYODmnC9xZ6BizYegcreUS.pWvrKGjux3IcCo1Jurcs5O/Xe5G6', 'Pak Bambang Hermanto, S.Pd.', 'guru', 1, 1);

INSERT IGNORE INTO kelas (id, nama, tingkat, jurusan, wali_kelas_id, aktif) VALUES
 (1, 'X TKJ 1', 'X', 'TKJ', 2, 1),
 (2, 'XI TKJ 1', 'XI', 'TKJ', 3, 1),
 (3, 'XII TKJ 1', 'XII', 'TKJ', 4, 1);

INSERT IGNORE INTO guru_kelas (guru_id, kelas_id) VALUES
 (2, 1),
 (3, 2),
 (4, 3);

-- 20 akun siswa.
INSERT IGNORE INTO pengguna (id, username, password_hash, nama, peran, aktif, wajib_ganti_password) VALUES
 (5, '20260100', '$2y$12$/dyJ3y.n3loWAKW88GmdE.AFN6J3mjuqn2hxusqU.lVceaagwBvlm', 'Ahmad Rizky Prasetyo', 'siswa', 1, 1),
 (6, '20260101', '$2y$12$/dyJ3y.n3loWAKW88GmdE.AFN6J3mjuqn2hxusqU.lVceaagwBvlm', 'Alya Putri Wijaya', 'siswa', 1, 1),
 (7, '20260102', '$2y$12$/dyJ3y.n3loWAKW88GmdE.AFN6J3mjuqn2hxusqU.lVceaagwBvlm', 'Bagas Pratama Saputri', 'siswa', 1, 1),
 (8, '20260103', '$2y$12$/dyJ3y.n3loWAKW88GmdE.AFN6J3mjuqn2hxusqU.lVceaagwBvlm', 'Dinda Maharani Hidayat', 'siswa', 1, 1),
 (9, '20260104', '$2y$12$/dyJ3y.n3loWAKW88GmdE.AFN6J3mjuqn2hxusqU.lVceaagwBvlm', 'Dimas Aditya Lestari', 'siswa', 1, 1),
 (10, '20260105', '$2y$12$/dyJ3y.n3loWAKW88GmdE.AFN6J3mjuqn2hxusqU.lVceaagwBvlm', 'Nabila Aulia Kurniawan', 'siswa', 1, 1),
 (11, '20260106', '$2y$12$/dyJ3y.n3loWAKW88GmdE.AFN6J3mjuqn2hxusqU.lVceaagwBvlm', 'Fajar Nugroho Anggraini', 'siswa', 1, 1),
 (12, '20260107', '$2y$12$/dyJ3y.n3loWAKW88GmdE.AFN6J3mjuqn2hxusqU.lVceaagwBvlm', 'Salsabila Rahma Setiawan', 'siswa', 1, 1),
 (13, '20260108', '$2y$12$/dyJ3y.n3loWAKW88GmdE.AFN6J3mjuqn2hxusqU.lVceaagwBvlm', 'Ilham Maulana Ramadhani', 'siswa', 1, 1),
 (14, '20260109', '$2y$12$/dyJ3y.n3loWAKW88GmdE.AFN6J3mjuqn2hxusqU.lVceaagwBvlm', 'Citra Amelia Puspita', 'siswa', 1, 1),
 (15, '20260110', '$2y$12$/dyJ3y.n3loWAKW88GmdE.AFN6J3mjuqn2hxusqU.lVceaagwBvlm', 'Kevin Saputra Prasetyo', 'siswa', 1, 1),
 (16, '20260111', '$2y$12$/dyJ3y.n3loWAKW88GmdE.AFN6J3mjuqn2hxusqU.lVceaagwBvlm', 'Nadia Kusuma Wijaya', 'siswa', 1, 1),
 (17, '20260112', '$2y$12$/dyJ3y.n3loWAKW88GmdE.AFN6J3mjuqn2hxusqU.lVceaagwBvlm', 'Rafi Ramadhan Saputri', 'siswa', 1, 1),
 (18, '20260113', '$2y$12$/dyJ3y.n3loWAKW88GmdE.AFN6J3mjuqn2hxusqU.lVceaagwBvlm', 'Alya Putri Hidayat', 'siswa', 1, 1),
 (19, '20260114', '$2y$12$/dyJ3y.n3loWAKW88GmdE.AFN6J3mjuqn2hxusqU.lVceaagwBvlm', 'Yusuf Mardiansyah Lestari', 'siswa', 1, 1),
 (20, '20260115', '$2y$12$/dyJ3y.n3loWAKW88GmdE.AFN6J3mjuqn2hxusqU.lVceaagwBvlm', 'Dinda Maharani Kurniawan', 'siswa', 1, 1),
 (21, '20260116', '$2y$12$/dyJ3y.n3loWAKW88GmdE.AFN6J3mjuqn2hxusqU.lVceaagwBvlm', 'Ahmad Rizky Anggraini', 'siswa', 1, 1),
 (22, '20260117', '$2y$12$/dyJ3y.n3loWAKW88GmdE.AFN6J3mjuqn2hxusqU.lVceaagwBvlm', 'Nabila Aulia Setiawan', 'siswa', 1, 1),
 (23, '20260118', '$2y$12$/dyJ3y.n3loWAKW88GmdE.AFN6J3mjuqn2hxusqU.lVceaagwBvlm', 'Bagas Pratama Ramadhani', 'siswa', 1, 1),
 (24, '20260119', '$2y$12$/dyJ3y.n3loWAKW88GmdE.AFN6J3mjuqn2hxusqU.lVceaagwBvlm', 'Salsabila Rahma Puspita', 'siswa', 1, 1);

INSERT IGNORE INTO siswa (id, pengguna_id, nis, nama, jenis_kelamin, kelas_id, qr_token, nama_wali, no_wa_wali, aktif) VALUES
 (1, 5, '20260100', 'Ahmad Rizky Prasetyo', 'L', 1, '81489304631599e20d669d7b1577fab1', 'Bapak Hidayat', '628123457000', 1),
 (2, 6, '20260101', 'Alya Putri Wijaya', 'P', 2, '3df4d767bec2806dfa9c466e66402646', 'Ibu Lestari', '628123457001', 1),
 (3, 7, '20260102', 'Bagas Pratama Saputri', 'L', 3, 'd7e72c505a984bc407eae3de0af64aa9', 'Bapak Kurniawan', '628123457002', 1),
 (4, 8, '20260103', 'Dinda Maharani Hidayat', 'P', 1, '98bca80d45581c508e0f557c8603e206', 'Ibu Anggraini', '628123457003', 1),
 (5, 9, '20260104', 'Dimas Aditya Lestari', 'L', 2, 'e95506d04f51df74fdeee0e5456dfd72', 'Bapak Setiawan', '628123457004', 1),
 (6, 10, '20260105', 'Nabila Aulia Kurniawan', 'P', 3, '6068b83aee33d856616a5251a3496d2d', 'Ibu Ramadhani', '628123457005', 1),
 (7, 11, '20260106', 'Fajar Nugroho Anggraini', 'L', 1, '9a0228de0774bedb44048f2bd83c9faa', 'Bapak Puspita', '628123457006', 1),
 (8, 12, '20260107', 'Salsabila Rahma Setiawan', 'P', 2, 'fcd00eda4f458cdeadbd507d406dd330', 'Ibu Prasetyo', '628123457007', 1),
 (9, 13, '20260108', 'Ilham Maulana Ramadhani', 'L', 3, 'b8e5af385272795a56274b424fdaf753', 'Bapak Wijaya', '628123457008', 1),
 (10, 14, '20260109', 'Citra Amelia Puspita', 'P', 1, 'c9fa2585a04d05483188620c60a40435', 'Ibu Saputri', '628123457009', 1),
 (11, 15, '20260110', 'Kevin Saputra Prasetyo', 'L', 2, '009d8a65934885bebeb647fd30ae2575', 'Bapak Hidayat', '628123457010', 1),
 (12, 16, '20260111', 'Nadia Kusuma Wijaya', 'P', 3, '36d7593ba03f1fd912bd7337c55bb753', 'Ibu Lestari', '628123457011', 1),
 (13, 17, '20260112', 'Rafi Ramadhan Saputri', 'L', 1, '0ca87745c54a615d3db6f4814aa7ad2e', 'Bapak Kurniawan', '628123457012', 1),
 (14, 18, '20260113', 'Alya Putri Hidayat', 'P', 2, '60bef31ab153806cfbfc543e948b554d', 'Ibu Anggraini', '628123457013', 1),
 (15, 19, '20260114', 'Yusuf Mardiansyah Lestari', 'L', 3, '0f23d97bbd2b7d565fe8bcf9bc20c287', 'Bapak Setiawan', '628123457014', 1),
 (16, 20, '20260115', 'Dinda Maharani Kurniawan', 'P', 1, 'b9a593407b8d0179f3bb8a91d480f34a', 'Ibu Ramadhani', '628123457015', 1),
 (17, 21, '20260116', 'Ahmad Rizky Anggraini', 'L', 2, '83035b807d0bab96d74bccd520034181', 'Bapak Puspita', '628123457016', 1),
 (18, 22, '20260117', 'Nabila Aulia Setiawan', 'P', 3, '42c19c3ab532326797aa703e4d2ff57f', 'Ibu Prasetyo', '628123457017', 1),
 (19, 23, '20260118', 'Bagas Pratama Ramadhani', 'L', 1, '9c552244a22e091896295420d84ff860', 'Bapak Wijaya', '628123457018', 1),
 (20, 24, '20260119', 'Salsabila Rahma Puspita', 'P', 2, '6a75672c8f2730ad3be5db8a7ba06b66', 'Ibu Saputri', '628123457019', 1);
