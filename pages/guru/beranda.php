<?php
declare(strict_types=1);

/*
 | BERANDA GURU — bagian 3 PDF.
 | Satu stiker tanggal, tanpa strip kalender, tanpa banner kehadiran.
 | Kartu CATAT POIN ±150 px, kartu aksi ±118 px dalam grid 2 kolom.
 | Semua guru mendapat kartu yang sama (Banding, Izin dan Sakit,
 | Penilaian Bulanan, Catatan Saya) dan menjangkau seluruh siswa aktif
 | tanpa penugasan guru_kelas / wali kelas.
 */

require_once __DIR__ . '/../../app/bootstrap.php';

$pengguna = auth_wajib(['guru']);
$idGuru = (int) $pengguna['id'];
$namaGuru = (string) $pengguna['nama'];
$labelKelas = 'Cakupan: seluruh siswa aktif';
$semester = semester_aktif();
$belum = jumlah_notifikasi_belum_dibaca($idGuru);

$kartu = [
    ['Scan Masuk', 'Absen datang & pulang', 'scan', 'bg-kuning', 'pages/guru/scan.php?mode=masuk'],
    ['Sholat Dhuha', 'Jendela 06.30–09.00', 'sholat', 'bg-toska', 'pages/guru/scan.php?mode=dhuha'],
    ['Sholat Dhuhur', 'Jendela 12.00–13.00', 'sholat', 'bg-biru', 'pages/guru/scan.php?mode=dhuhur'],
    ['Rekap Kelas', 'Skor per aspek', 'rekap', 'bg-ungu', 'pages/umum/rekap.php'],
    ['Siswa Pembinaan', 'Di bawah ambang', 'pembinaan', 'bg-oranye', 'pages/guru/pembinaan.php'],
];
$kartu[] = ['Banding', 'Putuskan banding siswa', 'banding', 'bg-pink', 'pages/guru/banding.php'];
$kartu[] = ['Izin dan Sakit', 'Catat keterangan hadir', 'izin', 'bg-kuning', 'pages/guru/izin.php'];
$kartu[] = ['Penilaian Bulanan', '4 aspek non-otomatis', 'penilaian', 'bg-toska', 'pages/guru/penilaian.php'];
$kartu[] = ['Catatan Saya', 'Riwayat & pembatalan', 'catatan', 'bg-pink', 'pages/guru/riwayat.php'];

halaman_atas('Beranda guru', '', ['nav' => 'beranda', 'tanpa_judul' => true]);
?>
<section class="kepala-guru" aria-label="Sapaan guru">
  <div class="avatar-guru" aria-hidden="true"><?= e(inisial($namaGuru)) ?></div>
  <div class="sapa">
    <p class="label-kecil">Beranda guru</p>
    <h1>Halo, <?= e($namaGuru) ?>!</h1>
    <p><?= e($labelKelas) ?></p>
  </div>
  <a class="tombol-ikon" href="<?= e(url('pages/umum/notifikasi.php')) ?>"
     aria-label="Notifikasi<?= $belum > 0 ? ', ' . $belum . ' belum dibaca' : '' ?>">
    <?= ikon('lonceng', 24) ?>
    <?php if ($belum > 0): ?><span class="lencana"><?= $belum ?></span><?php endif; ?>
  </a>
</section>

<div class="stiker stiker-tanggal" aria-label="Tanggal hari ini"><?= e(stiker_tanggal_hari_ini()) ?></div>

<a class="kartu kartu-lebar bg-koral margin-bawah" href="<?= e(url('pages/guru/catat_poin.php')) ?>" aria-label="Catat poin karakter siswa">
  <div>
    <p class="label-kecil">Catat poin karakter</p>
    <h2 class="judul-kartu">CATAT POIN</h2>
    <p class="teks-kecil" style="margin:6px 0 0">Pilih siswa, pilih kejadian, simpan sekali klik.</p>
  </div>
  <span class="kotak-ikon" aria-hidden="true"><?= ikon('plus', 40) ?></span>
</a>

<div class="grid grid-2">
  <?php foreach ($kartu as $k): ?>
    <a class="kartu kartu-aksi <?= e($k[3]) ?>" href="<?= e(url($k[4])) ?>">
      <span class="kotak-ikon" aria-hidden="true"><?= ikon($k[2], 22) ?></span>
      <span>
        <span class="judul-kartu"><?= e($k[0]) ?></span><br>
        <span class="ket-kartu"><?= e($k[1]) ?></span>
      </span>
    </a>
  <?php endforeach; ?>
</div>

<div class="baris-tombol margin-atas">
  <a class="tombol tombol-putih" href="<?= e(url('pages/guru/riwayat.php')) ?>"><?= ikon('catatan', 20) ?> Catatan Saya</a>
  <a class="tombol tombol-putih" href="<?= e(url('pages/umum/siswa.php')) ?>"><?= ikon('siswa', 20) ?> Data Siswa</a>
  <a class="tombol tombol-putih" href="<?= e(url('pages/umum/rekap_kehadiran.php')) ?>"><?= ikon('rekap', 20) ?> Rekap Kehadiran</a>
</div>

<?php if ($semester !== null): ?>
  <p class="teks-kecil margin-atas">Semester berjalan: <?= e($semester['nama']) ?> · <?= e($semester['tahun_ajaran']) ?></p>
<?php endif; ?>
<?php halaman_bawah(); ?>
