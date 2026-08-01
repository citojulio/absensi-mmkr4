<?php
require_once __DIR__ . '/includes/session_init.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

$pageTitle = 'Absensi Kehadiran';
$activeNav = 'absen';

$sukses = $_SESSION['absen_sukses'] ?? null;
unset($_SESSION['absen_sukses']);

require_once __DIR__ . '/includes/header.php';
?>

<div class="checkin-wrap">

<?php if ($sukses): ?>

    <div class="checkin-card">
        <div class="success-stamp">
            <div class="check-circle">
                <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M5 13l4 4L19 7" stroke="#3C7A5E" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </div>
            <h2>Terima kasih, <?= h($sukses['nama']) ?>!</h2>
            <p class="text-muted">Kehadiran Anda pukul <?= h($sukses['jam']) ?> WIB telah tercatat pada<br><?= h(formatTanggalIndo($sukses['tanggal'])) ?>.</p>
            <span class="stamp-hadir">✓ Hadir</span>
        </div>
        <div style="text-align:center; margin-top:26px;">
            <a href="index.php" class="btn btn-outline">Absen untuk orang lain</a>
        </div>
    </div>

<?php else: ?>

    <div class="checkin-header">
        <span class="eyebrow">Pengajian Remaja &middot; MM KR4</span>
        <h1>Absensi Kehadiran</h1>
        <div class="tanggal-hari-ini"><?= h(formatTanggalIndo(date('Y-m-d'))) ?></div>
    </div>

    <div class="checkin-card">
        <form action="absen_simpan.php" method="POST" id="formAbsensi">

            <div class="form-group">
                <label>Gender</label>
                <div class="gender-choice">
                    <div class="gender-option">
                        <input type="radio" name="gender" id="genderL" value="L" required>
                        <label for="genderL">Laki-laki</label>
                    </div>
                    <div class="gender-option">
                        <input type="radio" name="gender" id="genderP" value="P" required>
                        <label for="genderP">Perempuan</label>
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label for="namaSelect">Nama</label>
                <select id="namaSelect" name="anggota_id" disabled required>
                    <option value="">-- Pilih Gender terlebih dahulu --</option>
                </select>
                <div class="form-hint" id="namaHint">Nama tidak ada di daftar? Hubungi pengurus untuk didaftarkan lewat menu Data Anggota.</div>
            </div>

            <div class="form-group">
                <label for="statusSelect">Status Kehadiran</label>
                <select id="statusSelect" name="status_display">
                    <option value="Hadir">Hadir</option>
                </select>
            </div>

            <button type="submit" class="btn btn-primary btn-block" id="btnSubmitAbsen" disabled>
                Simpan Absensi
            </button>
        </form>
    </div>

<?php endif; ?>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
