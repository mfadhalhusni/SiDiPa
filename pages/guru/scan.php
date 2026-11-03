<?php
declare(strict_types=1);

/* Pemindai QR berkelanjutan + fallback NIS manual. */

require_once __DIR__ . '/../../app/bootstrap.php';

$pengguna = auth_wajib(['guru', 'admin']);
cek_input_semester();
$peran = (string) $pengguna['peran'];
$modeValid = ['masuk' => 'Scan Masuk', 'pulang' => 'Scan Pulang', 'dhuha' => 'Sholat Dhuha', 'dhuhur' => 'Sholat Dhuhur'];
$mode = teks($_GET['mode'] ?? '', 20);
if (!isset($modeValid[$mode])) {
    $mode = (string) ($_SESSION['scan_mode_terakhir'] ?? 'masuk');
}
if (!isset($modeValid[$mode])) {
    $mode = 'masuk';
}

$idKelas = $peran === 'guru' ? id_kelas_guru((int) $pengguna['id']) : [];
$syarat = [];
$param = [];
if ($idKelas !== []) {
    $tanda = implode(', ', array_fill(0, count($idKelas), '?'));
    $syarat[] = 's.kelas_id IN (' . $tanda . ')';
    $param = array_merge($param, $idKelas);
} elseif ($peran === 'guru') {
    $syarat[] = '1 = 0';
}
$where = $syarat !== [] ? ' WHERE ' . implode(' AND ', $syarat) : '';

$terakhir = db_semua(
    'SELECT * FROM (
       SELECT h.dibuat_pada AS waktu, h.jam_masuk AS jam, \'masuk\' AS mode,
              s.nama AS nama_siswa, s.nis, h.status
       FROM kehadiran h JOIN siswa s ON s.id = h.siswa_id' . $where . '
       UNION ALL
       SELECT ki.dibuat_pada AS waktu, ki.jam_scan AS jam, ji.kode AS mode,
              s.nama AS nama_siswa, s.nis, ki.status
       FROM kehadiran_ibadah ki JOIN siswa s ON s.id = ki.siswa_id
       JOIN jenis_ibadah ji ON ji.id = ki.jenis_ibadah_id' . $where . '
     ) gabungan
     ORDER BY waktu DESC, jam DESC LIMIT 10',
    array_merge($param, $param)
);

halaman_atas($modeValid[$mode], 'Pemindaian · waktu server Asia/Jakarta', ['nav' => 'beranda']);
?>
<div class="pesan pesan-info" role="status"><?= ikon('info', 20) ?>
  <span>Mode <strong><?= e($modeValid[$mode]) ?></strong> tersimpan sebagai pilihan terakhir. Kamera memerlukan HTTPS; bila tidak tersedia, pakai <strong>Input NIS manual</strong>.</span>
</div>

<nav class="baris-tombol margin-bawah" aria-label="Pilih mode pemindaian">
  <?php foreach ($modeValid as $k => $label): ?>
    <a class="tombol <?= $k === $mode ? 'tombol-utama' : 'tombol-putih' ?>"
       href="<?= e(url('pages/guru/scan.php?mode=' . rawurlencode($k))) ?>"><?= e($label) ?></a>
  <?php endforeach; ?>
</nav>

<div class="kartu bg-toska margin-bawah" id="hasil-scan" aria-live="polite" role="status">
  <p class="label-kecil">Hasil pemindaian</p>
  <h2 id="hasil-judul" style="margin:0">Siap memindai</h2>
  <p class="teks-kecil" id="hasil-rinci" style="margin:6px 0 0">Arahkan kamera ke kartu QR siswa atau ketik NIS di bawah.</p>
</div>

<div class="grid grid-2 margin-bawah">
  <section class="kartu">
    <p class="label-kecil">Pemindai kamera</p>
    <div id="qr-reader" style="width:100%;min-height:240px;background:#000;border:3px solid #000;border-radius:10px;overflow:hidden"></div>
    <div class="baris-tombol margin-atas">
      <button class="tombol tombol-utama" type="button" id="mulai-kamera"><?= ikon('kamera', 20) ?> Aktifkan kamera</button>
      <button class="tombol tombol-putih" type="button" id="henti-kamera"><?= ikon('silang', 20) ?> Hentikan</button>
    </div>
    <?php if (!https_aktif()): ?>
      <div class="pesan pesan-galat margin-atas" role="alert"><?= ikon('peringatan', 20) ?><span>Kamera diblokir karena halaman tidak memakai HTTPS.</span></div>
    <?php endif; ?>
  </section>

  <section class="kartu">
    <p class="label-kecil">Input NIS manual</p>
    <form id="form-manual" autocomplete="off">
      <div class="form-baris">
        <label for="nis">NIS siswa</label>
        <input class="input" id="nis" name="nis" inputmode="numeric" placeholder="Misal 20260100" required>
      </div>
      <div class="form-aksi"><button class="tombol tombol-utama" type="submit"><?= ikon('scan', 20) ?> Catat <?= e($modeValid[$mode]) ?></button></div>
    </form>
    <div class="garis-pemisah"></div>
    <p class="label-kecil">Jendela waktu</p>
    <p class="teks-kecil" style="margin:0">
      Masuk ≤ <?= e(pengaturan('batas_terlambat', '07:00')) ?> · awal &lt; <?= e(pengaturan('jam_bonus_pagi', '06:50')) ?><br>
      Dhuha 06.30–09.00 · Dhuhur 12.00–13.00<br>
      Duplikasi hari + jenis ditolak. Izin/sakit/udzur netral.
    </p>
  </section>
</div>

