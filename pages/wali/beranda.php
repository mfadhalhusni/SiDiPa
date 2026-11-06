<?php
declare(strict_types=1);

/*
 | Tahap 5 — Portal wali murid (opsional di PDF, bagian 4 & 15).
 | HANYA BACA ringkasan anaknya: skor per aspek, ringkas kehadiran bulan ini,
 | dan sertifikat. Tidak ada riwayat pelanggaran, nama pencatat, nomor WA,
 | atau data siswa lain. Admin menghubungkan akun lewat Data siswa →
 | "Akun portal wali murid".
 */

require_once __DIR__ . '/../../app/bootstrap.php';

$pengguna = auth_wajib(['wali']);
$siswa = siswa_akun((int) $pengguna['id'], 'wali');
$semester = semester_aktif();
$semesterId = (int) ($semester['id'] ?? 0);

$skor = [];
$kehadiran = [];
$sertifikat = [];
if ($siswa !== null) {
    if ($semesterId > 0) {
        $skor = skor_per_aspek((int) $siswa['id'], $semesterId);
    }
    $baris = rekap_kehadiran(date('Y-m-01'), date('Y-m-t'), ['siswa_id' => (int) $siswa['id']]);
    $kehadiran = $baris[0] ?? [];
    $sertifikat = sertifikat_siswa((int) $siswa['id']);
}
$warna = ['bg-koral', 'bg-kuning', 'bg-tosca', 'bg-ungu', 'bg-oranye', 'bg-biru'];

halaman_atas('Beranda Wali Murid', 'Ringkasan anak', ['nav' => 'beranda']);
?>
<?php if ($siswa === null): ?>
  <div class="pesan pesan-galat" role="alert"><?= ikon('peringatan', 20) ?>
    <span>Akun wali belum terhubung ke data siswa. Minta admin memilih akun ini pada menu Data siswa → Akun portal wali murid.</span></div>
<?php else: ?>
  <section class="kartu bg-toska margin-bawah">
    <p class="label-kecil">Halo, <?= e($pengguna['nama']) ?>!</p>
    <h2 style="margin:0"><?= e($siswa['nama']) ?></h2>
    <p class="teks-kecil" style="margin:4px 0 0">NIS <?= e($siswa['nis']) ?> · <?= e($siswa['kelas'] ?? '-') ?> · <?= e($semester['nama'] ?? 'Belum ada semester aktif') ?></p>
  </section>

  <div class="pesan pesan-info" role="status"><?= ikon('info', 20) ?>
    <span>Portal ini hanya menampilkan ringkasan. Riwayat catatan dan identitas pemberi poin tidak tersedia untuk wali murid.</span>
  </div>

  <?= bagian_judul('Skor karakter', 'Predikat dihitung per aspek terhadap target semester.') ?>
  <div class="skor-grid margin-bawah">
    <?php foreach ($skor as $i => $s): ?>
      <div class="kartu kartu-skor <?= e($warna[$i % count($warna)]) ?>">
        <p class="label-kecil"><?= e($s['aspek']) ?></p>
        <span class="predikat"><?= e($s['predikat']) ?></span>
        <span class="angka mono">Skor <?= e($s['skor']) ?> / <?= e($s['target']) ?></span>
        <p class="teks-kecil" style="margin:4px 0 0"><?= e($s['persentase']) ?>% · <?= e($s['keterangan']) ?></p>
      </div>
    <?php endforeach; ?>
    <?php if ($skor === []): ?><div class="kartu tabel-kosong">Belum ada semester aktif.</div><?php endif; ?>
  </div>

  <?= bagian_judul('Kehadiran bulan ini', NAMA_BULAN[(int) date('n')] . ' ' . date('Y')) ?>
  <div class="grid grid-stat margin-bawah">
    <div class="kartu kartu-stat bg-toska"><p class="label-kecil">Hadir</p><span class="angka mono"><?= e($kehadiran['hadir'] ?? 0) ?></span></div>
    <div class="kartu kartu-stat bg-oranye"><p class="label-kecil">Terlambat</p><span class="angka mono"><?= e($kehadiran['terlambat'] ?? 0) ?></span></div>
    <div class="kartu kartu-stat bg-ungu"><p class="label-kecil">Izin / Sakit</p><span class="angka mono"><?= e((int) ($kehadiran['izin'] ?? 0) + (int) ($kehadiran['sakit'] ?? 0)) ?></span></div>
    <div class="kartu kartu-stat bg-koral"><p class="label-kecil">Alpa</p><span class="angka mono"><?= e($kehadiran['alpa'] ?? 0) ?></span></div>
  </div>

  <?= bagian_judul('Sertifikat', count($sertifikat) . ' sertifikat · 2 halaman: pernyataan + deskripsi penilaian') ?>
  <div class="tabel-bungkus margin-bawah">
    <table class="tabel">
      <thead><tr><th>Semester</th><th>Kode</th><th>Status</th><th>Aksi</th></tr></thead>
      <tbody>
      <?php foreach ($sertifikat as $sr): ?>
        <?php $sah = (string) $sr['status'] === 'terbit'; ?>
        <tr>
          <td><strong><?= e($sr['semester']) ?></strong><br><span class="teks-kecil mono"><?= e($sr['tahun_ajaran']) ?></span></td>
          <td class="mono teks-kecil"><?= e($sr['kode']) ?></td>
          <td><span class="chip <?= $sah ? 'badge-tosca' : 'badge-koral' ?>"><?= $sah ? 'Terbit' : 'Dibatalkan' ?></span></td>
          <td>
            <?php $bolehCetak = $sah && sertifikat_sudah_ganti_semester((int) ($sr['semester_id'] ?? 0)); ?>
            <?php if ($sah): ?>
              <a class="tombol tombol-kecil <?= $bolehCetak ? 'tombol-utama' : 'tombol-putih' ?>" href="<?= e(url('pages/umum/cetak_sertifikat.php?kode=' . rawurlencode((string) $sr['kode']))) ?>"><?= ikon($bolehCetak ? 'cetak' : 'mata', 16) ?> <?= $bolehCetak ? 'Cetak' : 'Lihat' ?></a>
              <a class="tombol tombol-kecil tombol-putih" href="<?= e(url('verifikasi.php?kode=' . rawurlencode((string) $sr['kode']))) ?>" aria-label="Verifikasi publik"><?= ikon('mata', 16) ?></a>
              <?php if (!$bolehCetak): ?><br><span class="teks-kecil">Cetak dibuka setelah semester berganti</span><?php endif; ?>
            <?php else: ?><span class="teks-kecil">Cetak tidak tersedia</span><?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if ($sertifikat === []): ?><tr><td colspan="4" class="tabel-kosong">Belum ada sertifikat yang diterbitkan.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>

  <div class="baris-tombol">
    <a class="tombol tombol-putih" href="<?= e(url('pages/siswa/kartu.php')) ?>"><?= ikon('qr', 20) ?> Kartu QR anak</a>
    <a class="tombol tombol-putih" href="<?= e(url('pages/umum/rekap_kehadiran.php')) ?>"><?= ikon('rekap', 20) ?> Rekap kehadiran</a>
    <a class="tombol tombol-putih" href="<?= e(url('pages/profil.php')) ?>"><?= ikon('profil', 20) ?> Profil</a>
  </div>
<?php endif; ?>
<?php halaman_bawah(); ?>
