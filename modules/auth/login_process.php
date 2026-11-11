<?php
require_once '../../config/database.php';
require_once '../../core/auth.php';

$email = trim($_POST['email'] ?? '');
$password = trim($_POST['password'] ?? '');

$stmt = $pdo->prepare("SELECT * FROM users WHERE LOWER(email) = LOWER(?) AND is_active = 1");
$stmt->execute([$email]);
$user = $stmt->fetch();

if ($user && password_verify($password, $user['password'])) {

    $_SESSION['user'] = [
        'id' => $user['id'],
        'nama' => $user['nama'],
        'email' => $user['email'],
        'role' => $user['role']
    ];

    // redirect berdasarkan role
    if ($user['role'] === 'admin') {
        header("Location: ../admin/dashboard.php");
    } else {
        header("Location: ../pegawai/dashboard.php");
    }
    exit;

} else {
    header("Location: login.php?error=1");
    exit;
}
