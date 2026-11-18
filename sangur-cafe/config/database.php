<?php
// ============================================================
// KONFIGURASI DASAR & KONEKSI DATABASE
// Sistem Informasi Penjualan Berbasis Web - Sangur Cafe Pekanbaru
// ============================================================

// Ubah baris ini jika folder proyek di-rename atau diakses
// lewat sub-path lain, misal: define('BASE_URL', '/tugas-akhir/');
define('BASE_URL', '/sangur-cafe/');

// Nama database (sesuaikan jika perlu)
define('DB_NAME', 'sangur_cafe');
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', ''); // default XAMPP: root tanpa password

date_default_timezone_set('Asia/Jakarta');

// Koneksi PDO dengan prepared statement (anti SQL Injection)
try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    die('<div style="font-family:sans-serif;padding:2rem;max-width:600px;margin:3rem auto;border:1px solid #c0392b;border-radius:8px;background:#fdf0ef;">'
        . '<h3 style="color:#c0392b;margin-top:0;">Koneksi database gagal</h3>'
        . '<p>Pastikan <strong>MySQL XAMPP sudah menyala</strong> dan database <strong>' . htmlspecialchars(DB_NAME) . '</strong> sudah di-import dari file <code>database.sql</code>.</p>'
        . '<p style="color:#666;font-size:0.9rem;">Detail: ' . htmlspecialchars($e->getMessage()) . '</p>'
        . '<p><a href="' . BASE_URL . 'README.md" style="color:#8b5a2b;">Lihat petunjuk instalasi di README</a></p>'
        . '</div>');
}
