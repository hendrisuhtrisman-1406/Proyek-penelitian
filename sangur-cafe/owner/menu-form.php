<?php
// ============================================================
// FORM TAMBAH / EDIT MENU (file tambahan yang diperbolehkan)
// Upload gambar ke assets/img/, simpan nama file saja.
// ============================================================
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_role(['owner']);

$action = $_GET['action'] ?? 'add';
$id     = (int) ($_GET['id'] ?? 0);
$is_edit = ($action === 'edit' && $id > 0);

// Data default form
$menu = [
    'id'          => 0,
    'name'        => '',
    'description' => '',
    'price'       => 0,
    'category_id' => 1,
    'image'       => null,
    'status'      => 'tersedia',
];

if ($is_edit) {
    $stmt = $pdo->prepare("SELECT * FROM menus WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) {
        $_SESSION['flash'] = 'Menu tidak ditemukan.';
        redirect('owner/menu-manage.php');
    }
    $menu = $row;
}

// Kategori untuk dropdown
$stmt = $pdo->prepare("SELECT * FROM categories ORDER BY id ASC");
$stmt->execute();
$categories = $stmt->fetchAll();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name        = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $price       = (int) ($_POST['price'] ?? 0);
    $category_id = (int) ($_POST['category_id'] ?? 0);
    $status      = $_POST['status'] ?? 'tersedia';
    if (!in_array($status, ['tersedia', 'habis'], true)) $status = 'tersedia';

    // Validasi dasar
    if ($name === '') $errors[] = 'Nama menu wajib diisi.';
    if ($price <= 0)  $errors[] = 'Harga harus lebih dari 0 (Rp).';

    // Validasi kategori
    $stmt = $pdo->prepare("SELECT id FROM categories WHERE id = ? LIMIT 1");
    $stmt->execute([$category_id]);
    if (!$stmt->fetch()) $errors[] = 'Kategori tidak valid.';

    // Upload gambar (opsional)
    $image_name = $is_edit ? $menu['image'] : null;
    if (!empty($_FILES['image']['name'])) {
        $allowed_ext = ['jpg', 'jpeg', 'png', 'svg', 'gif'];
        $max_size    = 2 * 1024 * 1024; // 2 MB
        $file        = $_FILES['image'];

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'Gagal mengunggah gambar (kode error: ' . (int) $file['error'] . ').';
        } elseif ($file['size'] > $max_size) {
            $errors[] = 'Ukuran gambar terlalu besar. Maksimal 2 MB.';
        } else {
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, $allowed_ext, true)) {
                $errors[] = 'Ekstensi gambar tidak diizinkan. Gunakan: jpg, jpeg, png, svg, atau gif.';
            } else {
                $image_name = 'menu_' . date('YmdHis') . '_' . substr(md5(uniqid('', true)), 0, 8) . '.' . $ext;
                $dest = __DIR__ . '/../assets/img/' . $image_name;
                if (!move_uploaded_file($file['tmp_name'], $dest)) {
                    $errors[] = 'Gagal menyimpan file gambar.';
                    $image_name = $is_edit ? $menu['image'] : null;
                }
            }
        }
    }

    if (empty($errors)) {
        if ($is_edit) {
            $stmt = $pdo->prepare(
                "UPDATE menus SET name = ?, description = ?, price = ?, category_id = ?, image = ?, status = ? WHERE id = ?"
            );
            $stmt->execute([$name, $description, $price, $category_id, $image_name, $status, $id]);
            $_SESSION['flash'] = 'Menu berhasil diperbarui.';
        } else {
            $stmt = $pdo->prepare(
                "INSERT INTO menus (name, description, price, category_id, image, status) VALUES (?, ?, ?, ?, ?, ?)"
            );
            $stmt->execute([$name, $description, $price, $category_id, $image_name, $status]);
            $_SESSION['flash'] = 'Menu berhasil ditambahkan.';
        }
        redirect('owner/menu-manage.php');
    }

    // Jika ada error: tampilkan flash & isi ulang form dari input
    $_SESSION['flash'] = implode(' ', $errors);
    $menu = array_merge($menu, [
        'name'        => $name,
        'description' => $description,
        'price'       => $price,
        'category_id' => $category_id,
        'status'      => $status,
    ]);
}

