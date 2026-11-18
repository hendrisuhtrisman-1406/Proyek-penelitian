<?php
// ============================================================
// KELOLA MEJA + QR CODE - generate QR per meja (offline qrcode.min.js)
// ============================================================
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_role(['owner']);

// --- Tambah meja ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add') {
    $table_number = (int) ($_POST['table_number'] ?? 0);
    if ($table_number <= 0) {
        $_SESSION['flash'] = 'Nomor meja harus berupa angka positif.';
    } else {
        $qr_token = md5(uniqid((string) mt_rand(), true));
        try {
            $stmt = $pdo->prepare("INSERT INTO tables (table_number, qr_token) VALUES (?, ?)");
            $stmt->execute([$table_number, $qr_token]);
            $_SESSION['flash'] = 'Meja ' . $table_number . ' berhasil ditambahkan.';
        } catch (PDOException $ex) {
            if ($ex->getCode() === '23000') {
                $_SESSION['flash'] = 'Meja dengan nomor ' . $table_number . ' sudah ada.';
            } else {
                $_SESSION['flash'] = 'Gagal menambahkan meja.';
            }
        }
    }
    redirect('owner/tables.php');
}

// --- Hapus meja ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $id = (int) ($_POST['id'] ?? 0);
    if ($id > 0) {
        $stmt = $pdo->prepare("DELETE FROM tables WHERE id = ?");
        $stmt->execute([$id]);
        $_SESSION['flash'] = 'Meja berhasil dihapus. (Pesanan terkait ikut terhapus.)';
    }
    redirect('owner/tables.php');
}

// --- Daftar meja ---
$stmt = $pdo->prepare("SELECT * FROM tables ORDER BY table_number ASC");
$stmt->execute();
$tables = $stmt->fetchAll();
$table_numbers = array_column($tables, 'table_number');

$page_title = 'Kelola Meja & QR';
require __DIR__ . '/../includes/header.php';
?>
<style>
@media screen {
    .print-qr-area { position: absolute; left: -10000px; top: 0; width: 220px; }
}
@media print {
    .print-qr-area { position: static; }
    .print-qr-item { display: inline-block; margin: 1rem; text-align: center; page-break-inside: avoid; }
    .print-qr-item .qr-box { border: 1px solid #999; }
}
</style>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
        <h4 class="fw-bold mb-1">Kelola Meja &amp; QR Code</h4>
        <p class="text-muted mb-0">Setiap meja memiliki QR Code yang dipindai pelanggan untuk memesan.</p>
    </div>
    <button type="button" class="btn btn-cafe" onclick="window.print()">Cetak Semua QR</button>
</div>

<div class="no-print">
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form method="POST" action="" class="row g-2 align-items-end">
                <input type="hidden" name="action" value="add">
                <div class="col-12 col-md-4">
                    <label class="form-label small fw-semibold">Nomor Meja Baru</label>
                    <input type="number" name="table_number" class="form-control" min="1" required placeholder="Contoh: 7">
                </div>
                <div class="col-12 col-md-3">
                    <button type="submit" class="btn btn-cafe w-100">+ Tambah Meja</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nomor Meja</th>
                        <th>Token QR</th>
                        <th class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($tables)): ?>
                        <tr><td colspan="4" class="text-center text-muted py-4">Belum ada meja.</td></tr>
                    <?php else: foreach ($tables as $t): ?>
                    <tr>
                        <td><?= (int) $t['id'] ?></td>
                        <td class="fw-semibold">Meja <?= (int) $t['table_number'] ?></td>
                        <td><code class="small"><?= e($t['qr_token']) ?></code></td>
                        <td class="text-center">
                            <button type="button" class="btn btn-sm btn-outline-cafe me-1 btn-show-qr"
                                    data-table="<?= (int) $t['table_number'] ?>">Tampilkan QR</button>
                            <form method="POST" action="" class="d-inline"
                                  onsubmit="return confirmHapus('Yakin menghapus meja ini? Semua pesanan terkait akan ikut terhapus.');">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-danger">Hapus</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal QR Code -->
<div class="modal fade" id="qrModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="qrModalLabel">QR Code Meja</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body text-center">
                <div class="qr-box mb-3">
                    <div id="qr-target"></div>
                </div>
                <p class="mb-1 fw-semibold" id="qr-table-label">Meja -</p>
                <p class="small text-muted mb-0">URL: <span id="qr-url-text" class="text-break"></span></p>
                <p class="small text-muted">Pindai QR ini untuk membuka menu pelanggan di meja tersebut.</p>
            </div>
            <div class="modal-footer no-print">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<!-- Area cetak semua QR (tersembunyi di layar, tampil saat print) -->
<div class="print-qr-area" id="print-qr-area">
    <h4 class="mb-3">QR Code Meja — Sangur Cafe</h4>
    <?php foreach ($tables as $t): ?>
        <div class="print-qr-item">
            <div class="qr-box">
                <div id="qr-print-<?= (int) $t['table_number'] ?>"></div>
            </div>
            <p class="fw-bold mt-2">Meja <?= (int) $t['table_number'] ?></p>
        </div>
    <?php endforeach; ?>
</div>

<script src="<?= BASE_URL ?>assets/lib/qrcode.min.js"></script>
<script>
var BASE_URL_PATH = '<?= e(BASE_URL) ?>';
var TABLE_NUMBERS = <?= json_encode(array_map('intval', $table_numbers)) ?>;

function buildQrUrl(tableNumber) {
    return window.location.origin + BASE_URL_PATH + 'customer/menu.php?table=' + tableNumber;
}

// Generate semua QR untuk area cetak saat halaman dimuat
window.addEventListener('load', function () {
    for (var i = 0; i < TABLE_NUMBERS.length; i++) {
        var num = TABLE_NUMBERS[i];
        var el = document.getElementById('qr-print-' + num);
        if (el) {
            new QRCode(el, { text: buildQrUrl(num), width: 160, height: 160 });
        }
    }
});

// Tampilkan QR per meja di modal
document.addEventListener('click', function (e) {
    var btn = e.target.closest('.btn-show-qr');
    if (!btn) return;
    var num = parseInt(btn.getAttribute('data-table'), 10);
    var url = buildQrUrl(num);

    document.getElementById('qr-table-label').textContent = 'Meja ' + num;
    document.getElementById('qr-url-text').textContent = url;

    var target = document.getElementById('qr-target');
    target.innerHTML = '';
    new QRCode(target, { text: url, width: 180, height: 180 });

    var modal = new bootstrap.Modal(document.getElementById('qrModal'));
    modal.show();
});
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
