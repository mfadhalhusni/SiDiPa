<?php
declare(strict_types=1);

/*
 |--------------------------------------------------------------------------
 | Perhitungan skor, predikat, dan KERAHASIAAN PENCATAT
 |--------------------------------------------------------------------------
 | Fungsi riwayat_catatan(), catatan_saya(), dan seluruh endpoint JSON hanya
 | memilih kolom yang diizinkan. Kolom `pencatat_id` serta nama pencatat TIDAK
 | PERNAH ikut dalam hasil untuk siswa, wali kelas, BK, kepala sekolah, guru
 | lain, maupun wali murid. Satu-satunya jalan membaca identitas pencatat
 | adalah lihat_pencatat(), yang hanya boleh dipanggil admin dan selalu
 | menulis jejak ke log_akses.
 */

function daftar_aspek(): array
{
    return db_semua('SELECT id, kode, nama, urutan, target_semester FROM aspek ORDER BY urutan, id');
}

function semester_aktif(): ?array
{
    return db_satu(
        "SELECT * FROM semester WHERE aktif = 1 AND status = 'berjalan' ORDER BY tgl_mulai DESC, id DESC LIMIT 1"
    ) ?: db_satu('SELECT * FROM semester ORDER BY tgl_mulai DESC, id DESC LIMIT 1');
}

function semester_wajib(): array
{
    $semester = semester_aktif();
    if ($semester === null) {
        throw new RuntimeException('Belum ada semester aktif. Tambahkan semester lewat menu admin.');
    }
    return $semester;
}

function ambang_predikat(): array
{
    return [
        'A+' => (float) pengaturan('predikat_aplus', '100'),
        'A'  => (float) pengaturan('predikat_a', '90'),
        'B'  => (float) pengaturan('predikat_b', '75'),
        'C'  => (float) pengaturan('predikat_c', '60'),
        'D'  => (float) pengaturan('predikat_d', '0'),
    ];
}

/** Predikat SELALU per aspek. Tidak ada predikat keseluruhan. */
function predikat_dari_persentase(float $persentase): array
{
    $b = ambang_predikat();
    if ($persentase > $b['A+']) {
        return ['kode' => 'A+', 'keterangan' => 'Melampaui target'];
    }
    if ($persentase >= $b['A']) {
        return ['kode' => 'A', 'keterangan' => 'Sangat baik'];
    }
    if ($persentase >= $b['B']) {
        return ['kode' => 'B', 'keterangan' => 'Baik'];
    }
    if ($persentase >= $b['C']) {
        return ['kode' => 'C', 'keterangan' => 'Cukup'];
    }
    if ($persentase >= $b['D']) {
        return ['kode' => 'D', 'keterangan' => 'Perlu pembinaan'];
    }
    return ['kode' => 'E', 'keterangan' => 'Pembinaan intensif'];
}

/** Skor awal 0 per semester, tanpa batas atas dan boleh minus. */
function skor_per_aspek(int $siswaId, int $semesterId): array
{
    $baris = db_semua(
        "SELECT a.id, a.kode, a.nama, a.urutan, a.target_semester,
                COALESCE(SUM(c.poin), 0) AS skor
         FROM aspek a
         LEFT JOIN catatan_poin c
           ON c.aspek_id = a.id AND c.siswa_id = ? AND c.semester_id = ? AND c.status = 'aktif'
         GROUP BY a.id, a.kode, a.nama, a.urutan, a.target_semester
         ORDER BY a.urutan, a.id",
        [$siswaId, $semesterId]
    );
    $hasil = [];
    foreach ($baris as $a) {
        $target = (int) $a['target_semester'];
        $skor = (int) $a['skor'];
        $persentase = $target > 0 ? ($skor / $target) * 100 : 0.0;
        $predikat = predikat_dari_persentase($persentase);
        $hasil[] = [
            'aspek_id' => (int) $a['id'],
            'kode' => $a['kode'],
            'aspek' => $a['nama'],
            'target' => $target,
            'skor' => $skor,
            'persentase' => round($persentase, 1),
            'predikat' => $predikat['kode'],
            'keterangan' => $predikat['keterangan'],
        ];
    }
    return $hasil;
}

