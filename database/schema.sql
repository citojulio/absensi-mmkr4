-- =========================================================
-- Skema Database: Absensi Pengajian Remaja MM KR4
-- =========================================================
-- Cara pakai:
--   mysql -u USERNAME -p < schema.sql
-- atau import lewat phpMyAdmin (tab "Import") di hosting kamu.
-- =========================================================

CREATE DATABASE IF NOT EXISTS absensi_mmkr4
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE absensi_mmkr4;

-- Bersihkan dulu kalau ada sisa dari percobaan run sebelumnya yang gagal di
-- tengah jalan, supaya script ini AMAN dijalankan berkali-kali dari awal
-- tanpa kena error "table already exists". Urutan DROP memperhatikan
-- foreign key (absensi & sessions duluan, baru anggota & admin).
DROP TABLE IF EXISTS absensi_mmkr4.absensi;
DROP TABLE IF EXISTS absensi_mmkr4.sessions;
DROP TABLE IF EXISTS absensi_mmkr4.admin;
DROP TABLE IF EXISTS absensi_mmkr4.anggota;

-- ---------------------------------------------------------
-- Tabel anggota
-- Menyimpan data anggota MM KR4. Field wajib minimal: nama & gender.
-- `kategori` = jenjang usia (SMP 1-3 / SMA 1-3 / PRA NIKAH 1-4),
-- diambil dari data Excel, sifatnya opsional (boleh kosong).
-- `is_active` dipakai untuk "soft delete": saat admin menghapus
-- anggota lewat web, baris TIDAK dihapus fisik, hanya ditandai
-- nonaktif (0). Ini penting supaya riwayat absensi lama tidak
-- ikut hilang / rusak walau anggotanya kemudian keluar/dihapus.
-- ---------------------------------------------------------
CREATE TABLE absensi_mmkr4.anggota (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nama        VARCHAR(100) NOT NULL,
    gender      ENUM('L','P') NOT NULL,
    kategori    VARCHAR(20) DEFAULT NULL,
    is_active   TINYINT(1) NOT NULL DEFAULT 1,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_gender_active (gender, is_active),
    INDEX idx_nama (nama)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------
-- Tabel absensi
-- Satu baris = satu kehadiran anggota pada satu tanggal.
-- UNIQUE(anggota_id, tanggal) mencegah orang yang sama absen
-- dobel di tanggal yang sama.
-- Status sengaja dibuat ENUM (bukan cuma kolom tetap 'Hadir')
-- supaya suatu saat gampang ditambah nilai lain (Izin/Sakit/Alpa)
-- tanpa perlu ubah struktur tabel dari nol.
-- ---------------------------------------------------------
CREATE TABLE absensi_mmkr4.absensi (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    anggota_id  INT UNSIGNED NOT NULL,
    tanggal     DATE NOT NULL,
    status      ENUM('Hadir') NOT NULL DEFAULT 'Hadir',
    waktu_absen TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (anggota_id) REFERENCES absensi_mmkr4.anggota(id) ON DELETE CASCADE,
    UNIQUE KEY uniq_anggota_tanggal (anggota_id, tanggal),
    INDEX idx_tanggal (tanggal)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------
-- Tabel sessions
-- Dipakai untuk menyimpan session login admin di DATABASE, bukan file lokal.
-- Wajib ada karena aplikasi ini di-deploy sebagai serverless function di
-- Vercel: setiap request bisa dilayani instance yang berbeda-beda dan tidak
-- ada disk lokal yang persisten antar request, jadi session PHP bawaan
-- (berbasis file) tidak bisa diandalkan. Lihat api/includes/DbSessionHandler.php.
-- ---------------------------------------------------------
CREATE TABLE absensi_mmkr4.sessions (
    id            VARCHAR(128) NOT NULL PRIMARY KEY,
    data          MEDIUMTEXT,
    last_activity INT UNSIGNED NOT NULL,
    INDEX idx_last_activity (last_activity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------
-- Tabel admin (login pengurus/sekretaris)
-- ---------------------------------------------------------
CREATE TABLE absensi_mmkr4.admin (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username     VARCHAR(50) NOT NULL UNIQUE,
    password     VARCHAR(255) NOT NULL,
    nama_lengkap VARCHAR(100) DEFAULT NULL,
    created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Akun admin default. Username: admin | Password: mmkr4admin
-- PENTING: ganti password ini setelah login pertama kali!
-- (lihat cara ganti password di README.md)
INSERT INTO absensi_mmkr4.admin (username, password, nama_lengkap) VALUES
('admin', '$2y$10$KrDvXHwQz.dnXEJ87rTdJuWJuM1yeyBz11EfdBlG.HrncjO2.3SKC', 'Admin MM KR4');

-- ---------------------------------------------------------
-- Data awal 56 anggota aktif, diimpor dari DATA_MM_KR4_2026.xlsx
-- (21 Laki-laki, 35 Perempuan)
-- ---------------------------------------------------------
INSERT INTO absensi_mmkr4.anggota (nama, gender, kategori, is_active) VALUES
('ADAM DAMARA', 'L', 'PRA 4', 1),
('AFIF AHMAD ANOM MAHEGA', 'L', 'PRA 4', 1),
('AKMAL FADLIL ARRIZQI', 'L', 'SMP 1', 1),
('ALDINO GEMILANG', 'L', 'SMA 3', 1),
('BAGAS ABDILLAH ROSYIDI', 'L', 'PRA 3', 1),
('CITO JULIO IBRAHIM K', 'L', 'PRA 3', 1),
('DARIEL ABDULLOH ASSIDIQI', 'L', 'SMP 2', 1),
('DIKA AVIAN', 'L', 'PRA 4', 1),
('ERICK FAISAL FIRDAUS', 'L', 'PRA 1', 1),
('FIRDAN FERDIANSYAH', 'L', 'PRA 4', 1),
('GENEVA/EPONG', 'L', 'SMP 3', 1),
('GERDA GHONIYYAH NUR', 'L', 'PRA 4', 1),
('ILYAS SAYYIDU RUZIQ ALBASITHU', 'L', 'SMA 2', 1),
('M. AKBAR HARGIANTO', 'L', 'PRA 2', 1),
('M. MAGNUS R', 'L', NULL, 1),
('M. SEPTIAN FACHREZA', 'L', 'SMA 3', 1),
('MH DAMIS OKTALIVIAN', 'L', 'SMA 3', 1),
('MUHAMMAD RAFZAN', 'L', 'PRA 1', 1),
('MUSA BUDI PRASOJO', 'L', 'PRA 4', 1),
('NANDA ARDHIANSYAH', 'L', 'PRA 4', 1),
('YUNUS IBRAHIM PRAKOSO', 'L', 'PRA 3', 1),
('ANNISA FAJRIN HIDAYATI', 'P', 'PRA 4', 1),
('APSARI PUTRI P', 'P', 'PRA 3', 1),
('ARDITA CITRA SALSABILA', 'P', 'SMP 2', 1),
('AULIA FACHRUN NISA\'', 'P', 'PRA 4', 1),
('AULIA KARIMAH', 'P', 'PRA 4', 1),
('CALISTA SHAFA AZZURA', 'P', 'SMA 1', 1),
('CHIQUITITA OKTAFARAHSANU PRABANI', 'P', 'PRA 1', 1),
('DEVI ULFINASARI PUTRI', 'P', 'PRA 4', 1),
('DISKA ARYANTI ZAHRANISA', 'P', 'SMP 1', 1),
('FADIAH FATHIN FAUZIE', 'P', 'SMA 2', 1),
('FITRIA ZAHRA ALLYA NISSA', 'P', 'SMA 3', 1),
('GHEA ABBYAH NUR', 'P', 'PRA 3', 1),
('INTAN SEPTININGRUM', 'P', 'PRA 2', 1),
('JESSY AMARIA ZUBAIDAH', 'P', 'PRA 1', 1),
('KANAYA SHIFA ANANDA', 'P', 'PRA 1', 1),
('KARINA PUTRI RIZKIKA', 'P', 'SMA 2', 1),
('KEISYA EARLENE SAFIRA', 'P', 'SMP 2', 1),
('LIYANA ROFIZAH MUMTAZ', 'P', 'PRA 1', 1),
('LOVIA FITKUR AMIN', 'P', 'PRA 1', 1),
('MAULUNA DAFIZA', 'P', 'PRA 1', 1),
('NABILA KRISTIN NINGRUM', 'P', 'SMP 1', 1),
('NABILA TSANIA SEPTYASA', 'P', 'SMA 2', 1),
('NAMIRA NURMA YULIA', 'P', 'PRA 4', 1),
('NEEZA AMELIA PUTRI', 'P', 'SMA 2', 1),
('OLIVIA FIRDAUS', 'P', 'PRA 1', 1),
('PACHA TESA AYU NURMALA', 'P', 'PRA 1', 1),
('QONITAH RACHMAWATI', 'P', 'PRA 4', 1),
('RANNI PRAMESTI', 'P', 'PRA 4', 1),
('RAYA RAMBU SYAWALA', 'P', 'SMA 2', 1),
('REVALENTINA RIZKYA SALSABILA TINO SAPUTRI', 'P', 'SMA 1', 1),
('SUNDA PUTRI GAYATRI', 'P', 'PRA 4', 1),
('TAFRINA ANGGUN PRAMITA', 'P', 'SMA 1', 1),
('TARINA KHARISMA DEWI', 'P', 'SMA 3', 1),
('TYTA F. E.', 'P', 'SMA 3', 1),
('ZAYYANAH', 'P', 'SMA 1', 1);
