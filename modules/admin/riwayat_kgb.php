
<?php

require_once __DIR__ . '/../../core/middleware.php';
requireAdmin();

require_once __DIR__ . '/../../config/database.php';

function h($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/*
|--------------------------------------------------------------------------
| FILTER
|--------------------------------------------------------------------------
*/

$year = trim($_GET['year'] ?? '');
$keyword = trim($_GET['keyword'] ?? '');

$where = [
    "pk.status IN ('approved', 'completed')"
];

$params = [];

if ($year !== '' && preg_match('/^\d{4}$/', $year)) {
    $where[] = 'YEAR(pk.periode_kgb) = ?';
    $params[] = (int) $year;
} else {
    $year = '';
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

$whereSql = 'WHERE ' . implode(' AND ', $where);

/*
|--------------------------------------------------------------------------
| AMBIL DATA RIWAYAT FINAL
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        pk.id,
        pk.periode_kgb,
        pk.status,
        pk.tanggal_pengajuan,

        p.nip,
        p.jabatan,
        p.golongan,
        p.unit_kerja,

        u.nama AS pegawai_nama,
        u.email AS pegawai_email,

        sk.nomor_sk,
        sk.file_sk,
        sk.tanggal_approve

    FROM pengajuan_kgb pk

    INNER JOIN pegawai p
        ON p.id = pk.pegawai_id

    INNER JOIN users u
        ON u.id = p.user_id

    LEFT JOIN sk_kgb sk
        ON sk.pengajuan_id = pk.id

    {$whereSql}

    ORDER BY pk.periode_kgb DESC, pk.id DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| DAFTAR TAHUN
|--------------------------------------------------------------------------
*/

$yearRows = $pdo->query("
    SELECT DISTINCT YEAR(periode_kgb) AS tahun
    FROM pengajuan_kgb
    WHERE status IN ('approved', 'completed')
      AND periode_kgb IS NOT NULL
    ORDER BY tahun DESC
")->fetchAll(PDO::FETCH_COLUMN);

/*
|--------------------------------------------------------------------------
| REKAP PER TAHUN
|--------------------------------------------------------------------------
*/

$rekapPerTahun = [];

foreach ($rows as $item) {

    $yr = !empty($item['periode_kgb'])
        ? date('Y', strtotime($item['periode_kgb']))
        : '-';

    if (!isset($rekapPerTahun[$yr])) {
        $rekapPerTahun[$yr] = [
            'total' => 0,
            'with_sk' => 0
        ];
    }

    $rekapPerTahun[$yr]['total']++;

    if (!empty($item['file_sk'])) {
        $rekapPerTahun[$yr]['with_sk']++;
    }
}

krsort($rekapPerTahun);

/*
|--------------------------------------------------------------------------
| TOTAL SK
|--------------------------------------------------------------------------
*/

$skCount = 0;

foreach ($rows as $row) {
    if (!empty($row['file_sk'])) {
        $skCount++;
    }
}

include __DIR__ . '/../../templates/header.php';

?>

<div class="d-flex justify-content-between align-items-center mb-3">

    <h3 class="mb-0">Riwayat KGB Final</h3>

</div>

<!-- FILTER -->

<div class="card shadow-sm mb-3">

    <div class="card-body">

        <form method="GET" class="row g-2 align-items-end">

            <div class="col-md-3">

                <label class="form-label">Tahun</label>

                <select name="year" class="form-select">

                    <option value="">Semua Tahun</option>

                    <?php foreach ($yearRows as $yr): ?>

                        <option
                            value="<?= h($yr) ?>"
                            <?= (string) $year === (string) $yr ? 'selected' : '' ?>
                        >
                            <?= h($yr) ?>
                        </option>

                    <?php endforeach; ?>

                </select>

            </div>

            <div class="col-md-5">

                <label class="form-label">Kata Kunci</label>

                <input
                    type="text"
                    name="keyword"
                    class="form-control"
                    value="<?= h($keyword) ?>"
                    placeholder="Nama, email, NIP, unit kerja"
                >

            </div>

            <div class="col-md-4 d-flex gap-2">

                <button type="submit" class="btn btn-primary w-100">
                    Filter
                </button>

                <a
                    href="riwayat_kgb.php"
                    class="btn btn-outline-secondary"
                >
                    Reset
                </a>

            </div>

        </form>

    </div>

</div>

<!-- RINGKASAN -->

<div class="row mb-3">

    <div class="col-md-4">

        <div class="card shadow-sm">

            <div class="card-body">

                <small>Total Riwayat</small>

                <h4><?= count($rows) ?></h4>

            </div>

        </div>

    </div>

    <div class="col-md-4">

        <div class="card shadow-sm">

            <div class="card-body">

                <small>SK Sudah Terbit</small>

                <h4><?= $skCount ?></h4>

            </div>

        </div>

    </div>

    <div class="col-md-4">

        <div class="card shadow-sm">

            <div class="card-body">

                <small>Tahun Aktif</small>

                <h4><?= count($rekapPerTahun) ?></h4>

            </div>

        </div>

    </div>

</div>

<!-- REKAP PER TAHUN -->

<div class="card shadow-sm mb-3">

    <div class="card-header">

        <strong>Ringkasan Final Per Tahun</strong>

    </div>

    <div class="card-body">

        <?php if (empty($rekapPerTahun)): ?>

            <span class="text-muted">
                Belum ada data riwayat final.
            </span>

        <?php else: ?>

            <div class="d-flex flex-wrap gap-2">

                <?php foreach ($rekapPerTahun as $yr => $data): ?>

                    <span class="badge bg-dark">

                        <?= h($yr) ?>:
                        <?= (int) $data['total'] ?> pengajuan
                        (SK: <?= (int) $data['with_sk'] ?>)

                    </span>

                <?php endforeach; ?>

            </div>

        <?php endif; ?>

    </div>

</div>

<!-- TABEL RIWAYAT FINAL -->

<div class="card shadow-sm">

    <div class="card-header">

        <strong>Daftar Riwayat Final</strong>

    </div>

    <div class="card-body p-0">

        <div class="table-responsive">

            <table class="table table-striped table-bordered mb-0">

                <thead class="table-dark">

                    <tr>
                        <th>#</th>
                        <th>Pegawai</th>
                        <th>Detail</th>
                        <th>Periode KGB</th>
                        <th>Status</th>
                        <th>Tanggal Pengajuan</th>
                        <th>SK</th>
                    </tr>

                </thead>

                <tbody>

                    <?php if (empty($rows)): ?>

                        <tr>

                            <td colspan="7" class="text-center py-3">

                                Tidak ada data riwayat final.

                            </td>

                        </tr>

                    <?php else: ?>

                        <?php foreach ($rows as $index => $row): ?>

                            <tr>

                                <td><?= $index + 1 ?></td>

                                <td>

                                    <?= h($row['pegawai_nama']) ?>

                                    <br>

                                    <small>
                                        <?= h($row['pegawai_email']) ?>
                                    </small>

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

                                    <?= h($row['periode_kgb'] ?: '-') ?>

                                </td>

                                <td>

                                    <?php if ($row['status'] === 'completed'): ?>

                                        <span class="badge bg-success">
                                            COMPLETED
                                        </span>

                                    <?php else: ?>

                                        <span class="badge bg-primary">
                                            APPROVED
                                        </span>

                                    <?php endif; ?>

                                </td>

                                <td>

                                    <?= h($row['tanggal_pengajuan'] ?: '-') ?>

                                </td>

                                <td>

                                    <?php if (!empty($row['file_sk'])): ?>

                                        <strong>
                                            No: <?= h($row['nomor_sk'] ?: '-') ?>
                                        </strong>

                                        <br>

                                        Tgl: <?= h($row['tanggal_approve'] ?: '-') ?>

                                        <br>

                                        <a
                                            href="<?= h(appUrl('/' . ltrim($row['file_sk'], '/'))) ?>"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                        >
                                            Lihat SK
                                        </a>

                                    <?php else: ?>

                                        <span class="text-muted">
                                            Belum ada SK
                                        </span>

                                    <?php endif; ?>

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
