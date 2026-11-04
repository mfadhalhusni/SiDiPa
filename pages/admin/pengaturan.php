<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/bootstrap.php';

$pengguna = auth_wajib(['admin']);
$pesanGalat = '';

$grup = [
    'sekolah' => 'Identitas sekolah',
    'jam' => 'Jam dan waktu',
    'poin' => 'Bobot poin otomatis',
    'predikat' => 'Ambang predikat (8 tingkat)',
    'pembinaan' => 'Ambang pembinaan',
    'penilaian' => 'Poin penilaian bulanan',
    'lainnya' => 'Lainnya',
    'wa' => 'WhatsApp (OpenWA)',
];

$definisi = [
    'sekolah' => [
        ['kunci' => 'nama_sekolah', 'label' => 'Nama sekolah', 'tipe' => 'teks'],
        ['kunci' => 'alamat_sekolah', 'label' => 'Alamat sekolah', 'tipe' => 'teks'],
        ['kunci' => 'nama_kepsek', 'label' => 'Nama kepala sekolah', 'tipe' => 'teks'],
        ['kunci' => 'nip_kepsek', 'label' => 'NIP kepala sekolah', 'tipe' => 'teks'],
        ['kunci' => 'logo_sekolah', 'label' => 'Logo sekolah', 'tipe' => 'berkas', 'bantuan' => 'JPG, PNG, atau WebP maksimal 2 MB. Dipakai pada kartu dan sertifikat.'],
        ['kunci' => 'ttd_kepsek', 'label' => 'Tanda tangan kepala sekolah', 'tipe' => 'berkas'],
        ['kunci' => 'ttd_wali_default', 'label' => 'Tanda tangan wali kelas bawaan', 'tipe' => 'berkas'],
    ],
    'jam' => [
        ['kunci' => 'jam_bonus_pagi', 'label' => 'Jam bonus datang pagi', 'tipe' => 'jam', 'bantuan' => 'Scan sebelum jam ini tercatat hadir awal.'],
        ['kunci' => 'batas_terlambat', 'label' => 'Batas terlambat', 'tipe' => 'jam', 'bantuan' => 'Scan sesudah jam ini berstatus terlambat.'],
        ['kunci' => 'jam_cron_harian', 'label' => 'Jam proses harian', 'tipe' => 'jam', 'bantuan' => 'Sesuaikan dengan jam cron cPanel.'],
    ],
    'poin' => [
        ['kunci' => 'poin_terlambat', 'label' => 'Poin terlambat', 'tipe' => 'angka'],
        ['kunci' => 'poin_alpa', 'label' => 'Poin alpa satu hari', 'tipe' => 'angka'],
        ['kunci' => 'poin_tidak_sholat', 'label' => 'Poin tidak hadir sholat', 'tipe' => 'angka'],
        ['kunci' => 'poin_minggu_disiplin', 'label' => 'Poin minggu tanpa terlambat dan alpa', 'tipe' => 'angka'],
        ['kunci' => 'poin_minggu_dhuha', 'label' => 'Poin minggu penuh dhuha', 'tipe' => 'angka'],
        ['kunci' => 'poin_minggu_dhuhur', 'label' => 'Poin minggu penuh dhuhur', 'tipe' => 'angka'],
        ['kunci' => 'poin_bonus_pagi', 'label' => 'Poin bonus datang pagi', 'tipe' => 'angka'],
        ['kunci' => 'bonus_pagi_setiap_hari', 'label' => 'Bonus diberikan setiap N hari awal', 'tipe' => 'angka'],
        ['kunci' => 'bonus_pagi_maks', 'label' => 'Maksimal bonus pagi per semester', 'tipe' => 'angka'],
        ['kunci' => 'batas_pengurangan_harian', 'label' => 'Maksimal pengurangan manual per aspek per hari', 'tipe' => 'angka'],
    ],
    'predikat' => [
        ['kunci' => 'predikat_aplus', 'label' => 'A+ mulai persentase (bawaan 95)', 'tipe' => 'angka'],
        ['kunci' => 'predikat_a', 'label' => 'A mulai persentase (bawaan 90)', 'tipe' => 'angka'],
        ['kunci' => 'predikat_bplus', 'label' => 'B+ mulai persentase (bawaan 85)', 'tipe' => 'angka'],
        ['kunci' => 'predikat_b', 'label' => 'B mulai persentase (bawaan 80)', 'tipe' => 'angka'],
        ['kunci' => 'predikat_bminus', 'label' => 'B- mulai persentase (bawaan 75)', 'tipe' => 'angka'],
        ['kunci' => 'predikat_cplus', 'label' => 'C+ mulai persentase (bawaan 70)', 'tipe' => 'angka'],
        ['kunci' => 'predikat_c', 'label' => 'C mulai persentase (bawaan 60)', 'tipe' => 'angka'],
        ['kunci' => 'predikat_d', 'label' => 'D mulai persentase (bawaan 0; di bawahnya tetap D)', 'tipe' => 'angka'],
    ],
    'pembinaan' => [
        ['kunci' => 'ambang_pembinaan_persen', 'label' => 'Persentase di bawah nilai ini masuk pembinaan', 'tipe' => 'angka'],
        ['kunci' => 'ambang_pembinaan_skor', 'label' => 'Skor di bawah nilai ini memberi notifikasi BK', 'tipe' => 'angka'],
    ],
    'penilaian' => [
        ['kunci' => 'penilaian_kurang', 'label' => 'Kurang', 'tipe' => 'angka'],
        ['kunci' => 'penilaian_cukup', 'label' => 'Cukup', 'tipe' => 'angka'],
        ['kunci' => 'penilaian_baik', 'label' => 'Baik', 'tipe' => 'angka'],
        ['kunci' => 'penilaian_sangat_baik', 'label' => 'Sangat Baik', 'tipe' => 'angka'],
    ],
    'lainnya' => [
        ['kunci' => 'batas_batal_catatan_jam', 'label' => 'Batas pembatalan catatan oleh guru (jam)', 'tipe' => 'angka'],
        ['kunci' => 'maks_ukuran_bukti', 'label' => 'Ukuran maksimal foto bukti (byte)', 'tipe' => 'angka'],
    ],
    'wa' => [
        ['kunci' => 'wa_aktif', 'label' => 'Aktifkan pengiriman WhatsApp', 'tipe' => 'centang'],
        ['kunci' => 'wa_base_url', 'label' => 'Alamat server OpenWA', 'tipe' => 'teks', 'bantuan' => 'Misal https://wa.smk.sch.id. Kosongkan bila belum ada.'],
        ['kunci' => 'wa_api_key', 'label' => 'API key', 'tipe' => 'teks'],
        ['kunci' => 'wa_session_id', 'label' => 'Session id', 'tipe' => 'teks'],
        ['kunci' => 'wa_endpoint', 'label' => 'Endpoint kirim pesan', 'tipe' => 'teks', 'bantuan' => 'Sesuaikan dengan dokumentasi OpenWA di app/wa.php.'],
        ['kunci' => 'wa_maks_per_jalan', 'label' => 'Maksimal pesan per kali cron', 'tipe' => 'angka'],
        ['kunci' => 'wa_percobaan_maks', 'label' => 'Maksimal percobaan kirim', 'tipe' => 'angka'],
        ['kunci' => 'wa_jeda_min', 'label' => 'Jeda acak minimum (detik)', 'tipe' => 'angka'],
        ['kunci' => 'wa_jeda_maks', 'label' => 'Jeda acak maksimum (detik)', 'tipe' => 'angka'],
        ['kunci' => 'wa_pemicu_alpa', 'label' => 'Pesan alpa', 'tipe' => 'centang'],
        ['kunci' => 'wa_pemicu_terlambat', 'label' => 'Pesan terlambat', 'tipe' => 'centang'],
        ['kunci' => 'wa_pemicu_pelanggaran_berat', 'label' => 'Pesan pelanggaran berat', 'tipe' => 'centang'],
        ['kunci' => 'wa_pemicu_skor_rendah', 'label' => 'Pesan skor aspek rendah', 'tipe' => 'centang'],
        ['kunci' => 'wa_pemicu_sertifikat', 'label' => 'Pesan sertifikat terbit', 'tipe' => 'centang'],
        ['kunci' => 'wa_template_alpa', 'label' => 'Template alpa', 'tipe' => 'area'],
        ['kunci' => 'wa_template_terlambat', 'label' => 'Template terlambat', 'tipe' => 'area'],
        ['kunci' => 'wa_template_pelanggaran_berat', 'label' => 'Template pelanggaran berat', 'tipe' => 'area'],
        ['kunci' => 'wa_template_skor_rendah', 'label' => 'Template skor rendah', 'tipe' => 'area'],
        ['kunci' => 'wa_template_sertifikat', 'label' => 'Template sertifikat terbit', 'tipe' => 'area'],
    ],
];