/** Daftar kolom aman: TIDAK ADA pencatat_id / nama pencatat. */
const KOLOM_CATATAN_AMAN = "c.id, c.tanggal, c.poin, c.keterangan, c.sumber, c.status,
        c.bukti_path, c.aspek_id, a.nama AS aspek, a.kode AS kode_aspek,
        COALESCE(k.nama, 'Penilaian bulanan') AS kejadian,
        COALESCE(k.tingkat, 'none') AS tingkat";

function riwayat_catatan(int $siswaId, int $semesterId, int $batas = 200): array
{
    return db_semua(
        'SELECT ' . KOLOM_CATATAN_AMAN . '
         FROM catatan_poin c
         JOIN aspek a ON a.id = c.aspek_id
         LEFT JOIN katalog_poin k ON k.id = c.katalog_id
         WHERE c.siswa_id = ? AND c.semester_id = ?
         ORDER BY c.tanggal DESC, c.id DESC LIMIT ' . max(1, min(500, $batas)),
        [$siswaId, $semesterId]
    );
}

/** Guru melihat catatan MILIKNYA sendiri; kolom pencatat_id hanya dipakai sebagai filter. */
function catatan_saya(int $guruId, int $semesterId, int $batas = 200): array
{
    return db_semua(
        'SELECT ' . KOLOM_CATATAN_AMAN . ', s.nama AS nama_siswa, s.nis
         FROM catatan_poin c
         JOIN aspek a ON a.id = c.aspek_id
         JOIN siswa s ON s.id = c.siswa_id
         LEFT JOIN katalog_poin k ON k.id = c.katalog_id
         WHERE c.pencatat_id = ? AND c.semester_id = ?
         ORDER BY c.tanggal DESC, c.id DESC LIMIT ' . max(1, min(500, $batas)),
        [$guruId, $semesterId]
    );
}

/** Khusus admin: daftar catatan lengkap beserta identitas pencatat. */
function catatan_admin(array $saring = [], int $batas = 300): array
{
    $where = ['1 = 1'];
    $param = [];
    if (!empty($saring['semester_id'])) {
        $where[] = 'c.semester_id = ?';
        $param[] = (int) $saring['semester_id'];
    }
    if (!empty($saring['siswa_id'])) {
        $where[] = 'c.siswa_id = ?';
        $param[] = (int) $saring['siswa_id'];
    }
    return db_semua(
        'SELECT ' . KOLOM_CATATAN_AMAN . ', c.pencatat_id, c.dibuat_pada,
                c.alasan_batal, c.dibatalkan_pada, p.nama AS pencatat_nama,
                s.nama AS nama_siswa, s.nis
         FROM catatan_poin c
         JOIN aspek a ON a.id = c.aspek_id
         JOIN siswa s ON s.id = c.siswa_id
         LEFT JOIN katalog_poin k ON k.id = c.katalog_id
         LEFT JOIN pengguna p ON p.id = c.pencatat_id
         WHERE ' . implode(' AND ', $where) . '
         ORDER BY c.tanggal DESC, c.id DESC LIMIT ' . max(1, min(1000, $batas)),
        $param
    );
}

/**
 * Tombol "Lihat pencatat": admin saja, dan SETIAP pembukaan dicatat.
 * Mengembalikan null bila pemanggil bukan admin.
 */
function lihat_pencatat(int $catatanId): ?array
{
    $pengguna = auth_pengguna();
    if ($pengguna === null || $pengguna['peran'] !== 'admin') {
        return null;
    }
    log_akses('lihat_pencatat', 'catatan_poin', $catatanId);
    return db_satu(
        'SELECT c.id, c.pencatat_id, p.nama AS pencatat_nama, p.username,
                c.tanggal, c.poin, s.nama AS nama_siswa
         FROM catatan_poin c
         JOIN siswa s ON s.id = c.siswa_id
         LEFT JOIN pengguna p ON p.id = c.pencatat_id
         WHERE c.id = ? LIMIT 1',
        [$catatanId]
    );
}

