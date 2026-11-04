<?php
declare(strict_types=1);

/*
 | §10 PDF — mode "catat banyak siswa sekaligus untuk satu kejadian".
 | Satu kejadian katalog, satu tanggal, satu deskripsi, banyak siswa.
 | Semua penyisipan berada dalam SATU transaksi: bila satu siswa gagal
 | (misal melampaui cap harian), tidak ada catatan yang tersimpan.
 | Semua siswa aktif boleh dipilih tanpa penugasan kelas.
 */

require_once __DIR__ . '/../../app/bootstrap.php';

$pengguna = auth_wajib(['guru']);
cek_input_semester();
$idGuru = (int) $pengguna['id'];
$semester = semester_wajib();
$semesterId = (int) $semester['id'];
$batasHarian = (int) pengaturan('batas_pengurangan_harian', '10');
$pesanGalat = '';
$siswa = daftar_siswa_untuk_guru();
$katalog = db_semua(
    'SELECT kp.id, kp.kode, kp.nama, kp.poin, kp.tingkat, kp.wajib_keterangan,
            a.id AS aspek_id, a.nama AS aspek
     FROM katalog_poin kp JOIN aspek a ON a.id = kp.aspek_id
     WHERE kp.aktif = 1 AND kp.otomatis = 0
     ORDER BY a.urutan, a.id, kp.jenis, kp.nama'
);

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_wajib();
    $mentah = $_POST['siswa_id'] ?? [];
    $pilih = is_array($mentah) ? array_values(array_unique(array_filter(array_map('intval', $mentah)))) : [];
    $boleh = array_map('intval', array_column($siswa, 'id'));
    $katalogId = post_int('katalog_id');
    $keterangan = post('keterangan', '', 2000);
    $tanggal = post('tanggal', '', 10);
    if (!tanggal_valid($tanggal) || $tanggal > date('Y-m-d')) {
        $tanggal = date('Y-m-d');
    }
    $baris = $katalogId > 0
        ? db_satu('SELECT * FROM katalog_poin WHERE id = ? AND aktif = 1 AND otomatis = 0 LIMIT 1', [$katalogId])
        : null;

    if ($pilih === []) {
        $pesanGalat = 'Pilih minimal satu siswa.';
    } elseif (array_diff($pilih, $boleh) !== []) {
        $pesanGalat = 'Ada siswa di luar kelas yang Anda ampu.';
    } elseif ($baris === null) {
        $pesanGalat = 'Pilih kejadian dari katalog poin yang tersedia.';
    } else {
        $tingkat = (string) $baris['tingkat'];
        $perluKeterangan = (int) $baris['wajib_keterangan'] === 1 || in_array($tingkat, ['sedang', 'berat'], true);
        $poin = (int) $baris['poin'];
        $aspekId = (int) $baris['aspek_id'];

        if ($perluKeterangan && trim($keterangan) === '') {
            $pesanGalat = 'Kejadian tingkat ' . $tingkat . ' wajib menyertakan deskripsi.';
        } elseif (!in_array($poin, range(-100, 100), true)) {
            $pesanGalat = 'Nilai poin katalog tidak valid.';
        } else {
            $terhalang = [];
            if ($poin < 0) {
                foreach ($pilih as $sid) {
                    $terpakai = pengurangan_manual_harian($sid, $aspekId, $tanggal);
                    if ($terpakai + abs($poin) > $batasHarian) {
                        $nama = '';
                        foreach ($siswa as $s) {
                            if ((int) $s['id'] === $sid) {
                                $nama = (string) $s['nama'];
                                break;
                            }
                        }
                        $terhalang[] = $nama . ' (sisa ' . max(0, $batasHarian - $terpakai) . ')';
                    }
                }
            }
            if ($terhalang !== []) {
                $pesanGalat = 'Batas pengurangan ' . $batasHarian . ' poin/aspek/hari terlampaui untuk: '
                    . implode(', ', array_slice($terhalang, 0, 4)) . '. Tidak ada catatan yang disimpan.';
            } else {
                $unggah = unggah_bukti($_FILES['bukti'] ?? []);
                if (!$unggah['ok']) {
                    $pesanGalat = $unggah['pesan'];
                } else {
                    db_transaksi(function () use (
                        $semesterId, $pilih, $baris, $poin, $aspekId, $keterangan,
                        $tanggal, $idGuru, $unggah
                    ): void {
                        foreach ($pilih as $sid) {
                            tambah_catatan([
                                'semester_id' => $semesterId,
                                'siswa_id' => $sid,
                                'katalog_id' => (int) $baris['id'],
                                'aspek_id' => $aspekId,
                                'poin' => $poin,
                                'sumber' => 'manual',
                                'pencatat_id' => $idGuru,
                                'keterangan' => $keterangan !== '' ? $keterangan : null,
                                'bukti_path' => $unggah['path'],
                                'tanggal' => $tanggal,
                            ]);
                        }
                    });
                    if ($tingkat === 'berat') {
                        foreach ($siswa as $s) {
                            if (in_array((int) $s['id'], $pilih, true)) {
                                notifikasi_pelanggaran_berat((int) $s['id'], (string) $s['nama'], (string) $baris['nama'], $poin);
                            }
                        }
                    }
                    foreach ($pilih as $sid) {
                        evaluasi_ambang_siswa($sid, $semesterId);
                    }
                    log_akses('catat_poin_banyak', 'katalog_poin', (int) $baris['id']);
                    flash(count($pilih) . ' catatan poin untuk ' . $baris['nama'] . ' tersimpan dalam satu transaksi.');
                    alihkan('pages/guru/riwayat.php');
                }
            }
        }
    }
}

