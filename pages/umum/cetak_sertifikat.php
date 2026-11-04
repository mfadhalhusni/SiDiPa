<?php
declare(strict_types=1);

/*
 | Tahap 4 — Cetak sertifikat A4 landscape (CSS @page, tanpa library PDF).
 | HANYA sertifikat berstatus `terbit` yang boleh dicetak.
 | Siswa / wali: cetak ditahan sampai admin mengaktifkan semester baru.
 | Akses: admin / BK / kepala sekolah (semua), siswa / wali (anaknya sendiri).
 | Data diambil dari SNAPSHOT data_json, bukan skor berjalan, sehingga nilai
 | tidak berubah bila ada catatan baru setelah penerbitan.
 */

require_once __DIR__ . '/../../app/bootstrap.php';

$pengguna = auth_wajib();
$peran = (string) $pengguna['peran'];
if (!in_array($peran, ['admin', 'bk', 'kepsek', 'siswa', 'wali'], true)) {
    log_akses('cetak_sertifikat_ditolak', 'peran');
    auth_403('Hanya admin, BK, kepala sekolah, siswa, atau wali murid yang dapat mencetak sertifikat.');
}

$kode = teks($_GET['kode'] ?? '', 60);
$sertifikat = sertifikat_dari_kode($kode);
if ($sertifikat === null) {
    http_response_code(404);
    auth_403('Kode sertifikat tidak ditemukan.');
}

if (in_array($peran, ['siswa', 'wali'], true)) {
    $milik = siswa_akun((int) $pengguna['id'], $peran);
    if ($milik === null || (int) $milik['id'] !== (int) $sertifikat['siswa_id']) {
        log_akses('cetak_sertifikat_ditolak', 'sertifikat', (int) $sertifikat['id']);
        auth_403('Sertifikat ini bukan milik Anda.');
    }
}

$json = json_decode((string) $sertifikat['data_json'], true);
if (!is_array($json)) {
    $json = [];
}
$sekolah = is_array($json['sekolah'] ?? null) ? $json['sekolah'] : [];
$identitas = is_array($json['siswa'] ?? null) ? $json['siswa'] : [];
$semester = is_array($json['semester'] ?? null) ? $json['semester'] : [];
$aspek = is_array($json['aspek'] ?? null) ? $json['aspek'] : [];

$ambil = static function (array $sumber, string $kunci, string $default = ''): string {
    return e((string) ($sumber[$kunci] ?? $default));
};
$gambar = static function (string $jalur) use ($sekolah): string {
    $nilai = (string) ($jalur);
    return e(preg_match('#^https?://#i', $nilai) ? $nilai : url($nilai));
};

$sah = (string) $sertifikat['status'] === 'terbit';
// Siswa/wali hanya boleh MELIHAT sampai semester sertifikat digantikan
// semester aktif yang baru. Peran lain tetap dapat mencetak seperti biasa.
$terbatas = sertifikat_peran_terbatas($peran);
$bolehCetak = $sah && (!$terbatas
    || sertifikat_sudah_ganti_semester((int) ($sertifikat['semester_id'] ?? 0)));
$terkunci = $sah && !$bolehCetak;
$urlVerifikasi = url_publik('verifikasi.php?kode=' . rawurlencode((string) $sertifikat['kode']));
$urlKembali = $peran === 'siswa' || $peran === 'wali'
    ? 'pages/siswa/sertifikat.php'
    : ($peran === 'bk' || $peran === 'kepsek' ? 'pages/umum/laporan.php' : 'pages/admin/sertifikat.php');
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <?= pwa_meta() ?>
  <title><?= $sah ? 'Sertifikat ' : 'Sertifikat tidak sah - ' ?><?= $ambil($identitas, 'nama') ?> - SiDiPa</title>
  <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= e(asset('css/sidipa.css')) ?>">
  <link rel="stylesheet" href="<?= e(asset('css/cetak.css')) ?>">
