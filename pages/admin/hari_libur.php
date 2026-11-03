<?php
declare(strict_types=1);

/*
 | Tahap 3 — Admin mengelola hari libur (bagian 7 & 10 PDF).
 | Tanggal yang ada di tabel ini dilewati oleh cron/harian.php,
 | cron/mingguan.php, dan api/scan.php sehingga tidak ada alpa/poin minus.
 */

require_once __DIR__ . '/../../app/bootstrap.php';

$pengguna = auth_wajib(['admin']);
$pesanGalat = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_wajib();
    $aksi = aksi_post();

    if ($aksi === 'tambah') {
        $mulai = post('tgl_mulai', '', 10);
        $selesai = post('tgl_selesai', '', 10);
        $keterangan = post('keterangan', '', 160);
        if ($selesai === '') {
            $selesai = $mulai;
        }
        if (!tanggal_valid($mulai) || !tanggal_valid($selesai)) {
            $pesanGalat = 'Format tanggal tidak valid. Gunakan YYYY-MM-DD.';
        } elseif ($selesai < $mulai) {
            $pesanGalat = 'Tanggal selesai tidak boleh sebelum tanggal mulai.';
        } elseif ((strtotime($selesai) - strtotime($mulai)) / 86400 > 92) {
            $pesanGalat = 'Rentang maksimal 93 hari. Tambahkan beberapa periode bila perlu.';
        } else {
            $jumlah = 0;
            db_transaksi(function () use ($mulai, $selesai, $keterangan, &$jumlah): void {
                $hari = $mulai;
                while ($hari <= $selesai) {
                    db_jalankan(
                        'INSERT INTO hari_libur (tanggal, keterangan) VALUES (?, ?)
                         ON DUPLICATE KEY UPDATE keterangan = VALUES(keterangan)',
                        [$hari, $keterangan !== '' ? $keterangan : null]
                    );
                    $jumlah++;
                    $hari = date('Y-m-d', strtotime($hari . ' +1 day'));
                }
            });
            log_akses('tambah_hari_libur', 'hari_libur');
            flash($jumlah . ' hari libur disimpan.');
            alihkan('pages/admin/hari_libur.php');
        }
    } elseif ($aksi === 'hapus') {
        $id = post_int('id');
        db_jalankan('DELETE FROM hari_libur WHERE id = ?', [$id]);
        log_akses('hapus_hari_libur', 'hari_libur', $id);
        flash('Hari libur dihapus.');
        alihkan('pages/admin/hari_libur.php');
    }
}

$daftar = db_semua('SELECT id, tanggal, keterangan FROM hari_libur ORDER BY tanggal DESC LIMIT 300');
$tahunIni = date('Y');
$akanDatang = 0;
$hariIni = date('Y-m-d');
foreach ($daftar as $h) {
    if ($h['tanggal'] >= $hariIni) {
        $akanDatang++;
    }
}

halaman_atas('Hari Libur', 'Admin / Kalender sekolah', [
    'nav' => 'rekap',
    'aksi' => '<a class="tombol tombol-putih" href="' . e(url('pages/umum/rekap_kehadiran.php')) . '">' . ikon('rekap', 20) . ' Rekap Kehadiran</a>',
]);
?>
<?php if ($pesanGalat !== ''): ?>
  <div class="pesan pesan-galat" role="alert"><?= ikon('peringatan', 20) ?><span><?= e($pesanGalat) ?></span></div>
<?php endif; ?>

<div class="pesan pesan-info" role="status"><?= ikon('info', 20) ?>
  <span>Tanggal libur dilewati oleh cron harian/mingguan dan scan: siswa tidak ditandai alpa, tidak hadir sholat, dan tidak mendapat poin pengurangan. <?= $akanDatang ?> hari libur masih akan datang pada <?= e($tahunIni) ?> dan seterusnya.</span>
</div>

<section class="kartu form-kartu">
  <p class="label-kecil">Tambah periode libur</p>
  <h2>Satu tanggal atau rentang</h2>
  <form method="post" action="<?= e(url('pages/admin/hari_libur.php')) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="aksi" value="tambah">
    <div class="form-grid">
      <div class="form-baris"><label for="tgl_mulai">Tanggal mulai <span class="wajib">wajib</span></label>
        <input class="input" id="tgl_mulai" name="tgl_mulai" type="date" value="<?= e(date('Y-m-d')) ?>" required></div>
      <div class="form-baris"><label for="tgl_selesai">Tanggal selesai</label>
        <input class="input" id="tgl_selesai" name="tgl_selesai" type="date" value="<?= e(date('Y-m-d')) ?>">
        <p class="bantuan">Kosongkan atau samakan dengan tanggal mulai untuk satu hari.</p></div>
      <div class="form-baris lebar-penuh"><label for="keterangan">Keterangan</label>
        <input class="input" id="keterangan" name="keterangan" maxlength="160" placeholder="Mis. Libur Idul Fitri atau Ujian Sekolah"></div>
    </div>
    <div class="form-aksi"><button class="tombol tombol-utama" type="submit"><?= ikon('simpan', 20) ?> Simpan hari libur</button></div>
  </form>
</section>

<?= bagian_judul('Daftar hari libur', count($daftar) . ' tanggal tersimpan') ?>
<div class="tabel-bungkus">
  <table class="tabel">
    <thead><tr><th>Tanggal</th><th>Hari</th><th>Keterangan</th><th>Aksi</th></tr></thead>
    <tbody>
    <?php foreach ($daftar as $h): ?>
      <tr>
        <td class="mono"><strong><?= e($h['tanggal']) ?></strong></td>
        <td><?= e(tanggal_indonesia($h['tanggal'], true)) ?></td>
        <td><?= e($h['keterangan'] ?? '-') ?></td>
        <td>
          <form method="post" action="<?= e(url('pages/admin/hari_libur.php')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="aksi" value="hapus">
            <input type="hidden" name="id" value="<?= e($h['id']) ?>">
            <button class="tombol tombol-kecil tombol-putih" type="submit" data-konfirmasi="Hapus hari libur <?= e($h['tanggal']) ?>?" aria-label="Hapus hari libur"><?= ikon('hapus', 18) ?></button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if ($daftar === []): ?><tr><td colspan="4" class="tabel-kosong">Belum ada hari libur. Tambahkan tanggal libur nasional atau kegiatan sekolah.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<?php halaman_bawah(); ?>
