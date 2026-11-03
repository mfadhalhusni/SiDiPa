<?php
declare(strict_types=1);

/* Daftar "Siswa Pembinaan" (persentase aspek di bawah ambang) + tindak lanjut. */

require_once __DIR__ . '/../../app/bootstrap.php';

$pengguna = auth_wajib(['guru', 'bk', 'admin']);
$peran = (string) $pengguna['peran'];
$idPengguna = (int) $pengguna['id'];
$semester = semester_aktif();
$semesterId = (int) ($semester['id'] ?? 0);
$ambang = (float) pengaturan('ambang_pembinaan_persen', '60');
$pesanGalat = '';

if ($peran === 'guru') {
    $idKelas = id_kelas_guru($idPengguna);
} else {
    $idKelas = array_map('intval', array_column(db_semua('SELECT id FROM kelas WHERE aktif = 1'), 'id'));
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_wajib();
    if (aksi_post() === 'tindak') {
        $id = post_int('pembinaan_id');
        $status = post('status', '', 20);
        $tindak = post('tindak_lanjut', '', 2000);
        $baris = db_satu('SELECT id FROM pembinaan WHERE id = ? LIMIT 1', [$id]);
        if ($baris === null) {
            $pesanGalat = 'Data pembinaan tidak ditemukan.';
        } elseif (!in_array($status, ['baru', 'proses', 'selesai'], true)) {
            $pesanGalat = 'Status tidak valid.';
        } else {
            db_ubah('pembinaan', [
                'status' => $status,
                'tindak_lanjut' => $tindak,
                'dicatat_oleh' => $idPengguna,
            ], 'id = ?', [$id]);
            flash('Tindak lanjut pembinaan diperbarui.');
            alihkan('pages/guru/pembinaan.php');
        }
    }
}

$daftar = siswa_perlu_pembinaan($idKelas, $semesterId);
$tindakan = [];
if ($semesterId > 0) {
    $tindakan = db_semua(
        'SELECT p.id, p.pemicu, p.status, p.tindak_lanjut, p.tanggal, s.nama AS nama_siswa, k.nama AS kelas
         FROM pembinaan p JOIN siswa s ON s.id = p.siswa_id
         LEFT JOIN kelas k ON k.id = s.kelas_id
         WHERE p.semester_id = ? ORDER BY p.tanggal DESC, p.id DESC LIMIT 100',
        [$semesterId]
    );
}

halaman_atas('Siswa Pembinaan', 'Ambang ' . $ambang . '% per aspek', ['nav' => 'siswa']);
?>
<?php if ($pesanGalat !== ''): ?>
  <div class="pesan pesan-galat" role="alert"><?= ikon('peringatan', 20) ?><span><?= e($pesanGalat) ?></span></div>
<?php endif; ?>

<div class="pesan pesan-info" role="status"><?= ikon('info', 20) ?>
  <span>Siswa masuk daftar bila persentase salah satu aspek di bawah <strong><?= e($ambang) ?>%</strong>. Tidak ada peringkat skor terendah; daftar ini untuk pendampingan.</span>
</div>

<div class="tabel-bungkus">
  <table class="tabel" id="tabel-pembinaan">
    <thead><tr><th>Siswa</th><th>Kelas</th><th>Aspek terendah</th><th>Persentase</th><th>Predikat</th></tr></thead>
    <tbody>
    <?php foreach ($daftar as $s): ?>
      <tr>
        <td><strong><?= e($s['nama']) ?></strong><br><span class="teks-kecil mono"><?= e($s['nis']) ?></span></td>
        <td><?= e($s['kelas'] ?? '-') ?></td>
        <td><?= e($s['aspek_terendah']) ?></td>
        <td class="mono"><?= e($s['persentase']) ?>%</td>
        <td><strong><?= e($s['predikat']) ?></strong></td>
      </tr>
    <?php endforeach; ?>
    <?php if ($daftar === []): ?><tr><td colspan="5" class="tabel-kosong">Belum ada siswa yang berada di bawah ambang pembinaan.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>

<?= bagian_judul('Tindak lanjut', 'Catatan pendampingan BK / wali kelas.') ?>
<div class="tabel-bungkus">
  <table class="tabel">
    <thead><tr><th>Siswa</th><th>Pemicu</th><th>Status</th><th>Catatan &amp; aksi</th></tr></thead>
    <tbody>
    <?php foreach ($tindakan as $t): ?>
      <tr>
        <td><strong><?= e($t['nama_siswa']) ?></strong><br><span class="teks-kecil"><?= e($t['kelas'] ?? '-') ?></span></td>
        <td><?= e($t['pemicu']) ?><br><span class="teks-kecil mono"><?= e(tanggal_indonesia($t['tanggal'], false)) ?></span></td>
        <td>
          <?php if ($t['status'] === 'baru'): ?><span class="chip badge-koral">Baru</span>
          <?php elseif ($t['status'] === 'proses'): ?><span class="chip badge-kuning">Proses</span>
          <?php else: ?><span class="chip badge-toska">Selesai</span><?php endif; ?>
        </td>
        <td>
          <p class="teks-kecil" style="margin:0 0 6px"><?= e($t['tindak_lanjut'] ?? '-') ?></p>
          <form method="post" action="<?= e(url('pages/guru/pembinaan.php')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="aksi" value="tindak">
            <input type="hidden" name="pembinaan_id" value="<?= (int) $t['id'] ?>">
            <div class="baris-tombol">
              <select class="input" name="status" aria-label="Status">
                <option value="baru" <?= $t['status'] === 'baru' ? 'selected' : '' ?>>Baru</option>
                <option value="proses" <?= $t['status'] === 'proses' ? 'selected' : '' ?>>Proses</option>
                <option value="selesai" <?= $t['status'] === 'selesai' ? 'selected' : '' ?>>Selesai</option>
              </select>
              <input class="input" name="tindak_lanjut" maxlength="2000" value="<?= e($t['tindak_lanjut'] ?? '') ?>" placeholder="Catatan tindak lanjut">
              <button class="tombol tombol-putih" type="submit"><?= ikon('simpan', 18) ?> Simpan</button>
            </div>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if ($tindakan === []): ?><tr><td colspan="4" class="tabel-kosong">Belum ada catatan tindak lanjut.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<?php halaman_bawah(); ?>
