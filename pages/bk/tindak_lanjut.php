<?php
declare(strict_types=1);

/*
 | Tahap 4 — Tindak lanjut pembinaan oleh BK / admin (bagian 8 & 10 PDF).
 | Kegiatan perbaikan boleh menghasilkan catatan poin POSITIF. Kolom
 | pencatat poin tetap tidak ditampilkan ke peran non-admin.
 */

require_once __DIR__ . '/../../app/bootstrap.php';

$pengguna = auth_wajib(['bk', 'admin']);
$idPengguna = (int) $pengguna['id'];
$semester = semester_wajib();
$semesterId = (int) $semester['id'];
$pesanGalat = '';

$katalogPositif = db_semua(
    "SELECT id, nama, aspek_id, poin FROM katalog_poin WHERE jenis = 'positif' AND aktif = 1 AND otomatis = 0 ORDER BY nama"
);

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_wajib();
    $aksi = aksi_post();

    if ($aksi === 'tambah') {
        $siswaId = post_int('siswa_id');
        $pemicu = post('pemicu', '', 120);
        $tindak = post('tindak_lanjut', '', 2000);
        $status = post('status', 'baru', 20);
        $tanggal = post('tanggal', date('Y-m-d'), 10);
        $katalogId = post_int('katalog_id');
        $beriPoin = post_bool('beri_poin');
        $siswa = db_satu('SELECT id, nama FROM siswa WHERE id = ? AND aktif = 1', [$siswaId]);
        $katalog = $katalogId > 0
            ? db_satu("SELECT id, aspek_id, poin, nama FROM katalog_poin WHERE id = ? AND jenis = 'positif' AND aktif = 1", [$katalogId])
            : null;

        if ($siswa === null) {
            $pesanGalat = 'Pilih siswa yang valid.';
        } elseif ($pemicu === '' || !in_array($status, ['baru', 'proses', 'selesai'], true) || !tanggal_valid($tanggal)) {
            $pesanGalat = 'Pemicu, status, dan tanggal wajib diisi dengan benar.';
        } elseif ($beriPoin && $katalog === null) {
            $pesanGalat = 'Pilih katalog poin positif yang valid.';
        } elseif ($beriPoin && !semester_input_terbuka()) {
            $pesanGalat = 'Periode input semester sudah ditutup; catatan poin positif tidak dapat ditambahkan.';
        } else {
            db_transaksi(function () use ($siswaId, $semesterId, $pemicu, $tindak, $status, $tanggal, $beriPoin, $katalog, $idPengguna): void {
                db_sisip('pembinaan', [
                    'siswa_id' => $siswaId,
                    'semester_id' => $semesterId,
                    'pemicu' => $pemicu,
                    'tindak_lanjut' => $tindak !== '' ? $tindak : null,
                    'status' => $status,
                    'dicatat_oleh' => $idPengguna,
                    'tanggal' => $tanggal,
                ]);
                if ($beriPoin && $katalog !== null) {
                    tambah_catatan([
                        'semester_id' => $semesterId,
                        'siswa_id' => $siswaId,
                        'katalog_id' => (int) $katalog['id'],
                        'aspek_id' => (int) $katalog['aspek_id'],
                        'poin' => (int) $katalog['poin'],
                        'sumber' => 'manual',
                        'pencatat_id' => $idPengguna,
                        'keterangan' => 'Kegiatan perbaikan: ' . $katalog['nama'],
                        'tanggal' => $tanggal,
                    ]);
                }
            });
            evaluasi_ambang_siswa($siswaId, $semesterId);
            log_akses('tambah_tindak_lanjut', 'pembinaan');
            flash('Tindak lanjut ' . $siswa['nama'] . ' disimpan' . ($beriPoin ? ' beserta poin positifnya.' : '.'));
            alihkan('pages/bk/tindak_lanjut.php');
        }
    } elseif ($aksi === 'perbarui') {
        $id = post_int('pembinaan_id');
        $status = post('status', '', 20);
        $tindak = post('tindak_lanjut', '', 2000);
        if (!in_array($status, ['baru', 'proses', 'selesai'], true)) {
            $pesanGalat = 'Status tidak valid.';
        } elseif (db_satu('SELECT id FROM pembinaan WHERE id = ?', [$id]) === null) {
            $pesanGalat = 'Data pembinaan tidak ditemukan.';
        } else {
            db_ubah('pembinaan', [
                'status' => $status,
                'tindak_lanjut' => $tindak,
                'dicatat_oleh' => $idPengguna,
            ], 'id = ?', [$id]);
            log_akses('ubah_tindak_lanjut', 'pembinaan', $id);
            flash('Tindak lanjut diperbarui.');
            alihkan('pages/bk/tindak_lanjut.php');
        }
    }
}

$daftar = db_semua(
    'SELECT p.id, p.pemicu, p.tindak_lanjut, p.status, p.tanggal, s.nama AS nama_siswa, k.nama AS kelas
     FROM pembinaan p JOIN siswa s ON s.id = p.siswa_id
     LEFT JOIN kelas k ON k.id = s.kelas_id
     WHERE p.semester_id = ? ORDER BY p.tanggal DESC, p.id DESC LIMIT 100',
    [$semesterId]
);

