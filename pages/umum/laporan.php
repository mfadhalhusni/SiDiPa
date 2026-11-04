<?php
declare(strict_types=1);

/*
 | Tahap 4 — Laporan per kelas dan per aspek (bagian 10 PDF).
 | Hanya admin, BK, dan kepala sekolah. Isi murni agregat skor: tidak ada
 | pencatat_id, riwayat pelanggaran, maupun peringkat skor terendah.
 */

require_once __DIR__ . '/../../app/bootstrap.php';

$pengguna = auth_wajib(['admin', 'bk', 'kepsek']);
$peran = (string) $pengguna['peran'];
$semesterId = bilangan($_GET['semester'] ?? $_POST['semester'] ?? 0);
$daftarSemester = daftar_semester();
if ($semesterId <= 0) {
    $semesterId = (int) (semester_aktif()['id'] ?? ($daftarSemester[0]['id'] ?? 0));
}
$semester = semester_dari_id($semesterId) ?? semester_aktif();
$semesterId = (int) ($semester['id'] ?? 0);
$mulai = (string) ($semester['tgl_mulai'] ?? date('Y-m-01'));
$selesai = (string) ($semester['tgl_selesai'] ?? date('Y-m-t'));

$ringkasan = ringkasan_kelas($semesterId, $mulai, $selesai);
$aspek = daftar_aspek();
$teladan = $semesterId > 0 ? siswa_teladan($semesterId, 6) : [];

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && aksi_post() === 'ekspor') {
    csrf_wajib();
    $header = ['NIS', 'Nama', 'Kelas'];
    foreach ($aspek as $a) {
        $header[] = (string) $a['nama'] . ' - skor';
        $header[] = (string) $a['nama'] . ' - %';
        $header[] = (string) $a['nama'] . ' - predikat';
    }
    $siswa = db_semua(
        'SELECT s.id, s.nis, s.nama, k.nama AS kelas FROM siswa s
         LEFT JOIN kelas k ON k.id = s.kelas_id WHERE s.aktif = 1 ORDER BY k.nama, s.nama LIMIT 500'
    );
    $baris = [];
    foreach ($siswa as $s) {
        $skor = $semesterId > 0 ? skor_per_aspek((int) $s['id'], $semesterId) : [];
        $urut = [];
        foreach ($skor as $a) {
            $urut[$a['kode']] = $a;
        }
        $isi = [$s['nis'], $s['nama'], $s['kelas'] ?? '-'];
        foreach ($aspek as $a) {
            $nilai = $urut[$a['kode']] ?? ['skor' => 0, 'persentase' => 0, 'predikat' => '-'];
            $isi[] = $nilai['skor'];
            $isi[] = $nilai['persentase'] . '%';
            $isi[] = $nilai['predikat'];
        }
        $baris[] = $isi;
    }
    $headerKelas = array_merge(
        ['Kelas', 'Jumlah Siswa'],
        array_map(static fn($a): string => 'Rata ' . (string) $a['nama'], $aspek),
        ['% Hadir', 'Pembinaan', 'Banding', 'Sertifikat']
    );
    $isiKelas = [];
    foreach ($ringkasan as $r) {
        $deret = [$r['nama'], (int) $r['jumlah_siswa']];
        foreach ($r['aspek'] as $a) {
            $deret[] = (float) $a['rata'];
        }
        $deret[] = (float) $r['persentase_hadir'];
        $deret[] = (int) $r['pembinaan'];
        $deret[] = (int) $r['banding'];
        $deret[] = (int) $r['sertifikat'];
        $isiKelas[] = $deret;
    }
    log_akses('ekspor_xlsx_laporan', 'semester', $semesterId ?: null);
    xlsx_keluar(
        'sidipa-laporan-' . ($semester['tahun_ajaran'] ?? date('Y')) . '-smt' . ($semester['semester'] ?? 1),
        [
            [
                'nama' => 'Skor per Siswa',
                'header' => $header,
                'baris' => $baris,
                'lebar' => array_merge([14, 28, 18], array_fill(0, count($aspek) * 3, 13)),
            ],
            [
                'nama' => 'Ringkasan per Kelas',
                'header' => $headerKelas,
                'baris' => $isiKelas,
                'lebar' => array_merge([22, 14], array_fill(0, count($aspek), 14), [12, 12, 12, 14]),
            ],
        ],
        [
            ['label' => 'Semester', 'nilai' => (string) ($semester['nama'] ?? '-')],
            ['label' => 'Tahun ajaran', 'nilai' => (string) ($semester['tahun_ajaran'] ?? '-')],
            ['label' => 'Periode', 'nilai' => $mulai . ' s/d ' . $selesai],
            ['label' => 'Jumlah siswa', 'nilai' => count($baris)],
            ['label' => 'Dibuat pada', 'nilai' => date('Y-m-d H:i:s') . ' WIB'],
        ]
    );
}

