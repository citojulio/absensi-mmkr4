<?php
require_once __DIR__ . '/includes/session_init.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
wajibLogin();

$mode = ($_GET['mode'] ?? 'harian') === 'bulanan' ? 'bulanan' : 'harian';
$pdo = getDB();

// Total anggota aktif per gender (dipakai di kedua mode).
$totalGender = ['L' => 0, 'P' => 0];
foreach ($pdo->query("SELECT gender, COUNT(*) c FROM anggota WHERE is_active = 1 GROUP BY gender") as $row) {
    $totalGender[$row['gender']] = (int) $row['c'];
}

if ($mode === 'harian') {
    $tanggal = tanggalValidAtauHariIni($_GET['tanggal'] ?? null);
    $tsTanggal = strtotime($tanggal);
    $tanggalKemarin = date('Y-m-d', strtotime('-1 day', $tsTanggal));
    $tanggalBesok = date('Y-m-d', strtotime('+1 day', $tsTanggal));

    $stmtHadir = $pdo->prepare(
        "SELECT a.id, a.nama, a.kategori, ab.waktu_absen
         FROM absensi ab JOIN anggota a ON a.id = ab.anggota_id
         WHERE ab.tanggal = :tanggal AND a.gender = :gender
         ORDER BY a.nama ASC"
    );
    $stmtHadir->execute(['tanggal' => $tanggal, 'gender' => 'L']);
    $hadirL = $stmtHadir->fetchAll();
    $stmtHadir->execute(['tanggal' => $tanggal, 'gender' => 'P']);
    $hadirP = $stmtHadir->fetchAll();

    $totalHadir = count($hadirL) + count($hadirP);
    $totalAnggotaAktif = $totalGender['L'] + $totalGender['P'];
} else {
    $bulanTerpilih = bulanValidAtauSekarang($_GET['bulan'] ?? null);
    $bulanTs = strtotime($bulanTerpilih . '-01');
    $bulanSebelumnya = date('Y-m', strtotime('-1 month', $bulanTs));
    $bulanBerikutnya = date('Y-m', strtotime('+1 month', $bulanTs));
    $awalBulan = date('Y-m-01', $bulanTs);
    $akhirBulan = date('Y-m-t', $bulanTs);

    $daftarTanggalSesi = $pdo->prepare(
        "SELECT DISTINCT tanggal FROM absensi WHERE tanggal BETWEEN :awal AND :akhir ORDER BY tanggal ASC"
    );
    $daftarTanggalSesi->execute(['awal' => $awalBulan, 'akhir' => $akhirBulan]);
    $daftarTanggalSesi = $daftarTanggalSesi->fetchAll(PDO::FETCH_COLUMN);

    $matrixL = ambilMatrixBulanan($pdo, 'L', $awalBulan, $akhirBulan);
    $matrixP = ambilMatrixBulanan($pdo, 'P', $awalBulan, $akhirBulan);

    $jumlahSesi = count($daftarTanggalSesi);
    $totalKehadiranBulan = array_sum(array_map(fn($a) => count($a['tanggal_hadir']), array_merge($matrixL, $matrixP)));
    $rataRataPerSesi = $jumlahSesi > 0 ? round($totalKehadiranBulan / $jumlahSesi, 1) : 0;
}

$pageTitle = 'Laporan Harian';
$activeNav = 'laporan';
require_once __DIR__ . '/includes/header.php';

function renderTabelHadir(array $rows, string $tableId, string $emptyText): void
{
    echo '<div class="table-wrap"><table id="' . h($tableId) . '">';
    echo '<thead><tr><th class="num">No</th><th>Nama</th><th>Kategori</th><th>Jam Hadir</th></tr></thead><tbody>';
    if (empty($rows)) {
        echo '<tr><td colspan="4" class="table-empty">' . h($emptyText) . '</td></tr>';
    } else {
        $no = 1;
        foreach ($rows as $r) {
            echo '<tr data-row>';
            echo '<td class="num">' . $no++ . '</td>';
            echo '<td>' . h($r['nama']) . '</td>';
            echo '<td>' . h($r['kategori'] ?: '-') . '</td>';
            echo '<td class="nowrap">' . h(formatJam($r['waktu_absen'])) . '</td>';
            echo '</tr>';
        }
        echo '<tr class="js-empty-search" style="display:none;"><td colspan="4" class="table-empty">Tidak ada nama yang cocok dengan pencarian.</td></tr>';
    }
    echo '</tbody></table></div>';
}

