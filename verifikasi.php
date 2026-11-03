<?php
declare(strict_types=1);

/*
 | Verifikasi sertifikat publik - TANPA LOGIN.
 | Hanya menampilkan status, nama, kelas, semester, dan skor per aspek.
 | DILARANG menampilkan riwayat pelanggaran, nama pencatat, nomor WhatsApp,
 | atau data pribadi lainnya.
 */

require_once __DIR__ . '/app/bootstrap.php';

$kode = teks($_GET['kode'] ?? ($_POST['kode'] ?? ''), 60);
$penggunaPublik = auth_pengguna();
$bisaBatalkan = $penggunaPublik !== null && $penggunaPublik['peran'] === 'admin';
$sertifikat = null;
$pesanGalat = '';
$pesanAksi = '';
$data = [];

// Admin yang sedang login dapat membatalkan sertifikat dari halaman publik ini.
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_wajib();
    if (!$bisaBatalkan) {
        log_akses('batalkan_sertifikat_ditolak', 'verifikasi');
        auth_403('Hanya admin yang dapat membatalkan sertifikat.');
    }
    if (aksi_post() === 'batalkan') {
        $kodeAksi = teks($_POST['kode'] ?? '', 60);
        $target = sertifikat_dari_kode($kodeAksi);
        if ($target === null) {
            $pesanAksi = 'Sertifikat tidak ditemukan.';
        } else {
            $hasil = batalkan_sertifikat((int) $target['id'], (int) $penggunaPublik['id'], post('alasan_batal', '', 255));
            $pesanAksi = $hasil['pesan'];
        }
        $kode = $kodeAksi;
    }
}

if ($kode !== '') {
    try {
        $sertifikat = db_satu(
            'SELECT sert.id, sert.kode, sert.status, sert.data_json, sert.tanggal_terbit,
                    sw.nama AS nama_siswa, sw.nis, k.nama AS kelas,
                    sm.nama AS nama_semester, sm.tahun_ajaran, sm.semester
             FROM sertifikat sert
             JOIN siswa sw ON sw.id = sert.siswa_id
             LEFT JOIN kelas k ON k.id = sw.kelas_id
             JOIN semester sm ON sm.id = sert.semester_id
             WHERE sert.kode = ? LIMIT 1',
            [$kode]
        );
        if ($sertifikat !== null) {
            $json = json_decode((string) $sertifikat['data_json'], true);
            $data = is_array($json['aspek'] ?? null) ? $json['aspek'] : (is_array($json) ? $json : []);
        } else {
            $pesanGalat = 'Kode sertifikat tidak ditemukan.';
        }
    } catch (Throwable $galat) {
        catat_kesalahan('verifikasi: ' . $galat->getMessage());
        $pesanGalat = 'Layanan verifikasi belum siap. Coba beberapa saat lagi.';
    }
}

$namaSekolah = pengaturan('nama_sekolah', 'SMK SiDiPa');
$sah = $sertifikat !== null && $sertifikat['status'] === 'terbit';
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <?= pwa_meta() ?>
  <title>Verifikasi Sertifikat - SiDiPa</title>
  <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= e(asset('css/sidipa.css')) ?>">
