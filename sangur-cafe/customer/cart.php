<?php
// ============================================================
// KERANJANG PELANGGAN
// Menampilkan item pesanan, kontrol qty, total, input nama,
// dan form "Kirim Pesanan" yang POST ke api/submit-order.php.
// ============================================================
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

$page_title = 'Keranjang Pesanan';
$customer_page = true;

if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// --- Proses POST: ubah jumlah / hapus item ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action  = $_POST['action'];
    $menu_id = (int)($_POST['menu_id'] ?? 0);
    if ($menu_id > 0 && in_array($action, ['inc', 'dec', 'remove'], true)) {
        $cur = (int)($_SESSION['cart'][$menu_id] ?? 0);
        if ($action === 'inc') {
            $_SESSION['cart'][$menu_id] = $cur + 1;
        } elseif ($action === 'remove' || $cur <= 1) {
            unset($_SESSION['cart'][$menu_id]);
        } else {
            $_SESSION['cart'][$menu_id] = $cur - 1;
        }
    }
    redirect('customer/cart.php');
}

// --- Fungsi: cari table_number dari session cart_table_id ---
$table_id     = (int)($_SESSION['cart_table_id'] ?? 0);
$table_number = 0;
if ($table_id > 0) {
    $st = $pdo->prepare('SELECT table_number FROM tables WHERE id = ?');
    $st->execute([$table_id]);
    $trow = $st->fetch();
    if ($trow) $table_number = (int)$trow['table_number'];
}

// --- Jika keranjang kosong, kembalikan ke menu ---
if (empty($_SESSION['cart'])) {
    $_SESSION['flash'] = 'Keranjang Anda kosong. Silakan pilih menu terlebih dahulu.';
    if ($table_number > 0) {
        redirect('customer/menu.php?table=' . $table_number);
    }
    redirect('');
}

// --- Ambil detail item dari database (harga dihitung server-side) ---
$ids = array_keys($_SESSION['cart']);
$in  = implode(',', array_fill(0, count($ids), '?'));
$st  = $pdo->prepare("SELECT * FROM menus WHERE id IN ($in)");
$st->execute($ids);

$items = [];
$total = 0;
foreach ($st->fetchAll() as $m) {
    $q = (int)($_SESSION['cart'][$m['id']] ?? 0);
    if ($q <= 0) continue;
    $sub   = $q * (int)$m['price'];
    $items[] = ['menu' => $m, 'qty' => $q, 'subtotal' => $sub];
    $total  += $sub;
}

// Bila setelah filter tidak ada item valid, kembali ke menu
if (empty($items)) {
    $_SESSION['cart'] = [];
    $_SESSION['flash'] = 'Keranjang Anda kosong. Silakan pilih menu terlebih dahulu.';
    if ($table_number > 0) redirect('customer/menu.php?table=' . $table_number);
    redirect('');
}

require __DIR__ . '/../includes/header.php';
?>

<div class="customer-header mb-4">
  <h5>Sangur Cafe &mdash; Keranjang Pesanan</h5>
  <small>Meja <?= $table_number > 0 ? (int)$table_number : '-' ?> &middot; periksa pesanan Anda sebelum dikirim</small>
</div>

<div class="row">
  <div class="col-lg-8">
    <div class="card order-card">
      <div class="card-body">
        <h6 class="fw-bold mb-3">Item Pesanan</h6>
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead>
              <tr>
                <th>Menu</th>
                <th>Harga</th>
                <th class="text-center" style="width:140px">Jumlah</th>
                <th class="text-end">Subtotal</th>
                <th style="width:40px"></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($items as $it): $m = $it['menu']; ?>
              <tr>
                <td class="fw-semibold"><?= e($m['name']) ?></td>
                <td><?= rp($m['price']) ?></td>
                <td class="text-center">
                  <form method="post" action="" class="d-inline-flex align-items-center gap-1">
                    <input type="hidden" name="menu_id" value="<?= (int)$m['id'] ?>">
                    <button type="submit" name="action" value="dec" class="btn btn-sm btn-outline-cafe" aria-label="Kurangi">&minus;</button>
                    <span class="fw-bold px-2"><?= (int)$it['qty'] ?></span>
                    <button type="submit" name="action" value="inc" class="btn btn-sm btn-outline-cafe" aria-label="Tambah">+</button>
                  </form>
                </td>
                <td class="text-end fw-bold"><?= rp($it['subtotal']) ?></td>
                <td>
                  <form method="post" action="" class="d-inline">
                    <input type="hidden" name="menu_id" value="<?= (int)$m['id'] ?>">
                    <button type="submit" name="action" value="remove" class="btn btn-sm btn-outline-danger" aria-label="Hapus">&times;</button>
                  </form>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
            <tfoot>
              <tr>
                <th colspan="3" class="text-end">Total</th>
                <th class="text-end harga fs-5"><?= rp($total) ?></th>
                <th></th>
              </tr>
            </tfoot>
          </table>
        </div>
        <a href="<?= BASE_URL ?>customer/menu.php?table=<?= (int)$table_number ?>" class="btn btn-outline-cafe btn-sm mt-3">&larr; Tambah menu lain</a>
      </div>
    </div>
  </div>

  <div class="col-lg-4">
    <div class="card order-card sticky-top" style="top:1rem">
      <div class="card-body">
        <h6 class="fw-bold mb-3">Kirim Pesanan</h6>
        <form method="post" action="<?= BASE_URL ?>api/submit-order.php" id="form-kirim">
          <input type="hidden" name="table_id" value="<?= (int)$table_id ?>">
          <?php foreach ($items as $it): ?>
            <input type="hidden" name="items[<?= (int)$it['menu']['id'] ?>]" value="<?= (int)$it['qty'] ?>">
          <?php endforeach; ?>

          <div class="mb-3">
            <label for="customer_name" class="form-label small fw-semibold">Nama Anda <span class="text-muted">(opsional)</span></label>
            <input type="text" name="customer_name" id="customer_name" class="form-control" maxlength="100" placeholder="Misal: Budi / Ibu Ani">
          </div>

          <div class="d-flex justify-content-between fw-bold mb-3 border-top pt-3">
            <span>Total Pembayaran</span>
            <span class="harga fs-5"><?= rp($total) ?></span>
          </div>

          <button type="submit" class="btn btn-cafe btn-lg w-100" id="btn-kirim">Kirim Pesanan</button>
          <p class="text-muted small mt-2 mb-0 text-center">Pesanan akan langsung diterima kasir &amp; dapur. Pembayaran di meja.</p>
        </form>
      </div>
    </div>
  </div>
</div>

<script>
// Cegah double submit
document.getElementById('form-kirim').addEventListener('submit', function () {
  var b = document.getElementById('btn-kirim');
  if (b) { b.disabled = true; b.textContent = 'Mengirim...'; }
});
</script>

<?php require __DIR__ . '/../includes/footer.php';
