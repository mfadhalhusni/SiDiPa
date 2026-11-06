-- ==========================================================================
-- SiDiPa — MIGRASI untuk instalasi LAMA yang ingin memakai fitur
-- "Level penanganan siswa bermasalah" (bagian 22, menu Guru BK).
--
-- Jalankan SQL ini SATU KALI lewat phpMyAdmin / cPanel setelah mengunggah
-- berkas aplikasi terbaru. Instalasi BARU tidak perlu menjalankannya karena
-- tabel ini sudah ada di database.sql.
--
-- Tidak ada ALTER TABLE pada tabel lain dan tidak ada perubahan data lama.
-- ==========================================================================

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
