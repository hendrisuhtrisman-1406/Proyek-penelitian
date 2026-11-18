<?php
// ============================================================
// STATUS PESANAN PELANGGAN
// Parameter GET: ?order=ID (id dari tabel orders)
// Auto-refresh tiap 5 detik (header Refresh + meta + JS).
// ============================================================
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

$page_title = 'Status Pesanan';
$customer_page = true;

$order_id = (int)($_GET['order'] ?? 0);
$order    = null;
$items    = [];
$not_found = '';

if ($order_id > 0) {
    $st = $pdo->prepare(
        'SELECT o.*, t.table_number
         FROM orders o
         JOIN tables t ON t.id = o.table_id
         WHERE o.id = ?'
    );
    $st->execute([$order_id]);
    $order = $st->fetch();
    if ($order) {
        $st = $pdo->prepare(
            'SELECT oi.*, m.name
             FROM order_items oi
             JOIN menus m ON m.id = oi.menu_id
             WHERE oi.order_id = ?
             ORDER BY oi.id'
        );
        $st->execute([$order_id]);
        $items = $st->fetchAll();
        // Auto-refresh selama pesanan belum selesai disajikan
        if ($order['status'] !== 'disajikan') {
            header('Refresh: 5');
        }
    } else {
        $not_found = 'Pesanan nomor ' . $order_id . ' tidak ditemukan.';
    }
} else {
    $not_found = 'Masukkan nomor pesanan untuk melihat status.';
}

require __DIR__ . '/../includes/header.php';
?>

<?php if ($order): ?>
<?php if ($order['status'] !== 'disajikan'): ?><meta http-equiv="refresh" content="5"><?php endif; ?>

<div class="customer-header mb-4">
  <h5>Sangur Cafe &mdash; Meja <?= (int)$order['table_number'] ?></h5>
  <small>Status Pesanan #<?= (int)$order['id'] ?> &middot; diperbarui otomatis setiap 5 detik</small>
</div>

<div class="card order-card">
  <div class="card-body">
    <div class="d-flex flex-wrap justify-content-between align-items-start mb-3 gap-2">
      <div>
        <h6 class="fw-bold mb-1">Pesanan #<?= (int)$order['id'] ?></h6>
        <small class="text-muted">Dipesan: <?= tgl_id($order['ordered_at']) ?></small>
        <?php if (!empty($order['customer_name'])): ?>
          <br><small class="text-muted">Atas nama: <?= e($order['customer_name']) ?></small>
        <?php endif; ?>
      </div>
      <div class="text-end">
        <div class="mb-1"><?= status_label($order['status']) ?></div>
        <div><?= payment_label($order['payment_status']) ?></div>
      </div>
    </div>

    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead>
          <tr>
            <th>Menu</th>
            <th class="text-center">Jumlah</th>
            <th class="text-end">Harga</th>
            <th class="text-end">Subtotal</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($items as $it): ?>
          <tr>
            <td><?= e($it['name']) ?></td>
            <td class="text-center"><?= (int)$it['quantity'] ?></td>
            <td class="text-end"><?= rp($it['price_at_order']) ?></td>
            <td class="text-end fw-semibold"><?= rp($it['subtotal']) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr>
            <th colspan="3" class="text-end">Total</th>
            <th class="text-end harga fs-5"><?= rp($order['total_price']) ?></th>
          </tr>
        </tfoot>
      </table>
    </div>

    <?php if ($order['status'] === 'disajikan'): ?>
      <div class="alert alert-success mt-3 mb-0">
        Pesanan Anda sudah disajikan. Selamat menikmati! Terima kasih telah berkunjung ke Sangur Cafe.
      </div>
    <?php else: ?>
      <p class="text-muted small mt-3 mb-0 text-center">
        Halaman ini akan diperbarui otomatis. Mohon tunggu, pesanan Anda sedang kami proses.
      </p>
    <?php endif; ?>
  </div>
</div>

<a href="<?= BASE_URL ?>customer/menu.php?table=<?= (int)$order['table_number'] ?>" class="btn btn-outline-cafe btn-sm mt-3">&larr; Pesan menu lagi</a>

<?php if ($order['status'] !== 'disajikan'): ?>
<script>
// Fallback auto-refresh via JavaScript (bila meta/header refresh diabaikan)
setTimeout(function () { location.reload(); }, 5000);
</script>
<?php endif; ?>

<?php else: ?>

<div class="customer-header mb-4">
  <h5>Sangur Cafe &mdash; Cek Status Pesanan</h5>
  <small>Masukkan nomor pesanan yang Anda terima</small>
</div>

<?php if ($not_found): ?>
<div class="alert alert-warning" role="alert"><?= e($not_found) ?></div>
<?php endif; ?>

<?php endif; // order ?>

<!-- Form cek status pesanan lain -->
<div class="card mt-4">
  <div class="card-body">
    <h6 class="fw-bold mb-3">Cek status pesanan lain</h6>
    <form method="get" action="" class="row g-2 align-items-center">
      <div class="col-auto flex-grow-1">
        <input type="number" name="order" class="form-control" placeholder="Nomor pesanan, contoh: 5" min="1" required>
      </div>
      <div class="col-auto">
        <button type="submit" class="btn btn-cafe">Cek Status</button>
      </div>
    </form>
  </div>
</div>

<?php require __DIR__ . '/../includes/footer.php';
