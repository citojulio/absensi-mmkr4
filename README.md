# Absensi Pengajian Remaja — MM KR4

Website absensi kehadiran untuk Pengajian Remaja Masjid, Muda Mudi Karang Empat (MM KR4). Dibangun dengan PHP + MySQL, disesuaikan agar bisa berjalan sebagai serverless function di **Vercel**.

## Fitur

1. **Form Absensi** (halaman publik) — pilih Gender → daftar Nama otomatis muncul sesuai gender → simpan kehadiran dengan tanggal otomatis. Anti-absen-dobel di hari yang sama.
2. **Laporan Harian** (admin) — tabel kehadiran per tanggal, dipisah Laki-laki/Perempuan, pencarian nama, ekspor Excel (.xlsx asli) & PDF.
3. **Statistik Kehadiran** (admin) — grafik kehadiran bulanan, bisa dilihat keseluruhan atau per anggota (lengkap dengan persentase kehadiran per bulan).
4. **Manajemen Data Anggota** (admin) — tambah/ubah/hapus anggota. Hapus bersifat *soft-delete* (data disembunyikan, bukan dihapus permanen) supaya riwayat Laporan & Statistik lama tidak rusak.

## ⚠️ Baca dulu: kenapa strukturnya agak berbeda dari PHP biasa

Vercel **tidak** menyediakan server PHP+MySQL tradisional. Supaya bisa jalan di sana, project ini pakai:

