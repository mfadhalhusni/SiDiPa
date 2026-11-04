<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/bootstrap.php';

$pengguna = auth_wajib(['admin']);
$pesanGalat = '';
$pratinjau = [];
$ringkasan = null;

/** Samakan nama kolom CSV agar toleran terhadap variasi penulisan. */
function normalisasi_header(string $nama): string
{
    $nama = strtolower(trim(preg_replace('/^\xEF\xBB\xBF/', '', $nama)));
    $nama = str_replace([' ', '-', '.', '/'], '_', $nama);
    $peta = [
        'nis' => 'nis', 'no_induk' => 'nis', 'nomor_induk' => 'nis', 'nisn' => 'nis',
        'username' => 'username', 'nama_pengguna' => 'username', 'user' => 'username',
        'username_siswa' => 'username', 'login' => 'username',
        'nama' => 'nama', 'nama_siswa' => 'nama', 'nama_lengkap' => 'nama',
        'kelas' => 'kelas', 'nama_kelas' => 'kelas', 'rombel' => 'kelas',
        'jenis_kelamin' => 'jk', 'jeniskelamin' => 'jk', 'jk' => 'jk', 'gender' => 'jk',
        'nama_wali' => 'nama_wali', 'nama_wali_murid' => 'nama_wali', 'wali' => 'nama_wali', 'ortu' => 'nama_wali',
        'no_wa_wali' => 'no_wa', 'no_wa' => 'no_wa', 'wa_wali' => 'no_wa', 'hp_wali' => 'no_wa', 'telepon_wali' => 'no_wa',
    ];
    return $peta[$nama] ?? $nama;
}

function baca_csv_siswa(string $path): array
{
    $handle = fopen($path, 'rb');
    if ($handle === false) {
        throw new RuntimeException('Berkas CSV tidak dapat dibuka.');
    }
    $awal = (string) fgets($handle);
    rewind($handle);
    $pemisah = substr_count($awal, ';') > substr_count($awal, ',') ? ';' : ',';
    $header = fgetcsv($handle, 0, $pemisah);
    if (!is_array($header) || $header === []) {
        fclose($handle);
        throw new RuntimeException('Baris judul CSV tidak terbaca.');
    }
    $kolom = [];
    foreach ($header as $i => $judul) {
        $kolom[normalisasi_header((string) $judul)] = $i;
    }
    foreach (['nis', 'nama', 'kelas'] as $wajib) {
        if (!array_key_exists($wajib, $kolom)) {
            fclose($handle);
            throw new RuntimeException('Kolom wajib "' . $wajib . '" tidak ada. Header: ' . implode(', ', $header));
        }
    }

    $hasil = [];
    $nomor = 1;
    while (($baris = fgetcsv($handle, 0, $pemisah)) !== false) {
        $nomor++;
        if ($baris === [null] || $baris === false || count(array_filter($baris, fn($v) => trim((string) $v) !== '')) === 0) {
            continue;
        }
        $ambil = function (string $kunci) use ($kolom, $baris): string {
            return array_key_exists($kunci, $kolom) ? trim((string) ($baris[$kolom[$kunci]] ?? '')) : '';
        };
        $jkRaw = strtoupper($ambil('jk'));
        $jk = in_array($jkRaw, ['P', 'PEREMPUAN', 'WANITA'], true) ? 'P' : 'L';
        $hasil[] = [
            'baris' => $nomor,
            'nis' => $ambil('nis'),
            'username' => $ambil('username'),
            'nama' => $ambil('nama'),
            'kelas' => $ambil('kelas'),
            'jenis_kelamin' => $jk,
            'nama_wali' => $ambil('nama_wali'),
            'no_wa_wali' => normalisasi_nomor_wa($ambil('no_wa')),
            'status' => 'siap',
            'catatan' => '',
        ];
        if (count($hasil) >= 1000) {
            break;
        }
    }
    fclose($handle);
    return $hasil;
}

