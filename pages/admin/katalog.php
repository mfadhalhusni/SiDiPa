<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/bootstrap.php';

$pengguna = auth_wajib(['admin']);
$pesanGalat = '';
$daftarAspek = daftar_aspek();
$tingkatList = ['ringan', 'sedang', 'berat', 'positif', 'none'];
$jenisList = ['pelanggaran', 'positif'];

$form = ['id' => 0, 'kode' => '', 'nama' => '', 'aspek_id' => 0, 'jenis' => 'pelanggaran',
    'tingkat' => 'ringan', 'poin' => 0, 'otomatis' => 0, 'wajib_keterangan' => 0, 'aktif' => 1];

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_wajib();
    $aksi = aksi_post();

    if ($aksi === 'simpan') {
        $id = post_int('id');
        $kode = strtoupper(preg_replace('/[^A-Z0-9_]/i', '_', post('kode', '', 40)));
        $nama = post('nama', '', 140);
        $aspekId = post_int('aspek_id');
        $jenis = in_array((string) ($_POST['jenis'] ?? ''), $jenisList, true) ? (string) $_POST['jenis'] : 'pelanggaran';
        $tingkat = in_array((string) ($_POST['tingkat'] ?? ''), $tingkatList, true) ? (string) $_POST['tingkat'] : 'none';
        $poin = post_int('poin');
        $otomatis = post_bool('otomatis') ? 1 : 0;
        $wajib = in_array($tingkat, ['sedang', 'berat'], true) ? 1 : (post_bool('wajib_keterangan') ? 1 : 0);
        $aktif = post_bool('aktif') ? 1 : 0;
        $form = ['id' => $id, 'kode' => $kode, 'nama' => $nama, 'aspek_id' => $aspekId, 'jenis' => $jenis,
            'tingkat' => $tingkat, 'poin' => $poin, 'otomatis' => $otomatis,
            'wajib_keterangan' => $wajib, 'aktif' => $aktif];

        if ($kode === '' || $nama === '' || $aspekId <= 0) {
            $pesanGalat = 'Kode, nama kejadian, dan aspek wajib diisi.';
        } elseif (db_satu('SELECT id FROM aspek WHERE id = ?', [$aspekId]) === null) {
            $pesanGalat = 'Aspek tidak ditemukan.';
        } elseif (db_satu('SELECT id FROM katalog_poin WHERE kode = ? AND id <> ?', [$kode, $id]) !== null) {
            $pesanGalat = 'Kode katalog sudah dipakai.';
        } else {
            try {
                if ($id > 0) {
                    db_jalankan(
                        'UPDATE katalog_poin SET kode = ?, nama = ?, aspek_id = ?, jenis = ?, tingkat = ?, poin = ?, otomatis = ?, wajib_keterangan = ?, aktif = ? WHERE id = ?',
                        [$kode, $nama, $aspekId, $jenis, $tingkat, $poin, $otomatis, $wajib, $aktif, $id]
                    );
                } else {
                    db_sisip('katalog_poin', [
                        'kode' => $kode, 'nama' => $nama, 'aspek_id' => $aspekId, 'jenis' => $jenis,
                        'tingkat' => $tingkat, 'poin' => $poin, 'otomatis' => $otomatis,
                        'wajib_keterangan' => $wajib, 'aktif' => $aktif,
                    ]);
                }
                log_akses($id > 0 ? 'ubah_katalog' : 'tambah_katalog', 'katalog_poin', $id > 0 ? $id : null);
                flash('Katalog poin disimpan.');
                alihkan('pages/admin/katalog.php');
            } catch (Throwable $galat) {
                $pesanGalat = $galat->getMessage();
            }
        }
    } elseif ($aksi === 'toggle') {
        $id = post_int('id');
        db_jalankan('UPDATE katalog_poin SET aktif = 1 - aktif WHERE id = ?', [$id]);
        log_akses('toggle_katalog', 'katalog_poin', $id);
        flash('Status katalog diperbarui.');
        alihkan('pages/admin/katalog.php');
    }
}