/** Pembatas pengurangan manual per aspek per hari (default 10 poin). */
function pengurangan_manual_harian(int $siswaId, int $aspekId, string $tanggal): int
{
    $jumlah = db_nilai(
        "SELECT COALESCE(SUM(CASE WHEN poin < 0 THEN -poin ELSE 0 END), 0)
         FROM catatan_poin
         WHERE siswa_id = ? AND aspek_id = ? AND tanggal = ?
           AND sumber = 'manual' AND status = 'aktif'",
        [$siswaId, $aspekId, $tanggal],
        0
    );
    return (int) $jumlah;
}

/** Tambah catatan poin (dipakai halaman guru, cron, penilaian bulanan, banding). */
function tambah_catatan(array $data): int
{
    $wajib = ['semester_id', 'siswa_id', 'aspek_id', 'poin', 'sumber', 'tanggal'];
    foreach ($wajib as $kunci) {
        if (!array_key_exists($kunci, $data)) {
            throw new InvalidArgumentException('Data catatan kurang: ' . $kunci);
        }
    }
    return db_sisip('catatan_poin', [
        'semester_id' => (int) $data['semester_id'],
        'siswa_id' => (int) $data['siswa_id'],
        'katalog_id' => isset($data['katalog_id']) ? (int) $data['katalog_id'] : null,
        'aspek_id' => (int) $data['aspek_id'],
        'poin' => (int) $data['poin'],
        'sumber' => (string) $data['sumber'],
        'kunci_unik' => isset($data['kunci_unik']) ? teks($data['kunci_unik'], 100) : null,
        'pencatat_id' => isset($data['pencatat_id']) ? (int) $data['pencatat_id'] : null,
        'keterangan' => isset($data['keterangan']) ? teks($data['keterangan'], 2000) : null,
        'bukti_path' => isset($data['bukti_path']) ? teks($data['bukti_path'], 255) : null,
        'status' => 'aktif',
        'tanggal' => (string) $data['tanggal'],
    ]);
}

/** Batalkan catatan (tidak pernah menghapus), dengan jejak audit. */
function batalkan_catatan(int $catatanId, string $alasan, int $oleh): array
{
    return db_transaksi(function () use ($catatanId, $alasan, $oleh): array {
        $catatan = db_satu('SELECT id, status, siswa_id FROM catatan_poin WHERE id = ? FOR UPDATE', [$catatanId]);
        if ($catatan === null) {
            return ['ok' => false, 'pesan' => 'Catatan tidak ditemukan.'];
        }
        if ($catatan['status'] !== 'aktif') {
            return ['ok' => false, 'pesan' => 'Catatan sudah dibatalkan sebelumnya.'];
        }
        db_jalankan(
            "UPDATE catatan_poin SET status = 'dibatalkan', alasan_batal = ?, dibatalkan_oleh = ?, dibatalkan_pada = NOW() WHERE id = ?",
            [teks($alasan, 2000), $oleh, $catatanId]
        );
        log_akses('batalkan_catatan', 'catatan_poin', $catatanId, $oleh);
        return ['ok' => true, 'pesan' => 'Catatan dibatalkan dan poin kembali.'];
    });
}

/** Peringkat POSITIF saja. Dilarang menampilkan siswa dengan skor terendah. */
function siswa_teladan(int $semesterId, int $batas = 5): array
{
    return db_semua(
        "SELECT a.nama AS aspek, s.nama AS nama_siswa, k.nama AS kelas, SUM(c.poin) AS skor
         FROM catatan_poin c
         JOIN aspek a ON a.id = c.aspek_id
         JOIN siswa s ON s.id = c.siswa_id
         LEFT JOIN kelas k ON k.id = s.kelas_id
         WHERE c.semester_id = ? AND c.status = 'aktif'
         GROUP BY a.id, a.nama, s.id, s.nama, k.nama
         HAVING skor > 0
         ORDER BY skor DESC LIMIT " . max(1, min(20, $batas)),
        [$semesterId]
    );
}

