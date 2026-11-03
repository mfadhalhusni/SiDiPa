<?php
declare(strict_types=1);

/* Dashboard siswa: skor per aspek, predikat, dan catatan MILIKNYA sendiri. */

require_once __DIR__ . '/../../app/bootstrap.php';

$pengguna = auth_wajib(['siswa']);
$siswa = siswa_akun((int) $pengguna['id'], 'siswa');
$semester = semester_aktif();
$semesterId = (int) ($semester['id'] ?? 0);
$skor = [];
$terbaru = [];
$mingguan = [];
if ($siswa !== null && $semesterId > 0) {
    $skor = skor_per_aspek((int) $siswa['id'], $semesterId);
    $terbaru = array_slice(riwayat_catatan((int) $siswa['id'], $semesterId, 5), 0, 5);
    $mingguan = grafik_mingguan_siswa((int) $siswa['id'], $semesterId, 8);
}
$warna = ['bg-koral', 'bg-kuning', 'bg-toska', 'bg-ungu', 'bg-oranye', 'bg-biru'];

halaman_atas('Beranda siswa', '', ['nav' => 'beranda']);
?>
<?php if ($siswa === null): ?>
  <div class="pesan pesan-galat" role="alert"><?= ikon('peringatan', 20) ?><span>Akun siswa belum terhubung ke data siswa. Hubungi admin.</span></div>
