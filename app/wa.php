<?php
declare(strict_types=1);

/*
 |==========================================================================
 | ADAPTER OPENWA - SATU-SATUNYA BAGIAN YANG BOLEH DISESUAIKAN
 |==========================================================================
 | Sekolah menjalankan OpenWA di VPS sendiri. Detail endpoint dan bentuk
 | payload belum final, jadi seluruh penyesuaian cukup dilakukan di dua tempat:
 |
 |   1. Pengaturan admin -> grup "WhatsApp": wa_base_url, wa_api_key,
 |      wa_session_id, wa_endpoint, wa_path (nama field tujuan/pesan).
 |   2. Method WaClient::send() di bawah -> array $payload dan $headers.
 |
 | Ubah array $payload mengikuti dokumentasi OpenWA yang dipakai server Anda,
 | misalnya { "session": "...", "target": "62...", "message": "..." }.
 | Jangan mengubah cara pemanggilan: send($nomor, $pesan) tetap dipakai cron.
 |==========================================================================
 */

final class WaClient
{
    private string $baseUrl;
    private string $apiKey;
    private string $sessionId;
    private string $endpoint;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) pengaturan('wa_base_url', ''), '/');
        $this->apiKey = (string) pengaturan('wa_api_key', '');
        $this->sessionId = (string) pengaturan('wa_session_id', 'default');
        $this->endpoint = (string) pengaturan('wa_endpoint', '/api/send-message');
    }

    /** Kirim satu pesan. Kembalikan ['ok' => bool, 'kode' => int, 'balasan' => string]. */
    public function send(string $nomor, string $pesan): array
    {
        $nomor = normalisasi_nomor_wa($nomor);
        if ($nomor === '' || $pesan === '') {
            return ['ok' => false, 'kode' => 0, 'balasan' => 'Nomor atau pesan kosong.'];
        }
        if ($this->baseUrl === '') {
            return ['ok' => false, 'kode' => 0, 'balasan' => 'Alamat server OpenWA belum diisi di menu Pengaturan.'];
        }
        if (!function_exists('curl_init')) {
            return ['ok' => false, 'kode' => 0, 'balasan' => 'Ekstensi PHP cURL belum aktif di hosting.'];
        }

        // ---- SESUAIKAN DI SINI sesuai dokumentasi OpenWA Anda ----------------
        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            'X-API-Key: ' . $this->apiKey,
        ];
        $payload = [
            'session' => $this->sessionId,
            'to' => $nomor,
            'text' => $pesan,
        ];
        $url = $this->baseUrl . '/' . ltrim($this->endpoint, '/');
        // ---------------------------------------------------------------------

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $balasan = curl_exec($ch);
        $kode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $galat = curl_error($ch);
        curl_close($ch);

        if ($balasan === false) {
            return ['ok' => false, 'kode' => 0, 'balasan' => 'Gagal menghubungi OpenWA: ' . $galat];
        }
        $berhasil = $kode >= 200 && $kode < 300;
        return ['ok' => $berhasil, 'kode' => $kode, 'balasan' => mb_substr((string) $balasan, 0, 500, 'UTF-8')];
    }
}

const WA_JENIS = [
    'alpa' => 'Siswa alpa',
    'terlambat' => 'Terlambat masuk',
    'pelanggaran_berat' => 'Pelanggaran berat',
    'skor_rendah' => 'Skor aspek di bawah ambang',
    'sertifikat' => 'Sertifikat terbit',
];

function wa_render_template(string $template, array $variabel): string
{
    $kunci = ['{nama}', '{kelas}', '{tanggal}', '{jam}', '{aspek}', '{poin}'];
    $nilai = [
        $variabel['nama'] ?? '',
        $variabel['kelas'] ?? '',
        $variabel['tanggal'] ?? date('d/m/Y'),
        $variabel['jam'] ?? date('H.i'),
        $variabel['aspek'] ?? '',
        $variabel['poin'] ?? '',
    ];
    return str_replace($kunci, $nilai, $template);
}

/** Masukkan pesan ke ANTREAN, bukan kirim langsung saat scan. */
function wa_antri(int $siswaId, string $jenis, array $variabel = []): bool
{
    if (!isset(WA_JENIS[$jenis])) {
        return false;
    }
    if ((string) pengaturan('wa_pemicu_' . $jenis, '0') !== '1') {
        return false;
    }
    $siswa = db_satu(
        'SELECT s.nama, s.no_wa_wali, k.nama AS kelas FROM siswa s
         LEFT JOIN kelas k ON k.id = s.kelas_id WHERE s.id = ? LIMIT 1',
        [$siswaId]
    );
    if ($siswa === null) {
        return false;
    }
    $nomor = normalisasi_nomor_wa((string) ($siswa['no_wa_wali'] ?? ''));
    if ($nomor === '') {
        return false;
    }
    $variabel['nama'] = $variabel['nama'] ?? $siswa['nama'];
    $variabel['kelas'] = $variabel['kelas'] ?? ($siswa['kelas'] ?? '-');
    $template = (string) pengaturan('wa_template_' . $jenis, 'SiDiPa: {nama} ({kelas}) - {aspek} {poin} pada {tanggal} pukul {jam}.');
    $pesan = wa_render_template($template, $variabel);
    db_sisip('notifikasi_wa', [
        'siswa_id' => $siswaId,
        'nomor' => $nomor,
        'jenis' => $jenis,
        'pesan' => $pesan,
        'status' => 'antri',
        'percobaan' => 0,
    ]);
    return true;
}
