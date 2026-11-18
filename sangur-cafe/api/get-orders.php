<?php
// ============================================================
// API: GET api/get-orders.php?role=kasir|kitchen
// Output: JSON daftar pesanan aktif + item. Tidak pakai header/footer.
// ============================================================
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');

// Hanya user terautentikasi (kasir / kitchen / owner) yang boleh polling
require_login();

$role = isset($_GET['role']) ? $_GET['role'] : 'kasir';

try {
    if ($role === 'kitchen') {
        // Dapur hanya melihat pesanan yang harus disiapkan
        $sql = "SELECT o.id, o.table_id, o.customer_name, o.total_price, o.status,
                       o.payment_status, o.ordered_at, t.table_number
                FROM orders o
                LEFT JOIN tables t ON t.id = o.table_id
                WHERE o.status IN ('pending','disiapkan')
                ORDER BY o.ordered_at ASC";
        $stmt = $pdo->query($sql);
    } else {
        // Kasir: pesanan aktif = belum bayar ATAU status belum final
        $sql = "SELECT o.id, o.table_id, o.customer_name, o.total_price, o.status,
                       o.payment_status, o.ordered_at, t.table_number
                FROM orders o
                LEFT JOIN tables t ON t.id = o.table_id
                WHERE o.payment_status = 'unpaid'
                   OR o.status IN ('pending','disiapkan','siap')
                ORDER BY o.ordered_at DESC";
        $stmt = $pdo->query($sql);
    }

    $orders = $stmt->fetchAll();

    if (!empty($orders)) {
        $orderIds = array_column($orders, 'id');
        $placeholders = implode(',', array_fill(0, count($orderIds), '?'));

        $itemSql = "SELECT oi.order_id, m.name, oi.quantity, oi.subtotal
                    FROM order_items oi
                    LEFT JOIN menus m ON m.id = oi.menu_id
                    WHERE oi.order_id IN ($placeholders)
                    ORDER BY oi.id ASC";
        $itemStmt = $pdo->prepare($itemSql);
        $itemStmt->execute($orderIds);
        $items = $itemStmt->fetchAll();

        $itemsByOrder = [];
        foreach ($items as $it) {
            $itemsByOrder[$it['order_id']][] = [
                'name'     => $it['name'] !== null ? $it['name'] : '(Menu dihapus)',
                'quantity' => (int)$it['quantity'],
                'subtotal' => (int)$it['subtotal'],
            ];
        }
    } else {
        $itemsByOrder = [];
    }

    $result = [];
    foreach ($orders as $o) {
        $result[] = [
            'id'             => (int)$o['id'],
            'table_number'   => $o['table_number'] !== null ? (string)$o['table_number'] : '-',
            'customer_name'  => $o['customer_name'] !== null ? $o['customer_name'] : 'Umum',
            'total_price'    => (int)$o['total_price'],
            'status'         => $o['status'],
            'payment_status' => $o['payment_status'],
            'ordered_at'     => $o['ordered_at'],
            'items'          => isset($itemsByOrder[$o['id']]) ? $itemsByOrder[$o['id']] : [],
        ];
    }

    echo json_encode(['orders' => $result]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Gagal memuat data pesanan.']);
}
exit;
