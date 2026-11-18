<?php
// ============================================================
// FUNGSI BANTUAN & AUTHENTICATION
// Wajib di-include di setiap halaman (setelah config/database.php)
// ============================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/** Escape output anti XSS */
function e($str) {
    return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
}

/** Format Rupiah: Rp 15.000 */
function rp($angka) {
    return 'Rp ' . number_format((int)$angka, 0, ',', '.');
}

/** Format tanggal Indonesia: 17 November 2026 14:30 */
function tgl_id($datetime) {
    if (empty($datetime)) return '-';
    $ts = is_numeric($datetime) ? (int)$datetime : strtotime($datetime);
    $bulan = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
              'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    return date('j', $ts) . ' ' . $bulan[(int)date('n', $ts)] . ' ' . date('Y H:i', $ts);
}

/** Redirect ke URL relatif terhadap BASE_URL */
function redirect($path = '') {
    header('Location: ' . BASE_URL . ltrim($path, '/'));
    exit;
}

/** Apakah user sedang login? */
function is_login() {
    return !empty($_SESSION['user_id']);
}

/** Wajib login; jika belum, lempar ke halaman login */
function require_login() {
    if (!is_login()) {
        $_SESSION['flash'] = 'Silakan login terlebih dahulu.';
        redirect('login.php');
    }
}

/** Wajib login dengan role tertentu (array atau string) */
function require_role($roles) {
    require_login();
    $roles = (array)$roles;
    if (!in_array($_SESSION['role'], $roles, true)) {
        http_response_code(403);
        die('<div style="font-family:sans-serif;padding:2rem;max-width:500px;margin:3rem auto;text-align:center;">'
            . '<h3 style="color:#c0392b;">Akses Ditolak</h3>'
            . '<p>Halaman ini hanya dapat diakses oleh: <strong>' . e(implode(', ', $roles)) . '</strong>.</p>'
            . '<p><a href="' . BASE_URL . 'login.php">Kembali ke Login</a></p></div>');
    }
}

/** Label status pesanan dalam bahasa Indonesia + warna badge Bootstrap */
function status_label($status) {
    $map = [
        'pending'   => ['Menunggu Konfirmasi', 'warning'],
        'disiapkan' => ['Sedang Disiapkan',  'info'],
        'siap'      => ['Siap Disajikan',    'primary'],
        'disajikan' => ['Sudah Disajikan',   'success'],
    ];
    if (isset($map[$status])) {
        return '<span class="badge bg-' . $map[$status][1] . '">' . $map[$status][0] . '</span>';
    }
    return '<span class="badge bg-secondary">' . e($status) . '</span>';
}

function payment_label($status) {
    if ($status === 'paid') return '<span class="badge bg-success">Lunas</span>';
    return '<span class="badge bg-danger">Belum Bayar</span>';
}

/** Ambil flash message lalu hapus */
function flash() {
    if (!empty($_SESSION['flash'])) {
        $msg = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return '<div class="alert alert-info alert-dismissible fade show" role="alert">'
             . e($msg)
             . '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
    }
    return '';
}

/** Daftar role untuk label */
function role_label($role) {
    $map = ['owner' => 'Owner', 'kasir' => 'Kasir', 'kitchen' => 'Kitchen/Dapur'];
    return isset($map[$role]) ? $map[$role] : e($role);
}
