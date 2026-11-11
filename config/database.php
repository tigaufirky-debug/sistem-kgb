<?php

$dbHost = getenv('DB_HOST') ?: 'localhost';
$dbName = getenv('DB_NAME') ?: 'kgbminse_db';
$dbUser = getenv('DB_USER') ?: 'kgbminse_admin';
$dbPass = getenv('DB_PASS') ?: ')5qAa{m96Km?9+7z';
$dbCharset = getenv('DB_CHARSET') ?: 'utf8mb4';

// ========================================
// 2. KONFIGURASI DSN
// ========================================

$dsn = "mysql:host={$dbHost};dbname={$dbName};charset={$dbCharset}";


// ========================================
// 3. PENGATURAN PDO
// ========================================

$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];


// ========================================
// 4. PROSES KONEKSI DATABASE
// ========================================

try {

    $pdo = new PDO(
        $dsn,
        $dbUser,
        $dbPass,
        $options
    );

} catch (PDOException $e) {

    error_log(
        'Koneksi Database KGB Gagal: ' . $e->getMessage()
    );

    http_response_code(500);

    exit('Koneksi database gagal. Periksa konfigurasi database.');

}

?>