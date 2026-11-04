<?php
declare(strict_types=1);

/*
 | Tahap 3 — Cetak kartu siswa 85,6 × 54 mm per kelas.
 | QR berisi qr_token, BUKAN NIS (lihat bagian 7 PDF). Pustaka QR disertakan
 | lokal di public/js/qrcode.min.js agar tetap berfungsi saat offline.
 | Halaman ini sengaja berdiri sendiri tanpa kerangka aplikasi supaya hasil
 | cetak bersih; @page di public/css/kartu.css mengatur ukuran kartu.
 */

require_once __DIR__ . '/../../app/bootstrap.php';

auth_wajib(['admin']);

$daftarKelas = daftar_kelas(false);
$kelasId = bilangan($_GET['kelas'] ?? 0);
$siswaId = bilangan($_GET['siswa'] ?? 0);
$namaSekolah = (string) pengaturan('nama_sekolah', 'SMK SiDiPa');

$syarat = ['s.aktif = 1'];
$param = [];
if ($siswaId > 0) {
    $syarat[] = 's.id = ?';
    $param[] = $siswaId;
} elseif ($kelasId > 0) {
    $syarat[] = 's.kelas_id = ?';
    $param[] = $kelasId;
}
$daftarSiswa = db_semua(
    'SELECT s.id, s.nis, s.nama, s.qr_token, COALESCE(k.nama, \'-\') AS kelas, k.tingkat, k.jurusan,
            COALESCE(p.username, \'-\') AS username
     FROM siswa s LEFT JOIN kelas k ON k.id = s.kelas_id
     LEFT JOIN pengguna p ON p.id = s.pengguna_id
     WHERE ' . implode(' AND ', $syarat) . '
     ORDER BY k.nama, s.nama LIMIT 300',
    $param
);
$labelKelas = 'Semua kelas';
foreach ($daftarKelas as $k) {
    if ((int) $k['id'] === $kelasId) {
        $labelKelas = (string) $k['nama'];
    }
}
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <?= pwa_meta() ?>
  <?= csrf_meta() ?>
  <title>Cetak Kartu Siswa - SiDiPa</title>
  <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= e(asset('css/sidipa.css')) ?>">
  <link rel="stylesheet" href="<?= e(asset('css/kartu.css')) ?>">
</head>
<body class="cetak-kartu">
  <div class="cetak-alat">
    <p class="petunjuk-cetak">
      <strong><?= e($labelKelas) ?></strong> · <?= count($daftarSiswa) ?> kartu.
      Saat mencetak pilih ukuran kertas sesuai <strong>85,6 × 54 mm</strong>, skala 100%, dan matikan header/footer browser.
      Kartu memuat username login siswa, bukan NIS sebagai kata sandi akun.
    </p>
    <form method="get" action="<?= e(url('pages/admin/kartu.php')) ?>" class="baris-tombol">
      <label class="sembunyi" for="kelas">Kelas</label>
      <select class="input" id="kelas" name="kelas" onchange="this.form.submit()">
        <option value="0">Semua kelas</option>
        <?php foreach ($daftarKelas as $k): ?>
          <option value="<?= e($k['id']) ?>" <?= (int) $k['id'] === $kelasId ? 'selected' : '' ?>><?= e($k['nama']) ?></option>
        <?php endforeach; ?>
      </select>
      <button class="tombol tombol-putih" type="submit"><?= ikon('filter', 18) ?> Tampilkan</button>
    </form>
    <div class="baris-tombol">
      <button class="tombol tombol-utama" type="button" data-cetak><?= ikon('cetak', 20) ?> Cetak / Simpan PDF</button>
      <a class="tombol tombol-putih" href="<?= e(url('pages/admin/siswa.php')) ?>"><?= ikon('panah_kiri', 18) ?> Data siswa</a>
    </div>
  </div>

  <?php if ($daftarSiswa === []): ?>
    <div class="cetak-alat"><p class="petunjuk-cetak">Belum ada siswa aktif pada pilihan ini. Tambahkan siswa atau impor CSV terlebih dahulu.</p></div>
  <?php endif; ?>

  <div class="lembar-kartu">
    <?php foreach ($daftarSiswa as $s): ?>
      <article class="kartu-siswa">
        <div class="info">
          <p class="label-kecil">Siswa Digital Pasport</p>
          <p class="nama"><?= e($s['nama']) ?></p>
          <div class="baris">NIS <strong class="mono"><?= e($s['nis']) ?></strong></div>
          <div class="baris">Kelas <strong><?= e($s['kelas']) ?></strong></div>
          <div class="baris">Login <strong class="mono"><?= e($s['username'] ?? '-') ?></strong></div>
          <p class="kaki"><?= e(mb_strtoupper(mb_substr($namaSekolah, 0, 34, 'UTF-8'), 'UTF-8')) ?></p>
        </div>
        <div class="qr" role="img" aria-label="QR kartu <?= e($s['nama']) ?>" data-qr="<?= e($s['qr_token']) ?>"></div>
      </article>
    <?php endforeach; ?>
  </div>

  <script src="<?= e(asset('js/qrcode.min.js')) ?>"></script>
  <script src="<?= e(asset('js/qr-sidipa.js')) ?>"></script>
  <?= pwa_skrip() ?>
</body>
</html>