function renderTabelBulanan(array $anggotaList, array $daftarTanggalSesi, string $tableId, string $emptyText): void
{
    $totalKolom = count($daftarTanggalSesi) + 3;
    echo '<div class="table-wrap"><table id="' . h($tableId) . '">';
    echo '<thead><tr><th class="num">No</th><th>Nama</th>';
    foreach ($daftarTanggalSesi as $tgl) {
        echo '<th class="num nowrap" title="' . h(formatTanggalIndo($tgl)) . '">' . h(date('d/m', strtotime($tgl))) . '</th>';
    }
    echo '<th class="num">Total</th></tr></thead><tbody>';

    if (empty($anggotaList) || empty($daftarTanggalSesi)) {
        $pesan = empty($daftarTanggalSesi) ? 'Belum ada sesi pengajian tercatat pada bulan ini.' : $emptyText;
        echo '<tr><td colspan="' . $totalKolom . '" class="table-empty">' . h($pesan) . '</td></tr>';
    } else {
        $no = 1;
        foreach ($anggotaList as $a) {
            echo '<tr data-row>';
            echo '<td class="num">' . $no++ . '</td>';
            echo '<td>' . h($a['nama']) . '</td>';
            $total = 0;
            foreach ($daftarTanggalSesi as $tgl) {
                $hadir = isset($a['tanggal_hadir'][$tgl]);
                if ($hadir) $total++;
                echo '<td class="num">' . ($hadir ? '<span class="badge badge-hadir">&#10003;</span>' : '<span class="text-muted">&#8211;</span>') . '</td>';
            }
            echo '<td class="num" style="font-weight:700;">' . $total . '</td>';
            echo '</tr>';
        }
        echo '<tr class="js-empty-search" style="display:none;"><td colspan="' . $totalKolom . '" class="table-empty">Tidak ada nama yang cocok dengan pencarian.</td></tr>';
    }
    echo '</tbody></table></div>';
}
?>

<div class="tab-switch no-print">
    <a href="laporan.php?mode=harian" class="btn <?= $mode === 'harian' ? 'btn-primary' : 'btn-outline' ?>">Harian</a>
    <a href="laporan.php?mode=bulanan" class="btn <?= $mode === 'bulanan' ? 'btn-primary' : 'btn-outline' ?>">Bulanan</a>
</div>

<?php if ($mode === 'harian'): ?>

<div class="card no-print" style="border-left-color: var(--color-accent);">
    <form method="GET" action="laporan.php" style="display:flex; gap:10px; align-items:flex-end; flex-wrap:wrap;">
        <input type="hidden" name="mode" value="harian">
        <div class="form-group" style="margin-bottom:0;">
            <label for="tanggal">Pilih Tanggal</label>
            <input type="date" id="tanggal" name="tanggal" value="<?= h($tanggal) ?>" onchange="this.form.submit()">
        </div>
        <a href="laporan.php?mode=harian&tanggal=<?= h($tanggalKemarin) ?>" class="btn btn-outline">&larr; Kemarin</a>
        <a href="laporan.php?mode=harian&tanggal=<?= h($tanggalBesok) ?>" class="btn btn-outline">Besok &rarr;</a>
        <a href="laporan.php?mode=harian" class="btn btn-outline">Hari Ini</a>
    </form>
</div>

