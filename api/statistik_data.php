<?php
/**
 * GET statistik_data.php?tahun=YYYY&anggota_id=(opsional)
 * Mengembalikan JSON data kehadiran bulanan untuk Chart.js.
 */
require_once __DIR__ . '/includes/session_init.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

header('Content-Type: application/json; charset=utf-8');

if (!isAdminLoggedIn()) {
    http_response_code(403);
    echo json_encode(['error' => 'Tidak diizinkan. Silakan login sebagai admin.']);
    exit;
}

$tahun = filter_input(INPUT_GET, 'tahun', FILTER_VALIDATE_INT) ?: (int) date('Y');
$anggotaId = filter_input(INPUT_GET, 'anggota_id', FILTER_VALIDATE_INT);

$pdo = getDB();
$labelBulan = [];
for ($b = 1; $b <= 12; $b++) {
    $labelBulan[] = namaBulan($b);
}

try {
    if ($anggotaId) {
        // ---- Mode: statistik per anggota ----
        $stmtNama = $pdo->prepare('SELECT nama FROM anggota WHERE id = :id');
        $stmtNama->execute(['id' => $anggotaId]);
        $anggota = $stmtNama->fetch();
        if (!$anggota) {
            http_response_code(404);
            echo json_encode(['error' => 'Anggota tidak ditemukan.']);
            exit;
        }

        $hadirPerBulan = array_fill(1, 12, 0);
        $stmt = $pdo->prepare(
            "SELECT MONTH(tanggal) bulan, COUNT(*) jumlah FROM absensi
             WHERE anggota_id = :id AND YEAR(tanggal) = :tahun GROUP BY MONTH(tanggal)"
        );
        $stmt->execute(['id' => $anggotaId, 'tahun' => $tahun]);
        foreach ($stmt as $row) {
            $hadirPerBulan[(int) $row['bulan']] = (int) $row['jumlah'];
        }

        $sesiPerBulan = array_fill(1, 12, 0);
        $stmtSesi = $pdo->prepare(
            "SELECT MONTH(tanggal) bulan, COUNT(DISTINCT tanggal) sesi FROM absensi
             WHERE YEAR(tanggal) = :tahun GROUP BY MONTH(tanggal)"
        );
        $stmtSesi->execute(['tahun' => $tahun]);
        foreach ($stmtSesi as $row) {
            $sesiPerBulan[(int) $row['bulan']] = (int) $row['sesi'];
        }

        $persentase = [];
        foreach ($hadirPerBulan as $bulan => $hadir) {
            $sesi = $sesiPerBulan[$bulan];
            $persentase[] = $sesi > 0 ? round(($hadir / $sesi) * 100) : null;
        }

        echo json_encode([
            'mode'        => 'individu',
            'nama'        => $anggota['nama'],
            'labels'      => $labelBulan,
            'hadir'       => array_values($hadirPerBulan),
            'sesi'        => array_values($sesiPerBulan),
            'persentase'  => $persentase,
            'total_hadir' => array_sum($hadirPerBulan),
        ]);
    } else {
        // ---- Mode: keseluruhan anggota, dipecah per gender ----
        $dataL = array_fill(1, 12, 0);
        $dataP = array_fill(1, 12, 0);

        $stmt = $pdo->prepare(
            "SELECT MONTH(ab.tanggal) bulan, a.gender, COUNT(*) jumlah
             FROM absensi ab JOIN anggota a ON a.id = ab.anggota_id
             WHERE YEAR(ab.tanggal) = :tahun
             GROUP BY MONTH(ab.tanggal), a.gender"
        );
        $stmt->execute(['tahun' => $tahun]);
        foreach ($stmt as $row) {
            $bulan = (int) $row['bulan'];
            if ($row['gender'] === 'L') {
                $dataL[$bulan] = (int) $row['jumlah'];
            } else {
                $dataP[$bulan] = (int) $row['jumlah'];
            }
        }

        $totalHadirTahunIni = array_sum($dataL) + array_sum($dataP);
        $bulanTertinggiIdx = 1;
        $nilaiTertinggi = -1;
        for ($b = 1; $b <= 12; $b++) {
            $gab = $dataL[$b] + $dataP[$b];
            if ($gab > $nilaiTertinggi) {
                $nilaiTertinggi = $gab;
                $bulanTertinggiIdx = $b;
            }
        }

        echo json_encode([
            'mode'            => 'keseluruhan',
            'labels'          => $labelBulan,
            'laki_laki'       => array_values($dataL),
            'perempuan'       => array_values($dataP),
            'total_hadir'     => $totalHadirTahunIni,
            'rata_rata_bulan' => round($totalHadirTahunIni / 12, 1),
            'bulan_tertinggi' => namaBulan($bulanTertinggiIdx) . ' (' . $nilaiTertinggi . ' kehadiran)',
        ]);
    }
} catch (Throwable $e) {
    error_log('statistik_data error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Gagal mengambil data statistik.']);
}
