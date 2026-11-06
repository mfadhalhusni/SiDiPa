-- ==========================================================================
-- SiDiPa - Siswa Digital Pasport
-- Skema + data awal. Impor lewat phpMyAdmin cPanel setelah database dibuat.
-- InnoDB / utf8mb4 / kunci asing / indeks pada kolom pencarian.
-- ==========================================================================
SET NAMES utf8mb4;
SET time_zone = '+07:00';

-- --------------------------------------------------------------------------
-- 1. Pengguna & peran
-- --------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS pengguna (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  username VARCHAR(60) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  nama VARCHAR(120) NOT NULL,
  foto VARCHAR(255) DEFAULT NULL,
  peran ENUM('admin','guru','bk','kepsek','siswa','wali') NOT NULL,
  aktif TINYINT(1) NOT NULL DEFAULT 1,
  wajib_ganti_password TINYINT(1) NOT NULL DEFAULT 0,
  dibuat_pada DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_pengguna_username (username),
  KEY idx_pengguna_peran (peran, aktif)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------------------
-- 2. Semester
-- --------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS semester (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nama VARCHAR(80) NOT NULL,
  tahun_ajaran VARCHAR(20) NOT NULL,
  semester TINYINT UNSIGNED NOT NULL,
  tgl_mulai DATE NOT NULL,
  tgl_selesai DATE NOT NULL,
  status ENUM('berjalan','ditutup') NOT NULL DEFAULT 'berjalan',
  aktif TINYINT(1) NOT NULL DEFAULT 0,
  dibuat_pada DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_semester_periode (tahun_ajaran, semester),
  KEY idx_semester_aktif (aktif, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------------------
-- 3. Kelas & guru pengampu
-- --------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS kelas (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nama VARCHAR(60) NOT NULL,
  tingkat VARCHAR(10) NOT NULL DEFAULT 'X',
  jurusan VARCHAR(60) NOT NULL DEFAULT '-',
  wali_kelas_id INT UNSIGNED DEFAULT NULL,
  aktif TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  UNIQUE KEY uq_kelas_nama (nama),
  KEY idx_kelas_wali (wali_kelas_id),
  CONSTRAINT fk_kelas_wali FOREIGN KEY (wali_kelas_id) REFERENCES pengguna (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Guru dapat mengampu beberapa kelas; dipakai pembatasan akses rekap guru.
CREATE TABLE IF NOT EXISTS guru_kelas (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  guru_id INT UNSIGNED NOT NULL,
  kelas_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_guru_kelas (guru_id, kelas_id),
  KEY idx_guru_kelas_kelas (kelas_id),
  CONSTRAINT fk_guru_kelas_guru FOREIGN KEY (guru_id) REFERENCES pengguna (id) ON DELETE CASCADE,
  CONSTRAINT fk_guru_kelas_kelas FOREIGN KEY (kelas_id) REFERENCES kelas (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------------------
-- 4. Siswa
-- --------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS siswa (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  pengguna_id INT UNSIGNED NOT NULL,
  nis VARCHAR(30) NOT NULL,
  nama VARCHAR(120) NOT NULL,
  jenis_kelamin ENUM('L','P') NOT NULL DEFAULT 'L',
  kelas_id INT UNSIGNED NOT NULL,
  qr_token VARCHAR(64) NOT NULL, -- kolom lama; QR kartu kini memuat pengguna.username (bagian 18)
  nama_wali VARCHAR(120) DEFAULT NULL,
  no_wa_wali VARCHAR(25) DEFAULT NULL,
  wali_pengguna_id INT UNSIGNED DEFAULT NULL,
  aktif TINYINT(1) NOT NULL DEFAULT 1,
  dibuat_pada DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_siswa_pengguna (pengguna_id),
  UNIQUE KEY uq_siswa_nis (nis),
  UNIQUE KEY uq_siswa_qr (qr_token),
  KEY idx_siswa_nama (nama),
  KEY idx_siswa_kelas (kelas_id, aktif),
  KEY idx_siswa_wali (wali_pengguna_id),
  CONSTRAINT fk_siswa_pengguna FOREIGN KEY (pengguna_id) REFERENCES pengguna (id) ON DELETE RESTRICT,
  CONSTRAINT fk_siswa_kelas FOREIGN KEY (kelas_id) REFERENCES kelas (id) ON DELETE RESTRICT,
  CONSTRAINT fk_siswa_wali FOREIGN KEY (wali_pengguna_id) REFERENCES pengguna (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------------------
-- 5. Aspek & katalog poin
-- --------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS aspek (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  kode VARCHAR(30) NOT NULL,
  nama VARCHAR(60) NOT NULL,
  urutan TINYINT UNSIGNED NOT NULL DEFAULT 0,
  target_semester INT NOT NULL DEFAULT 100,
  PRIMARY KEY (id),
  UNIQUE KEY uq_aspek_kode (kode)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Catatan: tingkat 'positif' dipakai katalog bawaan bagian 6 selain
-- ringan/sedang/berat/none, sehingga enum mencakup kelima nilai.
CREATE TABLE IF NOT EXISTS katalog_poin (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  kode VARCHAR(40) NOT NULL,
  nama VARCHAR(140) NOT NULL,
  aspek_id INT UNSIGNED NOT NULL,
  jenis ENUM('pelanggaran','positif') NOT NULL,
  tingkat ENUM('ringan','sedang','berat','positif','none') NOT NULL DEFAULT 'none',
  poin INT NOT NULL,
  otomatis TINYINT(1) NOT NULL DEFAULT 0,
  wajib_keterangan TINYINT(1) NOT NULL DEFAULT 0,
  aktif TINYINT(1) NOT NULL DEFAULT 1,
  dibuat_pada DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_katalog_kode (kode),
  KEY idx_katalog_aspek (aspek_id, aktif),
  KEY idx_katalog_nama (nama),
  CONSTRAINT fk_katalog_aspek FOREIGN KEY (aspek_id) REFERENCES aspek (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------------------
-- 6. Catatan poin (satu-satunya sumber skor)
-- --------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS catatan_poin (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  semester_id INT UNSIGNED NOT NULL,
  siswa_id INT UNSIGNED NOT NULL,
  katalog_id INT UNSIGNED DEFAULT NULL,
  aspek_id INT UNSIGNED NOT NULL,
  poin INT NOT NULL,
  sumber ENUM('manual','sistem','berkala') NOT NULL DEFAULT 'manual',
  kunci_unik VARCHAR(100) DEFAULT NULL,
  pencatat_id INT UNSIGNED DEFAULT NULL,
  keterangan TEXT,
  bukti_path VARCHAR(255) DEFAULT NULL,
  status ENUM('aktif','dibatalkan') NOT NULL DEFAULT 'aktif',
  alasan_batal TEXT,
  dibatalkan_oleh INT UNSIGNED DEFAULT NULL,
  dibatalkan_pada DATETIME DEFAULT NULL,
  tanggal DATE NOT NULL,
  dibuat_pada DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_catatan_kunci_unik (kunci_unik),
  KEY idx_catatan_skor (semester_id, siswa_id, aspek_id, status),
  KEY idx_catatan_pencatat (pencatat_id, dibuat_pada),
  KEY idx_catatan_tanggal (tanggal),
  KEY idx_catatan_siswa (siswa_id, tanggal),
  CONSTRAINT fk_catatan_semester FOREIGN KEY (semester_id) REFERENCES semester (id) ON DELETE RESTRICT,
  CONSTRAINT fk_catatan_siswa FOREIGN KEY (siswa_id) REFERENCES siswa (id) ON DELETE RESTRICT,
  CONSTRAINT fk_catatan_katalog FOREIGN KEY (katalog_id) REFERENCES katalog_poin (id) ON DELETE SET NULL,
  CONSTRAINT fk_catatan_aspek FOREIGN KEY (aspek_id) REFERENCES aspek (id) ON DELETE RESTRICT,
  CONSTRAINT fk_catatan_pencatat FOREIGN KEY (pencatat_id) REFERENCES pengguna (id) ON DELETE SET NULL,
  CONSTRAINT fk_catatan_pembatal FOREIGN KEY (dibatalkan_oleh) REFERENCES pengguna (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------------------
-- 7. Penilaian bulanan wali kelas
-- --------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS penilaian_bulanan (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  semester_id INT UNSIGNED NOT NULL,
  siswa_id INT UNSIGNED NOT NULL,
  aspek_id INT UNSIGNED NOT NULL,
  bulan CHAR(7) NOT NULL,
  nilai ENUM('kurang','cukup','baik','sangat_baik') NOT NULL,
  poin INT NOT NULL,
  penilai_id INT UNSIGNED DEFAULT NULL,
  catatan_id BIGINT UNSIGNED DEFAULT NULL,
  dibuat_pada DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_penilaian_bulanan (semester_id, siswa_id, aspek_id, bulan),
  KEY idx_penilaian_siswa (siswa_id, bulan),
  CONSTRAINT fk_penilaian_semester FOREIGN KEY (semester_id) REFERENCES semester (id) ON DELETE RESTRICT,
  CONSTRAINT fk_penilaian_siswa FOREIGN KEY (siswa_id) REFERENCES siswa (id) ON DELETE RESTRICT,
  CONSTRAINT fk_penilaian_aspek FOREIGN KEY (aspek_id) REFERENCES aspek (id) ON DELETE RESTRICT,
  CONSTRAINT fk_penilaian_penilai FOREIGN KEY (penilai_id) REFERENCES pengguna (id) ON DELETE SET NULL,
  CONSTRAINT fk_penilaian_catatan FOREIGN KEY (catatan_id) REFERENCES catatan_poin (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------------------
-- 8. Kehadiran & ibadah
-- --------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS kehadiran (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  semester_id INT UNSIGNED NOT NULL,
  siswa_id INT UNSIGNED NOT NULL,
  tanggal DATE NOT NULL,
  jam_masuk TIME DEFAULT NULL,
  jam_pulang TIME DEFAULT NULL,
  status ENUM('hadir','terlambat','izin','sakit','alpa') NOT NULL DEFAULT 'hadir',
  awal TINYINT(1) NOT NULL DEFAULT 0,
  pemindai_id INT UNSIGNED DEFAULT NULL,
  dibuat_pada DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  diubah_pada DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_kehadiran_harian (siswa_id, tanggal),
  KEY idx_kehadiran_tanggal (tanggal, status),
  KEY idx_kehadiran_semester (semester_id, siswa_id),
  CONSTRAINT fk_kehadiran_semester FOREIGN KEY (semester_id) REFERENCES semester (id) ON DELETE RESTRICT,
  CONSTRAINT fk_kehadiran_siswa FOREIGN KEY (siswa_id) REFERENCES siswa (id) ON DELETE RESTRICT,
  CONSTRAINT fk_kehadiran_pemindai FOREIGN KEY (pemindai_id) REFERENCES pengguna (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS jenis_ibadah (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  kode VARCHAR(20) NOT NULL,
  nama VARCHAR(60) NOT NULL,
  jam_mulai TIME NOT NULL,
  jam_selesai TIME NOT NULL,
  aktif TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  UNIQUE KEY uq_jenis_ibadah_kode (kode)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS kehadiran_ibadah (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  semester_id INT UNSIGNED NOT NULL,
  siswa_id INT UNSIGNED NOT NULL,
  jenis_ibadah_id INT UNSIGNED NOT NULL,
  tanggal DATE NOT NULL,
  jam_scan TIME DEFAULT NULL,
  status ENUM('hadir','udzur','tidak_hadir') NOT NULL,
  pemindai_id INT UNSIGNED DEFAULT NULL,
  catatan_id BIGINT UNSIGNED DEFAULT NULL,
  dibuat_pada DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_kehadiran_ibadah (siswa_id, jenis_ibadah_id, tanggal),
  KEY idx_ibadah_tanggal (tanggal, status),
  CONSTRAINT fk_ibadah_semester FOREIGN KEY (semester_id) REFERENCES semester (id) ON DELETE RESTRICT,
  CONSTRAINT fk_ibadah_siswa FOREIGN KEY (siswa_id) REFERENCES siswa (id) ON DELETE RESTRICT,
  CONSTRAINT fk_ibadah_jenis FOREIGN KEY (jenis_ibadah_id) REFERENCES jenis_ibadah (id) ON DELETE RESTRICT,
  CONSTRAINT fk_ibadah_pemindai FOREIGN KEY (pemindai_id) REFERENCES pengguna (id) ON DELETE SET NULL,
  CONSTRAINT fk_ibadah_catatan FOREIGN KEY (catatan_id) REFERENCES catatan_poin (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS izin_siswa (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  siswa_id INT UNSIGNED NOT NULL,
  tgl_mulai DATE NOT NULL,
  tgl_selesai DATE NOT NULL,
  jenis ENUM('izin','sakit','udzur') NOT NULL,
  keterangan VARCHAR(255) DEFAULT NULL,
  dicatat_oleh INT UNSIGNED DEFAULT NULL,
  dibuat_pada DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_izin_rentang (siswa_id, tgl_mulai, tgl_selesai),
  CONSTRAINT fk_izin_siswa FOREIGN KEY (siswa_id) REFERENCES siswa (id) ON DELETE RESTRICT,
  CONSTRAINT fk_izin_pencatat FOREIGN KEY (dicatat_oleh) REFERENCES pengguna (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS hari_libur (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  tanggal DATE NOT NULL,
  keterangan VARCHAR(160) DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_hari_libur_tanggal (tanggal)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------------------
-- 9. Banding, pembinaan, sertifikat
-- --------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS banding (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  catatan_id BIGINT UNSIGNED NOT NULL,
  siswa_id INT UNSIGNED NOT NULL,
  alasan TEXT NOT NULL,
  status ENUM('menunggu','diterima','ditolak') NOT NULL DEFAULT 'menunggu',
  keputusan TEXT,
  diputuskan_oleh INT UNSIGNED DEFAULT NULL,
  dibuat_pada DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  diputuskan_pada DATETIME DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_banding_catatan (catatan_id),
  KEY idx_banding_status (status, dibuat_pada),
  CONSTRAINT fk_banding_catatan FOREIGN KEY (catatan_id) REFERENCES catatan_poin (id) ON DELETE RESTRICT,
  CONSTRAINT fk_banding_siswa FOREIGN KEY (siswa_id) REFERENCES siswa (id) ON DELETE RESTRICT,
  CONSTRAINT fk_banding_pemutus FOREIGN KEY (diputuskan_oleh) REFERENCES pengguna (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pembinaan (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  siswa_id INT UNSIGNED NOT NULL,
  semester_id INT UNSIGNED NOT NULL,
  pemicu VARCHAR(120) NOT NULL,
  tindak_lanjut TEXT,
  status ENUM('baru','proses','selesai') NOT NULL DEFAULT 'baru',
  dicatat_oleh INT UNSIGNED DEFAULT NULL,
  tanggal DATE NOT NULL,
  dibuat_pada DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_pembinaan_status (status, tanggal),
  KEY idx_pembinaan_siswa (siswa_id, semester_id),
  CONSTRAINT fk_pembinaan_siswa FOREIGN KEY (siswa_id) REFERENCES siswa (id) ON DELETE RESTRICT,
  CONSTRAINT fk_pembinaan_semester FOREIGN KEY (semester_id) REFERENCES semester (id) ON DELETE RESTRICT,
  CONSTRAINT fk_pembinaan_pencatat FOREIGN KEY (dicatat_oleh) REFERENCES pengguna (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------------------
-- Bagian 22 — Level penanganan siswa bermasalah (Guru BK).
-- Satu baris = satu penetapan/perubahan level, sehingga riwayatnya lengkap.
-- --------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS penanganan_siswa (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  siswa_id INT UNSIGNED NOT NULL,
  semester_id INT UNSIGNED NOT NULL,
  level TINYINT UNSIGNED NOT NULL DEFAULT 1,
  status ENUM('baru','proses','selesai') NOT NULL DEFAULT 'baru',
  tahap VARCHAR(160) NOT NULL DEFAULT '',
  penanggung_jawab VARCHAR(120) NOT NULL DEFAULT '',
  catatan TEXT,
  dicatat_oleh INT UNSIGNED DEFAULT NULL,
  tanggal DATE NOT NULL,
  dibuat_pada DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_penanganan_siswa (siswa_id, semester_id, id),
  KEY idx_penanganan_level (level, status),
  KEY idx_penanganan_semester (semester_id, tanggal),
  CONSTRAINT fk_penanganan_siswa FOREIGN KEY (siswa_id) REFERENCES siswa (id) ON DELETE RESTRICT,
  CONSTRAINT fk_penanganan_semester FOREIGN KEY (semester_id) REFERENCES semester (id) ON DELETE RESTRICT,
  CONSTRAINT fk_penanganan_pencatat FOREIGN KEY (dicatat_oleh) REFERENCES pengguna (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sertifikat (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  siswa_id INT UNSIGNED NOT NULL,
  semester_id INT UNSIGNED NOT NULL,
  kode VARCHAR(60) NOT NULL,
  data_json LONGTEXT NOT NULL,
  status ENUM('terbit','dibatalkan') NOT NULL DEFAULT 'terbit',
  diterbitkan_oleh INT UNSIGNED DEFAULT NULL,
  tanggal_terbit DATE NOT NULL,
  dibuat_pada DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  alasan_batal VARCHAR(255) DEFAULT NULL,
  dibatalkan_oleh INT UNSIGNED DEFAULT NULL,
  dibatalkan_pada DATETIME DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_sertifikat_kode (kode),
  UNIQUE KEY uq_sertifikat_siswa_semester (siswa_id, semester_id),
  CONSTRAINT fk_sertifikat_siswa FOREIGN KEY (siswa_id) REFERENCES siswa (id) ON DELETE RESTRICT,
  CONSTRAINT fk_sertifikat_semester FOREIGN KEY (semester_id) REFERENCES semester (id) ON DELETE RESTRICT,
  CONSTRAINT fk_sertifikat_penerbit FOREIGN KEY (diterbitkan_oleh) REFERENCES pengguna (id) ON DELETE SET NULL,
  CONSTRAINT fk_sertifikat_pembatal FOREIGN KEY (dibatalkan_oleh) REFERENCES pengguna (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------------------
-- 10. Pengaturan, notifikasi, antrean WA, log
-- --------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS pengaturan (
  kunci VARCHAR(80) NOT NULL,
  nilai TEXT,
  grup VARCHAR(40) NOT NULL DEFAULT 'umum',
  diperbarui_pada DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (kunci),
  KEY idx_pengaturan_grup (grup)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notifikasi_wa (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  siswa_id INT UNSIGNED NOT NULL,
  nomor VARCHAR(25) NOT NULL,
  jenis VARCHAR(40) NOT NULL,
  pesan TEXT NOT NULL,
  status ENUM('antri','terkirim','gagal') NOT NULL DEFAULT 'antri',
  percobaan TINYINT UNSIGNED NOT NULL DEFAULT 0,
  balasan TEXT,
  dibuat_pada DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  dikirim_pada DATETIME DEFAULT NULL,
  PRIMARY KEY (id),
  KEY idx_wa_status (status, dibuat_pada),
  KEY idx_wa_siswa (siswa_id, jenis),
  CONSTRAINT fk_wa_siswa FOREIGN KEY (siswa_id) REFERENCES siswa (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS log_akses (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  pengguna_id INT UNSIGNED DEFAULT NULL,
  aksi VARCHAR(60) NOT NULL,
  objek VARCHAR(80) DEFAULT NULL,
  objek_id BIGINT UNSIGNED DEFAULT NULL,
  ip VARCHAR(45) DEFAULT NULL,
  waktu DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_log_aksi (aksi, waktu),
  KEY idx_log_pengguna (pengguna_id, waktu),
  CONSTRAINT fk_log_pengguna FOREIGN KEY (pengguna_id) REFERENCES pengguna (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Notifikasi in-app (lonceng di header). Tidak dikirim ke siswa sebagai nama pencatat.
CREATE TABLE IF NOT EXISTS notifikasi (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  pengguna_id INT UNSIGNED NOT NULL,
  judul VARCHAR(140) NOT NULL,
  pesan TEXT NOT NULL,
  tautan VARCHAR(255) DEFAULT NULL,
  dibaca TINYINT(1) NOT NULL DEFAULT 0,
  dibuat_pada DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_notifikasi_pengguna (pengguna_id, dibaca, dibuat_pada),
  CONSTRAINT fk_notifikasi_pengguna FOREIGN KEY (pengguna_id) REFERENCES pengguna (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==========================================================================
-- DATA AWAL
-- ==========================================================================

-- Enam aspek, target semester 100 (dapat diubah admin).
INSERT IGNORE INTO aspek (id, kode, nama, urutan, target_semester) VALUES
 (1, 'KEDISIPLINAN', 'Kedisiplinan', 1, 100),
 (2, 'KEJUJURAN', 'Kejujuran', 2, 100),
 (3, 'RELIGIUS', 'Religius', 3, 100),
 (4, 'KERJA_SAMA_TIM', 'Kerja Sama Tim', 4, 100),
 (5, 'KEPEMIMPINAN', 'Kepemimpinan', 5, 100),
 (6, 'PENYELESAIAN_MASALAH', 'Penyelesaian Masalah', 6, 100);

-- Katalog bawaan bagian 6. Kolom Auto = otomatis = 1.
INSERT IGNORE INTO katalog_poin (kode, nama, aspek_id, jenis, tingkat, poin, otomatis, wajib_keterangan, aktif) VALUES
 ('DIS_TERLAMBAT', 'Terlambat masuk', 1, 'pelanggaran', 'ringan', -2, 1, 0, 1),
 ('DIS_ALPA', 'Alpa satu hari', 1, 'pelanggaran', 'sedang', -5, 1, 1, 1),
 ('DIS_BOLOS', 'Bolos jam pelajaran', 1, 'pelanggaran', 'sedang', -5, 0, 1, 1),
 ('DIS_SERAGAM', 'Seragam atau atribut tidak lengkap', 1, 'pelanggaran', 'ringan', -2, 0, 0, 1),
 ('DIS_TUGAS', 'Tidak mengumpulkan tugas tepat waktu', 1, 'pelanggaran', 'ringan', -2, 0, 0, 1),
 ('DIS_KELAHI', 'Berkelahi', 1, 'pelanggaran', 'berat', -20, 0, 1, 1),
 ('DIS_RUSAK', 'Merusak fasilitas sekolah', 1, 'pelanggaran', 'berat', -15, 0, 1, 1),
 ('DIS_ROKOK', 'Merokok atau membawa rokok', 1, 'pelanggaran', 'berat', -20, 0, 1, 1),
 ('DIS_BONUS_PAGI', 'Bonus datang sebelum 06.50 (per 5 hari)', 1, 'positif', 'positif', 1, 1, 0, 1),
 ('DIS_MINGGU_BERSIH', 'Minggu tanpa terlambat dan alpa', 1, 'positif', 'positif', 2, 1, 0, 1),
 ('JUJ_MENYONTEK', 'Menyontek', 2, 'pelanggaran', 'sedang', -10, 0, 1, 1),
 ('JUJ_BOHONG', 'Berbohong kepada guru', 2, 'pelanggaran', 'sedang', -5, 0, 1, 1),
 ('JUJ_PALSU', 'Memalsukan tanda tangan atau surat', 2, 'pelanggaran', 'berat', -15, 0, 1, 1),
 ('JUJ_AMBIL', 'Mengambil barang milik orang lain', 2, 'pelanggaran', 'berat', -25, 0, 1, 1),
 ('JUJ_TEMUKAN', 'Mengembalikan barang temuan', 2, 'positif', 'positif', 5, 0, 0, 1),
 ('JUJ_AKUI', 'Mengakui kesalahan dengan jujur', 2, 'positif', 'positif', 3, 0, 0, 1),
 ('REL_DHUHA_TIDAK', 'Tidak hadir sholat dhuha tanpa udzur', 3, 'pelanggaran', 'ringan', -3, 1, 0, 1),
 ('REL_DHUHUR_TIDAK', 'Tidak hadir sholat dhuhur berjamaah tanpa udzur', 3, 'pelanggaran', 'ringan', -3, 1, 0, 1),
 ('REL_GANGGU', 'Mengganggu ibadah teman', 3, 'pelanggaran', 'sedang', -5, 0, 1, 1),
 ('REL_KEGIATAN', 'Tidak mengikuti kegiatan keagamaan wajib', 3, 'pelanggaran', 'ringan', -3, 0, 0, 1),
 ('REL_MINGGU_DHUHA', 'Minggu penuh sholat dhuha', 3, 'positif', 'positif', 2, 1, 0, 1),
 ('REL_MINGGU_DHUHUR', 'Minggu penuh sholat dhuhur', 3, 'positif', 'positif', 2, 1, 0, 1),
 ('REL_IMAM', 'Menjadi imam, muadzin, atau petugas kegiatan keagamaan', 3, 'positif', 'positif', 3, 0, 0, 1),
 ('TIM_PIKET', 'Tidak ikut kerja kelompok atau piket', 4, 'pelanggaran', 'ringan', -3, 0, 0, 1),
 ('TIM_BERTENGKAR', 'Bertengkar dalam kelompok', 4, 'pelanggaran', 'sedang', -5, 0, 1, 1),
 ('TIM_BULLYING', 'Perundungan (bullying)', 4, 'pelanggaran', 'berat', -25, 0, 1, 1),
 ('TIM_BANTU', 'Aktif membantu teman', 4, 'positif', 'positif', 3, 0, 0, 1),
 ('TIM_GOTONG', 'Gotong royong atau kegiatan sekolah', 4, 'positif', 'positif', 3, 0, 0, 1),
 ('PIM_LALAI', 'Lalai dari tanggung jawab jabatan', 5, 'pelanggaran', 'sedang', -5, 0, 1, 1),
 ('PIM_KETUA', 'Menjadi ketua kelompok atau kegiatan', 5, 'positif', 'positif', 5, 0, 0, 1),
 ('PIM_DOAK', 'Memimpin doa, upacara, atau kegiatan', 5, 'positif', 'positif', 3, 0, 0, 1),
 ('PIM_TELADAN', 'Menjadi teladan (apresiasi guru)', 5, 'positif', 'positif', 3, 0, 0, 1),
 ('SOL_ADU_DOMBA', 'Mengadu domba atau memperkeruh masalah', 6, 'pelanggaran', 'sedang', -10, 0, 1, 1),
 ('SOL_HAKIM', 'Main hakim sendiri', 6, 'pelanggaran', 'sedang', -10, 0, 1, 1),
 ('SOL_TENGAHI', 'Menengahi konflik dengan baik', 6, 'positif', 'positif', 5, 0, 0, 1),
 ('SOL_SOLUSI', 'Memberi solusi atau inisiatif memperbaiki sesuatu', 6, 'positif', 'positif', 5, 0, 0, 1);

-- Dua jenis ibadah dengan jendela waktu scan.
INSERT IGNORE INTO jenis_ibadah (id, kode, nama, jam_mulai, jam_selesai, aktif) VALUES
 (1, 'dhuha', 'Sholat Dhuha Berjamaah', '06:30:00', '09:00:00', 1),
 (2, 'dhuhur', 'Sholat Dhuhur Berjamaah', '12:00:00', '13:00:00', 1);

-- Satu semester contoh yang sedang berjalan.
INSERT IGNORE INTO semester (id, nama, tahun_ajaran, semester, tgl_mulai, tgl_selesai, status, aktif) VALUES
 (1, 'Semester Ganjil', '2026/2027', 1, '2026-07-13', '2026-12-19', 'berjalan', 1);

-- Akun admin: kata sandi sementara SiDiPa@2026, WAJIB diganti saat login pertama.
INSERT IGNORE INTO pengguna (id, username, password_hash, nama, peran, aktif, wajib_ganti_password) VALUES
 (1, 'admin', '$2y$12$l4biZdycwLTQcjQto/t4sOlrPMkxE1t6Qx4dmLp8krZVJXadtEV.2', 'Administrator SiDiPa', 'admin', 1, 1);

-- Pengaturan bawaan. Semua angka dapat dikalibrasi admin lewat menu Pengaturan.
INSERT IGNORE INTO pengaturan (kunci, nilai, grup) VALUES
 ('nama_sekolah', 'SMK Negeri 1 Contoh', 'sekolah'),
 ('alamat_sekolah', 'Jl. Pendidikan No. 1, Surabaya', 'sekolah'),
 ('nama_kepsek', 'Drs. H. Contoh Kepala, M.Pd.', 'sekolah'),
 ('nip_kepsek', '196501011990031001', 'sekolah'),
 ('logo_sekolah', 'public/img/logo-sidipa.svg', 'sekolah'),
 ('ttd_kepsek', 'public/img/tanda-tangan.svg', 'sekolah'),
 ('ttd_wali_default', 'public/img/tanda-tangan.svg', 'sekolah'),
 ('jam_bonus_pagi', '06:50', 'jam'),
 ('batas_terlambat', '07:00', 'jam'),
 ('jam_cron_harian', '15:00', 'jam'),
 ('poin_terlambat', '-2', 'poin'),
 ('poin_alpa', '-5', 'poin'),
 ('poin_tidak_sholat', '-3', 'poin'),
 ('poin_minggu_disiplin', '2', 'poin'),
 ('poin_minggu_dhuha', '2', 'poin'),
 ('poin_minggu_dhuhur', '2', 'poin'),
 ('poin_bonus_pagi', '1', 'poin'),
 ('bonus_pagi_setiap_hari', '5', 'poin'),
 ('bonus_pagi_maks', '10', 'poin'),
 -- Warisan sejak bagian 20: pagar pengurangan harian DIHAPUS, baris ini
 -- sengaja dibiarkan (INSERT IGNORE) agar instalasi lama tidak perlu migrasi.
 ('batas_pengurangan_harian', '10', 'poin'),
 -- Skala 8 tingkat: A+ 95, A 90, B+ 85, B 80, B- 75, C+ 70, C 60, D < 60.
 ('predikat_aplus', '95', 'predikat'),
 ('predikat_a', '90', 'predikat'),
 ('predikat_bplus', '85', 'predikat'),
 ('predikat_b', '80', 'predikat'),
 ('predikat_bminus', '75', 'predikat'),
 ('predikat_cplus', '70', 'predikat'),
 ('predikat_c', '60', 'predikat'),
 ('predikat_d', '0', 'predikat'),
 ('ambang_pembinaan_persen', '60', 'pembinaan'),
 ('ambang_pembinaan_skor', '0', 'pembinaan'),
 ('penilaian_kurang', '-3', 'penilaian'),
 ('penilaian_cukup', '0', 'penilaian'),
 ('penilaian_baik', '3', 'penilaian'),
 ('penilaian_sangat_baik', '5', 'penilaian'),
 ('batas_batal_catatan_jam', '24', 'lainnya'),
 ('maks_ukuran_bukti', '2097152', 'lainnya'),
 ('wa_aktif', '1', 'wa'),
 ('wa_base_url', '', 'wa'),
 ('wa_api_key', '', 'wa'),
 ('wa_session_id', 'default', 'wa'),
 ('wa_endpoint', '/api/send-message', 'wa'),
 ('wa_maks_per_jalan', '20', 'wa'),
 ('wa_percobaan_maks', '3', 'wa'),
 ('wa_jeda_min', '3', 'wa'),
 ('wa_jeda_maks', '8', 'wa'),
 ('wa_pemicu_alpa', '1', 'wa'),
 ('wa_pemicu_terlambat', '0', 'wa'),
 ('wa_pemicu_pelanggaran_berat', '1', 'wa'),
 ('wa_pemicu_skor_rendah', '1', 'wa'),
 ('wa_pemicu_sertifikat', '1', 'wa'),
 ('wa_template_alpa', 'Yth. wali murid, {nama} ({kelas}) tercatat alpa pada {tanggal}. Mohon menghubungi wali kelas. - SiDiPa', 'wa'),
 ('wa_template_terlambat', 'Yth. wali murid, {nama} ({kelas}) tercatat terlambat pukul {jam} pada {tanggal}. - SiDiPa', 'wa'),
 ('wa_template_pelanggaran_berat', 'Yth. wali murid, {nama} ({kelas}) mendapat catatan {aspek} {poin} pada {tanggal}. Mohon koordinasi dengan BK. - SiDiPa', 'wa'),
 ('wa_template_skor_rendah', 'Yth. wali murid, skor {aspek} {nama} ({kelas}) perlu pembinaan. - SiDiPa', 'wa'),
 ('wa_template_sertifikat', 'Yth. wali murid, sertifikat karakter {nama} ({kelas}) telah terbit pada {tanggal}. - SiDiPa', 'wa');


-- --------------------------------------------------------------------------
-- 22. Login persisten ("ingat saya") — SLIDING 30 HARI
-- Hanya hash SHA-256 token yang disimpan; token mentah ada di cookie perangkat.
-- Token diputar setiap kali dipakai, jadi token lama langsung tidak berlaku.
-- `kedaluwarsa` BUKAN batas absolut sejak login: setiap aktivitas yang masih
-- dalam masa berlaku menggesernya ke (NOW() + SIDIPA_INGAT_HARI). Pengguna
-- hanya perlu login ulang bila tidak ada aktivitas selama masa itu.
-- Tidak ada kolom/tabel tambahan untuk perilaku ini.
-- --------------------------------------------------------------------------
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
