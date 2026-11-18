<?php
// ============================================================
// DASHBOARD OWNER - statistik hari ini + pesanan terbaru
// ============================================================
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_role(['owner']);

// 1. Total pesanan hari ini
$stmt = $pdo->prepare("SELECT COUNT(*) AS cnt FROM orders WHERE DATE(ordered_at) = CURDATE()");
$stmt->execute();
$total_orders_today = (int) $stmt->fetch()['cnt'];

// 2. Pendapatan hari ini (pesanan lunas)
$stmt = $pdo->prepare("SELECT COALESCE(SUM(total_price), 0) AS total FROM orders WHERE payment_status = 'paid' AND DATE(ordered_at) = CURDATE()");
$stmt->execute();
$revenue_today = (int) $stmt->fetch()['total'];

// 3. Total transaksi (pembayaran) hari ini
$stmt = $pdo->prepare("SELECT COUNT(*) AS cnt FROM transactions WHERE DATE(paid_at) = CURDATE()");
$stmt->execute();
$trx_today = (int) $stmt->fetch()['cnt'];

// 4. Menu terlaris hari ini (top 5 berdasarkan quantity)
$stmt = $pdo->prepare(
    "SELECT m.name, SUM(oi.quantity) AS qty
     FROM order_items oi
     JOIN orders o ON o.id = oi.order_id
     JOIN menus  m ON m.id = oi.menu_id
     WHERE DATE(o.ordered_at) = CURDATE()
     GROUP BY oi.menu_id, m.name
     ORDER BY qty DESC
     LIMIT 5"
);
$stmt->execute();
$top_menus = $stmt->fetchAll();

// 5. 5 pesanan terbaru
$stmt = $pdo->prepare(
    "SELECT o.id, o.total_price, o.status, o.payment_status, o.ordered_at, t.table_number
     FROM orders o
     JOIN tables t ON t.id = o.table_id
     ORDER BY o.ordered_at DESC
     LIMIT 5"
);
$stmt->execute();
$recent_orders = $stmt->fetchAll();

$page_title = 'Dashboard Owner';
require __DIR__ . '/../includes/header.php';
?>
<h4 class="fw-bold mb-1">Dashboard Owner</h4>
<p class="text-muted mb-4">Ringkasan aktivitas Sangur Cafe hari ini, <?= tgl_id(date('Y-m-d H:i')) ?>.</p>

<div class="row g-3 mb-4">
    <!-- Total Pesanan -->
    <div class="col-12 col-sm-6 col-lg-3">
        <div class="stat-card p-3 h-100">
            <div class="stat-label">Total Pesanan Hari Ini</div>
            <div class="stat-value"><?= (int) $total_orders_today ?></div>
            <small class="opacity-75">pesanan masuk</small>
        </div>
    </div>
    <!-- Pendapatan -->
    <div class="col-12 col-sm-6 col-lg-3">
        <div class="stat-card p-3 h-100">
            <div class="stat-label">Pendapatan Hari Ini</div>
            <div class="stat-value"><?= rp($revenue_today) ?></div>
            <small class="opacity-75">pesanan lunas</small>
        </div>
    </div>
    <!-- Total Transaksi -->
    <div class="col-12 col-sm-6 col-lg-3">
        <div class="stat-card p-3 h-100">
            <div class="stat-label">Total Transaksi Hari Ini</div>
            <div class="stat-value"><?= (int) $trx_today ?></div>
            <small class="opacity-75">pembayaran tercatat</small>
        </div>
    </div>
    <!-- Menu Terlaris -->
    <div class="col-12 col-sm-6 col-lg-3">
        <div class="stat-card p-3 h-100">
            <div class="stat-label mb-2">Menu Terlaris Hari Ini</div>
            <?php if (empty($top_menus)): ?>
                <small class="opacity-75">Belum ada penjualan hari ini.</small>
            <?php else: ?>
                <ol class="mb-0 ps-3 small">
                    <?php foreach ($top_menus as $tm): ?>
                        <li><?= e($tm['name']) ?> <span class="opacity-75">(<?= (int) $tm['qty'] ?>x)</span></li>
                    <?php endforeach; ?>
                </ol>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <strong class="text-dark">Pesanan Terbaru</strong>
        <a href="<?= BASE_URL ?>owner/report.php" class="btn btn-sm btn-outline-cafe">Lihat Laporan</a>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>No Pesanan</th>
                    <th>Meja</th>
                    <th>Waktu</th>
                    <th class="text-end">Total</th>
                    <th>Status</th>
                    <th>Pembayaran</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($recent_orders)): ?>
                    <tr><td colspan="6" class="text-center text-muted py-4">Belum ada pesanan.</td></tr>
                <?php else: foreach ($recent_orders as $o): ?>
                <tr>
                    <td class="fw-semibold">#<?= (int) $o['id'] ?></td>
                    <td>Meja <?= (int) $o['table_number'] ?></td>
                    <td><?= tgl_id($o['ordered_at']) ?></td>
                    <td class="text-end fw-semibold"><?= rp($o['total_price']) ?></td>
                    <td><?= status_label($o['status']) ?></td>
                    <td><?= payment_label($o['payment_status']) ?></td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
