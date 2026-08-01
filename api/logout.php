<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/DbSessionHandler.php';
require_once __DIR__ . '/includes/functions.php';

session_set_save_handler(new DbSessionHandler(getDB()), true);
session_start();

$_SESSION = [];
session_destroy();

// Mulai session baru (ID baru) hanya untuk menitipkan pesan flash setelah redirect.
session_start();
setFlash('info', 'Anda telah keluar dari akun admin.');
redirect('login.php');
