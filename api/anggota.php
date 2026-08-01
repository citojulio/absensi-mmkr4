<?php
require_once __DIR__ . '/includes/session_init.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
wajibLogin();

$pdo = getDB();
$daftarAnggota = $pdo->query(
    "SELECT id, nama, gender, kategori FROM anggota WHERE is_active = 1 ORDER BY gender ASC, nama ASC"
)->fetchAll();

$totalL = count(array_filter($daftarAnggota, fn($a) => $a['gender'] === 'L'));
$totalP = count(array_filter($daftarAnggota, fn($a) => $a['gender'] === 'P'));

$pageTitle = 'Data Anggota';
$activeNav = 'anggota';
require_once __DIR__ . '/includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <div>
            <h2>Data Anggota</h2>
            <span class="text-muted"><?= count($daftarAnggota) ?> anggota aktif &middot; <?= $totalL ?> Laki-laki, <?= $totalP ?> Perempuan</span>
        </div>
        <button type="button" class="btn btn-primary" id="btnTambahAnggota">+ Tambah Anggota</button>
    </div>

    <div class="toolbar">
        <div class="form-group grow">
            <label for="cariAnggota">Cari Nama</label>
            <input type="search" id="cariAnggota" placeholder="Ketik nama untuk menyaring..." data-table-search="tabelAnggota">
        </div>
    </div>

    <div class="table-wrap">
        <table id="tabelAnggota">
            <thead><tr><th class="num">No</th><th>Nama</th><th>Gender</th><th>Kategori</th><th>Aksi</th></tr></thead>
            <tbody>
            <?php if (empty($daftarAnggota)): ?>
                <tr><td colspan="5" class="table-empty">Belum ada data anggota.</td></tr>
            <?php else: $no = 1; foreach ($daftarAnggota as $a): ?>
                <tr data-row>
                    <td class="num"><?= $no++ ?></td>
                    <td><?= h($a['nama']) ?></td>
                    <td><span class="badge badge-gender-<?= strtolower($a['gender']) ?>"><?= h(labelGender($a['gender'])) ?></span></td>
                    <td><?= h($a['kategori'] ?: '-') ?></td>
                    <td class="nowrap">
                        <button type="button" class="btn btn-outline btn-sm btn-edit-anggota"
                            data-id="<?= (int) $a['id'] ?>" data-nama="<?= h($a['nama']) ?>"
                            data-gender="<?= h($a['gender']) ?>" data-kategori="<?= h($a['kategori'] ?? '') ?>">Ubah</button>
                        <form action="anggota_simpan.php" method="POST" class="form-hapus-anggota" data-nama="<?= h($a['nama']) ?>" style="display:inline;">
                            <input type="hidden" name="action" value="hapus">
                            <input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
                            <button type="submit" class="btn btn-danger btn-sm">Hapus</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            <tr class="js-empty-search" style="display:none;"><td colspan="5" class="table-empty">Tidak ada nama yang cocok dengan pencarian.</td></tr>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Tambah/Ubah Anggota -->
<div class="modal-overlay" id="modalAnggota">
    <div class="modal-box">
        <h3 id="modalAnggotaTitle">Tambah Anggota</h3>
        <form action="anggota_simpan.php" method="POST" id="formAnggota">
            <input type="hidden" name="id" id="field_id" value="">
            <div class="form-group">
                <label for="field_nama">Nama Lengkap</label>
                <input type="text" name="nama" id="field_nama" required maxlength="100" placeholder="Contoh: AHMAD FAUZAN">
            </div>
            <div class="form-group">
                <label for="field_gender">Gender</label>
                <select name="gender" id="field_gender" required>
                    <option value="">-- Pilih --</option>
                    <option value="L">Laki-laki</option>
                    <option value="P">Perempuan</option>
                </select>
            </div>
            <div class="form-group">
                <label for="field_kategori">Kategori Usia <span class="text-muted">(opsional)</span></label>
                <select name="kategori" id="field_kategori">
                    <option value="">-- Tidak ada --</option>
                    <?php foreach (daftarKategori() as $k): ?>
                        <option value="<?= h($k) ?>"><?= h($k) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-outline" data-close-modal>Batal</button>
                <button type="submit" class="btn btn-primary">Simpan</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