- **Runtime PHP komunitas** ([`vercel-php`](https://github.com/vercel-community/php)) — karena itu, semua halaman PHP diletakkan di dalam folder `api/`.
- **Session berbasis database** (bukan file) — lihat `api/includes/DbSessionHandler.php`. Ini WAJIB karena di lingkungan serverless, setiap request bisa dilayani "komputer" yang berbeda-beda dan tidak ada file lokal yang persisten antar request.
- **Database MySQL eksternal** — Vercel sendiri tidak menyediakan hosting database, jadi kita pakai layanan terpisah (panduan di bawah pakai **TiDB Cloud**, gratis & MySQL-compatible).

Semua bagian ini **sudah saya uji secara menyeluruh secara lokal** (login, semua CRUD, export, statistik, session tersimpan & terbaca dari database) dan berjalan benar. Yang **belum** bisa saya uji langsung dari sini adalah kombinasi nyata Vercel + TiDB Cloud di internet (sandbox saya tidak punya akses ke domain tersebut) — jadi ikuti langkah di bawah dengan teliti, dan lihat bagian Troubleshooting kalau ada yang tidak sesuai.

## Struktur Folder

```
├── api/                        # SEMUA halaman & logic PHP (jadi serverless function)
│   ├── index.php               # Form Absensi (halaman utama)
│   ├── login.php, logout.php
│   ├── laporan.php, laporan_export.php, laporan_pdf.php
│   ├── statistik.php, statistik_data.php
│   ├── anggota.php, anggota_simpan.php, anggota_hapus.php
│   ├── absen_simpan.php, get_anggota.php
│   ├── config/database.php     # Koneksi DB (baca dari Environment Variables)
│   ├── includes/                # Helper, header/footer, session handler
│   └── lib/                     # FPDF & SimpleXLSXGen (vendor, tanpa Composer)
├── assets/                     # CSS & JS (disajikan sebagai file statis)
├── database/schema.sql         # Struktur tabel + data awal 56 anggota
├── vercel.json                 # Konfigurasi runtime PHP untuk Vercel
└── .gitignore
```

---

## Langkah 1 — Siapkan Database (TiDB Cloud, gratis)

1. Daftar di **https://tidbcloud.com** (gratis, tanpa kartu kredit).
2. Buat cluster baru → pilih paket **Starter/Serverless** (gratis: 5 GiB storage, jauh lebih dari cukup untuk skala Pengajian Remaja).
3. Setelah cluster jadi, buka tab **Connect**. Catat:
   - **Host** (contoh: `gateway01.xx-xxxx-1.prod.aws.tidbcloud.com`)
   - **Port**: `4000`
   - **User**: formatnya `<prefix>.root` (prefix unik per cluster, tampil di halaman Connect)
   - **Password**: buat password baru di halaman yang sama
4. Buka menu **Chat2Query / SQL Editor** di dashboard TiDB Cloud, lalu tempel seluruh isi file `database/schema.sql`, jalankan (Run). Ini akan otomatis membuat semua tabel + 56 data anggota + akun admin default.

> Kalau kamu lebih nyaman pakai database MySQL lain (yang mengizinkan koneksi dari luar/remote), itu juga bisa — tinggal sesuaikan nilai environment variable di Langkah 3. Yang penting: mendukung protokol MySQL standar.

## Langkah 2 — Push ke GitHub

```bash
git init
git add .
git commit -m "Absensi MM KR4"
git branch -M main
git remote add origin https://github.com/USERNAME/NAMA-REPO.git
git push -u origin main
```

## Langkah 3 — Deploy ke Vercel

1. Buka **https://vercel.com** → **Add New → Project** → pilih/import repo GitHub kamu.
2. **Jangan klik Deploy dulu.** Buka bagian **Environment Variables**, isi semua ini (nilai dari Langkah 1):

   | Key | Value |
   |---|---|
   | `DB_HOST` | host dari TiDB Cloud |
   | `DB_PORT` | `4000` |
   | `DB_NAME` | `absensi_mmkr4` |
   | `DB_USER` | `<prefix>.root` |
   | `DB_PASS` | password TiDB Cloud kamu |
   | `DB_SSL` | `true` |

3. Klik **Deploy**. Tunggu proses build selesai.
4. Buka domain yang diberikan Vercel (`nama-project.vercel.app`) — halaman Form Absensi akan otomatis tampil di `/` berkat `vercel.json`.

## Langkah 4 — Verifikasi & Amankan

- [ ] Coba isi Form Absensi di halaman utama, pastikan tersimpan.
- [ ] Login admin di `/api/login.php` — **Username: `admin` / Password: `mmkr4admin`**
- [ ] **PENTING — segera ganti password default ini.** Cara paling gampang: buka SQL Editor TiDB Cloud, jalankan (ganti `HASH_BARU` dengan hasil dari perintah PHP di bawah):
  ```sql
  UPDATE admin SET password = 'HASH_BARU' WHERE username = 'admin';
  ```
  Untuk membuat hash password baru, jalankan di komputer yang ada PHP-nya:
  ```bash
  php -r "echo password_hash('password_baru_kamu', PASSWORD_DEFAULT);"
  ```
- [ ] Cek Laporan, Statistik, dan Data Anggota semua bisa diakses setelah login.
- [ ] Coba akses langsung `nama-project.vercel.app/api/config/database.php` di browser — seharusnya muncul error/kosong, BUKAN kode PHP mentah. Kalau ternyata kode PHP-nya terlihat, beri tahu saya supaya saya bantu perbaiki konfigurasinya (ini bagian yang tidak bisa saya tes langsung dari sandbox saya).

---

## Menjalankan di Lokal (opsional, untuk coba-coba dulu)

Butuh PHP 8.1+ dan MySQL/MariaDB terpasang di komputer kamu.

```bash
# Import struktur database
mysql -u root -p < database/schema.sql

# Jalankan dari folder root project (bukan dari dalam folder api/)
DB_USER=root DB_PASS="" php -S localhost:8000
```

Buka `http://localhost:8000/api/index.php` di browser.

## Troubleshooting

| Masalah | Kemungkinan Penyebab |
|---|---|
| "Gagal terhubung ke database" | Environment Variables di Vercel salah/belum diisi lengkap, atau `DB_SSL` belum `true` |
| Setelah login langsung ke-logout sendiri | Cek apakah tabel `sessions` sudah ter-import dari `schema.sql` |
| Halaman blank/500 setelah deploy | Buka tab **Logs** di dashboard Vercel (menu Deployments → klik deployment terbaru → Runtime Logs) untuk lihat pesan error PHP-nya |
| Import `schema.sql` gagal di TiDB Cloud | Coba jalankan per-blok (per `CREATE TABLE`) satu-satu lewat SQL Editor, karena kadang editor online kurang nyaman untuk file panjang sekaligus |

## Kredensial Admin Default

- Username: `admin`
- Password: `mmkr4admin`
- **Wajib diganti setelah deploy pertama** (lihat Langkah 4).
