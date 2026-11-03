<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/bootstrap.php';

$pengguna = auth_wajib();
$idPengguna = (int) $pengguna['id'];

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_wajib();
    $aksi = aksi_post();
    if ($aksi === 'baca') {
        db_jalankan('UPDATE notifikasi SET dibaca = 1 WHERE id = ? AND pengguna_id = ?', [post_int('id'), $idPengguna]);
        flash('Notifikasi ditandai sudah dibaca.');
    } elseif ($aksi === 'semua') {
        db_jalankan('UPDATE notifikasi SET dibaca = 1 WHERE pengguna_id = ?', [$idPengguna]);
        flash('Semua notifikasi ditandai sudah dibaca.');
    }
    alihkan('pages/umum/notifikasi.php');
}

$daftar = db_semua(
    'SELECT id, judul, pesan, tautan, dibaca, dibuat_pada FROM notifikasi
     WHERE pengguna_id = ? ORDER BY dibuat_pada DESC, id DESC LIMIT 100',
    [$idPengguna]
);
$belum = (int) db_nilai('SELECT COUNT(*) FROM notifikasi WHERE pengguna_id = ? AND dibaca = 0', [$idPengguna], 0);

halaman_atas('Notifikasi', $belum . ' belum dibaca', ['nav' => 'beranda']);
?>
<?php if ($belum > 0): ?>
  <form method="post" action="<?= e(url('pages/umum/notifikasi.php')) ?>" style="margin-bottom:14px">
    <?= csrf_field() ?><input type="hidden" name="aksi" value="semua">
    <button class="tombol" type="submit"><?= ikon('centang', 20) ?> Tandai semua dibaca</button>
  </form>
<?php endif; ?>

<div class="grid" style="gap:12px">
  <?php foreach ($daftar as $n): ?>
    <?php
    $tautanAman = '';
    if (!empty($n['tautan']) && !preg_match('#(^//|://|^\\)#', (string) $n['tautan'])) {
        $tautanAman = url((string) $n['tautan']);
    }
    ?>
    <article class="kartu <?= (int) $n['dibaca'] === 0 ? 'bg-kuning' : 'bg-putih' ?>">
      <p class="label-kecil"><?= e(tanggal_indonesia($n['dibuat_pada'])) ?></p>
      <h3><?= e($n['judul']) ?></h3>
      <p class="teks-kecil"><?= e($n['pesan']) ?></p>
      <div class="baris-tombol">
        <?php if ($tautanAman !== ''): ?><a class="tombol tombol-kecil tombol-putih" href="<?= e($tautanAman) ?>">Buka</a><?php endif; ?>
        <?php if ((int) $n['dibaca'] === 0): ?>
          <form method="post" style="display:inline"><?= csrf_field() ?><input type="hidden" name="aksi" value="baca"><input type="hidden" name="id" value="<?= e($n['id']) ?>"><button class="tombol tombol-kecil" type="submit">Tandai dibaca</button></form>
        <?php endif; ?>
      </div>
    </article>
  <?php endforeach; ?>
  <?php if ($daftar === []): ?><div class="kartu tabel-kosong">Belum ada notifikasi untuk akun ini.</div><?php endif; ?>
</div>
<?php halaman_bawah(); ?>
