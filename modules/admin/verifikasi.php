
<?php

// Aktifkan error sementara untuk pemeriksaan
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Middleware dan hak akses admin
require_once __DIR__ . '/../../core/middleware.php';
requireAdmin();

// Koneksi database
require_once __DIR__ . '/../../config/database.php';

// Fungsi keamanan output
function h($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

// Fungsi badge status
function statusBadge($status)
{
    $map = [
        'draft'      => 'secondary',
        'submitted'  => 'primary',
        'calculated' => 'info',
        'revisi'     => 'warning',
        'approved'   => 'success',
        'rejected'   => 'danger'
    ];

    $class = $map[$status] ?? 'secondary';

    return '<span class="badge bg-' . $class . '">' .
        strtoupper(h($status)) .
        '</span>';
}

$errors = [];

$success = $_SESSION['verifikasi_flash_success'] ?? null;
unset($_SESSION['verifikasi_flash_success']);


/*
|--------------------------------------------------------------------------
| PROSES VERIFIKASI
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $id = (int) ($_POST['id'] ?? 0);

    $newStatus = trim($_POST['new_status'] ?? '');

    $catatanAdmin = trim($_POST['catatan_admin'] ?? '');

    $allowed = [
        'approved',
        'revisi',
        'rejected'
    ];

    // Validasi ID
    if ($id <= 0) {
        $errors[] = 'ID pengajuan tidak valid.';
    }

    // Validasi status
    if (!in_array($newStatus, $allowed, true)) {
        $errors[] = 'Status verifikasi tidak valid.';
    }

    // Catatan wajib untuk revisi dan reject
    if (
        in_array($newStatus, ['revisi', 'rejected'], true)
        && $catatanAdmin === ''
    ) {
        $errors[] = 'Catatan admin wajib diisi untuk revisi atau reject.';
    }

    // Periksa pengajuan
    if (!$errors) {

        $stmt = $pdo->prepare("
            SELECT id, status
            FROM pengajuan_kgb
            WHERE id = ?
        ");

        $stmt->execute([$id]);

        $pengajuan = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$pengajuan) {

            $errors[] = 'Pengajuan tidak ditemukan.';

        } elseif (
            !in_array(
                $pengajuan['status'],
                ['submitted', 'calculated'],
                true
            )
        ) {

            $errors[] = 'Pengajuan ini sudah diverifikasi atau tidak dapat diproses.';
        }
    }

    // Simpan perubahan status
    if (!$errors) {

        $stmt = $pdo->prepare("
            UPDATE pengajuan_kgb
            SET
                status = ?,
                catatan_admin = ?
            WHERE id = ?
        ");

        $stmt->execute([
            $newStatus,
            $catatanAdmin !== '' ? $catatanAdmin : null,
            $id
        ]);

        $_SESSION['verifikasi_flash_success'] =
            'Pengajuan berhasil diverifikasi.';

        header('Location: verifikasi.php');
        exit;
    }
}


/*
|--------------------------------------------------------------------------
| AMBIL DATA PENGAJUAN
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query("
    SELECT
        pk.id,
        pk.pegawai_id,
        pk.periode_kgb,
        pk.file_berkas,
        pk.status,
        pk.catatan_admin,
        pk.tanggal_pengajuan,

        p.nip,
        p.jabatan,
        p.golongan,

        u.nama AS pegawai_nama

    FROM pengajuan_kgb pk

    INNER JOIN pegawai p
        ON p.id = pk.pegawai_id

    INNER JOIN users u
        ON u.id = p.user_id

    ORDER BY
        pk.tanggal_pengajuan DESC,
        pk.id DESC
");

$list = $stmt->fetchAll(PDO::FETCH_ASSOC);


// Memanggil header
include __DIR__ . '/../../templates/header.php';

?>

<div class="container-fluid">

    <div class="d-flex justify-content-between align-items-center mb-4">

        <div>

            <h3 class="fw-bold mb-1">
                Verifikasi Pengajuan KGB
            </h3>

            <small class="text-muted">
                Pengelolaan dan pemeriksaan berkas pengajuan pegawai
            </small>

        </div>

    </div>


    <!-- PESAN BERHASIL -->

    <?php if ($success): ?>

        <div class="alert alert-success alert-dismissible fade show">

            <?= h($success) ?>

            <button
                type="button"
                class="btn-close"
                data-bs-dismiss="alert"
            ></button>

        </div>

    <?php endif; ?>


    <!-- PESAN ERROR -->

    <?php if ($errors): ?>

        <div class="alert alert-danger">

            <strong>Terjadi Kesalahan:</strong>

            <ul class="mb-0 mt-2">

                <?php foreach ($errors as $error): ?>

                    <li><?= h($error) ?></li>

                <?php endforeach; ?>

            </ul>

        </div>

    <?php endif; ?>


    <!-- TABEL PENGAJUAN -->

    <div class="card shadow-sm border-0">

        <div class="card-header bg-white py-3">

            <strong>
                Daftar Pengajuan Pegawai
            </strong>

        </div>

        <div class="card-body p-0">

            <div class="table-responsive">

                <table class="table table-bordered table-striped table-hover mb-0">

                    <thead class="table-dark">

                        <tr>

                            <th>No.</th>
                            <th>Nama Pegawai</th>
                            <th>NIP</th>
                            <th>Jabatan</th>
                            <th>Golongan</th>
                            <th>Periode KGB</th>
                            <th>Tanggal Pengajuan</th>
                            <th>Status</th>
                            <th>Berkas PDF</th>
                            <th>Catatan Admin</th>
                            <th>Aksi</th>

                        </tr>

                    </thead>

                    <tbody>

                        <?php if (empty($list)): ?>

                            <tr>

                                <td colspan="11" class="text-center py-4">

                                    Belum ada data pengajuan.

                                </td>

                            </tr>

                        <?php else: ?>

                            <?php foreach ($list as $i => $row): ?>

                                <tr>

                                    <td>
                                        <?= $i + 1 ?>
                                    </td>

                                    <td>
                                        <?= h($row['pegawai_nama']) ?>
                                    </td>

                                    <td>
                                        <?= h($row['nip']) ?>
                                    </td>

                                    <td>
                                        <?= h($row['jabatan']) ?>
                                    </td>

                                    <td>
                                        <?= h($row['golongan']) ?>
                                    </td>

                                    <td>
                                        <?= h($row['periode_kgb']) ?>
                                    </td>

                                    <td>
                                        <?= h($row['tanggal_pengajuan']) ?>
                                    </td>

                                    <td>
                                        <?= statusBadge($row['status']) ?>
                                    </td>


                                    <!-- BERKAS PDF -->

                                    <td>

                                        <?php if (!empty($row['file_berkas'])): ?>

                                            <a
                                                href="../../<?= h($row['file_berkas']) ?>"
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                class="btn btn-sm btn-primary mb-1"
                                            >

                                                <i class="bi bi-eye"></i>
                                                Lihat PDF

                                            </a>

                                            <br>

                                            <a
                                                href="../../<?= h($row['file_berkas']) ?>"
                                                download
                                                class="btn btn-sm btn-success"
                                            >

                                                <i class="bi bi-download"></i>
                                                Download

                                            </a>

                                        <?php else: ?>

                                            <span class="text-danger">
                                                File tidak tersedia
                                            </span>

                                        <?php endif; ?>

                                    </td>


                                    <!-- CATATAN ADMIN -->

                                    <td>

                                        <?= nl2br(h($row['catatan_admin'] ?? '-')) ?>

                                    </td>


                                    <!-- AKSI VERIFIKASI -->

                                    <td style="min-width: 180px;">

                                        <?php if (
                                            in_array(
                                                $row['status'],
                                                ['submitted', 'calculated'],
                                                true
                                            )
                                        ): ?>

                                            <form method="POST">

                                                <input
                                                    type="hidden"
                                                    name="id"
                                                    value="<?= (int) $row['id'] ?>"
                                                >

                                                <select
                                                    name="new_status"
                                                    class="form-select form-select-sm mb-2"
                                                    required
                                                >

                                                    <option value="">
                                                        Pilih keputusan
                                                    </option>

                                                    <option value="approved">
                                                        Approve
                                                    </option>

                                                    <option value="revisi">
                                                        Revisi
                                                    </option>

                                                    <option value="rejected">
                                                        Reject
                                                    </option>

                                                </select>


                                                <textarea
                                                    name="catatan_admin"
                                                    class="form-control form-control-sm mb-2"
                                                    rows="2"
                                                    placeholder="Catatan admin"
                                                ></textarea>


                                                <button
                                                    type="submit"
                                                    class="btn btn-primary btn-sm w-100"
                                                >

                                                    <i class="bi bi-check-circle"></i>
                                                    Simpan

                                                </button>

                                            </form>

                                        <?php else: ?>

                                            <small class="text-muted">

                                                Sudah diverifikasi

                                            </small>

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

</div>


<?php

// Memanggil footer
include __DIR__ . '/../../templates/footer.php';

?>
