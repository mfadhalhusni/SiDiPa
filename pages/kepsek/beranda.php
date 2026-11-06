<?php
declare(strict_types=1);

/* Tahap 4 — Dashboard kepala sekolah (HANYA BACA, bagian 4 & 10 PDF). */

require_once __DIR__ . '/../../app/bootstrap.php';

$pengguna = auth_wajib(['kepsek']);
$semester = semester_aktif();
$semesterId = (int) ($semester['id'] ?? 0);
$mulai = date('Y-m-01');
$selesai = date('Y-m-t');
$ringkasan = ringkasan_kelas($semesterId, $mulai, $selesai);
$aspek = daftar_aspek();
$idKelas = array_map('intval', array_column(db_semua('SELECT id FROM kelas WHERE aktif = 1'), 'id'));
$pembinaan = $semesterId > 0 ? siswa_perlu_pembinaan($idKelas, $semesterId) : [];

$stat = [
    'siswa' => (int) db_nilai('SELECT COUNT(*) FROM siswa WHERE aktif = 1', [], 0),
    'kelas' => count($ringkasan),
    'sertifikat' => (int) db_nilai("SELECT COUNT(*) FROM sertifikat WHERE status = 'terbit'", [], 0),
    'banding' => (int) db_nilai("SELECT COUNT(*) FROM banding WHERE status = 'menunggu'", [], 0),
];

// Rata-rata sekolah per aspek (agregat, bukan peringkat siswa).
$rataSekolah = [];
foreach ($aspek as $a) {
    $total = 0;
    $jumlah = 0;
    foreach ($ringkasan as $r) {
        $total += (int) ($r['aspek'][(int) $a['id']]['total'] ?? 0);
        $jumlah += (int) $r['jumlah_siswa'];
    }
    $rataSekolah[] = ['nama' => (string) $a['nama'], 'rata' => $jumlah > 0 ? round($total / $jumlah, 1) : 0.0];
}
$warna = ['bg-koral', 'bg-kuning', 'bg-toska', 'bg-ungu', 'bg-oranye', 'bg-biru'];

halaman_atas('Dashboard Kepala Sekolah', 'Laporan hanya baca', ['nav' => 'beranda']);
?>
<section class="kartu bg-biru margin-bawah">
  <p class="label-kecil">Halo, <?= e($pengguna['nama']) ?>!</p>
  <h2 style="margin:0"><?= e($semester['nama'] ?? 'Belum ada semester aktif') ?></h2>
  <p class="teks-kecil" style="margin:4px 0 0">Tahun ajaran <?= e($semester['tahun_ajaran'] ?? '-') ?> · <?= e(NAMA_BULAN[(int) date('n')] . ' ' . date('Y')) ?>. Akun kepala sekolah hanya dapat membaca laporan.</p>
</section>

<div class="grid grid-stat margin-bawah">
  <div class="kartu kartu-stat bg-putih"><p class="label-kecil">Siswa aktif</p><span class="angka mono"><?= e($stat['siswa']) ?></span></div>
  <div class="kartu kartu-stat bg-ungu"><p class="label-kecil">Kelas aktif</p><span class="angka mono"><?= e($stat['kelas']) ?></span></div>
  <div class="kartu kartu-stat bg-toska"><p class="label-kecil">Sertifikat terbit</p><span class="angka mono"><?= e($stat['sertifikat']) ?></span></div>
  <div class="kartu kartu-stat bg-pink"><p class="label-kecil">Banding menunggu</p><span class="angka mono"><?= e($stat['banding']) ?></span></div>
</div>

<?= bagian_judul('Rata-rata skor sekolah', 'Per aspek, bukan predikat keseluruhan.') ?>
<div class="skor-grid margin-bawah">
  <?php foreach ($rataSekolah as $i => $r): ?>
    <div class="kartu kartu-skor <?= e($warna[$i % count($warna)]) ?>">
      <p class="label-kecil"><?= e($r['nama']) ?></p>
      <span class="angka mono"><?= e($r['rata']) ?></span>
      <p class="teks-kecil" style="margin:4px 0 0">Rata-rata per siswa</p>
    </div>
  <?php endforeach; ?>
</div>

<section class="kartu margin-bawah">
  <p class="label-kecil">Laporan</p>
  <div class="daftar-tautan">
    <a href="<?= e(url('pages/umum/laporan.php')) ?>"><?= ikon('grafik', 22) ?> Laporan per kelas dan per aspek + XLSX</a>
    <a href="<?= e(url('pages/umum/rekap_kehadiran.php')) ?>"><?= ikon('rekap', 22) ?> Rekap kehadiran + XLSX</a>
    <a href="<?= e(url('pages/umum/rekap.php')) ?>"><?= ikon('bintang', 22) ?> Rekap skor & siswa teladan</a>
  </div>
</section>

<?= bagian_judul('Ringkasan per kelas', 'Angka agregat bulan berjalan.') ?>
<div class="tabel-bungkus margin-bawah">
  <table class="tabel">
    <thead><tr><th>Kelas</th><th>Siswa</th><?php foreach ($aspek as $a): ?><th><?= e($a['nama']) ?></th><?php endforeach; ?><th>% Hadir</th><th>Sertifikat</th></tr></thead>
    <tbody>
    <?php foreach ($ringkasan as $r): ?>
      <tr>
        <td><strong><?= e($r['nama']) ?></strong></td>
        <td class="mono"><?= e($r['jumlah_siswa']) ?></td>
        <?php foreach ($aspek as $a): ?><td class="mono"><?= e($r['aspek'][(int) $a['id']]['rata'] ?? 0) ?></td><?php endforeach; ?>
        <td class="mono"><?= e($r['persentase_hadir']) ?>%</td>
        <td class="mono"><?= e($r['sertifikat']) ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if ($ringkasan === []): ?><tr><td colspan="<?= 5 + count($aspek) ?>" class="tabel-kosong">Belum ada kelas aktif.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>

<?= bagian_judul('Pendampingan berjalan', count($pembinaan) . ' siswa berada di bawah ambang pembinaan') ?>
<div class="tabel-bungkus">
  <table class="tabel">
    <thead><tr><th>Siswa</th><th>Kelas</th><th>Aspek</th><th>Predikat</th></tr></thead>
    <tbody>
    <?php foreach (array_slice($pembinaan, 0, 10) as $s): ?>
      <tr><td><strong><?= e($s['nama']) ?></strong></td><td><?= e($s['kelas'] ?? '-') ?></td>
        <td><?= e($s['aspek_terendah']) ?></td><td><strong><?= e($s['predikat']) ?></strong></td></tr>
    <?php endforeach; ?>
    <?php if ($pembinaan === []): ?><tr><td colspan="4" class="tabel-kosong">Belum ada siswa yang memerlukan pendampingan.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<?php halaman_bawah(); ?>