<div class="card">
    <div class="card-header">
        <div>
            <h2>Laporan Kehadiran</h2>
            <span class="text-muted"><?= h(formatTanggalIndo($tanggal)) ?></span>
        </div>
    </div>

    <div class="stat-grid" style="margin-bottom:22px;">
        <div class="stat-box">
            <div class="stat-value"><?= $totalHadir ?> / <?= $totalAnggotaAktif ?></div>
            <div class="stat-label">Total Hadir</div>
        </div>
        <div class="stat-box">
            <div class="stat-value"><?= count($hadirL) ?> / <?= $totalGender['L'] ?></div>
            <div class="stat-label">Laki-laki Hadir</div>
        </div>
        <div class="stat-box">
            <div class="stat-value"><?= count($hadirP) ?> / <?= $totalGender['P'] ?></div>
            <div class="stat-label">Perempuan Hadir</div>
        </div>
    </div>

    <div class="toolbar no-print">
        <div class="form-group grow">
            <label for="cariNama">Cari Nama</label>
            <input type="search" id="cariNama" placeholder="Ketik nama untuk menyaring tabel di bawah..." data-table-search="tabelHadirL,tabelHadirP">
        </div>
        <div class="toolbar-actions">
            <a class="btn btn-accent" href="laporan_export.php?mode=harian&tanggal=<?= h($tanggal) ?>">&#8681; Excel</a>
            <a class="btn btn-outline" href="laporan_export.php?mode=harian&tanggal=<?= h($tanggal) ?>&tipe=pdf" target="_blank">&#8681; PDF</a>
        </div>
    </div>

    <h3>Laki-laki <span class="text-muted text-sm">(<?= count($hadirL) ?> hadir)</span></h3>
    <?php renderTabelHadir($hadirL, 'tabelHadirL', 'Belum ada anggota Laki-laki yang absen pada tanggal ini.'); ?>

    <h3 style="margin-top:26px;">Perempuan <span class="text-muted text-sm">(<?= count($hadirP) ?> hadir)</span></h3>
    <?php renderTabelHadir($hadirP, 'tabelHadirP', 'Belum ada anggota Perempuan yang absen pada tanggal ini.'); ?>
</div>

<?php else: ?>

<div class="card no-print" style="border-left-color: var(--color-accent);">
    <form method="GET" action="laporan.php" style="display:flex; gap:10px; align-items:flex-end; flex-wrap:wrap;">
        <input type="hidden" name="mode" value="bulanan">
        <div class="form-group" style="margin-bottom:0;">
            <label for="bulan">Pilih Bulan</label>
            <input type="month" id="bulan" name="bulan" value="<?= h($bulanTerpilih) ?>" onchange="this.form.submit()">
        </div>
        <a href="laporan.php?mode=bulanan&bulan=<?= h($bulanSebelumnya) ?>" class="btn btn-outline">&larr; Bulan Lalu</a>
        <a href="laporan.php?mode=bulanan&bulan=<?= h($bulanBerikutnya) ?>" class="btn btn-outline">Bulan Depan &rarr;</a>
        <a href="laporan.php?mode=bulanan" class="btn btn-outline">Bulan Ini</a>
    </form>
</div>

<div class="card">
    <div class="card-header">
        <div>
            <h2>Laporan Bulanan</h2>
            <span class="text-muted"><?= h(namaBulan((int) date('n', $bulanTs))) ?> <?= h(date('Y', $bulanTs)) ?></span>
        </div>
    </div>

    <div class="stat-grid" style="margin-bottom:22px;">
        <div class="stat-box">
            <div class="stat-value"><?= $jumlahSesi ?></div>
            <div class="stat-label">Jumlah Sesi Pengajian</div>
        </div>
        <div class="stat-box">
            <div class="stat-value"><?= $totalKehadiranBulan ?></div>
            <div class="stat-label">Total Kehadiran</div>
        </div>
        <div class="stat-box">
            <div class="stat-value"><?= $rataRataPerSesi ?></div>
            <div class="stat-label">Rata-rata Hadir / Sesi</div>
        </div>
    </div>

    <div class="toolbar no-print">
        <div class="form-group grow">
            <label for="cariNamaBulanan">Cari Nama</label>
            <input type="search" id="cariNamaBulanan" placeholder="Ketik nama untuk menyaring tabel di bawah..." data-table-search="tabelBulananL,tabelBulananP">
        </div>
        <div class="toolbar-actions">
            <a class="btn btn-accent" href="laporan_export.php?mode=bulanan&bulan=<?= h($bulanTerpilih) ?>">&#8681; Excel</a>
            <a class="btn btn-outline" href="laporan_export.php?mode=bulanan&bulan=<?= h($bulanTerpilih) ?>&tipe=pdf" target="_blank">&#8681; PDF</a>
        </div>
    </div>

    <h3>Laki-laki</h3>
    <?php renderTabelBulanan($matrixL, $daftarTanggalSesi, 'tabelBulananL', 'Belum ada anggota Laki-laki yang absen bulan ini.'); ?>

    <h3 style="margin-top:26px;">Perempuan</h3>
    <?php renderTabelBulanan($matrixP, $daftarTanggalSesi, 'tabelBulananP', 'Belum ada anggota Perempuan yang absen bulan ini.'); ?>
</div>

<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