$daftarAspek = daftar_aspek();
$pesanVariabel = 'Variabel tersedia: {nama}, {kelas}, {tanggal}, {jam}, {aspek}, {poin}.';

/** Validasi dan simpan berkas gambar yang diunggah. */
function simpan_berkas_pengaturan(string $kunci, string $grup): array
{
    if (!isset($_FILES[$kunci]) || (int) ($_FILES[$kunci]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return ['ok' => true, 'pesan' => ''];
    }
    $berkas = $_FILES[$kunci];
    if ((int) $berkas['error'] !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'pesan' => 'Unggahan ' . $kunci . ' gagal (kode ' . (int) $berkas['error'] . ').'];
    }
    if ((int) $berkas['size'] > SIDIPA_MAKS_BUKTI) {
        return ['ok' => false, 'pesan' => 'Ukuran ' . $kunci . ' melebihi 2 MB.'];
    }
    $mime = '';
    if (function_exists('finfo_open')) {
        $fi = finfo_open(FILEINFO_MIME_TYPE);
        $mime = (string) finfo_file($fi, $berkas['tmp_name']);
        finfo_close($fi);
    } else {
        $info = @getimagesize($berkas['tmp_name']);
        $mime = (string) ($info['mime'] ?? '');
    }
    $izin = ['image/jpeg' => '.jpg', 'image/png' => '.png', 'image/webp' => '.webp'];
    if (!isset($izin[$mime])) {
        return ['ok' => false, 'pesan' => 'Berkas ' . $kunci . ' harus berupa JPG, PNG, atau WebP.'];
    }
    if (!is_uploaded_file($berkas['tmp_name'])) {
        return ['ok' => false, 'pesan' => 'Unggahan tidak valid.'];
    }
    $namaBerkas = 'set-' . preg_replace('/[^a-z0-9_]/i', '-', $kunci) . '-' . acak_token(16) . $izin[$mime];
    if (!move_uploaded_file($berkas['tmp_name'], SIDIPA_DIR_UPLOAD . '/' . $namaBerkas)) {
        return ['ok' => false, 'pesan' => 'Gagal menyimpan ' . $kunci . '. Pastikan folder uploads dapat ditulis.'];
    }
    return ['ok' => true, 'pesan' => 'public/uploads/' . $namaBerkas, 'nilai' => 'public/uploads/' . $namaBerkas, 'grup' => $grup];
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_wajib();
    $kesalahan = [];
    $nilaiBaru = [];

    foreach ($definisi as $kodeGrup => $item2) {
        foreach ($item2 as $item) {
            $kunci = $item['kunci'];
            $tipe = $item['tipe'];
            if ($tipe === 'berkas') {
                $hasil = simpan_berkas_pengaturan($kunci, $kodeGrup);
                if (!$hasil['ok']) {
                    $kesalahan[] = $hasil['pesan'];
                } elseif (isset($hasil['nilai'])) {
                    $nilaiBaru[$kunci] = $hasil['nilai'];
                }
                continue;
            }
            if ($tipe === 'centang') {
                $nilaiBaru[$kunci] = post_bool($kunci) ? '1' : '0';
                continue;
            }
            $nilai = $tipe === 'area' ? (string) ($_POST[$kunci] ?? '') : post($kunci, '', 500);
            if ($tipe === 'angka' && filter_var($nilai, FILTER_VALIDATE_INT) === false) {
                $kesalahan[] = $item['label'] . ' harus berupa bilangan bulat.';
                continue;
            }
            if ($tipe === 'jam' && !preg_match('/^([01][0-9]|2[0-3]):[0-5][0-9]$/', $nilai)) {
                $kesalahan[] = $item['label'] . ' harus berformat HH:MM.';
                continue;
            }
            $nilaiBaru[$kunci] = $nilai;
        }
    }

    $targetAspek = [];
    if (isset($_POST['aspek_target']) && is_array($_POST['aspek_target'])) {
        foreach ($_POST['aspek_target'] as $idAspek => $nilai) {
            $idAspek = bilangan($idAspek);
            $nilai = bilangan($nilai, -1);
            if ($idAspek > 0 && $nilai > 0) {
                $targetAspek[$idAspek] = $nilai;
            } else {
                $kesalahan[] = 'Target aspek harus bilangan bulat lebih dari 0.';
            }
        }
    }

    if ($kesalahan !== []) {
        $pesanGalat = implode(' ', $kesalahan);
    } else {
        try {
            db_transaksi(function () use ($nilaiBaru, $targetAspek, $definisi): void {
                foreach ($nilaiBaru as $kunci => $nilai) {
                    $grupKunci = 'umum';
                    foreach ($definisi as $kode => $item2) {
                        foreach ($item2 as $item) {
                            if ($item['kunci'] === $kunci) {
                                $grupKunci = $kode;
                                break 2;
                            }
                        }
                    }
                    atur_pengaturan($kunci, $nilai, $grupKunci);
                }
                foreach ($targetAspek as $idAspek => $target) {
                    db_jalankan('UPDATE aspek SET target_semester = ? WHERE id = ?', [$target, $idAspek]);
                }
            });
            reset_cache_pengaturan();
            log_akses('ubah_pengaturan', 'pengaturan');
            flash('Pengaturan dan target aspek disimpan.');
            alihkan('pages/admin/pengaturan.php');
        } catch (Throwable $galat) {
            $pesanGalat = 'Gagal menyimpan: ' . $galat->getMessage();
        }
    }
}

