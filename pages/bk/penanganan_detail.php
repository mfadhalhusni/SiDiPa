<?php
declare(strict_types=1);

/*
 | Bagian 22 — Detail satu siswa bermasalah: identitas, ringkasan poin per
 | aspek, seluruh riwayat pelanggaran (termasuk yang dibatalkan), riwayat
 | level penanganan, dan catatan tindak lanjut BK lama.
 | Akses: bk / admin. Tanpa identitas pencatat, WA, bukti, atau keterangan bebas.
 */

require_once __DIR__ . '/../../app/bootstrap.php';

auth_wajib(['bk', 'admin']);
$semester = semester_aktif();
$semesterId = (int) ($semester['id'] ?? 0);
$siswaId = (int) bilangan($_GET['siswa_id'] ?? 0);

$siswa = $siswaId > 0
    ? db_satu(
        "SELECT s.id, s.nis, s.nama, COALESCE(k.nama, '-') AS kelas
         FROM siswa s LEFT JOIN kelas k ON k.id = s.kelas_id
         WHERE s.id = ? AND s.aktif = 1 LIMIT 1",
        [$siswaId]
    )
    : null;
if ($siswa === null) {
    http_response_code(404);
    echo '<!doctype html><html lang="id"><head><meta charset="utf-8">'
        . '<title>Data siswa tidak ditemukan - SiDiPa</title>'
        . '<link rel="stylesheet" href="' . e(asset('css/sidipa.css')) . '"></head>'
        . '<body class="halaman-sederhana"><main class="kotak-sederhana">'
        . '<h1>Data siswa tidak ditemukan</h1>'
        . '<p>Siswa tidak ada atau sudah dinonaktifkan.</p>'
        . '<a class="tombol" href="' . e(url('pages/bk/penanganan.php')) . '">Kembali ke daftar</a>'
        . '</main></body></html>';
    exit;
}

$ringkasanSemua = $semesterId > 0 ? ringkasan_pelanggaran_semester($semesterId) : [];
$ringkasan = $ringkasanSemua[$siswaId] ?? [
    'total_negatif' => 0, 'jumlah_pelanggaran' => 0, 'jumlah_berat' => 0,
    'jumlah_sedang' => 0, 'level_otomatis' => 0,
];
$terkini = penanganan_terkini_semester($semesterId);
$t = $terkini[$siswaId] ?? null;
$levelOtomatis = (int) $ringkasan['level_otomatis'];
$levelEfektif = $t !== null ? (int) $t['level'] : $levelOtomatis;
$info = informasi_level($levelEfektif > 0 ? $levelEfektif : 1);
$statusEfektif = $t !== null ? (string) $t['status'] : 'baru';

$skor = $semesterId > 0 ? skor_per_aspek($siswaId, $semesterId) : [];
$agregat = ringkasan_skor_siswa($skor);
$pelanggaran = catatan_pelanggaran_siswa($siswaId, $semesterId);
$penanganan = riwayat_penanganan_siswa($siswaId, $semesterId);
$tindakLanjut = $semesterId > 0
    ? db_semua(
        'SELECT id, pemicu, tindak_lanjut, status, tanggal FROM pembinaan
         WHERE siswa_id = ? AND semester_id = ? ORDER BY tanggal DESC, id DESC LIMIT 100',
        [$siswaId, $semesterId]
    )
    : [];

halaman_atas('Detail Penanganan', $siswa['nama'] . ' · ' . $siswa['kelas'], [
    'nav' => 'siswa',
    'aksi' => '<a class="tombol tombol-putih" href="' . e(url('pages/bk/penanganan.php')) . '">' . ikon('panah_kiri', 20) . ' Kembali</a>',
]);
?>

<section class="kartu bg-ungu margin-bawah">
  <p class="label-kecil">Siswa bermasalah · semester <?= e($semester['nama'] ?? '-') ?></p>
  <h2 style="margin:0"><?= e($siswa['nama']) ?></h2>
  <p class="teks-kecil" style="margin:4px 0 0">NIS <?= e($siswa['nis']) ?> · <?= e($siswa['kelas']) ?> · <?= e($semester['tahun_ajaran'] ?? '-') ?></p>
