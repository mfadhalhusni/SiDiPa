<?php
declare(strict_types=1);

/* Tahap 4 — Dashboard Bimbingan Konseling: ringkasan per kelas & per aspek. */

require_once __DIR__ . '/../../app/bootstrap.php';

$pengguna = auth_wajib(['bk']);
$semester = semester_aktif();
$semesterId = (int) ($semester['id'] ?? 0);
$mulai = date('Y-m-01');
$selesai = date('Y-m-t');
$ringkasan = ringkasan_kelas($semesterId, $mulai, $selesai);
$idKelas = array_map('intval', array_column(db_semua('SELECT id FROM kelas WHERE aktif = 1'), 'id'));
$pembinaan = $semesterId > 0 ? siswa_perlu_pembinaan($idKelas, $semesterId) : [];
$aspek = daftar_aspek();

$stat = [
    'siswa' => (int) db_nilai('SELECT COUNT(*) FROM siswa WHERE aktif = 1', [], 0),
    'pembinaan' => count($pembinaan),
    'banding' => (int) db_nilai("SELECT COUNT(*) FROM banding WHERE status = 'menunggu'", [], 0),
    'sertifikat' => (int) db_nilai("SELECT COUNT(*) FROM sertifikat WHERE status = 'terbit'", [], 0),
    'hadir' => (int) db_nilai("SELECT COUNT(*) FROM kehadiran WHERE tanggal = ? AND status IN ('hadir','terlambat')", [date('Y-m-d')], 0),
    'alpa' => (int) db_nilai("SELECT COUNT(*) FROM kehadiran WHERE tanggal = ? AND status = 'alpa'", [date('Y-m-d')], 0),
];

halaman_atas('Dashboard BK', 'Bimbingan dan konseling', [
    'nav' => 'beranda',
    'aksi' => '<a class="tombol tombol-utama" href="' . e(url('pages/bk/tindak_lanjut.php')) . '">' . ikon('catatan', 20) . ' Tindak Lanjut</a>',
]);
?>
<section class="kartu bg-ungu margin-bawah">
  <p class="label-kecil">Halo, <?= e($pengguna['nama']) ?>!</p>
  <h2 style="margin:0"><?= e($semester['nama'] ?? 'Belum ada semester aktif') ?></h2>
  <p class="teks-kecil" style="margin:4px 0 0">Ringkasan bulan <?= e(NAMA_BULAN[(int) date('n')] . ' ' . date('Y')) ?>. Peringkat siswa dengan skor terendah tidak pernah ditampilkan.</p>
</section>

<div class="grid grid-stat margin-bawah">
  <div class="kartu kartu-stat bg-biru"><p class="label-kecil">Siswa aktif</p><span class="angka mono"><?= e($stat['siswa']) ?></span></div>
  <div class="kartu kartu-stat bg-koral"><p class="label-kecil">Perlu pembinaan</p><span class="angka mono"><?= e($stat['pembinaan']) ?></span></div>
  <div class="kartu kartu-stat bg-toska"><p class="label-kecil">Hadir hari ini</p><span class="angka mono"><?= e($stat['hadir']) ?></span></div>
  <div class="kartu kartu-stat bg-oranye"><p class="label-kecil">Alpa hari ini</p><span class="angka mono"><?= e($stat['alpa']) ?></span></div>
  <div class="kartu kartu-stat bg-pink"><p class="label-kecil">Banding menunggu</p><span class="angka mono"><?= e($stat['banding']) ?></span></div>
  <div class="kartu kartu-stat bg-kuning"><p class="label-kecil">Sertifikat terbit</p><span class="angka mono"><?= e($stat['sertifikat']) ?></span></div>
</div>

<section class="kartu margin-bawah">
  <p class="label-kecil">Jalan pintas</p>
  <div class="daftar-tautan">
    <a href="<?= e(url('pages/bk/tindak_lanjut.php')) ?>"><?= ikon('catatan', 22) ?> Tindak lanjut pembinaan</a>
    <a href="<?= e(url('pages/guru/pembinaan.php')) ?>"><?= ikon('pembinaan', 22) ?> Siswa pembinaan</a>
    <a href="<?= e(url('pages/umum/laporan.php')) ?>"><?= ikon('grafik', 22) ?> Laporan per kelas dan aspek</a>
    <a href="<?= e(url('pages/umum/rekap_kehadiran.php')) ?>"><?= ikon('rekap', 22) ?> Rekap kehadiran + CSV</a>
  </div>
</section>

<?= bagian_judul('Ringkasan per kelas', 'Rata-rata skor per aspek dihitung dari catatan aktif semester berjalan.') ?>
<div class="tabel-bungkus margin-bawah">
  <table class="tabel">
    <thead><tr><th>Kelas</th><th>Siswa</th><?php foreach ($aspek as $a): ?><th><?= e($a['nama']) ?></th><?php endforeach; ?><th>% Hadir</th><th>Pembinaan</th></tr></thead>
    <tbody>
    <?php foreach ($ringkasan as $r): ?>
      <tr>
        <td><strong><?= e($r['nama']) ?></strong></td>
        <td class="mono"><?= e($r['jumlah_siswa']) ?></td>
        <?php foreach ($aspek as $a): ?>
          <td class="mono"><?= e($r['aspek'][(int) $a['id']]['rata'] ?? 0) ?></td>
        <?php endforeach; ?>
        <td class="mono"><?= e($r['persentase_hadir']) ?>%</td>
        <td class="mono"><?= e($r['pembinaan']) ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if ($ringkasan === []): ?><tr><td colspan="<?= 5 + count($aspek) ?>" class="tabel-kosong">Belum ada kelas aktif.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>

<?= bagian_judul('Siswa perlu pembinaan', 'Daftar pendampingan, bukan peringkat skor terendah.') ?>
<div class="tabel-bungkus">
  <table class="tabel">
    <thead><tr><th>Siswa</th><th>Kelas</th><th>Aspek terendah</th><th>Persentase</th><th>Predikat</th></tr></thead>
    <tbody>
    <?php foreach (array_slice($pembinaan, 0, 20) as $s): ?>
      <tr><td><strong><?= e($s['nama']) ?></strong><br><span class="teks-kecil mono"><?= e($s['nis']) ?></span></td>
        <td><?= e($s['kelas'] ?? '-') ?></td><td><?= e($s['aspek_terendah']) ?></td>
        <td class="mono"><?= e($s['persentase']) ?>%</td><td><strong><?= e($s['predikat']) ?></strong></td></tr>
    <?php endforeach; ?>
    <?php if ($pembinaan === []): ?><tr><td colspan="5" class="tabel-kosong">Belum ada siswa di bawah ambang pembinaan.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<?php halaman_bawah(); ?>
