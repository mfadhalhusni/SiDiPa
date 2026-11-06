<?php
declare(strict_types=1);

/**
 * Otentikasi, sesi, dan "ingat saya".
 *
 * Kebijakan login: sekali masuk, cookie sesi DAN token "ingat saya" tersimpan
 * 30 hari di perangkat (HP/PC). Pengguna dapat menolak lewat centangan
 * "Jangan simpan login" pada form masuk. Token disimpan hanya sebagai hash
 * SHA-256 di tabel ingat_login dan diputar setiap kali dipakai ulang.
 */

const AUTH_COOKIE_INGAT = 'SIDIPAINGAT';

/** Lama penyimpanan login dalam detik. */
function auth_ingat_detik(): int
{
    return max(1, (int) SIDIPA_INGAT_HARI) * 86400;
}

/**
 * Aktif otomatis, kecuali pengguna menolak pada form login. Pilihan itu
 * disimpan di sesi supaya request berikutnya tidak mengubah cookie sesi
 * menjadi persisten lagi.
 */
function auth_ingat_aktif(): bool
{
    static $nilai = null;
    if ($nilai !== null) {
        return $nilai;
    }
    $skrip = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
    $postLogin = (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST')
        && str_contains($skrip, 'login.php');
    if ($postLogin) {
        $nilai = !isset($_POST['jangan_ingat']);
    } elseif (session_status() === PHP_SESSION_ACTIVE && array_key_exists('ingat_aktif', $_SESSION)) {
        $nilai = (bool) $_SESSION['ingat_aktif'];
    } else {
        $nilai = true;
    }
    return $nilai;
}

/**
 * SameSite yang dipakai. 'None' hanya sah bersama HTTPS; bila halaman
 * dibuka lewat HTTP (mis. curl uji lokal) otomatis turun ke 'Lax'.
 */
function auth_samesite(): string
{
    $nilai = ucfirst(strtolower((string) SIDIPA_SAMESITE));
    if (!in_array($nilai, ['Lax', 'None', 'Strict'], true)) {
        $nilai = 'Lax';
    }
    return ($nilai === 'None' && !https_aktif()) ? 'Lax' : $nilai;
}

/** Cookie dipartisi (CHIPS) agar tetap tersimpan saat aplikasi di-iframe. */
function auth_partisi(): bool
{
    return https_aktif() && SIDIPA_COOKIE_PARTISI && auth_samesite() === 'None';
}

/**
 * Susun nilai header Set-Cookie. Mendukung Max-Age/Expires (login persisten)
 * dan atribut Partitioned yang belum didukung session_set_cookie_params().
 * $umur > 0  = cookie persisten; $umur = 0 = cookie sesi (tanpa Max-Age);
 * $umur < 0  = hapus cookie (Max-Age=0 + Expires lampau).
 */
function auth_header_cookie(string $nama, string $nilai, int $umur, bool $partisi): string
{
    $bagian = [
        $nama . '=' . $nilai,
        'Path=' . jalur_cookie_sesi(),
    ];
    if ($umur > 0) {
        $bagian[] = 'Max-Age=' . $umur;
        $bagian[] = 'Expires=' . gmdate('D, d M Y H:i:s', time() + $umur) . ' GMT';
    } elseif ($umur < 0) {
        $bagian[] = 'Max-Age=0';
        $bagian[] = 'Expires=Thu, 01 Jan 1970 00:00:00 GMT';
    }
    $bagian[] = 'SameSite=' . auth_samesite();
    if (https_aktif()) {
        $bagian[] = 'Secure';
    }
    $bagian[] = 'HttpOnly';
    if ($partisi) {
        $bagian[] = 'Partitioned';
    }
    return implode('; ', $bagian);
}

/** Hapus cookie lama yang tidak dipartisi agar tidak ada nilai ganda. */
function auth_header_hapus_lama(string $nama): string
{
    return $nama . '=; Max-Age=0; Expires=Thu, 01 Jan 1970 00:00:00 GMT; Path='
        . jalur_cookie_sesi() . '; SameSite=Lax' . (https_aktif() ? '; Secure' : '');
}

/** Terapkan ulang header cookie sesi setelah session_start/regenerate_id. */
function auth_pasang_cookie(): void
{
    if (headers_sent()) {
        return;
    }
    $partisi = auth_partisi();
    $umur = auth_ingat_aktif() ? auth_ingat_detik() : 0;
    // Satu header sesi saja agar tidak ada cookie ganda bernama SIDIPASES.
    header_remove('Set-Cookie');
    if ($partisi) {
        header('Set-Cookie: ' . auth_header_hapus_lama(session_name()), false);
    }
    header('Set-Cookie: ' . auth_header_cookie(session_name(), session_id(), $umur, $partisi), false);
}

/** Mulai sesi dengan cookie persisten + HttpOnly + SameSite. */
function auth_mulai(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    // Nilai di bawah akan dioverride auth_pasang_cookie() SETELAH
    // session_start(), saat $_SESSION sudah bisa dibaca (pilihan "ingat saya").
    session_name('SIDIPASES');
    session_set_cookie_params([
        'lifetime' => auth_ingat_detik(),
        'path' => jalur_cookie_sesi(),
        'domain' => '',
        'secure' => https_aktif(),
        'httponly' => true,
        'samesite' => auth_samesite(),
    ]);
    ini_set('session.use_strict_mode', '1');
    ini_set('session.gc_maxlifetime', (string) max(7200, auth_ingat_detik()));
    session_start();
    // Ubah isi sesi pada setiap request agar berkas sesi tersegar walaupun
    // session.lazy_write aktif; tanpa ini GC dapat membuang sesi yang masih
    // dipakai, sehingga batas 30 hari tidak benar-benar bergulir.
    $_SESSION['waktu_aktif'] = time();
    auth_pasang_cookie();
}

/** Buat token "ingat saya" baru; $tokenLama diputar (dihapus) bila ada. */
function auth_ingat_simpan(int $penggunaId, ?string $tokenLama = null): void
{
    try {
        if ($tokenLama !== null) {
            db_jalankan('DELETE FROM ingat_login WHERE token_hash = ?', [hash('sha256', $tokenLama)]);
        }
        $token = bin2hex(random_bytes(32));
        // Waktu dihitung MySQL (bukan date() PHP) agar tidak bergantung pada
        // selisih zona waktu PHP vs koneksi database.
        db_jalankan(
            'INSERT INTO ingat_login (pengguna_id, token_hash, kedaluwarsa)
             VALUES (?, ?, DATE_ADD(NOW(), INTERVAL ? SECOND))',
            [$penggunaId, hash('sha256', $token), auth_ingat_detik()]
        );
        // Bersihkan token kedaluwarsa sesekali agar tabel tidak menumpuk.
        if (random_int(1, 20) === 1) {
            db_jalankan('DELETE FROM ingat_login WHERE kedaluwarsa < NOW()', []);
        }
    } catch (Throwable $galat) {
        catat_kesalahan('auth_ingat_simpan gagal: ' . $galat->getMessage());
        return;
    }
    if (headers_sent()) {
        return;
    }
    $partisi = auth_partisi();
    if ($partisi) {
        header('Set-Cookie: ' . auth_header_hapus_lama(AUTH_COOKIE_INGAT), false);
    }
    header('Set-Cookie: ' . auth_header_cookie(
        AUTH_COOKIE_INGAT, $token, auth_ingat_detik(), $partisi
    ), false);
}

/** Cabut semua token "ingat saya" milik satu akun (mis. setelah ganti sandi). */
function auth_ingat_cabut_semua(int $penggunaId): void
{
    try {
        db_jalankan('DELETE FROM ingat_login WHERE pengguna_id = ?', [$penggunaId]);
    } catch (Throwable $galat) {
        catat_kesalahan('auth_ingat_cabut_semua gagal: ' . $galat->getMessage());
    }
}

/** Hapus cookie "ingat saya" di browser + baris token terkait. */
function auth_ingat_hapus(): void
{
    $token = (string) ($_COOKIE[AUTH_COOKIE_INGAT] ?? '');
    if (preg_match('/^[a-f0-9]{64}$/', $token) === 1) {
        try {
            db_jalankan('DELETE FROM ingat_login WHERE token_hash = ?', [hash('sha256', $token)]);
        } catch (Throwable $galat) {
            catat_kesalahan('auth_ingat_hapus gagal: ' . $galat->getMessage());
        }
    }
    if (headers_sent()) {
        return;
    }
    header('Set-Cookie: ' . auth_header_cookie(AUTH_COOKIE_INGAT, '', -1, false), false);
    if (auth_partisi()) {
        header('Set-Cookie: ' . auth_header_cookie(AUTH_COOKIE_INGAT, '', -1, true), false);
    }
    unset($_COOKIE[AUTH_COOKIE_INGAT]);
}

/**
 * Pulihkan login dari cookie "ingat saya" bila sesi PHP sudah hilang.
 * Mengembalikan id pengguna atau null. Token selalu diputar.
 */
function auth_ingat_pulih(): ?int
{
    $token = (string) ($_COOKIE[AUTH_COOKIE_INGAT] ?? '');
    if (preg_match('/^[a-f0-9]{64}$/', $token) !== 1) {
        if ($token !== '') {
            auth_ingat_hapus();
        }
        return null;
    }
    try {
        $baris = db_satu(
            'SELECT t.id, t.pengguna_id, p.peran, p.aktif
             FROM ingat_login t JOIN pengguna p ON p.id = t.pengguna_id
             WHERE t.token_hash = ? AND t.kedaluwarsa > NOW() LIMIT 1',
            [hash('sha256', $token)]
        );
        if ($baris === null || (int) $baris['aktif'] !== 1) {
            auth_ingat_hapus();
            return null;
        }
        $id = (int) $baris['pengguna_id'];
        session_regenerate_id(true);
        auth_pasang_cookie();
        $_SESSION['pengguna_id'] = $id;
        $_SESSION['peran'] = (string) $baris['peran'];
        $_SESSION['waktu_masuk'] = time();
        $_SESSION['ingat_aktif'] = true;
        unset($_SESSION['csrf_token']);
        csrf_token();
        auth_ingat_simpan($id, $token);   // rotasi token
        log_akses('login_ingat', 'auth', $id, $id);
        return $id;
    } catch (Throwable $galat) {
        catat_kesalahan('auth_ingat_pulih gagal: ' . $galat->getMessage());
        return null;
    }
}

/**
 * SLIDING / IDLE TIMEOUT — geser batas "ingat saya" mengikuti aktivitas
 * terakhir. Setiap halaman yang dibuka saat pengguna sudah masuk menulis
 * ulang Max-Age cookie dan (paling sering sekali sehari) kedaluwarsa di DB.
 * Pengguna hanya diminta login lagi bila benar-benar tidak membuka aplikasi
 * selama SIDIPA_INGAT_HARI hari berturut-turut.
 *
 * Tidak menghidupkan kembali token yang sudah kedaluwarsa, dan tidak berlaku
 * bila pengguna memilih "Jangan simpan login di perangkat ini".
 */
function auth_ingat_perpanjang(): void
{
    if (!auth_ingat_aktif() || headers_sent()) {
        return;
    }
    $token = (string) ($_COOKIE[AUTH_COOKIE_INGAT] ?? '');
    if (preg_match('/^[a-f0-9]{64}$/', $token) !== 1) {
        return;
    }
    $id = (int) ($_SESSION['pengguna_id'] ?? 0);
    if ($id <= 0) {
        return;
    }
    $hash = hash('sha256', $token);
    $detik = auth_ingat_detik();
    try {
        // Token yang sudah lewat batas TIDAK boleh diperpanjang.
        $hidup = (int) db_nilai(
            'SELECT COUNT(*) FROM ingat_login
             WHERE token_hash = ? AND pengguna_id = ? AND kedaluwarsa > NOW()',
            [$hash, $id],
            0
        );
        if ($hidup === 0) {
            auth_ingat_hapus();
            return;
        }
        // Batasi penulisan DB: cukup sekali per jeda (1 hari untuk 30 hari).
        $jeda = max(3600, min(86400, intdiv($detik, 30)));
        db_jalankan(
            'UPDATE ingat_login SET kedaluwarsa = DATE_ADD(NOW(), INTERVAL ? SECOND)
             WHERE token_hash = ? AND pengguna_id = ?
               AND kedaluwarsa < DATE_ADD(NOW(), INTERVAL ? SECOND)',
            [$detik, $hash, $id, $detik - $jeda]
        );
    } catch (Throwable $galat) {
        catat_kesalahan('auth_ingat_perpanjang gagal: ' . $galat->getMessage());
        return;
    }
    // Header murah: cookie browser ikut bergeser pada setiap request.
    $partisi = auth_partisi();
    if ($partisi) {
        header('Set-Cookie: ' . auth_header_hapus_lama(AUTH_COOKIE_INGAT), false);
    }
    header('Set-Cookie: ' . auth_header_cookie(
        AUTH_COOKIE_INGAT, $token, $detik, $partisi
    ), false);
}

function auth_pengguna(): ?array
{
    static $pengguna = null;
    static $sudah = false;
    if ($sudah) {
        return $pengguna;
    }
    $sudah = true;
    $id = $_SESSION['pengguna_id'] ?? null;
    $dariSesi = $id !== null;
    if ($id === null) {
        // Sesi hilang (browser ditutup, server restart, dsb.) -> pulihkan
        // otomatis dari cookie "ingat saya" tanpa perlu login ulang.
        // Pemulihan sudah memberi token + batas 30 hari yang benar-benar baru.
        $id = auth_ingat_pulih();
    }
    if ($id === null) {
        return null;
    }
    try {
        $pengguna = db_satu(
            'SELECT id, username, nama, peran, aktif, wajib_ganti_password FROM pengguna WHERE id = ? LIMIT 1',
            [(int) $id]
        );
    } catch (Throwable $galat) {
        catat_kesalahan('auth_pengguna gagal: ' . $galat->getMessage());
        $pengguna = null;
    }
    if ($pengguna === null || (int) $pengguna['aktif'] !== 1) {
        auth_keluar();
        return null;
    }
    if ($dariSesi) {
        // Aktivitas terkonfirmasi -> hitung ulang 30 hari dari sekarang.
        auth_ingat_perpanjang();
    }
    return $pengguna;
}

function auth_peran(): string
{
    $p = auth_pengguna();
    return $p['peran'] ?? 'tamu';
}

function auth_boleh(array $daftarPeran): bool
{
    return in_array(auth_peran(), $daftarPeran, true);
}

/** Pembatasan percobaan login: 5 kegagalan / 15 menit per IP atau username. */
function auth_diblokir(string $username): bool
{
    try {
        $jumlah = (int) db_nilai(
            "SELECT COUNT(*) FROM log_akses
             WHERE aksi = 'login_gagal' AND waktu > DATE_SUB(NOW(), INTERVAL 15 MINUTE)
               AND (ip = ? OR objek = ?)",
            [alamat_ip(), teks($username, 60)],
            0
        );
        return $jumlah >= 5;
    } catch (Throwable $galat) {
        catat_kesalahan('auth_diblokir gagal: ' . $galat->getMessage());
        return false;
    }
}

function auth_masuk(string $username, string $kataSandi): array
{
    $username = teks($username, 60);
    if ($username === '' || $kataSandi === '') {
        return ['ok' => false, 'pesan' => 'Username dan kata sandi wajib diisi.'];
    }
    if (auth_diblokir($username)) {
        log_akses('login_diblokir', $username);
        return ['ok' => false, 'pesan' => 'Terlalu banyak percobaan gagal. Coba lagi dalam 15 menit.'];
    }
    try {
        $pengguna = db_satu('SELECT * FROM pengguna WHERE username = ? LIMIT 1', [$username]);
    } catch (Throwable $galat) {
        catat_kesalahan('login query gagal: ' . $galat->getMessage());
        return ['ok' => false, 'pesan' => 'Sistem belum siap. Pastikan database.sql sudah diimpor.'];
    }
    if ($pengguna === null || !password_verify($kataSandi, (string) $pengguna['password_hash'])) {
        log_akses('login_gagal', $username);
        /* Username siswa kini boleh angka semua, jadi angka bukan lagi
           indikasi "masih memakai NIS". Beri petunjuk netral bila gagal. */
        if ($pengguna === null && preg_match('/^\d+$/', $username)) {
            return ['ok' => false, 'pesan' => 'Username angka ini tidak terdaftar. Username siswa boleh angka maupun huruf sesuai yang dibuat admin; hubungi admin bila lupa.'];
        }
        return ['ok' => false, 'pesan' => 'Username atau kata sandi salah.'];
    }
    if ((int) $pengguna['aktif'] !== 1) {
        log_akses('login_nonaktif', $username, (int) $pengguna['id']);
        return ['ok' => false, 'pesan' => 'Akun ini dinonaktifkan. Hubungi admin.'];
    }

    session_regenerate_id(true);
    auth_pasang_cookie();
    $_SESSION['pengguna_id'] = (int) $pengguna['id'];
    $_SESSION['peran'] = $pengguna['peran'];
    $_SESSION['waktu_masuk'] = time();
    $ingat = auth_ingat_aktif();
    $_SESSION['ingat_aktif'] = $ingat;
    unset($_SESSION['csrf_token']);
    csrf_token();
    if ($ingat) {
        auth_ingat_simpan((int) $pengguna['id']);
    } else {
        auth_ingat_hapus();
    }
    log_akses('login_berhasil', 'auth', (int) $pengguna['id'], (int) $pengguna['id']);
    return ['ok' => true, 'pengguna' => $pengguna];
}

function auth_keluar(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        $_SESSION = [];
    }
    if (!headers_sent()) {
        // Header sesi diganti total, lalu cookie "ingat saya" dihapus SETELAH
        // header_remove agar perintah penghapusannya tidak ikut terbuang.
        $partisi = auth_partisi();
        header_remove('Set-Cookie');
        header('Set-Cookie: ' . auth_header_cookie(session_name(), '', -1, false), false);
        if ($partisi) {
            header('Set-Cookie: ' . auth_header_cookie(session_name(), '', -1, true), false);
        }
    }
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
    auth_ingat_hapus();
}

