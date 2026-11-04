<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

if (auth_pengguna() !== null) {
    alihkan('index.php');
}

$pesanGalat = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (csrf_valid()) {
        $hasil = auth_masuk((string) ($_POST['username'] ?? ''), (string) ($_POST['kata_sandi'] ?? ''));
        if ($hasil['ok']) {
            alihkan('index.php');
        }
        $pesanGalat = $hasil['pesan'];
    } else {
        /* Cookie sesi tidak ikut terkirim (halaman dipulihkan dari cache,
           preview disematkan lintas-situs, atau cookie ditahan browser).
           Alih-alih halaman 403 yang buntu, siapkan token baru dan tampilkan
           ulang form agar pengguna cukup menekan Masuk sekali lagi. */
        log_akses('csrf_ditolak', 'keamanan');
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $pesanGalat = 'Sesi formulir sudah kedaluwarsa. Token keamanan baru sudah disiapkan; '
            . 'silakan tekan Masuk sekali lagi. Bila berulang, muat ulang halaman atau buka aplikasi di tab baru.';
    }
}

$namaSekolah = pengaturan('nama_sekolah', 'SMK SiDiPa');
$didalamFrame = strtolower((string) ($_SERVER['HTTP_SEC_FETCH_DEST'] ?? '')) === 'iframe'
    || strtolower((string) ($_SERVER['HTTP_SEC_FETCH_SITE'] ?? '')) === 'cross-site';
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <?= pwa_meta() ?>
  <title>Masuk - SiDiPa</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= e(asset('css/sidipa.css')) ?>">
</head>
<body class="halaman-login">
  <main class="kartu-login kartu">
    <div class="merek">
      <span class="kotak-logo"><?= ikon('sekolah', 26) ?></span>
      <span class="teks-merek">SiDiPa<small><?= e($namaSekolah) ?></small></span>
    </div>
    <p class="label-kecil">Siswa Digital Pasport</p>
    <h1>Masuk ke akun</h1>
    <p class="teks-kecil">Penilaian karakter, kehadiran QR, dan sertifikat digital.</p>

    <?php if ($pesanGalat !== ''): ?>
      <div class="pesan pesan-galat" role="alert"><?= ikon('peringatan', 20) ?><span><?= e($pesanGalat) ?></span></div>
    <?php endif; ?>
    <?php if (!https_aktif()): ?>
      <div class="pesan pesan-info" role="status"><?= ikon('info', 20) ?><span>Kamera pemindai QR memerlukan HTTPS. Aktifkan SSL di cPanel.</span></div>
    <?php endif; ?>
    <?php if ($didalamFrame): ?>
      <div class="pesan pesan-info" role="status"><?= ikon('info', 20) ?>
        <span>SiDiPa terbuka di dalam frame. Bila login gagal berulang kali, buka aplikasi di tab baru agar cookie sesi tersimpan.</span></div>
    <?php endif; ?>

    <form method="post" action="<?= e(url('pages/login.php')) ?>" autocomplete="off">
      <?= csrf_field() ?>
      <div class="form-baris">
        <label for="username">Username</label>
        <input class="input" id="username" name="username" type="text" required autocomplete="username" placeholder="mis. ahmad.rizky" autofocus>
        <p class="bantuan">Siswa masuk dengan <strong>username siswa</strong>, bukan NIS/NISN. Guru, wali, dan staf memakai username akun masing-masing.</p>
      </div>
      <div class="form-baris">
        <label for="kata_sandi">Kata sandi</label>
        <input class="input" id="kata_sandi" name="kata_sandi" type="password" required autocomplete="current-password">
      </div>
      <div class="form-baris lebar-penuh">
        <label style="display:flex;gap:10px;align-items:flex-start;cursor:pointer;font-weight:600">
          <input type="checkbox" name="jangan_ingat" value="1" style="width:20px;height:20px;min-height:0;margin-top:2px">
          <span>Jangan simpan login di perangkat ini</span>
        </label>
        <p class="bantuan">Login tersimpan otomatis di HP/PC ini dan batas <?= (int) SIDIPA_INGAT_HARI ?> hari <strong>diperpanjang setiap kali Anda membuka SiDiPa</strong>. Anda cukup masuk sekali; login baru diminta lagi bila tidak membuka aplikasi selama <?= (int) SIDIPA_INGAT_HARI ?> hari berturut-turut. Centang kotak di atas bila memakai perangkat bersama.</p>
      </div>
      <div class="form-aksi">
        <button class="tombol tombol-utama tombol-penuh" type="submit">Masuk <?= ikon('masuk', 20) ?></button>
      </div>
    </form>
    <div class="tautan-bawah">
      <a href="<?= e(url('verifikasi.php')) ?>">Verifikasi sertifikat</a>
      <span>Zona waktu Asia/Jakarta</span>
    </div>
    <div class="pwa-login">
      <span class="status-jaringan" data-status-jaringan hidden role="status"></span>
      <button type="button" class="tombol tombol-putih tombol-penuh tombol-pasang" data-pasang-pwa hidden>
        <?= ikon('unduh', 20) ?> Pasang aplikasi SiDiPa
      </button>
    </div>
  </main>
  <?= pwa_skrip() ?>
  <script>
    /* Halaman yang dipulihkan dari back/forward cache membawa token lama. */
    window.addEventListener('pageshow', function (e) {
      if (e.persisted) { window.location.reload(); }
    });
  </script>
</body>
</html>
