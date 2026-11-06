<?php
declare(strict_types=1);

/*
 | Tahap 4 — Semester & Sertifikat (bagian 9 & 10 PDF).
 | Alur: admin menutup periode input -> sistem menghitung skor/persentase/
 | predikat per aspek -> admin memverifikasi -> "Terbitkan sertifikat"
 | (per kelas atau semua) -> snapshot JSON dibekukan -> WA masuk antrian.
 | Pembatalan menyimpan alasan, pelaku, dan waktu. Baris tidak dihapus.
 */

require_once __DIR__ . '/../../app/bootstrap.php';

$pengguna = auth_wajib(['admin']);
$idAdmin = (int) $pengguna['id'];
$pesanGalat = '';

$semesterId = bilangan($_GET['semester'] ?? $_POST['semester'] ?? 0);
$kelasId = bilangan($_GET['kelas'] ?? $_POST['kelas'] ?? 0);
$daftarSemester = daftar_semester();
if ($semesterId <= 0) {
    $semesterId = (int) ($daftarSemester[0]['id'] ?? 0);
}
$semesterPilih = semester_dari_id($semesterId) ?? ($daftarSemester[0] ?? null);
$semesterId = (int) ($semesterPilih['id'] ?? 0);
$daftarKelas = daftar_kelas(false);

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_wajib();
    $aksi = aksi_post();

    if ($aksi === 'tambah_semester') {
        $nama = post('nama', '', 80);
        $tahun = post('tahun_ajaran', '', 20);
        $angka = bilangan($_POST['semester'] ?? 0);
        $mulai = post('tgl_mulai', '', 10);
        $selesai = post('tgl_selesai', '', 10);
        if ($nama === '' || $tahun === '' || !in_array($angka, [1, 2], true)
            || !tanggal_valid($mulai) || !tanggal_valid($selesai) || $selesai <= $mulai) {
            $pesanGalat = 'Lengkapi nama, tahun ajaran, semester (1/2), serta rentang tanggal yang valid.';
        } elseif (db_satu('SELECT id FROM semester WHERE tahun_ajaran = ? AND semester = ?', [$tahun, $angka]) !== null) {
            $pesanGalat = 'Kombinasi tahun ajaran dan semester sudah ada.';
        } else {
            db_sisip('semester', [
                'nama' => $nama, 'tahun_ajaran' => $tahun, 'semester' => $angka,
                'tgl_mulai' => $mulai, 'tgl_selesai' => $selesai,
                'status' => 'berjalan', 'aktif' => 0,
            ]);
            log_akses('tambah_semester', 'semester');
            flash('Semester ditambahkan. Aktifkan bila siap menerima input.');
            alihkan('pages/admin/sertifikat.php');
        }
    } elseif (in_array($aksi, ['aktifkan', 'tutup', 'buka_kembali'], true)) {
        $id = post_int('id');
        $target = semester_dari_id($id);
        if ($target === null) {
            $pesanGalat = 'Semester tidak ditemukan.';
        } elseif ($aksi === 'aktifkan' && (string) $target['status'] !== 'berjalan') {
            $pesanGalat = 'Semester yang sudah ditutup tidak dapat diaktifkan. Gunakan tombol Buka kembali.';
        } else {
            db_transaksi(function () use ($id, $aksi): void {
                if ($aksi !== 'tutup') {
                    db_jalankan('UPDATE semester SET aktif = 0');
                    db_jalankan("UPDATE semester SET aktif = 1, status = 'berjalan' WHERE id = ?", [$id]);
                } else {
                    db_jalankan("UPDATE semester SET aktif = 0, status = 'ditutup' WHERE id = ?", [$id]);
                }
            });
            log_akses($aksi . '_semester', 'semester', $id);
            $pesan = [
                'aktifkan' => 'Semester diaktifkan. Skor siswa mulai dari 0 lagi.',
                'tutup' => 'Periode input ditutup. Catat poin, scan, izin, dan penilaian bulanan dibekukan.',
                'buka_kembali' => 'Periode input dibuka kembali dan semester ini menjadi aktif.',
            ][$aksi];
            flash($pesan);
            alihkan('pages/admin/sertifikat.php?semester=' . $id);
        }
    } elseif ($aksi === 'terbitkan') {
        $idSemester = post_int('semester');
        $idKelas = post_int('kelas');
        $target = semester_dari_id($idSemester);
        if ($target === null || (string) $target['status'] !== 'ditutup') {
            $pesanGalat = 'Tutup periode input semester yang dipilih terlebih dahulu.';
        } else {
            $syarat = ['s.aktif = 1'];
            $param = [];
            if ($idKelas > 0) {
                $syarat[] = 's.kelas_id = ?';
                $param[] = $idKelas;
            }
            $siswa = db_semua(
                'SELECT s.id, s.nama FROM siswa s WHERE ' . implode(' AND ', $syarat) . ' ORDER BY s.nama LIMIT 500',
                $param
            );
            $berhasil = 0;
            $sudah = 0;
            $gagal = [];
            foreach ($siswa as $s) {
                $hasil = terbitkan_sertifikat((int) $s['id'], $idSemester, $idAdmin);
                if ($hasil['ok']) {
                    $berhasil++;
                } elseif (str_contains((string) $hasil['pesan'], 'sudah terbit')) {
                    $sudah++;
                } else {
                    $gagal[] = $s['nama'] . ': ' . $hasil['pesan'];
                }
            }
            log_akses('terbitkan_sertifikat_masal', 'semester', $idSemester);
            flash($berhasil . ' sertifikat diterbitkan, ' . $sudah . ' sudah terbit sebelumnya'
                . ($gagal === [] ? '.' : '. Gagal: ' . implode('; ', array_slice($gagal, 0, 3))));
            alihkan('pages/admin/sertifikat.php?semester=' . $idSemester . '&kelas=' . $idKelas);
        }
    } elseif ($aksi === 'batalkan') {
        $id = post_int('sertifikat_id');
        $alasan = post('alasan_batal', '', 255);
        $hasil = batalkan_sertifikat($id, $idAdmin, $alasan);
        flash($hasil['pesan'], $hasil['ok'] ? 'sukses' : 'galat');
        alihkan('pages/admin/sertifikat.php?semester=' . $semesterId . '&kelas=' . $kelasId);
    }
}

