<?php
declare(strict_types=1);

/* Catat poin manual guru: katalog + bukti + notifikasi berat + cegah ganda. */

require_once __DIR__ . '/../../app/bootstrap.php';

$pengguna = auth_wajib(['guru']);
cek_input_semester();
$idGuru = (int) $pengguna['id'];
$semester = semester_wajib();
$semesterId = (int) $semester['id'];
$pesanGalat = '';
$katalog = db_semua(
    'SELECT kp.id, kp.kode, kp.nama, kp.poin, kp.jenis, kp.tingkat, kp.wajib_keterangan,
            a.id AS aspek_id, a.nama AS aspek
     FROM katalog_poin kp JOIN aspek a ON a.id = kp.aspek_id
     WHERE kp.aktif = 1 AND kp.otomatis = 0
     ORDER BY a.urutan, a.id, kp.jenis, kp.nama'
);

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_wajib();
    $siswaId = post_int('siswa_id');
    $katalogId = post_int('katalog_id');
    $keterangan = post('keterangan', '', 2000);
    $tanggal = post('tanggal', '', 10);
    if (!tanggal_valid($tanggal) || $tanggal > date('Y-m-d')) {
        $tanggal = date('Y-m-d');
    }

    $boleh = guru_boleh_siswa($siswaId);
    $baris = $katalogId > 0
        ? db_satu('SELECT * FROM katalog_poin WHERE id = ? AND aktif = 1 AND otomatis = 0 LIMIT 1', [$katalogId])
        : null;

    if (!$boleh) {
        $pesanGalat = 'Pilih siswa yang masih aktif.';
    } elseif ($baris === null) {
        $pesanGalat = 'Pilih kejadian dari katalog poin yang tersedia.';
    } else {
        $tingkat = (string) $baris['tingkat'];
        $perluKeterangan = (int) $baris['wajib_keterangan'] === 1 || in_array($tingkat, ['sedang', 'berat'], true);
        $poin = (int) $baris['poin'];
        $aspekId = (int) $baris['aspek_id'];

        if ($perluKeterangan && trim($keterangan) === '') {
            $pesanGalat = 'Kejadian tingkat ' . $tingkat . ' wajib menyertakan deskripsi.';
        } elseif (!in_array((int) $poin, range(-100, 100), true)) {
            $pesanGalat = 'Nilai poin katalog tidak valid (harus bilangan bulat).';
        } else {
            /* Tidak ada lagi batas pengurangan harian. Pencegahan ganda
               dilakukan lewat panel "Catatan siswa pada tanggal ini". */
            $unggah = unggah_bukti($_FILES['bukti'] ?? []);
            if (!$unggah['ok']) {
                $pesanGalat = $unggah['pesan'];
            } else {
                db_transaksi(function () use (
                    $semesterId, $siswaId, $baris, $poin, $aspekId, $keterangan,
                    $tanggal, $idGuru, $unggah
                ): void {
                    tambah_catatan([
                        'semester_id' => $semesterId,
                        'siswa_id' => $siswaId,
                        'katalog_id' => (int) $baris['id'],
                        'aspek_id' => $aspekId,
                        'poin' => $poin,
                        'sumber' => 'manual',
                        'pencatat_id' => $idGuru,
                        'keterangan' => $keterangan !== '' ? $keterangan : null,
                        'bukti_path' => $unggah['path'],
                        'tanggal' => $tanggal,
                    ]);
                });

                if ($tingkat === 'berat') {
                    $dataSiswa = db_satu('SELECT nama FROM siswa WHERE id = ? LIMIT 1', [$siswaId]);
                    notifikasi_pelanggaran_berat(
                        $siswaId,
                        (string) ($dataSiswa['nama'] ?? 'Siswa'),
                        (string) $baris['nama'],
                        $poin
                    );
                }
                evaluasi_ambang_siswa($siswaId, $semesterId);

                flash('Poin ' . ($poin > 0 ? '+' : '') . $poin . ' untuk ' . (string) $baris['nama'] . ' tersimpan.');
                alihkan('pages/guru/riwayat.php');
            }
        }
    }
}

halaman_atas('Catat poin', 'Beranda guru / Catat poin', [
    'nav' => 'beranda',
    'aksi' => '<a class="tombol tombol-toska" href="' . e(url('pages/guru/catat_banyak.php')) . '">' . ikon('siswa', 20) . ' Catat banyak siswa</a>',
]);
?>
<?php if ($pesanGalat !== ''): ?>
  <div class="pesan pesan-galat" role="alert"><?= ikon('peringatan', 20) ?><span><?= e($pesanGalat) ?></span></div>
