<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/bootstrap.php';

$pengguna = auth_wajib(['admin']);
$daftarKelas = daftar_kelas(false);
$daftarWali = db_semua("SELECT id, username, nama FROM pengguna WHERE peran = 'wali' AND aktif = 1 ORDER BY nama");
$pesanGalat = '';

$form = [
    'id' => 0, 'nis' => '', 'username' => '', 'nama' => '', 'jenis_kelamin' => 'L', 'kelas_id' => 0,
    'nama_wali' => '', 'no_wa_wali' => '', 'wali_pengguna_id' => 0, 'aktif' => 1,
];

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_wajib();
    $aksi = aksi_post();

    if ($aksi === 'simpan') {
        $id = post_int('id');
        $nis = post('nis', '', 30);
        $nama = post('nama', '', 120);
        /* Username login siswa terpisah dari NIS. Kosong = dibuat otomatis
           dari nama (siswa baru) atau dipertahankan (siswa lama). */
        $usernameDipilih = username_siswa_bersih(post('username', '', 60));
        $penggunaIdLama = 0;
        if ($id > 0) {
            $akunLama = db_satu('SELECT p.id, p.username FROM siswa s JOIN pengguna p ON p.id = s.pengguna_id WHERE s.id = ?', [$id]);
            $penggunaIdLama = (int) ($akunLama['id'] ?? 0);
        }
        if ($usernameDipilih === '' && $id > 0 && $penggunaIdLama > 0) {
            $username = username_siswa_bersih((string) ($akunLama['username'] ?? ''));
        } elseif ($usernameDipilih === '') {
            $username = username_siswa_unik(username_siswa_dari_nama($nama));
        } else {
            $username = $usernameDipilih;
        }
        $jk = in_array((string) ($_POST['jenis_kelamin'] ?? 'L'), ['L', 'P'], true) ? (string) $_POST['jenis_kelamin'] : 'L';
        $kelasId = post_int('kelas_id');
        $namaWali = post('nama_wali', '', 120);
        $noWa = normalisasi_nomor_wa(post('no_wa_wali', '', 25));
        $waliId = post_int('wali_pengguna_id');
        $aktif = post_bool('aktif') ? 1 : 0;

        $form = ['id' => $id, 'nis' => $nis, 'username' => $usernameDipilih, 'nama' => $nama, 'jenis_kelamin' => $jk,
            'kelas_id' => $kelasId, 'nama_wali' => $namaWali, 'no_wa_wali' => $noWa,
            'wali_pengguna_id' => $waliId, 'aktif' => $aktif];

        $galatUsername = username_siswa_sah($username);
        if ($nis === '' || $nama === '' || $kelasId <= 0) {
            $pesanGalat = 'NIS, nama, dan kelas wajib diisi.';
        } elseif ($galatUsername !== '') {
            $pesanGalat = $galatUsername;
        } elseif (db_satu('SELECT id FROM pengguna WHERE username = ? AND id <> ?', [$username, $penggunaIdLama]) !== null) {
            $pesanGalat = 'Username ' . $username . ' sudah dipakai akun lain.';
        } elseif (db_satu('SELECT id FROM kelas WHERE id = ?', [$kelasId]) === null) {
            $pesanGalat = 'Kelas yang dipilih tidak ada.';
        } elseif ($waliId > 0 && db_satu("SELECT id FROM pengguna WHERE id = ? AND peran = 'wali'", [$waliId]) === null) {
            $pesanGalat = 'Akun wali murid yang dipilih tidak valid.';
        } else {
            try {
                db_transaksi(function () use ($id, $nis, $username, $nama, $jk, $kelasId, $namaWali, $noWa, $waliId, $aktif): void {
                    if ($id > 0) {
                        $lama = db_satu('SELECT id, pengguna_id FROM siswa WHERE id = ? FOR UPDATE', [$id]);
                        if ($lama === null) {
                            throw new RuntimeException('Data siswa tidak ditemukan.');
                        }
                        $bentrok = db_satu('SELECT id FROM siswa WHERE nis = ? AND id <> ?', [$nis, $id]);
                        if ($bentrok !== null) {
                            throw new RuntimeException('NIS ' . $nis . ' sudah dipakai siswa lain.');
                        }
                        $userBentrok = db_satu('SELECT id FROM pengguna WHERE username = ? AND id <> ?', [$username, (int) $lama['pengguna_id']]);
                        if ($userBentrok !== null) {
                            throw new RuntimeException('Username ' . $username . ' sudah dipakai.');
                        }
                        db_jalankan(
                            'UPDATE siswa SET nis = ?, nama = ?, jenis_kelamin = ?, kelas_id = ?, nama_wali = ?, no_wa_wali = ?, wali_pengguna_id = ?, aktif = ? WHERE id = ?',
                            [$nis, $nama, $jk, $kelasId, $namaWali !== '' ? $namaWali : null, $noWa !== '' ? $noWa : null,
                                $waliId > 0 ? $waliId : null, $aktif, $id]
                        );
                        db_jalankan('UPDATE pengguna SET username = ?, nama = ?, aktif = ? WHERE id = ?', [$username, $nama, $aktif, (int) $lama['pengguna_id']]);
                    } else {
                        if (db_satu('SELECT id FROM siswa WHERE nis = ?', [$nis]) !== null) {
                            throw new RuntimeException('NIS ' . $nis . ' sudah terdaftar.');
                        }
                        if (db_satu('SELECT id FROM pengguna WHERE username = ?', [$username]) !== null) {
                            throw new RuntimeException('Username ' . $username . ' sudah dipakai.');
                        }
                        $penggunaId = db_sisip('pengguna', [
                            'username' => $username,
                            'password_hash' => password_hash('Siswa@2026', PASSWORD_DEFAULT),
                            'nama' => $nama,
                            'peran' => 'siswa',
                            'aktif' => $aktif,
                            'wajib_ganti_password' => 1,
                        ]);
                        db_sisip('siswa', [
                            'pengguna_id' => $penggunaId,
                            /* qr_token kolom lama, tetap diisi agar skema NOT NULL
                               terpenuhi; QR kartu kini memuat p.username. */
                            'nis' => $nis,
                            'nama' => $nama,
                            'jenis_kelamin' => $jk,
                            'kelas_id' => $kelasId,
                            'qr_token' => acak_token(32),
                            'nama_wali' => $namaWali !== '' ? $namaWali : null,
                            'no_wa_wali' => $noWa !== '' ? $noWa : null,
                            'wali_pengguna_id' => $waliId > 0 ? $waliId : null,
                            'aktif' => $aktif,
                        ]);
                    }
                });
                log_akses($id > 0 ? 'ubah_siswa' : 'tambah_siswa', 'siswa', $id > 0 ? $id : null);
                flash($id > 0
                    ? 'Data siswa diperbarui. Username login: ' . $username
                    : 'Siswa ditambahkan. Username login: ' . $username . ' · Kata sandi sementara: Siswa@2026');
                alihkan('pages/admin/siswa.php');
            } catch (Throwable $galat) {
                $pesanGalat = $galat->getMessage();
            }
        }
    } elseif ($aksi === 'buat_username') {
        /* Migrasi warisan: HANYA akun yang username-nya masih SAMA PERSIS
           dengan NIS-nya yang dibuatkan username dari nama. Username angka
           yang sengaja dibuat admin (dan tidak sama dengan NIS) kini SAH,
           jadi tidak boleh ikut ditimpa. Aman dijalankan berulang. */
        try {
            $daftarLama = db_semua(
                "SELECT s.id, s.nis, s.nama, p.id AS pengguna_id, p.username
                 FROM siswa s JOIN pengguna p ON p.id = s.pengguna_id
                 WHERE p.peran = 'siswa' AND p.username = s.nis
                 ORDER BY s.id"
            );
            $hasil = [];
            db_transaksi(function () use ($daftarLama, &$hasil): void {
                $terpakai = [];
                foreach ($daftarLama as $s) {
                    $baru = username_siswa_unik(
                        username_siswa_dari_nama((string) $s['nama']),
                        (int) $s['pengguna_id'],
                        $terpakai
                    );
                    db_jalankan('UPDATE pengguna SET username = ? WHERE id = ?', [$baru, (int) $s['pengguna_id']]);
                    $terpakai[] = $baru;
                    $hasil[] = ['nis' => (string) $s['nis'], 'nama' => (string) $s['nama'],
                        'lama' => (string) $s['username'], 'baru' => $baru];
                }
            });
            $_SESSION['hasil_username_siswa'] = $hasil;
            log_akses('buat_username_siswa_massal', 'pengguna', count($hasil));
            flash(count($hasil) . ' akun warisan (username = NIS) sudah memakai username baru. Daftarnya tampil di bawah.');
        } catch (Throwable $galat) {
            $pesanGalat = 'Migrasi username gagal: ' . $galat->getMessage();
        }
        alihkan('pages/admin/siswa.php');
    } elseif (in_array($aksi, ['aktifkan', 'nonaktifkan'], true)) {
        $id = post_int('id');
        $nilai = $aksi === 'aktifkan' ? 1 : 0;
        try {
            db_transaksi(function () use ($id, $nilai): void {
                $siswa = db_satu('SELECT pengguna_id FROM siswa WHERE id = ?', [$id]);
                if ($siswa === null) {
                    throw new RuntimeException('Siswa tidak ditemukan.');
                }
                db_jalankan('UPDATE siswa SET aktif = ? WHERE id = ?', [$nilai, $id]);
                db_jalankan('UPDATE pengguna SET aktif = ? WHERE id = ?', [$nilai, (int) $siswa['pengguna_id']]);
            });
            log_akses($aksi . '_siswa', 'siswa', $id);
            flash('Status siswa diperbarui.');
        } catch (Throwable $galat) {
            $pesanGalat = $galat->getMessage();
        }
        alihkan('pages/admin/siswa.php');
    } elseif ($aksi === 'reset_sandi') {
        $id = post_int('id');
        $siswa = db_satu('SELECT pengguna_id FROM siswa WHERE id = ?', [$id]);
        if ($siswa !== null) {
            db_jalankan(
                'UPDATE pengguna SET password_hash = ?, wajib_ganti_password = 1 WHERE id = ?',
                [password_hash('Siswa@2026', PASSWORD_DEFAULT), (int) $siswa['pengguna_id']]
            );
            log_akses('reset_sandi_siswa', 'pengguna', (int) $siswa['pengguna_id']);
            flash('Kata sandi direset ke Siswa@2026 dan wajib diganti saat login pertama.');
        }
        alihkan('pages/admin/siswa.php');
    }
}

