<?php
declare(strict_types=1);

/*
 | Endpoint JSON DAFTAR CATATAN POIN SISWA PADA SATU TANGGAL.
 | Dipakai panel "Catatan siswa pada tanggal ini" pada menu Catat poin dan
 | Catat banyak siswa: begitu guru memilih siswa + tanggal, daftar pelanggaran
 | yang SUDAH tercatat (termasuk yang ditulis guru lain) langsung tampil agar
 | tidak ada pencatatan ganda.
 |
 | Endpoint ini MURNI INFORMASI: tidak ada batas/plafon pengurangan poin.
 | KERAHASIAAN: tidak pernah mengirim pencatat_id, nama pencatat, nomor WA,
 | bukti foto, maupun keterangan bebas. Validasi CSRF + sesi + peran tetap.
 */

require_once __DIR__ . '/../app/bootstrap.php';

$pengguna = auth_pengguna();
if ($pengguna === null) {
    json_keluar(['ok' => false, 'pesan' => 'Sesi berakhir. Masuk kembali.'], 401);
}
if (!csrf_valid()) {
    json_keluar(['ok' => false, 'pesan' => 'Token keamanan tidak valid.'], 403);
}
wajib_metode('GET');

$peran = $pengguna['peran'];
if (!in_array($peran, ['admin', 'bk', 'kepsek', 'guru', 'siswa', 'wali'], true)) {
    json_keluar(['ok' => false, 'pesan' => 'Peran tidak diizinkan.'], 403);
}

$tanggal = teks($_GET['tanggal'] ?? '', 10);
if (!tanggal_valid($tanggal)) {
    json_keluar(['ok' => false, 'pesan' => 'Tanggal tidak valid (format YYYY-MM-DD).'], 422);
}

/* Satu atau banyak siswa dipisah koma; dibatasi 60 ID per permintaan. */
$mentah = teks($_GET['siswa_id'] ?? '', 500);
$ids = array_values(array_unique(array_filter(array_map('intval', explode(',', $mentah)))));
$ids = array_slice($ids, 0, 60);

/* Akun siswa/wali hanya boleh membaca catatan siswa yang terhubung dengannya. */
if ($peran === 'siswa' || $peran === 'wali') {
    $sendiri = siswa_akun((int) $pengguna['id'], $peran);
    $ids = $sendiri === null
        ? []
        : array_values(array_intersect($ids, [(int) $sendiri['id']]));
}

json_keluar([
    'ok' => true,
    'tanggal' => $tanggal,
    'siswa' => riwayat_harian_siswa($ids, $tanggal),
]);