$page_title = $is_edit ? 'Edit Menu' : 'Tambah Menu';
require __DIR__ . '/../includes/header.php';
?>
<h4 class="fw-bold mb-1"><?= $is_edit ? 'Edit Menu' : 'Tambah Menu Baru' ?></h4>
<p class="text-muted mb-4">
    <a href="<?= BASE_URL ?>owner/menu-manage.php" class="text-decoration-none">&larr; Kembali ke Daftar Menu</a>
</p>

<div class="card border-0 shadow-sm" style="max-width:720px;">
    <div class="card-body p-4">
        <form method="POST" action="" enctype="multipart/form-data">
            <div class="mb-3">
                <label class="form-label fw-semibold">Nama Menu <span class="text-danger">*</span></label>
                <input type="text" name="name" class="form-control" required maxlength="100"
                       value="<?= e($menu['name']) ?>" placeholder="Contoh: Nasi Goreng Spesial">
            </div>

            <div class="mb-3">
                <label class="form-label fw-semibold">Deskripsi</label>
                <textarea name="description" class="form-control" rows="2" maxlength="500"
                          placeholder="Deskripsi singkat menu"><?= e($menu['description'] ?? '') ?></textarea>
            </div>

            <div class="row g-3">
                <div class="col-12 col-md-6">
                    <label class="form-label fw-semibold">Harga (Rp) <span class="text-danger">*</span></label>
                    <input type="number" name="price" class="form-control" required min="1" step="1"
                           value="<?= (int) $menu['price'] ?>" placeholder="Contoh: 25000">
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label fw-semibold">Kategori <span class="text-danger">*</span></label>
                    <select name="category_id" class="form-select" required>
                        <?php foreach ($categories as $c): ?>
                            <option value="<?= (int) $c['id'] ?>" <?= (int) $menu['category_id'] === (int) $c['id'] ? 'selected' : '' ?>>
                                <?= e($c['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label fw-semibold">Status</label>
                    <select name="status" class="form-select">
                        <option value="tersedia" <?= $menu['status'] === 'tersedia' ? 'selected' : '' ?>>Tersedia</option>
                        <option value="habis"    <?= $menu['status'] === 'habis'    ? 'selected' : '' ?>>Habis</option>
                    </select>
                </div>
            </div>

            <div class="mb-3 mt-3">
                <label class="form-label fw-semibold">Gambar Menu</label>
                <?php if ($is_edit && !empty($menu['image'])): ?>
                    <div class="mb-2">
                        <img src="<?= BASE_URL ?>assets/img/<?= e($menu['image']) ?>"
                             alt="Gambar saat ini"
                             style="width:90px;height:90px;object-fit:cover;border-radius:10px;background:#f5ebe0;">
                        <small class="text-muted ms-2">Gambar saat ini. Biarkan kosong jika tidak ingin mengubah.</small>
                    </div>
                <?php endif; ?>
                <input type="file" name="image" class="form-control" accept=".jpg,.jpeg,.png,.svg,.gif">
                <small class="text-muted">Format: JPG, JPEG, PNG, SVG, GIF. Maksimal 2 MB. Kosongkan jika tidak ingin mengubah gambar.</small>
            </div>

            <div class="d-flex gap-2 mt-4">
                <button type="submit" class="btn btn-cafe px-4">
                    <?= $is_edit ? 'Simpan Perubahan' : 'Tambah Menu' ?>
                </button>
                <a href="<?= BASE_URL ?>owner/menu-manage.php" class="btn btn-outline-secondary">Batal</a>
            </div>
        </form>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