function tandai_duplikat(array &$baris2): void
{
    $terlihat = [];
    $usernameTerlihat = [];
    $terpakai = [];
    foreach ($baris2 as &$r) {
        $mentah = (string) ($r['username'] ?? '');
        $r['username'] = $mentah === '' ? '' : username_siswa_bersih($mentah);
        if ($r['nis'] === '' || $r['nama'] === '' || $r['kelas'] === '') {
            $r['status'] = 'galat';
            $r['catatan'] = 'NIS, nama, atau kelas kosong.';
            continue;
        }
        if ($mentah !== '' && $r['username'] !== $mentah) {
            $r['status'] = 'galat';
            $r['catatan'] = 'Username mengandung karakter yang tidak diizinkan.';
            continue;
        }
        if ($mentah !== '') {
            $sah = username_siswa_sah($r['username']);
            if ($sah !== '') {
                $r['status'] = 'galat';
                $r['catatan'] = $sah;
                continue;
            }
        }
        $kunci = strtolower($r['nis']);
        if (isset($terlihat[$kunci])) {
            $r['status'] = 'lewati';
            $r['catatan'] = 'NIS ganda di CSV (baris ' . $terlihat[$kunci] . ').';
            continue;
        }
        $terlihat[$kunci] = $r['baris'];
        if ($r['username'] === '') {
            /* Kolom username kosong: buat otomatis dari nama dan pastikan
               unik terhadap DB maupun baris lain dalam berkas yang sama. */
            $r['username'] = username_siswa_unik(
                username_siswa_dari_nama((string) $r['nama']), 0, $terpakai
            );
            $r['catatan'] = 'Username dibuat otomatis: ' . $r['username'];
        } elseif (isset($usernameTerlihat[strtolower($r['username'])])) {
            $r['status'] = 'lewati';
            $r['catatan'] = 'Username ganda di CSV (baris ' . $usernameTerlihat[strtolower($r['username'])] . ').';
            continue;
        }
        $usernameTerlihat[strtolower($r['username'])] = $r['baris'];
        $terpakai[] = $r['username'];
        $sudahAda = db_satu('SELECT id FROM siswa WHERE nis = ?', [$r['nis']])
            ?? db_satu('SELECT id FROM pengguna WHERE username = ?', [$r['username']]);
        if ($sudahAda !== null) {
            $r['status'] = 'lewati';
            $r['catatan'] = 'NIS atau username sudah terdaftar.';
        }
    }
    unset($r);
}