function auth_landing(string $peran): string
{
    return match ($peran) {
        'admin' => 'pages/admin/dashboard.php',
        'guru' => 'pages/guru/beranda.php',
        'siswa' => 'pages/siswa/beranda.php',
        'bk' => 'pages/bk/beranda.php',
        'kepsek' => 'pages/kepsek/beranda.php',
        'wali' => 'pages/wali/beranda.php',
        default => 'pages/umum/beranda.php',
    };
}

function auth_403(string $pesan = 'Anda tidak memiliki hak untuk membuka halaman ini.'): void
{
    http_response_code(403);
    echo '<!doctype html><html lang="id"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<title>Akses ditolak - SiDiPa</title>'
        . '<link rel="stylesheet" href="' . e(asset('css/sidipa.css')) . '"></head>'
        . '<body class="halaman-sederhana"><main class="kotak-sederhana">'
        . '<h1>Akses ditolak</h1><p>' . e($pesan) . '</p>'
        . '<a class="tombol" href="' . e(url('index.php')) . '">Kembali ke beranda</a>'
        . '</main></body></html>';
    exit;
}

/**
 * Pemeriksaan peran di SERVER pada setiap halaman dan endpoint.
 * $peran kosong = peran apa pun yang sudah login.
 */
function auth_wajib(array $peran = [], bool $abaikanGantiSandi = false): array
{
    $pengguna = auth_pengguna();
    if ($pengguna === null) {
        alihkan('pages/login.php');
    }
    if ($peran !== [] && !in_array($pengguna['peran'], $peran, true)) {
        log_akses('akses_ditolak', 'peran');
        auth_403();
    }
    if (!$abaikanGantiSandi && (int) $pengguna['wajib_ganti_password'] === 1) {
        $skrip = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
        if (!str_contains($skrip, 'ganti_password.php') && !str_contains($skrip, 'logout.php')) {
            alihkan('pages/ganti_password.php');
        }
    }
    return $pengguna;
}

