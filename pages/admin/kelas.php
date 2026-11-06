<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/bootstrap.php';

$pengguna = auth_wajib(['admin']);
$pesanGalat = '';
$daftarGuru = db_semua("SELECT id, nama FROM pengguna WHERE peran = 'guru' AND aktif = 1 ORDER BY nama");

$form = ['id' => 0, 'nama' => '', 'tingkat' => 'X', 'jurusan' => '-', 'wali_kelas_id' => 0, 'guru_ids' => [], 'aktif' => 1];

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_wajib();
    $aksi = aksi_post();

    if ($aksi === 'simpan') {
        $id = post_int('id');
        $nama = post('nama', '', 60);
        $tingkat = post('tingkat', 'X', 10);
        $jurusan = post('jurusan', '-', 60);
        $waliId = post_int('wali_kelas_id');
        $aktif = post_bool('aktif') ? 1 : 0;
        $guruIds = [];
        if (isset($_POST['guru_ids']) && is_array($_POST['guru_ids'])) {
            foreach ($_POST['guru_ids'] as $gid) {
                $gid = bilangan($gid);
                if ($gid > 0) {
                    $guruIds[$gid] = $gid;
                }
            }
        }
        $guruIds = array_values($guruIds);
        $guruValid = [];
        foreach ($guruIds as $gid) {
            if (db_satu('SELECT id FROM pengguna WHERE id = ? AND peran = ? AND aktif = 1', [$gid, 'guru']) !== null) {
                $guruValid[] = $gid;
            }
        }
        $form = ['id' => $id, 'nama' => $nama, 'tingkat' => $tingkat, 'jurusan' => $jurusan,
            'wali_kelas_id' => $waliId, 'guru_ids' => $guruValid, 'aktif' => $aktif];

        if ($nama === '') {
            $pesanGalat = 'Nama kelas wajib diisi.';
        } elseif ($waliId > 0 && db_satu('SELECT id FROM pengguna WHERE id = ? AND peran = ?', [$waliId, 'guru']) === null) {
            $pesanGalat = 'Wali kelas harus akun guru yang aktif.';
        } else {
            try {
                db_transaksi(function () use ($id, $nama, $tingkat, $jurusan, $waliId, $aktif, $guruValid): void {
                    $bentrok = db_satu('SELECT id FROM kelas WHERE nama = ? AND id <> ?', [$nama, $id]);
                    if ($bentrok !== null) {
                        throw new RuntimeException('Nama kelas sudah dipakai.');
                    }
                    if ($id > 0) {
                        db_jalankan(
                            'UPDATE kelas SET nama = ?, tingkat = ?, jurusan = ?, wali_kelas_id = ?, aktif = ? WHERE id = ?',
                            [$nama, $tingkat, $jurusan, $waliId > 0 ? $waliId : null, $aktif, $id]
                        );
                        $kelasId = $id;
                    } else {
                        $kelasId = db_sisip('kelas', [
                            'nama' => $nama, 'tingkat' => $tingkat, 'jurusan' => $jurusan,
                            'wali_kelas_id' => $waliId > 0 ? $waliId : null, 'aktif' => $aktif,
                        ]);
                    }
                    db_jalankan('DELETE FROM guru_kelas WHERE kelas_id = ?', [$kelasId]);
                    foreach ($guruValid as $gid) {
                        db_sisip('guru_kelas', ['guru_id' => $gid, 'kelas_id' => $kelasId]);
                    }
                });
                log_akses($id > 0 ? 'ubah_kelas' : 'tambah_kelas', 'kelas', $id > 0 ? $id : null);
                flash('Data kelas dan guru pengampu disimpan.');
                alihkan('pages/admin/kelas.php');
            } catch (Throwable $galat) {
                $pesanGalat = $galat->getMessage();
            }
        }
    } elseif (in_array($aksi, ['aktifkan', 'nonaktifkan'], true)) {
        $id = post_int('id');
        db_jalankan('UPDATE kelas SET aktif = ? WHERE id = ?', [$aksi === 'aktifkan' ? 1 : 0, $id]);
        log_akses($aksi . '_kelas', 'kelas', $id);
        flash('Status kelas diperbarui.');
        alihkan('pages/admin/kelas.php');
    }
}

if (isset($_GET['edit'])) {
    $baris = db_satu('SELECT * FROM kelas WHERE id = ?', [bilangan($_GET['edit'])]);
    if ($baris !== null) {
        $form = [
            'id' => (int) $baris['id'], 'nama' => $baris['nama'], 'tingkat' => $baris['tingkat'],
            'jurusan' => $baris['jurusan'], 'wali_kelas_id' => (int) $baris['wali_kelas_id'],
            'guru_ids' => array_map('intval', array_column(db_semua('SELECT guru_id FROM guru_kelas WHERE kelas_id = ?', [(int) $baris['id']]), 'guru_id')),
            'aktif' => (int) $baris['aktif'],
        ];
    }
}

$daftarKelas = db_semua(
    'SELECT k.*, w.nama AS wali,
            (SELECT COUNT(*) FROM siswa s WHERE s.kelas_id = k.id AND s.aktif = 1) AS jumlah_siswa,
            (SELECT GROUP_CONCAT(p.nama SEPARATOR ", ") FROM guru_kelas gk JOIN pengguna p ON p.id = gk.guru_id WHERE gk.kelas_id = k.id) AS pengampu
     FROM kelas k LEFT JOIN pengguna w ON w.id = k.wali_kelas_id
     ORDER BY k.nama'
);

