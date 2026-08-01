<?php
/**
 * Header bersama untuk semua halaman.
 * Wajib set $pageTitle sebelum require file ini.
 * Opsional set $activeNav ('absen'|'laporan'|'statistik'|'anggota') untuk highlight menu.
 */
$pageTitle = $pageTitle ?? 'Absensi MM KR4';
$activeNav = $activeNav ?? '';
$sudahLogin = isAdminLoggedIn();
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
<title><?= h($pageTitle) ?> · MM KR4</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:wght@500;600;700&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
<div class="app-shell">
    <header class="navbar">
        <div class="container">
            <a href="index.php" class="navbar-brand">
                <span class="mark">MM</span>
                <span>Absensi KR4</span>
            </a>
            <button class="navbar-toggle" id="navToggle" aria-label="Buka menu" aria-expanded="false">&#9776;</button>
            <nav class="navbar-links" id="navLinks">
                <a href="index.php" class="<?= $activeNav === 'absen' ? 'active' : '' ?>">Absen</a>
                <?php if ($sudahLogin): ?>
                    <a href="laporan.php" class="<?= $activeNav === 'laporan' ? 'active' : '' ?>">Laporan Harian</a>
                    <a href="statistik.php" class="<?= $activeNav === 'statistik' ? 'active' : '' ?>">Statistik</a>
                    <a href="anggota.php" class="<?= $activeNav === 'anggota' ? 'active' : '' ?>">Data Anggota</a>
                    <a href="logout.php">Keluar</a>
                <?php else: ?>
                    <a href="login.php" class="<?= $activeNav === 'login' ? 'active' : '' ?>">Login Admin</a>
                <?php endif; ?>
            </nav>
        </div>
    </header>
    <main class="main-content">
        <div class="container">
        <?php $flash = getFlash(); if ($flash): ?>
            <div class="alert alert-<?= h($flash['tipe']) ?>"><?= h($flash['pesan']) ?></div>
        <?php endif; ?>
