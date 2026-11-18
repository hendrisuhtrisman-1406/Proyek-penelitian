<?php
// ============================================================
// KASIR - TRANSACTIONS: daftar transaksi lunas + filter tanggal + total pendapatan
// ============================================================
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_role(['kasir', 'owner']);

$today = date('Y-m-d');
$from = isset($_GET['from']) ? trim($_GET['from']) : $today;
$to   = isset($_GET['to'])   ? trim($_GET['to'])   : $today;

// Validasi format tanggal Y-m-d (sederhana)
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) $from = $today;
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to))   $to = $today;

$fromTime = $from . ' 00:00:00';
$toTime   = $to . ' 23:59:59';

$sql = "SELECT tr.id AS tx_id, tr.order_id, tr.amount_paid, tr.change_amount, tr.paid_at,
               o.total_price, t.table_number, u.nama AS cashier_name
        FROM transactions tr
        JOIN orders o ON o.id = tr.order_id
        LEFT JOIN tables t ON t.id = o.table_id
        LEFT JOIN users u ON u.id = tr.cashier_id
        WHERE tr.paid_at BETWEEN ? AND ?
        ORDER BY tr.paid_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute([$fromTime, $toTime]);
$transactions = $stmt->fetchAll();

$totalPendapatan = 0;
foreach ($transactions as $tx) {
    $totalPendapatan += (int)$tx['total_price'];
}

$page_title = 'Riwayat Transaksi';
require __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
    <div>
        <h4 class="mb-1">Riwayat Transaksi</h4>
        <small class="text-muted">Daftar semua pembayaran yang sudah lunas.</small>
    </div>
    <a href="<?= BASE_URL ?>kasir/dashboard.php" class="btn btn-outline-cafe btn-sm">&larr; Kembali ke Pesanan</a>
</div>

<!-- Filter tanggal -->
<form method="get" class="row g-2 mb-3 align-items-end no-print">
    <div class="col-md-3">
        <label class="form-label small mb-1">Dari Tanggal</label>
        <input type="date" name="from" class="form-control" value="<?= e($from) ?>">
    </div>
    <div class="col-md-3">
        <label class="form-label small mb-1">Sampai Tanggal</label>
        <input type="date" name="to" class="form-control" value="<?= e($to) ?>">
    </div>
    <div class="col-md-3">
        <button type="submit" class="btn btn-cafe w-100">Tampilkan</button>
    </div>
</form>

<div class="card shadow-sm border-0">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Waktu</th>
                        <th>Pesanan</th>
                        <th>Meja</th>
                        <th class="text-end">Total</th>
                        <th class="text-end">Bayar</th>
                        <th class="text-end">Kembalian</th>
                        <th>Kasir</th>
                        <th class="no-print">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($transactions)): ?>
                    <tr><td colspan="9" class="text-center py-4 text-muted">Belum ada transaksi pada periode ini.</td></tr>
                <?php else: ?>
                    <?php $no = 1; foreach ($transactions as $tx): ?>
                    <tr>
                        <td><?= $no++ ?></td>
                        <td><?= tgl_id($tx['paid_at']) ?></td>
                        <td><strong>#<?= (int)$tx['order_id'] ?></strong></td>
                        <td><?= e($tx['table_number'] ?? '-') ?></td>
                        <td class="text-end"><?= rp($tx['total_price']) ?></td>
                        <td class="text-end"><?= rp($tx['amount_paid']) ?></td>
                        <td class="text-end text-success"><?= rp($tx['change_amount']) ?></td>
                        <td><?= e($tx['cashier_name'] ?? '-') ?></td>
                        <td class="no-print">
                            <a href="<?= BASE_URL ?>kasir/payment.php?order=<?= (int)$tx['order_id'] ?>"
                               class="btn btn-outline-cafe btn-sm">Detail / Struk</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
                <?php if (!empty($transactions)): ?>
                <tfoot>
                    <tr class="table-light">
                        <th colspan="4" class="text-end">Total Pendapatan Periode</th>
                        <th class="text-end text-danger"><?= rp($totalPendapatan) ?></th>
                        <th colspan="4"></th>
                    </tr>
                </tfoot>
                <?php endif; ?>
            </table>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