if (isset($_GET['edit'])) {
    $baris = db_satu('SELECT * FROM katalog_poin WHERE id = ?', [bilangan($_GET['edit'])]);
    if ($baris !== null) {
        $form = [
            'id' => (int) $baris['id'], 'kode' => $baris['kode'], 'nama' => $baris['nama'],
            'aspek_id' => (int) $baris['aspek_id'], 'jenis' => $baris['jenis'], 'tingkat' => $baris['tingkat'],
            'poin' => (int) $baris['poin'], 'otomatis' => (int) $baris['otomatis'],
            'wajib_keterangan' => (int) $baris['wajib_keterangan'], 'aktif' => (int) $baris['aktif'],
        ];
    }
}

$cari = teks($_GET['cari'] ?? '', 80);
$syarat = ['1 = 1'];
$param = [];
if ($cari !== '') {
    $syarat[] = '(k.nama LIKE ? OR k.kode LIKE ?)';
    $param[] = '%' . $cari . '%';
    $param[] = '%' . $cari . '%';
}
$daftar = db_semua(
    'SELECT k.*, a.nama AS aspek, a.urutan FROM katalog_poin k
     JOIN aspek a ON a.id = k.aspek_id
     WHERE ' . implode(' AND ', $syarat) . '
     ORDER BY a.urutan, k.jenis DESC, k.nama',
    $param
);
$grup = [];
foreach ($daftar as $baris) {
    $grup[$baris['aspek']][] = $baris;
}

halaman_atas('Katalog poin', 'Admin / Aturan penilaian', ['nav' => 'rekap']);
?>
<?php if ($pesanGalat !== ''): ?><div class="pesan pesan-galat" role="alert"><?= ikon('peringatan', 20) ?><span><?= e($pesanGalat) ?></span></div><?php endif; ?>

<section class="kartu form-kartu">
  <p class="label-kecil"><?= $form['id'] > 0 ? 'Ubah kejadian' : 'Tambah kejadian' ?></p>
  <h2><?= $form['id'] > 0 ? e($form['nama']) : 'Kejadian baru' ?></h2>
  <form method="post" action="<?= e(url('pages/admin/katalog.php')) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="aksi" value="simpan">
    <input type="hidden" name="id" value="<?= e($form['id']) ?>">
    <div class="form-grid">
      <div class="form-baris"><label for="kode">Kode unik <span class="wajib">wajib</span></label>
        <input class="input" id="kode" name="kode" value="<?= e($form['kode']) ?>" required maxlength="40" placeholder="DIS_TERLAMBAT">
        <p class="bantuan">Kode dipakai cron untuk kejadian otomatis; jangan ubah kode bawaan. Untuk kejadian otomatis, besar poin diambil dari menu Pengaturan → Bobot poin otomatis.</p></div>
      <div class="form-baris"><label for="nama">Nama kejadian <span class="wajib">wajib</span></label>
        <input class="input" id="nama" name="nama" value="<?= e($form['nama']) ?>" required maxlength="140"></div>
      <div class="form-baris"><label for="aspek_id">Aspek</label>
        <select class="input" id="aspek_id" name="aspek_id" required>
          <?php foreach ($daftarAspek as $a): ?>
            <option value="<?= e($a['id']) ?>" <?= (int) $a['id'] === (int) $form['aspek_id'] ? 'selected' : '' ?>><?= e($a['nama']) ?></option>
          <?php endforeach; ?>
        </select></div>
      <div class="form-baris"><label for="jenis">Jenis</label>
        <select class="input" id="jenis" name="jenis">
          <?php foreach ($jenisList as $j): ?><option value="<?= e($j) ?>" <?= $form['jenis'] === $j ? 'selected' : '' ?>><?= e(ucfirst($j)) ?></option><?php endforeach; ?>
        </select></div>
      <div class="form-baris"><label for="tingkat">Tingkat</label>
        <select class="input" id="tingkat" name="tingkat">
          <?php foreach ($tingkatList as $t): ?><option value="<?= e($t) ?>" <?= $form['tingkat'] === $t ? 'selected' : '' ?>><?= e(ucfirst($t)) ?></option><?php endforeach; ?>
        </select>
        <p class="bantuan">Sedang dan berat otomatis mewajibkan keterangan.</p></div>
      <div class="form-baris"><label for="poin">Poin (boleh minus)</label>
        <input class="input" id="poin" name="poin" type="number" step="1" value="<?= e($form['poin']) ?>" required></div>
      <div class="form-baris lebar-penuh pilihan-baris">
        <input type="checkbox" id="otomatis" name="otomatis" value="1" <?= (int) $form['otomatis'] === 1 ? 'checked' : '' ?>>
        <label for="otomatis" style="text-transform:none;letter-spacing:0;font-size:.9rem">Dihasilkan sistem (cron)</label>
      </div>
      <div class="form-baris lebar-penuh pilihan-baris">
        <input type="checkbox" id="wajib_keterangan" name="wajib_keterangan" value="1" <?= (int) $form['wajib_keterangan'] === 1 ? 'checked' : '' ?>>
        <label for="wajib_keterangan" style="text-transform:none;letter-spacing:0;font-size:.9rem">Wajib keterangan</label>
      </div>
      <div class="form-baris lebar-penuh pilihan-baris">
        <input type="checkbox" id="aktif" name="aktif" value="1" <?= (int) $form['aktif'] === 1 ? 'checked' : '' ?>>
        <label for="aktif" style="text-transform:none;letter-spacing:0;font-size:.9rem">Aktif</label>
      </div>
    </div>
    <div class="form-aksi">
      <button class="tombol tombol-utama" type="submit"><?= ikon('simpan', 20) ?> Simpan</button>
      <?php if ($form['id'] > 0): ?><a class="tombol tombol-putih" href="<?= e(url('pages/admin/katalog.php')) ?>">Batal</a><?php endif; ?>
    </div>
  </form>