/** Ambil baris katalog berdasarkan kode stabil (dipakai cron). */
function katalog_kode(string $kode): ?array
{
    return db_satu('SELECT * FROM katalog_poin WHERE kode = ? LIMIT 1', [$kode]);
}

function semester_dari_tanggal(string $tanggal): ?array
{
    return db_satu(
        'SELECT * FROM semester WHERE tgl_mulai <= ? AND tgl_selesai >= ? ORDER BY aktif DESC, id DESC LIMIT 1',
        [$tanggal, $tanggal]
    );
}

function izin_pada_tanggal(int $siswaId, string $tanggal): ?array
{
    return db_satu(
        'SELECT id, jenis, keterangan FROM izin_siswa
         WHERE siswa_id = ? AND ? BETWEEN tgl_mulai AND tgl_selesai
         ORDER BY CASE jenis WHEN \'udzur\' THEN 1 ELSE 0 END, id DESC LIMIT 1',
        [$siswaId, $tanggal]
    );
}

function status_kehadiran_hari(int $siswaId, string $tanggal): ?array
{
    return db_satu('SELECT * FROM kehadiran WHERE siswa_id = ? AND tanggal = ? LIMIT 1', [$siswaId, $tanggal]);
}

/**
 * Catatan poin otomatis yang IDEMPOTEN lewat kunci_unik.
 * $poin diambil dari pengaturan bila diberikan; bila tidak, dari katalog.
 * Mengembalikan true hanya saat catatan baru benar-benar dibuat.
 */
function catatan_sistem(
    string $kodeKatalog,
    int $siswaId,
    int $semesterId,
    string $tanggal,
    string $kunciUnik,
    ?int $poin = null,
    ?string $keterangan = null
): bool {
    if (db_satu('SELECT id FROM catatan_poin WHERE kunci_unik = ?', [$kunciUnik]) !== null) {
        return false;
    }
    $katalog = katalog_kode($kodeKatalog);
    if ($katalog === null) {
        catat_kesalahan('Katalog otomatis tidak ditemukan: ' . $kodeKatalog);
        return false;
    }
    tambah_catatan([
        'semester_id' => $semesterId,
        'siswa_id' => $siswaId,
        'katalog_id' => (int) $katalog['id'],
        'aspek_id' => (int) $katalog['aspek_id'],
        'poin' => $poin ?? (int) $katalog['poin'],
        'sumber' => 'sistem',
        'kunci_unik' => $kunciUnik,
        'keterangan' => $keterangan ?? ('Dihasilkan sistem: ' . $katalog['nama']),
        'tanggal' => $tanggal,
    ]);
    return true;
}

/** Batalkan catatan otomatis pada tanggal tertentu (misal izin masuk belakangan). */
function batalkan_catatan_otomatis_harian(int $siswaId, string $tanggal): int
{
    return db_jalankan(
        "UPDATE catatan_poin SET status = 'dibatalkan', alasan_batal = 'Diperbaiki: siswa memiliki izin, sakit, atau udzur', dibatalkan_pada = NOW()
         WHERE siswa_id = ? AND tanggal = ? AND status = 'aktif' AND kunci_unik LIKE ?",
        [$siswaId, $tanggal, 'harian:%']
    );
}

/* ==========================================================================
 | TAHAP 2 — pembatalan mandiri, notifikasi, dan ambang pembinaan
 | ========================================================================== */

/**
 * Guru membatalkan CATATANNYA SENDIRI dalam batas jam yang diatur admin.
 * Catatan tidak dihapus: alasan, aktor, dan waktu tersimpan di baris yang sama.
 */
