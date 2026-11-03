<?php
declare(strict_types=1);

/* Izin / sakit / udzur oleh wali kelas. Bersifat netral: poin otomatis dibatalkan. */

require_once __DIR__ . '/../../app/bootstrap.php';

$pengguna = auth_wajib(['guru']);
cek_input_semester();
$idGuru = (int) $pengguna['id'];
$semesterId = (int) (semester_aktif()['id'] ?? 0);
$pesanGalat = '';

$kelasWali = db_semua(
    'SELECT id, nama FROM kelas WHERE wali_kelas_id = ? AND aktif = 1 ORDER BY nama',
    [$idGuru]
);
$idKelasWali = array_map('intval', array_column($kelasWali, 'id'));
$siswa = [];
if ($idKelasWali !== []) {
    $tanda = implode(', ', array_fill(0, count($idKelasWali), '?'));
    $siswa = db_semua(
        'SELECT s.id, s.nis, s.nama, k.nama AS kelas FROM siswa s
         JOIN kelas k ON k.id = s.kelas_id
         WHERE s.aktif = 1 AND s.kelas_id IN (' . $tanda . ') ORDER BY k.nama, s.nama LIMIT 300',
        $idKelasWali
    );
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_wajib();
    $aksi = aksi_post();
    if ($aksi === 'tambah') {
        $siswaId = post_int('siswa_id');
        $mulai = post('tgl_mulai', '', 10);
        $selesai = post('tgl_selesai', '', 10);
        $jenis = post('jenis', '', 10);
        $keterangan = post('keterangan', '', 255);
        $milik = $siswaId > 0 && in_array(
            (int) db_nilai('SELECT kelas_id FROM siswa WHERE id = ? LIMIT 1', [$siswaId], 0),
            $idKelasWali,
            true
        );
        if (!$milik) {
            $pesanGalat = 'Pilih siswa dari kelas yang Anda walikan.';
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
        $boleh = (int) db_nilai(
            'SELECT COUNT(*) FROM izin_siswa i JOIN siswa s ON s.id = i.siswa_id
             JOIN kelas k ON k.id = s.kelas_id WHERE i.id = ? AND k.wali_kelas_id = ?',
            [$id, $idGuru],
            0
        );
        if ($boleh === 0) {
            $pesanGalat = 'Data izin tidak ditemukan di kelas Anda.';
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

halaman_atas('Izin dan Sakit', 'Wali kelas', ['nav' => 'siswa']);
?>
<?php if ($pesanGalat !== ''): ?>
  <div class="pesan pesan-galat" role="alert"><?= ikon('peringatan', 20) ?><span><?= e($pesanGalat) ?></span></div>
<?php endif; ?>

<?php if ($kelasWali === []): ?>
  <div class="pesan pesan-info" role="status"><?= ikon('info', 20) ?>
    <span>Halaman ini khusus wali kelas. Anda belum ditetapkan sebagai wali kelas.</span></div>
<?php else: ?>
  <div class="pesan pesan-info" role="status"><?= ikon('info', 20) ?>
    <span>Izin, sakit, dan udzur bersifat <strong>netral</strong>: tidak memotong poin, dan poin otomatis yang sudah terlanjur dibuat pada rentang tanggal dibatalkan.</span>
  </div>

  <form class="kartu form-kartu" method="post" action="<?= e(url('pages/guru/izin.php')) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="aksi" value="tambah">
    <div class="form-grid">
      <div class="form-baris lebar-penuh">
        <label for="siswa_id">Siswa <span class="wajib">wajib</span></label>
        <select class="input" id="siswa_id" name="siswa_id" required>
          <option value="">— Pilih siswa —</option>
          <?php foreach ($siswa as $s): ?>
            <option value="<?= (int) $s['id'] ?>"><?= e($s['nama']) ?> · <?= e($s['nis']) ?> · <?= e($s['kelas']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
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
