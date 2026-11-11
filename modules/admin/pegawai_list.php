<?php
require_once '../../core/middleware.php';
requireAdmin();
require_once '../../config/database.php';

function h($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$errors = [];
$success = $_SESSION['pegawai_flash_success'] ?? null;
unset($_SESSION['pegawai_flash_success']);

if (!empty($_SESSION['pegawai_flash_error'])) {
    $errors[] = $_SESSION['pegawai_flash_error'];
    unset($_SESSION['pegawai_flash_error']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $id = (int) ($_POST['id'] ?? 0);
    if ($id > 0) {
        $stmt = $pdo->prepare('DELETE FROM pegawai WHERE id = ?');
        $stmt->execute([$id]);
        $_SESSION['pegawai_flash_success'] = 'Data pegawai berhasil dihapus.';
        header('Location: pegawai_list.php');
        exit;
    }
}

$userCountStmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'pegawai' AND is_active = 1");
$hasPegawaiUsers = (int) $userCountStmt->fetchColumn() > 0;

$listQuery = $pdo->query("\n    SELECT p.*, u.nama, u.email,\n           COALESCE(pk.total_pengajuan, 0) AS total_pengajuan,\n           pk.last_periode,\n           pk.last_status\n    FROM pegawai p\n    INNER JOIN users u ON u.id = p.user_id\n    LEFT JOIN (\n        SELECT pegawai_id,\n               COUNT(*) AS total_pengajuan,\n               MAX(periode_kgb) AS last_periode,\n               SUBSTRING_INDEX(GROUP_CONCAT(status ORDER BY periode_kgb DESC), ',', 1) AS last_status\n        FROM pengajuan_kgb\n        GROUP BY pegawai_id\n    ) pk ON pk.pegawai_id = p.id\n    ORDER BY p.created_at DESC\n");
$pegawaiList = $listQuery->fetchAll();

include '../../templates/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0">Kelola Data Pegawai</h3>
    <div class="d-flex gap-2">
        <a href="pegawai_form.php" class="btn btn-primary btn-sm">+ Tambah Pegawai</a>
    </div>
</div>

<?php if ($success): ?>
    <div class="alert alert-success"><?= h($success) ?></div>
<?php endif; ?>

<?php if ($errors): ?>
    <div class="alert alert-danger mb-3">
        <strong>Proses gagal:</strong>
        <ul class="mb-0 mt-2">
            <?php foreach ($errors as $error): ?>
                <li><?= h($error) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<?php if (!$hasPegawaiUsers): ?>
    <div class="alert alert-warning">
        Belum ada akun user dengan role <strong>pegawai</strong>. Buat dulu akun pegawai di
        <a href="user_list.php" class="alert-link">Kelola User Pegawai</a>
        agar data pegawai bisa ditambahkan.
    </div>
<?php endif; ?>

<div class="card shadow-sm">
    <div class="card-header">
        <strong>Daftar Pegawai</strong>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-striped table-bordered mb-0">
                <thead class="table-dark">
                <tr>
                    <th>#</th>
                    <th>Nama</th>
                    <th>Email</th>
                    <th>NIP</th>
                    <th>Jabatan</th>
                    <th>Golongan</th>
                    <th>TMT Mulai</th>
                    <th>Masa Kerja</th>
                    <th>Riwayat KGB</th>
                    <th>Aksi</th>
                </tr>
                </thead>
                <tbody>
                <?php if (!$pegawaiList): ?>
                    <tr>
                        <td colspan="10" class="text-center py-3">Belum ada data pegawai.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($pegawaiList as $index => $row): ?>
                        <tr>
                            <td><?= $index + 1 ?></td>
                            <td><?= h($row['nama']) ?></td>
                            <td><?= h($row['email']) ?></td>
                            <td><?= h($row['nip']) ?></td>
                            <td><?= h($row['jabatan']) ?></td>
                            <td><?= h($row['golongan']) ?></td>
                            <td><?= h($row['tmt_mulai']) ?></td>
                            <td><?= h($row['masa_kerja']) ?> th</td>
                            <td>
                                Total: <?= h($row['total_pengajuan']) ?><br>
                                Terakhir: <?= h($row['last_periode'] ?: '-') ?><br>
                                Status: <?= strtoupper(h($row['last_status'] ?: '-')) ?>
                            </td>
                            <td>
                                <div class="d-flex gap-1">
                                    <a href="pegawai_form.php?id=<?= (int) $row['id'] ?>" class="btn btn-sm btn-warning">Edit</a>
                                    <form method="POST" onsubmit="return confirm('Hapus data pegawai ini?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                        <button class="btn btn-sm btn-danger">Hapus</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include '../../templates/footer.php'; ?>