halaman_atas('Catat banyak siswa', 'Satu kejadian / banyak siswa', [
    'nav' => 'beranda',
    'aksi' => '<a class="tombol tombol-putih" href="' . e(url('pages/guru/catat_poin.php')) . '">' . ikon('siswa', 20) . ' Mode satu siswa</a>',
]);
?>
<?php if ($pesanGalat !== ''): ?>
  <div class="pesan pesan-galat" role="alert"><?= ikon('peringatan', 20) ?><span><?= e($pesanGalat) ?></span></div>
<?php endif; ?>

<?php if ($siswa === [] || $katalog === []): ?>
  <div class="pesan pesan-info" role="status"><?= ikon('info', 20) ?>
    <span><?= $siswa === [] ? 'Belum ada siswa aktif.' : 'Katalog poin manual belum tersedia.' ?> Minta admin melengkapi data.</span></div>
<?php else: ?>
  <div class="pesan pesan-info" role="status"><?= ikon('info', 20) ?>
    <span>Pilih beberapa siswa, lalu satu kejadian. Batas pengurangan <strong><?= e($batasHarian) ?> poin per aspek per siswa per hari</strong> tetap berlaku; bila ada siswa yang melampauinya, seluruh penyimpanan dibatalkan.</span></div>

  <form class="kartu form-kartu" method="post" action="<?= e(url('pages/guru/catat_banyak.php')) ?>" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <div class="form-grid">
      <div class="form-baris lebar-penuh">
        <div class="baris-tombol" style="justify-content:space-between">
          <label style="text-transform:uppercase;letter-spacing:.08em;font-size:.78rem;font-weight:700">Siswa <span class="wajib">wajib</span></label>
          <div class="baris-tombol">
            <button class="tombol tombol-kecil tombol-putih" type="button" data-pilih-semua="1"><?= ikon('centang', 16) ?> Pilih semua</button>
            <button class="tombol tombol-kecil tombol-putih" type="button" data-pilih-semua="0"><?= ikon('silang', 16) ?> Kosongkan</button>
          </div>
        </div>
        <div class="daftar-siswa" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(210px,1fr));gap:8px;margin-top:8px">
          <?php foreach ($siswa as $s): ?>
            <label class="pilihan-baris" style="border:2px solid #000;border-radius:8px;padding:8px 10px;display:flex;gap:8px;align-items:center;cursor:pointer;background:#fff">
              <input type="checkbox" name="siswa_id[]" value="<?= (int) $s['id'] ?>" style="width:20px;height:20px;min-height:0">
              <span><strong style="font-size:.88rem"><?= e($s['nama']) ?></strong><br>
                <span class="teks-kecil mono"><?= e($s['nis']) ?> · <?= e($s['kelas']) ?></span></span>
            </label>
          <?php endforeach; ?>
        </div>
        <p class="bantuan" id="jumlah-terpilih">0 siswa dipilih.</p>
      </div>
      <div class="form-baris lebar-penuh"><label for="katalog_id">Kejadian / katalog poin <span class="wajib">wajib</span></label>
        <select class="input" id="katalog_id" name="katalog_id" required data-pilih-poin>
          <option value="">— Pilih kejadian —</option>
          <?php foreach ($katalog as $k): ?>
            <option value="<?= (int) $k['id'] ?>" data-poin="<?= (int) $k['poin'] ?>" data-tingkat="<?= e($k['tingkat']) ?>" data-wajib="<?= (int) $k['wajib_keterangan'] ?>">
              <?= e($k['aspek']) ?> · <?= e($k['nama']) ?> (<?= ((int) $k['poin'] > 0 ? '+' : '') . (int) $k['poin'] ?>)
            </option>
          <?php endforeach; ?>
        </select>
        <p class="bantuan">Poin per siswa: <strong id="pratinjau-poin">—</strong></p></div>
      <div class="form-baris"><label for="tanggal">Tanggal kejadian</label>
        <input class="input" id="tanggal" name="tanggal" type="date" value="<?= e(date('Y-m-d')) ?>" max="<?= e(date('Y-m-d')) ?>"></div>
      <div class="form-baris"><label for="bukti">Bukti foto (opsional, dipakai bersama)</label>
        <input class="input" id="bukti" name="bukti" type="file" accept="image/jpeg,image/png,image/webp"></div>
      <div class="form-baris lebar-penuh"><label for="keterangan">Deskripsi</label>
        <textarea class="input" id="keterangan" name="keterangan" rows="3" maxlength="2000" placeholder="Wajib untuk kejadian sedang atau berat."></textarea></div>
    </div>
    <div class="form-aksi">
      <button class="tombol tombol-utama" type="submit" data-konfirmasi="Simpan poin untuk semua siswa yang dipilih?">
        <?= ikon('simpan', 20) ?> Simpan ke semua siswa terpilih
      </button>
      <a class="tombol tombol-putih" href="<?= e(url('pages/guru/beranda.php')) ?>">Batal</a>
    </div>
  </form>

  <script>
    (function () {
      'use strict';
      var kotak = Array.prototype.slice.call(document.querySelectorAll('input[name="siswa_id[]"]'));
      var keluaran = document.getElementById('jumlah-terpilih');
      function hitung() {
        var n = kotak.filter(function (x) { return x.checked; }).length;
        if (keluaran) { keluaran.textContent = n + ' siswa dipilih.'; }
      }
      document.querySelectorAll('[data-pilih-semua]').forEach(function (tombol) {
        tombol.addEventListener('click', function () {
          var ya = tombol.getAttribute('data-pilih-semua') === '1';
          kotak.forEach(function (x) { x.checked = ya; });
          hitung();
        });
      });
      kotak.forEach(function (x) { x.addEventListener('change', hitung); });
      var pilih = document.querySelector('[data-pilih-poin]');
      var out = document.getElementById('pratinjau-poin');
      if (pilih && out) {
        pilih.addEventListener('change', function () {
          var o = pilih.options[pilih.selectedIndex];
          var p = o ? o.getAttribute('data-poin') : null;
          out.textContent = p === null || p === '' ? '—' : (Number(p) > 0 ? '+' : '') + p + ' poin';
        });
      }
      hitung();
    })();
  </script>
<?php endif; ?>
<?php halaman_bawah(); ?>
