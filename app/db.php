<?php
declare(strict_types=1);

/** Koneksi PDO MySQL/MariaDB. Semua query memakai prepared statements. */
function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $dsn = 'mysql:host=' . SIDIPA_DB_HOST . ';port=' . SIDIPA_DB_PORT
        . ';dbname=' . SIDIPA_DB_NAME . ';charset=utf8mb4';
    try {
        $pdo = new PDO($dsn, SIDIPA_DB_USER, SIDIPA_DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_STRINGIFY_FETCHES => false,
        ]);
        // Waktu scan selalu memakai jam server dengan zona Asia/Jakarta.
        $pdo->exec("SET time_zone = '+07:00'");
    } catch (PDOException $galat) {
        catat_kesalahan('Koneksi database gagal: ' . $galat->getMessage());
        throw new RuntimeException('Koneksi database gagal. Periksa config.php dan berkas log di storage/logs.');
    }
    return $pdo;
}

function db_semua(string $sql, array $param = []): array
{
    $stmt = db()->prepare($sql);
    $stmt->execute($param);
    return $stmt->fetchAll();
}

function db_satu(string $sql, array $param = []): ?array
{
    $stmt = db()->prepare($sql);
    $stmt->execute($param);
    $baris = $stmt->fetch();
    return $baris === false ? null : $baris;
}

function db_nilai(string $sql, array $param = [], $default = null)
{
    $stmt = db()->prepare($sql);
    $stmt->execute($param);
    $nilai = $stmt->fetchColumn();
    return $nilai === false ? $default : $nilai;
}

function db_jalankan(string $sql, array $param = []): int
{
    $stmt = db()->prepare($sql);
    $stmt->execute($param);
    return $stmt->rowCount();
}

function db_id_terakhir(): int
{
    return (int) db()->lastInsertId();
}

/** Transaksi untuk operasi yang mengubah beberapa tabel sekaligus. */
function db_transaksi(callable $pekerjaan)
{
    $pdo = db();
    // Transaksi bertingkat: jalankan langsung di dalam transaksi induk,
    // sehingga rollback induk tetap membatalkan seluruh perubahan.
    if ($pdo->inTransaction()) {
        return $pekerjaan($pdo);
    }
    $pdo->beginTransaction();
    try {
        $hasil = $pekerjaan($pdo);
        $pdo->commit();
        return $hasil;
    } catch (Throwable $galat) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        catat_kesalahan('Transaksi dibatalkan: ' . $galat->getMessage());
        throw $galat;
    }
}

/** Validasi nama identifier sebelum disisipkan ke SQL (tidak pernah dari input pengguna). */
function db_identifier(string $nama): string
{
    if (!preg_match('/^[a-z_][a-z0-9_]*$/i', $nama)) {
        throw new InvalidArgumentException('Nama tabel/kolom tidak valid.');
    }
    return $nama;
}

function db_sisip(string $tabel, array $data): int
{
    $tabel = db_identifier($tabel);
    $kolom = [];
    $tanda = [];
    $nilai = [];
    foreach ($data as $kunci => $isi) {
        $kolom[] = db_identifier((string) $kunci);
        $tanda[] = '?';
        $nilai[] = $isi;
    }
    if (!$kolom) {
        throw new InvalidArgumentException('Data sisipan kosong.');
    }
    db_jalankan('INSERT INTO ' . $tabel . ' (' . implode(', ', $kolom) . ') VALUES (' . implode(', ', $tanda) . ')', $nilai);
    return db_id_terakhir();
}

function db_ubah(string $tabel, array $data, string $syarat, array $paramSyarat = []): int
{
    $tabel = db_identifier($tabel);
    $bagian = [];
    $nilai = [];
    foreach ($data as $kunci => $isi) {
        $bagian[] = db_identifier((string) $kunci) . ' = ?';
        $nilai[] = $isi;
    }
    if (!$bagian) {
        return 0;
    }
    return db_jalankan(
        'UPDATE ' . $tabel . ' SET ' . implode(', ', $bagian) . ' WHERE ' . $syarat,
        array_merge($nilai, $paramSyarat)
    );
}
