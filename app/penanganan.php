<?php
declare(strict_types=1);

/*
 |--------------------------------------------------------------------------
 | Bagian 22 — Level penanganan siswa bermasalah (akses Guru BK / admin).
 |--------------------------------------------------------------------------
 | Sumber "siswa bermasalah" adalah RIWAYAT PELANGGARAN pada semester
 | berjalan: akumulasi poin negatif aktif + jumlah pelanggaran tingkat
 | sedang/berat. Bukan peringkat skor terendah (larangan lama tetap berlaku).
 |
 | Level otomatis L1..L4 dihitung dari data itu. BK dapat menaikkan atau
 | menurunkan level secara manual; setiap penetapan disimpan sebagai BARIS
 | BARU sehingga seluruh riwayat perubahan tetap terbaca (audit trail).
 |
 | PRIVASI: helper di sini TIDAK PERNAH memilih pencatat_id, nama pencatat,
 | keterangan bebas, bukti foto, atau nomor WA. Identitas pencatat hanya
 | boleh dibaca admin lewat lihat_pencatat().
 |--------------------------------------------------------------------------
 */

/** Empat tahap penanganan, dari yang paling ringan ke paling berat. */
const LEVEL_PENANGANAN = [
    1 => [
        'kode' => 'L1',
        'nama' => 'Pembinaan awal',
        'penanggung_jawab' => 'Wali kelas',
        'tahap' => 'Teguran dan bimbingan ringan oleh wali kelas.',
    ],
    2 => [
        'kode' => 'L2',
        'nama' => 'Konseling BK',
        'penanggung_jawab' => 'Guru BK',
        'tahap' => 'Konseling individual, identifikasi penyebab, dan kontrak perilaku ringan.',
    ],
    3 => [
        'kode' => 'L3',
        'nama' => 'Pendampingan intensif',
        'penanggung_jawab' => 'Guru BK + wali murid',
        'tahap' => 'Panggilan wali murid, pendampingan berkala, dan pemantauan mingguan.',
    ],
    4 => [
        'kode' => 'L4',
        'nama' => 'Penanganan pimpinan',
        'penanggung_jawab' => 'Kepala sekolah',
        'tahap' => 'Pembinaan pimpinan dan tindakan lanjutan sesuai tata tertib sekolah.',
    ],
];

/** Ambang akumulasi poin negatif untuk tiap level (dapat dilihat di UI). */
const AMBANG_LEVEL_PENANGANAN = [1 => 1, 2 => 10, 3 => 25, 4 => 50];

function informasi_level(int $level): array
{
    return LEVEL_PENANGANAN[$level] ?? LEVEL_PENANGANAN[1];
}

function level_penanganan_sah(int $level): bool
{
    return $level >= 1 && $level <= 4;
}

/**
 * Tentukan level otomatis dari akumulasi pelanggaran.
 * Satu pelanggaran berat langsung L3; dua atau lebih (atau >= 50 poin) L4.
 */
function level_penanganan_otomatis(int $totalNegatif, int $jumlahBerat, int $jumlahSedang): int
{
    if ($totalNegatif <= 0) {
        return 0;
    }
    if ($jumlahBerat >= 2 || $totalNegatif >= AMBANG_LEVEL_PENANGANAN[4]) {
        return 4;
    }
    if ($jumlahBerat >= 1 || $totalNegatif >= AMBANG_LEVEL_PENANGANAN[3]) {
        return 3;
    }
    if ($jumlahSedang >= 1 || $totalNegatif >= AMBANG_LEVEL_PENANGANAN[2]) {
        return 2;
    }
    return 1;
}

function alasan_level_otomatis(array $r): string
{
    $bagian = ['total pengurangan ' . (int) $r['total_negatif'] . ' poin'];
    if ((int) $r['jumlah_berat'] > 0) {
        $bagian[] = (int) $r['jumlah_berat'] . ' pelanggaran berat';
    }
    if ((int) $r['jumlah_sedang'] > 0) {
        $bagian[] = (int) $r['jumlah_sedang'] . ' pelanggaran sedang';
    }
    return implode(', ', $bagian);
}

/** Instalasi lama yang belum menjalankan migrasi tetap bisa membuka halaman BK. */
function penanganan_tabel_ada(): bool
{
    static $ada = null;
    if ($ada !== null) {
        return $ada;
    }
    try {
        db_nilai('SELECT 1 FROM penanganan_siswa LIMIT 1', [], 0);
        $ada = true;
    } catch (Throwable $galat) {
        catat_kesalahan('Tabel penanganan_siswa belum ada: ' . $galat->getMessage());
        $ada = false;
    }
    return $ada;
}