function auth_sandi_valid(string $sandi): bool
{
    return mb_strlen($sandi, 'UTF-8') >= 8;
}

/** Ganti kata sandi dengan verifikasi kata sandi lama + jejak audit. */
function auth_ganti_sandi(int $id, string $lama, string $baru, string $ulang): array
{
    if (!auth_sandi_valid($baru)) {
        return ['ok' => false, 'pesan' => 'Kata sandi baru minimal 8 karakter.'];
    }
    if (!hash_equals($baru, $ulang)) {
        return ['ok' => false, 'pesan' => 'Ulangi kata sandi baru dengan tepat.'];
    }
    $pengguna = db_satu('SELECT id, password_hash FROM pengguna WHERE id = ? LIMIT 1', [$id]);
    if ($pengguna === null || !password_verify($lama, (string) $pengguna['password_hash'])) {
        return ['ok' => false, 'pesan' => 'Kata sandi lama tidak sesuai.'];
    }
    db_jalankan(
        'UPDATE pengguna SET password_hash = ?, wajib_ganti_password = 0 WHERE id = ?',
        [password_hash($baru, PASSWORD_DEFAULT), $id]
    );
    // Semua perangkat lain harus login ulang; perangkat ini dapat token baru.
    auth_ingat_cabut_semua($id);
    if (auth_ingat_aktif()) {
        auth_ingat_simpan($id);
    }
    log_akses('ganti_kata_sandi', 'pengguna', $id, $id);
    return ['ok' => true, 'pesan' => 'Kata sandi berhasil diperbarui.'];
}
