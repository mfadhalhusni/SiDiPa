<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';

date_default_timezone_set(SIDIPA_ZONA_WAKTU);
if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
}
error_reporting(E_ALL);
ini_set('display_errors', SIDIPA_DEBUG ? '1' : '0');
ini_set('log_errors', '1');
ini_set('error_log', SIDIPA_LOG);

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/skor.php';
require_once __DIR__ . '/laporan.php';
require_once __DIR__ . '/xlsx.php';
require_once __DIR__ . '/sertifikat.php';
require_once __DIR__ . '/wa.php';
require_once __DIR__ . '/ikon.php';
require_once __DIR__ . '/layout.php';

set_exception_handler(function (Throwable $galat): void {
    catat_kesalahan('Galat tak tertangani: ' . $galat->getMessage() . ' @ ' . $galat->getFile() . ':' . $galat->getLine());
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, 'SiDiPa: ' . $galat->getMessage() . PHP_EOL);
        return;
    }
    if (apakah_api()) {
        json_keluar(['ok' => false, 'pesan' => 'Terjadi kesalahan pada server. Detail tersimpan di log.'], 500);
    }
    http_response_code(500);
    echo '<!doctype html><html lang="id"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<title>Galat - SiDiPa</title><link rel="stylesheet" href="' . e(asset('css/sidipa.css')) . '">'
        . '</head><body class="halaman-sederhana"><main class="kotak-sederhana">'
        . '<h1>Terjadi kesalahan</h1><p>Silakan coba lagi. Bila berulang, hubungi admin dan sertakan waktu kejadian.</p>'
        . '<a class="tombol" href="' . e(url('index.php')) . '">Kembali ke beranda</a></main></body></html>';
});

auth_mulai();
