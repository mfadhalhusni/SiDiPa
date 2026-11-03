<?php
declare(strict_types=1);

/*
 | Tahap 3 — Rekap kehadiran HARIAN, BULANAN, dan SEMESTER per siswa dan per
 | kelas dengan ekspor CSV (bagian 7 PDF). Akses dibatasi di server:
 |  - siswa / wali: hanya anaknya sendiri
 |  - guru: hanya kelas yang diampu
 |  - admin / BK / kepala sekolah: semua kelas
 | Kolom yang ditampilkan murni agregat kehadiran; tidak ada pencatat_id.
 */

require_once __DIR__ . '/../../app/bootstrap.php';

$pengguna = auth_wajib();
$peran = (string) $pengguna['peran'];
$idPengguna = (int) $pengguna['id'];

$jenis = (string) ($_GET['jenis'] ?? $_POST['jenis'] ?? 'bulanan');
if (!in_array($jenis, ['harian', 'bulanan', 'semester'], true)) {
    $jenis = 'bulanan';
}
$tanggal = teks($_GET['tanggal'] ?? $_POST['tanggal'] ?? date('Y-m-d'), 10);
$bulan = teks($_GET['bulan'] ?? $_POST['bulan'] ?? date('Y-m'), 7);
$semesterId = bilangan($_GET['semester'] ?? $_POST['semester'] ?? 0);
$filterKelas = bilangan($_GET['kelas'] ?? $_POST['kelas'] ?? 0);
if ($semesterId <= 0) {
    $semesterId = (int) (semester_aktif()['id'] ?? 0);
}
$periode = periode_rekap($jenis, $tanggal, $bulan, $semesterId);
$tanggal = $jenis === 'harian' ? $periode['mulai'] : $tanggal;
if (!tanggal_valid($tanggal)) {
    $tanggal = date('Y-m-d');
}

$saring = [];
$sendiri = null;
if (in_array($peran, ['siswa', 'wali'], true)) {
    $sendiri = siswa_akun($idPengguna, $peran);
    if ($sendiri === null) {
        // Akun belum terhubung: jangan tampilkan data siswa lain.
        $saring['id_kelas'] = [];
    } else {
        $saring['siswa_id'] = (int) $sendiri['id'];
    }
    $filterKelas = 0;
} elseif ($peran === 'guru') {
    $saring['id_kelas'] = id_kelas_guru($idPengguna);
} elseif ($filterKelas > 0) {
    $saring['kelas_id'] = $filterKelas;
}

$barisSiswa = rekap_kehadiran($periode['mulai'], $periode['selesai'], $saring);
$perKelas = rekap_per_kelas($barisSiswa);
$libur = jumlah_hari_libur($periode['mulai'], $periode['selesai']);

$total = [
    'siswa' => count($barisSiswa), 'hadir' => 0, 'awal' => 0, 'terlambat' => 0,
    'izin' => 0, 'sakit' => 0, 'alpa' => 0, 'tercatat' => 0,
];
foreach ($barisSiswa as $b) {
    foreach (['hadir', 'awal', 'terlambat', 'izin', 'sakit', 'alpa', 'tercatat'] as $k) {
        $total[$k] += (int) $b[$k];
    }
}
$efektif = $total['hadir'] + $total['terlambat'];
$total['persentase'] = $total['tercatat'] > 0 ? round($efektif / $total['tercatat'] * 100, 1) : 0.0;

// Ekspor CSV: POST + CSRF, dijalankan SEBELUM ada keluaran HTML.
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && aksi_post() === 'ekspor') {
    csrf_wajib();
    $isi = [];
    foreach ($barisSiswa as $b) {
        $isi[] = [
            $periode['mulai'] . ' s/d ' . $periode['selesai'],
            $b['nis'], $b['nama'], $b['kelas'],
            $b['hadir'], $b['awal'], $b['terlambat'], $b['izin'], $b['sakit'], $b['alpa'],
            $b['tercatat'], $b['persentase'] . '%',
        ];
    }
    log_akses('ekspor_csv_kehadiran', 'kehadiran');
    csv_keluar('sidipa-rekap-kehadiran-' . $periode['mulai'] . '-' . $periode['selesai'], [
        'Periode', 'NIS', 'Nama', 'Kelas', 'Hadir', 'Hadir Awal', 'Terlambat',
        'Izin', 'Sakit', 'Alpa', 'Hari Tercatat', 'Persentase Hadir',
    ], $isi);
}