halaman_atas('Kelas dan wali kelas', 'Admin / Data master', ['nav' => 'siswa']);
?>
<?php if ($pesanGalat !== ''): ?><div class="pesan pesan-galat" role="alert"><?= ikon('peringatan', 20) ?><span><?= e($pesanGalat) ?></span></div><?php endif; ?>

<section class="kartu form-kartu">
  <p class="label-kecil"><?= $form['id'] > 0 ? 'Ubah kelas' : 'Tambah kelas' ?></p>
  <h2><?= $form['id'] > 0 ? e($form['nama']) : 'Kelas baru' ?></h2>
  <form method="post" action="<?= e(url('pages/admin/kelas.php')) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="aksi" value="simpan">
    <input type="hidden" name="id" value="<?= e($form['id']) ?>">
    <div class="form-grid">
      <div class="form-baris"><label for="nama">Nama kelas <span class="wajib">wajib</span></label>
        <input class="input" id="nama" name="nama" value="<?= e($form['nama']) ?>" placeholder="X TKJ 1" required maxlength="60"></div>
      <div class="form-baris"><label for="tingkat">Tingkat</label>
        <input class="input" id="tingkat" name="tingkat" value="<?= e($form['tingkat']) ?>" maxlength="10"></div>
      <div class="form-baris"><label for="jurusan">Jurusan</label>
        <input class="input" id="jurusan" name="jurusan" value="<?= e($form['jurusan']) ?>" maxlength="60"></div>
      <div class="form-baris"><label for="wali">Wali kelas</label>
        <select class="input" id="wali" name="wali_kelas_id">
          <option value="0">-- Belum ditetapkan --</option>
          <?php foreach ($daftarGuru as $g): ?>
            <option value="<?= e($g['id']) ?>" <?= (int) $g['id'] === (int) $form['wali_kelas_id'] ? 'selected' : '' ?>><?= e($g['nama']) ?></option>
          <?php endforeach; ?>
        </select></div>
      <div class="form-baris lebar-penuh"><label for="guru_ids">Guru pengampu (bisa lebih dari satu)</label>
        <select class="input" id="guru_ids" name="guru_ids[]" multiple size="5" style="min-height:120px">
          <?php foreach ($daftarGuru as $g): ?>
            <option value="<?= e($g['id']) ?>" <?= in_array((int) $g['id'], $form['guru_ids'], true) ? 'selected' : '' ?>><?= e($g['nama']) ?></option>
          <?php endforeach; ?>
        </select>
        <p class="bantuan">Tahan Ctrl / Command untuk memilih beberapa guru. Penugasan bersifat opsional: semua guru kini dapat menilai seluruh siswa aktif.</p></div>
      <div class="form-baris lebar-penuh pilihan-baris">
        <input type="checkbox" id="aktif" name="aktif" value="1" <?= (int) $form['aktif'] === 1 ? 'checked' : '' ?>>
        <label for="aktif" style="text-transform:none;letter-spacing:0;font-size:.9rem">Kelas aktif</label>
      </div>
    </div>
    <div class="form-aksi">
      <button class="tombol tombol-utama" type="submit"><?= ikon('simpan', 20) ?> Simpan</button>
      <?php if ($form['id'] > 0): ?><a class="tombol tombol-putih" href="<?= e(url('pages/admin/kelas.php')) ?>">Batal</a><?php endif; ?>
    </div>
  </form>
</section>

<?= bagian_judul('Daftar kelas') ?>
<div class="tabel-bungkus">
  <table class="tabel">
    <thead><tr><th>Kelas</th><th>Wali kelas</th><th>Guru pengampu</th><th>Siswa</th><th>Status</th><th>Aksi</th></tr></thead>
    <tbody>
    <?php foreach ($daftarKelas as $k): ?>
      <tr>
        <td><strong><?= e($k['nama']) ?></strong><br><span class="teks-kecil"><?= e($k['tingkat']) ?> / <?= e($k['jurusan']) ?></span></td>
        <td><?= e($k['wali'] ?? '-') ?></td>
        <td><?= e($k['pengampu'] ?? '-') ?></td>
        <td class="mono"><?= e($k['jumlah_siswa']) ?></td>
        <td><span class="badge <?= (int) $k['aktif'] === 1 ? 'badge-toska' : 'badge-koral' ?>"><?= (int) $k['aktif'] === 1 ? 'Aktif' : 'Nonaktif' ?></span></td>
        <td><div class="baris-tombol">
          <a class="tombol tombol-kecil tombol-putih" href="<?= e(url('pages/admin/kelas.php?edit=' . (int) $k['id'])) ?>" aria-label="Ubah kelas"><?= ikon('ubah', 18) ?></a>
          <form method="post" style="display:inline"><?= csrf_field() ?><input type="hidden" name="aksi" value="<?= (int) $k['aktif'] === 1 ? 'nonaktifkan' : 'aktifkan' ?>"><input type="hidden" name="id" value="<?= e($k['id']) ?>"><button class="tombol tombol-kecil" type="submit" aria-label="Ubah status kelas"><?= ikon((int) $k['aktif'] === 1 ? 'silang' : 'centang', 18) ?></button></form>
        </div></td>
      </tr>
    <?php endforeach; ?>
    <?php if ($daftarKelas === []): ?><tr><td colspan="6" class="tabel-kosong">Belum ada kelas. Tambahkan kelas sebelum mengimpor siswa.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<?php halaman_bawah(); ?>