</section>

<div class="grid grid-stat margin-bawah">
  <div class="kartu kartu-stat bg-koral"><p class="label-kecil">Total poin negatif</p><span class="angka mono"><?= e((string) $ringkasan['total_negatif']) ?></span></div>
  <div class="kartu kartu-stat bg-oranye"><p class="label-kecil">Pelanggaran berat</p><span class="angka mono"><?= e((string) $ringkasan['jumlah_berat']) ?></span></div>
  <div class="kartu kartu-stat bg-biru"><p class="label-kecil">Level otomatis</p><span class="angka mono"><?= e($levelOtomatis > 0 ? informasi_level($levelOtomatis)['kode'] : '-') ?></span></div>
  <div class="kartu kartu-stat bg-ungu"><p class="label-kecil">Level penanganan</p><span class="angka mono"><?= e($info['kode']) ?></span></div>
</div>

<section class="kartu margin-bawah">
  <p class="label-kecil">Penanganan berjalan</p>
  <div class="baris-tombol" style="align-items:center;gap:12px">
    <span class="chip <?= e(badge_level($levelEfektif > 0 ? $levelEfektif : 1)) ?>"><?= e($info['kode']) ?> — <?= e($info['nama']) ?></span>
    <span class="chip <?= e(badge_status_penanganan($statusEfektif)) ?>"><?= e(label_status_penanganan($statusEfektif)) ?></span>
  </div>
  <p style="margin:10px 0 4px"><strong>Tahap:</strong> <?= e($t !== null && $t['tahap'] !== '' ? $t['tahap'] : $info['tahap']) ?></p>
  <p style="margin:0 0 4px"><strong>Penanggung jawab:</strong> <?= e($t !== null && $t['penanggung_jawab'] !== '' ? $t['penanggung_jawab'] : $info['penanggung_jawab']) ?></p>
  <p style="margin:0"><strong>Catatan terakhir:</strong> <?= e($t !== null && ($t['catatan'] ?? '') !== '' ? $t['catatan'] : 'Belum ada catatan.') ?></p>
  <p class="teks-kecil" style="margin:8px 0 0">Alasan level otomatis: <?= e(alasan_level_otomatis($ringkasan)) ?>. Catatan dibatalkan tidak menaikkan level.</p>
  <?php if ($levelEfektif !== $levelOtomatis && $t !== null): ?>
    <p class="teks-kecil" style="margin:4px 0 0">Level ditetapkan BK (<?= e($info['kode']) ?>), berbeda dari rekomendasi otomatis <?= e($levelOtomatis > 0 ? informasi_level($levelOtomatis)['kode'] : '-') ?>.</p>
  <?php endif; ?>
  <div class="baris-tombol margin-atas">
    <a class="tombol tombol-utama" href="<?= e(url('pages/bk/penanganan.php')) ?>"><?= ikon('simpan', 18) ?> Tetapkan / ubah level</a>
    <a class="tombol tombol-putih" href="<?= e(url('pages/bk/tindak_lanjut.php')) ?>"><?= ikon('catatan', 18) ?> Tindak lanjut BK</a>
  </div>
</section>

<?= bagian_judul('Ringkasan poin per aspek', 'Predikat per aspek; rata-rata karakter ' . $agregat['rata_persen'] . '% (' . $agregat['predikat'] . ').') ?>
<div class="tabel-bungkus margin-bawah">
  <table class="tabel">
    <thead><tr><th>Aspek</th><th>Skor</th><th>Persentase</th><th>Predikat</th><th>Keterangan</th></tr></thead>
    <tbody>
    <?php foreach ($skor as $a): ?>
      <tr><td><?= e($a['aspek']) ?></td><td class="mono"><?= e((string) $a['skor']) ?> / <?= e((string) $a['target']) ?></td>
        <td class="mono"><?= e((string) $a['persentase']) ?>%</td><td><strong><?= e($a['predikat']) ?></strong></td><td><?= e($a['keterangan']) ?></td></tr>
    <?php endforeach; ?>
    <?php if ($skor === []): ?><tr><td colspan="5" class="tabel-kosong">Belum ada data aspek.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>

