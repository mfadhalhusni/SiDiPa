<?php
declare(strict_types=1);

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

/** Susun header Set-Cookie dengan atribut yang tidak didukung PHP 8.3. */
function auth_header_cookie(bool $partisi): string
{
    $p = session_get_cookie_params();
    $bagian = [
        session_name() . '=' . session_id(),
        'Path=' . ($p['path'] ?: '/'),
        'SameSite=' . (string) ($p['samesite'] ?: 'Lax'),
    ];
    if (!empty($p['domain'])) {
        $bagian[] = 'Domain=' . $p['domain'];
    }
    if (!empty($p['secure'])) {
        $bagian[] = 'Secure';
    }
    if (!empty($p['httponly'])) {
        $bagian[] = 'HttpOnly';
    }
    if ($partisi) {
        $bagian[] = 'Partitioned';
    }
    return implode('; ', $bagian);
}

/** Terapkan ulang header cookie sesi setelah session_start/regenerate_id. */
function auth_pasang_cookie(): void
{
    if (headers_sent()) {
        return;
    }
    $partisi = auth_partisi();
    // Satu header saja agar tidak ada cookie ganda bernama SIDIPASES.
    header_remove('Set-Cookie');
    if ($partisi) {
        // Buang cookie lama yang tidak dipartisi, supaya browser tidak
        // mengirim dua nilai SIDIPASES sekaligus ke PHP.
        $p = session_get_cookie_params();
        header('Set-Cookie: ' . session_name() . '=; Expires=Thu, 01 Jan 1970 00:00:00 GMT; '
            . 'Max-Age=0; Path=' . ($p['path'] ?: '/') . '; SameSite=Lax', false);
    }
    header('Set-Cookie: ' . auth_header_cookie($partisi), false);
}

/** Mulai sesi dengan cookie HttpOnly + SameSite, secure otomatis saat HTTPS. */
function auth_mulai(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    session_name('SIDIPASES');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => jalur_cookie_sesi(),
        'domain' => '',
        'secure' => https_aktif(),
        'httponly' => true,
        'samesite' => auth_samesite(),
    ]);
    ini_set('session.use_strict_mode', '1');
    ini_set('session.gc_maxlifetime', '7200');
    session_start();
    auth_pasang_cookie();
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
        return ['ok' => false, 'pesan' => 'Nama pengguna dan kata sandi wajib diisi.'];
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
        return ['ok' => false, 'pesan' => 'Nama pengguna atau kata sandi salah.'];
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
    unset($_SESSION['csrf_token']);
    csrf_token();
    log_akses('login_berhasil', 'auth', (int) $pengguna['id'], (int) $pengguna['id']);
    return ['ok' => true, 'pengguna' => $pengguna];
}

function auth_keluar(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return;
    }
    $_SESSION = [];
    if (ini_get('session.use_cookies') && !headers_sent()) {
        $param = session_get_cookie_params();
        $dasar = session_name() . '=; Expires=Thu, 01 Jan 1970 00:00:00 GMT; Max-Age=0'
            . '; Path=' . ($param['path'] ?: '/')
            . '; HttpOnly; SameSite=' . (string) ($param['samesite'] ?: 'Lax');
        if (!empty($param['secure'])) {
            $dasar .= '; Secure';
        }
        header_remove('Set-Cookie');
        header('Set-Cookie: ' . $dasar, false);
        if (auth_partisi()) {
            header('Set-Cookie: ' . $dasar . '; Partitioned', false);
        }
    }
    session_destroy();
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
    log_akses('ganti_kata_sandi', 'pengguna', $id, $id);
    return ['ok' => true, 'pesan' => 'Kata sandi berhasil diperbarui.'];
}