/**
 * Ringkasan pelanggaran SELURUH siswa pada satu semester, dikunci siswa_id.
 * Yang dihitung hanya catatan berpoin negatif berstatus aktif; catatan
 * dibatalkan tidak menaikkan level.
 */
function ringkasan_pelanggaran_semester(int $semesterId): array
{
    if ($semesterId <= 0) {
        return [];
    }
    $baris = db_semua(
        "SELECT c.siswa_id,
                COALESCE(SUM(CASE WHEN c.poin < 0 AND c.status = 'aktif' THEN -c.poin ELSE 0 END), 0) AS total_negatif,
                COALESCE(SUM(CASE WHEN c.poin < 0 AND c.status = 'aktif' THEN 1 ELSE 0 END), 0) AS jumlah_pelanggaran,
                COALESCE(SUM(CASE WHEN c.poin < 0 AND c.status = 'aktif' AND COALESCE(k.tingkat, 'none') = 'berat' THEN 1 ELSE 0 END), 0) AS jumlah_berat,
                COALESCE(SUM(CASE WHEN c.poin < 0 AND c.status = 'aktif' AND COALESCE(k.tingkat, 'none') = 'sedang' THEN 1 ELSE 0 END), 0) AS jumlah_sedang
         FROM catatan_poin c
         LEFT JOIN katalog_poin k ON k.id = c.katalog_id
         WHERE c.semester_id = ?
         GROUP BY c.siswa_id",
        [$semesterId]
    );
    $hasil = [];
    foreach ($baris as $b) {
        $total = (int) $b['total_negatif'];
        if ($total <= 0) {
            continue;
        }
        $berat = (int) $b['jumlah_berat'];
        $sedang = (int) $b['jumlah_sedang'];
        $hasil[(int) $b['siswa_id']] = [
            'total_negatif' => $total,
            'jumlah_pelanggaran' => (int) $b['jumlah_pelanggaran'],
            'jumlah_berat' => $berat,
            'jumlah_sedang' => $sedang,
            'level_otomatis' => level_penanganan_otomatis($total, $berat, $sedang),
        ];
    }
    return $hasil;
}

/** Penetapan TERAKHIR per siswa (baris dengan id tertinggi). */
function penanganan_terkini_semester(int $semesterId): array
{
    if ($semesterId <= 0 || !penanganan_tabel_ada()) {
        return [];
    }
    try {
        $baris = db_semua(
            'SELECT p.id, p.siswa_id, p.level, p.status, p.tahap, p.penanggung_jawab,
                    p.catatan, p.tanggal, p.dibuat_pada
             FROM penanganan_siswa p
             JOIN (SELECT siswa_id, MAX(id) AS maks FROM penanganan_siswa
                   WHERE semester_id = ? GROUP BY siswa_id) t ON t.maks = p.id',
            [$semesterId]
        );
    } catch (Throwable $galat) {
        catat_kesalahan('penanganan_terkini_semester gagal: ' . $galat->getMessage());
        return [];
    }
    $hasil = [];
    foreach ($baris as $p) {
        $hasil[(int) $p['siswa_id']] = $p;
    }
    return $hasil;
}

/** Daftar siswa bermasalah + level efektif + status penanganan. */
function daftar_siswa_bermasalah(int $semesterId): array
{
    $ringkasan = ringkasan_pelanggaran_semester($semesterId);
    if ($ringkasan === []) {
        return [];
    }
    $ids = array_map('intval', array_keys($ringkasan));
    $tanda = implode(',', array_fill(0, count($ids), '?'));
    $siswa = db_semua(
        "SELECT s.id, s.nis, s.nama, COALESCE(k.nama, '-') AS kelas
         FROM siswa s LEFT JOIN kelas k ON k.id = s.kelas_id
         WHERE s.aktif = 1 AND s.id IN ($tanda)
         ORDER BY k.nama, s.nama",
        $ids
    );
    $terkini = penanganan_terkini_semester($semesterId);
    $hasil = [];
    foreach ($siswa as $s) {
        $id = (int) $s['id'];
        $r = $ringkasan[$id];
        $t = $terkini[$id] ?? null;
        $level = $t !== null ? (int) $t['level'] : (int) $r['level_otomatis'];
        $info = informasi_level($level);
        $hasil[] = [
            'id' => $id,
            'nis' => (string) $s['nis'],
            'nama' => (string) $s['nama'],
            'kelas' => (string) $s['kelas'],
            'total_negatif' => (int) $r['total_negatif'],
            'jumlah_pelanggaran' => (int) $r['jumlah_pelanggaran'],
            'jumlah_berat' => (int) $r['jumlah_berat'],
            'jumlah_sedang' => (int) $r['jumlah_sedang'],
            'level_otomatis' => (int) $r['level_otomatis'],
            'alasan_otomatis' => alasan_level_otomatis($r),
            'level' => $level,
            'level_kode' => $info['kode'],
            'level_nama' => $info['nama'],
            'status' => $t !== null ? (string) $t['status'] : 'baru',
            'penanggung_jawab' => $t !== null && $t['penanggung_jawab'] !== ''
                ? (string) $t['penanggung_jawab'] : $info['penanggung_jawab'],
            'tahap' => $t !== null && $t['tahap'] !== ''
                ? (string) $t['tahap'] : $info['tahap'],
            'catatan' => $t !== null ? (string) ($t['catatan'] ?? '') : '',
            'ditetapkan' => $t !== null,
            'terakhir' => $t !== null ? (string) $t['tanggal'] : '',
        ];
    }
    usort($hasil, static function (array $a, array $b): int {
        return ($b['level'] <=> $a['level']) ?: ($b['total_negatif'] <=> $a['total_negatif']);
    });
    return $hasil;
}