<?php endif; ?>

<?php if ($siswa === [] || $katalog === []): ?>
  <div class="pesan pesan-info" role="status"><?= ikon('info', 20) ?>
    <span><?= $siswa === [] ? 'Belum ada siswa aktif.' : 'Katalog poin manual belum tersedia.' ?> Minta admin melengkapi data.</span>
  </div>
<?php else: ?>
  <form class="kartu form-kartu" method="post" action="<?= e(url('pages/guru/catat_poin.php')) ?>" enctype="multipart/form-data" data-lewati-muat="1">
    <?= csrf_field() ?>
    <div class="form-grid">
      <?php kotak_cari_siswa([
          'label' => 'Siswa', 'nama' => 'siswa_id',
          'bantuan' => 'Ketik NIS atau nama siswa, lalu pilih dari saran. Semua siswa aktif dapat dicari.',
      ]); ?>
      <div class="form-baris lebar-penuh">
        <label for="katalog_id">Kejadian / katalog poin <span class="wajib">wajib</span></label>
        <select class="input" id="katalog_id" name="katalog_id" required data-pilih-poin>
          <option value="">— Pilih kejadian —</option>
          <?php foreach ($katalog as $k): ?>
            <option value="<?= (int) $k['id'] ?>"
                    data-poin="<?= (int) $k['poin'] ?>"
                    data-tingkat="<?= e($k['tingkat']) ?>"
                    data-wajib="<?= (int) $k['wajib_keterangan'] ?>">
              <?= e($k['aspek']) ?> · <?= e($k['nama']) ?> (<?= ((int) $k['poin'] > 0 ? '+' : '') . (int) $k['poin'] ?>)
            </option>
          <?php endforeach; ?>
        </select>
        <p class="bantuan">Poin mengikuti katalog yang diatur admin. Output: <strong id="pratinjau-poin">—</strong></p>
      </div>
      <div class="form-baris">
        <label for="tanggal">Tanggal kejadian</label>
        <input class="input" id="tanggal" name="tanggal" type="date" value="<?= e(date('Y-m-d')) ?>" max="<?= e(date('Y-m-d')) ?>">
        <p class="bantuan">Mengikuti zona waktu Asia/Jakarta, tidak boleh melebihi hari ini.</p>
      </div>
      <div class="form-baris">
        <label for="bukti">Bukti foto (opsional)</label>
        <input class="input" id="bukti" name="bukti" type="file" accept="image/jpeg,image/png,image/webp">
        <p class="bantuan">JPG/PNG/WEBP, maksimal <?= e(round((int) pengaturan('maks_ukuran_bukti', (string) SIDIPA_MAKS_BUKTI) / 1048576, 1)) ?> MB.</p>
      </div>
      <div class="form-baris lebar-penuh">
        <label for="keterangan">Deskripsi</label>
        <textarea class="input" id="keterangan" name="keterangan" rows="3" maxlength="2000" placeholder="Wajib untuk kejadian sedang atau berat."></textarea>
        <p class="bantuan">Kejadian sedang/berat wajib dideskripsikan. Tidak boleh memuat nama pencatat.</p>
      </div>
    </div>
    <div class="pesan pesan-info" role="status"><?= ikon('info', 20) ?>
      <span><strong>Pengurangan poin tidak dibatasi</strong>, termasuk pelanggaran berat (−15, −20, −25). Sebelum menyimpan, periksa <strong>Catatan siswa pada tanggal ini</strong> di bawah agar pelanggaran tidak tercatat dua kali.</span>
    </div>
    <div class="form-aksi">
      <button class="tombol tombol-utama" type="submit"><?= ikon('simpan', 20) ?> Simpan poin</button>
      <a class="tombol tombol-putih" href="<?= e(url('pages/guru/beranda.php')) ?>">Batal</a>
    </div>
  </form>
  <?php kartu_riwayat_harian(false); ?>
  <script>
    (function () {
      var pilih = document.querySelector('[data-pilih-poin]');
      var out = document.getElementById('pratinjau-poin');
      if (!pilih || !out) return;
      pilih.addEventListener('change', function () {
        var o = pilih.options[pilih.selectedIndex];
        var p = o ? o.getAttribute('data-poin') : null;
        out.textContent = p === null ? '—' : (Number(p) > 0 ? '+' : '') + p + ' poin';
      });
    })();
  </script>
<?php endif; ?>
<?php halaman_bawah(); ?>