</head>
<body class="halaman-sederhana">
  <main class="kotak-sederhana">
    <div class="merek" style="margin-bottom:14px">
      <span class="kotak-logo"><?= ikon('sekolah', 26) ?></span>
      <span class="teks-merek">SiDiPa<small><?= e($namaSekolah) ?></small></span>
    </div>
    <p class="label-kecil">Layanan publik</p>
    <h1>Verifikasi sertifikat</h1>

    <form method="get" action="<?= e(url('verifikasi.php')) ?>" style="margin-bottom:16px">
      <div class="form-baris">
        <label for="kode">Kode sertifikat</label>
        <input class="input" id="kode" name="kode" type="text" value="<?= e($kode) ?>" placeholder="SDP-20261-123456-AB12" required>
      </div>
      <div class="form-aksi"><button class="tombol tombol-utama" type="submit"><?= ikon('cari', 20) ?> Periksa</button></div>
    </form>

    <?php if ($pesanGalat !== ''): ?>
      <div class="pesan pesan-galat" role="alert"><?= ikon('peringatan', 20) ?><span><?= e($pesanGalat) ?></span></div>
    <?php endif; ?>
    <?php if ($pesanAksi !== ''): ?>
      <div class="pesan pesan-info" role="status"><?= ikon('info', 20) ?><span><?= e($pesanAksi) ?></span></div>
    <?php endif; ?>

    <?php if ($sertifikat !== null): ?>
      <div class="pesan <?= $sah ? 'pesan-sukses' : 'pesan-galat' ?>" role="status">
        <?= ikon($sah ? 'centang' : 'silang', 20) ?>
        <span><?= $sah ? 'SERTIFIKAT SAH' : 'SERTIFIKAT DIBATALKAN' ?></span>
      </div>
      <div class="tabel-bungkus" style="margin-bottom:14px">
        <table class="tabel">
          <tbody>
            <tr><th>Nama</th><td><?= e($sertifikat['nama_siswa']) ?></td></tr>
            <tr><th>Kelas</th><td><?= e($sertifikat['kelas'] ?? '-') ?></td></tr>
            <tr><th>Tahun ajaran</th><td><?= e($sertifikat['tahun_ajaran']) ?> / Semester <?= e($sertifikat['semester']) ?></td></tr>
            <tr><th>Kode</th><td class="mono"><?= e($sertifikat['kode']) ?></td></tr>
            <tr><th>Tanggal terbit</th><td><?= e(tanggal_indonesia($sertifikat['tanggal_terbit'], false)) ?></td></tr>
          </tbody>
        </table>
      </div>
      <p class="label-kecil">Skor per aspek (snapshot saat terbit)</p>
      <div class="tabel-bungkus">
        <table class="tabel sertifikat-skor">
          <thead><tr><th>Aspek</th><th>Skor</th><th>Persentase</th><th>Predikat</th></tr></thead>
          <tbody>
          <?php foreach ($data as $baris): ?>
            <tr>
              <td><?= e($baris['aspek'] ?? $baris['nama'] ?? '-') ?></td>
              <td class="mono"><?= e($baris['skor'] ?? '-') ?></td>
              <td class="mono"><?= e($baris['persentase'] ?? '-') ?>%</td>
              <td><strong><?= e($baris['predikat'] ?? '-') ?></strong></td>
            </tr>
          <?php endforeach; ?>
          <?php if ($data === []): ?><tr><td colspan="4" class="tabel-kosong">Data aspek belum tersedia.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
      <p class="teks-kecil" style="margin-top:12px">Halaman ini hanya menampilkan data skor dan status sertifikat. Riwayat catatan dan data pribadi lain tidak pernah ditampilkan.</p>

      <?php if ($bisaBatalkan && $sah): ?>
        <div class="form-kartu kartu margin-atas">
          <p class="label-kecil">Khusus admin</p>
          <h2>Batalkan sertifikat</h2>
          <form method="post" action="<?= e(url('verifikasi.php')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="aksi" value="batalkan">
            <input type="hidden" name="kode" value="<?= e($sertifikat['kode']) ?>">
            <div class="form-baris">
              <label for="alasan_batal">Alasan pembatalan <span class="wajib">wajib</span></label>
              <input class="input" id="alasan_batal" name="alasan_batal" maxlength="255" required placeholder="Mis. salah data identitas">
            </div>
            <div class="form-aksi">
              <button class="tombol tombol-utama" type="submit" data-konfirmasi="Batalkan sertifikat ini? Status publik akan berubah menjadi dibatalkan.">
                <?= ikon('silang', 20) ?> Batalkan sertifikat
              </button>
            </div>
          </form>
          <p class="teks-kecil" style="margin-bottom:0">Alasan, pelaku, dan waktu pembatalan tersimpan sebagai jejak audit. Snapshot skor tidak diubah.</p>
        </div>
      <?php endif; ?>
    <?php endif; ?>

    <div class="baris-tombol" style="margin-top:16px">
      <a class="tombol tombol-putih" href="<?= e(url('pages/login.php')) ?>">Masuk SiDiPa</a>
    </div>
    <div class="pwa-login">
      <span class="status-jaringan" data-status-jaringan hidden role="status"></span>
      <button type="button" class="tombol tombol-putih tombol-penuh tombol-pasang" data-pasang-pwa hidden>
        <?= ikon('unduh', 20) ?> Pasang aplikasi SiDiPa
      </button>
    </div>
  </main>
  <?= pwa_skrip() ?>
</body>
</html>
