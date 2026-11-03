<?php
declare(strict_types=1);

function menu_samping_utama(string $peran): array
{
    if ($peran === 'admin') {
        return [
            ['label' => 'Dashboard', 'url' => 'pages/admin/dashboard.php', 'ikon' => 'beranda'],
            ['label' => 'Siswa', 'url' => 'pages/admin/siswa.php', 'ikon' => 'siswa'],
            ['label' => 'Kelas', 'url' => 'pages/admin/kelas.php', 'ikon' => 'kelas'],
            ['label' => 'Impor CSV', 'url' => 'pages/admin/impor_siswa.php', 'ikon' => 'unggah'],
            ['label' => 'Cetak Kartu QR', 'url' => 'pages/admin/kartu.php', 'ikon' => 'cetak'],
            ['label' => 'Pengguna', 'url' => 'pages/admin/pengguna.php', 'ikon' => 'pengguna'],
            ['label' => 'Katalog Poin', 'url' => 'pages/admin/katalog.php', 'ikon' => 'katalog'],
            ['label' => 'Pengaturan', 'url' => 'pages/admin/pengaturan.php', 'ikon' => 'pengaturan'],
            ['label' => 'Semester & Sertifikat', 'url' => 'pages/admin/sertifikat.php', 'ikon' => 'sertifikat'],
            ['label' => 'Hari Libur', 'url' => 'pages/admin/hari_libur.php', 'ikon' => 'libur'],
            ['label' => 'Rekap Kehadiran', 'url' => 'pages/umum/rekap_kehadiran.php', 'ikon' => 'rekap'],
            ['label' => 'Laporan', 'url' => 'pages/umum/laporan.php', 'ikon' => 'grafik'],
            ['label' => 'Proses Manual Cron', 'url' => 'pages/admin/proses_cron.php', 'ikon' => 'cron'],
            ['label' => 'Antrian WA', 'url' => 'pages/admin/antrian_wa.php', 'ikon' => 'wa'],
            ['label' => 'Banding Siswa', 'url' => 'pages/guru/banding.php', 'ikon' => 'banding'],
            ['label' => 'Siswa Pembinaan', 'url' => 'pages/guru/pembinaan.php', 'ikon' => 'pembinaan'],
            ['label' => 'Log Akses', 'url' => 'pages/admin/log_akses.php', 'ikon' => 'log'],
        ];
    }
    if ($peran === 'bk') {
        return [
            ['label' => 'Dashboard BK', 'url' => 'pages/bk/beranda.php', 'ikon' => 'beranda'],
            ['label' => 'Siswa Pembinaan', 'url' => 'pages/guru/pembinaan.php', 'ikon' => 'pembinaan'],
            ['label' => 'Tindak Lanjut', 'url' => 'pages/bk/tindak_lanjut.php', 'ikon' => 'catatan'],
            ['label' => 'Laporan', 'url' => 'pages/umum/laporan.php', 'ikon' => 'grafik'],
            ['label' => 'Rekap Kehadiran', 'url' => 'pages/umum/rekap_kehadiran.php', 'ikon' => 'rekap'],
        ];
    }
    if ($peran === 'kepsek') {
        return [
            ['label' => 'Dashboard', 'url' => 'pages/kepsek/beranda.php', 'ikon' => 'beranda'],
            ['label' => 'Laporan', 'url' => 'pages/umum/laporan.php', 'ikon' => 'grafik'],
            ['label' => 'Rekap Kehadiran', 'url' => 'pages/umum/rekap_kehadiran.php', 'ikon' => 'rekap'],
            ['label' => 'Rekap Skor', 'url' => 'pages/umum/rekap.php', 'ikon' => 'rekap'],
        ];
    }
    return [
        ['label' => 'Beranda', 'url' => 'pages/umum/beranda.php', 'ikon' => 'beranda'],
        ['label' => 'Siswa', 'url' => 'pages/umum/siswa.php', 'ikon' => 'siswa'],
        ['label' => 'Rekap', 'url' => 'pages/umum/rekap.php', 'ikon' => 'rekap'],
        ['label' => 'Notifikasi', 'url' => 'pages/umum/notifikasi.php', 'ikon' => 'lonceng'],
    ];
}

function menu_bawah_utama(): array
{
    return [
        ['id' => 'beranda', 'label' => 'Beranda', 'ikon' => 'beranda', 'url' => 'pages/umum/beranda.php'],
        ['id' => 'siswa', 'label' => 'Siswa', 'ikon' => 'siswa', 'url' => 'pages/umum/siswa.php'],
        ['id' => 'rekap', 'label' => 'Rekap', 'ikon' => 'rekap', 'url' => 'pages/umum/rekap.php'],
        ['id' => 'profil', 'label' => 'Profil', 'ikon' => 'profil', 'url' => 'pages/profil.php'],
    ];
}

