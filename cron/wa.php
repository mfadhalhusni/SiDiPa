<?php
declare(strict_types=1);

/*
 | Cron pengirim WhatsApp (OpenWA).
 | Pesan TIDAK dikirim saat scan: scan hanya mengisi tabel notifikasi_wa.
 | Aturan: maksimal 20 pesan per jalan, jeda acak 3-8 detik, 3 kali percobaan.
 | Endpoint & payload OpenWA diatur di app/wa.php + menu Pengaturan.
 */

require_once __DIR__ . '/../app/bootstrap.php';

function cron_wa(): array
{
    if ((string) pengaturan('wa_aktif', '1') !== '1') {
        return ['ok' => true, 'diproses' => 0, 'terkirim' => 0, 'gagal' => 0, 'pesan' => 'Pengiriman WhatsApp dinonaktifkan.'];
    }
    $maks = max(1, min(100, (int) pengaturan('wa_maks_per_jalan', '20')));
    $percobaanMaks = max(1, (int) pengaturan('wa_percobaan_maks', '3'));
    $jedaMin = max(0, (int) pengaturan('wa_jeda_min', '3'));
    $jedaMaks = max($jedaMin, (int) pengaturan('wa_jeda_maks', '8'));

    $antrian = db_semua(
        'SELECT id, siswa_id, nomor, jenis, pesan, percobaan, status
         FROM notifikasi_wa
         WHERE status = ? OR (status = ? AND percobaan < ?)
         ORDER BY id ASC LIMIT ' . $maks,
        ['antri', 'gagal', $percobaanMaks]
    );

    $klien = new WaClient();
    $terkirim = 0;
    $gagal = 0;
    $jumlah = count($antrian);
    $urutan = 0;

    foreach ($antrian as $pesan) {
        $urutan++;
        $percobaan = (int) $pesan['percobaan'] + 1;
        $hasil = $klien->send((string) $pesan['nomor'], (string) $pesan['pesan']);
        $status = $hasil['ok'] ? 'terkirim' : ($percobaan >= $percobaanMaks ? 'gagal' : 'antri');
        db_jalankan(
            'UPDATE notifikasi_wa SET status = ?, percobaan = ?, balasan = ?, dikirim_pada = ? WHERE id = ?',
            [
                $status,
                $percobaan,
                teks($hasil['balasan'], 500),
                $hasil['ok'] ? date('Y-m-d H:i:s') : null,
                (int) $pesan['id'],
            ]
        );
        if ($hasil['ok']) {
            $terkirim++;
        } else {
            $gagal++;
        }
        if ($urutan < $jumlah && $jedaMaks > 0) {
            sleep(random_int($jedaMin, $jedaMaks));
        }
    }

    log_akses('cron_wa', 'cron', null);
    return [
        'ok' => true, 'diproses' => $jumlah, 'terkirim' => $terkirim, 'gagal' => $gagal,
        'pesan' => 'Cron WA selesai: ' . $jumlah . ' diproses, ' . $terkirim . ' terkirim, ' . $gagal . ' belum berhasil.',
    ];
}

if (PHP_SAPI === 'cli' && isset($_SERVER['SCRIPT_FILENAME']) && realpath((string) $_SERVER['SCRIPT_FILENAME']) === realpath(__FILE__)) {
    try {
        $hasil = cron_wa();
        fwrite(STDOUT, $hasil['pesan'] . PHP_EOL);
        exit(0);
    } catch (Throwable $galat) {
        catat_kesalahan('cron/wa.php: ' . $galat->getMessage());
        fwrite(STDERR, 'Cron WA gagal: ' . $galat->getMessage() . PHP_EOL);
        exit(1);
    }
}
