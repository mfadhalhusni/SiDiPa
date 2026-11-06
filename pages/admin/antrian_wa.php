<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/bootstrap.php';

$pengguna = auth_wajib(['admin']);

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_wajib();
    $aksi = aksi_post();
    if ($aksi === 'ulang') {
        db_jalankan(
            "UPDATE notifikasi_wa SET status = 'antri', percobaan = 0, balasan = NULL, dikirim_pada = NULL WHERE id = ?",
            [post_int('id')]
        );
        log_akses('ulang_wa', 'notifikasi_wa', post_int('id'));
        flash('Pesan dimasukkan kembali ke antrian.');
    } elseif ($aksi === 'ulang_gagal') {
        $jumlah = db_jalankan(
            "UPDATE notifikasi_wa SET status = 'antri', percobaan = 0, balasan = NULL, dikirim_pada = NULL WHERE status = 'gagal'"
        );
        log_akses('ulang_semua_wa_gagal', 'notifikasi_wa');
        flash($jumlah . ' pesan gagal dimasukkan kembali ke antrian.');
    }
    alihkan('pages/admin/antrian_wa.php');
}

$filter = teks($_GET['status'] ?? '', 20);
if (!in_array($filter, ['antri', 'terkirim', 'gagal'], true)) {
    $filter = '';
}
$syarat = $filter === '' ? '1 = 1' : 'w.status = ?';
$param = $filter === '' ? [] : [$filter];
$daftar = db_semua(
    'SELECT w.*, s.nama AS nama_siswa FROM notifikasi_wa w
     JOIN siswa s ON s.id = w.siswa_id
     WHERE ' . $syarat . ' ORDER BY w.id DESC LIMIT 200',
    $param
);
$jumlah = [
    'antri' => (int) db_nilai("SELECT COUNT(*) FROM notifikasi_wa WHERE status = 'antri'", [], 0),
    'terkirim' => (int) db_nilai("SELECT COUNT(*) FROM notifikasi_wa WHERE status = 'terkirim'", [], 0),
    'gagal' => (int) db_nilai("SELECT COUNT(*) FROM notifikasi_wa WHERE status = 'gagal'", [], 0),
];

halaman_atas('Antrian WhatsApp', 'Admin / OpenWA', ['nav' => 'rekap']);
?>
<div class="grid grid-stat margin-bawah">
  <div class="kartu kartu-stat bg-kuning"><p class="label-kecil">Antri</p><span class="angka mono"><?= e($jumlah['antri']) ?></span></div>
  <div class="kartu kartu-stat bg-toska"><p class="label-kecil">Terkirim</p><span class="angka mono"><?= e($jumlah['terkirim']) ?></span></div>
  <div class="kartu kartu-stat bg-koral"><p class="label-kecil">Gagal</p><span class="angka mono"><?= e($jumlah['gagal']) ?></span></div>
</div>

<div class="baris-tombol margin-bawah">
  <?php foreach (['' => 'Semua', 'antri' => 'Antri', 'terkirim' => 'Terkirim', 'gagal' => 'Gagal'] as $k => $label): ?>
    <a class="tombol tombol-kecil <?= $filter === $k ? 'tombol-utama' : 'tombol-putih' ?>" href="<?= e(url('pages/admin/antrian_wa.php?status=' . $k)) ?>"><?= e($label) ?></a>
  <?php endforeach; ?>
  <?php if ($jumlah['gagal'] > 0): ?>
  <form method="post" style="display:inline"><?= csrf_field() ?><input type="hidden" name="aksi" value="ulang_gagal"><button class="tombol tombol-kecil tombol-toska" type="submit" data-konfirmasi="Ulang semua pesan yang gagal?"><?= ikon('refresh', 18) ?> Ulang gagal</button></form>
  <?php endif; ?>
</div>

<div class="tabel-bungkus">
  <table class="tabel">
    <thead><tr><th>Waktu</th><th>Siswa</th><th>Nomor</th><th>Jenis</th><th>Pesan</th><th>Status</th><th>Coba</th><th>Aksi</th></tr></thead>
    <tbody>
    <?php foreach ($daftar as $w): ?>
      <tr>
        <td class="mono"><?= e(date('d/m/Y H:i', strtotime((string) $w['dibuat_pada']))) ?></td>
        <td><?= e($w['nama_siswa']) ?></td>
        <td class="mono"><?= e($w['nomor']) ?></td>
        <td><?= e($w['jenis']) ?></td>
        <td class="teks-kecil"><?= e(mb_substr((string) $w['pesan'], 0, 90, 'UTF-8')) ?>…</td>
        <td><span class="badge <?= $w['status'] === 'terkirim' ? 'badge-toska' : ($w['status'] === 'gagal' ? 'badge-koral' : 'badge-kuning') ?>"><?= e($w['status']) ?></span></td>
        <td class="mono"><?= e($w['percobaan']) ?></td>
        <td>
          <?php if ($w['status'] !== 'terkirim'): ?>
          <form method="post" style="display:inline"><?= csrf_field() ?><input type="hidden" name="aksi" value="ulang"><input type="hidden" name="id" value="<?= e($w['id']) ?>"><button class="tombol tombol-kecil" type="submit" aria-label="Ulang pesan"><?= ikon('refresh', 18) ?></button></form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if ($daftar === []): ?><tr><td colspan="8" class="tabel-kosong">Belum ada pesan pada kategori ini.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<?php halaman_bawah(); ?>
