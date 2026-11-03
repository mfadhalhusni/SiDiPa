<?php
declare(strict_types=1);

/*
 | Cron mingguan SiDiPa.
 | - Minggu tanpa terlambat dan alpa: Kedisiplinan + poin_minggu_disiplin.
 | - Minggu penuh hadir sholat dhuha / dhuhur: Religius + poin.
 | - Bonus datang pagi: setiap N hari awal kumulatif memberi +poin_bonus_pagi,
 |   maksimal bonus_pagi_maks poin per semester.
 | Hari libur, Sabtu/Minggu, izin, sakit, dan udzur tidak dihitung ketidakhadiran.
 | Semua penghargaan memakai kunci_unik sehingga aman dijalankan berulang.
 */

require_once __DIR__ . '/../app/bootstrap.php';

function senin_dari_tanggal(string $tanggal): string
{
    $waktu = strtotime($tanggal);
    if ($waktu === false) {
        throw new InvalidArgumentException('Tanggal tidak valid.');
    }
    $mundur = (int) date('N', $waktu) - 1;
    return date('Y-m-d', strtotime('-' . $mundur . ' days', $waktu));
}

function cron_mingguan(string $tanggalAcuan): array
{
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggalAcuan) || strtotime($tanggalAcuan) === false) {
        throw new InvalidArgumentException('Format tanggal tidak valid. Gunakan YYYY-MM-DD.');
    }
    $senin = senin_dari_tanggal($tanggalAcuan);
    $minggu = date('Y-m-d', strtotime($senin . ' +6 days'));
    $semester = semester_dari_tanggal($senin) ?? semester_dari_tanggal($minggu) ?? semester_aktif();
    if ($semester === null) {
        return ['ok' => false, 'pesan' => 'Tidak ada semester untuk minggu ' . $senin . '.', 'rincian' => []];
    }
    $semesterId = (int) $semester['id'];

    // Hari sekolah efektif dalam minggu ini.
    $hariSekolah = [];
    for ($i = 0; $i < 7; $i++) {
        $hari = date('Y-m-d', strtotime($senin . " +$i days"));
        if ((int) date('N', strtotime($hari)) >= 6) {
            continue;
        }
        if ($hari < (string) $semester['tgl_mulai'] || $hari > (string) $semester['tgl_selesai']) {
            continue;
        }
        if ((int) db_nilai('SELECT COUNT(*) FROM hari_libur WHERE tanggal = ?', [$hari], 0) > 0) {
            continue;
        }
        $hariSekolah[] = $hari;
    }

    $poinDisiplin = (int) pengaturan('poin_minggu_disiplin', '2');
    $poinDhuha = (int) pengaturan('poin_minggu_dhuha', '2');
    $poinDhuhur = (int) pengaturan('poin_minggu_dhuhur', '2');
    $poinBonus = (int) pengaturan('poin_bonus_pagi', '1');
    $setiapHari = max(1, (int) pengaturan('bonus_pagi_setiap_hari', '5'));
    $maksBonus = max(0, (int) pengaturan('bonus_pagi_maks', '10'));
    $maksMilestone = $poinBonus > 0 ? intdiv($maksBonus, $poinBonus) : 0;

    $katalogBonus = katalog_kode('DIS_BONUS_PAGI');
    $idKatalogBonus = $katalogBonus !== null ? (int) $katalogBonus['id'] : 0;
    $jenisIbadah = db_semua('SELECT id, kode FROM jenis_ibadah WHERE aktif = 1 ORDER BY id');
    $siswaList = db_semua('SELECT id, nama FROM siswa WHERE aktif = 1 ORDER BY id');

    $hasil = [
        'ok' => true, 'senin' => $senin, 'minggu' => $minggu, 'semester_id' => $semesterId,
        'hari_sekolah' => count($hariSekolah), 'siswa_diproses' => count($siswaList),
        'disiplin' => 0, 'dhuha' => 0, 'dhuhur' => 0, 'bonus_pagi' => 0,
        'pesan' => '', 'rincian' => [],
    ];

    db_transaksi(function () use (
        &$hasil, $hariSekolah, $siswaList, $jenisIbadah, $semester, $semesterId,
        $senin, $minggu, $poinDisiplin, $poinDhuha, $poinDhuhur, $poinBonus,
        $setiapHari, $maksMilestone, $idKatalogBonus
    ): void {
        foreach ($siswaList as $siswa) {
            $sid = (int) $siswa['id'];
            $statusMinggu = [];
            foreach ($hariSekolah as $hari) {
                $izin = izin_pada_tanggal($sid, $hari);
                if ($izin !== null) {
                    $statusMinggu[$hari] = 'izin';
                    continue;
                }
                $hadir = status_kehadiran_hari($sid, $hari);
                $statusMinggu[$hari] = $hadir === null ? 'alpa' : (string) $hadir['status'];
            }

            $bersih = true;
            foreach ($statusMinggu as $status) {
                if (in_array($status, ['terlambat', 'alpa'], true)) {
                    $bersih = false;
                    break;
                }
            }
            if ($bersih && $hariSekolah !== []) {
                $kunci = "minggu:disiplin:$semesterId:$sid:$senin";
                if (catatan_sistem('DIS_MINGGU_BERSIH', $sid, $semesterId, $minggu, $kunci, $poinDisiplin, 'Minggu tanpa terlambat dan alpa.')) {
                    $hasil['disiplin']++;
                }
            }

            foreach ($jenisIbadah as $jenis) {
                $penuh = true;
                $adaHariWajib = false;
                foreach ($statusMinggu as $hari => $status) {
                    if (!in_array($status, ['hadir', 'terlambat'], true)) {
                        continue; // libur, izin, sakit, udzur, alpa tidak dihitung ketidakhadiran ibadah
                    }
                    $adaHariWajib = true;
                    $ibadah = db_satu(
                        'SELECT status FROM kehadiran_ibadah WHERE siswa_id = ? AND jenis_ibadah_id = ? AND tanggal = ? LIMIT 1',
                        [$sid, (int) $jenis['id'], $hari]
                    );
                    $statusIbadah = $ibadah['status'] ?? 'tidak_hadir';
                    if ($statusIbadah === 'udzur') {
                        continue;
                    }
                    if ($statusIbadah !== 'hadir') {
                        $penuh = false;
                        break;
                    }
                }
                if (!$penuh || !$adaHariWajib) {
                    continue;
                }
                $kodeKatalog = $jenis['kode'] === 'dhuha' ? 'REL_MINGGU_DHUHA' : 'REL_MINGGU_DHUHUR';
                $kunci = "minggu:{$jenis['kode']}:$semesterId:$sid:$senin";
                $poin = $jenis['kode'] === 'dhuha' ? $poinDhuha : $poinDhuhur;
                if (catatan_sistem($kodeKatalog, $sid, $semesterId, $minggu, $kunci, $poin, 'Minggu penuh hadir sholat ' . $jenis['kode'] . '.')) {
                    $hasil[$jenis['kode'] === 'dhuha' ? 'dhuha' : 'dhuhur']++;
                }
            }

            // Bonus datang pagi kumulatif sejak awal semester sampai akhir minggu ini.
            if ($idKatalogBonus > 0 && $maksMilestone > 0) {
                $jumlahAwal = (int) db_nilai(
                    "SELECT COUNT(*) FROM kehadiran k
                     WHERE k.siswa_id = ? AND k.tanggal BETWEEN ? AND ?
                       AND k.awal = 1 AND k.status NOT IN ('alpa', 'izin', 'sakit')
                       AND NOT EXISTS (SELECT 1 FROM hari_libur h WHERE h.tanggal = k.tanggal)",
                    [$sid, (string) $semester['tgl_mulai'], $minggu],
                    0
                );
                $milestone = min(intdiv($jumlahAwal, $setiapHari), $maksMilestone);
                $sudahDiberi = (int) db_nilai(
                    'SELECT COUNT(*) FROM catatan_poin WHERE siswa_id = ? AND semester_id = ? AND katalog_id = ?',
                    [$sid, $semesterId, $idKatalogBonus],
                    0
                );
                for ($n = $sudahDiberi + 1; $n <= $milestone; $n++) {
                    $kunci = "bonus_pagi:$semesterId:$sid:$n";
                    $katalog = katalog_kode('DIS_BONUS_PAGI');
                    if ($katalog !== null && catatan_sistem('DIS_BONUS_PAGI', $sid, $semesterId, $minggu, $kunci, $poinBonus, 'Bonus datang pagi ke-' . $n . ' (kumulatif ' . ($n * $setiapHari) . ' hari).')) {
                        $hasil['bonus_pagi']++;
                    }
                }
            }
        }
    });

    $hasil['pesan'] = 'Cron mingguan selesai: ' . $hasil['disiplin'] . ' poin disiplin, '
        . $hasil['dhuha'] . ' poin dhuha, ' . $hasil['dhuhur'] . ' poin dhuhur, '
        . $hasil['bonus_pagi'] . ' bonus datang pagi.';
    log_akses('cron_mingguan', 'cron', null);
    return $hasil;
}

if (PHP_SAPI === 'cli' && isset($_SERVER['SCRIPT_FILENAME']) && realpath((string) $_SERVER['SCRIPT_FILENAME']) === realpath(__FILE__)) {
    $tanggal = date('Y-m-d');
    foreach (array_slice($argv ?? [], 1) as $arg) {
        if (str_starts_with((string) $arg, '--tanggal=')) {
            $tanggal = substr((string) $arg, 10);
        }
    }
    try {
        $hasil = cron_mingguan($tanggal);
        fwrite(STDOUT, $hasil['pesan'] . PHP_EOL);
        exit($hasil['ok'] ? 0 : 1);
    } catch (Throwable $galat) {
        catat_kesalahan('cron/mingguan.php: ' . $galat->getMessage());
        fwrite(STDERR, 'Cron mingguan gagal: ' . $galat->getMessage() . PHP_EOL);
        exit(1);
    }
}
