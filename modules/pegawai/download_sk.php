
<?php

require_once __DIR__ . '/../../core/middleware.php';
requirePegawai();

require_once __DIR__ . '/../../config/database.php';

/*
|--------------------------------------------------------------------------
| 1. AMBIL ID PENGAJUAN
|--------------------------------------------------------------------------
*/

$pengajuanId = (int) ($_GET['pengajuan_id'] ?? 0);

if ($pengajuanId <= 0) {
    http_response_code(400);
    exit('Permintaan tidak valid.');
}

/*
|--------------------------------------------------------------------------
| 2. AMBIL DATA SK DAN PEGAWAI
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        sk.file_sk,
        sk.nomor_sk,
        sk.tanggal_approve,

        pk.id,
        pk.periode_kgb,
        pk.status,

        p.user_id

    FROM sk_kgb sk

    INNER JOIN pengajuan_kgb pk
        ON pk.id = sk.pengajuan_id

    INNER JOIN pegawai p
        ON p.id = pk.pegawai_id

    WHERE pk.id = ?

    LIMIT 1
");

$stmt->execute([$pengajuanId]);

$row = $stmt->fetch(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| 3. VALIDASI DATA SK
|--------------------------------------------------------------------------
*/

if (!$row) {
    http_response_code(404);
    exit('Data SK tidak ditemukan.');
}

/*
|--------------------------------------------------------------------------
| 4. VALIDASI KEPEMILIKAN SK
|--------------------------------------------------------------------------
*/

if (
    (int) $row['user_id'] !==
    (int) $_SESSION['user']['id']
) {
    http_response_code(403);
    exit('Akses ditolak. SK bukan milik Anda.');
}

/*
|--------------------------------------------------------------------------
| 5. VALIDASI STATUS PENGAJUAN
|--------------------------------------------------------------------------
*/

if (
    !in_array(
        $row['status'],
        ['approved', 'completed'],
        true
    )
) {
    http_response_code(403);
    exit('SK belum tersedia.');
}

/*
|--------------------------------------------------------------------------
| 6. VALIDASI FILE SK
|--------------------------------------------------------------------------
*/

if (empty($row['file_sk'])) {
    http_response_code(404);
    exit('File SK belum tersedia.');
}

$relativePath = str_replace(
    '\\',
    '/',
    (string) $row['file_sk']
);

/*
|--------------------------------------------------------------------------
| 7. VALIDASI PATH FILE
|--------------------------------------------------------------------------
*/

if (
    !preg_match(
        '#^uploads/sk_kgb/[a-zA-Z0-9._-]+\.pdf$#i',
        $relativePath
    )
) {
    http_response_code(404);
    exit('Lokasi file SK tidak valid.');
}

/*
|--------------------------------------------------------------------------
| 8. PERIKSA KEBERADAAN FILE
|--------------------------------------------------------------------------
*/

$baseDir = realpath(
    __DIR__ . '/../../uploads/sk_kgb'
);

$fullPath = realpath(
    __DIR__ . '/../../' . $relativePath
);

if (
    !$baseDir ||
    !$fullPath ||
    !is_file($fullPath) ||
    strpos(
        $fullPath,
        $baseDir . DIRECTORY_SEPARATOR
    ) !== 0
) {
    http_response_code(404);
    exit('File SK tidak ditemukan di server.');
}

/*
|--------------------------------------------------------------------------
| 9. BUAT NAMA FILE DOWNLOAD
|--------------------------------------------------------------------------
*/

$safeNoSk = preg_replace(
    '/[^a-zA-Z0-9_-]+/',
    '_',
    (string) (
        $row['nomor_sk'] ?: 'SK_KGB'
    )
);

$safePeriode = preg_replace(
    '/[^0-9-]+/',
    '',
    (string) $row['periode_kgb']
);

$downloadName = trim(
    $safeNoSk . '_' . $safePeriode,
    '_'
) . '.pdf';

/*
|--------------------------------------------------------------------------
| 10. KIRIM FILE PDF KE PEGAWAI
|--------------------------------------------------------------------------
*/

if (headers_sent()) {
    exit(
        'Tidak dapat mengirim file karena terdapat output sebelumnya.'
    );
}

header('Content-Type: application/pdf');

header(
    'Content-Length: ' . filesize($fullPath)
);

header(
    'Content-Disposition: attachment; filename="' .
    $downloadName .
    '"'
);

header('X-Content-Type-Options: nosniff');

readfile($fullPath);

exit;
