<?php
declare(strict_types=1);

/* Penilaian bulanan wali kelas atas 4 aspek non-otomatis. */

require_once __DIR__ . '/../../app/bootstrap.php';

$pengguna = auth_wajib(['guru']);
cek_input_semester();
$idGuru = (int) $pengguna['id'];
$semester = semester_wajib();
$semesterId = (int) $semester['id'];
$pesanGalat = '';

$bulan = teks($_GET['bulan'] ?? ($_POST['bulan'] ?? ''), 7);
if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $bulan)) {
    $bulan = date('Y-m');
}
$siswaId = (int) ($_GET['siswa_id'] ?? 0);
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $siswaId = post_int('siswa_id') ?: $siswaId;
}
$aspek = aspek_berkala();
$opsi = [
    'kurang' => 'Kurang (' . (int) pengaturan('penilaian_kurang', '-3') . ')',
    'cukup' => 'Cukup (' . (int) pengaturan('penilaian_cukup', '0') . ')',
    'baik' => 'Baik (' . (int) pengaturan('penilaian_baik', '3') . ')',
    'sangat_baik' => 'Sangat Baik (' . (int) pengaturan('penilaian_sangat_baik', '5') . ')',
];

/* Tidak lagi memuat seluruh daftar siswa: pencarian dilakukan di server
   lewat api/cari_siswa.php, jadi siswa di luar batas 300 tetap bisa dibuka. */
$jumlahSiswa = (int) db_nilai('SELECT COUNT(*) FROM siswa WHERE aktif = 1', [], 0);
$terpilih = $siswaId > 0
    ? db_satu(
        'SELECT s.id, s.nis, s.nama, COALESCE(k.nama, \'-\') AS kelas
         FROM siswa s LEFT JOIN kelas k ON k.id = s.kelas_id
         WHERE s.id = ? AND s.aktif = 1',
        [$siswaId]
    )
    : null;

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && $terpilih !== null) {
    csrf_wajib();
    if (aksi_post() === 'simpan') {
        $bulanPost = post('bulan', '', 7);
        if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $bulanPost)) {
            $pesanGalat = 'Bulan tidak valid.';
        } else {
            $kirim = $_POST['nilai'] ?? [];
            $tersimpan = 0;
            if (is_array($kirim)) {
                foreach ($aspek as $a) {
                    $kunci = (string) $a['id'];
                    $nilai = isset($kirim[$kunci]) ? (string) $kirim[$kunci] : '';
                    if ($nilai === '') {
                        continue;
                    }
                    if (simpan_penilaian_bulanan($semesterId, $siswaId, (int) $a['id'], $bulanPost, $nilai, $idGuru)) {
                        $tersimpan++;
                    } else {
                        $pesanGalat = 'Nilai tidak dikenal: ' . $nilai;
                        break;
                    }
                }
            }
            if ($pesanGalat === '') {
                evaluasi_ambang_siswa($siswaId, $semesterId);
                flash($tersimpan . ' aspek penilaian bulanan tersimpan untuk ' . (string) $terpilih['nama'] . '.');
                alihkan('pages/guru/penilaian.php?siswa_id=' . $siswaId . '&bulan=' . rawurlencode($bulanPost));
            }
        }
    }
}

$nilaiSaatIni = [];
if ($siswaId > 0) {
    foreach (db_semua(
        'SELECT aspek_id, nilai, poin FROM penilaian_bulanan WHERE siswa_id = ? AND bulan = ?',
        [$siswaId, $bulan]
    ) as $n) {
        $nilaiSaatIni[(int) $n['aspek_id']] = $n;
    }
}
$rekap = db_semua(
    'SELECT p.bulan, p.nilai, p.poin, s.nama AS nama_siswa, a.nama AS aspek
     FROM penilaian_bulanan p JOIN siswa s ON s.id = p.siswa_id JOIN aspek a ON a.id = p.aspek_id
     WHERE p.semester_id = ? ORDER BY p.bulan DESC, s.nama, a.urutan LIMIT 200',
    [$semesterId]
);

halaman_atas('Penilaian Bulanan', 'Semua siswa · ' . $bulan, ['nav' => 'siswa']);
?>
<?php if ($pesanGalat !== ''): ?>
  <div class="pesan pesan-galat" role="alert"><?= ikon('peringatan', 20) ?><span><?= e($pesanGalat) ?></span></div>
<?php endif; ?>

