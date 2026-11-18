# ☕ Sistem Informasi Penjualan Berbasis Web — Sangur Cafe Pekanbaru

> Rancang Bangun Sistem Informasi Penjualan Berbasis Web pada Sangur Cafe Pekanbaru
> Penulis: Hendri Suhtriman (NIM 2398744422) — Prodi Sistem Informasi, ITB Master Pekanbaru
> Dosen Pembimbing: Jonni Adi Putra, M.Kom.
> Tech Stack: PHP, MySQL, HTML, CSS, JavaScript, Bootstrap 5 (semua library offline).

---

## 📋 Gambaran Singkat

Pelanggan **scan QR Code** yang ditempel di meja → terbuka halaman menu digital → pilih menu & jumlah → kirim pesanan. Pesanan masuk **real-time** ke layar **Kasir** dan **Kitchen/Dapur**. Dapur mengupdate status pengerjaan. Kasir memproses pembayaran tunai & mencetak struk. **Owner** melihat dashboard, laporan penjualan, serta mengelola menu, meja+QR, dan akun pengguna.

**4 Hak Akses:**
| Role | Login? | Fungsi Utama |
|---|---|---|
| 👤 Pelanggan | Tidak (cukup scan QR) | Lihat menu, pilih item & jumlah, kirim pesanan, pantau status pesanan |
| 🧾 Kasir | Ya | Pesanan masuk real-time, proses pembayaran tunai, cetak struk, riwayat transaksi |
| 👨‍🍳 Kitchen | Ya | Daftar pesanan yang harus disiapkan, update status (disiapkan → siap disajikan) |
| 🧑‍💼 Owner | Ya | Dashboard ringkasan, laporan penjualan (filter periode), kelola menu, kelola meja & generate QR, kelola akun pengguna |

---

## 🚀 Petunjuk Instalasi (XAMPP — Windows)

