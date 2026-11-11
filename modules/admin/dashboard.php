
<?php

// Aktifkan error sementara untuk proses pemeriksaan.
// Setelah dashboard normal, ubah display_errors menjadi 0.
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Memanggil middleware dan memeriksa hak akses admin
require_once __DIR__ . '/../../core/middleware.php';

requireAdmin();

// Memanggil konfigurasi database
require_once __DIR__ . '/../../config/database.php';

/*
|--------------------------------------------------------------------------
| TOTAL DATA
|--------------------------------------------------------------------------
*/

// Total pegawai
$totalPegawai = $pdo->query("
    SELECT COUNT(*)
    FROM pegawai
")->fetchColumn();

// Total pengajuan
$totalPengajuan = $pdo->query("
    SELECT COUNT(*)
    FROM pengajuan_kgb
")->fetchColumn();

// Pengajuan yang menunggu verifikasi
$menungguVerifikasi = $pdo->query("
    SELECT COUNT(*)
    FROM pengajuan_kgb
    WHERE status IN ('submitted', 'calculated')
")->fetchColumn();

/*
|--------------------------------------------------------------------------
| GRAFIK STATUS PENGAJUAN
|--------------------------------------------------------------------------
*/

// Mengambil jumlah pengajuan berdasarkan status
$statusRows = $pdo->query("
    SELECT status, COUNT(*) AS total
    FROM pengajuan_kgb
    GROUP BY status
")->fetchAll(PDO::FETCH_KEY_PAIR);

// Daftar status pengajuan
$statusLabels = [
    'draft',
    'submitted',
    'calculated',
    'revisi',
    'approved',
    'rejected'
];

// Menyiapkan data grafik
$statusData = [];

foreach ($statusLabels as $label) {
    $statusData[] = (int) ($statusRows[$label] ?? 0);
}

// Total berdasarkan status
$totalApproved = (int) ($statusRows['approved'] ?? 0);
$totalRejected = (int) ($statusRows['rejected'] ?? 0);
$totalRevisi = (int) ($statusRows['revisi'] ?? 0);

/*
|--------------------------------------------------------------------------
| HEADER
|--------------------------------------------------------------------------
*/

include __DIR__ . '/../../templates/header.php';

?>

<style>

.dashboard-card {
    border: 0;
    border-radius: 18px;
    overflow: hidden;
    transition: 0.3s;
}

.dashboard-card:hover {
    transform: translateY(-4px);
}

.stat-icon {
    width: 55px;
    height: 55px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    color: white;
}

.chart-card {
    border: 0;
    border-radius: 18px;
}

.chart-container {
    position: relative;
    height: 380px;
}

.small-stat {
    border-radius: 16px;
    border: 0;
}

</style>

<div class="container-fluid">

    <!-- JUDUL DASHBOARD -->

    <div class="d-flex justify-content-between align-items-center mb-4">

        <div>

            <h2 class="fw-bold mb-1">
                Dashboard Admin
            </h2>

            <small class="text-muted">
                Monitoring Sistem KGB
            </small>

        </div>

    </div>


    <!-- CARD STATISTIK -->

    <div class="row g-4 mb-4">

        <!-- TOTAL PEGAWAI -->

        <div class="col-md-3">

            <div class="card shadow-sm dashboard-card">

                <div class="card-body d-flex align-items-center justify-content-between">

                    <div>

                        <small class="text-muted">
                            Total Pegawai
                        </small>

                        <h2 class="fw-bold text-primary mt-2">
                            <?= (int) $totalPegawai ?>
                        </h2>

                    </div>

                    <div class="stat-icon bg-primary">
                        <i class="bi bi-people-fill"></i>
                    </div>

                </div>

            </div>

        </div>


        <!-- TOTAL PENGAJUAN -->

        <div class="col-md-3">

            <div class="card shadow-sm dashboard-card">

                <div class="card-body d-flex align-items-center justify-content-between">

                    <div>

                        <small class="text-muted">
                            Total Pengajuan
                        </small>

                        <h2 class="fw-bold text-success mt-2">
                            <?= (int) $totalPengajuan ?>
                        </h2>

                    </div>

                    <div class="stat-icon bg-success">
                        <i class="bi bi-folder-check"></i>
                    </div>

                </div>

            </div>

        </div>


        <!-- MENUNGGU VERIFIKASI -->

        <div class="col-md-3">

            <div class="card shadow-sm dashboard-card">

                <div class="card-body d-flex align-items-center justify-content-between">

                    <div>

                        <small class="text-muted">
                            Menunggu Verifikasi
                        </small>

                        <h2 class="fw-bold text-warning mt-2">
                            <?= (int) $menungguVerifikasi ?>
                        </h2>

                    </div>

                    <div class="stat-icon bg-warning">
                        <i class="bi bi-clock-history"></i>
                    </div>

                </div>

            </div>

        </div>


        <!-- PENGAJUAN DISETUJUI -->

        <div class="col-md-3">

            <div class="card shadow-sm dashboard-card">

                <div class="card-body d-flex align-items-center justify-content-between">

                    <div>

                        <small class="text-muted">
                            Pengajuan Disetujui
                        </small>

                        <h2 class="fw-bold text-danger mt-2">
                            <?= $totalApproved ?>
                        </h2>

                    </div>

                    <div class="stat-icon bg-danger">
                        <i class="bi bi-check-circle-fill"></i>
                    </div>

                </div>

            </div>

        </div>

    </div>


    <!-- GRAFIK STATUS -->

    <div class="row g-4 mb-4">

        <!-- BAR CHART -->

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


        <!-- DOUGHNUT CHART -->

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


    <!-- DETAIL STATUS PENGAJUAN -->

    <div class="row g-4">

        <!-- DRAFT -->

        <div class="col-md-2">

            <div class="card shadow-sm small-stat text-center">

                <div class="card-body">

                    <h6 class="text-muted">
                        DRAFT
                    </h6>

                    <h2 class="fw-bold text-secondary">
                        <?= (int) ($statusRows['draft'] ?? 0) ?>
                    </h2>

                </div>

            </div>

        </div>


        <!-- SUBMITTED -->

        <div class="col-md-2">

            <div class="card shadow-sm small-stat text-center">

                <div class="card-body">

                    <h6 class="text-muted">
                        SUBMITTED
                    </h6>

                    <h2 class="fw-bold text-primary">
                        <?= (int) ($statusRows['submitted'] ?? 0) ?>
                    </h2>

                </div>

            </div>

        </div>


        <!-- CALCULATED -->

        <div class="col-md-2">

            <div class="card shadow-sm small-stat text-center">

                <div class="card-body">

                    <h6 class="text-muted">
                        CALCULATED
                    </h6>

                    <h2 class="fw-bold text-info">
                        <?= (int) ($statusRows['calculated'] ?? 0) ?>
                    </h2>

                </div>

            </div>

        </div>


        <!-- REVISI -->

        <div class="col-md-2">

            <div class="card shadow-sm small-stat text-center">

                <div class="card-body">

                    <h6 class="text-muted">
                        REVISI
                    </h6>

                    <h2 class="fw-bold text-warning">
                        <?= $totalRevisi ?>
                    </h2>

                </div>

            </div>

        </div>


        <!-- APPROVED -->

        <div class="col-md-2">

            <div class="card shadow-sm small-stat text-center">

                <div class="card-body">

                    <h6 class="text-muted">
                        APPROVED
                    </h6>

                    <h2 class="fw-bold text-success">
                        <?= $totalApproved ?>
                    </h2>

                </div>

            </div>

        </div>


        <!-- REJECTED -->

        <div class="col-md-2">

            <div class="card shadow-sm small-stat text-center">

                <div class="card-body">

                    <h6 class="text-muted">
                        REJECTED
                    </h6>

                    <h2 class="fw-bold text-danger">
                        <?= $totalRejected ?>
                    </h2>

                </div>

            </div>

        </div>

    </div>

</div>


<!-- CHART.JS -->

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>

// Label status pengajuan
const labels = <?= json_encode(array_map('strtoupper', $statusLabels)) ?>;

// Data jumlah pengajuan
const dataStatus = <?= json_encode($statusData) ?>;

// Warna grafik
const colors = [
    '#6c757d',
    '#0d6efd',
    '#0dcaf0',
    '#ffc107',
    '#198754',
    '#dc3545'
];


/*
|--------------------------------------------------------------------------
| BAR CHART
|--------------------------------------------------------------------------
*/

new Chart(document.getElementById('barChart'), {

    type: 'bar',

    data: {

        labels: labels,

        datasets: [{

            label: 'Jumlah Pengajuan',

            data: dataStatus,

            backgroundColor: colors,

            borderRadius: 12

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


/*
|--------------------------------------------------------------------------
| DOUGHNUT CHART
|--------------------------------------------------------------------------
*/

new Chart(document.getElementById('pieChart'), {

    type: 'doughnut',

    data: {

        labels: labels,

        datasets: [{

            data: dataStatus,

            backgroundColor: colors,

            borderWidth: 2

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


<?php

// Memanggil footer
include __DIR__ . '/../../templates/footer.php';

?>