/** Cari kelas berdasarkan nama; buat otomatis bila belum ada. */
function kelas_dari_csv(string $nama): int
{
    $ada = db_satu('SELECT id FROM kelas WHERE nama = ?', [$nama]);
    if ($ada !== null) {
        return (int) $ada['id'];
    }
    $bagian = preg_split('/\s+/', trim($nama), -1, PREG_SPLIT_NO_EMPTY);
    $tingkat = $bagian[0] ?? 'X';
    $jurusan = $bagian[1] ?? '-';
    return db_sisip('kelas', ['nama' => $nama, 'tingkat' => $tingkat, 'jurusan' => $jurusan, 'aktif' => 1]);
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_wajib();
    $langkah = post('langkah', '', 20);

    if ($langkah === 'pratinjau') {
        if (!isset($_FILES['berkas_csv']) || (int) ($_FILES['berkas_csv']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $pesanGalat = 'Pilih berkas CSV terlebih dahulu.';
        } elseif ((int) $_FILES['berkas_csv']['size'] > 2 * 1024 * 1024) {
            $pesanGalat = 'Berkas CSV maksimal 2 MB.';
        } elseif (!preg_match('/\.(csv|txt)$/i', (string) $_FILES['berkas_csv']['name'])) {
            $pesanGalat = 'Berkas harus berekstensi .csv atau .txt.';
        } else {
            try {
                $pratinjau = baca_csv_siswa((string) $_FILES['berkas_csv']['tmp_name']);
                tandai_duplikat($pratinjau);
                $_SESSION['impor_siswa_baris'] = $pratinjau;
                if ($pratinjau === []) {
                    $pesanGalat = 'CSV tidak berisi baris data.';
                }
            } catch (Throwable $galat) {
                $pesanGalat = $galat->getMessage();
            }
        }
    } elseif ($langkah === 'simpan') {
        $pratinjau = $_SESSION['impor_siswa_baris'] ?? [];
        if (!is_array($pratinjau) || $pratinjau === []) {
            $pesanGalat = 'Tidak ada data pratinjau. Unggah ulang berkas CSV.';
        } else {
            try {
                $hasil = db_transaksi(function () use ($pratinjau): array {
                    $masuk = 0;
                    $lewati = 0;
                    $kelasBaru = 0;
                    foreach ($pratinjau as $r) {
                        if ($r['status'] !== 'siap') {
                            $lewati++;
                            continue;
                        }
                        $username = (string) ($r['username'] ?? '');
                        if ($username === '') {
                            $username = username_siswa_dari_nama((string) $r['nama']);
                        }
                        if (db_satu('SELECT id FROM siswa WHERE nis = ?', [$r['nis']]) !== null
                            || db_satu('SELECT id FROM pengguna WHERE username = ?', [$username]) !== null) {
                            $lewati++;
                            continue;
                        }
                        $kelasSebelum = (int) db_nilai('SELECT COUNT(*) FROM kelas', [], 0);
                        $kelasId = kelas_dari_csv($r['kelas']);
                        if ((int) db_nilai('SELECT COUNT(*) FROM kelas', [], 0) > $kelasSebelum) {
                            $kelasBaru++;
                        }
                        $penggunaId = db_sisip('pengguna', [
                            'username' => $username,
                            'password_hash' => password_hash('Siswa@2026', PASSWORD_DEFAULT),
                            'nama' => $r['nama'],
                            'peran' => 'siswa',
                            'aktif' => 1,
                            'wajib_ganti_password' => 1,
                        ]);
                        db_sisip('siswa', [
                            'pengguna_id' => $penggunaId,
                            'nis' => $r['nis'],
                            'nama' => $r['nama'],
                            'jenis_kelamin' => $r['jenis_kelamin'],
                            'kelas_id' => $kelasId,
                            'qr_token' => acak_token(32),
                            'nama_wali' => $r['nama_wali'] !== '' ? $r['nama_wali'] : null,
                            'no_wa_wali' => $r['no_wa_wali'] !== '' ? $r['no_wa_wali'] : null,
                            'aktif' => 1,
                        ]);
                        $masuk++;
                    }
                    return ['masuk' => $masuk, 'lewati' => $lewati, 'kelas_baru' => $kelasBaru];
                });
                unset($_SESSION['impor_siswa_baris']);
                $pratinjau = [];
                log_akses('impor_siswa_csv', 'siswa');
                $ringkasan = $hasil;
                flash($hasil['masuk'] . ' siswa berhasil diimpor.');
            } catch (Throwable $galat) {
                $pesanGalat = 'Impor dibatalkan: ' . $galat->getMessage();
            }
        }
    }
}

halaman_atas('Impor siswa dari CSV', 'Admin / Data master', [
    'nav' => 'siswa',
    'aksi' => '<a class="tombol tombol-putih" href="' . e(url('contoh/siswa_contoh.csv')) . '">' . ikon('unduh', 20) . ' Contoh CSV</a>',
]);
?>
<?php if ($pesanGalat !== ''): ?><div class="pesan pesan-galat" role="alert"><?= ikon('peringatan', 20) ?><span><?= e($pesanGalat) ?></span></div><?php endif; ?>

<?php if ($ringkasan !== null): ?>
  <div class="pesan pesan-sukses" role="status"><?= ikon('centang', 20) ?><span>
    Selesai: <strong><?= e($ringkasan['masuk']) ?></strong> siswa masuk,
    <?= e($ringkasan['lewati']) ?> baris dilewati,
    <?= e($ringkasan['kelas_baru']) ?> kelas baru dibuat.
    Kata sandi awal siswa: <strong>Siswa@2026</strong> (wajib diganti saat login pertama).
    Username login tampil di kolom pratinjau; siswa <strong>tidak memakai NIS</strong> untuk masuk.
  </span></div>
<?php endif; ?>

<section class="kartu form-kartu">
  <p class="label-kecil">Unggah berkas</p>
  <h2>Data siswa lama</h2>
  <p class="teks-kecil">Kolom wajib: <strong>nis, nama, kelas</strong>. Kolom opsional: <strong>username</strong>, jenis_kelamin, nama_wali, no_wa_wali. Bila <code>username</code> kosong, sistem membuatnya otomatis dari nama (mis. <code>ahmad.rizky</code>) dan menambahkan angka bila bentrok. Username tidak boleh hanya angka seperti NIS. Pemisah koma atau titik koma dideteksi otomatis. Kelas yang belum ada dibuat otomatis.</p>
  <form method="post" action="<?= e(url('pages/admin/impor_siswa.php')) ?>" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <input type="hidden" name="langkah" value="pratinjau">
    <div class="form-baris">
      <label for="berkas_csv">Berkas CSV <span class="wajib">wajib</span></label>
      <input class="input" id="berkas_csv" name="berkas_csv" type="file" accept=".csv,text/csv,text/plain" required>
    </div>
    <div class="form-aksi"><button class="tombol tombol-utama" type="submit"><?= ikon('cari', 20) ?> Periksa isi CSV</button></div>
  </form>
</section>

<?php if ($pratinjau !== []): ?>
  <?= bagian_judul('Pratinjau', 'Periksa sebelum menyimpan. Baris bermasalah otomatis dilewati.') ?>
  <div class="tabel-bungkus">
    <table class="tabel">
      <thead><tr><th>Baris</th><th>NIS</th><th>Username</th><th>Nama</th><th>Kelas</th><th>L/P</th><th>Status</th></tr></thead>
      <tbody>
      <?php foreach ($pratinjau as $r): ?>
        <tr>
          <td class="mono"><?= e($r['baris']) ?></td>
          <td class="mono"><?= e($r['nis']) ?></td>
          <td class="mono"><?= e($r['username'] ?? '') ?></td>
          <td><?= e($r['nama']) ?></td>
          <td><?= e($r['kelas']) ?></td>
          <td><?= e($r['jenis_kelamin']) ?></td>
          <td><span class="badge <?= $r['status'] === 'siap' ? 'badge-toska' : ($r['status'] === 'galat' ? 'badge-koral' : 'badge-kuning') ?>"><?= e($r['status']) ?></span>
            <?php if ($r['catatan'] !== ''): ?><br><span class="teks-kecil"><?= e($r['catatan']) ?></span><?php endif; ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <form method="post" action="<?= e(url('pages/admin/impor_siswa.php')) ?>" style="margin-top:14px">
    <?= csrf_field() ?>
    <input type="hidden" name="langkah" value="simpan">
    <button class="tombol tombol-utama" type="submit" data-konfirmasi="Simpan semua baris bertstatus siap ke database?"><?= ikon('simpan', 20) ?> Simpan hasil impor</button>
  </form>
<?php endif; ?>
<?php halaman_bawah(); ?>
