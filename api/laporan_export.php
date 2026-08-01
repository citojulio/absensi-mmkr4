<?php
/**
 * Handler gabungan untuk ekspor Laporan (Harian & Bulanan) ke Excel ATAU PDF.
 * (Digabung jadi satu file supaya jumlah serverless function di Vercel
 * tidak melebihi batas paket Hobby/gratis: maksimal 12 function per deployment.)
 *
 * Query string:
 *   ?mode=harian&tanggal=YYYY-MM-DD    (default)
 *   ?mode=bulanan&bulan=YYYY-MM
 *   &tipe=excel (default) atau &tipe=pdf
 *
 * PENTING soal urutan kode di file ini: SEMUA function & class didefinisikan
 * DULU, baru logic pemanggilannya di paling BAWAH file (lihat catatan di
 * laporan_export.php versi sebelumnya soal kenapa ini wajib).
 */
require_once __DIR__ . '/includes/session_init.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
wajibLogin();

// =========================================================
// Excel — Laporan Harian
// =========================================================
function exportExcelHarian(string $tanggal, array $hadirL, array $hadirP): void
{
    require_once __DIR__ . '/lib/simplexlsxgen/SimpleXLSXGen.php';

    $bentukBaris = function (array $data): array {
        $rows = [['No', 'Nama', 'Kategori', 'Jam Hadir']];
        $no = 1;
        foreach ($data as $r) {
            $rows[] = [$no++, $r['nama'], $r['kategori'] ?: '-', formatJam($r['waktu_absen'])];
        }
        if (count($rows) === 1) {
            $rows[] = ['-', 'Tidak ada data hadir pada tanggal ini', '', ''];
        }
        return $rows;
    };

    $xlsx = \Shuchkin\SimpleXLSXGen::fromArray($bentukBaris($hadirL), 'Laki-laki');
    $xlsx->addSheet($bentukBaris($hadirP), 'Perempuan');

    $xlsx->downloadAs('Laporan_Harian_MMKR4_' . $tanggal . '.xlsx');
}

// =========================================================
// Excel — Laporan Bulanan (matrix nama x tanggal)
// =========================================================
function exportExcelBulanan(string $bulan, array $matrixL, array $matrixP, array $tanggalSesi): void
{
    require_once __DIR__ . '/lib/simplexlsxgen/SimpleXLSXGen.php';

    $bentukBarisBulanan = function (array $anggotaList) use ($tanggalSesi): array {
        $header = ['No', 'Nama'];
        foreach ($tanggalSesi as $tgl) {
            $header[] = date('d/m', strtotime($tgl));
        }
        $header[] = 'Total';
        $rows = [$header];

        $no = 1;
        foreach ($anggotaList as $a) {
            $row = [$no++, $a['nama']];
            $total = 0;
            foreach ($tanggalSesi as $tgl) {
                $hadir = isset($a['tanggal_hadir'][$tgl]);
                if ($hadir) {
                    $total++;
                }
                $row[] = $hadir ? 'Hadir' : '';
            }
            $row[] = $total;
            $rows[] = $row;
        }
        if (count($rows) === 1) {
            $kosong = array_fill(0, count($tanggalSesi), '');
            $rows[] = array_merge(['-', 'Tidak ada data pada bulan ini'], $kosong, ['']);
        }
        return $rows;
    };

    $xlsx = \Shuchkin\SimpleXLSXGen::fromArray($bentukBarisBulanan($matrixL), 'Laki-laki');
    $xlsx->addSheet($bentukBarisBulanan($matrixP), 'Perempuan');

    $xlsx->downloadAs('Laporan_Bulanan_MMKR4_' . $bulan . '.xlsx');
}

// =========================================================
// PDF — class dasar dipakai bersama Harian & Bulanan
// =========================================================

/** FPDF core font pakai encoding Latin-1, sedangkan data kita UTF-8 dari MySQL. */
function pdfText(?string $s): string
{
    return iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $s ?? '');
}

require_once __DIR__ . '/lib/fpdf/fpdf.php';

class LaporanPDF extends FPDF
{
    public string $judul = 'Laporan Absensi Pengajian Remaja - MM KR4';
    public string $subjudul = '';

    function Header(): void
    {
        $this->SetFont('Helvetica', 'B', 14);
        $this->SetTextColor(36, 70, 59);
        $this->Cell(0, 8, pdfText($this->judul), 0, 1);
        $this->SetFont('Helvetica', '', 10);
        $this->SetTextColor(110, 106, 94);
        $this->Cell(0, 6, pdfText($this->subjudul), 0, 1);
        $this->Ln(3);
        $this->SetDrawColor(201, 151, 63);
        $this->SetLineWidth(0.6);
        $lebar = $this->CurOrientation === 'L' ? 287 : 200;
        $this->Line(10, $this->GetY(), $lebar, $this->GetY());
        $this->Ln(6);
    }

