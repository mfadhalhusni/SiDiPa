<?php
declare(strict_types=1);

/*
 |--------------------------------------------------------------------------
 | Tahap 3–4: rekap kehadiran, ekspor XLSX, dan ringkasan lintas kelas.
 | Semua fungsi di sini HANYA membaca kolom agregat. Tidak ada pencatat_id,
 | qr_token, nomor WhatsApp, atau riwayat pelanggaran yang ikut terkirim.
 |--------------------------------------------------------------------------
 */

/** Daftar semester, terbaru lebih dulu. */
function daftar_semester(): array
{
    return db_semua('SELECT * FROM semester ORDER BY tgl_mulai DESC, id DESC');
}

function semester_dari_id(int $id): ?array
{
    return $id > 0 ? db_satu('SELECT * FROM semester WHERE id = ? LIMIT 1', [$id]) : null;
}

/**
 * Rentang tanggal untuk rekap. $jenis: harian | bulanan | semester.
 * Mengembalikan ['mulai','selesai','label','jenis'].
 */
function periode_rekap(string $jenis, string $tanggal, string $bulan, int $semesterId): array
{
    $hariIni = date('Y-m-d');
    if ($jenis === 'harian') {
        $t = tanggal_valid($tanggal) ? $tanggal : $hariIni;
        return ['mulai' => $t, 'selesai' => $t, 'label' => tanggal_indonesia($t, true), 'jenis' => 'harian'];
    }
    if ($jenis === 'semester') {
        $semester = semester_dari_id($semesterId) ?? semester_aktif();
        if ($semester !== null) {
            return [
                'mulai' => (string) $semester['tgl_mulai'],
                'selesai' => (string) $semester['tgl_selesai'],
                'label' => (string) $semester['nama'] . ' (' . $semester['tahun_ajaran'] . ')',
                'jenis' => 'semester',
            ];
        }
    }
    if (!preg_match('/^(\d{4})-(0[1-9]|1[0-2])$/', $bulan, $m)) {
        $bulan = date('Y-m');
        [$t, $b] = array_map('intval', explode('-', $bulan));
    } else {
        $t = (int) $m[1];
        $b = (int) $m[2];
    }
    $mulai = sprintf('%04d-%02d-01', $t, $b);
    $selesai = date('Y-m-t', strtotime($mulai));
    return [
        'mulai' => $mulai,
        'selesai' => $selesai,
        'label' => NAMA_BULAN[$b] . ' ' . $t,
        'jenis' => 'bulanan',
    ];
}

/** Jumlah hari libur pada rentang (untuk keterangan hari efektif). */
function jumlah_hari_libur(string $mulai, string $selesai): int
{
    return (int) db_nilai(
        'SELECT COUNT(*) FROM hari_libur WHERE tanggal BETWEEN ? AND ?',
        [$mulai, $selesai],
        0
    );
}

/**
 * Rekap kehadiran per siswa. $saring menerima 'kelas_id', 'siswa_id',
 * dan 'id_kelas' (array pembatas kelas untuk guru).
 */