if (isset($_GET['edit'])) {
    $baris = db_satu('SELECT s.*, p.username FROM siswa s JOIN pengguna p ON p.id = s.pengguna_id WHERE s.id = ?', [bilangan($_GET['edit'])]);
    if ($baris !== null) {
        $form = [
            'id' => (int) $baris['id'], 'nis' => $baris['nis'], 'username' => (string) ($baris['username'] ?? ''), 'nama' => $baris['nama'],
            'jenis_kelamin' => $baris['jenis_kelamin'], 'kelas_id' => (int) $baris['kelas_id'],
            'nama_wali' => (string) $baris['nama_wali'], 'no_wa_wali' => (string) $baris['no_wa_wali'],
            'wali_pengguna_id' => (int) ($baris['wali_pengguna_id'] ?? 0), 'aktif' => (int) $baris['aktif'],
        ];
    }
}

$cari = teks($_GET['cari'] ?? '', 80);
$filterKelas = bilangan($_GET['kelas'] ?? 0);
$syarat = ['1 = 1'];
$param = [];
if ($cari !== '') {
    $syarat[] = '(s.nama LIKE ? OR s.nis LIKE ? OR p.username LIKE ?)';
    $param[] = '%' . $cari . '%';
    $param[] = '%' . $cari . '%';
    $param[] = '%' . $cari . '%';
}
if ($filterKelas > 0) {
    $syarat[] = 's.kelas_id = ?';
    $param[] = $filterKelas;
}
$daftarSiswa = db_semua(
    'SELECT s.id, s.nis, s.nama, s.jenis_kelamin, s.aktif,
            k.nama AS kelas, p.username
     FROM siswa s
     LEFT JOIN kelas k ON k.id = s.kelas_id
     JOIN pengguna p ON p.id = s.pengguna_id
     WHERE ' . implode(' AND ', $syarat) . '
     ORDER BY k.nama, s.nama LIMIT 200',
    $param
);

