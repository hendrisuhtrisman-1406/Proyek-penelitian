<?php
// ============================================================
// MENU DIGITAL PELANGGAN (setelah scan QR)
// Parameter GET: ?table=N  (N = table_number di tabel `tables`)
// Cart disimpan di $_SESSION['cart'] = [menu_id => qty],
// di-reset per meja (table_id disimpan di $_SESSION['cart_table_id']).
// ============================================================
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

$page_title = 'Menu Digital';
$customer_page = true;

/** Inisial nama (maks 2 huruf) untuk placeholder gambar menu */
function initials($name) {
    $words = preg_split('/\s+/', trim((string)$name));
    $init = '';
    foreach (array_slice($words, 0, 2) as $w) {
        if ($w !== '') $init .= strtoupper(substr($w, 0, 1));
    }
    return $init !== '' ? $init : '?';
}

// --- Validasi meja dari parameter ?table=N ---
$table_number = isset($_GET['table']) ? (int)$_GET['table'] : 0;
$table_id = null;
$meja_error = '';

if ($table_number > 0) {
    $stmt = $pdo->prepare('SELECT id, table_number FROM tables WHERE table_number = ?');
    $stmt->execute([$table_number]);
    $meja = $stmt->fetch();
    if ($meja) {
        $table_id = (int)$meja['id'];
        // Reset keranjang bila pindah meja
        if (!isset($_SESSION['cart_table_id']) || (int)$_SESSION['cart_table_id'] !== $table_id) {
            $_SESSION['cart'] = [];
            $_SESSION['cart_table_id'] = $table_id;
        }
    } else {
        $meja_error = 'Meja nomor ' . $table_number . ' tidak terdaftar di sistem. Silakan scan QR code yang tersedia di meja Anda.';
    }
} else {
    $meja_error = 'Parameter meja tidak ditemukan. Silakan scan QR code yang ada di meja Anda untuk membuka menu.';
}

// --- Proses POST: tambah / naik / turunkan jumlah di keranjang ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action     = $_POST['action'] ?? '';
    $menu_id    = (int)($_POST['menu_id'] ?? 0);
    $post_table = (int)($_POST['table'] ?? 0);

    $tid = null;
    if ($post_table > 0) {
        $st = $pdo->prepare('SELECT id, table_number FROM tables WHERE table_number = ?');
        $st->execute([$post_table]);
        $trow = $st->fetch();
        if ($trow) $tid = (int)$trow['id'];
    }
    if (!$tid) {
        $_SESSION['flash'] = 'Meja tidak valid. Silakan scan QR ulang.';
        redirect('customer/menu.php');
    }
    if (!isset($_SESSION['cart_table_id']) || (int)$_SESSION['cart_table_id'] !== $tid) {
        $_SESSION['cart'] = [];
        $_SESSION['cart_table_id'] = $tid;
    }
    if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }

    if ($menu_id > 0 && in_array($action, ['add', 'inc', 'dec'], true)) {
        $st = $pdo->prepare('SELECT id, name, price, status FROM menus WHERE id = ?');
        $st->execute([$menu_id]);
        $menu = $st->fetch();
        if (!$menu) {
            $_SESSION['flash'] = 'Menu tidak ditemukan.';
        } elseif ($menu['status'] !== 'tersedia' && $action !== 'dec') {
            $_SESSION['flash'] = 'Maaf, ' . $menu['name'] . ' sedang habis dan tidak dapat dipesan.';
        } else {
            $cur = (int)($_SESSION['cart'][$menu_id] ?? 0);
            if ($action === 'add') {
                $qty = (int)($_POST['qty'] ?? 1);
                if ($qty < 1) $qty = 1;
                $_SESSION['cart'][$menu_id] = $cur + $qty;
                $_SESSION['flash'] = $menu['name'] . ' berhasil ditambahkan ke keranjang.';
            } elseif ($action === 'inc') {
                $_SESSION['cart'][$menu_id] = $cur + 1;
            } elseif ($action === 'dec') {
                if ($cur <= 1) {
                    unset($_SESSION['cart'][$menu_id]);
                } else {
                    $_SESSION['cart'][$menu_id] = $cur - 1;
                }
            }
        }
    }
    redirect('customer/menu.php?table=' . $post_table);
}

// --- Ambil kategori & menu per kategori ---
$categories = $pdo->query('SELECT id, name FROM categories ORDER BY id')->fetchAll();
$menus_by_cat = [];
foreach ($categories as $c) {
    $st = $pdo->prepare('SELECT * FROM menus WHERE category_id = ? ORDER BY id');
    $st->execute([$c['id']]);
    $menus_by_cat[(int)$c['id']] = $st->fetchAll();
}

// --- Peta qty keranjang + ringkasan (total & jumlah item) ---
$cart_qty   = [];
$cart_items = [];
$cart_total = 0;
$cart_count = 0;
if (!empty($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $mid => $q) {
        $mid = (int)$mid;
        $q   = (int)$q;
        if ($mid > 0 && $q > 0) $cart_qty[$mid] = $q;
    }
    if (!empty($cart_qty)) {
        $ids = array_keys($cart_qty);
        $in  = implode(',', array_fill(0, count($ids), '?'));
        $st  = $pdo->prepare("SELECT id, name, price FROM menus WHERE id IN ($in)");
        $st->execute($ids);
        foreach ($st->fetchAll() as $m) {
            $q   = (int)$cart_qty[(int)$m['id']];
            $sub = $q * (int)$m['price'];
            $cart_items[] = ['id' => (int)$m['id'], 'name' => $m['name'], 'qty' => $q, 'subtotal' => $sub];
            $cart_total  += $sub;
            $cart_count  += $q;
        }
    }
}

