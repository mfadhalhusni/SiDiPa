<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/bootstrap.php';
require_once __DIR__ . '/../../cron/harian.php';
require_once __DIR__ . '/../../cron/mingguan.php';
require_once __DIR__ . '/../../cron/wa.php';

$pengguna = auth_wajib(['admin']);
$hasilCron = null;
$pesanGalat = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_wajib();
    $aksi = aksi_post();
    $tanggal = post('tanggal', date('Y-m-d'), 10);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal)) {
        $tanggal = date('Y-m-d');
    }
    try {
        if ($aksi === 'harian') {
            $hasilCron = cron_harian($tanggal);
        } elseif ($aksi === 'mingguan') {
            $hasilCron = cron_mingguan($tanggal);
        } elseif ($aksi === 'wa') {
            $hasilCron = cron_wa();
        } else {
            throw new InvalidArgumentException('Jenis proses tidak dikenal.');
        }
    } catch (Throwable $galat) {
        catat_kesalahan('proses_cron: ' . $galat->getMessage());
        $pesanGalat = 'Proses gagal: ' . $galat->getMessage();
    }
}

halaman_atas('Proses manual cron', 'Admin / Otomatisasi', ['nav' => 'rekap']);
?>
<div class="pesan pesan-info" role="status"><?= ikon('info', 20) ?><span>Tombol ini menjalankan fungsi yang sama persis dengan cron cPanel. Proses harian/mingguan aman dijalankan berulang karena memakai kunci unik.</span></div>
<?php if ($pesanGalat !== ''): ?><div class="pesan pesan-galat" role="alert"><?= ikon('peringatan', 20) ?><span><?= e($pesanGalat) ?></span></div><?php endif; ?>
<?php if ($hasilCron !== null): ?>
  <div class="pesan <?= $hasilCron['ok'] ? 'pesan-sukses' : 'pesan-galat' ?>" role="status">
    <?= ikon($hasilCron['ok'] ? 'centang' : 'peringatan', 20) ?><span><?= e($hasilCron['pesan']) ?></span>
  </div>
<?php endif; ?>

<div class="grid grid-2">
  <section class="kartu form-kartu">
    <p class="label-kecil">Harian</p>
    <h2>Proses harian</h2>
    <p class="teks-kecil">Menandai alpa, poin terlambat, dan ketidakhadiran sholat; memasukkan pesan WA ke antrian.</p>
    <form method="post" action="<?= e(url('pages/admin/proses_cron.php')) ?>">
      <?= csrf_field() ?><input type="hidden" name="aksi" value="harian">
      <div class="form-baris"><label for="tanggal_h">Tanggal diproses</label>
        <input class="input" id="tanggal_h" name="tanggal" type="date" value="<?= e(date('Y-m-d')) ?>" required></div>
      <div class="form-aksi"><button class="tombol tombol-utama" type="submit"><?= ikon('cron', 20) ?> Jalankan harian</button></div>
    </form>
  </section>

  <section class="kartu form-kartu">
    <p class="label-kecil">Mingguan</p>
    <h2>Proses mingguan</h2>
    <p class="teks-kecil">Poin minggu bersih, minggu penuh sholat, dan bonus datang pagi kumulatif. Tanggal acuan otomatis dimundurkan ke Senin.</p>
    <form method="post" action="<?= e(url('pages/admin/proses_cron.php')) ?>">
      <?= csrf_field() ?><input type="hidden" name="aksi" value="mingguan">
      <div class="form-baris"><label for="tanggal_m">Tanggal dalam minggu</label>
        <input class="input" id="tanggal_m" name="tanggal" type="date" value="<?= e(date('Y-m-d')) ?>" required></div>
      <div class="form-aksi"><button class="tombol tombol-toska" type="submit"><?= ikon('cron', 20) ?> Jalankan mingguan</button></div>
    </form>
  </section>

  <section class="kartu form-kartu">
    <p class="label-kecil">WhatsApp</p>
    <h2>Kirim antrean WA</h2>
    <p class="teks-kecil">Maksimal <?= e(pengaturan('wa_maks_per_jalan', '20')) ?> pesan per jalan dengan jeda acak <?= e(pengaturan('wa_jeda_min', '3')) ?>–<?= e(pengaturan('wa_jeda_maks', '8')) ?> detik. Proses bisa memakan waktu beberapa menit.</p>
    <form method="post" action="<?= e(url('pages/admin/proses_cron.php')) ?>">
      <?= csrf_field() ?><input type="hidden" name="aksi" value="wa">
      <div class="form-aksi"><button class="tombol tombol-utama" type="submit" data-konfirmasi="Kirim antrean WhatsApp sekarang? Proses berjalan dengan jeda antar pesan."><?= ikon('wa', 20) ?> Kirim sekarang</button></div>
    </form>
  </section>

  <section class="kartu">
    <p class="label-kecil">Perintah cron cPanel</p>
    <h2>Tiga job</h2>
    <div class="tabel-bungkus">
      <table class="tabel">
        <thead><tr><th>Waktu</th><th>Berkas</th></tr></thead>
        <tbody>
          <tr><td>15.00 Senin–Jumat</td><td class="mono">cron/harian.php</td></tr>
          <tr><td>15.30 Jumat</td><td class="mono">cron/mingguan.php</td></tr>
          <tr><td>Tiap 5 menit</td><td class="mono">cron/wa.php</td></tr>
        </tbody>
      </table>
    </div>
    <p class="teks-kecil" style="margin-top:10px">Contoh perintah lengkap ada di README.md.</p>
  </section>
</div>
<?php halaman_bawah(); ?>
