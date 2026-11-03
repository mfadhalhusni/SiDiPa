<?php
declare(strict_types=1);

/* Tahap 3 — Kartu QR pribadi siswa (juga bisa dibuka wali murid untuk anaknya). */

require_once __DIR__ . '/../../app/bootstrap.php';

$pengguna = auth_wajib(['siswa', 'wali']);
$siswa = siswa_akun((int) $pengguna['id'], (string) $pengguna['peran']);
$namaSekolah = (string) pengaturan('nama_sekolah', 'SMK SiDiPa');
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <?= pwa_meta() ?>
  <title>Kartu QR Saya - SiDiPa</title>
  <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= e(asset('css/sidipa.css')) ?>">
  <link rel="stylesheet" href="<?= e(asset('css/kartu.css')) ?>">
</head>
<body class="cetak-kartu">
  <div class="cetak-alat">
    <p class="petunjuk-cetak">
      Tunjukkan QR ini kepada guru/petugas saat scan masuk dan sholat. QR berisi token unik, bukan NIS.
      Jika kartu hilang, minta admin membuat ulang token.
    </p>
    <div class="baris-tombol">
      <?php if ($siswa !== null): ?>
        <button class="tombol tombol-utama" type="button" data-cetak><?= ikon('cetak', 20) ?> Cetak / Simpan PDF</button>
      <?php endif; ?>
      <a class="tombol tombol-putih" href="<?= e(url(auth_landing((string) $pengguna['peran']))) ?>"><?= ikon('panah_kiri', 18) ?> Kembali</a>
    </div>
  </div>

  <?php if ($siswa === null): ?>
    <div class="cetak-alat"><p class="petunjuk-cetak">Akun belum terhubung ke data siswa. Hubungi admin.</p></div>
  <?php else: ?>
    <div class="lembar-kartu">
      <article class="kartu-siswa">
        <div class="info">
          <p class="label-kecil">Siswa Digital Pasport</p>
          <p class="nama"><?= e($siswa['nama']) ?></p>
          <div class="baris">NIS <strong class="mono"><?= e($siswa['nis']) ?></strong></div>
          <div class="baris">Kelas <strong><?= e($siswa['kelas'] ?? '-') ?></strong></div>
          <p class="kaki"><?= e(mb_strtoupper(mb_substr($namaSekolah, 0, 34, 'UTF-8'), 'UTF-8')) ?></p>
        </div>
        <div class="qr" role="img" aria-label="QR kartu siswa" data-qr="<?= e($siswa['qr_token']) ?>"></div>
      </article>
    </div>
  <?php endif; ?>

  <script src="<?= e(asset('js/qrcode.min.js')) ?>"></script>
  <script src="<?= e(asset('js/qr-sidipa.js')) ?>"></script>
  <?= pwa_skrip() ?>
</body>
</html>