$statistik = [
    'siswa' => (int) db_nilai('SELECT COUNT(*) FROM siswa WHERE aktif = 1', [], 0),
    'terbit' => (int) db_nilai("SELECT COUNT(*) FROM sertifikat WHERE semester_id = ? AND status = 'terbit'", [$semesterId], 0),
    'batal' => (int) db_nilai("SELECT COUNT(*) FROM sertifikat WHERE semester_id = ? AND status = 'dibatalkan'", [$semesterId], 0),
];

$syaratSertifikat = ['sr.semester_id = ?'];
$paramSertifikat = [$semesterId];
if ($kelasId > 0) {
    $syaratSertifikat[] = 's.kelas_id = ?';
    $paramSertifikat[] = $kelasId;
}
$daftarSertifikat = db_semua(
    'SELECT sr.id, sr.kode, sr.status, sr.tanggal_terbit, sr.alasan_batal,
            s.nis, s.nama AS nama_siswa, k.nama AS kelas,
            sm.nama AS semester, sm.tahun_ajaran
     FROM sertifikat sr
     JOIN siswa s ON s.id = sr.siswa_id
     JOIN semester sm ON sm.id = sr.semester_id
     LEFT JOIN kelas k ON k.id = s.kelas_id
     WHERE ' . implode(' AND ', $syaratSertifikat) . '
     ORDER BY k.nama, s.nama LIMIT 500',
    $paramSertifikat
);

halaman_atas('Semester & Sertifikat', 'Admin / Akhir periode', [
    'nav' => 'beranda',
    'aksi' => '<a class="tombol tombol-putih" href="' . e(url('verifikasi.php')) . '">' . ikon('mata', 20) . ' Verifikasi publik</a>',
]);
?>
<?php if ($pesanGalat !== ''): ?>
  <div class="pesan pesan-galat" role="alert"><?= ikon('peringatan', 20) ?><span><?= e($pesanGalat) ?></span></div>
<?php endif; ?>

<div class="pesan <?= semester_input_terbuka() ? 'pesan-sukses' : 'pesan-info' ?>" role="status">
  <?= ikon(semester_input_terbuka() ? 'centang' : 'kunci', 20) ?>
  <span><?= semester_input_terbuka()
    ? 'Periode input masih terbuka. Tutup semester sebelum menerbitkan sertifikat.'
    : 'Periode input tertutup. Catat poin, scan, izin, dan penilaian bulanan dibekukan; sertifikat dapat diterbitkan.' ?></span>
</div>

