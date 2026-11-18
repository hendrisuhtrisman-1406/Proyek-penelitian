<?php
// ============================================================
// HALAMAN DEPAN (LANDING) - Sangur Cafe POS
// Jika sudah login -> redirect ke dashboard sesuai role.
// Jika belum -> tampilkan landing page sederhana.
// ============================================================
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

if (is_login()) {
    $role = $_SESSION['role'] ?? '';
    if ($role === 'owner')   redirect('owner/dashboard.php');
    if ($role === 'kasir')   redirect('kasir/dashboard.php');
    if ($role === 'kitchen') redirect('kitchen/dashboard.php');
    redirect('login.php');
}

$page_title = 'Selamat Datang';
require __DIR__ . '/includes/header.php';
?>
<style>
.hero-cafe {
    background: linear-gradient(135deg, #4e342e, #a0522d);
    color: #fff;
    border-radius: 18px;
    padding: 3.5rem 2rem;
    text-align: center;
    box-shadow: 0 8px 30px rgba(78,52,46,0.25);
}
.hero-cafe h1 { font-weight: 800; font-size: 2.6rem; margin-bottom: 0.5rem; }
.hero-cafe .lead { opacity: 0.92; max-width: 620px; margin: 0 auto 1.5rem; }
.feature-card {
    background: #fff; border-radius: 14px; padding: 1.5rem; height: 100%;
    box-shadow: 0 3px 10px rgba(78,52,46,0.10); border-top: 4px solid #a0522d;
}
.feature-card h5 { color: #4e342e; font-weight: 700; }
.customer-note {
    background: #f5ebe0; border-left: 5px solid #d4a017;
    border-radius: 10px; padding: 1.2rem 1.5rem;
}
</style>

<div class="hero-cafe mb-4">
    <h1>Sangur Cafe</h1>
    <p class="lead">Sistem Informasi Penjualan Berbasis Web untuk mengelola pesanan, dapur, meja pelanggan via QR code, hingga laporan penjualan — dalam satu aplikasi.</p>
    <a href="<?= BASE_URL ?>login.php" class="btn btn-light btn-lg fw-bold px-4">Login Staff</a>
</div>

<div class="row g-3 mb-4">
    <div class="col-12 col-md-4">
        <div class="feature-card">
            <h5>Pesanan Cepat</h5>
            <p class="text-muted mb-0">Pelanggan memesan langsung dari meja dengan memindai QR code. Pesanan masuk real-time ke kasir dan dapur.</p>
        </div>
    </div>
    <div class="col-12 col-md-4">
        <div class="feature-card">
            <h5>Dapur Terintegrasi</h5>
            <p class="text-muted mb-0">Dapur melihat daftar pesanan yang harus disiapkan dan memperbarui status hingga siap disajikan.</p>
        </div>
    </div>
    <div class="col-12 col-md-4">
        <div class="feature-card">
            <h5>Laporan Owner</h5>
            <p class="text-muted mb-0">Owner memantau pendapatan harian, menu terlaris, dan mencetak / mengekspor laporan penjualan kapan saja.</p>
        </div>
    </div>
</div>

<div class="customer-note">
    <strong>Untuk Pelanggan:</strong> Silakan pindai <strong>QR Code</strong> yang tersedia di meja Anda untuk melihat menu dan memesan tanpa perlu antre.
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