/** Seluruh riwayat penetapan level satu siswa (jejak perubahan). */
function riwayat_penanganan_siswa(int $siswaId, int $semesterId): array
{
    if ($siswaId <= 0 || $semesterId <= 0 || !penanganan_tabel_ada()) {
        return [];
    }
    try {
        return db_semua(
            'SELECT id, level, status, tahap, penanggung_jawab, catatan, tanggal, dibuat_pada
             FROM penanganan_siswa WHERE siswa_id = ? AND semester_id = ?
             ORDER BY tanggal DESC, id DESC LIMIT 100',
            [$siswaId, $semesterId]
        );
    } catch (Throwable $galat) {
        catat_kesalahan('riwayat_penanganan_siswa gagal: ' . $galat->getMessage());
        return [];
    }
}

/** Riwayat perubahan level terbaru lintas siswa, untuk dashboard/list. */
function riwayat_penanganan_semester(int $semesterId, int $batas = 20): array
{
    if ($semesterId <= 0 || !penanganan_tabel_ada()) {
        return [];
    }
    try {
        return db_semua(
            'SELECT p.id, p.siswa_id, p.level, p.status, p.tahap, p.penanggung_jawab,
                    p.catatan, p.tanggal, s.nama AS nama_siswa, s.nis
             FROM penanganan_siswa p JOIN siswa s ON s.id = p.siswa_id
             WHERE p.semester_id = ? ORDER BY p.tanggal DESC, p.id DESC LIMIT ' . (int) $batas,
            [$semesterId]
        );
    } catch (Throwable $galat) {
        catat_kesalahan('riwayat_penanganan_semester gagal: ' . $galat->getMessage());
        return [];
    }
}

/**
 * Semua catatan pelanggaran (poin negatif) satu siswa, TERMASUK yang sudah
 * dibatalkan, agar BK tahu kejadian mana yang tidak boleh dicatat ulang.
 * Sengaja tanpa pencatat_id / keterangan bebas / bukti / nomor WA.
 */
function catatan_pelanggaran_siswa(int $siswaId, int $semesterId): array
{
    if ($siswaId <= 0 || $semesterId <= 0) {
        return [];
    }
    return db_semua(
        "SELECT c.id, c.tanggal, c.dibuat_pada, c.poin, c.sumber, c.status,
                COALESCE(k.nama, CASE WHEN c.sumber = 'berkala' THEN 'Penilaian bulanan' ELSE 'Catatan sistem' END) AS kejadian,
                COALESCE(k.tingkat, 'none') AS tingkat,
                a.nama AS aspek
         FROM catatan_poin c
         JOIN aspek a ON a.id = c.aspek_id
         LEFT JOIN katalog_poin k ON k.id = c.katalog_id
         WHERE c.siswa_id = ? AND c.semester_id = ? AND c.poin < 0
         ORDER BY c.tanggal DESC, c.id DESC LIMIT 200",
        [$siswaId, $semesterId]
    );
}

function status_penanganan_sah(string $status): bool
{
    return in_array($status, ['baru', 'proses', 'selesai'], true);
}

/** Kelas CSS chip untuk tiap level (neo brutalism). */
function badge_level(int $level): string
{
    return [1 => 'badge-biru', 2 => 'badge-kuning', 3 => 'badge-oranye', 4 => 'badge-koral'][$level] ?? 'badge-abu';
}

function label_status_penanganan(string $status): string
{
    return ['baru' => 'Baru', 'proses' => 'Proses', 'selesai' => 'Selesai'][$status] ?? 'Baru';
}

function badge_status_penanganan(string $status): string
{
    return ['baru' => 'badge-koral', 'proses' => 'badge-kuning', 'selesai' => 'badge-toska'][$status] ?? 'badge-abu';
}
