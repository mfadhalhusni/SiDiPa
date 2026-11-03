<?php
declare(strict_types=1);

/*
 | Cron harian SiDiPa (default pukul 15.00 waktu server).
 | - Menandai alpa bila tidak ada scan, izin, sakit, atau hari libur.
 | - Membuat catatan poin otomatis terlambat / alpa / tidak sholat.
 | - Memasukkan notifikasi WhatsApp ke ANTREAN (bukan kirim langsung).
 | AMAN DIJALANKAN DUA KALI: semua catatan memakai kunci_unik dan
 | kehadiran memakai INSERT ... ON DUPLICATE KEY UPDATE.
 */

require_once __DIR__ . '/../app/bootstrap.php';

function cron_harian(string $tanggal): array
{
    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $tanggal, $m) || !checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
        throw new InvalidArgumentException('Format tanggal tidak valid. Gunakan YYYY-MM-DD.');
    }
    if (db_satu('SELECT id FROM hari_libur WHERE tanggal = ?', [$tanggal]) !== null) {
        return ['ok' => true, 'tanggal' => $tanggal, 'pesan' => 'Hari libur, tidak ada proses.', 'rincian' => []];
    }
    $semester = semester_dari_tanggal($tanggal) ?? semester_aktif();
    if ($semester === null) {
        return ['ok' => false, 'tanggal' => $tanggal, 'pesan' => 'Tidak ada semester yang mencakup tanggal ini.', 'rincian' => []];
    }
    $semesterId = (int) $semester['id'];
    $hariIni = date('Y-m-d');
    $jamSekarang = date('H:i:s');
    $siswaList = db_semua('SELECT id, nama FROM siswa WHERE aktif = 1 ORDER BY id');
    $jenisIbadah = db_semua('SELECT id, kode, jam_mulai, jam_selesai FROM jenis_ibadah WHERE aktif = 1 ORDER BY id');

    $poinAlpa = (int) pengaturan('poin_alpa', '-5');
    $poinTerlambat = (int) pengaturan('poin_terlambat', '-2');
    $poinTidakSholat = (int) pengaturan('poin_tidak_sholat', '-3');

    $hasil = [
        'ok' => true, 'tanggal' => $tanggal, 'semester_id' => $semesterId,
        'siswa_diproses' => count($siswaList), 'alpa' => 0, 'terlambat' => 0,
        'izin_sakit' => 0, 'tidak_sholat' => 0, 'udzur_sholat' => 0, 'wa_masuk_antrian' => 0,
        'pesan' => '', 'rincian' => [],
    ];

    db_transaksi(function () use (
        &$hasil, $siswaList, $jenisIbadah, $semesterId, $tanggal, $hariIni, $jamSekarang,
        $poinAlpa, $poinTerlambat, $poinTidakSholat
    ): void {
        foreach ($siswaList as $siswa) {
            $sid = (int) $siswa['id'];
            $izin = izin_pada_tanggal($sid, $tanggal);

            if ($izin !== null) {
                $statusHadir = $izin['jenis'] === 'sakit' ? 'sakit' : 'izin';
                db_jalankan(
                    'INSERT INTO kehadiran (semester_id, siswa_id, tanggal, status, awal)
                     VALUES (?, ?, ?, ?, 0)
                     ON DUPLICATE KEY UPDATE status = VALUES(status), semester_id = VALUES(semester_id)',
                    [$semesterId, $sid, $tanggal, $statusHadir]
                );
                batalkan_catatan_otomatis_harian($sid, $tanggal);
                $hasil[$izin['jenis'] === 'udzur' ? 'udzur_sholat' : 'izin_sakit']++;

                if ($izin['jenis'] === 'udzur') {
                    foreach ($jenisIbadah as $jenis) {
                        db_jalankan(
                            "INSERT INTO kehadiran_ibadah (semester_id, siswa_id, jenis_ibadah_id, tanggal, status)
                             VALUES (?, ?, ?, ?, 'udzur')
                             ON DUPLICATE KEY UPDATE status = 'udzur'",
                            [$semesterId, $sid, (int) $jenis['id'], $tanggal]
                        );
                    }
                }
                continue;
            }

            $hadir = status_kehadiran_hari($sid, $tanggal);
            if ($hadir === null) {
                db_jalankan(
                    "INSERT INTO kehadiran (semester_id, siswa_id, tanggal, status, awal)
                     VALUES (?, ?, ?, 'alpa', 0)
                     ON DUPLICATE KEY UPDATE status = 'alpa', semester_id = VALUES(semester_id)",
                    [$semesterId, $sid, $tanggal]
                );
                $kunci = "harian:alpa:$semesterId:$sid:$tanggal";
                if (catatan_sistem('DIS_ALPA', $sid, $semesterId, $tanggal, $kunci, $poinAlpa)) {
                    $hasil['alpa']++;
                    if (wa_antri($sid, 'alpa', ['tanggal' => $tanggal])) {
                        $hasil['wa_masuk_antrian']++;
                    }
                }
                // Siswa tanpa kehadiran sekolah tidak dihitung tidak sholat.
                continue;
            }

            if ($hadir['status'] === 'terlambat') {
                $kunci = "harian:terlambat:$semesterId:$sid:$tanggal";
                if (catatan_sistem('DIS_TERLAMBAT', $sid, $semesterId, $tanggal, $kunci, $poinTerlambat, 'Scan masuk melewati batas terlambat.')) {
                    $hasil['terlambat']++;
                    if (wa_antri($sid, 'terlambat', ['tanggal' => $tanggal, 'jam' => $hadir['jam_masuk'] ?? '-'])) {
                        $hasil['wa_masuk_antrian']++;
                    }
                }
            }

            if (!in_array($hadir['status'], ['hadir', 'terlambat'], true)) {
                continue;
            }

            foreach ($jenisIbadah as $jenis) {
                $jendelaBerakhir = ($tanggal < $hariIni) || ($jamSekarang >= (string) $jenis['jam_selesai']);
                if (!$jendelaBerakhir) {
                    continue;
                }
                $udzur = db_satu(
                    "SELECT id FROM izin_siswa WHERE siswa_id = ? AND ? BETWEEN tgl_mulai AND tgl_selesai AND jenis = 'udzur' LIMIT 1",
                    [$sid, $tanggal]
                );
                $sudah = db_satu(
                    'SELECT id, status FROM kehadiran_ibadah WHERE siswa_id = ? AND jenis_ibadah_id = ? AND tanggal = ? LIMIT 1',
                    [$sid, (int) $jenis['id'], $tanggal]
                );
                if ($udzur !== null && ($sudah === null || $sudah['status'] !== 'hadir')) {
                    db_jalankan(
                        "INSERT INTO kehadiran_ibadah (semester_id, siswa_id, jenis_ibadah_id, tanggal, status)
                         VALUES (?, ?, ?, ?, 'udzur')
                         ON DUPLICATE KEY UPDATE status = 'udzur'",
                        [$semesterId, $sid, (int) $jenis['id'], $tanggal]
                    );
                    $hasil['udzur_sholat']++;
                    continue;
                }
                if ($sudah !== null) {
                    continue;
                }
                db_jalankan(
                    "INSERT INTO kehadiran_ibadah (semester_id, siswa_id, jenis_ibadah_id, tanggal, status)
                     VALUES (?, ?, ?, ?, 'tidak_hadir')",
                    [$semesterId, $sid, (int) $jenis['id'], $tanggal]
                );
                $kodeKatalog = $jenis['kode'] === 'dhuha' ? 'REL_DHUHA_TIDAK' : 'REL_DHUHUR_TIDAK';
                $kunci = "harian:ibadah:{$jenis['kode']}:$semesterId:$sid:$tanggal";
                if (catatan_sistem($kodeKatalog, $sid, $semesterId, $tanggal, $kunci, $poinTidakSholat)) {
                    $hasil['tidak_sholat']++;
                }
            }
        }
    });

    $hasil['pesan'] = 'Cron harian selesai: ' . $hasil['alpa'] . ' alpa, ' . $hasil['terlambat']
        . ' terlambat, ' . $hasil['tidak_sholat'] . ' tidak sholat, ' . $hasil['wa_masuk_antrian'] . ' pesan masuk antrian.';
    log_akses('cron_harian', 'cron', null);
    return $hasil;
}

// Menjalankan langsung dari baris perintah cPanel: php cron/harian.php --tanggal=2026-11-03
if (PHP_SAPI === 'cli' && isset($_SERVER['SCRIPT_FILENAME']) && realpath((string) $_SERVER['SCRIPT_FILENAME']) === realpath(__FILE__)) {
    $tanggal = date('Y-m-d');
    foreach (array_slice($argv ?? [], 1) as $arg) {
        if (str_starts_with((string) $arg, '--tanggal=')) {
            $tanggal = substr((string) $arg, 10);
        }
    }
    try {
        $hasil = cron_harian($tanggal);
        fwrite(STDOUT, $hasil['pesan'] . PHP_EOL);
        exit($hasil['ok'] ? 0 : 1);
    } catch (Throwable $galat) {
        catat_kesalahan('cron/harian.php: ' . $galat->getMessage());
        fwrite(STDERR, 'Cron harian gagal: ' . $galat->getMessage() . PHP_EOL);
        exit(1);
    }
}