    function Footer(): void
    {
        $this->SetY(-15);
        $this->SetFont('Helvetica', 'I', 8);
        $this->SetTextColor(150, 150, 150);
        $this->Cell(0, 10, pdfText('Dicetak ' . date('d-m-Y H:i')) . ' WIB - Halaman ' . $this->PageNo(), 0, 0, 'C');
    }

    /** Tabel Laporan Harian: No | Nama | Kategori | Jam Hadir */
    function tabelHarian(string $judul, array $rows, int $totalAktif): void
    {
        $this->SetFont('Helvetica', 'B', 11);
        $this->SetTextColor(36, 70, 59);
        $this->Cell(0, 8, pdfText($judul . ' (' . count($rows) . ' dari ' . $totalAktif . ' hadir)'), 0, 1);

        $this->SetFont('Helvetica', 'B', 9);
        $this->SetFillColor(228, 234, 230);
        $this->SetTextColor(23, 46, 38);
        $this->Cell(12, 8, 'No', 0, 0, 'C', true);
        $this->Cell(90, 8, pdfText('Nama'), 0, 0, 'L', true);
        $this->Cell(40, 8, pdfText('Kategori'), 0, 0, 'L', true);
        $this->Cell(38, 8, pdfText('Jam Hadir'), 0, 1, 'C', true);

        $this->SetFont('Helvetica', '', 9);
        $this->SetTextColor(35, 33, 28);
        if (empty($rows)) {
            $this->Cell(180, 8, pdfText('Belum ada yang absen pada tanggal ini.'), 0, 1, 'C');
        } else {
            $no = 1;
            $fill = false;
            foreach ($rows as $r) {
                $this->SetFillColor(251, 250, 246);
                $this->Cell(12, 7, (string) $no++, 0, 0, 'C', $fill);
                $this->Cell(90, 7, pdfText($r['nama']), 0, 0, 'L', $fill);
                $this->Cell(40, 7, pdfText($r['kategori'] ?: '-'), 0, 0, 'L', $fill);
                $this->Cell(38, 7, pdfText(formatJam($r['waktu_absen'])), 0, 1, 'C', $fill);
                $fill = !$fill;
            }
        }
        $this->Ln(6);
    }

    /** Tabel Laporan Bulanan: matrix No | Nama | tanggal-tanggal sesi | Total */
    function tabelBulanan(string $judul, array $anggotaList, array $tanggalSesi): void
    {
        $this->SetFont('Helvetica', 'B', 11);
        $this->SetTextColor(36, 70, 59);
        $this->Cell(0, 8, pdfText($judul . ' (' . count($anggotaList) . ' anggota)'), 0, 1);

        if (empty($tanggalSesi)) {
            $this->SetFont('Helvetica', '', 9);
            $this->Cell(0, 8, pdfText('Belum ada sesi pengajian tercatat pada bulan ini.'), 0, 1);
            $this->Ln(4);
            return;
        }

        $lebarNo = 10;
        $lebarNama = 55;
        $lebarTotal = 16;
        $lebarHalaman = $this->CurOrientation === 'L' ? 277 : 190;
        $sisaLebar = $lebarHalaman - $lebarNo - $lebarNama - $lebarTotal;
        $lebarTanggal = max(9, $sisaLebar / count($tanggalSesi));

        $this->SetFont('Helvetica', 'B', 8);
        $this->SetFillColor(228, 234, 230);
        $this->SetTextColor(23, 46, 38);
        $this->Cell($lebarNo, 8, 'No', 0, 0, 'C', true);
        $this->Cell($lebarNama, 8, pdfText('Nama'), 0, 0, 'L', true);
        foreach ($tanggalSesi as $tgl) {
            $this->Cell($lebarTanggal, 8, date('d/m', strtotime($tgl)), 0, 0, 'C', true);
        }
        $this->Cell($lebarTotal, 8, 'Tot', 0, 1, 'C', true);

        $this->SetFont('Helvetica', '', 8);
        $this->SetTextColor(35, 33, 28);
        if (empty($anggotaList)) {
            $lebarTotalTabel = $lebarNo + $lebarNama + $lebarTotal + ($lebarTanggal * count($tanggalSesi));
            $this->Cell($lebarTotalTabel, 8, pdfText('Belum ada anggota pada gender ini.'), 0, 1, 'C');
        } else {
            $no = 1;
            $fill = false;
            foreach ($anggotaList as $a) {
                $this->SetFillColor(251, 250, 246);
                $this->Cell($lebarNo, 7, (string) $no++, 0, 0, 'C', $fill);
                $this->Cell($lebarNama, 7, pdfText($a['nama']), 0, 0, 'L', $fill);
                $total = 0;
                foreach ($tanggalSesi as $tgl) {
                    $hadir = isset($a['tanggal_hadir'][$tgl]);
                    if ($hadir) $total++;
                    $this->Cell($lebarTanggal, 7, $hadir ? pdfText('v') : '-', 0, 0, 'C', $fill);
                }
                $this->Cell($lebarTotal, 7, (string) $total, 0, 1, 'C', $fill);
                $fill = !$fill;
            }
        }
        $this->Ln(6);
    }
}