</section>

<form class="kartu margin-bawah" method="get" action="<?= e(url('pages/admin/katalog.php')) ?>">
  <div class="form-baris"><label for="cari">Cari kejadian atau kode</label>
    <input class="input" id="cari" name="cari" value="<?= e($cari) ?>" data-saring-tabel=".tabel-katalog"></div>
  <div class="form-aksi"><button class="tombol" type="submit"><?= ikon('cari', 20) ?> Cari</button></div>
</form>

<?php foreach ($grup as $namaAspek => $baris2): ?>
  <?= bagian_judul($namaAspek, count($baris2) . ' kejadian') ?>
  <div class="tabel-bungkus">
    <table class="tabel tabel-katalog">
      <thead><tr><th>Kejadian</th><th>Tingkat</th><th>Poin</th><th>Auto</th><th>Ket.</th><th>Status</th><th>Aksi</th></tr></thead>
      <tbody>
      <?php foreach ($baris2 as $k): ?>
        <tr>
          <td><strong><?= e($k['nama']) ?></strong><br><span class="teks-kecil mono"><?= e($k['kode']) ?></span></td>
          <td><span class="badge <?= $k['tingkat'] === 'berat' ? 'badge-koral' : ($k['tingkat'] === 'positif' ? 'badge-toska' : 'badge-kuning') ?>"><?= e(ucfirst($k['tingkat'])) ?></span></td>
          <td class="mono"><strong><?= (int) $k['poin'] > 0 ? '+' : '' ?><?= e($k['poin']) ?></strong></td>
          <td><?= (int) $k['otomatis'] === 1 ? 'Ya' : 'Tidak' ?></td>
          <td><?= (int) $k['wajib_keterangan'] === 1 ? 'Wajib' : 'Opsional' ?></td>
          <td><span class="badge <?= (int) $k['aktif'] === 1 ? 'badge-toska' : 'badge-koral' ?>"><?= (int) $k['aktif'] === 1 ? 'Aktif' : 'Nonaktif' ?></span></td>
          <td><div class="baris-tombol">
            <a class="tombol tombol-kecil tombol-putih" href="<?= e(url('pages/admin/katalog.php?edit=' . (int) $k['id'])) ?>" aria-label="Ubah kejadian"><?= ikon('ubah', 18) ?></a>
            <form method="post" style="display:inline"><?= csrf_field() ?><input type="hidden" name="aksi" value="toggle"><input type="hidden" name="id" value="<?= e($k['id']) ?>"><button class="tombol tombol-kecil" type="submit" aria-label="Ubah status kejadian"><?= ikon('refresh', 18) ?></button></form>
          </div></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endforeach; ?>
<?php if ($daftar === []): ?><div class="kartu tabel-kosong">Tidak ada kejadian yang cocok.</div><?php endif; ?>
<?php halaman_bawah(); ?>
