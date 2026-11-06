<?php
declare(strict_types=1);

/*
 | Bagian 22 — Siswa bermasalah & level penanganan (Guru BK / admin).
 | Sumber daftar: riwayat pelanggaran semester berjalan (bukan peringkat
 | skor terendah). Setiap penetapan level disimpan sebagai baris baru
 | sehingga riwayat naik/turun level selalu dapat dibaca.
 | Tidak pernah menampilkan identitas pencatat / WA / bukti / keterangan bebas.
 */

require_once __DIR__ . '/../../app/bootstrap.php';

$pengguna = auth_wajib(['bk', 'admin']);
$idPengguna = (int) $pengguna['id'];
$semester = semester_aktif();
$semesterId = (int) ($semester['id'] ?? 0);
$pesanGalat = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_wajib();
    if (aksi_post() === 'tetapkan') {
        $siswaId = post_int('siswa_id');
        $level = post_int('level');
        $status = post('status', 'baru', 20);
        $tanggal = post('tanggal', date('Y-m-d'), 10);
        $tahap = post('tahap', '', 160);
        $penanggung = post('penanggung_jawab', '', 120);
        $catatan = post('catatan', '', 2000);
        $siswa = db_satu('SELECT id, nama FROM siswa WHERE id = ? AND aktif = 1 LIMIT 1', [$siswaId]);

        if (!penanganan_tabel_ada()) {
            $pesanGalat = 'Tabel penanganan_siswa belum ada. Jalankan contoh/migrasi_penanganan_bk.sql pada database Anda.';
        } elseif ($siswa === null) {
            $pesanGalat = 'Pilih siswa yang valid.';
        } elseif (!level_penanganan_sah($level)) {
            $pesanGalat = 'Level penanganan harus L1 sampai L4.';
        } elseif (!status_penanganan_sah($status)) {
            $pesanGalat = 'Status penanganan tidak valid.';
        } elseif (!tanggal_valid($tanggal)) {
            $pesanGalat = 'Tanggal penanganan tidak valid.';
        } else {
            $info = informasi_level($level);
            db_sisip('penanganan_siswa', [
                'siswa_id' => $siswaId,
                'semester_id' => $semesterId,
                'level' => $level,
                'status' => $status,
                'tahap' => $tahap !== '' ? $tahap : $info['tahap'],
                'penanggung_jawab' => $penanggung !== '' ? $penanggung : $info['penanggung_jawab'],
                'catatan' => $catatan !== '' ? $catatan : null,
                'dicatat_oleh' => $idPengguna,
                'tanggal' => $tanggal,
            ]);
            log_akses('tetapkan_level_penanganan', 'penanganan_siswa', $siswaId);
            flash('Penanganan ' . $info['kode'] . ' untuk ' . $siswa['nama'] . ' disimpan.');
            alihkan('pages/bk/penanganan.php');
        }
    }
}

$daftar = $semesterId > 0 ? daftar_siswa_bermasalah($semesterId) : [];
$riwayat = $semesterId > 0 ? riwayat_penanganan_semester($semesterId, 15) : [];

// Filter tampilan (tidak mengubah data).
$saringLevel = (int) teks($_GET['level'] ?? '0', 3);
$saringLevel = level_penanganan_sah($saringLevel) ? $saringLevel : 0;
$cari = teks($_GET['cari'] ?? '', 60);
$tampil = array_values(array_filter($daftar, static function (array $s) use ($saringLevel, $cari): bool {
    if ($saringLevel > 0 && (int) $s['level'] !== $saringLevel) {
        return false;
    }
    return $cari === '' || stripos($s['nama'] . ' ' . $s['nis'] . ' ' . $s['kelas'], $cari) !== false;
}));

$stat = [
    'semua' => count($daftar),
    'l4' => count(array_filter($daftar, static fn($s) => (int) $s['level'] === 4)),
    'l3' => count(array_filter($daftar, static fn($s) => (int) $s['level'] === 3)),
    'baru' => count(array_filter($daftar, static fn($s) => $s['status'] === 'baru')),
    'belum' => count(array_filter($daftar, static fn($s) => !$s['ditetapkan'])),
];

$urlDetail = static function (int $id): string {
    return url('pages/bk/penanganan_detail.php?siswa_id=' . $id);
};

halaman_atas('Siswa Bermasalah', 'Level penanganan BK · ' . ($semester['nama'] ?? 'Belum ada semester aktif'), [
    'nav' => 'siswa',
    'aksi' => '<a class="tombol tombol-putih" href="' . e(url('pages/bk/beranda.php')) . '">' . ikon('beranda', 20) . ' Dashboard BK</a>',
]);
?>
<?php if ($pesanGalat !== ''): ?>
  <div class="pesan pesan-galat" role="alert"><?= ikon('peringatan', 20) ?><span><?= e($pesanGalat) ?></span></div>
