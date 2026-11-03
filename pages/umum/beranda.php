<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/bootstrap.php';

$pengguna = auth_wajib();
$peran = $pengguna['peran'];
if ($peran === 'admin') {
    alihkan('pages/admin/dashboard.php');
}
if ($peran === 'guru') {
    alihkan('pages/guru/beranda.php');
}
if ($peran === 'siswa') {
    alihkan('pages/siswa/beranda.php');
}
if (in_array($peran, ['bk', 'kepsek', 'wali'], true)) {
    alihkan(auth_landing($peran));
}

$semester = semester_aktif();
$semesterId = (int) ($semester['id'] ?? 0);
$siswaSendiri = in_array($peran, ['siswa', 'wali'], true) ? siswa_akun((int) $pengguna['id'], $peran) : null;
$skor = [];
if ($siswaSendiri !== null && $semesterId > 0) {
    $skor = skor_per_aspek((int) $siswaSendiri['id'], $semesterId);
}
$warna = ['bg-koral', 'bg-kuning', 'bg-toska', 'bg-ungu', 'bg-oranye', 'bg-biru'];

try {
    $jumlahSiswa = (int) db_nilai('SELECT COUNT(*) FROM siswa WHERE aktif = 1', [], 0);
    $jumlahCatatan = (int) db_nilai("SELECT COUNT(*) FROM catatan_poin WHERE semester_id = ? AND status = 'aktif'", [$semesterId], 0);
} catch (Throwable $galat) {
    $jumlahSiswa = 0;
    $jumlahCatatan = 0;
}

halaman_atas('Beranda', pengaturan('nama_sekolah', 'SiDiPa'), ['nav' => 'beranda']);
?>
<div class="kartu bg-kuning margin-bawah">
  <p class="label-kecil">Semester berjalan</p>
  <h2 style="margin:0"><?= e($semester['nama'] ?? 'Belum ada semester') ?></h2>
  <p class="teks-kecil" style="margin:4px 0 0">Tahun ajaran <?= e($semester['tahun_ajaran'] ?? '-') ?> · Semester <?= e($semester['semester'] ?? '-') ?></p>
</div>

<?php if ($siswaSendiri !== null): ?>
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
  <div class="kartu margin-atas">
    <p class="label-kecil">Data siswa</p>
    <h2><?= e($siswaSendiri['nama']) ?></h2>
    <p class="teks-kecil">NIS <?= e($siswaSendiri['nis']) ?> · Kelas <?= e($siswaSendiri['kelas'] ?? '-') ?></p>
  </div>
<?php else: ?>
  <div class="grid grid-stat margin-bawah">
    <div class="kartu kartu-stat bg-toska"><p class="label-kecil">Siswa aktif</p><span class="angka mono"><?= e($jumlahSiswa) ?></span></div>
    <div class="kartu kartu-stat bg-biru"><p class="label-kecil">Catatan semester</p><span class="angka mono"><?= e($jumlahCatatan) ?></span></div>
  </div>
  <section class="kartu">
    <p class="label-kecil">Menu cepat</p>
    <h2>Halo, <?= e($pengguna['nama']) ?>!</h2>
    <p class="teks-kecil">Peran: <?= e(label_peran($peran)) ?>.</p>
    <div class="daftar-tautan">
      <a href="<?= e(url('pages/umum/siswa.php')) ?>"><?= ikon('siswa', 22) ?> Data siswa</a>
      <a href="<?= e(url('pages/umum/rekap.php')) ?>"><?= ikon('rekap', 22) ?> Rekap skor per aspek</a>
      <a href="<?= e(url('pages/umum/notifikasi.php')) ?>"><?= ikon('lonceng', 22) ?> Notifikasi</a>
      <a href="<?= e(url('pages/profil.php')) ?>"><?= ikon('profil', 22) ?> Profil dan kata sandi</a>
    </div>
  </section>
<?php endif; ?>
<?php halaman_bawah(); ?>
