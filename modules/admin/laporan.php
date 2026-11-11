
<?php

require_once __DIR__ . '/../../core/middleware.php';
requireAdmin();

require_once __DIR__ . '/../../config/database.php';

function h($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/* =====================================
   FILTER STATUS DAN TANGGAL
===================================== */

$allowedStatuses = [
    'draft',
    'submitted',
    'revisi',
    'approved',
    'rejected',
    'completed'
];

$status = trim($_GET['status'] ?? '');
$dateFrom = trim($_GET['date_from'] ?? '');
$dateTo = trim($_GET['date_to'] ?? '');
$keyword = trim($_GET['keyword'] ?? '');
$export = trim($_GET['export'] ?? '');

if (!in_array($status, $allowedStatuses, true)) {
    $status = '';
}

$where = [];
$params = [];

if ($status !== '') {
    $where[] = 'pk.status = ?';
    $params[] = $status;
}

if ($dateFrom !== '') {
    $where[] = 'pk.tanggal_pengajuan >= ?';
    $params[] = $dateFrom;
}

if ($dateTo !== '') {
    $where[] = 'pk.tanggal_pengajuan < DATE_ADD(?, INTERVAL 1 DAY)';
    $params[] = $dateTo;
}

if ($keyword !== '') {

    $where[] = "
        (
            u.nama LIKE ?
            OR u.email LIKE ?
            OR p.nip LIKE ?
            OR p.unit_kerja LIKE ?
        )
    ";

    $like = '%' . $keyword . '%';

    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

$whereSql = $where
    ? 'WHERE ' . implode(' AND ', $where)
    : '';

/* =====================================
   QUERY DASAR
===================================== */

$baseSql = "
    FROM pengajuan_kgb pk

    INNER JOIN pegawai p
        ON p.id = pk.pegawai_id

    INNER JOIN users u
        ON u.id = p.user_id

    LEFT JOIN sk_kgb sk
        ON sk.pengajuan_id = pk.id
";

/* =====================================
   QUERY DATA LAPORAN
===================================== */

$selectSql = "
    SELECT
        pk.id,
        pk.periode_kgb,
        pk.status,
        pk.tanggal_pengajuan,
        pk.catatan_admin,

        p.nip,
        p.jabatan,
        p.golongan,
        p.unit_kerja,

        u.nama AS pegawai_nama,
        u.email AS pegawai_email,

        sk.nomor_sk,
        sk.file_sk,
        sk.tanggal_approve

    {$baseSql}

    {$whereSql}

    ORDER BY pk.tanggal_pengajuan DESC, pk.id DESC
";

/* =====================================
   EXPORT CSV
===================================== */

if ($export === 'csv') {

    $stmt = $pdo->prepare($selectSql);
    $stmt->execute($params);

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $filename = 'laporan_pengajuan_kgb_' . date('Ymd_His') . '.csv';

    header('Content-Type: text/csv; charset=UTF-8');

    header(
        'Content-Disposition: attachment; filename="' .
        $filename . '"'
    );

    $out = fopen('php://output', 'w');

    // BOM agar karakter CSV lebih mudah dibaca Excel
    fwrite($out, "\xEF\xBB\xBF");

    fputcsv($out, [
        'ID Pengajuan',
        'Nama Pegawai',
        'Email',
        'NIP',
        'Unit Kerja',
        'Jabatan',
        'Golongan',
        'Periode KGB',
        'Status',
        'Tanggal Pengajuan',
        'Nomor SK',
        'Tanggal Approve SK',
        'Catatan Admin'
    ]);

    foreach ($rows as $row) {

        fputcsv($out, [
            $row['id'],
            $row['pegawai_nama'],
            $row['pegawai_email'],
            $row['nip'],
            $row['unit_kerja'],
            $row['jabatan'],
            $row['golongan'],
            $row['periode_kgb'],
            $row['status'],
            $row['tanggal_pengajuan'],
            $row['nomor_sk'],
            $row['tanggal_approve'],
            $row['catatan_admin']
        ]);
    }

    fclose($out);
    exit;
}

/* =====================================
   AMBIL DATA LAPORAN
===================================== */

$stmt = $pdo->prepare($selectSql);
$stmt->execute($params);

$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* =====================================
   REKAP STATUS
===================================== */

$rekapSql = "
    SELECT
        pk.status,
        COUNT(*) AS total

    {$baseSql}

    {$whereSql}

    GROUP BY pk.status
";

$stmt = $pdo->prepare($rekapSql);
$stmt->execute($params);

$rekap = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

/* =====================================
   TOTAL DATA DAN SK
===================================== */

$totalData = count($rows);

$totalSk = 0;

foreach ($rows as $row) {

    if (!empty($row['file_sk'])) {
        $totalSk++;
    }
}

include __DIR__ . '/../../templates/header.php';

?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0">Laporan Pengajuan KGB</h3>
</div>

<!-- FILTER LAPORAN -->

<div class="card shadow-sm mb-3">

    <div class="card-body">

        <form method="GET" class="row g-2 align-items-end">

            <div class="col-md-2">

                <label class="form-label">Status</label>

                <select name="status" class="form-select">

                    <option value="">Semua</option>

                    <?php foreach ($allowedStatuses as $s): ?>

                        <option
                            value="<?= h($s) ?>"
                            <?= $status === $s ? 'selected' : '' ?>
                        >
                            <?= strtoupper(h($s)) ?>
                        </option>

                    <?php endforeach; ?>

                </select>

            </div>

            <div class="col-md-2">

                <label class="form-label">Tanggal Dari</label>

                <input
                    type="date"
                    name="date_from"
                    class="form-control"
                    value="<?= h($dateFrom) ?>"
                >

            </div>

            <div class="col-md-2">

                <label class="form-label">Tanggal Sampai</label>

                <input
                    type="date"
                    name="date_to"
                    class="form-control"
                    value="<?= h($dateTo) ?>"
                >

            </div>

            <div class="col-md-4">

                <label class="form-label">Kata Kunci</label>

                <input
                    type="text"
                    name="keyword"
                    class="form-control"
                    value="<?= h($keyword) ?>"
                    placeholder="Nama, email, NIP, unit kerja"
                >

            </div>

            <div class="col-md-2 d-flex gap-2">

                <button class="btn btn-primary w-100">
                    Filter
                </button>

                <a
                    href="laporan.php"
                    class="btn btn-outline-secondary"
                >
                    Reset
                </a>

            </div>

            <div class="col-md-12 text-end">

                <?php
                $query = $_GET;
                $query['export'] = 'csv';
                ?>

                <a
                    href="laporan.php?<?= h(http_build_query($query)) ?>"
                    class="btn btn-success btn-sm"
                >
                    Export CSV
                </a>

            </div>

        </form>

    </div>

</div>

<!-- RINGKASAN LAPORAN -->

<div class="row mb-3">

    <div class="col-md-3">

        <div class="card shadow-sm">
            <div class="card-body">
                <small>Total Data</small>
                <h4><?= $totalData ?></h4>
            </div>
        </div>

    </div>

    <div class="col-md-3">

        <div class="card shadow-sm">
            <div class="card-body">
                <small>Approved</small>
                <h4><?= (int) ($rekap['approved'] ?? 0) ?></h4>
            </div>
        </div>

    </div>

    <div class="col-md-3">

        <div class="card shadow-sm">
            <div class="card-body">
                <small>Revisi</small>
                <h4><?= (int) ($rekap['revisi'] ?? 0) ?></h4>
            </div>
        </div>

    </div>

    <div class="col-md-3">

        <div class="card shadow-sm">
            <div class="card-body">
                <small>Sudah Ada SK</small>
                <h4><?= $totalSk ?></h4>
            </div>
        </div>

    </div>

</div>

<!-- REKAP STATUS -->

<div class="mb-2 d-flex flex-wrap gap-2">

    <span class="badge bg-secondary">
        DRAFT: <?= (int) ($rekap['draft'] ?? 0) ?>
    </span>

    <span class="badge bg-primary">
        SUBMITTED: <?= (int) ($rekap['submitted'] ?? 0) ?>
    </span>

    <span class="badge bg-warning text-dark">
        REVISI: <?= (int) ($rekap['revisi'] ?? 0) ?>
    </span>

    <span class="badge bg-success">
        APPROVED: <?= (int) ($rekap['approved'] ?? 0) ?>
    </span>

    <span class="badge bg-danger">
        REJECTED: <?= (int) ($rekap['rejected'] ?? 0) ?>
    </span>

    <span class="badge bg-info">
        COMPLETED: <?= (int) ($rekap['completed'] ?? 0) ?>
    </span>

</div>

<!-- TABEL LAPORAN -->

<div class="card shadow-sm">

    <div class="card-body p-0">

        <div class="table-responsive">

            <table class="table table-striped table-bordered mb-0">

                <thead class="table-dark">

                    <tr>
                        <th>#</th>
                        <th>Pegawai</th>
                        <th>Detail</th>
                        <th>Periode</th>
                        <th>Status</th>
                        <th>Tanggal</th>
                        <th>SK</th>
                        <th>Catatan</th>
                    </tr>

                </thead>

                <tbody>

                <?php if (!$rows): ?>

                    <tr>
                        <td colspan="8" class="text-center py-3">
                            Tidak ada data laporan.
                        </td>
                    </tr>

                <?php else: ?>

                    <?php foreach ($rows as $index => $row): ?>

                        <tr>

                            <td><?= $index + 1 ?></td>

                            <td>
                                <?= h($row['pegawai_nama']) ?>
                                <br>
                                <small><?= h($row['pegawai_email']) ?></small>
                            </td>

                            <td>
                                NIP: <?= h($row['nip']) ?>
                                <br>
                                Unit: <?= h($row['unit_kerja'] ?: '-') ?>
                                <br>
                                Jabatan: <?= h($row['jabatan'] ?: '-') ?>
                                <br>
                                Golongan: <?= h($row['golongan'] ?: '-') ?>
                            </td>

                            <td>
                                <?= h($row['periode_kgb']) ?>
                            </td>

                            <td>
                                <?= strtoupper(h($row['status'])) ?>
                            </td>

                            <td>
                                <?= h($row['tanggal_pengajuan'] ?: '-') ?>
                            </td>

                            <td>

                                <?php if (!empty($row['file_sk'])): ?>

                                    No:
                                    <?= h($row['nomor_sk'] ?: '-') ?>

                                    <br>

                                    Tgl:
                                    <?= h($row['tanggal_approve'] ?: '-') ?>

                                    <br>

                                    <a
                                        href="<?= h(appUrl('/' . $row['file_sk'])) ?>"
                                        target="_blank"
                                    >
                                        Lihat SK
                                    </a>

                                <?php else: ?>

                                    -

                                <?php endif; ?>

                            </td>

                            <td>
                                <?= nl2br(h($row['catatan_admin'] ?: '-')) ?>
                            </td>

                        </tr>

                    <?php endforeach; ?>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>

</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
