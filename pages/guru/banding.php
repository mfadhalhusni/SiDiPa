<?php
declare(strict_types=1);

/*
 | Keputusan banding: wali kelas untuk siswa di kelasnya, atau admin.
 | Banding diterima -> catatan dibatalkan (tidak dihapus) + poin kembali.
 | Tidak ada identitas pencatat yang ditampilkan pada halaman ini.
 */

require_once __DIR__ . '/../../app/bootstrap.php';

$pengguna = auth_wajib(['guru', 'admin']);
$peran = (string) $pengguna['peran'];
$idPengguna = (int) $pengguna['id'];
$semester = semester_wajib();
$semesterId = (int) $semester['id'];
$pesanGalat = '';

/** Apakah pengguna ini berhak memutus banding siswa tersebut. */
function boleh_putus_banding(int $idPengguna, string $peran, int $siswaId): bool
{
    if ($peran === 'admin') {
        return true;
    }
    return wali_kelas_siswa($siswaId) === $idPengguna;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_wajib();
    if (aksi_post() === 'putus') {
        $bandingId = post_int('banding_id');
        $keputusan = post('keputusan', '', 20);
        $alasan = post('alasan_keputusan', '', 2000);
        $baris = db_satu(
            'SELECT b.id, b.catatan_id, b.siswa_id, b.status, c.semester_id
             FROM banding b JOIN catatan_poin c ON c.id = b.catatan_id
             WHERE b.id = ? LIMIT 1',
            [$bandingId]
        );
        if ($baris === null) {
            $pesanGalat = 'Banding tidak ditemukan.';
        } elseif ($baris['status'] !== 'menunggu') {
            $pesanGalat = 'Banding ini sudah diputus.';
        } elseif (!in_array($keputusan, ['diterima', 'ditolak'], true)) {
            $pesanGalat = 'Pilih keputusan yang valid.';
        } elseif (trim($alasan) === '') {
            $pesanGalat = 'Alasan keputusan wajib diisi.';
        } elseif (!boleh_putus_banding($idPengguna, $peran, (int) $baris['siswa_id'])) {
            $pesanGalat = 'Anda bukan wali kelas siswa ini.';
        } else {
            db_transaksi(function () use ($baris, $keputusan, $alasan, $idPengguna): void {
                db_jalankan(
                    "UPDATE banding SET status = ?, keputusan = ?, diputuskan_oleh = ?, diputuskan_pada = NOW() WHERE id = ?",
                    [$keputusan, $alasan, $idPengguna, (int) $baris['id']]
                );
                if ($keputusan === 'diterima') {
                    batalkan_catatan(
                        (int) $baris['catatan_id'],
                        'Banding diterima: ' . $alasan,
                        $idPengguna
                    );
                }
            });
            evaluasi_ambang_siswa((int) $baris['siswa_id'], (int) $baris['semester_id']);
            flash('Banding ' . $keputusan . '. ' . ($keputusan === 'diterima' ? 'Catatan dibatalkan dan poin kembali.' : 'Catatan tetap berlaku.'));
            alihkan('pages/guru/banding.php');
        }
    }
}

