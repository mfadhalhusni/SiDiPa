<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/bootstrap.php';

$pengguna = auth_wajib();
$peran = $pengguna['peran'];
if ($peran === 'admin') {
    alihkan('pages/admin/siswa.php');
}

$cari = teks($_GET['cari'] ?? '', 80);
$sendiri = in_array($peran, ['siswa', 'wali'], true) ? siswa_akun((int) $pengguna['id'], $peran) : null;

$syarat = ['s.aktif = 1'];
$param = [];
if (in_array($peran, ['siswa', 'wali'], true) && $sendiri === null) {
    // Akun belum terhubung: jangan pernah tampilkan daftar siswa lain.
    $syarat[] = '1 = 0';
} elseif ($sendiri !== null) {
    $syarat[] = 's.id = ?';
    $param[] = (int) $sendiri['id'];
} elseif ($peran === 'guru') {
    $idKelas = id_kelas_guru((int) $pengguna['id']);
    if ($idKelas === []) {
        $syarat[] = '1 = 0';
    } else {
        $syarat[] = 's.kelas_id IN (' . implode(', ', array_fill(0, count($idKelas), '?')) . ')';
        $param = array_merge($param, $idKelas);
    }
}
if ($cari !== '') {
    $syarat[] = '(s.nama LIKE ? OR s.nis LIKE ?)';
    $param[] = '%' . $cari . '%';
    $param[] = '%' . $cari . '%';
}
$daftar = db_semua(
    'SELECT s.id, s.nis, s.nama, s.jenis_kelamin, k.nama AS kelas
     FROM siswa s LEFT JOIN kelas k ON k.id = s.kelas_id
     WHERE ' . implode(' AND ', $syarat) . '
     ORDER BY k.nama, s.nama LIMIT 200',
    $param
);

halaman_atas('Data siswa', $sendiri !== null ? 'Data saya' : 'Daftar siswa', ['nav' => 'siswa']);
?>
<?php if ($sendiri !== null): ?>
  <div class="pesan pesan-info" role="status"><?= ikon('info', 20) ?><span>Akun <?= e(label_peran($peran)) ?> hanya dapat membaca data siswa yang terhubung dengan akunnya.</span></div>
<?php elseif ($peran === 'guru' && $daftar === [] && $cari === ''): ?>
  <div class="pesan pesan-info" role="status"><?= ikon('info', 20) ?><span>Anda belum ditetapkan sebagai guru pengampu kelas. Minta admin mengatur kelas di menu Kelas.</span></div>
<?php endif; ?>

<form class="kartu form-kartu" method="get" action="<?= e(url('pages/umum/siswa.php')) ?>">
  <div class="form-baris"><label for="cari">Cari nama atau NIS</label>
    <input class="input" id="cari" name="cari" value="<?= e($cari) ?>" data-saring-tabel="#tabel-siswa-umum"></div>
  <div class="form-aksi"><button class="tombol" type="submit"><?= ikon('cari', 20) ?> Cari</button></div>
</form>

<div class="tabel-bungkus">
  <table class="tabel" id="tabel-siswa-umum">
    <thead><tr><th>NIS</th><th>Nama</th><th>Kelas</th><th>Jenis kelamin</th></tr></thead>
    <tbody>
    <?php foreach ($daftar as $s): ?>
      <tr>
        <td class="mono"><?= e($s['nis']) ?></td>
        <td><strong><?= e($s['nama']) ?></strong></td>
        <td><?= e($s['kelas'] ?? '-') ?></td>
        <td><?= e($s['jenis_kelamin'] === 'L' ? 'Laki-laki' : 'Perempuan') ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if ($daftar === []): ?><tr><td colspan="4" class="tabel-kosong">Tidak ada data siswa yang dapat ditampilkan.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<?php halaman_bawah(); ?>