require __DIR__ . '/../includes/header.php';
?>

<?php if ($table_id): ?>
<div class="customer-header mb-4">
  <h5>Sangur Cafe &mdash; Meja <?= (int)$table_number ?></h5>
  <small>Pilih menu favoritmu, pesan langsung dari meja</small>
</div>
<?php else: ?>
<div class="alert alert-danger" role="alert">
  <h5 class="alert-heading">Meja Tidak Valid</h5>
  <p class="mb-0"><?= e($meja_error) ?></p>
</div>
<?php endif; ?>

<?php if ($table_id): ?>
<div class="row">
  <!-- ===== DAFTAR MENU ===== -->
  <div class="col-md-8">
    <?php foreach ($categories as $cat):
      $cid  = (int)$cat['id'];
      $list = $menus_by_cat[$cid] ?? [];
    ?>
    <h5 class="mt-2 mb-3 fw-bold">
      <span class="badge badge-kategori fs-6"><?= e($cat['name']) ?></span>
    </h5>
    <div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 g-3 mb-4">
      <?php foreach ($list as $m):
        $mid   = (int)$m['id'];
        $habis = ($m['status'] === 'habis');
        $qC    = (int)($cart_qty[$mid] ?? 0);
      ?>
      <div class="col">
        <div class="card menu-card <?= $habis ? 'menu-habis' : '' ?>">
          <?php if (!empty($m['image'])): ?>
            <img src="<?= BASE_URL ?>assets/img/<?= e($m['image']) ?>" class="card-img-top" alt="<?= e($m['name']) ?>" loading="lazy">
          <?php else: ?>
            <div class="menu-placeholder" style="background:linear-gradient(135deg,#6d4c41,#a0522d)"><?= e(initials($m['name'])) ?></div>
          <?php endif; ?>
          <div class="card-body">
            <div class="d-flex justify-content-between align-items-start mb-1">
              <h6 class="card-title mb-0"><?= e($m['name']) ?></h6>
              <?php if ($habis): ?><span class="badge bg-danger">Habis</span><?php endif; ?>
            </div>
            <p class="small text-muted mb-2" style="min-height:2.6em"><?= e($m['description']) ?></p>
            <div class="d-flex justify-content-between align-items-center">
              <span class="harga"><?= rp($m['price']) ?></span>
              <?php if ($habis): ?>
                <button type="button" class="btn btn-secondary btn-sm" disabled>Habis</button>
              <?php elseif ($qC > 0): ?>
                <form method="post" action="" class="d-inline">
                  <input type="hidden" name="menu_id" value="<?= $mid ?>">
                  <input type="hidden" name="table" value="<?= (int)$table_number ?>">
                  <div class="input-group input-group-sm" style="width:112px">
                    <button type="submit" name="action" value="dec" class="btn btn-outline-cafe">&minus;</button>
                    <span class="input-group-text flex-grow-1 justify-content-center fw-bold"><?= $qC ?></span>
                    <button type="submit" name="action" value="inc" class="btn btn-outline-cafe">+</button>
                  </div>
                </form>
              <?php else: ?>
                <form method="post" action="" class="d-inline">
                  <input type="hidden" name="action" value="add">
                  <input type="hidden" name="menu_id" value="<?= $mid ?>">
                  <input type="hidden" name="table" value="<?= (int)$table_number ?>">
                  <div class="input-group input-group-sm" style="width:138px">
                    <input type="number" name="qty" value="1" min="1" class="form-control" aria-label="Jumlah">
                    <button type="submit" class="btn btn-cafe">Tambah</button>
                  </div>
                </form>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- ===== RINGKASAN KERANJANG (desktop sidebar) ===== -->
  <div class="col-md-4">
    <div class="card order-card sticky-top d-none d-md-block" style="top:1rem">
      <div class="card-body">
        <h6 class="fw-bold mb-3">Keranjang (<?= (int)$cart_count ?> item)</h6>
        <?php if (empty($cart_items)): ?>
          <p class="text-muted small mb-0">Keranjang masih kosong. Pilih menu untuk mulai memesan.</p>
        <?php else: ?>
          <ul class="list-unstyled small mb-3">
            <?php foreach ($cart_items as $ci): ?>
            <li class="d-flex justify-content-between border-bottom py-1">
              <span><?= e($ci['name']) ?> &times; <?= (int)$ci['qty'] ?></span>
              <span class="fw-bold"><?= rp($ci['subtotal']) ?></span>
            </li>
            <?php endforeach; ?>
          </ul>
          <div class="d-flex justify-content-between fw-bold mb-3">
            <span>Total</span><span class="harga"><?= rp($cart_total) ?></span>
          </div>
          <a href="<?= BASE_URL ?>customer/cart.php" class="btn btn-cafe w-100">Lihat Keranjang &amp; Pesan</a>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<!-- Tombol keranjang mengambang (mobile) -->
<?php if ($cart_count > 0): ?>
<a href="<?= BASE_URL ?>customer/cart.php" class="cart-fab d-md-none">
  Keranjang (<?= (int)$cart_count ?>) &middot; <?= rp($cart_total) ?>
</a>
<?php endif; ?>

<?php endif; // table_id valid ?>

<?php require __DIR__ . '/../includes/footer.php';
