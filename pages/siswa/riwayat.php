<?php
declare(strict_types=1);

/*
 | Riwayat catatan siswa — PRIVASI KETAT.
 | Hanya kategori, tanggal (TANPA jam), poin, dan deskripsi.
 | Nama/ID pencatat, jam, dan mapel TIDAK PERNAH dikirim ke peran ini,
 | termasuk melalui JSON. Kolom diambil dari KOLOM_CATATAN_AMAN.
 */

require_once __DIR__ . '/../../app/bootstrap.php';

$pengguna = auth_wajib(['siswa']);
$siswa = siswa_akun((int) $pengguna['id'], 'siswa');
$semester = semester_aktif();
$semesterId = (int) ($semester['id'] ?? 0);
$aspekFilter = post_int('aspek_id') ?: (int) ($_GET['aspek_id'] ?? 0);
$daftar = [];

if ($siswa !== null && $semesterId > 0) {
    $semua = riwayat_catatan((int) $siswa['id'], $semesterId, 300);
    foreach ($semua as $c) {
        if ($aspekFilter > 0 && (int) $c['aspek_id'] !== $aspekFilter) {
            continue;
        }
        $daftar[] = $c;
    }
}
$aspek = daftar_aspek();

halaman_atas('Riwayat catatan', 'Data saya', ['nav' => 'beranda']);
?>
<?php if ($siswa === null): ?>
  <div class="pesan pesan-galat" role="alert"><?= ikon('peringatan', 20) ?><span>Data siswa tidak ditemukan untuk akun ini.</span></div>
<?php else: ?>
  <div class="pesan pesan-info" role="status"><?= ikon('info', 20) ?>
    <span>Riwayat ini hanya menampilkan kategori, tanggal, poin, dan deskripsi. Catatan yang dibatalkan tidak dihitung dalam skor.</span>
  </div>

  <form class="kartu form-kartu" method="get" action="<?= e(url('pages/siswa/riwayat.php')) ?>">
    <div class="form-baris">
      <label for="aspek_id">Saring per aspek</label>
      <select class="input" id="aspek_id" name="aspek_id">
        <option value="0">Semua aspek</option>
        <?php foreach ($aspek as $a): ?>
          <option value="<?= (int) $a['id'] ?>" <?= $aspekFilter === (int) $a['id'] ? 'selected' : '' ?>><?= e($a['nama']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-aksi"><button class="tombol" type="submit"><?= ikon('filter', 20) ?> Terapkan</button></div>
  </form>

  <div class="tabel-bungkus">
    <table class="tabel" id="tabel-riwayat-siswa">
      <thead><tr><th>Tanggal</th><th>Kategori</th><th>Poin</th><th>Deskripsi</th><th>Status</th></tr></thead>
      <tbody>
      <?php foreach ($daftar as $c): ?>
        <tr>
          <td class="mono"><?= e(tanggal_indonesia($c['tanggal'], false)) ?></td>
          <td><strong><?= e($c['aspek']) ?></strong><br><span class="teks-kecil"><?= e($c['kejadian']) ?></span></td>
          <td class="mono"><strong><?= ((int) $c['poin'] > 0 ? '+' : '') . (int) $c['poin'] ?></strong></td>
          <td><?= e($c['keterangan'] ?? '-') ?></td>
          <td><?= $c['status'] === 'aktif' ? '<span class="chip badge-toska">Aktif</span>' : '<span class="chip badge-abu">Dibatalkan</span>' ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if ($daftar === []): ?><tr><td colspan="5" class="tabel-kosong">Belum ada catatan pada semester ini.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>

  <div class="baris-tombol margin-atas">
    <a class="tombol tombol-putih" href="<?= e(url('pages/siswa/beranda.php')) ?>">Kembali ke beranda</a>
    <a class="tombol tombol-utama" href="<?= e(url('pages/siswa/banding.php')) ?>"><?= ikon('banding', 20) ?> Ajukan banding</a>
  </div>
<?php endif; ?>
<?php halaman_bawah(); ?>
