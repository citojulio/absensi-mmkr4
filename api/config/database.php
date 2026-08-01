<?php
/**
 * Konfigurasi koneksi database.
 *
 * Nilai-nilai di bawah ini SEMUA diambil dari Environment Variables,
 * supaya tidak ada password yang ikut ter-commit ke GitHub.
 *
 * Di Vercel: isi lewat Project Settings -> Environment Variables.
 * Di lokal (XAMPP/Laragon/php -S): buat file .env atau export manual,
 * atau edit sementara nilai fallback (setelah tanda ?:) di bawah ini.
 */

// Jangan tampilkan error/warning/deprecated PHP ke output HTML (bisa "membocorkan"
// teks ke atas halaman DAN memicu header terkirim lebih awal, yang berakibat
// session_start() gagal). Tetap dicatat ke log server (bisa dicek di tab Logs
// Vercel) supaya tetap bisa di-debug tanpa mengganggu tampilan/fungsi situs.
ini_set('display_errors', '0');
error_reporting(E_ALL);
ini_set('log_errors', '1');

define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_NAME', getenv('DB_NAME') ?: 'absensi_mmkr4');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');

// Sebagian besar database cloud (termasuk TiDB Cloud) MEWAJIBKAN koneksi SSL/TLS.
// Set environment variable DB_SSL=true untuk mengaktifkan.
define('DB_SSL', filter_var(getenv('DB_SSL') ?: 'false', FILTER_VALIDATE_BOOLEAN));

function getDB(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        if (DB_SSL) {
            // PENTING: PDO/mysqlnd baru benar-benar mengaktifkan koneksi terenkripsi
            // kalau ATTR_SSL_CA diisi (bukan cuma VERIFY_SERVER_CERT saja).
            // Kita sertakan CA bundle standar (berisi daftar penerbit sertifikat
            // terpercaya seperti yang dipakai AWS/TiDB Cloud) langsung di dalam
            // project, supaya tidak bergantung pada lokasi file CA di server Vercel.
            //
            // PHP 8.4+ memindahkan konstanta ini ke class Pdo\Mysql (yang lama,
            // PDO::MYSQL_ATTR_*, sudah deprecated sejak PHP 8.5). Kita deteksi
            // otomatis supaya tetap jalan baik di runtime lama maupun baru.
            $caPath = __DIR__ . '/ca-certificates.pem';
            if (class_exists('Pdo\\Mysql')) {
                $options[\Pdo\Mysql::ATTR_SSL_CA] = $caPath;
                $options[\Pdo\Mysql::ATTR_SSL_VERIFY_SERVER_CERT] = true;
            } else {
                $options[PDO::MYSQL_ATTR_SSL_CA] = $caPath;
                $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = true;
            }
        }

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // Jangan tampilkan detail koneksi (host/user/pass) ke pengunjung.
            error_log('DB Connection Error: ' . $e->getMessage());
            http_response_code(500);
            die('Gagal terhubung ke database. Hubungi admin/pengurus MM KR4. (Cek Environment Variables & database eksternal)');
        }
    }

    return $pdo;
}
