<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

$pengguna = auth_wajib();
$pesanGalat = '';
$pesanFoto = '';
$idPengguna = (int) $pengguna['id'];

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_wajib();
    $aksi = (string) ($_POST['aksi'] ?? 'ganti_sandi');

    if ($aksi === 'unggah_foto') {
        $unggah = unggah_foto_profil($_FILES['foto'] ?? []);
        if (!$unggah['ok']) {
            $pesanFoto = $unggah['pesan'];
        } else {
            try {
                $lama = db_nilai('SELECT foto FROM pengguna WHERE id = ?', [$idPengguna], null);
                db_ubah('pengguna', ['foto' => $unggah['path']], 'id = ?', [$idPengguna]);
                hapus_berkas_foto($lama === null ? null : (string) $lama);
                log_akses('unggah_foto_profil', 'pengguna', $idPengguna, $idPengguna);
                flash('Foto profil berhasil diperbarui.');
                alihkan('pages/profil.php');
            } catch (Throwable $galat) {
                hapus_berkas_foto($unggah['path']);
                catat_kesalahan('unggah foto profil gagal: ' . $galat->getMessage());
                $pesanFoto = 'Foto gagal disimpan. Silakan coba lagi.';
            }
        }
    } elseif ($aksi === 'hapus_foto') {
        try {
            $lama = db_nilai('SELECT foto FROM pengguna WHERE id = ?', [$idPengguna], null);
            db_ubah('pengguna', ['foto' => null], 'id = ?', [$idPengguna]);
            hapus_berkas_foto($lama === null ? null : (string) $lama);
            log_akses('hapus_foto_profil', 'pengguna', $idPengguna, $idPengguna);
            flash('Foto profil dihapus.');
            alihkan('pages/profil.php');
        } catch (Throwable $galat) {
            catat_kesalahan('hapus foto profil gagal: ' . $galat->getMessage());
            $pesanFoto = 'Foto gagal dihapus. Silakan coba lagi.';
        }
    } else {
        $hasil = auth_ganti_sandi(
            $idPengguna,
            (string) ($_POST['kata_sandi_lama'] ?? ''),
            (string) ($_POST['kata_sandi_baru'] ?? ''),
            (string) ($_POST['ulangi'] ?? '')
        );
        if ($hasil['ok']) {
            flash($hasil['pesan']);
            alihkan('pages/profil.php');
        }
        $pesanGalat = $hasil['pesan'];
    }
}

$foto = foto_pengguna($idPengguna);
$maksFoto = (int) pengaturan('maks_ukuran_foto', (string) SIDIPA_MAKS_BUKTI);

$siswa = null;
$kelasGuru = [];
try {
    if ($pengguna['peran'] === 'siswa') {
        $siswa = db_satu(
            'SELECT s.nis, s.nama, k.nama AS kelas FROM siswa s LEFT JOIN kelas k ON k.id = s.kelas_id WHERE s.pengguna_id = ? LIMIT 1',
            [(int) $pengguna['id']]
        );
    } elseif ($pengguna['peran'] === 'guru') {
        $kelasGuru = db_semua(
            'SELECT k.nama FROM guru_kelas gk JOIN kelas k ON k.id = gk.kelas_id WHERE gk.guru_id = ? ORDER BY k.nama',
            [(int) $pengguna['id']]
        );
    }
} catch (Throwable $galat) {
    catat_kesalahan('profil: ' . $galat->getMessage());
}

$semester = semester_aktif();