$daftarKelas = daftar_kelas(false);
$daftarSemester = daftar_semester();
$parameter = [
    'jenis' => $jenis, 'tanggal' => $tanggal, 'bulan' => $bulan,
    'semester' => $semesterId, 'kelas' => $filterKelas,
];

halaman_atas('Rekap Kehadiran', 'Harian / Bulanan / Semester', [
    'nav' => 'rekap',
    'aksi' => '<span class="chip badge-kuning">' . e($periode['label']) . '</span>',
]);
?>
<?php if (in_array($peran, ['siswa', 'wali'], true) && $sendiri === null): ?>
  <div class="pesan pesan-galat" role="alert"><?= ikon('peringatan', 20) ?><span>Akun belum terhubung ke data siswa. Hubungi admin.</span></div>
<?php endif; ?>
<div class="pesan pesan-info" role="status"><?= ikon('info', 20) ?>
  <span>Status dihitung dari tabel <code>kehadiran</code>. <?= $libur ?> hari libur pada rentang ini tidak dihitung sebagai ketidakhadiran. Izin, sakit, dan udzur tidak pernah mengurangi poin.</span>
</div>

<section class="kartu form-kartu">
  <p class="label-kecil">Saring periode</p>
  <form method="get" action="<?= e(url('pages/umum/rekap_kehadiran.php')) ?>" class="form-grid">
    <div class="form-baris"><label for="jenis">Periode</label>
      <select class="input" id="jenis" name="jenis">
        <option value="harian" <?= $jenis === 'harian' ? 'selected' : '' ?>>Harian</option>
        <option value="bulanan" <?= $jenis === 'bulanan' ? 'selected' : '' ?>>Bulanan</option>
        <option value="semester" <?= $jenis === 'semester' ? 'selected' : '' ?>>Semester</option>
      </select></div>
    <div class="form-baris"><label for="tanggal">Tanggal (harian)</label>
      <input class="input" id="tanggal" name="tanggal" type="date" value="<?= e($tanggal) ?>"></div>
    <div class="form-baris"><label for="bulan">Bulan</label>
      <input class="input" id="bulan" name="bulan" type="month" value="<?= e($bulan) ?>"></div>
    <div class="form-baris"><label for="semester">Semester</label>
      <select class="input" id="semester" name="semester">
        <?php foreach ($daftarSemester as $sm): ?>
          <option value="<?= e($sm['id']) ?>" <?= (int) $sm['id'] === $semesterId ? 'selected' : '' ?>><?= e($sm['nama']) ?> (<?= e($sm['tahun_ajaran']) ?>)</option>
        <?php endforeach; ?>
      </select></div>
    <?php if (!in_array($peran, ['siswa', 'wali', 'guru'], true)): ?>
      <div class="form-baris"><label for="kelas">Kelas</label>
        <select class="input" id="kelas" name="kelas">
          <option value="0">Semua kelas</option>
          <?php foreach ($daftarKelas as $k): ?>
            <option value="<?= e($k['id']) ?>" <?= (int) $k['id'] === $filterKelas ? 'selected' : '' ?>><?= e($k['nama']) ?></option>
          <?php endforeach; ?>
        </select></div>
    <?php endif; ?>
    <div class="form-baris lebar-penuh form-aksi">
      <button class="tombol tombol-utama" type="submit"><?= ikon('cari', 20) ?> Tampilkan</button>
    </div>
  </form>
  <form method="post" action="<?= e(url('pages/umum/rekap_kehadiran.php')) ?>" class="baris-tombol margin-atas">
    <?= csrf_field() ?>
    <input type="hidden" name="aksi" value="ekspor">
    <?php foreach ($parameter as $k => $v): ?><input type="hidden" name="<?= e($k) ?>" value="<?= e($v) ?>"><?php endforeach; ?>
    <button class="tombol tombol-toska" type="submit"><?= ikon('unduh', 20) ?> Ekspor CSV</button>
    <span class="teks-kecil">Berkas CSV memuat baris yang sedang ditampilkan.</span>
  </form>
