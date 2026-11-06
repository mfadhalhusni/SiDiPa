<?php
declare(strict_types=1);

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function csrf_meta(): string
{
    return '<meta name="csrf-token" content="' . e(csrf_token()) . '">';
}

function apakah_api(): bool
{
    $skrip = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
    return str_contains($skrip, '/api/')
        || str_contains(strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? '')), 'application/json')
        || (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest'
            && strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? '')) === 'POST');
}

function csrf_valid(): bool
{
    $dikirim = (string) ($_POST['csrf_token'] ?? '');
    if ($dikirim === '') {
        $dikirim = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    }
    if ($dikirim === '') {
        $dikirim = (string) ($_GET['csrf_token'] ?? '');
    }
    return $dikirim !== '' && hash_equals(csrf_token(), $dikirim);
}

/** Wajib dipanggil pada SETIAP permintaan POST, termasuk endpoint API. */
function csrf_wajib(): void
{
    if (csrf_valid()) {
        return;
    }
    log_akses('csrf_ditolak', 'keamanan');
    if (apakah_api()) {
        json_keluar(['ok' => false, 'pesan' => 'Token keamanan tidak valid. Muat ulang halaman lalu coba lagi.'], 403);
    }
    http_response_code(403);
    echo '<!doctype html><html lang="id"><head><meta charset="utf-8"><title>Akses ditolak</title>'
        . '<link rel="stylesheet" href="' . e(asset('css/sidipa.css')) . '"></head><body class="halaman-sederhana">'
        . '<main class="kotak-sederhana"><h1>Token keamanan tidak valid</h1>'
        . '<p>Sesi formulir sudah kedaluwarsa. Silakan muat ulang halaman dan ulangi.</p>'
        . '<a class="tombol" href="' . e(url('index.php')) . '">Kembali ke beranda</a></main></body></html>';
    exit;
}