<?php else: ?>
  <section class="kartu bg-ungu margin-bawah">
    <p class="label-kecil">Halo, <?= e($siswa['nama']) ?>!</p>
    <h2 style="margin:0"><?= e($siswa['kelas'] ?? 'Kelas belum diatur') ?></h2>
    <p class="teks-kecil" style="margin:4px 0 0">NIS <?= e($siswa['nis']) ?> · <?= e($semester['nama'] ?? 'Semester belum aktif') ?></p>
  </section>

  <?= bagian_judul('Skor karakter saya', 'Predikat dihitung per aspek terhadap target semester.') ?>
  <div class="skor-grid">
    <?php foreach ($skor as $i => $s): ?>
      <div class="kartu kartu-skor <?= e($warna[$i % count($warna)]) ?>">
        <p class="label-kecil"><?= e($s['aspek']) ?></p>
        <span class="predikat"><?= e($s['predikat']) ?></span>
        <span class="angka mono">Skor <?= e($s['skor']) ?> / <?= e($s['target']) ?></span>
        <p class="teks-kecil" style="margin:4px 0 0"><?= e($s['persentase']) ?>% · <?= e($s['keterangan']) ?></p>
      </div>
    <?php endforeach; ?>
    <?php if ($skor === []): ?><div class="kartu tabel-kosong">Belum ada semester aktif.</div><?php endif; ?>
  </div>

  <?php if ($mingguan !== []): ?>
    <?php
    $kiri = 46; $kanan = 576; $atas = 18; $bawah = 128;
    $deret = array_map('intval', array_column($mingguan, 'kumulatif'));
    $min = min(array_merge([0], $deret));
    $max = max(array_merge([0], $deret));
    if ($max === $min) { $max = $min + 10; }
    $skalaY = static function (float $v) use ($min, $max, $atas, $bawah): float {
        return $bawah - (($v - $min) / ($max - $min)) * ($bawah - $atas);
    };
    $titik = [];
    $posisiX = [];
    $jumlah = count($mingguan);
    foreach ($mingguan as $i => $m) {
        $x = $kiri + ($jumlah > 1 ? ($i * ($kanan - $kiri) / ($jumlah - 1)) : ($kanan - $kiri) / 2);
        $posisiX[] = $x;
        $titik[] = round($x, 1) . ',' . round($skalaY((float) $m['kumulatif']), 1);
    }
    $yNol = round($skalaY(0), 1);
    $terakhir = end($mingguan);
    ?>
    <?= bagian_judul('Perkembangan mingguan', 'Akumulasi poin semua aspek pada 8 minggu terakhir semester ini.') ?>
    <div class="kartu margin-bawah" style="padding:14px 16px">
      <svg viewBox="0 0 600 170" role="img" aria-label="Grafik akumulasi poin per minggu" style="width:100%;height:auto;display:block">
        <rect x="0" y="0" width="600" height="170" fill="#fff"/>
        <?php for ($g = 0; $g <= 2; $g++): ?>
          <?php $yg = round($atas + $g * ($bawah - $atas) / 2, 1); ?>
          <line x1="<?= $kiri ?>" y1="<?= e((string) $yg) ?>" x2="<?= $kanan ?>" y2="<?= e((string) $yg) ?>" stroke="#d9d9d9" stroke-width="1"/>
          <text x="40" y="<?= e((string) ($yg + 4)) ?>" text-anchor="end" font-size="11" fill="#444">
            <?= e((string) (int) round($max - $g * ($max - $min) / 2)) ?>
          </text>
        <?php endfor; ?>
        <?php if ($min < 0): ?>
          <line x1="<?= $kiri ?>" y1="<?= e((string) $yNol) ?>" x2="<?= $kanan ?>" y2="<?= e((string) $yNol) ?>" stroke="#000" stroke-width="2" stroke-dasharray="5 4"/>
        <?php endif; ?>
        <line x1="<?= $kiri ?>" y1="<?= $bawah ?>" x2="<?= $kanan ?>" y2="<?= $bawah ?>" stroke="#000" stroke-width="2"/>
        <line x1="<?= $kiri ?>" y1="<?= $atas ?>" x2="<?= $kiri ?>" y2="<?= $bawah ?>" stroke="#000" stroke-width="2"/>
        <polyline points="<?= e(implode(' ', $titik)) ?>" fill="none" stroke="#000" stroke-width="3" stroke-linejoin="round" stroke-linecap="round"/>
        <?php foreach ($titik as $t): ?>
          <?php [$cx, $cy] = explode(',', $t); ?>
          <circle cx="<?= e($cx) ?>" cy="<?= e($cy) ?>" r="4.5" fill="#fff" stroke="#000" stroke-width="2.5"/>
        <?php endforeach; ?>
        <?php foreach ($mingguan as $i => $m): ?>
          <?php if ($i % 2 === 0 || $i === $jumlah - 1): ?>
            <text x="<?= e((string) round($posisiX[$i], 1)) ?>" y="148" text-anchor="middle" font-size="11" fill="#333"><?= e($m['label']) ?></text>
          <?php endif; ?>
        <?php endforeach; ?>
        <text x="<?= $kanan ?>" y="<?= e((string) round($skalaY((float) $terakhir['kumulatif']) - 12, 1)) ?>"
              text-anchor="end" font-size="13" font-weight="700" fill="#000">
          <?= ((int) $terakhir['kumulatif'] > 0 ? '+' : '') . (int) $terakhir['kumulatif'] ?> poin
        </text>
      </svg>
      <p class="teks-kecil" style="margin:6px 0 0">Angka kumulatif: <strong><?= e((string) $terakhir['kumulatif']) ?></strong>.
        Poin minggu ini: <strong><?= ((int) $terakhir['poin'] > 0 ? '+' : '') . (int) $terakhir['poin'] ?></strong>.
        Grafik hanya menampilkan angka agregat, tanpa nama pencatat.</p>
    </div>
  <?php endif; ?>

  <div class="baris-tombol margin-atas">
    <a class="tombol tombol-utama" href="<?= e(url('pages/siswa/riwayat.php')) ?>"><?= ikon('catatan', 20) ?> Riwayat catatan saya</a>
    <a class="tombol tombol-putih" href="<?= e(url('pages/siswa/banding.php')) ?>"><?= ikon('banding', 20) ?> Ajukan banding</a>
    <a class="tombol tombol-putih" href="<?= e(url('pages/umum/rekap.php')) ?>"><?= ikon('rekap', 20) ?> Rekap</a>
    <a class="tombol tombol-putih" href="<?= e(url('pages/siswa/sertifikat.php')) ?>"><?= ikon('sertifikat', 20) ?> Sertifikat saya</a>
    <a class="tombol tombol-putih" href="<?= e(url('pages/siswa/kartu.php')) ?>"><?= ikon('qr', 20) ?> Kartu QR saya</a>
  </div>

  <?= bagian_judul('Catatan terbaru', 'Hanya kategori, tanggal, poin, dan deskripsi.') ?>
  <div class="tabel-bungkus">
    <table class="tabel">
      <thead><tr><th>Tanggal</th><th>Aspek / kategori</th><th>Poin</th><th>Deskripsi</th></tr></thead>
      <tbody>
      <?php foreach ($terbaru as $c): ?>
        <tr>
          <td class="mono"><?= e(tanggal_indonesia($c['tanggal'], false)) ?></td>
          <td><strong><?= e($c['aspek']) ?></strong><br><span class="teks-kecil"><?= e($c['kejadian']) ?></span></td>
          <td class="mono"><strong><?= ((int) $c['poin'] > 0 ? '+' : '') . (int) $c['poin'] ?></strong></td>
          <td><?= e($c['keterangan'] ?? '-') ?>
            <?php if ($c['status'] === 'dibatalkan'): ?><span class="chip badge-abu">Dibatalkan</span><?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if ($terbaru === []): ?><tr><td colspan="4" class="tabel-kosong">Belum ada catatan pada semester ini.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>
<?php halaman_bawah(); ?>
