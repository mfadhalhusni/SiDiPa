<?php
declare(strict_types=1);

/** Pustaka ikon SVG inline bergaris tebal. DILARANG memakai emoji. */
function ikon(string $nama, int $ukuran = 24, string $kelas = ''): string
{
    static $peta = null;
    if ($peta === null) {
        $peta = [
            'beranda' => '<path d="M3 10.6 12 3l9 7.6"/><path d="M5.5 9.5V21h13V9.5"/><path d="M9.5 21v-6.5h5V21"/>',
            'siswa' => '<circle cx="12" cy="8" r="3.5"/><path d="M4.5 20c.6-4 3.5-6 7.5-6s6.9 2 7.5 6"/><path d="M3 20h18"/>',
            'rekap' => '<path d="M4 20h16"/><rect x="5" y="11" width="4" height="9"/><rect x="10" y="6" width="4" height="14"/><rect x="15" y="13" width="4" height="7"/>',
            'profil' => '<circle cx="12" cy="8" r="3.6"/><path d="M4.5 20.5c.8-4.2 3.6-6.3 7.5-6.3s6.7 2.1 7.5 6.3"/><circle cx="12" cy="12" r="9.2"/>',
            'lonceng' => '<path d="M18 9a6 6 0 0 0-12 0c0 6-2 7-2 7h16s-2-1-2-7"/><path d="M10 20a2 2 0 0 0 4 0"/>',
            'plus' => '<path d="M12 4v16"/><path d="M4 12h16"/>',
            'minus' => '<path d="M4 12h16"/>',
            'cari' => '<circle cx="10.5" cy="10.5" r="6.5"/><path d="m15.5 15.5 5 5"/>',
            'simpan' => '<path d="M4 4h13l3 3v13H4z"/><path d="M8 4v6h8V4"/><path d="M8 20v-6h8v6"/>',
            'hapus' => '<path d="M4 7h16"/><path d="M9 7V4h6v3"/><path d="M6 7l1 13h10l1-13"/><path d="M10 11v6M14 11v6"/>',
            'ubah' => '<path d="M4 20h4L20 8l-4-4L4 16z"/><path d="m14 6 4 4"/>',
            'centang' => '<path d="m4 12 5 5L20 6"/>',
            'silang' => '<path d="M5 5l14 14"/><path d="M19 5 5 19"/>',
            'info' => '<circle cx="12" cy="12" r="9"/><path d="M12 11v6"/><path d="M12 7.5h.01"/>',
            'peringatan' => '<path d="M12 3 2.5 20h19z"/><path d="M12 9v5"/><path d="M12 17h.01"/>',
            'kunci' => '<rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/><path d="M12 15v2"/>',
            'keluar' => '<path d="M10 4H5v16h5"/><path d="M14 8l4 4-4 4"/><path d="M9 12h9"/>',
            'masuk' => '<path d="M14 4h5v16h-5"/><path d="M10 8l-4 4 4 4"/><path d="M6 12h9"/>',
            'pulang' => '<path d="M14 4h5v16h-5"/><path d="M10 8l4 4-4 4"/><path d="M15 12H6"/>',
            'sekolah' => '<path d="M3 9 12 4l9 5"/><path d="M5 9v11h14V9"/><path d="M9 20v-6h6v6"/><path d="M12 4v3"/>',
            'kelas' => '<rect x="3" y="4" width="18" height="16" rx="2"/><path d="M3 9h18"/><path d="M8 13h3M8 16h6"/>',
            'pengguna' => '<circle cx="9" cy="8" r="3.2"/><path d="M3 20c.5-3.6 2.8-5.5 6-5.5s5.5 1.9 6 5.5"/><path d="M16 5.5a3 3 0 0 1 0 5.8"/><path d="M17 15c2.3.5 3.7 2.2 4 5"/>',
            'katalog' => '<path d="M5 4h11l3 3v13H5z"/><path d="M8 9h8M8 13h8M8 17h5"/>',
            'pengaturan' => '<circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3M4.9 4.9l2.1 2.1M17 17l2.1 2.1M19.1 4.9 17 7M7 17l-2.1 2.1"/>',
            'log' => '<path d="M6 3h9l4 4v14H6z"/><path d="M9 11h7M9 15h7M9 7h4"/>',
            'qr' => '<rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><path d="M14 14h3v3h-3zM18 18h3v3h-3zM14 20h1"/>',
            'scan' => '<path d="M4 8V5h3M17 5h3v3M20 16v3h-3M7 19H4v-3"/><path d="M3 12h18"/><path d="M7 8h2v2H7zM11 8h2v2h-2zM15 8h2v2h-2z"/>',
            'kamera' => '<rect x="3" y="7" width="18" height="13" rx="2"/><circle cx="12" cy="13.5" r="3.5"/><path d="M8 7l1.5-3h5L16 7"/>',
            'sholat' => '<path d="M3 20h18"/><path d="M5 20v-7h14v7"/><path d="M12 3c2.5 2 4 4 4 6.5a4 4 0 0 1-8 0C8 7 9.5 5 12 3z"/><path d="M12 13v7"/>',
            'pembinaan' => '<path d="M12 21s-7-4.4-7-9.5A3.5 3.5 0 0 1 12 7a3.5 3.5 0 0 1 7 4.5C19 16.6 12 21 12 21z"/>',
            'banding' => '<path d="M6 4h12v12H9l-3 4z"/><path d="M9 8h6M9 11h4"/>',
            'catatan' => '<path d="M6 3h9l4 4v14H6z"/><path d="M9 10h7M9 14h7M9 18h4"/>',
            'izin' => '<rect x="4" y="5" width="16" height="16" rx="2"/><path d="M8 3v4M16 3v4M4 10h16"/><path d="m9 15 2 2 4-4"/>',
            'penilaian' => '<path d="M4 4h16v16H4z"/><path d="M8 9h8M8 13h5"/><path d="m14 16 1.5 1.5L18 15"/>',
            'sertifikat' => '<rect x="3" y="5" width="18" height="13" rx="1"/><path d="M7 9h10M7 12h7"/><circle cx="17.5" cy="15" r="2"/><path d="m16 17-1 4 2.5-1.5L20 21l-1-4"/>',
            'libur' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M8 3v4M16 3v4M3 10h18"/><path d="M9 15l2 2 4-4"/>',
            'cron' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3.5 2"/>',
            'wa' => '<path d="M4 20l1.5-4A8 8 0 1 1 9 19z"/><path d="M9 9.5c.3 2 2.5 4 4.5 4.5l1-1 2 .8c-.4 1.5-2 2.2-3.5 2A7.5 7.5 0 0 1 8 13c-.2-1.5.5-3.1 2-3.5l.8 2z"/>',
            'grafik' => '<path d="M4 19V5M4 19h16"/><path d="m6 15 4-5 3.5 3L20 7"/>',
            'bintang' => '<path d="m12 3 2.7 5.7 6.3.9-4.5 4.4 1 6.2-5.5-3-5.5 3 1-6.2L3 9.6l6.3-.9z"/>',
            'unduh' => '<path d="M12 3v12"/><path d="m7 11 5 5 5-5"/><path d="M4 20h16"/>',
            'unggah' => '<path d="M12 17V5"/><path d="m7 9 5-5 5 5"/><path d="M4 20h16"/>',
            'cetak' => '<path d="M7 8V3h10v5"/><rect x="3" y="8" width="18" height="8" rx="2"/><path d="M7 14h10v7H7z"/><path d="M17 11h.01"/>',
            'tanggal' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M8 3v4M16 3v4M3 10h18"/><path d="M8 14h2v2H8zM14 14h2v2h-2z"/>',
            'jam' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3.5 2"/>',
            'buku' => '<path d="M4 5c2.5-1.5 5-1.5 8 0v15c-3-1.5-5.5-1.5-8 0z"/><path d="M20 5c-2.5-1.5-5-1.5-8 0v15c3-1.5 5.5-1.5 8 0z"/>',
            'mata' => '<path d="M2 12s3.8-6 10-6 10 6 10 6-3.8 6-10 6-10-6-10-6z"/><circle cx="12" cy="12" r="2.8"/>',
            'refresh' => '<path d="M20 7v5h-5"/><path d="M4 17v-5h5"/><path d="M6.5 9A7 7 0 0 1 19 8l1 4M17.5 15A7 7 0 0 1 5 16l-1-4"/>',
            'filter' => '<path d="M3 5h18l-7 8v6l-4 2v-8z"/>',
            'tanya' => '<circle cx="12" cy="12" r="9"/><path d="M9.5 9a2.6 2.6 0 1 1 4 2.3c-1 .7-1.5 1.2-1.5 2.2"/><path d="M12 17h.01"/>',
            'panah_kiri' => '<path d="m14 5-7 7 7 7"/>',
            'panah_kanan' => '<path d="m10 5 7 7-7 7"/>',
            'titik_tiga' => '<circle cx="5" cy="12" r="1.4"/><circle cx="12" cy="12" r="1.4"/><circle cx="19" cy="12" r="1.4"/>',
            'tanda_tangan' => '<path d="M3 17c3-1 4-9 6-9s1 7 3 7 2-5 4-5 2 4 5 3"/><path d="M3 21h18"/>',
        ];
    }
    $isi = $peta[$nama] ?? $peta['info'];
    $kelasAtribut = $kelas !== '' ? ' class="' . e($kelas) . '"' : '';
    return '<svg' . $kelasAtribut . ' width="' . $ukuran . '" height="' . $ukuran . '" viewBox="0 0 24 24" '
        . 'fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" '
        . 'stroke-linejoin="round" aria-hidden="true" focusable="false">' . $isi . '</svg>';
}
