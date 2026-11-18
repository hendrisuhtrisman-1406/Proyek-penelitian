<?php
// Samakan hash live DB dengan yang akan ditulis di database.sql
require_once __DIR__ . '/config/database.php';
$map = [
    'owner'   => '$2y$10$o2AhSTlCtxt.QV/z5sFRCuZlRg9v04DKYEhnGtdPWBJQCWZiqG/4C',
    'kasir'   => '$2y$10$3/YyLNdm4ICw9J2LOqQH5uTQgx755L4vavrA0PX4qF2JDXuRQQ5L6',
    'kitchen' => '$2y$10$jv4u.a9F2DkD9eISasz.h.JSyHf8sQq8r6l.yoFIbpmjP/V4K42p2',
];
$pass = ['owner' => 'owner123', 'kasir' => 'kasir123', 'kitchen' => 'kitchen123'];
$upd = $pdo->prepare("UPDATE users SET password_hash = ? WHERE username = ?");
foreach ($map as $u => $h) {
    $upd->execute([$h, $u]);
    echo $u . " -> " . (password_verify($pass[$u], $h) ? "VALID" : "GAGAL") . "\n";
}
echo "SYNC_DONE\n";