halaman_atas('Data siswa', 'Admin / Data master', [
    'nav' => 'siswa',
    'aksi' => '<a class="tombol tombol-putih" href="' . e(url('pages/admin/kartu.php?kelas=' . $filterKelas)) . '">' . ikon('cetak', 20) . ' Cetak Kartu</a>'
        . '<a class="tombol tombol-toska" href="' . e(url('pages/admin/impor_siswa.php')) . '">' . ikon('unggah', 20) . ' Impor CSV</a>',
]);
?>
<?php if ($pesanGalat !== ''): ?><div class="pesan pesan-galat" role="alert"><?= ikon('peringatan', 20) ?><span><?= e($pesanGalat) ?></span></div><?php endif; ?>

<?php
$hasilUsername = $_SESSION['hasil_username_siswa'] ?? [];
unset($_SESSION['hasil_username_siswa']);
if (is_array($hasilUsername) && $hasilUsername !== []): ?>
<section class="kartu form-kartu">
  <p class="label-kecil">Hasil migrasi</p>
  <h2>Username siswa yang baru dibuat</h2>
  <p class="teks-kecil">Kata sandi tidak berubah. Bagikan username ini kepada siswa; <strong>NIS sudah tidak dapat dipakai untuk masuk</strong>. Salin atau cetak halaman ini sebelum ditutup.</p>
  <div class="tabel-bungkus">
    <table class="tabel">
      <thead><tr><th>NIS</th><th>Nama</th><th>Username lama</th><th>Username baru</th></tr></thead>
      <tbody>
      <?php foreach ($hasilUsername as $h): ?>
        <tr><td class="mono"><?= e($h['nis']) ?></td><td><?= e($h['nama']) ?></td>
          <td class="mono"><?= e($h['lama']) ?></td><td><strong class="mono"><?= e($h['baru']) ?></strong></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>