$syarat = ['1 = 1'];
$param = [];
if ($peran !== 'admin') {
    $syarat[] = 'k.wali_kelas_id = ?';
    $param[] = $idPengguna;
}
$daftar = db_semua(
    'SELECT b.id, b.alasan, b.status, b.keputusan, b.dibuat_pada, b.diputuskan_pada,
            s.nama AS nama_siswa, s.nis, k.nama AS kelas,
            c.id AS catatan_id, c.tanggal AS tanggal_catatan, c.poin,
            a.nama AS aspek, COALESCE(kp.nama, \'Penilaian bulanan\') AS kejadian
     FROM banding b
     JOIN siswa s ON s.id = b.siswa_id
     LEFT JOIN kelas k ON k.id = s.kelas_id
     JOIN catatan_poin c ON c.id = b.catatan_id
     JOIN aspek a ON a.id = c.aspek_id
     LEFT JOIN katalog_poin kp ON kp.id = c.katalog_id
     WHERE ' . implode(' AND ', $syarat) . '
     ORDER BY FIELD(b.status, \'menunggu\', \'diterima\', \'ditolak\'), b.dibuat_pada DESC
     LIMIT 200',
    $param
);

halaman_atas('Banding siswa', $peran === 'admin' ? 'Panel admin' : 'Wali kelas', ['nav' => 'beranda']);
?>
<?php if ($pesanGalat !== ''): ?>
  <div class="pesan pesan-galat" role="alert"><?= ikon('peringatan', 20) ?><span><?= e($pesanGalat) ?></span></div>
<?php endif; ?>
<?php if ($peran !== 'admin' && (int) db_nilai('SELECT COUNT(*) FROM kelas WHERE wali_kelas_id = ? AND aktif = 1', [$idPengguna], 0) === 0): ?>
  <div class="pesan pesan-galat" role="alert"><?= ikon('peringatan', 20) ?>
    <span>Halaman keputusan banding hanya untuk wali kelas atau admin. Anda belum ditetapkan sebagai wali kelas.</span></div>
<?php endif; ?>

<div class="pesan pesan-info" role="status"><?= ikon('info', 20) ?>
  <span>Keputusan wajib menyertakan alasan. Banding yang diterima membatalkan catatan tanpa menghapusnya.</span>
</div>

<div class="tabel-bungkus">
  <table class="tabel">
    <thead><tr><th>Siswa</th><th>Catatan</th><th>Alasan siswa</th><th>Status &amp; keputusan</th></tr></thead>
    <tbody>
    <?php foreach ($daftar as $b): ?>
      <tr>
        <td><strong><?= e($b['nama_siswa']) ?></strong><br><span class="teks-kecil mono"><?= e($b['nis']) ?></span><br><span class="teks-kecil"><?= e($b['kelas'] ?? '-') ?></span></td>
        <td class="mono"><?= e(tanggal_indonesia($b['tanggal_catatan'], false)) ?><br>
          <span class="teks-kecil"><?= e($b['aspek']) ?> · <?= e($b['kejadian']) ?> · <?= (int) $b['poin'] ?> poin</span>
        </td>
        <td><?= e($b['alasan']) ?></td>
        <td>
          <?php if ($b['status'] === 'menunggu'): ?>
            <span class="chip badge-kuning">Menunggu</span>
            <form method="post" action="<?= e(url('pages/guru/banding.php')) ?>" style="margin-top:8px" data-konfirmasi="Simpan keputusan banding ini?">
              <?= csrf_field() ?>
              <input type="hidden" name="aksi" value="putus">
              <input type="hidden" name="banding_id" value="<?= (int) $b['id'] ?>">
              <div class="form-baris" style="margin-bottom:6px">
                <label for="keputusan-<?= (int) $b['id'] ?>">Keputusan</label>
                <select class="input" id="keputusan-<?= (int) $b['id'] ?>" name="keputusan" required>
                  <option value="ditolak">Ditolak</option>
                  <option value="diterima">Diterima (batalkan catatan)</option>
                </select>
              </div>
              <div class="form-baris" style="margin-bottom:6px">
                <label for="alasan-<?= (int) $b['id'] ?>">Alasan keputusan</label>
                <input class="input" id="alasan-<?= (int) $b['id'] ?>" name="alasan_keputusan" required maxlength="2000" placeholder="Tuliskan pertimbangan">
              </div>
              <button class="tombol tombol-utama" type="submit"><?= ikon('centang', 18) ?> Simpan keputusan</button>
            </form>
          <?php else: ?>
            <?php if ($b['status'] === 'diterima'): ?><span class="chip badge-toska">Diterima</span>
            <?php else: ?><span class="chip badge-koral">Ditolak</span><?php endif; ?>
            <p class="teks-kecil" style="margin:6px 0 0"><?= e($b['keputusan'] ?? '-') ?></p>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if ($daftar === []): ?><tr><td colspan="4" class="tabel-kosong">Belum ada banding yang perlu diputus.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<?php halaman_bawah(); ?>
