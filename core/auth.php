
<?php

// ========================================
// 1. SESSION
// ========================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


// ========================================
// 2. BASE PATH
// ========================================

function basePath()
{
    return '';
}


// ========================================
// 3. URL APLIKASI
// ========================================

function appUrl($path = '')
{
    return '/' . ltrim($path, '/');
}


// ========================================
// 4. CEK LOGIN
// ========================================

function isLogin()
{
    return isset($_SESSION['user'])
        && is_array($_SESSION['user']);
}


// ========================================
// 5. DATA USER
// ========================================

function user()
{
    return $_SESSION['user'] ?? null;
}


// ========================================
// 6. WAJIB LOGIN
// ========================================

function requireLogin()
{
    if (!isLogin()) {
        header('Location: ' . appUrl('/modules/auth/login.php'));
        exit;
    }
}

?>