<?php endif; ?>

<section class="kartu form-kartu">
  <p class="label-kecil"><?= $form['id'] > 0 ? 'Ubah data' : 'Tambah siswa' ?></p>
  <h2><?= $form['id'] > 0 ? e($form['nama']) : 'Siswa baru' ?></h2>
  <form method="post" action="<?= e(url('pages/admin/siswa.php')) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="aksi" value="simpan">
    <input type="hidden" name="id" value="<?= e($form['id']) ?>">
    <div class="form-grid">
      <div class="form-baris"><label for="nis">NIS <span class="wajib">wajib</span></label>
        <input class="input" id="nis" name="nis" value="<?= e($form['nis']) ?>" required maxlength="30"></div>
      <div class="form-baris"><label for="nama">Nama lengkap <span class="wajib">wajib</span></label>
        <input class="input" id="nama" name="nama" value="<?= e($form['nama']) ?>" required maxlength="120"></div>
      <div class="form-baris"><label for="username">Username siswa (untuk login)</label>
        <input class="input" id="username" name="username" value="<?= e($form['username']) ?>" maxlength="60" placeholder="bebas: 20260100, AHMAD, atau ahmad.rizky" autocomplete="off">
        <p class="bantuan">Dipakai siswa saat masuk dan sebagai isi QR kartu. <strong>Karakter bebas</strong>: boleh angka semua, huruf semua, huruf besar, spasi, atau simbol (maksimal 60 karakter). Kosongkan agar dibuat otomatis dari nama.</p></div>
      <div class="form-baris"><label for="jk">Jenis kelamin</label>
        <select class="input" id="jk" name="jenis_kelamin">
          <option value="L" <?= $form['jenis_kelamin'] === 'L' ? 'selected' : '' ?>>Laki-laki</option>
          <option value="P" <?= $form['jenis_kelamin'] === 'P' ? 'selected' : '' ?>>Perempuan</option>
        </select></div>
      <div class="form-baris"><label for="kelas_id">Kelas <span class="wajib">wajib</span></label>
        <select class="input" id="kelas_id" name="kelas_id" required>
          <option value="0">-- Pilih kelas --</option>
          <?php foreach ($daftarKelas as $k): ?>
            <option value="<?= e($k['id']) ?>" <?= (int) $k['id'] === (int) $form['kelas_id'] ? 'selected' : '' ?>><?= e($k['nama']) ?></option>
          <?php endforeach; ?>
        </select></div>
      <div class="form-baris"><label for="nama_wali">Nama wali murid</label>
        <input class="input" id="nama_wali" name="nama_wali" value="<?= e($form['nama_wali']) ?>" maxlength="120"></div>
      <div class="form-baris"><label for="no_wa_wali">Nomor WhatsApp wali</label>
        <input class="input" id="no_wa_wali" name="no_wa_wali" value="<?= e($form['no_wa_wali']) ?>" placeholder="08xxxxxxxxxx" maxlength="25">
        <p class="bantuan">Dinormalisasi otomatis ke format 62.</p></div>
      <div class="form-baris"><label for="wali_pengguna_id">Akun portal wali murid</label>
        <select class="input" id="wali_pengguna_id" name="wali_pengguna_id">
          <option value="0">-- Belum ada --</option>
          <?php foreach ($daftarWali as $w): ?>
            <option value="<?= e($w['id']) ?>" <?= (int) $w['id'] === (int) $form['wali_pengguna_id'] ? 'selected' : '' ?>><?= e($w['nama']) ?> (<?= e($w['username']) ?>)</option>
          <?php endforeach; ?>
        </select>
        <p class="bantuan">Buat akun berperan "Wali murid" di menu Pengguna, lalu pilih di sini agar portal wali menampilkan anak ini.</p></div>
      <div class="form-baris lebar-penuh pilihan-baris">
        <input type="checkbox" id="aktif" name="aktif" value="1" <?= (int) $form['aktif'] === 1 ? 'checked' : '' ?>>
        <label for="aktif" style="text-transform:none;letter-spacing:0;font-size:.9rem">Akun aktif</label>
      </div>
    </div>
    <div class="form-aksi">
      <button class="tombol tombol-utama" type="submit"><?= ikon('simpan', 20) ?> Simpan</button>
      <?php if ($form['id'] > 0): ?><a class="tombol tombol-putih" href="<?= e(url('pages/admin/siswa.php')) ?>">Batal</a><?php endif; ?>
    </div>
  </form>
