<?php
// ============================================================
// API: POST api/update-status.php  {order_id, status}
// Transisi valid: pending->disiapkan, disiapkan->siap, siap->disajikan
// ============================================================
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');

// Hanya kitchen / owner yang boleh ubah status pesanan
require_role(['kitchen', 'owner']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Metode tidak diizinkan.']);
    exit;
}

$orderId = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;
$newStatus = isset($_POST['status']) ? trim($_POST['status']) : '';

if ($orderId <= 0 || $newStatus === '') {
    echo json_encode(['success' => false, 'error' => 'Data tidak lengkap.']);
    exit;
}

// Transisi status yang diizinkan: current => next
$allowed = [
    'pending'   => 'disiapkan',
    'disiapkan' => 'siap',
    'siap'      => 'disajikan',
];

try {
    $stmt = $pdo->prepare("SELECT status FROM orders WHERE id = ? LIMIT 1");
    $stmt->execute([$orderId]);
    $order = $stmt->fetch();

    if (!$order) {
        echo json_encode(['success' => false, 'error' => 'Pesanan tidak ditemukan.']);
        exit;
    }

    $current = $order['status'];

    if (!isset($allowed[$current]) || $allowed[$current] !== $newStatus) {
        echo json_encode(['success' => false, 'error' => 'Perubahan status tidak diizinkan.']);
        exit;
    }

    $upd = $pdo->prepare("UPDATE orders SET status = ?, updated_at = NOW() WHERE id = ?");
    $upd->execute([$newStatus, $orderId]);

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Gagal mengupdate status.']);
}
exit;