<?php if ($jumlahSiswa === 0): ?>
  <div class="pesan pesan-info" role="status"><?= ikon('info', 20) ?>
    <span>Belum ada siswa aktif. Tambahkan siswa terlebih dahulu.</span></div>
<?php else: ?>
  <div class="pesan pesan-info" role="status"><?= ikon('info', 20) ?>
    <span>Empat aspek non-otomatis dinilai per bulan. Unik per semester, siswa, aspek, dan bulan; mengubah nilai membatalkan catatan lama dan membuat catatan baru bersumber <code>berkala</code>.</span>
  </div>

  <form class="kartu form-kartu" method="get" action="<?= e(url('pages/guru/penilaian.php')) ?>" data-lewati-muat="1">
    <div class="form-grid">
      <div class="form-baris">
        <label for="bulan">Bulan</label>
        <input class="input" id="bulan" name="bulan" type="month" value="<?= e($bulan) ?>">
      </div>
      <?php kotak_cari_siswa([
          'id' => 'cari-siswa-penilaian', 'label' => 'Siswa', 'nama' => 'siswa_id',
          'nilai' => $siswaId > 0 ? (string) $siswaId : '',
          'teks' => $terpilih !== null
              ? $terpilih['nama'] . ' · ' . $terpilih['kelas'] : '',
          'kirim' => '1', 'kelas_baris' => 'form-baris',
          'bantuan' => 'Ketik NIS atau nama siswa, lalu pilih dari saran untuk langsung membuka nilainya.',
      ]); ?>
    </div>
    <div class="form-aksi"><button class="tombol" type="submit"><?= ikon('cari', 20) ?> Buka</button></div>
  </form>

  <?php if ($terpilih !== null): ?>
    <form class="kartu form-kartu margin-atas" method="post" action="<?= e(url('pages/guru/penilaian.php')) ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="aksi" value="simpan">
      <input type="hidden" name="bulan" value="<?= e($bulan) ?>">
      <input type="hidden" name="siswa_id" value="<?= $siswaId ?>">
      <p class="label-kecil">Penilaian <?= e($bulan) ?></p>
      <h2 style="margin:0 0 12px"><?= e($terpilih['nama']) ?> <span class="teks-kecil">· <?= e($terpilih['kelas']) ?></span></h2>
      <div class="form-grid">
        <?php foreach ($aspek as $a): ?>
          <div class="form-baris">
            <label for="nilai-<?= (int) $a['id'] ?>"><?= e($a['nama']) ?></label>
            <select class="input" id="nilai-<?= (int) $a['id'] ?>" name="nilai[<?= (int) $a['id'] ?>]">
              <option value="">— Tidak diubah —</option>
              <?php foreach ($opsi as $k => $label): ?>
                <option value="<?= e($k) ?>" <?= ($nilaiSaatIni[(int) $a['id']]['nilai'] ?? '') === $k ? 'selected' : '' ?>><?= e($label) ?></option>
              <?php endforeach; ?>
            </select>
            <?php if (isset($nilaiSaatIni[(int) $a['id']])): ?>
              <p class="bantuan">Tersimpan: <?= e($nilaiSaatIni[(int) $a['id']]['nilai']) ?> · <?= (int) $nilaiSaatIni[(int) $a['id']]['poin'] ?> poin</p>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
      <div class="form-aksi"><button class="tombol tombol-utama" type="submit"><?= ikon('simpan', 20) ?> Simpan penilaian</button></div>
    </form>
  <?php endif; ?>
<?php endif; ?>

<?= bagian_judul('Rekap penilaian tersimpan', 'Semester ' . (string) $semester['nama']) ?>
<div class="tabel-bungkus">
  <table class="tabel">
    <thead><tr><th>Bulan</th><th>Siswa</th><th>Aspek</th><th>Nilai</th><th>Poin</th></tr></thead>
    <tbody>
    <?php foreach ($rekap as $r): ?>
      <tr><td class="mono"><?= e($r['bulan']) ?></td><td><?= e($r['nama_siswa']) ?></td><td><?= e($r['aspek']) ?></td>
        <td><?= e($r['nilai']) ?></td><td class="mono"><?= (int) $r['poin'] ?></td></tr>
    <?php endforeach; ?>
    <?php if ($rekap === []): ?><tr><td colspan="5" class="tabel-kosong">Belum ada penilaian bulanan tersimpan.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<?php halaman_bawah(); ?>
