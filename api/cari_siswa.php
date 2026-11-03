<?php
declare(strict_types=1);

/*
 | Endpoint JSON pencarian siswa (nama, NIS, atau token QR kartu).
 | KERAHASIAAN: hanya kolom id, nis, nama, kelas yang dikirim.
 | Tidak ada pencatat_id, qr_token, nomor WhatsApp, maupun data pribadi lain.
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
$q = teks($_GET['q'] ?? '', 80);
$token = teks($_GET['token'] ?? '', 64);
if ($q === '' && $token === '') {
    json_keluar(['ok' => true, 'siswa' => []]);
}

$syarat = ['s.aktif = 1'];
$param = [];

if ($peran === 'siswa' || $peran === 'wali') {
    $sendiri = siswa_akun((int) $pengguna['id'], $peran);
    if ($sendiri === null) {
        json_keluar(['ok' => true, 'siswa' => []]);
    }
    $syarat[] = 's.id = ?';
    $param[] = (int) $sendiri['id'];
} elseif ($peran === 'guru') {
    $idKelas = id_kelas_guru((int) $pengguna['id']);
    if ($idKelas === []) {
        json_keluar(['ok' => true, 'siswa' => [], 'pesan' => 'Anda belum ditetapkan sebagai guru pengampu kelas.']);
    }
    $syarat[] = 's.kelas_id IN (' . implode(', ', array_fill(0, count($idKelas), '?')) . ')';
    $param = array_merge($param, $idKelas);
} elseif (!in_array($peran, ['admin', 'bk', 'kepsek'], true)) {
    json_keluar(['ok' => false, 'pesan' => 'Peran tidak diizinkan.'], 403);
}

if ($token !== '') {
    $syarat[] = 's.qr_token = ?';
    $param[] = $token;
} else {
    $syarat[] = '(s.nama LIKE ? OR s.nis LIKE ?)';
    $param[] = '%' . $q . '%';
    $param[] = '%' . $q . '%';
}

$daftar = db_semua(
    'SELECT s.id, s.nis, s.nama, k.nama AS kelas
     FROM siswa s LEFT JOIN kelas k ON k.id = s.kelas_id
     WHERE ' . implode(' AND ', $syarat) . '
     ORDER BY s.nama LIMIT 20',
    $param
);

json_keluar([
    'ok' => true,
    'siswa' => array_map(static function (array $s): array {
        return [
            'id' => (int) $s['id'],
            'nis' => $s['nis'],
            'nama' => $s['nama'],
            'kelas' => $s['kelas'] ?? '-',
        ];
    }, $daftar),
]);