<?php endif; ?>
<?php if (!penanganan_tabel_ada()): ?>
  <div class="pesan pesan-galat" role="alert"><?= ikon('peringatan', 20) ?><span><strong>Tabel <code>penanganan_siswa</code> belum ada.</strong> Daftar siswa tetap tampil, tetapi penetapan level belum bisa disimpan. Jalankan isi <code>contoh/migrasi_penanganan_bk.sql</code> sekali lewat phpMyAdmin.</span></div>
<?php endif; ?>

<div class="pesan pesan-info" role="status"><?= ikon('info', 20) ?>
  <span>Siswa masuk daftar bila memiliki <strong>catatan pelanggaran berpoin negatif</strong> pada semester berjalan (catatan dibatalkan tidak dihitung). Level otomatis:
    <strong>L1</strong> 1–9 poin · <strong>L2</strong> ≥10 poin atau ada pelanggaran sedang · <strong>L3</strong> ≥25 poin atau 1 pelanggaran berat · <strong>L4</strong> ≥50 poin atau ≥2 pelanggaran berat.
    Ini daftar pendampingan, <strong>bukan peringkat siswa dengan skor terendah</strong>. Identitas pencatat poin tidak ditampilkan.</span>
</div>

<div class="grid grid-stat margin-bawah">
  <div class="kartu kartu-stat bg-biru"><p class="label-kecil">Siswa bermasalah</p><span class="angka mono"><?= e((string) $stat['semua']) ?></span></div>
  <div class="kartu kartu-stat bg-koral"><p class="label-kecil">Level L4</p><span class="angka mono"><?= e((string) $stat['l4']) ?></span></div>
  <div class="kartu kartu-stat bg-oranye"><p class="label-kecil">Level L3</p><span class="angka mono"><?= e((string) $stat['l3']) ?></span></div>
  <div class="kartu kartu-stat bg-kuning"><p class="label-kecil">Status baru</p><span class="angka mono"><?= e((string) $stat['baru']) ?></span></div>
  <div class="kartu kartu-stat bg-ungu"><p class="label-kecil">Belum ditetapkan</p><span class="angka mono"><?= e((string) $stat['belum']) ?></span></div>
</div>

<section class="kartu form-kartu margin-bawah">
  <p class="label-kecil">Penetapan penanganan</p>
  <h2>Tetapkan / ubah level</h2>
  <p class="teks-kecil" style="margin:0 0 10px">Setiap penyimpanan menambah satu baris riwayat — level lama tetap terbaca di halaman detail siswa.</p>
  <form method="post" action="<?= e(url('pages/bk/penanganan.php')) ?>" data-lewati-muat="1">
    <?= csrf_field() ?>
    <input type="hidden" name="aksi" value="tetapkan">
    <div class="form-grid">
      <div class="form-baris"><label for="siswa_id">Siswa <span class="wajib">wajib</span></label>
        <select class="input" id="siswa_id" name="siswa_id" required>
          <option value="0">-- Pilih siswa bermasalah --</option>
          <?php foreach ($daftar as $s): ?>
            <option value="<?= (int) $s['id'] ?>"><?= e($s['nama'] . ' · ' . $s['nis'] . ' · ' . $s['kelas']) ?> (<?= e($s['level_kode']) ?>)</option>
          <?php endforeach; ?>
        </select></div>
      <div class="form-baris"><label for="level">Level penanganan</label>
        <select class="input" id="level" name="level">
          <?php foreach (LEVEL_PENANGANAN as $angka => $info): ?>
            <option value="<?= (int) $angka ?>"><?= e($info['kode'] . ' — ' . $info['nama']) ?></option>
          <?php endforeach; ?>
        </select></div>
      <div class="form-baris"><label for="status">Status</label>
        <select class="input" id="status" name="status">
          <option value="baru">Baru</option><option value="proses">Proses</option><option value="selesai">Selesai</option>
        </select></div>
      <div class="form-baris"><label for="tanggal">Tanggal</label>
        <input class="input" id="tanggal" name="tanggal" type="date" value="<?= e(date('Y-m-d')) ?>" required></div>
      <div class="form-baris"><label for="penanggung_jawab">Penanggung jawab</label>
        <input class="input" id="penanggung_jawab" name="penanggung_jawab" maxlength="120" placeholder="Kosongkan untuk pakai bawaan level"></div>
      <div class="form-baris lebar-penuh"><label for="tahap">Tahap penanganan</label>
        <input class="input" id="tahap" name="tahap" maxlength="160" placeholder="Kosongkan untuk pakai bawaan level"></div>
      <div class="form-baris lebar-penuh"><label for="catatan">Catatan penanganan</label>
        <textarea class="input" id="catatan" name="catatan" maxlength="2000" placeholder="Hasil konseling, kesepakatan, tindak lanjut, atau alasan perubahan level"></textarea></div>
    </div>
    <div class="form-aksi"><button class="tombol tombol-utama" type="submit"><?= ikon('simpan', 20) ?> Simpan penanganan</button></div>
  </form>
</section>

