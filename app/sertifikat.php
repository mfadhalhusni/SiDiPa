<?php
declare(strict_types=1);

/*
 |--------------------------------------------------------------------------
 | Tahap 4: penutupan periode semester, snapshot skor, penerbitan dan
 | pembatalan sertifikat karakter siswa.
 |
 | Aturan yang dijaga ketat:
 |  - Sertifikat HANYA terbit setelah admin menutup periode input semester.
 |  - data_json adalah SNAPSHOT: tidak pernah diubah setelah terbit.
 |  - Kode berformat SDP-TAHUNSEM-NIS-ACAK4 dan unik.
 |  - Pembatalan menyimpan alasan, pelaku, dan waktu (bukan menghapus baris).
 |  - verifikasi.php publik hanya membaca kunci `aspek` dari snapshot.
 |--------------------------------------------------------------------------
 */

/** Periode input masih dibuka bila ada semester aktif berstatus berjalan. */
function semester_input_terbuka(): bool
{
    return db_satu("SELECT id FROM semester WHERE aktif = 1 AND status = 'berjalan' LIMIT 1") !== null;
}

/** Panggil di halaman/endpoint yang menulis poin atau kehadiran. */
function cek_input_semester(string $untuk = 'halaman'): void
{
    if (semester_input_terbuka()) {
        return;
    }
    $pesan = 'Periode input semester sudah ditutup admin. Buka semester baru di menu Semester & Sertifikat sebelum mencatat poin atau memindai kehadiran.';
    if ($untuk === 'api') {
        json_keluar(['ok' => false, 'pesan' => $pesan], 423);
    }
    auth_403($pesan);
}

/** URL absolut untuk QR verifikasi publik (mendukung subdirektori cPanel). */
function url_publik(string $jalur): string
{
    $skema = https_aktif() ? 'https' : 'http';
    $host = teks($_SERVER['HTTP_HOST'] ?? 'localhost', 255);
    if ($host === '') {
        $host = 'localhost';
    }
    return $skema . '://' . $host . url($jalur);
}

/** SDP-TAHUNSEM-NIS-ACAK4, mis. SDP-20261-20260100-A1B2. */
function kode_sertifikat_baru(array $semester, string $nis): string
{
    $angka = preg_replace('/\D/', '', (string) ($semester['tahun_ajaran'] ?? ''));
    $tahun = substr($angka !== '' ? $angka : date('Y'), 0, 4);
    $penanda = $tahun . (int) ($semester['semester'] ?? 1);
    $nisBersih = preg_replace('/[^A-Za-z0-9]/', '', $nis);
    if ($nisBersih === '') {
        $nisBersih = 'SISWA';
    }
    do {
        $acak = strtoupper(substr(bin2hex(random_bytes(4)), 0, 4));
        $kode = 'SDP-' . $penanda . '-' . $nisBersih . '-' . $acak;
    } while (db_satu('SELECT id FROM sertifikat WHERE kode = ? LIMIT 1', [$kode]) !== null);
    return $kode;
}

/**
 * Ambil data siswa, kelas, wali kelas, sekolah, dan skor per aspek.
 * Hasilnya dibekukan sebagai JSON saat terbit.
 */
