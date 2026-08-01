<?php
require_once __DIR__ . '/includes/session_init.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
wajibLogin();

$pdo = getDB();

// Daftar tahun yang punya data absensi + tahun berjalan (supaya selalu ada minimal 1 pilihan).
$tahunList = $pdo->query("SELECT DISTINCT YEAR(tanggal) th FROM absensi ORDER BY th DESC")->fetchAll(PDO::FETCH_COLUMN);
$tahunSekarang = (int) date('Y');
if (!in_array($tahunSekarang, $tahunList)) {
    array_unshift($tahunList, $tahunSekarang);
}

// Daftar anggota aktif per gender, untuk dropdown "statistik per anggota".
$anggotaL = $pdo->query("SELECT id, nama FROM anggota WHERE gender='L' AND is_active=1 ORDER BY nama")->fetchAll();
$anggotaP = $pdo->query("SELECT id, nama FROM anggota WHERE gender='P' AND is_active=1 ORDER BY nama")->fetchAll();

$pageTitle = 'Statistik Kehadiran';
$activeNav = 'statistik';
require_once __DIR__ . '/includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h2>Statistik Kehadiran</h2>
    </div>

    <div class="toolbar">
        <div class="form-group">
            <label for="selectTahun">Tahun</label>
            <select id="selectTahun">
                <?php foreach ($tahunList as $th): ?>
                    <option value="<?= h((string) $th) ?>" <?= $th == $tahunSekarang ? 'selected' : '' ?>><?= h((string) $th) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group grow">
            <label for="selectAnggota">Tampilkan</label>
            <select id="selectAnggota">
                <option value="">-- Keseluruhan Anggota --</option>
                <optgroup label="Laki-laki">
                    <?php foreach ($anggotaL as $a): ?>
                        <option value="<?= (int) $a['id'] ?>"><?= h($a['nama']) ?></option>
                    <?php endforeach; ?>
                </optgroup>
                <optgroup label="Perempuan">
                    <?php foreach ($anggotaP as $a): ?>
                        <option value="<?= (int) $a['id'] ?>"><?= h($a['nama']) ?></option>
                    <?php endforeach; ?>
                </optgroup>
            </select>
        </div>
    </div>

    <div class="stat-grid" id="statSummary" style="margin-bottom:20px;"></div>

    <div class="chart-container">
        <canvas id="chartKehadiran"></canvas>
    </div>

    <div id="tabelPersenWrap" style="margin-top:22px; display:none;">
        <h3>Rincian Bulanan</h3>
        <div class="table-wrap">
            <table id="tabelPersen">
                <thead><tr><th>Bulan</th><th class="num">Hadir</th><th class="num">Jumlah Sesi</th><th class="num">Persentase</th></tr></thead>
                <tbody></tbody>
            </table>
        </div>
        <p class="form-hint">*Jumlah Sesi = banyaknya tanggal pengajian yang tercatat ada kehadiran (dari anggota manapun) pada bulan tersebut.</p>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.4/chart.umd.min.js"></script>
<script>
(function () {
    var selectTahun = document.getElementById('selectTahun');
    var selectAnggota = document.getElementById('selectAnggota');
    var statSummary = document.getElementById('statSummary');
    var tabelPersenWrap = document.getElementById('tabelPersenWrap');
    var tabelPersenBody = document.querySelector('#tabelPersen tbody');
    var ctx = document.getElementById('chartKehadiran').getContext('2d');
    var chart = null;

    var WARNA_L = '#24463B';
    var WARNA_P = '#C9973F';

    function statBox(value, label) {
        return '<div class="stat-box"><div class="stat-value">' + value + '</div><div class="stat-label">' + label + '</div></div>';
    }

    function muatData() {
        var tahun = selectTahun.value;
        var anggotaId = selectAnggota.value;
        var url = 'statistik_data.php?tahun=' + encodeURIComponent(tahun) + (anggotaId ? '&anggota_id=' + encodeURIComponent(anggotaId) : '');

        fetch(url).then(function (r) { return r.json(); }).then(function (data) {
            if (data.error) { statSummary.innerHTML = '<p class="text-muted">' + data.error + '</p>'; return; }

            if (chart) chart.destroy();

            if (data.mode === 'individu') {
                statSummary.innerHTML = statBox(data.total_hadir, 'Total Hadir Tahun Ini — ' + data.nama);
                chart = new Chart(ctx, {
                    type: 'bar',
                    data: { labels: data.labels, datasets: [{ label: data.nama, data: data.hadir, backgroundColor: WARNA_P, borderRadius: 4, maxBarThickness: 34 }] },
                    options: chartOptions()
                });

                tabelPersenWrap.style.display = '';
                tabelPersenBody.innerHTML = '';
                data.labels.forEach(function (label, i) {
                    var persen = data.persentase[i];
                    var tr = document.createElement('tr');
                    tr.innerHTML = '<td>' + label + '</td><td class="num">' + data.hadir[i] + '</td><td class="num">' + data.sesi[i] + '</td><td class="num">' + (persen === null ? '-' : persen + '%') + '</td>';
                    tabelPersenBody.appendChild(tr);
                });
            } else {
                statSummary.innerHTML =
                    statBox(data.total_hadir, 'Total Hadir Tahun Ini') +
                    statBox(data.rata_rata_bulan, 'Rata-rata / Bulan') +
                    statBox(data.bulan_tertinggi, 'Bulan Kehadiran Tertinggi');

                chart = new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: data.labels,
                        datasets: [
                            { label: 'Laki-laki', data: data.laki_laki, backgroundColor: WARNA_L, borderRadius: 4, maxBarThickness: 22 },
                            { label: 'Perempuan', data: data.perempuan, backgroundColor: WARNA_P, borderRadius: 4, maxBarThickness: 22 }
                        ]
                    },
                    options: chartOptions()
                });
                tabelPersenWrap.style.display = 'none';
            }
        }).catch(function () {
            statSummary.innerHTML = '<p class="text-muted">Gagal memuat data statistik.</p>';
        });
    }

    function chartOptions() {
        return {
            responsive: true, maintainAspectRatio: false,
            scales: { y: { beginAtZero: true, ticks: { precision: 0 } } },
            plugins: { legend: { display: true, position: 'bottom' } }
        };
    }

    selectTahun.addEventListener('change', muatData);
    selectAnggota.addEventListener('change', muatData);
    muatData();
})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
