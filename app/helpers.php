<?php
declare(strict_types=1);

/** Escape semua output HTML. */
function e($nilai): string
{
    return htmlspecialchars((string) $nilai, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Basis URL aplikasi (mendukung pemasangan di subdirektori cPanel). */
function basis_url(): string
{
    static $basis = null;
    if ($basis !== null) {
        return $basis;
    }
    if (SIDIPA_BASE_URL !== '') {
        $basis = rtrim(SIDIPA_BASE_URL, '/');
        return $basis;
    }
    if (PHP_SAPI === 'cli') {
        $basis = '';
        return $basis;
    }
    $skrip = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php'));
    $penanda = ['/pages/', '/api/', '/cron/', '/public/', '/install.php', '/verifikasi.php'];
    $pos = false;
    foreach ($penanda as $cari) {
        $ketemu = strpos($skrip, $cari);
        if ($ketemu !== false) {
            $pos = $ketemu;
            break;
        }
    }
    if ($pos === false) {
        $pos = strrpos($skrip, '/');
    }
    $basis = rtrim(substr($skrip, 0, (int) $pos), '/');
    return $basis;
}

/**
 * Path cookie sesi. basis_url() boleh berupa path ('/sidipa') maupun URL
 * penuh ('https://domain/sidipa'); cookie Path HARUS berupa path diawali '/'.
 * Mengirim URL penuh ke session_set_cookie_params membuat browser menolak
 * cookie, dan akibatnya token CSRF selalu gagal diverifikasi.
 */
function jalur_cookie_sesi(): string
{
    $basis = basis_url();
    if ($basis === '') {
        return '/';
    }
    if (preg_match('#^https?://#i', $basis) === 1) {
        $bagian = parse_url($basis, PHP_URL_PATH);
        $basis = is_string($bagian) ? $bagian : '';
    }
    $basis = '/' . trim((string) $basis, '/');
    return $basis === '/' ? '/' : rtrim($basis, '/');
}

/** URL relatif terhadap root aplikasi. */
function url(string $jalur = ''): string
{
    $basis = basis_url();
    return ($basis !== '' ? $basis : '') . '/' . ltrim($jalur, '/');
}

/** URL berkas statis di dalam public/. */
function asset(string $jalur = ''): string
{
    return url('public/' . ltrim($jalur, '/'));
}

function alihkan(string $tujuan): void
{
    $tujuan = preg_match('#^https?://#i', $tujuan) ? $tujuan : url($tujuan);
    header('Location: ' . $tujuan, true, 302);
    exit;
}

function json_keluar(array $data, int $kode = 200): void
{
    http_response_code($kode);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function wajib_metode(string $metode = 'POST'): void
{
    if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? '')) !== strtoupper($metode)) {
        json_keluar(['ok' => false, 'pesan' => 'Metode permintaan tidak diizinkan.'], 405);
    }
}

function bilangan($nilai, int $default = 0): int
{
    if (is_array($nilai) || is_object($nilai)) {
        return $default;
    }
    return filter_var($nilai, FILTER_VALIDATE_INT) === false ? $default : (int) $nilai;
}

function teks($nilai, int $maks = 255): string
{
    $hasil = trim((string) (is_scalar($nilai) ? $nilai : ''));
    if (function_exists('mb_substr')) {
        return mb_substr($hasil, 0, $maks, 'UTF-8');
    }
    return substr($hasil, 0, $maks);
}

function acak_token(int $panjang = 32): string
{
    return bin2hex(random_bytes(max(8, intdiv($panjang, 2))));
}

function alamat_ip(): string
{
    return teks($_SERVER['REMOTE_ADDR'] ?? '', 45);
}

/** Tulis galat ke berkas di LUAR folder publik. */
function catat_kesalahan(string $pesan): void
{
    $dir = dirname(SIDIPA_LOG);
    if (!is_dir($dir)) {
        @mkdir($dir, 0750, true);
    }
    $baris = '[' . date('Y-m-d H:i:s') . '] ' . str_replace(["\r", "\n"], ' ', $pesan) . PHP_EOL;
    @error_log($baris, 3, SIDIPA_LOG);
    if (SIDIPA_DEBUG) {
        error_log($baris);
    }
}

