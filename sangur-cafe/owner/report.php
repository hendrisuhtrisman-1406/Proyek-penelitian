<?php
// ============================================================
// LAPORAN PENJUALAN OWNER - filter periode, cetak, export CSV
// ============================================================
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_role(['owner']);

$today = date('Y-m-d');

// --- Ambil & validasi filter tanggal ---
$dari   = $_GET['dari']   ?? $today;
$sampai = $_GET['sampai'] ?? $today;
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dari))   $dari   = $today;
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $sampai)) $sampai = $today;
if ($dari > $sampai) { $tmp = $dari; $dari = $sampai; $sampai = $tmp; }

$dari_dt   = $dari . ' 00:00:00';
$sampai_dt = $sampai . ' 23:59:59';

// --- Ambil pesanan dalam periode ---
$stmt = $pdo->prepare(
    "SELECT o.id, o.table_id, o.total_price, o.payment_status, o.ordered_at, t.table_number
     FROM orders o
     JOIN tables t ON t.id = o.table_id
     WHERE o.ordered_at BETWEEN ? AND ?
     ORDER BY o.ordered_at ASC"
);
$stmt->execute([$dari_dt, $sampai_dt]);
$orders = $stmt->fetchAll();

// --- Ambil item pesanan (ringkas) ---
$items_by_order = [];
if (!empty($orders)) {
    $order_ids = array_column($orders, 'id');
    $placeholders = implode(',', array_fill(0, count($order_ids), '?'));
    $stmt = $pdo->prepare(
        "SELECT oi.order_id, m.name, oi.quantity
         FROM order_items oi
         JOIN menus m ON m.id = oi.menu_id
         WHERE oi.order_id IN ($placeholders)"
    );
    $stmt->execute($order_ids);
    foreach ($stmt->fetchAll() as $it) {
        $items_by_order[$it['order_id']][] = $it['name'] . ' x' . (int) $it['quantity'];
    }
}

// --- Hitung total ---
$total_pesanan = count($orders);
$total_pendapatan = 0;
foreach ($orders as $o) {
    if ($o['payment_status'] === 'paid') {
        $total_pendapatan += (int) $o['total_price'];
    }
}

// ============================================================
// EXPORT CSV (harus sebelum output apapun)
// ============================================================
if (($_GET['export'] ?? '') === 'csv') {
    $filename = 'laporan-penjualan-' . $dari . '-sampai-' . $sampai . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    echo "\xEF\xBB\xBF"; // BOM agar Excel membaca UTF-8 dengan benar

    $out = fopen('php://output', 'w');
    fputcsv($out, ['LAPORAN PENJUALAN SANGUR CAFE']);
    fputcsv($out, ['Periode', $dari . ' s/d ' . $sampai]);
    fputcsv($out, []);
    fputcsv($out, ['Tanggal', 'No Pesanan', 'Meja', 'Item', 'Total', 'Status Pembayaran']);

    foreach ($orders as $o) {
        $item_str = isset($items_by_order[$o['id']]) ? implode(', ', $items_by_order[$o['id']]) : '-';
        fputcsv($out, [
            $o['ordered_at'],
            '#' . $o['id'],
            'Meja ' . $o['table_number'],
            $item_str,
            (int) $o['total_price'],
            $o['payment_status'] === 'paid' ? 'Lunas' : 'Belum Bayar',
        ]);
    }
    fputcsv($out, []);
    fputcsv($out, ['TOTAL', '', '', 'Jumlah Pesanan: ' . $total_pesanan, $total_pendapatan, '']);
    fclose($out);
    exit;
}

// --- URL preset cepat ---
$urlPreset = function ($d, $s) {
    return '?dari=' . urlencode($d) . '&sampai=' . urlencode($s);
};
$preset_hari_ini    = $urlPreset($today, $today);
$preset_7_hari      = $urlPreset(date('Y-m-d', strtotime('-6 days')), $today);
$preset_bulan_ini   = $urlPreset(date('Y-m-01'), $today);
$csv_url = '?dari=' . urlencode($dari) . '&sampai=' . urlencode($sampai) . '&export=csv';

$page_title = 'Laporan Penjualan';
require __DIR__ . '/../includes/header.php';
?>
<h4 class="fw-bold mb-1">Laporan Penjualan</h4>
<p class="text-muted mb-4">Periode <?= tgl_id($dari . ' 00:00') ?> s/d <?= tgl_id($sampai . ' 23:59') ?>.</p>

<div class="no-print">
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" action="" class="row g-2 align-items-end">
                <div class="col-12 col-md-3">
                    <label class="form-label small fw-semibold">Dari Tanggal</label>
                    <input type="date" name="dari" value="<?= e($dari) ?>" class="form-control" required>
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label small fw-semibold">Sampai Tanggal</label>
                    <input type="date" name="sampai" value="<?= e($sampai) ?>" class="form-control" required>
                </div>
                <div class="col-12 col-md-2">
                    <button type="submit" class="btn btn-cafe w-100">Tampilkan</button>
                </div>
                <div class="col-12 col-md-4 text-md-end">
                    <div class="btn-group btn-group-sm w-100" role="group">
                        <a href="<?= e($preset_hari_ini) ?>" class="btn btn-outline-cafe">Hari Ini</a>
                        <a href="<?= e($preset_7_hari) ?>" class="btn btn-outline-cafe">7 Hari Terakhir</a>
                        <a href="<?= e($preset_bulan_ini) ?>" class="btn btn-outline-cafe">Bulan Ini</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="d-flex gap-2 mb-3">
        <button type="button" onclick="window.print()" class="btn btn-cafe">Cetak Laporan</button>
        <a href="<?= e($csv_url) ?>" class="btn btn-outline-cafe">Export CSV</a>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white">
        <strong class="text-dark">Rincian Pesanan</strong>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Tanggal</th>
                    <th>No Pesanan</th>
                    <th>Meja</th>
                    <th>Item</th>
                    <th class="text-end">Total</th>
                    <th>Pembayaran</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($orders)): ?>
                    <tr><td colspan="6" class="text-center text-muted py-4">Tidak ada pesanan pada periode ini.</td></tr>
                <?php else: foreach ($orders as $o): ?>
                <tr>
                    <td class="small"><?= tgl_id($o['ordered_at']) ?></td>
                    <td class="fw-semibold">#<?= (int) $o['id'] ?></td>
                    <td>Meja <?= (int) $o['table_number'] ?></td>
                    <td class="small">
                        <?php
                        if (isset($items_by_order[$o['id']])) {
                            echo e(implode(', ', $items_by_order[$o['id']]));
                        } else {
                            echo '-';
                        }
                        ?>
                    </td>
                    <td class="text-end fw-semibold"><?= rp($o['total_price']) ?></td>
                    <td><?= payment_label($o['payment_status']) ?></td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
            <tfoot>
                <tr class="table-light fw-bold">
                    <td colspan="3">TOTAL (<?= (int) $total_pesanan ?> pesanan)</td>
                    <td class="text-end">Pendapatan (lunas):</td>
                    <td class="text-end"><?= rp($total_pendapatan) ?></td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
