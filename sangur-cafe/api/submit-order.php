<?php
// ============================================================
// API SUBMIT ORDER (pelanggan, tanpa login)
// Menerima POST: table_id, customer_name (opsional),
// items[menu_id]=qty (array hidden dari cart).
// Fallback: bila items tidak dikirim, pakai $_SESSION['cart'].
// Harga dihitung ulang server-side dari tabel menus.
// Insert orders + order_items dalam satu PDO transaction.
// ============================================================
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

$is_ajax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])
            && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');

/** Kembalikan error: JSON untuk AJAX, flash+redirect untuk form POST biasa */
function submit_order_error($msg, $code = 400) {
    global $is_ajax;
    if ($is_ajax) {
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => $msg]);
        exit;
    }
    $_SESSION['flash'] = $msg;
    header('Location: ' . BASE_URL . 'customer/cart.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    submit_order_error('Metode tidak diizinkan.', 405);
}

// --- Validasi table_id ---
$table_id = (int)($_POST['table_id'] ?? 0);
if ($table_id <= 0) {
    submit_order_error('Meja tidak valid.');
}
$st = $pdo->prepare('SELECT id, table_number FROM tables WHERE id = ?');
$st->execute([$table_id]);
$meja = $st->fetch();
if (!$meja) {
    submit_order_error('Meja tidak ditemukan di sistem.');
}

// --- Ambil items: dari POST (array hidden) atau fallback session cart ---
$items = $_POST['items'] ?? null;
if (!is_array($items) || empty($items)) {
    if (!empty($_SESSION['cart']) && is_array($_SESSION['cart'])) {
        $items = $_SESSION['cart'];
    }
}
if (!is_array($items) || empty($items)) {
    submit_order_error('Keranjang kosong. Tidak ada pesanan yang dapat dikirim.');
}

// --- Validasi setiap menu & hitung total server-side ---
$order_items = [];
$total = 0;
foreach ($items as $menu_id => $qty) {
    $menu_id = (int)$menu_id;
    $qty     = (int)$qty;
    if ($menu_id <= 0) {
        submit_order_error('Menu tidak valid.');
    }
    if ($qty <= 0) {
        continue; // skip qty nol
    }
    $st = $pdo->prepare('SELECT id, name, price, status FROM menus WHERE id = ?');
    $st->execute([$menu_id]);
    $menu = $st->fetch();
    if (!$menu) {
        submit_order_error('Menu dengan ID ' . $menu_id . ' tidak ditemukan.');
    }
    if ($menu['status'] !== 'tersedia') {
        submit_order_error('Maaf, ' . $menu['name'] . ' sedang habis. Pesanan dibatalkan, silakan pilih menu lain.');
    }
    $price    = (int)$menu['price'];   // harga terkini dari DB, JANGAN percaya client
    $subtotal = $price * $qty;
    $total   += $subtotal;
    $order_items[] = [
        'menu_id'  => $menu_id,
        'qty'      => $qty,
        'price'    => $price,
        'subtotal' => $subtotal,
    ];
}
if (empty($order_items)) {
    submit_order_error('Tidak ada item pesanan yang valid.');
}

// --- Nama pelanggan (opsional) ---
$customer_name = trim((string)($_POST['customer_name'] ?? ''));
if ($customer_name === '') {
    $customer_name = null;
} else {
    $customer_name = substr($customer_name, 0, 100);
}

// --- Insert dalam satu PDO transaction ---
try {
    $pdo->beginTransaction();

    $st = $pdo->prepare(
        'INSERT INTO orders (table_id, customer_name, total_price, status, payment_status)
         VALUES (?, ?, ?, "pending", "unpaid")'
    );
    $st->execute([$table_id, $customer_name, $total]);
    $order_id = (int)$pdo->lastInsertId();

    $st = $pdo->prepare(
        'INSERT INTO order_items (order_id, menu_id, quantity, price_at_order, subtotal)
         VALUES (?, ?, ?, ?, ?)'
    );
    foreach ($order_items as $it) {
        $st->execute([$order_id, $it['menu_id'], $it['qty'], $it['price'], $it['subtotal']]);
    }

    $pdo->commit();
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    submit_order_error('Gagal menyimpan pesanan. Silakan coba lagi.', 500);
}

// --- Bersihkan keranjang session ---
unset($_SESSION['cart'], $_SESSION['cart_table_id']);

// --- Response: AJAX => JSON, form POST => redirect ke status ---
if ($is_ajax) {
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'order_id' => $order_id]);
    exit;
}

$_SESSION['flash'] = 'Pesanan berhasil dikirim! Nomor pesanan: #' . $order_id;
header('Location: ' . BASE_URL . 'customer/order-status.php?order=' . $order_id);
exit;