<?= bagian_judul('Daftar siswa bermasalah', count($tampil) . ' dari ' . count($daftar) . ' siswa · urut level tertinggi lalu poin negatif terbesar') ?>
<form class="baris-tombol margin-bawah" method="get" action="<?= e(url('pages/bk/penanganan.php')) ?>">
  <select class="input" name="level" aria-label="Saring level" style="max-width:220px">
    <option value="0">Semua level</option>
    <?php foreach (LEVEL_PENANGANAN as $angka => $info): ?>
      <option value="<?= (int) $angka ?>" <?= $saringLevel === (int) $angka ? 'selected' : '' ?>><?= e($info['kode'] . ' — ' . $info['nama']) ?></option>
    <?php endforeach; ?>
  </select>
  <input class="input" name="cari" value="<?= e($cari) ?>" placeholder="Cari nama / NIS / kelas" style="max-width:260px">
  <button class="tombol tombol-putih" type="submit"><?= ikon('cari', 18) ?> Saring</button>
</form>

<div class="tabel-bungkus margin-bawah">
  <table class="tabel">
    <thead><tr>
      <th>Siswa</th><th>Kelas</th><th>Poin negatif</th><th>Pelanggaran berat</th>
      <th>Level otomatis</th><th>Level penanganan</th><th>Status</th><th>Penanggung jawab</th><th>Aksi</th>
    </tr></thead>
    <tbody>
    <?php foreach ($tampil as $s): ?>
      <tr>
        <td><strong><?= e($s['nama']) ?></strong><br><span class="teks-kecil mono"><?= e($s['nis']) ?></span></td>
        <td><?= e($s['kelas']) ?></td>
        <td class="mono"><?= e((string) $s['total_negatif']) ?> <span class="teks-kecil">(<?= e((string) $s['jumlah_pelanggaran']) ?> catatan)</span></td>
        <td class="mono"><?= e((string) $s['jumlah_berat']) ?></td>
        <td><span class="chip <?= e(badge_level((int) $s['level_otomatis'])) ?>"><?= e(informasi_level((int) $s['level_otomatis'])['kode']) ?></span><br>
          <span class="teks-kecil"><?= e($s['alasan_otomatis']) ?></span></td>
        <td><span class="chip <?= e(badge_level((int) $s['level'])) ?>"><?= e($s['level_kode']) ?></span><br>
          <span class="teks-kecil"><?= e($s['level_nama']) ?></span>
          <?php if ((int) $s['level'] !== (int) $s['level_otomatis']): ?><br><span class="teks-kecil">ditetapkan BK</span><?php endif; ?></td>
        <td><span class="chip <?= e(badge_status_penanganan($s['status'])) ?>"><?= e(label_status_penanganan($s['status'])) ?></span><br>
          <span class="teks-kecil mono"><?= $s['terakhir'] !== '' ? e(tanggal_indonesia($s['terakhir'], false)) : 'belum ditetapkan' ?></span></td>
        <td><?= e($s['penanggung_jawab']) ?><br><span class="teks-kecil"><?= e($s['tahap']) ?></span></td>
        <td><a class="tombol tombol-kecil tombol-utama" href="<?= e($urlDetail((int) $s['id'])) ?>"><?= ikon('mata', 16) ?> Detail</a></td>
      </tr>
    <?php endforeach; ?>
    <?php if ($tampil === []): ?><tr><td colspan="9" class="tabel-kosong">Tidak ada siswa bermasalah pada filter ini.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>

<?= bagian_judul('Riwayat perubahan penanganan', '15 penetapan terakhir pada semester ini · jejak naik/turun level') ?>
<div class="tabel-bungkus">
  <table class="tabel">
    <thead><tr><th>Tanggal</th><th>Siswa</th><th>Level</th><th>Status</th><th>Penanggung jawab</th><th>Catatan</th></tr></thead>
    <tbody>
    <?php foreach ($riwayat as $r): ?>
      <?php $lv = (int) $r['level']; $info = informasi_level($lv); ?>
      <tr>
        <td class="mono"><?= e(tanggal_indonesia($r['tanggal'], false)) ?></td>
        <td><a href="<?= e($urlDetail((int) $r['siswa_id'])) ?>"><strong><?= e($r['nama_siswa']) ?></strong></a><br><span class="teks-kecil mono"><?= e($r['nis']) ?></span></td>
        <td><span class="chip <?= e(badge_level($lv)) ?>"><?= e($info['kode']) ?></span><br><span class="teks-kecil"><?= e($info['nama']) ?></span></td>
        <td><span class="chip <?= e(badge_status_penanganan((string) $r['status'])) ?>"><?= e(label_status_penanganan((string) $r['status'])) ?></span></td>
        <td><?= e((string) $r['penanggung_jawab']) ?></td>
        <td><?= e((string) ($r['catatan'] !== null && $r['catatan'] !== '' ? $r['catatan'] : $r['tahap'])) ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if ($riwayat === []): ?><tr><td colspan="6" class="tabel-kosong">Belum ada penetapan level tercatat.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<?php halaman_bawah(); ?>