function nav_aktif_dari_skrip(): string
{
    $skrip = strtolower(basename((string) ($_SERVER['SCRIPT_NAME'] ?? '')));
    foreach (['beranda', 'dashboard'] as $kunci) {
        if (str_contains($skrip, $kunci)) {
            return 'beranda';
        }
    }
    foreach (['siswa', 'impor', 'kelas'] as $kunci) {
        if (str_contains($skrip, $kunci)) {
            return 'siswa';
        }
    }
    foreach (['rekap', 'laporan'] as $kunci) {
        if (str_contains($skrip, $kunci)) {
            return 'rekap';
        }
    }
    foreach (['profil', 'ganti_password'] as $kunci) {
        if (str_contains($skrip, $kunci)) {
            return 'profil';
        }
    }
    // Halaman lain (katalog, pengaturan, cron, log) tidak menandai kotak kuning.
    return '';
}

function jumlah_notifikasi_belum_dibaca(int $penggunaId): int
{
    try {
        return (int) db_nilai(
            'SELECT COUNT(*) FROM notifikasi WHERE pengguna_id = ? AND dibaca = 0',
            [$penggunaId],
            0
        );
    } catch (Throwable $galat) {
        return 0;
    }
}

/** Tag kepala PWA: manifest, ikon, warna tema, dan basis URL untuk service worker. */
function pwa_meta(): string
{
    $basis = basis_url();
    $manifest = url('manifest.webmanifest');
    $ikon192 = asset('img/icon-192.png');
    $ikon512 = asset('img/icon-512.png');
    $apple = asset('img/apple-touch-icon.png');
    $favicon = asset('img/favicon.png');
    $svg = asset('img/logo-sidipa.svg');

    return '<meta name="theme-color" content="#FFF3E4">'
        . '<meta name="sidipa-base" content="' . e($basis) . '">'
        . '<meta name="description" content="SiDiPa — penilaian karakter siswa, kehadiran QR, ibadah, dan sertifikat digital.">'
        . '<meta name="apple-mobile-web-app-capable" content="yes">'
        . '<meta name="mobile-web-app-capable" content="yes">'
        . '<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">'
        . '<meta name="apple-mobile-web-app-title" content="SiDiPa">'
        . '<meta name="application-name" content="SiDiPa">'
        . '<meta name="msapplication-TileColor" content="#FFD93D">'
        . '<meta name="format-detection" content="telephone=no">'
        . '<link rel="manifest" href="' . e($manifest) . '">'
        . '<link rel="icon" type="image/png" sizes="64x64" href="' . e($favicon) . '">'
        . '<link rel="icon" type="image/svg+xml" href="' . e($svg) . '">'
        . '<link rel="apple-touch-icon" href="' . e($apple) . '">'
        . '<link rel="apple-touch-icon" sizes="192x192" href="' . e($ikon192) . '">'
        . '<link rel="apple-touch-icon" sizes="512x512" href="' . e($ikon512) . '">';
}

/** Skrip PWA yang dimuat di akhir setiap halaman. */
function pwa_skrip(): string
{
    return '<script src="' . e(asset('js/pwa.js')) . '" defer></script>';
}