<?= bagian_judul('Daftar semester', 'Hanya satu semester aktif pada satu waktu.') ?>
<div class="tabel-bungkus margin-bawah">
  <table class="tabel">
    <thead><tr><th>Semester</th><th>Tahun ajaran</th><th>Periode</th><th>Status</th><th>Aksi</th></tr></thead>
    <tbody>
    <?php foreach ($daftarSemester as $sm): ?>
      <?php $berjalan = (string) $sm['status'] === 'berjalan'; $aktif = (int) $sm['aktif'] === 1; ?>
      <tr>
        <td><strong><?= e($sm['nama']) ?></strong><br><span class="teks-kecil">Semester <?= e($sm['semester']) ?></span></td>
        <td class="mono"><?= e($sm['tahun_ajaran']) ?></td>
        <td class="mono teks-kecil"><?= e($sm['tgl_mulai']) ?><br><?= e($sm['tgl_selesai']) ?></td>
        <td><span class="chip <?= $aktif ? 'badge-toska' : ($berjalan ? 'badge-kuning' : 'badge-abu') ?>">
          <?= $aktif ? 'Aktif' : ($berjalan ? 'Siap' : 'Ditutup') ?></span></td>
        <td>
          <div class="baris-tombol">
            <a class="tombol tombol-kecil tombol-putih" href="<?= e(url('pages/admin/sertifikat.php?semester=' . (int) $sm['id'])) ?>" aria-label="Lihat semester"><?= ikon('mata', 16) ?></a>
            <?php if ($berjalan && !$aktif): ?>
              <form method="post"><?= csrf_field() ?><input type="hidden" name="aksi" value="aktifkan"><input type="hidden" name="id" value="<?= e($sm['id']) ?>">
                <button class="tombol tombol-kecil" type="submit" data-konfirmasi="Aktifkan semester ini? Semester lain dinonaktifkan.">Aktifkan</button></form>
            <?php endif; ?>
            <?php if ($berjalan): ?>
              <form method="post"><?= csrf_field() ?><input type="hidden" name="aksi" value="tutup"><input type="hidden" name="id" value="<?= e($sm['id']) ?>">
                <button class="tombol tombol-kecil tombol-putih" type="submit" data-konfirmasi="Tutup periode input? Semua input poin dan scan dibekukan.">Tutup</button></form>
            <?php else: ?>
              <form method="post"><?= csrf_field() ?><input type="hidden" name="aksi" value="buka_kembali"><input type="hidden" name="id" value="<?= e($sm['id']) ?>">
                <button class="tombol tombol-kecil tombol-putih" type="submit" data-konfirmasi="Buka kembali periode input semester ini?">Buka kembali</button></form>
            <?php endif; ?>
          </div>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if ($daftarSemester === []): ?><tr><td colspan="5" class="tabel-kosong">Belum ada semester.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>

<section class="kartu form-kartu">
  <p class="label-kecil">Semester baru</p>
  <h2>Mulai periode dari skor 0</h2>
  <form method="post" action="<?= e(url('pages/admin/sertifikat.php')) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="aksi" value="tambah_semester">
    <div class="form-grid">
      <div class="form-baris"><label for="nama">Nama semester <span class="wajib">wajib</span></label>
        <input class="input" id="nama" name="nama" maxlength="80" placeholder="Mis. Ganjil 2026/2027" required></div>
      <div class="form-baris"><label for="tahun_ajaran">Tahun ajaran <span class="wajib">wajib</span></label>
        <input class="input" id="tahun_ajaran" name="tahun_ajaran" maxlength="20" placeholder="2026/2027" required></div>
      <div class="form-baris"><label for="semester_baru">Semester ke-</label>
        <select class="input" id="semester_baru" name="semester"><option value="1">1 (Ganjil)</option><option value="2">2 (Genap)</option></select></div>
      <div class="form-baris"><label for="tgl_mulai">Tanggal mulai <span class="wajib">wajib</span></label>
        <input class="input" id="tgl_mulai" name="tgl_mulai" type="date" required></div>
      <div class="form-baris"><label for="tgl_selesai">Tanggal selesai <span class="wajib">wajib</span></label>
        <input class="input" id="tgl_selesai" name="tgl_selesai" type="date" required></div>
    </div>
    <div class="form-aksi"><button class="tombol tombol-utama" type="submit"><?= ikon('simpan', 20) ?> Tambah semester</button></div>
  </form>
</section>