function rekap_kehadiran(string $mulai, string $selesai, array $saring = []): array
{
    $where = ['s.aktif = 1'];
    $param = [$mulai, $selesai];
    if (!empty($saring['siswa_id'])) {
        $where[] = 's.id = ?';
        $param[] = (int) $saring['siswa_id'];
    } elseif (!empty($saring['kelas_id'])) {
        $where[] = 's.kelas_id = ?';
        $param[] = (int) $saring['kelas_id'];
    }
    if (!empty($saring['id_kelas']) && is_array($saring['id_kelas'])) {
        $idKelas = array_values(array_filter(array_map('intval', $saring['id_kelas'])));
        if ($idKelas === []) {
            return [];
        }
        $where[] = 's.kelas_id IN (' . implode(', ', array_fill(0, count($idKelas), '?')) . ')';
        $param = array_merge($param, $idKelas);
    }
    $baris = db_semua(
        'SELECT s.id, s.nis, s.nama, k.id AS kelas_id, COALESCE(k.nama, \'Tanpa kelas\') AS kelas,
                COALESCE(SUM(CASE WHEN h.status = \'hadir\' THEN 1 ELSE 0 END), 0) AS hadir,
                COALESCE(SUM(CASE WHEN h.status = \'hadir\' AND h.awal = 1 THEN 1 ELSE 0 END), 0) AS awal,
                COALESCE(SUM(CASE WHEN h.status = \'terlambat\' THEN 1 ELSE 0 END), 0) AS terlambat,
                COALESCE(SUM(CASE WHEN h.status = \'izin\' THEN 1 ELSE 0 END), 0) AS izin,
                COALESCE(SUM(CASE WHEN h.status = \'sakit\' THEN 1 ELSE 0 END), 0) AS sakit,
                COALESCE(SUM(CASE WHEN h.status = \'alpa\' THEN 1 ELSE 0 END), 0) AS alpa,
                COUNT(h.id) AS tercatat
         FROM siswa s
         LEFT JOIN kelas k ON k.id = s.kelas_id
         LEFT JOIN kehadiran h ON h.siswa_id = s.id AND h.tanggal BETWEEN ? AND ?
         WHERE ' . implode(' AND ', $where) . '
         GROUP BY s.id, s.nis, s.nama, k.id, k.nama
         ORDER BY k.nama, s.nama, s.id',
        $param
    );
    $hasil = [];
    foreach ($baris as $b) {
        $hadirEfektif = (int) $b['hadir'] + (int) $b['terlambat'];
        $tercatat = (int) $b['tercatat'];
        $b['hadir_efektif'] = $hadirEfektif;
        $b['persentase'] = $tercatat > 0 ? round($hadirEfektif / $tercatat * 100, 1) : 0.0;
        $hasil[] = $b;
    }
    return $hasil;
}

/** Agregasi per kelas dari hasil rekap_kehadiran(). */
function rekap_per_kelas(array $baris): array
{
    $kelompok = [];
    foreach ($baris as $b) {
        $kunci = (string) ($b['kelas'] ?? 'Tanpa kelas');
        if (!isset($kelompok[$kunci])) {
            $kelompok[$kunci] = [
                'kelas' => $kunci, 'siswa' => 0, 'hadir' => 0, 'awal' => 0,
                'terlambat' => 0, 'izin' => 0, 'sakit' => 0, 'alpa' => 0, 'tercatat' => 0,
            ];
        }
        $kelompok[$kunci]['siswa'] += 1;
        foreach (['hadir', 'awal', 'terlambat', 'izin', 'sakit', 'alpa', 'tercatat'] as $kolom) {
            $kelompok[$kunci][$kolom] += (int) ($b[$kolom] ?? 0);
        }
    }
    foreach ($kelompok as &$k) {
        $efektif = $k['hadir'] + $k['terlambat'];
        $k['persentase'] = $k['tercatat'] > 0 ? round($efektif / $k['tercatat'] * 100, 1) : 0.0;
    }
    unset($k);
    ksort($kelompok);
    return array_values($kelompok);
}

/* Ekspor laporan memakai xlsx_keluar() dari app/xlsx.php (format .xlsx). */

/**
 * Ringkasan per kelas untuk dashboard BK / kepala sekolah / laporan.
 * Hanya angka agregat: rata-rata skor per aspek, kehadiran, pembinaan,
 * banding menunggu, dan sertifikat terbit.
 */
function ringkasan_kelas(int $semesterId, string $mulai, string $selesai): array
{
    $kelas = db_semua(
        'SELECT k.id, k.nama, COUNT(s.id) AS jumlah_siswa
         FROM kelas k LEFT JOIN siswa s ON s.kelas_id = k.id AND s.aktif = 1
         WHERE k.aktif = 1 GROUP BY k.id, k.nama ORDER BY k.nama'
    );
    $hasil = [];
    foreach ($kelas as $k) {
        $hasil[(int) $k['id']] = [
            'id' => (int) $k['id'],
            'nama' => (string) $k['nama'],
            'jumlah_siswa' => (int) $k['jumlah_siswa'],
            'aspek' => [],
            'pembinaan' => 0,
            'banding' => 0,
            'sertifikat' => 0,
            'hadir' => 0,
            'terlambat' => 0,
            'izin' => 0,
            'sakit' => 0,
            'alpa' => 0,
            'tercatat' => 0,
        ];
    }
    foreach (daftar_aspek() as $a) {
        foreach ($hasil as $id => &$r) {
            $r['aspek'][(int) $a['id']] = ['nama' => (string) $a['nama'], 'total' => 0, 'rata' => 0.0];
        }
        unset($r);
    }

    if ($semesterId > 0) {
        foreach (db_semua(
            'SELECT s.kelas_id, c.aspek_id, COALESCE(SUM(c.poin), 0) AS total
             FROM catatan_poin c JOIN siswa s ON s.id = c.siswa_id
             WHERE c.semester_id = ? AND c.status = \'aktif\'
             GROUP BY s.kelas_id, c.aspek_id',
            [$semesterId]
        ) as $row) {
            $id = (int) $row['kelas_id'];
            $aid = (int) $row['aspek_id'];
            if (isset($hasil[$id]['aspek'][$aid])) {
                $hasil[$id]['aspek'][$aid]['total'] = (int) $row['total'];
            }
        }
        foreach (db_semua(
            'SELECT s.kelas_id, COUNT(*) AS jumlah FROM pembinaan p JOIN siswa s ON s.id = p.siswa_id
             WHERE p.semester_id = ? AND p.status <> \'selesai\' GROUP BY s.kelas_id',
            [$semesterId]
        ) as $row) {
            if (isset($hasil[(int) $row['kelas_id']])) {
                $hasil[(int) $row['kelas_id']]['pembinaan'] = (int) $row['jumlah'];
            }
        }
        foreach (db_semua(
            'SELECT s.kelas_id, COUNT(*) AS jumlah FROM sertifikat sr JOIN siswa s ON s.id = sr.siswa_id
             WHERE sr.semester_id = ? AND sr.status = \'terbit\' GROUP BY s.kelas_id',
            [$semesterId]
        ) as $row) {
            if (isset($hasil[(int) $row['kelas_id']])) {
                $hasil[(int) $row['kelas_id']]['sertifikat'] = (int) $row['jumlah'];
            }
        }
    }
    foreach (db_semua(
        'SELECT s.kelas_id, COUNT(*) AS jumlah FROM banding b JOIN siswa s ON s.id = b.siswa_id
         WHERE b.status = \'menunggu\' GROUP BY s.kelas_id'
    ) as $row) {
        if (isset($hasil[(int) $row['kelas_id']])) {
            $hasil[(int) $row['kelas_id']]['banding'] = (int) $row['jumlah'];
        }
    }
    foreach (db_semua(
        'SELECT s.kelas_id, h.status, COUNT(*) AS jumlah
         FROM kehadiran h JOIN siswa s ON s.id = h.siswa_id
         WHERE h.tanggal BETWEEN ? AND ? GROUP BY s.kelas_id, h.status',
        [$mulai, $selesai]
    ) as $row) {
        $id = (int) $row['kelas_id'];
        $status = (string) $row['status'];
        if (!isset($hasil[$id])) {
            continue;
        }
        $jumlah = (int) $row['jumlah'];
        $hasil[$id]['tercatat'] += $jumlah;
        if (isset($hasil[$id][$status])) {
            $hasil[$id][$status] += $jumlah;
        }
    }
    foreach ($hasil as &$r) {
        foreach ($r['aspek'] as &$a) {
            $a['rata'] = $r['jumlah_siswa'] > 0 ? round($a['total'] / $r['jumlah_siswa'], 1) : 0.0;
        }
        unset($a);
        $efektif = $r['hadir'] + $r['terlambat'];
        $r['persentase_hadir'] = $r['tercatat'] > 0 ? round($efektif / $r['tercatat'] * 100, 1) : 0.0;
    }
    unset($r);
    return array_values($hasil);
}


/* --------------------------------------------------------------------------
 | Tambahan: deret perkembangan skor mingguan untuk dashboard siswa (§10 PDF).
 | Hanya menjumlahkan poin agregat; tidak ada pencatat_id maupun riwayat
 | pelanggaran yang ikut terkirim.
 | -------------------------------------------------------------------------- */
function grafik_mingguan_siswa(int $siswaId, int $semesterId, int $jumlahMinggu = 8): array
{
    $jumlahMinggu = max(4, min(16, $jumlahMinggu));
    $seninIni = strtotime('monday this week');
    if ($seninIni === false) {
        $seninIni = strtotime('last monday') ?: time();
    }
    $awalRentang = date('Y-m-d', strtotime('-' . ($jumlahMinggu - 1) . ' weeks', $seninIni));
    $catatan = db_semua(
        "SELECT tanggal, poin FROM catatan_poin
         WHERE siswa_id = ? AND semester_id = ? AND status = 'aktif' AND tanggal >= ?
         ORDER BY tanggal",
        [$siswaId, $semesterId, $awalRentang]
    );
    $poinPerTanggal = [];
    foreach ($catatan as $c) {
        $kunci = (string) $c['tanggal'];
        $poinPerTanggal[$kunci] = ($poinPerTanggal[$kunci] ?? 0) + (int) $c['poin'];
    }

    $hasil = [];
    $kumulatif = 0;
    for ($i = $jumlahMinggu - 1; $i >= 0; $i--) {
        $senin = strtotime('-' . $i . ' weeks', $seninIni);
        $mulai = date('Y-m-d', $senin);
        $selesai = date('Y-m-d', strtotime('+6 days', $senin));
        $poin = 0;
        foreach ($poinPerTanggal as $tanggal => $nilai) {
            if ($tanggal >= $mulai && $tanggal <= $selesai) {
                $poin += $nilai;
            }
        }
        $kumulatif += $poin;
        $hasil[] = [
            'mulai' => $mulai,
            'label' => (int) date('j', $senin) . ' ' . NAMA_BULAN_PENDEK[(int) date('n', $senin)],
            'poin' => $poin,
            'kumulatif' => $kumulatif,
        ];
    }
    return $hasil;
}