function batalkan_catatan_sendiri(int $catatanId, int $guruId, string $alasan): array
{
    $batas = (int) pengaturan('batas_batal_catatan_jam', '24');
    $catatan = db_satu(
        'SELECT id, pencatat_id, status, sumber,
                TIMESTAMPDIFF(HOUR, dibuat_pada, NOW()) AS umur_jam
         FROM catatan_poin WHERE id = ? LIMIT 1',
        [$catatanId]
    );
    if ($catatan === null) {
        return ['ok' => false, 'pesan' => 'Catatan tidak ditemukan.'];
    }
    if ((int) $catatan['pencatat_id'] !== $guruId) {
        return ['ok' => false, 'pesan' => 'Hanya pencatat catatan ini yang dapat membatalkannya.'];
    }
    if ($catatan['status'] !== 'aktif') {
        return ['ok' => false, 'pesan' => 'Catatan sudah dibatalkan sebelumnya.'];
    }
    if ($catatan['sumber'] !== 'manual') {
        return ['ok' => false, 'pesan' => 'Catatan otomatis sistem tidak dapat dibatalkan manual.'];
    }
    if ((int) $catatan['umur_jam'] > $batas) {
        return ['ok' => false, 'pesan' => 'Batas pembatalan ' . $batas . ' jam sudah terlewati.'];
    }
    if (trim($alasan) === '') {
        return ['ok' => false, 'pesan' => 'Alasan pembatalan wajib diisi.'];
    }
    return batalkan_catatan($catatanId, $alasan, $guruId);
}

/** ID pengguna berperan tertentu yang masih aktif. */
function id_pengguna_peran(array $peran): array
{
    if ($peran === []) {
        return [];
    }
    $tanda = implode(', ', array_fill(0, count($peran), '?'));
    return array_map('intval', array_column(
        db_semua('SELECT id FROM pengguna WHERE aktif = 1 AND peran IN (' . $tanda . ')', $peran),
        'id'
    ));
}

/** Wali kelas dari sebuah siswa (0 bila kelas belum punya wali). */
function wali_kelas_siswa(int $siswaId): int
{
    return (int) db_nilai(
        'SELECT k.wali_kelas_id FROM siswa s JOIN kelas k ON k.id = s.kelas_id WHERE s.id = ? LIMIT 1',
        [$siswaId],
        0
    );
}

/** Kirim notifikasi in-app ke banyak pengguna tanpa duplikasi. */
function notifikasi_ke_banyak(array $idPengguna, string $judul, string $pesan, ?string $tautan = null): int
{
    $terkirim = 0;
    foreach (array_unique(array_filter(array_map('intval', $idPengguna))) as $id) {
        notifikasi_kirim($id, $judul, $pesan, $tautan);
        $terkirim++;
    }
    return $terkirim;
}

/**
 * Pelanggaran berat: beri tahu BK, kepala sekolah, dan wali kelas.
 * Pesan TIDAK memuat nama pencatat (privasi bagian 5 PDF).
 */
function notifikasi_pelanggaran_berat(int $siswaId, string $namaSiswa, string $kejadian, int $poin): int
{
    $penerima = id_pengguna_peran(['bk', 'kepsek']);
    $wali = wali_kelas_siswa($siswaId);
    if ($wali > 0) {
        $penerima[] = $wali;
    }
    return notifikasi_ke_banyak(
        $penerima,
        'Pelanggaran berat perlu ditindaklanjuti',
        $namaSiswa . ' tercatat: ' . $kejadian . ' (' . ($poin > 0 ? '+' : '') . $poin . ' poin). '
        . 'Buka rekap untuk menindaklanjuti.',
        url('pages/umum/rekap.php')
    );
}

/**
 * Evaluasi ambang setelah catatan berubah. Idempoten: pembinaan terbuka
 * tidak dibuat dua kali, dan notifikasi hanya dikirim saat status berubah.
 */
