
<?php

require_once __DIR__ . '/../../core/middleware.php';
requirePegawai();

require_once __DIR__ . '/../../config/database.php';

function h($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/*
|--------------------------------------------------------------------------
| IDENTITAS PEGAWAI
|--------------------------------------------------------------------------
*/

$userId = $_SESSION['user']['id'];

$stmt = $pdo->prepare("
    SELECT id
    FROM pegawai
    WHERE user_id = ?
    LIMIT 1
");

$stmt->execute([$userId]);

$pegawaiId = $stmt->fetchColumn();

if (!$pegawaiId) {
    die('Data pegawai tidak ditemukan. Silakan hubungi administrator.');
}

/*
|--------------------------------------------------------------------------
| TOTAL PENGAJUAN
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM pengajuan_kgb
    WHERE pegawai_id = ?
");

$stmt->execute([$pegawaiId]);

$totalPengajuan = (int) $stmt->fetchColumn();

/*
|--------------------------------------------------------------------------
| REKAP STATUS PENGAJUAN
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        status,
        COUNT(*) AS total
    FROM pengajuan_kgb
    WHERE pegawai_id = ?
    GROUP BY status
");

$stmt->execute([$pegawaiId]);

$statusRows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

$totalDraft = (int) ($statusRows['draft'] ?? 0);
$totalSubmitted = (int) ($statusRows['submitted'] ?? 0);
$totalRevisi = (int) ($statusRows['revisi'] ?? 0);
$totalApproved = (int) ($statusRows['approved'] ?? 0);
$totalRejected = (int) ($statusRows['rejected'] ?? 0);
$totalCompleted = (int) ($statusRows['completed'] ?? 0);

/*
|--------------------------------------------------------------------------
| DATA GRAFIK
|--------------------------------------------------------------------------
*/

$statusLabels = [
    'draft',
    'submitted',
    'revisi',
    'approved',
    'rejected',
    'completed'
];

$statusData = [
    $totalDraft,
    $totalSubmitted,
    $totalRevisi,
    $totalApproved,
    $totalRejected,
    $totalCompleted
];

/*
|--------------------------------------------------------------------------
| RIWAYAT PENGAJUAN TERBARU
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        id,
        periode_kgb,
        status,
        tanggal_pengajuan
    FROM pengajuan_kgb
    WHERE pegawai_id = ?
    ORDER BY tanggal_pengajuan DESC, id DESC
    LIMIT 5
");

$stmt->execute([$pegawaiId]);

$riwayat = $stmt->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| HEADER
|--------------------------------------------------------------------------
*/

include __DIR__ . '/../../templates/header.php';

?>

<style>

.dashboard-card {
    border: none;
    border-radius: 20px;
    transition: 0.3s;
}

.dashboard-card:hover {
    transform: translateY(-5px);
}

.icon-box {
    width: 60px;
    height: 60px;
    border-radius: 15px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    color: white;
}

.chart-card,
.table-card {
    border: none;
    border-radius: 20px;
}

.chart-container {
    position: relative;
    height: 350px;
}

</style>

<div class="container-fluid">

    <!-- HEADER DASHBOARD -->

    <div class="d-flex justify-content-between align-items-center mb-4">

        <div>

            <h2 class="fw-bold mb-1">
                Dashboard Pegawai
            </h2>

            <small class="text-muted">
                Monitoring pengajuan Kenaikan Gaji Berkala
            </small>

        </div>

    </div>

    <!-- RINGKASAN -->

    <div class="row g-4 mb-4">

        <div class="col-md-3">

            <div class="card shadow-sm dashboard-card">

                <div class="card-body d-flex justify-content-between align-items-center">

                    <div>

                        <small class="text-muted">
                            Total Pengajuan
                        </small>

                        <h2 class="fw-bold text-primary mt-2">
                            <?= $totalPengajuan ?>
                        </h2>

                    </div>

                    <div class="icon-box bg-primary">
                        <i class="bi bi-folder-fill"></i>
                    </div>

                </div>

            </div>

        </div>

        <div class="col-md-3">

            <div class="card shadow-sm dashboard-card">

                <div class="card-body d-flex justify-content-between align-items-center">

                    <div>

                        <small class="text-muted">
                            Disetujui
                        </small>

                        <h2 class="fw-bold text-success mt-2">
                            <?= $totalApproved ?>
                        </h2>

                    </div>

                    <div class="icon-box bg-success">
                        <i class="bi bi-check-circle-fill"></i>
                    </div>

                </div>

            </div>

        </div>

        <div class="col-md-3">

            <div class="card shadow-sm dashboard-card">

                <div class="card-body d-flex justify-content-between align-items-center">

                    <div>

                        <small class="text-muted">
                            Revisi
                        </small>

                        <h2 class="fw-bold text-warning mt-2">
                            <?= $totalRevisi ?>
                        </h2>

                    </div>

                    <div class="icon-box bg-warning">
                        <i class="bi bi-pencil-square"></i>
                    </div>

                </div>

            </div>

        </div>

        <div class="col-md-3">

            <div class="card shadow-sm dashboard-card">

                <div class="card-body d-flex justify-content-between align-items-center">

                    <div>

                        <small class="text-muted">
                            Selesai
                        </small>

                        <h2 class="fw-bold text-info mt-2">
                            <?= $totalCompleted ?>
                        </h2>

                    </div>

                    <div class="icon-box bg-info">
                        <i class="bi bi-file-earmark-check-fill"></i>
                    </div>

                </div>

            </div>

        </div>

    </div>

    <!-- GRAFIK -->

    <div class="row g-4 mb-4">

        <div class="col-lg-8">

            <div class="card shadow-sm chart-card">

                <div class="card-header bg-white border-0 pt-4">

                    <h5 class="fw-bold">
                        Grafik Status Pengajuan
                    </h5>

                </div>

                <div class="card-body">

                    <div class="chart-container">

                        <canvas id="barChart"></canvas>

                    </div>

                </div>

            </div>

        </div>

        <div class="col-lg-4">

            <div class="card shadow-sm chart-card">

                <div class="card-header bg-white border-0 pt-4">

                    <h5 class="fw-bold">
                        Persentase Status
                    </h5>

                </div>

                <div class="card-body">

                    <div class="chart-container">

                        <canvas id="pieChart"></canvas>

                    </div>

                </div>

            </div>

        </div>

    </div>

    <!-- DETAIL STATUS -->

    <div class="row g-3 mb-4">

        <div class="col-md-2">

            <div class="card shadow-sm text-center dashboard-card">

                <div class="card-body">

                    <h6>DRAFT</h6>

                    <h2 class="text-secondary">
                        <?= $totalDraft ?>
                    </h2>

                </div>

            </div>

        </div>

        <div class="col-md-2">

            <div class="card shadow-sm text-center dashboard-card">

                <div class="card-body">

                    <h6>SUBMITTED</h6>

                    <h2 class="text-primary">
                        <?= $totalSubmitted ?>
                    </h2>

                </div>

            </div>

        </div>

        <div class="col-md-2">

            <div class="card shadow-sm text-center dashboard-card">

                <div class="card-body">

                    <h6>REVISI</h6>

                    <h2 class="text-warning">
                        <?= $totalRevisi ?>
                    </h2>

                </div>

            </div>

        </div>

        <div class="col-md-2">

            <div class="card shadow-sm text-center dashboard-card">

                <div class="card-body">

                    <h6>APPROVED</h6>

                    <h2 class="text-success">
                        <?= $totalApproved ?>
                    </h2>

                </div>

            </div>

        </div>

        <div class="col-md-2">

            <div class="card shadow-sm text-center dashboard-card">

                <div class="card-body">

                    <h6>REJECTED</h6>

                    <h2 class="text-danger">
                        <?= $totalRejected ?>
                    </h2>

                </div>

            </div>

        </div>

        <div class="col-md-2">

            <div class="card shadow-sm text-center dashboard-card">

                <div class="card-body">

                    <h6>COMPLETED</h6>

                    <h2 class="text-info">
                        <?= $totalCompleted ?>
                    </h2>

                </div>

            </div>

        </div>

    </div>

    <!-- RIWAYAT TERBARU -->

    <div class="card shadow-sm table-card">

        <div class="card-header bg-white border-0 pt-4">

            <h5 class="fw-bold">
                Riwayat Pengajuan Terbaru
            </h5>

        </div>

        <div class="card-body p-0">

            <div class="table-responsive">

                <table class="table table-hover align-middle mb-0">

                    <thead class="table-dark">

                        <tr>

                            <th>#</th>
                            <th>Periode KGB</th>
                            <th>Status</th>
                            <th>Tanggal Pengajuan</th>

                        </tr>

                    </thead>

                    <tbody>

                        <?php if (empty($riwayat)): ?>

                            <tr>

                                <td colspan="4" class="text-center py-4">

                                    Belum ada pengajuan.

                                </td>

                            </tr>

                        <?php else: ?>

                            <?php foreach ($riwayat as $i => $r): ?>

                                <?php

                                $badge = 'secondary';

                                switch ($r['status']) {

                                    case 'submitted':
                                        $badge = 'primary';
                                        break;

                                    case 'approved':
                                        $badge = 'success';
                                        break;

                                    case 'revisi':
                                        $badge = 'warning';
                                        break;

                                    case 'rejected':
                                        $badge = 'danger';
                                        break;

                                    case 'completed':
                                        $badge = 'info';
                                        break;
                                }

                                ?>

                                <tr>

                                    <td><?= $i + 1 ?></td>

                                    <td>
                                        <?= h($r['periode_kgb'] ?: '-') ?>
                                    </td>

                                    <td>

                                        <span class="badge bg-<?= $badge ?>">

                                            <?= strtoupper(h($r['status'])) ?>

                                        </span>

                                    </td>

                                    <td>
                                        <?= h($r['tanggal_pengajuan'] ?: '-') ?>
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

<!-- CHART JS -->

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>

const labels = <?= json_encode(array_map('strtoupper', $statusLabels)) ?>;

const dataStatus = <?= json_encode($statusData) ?>;

const colors = [
    '#6c757d',
    '#0d6efd',
    '#ffc107',
    '#198754',
    '#dc3545',
    '#0dcaf0'
];

/* BAR CHART */

new Chart(document.getElementById('barChart'), {

    type: 'bar',

    data: {

        labels: labels,

        datasets: [{

            label: 'Jumlah Pengajuan',

            data: dataStatus,

            backgroundColor: colors,

            borderRadius: 10

        }]

    },

    options: {

        responsive: true,

        maintainAspectRatio: false,

        plugins: {

            legend: {
                display: false
            }

        },

        scales: {

            y: {

                beginAtZero: true,

                ticks: {
                    precision: 0
                }

            }

        }

    }

});

/* DOUGHNUT CHART */

new Chart(document.getElementById('pieChart'), {

    type: 'doughnut',

    data: {

        labels: labels,

        datasets: [{

            data: dataStatus,

            backgroundColor: colors

        }]

    },

    options: {

        responsive: true,

        maintainAspectRatio: false,

        plugins: {

            legend: {
                position: 'bottom'
            }

        }

    }

});

</script>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