/** Catat jejak akses. Tidak pernah menggagalkan halaman bila tabel belum ada. */
function log_akses(string $aksi, ?string $objek = null, ?int $objek_id = null, ?int $pengguna_id = null): void
{
    try {
        $uid = $pengguna_id ?? ($_SESSION['pengguna_id'] ?? null);
        db_jalankan(
            'INSERT INTO log_akses (pengguna_id, aksi, objek, objek_id, ip, waktu) VALUES (?, ?, ?, ?, ?, NOW())',
            [$uid !== null ? (int) $uid : null, teks($aksi, 60), $objek !== null ? teks($objek, 80) : null, $objek_id, alamat_ip()]
        );
    } catch (Throwable $galat) {
        catat_kesalahan('log_akses gagal: ' . $galat->getMessage());
    }
}

function flash(string $pesan, string $jenis = 'sukses'): void
{
    $_SESSION['flash'][] = ['pesan' => $pesan, 'jenis' => $jenis];
}

function ambil_flash(): array
{
    $daftar = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return is_array($daftar) ? $daftar : [];
}

/** Baca pengaturan dari tabel (bukan konstanta), dengan cache per permintaan. */
function pengaturan(string $kunci, $default = '')
{
    if (!isset($GLOBALS['sidipa_pengaturan_cache'])) {
        $cache = [];
        try {
            foreach (db_semua('SELECT kunci, nilai FROM pengaturan') as $baris) {
                $cache[$baris['kunci']] = $baris['nilai'];
            }
        } catch (Throwable $galat) {
            catat_kesalahan('pengaturan gagal dimuat: ' . $galat->getMessage());
        }
        $GLOBALS['sidipa_pengaturan_cache'] = $cache;
    }
    $cache = $GLOBALS['sidipa_pengaturan_cache'];
    return array_key_exists($kunci, $cache) && $cache[$kunci] !== null ? (string) $cache[$kunci] : $default;
}

function atur_pengaturan(string $kunci, string $nilai, string $grup = 'umum'): void
{
    db_jalankan(
        'INSERT INTO pengaturan (kunci, nilai, grup) VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE nilai = VALUES(nilai), grup = VALUES(grup)',
        [teks($kunci, 80), $nilai, teks($grup, 40)]
    );
}

function reset_cache_pengaturan(): void
{
    unset($GLOBALS['sidipa_pengaturan_cache']);
}

const NAMA_HARI = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
const NAMA_BULAN = [
    1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
    'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
];
const NAMA_BULAN_PENDEK = [
    1 => 'JAN', 'FEB', 'MAR', 'APR', 'MEI', 'JUN',
    'JUL', 'AGU', 'SEP', 'OKT', 'NOV', 'DES'
];

/** Contoh: "Selasa, 3 November 2026". */
function tanggal_indonesia($tanggal, bool $dengan_hari = true): string
{
    if (!$tanggal) {
        return '-';
    }
    $waktu = is_numeric($tanggal) ? (int) $tanggal : strtotime((string) $tanggal);
    if ($waktu === false) {
        return '-';
    }
    $hasil = (int) date('j', $waktu) . ' ' . NAMA_BULAN[(int) date('n', $waktu)] . ' ' . date('Y', $waktu);
    return $dengan_hari ? NAMA_HARI[(int) date('w', $waktu)] . ', ' . $hasil : $hasil;
}

/** Stiker tanggal beranda guru: "SELASA, 3 NOV 2026". */
function stiker_tanggal_hari_ini(): string
{
    $waktu = time();
    return mb_strtoupper(NAMA_HARI[(int) date('w', $waktu)], 'UTF-8')
        . ', ' . (int) date('j', $waktu) . ' '
        . NAMA_BULAN_PENDEK[(int) date('n', $waktu)] . ' ' . date('Y', $waktu);
}

