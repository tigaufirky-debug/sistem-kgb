
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
| AMBIL DATA PEGAWAI
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT p.*, u.nama, u.email
    FROM pegawai p
    INNER JOIN users u ON u.id = p.user_id
    WHERE p.user_id = ?
");

$stmt->execute([$userId]);
$pegawai = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$pegawai) {
    die('Data pegawai tidak ditemukan.');
}

$success = $_SESSION['success'] ?? null;
unset($_SESSION['success']);

$errors = [];

/*
|--------------------------------------------------------------------------
| PROSES PENGAJUAN
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $periodeKgb = trim($_POST['periode_kgb'] ?? '');
    $uploadPath = null;
    $destination = null;

    // Validasi periode
    if ($periodeKgb === '') {
        $errors[] = 'Periode KGB wajib diisi.';
    }

    // Validasi file
    if (!isset($_FILES['file_berkas'])) {
        $errors[] = 'File berkas wajib diupload.';
    } else {

        $fileError = $_FILES['file_berkas']['error'];

        if ($fileError !== UPLOAD_ERR_OK) {

            if ($fileError === UPLOAD_ERR_INI_SIZE ||
                $fileError === UPLOAD_ERR_FORM_SIZE) {
                $errors[] = 'Ukuran file melebihi batas upload server.';
            } else {
                $errors[] = 'File berkas gagal diupload. Silakan coba kembali.';
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | VALIDASI FORMAT DAN UKURAN PDF
    |--------------------------------------------------------------------------
    */

    if (!$errors) {

        $fileName = $_FILES['file_berkas']['name'];
        $tmpName = $_FILES['file_berkas']['tmp_name'];
        $fileSize = $_FILES['file_berkas']['size'];

        $extension = strtolower(
            pathinfo($fileName, PATHINFO_EXTENSION)
        );

        if ($extension !== 'pdf') {
            $errors[] = 'File harus berformat PDF.';
        }

        if ($fileSize > 5 * 1024 * 1024) {
            $errors[] = 'Ukuran file maksimal 5MB.';
        }

        if (!$errors) {

            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mimeType = $finfo->file($tmpName);

            if ($mimeType !== 'application/pdf') {
                $errors[] = 'File yang diupload bukan dokumen PDF yang valid.';
            }
        }

        /*
        |--------------------------------------------------------------------------
        | SIMPAN FILE
        |--------------------------------------------------------------------------
        */

        if (!$errors) {

            $dir = __DIR__ . '/../../uploads/kgb/';

            if (!is_dir($dir)) {
                if (!mkdir($dir, 0755, true)) {
                    $errors[] = 'Folder penyimpanan berkas tidak dapat dibuat.';
                }
            }

            if (!$errors && !is_writable($dir)) {
                $errors[] = 'Folder penyimpanan berkas tidak memiliki izin tulis.';
            }

            if (!$errors) {

                $newName = 'kgb_' . date('YmdHis') . '_' .
                    bin2hex(random_bytes(5)) . '.pdf';

                $destination = $dir . $newName;

                if (move_uploaded_file($tmpName, $destination)) {
                    $uploadPath = 'uploads/kgb/' . $newName;
                } else {
                    $errors[] = 'Upload file gagal.';
                }
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | SIMPAN DATA PENGAJUAN KE DATABASE
    |--------------------------------------------------------------------------
    */

    if (!$errors) {

        try {

            $stmt = $pdo->prepare("
                INSERT INTO pengajuan_kgb (
                    pegawai_id,
                    periode_kgb,
                    file_berkas,
                    status,
                    tanggal_pengajuan
                )
                VALUES (?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $pegawai['id'],
                $periodeKgb,
                $uploadPath,
                'submitted',
                date('Y-m-d')
            ]);

            $_SESSION['success'] = 'Pengajuan KGB berhasil dikirim.';

            header('Location: ' . appUrl('/modules/pegawai/pengajuan.php'));
            exit;

        } catch (PDOException $e) {

            // Hapus file jika penyimpanan database gagal
            if ($destination && file_exists($destination)) {
                unlink($destination);
            }

            error_log('Pengajuan KGB Error: ' . $e->getMessage());

            $errors[] = 'Pengajuan gagal disimpan ke database. Silakan hubungi administrator.';
        }
    }
}

/*
|--------------------------------------------------------------------------
| AMBIL RIWAYAT PENGAJUAN DAN SK
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        pk.*,
        sk.file_sk,
        sk.nomor_sk,
        sk.tanggal_approve
    FROM pengajuan_kgb pk
    LEFT JOIN sk_kgb sk
        ON sk.pengajuan_id = pk.id
    WHERE pk.pegawai_id = ?
    ORDER BY pk.tanggal_pengajuan DESC, pk.id DESC
");

$stmt->execute([$pegawai['id']]);
$riwayat = $stmt->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| BADGE STATUS
|--------------------------------------------------------------------------
*/

function statusBadge($status)
{
    $map = [
        'draft' => 'secondary',
        'submitted' => 'primary',
        'revisi' => 'warning',
        'approved' => 'success',
        'rejected' => 'danger',
        'completed' => 'dark'
    ];

    $class = $map[$status] ?? 'secondary';

    return '<span class="badge bg-' . $class . '">' .
        strtoupper(h($status)) .
        '</span>';
}

include __DIR__ . '/../../templates/header.php';

?>

<div class="container-fluid">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="mb-0">Pengajuan KGB</h3>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success shadow-sm">
            <?= h($success) ?>
        </div>
    <?php endif; ?>

    <?php if ($errors): ?>
        <div class="alert alert-danger shadow-sm">
            <strong>Terjadi Kesalahan:</strong>
            <ul class="mb-0 mt-2">
                <?php foreach ($errors as $error): ?>
                    <li><?= h($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="row">

        <!-- FORM PENGAJUAN -->

        <div class="col-lg-4">

            <div class="card shadow-sm border-0 mb-4">

                <div class="card-header bg-primary text-white">
                    <strong>Form Pengajuan</strong>
                </div>

                <div class="card-body">

                    <form method="POST" enctype="multipart/form-data">

                        <div class="mb-3">
                            <label class="form-label">Periode KGB</label>

                            <input
                                type="date"
                                name="periode_kgb"
                                class="form-control"
                                value="<?= h($_POST['periode_kgb'] ?? '') ?>"
                                required
                            >
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Upload Berkas PDF</label>

                            <input
                                type="file"
                                name="file_berkas"
                                class="form-control"
                                accept=".pdf,application/pdf"
                                required
                            >

                            <small class="text-muted">
                                Format PDF, maksimal 5MB.
                            </small>
                        </div>

                        <button
                            type="submit"
                            class="btn btn-primary w-100"
                        >
                            Kirim Pengajuan
                        </button>

                    </form>

                </div>

            </div>

        </div>

        <!-- RIWAYAT PENGAJUAN -->

        <div class="col-lg-8">

            <div class="card shadow-sm border-0">

                <div class="card-header bg-dark text-white">
                    <strong>Riwayat Pengajuan</strong>
                </div>

                <div class="card-body p-0">

                    <div class="table-responsive">

                        <table class="table table-striped table-hover align-middle mb-0">

                            <thead class="table-dark">
                                <tr>
                                    <th>#</th>
                                    <th>Periode</th>
                                    <th>Status</th>
                                    <th>Tanggal</th>
                                    <th>Berkas</th>
                                    <th>SK KGB</th>
                                    <th>Catatan</th>
                                </tr>
                            </thead>

                            <tbody>

                                <?php if (!$riwayat): ?>

                                    <tr>
                                        <td colspan="7" class="text-center py-4">
                                            Belum ada pengajuan.
                                        </td>
                                    </tr>

                                <?php else: ?>

                                    <?php foreach ($riwayat as $i => $row): ?>

                                        <tr>

                                            <td><?= $i + 1 ?></td>

                                            <td>
                                                <?= h($row['periode_kgb']) ?>
                                            </td>

                                            <td>
                                                <?= statusBadge($row['status']) ?>
                                            </td>

                                            <td>
                                                <?= h($row['tanggal_pengajuan']) ?>
                                            </td>

                                            <td>
                                                <?php if (!empty($row['file_berkas'])): ?>

                                                    <a
                                                        href="<?= h(appUrl('/' . $row['file_berkas'])) ?>"
                                                        target="_blank"
                                                        class="btn btn-sm btn-outline-primary"
                                                    >
                                                        Lihat PDF
                                                    </a>

                                                <?php else: ?>

                                                    <span class="text-muted">
                                                        Tidak ada file
                                                    </span>

                                                <?php endif; ?>
                                            </td>

                                            <td>

                                                <?php if (
                                                    !empty($row['file_sk']) &&
                                                    in_array(
                                                        $row['status'],
                                                        ['approved', 'completed'],
                                                        true
                                                    )
                                                ): ?>

                                                    <div class="mb-1">
                                                        <strong>
                                                            <?= h($row['nomor_sk'] ?: '-') ?>
                                                        </strong>
                                                    </div>

                                                    <small class="text-muted d-block mb-2">
                                                        <?= h($row['tanggal_approve'] ?: '-') ?>
                                                    </small>

                                                    <a
                                                        href="download_sk.php?pengajuan_id=<?= (int)$row['id'] ?>"
                                                        class="btn btn-sm btn-success"
                                                    >
                                                        Download SK
                                                    </a>

                                                <?php else: ?>

                                                    <span class="text-muted">
                                                        Belum tersedia
                                                    </span>

                                                <?php endif; ?>

                                            </td>

                                            <td>
                                                <?= nl2br(h($row['catatan_admin'] ?? '-')) ?>
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

    </div>

</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
