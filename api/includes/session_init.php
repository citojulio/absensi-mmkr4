<?php
/**
 * Bootstrap session berbasis database.
 * Pakai file ini di setiap halaman, GANTI baris session_start() dengan:
 *   require_once __DIR__ . '/includes/session_init.php';
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/DbSessionHandler.php';

session_set_save_handler(new DbSessionHandler(getDB()), true);

// Cookie session: httponly (tidak bisa diakses JS) & secure otomatis kalau diakses via HTTPS
// (di Vercel production selalu HTTPS; saat tes lokal dengan `php -S` biasanya HTTP, jadi secure dimatikan otomatis).
$httpsAktif = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'httponly' => true,
    'samesite' => 'Lax',
    'secure' => $httpsAktif,
]);

session_start();