function format_jam($jam): string
{
    if (!$jam) {
        return '-';
    }
    $waktu = strtotime((string) $jam);
    return $waktu === false ? '-' : date('H.i', $waktu);
}

/** Normalisasi nomor WhatsApp ke format 62xxxxxxxxxx. */
function normalisasi_nomor_wa(string $nomor): string
{
    $nomor = preg_replace('/[^0-9+]/', '', trim($nomor));
    $nomor = preg_replace('/^\+/', '', $nomor);
    if ($nomor === '') {
        return '';
    }
    if (str_starts_with($nomor, '0')) {
        $nomor = '62' . substr($nomor, 1);
    } elseif (str_starts_with($nomor, '8')) {
        $nomor = '62' . $nomor;
    } elseif (!str_starts_with($nomor, '62')) {
        $nomor = '62' . ltrim($nomor, '0');
    }
    return $nomor;
}



/**
 * Unggah foto profil akun. Mengembalikan path relatif `public/uploads/...`.
 * Validasi MIME memakai getimagesize() pada berkas sementara agar konten
 * berbahaya yang berganti ekstensi tetap ditolak.
 */
/**
 * Baca path foto profil. Aman bila instalasi lama belum menjalankan
 * ALTER TABLE: cukup kembali ke inisial, pengguna tidak terkunci.
 */
function foto_pengguna(int $penggunaId): string
{
    if ($penggunaId <= 0) {
        return '';
    }
    try {
        return (string) (db_nilai(
            'SELECT foto FROM pengguna WHERE id = ? LIMIT 1', [$penggunaId], ''
        ) ?? '');
    } catch (Throwable $galat) {
        return '';
    }
}

