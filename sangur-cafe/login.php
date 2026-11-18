<?php
// ============================================================
// LOGIN STAFF - Sangur Cafe POS
// POST username + password -> verifikasi password_verify ->
// set session -> redirect sesuai role. Gagal -> flash error.
// ============================================================
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

if (is_login()) {
    $role = $_SESSION['role'] ?? '';
    if ($role === 'owner')   redirect('owner/dashboard.php');
    if ($role === 'kasir')   redirect('kasir/dashboard.php');
    if ($role === 'kitchen') redirect('kitchen/dashboard.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare("SELECT id, nama, username, password_hash, role FROM users WHERE username = ? LIMIT 1");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['nama']    = $user['nama'];
        $_SESSION['role']    = $user['role'];

        if ($user['role'] === 'owner')   redirect('owner/dashboard.php');
        if ($user['role'] === 'kasir')   redirect('kasir/dashboard.php');
        if ($user['role'] === 'kitchen') redirect('kitchen/dashboard.php');
        redirect('login.php');
    }

    $_SESSION['flash'] = 'Username atau password salah. Silakan coba lagi.';
    redirect('login.php');
}

$page_title = 'Login Staff';
require __DIR__ . '/includes/header.php';
?>
<div class="card login-card">
    <div class="card-header">
        <h4 class="mb-1 fw-bold">Sangur Cafe</h4>
        <small class="opacity-75">Login Staff — Kasir / Dapur / Owner</small>
    </div>
    <div class="card-body p-4">
        <form method="POST" action="">
            <div class="mb-3">
                <label for="username" class="form-label fw-semibold">Username</label>
                <input type="text" class="form-control" id="username" name="username"
                       value="" required autofocus autocomplete="username" placeholder="Masukkan username">
            </div>
            <div class="mb-4">
                <label for="password" class="form-label fw-semibold">Password</label>
                <input type="password" class="form-control" id="password" name="password"
                       required autocomplete="current-password" placeholder="Masukkan password">
            </div>
            <button type="submit" class="btn btn-cafe w-100 fw-bold py-2">Masuk</button>
        </form>
    </div>
    <div class="card-footer text-center bg-white border-0 pb-3">
        <small class="text-muted">Pelanggan? Pindai QR Code di meja Anda.</small>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