</section>

<div class="grid grid-stat margin-bawah">
  <div class="kartu kartu-stat bg-biru"><p class="label-kecil">Siswa</p><span class="angka mono"><?= e($total['siswa']) ?></span></div>
  <div class="kartu kartu-stat bg-toska"><p class="label-kecil">Hadir</p><span class="angka mono"><?= e($total['hadir']) ?></span></div>
  <div class="kartu kartu-stat bg-kuning"><p class="label-kecil">Hadir awal</p><span class="angka mono"><?= e($total['awal']) ?></span></div>
  <div class="kartu kartu-stat bg-oranye"><p class="label-kecil">Terlambat</p><span class="angka mono"><?= e($total['terlambat']) ?></span></div>
  <div class="kartu kartu-stat bg-ungu"><p class="label-kecil">Izin / Sakit</p><span class="angka mono"><?= e($total['izin'] + $total['sakit']) ?></span></div>
  <div class="kartu kartu-stat bg-koral"><p class="label-kecil">Alpa</p><span class="angka mono"><?= e($total['alpa']) ?></span></div>
  <div class="kartu kartu-stat bg-pink"><p class="label-kecil">% Kehadiran</p><span class="angka mono"><?= e($total['persentase']) ?>%</span></div>
  <div class="kartu kartu-stat bg-putih"><p class="label-kecil">Hari libur</p><span class="angka mono"><?= e($libur) ?></span></div>
</div>

<?= bagian_judul('Rekap per kelas', $periode['label']) ?>
<div class="tabel-bungkus margin-bawah">
  <table class="tabel">
    <thead><tr><th>Kelas</th><th>Siswa</th><th>Hadir</th><th>Awal</th><th>Terlambat</th><th>Izin</th><th>Sakit</th><th>Alpa</th><th>% Hadir</th></tr></thead>
    <tbody>
    <?php foreach ($perKelas as $k): ?>
      <tr>
        <td><strong><?= e($k['kelas']) ?></strong></td>
        <td class="mono"><?= e($k['siswa']) ?></td>
        <td class="mono"><?= e($k['hadir']) ?></td>
        <td class="mono"><?= e($k['awal']) ?></td>
        <td class="mono"><?= e($k['terlambat']) ?></td>
        <td class="mono"><?= e($k['izin']) ?></td>
        <td class="mono"><?= e($k['sakit']) ?></td>
        <td class="mono"><?= e($k['alpa']) ?></td>
        <td class="mono"><strong><?= e($k['persentase']) ?>%</strong></td>
      </tr>
    <?php endforeach; ?>
    <?php if ($perKelas === []): ?><tr><td colspan="9" class="tabel-kosong">Belum ada data kehadiran pada periode ini.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>

<?= bagian_judul('Rekap per siswa', 'Kolom "Awal" adalah bagian dari "Hadir" (bonus datang pagi).') ?>
<div class="tabel-bungkus">
  <table class="tabel">
    <thead><tr><th>NIS</th><th>Nama</th><th>Kelas</th><th>Hadir</th><th>Awal</th><th>Terlambat</th><th>Izin</th><th>Sakit</th><th>Alpa</th><th>% Hadir</th></tr></thead>
    <tbody>
    <?php foreach ($barisSiswa as $b): ?>
      <tr>
        <td class="mono"><?= e($b['nis']) ?></td>
        <td><strong><?= e($b['nama']) ?></strong></td>
        <td><?= e($b['kelas']) ?></td>
        <td class="mono"><?= e($b['hadir']) ?></td>
        <td class="mono"><?= e($b['awal']) ?></td>
        <td class="mono"><?= e($b['terlambat']) ?></td>
        <td class="mono"><?= e($b['izin']) ?></td>
        <td class="mono"><?= e($b['sakit']) ?></td>
        <td class="mono"><?= e($b['alpa']) ?></td>
        <td class="mono"><strong><?= e($b['persentase']) ?>%</strong></td>
      </tr>
    <?php endforeach; ?>
    <?php if ($barisSiswa === []): ?><tr><td colspan="10" class="tabel-kosong">Belum ada siswa yang dapat ditampilkan pada periode ini.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<?php halaman_bawah(); ?>
