<?php
// ============================================================
// LOGOUT - hapus session lalu redirect ke halaman login
// ============================================================
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

$_SESSION = [];
session_destroy();

redirect('login.php');
