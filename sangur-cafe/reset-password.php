<?php
// ============================================================
// SKRIP RESET PASSWORD SEMENTARA - Sangur Cafe
// Jalankan via CLI: php reset-password.php
// Akan reset ketiga akun ke default README: owner123 / kasir123 / kitchen123
// ============================================================
require_once __DIR__ . '/config/database.php';

$accounts = [
    'owner'   => 'owner123',
    'kasir'   => 'kasir123',
    'kitchen' => 'kitchen123',
];

echo "=== Reset Password Akun Sangur Cafe ===\n\n";

$stmt = $pdo->query("SELECT id, username, role FROM users ORDER BY id");
$users = $stmt->fetchAll();
echo "Akun saat ini di database:\n";
foreach ($users as $u) {
    echo "  - id={$u['id']} username={$u['username']} role={$u['role']}\n";
}
echo "\n";

$upd = $pdo->prepare("UPDATE users SET password_hash = ? WHERE username = ?");
foreach ($accounts as $username => $plain) {
    $hash = password_hash($plain, PASSWORD_BCRYPT, ['cost' => 10]);
    $upd->execute([$hash, $username]);
    $rows = $upd->rowCount();
    echo ($rows > 0 ? "[OK]   " : "[SKIP] ") . "username={$username} -> password='{$plain}' (rows={$rows})\n";
    // verify
    $chk = $pdo->prepare("SELECT password_hash FROM users WHERE username = ?");
    $chk->execute([$username]);
    $h = $chk->fetchColumn();
    echo "       verify: " . (password_verify($plain, $h) ? "VALID" : "GAGAL") . "\n";
}

echo "\nSelesai. Silakan login di http://localhost/sangur-cafe/login.php\n";
echo "Disarankan segera ganti password melalui menu Owner -> Pengguna.\n";
echo "File ini bisa dihapus setelah selesai: reset-password.php\n";
