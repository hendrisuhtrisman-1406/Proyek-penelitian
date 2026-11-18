<?php
// ============================================================
// KELOLA PENGGUNA - CRUD kasir/kitchen/owner, password di-hash
// ============================================================
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_role(['owner']);

$VALID_ROLES = ['kasir', 'kitchen', 'owner'];

// --- Hapus pengguna ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $id = (int) ($_POST['id'] ?? 0);

    // Tidak boleh menghapus akun sendiri
    if ($id === (int) $_SESSION['user_id']) {
        $_SESSION['flash'] = 'Anda tidak dapat menghapus akun Anda sendiri.';
        redirect('owner/users.php');
    }

    // Ambil data user yang akan dihapus
    $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    $user = $stmt->fetch();

    if ($user) {
        // Tidak boleh menghapus akun owner terakhir
        if ($user['role'] === 'owner') {
            $stmt = $pdo->prepare("SELECT COUNT(*) AS cnt FROM users WHERE role = 'owner'");
            $stmt->execute();
            $owner_count = (int) $stmt->fetch()['cnt'];
            if ($owner_count <= 1) {
                $_SESSION['flash'] = 'Tidak dapat menghapus akun owner terakhir.';
                redirect('owner/users.php');
            }
        }
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$id]);
        $_SESSION['flash'] = 'Pengguna berhasil dihapus.';
    } else {
        $_SESSION['flash'] = 'Pengguna tidak ditemukan.';
    }
    redirect('owner/users.php');
}

