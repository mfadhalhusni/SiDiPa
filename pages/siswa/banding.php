<?php
declare(strict_types=1);

/* Pengajuan banding siswa atas catatan poin miliknya sendiri. */

require_once __DIR__ . '/../../app/bootstrap.php';

$pengguna = auth_wajib(['siswa']);
$siswa = siswa_akun((int) $pengguna['id'], 'siswa');
$semester = semester_aktif();
$semesterId = (int) ($semester['id'] ?? 0);
$pesanGalat = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && $siswa !== null && $semesterId > 0) {
    csrf_wajib();
    if (aksi_post() === 'ajukan') {
        $catatanId = post_int('catatan_id');
        $alasan = post('alasan', '', 2000);
        $milik = db_satu(
            'SELECT id FROM catatan_poin WHERE id = ? AND siswa_id = ? AND semester_id = ? AND status = ? LIMIT 1',
            [$catatanId, (int) $siswa['id'], $semesterId, 'aktif']
        );
        $sudah = (int) db_nilai('SELECT COUNT(*) FROM banding WHERE catatan_id = ?', [$catatanId], 0);
        if ($milik === null) {
            $pesanGalat = 'Catatan tidak ditemukan atau sudah dibatalkan.';
        } elseif ($sudah > 0) {
            $pesanGalat = 'Banding untuk catatan ini sudah pernah diajukan.';
        } elseif (trim($alasan) === '') {
            $pesanGalat = 'Alasan banding wajib diisi.';
        } else {
            db_sisip('banding', [
                'catatan_id' => $catatanId,
                'siswa_id' => (int) $siswa['id'],
                'alasan' => $alasan,
                'status' => 'menunggu',
            ]);
            flash('Banding terkirim. Wali kelas atau admin akan meninjaunya.');
            alihkan('pages/siswa/banding.php');
        }
    }
}

$catatan = [];
$banding = [];
if ($siswa !== null && $semesterId > 0) {
    $catatan = db_semua(
        'SELECT c.id, c.tanggal, c.poin, c.keterangan, a.nama AS aspek,
                COALESCE(k.nama, \'Penilaian bulanan\') AS kejadian
         FROM catatan_poin c
         JOIN aspek a ON a.id = c.aspek_id
         LEFT JOIN katalog_poin k ON k.id = c.katalog_id
         WHERE c.siswa_id = ? AND c.semester_id = ? AND c.status = \'aktif\'
         ORDER BY c.tanggal DESC, c.id DESC LIMIT 200',
        [(int) $siswa['id'], $semesterId]
    );
    $banding = db_semua(
        'SELECT b.id, b.alasan, b.status, b.keputusan, b.dibuat_pada, b.diputuskan_pada,
                c.tanggal AS tanggal_catatan, c.poin, a.nama AS aspek
         FROM banding b
         JOIN catatan_poin c ON c.id = b.catatan_id
         JOIN aspek a ON a.id = c.aspek_id
         WHERE b.siswa_id = ? ORDER BY b.dibuat_pada DESC, b.id DESC LIMIT 100',
        [(int) $siswa['id']]
    );
    $sudahBanding = array_map('intval', array_column(
        db_semua('SELECT catatan_id FROM banding WHERE siswa_id = ?', [(int) $siswa['id']]),
        'catatan_id'
    ));
} else {
    $sudahBanding = [];
}

halaman_atas('Banding', 'Data saya', ['nav' => 'beranda']);
?>
<?php if ($pesanGalat !== ''): ?>
  <div class="pesan pesan-galat" role="alert"><?= ikon('peringatan', 20) ?><span><?= e($pesanGalat) ?></span></div>
<?php endif; ?>

<div class="pesan pesan-info" role="status"><?= ikon('info', 20) ?>
  <span>Satu catatan hanya dapat dibanding sekali. Banding diterima akan membatalkan catatan dan mengembalikan poin.</span>
</div>

<?= bagian_judul('Banding saya', 'Status keputusan dari wali kelas atau admin.') ?>
<div class="tabel-bungkus">
  <table class="tabel">
    <thead><tr><th>Catatan</th><th>Alasan</th><th>Status</th></tr></thead>
    <tbody>
    <?php foreach ($banding as $b): ?>
      <tr>
        <td class="mono"><?= e(tanggal_indonesia($b['tanggal_catatan'], false)) ?><br><span class="teks-kecil"><?= e($b['aspek']) ?> · <?= (int) $b['poin'] ?> poin</span></td>
        <td><?= e($b['alasan']) ?>
          <?php if ((string) $b['keputusan'] !== ''): ?><p class="teks-kecil" style="margin:6px 0 0"><strong>Keputusan:</strong> <?= e($b['keputusan']) ?></p><?php endif; ?>
        </td>
        <td>
          <?php if ($b['status'] === 'menunggu'): ?><span class="chip badge-kuning">Menunggu</span>
          <?php elseif ($b['status'] === 'diterima'): ?><span class="chip badge-toska">Diterima</span>
          <?php else: ?><span class="chip badge-koral">Ditolak</span><?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if ($banding === []): ?><tr><td colspan="3" class="tabel-kosong">Belum ada banding yang diajukan.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>

<?= bagian_judul('Ajukan banding', 'Pilih catatan yang menurut Anda perlu ditinjau.') ?>
<div class="tabel-bungkus">
  <table class="tabel">
    <thead><tr><th>Tanggal</th><th>Kategori</th><th>Poin</th><th>Aksi</th></tr></thead>
    <tbody>
    <?php foreach ($catatan as $c): ?>
      <tr>
        <td class="mono"><?= e(tanggal_indonesia($c['tanggal'], false)) ?></td>
        <td><strong><?= e($c['aspek']) ?></strong><br><span class="teks-kecil"><?= e($c['kejadian']) ?></span></td>
        <td class="mono"><?= (int) $c['poin'] ?></td>
        <td>
          <?php if (in_array((int) $c['id'], $sudahBanding, true)): ?>
            <span class="chip badge-abu">Sudah dibanding</span>
          <?php else: ?>
            <form method="post" action="<?= e(url('pages/siswa/banding.php')) ?>" data-konfirmasi="Kirim banding untuk catatan ini?">
              <?= csrf_field() ?>
              <input type="hidden" name="aksi" value="ajukan">
              <input type="hidden" name="catatan_id" value="<?= (int) $c['id'] ?>">
              <div class="form-baris" style="margin-bottom:6px">
                <label for="alasan-<?= (int) $c['id'] ?>">Alasan</label>
                <input class="input" id="alasan-<?= (int) $c['id'] ?>" name="alasan" required maxlength="2000" placeholder="Jelaskan alasan Anda">
              </div>
              <button class="tombol tombol-putih" type="submit"><?= ikon('banding', 18) ?> Ajukan</button>
            </form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if ($catatan === []): ?><tr><td colspan="4" class="tabel-kosong">Tidak ada catatan aktif yang dapat dibanding.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<?php halaman_bawah(); ?>
