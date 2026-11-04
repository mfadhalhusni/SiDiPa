<?php
declare(strict_types=1);

/*
 | Pemeriksa instalasi SiDiPa.
 | Jalankan sekali dari browser setelah database.sql diimpor, lalu HAPUS file ini.
 | File ini hanya membaca konfigurasi, tidak mengubah data.
 */

require_once __DIR__ . '/app/bootstrap.php';

$cek = [];
function tambah_cek(string $label, bool $ok, string $rincian): void
{
    global $cek;
    $cek[] = ['label' => $label, 'ok' => $ok, 'rincian' => $rincian];
}

tambah_cek('PHP 8.1 atau lebih baru', PHP_VERSION_ID >= 80100, 'Versi terdeteksi: ' . PHP_VERSION);

$ekstensi = ['pdo_mysql', 'curl', 'fileinfo', 'mbstring', 'json', 'session'];
foreach ($ekstensi as $ext) {
    tambah_cek('Ekstensi ' . $ext, extension_loaded($ext), extension_loaded($ext) ? 'Tersedia' : 'Tidak aktif, minta aktifkan lewat Select PHP Version di cPanel');
}

$belumDiisi = SIDIPA_DB_NAME === 'nama_database_sidipa' || SIDIPA_DB_USER === 'nama_pengguna_database';
tambah_cek('config.php sudah diisi', !$belumDiisi, $belumDiisi ? 'Masih memakai nilai contoh. Isi nama database, pengguna, dan kata sandi cPanel.' : 'Nilai database sudah diset.');

$dbOk = false;
$tabelOk = false;
try {
    db();
    $dbOk = true;
    tambah_cek('Koneksi database', true, 'PDO MySQL terhubung ke ' . e(SIDIPA_DB_NAME) . '.');
    $jumlah = (int) db_nilai(
        "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'pengaturan'",
        [],
        0
    );
    $tabelOk = $jumlah > 0;
    tambah_cek('Skema database.sql', $tabelOk, $tabelOk ? 'Tabel pengaturan ditemukan.' : 'Tabel belum ada. Impor database.sql lewat phpMyAdmin.');
    if ($tabelOk) {
        $jumlahAspek = (int) db_nilai('SELECT COUNT(*) FROM aspek', [], 0);
        tambah_cek('Data awal', $jumlahAspek === 6, 'Jumlah aspek: ' . $jumlahAspek . ' (harus 6).');
        $ingatAda = (int) db_nilai(
            "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'ingat_login'",
            [],
            0
        );
        tambah_cek(
            'Tabel ingat_login (login 30 hari)',
            $ingatAda > 0,
            $ingatAda > 0
                ? 'Cookie "ingat saya" siap dipakai.'
                : 'Belum ada. Jalankan CREATE TABLE ingat_login dari bagian 15 README agar login tersimpan 30 hari.'
        );
        $kolomFoto = (int) db_nilai(
            "SELECT COUNT(*) FROM information_schema.columns
             WHERE table_schema = DATABASE() AND table_name = 'pengguna' AND column_name = 'foto'",
            [],
            0
        );
        tambah_cek(
            'Kolom pengguna.foto (foto profil)',
            $kolomFoto > 0,
            $kolomFoto > 0
                ? 'Unggah foto profil siap dipakai.'
                : 'Belum ada. Jalankan ALTER TABLE pada bagian 16 README agar siswa dapat mengunggah foto profil.'
        );
        $predikatLengkap = (int) db_nilai(
            "SELECT COUNT(*) FROM pengaturan WHERE kunci IN
             ('predikat_aplus','predikat_a','predikat_bplus','predikat_b',
              'predikat_bminus','predikat_cplus','predikat_c','predikat_d')",
            [],
            0
        );
        tambah_cek(
            'Skala predikat 8 tingkat',
            $predikatLengkap === 8,
            $predikatLengkap === 8
                ? 'Kedelapan ambang A+/A/B+/B/B-/C+/C/D tersedia.'
                : 'Baru ' . $predikatLengkap . ' dari 8 ambang. Jalankan SQL pada bagian 17 README untuk menambah predikat_bplus, predikat_bminus, dan predikat_cplus.'
        );
        $siswaNis = (int) db_nilai(
            "SELECT COUNT(*) FROM pengguna p JOIN siswa s ON s.pengguna_id = p.id
             WHERE p.peran = 'siswa' AND p.username REGEXP '^[0-9]+$'",
            [],
            0
        );
        tambah_cek(
            'Username siswa bukan NIS',
            $siswaNis === 0,
            $siswaNis === 0
                ? 'Seluruh akun siswa sudah memakai username.'
                : $siswaNis . ' akun siswa masih memakai NIS sebagai login. Pakai tombol "Buatkan username massal" di Data siswa (bagian 17 README).'
        );
    }
} catch (Throwable $galat) {
    tambah_cek('Koneksi database', false, 'Gagal: ' . $galat->getMessage());
    tambah_cek('Skema database.sql', false, 'Tidak dapat memeriksa tabel sebelum koneksi berhasil.');
}

