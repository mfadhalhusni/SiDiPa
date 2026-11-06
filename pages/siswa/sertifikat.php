<?php
declare(strict_types=1);

/*
 | Tahap 4 — Daftar sertifikat siswa (dapat dibuka wali murid untuk anaknya).
 | Siswa/wali HANYA bisa melihat sertifikat selama semester sertifikat masih
 | aktif. Cetak baru tersedia setelah admin mengaktifkan semester baru.
 */

require_once __DIR__ . '/../../app/bootstrap.php';

$pengguna = auth_wajib(['siswa', 'wali']);
$peran = (string) $pengguna['peran'];
$siswa = siswa_akun((int) $pengguna['id'], $peran);
$daftar = $siswa !== null ? sertifikat_siswa((int) $siswa['id']) : [];

halaman_atas('Sertifikat Saya', 'Akhir semester', ['nav' => 'beranda']);
?>
<?php if ($siswa === null): ?>
  <div class="pesan pesan-galat" role="alert"><?= ikon('peringatan', 20) ?><span>Akun belum terhubung ke data siswa. Hubungi admin.</span></div>
<?php else: ?>
  <section class="kartu bg-kuning margin-bawah">
    <p class="label-kecil">Sertifikat karakter</p>
    <h2 style="margin:0"><?= e($siswa['nama']) ?></h2>
    <p class="teks-kecil" style="margin:4px 0 0">NIS <?= e($siswa['nis']) ?> · <?= e($siswa['kelas'] ?? '-') ?> · <?= count($daftar) ?> sertifikat</p>
  </section>

  <div class="pesan pesan-info" role="status"><?= ikon('info', 20) ?>
    <span>Sertifikat diterbitkan admin setelah periode input semester ditutup. Nilai yang tercetak adalah snapshot saat terbit. Sertifikat terdiri dari <strong>2 halaman A4 landscape</strong>: halaman 1 surat pernyataan berisi nilai rata-rata karakter, halaman 2 deskripsi penilaian per aspek. <strong>Selama semester ini masih berjalan Anda hanya dapat melihat sertifikat; tombol cetak baru tersedia setelah semester berganti.</strong></span>
  </div>

  <div class="tabel-bungkus">
    <table class="tabel">
      <thead><tr><th>Semester</th><th>Kode</th><th>Tanggal terbit</th><th>Status</th><th>Aksi</th></tr></thead>
      <tbody>
      <?php foreach ($daftar as $sr): ?>
        <?php
        $sah = (string) $sr['status'] === 'terbit';
        $bolehCetak = $sah && sertifikat_sudah_ganti_semester((int) ($sr['semester_id'] ?? 0));
        ?>
        <tr>
          <td><strong><?= e($sr['semester']) ?></strong><br><span class="teks-kecil mono"><?= e($sr['tahun_ajaran']) ?></span></td>
          <td class="mono teks-kecil"><?= e($sr['kode']) ?></td>
          <td class="mono"><?= e(tanggal_indonesia($sr['tanggal_terbit'], false)) ?></td>
          <td><span class="chip <?= $sah ? 'badge-toska' : 'badge-koral' ?>"><?= $sah ? 'Terbit' : 'Dibatalkan' ?></span></td>
          <td>
            <?php if ($sah): ?>
              <a class="tombol tombol-kecil <?= $bolehCetak ? 'tombol-utama' : 'tombol-putih' ?>" href="<?= e(url('pages/umum/cetak_sertifikat.php?kode=' . rawurlencode((string) $sr['kode']))) ?>"><?= ikon($bolehCetak ? 'cetak' : 'mata', 16) ?> <?= $bolehCetak ? 'Cetak' : 'Lihat' ?></a>
              <a class="tombol tombol-kecil tombol-putih" href="<?= e(url('verifikasi.php?kode=' . rawurlencode((string) $sr['kode']))) ?>" aria-label="Verifikasi publik"><?= ikon('mata', 16) ?></a>
              <?php if (!$bolehCetak): ?><br><span class="teks-kecil">Cetak dibuka setelah semester berganti</span><?php endif; ?>
            <?php else: ?>
              <span class="teks-kecil">Cetak tidak tersedia</span>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if ($daftar === []): ?><tr><td colspan="5" class="tabel-kosong">Belum ada sertifikat. Sertifikat terbit setelah admin menutup semester.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>

  <div class="baris-tombol margin-atas">
    <a class="tombol tombol-putih" href="<?= e(url('pages/siswa/kartu.php')) ?>"><?= ikon('qr', 20) ?> Kartu QR saya</a>
    <a class="tombol tombol-putih" href="<?= e(url(auth_landing($peran))) ?>"><?= ikon('beranda', 20) ?> Beranda</a>
  </div>
<?php endif; ?>
<?php halaman_bawah(); ?>
