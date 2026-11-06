<?php
declare(strict_types=1);

/*
 | Endpoint pemindaian QR / NIS. QR kartu berisi USERNAME SISWA (bukan NIS
 | dan bukan lagi qr_token); fallback manual tetap memakai NIS. Semua
 | keputusan memakai WAKTU SERVER (Asia/Jakarta), bukan waktu perangkat.
 | Duplikasi hari+jenis ditolak. Respons JSON tidak pernah memuat qr_token
 | maupun identitas pencatat.
 */

require_once __DIR__ . '/../app/bootstrap.php';

wajib_metode('POST');
csrf_wajib();

$pengguna = auth_pengguna();
if ($pengguna === null) {
    json_keluar(['ok' => false, 'pesan' => 'Sesi berakhir. Muat ulang halaman lalu masuk lagi.'], 401);
}
$peran = (string) $pengguna['peran'];
if (!in_array($peran, ['guru', 'admin'], true)) {
    log_akses('scan_ditolak', 'peran');
    json_keluar(['ok' => false, 'pesan' => 'Hanya guru atau admin yang dapat memindai.'], 403);
}
cek_input_semester('api');

$mode = post('mode', '', 20);
$modeValid = ['masuk' => 'Masuk', 'pulang' => 'Pulang', 'dhuha' => 'Sholat Dhuha', 'dhuhur' => 'Sholat Dhuhur'];
if (!isset($modeValid[$mode])) {
    json_keluar(['ok' => false, 'pesan' => 'Mode pemindaian tidak dikenal.'], 422);
}
$token = post('token', '', 100);
$nis = post('nis', '', 30);

$syarat = ['s.aktif = 1', "p.peran = 'siswa'"];
$param = [];
if ($token !== '') {
    /* QR terbaru berisi username siswa. Token acak lama sengaja tidak
       diterima: seluruh kartu wajib dicetak ulang (lihat README bagian 18). */
    $syarat[] = 'p.username = ?';
    $param[] = $token;
} elseif ($nis !== '') {
    $syarat[] = 's.nis = ?';
    $param[] = $nis;
} else {
    json_keluar(['ok' => false, 'pesan' => 'Pindai QR kartu siswa atau isi NIS/nama terlebih dahulu.'], 422);
}
$siswa = db_satu(
    'SELECT s.id, s.nis, s.nama, s.kelas_id, k.nama AS kelas, p.username
     FROM siswa s LEFT JOIN kelas k ON k.id = s.kelas_id
     JOIN pengguna p ON p.id = s.pengguna_id
     WHERE ' . implode(' AND ', $syarat) . ' LIMIT 1',
    $param
);
if ($siswa === null) {
    if ($token !== '' && (int) db_nilai('SELECT COUNT(*) FROM siswa WHERE qr_token = ?', [$token], 0) > 0) {
        json_keluar([
            'ok' => false,
            'pesan' => 'Kartu QR lama sudah tidak berlaku. Cetak ulang kartu siswa; QR sekarang memuat username siswa.',
        ], 409);
    }
    json_keluar(['ok' => false, 'pesan' => 'Siswa tidak ditemukan atau sudah tidak aktif.'], 404);
}

$tanggal = date('Y-m-d');
$jam = date('H:i:s');
$menit = (int) date('H') * 60 + (int) date('i');
$semester = semester_dari_tanggal($tanggal) ?? semester_aktif();
if ($semester === null) {
    json_keluar(['ok' => false, 'pesan' => 'Belum ada semester aktif untuk tanggal ini.'], 409);
}
$semesterId = (int) $semester['id'];
$siswaId = (int) $siswa['id'];
$izin = izin_pada_tanggal($siswaId, $tanggal);
$jenisIzin = (string) ($izin['jenis'] ?? '');

if ((int) db_nilai('SELECT COUNT(*) FROM hari_libur WHERE tanggal = ?', [$tanggal], 0) > 0) {
    json_keluar(['ok' => false, 'pesan' => 'Hari ini adalah hari libur; pemindaian tidak diproses.'], 409);
}

$menitDariJam = static function (string $nilai): int {
    $bagian = explode(':', $nilai);
    return ((int) ($bagian[0] ?? 0)) * 60 + (int) ($bagian[1] ?? 0);
};

$status = 'hadir';
$poinDiberikan = 0;
$pesan = '';

