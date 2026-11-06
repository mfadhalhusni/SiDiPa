<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/bootstrap.php';

$pengguna = auth_wajib(['admin']);

$cariAksi = teks($_GET['aksi'] ?? '', 60);
$cariPengguna = bilangan($_GET['pengguna'] ?? 0);
$cariTanggal = teks($_GET['tanggal'] ?? '', 10);
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $cariTanggal)) {
    $cariTanggal = '';
}

$syarat = ['1 = 1'];
$param = [];
if ($cariAksi !== '') {
    $syarat[] = 'l.aksi LIKE ?';
    $param[] = '%' . $cariAksi . '%';
}
if ($cariPengguna > 0) {
    $syarat[] = 'l.pengguna_id = ?';
    $param[] = $cariPengguna;
}
if ($cariTanggal !== '') {
    $syarat[] = 'DATE(l.waktu) = ?';
    $param[] = $cariTanggal;
}
$daftarLog = db_semua(
    'SELECT l.*, p.nama AS nama_pengguna FROM log_akses l
     LEFT JOIN pengguna p ON p.id = l.pengguna_id
     WHERE ' . implode(' AND ', $syarat) . '
     ORDER BY l.id DESC LIMIT 200',
    $param
);
$daftarPengguna = db_semua('SELECT id, nama FROM pengguna ORDER BY nama LIMIT 300');

halaman_atas('Log akses', 'Admin / Jejak audit', ['nav' => 'rekap']);
?>
<div class="pesan pesan-info" role="status"><?= ikon('info', 20) ?><span>Setiap pembukaan identitas pencatat lewat tombol "Lihat pencatat" tercatat di sini dengan aksi <strong>lihat_pencatat</strong>.</span></div>

<form class="kartu form-kartu" method="get" action="<?= e(url('pages/admin/log_akses.php')) ?>">
  <div class="form-grid">
    <div class="form-baris"><label for="aksi">Cari aksi</label>
      <input class="input" id="aksi" name="aksi" value="<?= e($cariAksi) ?>" placeholder="lihat_pencatat"></div>
    <div class="form-baris"><label for="pengguna">Pengguna</label>
      <select class="input" id="pengguna" name="pengguna">
        <option value="0">Semua pengguna</option>
        <?php foreach ($daftarPengguna as $p): ?>
          <option value="<?= e($p['id']) ?>" <?= (int) $p['id'] === $cariPengguna ? 'selected' : '' ?>><?= e($p['nama']) ?></option>
        <?php endforeach; ?>
      </select></div>
    <div class="form-baris"><label for="tanggal">Tanggal</label>
      <input class="input" id="tanggal" name="tanggal" type="date" value="<?= e($cariTanggal) ?>"></div>
  </div>
  <div class="form-aksi"><button class="tombol" type="submit"><?= ikon('cari', 20) ?> Saring</button></div>
</form>

<div class="tabel-bungkus">
  <table class="tabel">
    <thead><tr><th>Waktu</th><th>Pengguna</th><th>Aksi</th><th>Objek</th><th>ID</th><th>IP</th></tr></thead>
    <tbody>
    <?php foreach ($daftarLog as $l): ?>
      <tr class="<?= $l['aksi'] === 'lihat_pencatat' ? 'bg-kuning' : '' ?>">
        <td class="mono"><?= e(date('d/m/Y H:i:s', strtotime((string) $l['waktu']))) ?></td>
        <td><?= e($l['nama_pengguna'] ?? 'Sistem / tamu') ?></td>
        <td><strong><?= e($l['aksi']) ?></strong></td>
        <td><?= e($l['objek'] ?? '-') ?></td>
        <td class="mono"><?= e($l['objek_id'] ?? '-') ?></td>
        <td class="mono"><?= e($l['ip'] ?? '-') ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if ($daftarLog === []): ?><tr><td colspan="6" class="tabel-kosong">Belum ada aktivitas yang cocok.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<?php halaman_bawah(); ?>
