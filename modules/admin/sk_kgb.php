
<?php

require_once __DIR__ . '/../../core/middleware.php';
requireAdmin();

require_once __DIR__ . '/../../config/database.php';

function h($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function escapePdfText($text)
{
    $text = str_replace('\\', '\\\\', $text);
    $text = str_replace('(', '\\(', $text);
    $text = str_replace(')', '\\)', $text);

    return preg_replace('/[\x00-\x1F\x7F]/', '', $text);
}

function generateSimplePdf(array $lines, $filePath)
{
    $stream = "BT\n/F1 12 Tf\n50 790 Td\n";

    foreach ($lines as $index => $line) {
        if ($index > 0) {
            $stream .= "0 -18 Td\n";
        }

        $stream .= '(' . escapePdfText($line) . ") Tj\n";
    }

    $stream .= "ET";

    $objects = [];

    $objects[] = "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";

    $objects[] = "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n";

    $objects[] = "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>\nendobj\n";

    $objects[] = "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n";

    $objects[] = "5 0 obj\n<< /Length " . strlen($stream) . " >>\nstream\n" . $stream . "\nendstream\nendobj\n";

    $pdf = "%PDF-1.4\n";
    $offsets = [0];

    foreach ($objects as $obj) {
        $offsets[] = strlen($pdf);
        $pdf .= $obj;
    }

    $xrefPos = strlen($pdf);

    $pdf .= "xref\n0 6\n";
    $pdf .= "0000000000 65535 f \n";

    for ($i = 1; $i <= 5; $i++) {
        $pdf .= sprintf('%010d 00000 n ', $offsets[$i]) . "\n";
    }

    $pdf .= "trailer\n<< /Size 6 /Root 1 0 R >>\n";
    $pdf .= "startxref\n" . $xrefPos . "\n%%EOF";

    return file_put_contents($filePath, $pdf) !== false;
}

function statusBadge($status)
{
    switch ($status) {
        case 'approved':
            return '<span class="badge bg-success">Approved</span>';

        case 'completed':
            return '<span class="badge bg-primary">SK Terbit</span>';

        default:
            return '<span class="badge bg-secondary">' . h($status) . '</span>';
    }
}

$errors = [];

$success = $_SESSION['sk_flash_success'] ?? null;
unset($_SESSION['sk_flash_success']);

/* =================================
   PROSES SIMPAN SK
================================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? '';

    if ($action === 'save_sk') {

        $pengajuanId = (int) ($_POST['pengajuan_id'] ?? 0);
        $nomorSk = trim($_POST['nomor_sk'] ?? '');
        $tanggalApprove = trim($_POST['tanggal_approve'] ?? '');
        $mode = trim($_POST['mode'] ?? 'upload');

        if ($pengajuanId <= 0) {
            $errors[] = 'Pengajuan tidak valid.';
        }

        if ($tanggalApprove === '') {
            $errors[] = 'Tanggal approve wajib diisi.';
        }

        if (!in_array($mode, ['upload', 'generate'], true)) {
            $errors[] = 'Mode SK tidak valid.';
        }

        $pengajuan = null;

        if (!$errors) {

            $stmt = $pdo->prepare("
                SELECT
                    pk.*,
                    p.nip,
                    p.jabatan,
                    p.golongan,
                    p.unit_kerja,
                    u.nama AS pegawai_nama
                FROM pengajuan_kgb pk
                INNER JOIN pegawai p ON p.id = pk.pegawai_id
                INNER JOIN users u ON u.id = p.user_id
                WHERE pk.id = ?
                LIMIT 1
            ");

            $stmt->execute([$pengajuanId]);
            $pengajuan = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$pengajuan) {
                $errors[] = 'Data pengajuan tidak ditemukan.';
            } elseif ($pengajuan['status'] !== 'approved') {
                $errors[] = 'SK hanya bisa dibuat untuk pengajuan berstatus APPROVED.';
            }
        }

        $savedPath = '';
        $target = '';

        if (!$errors) {

            $uploadDir = __DIR__ . '/../../uploads/sk_kgb';

            if (!is_dir($uploadDir)) {
                if (!mkdir($uploadDir, 0775, true)) {
                    $errors[] = 'Folder upload SK tidak dapat dibuat.';
                }
            }

            if (!$errors && !is_writable($uploadDir)) {
                $errors[] = 'Folder upload SK tidak memiliki izin penulisan.';
            }

            if (!$errors) {

                $filename = 'sk_kgb_' . $pengajuanId . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.pdf';

                $target = $uploadDir . '/' . $filename;

                if ($mode === 'upload') {

                    if (
                        !isset($_FILES['file_sk']) ||
                        $_FILES['file_sk']['error'] === UPLOAD_ERR_NO_FILE
                    ) {
                        $errors[] = 'File SK PDF wajib diupload.';

                    } else {

                        $file = $_FILES['file_sk'];

                        if ($file['error'] !== UPLOAD_ERR_OK) {
                            $errors[] = 'Upload file SK gagal.';

                        } else {

                            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

                            if ($ext !== 'pdf') {
                                $errors[] = 'File SK harus berformat PDF.';
                            }

                            $finfo = finfo_open(FILEINFO_MIME_TYPE);
                            $mime = finfo_file($finfo, $file['tmp_name']);
                            finfo_close($finfo);

                            if ($mime !== 'application/pdf') {
                                $errors[] = 'File upload bukan PDF valid.';
                            }

                            if ($file['size'] > 5 * 1024 * 1024) {
                                $errors[] = 'Ukuran file SK maksimal 5MB.';
                            }
                        }

                        if (!$errors) {
                            if (!move_uploaded_file($file['tmp_name'], $target)) {
                                $errors[] = 'Gagal menyimpan file SK.';
                            }
                        }
                    }

                } else {

                    $nomorValue = $nomorSk !== ''
                        ? $nomorSk
                        : ('AUTO/' . date('Ymd') . '/' . $pengajuanId);

                    $lines = [
                        'SURAT KEPUTUSAN KENAIKAN GAJI BERKALA',
                        '',
                        'Nomor SK: ' . $nomorValue,
                        'Tanggal Approve: ' . $tanggalApprove,
                        'Nama Pegawai: ' . $pengajuan['pegawai_nama'],
                        'NIP: ' . $pengajuan['nip'],
                        'Jabatan: ' . ($pengajuan['jabatan'] ?: '-'),
                        'Golongan: ' . ($pengajuan['golongan'] ?: '-'),
                        'Unit Kerja: ' . ($pengajuan['unit_kerja'] ?: '-'),
                        'Periode KGB: ' . $pengajuan['periode_kgb'],
                        '',
                        'Dokumen ini dibuat otomatis oleh Sistem KGB.'
                    ];

                    if (!generateSimplePdf($lines, $target)) {
                        $errors[] = 'Gagal generate PDF SK.';
                    }

                    if ($nomorSk === '') {
                        $nomorSk = $nomorValue;
                    }
                }

                if (!$errors) {
                    $savedPath = 'uploads/sk_kgb/' . $filename;
                }
            }
        }

        /* SIMPAN DATA SK KE DATABASE */

        if (!$errors) {

            try {

                $pdo->beginTransaction();

                $stmt = $pdo->prepare("
                    INSERT INTO sk_kgb
                    (
                        pengajuan_id,
                        nomor_sk,
                        file_sk,
                        tanggal_approve,
                        uploaded_by
                    )
                    VALUES (?, ?, ?, ?, ?)

                    ON DUPLICATE KEY UPDATE
                        nomor_sk = VALUES(nomor_sk),
                        file_sk = VALUES(file_sk),
                        tanggal_approve = VALUES(tanggal_approve),
                        uploaded_by = VALUES(uploaded_by)
                ");

                $stmt->execute([
                    $pengajuanId,
                    $nomorSk !== '' ? $nomorSk : null,
                    $savedPath,
                    $tanggalApprove,
                    $_SESSION['user']['id']
                ]);

                $stmt = $pdo->prepare("
                    UPDATE pengajuan_kgb
                    SET status = 'completed'
                    WHERE id = ?
                ");

                $stmt->execute([$pengajuanId]);

                $pdo->commit();

                $_SESSION['sk_flash_success'] = 'SK KGB berhasil disimpan.';

                header('Location: sk_kgb.php');
                exit;

            } catch (Throwable $e) {

                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                if ($target && file_exists($target)) {
                    unlink($target);
                }

                error_log('SK KGB Error: ' . $e->getMessage());

                $errors[] = 'Gagal menyimpan data SK. Silakan periksa struktur database.';
            }
        }
    }
}

/* =================================
   AMBIL DATA PENGAJUAN APPROVED
================================= */

$stmt = $pdo->query("
    SELECT
        pk.id AS pengajuan_id,
        pk.periode_kgb,
        pk.status,

        p.nip,
        p.jabatan,
        p.golongan,

        u.nama AS pegawai_nama,
        u.email AS pegawai_email,

        sk.id AS sk_id,
        sk.nomor_sk,
        sk.file_sk,
        sk.tanggal_approve,

        up.nama AS uploader_nama

    FROM pengajuan_kgb pk

    INNER JOIN pegawai p
        ON p.id = pk.pegawai_id

    INNER JOIN users u
        ON u.id = p.user_id

    LEFT JOIN sk_kgb sk
        ON sk.pengajuan_id = pk.id

    LEFT JOIN users up
        ON up.id = sk.uploaded_by

    WHERE pk.status IN ('approved', 'completed')

    ORDER BY pk.tanggal_pengajuan DESC, pk.id DESC
");

$approvedList = $stmt->fetchAll(PDO::FETCH_ASSOC);

include __DIR__ . '/../../templates/header.php';

?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0">SK KGB Digital</h3>
</div>

<?php if ($success): ?>
    <div class="alert alert-success">
        <?= h($success) ?>
    </div>
<?php endif; ?>

<?php if ($errors): ?>
    <div class="alert alert-danger">
        <strong>Proses SK gagal:</strong>

        <ul class="mb-0 mt-2">
            <?php foreach ($errors as $error): ?>
                <li><?= h($error) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="card shadow-sm">

    <div class="card-header">
        <strong>Daftar Pengajuan Approved</strong>
    </div>

    <div class="card-body p-0">

        <div class="table-responsive">

            <table class="table table-striped table-bordered mb-0">

                <thead class="table-dark">
                    <tr>
                        <th>#</th>
                        <th>Pegawai</th>
                        <th>Detail</th>
                        <th>Periode</th>
                        <th>SK Saat Ini</th>
                        <th>Upload SK</th>
                    </tr>
                </thead>

                <tbody>

                <?php if (!$approvedList): ?>

                    <tr>
                        <td colspan="6" class="text-center py-3">
                            Belum ada pengajuan approved.
                        </td>
                    </tr>

                <?php else: ?>

                    <?php foreach ($approvedList as $index => $row): ?>

                        <tr>

                            <td><?= $index + 1 ?></td>

                            <td>
                                <?= h($row['pegawai_nama']) ?><br>
                                <small><?= h($row['pegawai_email']) ?></small>
                            </td>

                            <td>
                                NIP: <?= h($row['nip']) ?><br>
                                Jabatan: <?= h($row['jabatan'] ?: '-') ?><br>
                                Golongan: <?= h($row['golongan'] ?: '-') ?>
                            </td>

                            <td><?= h($row['periode_kgb']) ?></td>

                            <td>
                                <?= statusBadge($row['status']) ?>
                            </td>

                            <td>

                                <?php if ($row['sk_id']): ?>

                                    <a href="<?= h(appUrl('/' . $row['file_sk'])) ?>" target="_blank">
                                        Lihat SK
                                    </a>

                                    <br>

                                    <small>No: <?= h($row['nomor_sk'] ?: '-') ?></small>
                                    <br>

                                    <small>Tgl: <?= h($row['tanggal_approve']) ?></small>
                                    <br>

                                    <small>Oleh: <?= h($row['uploader_nama'] ?: '-') ?></small>

                                <?php else: ?>

                                    <span class="text-muted">Belum ada SK</span>

                                <?php endif; ?>

                            </td>

                            <td style="min-width:320px;">

                                <form method="POST" enctype="multipart/form-data">

                                    <input type="hidden" name="action" value="save_sk">

                                    <input
                                        type="hidden"
                                        name="pengajuan_id"
                                        value="<?= (int) $row['pengajuan_id'] ?>"
                                    >

                                    <div class="mb-2">

                                        <label class="form-label mb-1">
                                            Nomor SK
                                        </label>

                                        <input
                                            type="text"
                                            name="nomor_sk"
                                            class="form-control form-control-sm"
                                            value="<?= h($row['nomor_sk'] ?: '') ?>"
                                            placeholder="Contoh: 800/123/2026"
                                        >

                                    </div>

                                    <div class="mb-2">

                                        <label class="form-label mb-1">
                                            Tanggal Approve
                                        </label>

                                        <input
                                            type="date"
                                            name="tanggal_approve"
                                            class="form-control form-control-sm"
                                            value="<?= h($row['tanggal_approve'] ?: date('Y-m-d')) ?>"
                                            required
                                        >

                                    </div>

                                    <div class="mb-2">

                                        <label class="form-label mb-1">
                                            Mode
                                        </label>

                                        <select
                                            name="mode"
                                            class="form-select form-select-sm"
                                        >
                                            <option value="upload">Upload PDF</option>
                                            <option value="generate">Generate PDF Otomatis</option>
                                        </select>

                                    </div>

                                    <div class="mb-2">

                                        <label class="form-label mb-1">
                                            File PDF (wajib jika mode upload)
                                        </label>

                                        <input
                                            type="file"
                                            name="file_sk"
                                            class="form-control form-control-sm"
                                            accept="application/pdf,.pdf"
                                        >

                                    </div>

                                    <button class="btn btn-sm btn-primary w-100">
                                        Simpan SK
                                    </button>

                                </form>

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