function evaluasi_ambang_siswa(int $siswaId, int $semesterId): array
{
    $siswa = db_satu(
        'SELECT s.nama, k.nama AS kelas, s.wali_pengguna_id FROM siswa s
         LEFT JOIN kelas k ON k.id = s.kelas_id WHERE s.id = ? LIMIT 1',
        [$siswaId]
    );
    if ($siswa === null) {
        return ['pembinaan' => false, 'negatif' => false];
    }
    $nama = (string) $siswa['nama'];
    $kelas = (string) ($siswa['kelas'] ?? '-');
    $skor = skor_per_aspek($siswaId, $semesterId);
    $ambangPersen = (float) pengaturan('ambang_pembinaan_persen', '60');
    $ambangSkor = (int) pengaturan('ambang_pembinaan_skor', '0');

    $perluPembinaan = false;
    $skorNegatif = false;
    $ringkasan = [];
    foreach ($skor as $s) {
        if ((float) $s['persentase'] < $ambangPersen) {
            $perluPembinaan = true;
            $ringkasan[] = $s['aspek'] . ' ' . $s['persentase'] . '%';
        }
        if ((int) $s['skor'] < $ambangSkor) {
            $skorNegatif = true;
        }
    }

    $waliKelas = wali_kelas_siswa($siswaId);
    if ($perluPembinaan) {
        $ada = (int) db_nilai(
            "SELECT COUNT(*) FROM pembinaan WHERE siswa_id = ? AND semester_id = ? AND status <> 'selesai'",
            [$siswaId, $semesterId],
            0
        );
        if ($ada === 0) {
            db_sisip('pembinaan', [
                'siswa_id' => $siswaId,
                'semester_id' => $semesterId,
                'pemicu' => 'Persentase aspek di bawah ' . $ambangPersen . '%',
                'tindak_lanjut' => implode(', ', $ringkasan),
                'status' => 'baru',
                'dicatat_oleh' => (int) (auth_pengguna()['id'] ?? 0) ?: null,
                'tanggal' => date('Y-m-d'),
            ]);
            if ($waliKelas > 0) {
                notifikasi_kirim(
                    $waliKelas,
                    'Siswa masuk daftar pembinaan',
                    $nama . ' (' . $kelas . ') perlu pembinaan: ' . implode(', ', $ringkasan) . '.',
                    url('pages/guru/pembinaan.php')
                );
            }
        }
    }
    if ($skorNegatif) {
        $sudah = (int) db_nilai(
            "SELECT COUNT(*) FROM pembinaan WHERE siswa_id = ? AND semester_id = ? AND pemicu = 'Skor negatif' AND status <> 'selesai'",
            [$siswaId, $semesterId],
            0
        );
        if ($sudah === 0) {
            db_sisip('pembinaan', [
                'siswa_id' => $siswaId,
                'semester_id' => $semesterId,
                'pemicu' => 'Skor negatif',
                'tindak_lanjut' => 'Skor salah satu aspek di bawah ' . $ambangSkor . '.',
                'status' => 'baru',
                'dicatat_oleh' => (int) (auth_pengguna()['id'] ?? 0) ?: null,
                'tanggal' => date('Y-m-d'),
            ]);
            $penerima = id_pengguna_peran(['bk']);
            $waliMurid = (int) ($siswa['wali_pengguna_id'] ?? 0);
            if ($waliMurid > 0) {
                $penerima[] = $waliMurid;
            }
            notifikasi_ke_banyak(
                $penerima,
                'Skor negatif perlu pendampingan',
                'Skor karakter ' . $nama . ' (' . $kelas . ') berada di bawah ' . $ambangSkor . '. '
                . 'Perlu pendampingan BK dan wali murid.',
                url('pages/umum/rekap.php')
            );
        }
    }
    return ['pembinaan' => $perluPembinaan, 'negatif' => $skorNegatif];
}

/** Ringkasan siswa pembinaan untuk kelas yang diampu guru. */
function siswa_perlu_pembinaan(array $idKelas, int $semesterId): array
{
    if ($idKelas === [] || $semesterId <= 0) {
        return [];
    }
    $tanda = implode(', ', array_fill(0, count($idKelas), '?'));
    $daftar = db_semua(
        'SELECT s.id, s.nis, s.nama, k.nama AS kelas FROM siswa s
         LEFT JOIN kelas k ON k.id = s.kelas_id
         WHERE s.aktif = 1 AND s.kelas_id IN (' . $tanda . ') ORDER BY k.nama, s.nama',
        $idKelas
    );
    $ambang = (float) pengaturan('ambang_pembinaan_persen', '60');
    $hasil = [];
    foreach ($daftar as $s) {
        $skor = skor_per_aspek((int) $s['id'], $semesterId);
        $terendah = null;
        foreach ($skor as $a) {
            if ($terendah === null || (float) $a['persentase'] < (float) $terendah['persentase']) {
                $terendah = $a;
            }
        }
        if ($terendah !== null && (float) $terendah['persentase'] < $ambang) {
            $s['aspek_terendah'] = $terendah['aspek'];
            $s['persentase'] = $terendah['persentase'];
            $s['predikat'] = $terendah['predikat'];
            $hasil[] = $s;
        }
    }
    return $hasil;
}

