<?php
declare(strict_types=1);

/*
 |--------------------------------------------------------------------------
 | SiDiPa - Konfigurasi utama
 |--------------------------------------------------------------------------
 | Isi empat nilai database di bawah sesuai informasi dari cPanel, kemudian
 | jalankan install.php dan HAPUS install.php setelah pemeriksaan berhasil.
 |
 | Semua angka operasional (jam masuk, batas terlambat, bobot poin, target
 | aspek, ambang predikat, template WhatsApp) TIDAK ditulis di sini: semuanya
 | tersimpan di tabel `pengaturan` dan dapat diubah admin lewat menu Pengaturan.
 |
 | Alternatif: nilai bisa diberikan lewat environment variable cPanel
 | (SIDIPA_DB_HOST, SIDIPA_DB_NAME, SIDIPA_DB_USER, SIDIPA_DB_PASS,
 |  SIDIPA_BASE_URL, SIDIPA_KUNCI) agar file ini tidak perlu disunting.
 */

// ---- Database cPanel -----------------------------------------------------
define('SIDIPA_DB_HOST', getenv('SIDIPA_DB_HOST') ?: 'localhost');
define('SIDIPA_DB_NAME', getenv('SIDIPA_DB_NAME') ?: 'nama_database_sidipa');
define('SIDIPA_DB_USER', getenv('SIDIPA_DB_USER') ?: 'nama_pengguna_database');
define('SIDIPA_DB_PASS', getenv('SIDIPA_DB_PASS') !== false ? (string) getenv('SIDIPA_DB_PASS') : 'ganti_kata_sandi_database');
define('SIDIPA_DB_PORT', (int) (getenv('SIDIPA_DB_PORT') ?: 3306));

// ---- Alamat aplikasi -----------------------------------------------------
// Kosongkan untuk deteksi otomatis. Isi mis. 'https://sidipa.smk.sch.id'
// bila aplikasi berada di subdirektori atau memakai domain khusus.
define('SIDIPA_BASE_URL', getenv('SIDIPA_BASE_URL') ?: '');

// ---- Kebijakan cookie sesi ----------------------------------------------
// 'Lax' adalah pilihan aman untuk produksi (dibuka sebagai tab langsung).
// Bila aplikasi disematkan lintas-situs (iframe preview) cookie Lax tidak
// dikirim browser, sehingga token CSRF dianggap kedaluwarsa. Set 'None'
// lewat environment untuk konteks tersebut; hanya berlaku saat HTTPS.
define('SIDIPA_SAMESITE', getenv('SIDIPA_SAMESITE') ?: 'Lax');
// Chrome memblokir cookie pihak ketiga; atribut Partitioned (CHIPS) membuat
// cookie sesi tetap tersimpan di dalam iframe. Abaikan pada produksi.
define('SIDIPA_COOKIE_PARTISI', (bool) (getenv('SIDIPA_COOKIE_PARTISI') ?: false));

// ---- Login persisten --------------------------------------------------------
// Sekali login, sesi tersimpan di perangkat selama N hari. Pengguna dapat
// menolak lewat centangan "Jangan simpan login" pada halaman masuk.
define('SIDIPA_INGAT_HARI', (int) (getenv('SIDIPA_INGAT_HARI') ?: 30));

// Kunci rahasia untuk token turunan (bukan untuk menyimpan kata sandi).
// Nilai ini sudah diacak; ganti dengan nilai acak lain saat produksi bila perlu.
define('SIDIPA_KUNCI', getenv('SIDIPA_KUNCI') ?: '5bd569f407f1750c3a7ef71c49e0bad5816c200897994123f2682cac1fd72d35');

// ---- Lingkungan ----------------------------------------------------------
define('SIDIPA_ZONA_WAKTU', 'Asia/Jakarta');
define('SIDIPA_DEBUG', false);
define('SIDIPA_LOG', __DIR__ . '/storage/logs/sidipa.log');
define('SIDIPA_DIR_UPLOAD', __DIR__ . '/public/uploads');
define('SIDIPA_MAKS_BUKTI', 2 * 1024 * 1024);