<?= bagian_judul('Riwayat pelanggaran', count($pelanggaran) . ' catatan · termasuk yang dibatalkan · tanpa identitas pencatat') ?>
<div class="tabel-bungkus margin-bawah">
  <table class="tabel">
    <thead><tr><th>Tanggal</th><th>Jam</th><th>Kejadian</th><th>Aspek</th><th>Poin</th><th>Tingkat</th><th>Sumber</th><th>Status</th></tr></thead>
    <tbody>
    <?php foreach ($pelanggaran as $c): ?>
      <?php $waktu = strtotime((string) $c['dibuat_pada']); ?>
      <tr>
        <td class="mono"><?= e(tanggal_indonesia($c['tanggal'], false)) ?></td>
        <td class="mono"><?= e($waktu !== false ? date('H:i', $waktu) : '-') ?></td>
        <td><?= e($c['kejadian']) ?></td>
        <td><?= e($c['aspek']) ?></td>
        <td class="mono"><?= e((string) $c['poin']) ?></td>
        <td><?= e($c['tingkat'] === 'none' ? '-' : $c['tingkat']) ?></td>
        <td><?= e($c['sumber']) ?></td>
        <td><span class="chip <?= $c['status'] === 'aktif' ? 'badge-koral' : 'badge-abu' ?>"><?= e($c['status']) ?></span></td>
      </tr>
    <?php endforeach; ?>
    <?php if ($pelanggaran === []): ?><tr><td colspan="8" class="tabel-kosong">Belum ada catatan pelanggaran pada semester ini.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>

<?= bagian_judul('Riwayat level penanganan', 'Jejak setiap penetapan, kenaikan, dan penurunan level') ?>
<div class="tabel-bungkus margin-bawah">
  <table class="tabel">
    <thead><tr><th>Tanggal</th><th>Level</th><th>Status</th><th>Tahap penanganan</th><th>Penanggung jawab</th><th>Catatan</th></tr></thead>
    <tbody>
    <?php foreach ($penanganan as $p): ?>
      <?php $lv = (int) $p['level']; $i = informasi_level($lv); ?>
      <tr>
        <td class="mono"><?= e(tanggal_indonesia($p['tanggal'], false)) ?></td>
        <td><span class="chip <?= e(badge_level($lv)) ?>"><?= e($i['kode']) ?></span><br><span class="teks-kecil"><?= e($i['nama']) ?></span></td>
        <td><span class="chip <?= e(badge_status_penanganan((string) $p['status'])) ?>"><?= e(label_status_penanganan((string) $p['status'])) ?></span></td>
        <td><?= e((string) $p['tahap']) ?></td>
        <td><?= e((string) $p['penanggung_jawab']) ?></td>
        <td><?= e((string) ($p['catatan'] ?? '-')) ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if ($penanganan === []): ?><tr><td colspan="6" class="tabel-kosong">Belum ada penetapan level. Buka halaman Siswa Bermasalah untuk menetapkannya.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>

<?= bagian_judul('Catatan tindak lanjut BK', 'Dari menu Tindak Lanjut / Siswa Pembinaan') ?>
<div class="tabel-bungkus">
  <table class="tabel">
    <thead><tr><th>Tanggal</th><th>Pemicu</th><th>Status</th><th>Catatan</th></tr></thead>
    <tbody>
    <?php foreach ($tindakLanjut as $b): ?>
      <tr>
        <td class="mono"><?= e(tanggal_indonesia($b['tanggal'], false)) ?></td>
        <td><?= e($b['pemicu']) ?></td>
        <td><span class="chip <?= e(badge_status_penanganan((string) $b['status'])) ?>"><?= e(label_status_penanganan((string) $b['status'])) ?></span></td>
        <td><?= e((string) ($b['tindak_lanjut'] ?? '-')) ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if ($tindakLanjut === []): ?><tr><td colspan="4" class="tabel-kosong">Belum ada catatan tindak lanjut.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<?php halaman_bawah(); ?>