<?= bagian_judul('Terbitkan sertifikat', $semesterPilih ? (string) $semesterPilih['nama'] . ' · ' . $semesterPilih['tahun_ajaran'] : 'Pilih semester') ?>
<section class="kartu form-kartu">
  <div class="grid grid-stat margin-bawah">
    <div class="kartu kartu-stat bg-biru"><p class="label-kecil">Siswa aktif</p><span class="angka mono"><?= e($statistik['siswa']) ?></span></div>
    <div class="kartu kartu-stat bg-toska"><p class="label-kecil">Sudah terbit</p><span class="angka mono"><?= e($statistik['terbit']) ?></span></div>
    <div class="kartu kartu-stat bg-koral"><p class="label-kecil">Dibatalkan</p><span class="angka mono"><?= e($statistik['batal']) ?></span></div>
  </div>
  <p class="teks-kecil">Sistem menghitung skor, persentase, dan predikat tiap aspek lalu membekukannya sebagai <code>data_json</code>. Kode berformat <code>SDP-TAHUNSEM-NIS-ACAK4</code>. Pesan sertifikat terbit masuk antrian WhatsApp.</p>
  <form method="post" action="<?= e(url('pages/admin/sertifikat.php')) ?>" class="form-grid">
    <?= csrf_field() ?>
    <input type="hidden" name="aksi" value="terbitkan">
    <div class="form-baris"><label for="semester">Semester</label>
      <select class="input" id="semester" name="semester">
        <?php foreach ($daftarSemester as $sm): ?>
          <option value="<?= e($sm['id']) ?>" <?= (int) $sm['id'] === $semesterId ? 'selected' : '' ?>><?= e($sm['nama']) ?> (<?= e($sm['status']) ?>)</option>
        <?php endforeach; ?>
      </select></div>
    <div class="form-baris"><label for="kelas">Cakupan</label>
      <select class="input" id="kelas" name="kelas">
        <option value="0">Semua siswa aktif</option>
        <?php foreach ($daftarKelas as $k): ?>
          <option value="<?= e($k['id']) ?>" <?= (int) $k['id'] === $kelasId ? 'selected' : '' ?>><?= e($k['nama']) ?></option>
        <?php endforeach; ?>
      </select></div>
    <div class="form-baris lebar-penuh form-aksi">
      <button class="tombol tombol-utama" type="submit" data-konfirmasi="Terbitkan sertifikat? Skor saat ini dibekukan sebagai snapshot.">
        <?= ikon('sertifikat', 20) ?> Terbitkan sertifikat
      </button>
    </div>
  </form>
</section>

<?= bagian_judul('Daftar sertifikat', count($daftarSertifikat) . ' baris · cetak 2 halaman A4 landscape') ?>
<div class="tabel-bungkus">
  <table class="tabel">
    <thead><tr><th>Siswa</th><th>Kelas</th><th>Kode</th><th>Terbit</th><th>Status</th><th>Aksi</th></tr></thead>
    <tbody>
    <?php foreach ($daftarSertifikat as $sr): ?>
      <?php $sah = (string) $sr['status'] === 'terbit'; ?>
      <tr>
        <td><strong><?= e($sr['nama_siswa']) ?></strong><br><span class="teks-kecil mono"><?= e($sr['nis']) ?></span></td>
        <td><?= e($sr['kelas'] ?? '-') ?></td>
        <td class="mono teks-kecil"><?= e($sr['kode']) ?></td>
        <td class="mono"><?= e(tanggal_indonesia($sr['tanggal_terbit'], false)) ?></td>
        <td>
          <span class="chip <?= $sah ? 'badge-toska' : 'badge-koral' ?>"><?= $sah ? 'Terbit' : 'Dibatalkan' ?></span>
          <?php if (!$sah && !empty($sr['alasan_batal'])): ?><p class="teks-kecil" style="margin:6px 0 0"><?= e($sr['alasan_batal']) ?></p><?php endif; ?>
        </td>
        <td>
          <div class="baris-tombol">
            <?php if ($sah): ?>
              <a class="tombol tombol-kecil tombol-utama" href="<?= e(url('pages/umum/cetak_sertifikat.php?kode=' . rawurlencode((string) $sr['kode']))) ?>"><?= ikon('cetak', 16) ?> Cetak</a>
              <a class="tombol tombol-kecil tombol-putih" href="<?= e(url('verifikasi.php?kode=' . rawurlencode((string) $sr['kode']))) ?>" aria-label="Verifikasi publik"><?= ikon('mata', 16) ?></a>
              <form method="post" action="<?= e(url('pages/admin/sertifikat.php')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="aksi" value="batalkan">
                <input type="hidden" name="sertifikat_id" value="<?= e($sr['id']) ?>">
                <input type="hidden" name="semester" value="<?= e($semesterId) ?>">
                <input type="hidden" name="kelas" value="<?= e($kelasId) ?>">
                <input class="input" name="alasan_batal" maxlength="255" placeholder="Alasan pembatalan" required style="max-width:190px">
                <button class="tombol tombol-kecil tombol-putih" type="submit" data-konfirmasi="Batalkan sertifikat ini?">Batalkan</button>
              </form>
            <?php else: ?>
              <span class="teks-kecil">Cetak dinonaktifkan</span>
            <?php endif; ?>
          </div>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if ($daftarSertifikat === []): ?><tr><td colspan="6" class="tabel-kosong">Belum ada sertifikat pada semester ini.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<?php halaman_bawah(); ?>
