<?php
// HEADER - selalu include dengan cara:
//   $page_title = 'Judul Halaman';
//   $customer_page = true; // opsional, untuk halaman pelanggan (tanpa navbar admin)
//   require __DIR__ . '/../includes/header.php';  (sesuaikan kedalaman folder)
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/auth.php';
if (!isset($page_title)) $page_title = 'Sangur Cafe';
$customer_page = !empty($customer_page);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($page_title) ?> — Sangur Cafe</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/lib/bootstrap.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
</head>
<body>
<?php if (!$customer_page): ?>
<nav class="navbar navbar-expand-lg navbar-dark cafe-navbar sticky-top">
  <div class="container">
    <a class="navbar-brand fw-bold" href="<?= BASE_URL ?>">
      <span class="brand-icon">☕</span> Sangur Cafe
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMenu">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navMenu">
      <ul class="navbar-nav me-auto">
        <?php if (!empty($_SESSION['role']) && $_SESSION['role'] === 'kasir'): ?>
          <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>kasir/dashboard.php">Pesanan Masuk</a></li>
          <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>kasir/transactions.php">Transaksi</a></li>
        <?php elseif (!empty($_SESSION['role']) && $_SESSION['role'] === 'kitchen'): ?>
          <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>kitchen/dashboard.php">Dapur</a></li>
        <?php elseif (!empty($_SESSION['role']) && $_SESSION['role'] === 'owner'): ?>
          <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>owner/dashboard.php">Dashboard</a></li>
          <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>owner/report.php">Laporan</a></li>
          <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>owner/menu-manage.php">Menu</a></li>
          <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>owner/tables.php">Meja & QR</a></li>
          <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>owner/users.php">Pengguna</a></li>
        <?php endif; ?>
      </ul>
      <ul class="navbar-nav">
        <?php if (is_login()): ?>
          <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
              <?= e($_SESSION['nama'] ?? '') ?> <small class="text-warning">(<?= e(role_label($_SESSION['role'] ?? '')) ?>)</small>
            </a>
            <ul class="dropdown-menu dropdown-menu-end">
              <li><a class="dropdown-item" href="<?= BASE_URL ?>logout.php">Keluar</a></li>
            </ul>
          </li>
        <?php else: ?>
          <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>login.php">Login</a></li>
        <?php endif; ?>
      </ul>
    </div>
  </div>
</nav>
<?php endif; ?>
<main class="container py-4">
<?= flash() ?>