$dirLog = dirname(SIDIPA_LOG);
if (!is_dir($dirLog)) {
    @mkdir($dirLog, 0750, true);
}
if (!is_dir(SIDIPA_DIR_UPLOAD)) {
    @mkdir(SIDIPA_DIR_UPLOAD, 0755, true);
}
tambah_cek('Folder storage/logs dapat ditulis', is_dir($dirLog) && is_writable($dirLog), $dirLog);
tambah_cek('Folder public/uploads dapat ditulis', is_dir(SIDIPA_DIR_UPLOAD) && is_writable(SIDIPA_DIR_UPLOAD), SIDIPA_DIR_UPLOAD);
tambah_cek('HTTPS aktif', https_aktif(), https_aktif() ? 'Kamera pemindai QR dan service worker dapat dipakai.' : 'Aktifkan SSL; kamera HP dan PWA menolak akses tanpa HTTPS.');

$berkasPwa = [
    'manifest.webmanifest',
    'sw.js',
    'offline.html',
    'public/js/pwa.js',
    'public/js/qrcode.min.js',
    'public/js/qr-sidipa.js',
    'public/js/html5-qrcode.min.js',
    'public/css/cetak.css',
    'public/css/kartu.css',
    'public/img/icon-192.png',
    'public/img/icon-512.png',
    'public/img/icon-maskable-512.png',
    'public/img/apple-touch-icon.png',
    'public/img/favicon.png',
];
foreach ($berkasPwa as $berkas) {
    tambah_cek(
        'Berkas PWA: ' . $berkas,
        is_file(__DIR__ . '/' . $berkas),
        is_file(__DIR__ . '/' . $berkas) ? 'Tersedia.' : 'Berkas belum ada. Unggah ulang paket SiDiPa.'
    );
}
tambah_cek(
    'Header service worker',
    !function_exists('apache_get_modules') || in_array('mod_headers', apache_get_modules(), true),
    function_exists('apache_get_modules') && !in_array('mod_headers', apache_get_modules(), true)
        ? 'Aktifkan mod_headers agar sw.js dikirim tanpa cache.'
        : 'mod_headers tersedia (atau tidak terdeteksi pada PHP-FPM).'
);
tambah_cek('install.php dihapus setelah dipakai', false, 'Hapus berkas install.php dari File Manager cPanel setelah semua pemeriksaan hijau.');

$semuaHijau = true;
foreach ($cek as $item) {
    if (!$item['ok'] && $item['label'] !== 'install.php dihapus setelah dipakai') {
        $semuaHijau = false;
    }
}

function ikon_cek(bool $ok): string
{
    return ikon($ok ? 'centang' : 'peringatan', 20);
}
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <?= pwa_meta() ?>
  <title>Instalasi - SiDiPa</title>
  <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= e(asset('css/sidipa.css')) ?>">
</head>
<body class="halaman-sederhana" data-abaikan-pwa="1">
  <main class="kotak-sederhana">
    <div class="merek" style="margin-bottom:14px">
      <span class="kotak-logo"><?= ikon('sekolah', 26) ?></span>
      <span class="teks-merek">SiDiPa<small>Pemeriksa instalasi</small></span>
    </div>
    <p class="label-kecil">Langkah sekali pakai</p>
    <h1>Cek kesiapan server</h1>
    <p class="teks-kecil">Halaman ini hanya memeriksa persyaratan cPanel. Tidak ada data yang diubah.</p>

    <?php if ($semuaHijau): ?>
      <div class="pesan pesan-sukses" role="status"><?= ikon('centang', 20) ?><span>Server siap. Hapus install.php, lalu masuk dengan akun admin.</span></div>
    <?php else: ?>
      <div class="pesan pesan-galat" role="alert"><?= ikon('peringatan', 20) ?><span>Masih ada pemeriksaan yang belum lolos. Perbaiki baris berwarna merah di bawah.</span></div>
    <?php endif; ?>

    <div class="tabel-bungkus">
      <table class="tabel">
        <thead><tr><th>Status</th><th>Pemeriksaan</th><th>Keterangan</th></tr></thead>
        <tbody>
        <?php foreach ($cek as $item): ?>
          <tr class="<?= $item['ok'] ? 'bg-toska' : 'bg-koral' ?>">
            <td><?= ikon_cek($item['ok']) ?></td>
            <td><strong><?= e($item['label']) ?></strong></td>
            <td><?= e($item['rincian']) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <div class="garis-pemisah"></div>
    <p class="label-kecil">Akun awal</p>
    <p class="teks-kecil">Nama pengguna <strong>admin</strong>, kata sandi sementara <strong>SiDiPa@2026</strong>. Kata sandi wajib diganti pada login pertama.</p>
    <div class="baris-tombol">
      <a class="tombol tombol-utama" href="<?= e(url('pages/login.php')) ?>">Ke halaman masuk</a>
      <a class="tombol tombol-putih" href="<?= e(url('verifikasi.php')) ?>">Verifikasi sertifikat</a>
    </div>
  </main>
  <?= pwa_skrip() ?>
</body>
</html>