### 1. Siapkan XAMPP
- Install [XAMPP for Windows](https://www.apachefriends.org/) (Apache + MySQL).
- Jalankan **XAMPP Control Panel**, klik **Start** pada **Apache** dan **MySQL**.

### 2. Copy folder proyek
- Copy folder `sangur-cafe` ke dalam `C:\xampp\htdocs\` sehingga menjadi `C:\xampp\htdocs\sangur-cafe\`.
- *(Opsional)* Jika Anda mengganti nama folder, ubah satu baris di `config/database.php`:
  `define('BASE_URL', '/nama-folder-anda/');`

### 3. Import database
- Buka browser → `http://localhost/phpmyadmin`
- Klik tab **Import** → **Choose File** → pilih `sangur-cafe/database.sql` → **Go**.
- Database `sangur_cafe` beserta tabel & data sample akan otomatis dibuat.
- *Jika MySQL Anda memakai password root, sesuaikan `DB_PASS` di `config/database.php`.*

### 4. Akses sistem
| Halaman | URL |
|---|---|
| Landing / Login Staff | `http://localhost/sangur-cafe/` |
| Halaman Pelanggan (simulasi scan QR Meja 1) | `http://localhost/sangur-cafe/customer/menu.php?table=1` |
| Generate & cetak QR (setelah login owner) | `http://localhost/sangur-cafe/owner/tables.php` |

### 5. Akun default (sudah ada di database)
| Role | Username | Password |
|---|---|---|
| Owner | `owner` | `owner123` |
| Kasir | `kasir` | `kasir123` |
| Kitchen | `kitchen` | `kitchen123` |

> Password disimpan dalam bentuk **hash bcrypt** (tidak plain-text). Ganti password secara berkala melalui menu Owner → Pengguna.

---

## 📁 Struktur Folder

```
sangur-cafe/
├── config/database.php          # Koneksi PDO + konfigurasi (BASE_URL, DB)
├── includes/
│   ├── auth.php                 # Session, login/role check, helper (rp, e, tgl_id, status_label, flash)
│   ├── header.php               # Header + navbar (role-aware) + Bootstrap CSS
│   └── footer.php               # Footer + script JS (jQuery, Bootstrap, app.js)
├── assets/
│   ├── css/style.css            # Tema cafe hangat (coklat-karamel-krem)
│   ├── js/app.js                # Helper JS (toast, format Rupiah)
│   ├── img/*.svg                # 12 placeholder gambar menu (offline)
│   └── lib/                     # Library OFFLINE: Bootstrap 5, jQuery, qrcode.js
├── customer/                    # Modul pelanggan (tanpa login)
│   ├── menu.php                 # Tampil menu setelah scan QR (?table=N)
│   ├── cart.php                 # Keranjang & kirim pesanan
│   └── order-status.php         # Pantau status pesanan (auto-refresh)
├── kasir/                       # Modul kasir
│   ├── dashboard.php            # Pesanan masuk real-time (AJAX polling)
│   ├── payment.php              # Proses pembayaran + cetak struk
│   └── transactions.php         # Riwayat transaksi + filter tanggal
├── kitchen/
│   └── dashboard.php            # Antrian dapur + update status pesanan
├── owner/                       # Modul owner
│   ├── dashboard.php            # Statistik hari ini + menu terlaris
│   ├── report.php               # Laporan penjualan (filter periode, cetak, CSV)
│   ├── menu-manage.php          # CRUD menu (+ upload gambar)
│   ├── menu-form.php            # Form tambah/edit menu
│   ├── tables.php               # CRUD meja + generate & cetak QR Code
│   └── users.php                # CRUD akun pengguna
├── api/                         # Endpoint JSON (AJAX real-time)
│   ├── get-orders.php           # Ambil daftar pesanan (?role=kasir|kitchen)
│   ├── update-status.php        # Update status pesanan (kitchen)
│   ├── pay-order.php            # Proses pembayaran (kasir)
│   └── submit-order.php         # Terima pesanan dari pelanggan
├── index.php                    # Landing page + redirect
├── login.php / logout.php       # Autentikasi staff
├── database.sql                 # Dump database + data sample
└── README.md                    # Dokumen ini
```

---

## 🔄 Alur Kerja Sistem (End-to-End)

1. **Owner** login → menu **Meja & QR** → cetak QR → tempel di setiap meja.
2. **Pelanggan** scan QR → terbuka `customer/menu.php?table=N` → pilih menu → **Kirim Pesanan**.
3. Pesanan tersimpan status `pending` → otomatis muncul (real-time, ±4 detik) di dashboard **Kasir** dan **Kitchen**.
4. **Kitchen** klik **Mulai Siapkan** (status → `disiapkan`) → setelah matang klik **Siap Disajikan** (status → `siap`). Pesanan hilang dari layar dapur.
5. **Kasir** pilih pesanan → **Proses Pembayaran** → input uang tunai → sistem hitung kembalian → **Bayar** → status `paid` → **Cetak Struk**.
6. **Owner** lihat **Dashboard** (pesanan & pendapatan hari ini, menu terlaris) dan **Laporan** (filter harian/mingguan/bulanan, cetak/CSV).

---

## 🧪 Skenario Black-Box Testing (minimal 8)

| # | Skenario Uji | Input yang Diberikan | Hasil yang Diharapkan | Status |
|---|---|---|---|---|
| 1 | **Akses pengguna tanpa login** | Buka `owner/dashboard.php` langsung tanpa login | Dialihkan ke `login.php` dengan pesan "Silakan login terlebih dahulu" | ☐ |
| 2 | **Login dengan kredensial salah** | username=kasir, password=salah | Tetap di halaman login, muncul pesan error "Username atau password salah" | ☐ |
| 3 | **Login valid per role** | owner/owner123, kasir/kasir123, kitchen/kitchen123 | Masuk ke dashboard sesuai peran masing-masing | ☐ |
| 4 | **Akses halaman lintas-role** | Login sebagai kasir, buka `owner/report.php` | Muncul halaman "Akses Ditolak" (HTTP 403) | ☐ |
| 5 | **Scan barcode / buka menu meja** | Buka `customer/menu.php?table=3` | Tampil daftar menu makanan & minuman, header bertuliskan "Meja 3" | ☐ |
| 6 | **Menu habis tidak bisa dipilih** | Tambah menu berstatus "Habis" (Roti Bakar Coklat) | Tombol tambah dinonaktifkan / item tidak masuk keranjang | ☐ |
| 7 | **Pemilihan item & jumlah** | Pilih 2 menu dengan qty berbeda → Lihat Keranjang | Item, qty, subtotal, dan total sesuai; qty bisa diubah +/- | ☐ |
| 8 | **Pengiriman pesanan** | Klik "Kirim Pesanan" dari keranjang | Tersimpan di database, muncul nomor pesanan, teralihkan ke halaman status | ☐ |
| 9 | **Pesanan tampil real-time di kasir & kitchen** | Setelah skenario 8, lihat dashboard kasir & kitchen (tanpa refresh manual) | Pesanan baru muncul otomatis dalam ≤5 detik (badge "Menunggu Konfirmasi") | ☐ |
| 10 | **Perubahan status oleh kitchen** | Kitchen klik "Mulai Siapkan" lalu "Siap Disajikan" | Status berubah → badge berubah warna; pesanan siap hilang dari daftar dapur | ☐ |
| 11 | **Pencatatan pembayaran & kembalian** | Kasir bayar pesanan total Rp 49.000 dengan uang Rp 100.000 | Kembalian Rp 51.000 terhitung otomatis; status jadi "Lunas"; tersimpan di transaksi & struk bisa dicetak | ☐ |
| 12 | **Pembayaran kurang dari total** | Input amount_paid < total | Tombol bayar dinonaktifkan / muncul peringatan "Uang kurang" | ☐ |
| 13 | **Laporan owner sesuai periode** | Owner buka Laporan → filter 7 hari terakhir → Export CSV | Tabel menampilkan hanya pesanan dalam periode, total pendapatan benar, file CSV terunduh | ☐ |
| 14 | **Kelola menu (tambah/edit/hapus)** | Owner tambah menu baru → edit harga → hapus | Perubahan tersimpan & langsung terlihat di halaman pelanggan | ☐ |
| 15 | **Generate & cetak QR meja** | Owner tambah meja baru → tampilkan QR → cetak | QR terbaca oleh HP dan mengarah ke `menu.php?table=N` | ☐ |
| 16 | **SQL Injection pada login** | Input username: `' OR '1'='1` | Login gagal, tidak masuk sistem (prepared statement) | ☐ |

> Cara menjalankan: lakukan skenario satu per satu pada browser, centang kolom Status. Pengujian black-box hanya mengamati input/output tanpa melihat kode.

---

## 🔐 Catatan Keamanan

- Password di-hash dengan `password_hash()` (bcrypt) — tidak ada plain-text di database.
- Semua query memakai **prepared statement** (PDO) → anti SQL Injection.
- Semua output di-escape dengan `htmlspecialchars()` → anti XSS.
- Session + role check di setiap halaman & API terproteksi → tidak bisa diakses tanpa login / lintas role.
- Upload gambar menu dibatasi ekstensi (jpg/jpeg/png/svg/gif).

## ⚙️ Catatan Teknis

- Real-time memakai **AJAX polling** (`setInterval` 4 detik) ke `api/get-orders.php` — tidak butuh WebSocket.
- QR Code digenerate **offline** dengan `qrcode.js` (tidak perlu internet / API eksternal).
- Semua library (Bootstrap 5, jQuery, qrcode.js) disimpan lokal di `assets/lib/` → sistem tetap jalan tanpa internet.
- Gambar menu memakai placeholder SVG lokal di `assets/img/` — dapat diganti dengan foto asli (upload lewat Owner → Kelola Menu).
- Diuji dengan PHP 8.x & MySQL/MariaDB XAMPP. Kompatibel dengan PHP 7.4+.

## ❓ Troubleshooting

| Gejala | Solusi |
|---|---|
| "Koneksi database gagal" | Pastikan MySQL XAMPP menyala & database `sangur_cafe` sudah di-import. |
| Halaman 404 / asset tidak muncul | Pastikan folder bernama `sangur-cafe` di htdocs, atau ubah `BASE_URL` di `config/database.php`. |
| QR scan membuka alamat salah | Di `owner/tables.php`, URL QR mengikuti alamat yang dipakai saat mengakses; cetak ulang jika pindah server. |
| Upload gambar gagal | Pastikan folder `assets/img/` dapat ditulis (permission), dan ukuran file < 2MB. |
