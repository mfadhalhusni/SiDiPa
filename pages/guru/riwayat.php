<?php
declare(strict_types=1);

/* "Catatan Saya": guru hanya melihat catatan yang ia tulis sendiri. */

require_once __DIR__ . '/../../app/bootstrap.php';

$pengguna = auth_wajib(['guru']);
$idGuru = (int) $pengguna['id'];
$semester = semester_aktif();
$semesterId = (int) ($semester['id'] ?? 0);
$batasJam = (int) pengaturan('batas_batal_catatan_jam', '24');
$status = teks($_GET['status'] ?? 'semua', 20);
if (!in_array($status, ['semua', 'aktif', 'dibatalkan'], true)) {
    $status = 'semua';
}
$pesanGalat = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_wajib();
    if (aksi_post() === 'batal') {
        $hasil = batalkan_catatan_sendiri(
            post_int('catatan_id'),
            $idGuru,
            post('alasan', '', 2000)
        );
        if ($hasil['ok']) {
            flash($hasil['pesan']);
            alihkan('pages/guru/riwayat.php?status=dibatalkan');
        }
        $pesanGalat = $hasil['pesan'];
    }
}

$syarat = ['c.pencatat_id = ?'];
$param = [$idGuru];
if ($semesterId > 0) {
    $syarat[] = 'c.semester_id = ?';
    $param[] = $semesterId;
}
if ($status !== 'semua') {
    $syarat[] = 'c.status = ?';
    $param[] = $status;
}
$daftar = db_semua(
    'SELECT c.id, c.tanggal, c.poin, c.keterangan, c.sumber, c.status, c.bukti_path,
            c.alasan_batal, c.dibatalkan_pada,
            TIMESTAMPDIFF(HOUR, c.dibuat_pada, NOW()) AS umur_jam,
            a.nama AS aspek, COALESCE(k.nama, \'Penilaian bulanan\') AS kejadian,
            COALESCE(k.tingkat, \'none\') AS tingkat,
            s.nama AS nama_siswa, s.nis
     FROM catatan_poin c
     JOIN aspek a ON a.id = c.aspek_id
     JOIN siswa s ON s.id = c.siswa_id
     LEFT JOIN katalog_poin k ON k.id = c.katalog_id
     WHERE ' . implode(' AND ', $syarat) . '
     ORDER BY c.tanggal DESC, c.id DESC LIMIT 300',
    $param
);

halaman_atas('Catatan Saya', 'Beranda guru / Riwayat', ['nav' => 'beranda']);
?>
<?php if ($pesanGalat !== ''): ?>
  <div class="pesan pesan-galat" role="alert"><?= ikon('peringatan', 20) ?><span><?= e($pesanGalat) ?></span></div>
<?php endif; ?>

<div class="pesan pesan-info" role="status"><?= ikon('info', 20) ?>
  <span>Catatan milik Anda sendiri dapat dibatalkan maksimal <strong><?= $batasJam ?> jam</strong> setelah dibuat. Pembatalan tidak menghapus data: alasan dan waktu tetap tersimpan.</span>
</div>

<form class="kartu form-kartu" method="get" action="<?= e(url('pages/guru/riwayat.php')) ?>">
  <div class="form-baris">
    <label for="status">Tampilkan</label>
    <select class="input" id="status" name="status">
      <option value="semua" <?= $status === 'semua' ? 'selected' : '' ?>>Semua</option>
      <option value="aktif" <?= $status === 'aktif' ? 'selected' : '' ?>>Aktif</option>
      <option value="dibatalkan" <?= $status === 'dibatalkan' ? 'selected' : '' ?>>Dibatalkan</option>
    </select>
  </div>
  <div class="form-aksi"><button class="tombol" type="submit"><?= ikon('filter', 20) ?> Terapkan</button></div>
</form>

<div class="tabel-bungkus">
  <table class="tabel" id="tabel-catatan-saya">
    <thead><tr><th>Tanggal</th><th>Siswa</th><th>Aspek / kejadian</th><th>Poin</th><th>Status &amp; aksi</th></tr></thead>
    <tbody>
    <?php foreach ($daftar as $c): ?>
      <?php
        $bisa = $c['status'] === 'aktif' && $c['sumber'] === 'manual' && (int) $c['umur_jam'] <= $batasJam;
        $sisa = max(0, $batasJam - (int) $c['umur_jam']);
      ?>
      <tr>
        <td class="mono"><?= e(tanggal_indonesia($c['tanggal'], false)) ?></td>
        <td><strong><?= e($c['nama_siswa']) ?></strong><br><span class="teks-kecil mono"><?= e($c['nis']) ?></span></td>
        <td>
          <strong><?= e($c['aspek']) ?></strong><br>
          <span class="teks-kecil"><?= e($c['kejadian']) ?></span>
          <?php if (in_array($c['tingkat'], ['sedang', 'berat'], true)): ?>
            <span class="chip badge-<?= $c['tingkat'] === 'berat' ? 'koral' : 'kuning' ?>">Tingkat <?= e($c['tingkat']) ?></span>
          <?php endif; ?>
          <?php if ((string) $c['keterangan'] !== ''): ?>
            <p class="teks-kecil" style="margin:4px 0 0"><?= e($c['keterangan']) ?></p>
          <?php endif; ?>
          <?php if ((string) $c['bukti_path'] !== ''): ?>
            <a class="teks-kecil" href="<?= e(url($c['bukti_path'])) ?>" target="_blank" rel="noopener">Lihat bukti</a>
          <?php endif; ?>
        </td>
        <td class="mono"><strong><?= ((int) $c['poin'] > 0 ? '+' : '') . (int) $c['poin'] ?></strong></td>
        <td>
          <?php if ($c['status'] === 'dibatalkan'): ?>
            <span class="chip badge-abu">Dibatalkan</span>
            <p class="teks-kecil" style="margin:6px 0 0"><strong>Alasan:</strong> <?= e($c['alasan_batal'] ?? '-') ?></p>
            <p class="teks-kecil">Waktu: <?= e($c['dibatalkan_pada'] ?? '-') ?></p>
          <?php else: ?>
            <span class="chip badge-toska">Aktif</span>
            <?php if ($bisa): ?>
              <form method="post" action="<?= e(url('pages/guru/riwayat.php')) ?>" style="margin-top:8px" data-konfirmasi="Yakin batalkan catatan ini? Poin akan kembali.">
                <?= csrf_field() ?>
                <input type="hidden" name="aksi" value="batal">
                <input type="hidden" name="catatan_id" value="<?= (int) $c['id'] ?>">
                <div class="form-baris" style="margin-bottom:6px">
                  <label for="alasan-<?= (int) $c['id'] ?>">Alasan pembatalan</label>
                  <input class="input" id="alasan-<?= (int) $c['id'] ?>" name="alasan" required maxlength="2000" placeholder="Misal: salah pilih siswa">
                </div>
                <button class="tombol tombol-putih" type="submit"><?= ikon('silang', 18) ?> Batalkan (<?= $sisa ?> jam lagi)</button>
              </form>
            <?php else: ?>
              <p class="teks-kecil" style="margin:6px 0 0">Batas <?= $batasJam ?> jam telah lewat atau catatan berasal dari sistem.</p>
            <?php endif; ?>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if ($daftar === []): ?>
      <tr><td colspan="5" class="tabel-kosong">Belum ada catatan yang Anda tulis pada semester ini.</td></tr>
    <?php endif; ?>
    </tbody>
  </table>
</div>

<div class="baris-tombol margin-atas">
  <a class="tombol tombol-utama" href="<?= e(url('pages/guru/catat_poin.php')) ?>"><?= ikon('plus', 20) ?> Catat poin baru</a>
  <a class="tombol tombol-putih" href="<?= e(url('pages/guru/beranda.php')) ?>">Kembali</a>
</div>
<?php halaman_bawah(); ?>
