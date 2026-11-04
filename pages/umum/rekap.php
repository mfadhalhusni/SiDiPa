<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/bootstrap.php';

$pengguna = auth_wajib();
$peran = $pengguna['peran'];
$semester = semester_aktif();
$semesterId = (int) ($semester['id'] ?? 0);
$sendiri = in_array($peran, ['siswa', 'wali'], true) ? siswa_akun((int) $pengguna['id'], $peran) : null;
$aspek = daftar_aspek();
$warna = ['bg-koral', 'bg-kuning', 'bg-toska', 'bg-ungu', 'bg-oranye', 'bg-biru'];

$skorSendiri = [];
if ($sendiri !== null && $semesterId > 0) {
    $skorSendiri = skor_per_aspek((int) $sendiri['id'], $semesterId);
}

$daftarSiswa = [];
if ($sendiri === null && $semesterId > 0) {
    $syarat = ['s.aktif = 1'];
    $param = [];
    $daftarSiswa = db_semua(
        'SELECT s.id, s.nama, k.nama AS kelas FROM siswa s LEFT JOIN kelas k ON k.id = s.kelas_id
         WHERE ' . implode(' AND ', $syarat) . ' ORDER BY k.nama, s.nama LIMIT 200',
        $param
    );
}

$akumulasi = [];
foreach ($daftarSiswa as $s) {
    $kelas = $s['kelas'] ?? 'Tanpa kelas';
    if (!isset($akumulasi[$kelas])) {
        $akumulasi[$kelas] = ['jumlah' => 0, 'aspek' => []];
        foreach ($aspek as $a) {
            $akumulasi[$kelas]['aspek'][(int) $a['id']] = ['total' => 0, 'predikat' => []];
        }
    }
    $akumulasi[$kelas]['jumlah']++;
    foreach (skor_per_aspek((int) $s['id'], $semesterId) as $baris) {
        $akumulasi[$kelas]['aspek'][$baris['aspek_id']]['total'] += $baris['skor'];
    }
}
ksort($akumulasi);

$teladan = [];
if ($semesterId > 0) {
    try {
        $teladan = siswa_teladan($semesterId, 6);
    } catch (Throwable $galat) {
        catat_kesalahan('rekap teladan: ' . $galat->getMessage());
    }
}

halaman_atas('Rekap skor', 'Per aspek / per semester', ['nav' => 'rekap']);
?>
<div class="pesan pesan-info" role="status"><?= ikon('info', 20) ?><span>Skor dimulai dari 0 tiap semester, boleh minus, dan predikat dihitung per aspek. Peringkat skor terendah tidak ditampilkan.</span></div>

<?php if ($sendiri !== null): ?>
  <?= bagian_judul('Enam aspek karakter', $sendiri['nama'] . ' · ' . ($sendiri['kelas'] ?? '-')) ?>
  <div class="skor-grid">
    <?php foreach ($skorSendiri as $i => $s): ?>
      <div class="kartu kartu-skor <?= e($warna[$i % count($warna)]) ?>">
        <p class="label-kecil"><?= e($s['aspek']) ?></p>
        <span class="predikat"><?= e($s['predikat']) ?></span>
        <span class="angka mono">Skor <?= e($s['skor']) ?> / <?= e($s['target']) ?></span>
        <p class="teks-kecil" style="margin:4px 0 0"><?= e($s['persentase']) ?>% · <?= e($s['keterangan']) ?></p>
      </div>
    <?php endforeach; ?>
    <?php if ($skorSendiri === []): ?><div class="kartu tabel-kosong">Belum ada semester aktif.</div><?php endif; ?>
  </div>
<?php else: ?>
  <?php foreach ($akumulasi as $namaKelas => $data): ?>
    <?= bagian_judul($namaKelas, $data['jumlah'] . ' siswa') ?>
    <div class="tabel-bungkus">
      <table class="tabel">
        <thead><tr><th>Aspek</th><th>Rata-rata skor</th></tr></thead>
        <tbody>
        <?php foreach ($aspek as $a): ?>
          <?php $rata = $data['jumlah'] > 0 ? $data['aspek'][(int) $a['id']]['total'] / $data['jumlah'] : 0; ?>
          <tr><td><?= e($a['nama']) ?></td><td class="mono"><strong><?= e(round($rata, 1)) ?></strong></td></tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endforeach; ?>
  <?php if ($akumulasi === []): ?><div class="kartu tabel-kosong">Belum ada siswa yang dapat direkap pada semester ini.</div><?php endif; ?>
<?php endif; ?>

<?= bagian_judul('Siswa teladan', 'Hanya peringkat positif per aspek.') ?>
<div class="tabel-bungkus">
  <table class="tabel">
    <thead><tr><th>Aspek</th><th>Siswa</th><th>Skor</th></tr></thead>
    <tbody>
    <?php foreach ($teladan as $t): ?>
      <tr><td><?= e($t['aspek']) ?></td><td><?= e($t['nama_siswa']) ?> <span class="teks-kecil">(<?= e($t['kelas'] ?? '-') ?>)</span></td><td class="mono">+<?= e($t['skor']) ?></td></tr>
    <?php endforeach; ?>
    <?php if ($teladan === []): ?><tr><td colspan="3" class="tabel-kosong">Belum ada catatan poin positif.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<?php halaman_bawah(); ?>
