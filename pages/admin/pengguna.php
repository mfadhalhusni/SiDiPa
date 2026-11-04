<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/bootstrap.php';

$pengguna = auth_wajib(['admin']);
$pesanGalat = '';
$peranList = ['admin', 'guru', 'bk', 'kepsek', 'siswa', 'wali'];

$form = ['id' => 0, 'username' => '', 'nama' => '', 'peran' => 'guru', 'aktif' => 1, 'wajib_ganti_password' => 1];

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_wajib();
    $aksi = aksi_post();

    if ($aksi === 'simpan') {
        $id = post_int('id');
        $username = post('username', '', 60);
        $nama = post('nama', '', 120);
        $peran = in_array((string) ($_POST['peran'] ?? ''), $peranList, true) ? (string) $_POST['peran'] : 'guru';
        $aktif = post_bool('aktif') ? 1 : 0;
        $wajibGanti = post_bool('wajib_ganti_password') ? 1 : 0;
        if ($peran === 'siswa') {
            $username = username_siswa_bersih($username);
        }
        $sandi = (string) ($_POST['kata_sandi'] ?? '');
        $sahUsername = $peran === 'siswa' ? username_siswa_sah($username) : '';
        $form = ['id' => $id, 'username' => $username, 'nama' => $nama, 'peran' => $peran,
            'aktif' => $aktif, 'wajib_ganti_password' => $wajibGanti];

        if ($username === '' || $nama === '') {
            $pesanGalat = 'Username dan nama lengkap wajib diisi.';
        } elseif ($sahUsername !== '') {
            $pesanGalat = $sahUsername;
        } elseif ($id === 0 && !auth_sandi_valid($sandi)) {
            $pesanGalat = 'Kata sandi awal minimal 8 karakter.';
        } elseif ($id > 0 && $sandi !== '' && !auth_sandi_valid($sandi)) {
            $pesanGalat = 'Kata sandi baru minimal 8 karakter.';
        } elseif ($id === (int) $pengguna['id'] && ($aktif === 0 || $peran !== 'admin')) {
            $pesanGalat = 'Akun Anda sendiri tidak boleh dinonaktifkan atau diubah perannya.';
        } else {
            try {
                db_transaksi(function () use ($id, $username, $nama, $peran, $aktif, $wajibGanti, $sandi): void {
                    if (db_satu('SELECT id FROM pengguna WHERE username = ? AND id <> ?', [$username, $id]) !== null) {
                        throw new RuntimeException('Username sudah dipakai.');
                    }
                    if ($id > 0) {
                        db_jalankan(
                            'UPDATE pengguna SET username = ?, nama = ?, peran = ?, aktif = ?, wajib_ganti_password = ? WHERE id = ?',
                            [$username, $nama, $peran, $aktif, $wajibGanti, $id]
                        );
                        if ($sandi !== '') {
                            db_jalankan('UPDATE pengguna SET password_hash = ?, wajib_ganti_password = 1 WHERE id = ?', [password_hash($sandi, PASSWORD_DEFAULT), $id]);
                        }
                    } else {
                        db_sisip('pengguna', [
                            'username' => $username, 'nama' => $nama, 'peran' => $peran,
                            'password_hash' => password_hash($sandi, PASSWORD_DEFAULT),
                            'aktif' => $aktif, 'wajib_ganti_password' => 1,
                        ]);
                    }
                });
                log_akses($id > 0 ? 'ubah_pengguna' : 'tambah_pengguna', 'pengguna', $id > 0 ? $id : null);
                flash('Data pengguna disimpan.');
                alihkan('pages/admin/pengguna.php');
            } catch (Throwable $galat) {
                $pesanGalat = $galat->getMessage();
            }
        }
    } elseif (in_array($aksi, ['aktifkan', 'nonaktifkan'], true)) {
        $id = post_int('id');
        if ($id === (int) $pengguna['id']) {
            $pesanGalat = 'Tidak dapat menonaktifkan akun sendiri.';
        } else {
            db_jalankan('UPDATE pengguna SET aktif = ? WHERE id = ?', [$aksi === 'aktifkan' ? 1 : 0, $id]);
            log_akses($aksi . '_pengguna', 'pengguna', $id);
            flash('Status akun diperbarui.');
            alihkan('pages/admin/pengguna.php');
        }
    } elseif ($aksi === 'reset_sandi') {
        $id = post_int('id');
        $target = db_satu('SELECT peran FROM pengguna WHERE id = ?', [$id]);
        if ($target !== null) {
            $sementara = $target['peran'] === 'siswa' ? 'Siswa@2026' : ($target['peran'] === 'wali' ? 'Wali@2026' : 'Guru@2026');
            db_jalankan('UPDATE pengguna SET password_hash = ?, wajib_ganti_password = 1 WHERE id = ?', [password_hash($sementara, PASSWORD_DEFAULT), $id]);
            log_akses('reset_sandi_pengguna', 'pengguna', $id);
            flash('Kata sandi direset ke ' . $sementara . ' dan wajib diganti saat login pertama.');
        }
        alihkan('pages/admin/pengguna.php');
    }
}

if (isset($_GET['edit'])) {
    $baris = db_satu('SELECT id, username, nama, peran, aktif, wajib_ganti_password FROM pengguna WHERE id = ?', [bilangan($_GET['edit'])]);
    if ($baris !== null) {
        $form = [
            'id' => (int) $baris['id'], 'username' => $baris['username'], 'nama' => $baris['nama'],
            'peran' => $baris['peran'], 'aktif' => (int) $baris['aktif'],
            'wajib_ganti_password' => (int) $baris['wajib_ganti_password'],
        ];
    }
}