function unggah_foto_profil(array $berkas): array
{
    if (empty($berkas) || (int) ($berkas['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return ['ok' => false, 'path' => null, 'pesan' => 'Pilih berkas foto terlebih dahulu.'];
    }
    if ((int) ($berkas['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'path' => null, 'pesan' => 'Foto gagal diunggah. Silakan coba lagi.'];
    }
    $maks = (int) pengaturan('maks_ukuran_foto', (string) SIDIPA_MAKS_BUKTI);
    $ukuran = (int) ($berkas['size'] ?? 0);
    if ($ukuran < 1 || $ukuran > $maks) {
        return ['ok' => false, 'path' => null, 'pesan' => 'Ukuran foto maksimal '
            . round($maks / 1048576, 1) . ' MB.'];
    }
    $info = @getimagesize((string) ($berkas['tmp_name'] ?? ''));
    $mime = is_array($info) ? (string) ($info['mime'] ?? '') : '';
    $izin = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!isset($izin[$mime])) {
        return ['ok' => false, 'path' => null, 'pesan' => 'Foto harus berupa JPG, PNG, atau WEBP.'];
    }
    if (!is_dir(SIDIPA_DIR_UPLOAD) && !@mkdir(SIDIPA_DIR_UPLOAD, 0755, true)) {
        return ['ok' => false, 'path' => null, 'pesan' => 'Folder unggahan tidak dapat disiapkan.'];
    }
    $nama = 'foto-' . date('Ymd') . '-' . bin2hex(random_bytes(10)) . '.' . $izin[$mime];
    $tujuan = rtrim(SIDIPA_DIR_UPLOAD, '/') . '/' . $nama;
    if (!@move_uploaded_file((string) $berkas['tmp_name'], $tujuan)) {
        return ['ok' => false, 'path' => null, 'pesan' => 'Foto gagal disimpan.'];
    }
    @chmod($tujuan, 0644);
    return ['ok' => true, 'path' => 'public/uploads/' . $nama, 'pesan' => ''];
}

/** Hapus berkas foto lama, hanya bila namanya sesuai pola yang kita buat. */
function hapus_berkas_foto(?string $path): void
{
    if ($path === null || $path === '') {
        return;
    }
    $nama = basename($path);
    if (preg_match('/^foto-[0-9]{8}-[a-f0-9]{20}\.(jpg|png|webp)$/', $nama) !== 1) {
        return;
    }
    $berkas = rtrim(SIDIPA_DIR_UPLOAD, '/') . '/' . $nama;
    if (is_file($berkas)) {
        @unlink($berkas);
    }
}

function inisial(string $nama): string
{
    $bagian = preg_split('/\s+/', trim($nama), -1, PREG_SPLIT_NO_EMPTY);
    if (!$bagian) {
        return 'S';
    }
    $awal = mb_substr($bagian[0], 0, 1, 'UTF-8');
    $akhir = count($bagian) > 1 ? mb_substr($bagian[count($bagian) - 1], 0, 1, 'UTF-8') : '';
    return mb_strtoupper($awal . $akhir, 'UTF-8');
}

/** Batasi akses langsung ke folder tertentu lewat HTTP. */
function https_aktif(): bool
{
    return (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
        || (($_SERVER['SERVER_PORT'] ?? '') === '443');
}

/** Pembantu pembacaan formulir POST dengan sanitasi. */
function post(string $kunci, string $default = '', int $maks = 255): string
{
    return isset($_POST[$kunci]) ? teks($_POST[$kunci], $maks) : $default;
}

function post_int(string $kunci, int $default = 0): int
{
    return isset($_POST[$kunci]) ? bilangan($_POST[$kunci], $default) : $default;
}

function post_bool(string $kunci): bool
{
    return isset($_POST[$kunci]) && (string) $_POST[$kunci] !== '0';
}

function aksi_post(): string
{
    return post('aksi', '', 40);
}

function daftar_kelas(bool $aktifSaja = true): array
{
    $sql = 'SELECT id, nama, tingkat, jurusan FROM kelas' . ($aktifSaja ? ' WHERE aktif = 1' : '') . ' ORDER BY nama';
    return db_semua($sql);
}

function kelas_diampu(int $guruId): array
{
    return db_semua(
        'SELECT k.id, k.nama FROM guru_kelas gk JOIN kelas k ON k.id = gk.kelas_id WHERE gk.guru_id = ? ORDER BY k.nama',
        [$guruId]
    );
}

/** Siswa milik akun yang sedang login (untuk peran siswa/wali). */
function siswa_akun(int $penggunaId, string $peran): ?array
{
    if ($peran === 'siswa') {
        return db_satu('SELECT s.*, k.nama AS kelas FROM siswa s LEFT JOIN kelas k ON k.id = s.kelas_id WHERE s.pengguna_id = ? LIMIT 1', [$penggunaId]);
    }
    if ($peran === 'wali') {
        return db_satu('SELECT s.*, k.nama AS kelas FROM siswa s LEFT JOIN kelas k ON k.id = s.kelas_id WHERE s.wali_pengguna_id = ? LIMIT 1', [$penggunaId]);
    }
    return null;
}

function notifikasi_kirim(int $penggunaId, string $judul, string $pesan, ?string $tautan = null): void
{
    db_sisip('notifikasi', [
        'pengguna_id' => $penggunaId,
        'judul' => teks($judul, 140),
        'pesan' => teks($pesan, 2000),
        'tautan' => $tautan !== null ? teks($tautan, 255) : null,
        'dibaca' => 0,
    ]);
}

/** Daftar ID kelas yang boleh dibaca guru; kosong = belum ada penugasan. */
function id_kelas_guru(int $guruId): array
{
    return array_map('intval', array_column(kelas_diampu($guruId), 'id'));
}

/**
 * KEBIJAKAN SEKOLAH — guru boleh menilai seluruh siswa aktif tanpa perlu
 * ditetapkan sebagai guru pengampu maupun wali kelas. Semua halaman guru
 * memakai fungsi ini; bila sekolah ingin membatasi lagi, ubah cukup di sini.
 */
function daftar_siswa_untuk_guru(int $batas = 300): array
{
    $batas = max(1, min(1000, $batas));
    return db_semua(
        'SELECT s.id, s.nis, s.nama, COALESCE(k.nama, \'-\') AS kelas
         FROM siswa s LEFT JOIN kelas k ON k.id = s.kelas_id
         WHERE s.aktif = 1 ORDER BY k.nama, s.nama LIMIT ' . $batas
    );
}

/** Validasi server: siswa harus ada dan aktif (tidak lagi dibatasi kelas). */
function guru_boleh_siswa(int $siswaId): bool
{
    return $siswaId > 0
        && (int) db_nilai('SELECT COUNT(*) FROM siswa WHERE id = ? AND aktif = 1', [$siswaId], 0) > 0;
}

/** Semua kelas aktif, dipakai rekap/pembinaan agar guru melihat seluruh siswa. */
function id_kelas_semua(): array
{
    return array_map('intval', array_column(
        db_semua('SELECT id FROM kelas WHERE aktif = 1 ORDER BY nama'), 'id'
    ));
}

/** Unggah bukti opsional: jpg/png/webp, ukuran dibatasi pengaturan, nama acak. */
function unggah_bukti(array $berkas): array
{
    if (empty($berkas) || (int) ($berkas['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return ['ok' => true, 'path' => null, 'pesan' => ''];
    }
    if ((int) ($berkas['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'path' => null, 'pesan' => 'Berkas bukti gagal diunggah.'];
    }
    $maks = (int) pengaturan('maks_ukuran_bukti', (string) SIDIPA_MAKS_BUKTI);
    if ((int) ($berkas['size'] ?? 0) > $maks || (int) ($berkas['size'] ?? 0) < 1) {
        return ['ok' => false, 'path' => null, 'pesan' => 'Ukuran bukti melebihi batas ' . round($maks / 1048576, 1) . ' MB.'];
    }
    $info = @getimagesize((string) ($berkas['tmp_name'] ?? ''));
    $mime = is_array($info) ? (string) ($info['mime'] ?? '') : '';
    $izin = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!isset($izin[$mime])) {
        return ['ok' => false, 'path' => null, 'pesan' => 'Bukti harus berupa gambar JPG, PNG, atau WEBP.'];
    }
    if (!is_dir(SIDIPA_DIR_UPLOAD) && !@mkdir(SIDIPA_DIR_UPLOAD, 0755, true)) {
        return ['ok' => false, 'path' => null, 'pesan' => 'Folder unggahan tidak dapat disiapkan.'];
    }
    $nama = 'bukti-' . date('Ymd') . '-' . bin2hex(random_bytes(10)) . '.' . $izin[$mime];
    $tujuan = rtrim(SIDIPA_DIR_UPLOAD, '/') . '/' . $nama;
    if (!@move_uploaded_file((string) $berkas['tmp_name'], $tujuan)) {
        return ['ok' => false, 'path' => null, 'pesan' => 'Bukti gagal disimpan.'];
    }
    @chmod($tujuan, 0644);
    return ['ok' => true, 'path' => 'public/uploads/' . $nama, 'pesan' => ''];
}

/** Validasi tanggal YYYY-MM-DD dari input pengguna. */
function tanggal_valid(string $nilai): bool
{
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $nilai)) {
        return false;
    }
    [$t, $b, $h] = array_map('intval', explode('-', $nilai));
    return checkdate($b, $h, $t);
}

/* --------------------------------------------------------------------------
 | USERNAME SISWA (login & QR memakai username, bukan NIS/NISN)
 | --------------------------------------------------------------------------
 | `pengguna.username` tetap satu-satunya sumber kebenaran untuk login dan
 | ISI QR KARTU. Untuk akun siswa nilainya terpisah dari `siswa.nis`: admin
 | dapat mengisinya sendiri (form Data siswa / kolom `username` pada CSV)
 | dengan KARAKTER BEBAS — angka semua, huruf semua, huruf besar, spasi,
 | maupun simbol — dan bila dikosongkan sistem membuatnya otomatis dari dua
 | kata pertama nama. NIS tetap dipakai untuk identitas, pencarian manual,
 | dan kode sertifikat.
 | Pembatasan yang tersisa murni teknis: karakter kontrol dibuang, spasi di
 | pinggir dipangkas, dan panjang dibatasi 60 karakter sesuai kolom DB.
 */

/** Rapikan username: buang karakter kontrol, pangkas pinggir, batasi 60. */
function username_siswa_bersih(string $nilai): string
{
    $nilai = (string) preg_replace('/[\x00-\x1F\x7F]/', '', $nilai);
    $nilai = trim($nilai);
    if (function_exists('mb_substr')) {
        return mb_substr($nilai, 0, 60, 'UTF-8');
    }
    return substr($nilai, 0, 60);
}

/** Panjang username dalam karakter (bukan byte), aman untuk karakter non-ASCII. */
function username_siswa_panjang(string $nilai): int
{
    return function_exists('mb_strlen') ? mb_strlen($nilai, 'UTF-8') : strlen($nilai);
}

/** Huruf kecil unicode untuk perbandingan unik yang peka huruf besar/kecil. */
function username_siswa_kunci(string $nilai): string
{
    return function_exists('mb_strtolower')
        ? mb_strtolower($nilai, 'UTF-8')
        : strtolower($nilai);
}

/**
 * Pesan galat bila username siswa tidak sah; string kosong berarti sah.
 * Tidak ada lagi larangan "harus ada huruf", "huruf kecil", atau minimal
 * 3 karakter: angka semua maupun huruf semua sama-sama diperbolehkan.
 */
function username_siswa_sah(string $nilai): string
{
    $bersih = username_siswa_bersih($nilai);
    if ($nilai !== '' && $bersih !== $nilai) {
        return 'Username mengandung karakter kontrol atau lebih dari 60 karakter.';
    }
    if ($bersih === '') {
        return 'Username siswa tidak boleh kosong.';
    }
    return '';
}

/** Buat calon username dari dua kata pertama nama, mis. "Alya Putri" -> alya.putri. */
function username_siswa_dari_nama(string $nama): string
{
    $nama = strtolower(trim($nama));
    $nama = (string) preg_replace('/[^a-z0-9\s]/', ' ', $nama);
    $bagian = preg_split('/\s+/', trim($nama), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    if ($bagian === []) {
        return 'siswa';
    }
    $dasar = implode('.', array_slice($bagian, 0, 2));
    return substr($dasar, 0, 52); // sisakan ruang untuk akhiran angka
}

/**
 * Pastikan username unik di tabel `pengguna` dan dalam satu batch impor.
 * Bentrokan diberi akhiran 2, 3, ... tanpa mengubah kata dasarnya.
 */
function username_siswa_unik(string $dasar, int $kecualiId = 0, array $terpakai = []): string
{
    $dasar = username_siswa_bersih($dasar);
    if ($dasar === '') {
        $dasar = 'siswa';
    }
    $dipakai = [];
    foreach ($terpakai as $nilai) {
        $dipakai[username_siswa_kunci((string) $nilai)] = true;
    }
    $kandidat = $dasar;
    for ($i = 1; $i <= 1000; $i++) {
        $ada = isset($dipakai[username_siswa_kunci($kandidat)]) || db_satu(
            'SELECT id FROM pengguna WHERE username = ? AND id <> ? LIMIT 1',
            [$kandidat, $kecualiId]
        ) !== null;
        if (!$ada) {
            return $kandidat;
        }
        $akhiran = (string) ($i + 1);
        $potong = 60 - strlen($akhiran);
        $pangkas = function_exists('mb_substr')
            ? mb_substr($dasar, 0, $potong, 'UTF-8')
            : substr($dasar, 0, $potong);
        $kandidat = $pangkas . $akhiran;
    }
    throw new RuntimeException('Username siswa tidak dapat dibuat unik. Hubungi admin.');
}
