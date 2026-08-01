<?php
/**
 * Handler gabungan untuk aksi Tambah/Ubah DAN Hapus data anggota.
 * (Digabung jadi satu file supaya jumlah serverless function di Vercel
 * tidak melebihi batas paket Hobby/gratis: maksimal 12 function per deployment.)
 *
 * Dibedakan lewat field POST "action":
 *   - action=hapus  -> soft-delete
 *   - (default)     -> simpan (tambah kalau id kosong, ubah kalau id terisi)
 */
require_once __DIR__ . '/includes/session_init.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
wajibLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('anggota.php');
}

$action = $_POST['action'] ?? 'simpan';

if ($action === 'hapus') {
    hapusAnggota();
} else {
    simpanAnggota();
}

/**
 * SOFT DELETE (menandai is_active = 0), bukan menghapus baris secara fisik,
 * supaya riwayat absensi anggota tersebut di Laporan & Statistik tidak ikut rusak/hilang.
 */
function hapusAnggota(): void
{
    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

    if (!$id) {
        setFlash('error', 'Anggota tidak valid.');
        redirect('anggota.php');
    }

    try {
        $pdo = getDB();
        $stmt = $pdo->prepare('SELECT nama FROM anggota WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $anggota = $stmt->fetch();

        if (!$anggota) {
            setFlash('error', 'Data anggota tidak ditemukan.');
            redirect('anggota.php');
        }

        $update = $pdo->prepare('UPDATE anggota SET is_active = 0 WHERE id = :id');
        $update->execute(['id' => $id]);

        setFlash('success', $anggota['nama'] . ' telah dihapus dari daftar anggota aktif.');
    } catch (Throwable $e) {
        error_log('anggota_hapus error: ' . $e->getMessage());
        setFlash('error', 'Terjadi kesalahan saat menghapus data anggota.');
    }

    redirect('anggota.php');
}

function simpanAnggota(): void
{
    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
    $nama = trim($_POST['nama'] ?? '');
    $gender = $_POST['gender'] ?? '';
    $kategori = trim($_POST['kategori'] ?? '');

    if ($nama === '' || mb_strlen($nama) > 100) {
        setFlash('error', 'Nama wajib diisi (maksimal 100 karakter).');
        redirect('anggota.php');
    }
    if (!in_array($gender, ['L', 'P'], true)) {
        setFlash('error', 'Gender wajib dipilih.');
        redirect('anggota.php');
    }
    if ($kategori !== '' && !in_array($kategori, daftarKategori(), true)) {
        $kategori = '';
    }
    $namaUpper = mb_strtoupper($nama);

    try {
        $pdo = getDB();

        if ($id) {
            $stmt = $pdo->prepare('UPDATE anggota SET nama = :nama, gender = :gender, kategori = :kategori WHERE id = :id');
            $stmt->execute([
                'nama' => $namaUpper,
                'gender' => $gender,
                'kategori' => $kategori ?: null,
                'id' => $id,
            ]);
            setFlash('success', 'Data ' . $namaUpper . ' berhasil diperbarui.');
        } else {
            $stmt = $pdo->prepare('INSERT INTO anggota (nama, gender, kategori, is_active) VALUES (:nama, :gender, :kategori, 1)');
            $stmt->execute([
                'nama' => $namaUpper,
                'gender' => $gender,
                'kategori' => $kategori ?: null,
            ]);
            setFlash('success', $namaUpper . ' berhasil ditambahkan ke daftar anggota.');
        }
    } catch (Throwable $e) {
        error_log('anggota_simpan error: ' . $e->getMessage());
        setFlash('error', 'Terjadi kesalahan saat menyimpan data anggota.');
    }

    redirect('anggota.php');
}