halaman_atas('Profil', 'Akun saya', ['nav' => 'profil']);
?>
<div class="grid grid-2">
  <section class="kartu">
    <p class="label-kecil">Data akun</p>
    <h2><?= e($pengguna['nama']) ?></h2>
    <table class="tabel">
      <tbody>
        <tr><th>Username</th><td class="mono"><?= e($pengguna['username']) ?></td></tr>
        <tr><th>Peran</th><td><span class="badge badge-kuning"><?= e(label_peran($pengguna['peran'])) ?></span></td></tr>
        <tr><th>Status</th><td>Aktif</td></tr>
        <?php if ($siswa !== null): ?>
          <tr><th>NIS</th><td><?= e($siswa['nis']) ?></td></tr>
          <tr><th>Kelas</th><td><?= e($siswa['kelas'] ?? '-') ?></td></tr>
        <?php endif; ?>
        <?php if ($kelasGuru !== []): ?>
          <tr><th>Kelas diampu</th><td><?= e(implode(', ', array_column($kelasGuru, 'nama'))) ?></td></tr>
        <?php endif; ?>
        <tr><th>Semester aktif</th><td><?= e($semester['nama'] ?? '-') ?> (<?= e($semester['tahun_ajaran'] ?? '-') ?>)</td></tr>
      </tbody>
    </table>
    <div class="form-aksi">
      <a class="tombol tombol-putih" href="<?= e(url('pages/logout.php?csrf_token=' . rawurlencode(csrf_token()))) ?>"><?= ikon('keluar', 20) ?> Keluar</a>
    </div>
  </section>

  <section class="kartu form-kartu">
    <p class="label-kecil">Foto profil</p>
    <h2>Foto saya</h2>
    <?php if ($pesanFoto !== ''): ?>
      <div class="pesan pesan-galat" role="alert"><?= ikon('peringatan', 20) ?><span><?= e($pesanFoto) ?></span></div>
    <?php endif; ?>
    <div class="foto-unggah">
      <?php if ($foto !== ''): ?>
        <img class="foto-profil" src="<?= e(url($foto)) ?>" alt="Foto profil <?= e($pengguna['nama']) ?>">
      <?php else: ?>
        <span class="foto-profil foto-inisial" aria-hidden="true"><?= e(inisial($pengguna['nama'])) ?></span>
      <?php endif; ?>
      <div class="foto-aksi">
        <form method="post" action="<?= e(url('pages/profil.php')) ?>" enctype="multipart/form-data">
          <?= csrf_field() ?>
          <input type="hidden" name="aksi" value="unggah_foto">
          <div class="form-baris">
            <label for="foto">Pilih foto</label>
            <input class="input" id="foto" name="foto" type="file" accept="image/jpeg,image/png,image/webp" required>
            <p class="bantuan">JPG, PNG, atau WEBP · maksimal <?= e((string) round($maksFoto / 1048576, 1)) ?> MB.</p>
          </div>
          <div class="form-aksi"><button class="tombol tombol-utama" type="submit"><?= ikon('unggah', 20) ?> Unggah foto</button></div>
        </form>
        <?php if ($foto !== ''): ?>
          <form method="post" action="<?= e(url('pages/profil.php')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="aksi" value="hapus_foto">
            <button class="tombol tombol-putih" type="submit">Hapus foto</button>
          </form>
        <?php endif; ?>
      </div>
    </div>
  </section>

  <section class="kartu form-kartu">
    <p class="label-kecil">Keamanan</p>
    <h2>Ubah kata sandi</h2>
    <?php if ($pesanGalat !== ''): ?>
      <div class="pesan pesan-galat" role="alert"><?= ikon('peringatan', 20) ?><span><?= e($pesanGalat) ?></span></div>
    <?php endif; ?>
    <form method="post" action="<?= e(url('pages/profil.php')) ?>" autocomplete="off">
      <?= csrf_field() ?>
      <div class="form-baris">
        <label for="lama">Kata sandi lama</label>
        <input class="input" id="lama" name="kata_sandi_lama" type="password" required autocomplete="current-password">
      </div>
      <div class="form-baris">
        <label for="baru">Kata sandi baru</label>
        <input class="input" id="baru" name="kata_sandi_baru" type="password" minlength="8" required autocomplete="new-password">
        <p class="bantuan">Minimal 8 karakter.</p>
      </div>
      <div class="form-baris">
        <label for="ulangi">Ulangi kata sandi baru</label>
        <input class="input" id="ulangi" name="ulangi" type="password" minlength="8" required autocomplete="new-password">
      </div>
      <div class="form-aksi"><button class="tombol tombol-utama" type="submit">Simpan</button></div>
    </form>
  </section>
</div>
<?php halaman_bawah(); ?>
