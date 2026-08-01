<?php
/**
 * Kumpulan fungsi bantuan yang dipakai di berbagai halaman.
 */

/** Escape output HTML supaya aman dari XSS. Selalu bungkus data dinamis dengan ini. */
function h(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

/** Nama-nama bulan dalam Bahasa Indonesia. */
function namaBulan(int $bulan): string
{
    $bulanList = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
    ];
    return $bulanList[$bulan] ?? '-';
}

/** Nama-nama hari dalam Bahasa Indonesia. */
function namaHari(string $tanggal): string
{
    $hariList = [
        'Sunday' => 'Minggu', 'Monday' => 'Senin', 'Tuesday' => 'Selasa',
        'Wednesday' => 'Rabu', 'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu',
    ];
    $hariInggris = date('l', strtotime($tanggal));
    return $hariList[$hariInggris] ?? $hariInggris;
}

/** Format tanggal "2026-07-29" menjadi "Rabu, 29 Juli 2026". */
function formatTanggalIndo(string $tanggal): string
{
    $ts = strtotime($tanggal);
    if (!$ts) {
        return $tanggal;
    }
    return namaHari($tanggal) . ', ' . date('j', $ts) . ' ' . namaBulan((int) date('n', $ts)) . ' ' . date('Y', $ts);
}

/** Format jam dari datetime, contoh "18:42". */
function formatJam(string $datetime): string
{
    $ts = strtotime($datetime);
    return $ts ? date('H:i', $ts) : '-';
}

/** Label lengkap untuk gender singkat (L/P). */
function labelGender(?string $genderKode): string
{
    return match ($genderKode) {
        'L' => 'Laki-laki',
        'P' => 'Perempuan',
        default => '-',
    };
}

/** Simpan pesan flash ke session untuk ditampilkan sekali setelah redirect (pola PRG). */
function setFlash(string $tipe, string $pesan): void
{
    $_SESSION['flash'] = ['tipe' => $tipe, 'pesan' => $pesan];
}

/** Ambil & hapus pesan flash (dipanggil sekali di halaman tujuan redirect). */
function getFlash(): ?array
{
    if (!empty($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

/** Redirect ke URL relatif lalu hentikan eksekusi. */
function redirect(string $path): never
{
    header('Location: ' . $path);
    exit;
}

/** Ambil daftar kategori usia sesuai catatan pada data Excel MM KR4. */
function daftarKategori(): array
{
    return ['SMP 1', 'SMP 2', 'SMP 3', 'SMA 1', 'SMA 2', 'SMA 3', 'PRA 1', 'PRA 2', 'PRA 3', 'PRA 4'];
}

/** Validasi format tanggal YYYY-MM-DD, kembalikan tanggal hari ini jika tidak valid. */
function tanggalValidAtauHariIni(?string $tanggal): string
{
    if ($tanggal && preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal)) {
        $d = DateTime::createFromFormat('Y-m-d', $tanggal);
        if ($d && $d->format('Y-m-d') === $tanggal) {
            return $tanggal;
        }
    }
    return date('Y-m-d');
}

/** Validasi format bulan YYYY-MM, kembalikan bulan berjalan jika tidak valid. */
function bulanValidAtauSekarang(?string $bulan): string
{
    if ($bulan && preg_match('/^\d{4}-\d{2}$/', $bulan)) {
        $d = DateTime::createFromFormat('Y-m-d', $bulan . '-01');
        if ($d && $d->format('Y-m') === $bulan) {
            return $bulan;
        }
    }
    return date('Y-m');
}

/**
 * Ambil data matrix kehadiran bulanan untuk satu gender: daftar anggota aktif
 * beserta set tanggal yang dihadiri dalam rentang tanggal tertentu.
 * Dipakai untuk Laporan Bulanan (tabel gaya "buku absensi": nama x tanggal).
 */
function ambilMatrixBulanan(PDO $pdo, string $gender, string $awal, string $akhir): array
{
    $stmt = $pdo->prepare(
        "SELECT a.id, a.nama, ab.tanggal
         FROM anggota a
         LEFT JOIN absensi ab ON ab.anggota_id = a.id AND ab.tanggal BETWEEN :awal AND :akhir
         WHERE a.gender = :gender AND a.is_active = 1
         ORDER BY a.nama ASC"
    );
    $stmt->execute(['gender' => $gender, 'awal' => $awal, 'akhir' => $akhir]);

    $anggotaMap = [];
    foreach ($stmt as $row) {
        $id = $row['id'];
        if (!isset($anggotaMap[$id])) {
            $anggotaMap[$id] = ['nama' => $row['nama'], 'tanggal_hadir' => []];
        }
        if ($row['tanggal']) {
            $anggotaMap[$id]['tanggal_hadir'][$row['tanggal']] = true;
        }
    }
    return array_values($anggotaMap);
}
