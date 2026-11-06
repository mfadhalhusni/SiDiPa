<?php
declare(strict_types=1);

/* Izin / sakit / udzur oleh wali kelas. Bersifat netral: poin otomatis dibatalkan. */

require_once __DIR__ . '/../../app/bootstrap.php';

$pengguna = auth_wajib(['guru']);
cek_input_semester();
$idGuru = (int) $pengguna['id'];
$semesterId = (int) (semester_aktif()['id'] ?? 0);
$pesanGalat = '';


if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_wajib();
    $aksi = aksi_post();
    if ($aksi === 'tambah') {
        $siswaId = post_int('siswa_id');
        $mulai = post('tgl_mulai', '', 10);
        $selesai = post('tgl_selesai', '', 10);
        $jenis = post('jenis', '', 10);
        $keterangan = post('keterangan', '', 255);
        $milik = guru_boleh_siswa($siswaId);
        if (!$milik) {
            $pesanGalat = 'Pilih siswa yang masih aktif.';
        } elseif (!tanggal_valid($mulai) || !tanggal_valid($selesai) || $selesai < $mulai) {
            $pesanGalat = 'Rentang tanggal tidak valid.';
        } elseif (!in_array($jenis, ['izin', 'sakit', 'udzur'], true)) {
            $pesanGalat = 'Jenis keterangan tidak valid.';
        } else {
            db_transaksi(function () use ($siswaId, $mulai, $selesai, $jenis, $keterangan, $idGuru): void {
                db_sisip('izin_siswa', [
                    'siswa_id' => $siswaId,
                    'tgl_mulai' => $mulai,
                    'tgl_selesai' => $selesai,
                    'jenis' => $jenis,
                    'keterangan' => $keterangan !== '' ? $keterangan : null,
                    'dicatat_oleh' => $idGuru,
                ]);
                $hari = strtotime($mulai);
                $akhir = strtotime($selesai);
                while ($hari !== false && $hari <= $akhir) {
                    batalkan_catatan_otomatis_harian($siswaId, date('Y-m-d', $hari));
                    $hari = strtotime('+1 day', $hari);
                }
            });
            flash('Keterangan ' . $jenis . ' tersimpan. Poin otomatis pada rentang tersebut dinetralkan.');
            alihkan('pages/guru/izin.php');
        }
    } elseif ($aksi === 'hapus') {
        $id = post_int('izin_id');
        $boleh = (int) db_nilai('SELECT COUNT(*) FROM izin_siswa WHERE id = ?', [$id], 0);
        if ($boleh === 0) {
            $pesanGalat = 'Data izin tidak ditemukan.';
        } else {
            db_jalankan('DELETE FROM izin_siswa WHERE id = ?', [$id]);
            flash('Keterangan izin dihapus.');
            alihkan('pages/guru/izin.php');
        }
    }
}

$daftar = db_semua(
    'SELECT i.id, i.tgl_mulai, i.tgl_selesai, i.jenis, i.keterangan, s.nama AS nama_siswa, s.nis, k.nama AS kelas
     FROM izin_siswa i JOIN siswa s ON s.id = i.siswa_id
     LEFT JOIN kelas k ON k.id = s.kelas_id
     WHERE i.tgl_selesai >= DATE_SUB(CURDATE(), INTERVAL 60 DAY)
     ORDER BY i.tgl_mulai DESC, i.id DESC LIMIT 200'
);

halaman_atas('Izin dan Sakit', 'Semua siswa aktif', ['nav' => 'siswa']);
?>
<?php if ($pesanGalat !== ''): ?>
  <div class="pesan pesan-galat" role="alert"><?= ikon('peringatan', 20) ?><span><?= e($pesanGalat) ?></span></div>
<?php endif; ?>

<?php if ($siswa === []): ?>
  <div class="pesan pesan-info" role="status"><?= ikon('info', 20) ?>
    <span>Belum ada siswa aktif. Tambahkan siswa terlebih dahulu.</span></div>
<?php else: ?>
  <div class="pesan pesan-info" role="status"><?= ikon('info', 20) ?>
    <span>Izin, sakit, dan udzur bersifat <strong>netral</strong>: tidak memotong poin, dan poin otomatis yang sudah terlanjur dibuat pada rentang tanggal dibatalkan.</span>
  </div>

  <form class="kartu form-kartu" method="post" action="<?= e(url('pages/guru/izin.php')) ?>" data-lewati-muat="1">
    <?= csrf_field() ?>
    <input type="hidden" name="aksi" value="tambah">
    <div class="form-grid">
      <?php kotak_cari_siswa(['label' => 'Siswa', 'nama' => 'siswa_id']); ?>
      <div class="form-baris">
        <label for="jenis">Jenis</label>
        <select class="input" id="jenis" name="jenis" required>
          <option value="izin">Izin</option>
          <option value="sakit">Sakit</option>
          <option value="udzur">Udzur (ibadah)</option>
        </select>
      </div>
      <div class="form-baris">
        <label for="keterangan">Keterangan</label>
        <input class="input" id="keterangan" name="keterangan" maxlength="255" placeholder="Opsional">
      </div>
      <div class="form-baris">
        <label for="tgl_mulai">Mulai</label>
        <input class="input" id="tgl_mulai" name="tgl_mulai" type="date" value="<?= e(date('Y-m-d')) ?>" required>
      </div>
      <div class="form-baris">
        <label for="tgl_selesai">Selesai</label>
        <input class="input" id="tgl_selesai" name="tgl_selesai" type="date" value="<?= e(date('Y-m-d')) ?>" required>
      </div>
    </div>
    <div class="form-aksi"><button class="tombol tombol-utama" type="submit"><?= ikon('simpan', 20) ?> Simpan keterangan</button></div>
  </form>
<?php endif; ?>

<div class="tabel-bungkus margin-atas">
  <table class="tabel" id="tabel-izin">
    <thead><tr><th>Siswa</th><th>Jenis</th><th>Rentang</th><th>Keterangan</th><th>Aksi</th></tr></thead>
    <tbody>
    <?php foreach ($daftar as $i): ?>
      <tr>
        <td><strong><?= e($i['nama_siswa']) ?></strong><br><span class="teks-kecil"><?= e($i['kelas'] ?? '-') ?></span></td>
        <td><span class="chip <?= $i['jenis'] === 'sakit' ? 'badge-koral' : ($i['jenis'] === 'udzur' ? 'badge-ungu' : 'badge-kuning') ?>"><?= e($i['jenis']) ?></span></td>
        <td class="mono"><?= e(tanggal_indonesia($i['tgl_mulai'], false)) ?> – <?= e(tanggal_indonesia($i['tgl_selesai'], false)) ?></td>
        <td><?= e($i['keterangan'] ?? '-') ?></td>
        <td>
          <form method="post" action="<?= e(url('pages/guru/izin.php')) ?>" data-konfirmasi="Hapus keterangan izin ini?">
            <?= csrf_field() ?>
            <input type="hidden" name="aksi" value="hapus">
            <input type="hidden" name="izin_id" value="<?= (int) $i['id'] ?>">
            <button class="tombol tombol-putih" type="submit"><?= ikon('hapus', 18) ?> Hapus</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if ($daftar === []): ?><tr><td colspan="5" class="tabel-kosong">Belum ada keterangan izin dalam 60 hari terakhir.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<?php halaman_bawah(); ?>