</section>

<section class="kartu form-kartu">
  <p class="label-kecil">Pembaruan akun siswa</p>
  <h2>Buatkan username untuk semua siswa</h2>
  <p class="teks-kecil">Tombol ini <strong>hanya</strong> mengganti akun warisan yang username-nya masih sama persis dengan NIS, dibuatkan username dari nama. Username lain — termasuk username angka pilihan admin — <strong>tidak diubah</strong>. Kata sandi tetap. <strong>QR kartu memuat username siswa</strong>; setelah username berubah, cetak ulang kartu siswa tersebut (lihat bagian 18 README). NIS tetap dipakai untuk kode sertifikat.</p>
  <form method="post" action="<?= e(url('pages/admin/siswa.php')) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="aksi" value="buat_username">
    <button class="tombol tombol-utama" type="submit" data-konfirmasi="Buatkan username baru hanya bagi akun yang masih memakai NIS sebagai username? Username lain tidak diubah."><?= ikon('pengguna', 20) ?> Buatkan username massal</button>
  </form>
</section>

<?= bagian_judul('Daftar siswa', 'Maksimal 200 baris. Gunakan pencarian untuk data besar.') ?>
<section class="kartu">
  <form class="form-grid" method="get" action="<?= e(url('pages/admin/siswa.php')) ?>" style="margin-bottom:14px">
    <?php kotak_cari_siswa([
        'id' => 'cari', 'id_saran' => 'saran-cari-siswa', 'id_petunjuk' => 'petunjuk-cari-siswa',
        'label' => 'Cari nama atau NIS', 'nama' => 'cari', 'teks' => $cari,
        'mode' => 'saring', 'wajib' => false, 'kelas_baris' => 'form-baris',
        'placeholder' => 'Ketik NIS atau nama siswa',
        'atribut_tambahan' => 'data-saring-tabel="#tabel-siswa"',
        'bantuan' => 'Saran muncul otomatis; pilih siswa untuk langsung menyaring tabel.',
        'gaya_petunjuk' => 'margin-top:6px',
    ]); ?>
    <div class="form-baris"><label for="kelas">Filter kelas</label>
      <select class="input" id="kelas" name="kelas" onchange="this.form.submit()">
        <option value="0">Semua kelas</option>
        <?php foreach ($daftarKelas as $k): ?>
          <option value="<?= e($k['id']) ?>" <?= (int) $k['id'] === $filterKelas ? 'selected' : '' ?>><?= e($k['nama']) ?></option>
        <?php endforeach; ?>
      </select></div>
    <div class="form-baris lebar-penuh"><button class="tombol" type="submit"><?= ikon('cari', 20) ?> Cari</button></div>
  </form>

  <div class="tabel-bungkus">
    <table class="tabel" id="tabel-siswa">
      <thead><tr><th>NIS</th><th>Username</th><th>Nama</th><th>Kelas</th><th>Status</th><th>Aksi</th></tr></thead>
      <tbody>
      <?php foreach ($daftarSiswa as $s): ?>
        <tr>
          <td class="mono"><?= e($s['nis']) ?></td>
          <td class="mono"><?= e($s['username'] ?? '-') ?></td>
          <td><strong><?= e($s['nama']) ?></strong><br><span class="teks-kecil"><?= e($s['jenis_kelamin'] === 'L' ? 'Laki-laki' : 'Perempuan') ?></span></td>
          <td><?= e($s['kelas'] ?? '-') ?></td>
          <td><span class="badge <?= (int) $s['aktif'] === 1 ? 'badge-toska' : 'badge-koral' ?>"><?= (int) $s['aktif'] === 1 ? 'Aktif' : 'Nonaktif' ?></span></td>
          <td>
            <div class="baris-tombol">
              <a class="tombol tombol-kecil tombol-putih" href="<?= e(url('pages/admin/siswa.php?edit=' . (int) $s['id'])) ?>" aria-label="Ubah <?= e($s['nama']) ?>"><?= ikon('ubah', 18) ?></a>
              <a class="tombol tombol-kecil tombol-putih" href="<?= e(url('pages/admin/kartu.php?siswa=' . (int) $s['id'])) ?>" aria-label="Cetak kartu <?= e($s['nama']) ?>"><?= ikon('cetak', 18) ?></a>
              <form method="post" style="display:inline"><?= csrf_field() ?><input type="hidden" name="aksi" value="reset_sandi"><input type="hidden" name="id" value="<?= e($s['id']) ?>"><button class="tombol tombol-kecil" type="submit" data-konfirmasi="Reset kata sandi siswa ini ke Siswa@2026?" aria-label="Reset kata sandi">Sandi</button></form>
              <form method="post" style="display:inline"><?= csrf_field() ?><input type="hidden" name="aksi" value="<?= (int) $s['aktif'] === 1 ? 'nonaktifkan' : 'aktifkan' ?>"><input type="hidden" name="id" value="<?= e($s['id']) ?>"><button class="tombol tombol-kecil tombol-putih" type="submit" aria-label="<?= (int) $s['aktif'] === 1 ? 'Nonaktifkan' : 'Aktifkan' ?> siswa"><?= ikon((int) $s['aktif'] === 1 ? 'silang' : 'centang', 18) ?></button></form>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if ($daftarSiswa === []): ?><tr><td colspan="6" class="tabel-kosong">Belum ada siswa. Tambahkan manual atau impor dari CSV.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</section>
<?php halaman_bawah(); ?>
