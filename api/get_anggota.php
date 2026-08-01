<?php
/**
 * GET api/get_anggota.php?gender=L|P
 * Mengembalikan JSON daftar anggota aktif sesuai gender, terurut A-Z.
 * Dipakai oleh form absensi (index.php) untuk mengisi dropdown Nama.
 */

require_once __DIR__ . '/config/database.php';

header('Content-Type: application/json; charset=utf-8');

$gender = $_GET['gender'] ?? '';

if (!in_array($gender, ['L', 'P'], true)) {
    http_response_code(400);
    echo json_encode(['error' => 'Parameter gender tidak valid. Gunakan L atau P.']);
    exit;
}

try {
    $pdo = getDB();
    $stmt = $pdo->prepare(
        'SELECT id, nama FROM anggota WHERE gender = :gender AND is_active = 1 ORDER BY nama ASC'
    );
    $stmt->execute(['gender' => $gender]);
    $data = $stmt->fetchAll();

    echo json_encode($data);
} catch (Throwable $e) {
    error_log('get_anggota error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Gagal mengambil data anggota.']);
}