halaman_atas('Laporan', 'Per kelas / per aspek / semester', [
    'nav' => 'rekap',
    'aksi' => '<a class="tombol tombol-putih" href="' . e(url('pages/umum/rekap_kehadiran.php')) . '">' . ikon('rekap', 20) . ' Rekap Kehadiran</a>',
]);
?>
<div class="pesan pesan-info" role="status"><?= ikon('info', 20) ?>
  <span>Semester <?= e($semester['nama'] ?? '-') ?> (<?= e($semester['tahun_ajaran'] ?? '-') ?>) · <?= e(tanggal_indonesia($mulai, false)) ?> sampai <?= e(tanggal_indonesia($selesai, false)) ?>. Predikat dihitung per aspek; tidak ada predikat keseluruhan.</span>
</div>

<section class="kartu form-kartu">
  <form method="get" action="<?= e(url('pages/umum/laporan.php')) ?>" class="form-grid">
    <div class="form-baris"><label for="semester">Semester</label>
      <select class="input" id="semester" name="semester" onchange="this.form.submit()">
        <?php foreach ($daftarSemester as $sm): ?>
          <option value="<?= e($sm['id']) ?>" <?= (int) $sm['id'] === $semesterId ? 'selected' : '' ?>><?= e($sm['nama']) ?> (<?= e($sm['tahun_ajaran']) ?>)</option>
        <?php endforeach; ?>
      </select></div>
    <div class="form-baris lebar-penuh form-aksi"><button class="tombol tombol-utama" type="submit"><?= ikon('cari', 20) ?> Tampilkan</button></div>
  </form>
  <form method="post" action="<?= e(url('pages/umum/laporan.php')) ?>" class="baris-tombol margin-atas">
    <?= csrf_field() ?>
    <input type="hidden" name="aksi" value="ekspor">
    <input type="hidden" name="semester" value="<?= e($semesterId) ?>">
    <button class="tombol tombol-toska" type="submit"><?= ikon('unduh', 20) ?> Unduh XLSX (skor + ringkasan)</button>
  </form>
</section>

<?= bagian_judul('Rata-rata skor per kelas', 'Angka rata-rata dari catatan aktif.') ?>
<div class="tabel-bungkus margin-bawah">
  <table class="tabel">
    <thead><tr><th>Kelas</th><th>Siswa</th><?php foreach ($aspek as $a): ?><th><?= e($a['nama']) ?></th><?php endforeach; ?><th>% Hadir</th><th>Pembinaan</th><th>Banding</th><th>Sertifikat</th></tr></thead>
    <tbody>
    <?php foreach ($ringkasan as $r): ?>
      <tr>
        <td><strong><?= e($r['nama']) ?></strong></td>
        <td class="mono"><?= e($r['jumlah_siswa']) ?></td>
        <?php foreach ($aspek as $a): ?><td class="mono"><?= e($r['aspek'][(int) $a['id']]['rata'] ?? 0) ?></td><?php endforeach; ?>
        <td class="mono"><?= e($r['persentase_hadir']) ?>%</td>
        <td class="mono"><?= e($r['pembinaan']) ?></td>
        <td class="mono"><?= e($r['banding']) ?></td>
        <td class="mono"><?= e($r['sertifikat']) ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if ($ringkasan === []): ?><tr><td colspan="<?= 7 + count($aspek) ?>" class="tabel-kosong">Belum ada kelas aktif.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>

<?= bagian_judul('Siswa teladan per aspek', 'Hanya peringkat positif yang boleh ditampilkan.') ?>
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