try {
    if ($mode === 'masuk' || $mode === 'pulang') {
        $ada = db_satu('SELECT id, jam_masuk, jam_pulang FROM kehadiran WHERE siswa_id = ? AND tanggal = ? LIMIT 1', [$siswaId, $tanggal]);
        if ($mode === 'masuk' && $ada !== null && $ada['jam_masuk'] !== null) {
            json_keluar(['ok' => false, 'pesan' => 'Siswa sudah scan masuk hari ini.'], 409);
        }
        if ($mode === 'pulang' && $ada !== null && $ada['jam_pulang'] !== null) {
            json_keluar(['ok' => false, 'pesan' => 'Siswa sudah scan pulang hari ini.'], 409);
        }

        $awal = 0;
        if ($mode === 'masuk') {
            $batasAwal = $menitDariJam((string) pengaturan('jam_bonus_pagi', '06:50'));
            $batasTelat = $menitDariJam((string) pengaturan('batas_terlambat', '07:00'));
            if (in_array($jenisIzin, ['izin', 'sakit'], true)) {
                $status = $jenisIzin;
                $pesan = 'Hadir tercatat sebagai ' . $jenisIzin . ' — tidak ada pemotongan poin.';
            } elseif ($menit < $batasAwal) {
                $status = 'hadir';
                $awal = 1;
                $pesan = 'Hadir awal sebelum ' . pengaturan('jam_bonus_pagi', '06:50') . '. Bonus dihitung mingguan.';
            } elseif ($menit <= $batasTelat) {
                $status = 'hadir';
                $pesan = 'Hadir tepat waktu.';
            } else {
                $status = 'terlambat';
                $poinDiberikan = (int) pengaturan('poin_terlambat', '-2');
                $pesan = 'Terlambat — ' . $poinDiberikan . ' poin Kedisiplinan.';
            }
        } else {
            $status = (string) ($ada['status'] ?? 'hadir');
            $awal = (int) ($ada['awal'] ?? 0);
            $pesan = 'Jam pulang tercatat.';
        }

        db_jalankan(
            'INSERT INTO kehadiran (semester_id, siswa_id, tanggal, jam_masuk, jam_pulang, status, awal, pemindai_id)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
               jam_masuk = COALESCE(VALUES(jam_masuk), jam_masuk),
               jam_pulang = COALESCE(VALUES(jam_pulang), jam_pulang),
               status = VALUES(status), awal = VALUES(awal), pemindai_id = VALUES(pemindai_id)',
            [
                $semesterId, $siswaId, $tanggal,
                $mode === 'masuk' ? $jam : null,
                $mode === 'pulang' ? $jam : null,
                $status, $awal, (int) $pengguna['id'],
            ]
        );

        if ($mode === 'masuk' && $status === 'terlambat') {
            catatan_sistem(
                'DIS_TERLAMBAT',
                $siswaId,
                $semesterId,
                $tanggal,
                'harian:terlambat:' . $siswaId . ':' . $tanggal,
                $poinDiberikan,
                'Terlambat masuk pukul ' . substr($jam, 0, 5)
            );
        }
        if ($mode === 'masuk' && in_array($jenisIzin, ['izin', 'sakit'], true)) {
            batalkan_catatan_otomatis_harian($siswaId, $tanggal);
        }
    } else {
        $ibadah = db_satu('SELECT id, nama, jam_mulai, jam_selesai FROM jenis_ibadah WHERE kode = ? AND aktif = 1 LIMIT 1', [$mode]);
        if ($ibadah === null) {
            json_keluar(['ok' => false, 'pesan' => 'Jenis ibadah belum dikonfigurasi.'], 409);
        }
        $mulai = $menitDariJam(substr((string) $ibadah['jam_mulai'], 0, 5));
        $selesai = $menitDariJam(substr((string) $ibadah['jam_selesai'], 0, 5));
        if ($menit < $mulai || $menit > $selesai) {
            json_keluar([
                'ok' => false,
                'pesan' => 'Di luar jendela ' . $ibadah['nama'] . ' (' . substr((string) $ibadah['jam_mulai'], 0, 5)
                    . '–' . substr((string) $ibadah['jam_selesai'], 0, 5) . ').',
            ], 409);
        }
        $sudah = db_satu(
            'SELECT id FROM kehadiran_ibadah WHERE siswa_id = ? AND jenis_ibadah_id = ? AND tanggal = ? LIMIT 1',
            [$siswaId, (int) $ibadah['id'], $tanggal]
        );
        if ($sudah !== null) {
            json_keluar(['ok' => false, 'pesan' => 'Sudah scan ' . $ibadah['nama'] . ' hari ini.'], 409);
        }
        $kehadiran = status_kehadiran_hari($siswaId, $tanggal);
        $statusHadir = (string) ($kehadiran['status'] ?? '');
        if (in_array($statusHadir, ['alpa', 'izin', 'sakit'], true)) {
            json_keluar(['ok' => false, 'pesan' => 'Siswa tidak hadir di sekolah; tidak dihitung sebagai tidak beribadah.'], 409);
        }
        $status = $jenisIzin === 'udzur' ? 'udzur' : 'hadir';
        $pesan = $status === 'udzur'
            ? 'Udzur tercatat — netral, tidak ada poin.'
            : 'Hadir ' . $ibadah['nama'] . ' tercatat.';
        db_sisip('kehadiran_ibadah', [
            'semester_id' => $semesterId,
            'siswa_id' => $siswaId,
            'jenis_ibadah_id' => (int) $ibadah['id'],
            'tanggal' => $tanggal,
            'jam_scan' => $jam,
            'status' => $status,
            'pemindai_id' => (int) $pengguna['id'],
        ]);
    }
} catch (Throwable $galat) {
    catat_kesalahan('scan: ' . $galat->getMessage());
    json_keluar(['ok' => false, 'pesan' => 'Gagal menyimpan pemindaian. Coba lagi.'], 500);
}

$_SESSION['scan_mode_terakhir'] = $mode;
log_akses('scan_' . $mode, 'siswa', $siswaId);

json_keluar([
    'ok' => true,
    'pesan' => $pesan,
    'mode' => $mode,
    'label' => $modeValid[$mode],
    'jam' => substr($jam, 0, 5),
    'tanggal' => $tanggal,
    'status' => $status,
    'poin' => $poinDiberikan,
    'siswa' => [
        'id' => $siswaId,
        'nis' => (string) $siswa['nis'],
        'username' => (string) $siswa['username'],
        'nama' => (string) $siswa['nama'],
        'kelas' => (string) ($siswa['kelas'] ?? '-'),
    ],
]);