function exportPdfHarian(string $tanggal, array $hadirL, array $hadirP, array $totalGender): void
{
    $pdf = new LaporanPDF();
    $pdf->subjudul = formatTanggalIndo($tanggal);
    $pdf->AddPage();
    $pdf->tabelHarian('Laki-laki', $hadirL, $totalGender['L']);
    $pdf->tabelHarian('Perempuan', $hadirP, $totalGender['P']);
    $pdf->Output('I', 'Laporan_Harian_MMKR4_' . $tanggal . '.pdf');
}

function exportPdfBulanan(string $bulan, array $matrixL, array $matrixP, array $tanggalSesi): void
{
    $pdf = new LaporanPDF();
    $pdf->subjudul = namaBulan((int) date('n', strtotime($bulan . '-01'))) . ' ' . date('Y', strtotime($bulan . '-01'));
    $pdf->AddPage(count($tanggalSesi) > 5 ? 'L' : 'P');
    $pdf->tabelBulanan('Laki-laki', $matrixL, $tanggalSesi);
    $pdf->tabelBulanan('Perempuan', $matrixP, $tanggalSesi);
    $pdf->Output('I', 'Laporan_Bulanan_MMKR4_' . $bulan . '.pdf');
}

// =========================================================
// Logic utama - dijalankan PALING BAWAH, setelah semua function/class di atas siap
// =========================================================
$mode = ($_GET['mode'] ?? 'harian') === 'bulanan' ? 'bulanan' : 'harian';
$tipe = ($_GET['tipe'] ?? 'excel') === 'pdf' ? 'pdf' : 'excel';

$pdo = getDB();

$totalGender = ['L' => 0, 'P' => 0];
foreach ($pdo->query("SELECT gender, COUNT(*) c FROM anggota WHERE is_active = 1 GROUP BY gender") as $row) {
    $totalGender[$row['gender']] = (int) $row['c'];
}

if ($mode === 'harian') {
    $tanggal = tanggalValidAtauHariIni($_GET['tanggal'] ?? null);

    $stmt = $pdo->prepare(
        "SELECT a.nama, a.kategori, ab.waktu_absen
         FROM absensi ab JOIN anggota a ON a.id = ab.anggota_id
         WHERE ab.tanggal = :tanggal AND a.gender = :gender
         ORDER BY a.nama ASC"
    );
    $stmt->execute(['tanggal' => $tanggal, 'gender' => 'L']);
    $hadirL = $stmt->fetchAll();
    $stmt->execute(['tanggal' => $tanggal, 'gender' => 'P']);
    $hadirP = $stmt->fetchAll();

    if ($tipe === 'pdf') {
        exportPdfHarian($tanggal, $hadirL, $hadirP, $totalGender);
    } else {
        exportExcelHarian($tanggal, $hadirL, $hadirP);
    }
} else {
    $bulan = bulanValidAtauSekarang($_GET['bulan'] ?? null);
    $awalBulan = date('Y-m-01', strtotime($bulan . '-01'));
    $akhirBulan = date('Y-m-t', strtotime($bulan . '-01'));

    $tanggalSesi = $pdo->prepare("SELECT DISTINCT tanggal FROM absensi WHERE tanggal BETWEEN :awal AND :akhir ORDER BY tanggal ASC");
    $tanggalSesi->execute(['awal' => $awalBulan, 'akhir' => $akhirBulan]);
    $tanggalSesi = $tanggalSesi->fetchAll(PDO::FETCH_COLUMN);

    $matrixL = ambilMatrixBulanan($pdo, 'L', $awalBulan, $akhirBulan);
    $matrixP = ambilMatrixBulanan($pdo, 'P', $awalBulan, $akhirBulan);

    if ($tipe === 'pdf') {
        exportPdfBulanan($bulan, $matrixL, $matrixP, $tanggalSesi);
    } else {
        exportExcelBulanan($bulan, $matrixL, $matrixP, $tanggalSesi);
    }
}
