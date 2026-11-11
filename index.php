<?php
require_once __DIR__ . '/core/auth.php';

if (!isLogin()) {
    header('Location: ' . appUrl('/modules/auth/login.php'));
    exit;
}

$role = $_SESSION['user']['role'] ?? '';

if ($role === 'admin') {
    header('Location: ' . appUrl('/modules/admin/dashboard.php'));
    exit;
}

header('Location: ' . appUrl('/modules/pegawai/dashboard.php'));
exit;