$daftar = db_semua('SELECT id, username, nama, peran, aktif, wajib_ganti_password, dibuat_pada FROM pengguna ORDER BY peran, nama LIMIT 300');

halaman_atas('Pengguna', 'Admin / Akses', ['nav' => 'profil']);
?>
<?php if ($pesanGalat !== ''): ?><div class="pesan pesan-galat" role="alert"><?= ikon('peringatan', 20) ?><span><?= e($pesanGalat) ?></span></div><?php endif; ?>

<section class="kartu form-kartu">
  <p class="label-kecil"><?= $form['id'] > 0 ? 'Ubah akun' : 'Tambah akun' ?></p>
  <h2><?= $form['id'] > 0 ? e($form['nama']) : 'Pengguna baru' ?></h2>
  <form method="post" action="<?= e(url('pages/admin/pengguna.php')) ?>" autocomplete="off">
    <?= csrf_field() ?>
    <input type="hidden" name="aksi" value="simpan">
    <input type="hidden" name="id" value="<?= e($form['id']) ?>">
    <div class="form-grid">
      <div class="form-baris"><label for="username">Username <span class="wajib">wajib</span></label>
        <input class="input" id="username" name="username" value="<?= e($form['username']) ?>" required maxlength="60" autocomplete="off">
        <p class="bantuan">Untuk akun siswa wajib memuat huruf dan <strong>tidak boleh berupa NIS/NISN</strong>. Lihat menu Data siswa untuk membuat username otomatis dari nama.</p></div>
      <div class="form-baris"><label for="nama">Nama lengkap <span class="wajib">wajib</span></label>
        <input class="input" id="nama" name="nama" value="<?= e($form['nama']) ?>" required maxlength="120"></div>
      <div class="form-baris"><label for="peran">Peran</label>
        <select class="input" id="peran" name="peran">
          <?php foreach ($peranList as $pr): ?>
            <option value="<?= e($pr) ?>" <?= $form['peran'] === $pr ? 'selected' : '' ?>><?= e(label_peran($pr)) ?></option>
          <?php endforeach; ?>
        </select></div>
      <div class="form-baris"><label for="kata_sandi"><?= $form['id'] > 0 ? 'Kata sandi baru (kosongkan bila tidak diubah)' : 'Kata sandi awal' ?></label>
        <input class="input" id="kata_sandi" name="kata_sandi" type="password" minlength="8" <?= $form['id'] > 0 ? '' : 'required' ?> autocomplete="new-password">
        <p class="bantuan">Minimal 8 karakter. Akun baru wajib mengganti kata sandi saat login pertama.</p></div>
      <div class="form-baris lebar-penuh pilihan-baris">
        <input type="checkbox" id="aktif" name="aktif" value="1" <?= (int) $form['aktif'] === 1 ? 'checked' : '' ?>>
        <label for="aktif" style="text-transform:none;letter-spacing:0;font-size:.9rem">Akun aktif</label>
      </div>
    </div>
    <div class="form-aksi">
      <button class="tombol tombol-utama" type="submit"><?= ikon('simpan', 20) ?> Simpan</button>
      <?php if ($form['id'] > 0): ?><a class="tombol tombol-putih" href="<?= e(url('pages/admin/pengguna.php')) ?>">Batal</a><?php endif; ?>
    </div>
  </form>
</section>

<?= bagian_judul('Daftar pengguna') ?>
<div class="tabel-bungkus">
  <table class="tabel">
    <thead><tr><th>Nama</th><th>Username</th><th>Peran</th><th>Status</th><th>Aksi</th></tr></thead>
    <tbody>
    <?php foreach ($daftar as $u): ?>
      <tr>
        <td><strong><?= e($u['nama']) ?></strong></td>
        <td class="mono"><?= e($u['username']) ?></td>
        <td><span class="badge badge-biru"><?= e(label_peran($u['peran'])) ?></span></td>
        <td><span class="badge <?= (int) $u['aktif'] === 1 ? 'badge-toska' : 'badge-koral' ?>"><?= (int) $u['aktif'] === 1 ? 'Aktif' : 'Nonaktif' ?></span></td>
        <td><div class="baris-tombol">
          <a class="tombol tombol-kecil tombol-putih" href="<?= e(url('pages/admin/pengguna.php?edit=' . (int) $u['id'])) ?>" aria-label="Ubah pengguna"><?= ikon('ubah', 18) ?></a>
          <form method="post" style="display:inline"><?= csrf_field() ?><input type="hidden" name="aksi" value="reset_sandi"><input type="hidden" name="id" value="<?= e($u['id']) ?>"><button class="tombol tombol-kecil" type="submit" data-konfirmasi="Reset kata sandi akun ini?" aria-label="Reset kata sandi">Sandi</button></form>
          <?php if ((int) $u['id'] !== (int) $pengguna['id']): ?>
          <form method="post" style="display:inline"><?= csrf_field() ?><input type="hidden" name="aksi" value="<?= (int) $u['aktif'] === 1 ? 'nonaktifkan' : 'aktifkan' ?>"><input type="hidden" name="id" value="<?= e($u['id']) ?>"><button class="tombol tombol-kecil tombol-putih" type="submit" aria-label="Ubah status"><?= ikon((int) $u['aktif'] === 1 ? 'silang' : 'centang', 18) ?></button></form>
          <?php endif; ?>
        </div></td>
      </tr>
    <?php endforeach; ?>
    <?php if ($daftar === []): ?><tr><td colspan="5" class="tabel-kosong">Belum ada pengguna.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<?php halaman_bawah(); ?>