// --- Simpan (tambah / edit) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save') {
    $id       = (int) ($_POST['id'] ?? 0);
    $nama     = trim($_POST['nama'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $role     = $_POST['role'] ?? '';
    $is_edit  = ($id > 0);

    $errors = [];
    if ($nama === '')     $errors[] = 'Nama wajib diisi.';
    if ($username === '') $errors[] = 'Username wajib diisi.';
    if (!in_array($role, $VALID_ROLES, true)) $errors[] = 'Role tidak valid.';

    // Cek username unik (exclude diri sendiri saat edit)
    if ($username !== '') {
        if ($is_edit) {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id <> ? LIMIT 1");
            $stmt->execute([$username, $id]);
        } else {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
            $stmt->execute([$username]);
        }
        if ($stmt->fetch()) $errors[] = 'Username sudah digunakan, silakan pilih username lain.';
    }

    // Password wajib saat tambah; saat edit kosong = tidak diubah
    if (!$is_edit && $password === '') {
        $errors[] = 'Password wajib diisi untuk pengguna baru.';
    }
    if ($password !== '' && strlen($password) < 5) {
        $errors[] = 'Password minimal 5 karakter.';
    }

    // Safety: tidak boleh menurunkan jabatan owner terakhir / mengubah role sendiri
    if ($is_edit && empty($errors)) {
        $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $old = $stmt->fetch();
        if ($old) {
            if ($id === (int) $_SESSION['user_id'] && $role !== 'owner') {
                $errors[] = 'Anda tidak dapat mengubah role akun Anda sendiri.';
            } elseif ($old['role'] === 'owner' && $role !== 'owner') {
                $stmt = $pdo->prepare("SELECT COUNT(*) AS cnt FROM users WHERE role = 'owner'");
                $stmt->execute();
                if ((int) $stmt->fetch()['cnt'] <= 1) {
                    $errors[] = 'Tidak dapat menurunkan jabatan owner terakhir.';
                }
            }
        }
    }

    if (empty($errors)) {
        try {
            if ($is_edit) {
                if ($password !== '') {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE users SET nama = ?, username = ?, password_hash = ?, role = ? WHERE id = ?");
                    $stmt->execute([$nama, $username, $hash, $role, $id]);
                } else {
                    $stmt = $pdo->prepare("UPDATE users SET nama = ?, username = ?, role = ? WHERE id = ?");
                    $stmt->execute([$nama, $username, $role, $id]);
                }
                $_SESSION['flash'] = 'Pengguna berhasil diperbarui.';
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (nama, username, password_hash, role) VALUES (?, ?, ?, ?)");
                $stmt->execute([$nama, $username, $hash, $role]);
                $_SESSION['flash'] = 'Pengguna berhasil ditambahkan.';
            }
            redirect('owner/users.php');
        } catch (PDOException $ex) {
            if ($ex->getCode() === '23000') {
                $errors[] = 'Username sudah digunakan (duplikat).';
            } else {
                $errors[] = 'Gagal menyimpan data pengguna.';
            }
        }
    }

    // Jika error: tampilkan flash & isi ulang form
    $_SESSION['flash'] = implode(' ', $errors);
    $edit_user = ['id' => $id, 'nama' => $nama, 'username' => $username, 'role' => $role];
} else {
    // Mode edit via GET ?action=edit&id=
    $edit_id = (int) ($_GET['id'] ?? 0);
    if (($_GET['action'] ?? '') === 'edit' && $edit_id > 0) {
        $stmt = $pdo->prepare("SELECT id, nama, username, role FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$edit_id]);
        $edit_user = $stmt->fetch();
        if (!$edit_user) {
            $_SESSION['flash'] = 'Pengguna tidak ditemukan.';
            redirect('owner/users.php');
        }
    } else {
        $edit_user = ['id' => 0, 'nama' => '', 'username' => '', 'role' => 'kasir'];
    }
}

// --- Daftar pengguna ---
$stmt = $pdo->prepare("SELECT id, nama, username, role, created_at FROM users ORDER BY FIELD(role, 'owner','kasir','kitchen'), id ASC");
$stmt->execute();
$users = $stmt->fetchAll();

$page_title = 'Kelola Pengguna';
require __DIR__ . '/../includes/header.php';

$role_badge = ['owner' => 'bg-danger', 'kasir' => 'bg-primary', 'kitchen' => 'bg-info'];
$is_edit_mode = ((int) $edit_user['id'] > 0);
?>
<h4 class="fw-bold mb-1">Kelola Pengguna</h4>
<p class="text-muted mb-4">Kelola akun staff (Kasir, Kitchen/Dapur, Owner). Password disimpan dalam bentuk ter-hash.</p>

<div class="row g-4">
    <!-- Form tambah / edit -->
    <div class="col-12 col-lg-4">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <strong class="text-dark"><?= $is_edit_mode ? 'Edit Pengguna' : 'Tambah Pengguna Baru' ?></strong>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <input type="hidden" name="action" value="save">
                    <input type="hidden" name="id" value="<?= (int) $edit_user['id'] ?>">

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Nama <span class="text-danger">*</span></label>
                        <input type="text" name="nama" class="form-control" required maxlength="100"
                               value="<?= e($edit_user['nama']) ?>" placeholder="Nama lengkap">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Username <span class="text-danger">*</span></label>
                        <input type="text" name="username" class="form-control" required maxlength="50"
                               value="<?= e($edit_user['username']) ?>" placeholder="Username untuk login">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">
                            Password <?= $is_edit_mode ? '' : '<span class="text-danger">*</span>' ?>
                        </label>
                        <input type="password" name="password" class="form-control" autocomplete="new-password"
                               placeholder="<?= $is_edit_mode ? 'Kosongkan jika tidak diubah' : 'Minimal 5 karakter' ?>">
                        <?php if ($is_edit_mode): ?>
                            <small class="text-muted">Kosongkan jika tidak ingin mengubah password.</small>
                        <?php endif; ?>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Role <span class="text-danger">*</span></label>
                        <select name="role" class="form-select" required>
                            <?php foreach ($VALID_ROLES as $r): ?>
                                <option value="<?= e($r) ?>" <?= $edit_user['role'] === $r ? 'selected' : '' ?>>
                                    <?= e(role_label($r)) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-cafe flex-fill">
                            <?= $is_edit_mode ? 'Simpan Perubahan' : 'Tambah Pengguna' ?>
                        </button>
                        <?php if ($is_edit_mode): ?>
                            <a href="<?= BASE_URL ?>owner/users.php" class="btn btn-outline-secondary">Batal</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Daftar pengguna -->
    <div class="col-12 col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Nama</th>
                            <th>Username</th>
                            <th>Role</th>
                            <th class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $u): ?>
                        <tr>
                            <td>
                                <span class="fw-semibold"><?= e($u['nama']) ?></span>
                                <?php if ((int) $u['id'] === (int) $_SESSION['user_id']): ?>
                                    <small class="text-muted">(Anda)</small>
                                <?php endif; ?>
                            </td>
                            <td><?= e($u['username']) ?></td>
                            <td><span class="badge <?= $role_badge[$u['role']] ?? 'bg-secondary' ?>"><?= e(role_label($u['role'])) ?></span></td>
                            <td class="text-center">
                                <a href="<?= BASE_URL ?>owner/users.php?action=edit&id=<?= (int) $u['id'] ?>"
                                   class="btn btn-sm btn-outline-cafe me-1">Edit</a>
                                <?php if ((int) $u['id'] !== (int) $_SESSION['user_id']): ?>
                                <form method="POST" action="" class="d-inline"
                                      onsubmit="return confirmHapus('Yakin ingin menghapus pengguna ini?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-danger">Hapus</button>
                                </form>
                                <?php else: ?>
                                    <button type="button" class="btn btn-sm btn-secondary" disabled title="Tidak dapat menghapus akun sendiri">Hapus</button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
