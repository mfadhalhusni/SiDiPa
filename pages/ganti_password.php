<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

$pengguna = auth_wajib([], true);
$pesanGalat = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_wajib();
    $hasil = auth_ganti_sandi(
        (int) $pengguna['id'],
        (string) ($_POST['kata_sandi_lama'] ?? ''),
        (string) ($_POST['kata_sandi_baru'] ?? ''),
        (string) ($_POST['ulangi'] ?? '')
    );
    if ($hasil['ok']) {
        flash($hasil['pesan']);
        alihkan('index.php');
    }
    $pesanGalat = $hasil['pesan'];
}

halaman_atas('Ganti kata sandi', 'Keamanan akun');
?>
<div class="kartu form-kartu" style="max-width:520px">
  <?php if ((int) $pengguna['wajib_ganti_password'] === 1): ?>
    <div class="pesan pesan-info" role="status"><?= ikon('info', 20) ?><span>Ini login pertama Anda. Kata sandi sementara wajib diganti sebelum melanjutkan.</span></div>
  <?php endif; ?>
  <?php if ($pesanGalat !== ''): ?>
    <div class="pesan pesan-galat" role="alert"><?= ikon('peringatan', 20) ?><span><?= e($pesanGalat) ?></span></div>
  <?php endif; ?>
  <form method="post" action="<?= e(url('pages/ganti_password.php')) ?>" autocomplete="off">
    <?= csrf_field() ?>
    <div class="form-grid">
      <div class="form-baris lebar-penuh">
        <label for="lama">Kata sandi lama</label>
        <input class="input" id="lama" name="kata_sandi_lama" type="password" required autocomplete="current-password">
      </div>
      <div class="form-baris">
        <label for="baru">Kata sandi baru <span class="wajib">wajib</span></label>
        <input class="input" id="baru" name="kata_sandi_baru" type="password" minlength="8" required autocomplete="new-password">
        <p class="bantuan">Minimal 8 karakter.</p>
      </div>
      <div class="form-baris">
        <label for="ulangi">Ulangi kata sandi baru</label>
        <input class="input" id="ulangi" name="ulangi" type="password" minlength="8" required autocomplete="new-password">
      </div>
    </div>
    <div class="form-aksi">
      <button class="tombol tombol-utama" type="submit">Simpan kata sandi</button>
      <a class="tombol tombol-putih" href="<?= e(url('pages/logout.php?csrf_token=' . rawurlencode(csrf_token()))) ?>">Keluar</a>
    </div>
  </form>
</div>
<?php halaman_bawah(); ?>