<?= bagian_judul('10 pemindaian terakhir', 'Data terbaru dari kelas yang Anda ampu.') ?>
<div class="tabel-bungkus">
  <table class="tabel">
    <thead><tr><th>Waktu</th><th>Siswa</th><th>Mode</th><th>Status</th></tr></thead>
    <tbody>
    <?php foreach ($terakhir as $t): ?>
      <tr>
        <td class="mono"><?= e(substr((string) $t['waktu'], 0, 16)) ?><br><span class="teks-kecil"><?= e(format_jam($t['jam'])) ?></span></td>
        <td><strong><?= e($t['nama_siswa']) ?></strong><br><span class="teks-kecil mono"><?= e($t['nis']) ?></span></td>
        <td><?= e($modeValid[$t['mode']] ?? $t['mode']) ?></td>
        <td><span class="chip <?= $t['status'] === 'terlambat' ? 'badge-koral' : 'badge-toska' ?>"><?= e($t['status']) ?></span></td>
      </tr>
    <?php endforeach; ?>
    <?php if ($terakhir === []): ?><tr><td colspan="4" class="tabel-kosong">Belum ada pemindaian tercatat.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>

<script src="<?= e(asset('js/html5-qrcode.min.js')) ?>" defer></script>
<script>
(function () {
  'use strict';
  var MODE = <?= json_encode($mode, JSON_UNESCAPED_SLASHES) ?>;
  var ENDPOINT = <?= json_encode(url('api/scan.php'), JSON_UNESCAPED_SLASHES) ?>;
  var judul = document.getElementById('hasil-judul');
  var rinci = document.getElementById('hasil-rinci');
  var kotak = document.getElementById('hasil-scan');
  var sibuk = false;
  var pemindai = null;
  var timerHasil = null;

  function bunyi() {
    try {
      var Ctx = window.AudioContext || window.webkitAudioContext;
      if (!Ctx) return;
      var ctx = new Ctx();
      var osc = ctx.createOscillator();
      var gain = ctx.createGain();
      osc.type = 'sine'; osc.frequency.value = 880;
      gain.gain.setValueAtTime(0.14, ctx.currentTime);
      gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.18);
      osc.connect(gain); gain.connect(ctx.destination);
      osc.start(); osc.stop(ctx.currentTime + 0.19);
    } catch (_) { /* perangkat tanpa audio */ }
  }

  function tampil(ok, teks, rincian) {
    kotak.classList.remove('bg-toska', 'bg-koral', 'bg-kuning');
    kotak.classList.add(ok ? 'bg-toska' : 'bg-koral');
    judul.textContent = teks;
    rinci.textContent = rincian;
    if (navigator.vibrate) navigator.vibrate(ok ? [90, 40, 90] : [220]);
    if (ok) bunyi();
    window.clearTimeout(timerHasil);
    timerHasil = window.setTimeout(function () {
      kotak.classList.remove('bg-toska');
      kotak.classList.add('bg-kuning');
      judul.textContent = 'Siap memindai lagi';
    }, 2000);
  }

  function kirim(data) {
    if (sibuk) return;
    sibuk = true;
    data.mode = MODE;
    window.SiDiPa.post(ENDPOINT, data).then(function (res) {
      if (res && res.ok) {
        var s = res.siswa || {};
        tampil(true, (s.nama || 'Siswa') + ' · ' + (res.label || MODE),
          (res.pesan || '') + ' | ' + (res.jam || '') + ' | ' + (s.kelas || '-')
          + (res.poin ? ' | ' + res.poin + ' poin' : ''));
        var tabel = document.querySelector('table.tabel tbody');
        if (tabel) {
          window.setTimeout(function () { window.location.reload(); }, 2200);
        }
      } else {
        tampil(false, 'Gagal', (res && res.pesan) ? res.pesan : 'Tidak dapat memproses pemindaian.');
      }
    }).catch(function () {
      tampil(false, 'Tidak ada koneksi', 'Periksa jaringan lalu coba lagi.');
    }).then(function () {
      window.setTimeout(function () { sibuk = false; }, 800);
    });
  }

  document.getElementById('form-manual').addEventListener('submit', function (e) {
    e.preventDefault();
    var nis = document.getElementById('nis').value.trim();
    if (nis !== '') { kirim({ nis: nis }); }
  });

  var mulai = document.getElementById('mulai-kamera');
  var henti = document.getElementById('henti-kamera');
  mulai.addEventListener('click', function () {
    if (typeof window.Html5Qrcode === 'undefined') {
      tampil(false, 'Pustaka QR belum termuat', 'Gunakan Input NIS manual atau muat ulang halaman.');
      return;
    }
    if (pemindai) return;
    pemindai = new window.Html5Qrcode('qr-reader', { formatsToSupport: [0] });
    window.Html5Qrcode.getCameras().then(function (kamera) {
      var id = kamera.length ? (kamera[kamera.length - 1].id) : null;
      var opsi = id ? { deviceId: { exact: id } } : { facingMode: 'environment' };
      return pemindai.start(opsi, { fps: 8, qrbox: { width: 220, height: 220 } },
        function (teks) { if (!sibuk && teks) kirim({ token: String(teks).trim() }); },
        function () {});
    }).then(function () {
      mulai.disabled = true; henti.disabled = false;
    }).catch(function (err) {
      tampil(false, 'Kamera tidak dapat dibuka', String(err && err.message ? err.message : err));
      pemindai = null;
    });
  });
  henti.addEventListener('click', function () {
    if (!pemindai) return;
    pemindai.stop().then(function () { pemindai.clear(); }).catch(function () {}).then(function () {
      pemindai = null; mulai.disabled = false; henti.disabled = true;
    });
  });
  henti.disabled = true;
}());
</script>
<?php halaman_bawah(); ?>
