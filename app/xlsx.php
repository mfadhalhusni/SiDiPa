<?php
declare(strict_types=1);

/*
 |--------------------------------------------------------------------------
 | Penulis XLSX (OOXML) murni PHP — TANPA Composer, TANPA ekstensi zip.
 |--------------------------------------------------------------------------
 | Berkas .xlsx adalah arsip ZIP berisi beberapa XML. Di sini ZIP ditulis
 | sendiri dengan metode STORE (tanpa kompresi) memakai crc32() bawaan PHP,
 | sehingga berjalan di shared hosting cPanel sekalipun.
 |
 | Sel teks memakai inlineStr (bukan sharedStrings) agar aman dari injeksi
 | formula: nilai yang diawali "=", "+", "-", "@" tetap terbaca sebagai teks.
 | Baris pertama dibuat tebal + dibekukan (freeze pane), ada autoFilter dan
 | lebar kolom. Beberapa lembar (sheet) didukung dalam satu berkas.
 |--------------------------------------------------------------------------
 */

/** Bersihkan karakter yang tidak valid di XML 1.0 lalu escape. */
function xlsx_teks(string $nilai): string
{
    $nilai = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $nilai) ?? '';
    return htmlspecialchars($nilai, ENT_XML1 | ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Nomor kolom 1-based -> huruf (1=A, 26=Z, 27=AA). */
function xlsx_kolom(int $n): string
{
    $huruf = '';
    while ($n > 0) {
        $n--;
        $huruf = chr(65 + ($n % 26)) . $huruf;
        $n = intdiv($n, 26);
    }
    return $huruf === '' ? 'A' : $huruf;
}

/** Satu sel <c>. Angka ditulis sebagai numerik, selainnya inlineStr. */
function xlsx_sel(string $ref, $nilai, bool $kepala): string
{
    $gaya = $kepala ? ' s="1"' : '';
    if (is_int($nilai) || is_float($nilai)) {
        return '<c r="' . $ref . '"' . $gaya . '><v>' . (string) $nilai . '</v></c>';
    }
    if ($nilai === null || $nilai === false) {
        $nilai = '';
    }
    $teks = xlsx_teks((string) $nilai);
    if ($teks === '') {
        return '<c r="' . $ref . '"' . $gaya . '/>';
    }
    return '<c r="' . $ref . '"' . $gaya . ' t="inlineStr"><is><t xml:space="preserve">'
        . $teks . '</t></is></c>';
}

/** XML satu lembar kerja. */
function xlsx_lembar_xml(array $lembar): string
{
    $header = array_values($lembar['header'] ?? []);
    $baris = $lembar['baris'] ?? [];
    $lebar = array_values($lembar['lebar'] ?? []);
    $jumlahKolom = max(1, count($header));
    foreach ($baris as $b) {
        $jumlahKolom = max($jumlahKolom, count((array) $b));
    }

    $cols = '<cols>';
    if ($lebar !== []) {
        foreach ($lebar as $i => $w) {
            $cols .= '<col min="' . ($i + 1) . '" max="' . ($i + 1)
                . '" width="' . max(6, min(80, (float) $w)) . '" customWidth="1"/>';
        }
    } else {
        $cols .= '<col min="1" max="' . $jumlahKolom . '" width="18" customWidth="1"/>';
    }
    $cols .= '</cols>';

    $isi = '<sheetData><row r="1">';
    foreach ($header as $i => $h) {
        $isi .= xlsx_sel(xlsx_kolom($i + 1) . '1', $h, true);
    }
    $isi .= '</row>';
    $r = 2;
    foreach ($baris as $b) {
        $isi .= '<row r="' . $r . '">';
        foreach (array_values((array) $b) as $i => $v) {
            $isi .= xlsx_sel(xlsx_kolom($i + 1) . $r, $v, false);
        }
        $isi .= '</row>';
        $r++;
    }
    $isi .= '</sheetData>';
    $akhir = xlsx_kolom($jumlahKolom) . max(1, $r - 1);

    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<dimension ref="A1:' . $akhir . '"/>'
        . '<sheetViews><sheetView workbookViewId="0">'
        . '<pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/>'
        . '</sheetView></sheetViews>'
        . '<sheetFormatPr defaultRowHeight="15"/>'
        . $cols . $isi
        . '<autoFilter ref="A1:' . $akhir . '"/>'
        . '</worksheet>';
}

/**
 * Arsip ZIP metode STORE. $berkas = ['nama/di/zip' => 'isi', ...].
 * Tidak butuh ZipArchive maupun gzcompress.
 */
function xlsx_zip(array $berkas): string
{
    $waktu = time();
    $dosWaktu = ((int) gmdate('H', $waktu) << 11)
        | ((int) gmdate('i', $waktu) << 5) | ((int) gmdate('s', $waktu) >> 1);
    $dosTanggal = (((int) gmdate('Y', $waktu) - 1980) << 9)
        | ((int) gmdate('n', $waktu) << 5) | (int) gmdate('j', $waktu);

    $data = '';
    $pusat = '';
    $offset = 0;
    $jumlah = 0;
    foreach ($berkas as $nama => $isi) {
        $nama = (string) $nama;
        $isi = (string) $isi;
        $crc = crc32($isi);
        $panjang = strlen($isi);
        $panjangNama = strlen($nama);

        $data .= pack(
            'VvvvvvVVVvv',
            0x04034b50, 20, 0x0800, 0, $dosWaktu, $dosTanggal,
            $crc, $panjang, $panjang, $panjangNama, 0
        ) . $nama . $isi;

        $pusat .= pack(
            'VvvvvvvVVVvvvvvVV',
            0x02014b50, 20, 20, 0x0800, 0, $dosWaktu, $dosTanggal,
            $crc, $panjang, $panjang, $panjangNama, 0, 0, 0, 0, 0, $offset
        ) . $nama;

        $offset += 30 + $panjangNama + $panjang;
        $jumlah++;
    }
    return $data . $pusat . pack(
        'VvvvvVVv', 0x06054b50, 0, 0, $jumlah, $jumlah, strlen($pusat), $offset, 0
    );
}

/** Nama lembar yang aman untuk Excel (maks. 31 karakter, tanpa karakter terlarang). */
function xlsx_nama_lembar(string $nama, int $urutan): string
{
    $nama = trim(str_replace(['[', ']', ':', '*', '?', '/', '\\'], '_', $nama));
    if ($nama === '') {
        $nama = 'Lembar ' . $urutan;
    }
    return function_exists('mb_substr') ? mb_substr($nama, 0, 31, 'UTF-8') : substr($nama, 0, 31);
}

/**
 * Kirim berkas .xlsx ke browser lalu hentikan respon.
 *
 * $lembar = [
 *   ['nama' => 'Rekap Siswa', 'header' => [...], 'baris' => [[...], ...], 'lebar' => [18, 14, ...]],
 *   ...
 * ]
 * $keterangan = [['label' => 'Periode', 'nilai' => '...'], ...] -> lembar tambahan.
 */
function xlsx_keluar(string $namaBerkas, array $lembar, array $keterangan = []): void
{
    $lembar = array_values(array_filter($lembar, 'is_array'));
    if ($keterangan !== []) {
        $lembar[] = [
            'nama' => 'Keterangan',
            'header' => ['Keterangan', 'Nilai'],
            'baris' => array_map(
                static fn($k): array => [$k['label'] ?? '', $k['nilai'] ?? ''],
                $keterangan
            ),
            'lebar' => [28, 60],
        ];
    }
    if ($lembar === []) {
        $lembar[] = ['nama' => 'Laporan', 'header' => [], 'baris' => []];
    }

    $namaBerkas = preg_replace('/[^A-Za-z0-9_.-]/', '_', $namaBerkas) ?: 'laporan';
    if (!str_ends_with(strtolower($namaBerkas), '.xlsx')) {
        $namaBerkas .= '.xlsx';
    }

    $jumlah = count($lembar);
    $namaLembar = [];
    $berkas = [];

    // [Content_Types].xml
    $jenis = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
        . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
        . '<Default Extension="xml" ContentType="application/xml"/>'
        . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
        . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>';
    for ($i = 1; $i <= $jumlah; $i++) {
        $jenis .= '<Override PartName="/xl/worksheets/sheet' . $i . '.xml"'
            . ' ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
    }
    $berkas['[Content_Types].xml'] = $jenis . '</Types>';

    $berkas['_rels/.rels'] = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
        . '</Relationships>';

    $buku = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"'
        . ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>';
    $relBuku = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';
    for ($i = 1; $i <= $jumlah; $i++) {
        $namaLembar[$i] = xlsx_nama_lembar((string) ($lembar[$i - 1]['nama'] ?? ''), $i);
        $buku .= '<sheet name="' . xlsx_teks($namaLembar[$i]) . '" sheetId="' . $i
            . '" r:id="rId' . $i . '"/>';
        $relBuku .= '<Relationship Id="rId' . $i
            . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet"'
            . ' Target="worksheets/sheet' . $i . '.xml"/>';
        $berkas['xl/worksheets/sheet' . $i . '.xml'] = xlsx_lembar_xml($lembar[$i - 1]);
    }
    $relBuku .= '<Relationship Id="rId' . ($jumlah + 1)
        . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles"'
        . ' Target="styles.xml"/>';
    $berkas['xl/workbook.xml'] = $buku . '</sheets></workbook>';
    $berkas['xl/_rels/workbook.xml.rels'] = $relBuku . '</Relationships>';

    // styles.xml: font 0 biasa, font 1 tebal -> cellXfs 0 biasa, 1 tebal.
    $berkas['xl/styles.xml'] = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font>'
        . '<font><b/><sz val="11"/><name val="Calibri"/></font></fonts>'
        . '<fills count="2"><fill><patternFill patternType="none"/></fill>'
        . '<fill><patternFill patternType="gray125"/></fill></fills>'
        . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
        . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
        . '<cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
        . '<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/></cellXfs>'
        . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
        . '</styleSheet>';

    $zip = xlsx_zip($berkas);

    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $namaBerkas . '"');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');
    header('X-Content-Type-Options: nosniff');
    header('Content-Length: ' . strlen($zip));
    echo $zip;
    exit;
}
