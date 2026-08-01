<?php
/**
 * Menyimpan submit dari Form Absensi (index.php).
 * Pola PRG (Post/Redirect/Get): setelah proses, selalu redirect balik ke index.php.
 */
require_once __DIR__ . '/includes/session_init.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('index.php');
}

$anggotaId = filter_input(INPUT_POST, 'anggota_id', FILTER_VALIDATE_INT);

if (!$anggotaId) {
    setFlash('error', 'Silakan pilih Gender dan Nama terlebih dahulu sebelum mengirim absensi.');
    redirect('index.php');
}

try {
    $pdo = getDB();

    // Pastikan anggota valid & masih aktif.
    $stmt = $pdo->prepare('SELECT id, nama, gender FROM anggota WHERE id = :id AND is_active = 1');
    $stmt->execute(['id' => $anggotaId]);
    $anggota = $stmt->fetch();

    if (!$anggota) {
        setFlash('error', 'Data anggota tidak ditemukan atau sudah tidak aktif. Silakan hubungi pengurus.');
        redirect('index.php');
    }

    $tanggalHariIni = date('Y-m-d');

    $insert = $pdo->prepare(
        'INSERT INTO absensi (anggota_id, tanggal, status) VALUES (:anggota_id, :tanggal, "Hadir")'
    );
    $insert->execute(['anggota_id' => $anggota['id'], 'tanggal' => $tanggalHariIni]);

    // Simpan detail untuk ditampilkan sebagai "stempel" konfirmasi di index.php.
    $_SESSION['absen_sukses'] = [
        'nama'    => $anggota['nama'],
        'gender'  => $anggota['gender'],
        'tanggal' => $tanggalHariIni,
        'jam'     => date('H:i'),
    ];
    redirect('index.php');

} catch (PDOException $e) {
    // Kode 23000 = pelanggaran UNIQUE constraint (anggota_id, tanggal) -> sudah absen hari ini.
    if ($e->getCode() === '23000') {
        try {
            $cek = $pdo->prepare('SELECT waktu_absen FROM absensi WHERE anggota_id = :id AND tanggal = :tgl');
            $cek->execute(['id' => $anggotaId, 'tgl' => date('Y-m-d')]);
            $existing = $cek->fetch();
            $jam = $existing ? formatJam($existing['waktu_absen']) : '-';
            setFlash('info', $anggota['nama'] . ' sudah tercatat Hadir hari ini pukul ' . $jam . '. Tidak perlu absen dua kali ya.');
        } catch (Throwable $inner) {
            setFlash('info', 'Anda sudah tercatat Hadir hari ini. Tidak perlu absen dua kali ya.');
        }
        redirect('index.php');
    }

    error_log('absen_simpan error: ' . $e->getMessage());
    setFlash('error', 'Terjadi kesalahan saat menyimpan absensi. Silakan coba lagi.');
    redirect('index.php');
}
