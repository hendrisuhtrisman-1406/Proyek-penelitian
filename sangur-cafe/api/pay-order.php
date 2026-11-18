<?php
// ============================================================
// API: POST api/pay-order.php  {order_id, amount_paid}
// Validasi amount_paid >= total_price; update payment_status='paid';
// insert transactions (cashier_id = session user); opsional status->disajikan
// ============================================================
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');

// Hanya kasir / owner yang boleh memproses pembayaran
require_role(['kasir', 'owner']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Metode tidak diizinkan.']);
    exit;
}

$orderId = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;
$amountPaid = isset($_POST['amount_paid']) ? (int)$_POST['amount_paid'] : 0;

if ($orderId <= 0 || $amountPaid <= 0) {
    echo json_encode(['success' => false, 'error' => 'Data pembayaran tidak valid.']);
    exit;
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("SELECT id, total_price, payment_status, status FROM orders WHERE id = ? LIMIT 1 FOR UPDATE");
    $stmt->execute([$orderId]);
    $order = $stmt->fetch();

    if (!$order) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'error' => 'Pesanan tidak ditemukan.']);
        exit;
    }

    if ($order['payment_status'] === 'paid') {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'error' => 'Pesanan ini sudah dibayar.']);
        exit;
    }

    $total = (int)$order['total_price'];

    if ($amountPaid < $total) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'error' => 'Jumlah bayar kurang dari total.']);
        exit;
    }

    $change = $amountPaid - $total;

    // Jika status masih 'siap', tandai sekaligus disajikan
    $newStatus = ($order['status'] === 'siap') ? 'disajikan' : $order['status'];

    $upd = $pdo->prepare("UPDATE orders SET payment_status = 'paid', status = ?, updated_at = NOW() WHERE id = ?");
    $upd->execute([$newStatus, $orderId]);

    $ins = $pdo->prepare("INSERT INTO transactions (order_id, amount_paid, change_amount, cashier_id, paid_at)
                          VALUES (?, ?, ?, ?, NOW())");
    $ins->execute([$orderId, $amountPaid, $change, (int)$_SESSION['user_id']]);

    $pdo->commit();

    echo json_encode(['success' => true, 'change' => $change]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Gagal memproses pembayaran.']);
}
exit;