function snapshot_sertifikat(int $siswaId, int $semesterId): array
{
    $siswa = db_satu(
        'SELECT s.nama, s.nis, COALESCE(k.nama, \'-\') AS kelas, k.wali_kelas_id,
                COALESCE(p.nama, \'\') AS nama_wali_kelas
         FROM siswa s
         LEFT JOIN kelas k ON k.id = s.kelas_id
         LEFT JOIN pengguna p ON p.id = k.wali_kelas_id
         WHERE s.id = ? LIMIT 1',
        [$siswaId]
    );
    if ($siswa === null) {
        throw new RuntimeException('Data siswa tidak ditemukan.');
    }
    $semester = semester_dari_id($semesterId);
    if ($semester === null) {
        throw new RuntimeException('Semester tidak ditemukan.');
    }
    $aspek = [];
    foreach (skor_per_aspek($siswaId, $semesterId) as $a) {
        $aspek[] = [
            'aspek' => $a['aspek'],
            'kode' => $a['kode'],
            'skor' => $a['skor'],
            'target' => $a['target'],
            'persentase' => $a['persentase'],
            'predikat' => $a['predikat'],
            'keterangan' => $a['keterangan'],
        ];
    }
    return [
        'versi' => 1,
        'dibuat_pada' => date('c'),
        'sekolah' => [
            'nama' => (string) pengaturan('nama_sekolah', 'SMK SiDiPa'),
            'alamat' => (string) pengaturan('alamat_sekolah', ''),
            'logo' => (string) pengaturan('logo_sekolah', 'public/img/logo-sidipa.svg'),
            'nama_kepsek' => (string) pengaturan('nama_kepsek', 'Kepala Sekolah'),
            'nip_kepsek' => (string) pengaturan('nip_kepsek', ''),
            'ttd_kepsek' => (string) pengaturan('ttd_kepsek', 'public/img/tanda-tangan.svg'),
            'ttd_wali' => (string) pengaturan('ttd_wali_default', 'public/img/tanda-tangan.svg'),
        ],
        'siswa' => [
            'nama' => (string) $siswa['nama'],
            'nis' => (string) $siswa['nis'],
            'kelas' => (string) $siswa['kelas'],
        ],
        'wali_kelas' => (string) ($siswa['nama_wali_kelas'] ?? ''),
        'semester' => [
            'nama' => (string) $semester['nama'],
            'tahun_ajaran' => (string) $semester['tahun_ajaran'],
            'semester' => (int) $semester['semester'],
            'tgl_mulai' => (string) $semester['tgl_mulai'],
            'tgl_selesai' => (string) $semester['tgl_selesai'],
        ],
        'aspek' => $aspek,
    ];
}

/**
 * Terbitkan (atau terbitkan ulang setelah dibatalkan) satu sertifikat.
 * Mengembalikan ['ok','pesan','kode','id','baru'].
 */
function terbitkan_sertifikat(int $siswaId, int $semesterId, int $oleh): array
{
    $semester = semester_dari_id($semesterId);
    if ($semester === null) {
        return ['ok' => false, 'pesan' => 'Semester tidak ditemukan.', 'kode' => '', 'id' => 0, 'baru' => false];
    }
    if ((string) $semester['status'] !== 'ditutup') {
        return ['ok' => false, 'pesan' => 'Tutup periode input semester terlebih dahulu.', 'kode' => '', 'id' => 0, 'baru' => false];
    }
    $siswa = db_satu('SELECT id, nis, nama FROM siswa WHERE id = ? LIMIT 1', [$siswaId]);
    if ($siswa === null) {
        return ['ok' => false, 'pesan' => 'Siswa tidak ditemukan.', 'kode' => '', 'id' => 0, 'baru' => false];
    }
    $lama = db_satu(
        'SELECT id, kode, status FROM sertifikat WHERE siswa_id = ? AND semester_id = ? LIMIT 1',
        [$siswaId, $semesterId]
    );
    if ($lama !== null && (string) $lama['status'] === 'terbit') {
        return ['ok' => false, 'pesan' => 'Sertifikat sudah terbit.', 'kode' => (string) $lama['kode'], 'id' => (int) $lama['id'], 'baru' => false];
    }

    try {
        $snapshot = snapshot_sertifikat($siswaId, $semesterId);
        $kode = kode_sertifikat_baru($semester, (string) $siswa['nis']);
        $json = json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $id = db_transaksi(function () use ($lama, $siswaId, $semesterId, $kode, $json, $oleh): int {
            $tanggal = date('Y-m-d');
            if ($lama !== null) {
                // Terbitkan ulang: snapshot lama diganti, bukan ditimpa sebagian.
                db_jalankan(
                    "UPDATE sertifikat SET kode = ?, data_json = ?, status = 'terbit',
                            diterbitkan_oleh = ?, tanggal_terbit = ?, dibuat_pada = NOW(),
                            alasan_batal = NULL, dibatalkan_oleh = NULL, dibatalkan_pada = NULL
                     WHERE id = ?",
                    [$kode, $json, $oleh, $tanggal, (int) $lama['id']]
                );
                return (int) $lama['id'];
            }
            return db_sisip('sertifikat', [
                'siswa_id' => $siswaId,
                'semester_id' => $semesterId,
                'kode' => $kode,
                'data_json' => $json,
                'status' => 'terbit',
                'diterbitkan_oleh' => $oleh,
                'tanggal_terbit' => $tanggal,
                'alasan_batal' => null,
                'dibatalkan_oleh' => null,
                'dibatalkan_pada' => null,
            ]);
        });
        log_akses('terbitkan_sertifikat', 'sertifikat', $id, $oleh);
        try {
            wa_antri($siswaId, 'sertifikat', [
                'tanggal' => tanggal_indonesia(date('Y-m-d'), false),
                'aspek' => 'Sertifikat karakter',
                'poin' => $kode,
            ]);
        } catch (Throwable $galatWa) {
            catat_kesalahan('WA sertifikat gagal masuk antrian: ' . $galatWa->getMessage());
        }
        return ['ok' => true, 'pesan' => 'Sertifikat ' . $siswa['nama'] . ' diterbitkan.', 'kode' => $kode, 'id' => $id, 'baru' => $lama === null];
    } catch (Throwable $galat) {
        catat_kesalahan('terbitkan_sertifikat: ' . $galat->getMessage());
        return ['ok' => false, 'pesan' => 'Gagal menerbitkan sertifikat: ' . $galat->getMessage(), 'kode' => '', 'id' => 0, 'baru' => false];
    }
}

/** Batalkan sertifikat dengan jejak audit lengkap. Baris tidak dihapus. */
function batalkan_sertifikat(int $id, int $oleh, string $alasan): array
{
    $alasan = trim($alasan);
    if ($alasan === '') {
        return ['ok' => false, 'pesan' => 'Alasan pembatalan wajib diisi.'];
    }
    return db_transaksi(function () use ($id, $oleh, $alasan): array {
        $baris = db_satu('SELECT id, status, kode FROM sertifikat WHERE id = ? FOR UPDATE', [$id]);
        if ($baris === null) {
            return ['ok' => false, 'pesan' => 'Sertifikat tidak ditemukan.'];
        }
        if ((string) $baris['status'] !== 'terbit') {
            return ['ok' => false, 'pesan' => 'Sertifikat sudah dibatalkan.'];
        }
        db_jalankan(
            "UPDATE sertifikat SET status = 'dibatalkan', alasan_batal = ?, dibatalkan_oleh = ?, dibatalkan_pada = NOW() WHERE id = ?",
            [teks($alasan, 255), $oleh, $id]
        );
        log_akses('batalkan_sertifikat', 'sertifikat', $id, $oleh);
        return ['ok' => true, 'pesan' => 'Sertifikat ' . $baris['kode'] . ' dibatalkan.'];
    });
}

/** Daftar sertifikat milik satu siswa untuk halaman siswa / wali. */
function sertifikat_siswa(int $siswaId): array
{
    return db_semua(
        'SELECT sr.id, sr.kode, sr.status, sr.tanggal_terbit, sm.nama AS semester, sm.tahun_ajaran
         FROM sertifikat sr JOIN semester sm ON sm.id = sr.semester_id
         WHERE sr.siswa_id = ? ORDER BY sr.tanggal_terbit DESC, sr.id DESC',
        [$siswaId]
    );
}

/** Sertifikat lengkap berdasar kode, untuk halaman cetak (butuh login). */
function sertifikat_dari_kode(string $kode): ?array
{
    return $kode === '' ? null : db_satu(
        'SELECT sr.*, s.id AS siswa_id, s.nis, s.nama AS nama_siswa
         FROM sertifikat sr JOIN siswa s ON s.id = sr.siswa_id
         WHERE sr.kode = ? LIMIT 1',
        [$kode]
    );
}