/** Kepala halaman umum: header, sidebar desktop, judul, pesan flash. */
function halaman_atas(string $judul, string $keterangan = '', array $opsi = []): void
{
    $pengguna = auth_pengguna();
    $peran = $pengguna['peran'] ?? 'tamu';
    $nama = $pengguna['nama'] ?? 'Pengguna';
    $skrip = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    $sidebar = in_array($peran, ['admin', 'bk', 'kepsek'], true);
    $aktif = (string) ($opsi['nav'] ?? nav_aktif_dari_skrip());
    $namaSekolah = pengaturan('nama_sekolah', 'SMK SiDiPa');
    $belum = $pengguna ? jumlah_notifikasi_belum_dibaca((int) $pengguna['id']) : 0;

    echo '<!doctype html><html lang="id"><head><meta charset="utf-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">';
    echo pwa_meta();
    echo csrf_meta();
    echo '<title>' . e($judul) . ' - SiDiPa</title>';
    echo '<link rel="preconnect" href="https://fonts.googleapis.com">';
    echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>';
    echo '<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">';
    echo '<link rel="stylesheet" href="' . e(asset('css/sidipa.css')) . '">';
    echo '</head><body class="peran-' . e($peran) . '"><div class="kerangka">';

    if ($sidebar) {
        echo '<aside class="sidebar" aria-label="Menu utama"><a class="merek-samping" href="' . e(url(auth_landing($peran))) . '">';
        echo '<span class="kotak-logo">' . ikon('sekolah', 26) . '</span><span>SiDiPa<small>' . e($peran === 'admin' ? 'Panel admin' : ($peran === 'bk' ? 'Bimbingan konseling' : 'Kepala sekolah')) . '</small></span></a>';
        echo '<nav class="nav-samping"><p class="label-kecil">MENU</p>';
        foreach (menu_samping_utama($peran) as $item) {
            $isAktif = basename($item['url']) === $skrip ? ' aria-current="page" class="item-samping aktif"' : ' class="item-samping"';
            echo '<a' . $isAktif . ' href="' . e(url($item['url'])) . '">' . ikon($item['ikon'], 22)
                . '<span>' . e($item['label']) . '</span></a>';
        }
        echo '</nav><div class="kaki-samping"><p class="label-kecil">' . e($namaSekolah) . '</p>';
        echo '<a class="item-samping" href="' . e(url('pages/logout.php?csrf_token=' . rawurlencode(csrf_token()))) . '">' . ikon('keluar', 22) . '<span>Keluar</span></a></div></aside>';
    }

    echo '<div class="konten-utama"><header class="kepala-aplikasi">';
    echo '<a class="merek" href="' . e(url(auth_landing($peran))) . '">';
    echo '<span class="kotak-logo">' . ikon('sekolah', 24) . '</span><span class="teks-merek">SiDiPa<small>' . e($namaSekolah) . '</small></span></a>';
    echo '<div class="kepala-kanan">';
    echo '<span class="status-jaringan" data-status-jaringan hidden role="status"></span>';
    echo '<button type="button" class="tombol-pasang" data-pasang-pwa hidden aria-label="Pasang aplikasi SiDiPa">'
        . ikon('unduh', 20) . '<span>Pasang</span></button>';
    echo '<span class="peran-badge">' . e(label_peran($peran)) . '</span>';
    echo '<a class="tombol-ikon" href="' . e(url('pages/umum/notifikasi.php')) . '" aria-label="Notifikasi' . ($belum > 0 ? ', ' . $belum . ' belum dibaca' : '') . '">';
    echo ikon('lonceng', 22);
    if ($belum > 0) {
        echo '<span class="lencana">' . $belum . '</span>';
    }
    echo '</a>';
    echo '<a class="avatar" href="' . e(url('pages/profil.php')) . '" aria-label="Profil ' . e($nama) . '">' . e(inisial($nama)) . '</a>';
    echo '</div></header><main class="isi-halaman" id="konten">';

    if (empty($opsi['tanpa_judul'])) {
        echo '<div class="kepala-judul"><div><p class="label-kecil">' . e($keterangan !== '' ? $keterangan : 'SiDiPa') . '</p>';
        echo '<h1>' . e($judul) . '</h1></div>';
        if (!empty($opsi['aksi'])) {
            echo '<div class="kepala-aksi">' . $opsi['aksi'] . '</div>';
        }
        echo '</div>';
    }

    foreach (ambil_flash() as $pesan) {
        $jenis = $pesan['jenis'] === 'galat' ? 'galat' : ($pesan['jenis'] === 'info' ? 'info' : 'sukses');
        echo '<div class="pesan pesan-' . $jenis . '" role="status">' . ikon($jenis === 'galat' ? 'peringatan' : 'centang', 20)
            . '<span>' . e($pesan['pesan']) . '</span>'
            . '<button type="button" class="tutup-pesan" data-tutup-pesan aria-label="Tutup pesan">' . ikon('silang', 18) . '</button></div>';
    }
}

function halaman_bawah(): void
{
    $peran = auth_peran();
    $aktif = nav_aktif_dari_skrip();
    echo '</main><nav class="nav-bawah" aria-label="Navigasi bawah">';
    foreach (menu_bawah_utama() as $item) {
        $tujuan = $item['url'];
        if ($item['id'] === 'beranda') {
            $tujuan = auth_landing($peran);
        }
        if ($item['id'] === 'siswa' && $peran === 'admin') {
            $tujuan = 'pages/admin/siswa.php';
        }
        $kelas = 'item-bawah' . ($item['id'] === $aktif ? ' aktif' : '');
        echo '<a class="' . $kelas . '"' . ($item['id'] === $aktif ? ' aria-current="page"' : '')
            . ' href="' . e(url($tujuan)) . '">' . ikon($item['ikon'], 22) . '<span>' . e($item['label']) . '</span></a>';
    }
    echo '</nav></div></div><script src="' . e(asset('js/sidipa.js')) . '" defer></script>'
        . pwa_skrip() . '</body></html>';
}

function label_peran(string $peran): string
{
    return match ($peran) {
        'admin' => 'Admin',
        'guru' => 'Guru',
        'bk' => 'BK',
        'kepsek' => 'Kepala sekolah',
        'siswa' => 'Siswa',
        'wali' => 'Wali murid',
        default => 'Tamu',
    };
}

/** Judul section yang sering dipakai halaman admin. */
function bagian_judul(string $teks, string $keterangan = ''): string
{
    return '<div class="bagian-judul"><h2>' . e($teks) . '</h2>'
        . ($keterangan !== '' ? '<p>' . e($keterangan) . '</p>' : '') . '</div>';
}