halaman_atas('Pengaturan', 'Admin / Kalibrasi sistem', ['nav' => 'profil']);
?>
<?php if ($pesanGalat !== ''): ?><div class="pesan pesan-galat" role="alert"><?= ikon('peringatan', 20) ?><span><?= e($pesanGalat) ?></span></div><?php endif; ?>
<div class="pesan pesan-info" role="status"><?= ikon('info', 20) ?><span>Semua jam, bobot poin, target, dan ambang tersimpan di database. Perubahan langsung berlaku tanpa menyunting kode.</span></div>

<form method="post" action="<?= e(url('pages/admin/pengaturan.php')) ?>" enctype="multipart/form-data">
  <?= csrf_field() ?>
  <?php foreach ($definisi as $kodeGrup => $item2): ?>
    <section class="kartu form-kartu">
      <p class="label-kecil">Grup pengaturan</p>
      <h2><?= e($grup[$kodeGrup]) ?></h2>
      <div class="form-grid">
        <?php foreach ($item2 as $item):
            $kunci = $item['kunci'];
            $nilai = pengaturan($kunci, '');
            $tipe = $item['tipe']; ?>
          <div class="form-baris <?= $tipe === 'area' || $tipe === 'berkas' ? 'lebar-penuh' : '' ?>">
            <?php if ($tipe === 'centang'): ?>
              <input type="hidden" name="<?= e($kunci) ?>" value="0">
              <div class="pilihan-baris">
                <input type="checkbox" id="<?= e($kunci) ?>" name="<?= e($kunci) ?>" value="1" <?= $nilai === '1' ? 'checked' : '' ?>>
                <label for="<?= e($kunci) ?>" style="text-transform:none;letter-spacing:0;font-size:.9rem"><?= e($item['label']) ?></label>
              </div>
            <?php else: ?>
              <label for="<?= e($kunci) ?>"><?= e($item['label']) ?></label>
              <?php if ($tipe === 'area'): ?>
                <textarea class="input" id="<?= e($kunci) ?>" name="<?= e($kunci) ?>" rows="2"><?= e($nilai) ?></textarea>
              <?php elseif ($tipe === 'berkas'): ?>
                <?php if ($nilai !== ''): ?>
                  <p class="bantuan">Berkas saat ini: <strong><?= e($nilai) ?></strong></p>
                  <img src="<?= e(url($nilai)) ?>" alt="Pratinjau <?= e($item['label']) ?>" style="max-height:80px;max-width:180px;border:3px solid #000;border-radius:8px;background:#fff;margin-bottom:8px">
                <?php endif; ?>
                <input class="input" id="<?= e($kunci) ?>" name="<?= e($kunci) ?>" type="file" accept="image/jpeg,image/png,image/webp">
              <?php else: ?>
                <input class="input" id="<?= e($kunci) ?>" name="<?= e($kunci) ?>" type="text" value="<?= e($nilai) ?>" <?= $tipe === 'jam' ? 'placeholder="06:50"' : '' ?>>
              <?php endif; ?>
            <?php endif; ?>
            <?php if (!empty($item['bantuan'])): ?><p class="bantuan"><?= e($item['bantuan']) ?></p><?php endif; ?>
            <?php if ($kodeGrup === 'wa' && $tipe === 'area'): ?><p class="bantuan"><?= e($pesanVariabel) ?></p><?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endforeach; ?>

  <section class="kartu form-kartu">
    <p class="label-kecil">Skala berlaku</p>
    <h2>Delapan tingkat predikat</h2>
    <p class="teks-kecil">Berlaku untuk sertifikat, rekap, laporan XLSX, kartu skor, dan predikat keseluruhan di beranda siswa. Urutan ambang harus menurun; nilai di bawah ambang C selalu menjadi <strong>D</strong>.</p>
    <div class="tabel-bungkus">
      <table class="tabel">
        <thead><tr><th>Predikat</th><th>Mulai persentase</th><th>Keterangan</th></tr></thead>
        <tbody>
        <?php $b = ambang_predikat(); $ket = keterangan_predikat(); foreach (URUTAN_PREDIKAT as $kode): ?>
          <tr><td><strong><?= e($kode) ?></strong></td><td class="mono"><?= e(rtrim(rtrim(number_format($b[$kode], 1, ',', '.'), '0'), ',')) ?>%</td><td><?= e($ket[$kode]) ?></td></tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>

  <section class="kartu form-kartu">
    <p class="label-kecil">Per aspek</p>
    <h2>Target semester</h2>
    <p class="teks-kecil">Persentase = skor aspek / target x 100. Predikat dihitung per aspek; beranda siswa juga menampilkan predikat keseluruhan.</p>
    <div class="form-grid">
      <?php foreach ($daftarAspek as $a): ?>
        <div class="form-baris">
          <label for="aspek_<?= e($a['id']) ?>"><?= e($a['nama']) ?></label>
          <input class="input" id="aspek_<?= e($a['id']) ?>" name="aspek_target[<?= e($a['id']) ?>]" type="number" min="1" step="1" value="<?= e($a['target_semester']) ?>" required>
        </div>
      <?php endforeach; ?>
    </div>
  </section>

  <div class="form-aksi">
    <button class="tombol tombol-utama" type="submit"><?= ikon('simpan', 20) ?> Simpan semua pengaturan</button>
  </div>
</form>
<?php halaman_bawah(); ?>