</head>
<body class="cetak-sertifikat<?= $terkunci ? ' cetak-terkunci' : '' ?>">
  <div class="cetak-alat">
    <p class="petunjuk-cetak">
      <?php if ($terkunci): ?>
        Sertifikat <strong><?= e($sertifikat['kode']) ?></strong> berstatus <strong>TERBIT</strong> dan dapat <strong>dilihat</strong>.
        Cetak belum tersedia karena semester sertifikat masih berjalan; tombol cetak aktif setelah admin mengaktifkan semester baru.
      <?php elseif ($sah): ?>
        Sertifikat <strong><?= e($sertifikat['kode']) ?></strong> berstatus <strong>TERBIT</strong>.
        Cetak dengan kertas <strong>A4 landscape</strong>, skala 100%, dan matikan header/footer browser.
      <?php else: ?>
        Sertifikat ini berstatus <strong>DIBATALKAN</strong> sehingga tidak dapat dicetak.
      <?php endif; ?>
    </p>
    <div class="baris-tombol">
      <?php if ($bolehCetak): ?>
        <button class="tombol tombol-utama" type="button" data-cetak><?= ikon('cetak', 20) ?> Cetak / Simpan PDF</button>
      <?php endif; ?>
      <a class="tombol tombol-putih" href="<?= e(url($urlKembali)) ?>"><?= ikon('panah_kiri', 18) ?> Kembali</a>
    </div>
  </div>

  <?php if (!$sah): ?>
    <div class="cetak-alat"><p class="petunjuk-cetak">Tombol cetak hanya aktif untuk sertifikat berstatus terbit. Hubungi admin bila pembatalan tidak sesuai.</p></div>
  <?php else: ?>
    <article class="kertas-sertifikat">
      <div class="bingkai-dalam">
        <header class="kepala-sertifikat">
          <img class="logo" src="<?= $gambar((string) ($sekolah['logo'] ?? 'public/img/logo-sidipa.svg')) ?>" alt="Logo sekolah">
          <div>
            <p class="label-kecil">Siswa Digital Pasport</p>
            <p class="nama-sekolah"><?= $ambil($sekolah, 'nama', 'SMK SiDiPa') ?></p>
            <p class="label-kecil"><?= $ambil($sekolah, 'alamat') ?></p>
          </div>
        </header>

        <h1 class="judul-sertifikat">Sertifikat Karakter Siswa</h1>
        <p class="subjudul-sertifikat">Diberikan atas pencapaian karakter pada semester <?= $ambil($semester, 'semester', '1') ?> tahun ajaran <?= $ambil($semester, 'tahun_ajaran') ?></p>

        <div class="identitas-sertifikat">
          <div class="pasangan"><span class="kunci">Nama</span><span><?= $ambil($identitas, 'nama') ?></span></div>
          <div class="pasangan"><span class="kunci">NIS</span><span><?= $ambil($identitas, 'nis') ?></span></div>
          <div class="pasangan"><span class="kunci">Kelas</span><span><?= $ambil($identitas, 'kelas') ?></span></div>
          <div class="pasangan"><span class="kunci">Semester</span><span><?= $ambil($semester, 'nama') ?></span></div>
        </div>

        <table class="sertifikat-skor">
          <thead><tr><th>Aspek karakter</th><th>Skor</th><th>Persentase</th><th>Predikat</th><th>Keterangan</th></tr></thead>
          <tbody>
          <?php foreach ($aspek as $a): ?>
            <tr>
              <td><?= e((string) ($a['aspek'] ?? '-')) ?></td>
              <td class="mono"><?= e((string) ($a['skor'] ?? 0)) ?> / <?= e((string) ($a['target'] ?? 100)) ?></td>
              <td class="mono"><?= e((string) ($a['persentase'] ?? 0)) ?>%</td>
              <td class="predikat"><?= e((string) ($a['predikat'] ?? '-')) ?></td>
              <td><?= e((string) ($a['keterangan'] ?? '-')) ?></td>
            </tr>
          <?php endforeach; ?>
          <?php if ($aspek === []): ?><tr><td colspan="5">Data aspek belum tersedia.</td></tr><?php endif; ?>
          </tbody>
        </table>
        <p class="subjudul-sertifikat" style="margin:0 0 5mm;text-align:left">Predikat pada sertifikat dihitung per aspek terhadap target semester.</p>

        <footer class="kaki-sertifikat">
          <div>
            <div class="qr-sertifikat" role="img" aria-label="QR verifikasi sertifikat" data-qr="<?= e($urlVerifikasi) ?>"></div>
            <p class="kode-sertifikat" style="margin-top:2mm">Pindai untuk verifikasi publik.<br>
              <span class="kode mono"><?= e($sertifikat['kode']) ?></span><br>
              Diterbitkan <?= e(tanggal_indonesia($sertifikat['tanggal_terbit'], false)) ?></p>
          </div>
          <div class="tanda-tangan">
            <img src="<?= $gambar((string) ($sekolah['ttd_wali'] ?? 'public/img/tanda-tangan.svg')) ?>" alt="Tanda tangan wali kelas">
            <p>Wali kelas<br><span class="nama"><?= e((string) ($json['wali_kelas'] ?? 'Wali Kelas')) ?></span></p>
          </div>
          <div class="tanda-tangan">
            <img src="<?= $gambar((string) ($sekolah['ttd_kepsek'] ?? 'public/img/tanda-tangan.svg')) ?>" alt="Tanda tangan kepala sekolah">
            <p>Kepala sekolah<br><span class="nama"><?= $ambil($sekolah, 'nama_kepsek', 'Kepala Sekolah') ?></span><br>
              <span class="mono"><?= $ambil($sekolah, 'nip_kepsek') ?></span></p>
          </div>
        </footer>
      </div>
    </article>
  <?php endif; ?>

  <script src="<?= e(asset('js/qrcode.min.js')) ?>"></script>
  <script src="<?= e(asset('js/qr-sidipa.js')) ?>"></script>
  <?= pwa_skrip() ?>
</body>
</html>
