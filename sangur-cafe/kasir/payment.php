<?php
// ============================================================
// KASIR - PAYMENT: proses pembayaran pesanan ?order=ID + cetak struk
// ============================================================
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_role(['kasir', 'owner']);

$orderId = isset($_GET['order']) ? (int)$_GET['order'] : 0;

if ($orderId <= 0) {
    $_SESSION['flash'] = 'Nomor pesanan tidak valid.';
    redirect('kasir/dashboard.php');
}

$stmt = $pdo->prepare("SELECT o.*, t.table_number
                       FROM orders o
                       LEFT JOIN tables t ON t.id = o.table_id
                       WHERE o.id = ? LIMIT 1");
$stmt->execute([$orderId]);
$order = $stmt->fetch();

if (!$order) {
    $_SESSION['flash'] = 'Pesanan tidak ditemukan.';
    redirect('kasir/dashboard.php');
}

$itemStmt = $pdo->prepare("SELECT oi.*, m.name
                           FROM order_items oi
                           LEFT JOIN menus m ON m.id = oi.menu_id
                           WHERE oi.order_id = ?
                           ORDER BY oi.id ASC");
$itemStmt->execute([$orderId]);
$items = $itemStmt->fetchAll();

$alreadyPaid = ($order['payment_status'] === 'paid');

// Jika sudah lunas, ambil data transaksi untuk struk
$tx = null;
if ($alreadyPaid) {
    $txStmt = $pdo->prepare("SELECT tr.*, u.nama AS cashier_name
                             FROM transactions tr
                             LEFT JOIN users u ON u.id = tr.cashier_id
                             WHERE tr.order_id = ? LIMIT 1");
    $txStmt->execute([$orderId]);
    $tx = $txStmt->fetch();
}

$page_title = 'Pembayaran Pesanan #' . $orderId;
require __DIR__ . '/../includes/header.php';
?>

<div class="row g-4">
    <!-- Kolom kiri: rincian pesanan + form bayar -->
    <div class="col-lg-7 no-print">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white border-bottom">
                <h5 class="mb-0">Pesanan #<?= (int)$order['id'] ?>
                    <span class="badge bg-secondary ms-2">Meja <?= e($order['table_number'] ?? '-') ?></span>
                </h5>
                <small class="text-muted">Pelanggan: <?= e($order['customer_name'] ?? 'Umum') ?> &middot; <?= tgl_id($order['ordered_at']) ?></small>
            </div>
            <div class="card-body">
                <table class="table table-sm align-middle">
                    <thead>
                        <tr><th>Item</th><th class="text-center">Qty</th><th class="text-end">Harga</th><th class="text-end">Subtotal</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($items as $it): ?>
                        <tr>
                            <td><?= e($it['name'] ?? '(Menu dihapus)') ?></td>
                            <td class="text-center"><?= (int)$it['quantity'] ?></td>
                            <td class="text-end"><?= rp($it['price_at_order']) ?></td>
                            <td class="text-end"><?= rp($it['subtotal']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr class="table-light">
                            <th colspan="3" class="text-end">Total</th>
                            <th class="text-end text-danger" id="totalDisplay"><?= rp($order['total_price']) ?></th>
                        </tr>
                    </tfoot>
                </table>

                <?php if (!$alreadyPaid): ?>
                <hr>
                <h6 class="mb-3">Form Pembayaran</h6>
                <div class="mb-3">
                    <label class="form-label">Jumlah Dibayar (Rp)</label>
                    <input type="number" id="amountPaid" class="form-control form-control-lg"
                           min="<?= (int)$order['total_price'] ?>" step="100" placeholder="Masukkan jumlah uang..."
                           autocomplete="off">
                    <div class="form-text">Minimal: <?= rp($order['total_price']) ?></div>
                </div>
                <div class="mb-3 p-3 rounded" style="background:#f5ebe0;">
                    <div class="d-flex justify-content-between">
                        <span>Total Belanja</span><strong id="totalVal"><?= rp($order['total_price']) ?></strong>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span>Uang Dibayar</span><strong id="paidVal">Rp 0</strong>
                    </div>
                    <hr class="my-2">
                    <div class="d-flex justify-content-between">
                        <span>Kembalian</span>
                        <strong class="text-success fs-5" id="changeVal">Rp 0</strong>
                    </div>
                </div>
                <div class="d-flex gap-2">
                    <button type="button" id="btnPay" class="btn btn-cafe btn-lg flex-fill" disabled>
                        Proses Pembayaran
                    </button>
                    <a href="<?= BASE_URL ?>kasir/dashboard.php" class="btn btn-outline-secondary btn-lg">Kembali</a>
                </div>
                <?php else: ?>
                <div class="alert alert-success">
                    Pesanan ini <strong>sudah lunas</strong>. Anda dapat mencetak ulang struk di samping.
                </div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-cafe flex-fill" onclick="window.print()">Cetak Struk</button>
                    <a href="<?= BASE_URL ?>kasir/dashboard.php" class="btn btn-outline-secondary">Kembali</a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Kolom kanan: STRUK (area yang tercetak) -->
    <div class="col-lg-5">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white border-bottom no-print">
                <h6 class="mb-0">Struk Pembayaran</h6>
            </div>
            <div class="card-body" id="struk" style="font-family:'Courier New',monospace;font-size:0.85rem;">
                <div class="text-center mb-2">
                    <strong style="font-size:1.1rem;">SANGUR CAFE</strong><br>
                    <small>Pekanbaru &middot; Telp (0761) 000000</small>
                </div>
                <hr class="my-2" style="border-top:1px dashed #999;">
                <div class="d-flex justify-content-between"><span>No. Pesanan</span><strong>#<?= (int)$order['id'] ?></strong></div>
                <div class="d-flex justify-content-between"><span>Meja</span><span><?= e($order['table_number'] ?? '-') ?></span></div>
                <div class="d-flex justify-content-between"><span>Pelanggan</span><span><?= e($order['customer_name'] ?? 'Umum') ?></span></div>
                <div class="d-flex justify-content-between"><span>Kasir</span><span id="strukKasir"><?= e($tx['cashier_name'] ?? ($_SESSION['nama'] ?? '-')) ?></span></div>
                <div class="d-flex justify-content-between"><span>Waktu</span><span id="strukWaktu"><?= tgl_id($tx['paid_at'] ?? $order['ordered_at']) ?></span></div>
                <hr class="my-2" style="border-top:1px dashed #999;">
                <?php foreach ($items as $it): ?>
                <div class="d-flex justify-content-between">
                    <span><?= (int)$it['quantity'] ?>x <?= e($it['name'] ?? '(Menu)') ?></span>
                    <span><?= rp($it['subtotal']) ?></span>
                </div>
                <?php endforeach; ?>
                <hr class="my-2" style="border-top:1px dashed #999;">
                <div class="d-flex justify-content-between"><strong>Total</strong><strong id="strukTotal"><?= rp($order['total_price']) ?></strong></div>
                <div class="d-flex justify-content-between"><span>Bayar</span><span id="strukBayar"><?= $tx ? rp($tx['amount_paid']) : '-' ?></span></div>
                <div class="d-flex justify-content-between"><span>Kembalian</span><span id="strukKembalian"><?= $tx ? rp($tx['change_amount']) : '-' ?></span></div>
                <hr class="my-2" style="border-top:1px dashed #999;">
                <div class="text-center mt-2">
                    <small>Terima kasih atas kunjungan Anda<br>Barang yang sudah dibeli tidak dapat ditukar</small>
                </div>
            </div>
        </div>

        <!-- Tombol setelah bayar sukses (hidden awalnya) -->
        <div id="afterPayActions" class="mt-3 d-none no-print">
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-cafe flex-fill" onclick="window.print()">Cetak Struk</button>
                <a href="<?= BASE_URL ?>kasir/dashboard.php" class="btn btn-outline-secondary flex-fill">Kembali</a>
            </div>
        </div>
    </div>
</div>

<?php if (!$alreadyPaid): ?>
<script>
(function(){
    var TOTAL = <?= (int)$order['total_price'] ?>;
    var ORDER_ID = <?= (int)$order['id'] ?>;

    function formatRp(n){
        return 'Rp ' + parseInt(n||0,10).toString().replace(/\B(?=(\d{3})+(?!\d))/g,'.');
    }

    function hitungKembalian(){
        var paid = parseInt($('#amountPaid').val() || 0, 10);
        var change = paid - TOTAL;
        $('#paidVal').text(formatRp(paid));
        $('#changeVal').text(formatRp(change < 0 ? 0 : change));
        $('#btnPay').prop('disabled', (paid < TOTAL));
    }

    $('#amountPaid').on('keyup change input', hitungKembalian);

    $('#btnPay').on('click', function(){
        var paid = parseInt($('#amountPaid').val() || 0, 10);
        if (paid < TOTAL){
            cafeToast('Jumlah bayar kurang dari total!', 'danger');
            return;
        }
        var $btn = $(this).prop('disabled', true).text('Memproses...');
        $.post('<?= BASE_URL ?>api/pay-order.php', {order_id: ORDER_ID, amount_paid: paid}, function(res){
            if (res && res.success){
                var change = parseInt(res.change || 0, 10);
                cafeToast('Pembayaran berhasil! Kembalian: ' + formatRp(change), 'success');
                // Isi struk
                var now = new Date();
                var waktu = now.getDate() + '-' + (now.getMonth()+1) + '-' + now.getFullYear()
                          + ' ' + now.getHours() + ':' + String(now.getMinutes()).padStart(2,'0');
                $('#strukBayar').text(formatRp(paid));
                $('#strukKembalian').text(formatRp(change));
                $('#strukWaktu').text(waktu);
                $('#afterPayActions').removeClass('d-none');
                $btn.text('Pembayaran Selesai');
            } else {
                cafeToast((res && res.error) ? res.error : 'Pembayaran gagal.', 'danger');
                $btn.prop('disabled', false).text('Proses Pembayaran');
            }
        }, 'json').fail(function(){
            cafeToast('Terjadi kesalahan jaringan.', 'danger');
            $btn.prop('disabled', false).text('Proses Pembayaran');
        });
    });
})();
</script>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
