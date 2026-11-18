<?php
// ============================================================
// KELOLA MENU - daftar menu + hapus (tambah/edit di menu-form.php)
// ============================================================
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_role(['owner']);

// --- Hapus menu (POST, dengan konfirmasi JS di sisi klien) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $id = (int) ($_POST['id'] ?? 0);
    if ($id > 0) {
        $stmt = $pdo->prepare("DELETE FROM menus WHERE id = ?");
        $stmt->execute([$id]);
        $_SESSION['flash'] = 'Menu berhasil dihapus.';
    } else {
        $_SESSION['flash'] = 'Menu tidak valid.';
    }
    redirect('owner/menu-manage.php');
}

// --- Daftar menu ---
$stmt = $pdo->prepare(
    "SELECT m.*, c.name AS category_name
     FROM menus m
     JOIN categories c ON c.id = m.category_id
     ORDER BY c.name ASC, m.name ASC"
);
$stmt->execute();
$menus = $stmt->fetchAll();

$page_title = 'Kelola Menu';
require __DIR__ . '/../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
        <h4 class="fw-bold mb-1">Kelola Menu</h4>
        <p class="text-muted mb-0">Daftar menu yang tersedia di Sangur Cafe.</p>
    </div>
    <a href="<?= BASE_URL ?>owner/menu-form.php?action=add" class="btn btn-cafe">+ Tambah Menu</a>
</div>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Foto</th>
                    <th>Nama Menu</th>
                    <th>Kategori</th>
                    <th class="text-end">Harga</th>
                    <th>Status</th>
                    <th class="text-center">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($menus)): ?>
                    <tr><td colspan="6" class="text-center text-muted py-4">Belum ada menu.</td></tr>
                <?php else: foreach ($menus as $m): ?>
                <tr>
                    <td>
                        <?php if (!empty($m['image'])): ?>
                            <img src="<?= BASE_URL ?>assets/img/<?= e($m['image']) ?>"
                                 alt="<?= e($m['name']) ?>"
                                 style="width:56px;height:56px;object-fit:cover;border-radius:10px;background:#f5ebe0;">
                        <?php else: ?>
                            <div class="menu-placeholder" style="width:56px;height:56px;border-radius:10px;font-size:1.2rem;background:linear-gradient(135deg,#6d4c41,#a0522d);">
                                <?= e(strtoupper(substr($m['name'], 0, 1))) ?>
                            </div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="fw-semibold"><?= e($m['name']) ?></div>
                        <?php $desc = (string) ($m['description'] ?? ''); ?>
                        <small class="text-muted"><?= e(strlen($desc) > 60 ? substr($desc, 0, 60) . '...' : $desc) ?></small>
                    </td>
                    <td><span class="badge badge-kategori"><?= e($m['category_name']) ?></span></td>
                    <td class="text-end fw-semibold"><?= rp($m['price']) ?></td>
                    <td>
                        <?php if ($m['status'] === 'tersedia'): ?>
                            <span class="badge bg-success">Tersedia</span>
                        <?php else: ?>
                            <span class="badge bg-danger">Habis</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-center">
                        <a href="<?= BASE_URL ?>owner/menu-form.php?action=edit&id=<?= (int) $m['id'] ?>"
                           class="btn btn-sm btn-outline-cafe me-1">Edit</a>
                        <form method="POST" action="" class="d-inline"
                              onsubmit="return confirmHapus('Yakin ingin menghapus menu ini?');">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= (int) $m['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-danger">Hapus</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
