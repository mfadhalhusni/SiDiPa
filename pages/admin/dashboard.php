<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/bootstrap.php';

$pengguna = auth_wajib(['admin']);
$semester = semester_aktif();
$semesterId = (int) ($semester['id'] ?? 0);

function hitung(string $sql, array $param = []): int
{
    try {
        return (int) db_nilai($sql, $param, 0);
    } catch (Throwable $galat) {
        catat_kesalahan('dashboard hitung: ' . $galat->getMessage());
        return 0;
    }
}

$stat = [
    ['label' => 'Siswa aktif', 'nilai' => hitung('SELECT COUNT(*) FROM siswa WHERE aktif = 1'), 'warna' => 'bg-koral'],
    ['label' => 'Kelas aktif', 'nilai' => hitung('SELECT COUNT(*) FROM kelas WHERE aktif = 1'), 'warna' => 'bg-biru'],
    ['label' => 'Akun guru', 'nilai' => hitung("SELECT COUNT(*) FROM pengguna WHERE peran = 'guru' AND aktif = 1"), 'warna' => 'bg-ungu'],
    ['label' => 'Katalog poin', 'nilai' => hitung('SELECT COUNT(*) FROM katalog_poin WHERE aktif = 1'), 'warna' => 'bg-kuning'],
    ['label' => 'Catatan semester', 'nilai' => hitung("SELECT COUNT(*) FROM catatan_poin WHERE semester_id = ? AND status = 'aktif'", [$semesterId]), 'warna' => 'bg-toska'],
    ['label' => 'Banding menunggu', 'nilai' => hitung("SELECT COUNT(*) FROM banding WHERE status = 'menunggu'"), 'warna' => 'bg-pink'],
    ['label' => 'Sertifikat terbit', 'nilai' => hitung("SELECT COUNT(*) FROM sertifikat WHERE status = 'terbit'"), 'warna' => 'bg-oranye'],
    ['label' => 'Antrian WA', 'nilai' => hitung("SELECT COUNT(*) FROM notifikasi_wa WHERE status = 'antri'"), 'warna' => 'bg-putih'],
];

$teladan = [];
try {
    $teladan = $semesterId > 0 ? siswa_teladan($semesterId, 5) : [];
} catch (Throwable $galat) {
    catat_kesalahan('dashboard teladan: ' . $galat->getMessage());
}

halaman_atas('Dashboard admin', pengaturan('nama_sekolah', 'SiDiPa'), ['nav' => 'beranda']);
?>
<div class="kartu bg-kuning margin-bawah">
  <p class="label-kecil">Semester berjalan</p>
  <h2 style="margin:0"><?= e($semester['nama'] ?? 'Belum ada semester') ?></h2>
  <p class="teks-kecil" style="margin:4px 0 0">
    Tahun ajaran <?= e($semester['tahun_ajaran'] ?? '-') ?> ·
    <?= e(tanggal_indonesia($semester['tgl_mulai'] ?? null, false)) ?> sampai <?= e(tanggal_indonesia($semester['tgl_selesai'] ?? null, false)) ?>
  </p>
</div>

<div class="grid grid-stat margin-bawah">
  <?php foreach ($stat as $s): ?>
    <div class="kartu kartu-stat <?= e($s['warna']) ?>">
      <p class="label-kecil"><?= e($s['label']) ?></p>
      <span class="angka mono"><?= e($s['nilai']) ?></span>
    </div>
  <?php endforeach; ?>
</div>

<div class="grid grid-2">
  <section class="kartu">
    <p class="label-kecil">Kelola data</p>
    <h2>Jalan pintas</h2>
    <div class="daftar-tautan">
      <a href="<?= e(url('pages/admin/siswa.php')) ?>"><?= ikon('siswa', 22) ?> Data siswa</a>
      <a href="<?= e(url('pages/admin/impor_siswa.php')) ?>"><?= ikon('unggah', 22) ?> Impor siswa dari CSV</a>
      <a href="<?= e(url('pages/admin/kelas.php')) ?>"><?= ikon('kelas', 22) ?> Kelas dan wali kelas</a>
      <a href="<?= e(url('pages/admin/katalog.php')) ?>"><?= ikon('katalog', 22) ?> Katalog poin</a>
      <a href="<?= e(url('pages/admin/pengaturan.php')) ?>"><?= ikon('pengaturan', 22) ?> Pengaturan sistem</a>
      <a href="<?= e(url('pages/admin/pengguna.php')) ?>"><?= ikon('pengguna', 22) ?> Pengguna</a>
      <a href="<?= e(url('pages/admin/kartu.php')) ?>"><?= ikon('cetak', 22) ?> Cetak kartu QR siswa</a>
      <a href="<?= e(url('pages/admin/hari_libur.php')) ?>"><?= ikon('libur', 22) ?> Hari libur</a>
      <a href="<?= e(url('pages/admin/sertifikat.php')) ?>"><?= ikon('sertifikat', 22) ?> Semester & sertifikat</a>
      <a href="<?= e(url('pages/umum/rekap_kehadiran.php')) ?>"><?= ikon('rekap', 22) ?> Rekap kehadiran + CSV</a>
      <a href="<?= e(url('pages/umum/laporan.php')) ?>"><?= ikon('grafik', 22) ?> Laporan per kelas</a>
      <a href="<?= e(url('pages/admin/log_akses.php')) ?>"><?= ikon('log', 22) ?> Log akses</a>
    </div>
  </section>

  <section class="kartu">
    <p class="label-kecil">Apresiasi</p>
    <h2>Siswa teladan per aspek</h2>
    <p class="teks-kecil">Hanya peringkat positif yang ditampilkan. Peringkat skor terendah tidak pernah diterbitkan.</p>
    <div class="tabel-bungkus">
      <table class="tabel">
        <thead><tr><th>Aspek</th><th>Siswa</th><th>Skor</th></tr></thead>
        <tbody>
        <?php foreach ($teladan as $t): ?>
          <tr><td><?= e($t['aspek']) ?></td><td><?= e($t['nama_siswa']) ?> <span class="teks-kecil">(<?= e($t['kelas'] ?? '-') ?>)</span></td><td class="mono">+<?= e($t['skor']) ?></td></tr>
        <?php endforeach; ?>
        <?php if ($teladan === []): ?><tr><td colspan="3" class="tabel-kosong">Belum ada catatan poin positif pada semester ini.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>
</div>
<?php halaman_bawah(); ?>