/** Empat aspek yang TIDAK memiliki katalog otomatis (dinilai wali kelas bulanan). */
function aspek_berkala(): array
{
    return db_semua(
        'SELECT a.id, a.kode, a.nama FROM aspek a
         WHERE NOT EXISTS (SELECT 1 FROM katalog_poin k WHERE k.aspek_id = a.id AND k.otomatis = 1)
         ORDER BY a.urutan, a.id'
    );
}

/** Poin penilaian bulanan, seluruhnya dapat dikalibrasi admin. */
function poin_penilaian(string $nilai): ?int
{
    $peta = [
        'kurang' => 'penilaian_kurang',
        'cukup' => 'penilaian_cukup',
        'baik' => 'penilaian_baik',
        'sangat_baik' => 'penilaian_sangat_baik',
    ];
    if (!isset($peta[$nilai])) {
        return null;
    }
    return (int) pengaturan($peta[$nilai], '0');
}

/**
 * Simpan penilaian bulanan. Unik per semester/siswa/aspek/bulan.
 * Perubahan nilai membatalkan catatan lama (tidak dihapus) lalu membuat
 * catatan baru bersumber `berkala`, sehingga jejak perubahan tetap ada.
 */
function simpan_penilaian_bulanan(
    int $semesterId,
    int $siswaId,
    int $aspekId,
    string $bulan,
    string $nilai,
    int $penilaiId
): bool {
    if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $bulan)) {
        return false;
    }
    $poin = poin_penilaian($nilai);
    if ($poin === null) {
        return false;
    }
    return db_transaksi(function () use ($semesterId, $siswaId, $aspekId, $bulan, $nilai, $poin, $penilaiId): bool {
        $lama = db_satu(
            'SELECT id, catatan_id FROM penilaian_bulanan
             WHERE semester_id = ? AND siswa_id = ? AND aspek_id = ? AND bulan = ? FOR UPDATE',
            [$semesterId, $siswaId, $aspekId, $bulan]
        );
        if ($lama !== null) {
            if ((int) ($lama['catatan_id'] ?? 0) > 0) {
                batalkan_catatan((int) $lama['catatan_id'], 'Penilaian bulanan diperbarui menjadi ' . $nilai, $penilaiId);
            }
            db_jalankan('DELETE FROM penilaian_bulanan WHERE id = ?', [(int) $lama['id']]);
        }
        $kunci = 'berkala:' . $semesterId . ':' . $siswaId . ':' . $aspekId . ':' . $bulan . ':' . bin2hex(random_bytes(4));
        $catatanId = tambah_catatan([
            'semester_id' => $semesterId,
            'siswa_id' => $siswaId,
            'aspek_id' => $aspekId,
            'poin' => $poin,
            'sumber' => 'berkala',
            'kunci_unik' => $kunci,
            'pencatat_id' => $penilaiId,
            'keterangan' => 'Penilaian bulanan ' . $bulan . ': ' . $nilai,
            'tanggal' => $bulan . '-01',
        ]);
        db_sisip('penilaian_bulanan', [
            'semester_id' => $semesterId,
            'siswa_id' => $siswaId,
            'aspek_id' => $aspekId,
            'bulan' => $bulan,
            'nilai' => $nilai,
            'poin' => $poin,
            'penilai_id' => $penilaiId,
            'catatan_id' => $catatanId,
        ]);
        return true;
    });
}
