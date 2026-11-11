
<?php

require_once __DIR__ . '/../../core/middleware.php';
requirePegawai();

require_once __DIR__ . '/../../config/database.php';

function h($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$userId = $_SESSION['user']['id'];

/*
|--------------------------------------------------------------------------
| DATA PEGAWAI
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT id
    FROM pegawai
    WHERE user_id = ?
");

$stmt->execute([$userId]);

$pegawaiId = $stmt->fetchColumn();

if (!$pegawaiId) {
    die('Data pegawai tidak ditemukan.');
}

/*
|--------------------------------------------------------------------------
| RIWAYAT KGB FINAL
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        pk.id,
        pk.periode_kgb,
        pk.status,
        pk.tanggal_pengajuan,
        pk.file_berkas,
        pk.catatan_admin,

        sk.nomor_sk,
        sk.file_sk,
        sk.tanggal_approve

    FROM pengajuan_kgb pk

    LEFT JOIN sk_kgb sk
        ON sk.pengajuan_id = pk.id

    WHERE pk.pegawai_id = ?
    AND pk.status IN ('approved', 'completed')

    ORDER BY pk.periode_kgb DESC, pk.id DESC
");

$stmt->execute([$pegawaiId]);

$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| STATISTIK
|--------------------------------------------------------------------------
*/

$totalRiwayat = count($rows);

$totalSk = 0;

foreach ($rows as $row) {

    if (!empty($row['file_sk'])) {
        $totalSk++;
    }
}

include __DIR__ . '/../../templates/header.php';

?>

<style>

.card-modern {
    border: 0;
    border-radius: 18px;
}

.stat-card {
    border: 0;
    border-radius: 18px;
    transition: .3s;
}

.stat-card:hover {
    transform: translateY(-4px);
}

.icon-box {
    width: 60px;
    height: 60px;
    border-radius: 15px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 22px;
}

.table-modern thead {
    background: #212529;
    color: white;
}

</style>

<div class="container-fluid">

    <!-- JUDUL -->

    <div class="d-flex justify-content-between align-items-center mb-4">

        <div>

            <h2 class="fw-bold mb-1">
                Riwayat KGB Final
            </h2>

            <small class="text-muted">
                Riwayat pengajuan KGB yang telah disetujui atau diselesaikan.
            </small>

        </div>

    </div>

    <!-- STATISTIK -->

    <div class="row g-4 mb-4">

        <div class="col-md-6">

            <div class="card shadow-sm stat-card">

                <div class="card-body d-flex justify-content-between align-items-center">

                    <div>

                        <small class="text-muted">
                            Total Riwayat
                        </small>

                        <h2 class="fw-bold text-primary mt-2">
                            <?= $totalRiwayat ?>
                        </h2>

                    </div>

                    <div class="icon-box bg-primary">
                        <i class="bi bi-folder-fill"></i>
                    </div>

                </div>

            </div>

        </div>

        <div class="col-md-6">

            <div class="card shadow-sm stat-card">

                <div class="card-body d-flex justify-content-between align-items-center">

                    <div>

                        <small class="text-muted">
                            SK Sudah Terbit
                        </small>

                        <h2 class="fw-bold text-success mt-2">
                            <?= $totalSk ?>
                        </h2>

                    </div>

                    <div class="icon-box bg-success">
                        <i class="bi bi-file-earmark-pdf-fill"></i>
                    </div>

                </div>

            </div>

        </div>

    </div>

    <!-- TABEL RIWAYAT -->

    <div class="card shadow-sm card-modern">

        <div class="card-header bg-white border-0 pt-4">

            <h5 class="fw-bold">
                Daftar Riwayat Final
            </h5>

        </div>

        <div class="card-body p-0">

            <div class="table-responsive">

                <table class="table table-striped table-hover table-modern align-middle mb-0">

                    <thead>

                        <tr>
                            <th>#</th>
                            <th>Periode KGB</th>
                            <th>Status</th>
                            <th>SK KGB</th>
                            <th>Tanggal Pengajuan</th>
                        </tr>

                    </thead>

                    <tbody>

                        <?php if (!$rows): ?>

                            <tr>

                                <td colspan="5" class="text-center py-4">

                                    Belum ada riwayat KGB final.

                                </td>

                            </tr>

                        <?php else: ?>

                            <?php foreach ($rows as $i => $row): ?>

                                <tr>

                                    <td>
                                        <?= $i + 1 ?>
                                    </td>

                                    <td>

                                        <strong>
                                            <?= h($row['periode_kgb']) ?>
                                        </strong>

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

                                        <?php if (!empty($row['file_sk'])): ?>

                                            <div class="mb-2">

                                                <strong>
                                                    <?= h($row['nomor_sk'] ?: '-') ?>
                                                </strong>

                                            </div>

                                            <small class="text-muted d-block mb-2">

                                                Tanggal SK:
                                                <?= h($row['tanggal_approve'] ?: '-') ?>

                                            </small>

                                            <a
                                                href="download_sk.php?pengajuan_id=<?= (int) $row['id'] ?>"
                                                class="btn btn-success btn-sm"
                                            >
                                                <i class="bi bi-download"></i>
                                                Download SK
                                            </a>

                                        <?php else: ?>

                                            <span class="text-muted">
                                                SK belum tersedia
                                            </span>

                                        <?php endif; ?>

                                    </td>

                                    <td>
                                        <?= h($row['tanggal_pengajuan']) ?>
                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </div>

    </div>

</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