halaman_atas('Tindak Lanjut', 'BK / pembinaan', [
    'nav' => 'siswa',
    'aksi' => '<a class="tombol tombol-putih" href="' . e(url('pages/guru/pembinaan.php')) . '">' . ikon('pembinaan', 20) . ' Siswa Pembinaan</a>',
]);
?>
<?php if ($pesanGalat !== ''): ?>
  <div class="pesan pesan-galat" role="alert"><?= ikon('peringatan', 20) ?><span><?= e($pesanGalat) ?></span></div>
<?php endif; ?>

<section class="kartu form-kartu">
  <p class="label-kecil">Catatan baru</p>
  <h2>Tindak lanjut pembinaan</h2>
  <form method="post" action="<?= e(url('pages/bk/tindak_lanjut.php')) ?>" data-lewati-muat="1">
    <?= csrf_field() ?>
    <input type="hidden" name="aksi" value="tambah">
    <div class="form-grid">
      <?php kotak_cari_siswa([
          'id' => 'cari-siswa-bk', 'label' => 'Siswa', 'nama' => 'siswa_id',
          'kelas_baris' => 'form-baris',
      ]); ?>
      <div class="form-baris"><label for="pemicu">Pemicu <span class="wajib">wajib</span></label>
        <input class="input" id="pemicu" name="pemicu" maxlength="120" required placeholder="Mis. skor Kedisiplinan di bawah ambang"></div>
      <div class="form-baris"><label for="tanggal">Tanggal</label>
        <input class="input" id="tanggal" name="tanggal" type="date" value="<?= e(date('Y-m-d')) ?>" required></div>
      <div class="form-baris"><label for="status">Status</label>
        <select class="input" id="status" name="status">
          <option value="baru">Baru</option><option value="proses">Proses</option><option value="selesai">Selesai</option>
        </select></div>
      <div class="form-baris lebar-penuh"><label for="tindak_lanjut">Tindak lanjut</label>
        <textarea class="input" id="tindak_lanjut" name="tindak_lanjut" maxlength="2000" placeholder="Panggilan, konseling, kegiatan perbaikan, atau catatan lain"></textarea></div>
      <div class="form-baris lebar-penuh pilihan-baris">
        <input type="checkbox" id="beri_poin" name="beri_poin" value="1">
        <label for="beri_poin" style="text-transform:none;letter-spacing:0;font-size:.9rem">Kegiatan perbaikan juga memberi poin positif</label>
      </div>
      <div class="form-baris lebar-penuh"><label for="katalog_id">Katalog poin positif</label>
        <select class="input" id="katalog_id" name="katalog_id">
          <option value="0">-- Tanpa poin --</option>
          <?php foreach ($katalogPositif as $k): ?>
            <option value="<?= e($k['id']) ?>"><?= e($k['nama']) ?> (+<?= e($k['poin']) ?>)</option>
          <?php endforeach; ?>
        </select>
        <p class="bantuan">Poin hanya ditambahkan bila kotak centang di atas dipilih.</p></div>
    </div>
    <div class="form-aksi"><button class="tombol tombol-utama" type="submit"><?= ikon('simpan', 20) ?> Simpan tindak lanjut</button></div>
  </form>
</section>

<?= bagian_judul('Riwayat tindak lanjut', count($daftar) . ' catatan pada semester ini') ?>
<div class="tabel-bungkus">
  <table class="tabel">
    <thead><tr><th>Siswa</th><th>Pemicu</th><th>Status</th><th>Catatan &amp; aksi</th></tr></thead>
    <tbody>
    <?php foreach ($daftar as $t): ?>
      <tr>
        <td><strong><?= e($t['nama_siswa']) ?></strong><br><span class="teks-kecil"><?= e($t['kelas'] ?? '-') ?></span><br>
          <span class="teks-kecil mono"><?= e(tanggal_indonesia($t['tanggal'], false)) ?></span></td>
        <td><?= e($t['pemicu']) ?></td>
        <td>
          <?php if ($t['status'] === 'baru'): ?><span class="chip badge-koral">Baru</span>
          <?php elseif ($t['status'] === 'proses'): ?><span class="chip badge-kuning">Proses</span>
          <?php else: ?><span class="chip badge-toska">Selesai</span><?php endif; ?>
        </td>
        <td>
          <form method="post" action="<?= e(url('pages/bk/tindak_lanjut.php')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="aksi" value="perbarui">
            <input type="hidden" name="pembinaan_id" value="<?= e($t['id']) ?>">
            <div class="form-baris" style="margin-bottom:6px">
              <textarea class="input" name="tindak_lanjut" maxlength="2000" placeholder="Catatan tindak lanjut"><?= e($t['tindak_lanjut'] ?? '') ?></textarea>
            </div>
            <div class="baris-tombol">
              <select class="input" name="status" aria-label="Status">
                <option value="baru" <?= $t['status'] === 'baru' ? 'selected' : '' ?>>Baru</option>
                <option value="proses" <?= $t['status'] === 'proses' ? 'selected' : '' ?>>Proses</option>
                <option value="selesai" <?= $t['status'] === 'selesai' ? 'selected' : '' ?>>Selesai</option>
              </select>
              <button class="tombol tombol-putih" type="submit"><?= ikon('simpan', 18) ?> Simpan</button>
            </div>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if ($daftar === []): ?><tr><td colspan="4" class="tabel-kosong">Belum ada tindak lanjut tercatat.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<?php halaman_bawah(); ?>
