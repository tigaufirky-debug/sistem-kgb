<?php
require_once '../../core/middleware.php';
requireAdmin();
require_once '../../config/database.php';

function h($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$errors = [];
$success = $_SESSION['user_flash_success'] ?? null;
unset($_SESSION['user_flash_success']);

if (!empty($_SESSION['user_flash_error'])) {
    $errors[] = $_SESSION['user_flash_error'];
    unset($_SESSION['user_flash_error']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $id = (int) ($_POST['id'] ?? 0);

    if ($id > 0) {
        $stmt = $pdo->prepare("SELECT id FROM pegawai WHERE user_id = ?");
        $stmt->execute([$id]);
        if ($stmt->fetch()) {
            $errors[] = 'User tidak bisa dihapus karena sudah terhubung ke data pegawai.';
        } else {
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND role = 'pegawai'");
            $stmt->execute([$id]);
            $_SESSION['user_flash_success'] = 'User pegawai berhasil dihapus.';
            header('Location: user_list.php');
            exit;
        }
    }
}

$users = $pdo->query("\n    SELECT u.id, u.nama, u.email, u.is_active, u.created_at,\n           CASE WHEN p.id IS NULL THEN 0 ELSE 1 END AS sudah_terhubung_pegawai\n    FROM users u\n    LEFT JOIN pegawai p ON p.user_id = u.id\n    WHERE u.role = 'pegawai'\n    ORDER BY u.created_at DESC\n")->fetchAll();

include '../../templates/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0">Kelola User Pegawai</h3>
    <div class="d-flex gap-2">
        <a href="user_form.php" class="btn btn-primary btn-sm">+ Tambah User Pegawai</a>
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

<div class="card shadow-sm">
    <div class="card-header">
        <strong>Daftar User Pegawai</strong>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-striped table-bordered mb-0">
                <thead class="table-dark">
                <tr>
                    <th>#</th>
                    <th>Nama</th>
                    <th>Email</th>
                    <th>Status</th>
                    <th>Relasi Pegawai</th>
                    <th>Dibuat</th>
                    <th>Aksi</th>
                </tr>
                </thead>
                <tbody>
                <?php if (!$users): ?>
                    <tr>
                        <td colspan="7" class="text-center py-3">Belum ada user pegawai.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($users as $index => $row): ?>
                        <tr>
                            <td><?= $index + 1 ?></td>
                            <td><?= h($row['nama']) ?></td>
                            <td><?= h($row['email']) ?></td>
                            <td><?= (int) $row['is_active'] === 1 ? 'Aktif' : 'Nonaktif' ?></td>
                            <td><?= (int) $row['sudah_terhubung_pegawai'] === 1 ? 'Sudah terhubung' : 'Belum terhubung' ?></td>
                            <td><?= h($row['created_at']) ?></td>
                            <td>
                                <div class="d-flex gap-1">
                                    <a href="user_form.php?id=<?= (int) $row['id'] ?>" class="btn btn-sm btn-warning">Edit</a>
                                    <form method="POST" onsubmit="return confirm('Hapus user ini?');">
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
