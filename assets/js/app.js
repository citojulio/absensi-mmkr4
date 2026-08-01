// =========================================================
// app.js — dipakai bersama di semua halaman.
// Setiap blok dibuat aman (cek elemen ada dulu) supaya satu
// file ini bisa dipasang di semua halaman tanpa error.
// =========================================================

document.addEventListener('DOMContentLoaded', function () {

    // ---------- Navbar toggle (mobile) ----------
    var navToggle = document.getElementById('navToggle');
    var navLinks = document.getElementById('navLinks');
    if (navToggle && navLinks) {
        navToggle.addEventListener('click', function () {
            var isOpen = navLinks.classList.toggle('open');
            navToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        });
    }

    // ---------- Form Absensi: Gender -> daftar Nama ----------
    var genderRadios = document.querySelectorAll('input[name="gender"]');
    var namaSelect = document.getElementById('namaSelect');
    var namaHint = document.getElementById('namaHint');
    var btnSubmitAbsen = document.getElementById('btnSubmitAbsen');

    if (genderRadios.length && namaSelect) {
        genderRadios.forEach(function (radio) {
            radio.addEventListener('change', function () {
                var gender = this.value;
                namaSelect.disabled = true;
                namaSelect.innerHTML = '<option value="">Memuat daftar nama...</option>';
                if (btnSubmitAbsen) btnSubmitAbsen.disabled = true;

                fetch('get_anggota.php?gender=' + encodeURIComponent(gender))
                    .then(function (res) {
                        if (!res.ok) throw new Error('Gagal memuat data');
                        return res.json();
                    })
                    .then(function (data) {
                        namaSelect.innerHTML = '';
                        var placeholder = document.createElement('option');
                        placeholder.value = '';
                        placeholder.textContent = data.length ? '-- Pilih nama Anda --' : 'Tidak ada anggota terdaftar';
                        namaSelect.appendChild(placeholder);

                        data.forEach(function (anggota) {
                            var opt = document.createElement('option');
                            opt.value = anggota.id;
                            opt.textContent = anggota.nama;
                            namaSelect.appendChild(opt);
                        });
                        namaSelect.disabled = false;
                        if (namaHint) {
                            namaHint.textContent = data.length + ' nama ditemukan untuk kategori ini.';
                        }
                    })
                    .catch(function () {
                        namaSelect.innerHTML = '<option value="">Gagal memuat, coba pilih ulang gender</option>';
                    });
            });
        });

        namaSelect.addEventListener('change', function () {
            if (btnSubmitAbsen) btnSubmitAbsen.disabled = !namaSelect.value;
        });
    }

    // ---------- Pencarian tabel sederhana (client-side) ----------
    // Elemen pemicu: <input data-table-search="ID_TABEL"> atau "ID_TABEL1,ID_TABEL2" untuk beberapa tabel sekaligus.
    document.querySelectorAll('[data-table-search]').forEach(function (input) {
        var ids = input.getAttribute('data-table-search').split(',').map(function (s) { return s.trim(); });
        var tables = ids.map(function (id) { return document.getElementById(id); }).filter(Boolean);
        if (!tables.length) return;

        input.addEventListener('input', function () {
            var keyword = input.value.trim().toLowerCase();
            tables.forEach(function (table) {
                var adaBaris = false;
                table.querySelectorAll('tbody tr[data-row]').forEach(function (row) {
                    var cocok = row.textContent.toLowerCase().indexOf(keyword) !== -1;
                    row.style.display = cocok ? '' : 'none';
                    if (cocok) adaBaris = true;
                });
                var emptyState = table.querySelector('.js-empty-search');
                if (emptyState) emptyState.style.display = adaBaris ? 'none' : '';
            });
        });
    });

    // ---------- Modal Tambah/Ubah Anggota ----------
    var modalOverlay = document.getElementById('modalAnggota');
    if (modalOverlay) {
        var form = document.getElementById('formAnggota');
        var modalTitle = document.getElementById('modalAnggotaTitle');
        var fieldId = document.getElementById('field_id');
        var fieldNama = document.getElementById('field_nama');
        var fieldGender = document.getElementById('field_gender');
        var fieldKategori = document.getElementById('field_kategori');

        function bukaModal() { modalOverlay.classList.add('open'); }
        function tutupModal() { modalOverlay.classList.remove('open'); }

        var btnTambah = document.getElementById('btnTambahAnggota');
        if (btnTambah) {
            btnTambah.addEventListener('click', function () {
                form.reset();
                fieldId.value = '';
                modalTitle.textContent = 'Tambah Anggota';
                bukaModal();
            });
        }

        document.querySelectorAll('.btn-edit-anggota').forEach(function (btn) {
            btn.addEventListener('click', function () {
                fieldId.value = btn.dataset.id;
                fieldNama.value = btn.dataset.nama;
                fieldGender.value = btn.dataset.gender;
                fieldKategori.value = btn.dataset.kategori || '';
                modalTitle.textContent = 'Ubah Data Anggota';
                bukaModal();
            });
        });

        document.querySelectorAll('[data-close-modal]').forEach(function (el) {
            el.addEventListener('click', tutupModal);
        });
        modalOverlay.addEventListener('click', function (e) {
            if (e.target === modalOverlay) tutupModal();
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') tutupModal();
        });
    }

    // ---------- Konfirmasi hapus anggota ----------
    document.querySelectorAll('.form-hapus-anggota').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            var nama = form.dataset.nama || 'anggota ini';
            if (!confirm('Hapus ' + nama + ' dari daftar anggota?\n\nRiwayat absensi yang sudah tercatat sebelumnya tetap tersimpan di laporan.')) {
                e.preventDefault();
            }
        });
    });

});
