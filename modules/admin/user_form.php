<?php
require_once '../../core/middleware.php';
requireAdmin();
require_once '../../config/database.php';

function h($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$errors = [];
$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$isEdit = $id > 0;

$formData = [
    'id' => $id,
    'nama' => '',
    'email' => '',
    'is_active' => 1,
];

if ($isEdit) {
    $stmt = $pdo->prepare("SELECT id, nama, email, is_active FROM users WHERE id = ? AND role = 'pegawai'");
    $stmt->execute([$id]);
    $existing = $stmt->fetch();

    if (!$existing) {
        $_SESSION['user_flash_error'] = 'User pegawai yang akan diedit tidak ditemukan.';
        header('Location: user_list.php');
        exit;
    }

    $formData = $existing;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama = trim($_POST['nama'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $isActive = isset($_POST['is_active']) ? 1 : 0;

    $formData = [
        'id' => $id,
        'nama' => $nama,
        'email' => $email,
        'is_active' => $isActive,
    ];

    if ($nama === '') {
        $errors[] = 'Nama wajib diisi.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Format email tidak valid.';
    }
    if (!$isEdit && $password === '') {
        $errors[] = 'Password wajib diisi untuk user baru.';
    }
    if ($password !== '' && strlen($password) < 6) {
        $errors[] = 'Password minimal 6 karakter.';
    }

    if (!$errors) {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE LOWER(email) = LOWER(?) AND id <> ?');
        $stmt->execute([$email, $id]);
        if ($stmt->fetch()) {
            $errors[] = 'Email sudah dipakai user lain.';
        }
    }

    if (!$errors) {
        if ($isEdit) {
            if ($password !== '') {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("\n                    UPDATE users\n                    SET nama = ?, email = ?, password = ?, is_active = ?, role = 'pegawai'\n                    WHERE id = ?\n                ");
                $stmt->execute([$nama, strtolower($email), $hash, $isActive, $id]);
            } else {
                $stmt = $pdo->prepare("\n                    UPDATE users\n                    SET nama = ?, email = ?, is_active = ?, role = 'pegawai'\n                    WHERE id = ?\n                ");
                $stmt->execute([$nama, strtolower($email), $isActive, $id]);
            }

            $_SESSION['user_flash_success'] = 'User pegawai berhasil diperbarui.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("\n                INSERT INTO users (nama, email, password, role, is_active)\n                VALUES (?, ?, ?, 'pegawai', ?)\n            ");
            $stmt->execute([$nama, strtolower($email), $hash, $isActive]);
            $_SESSION['user_flash_success'] = 'User pegawai berhasil ditambahkan.';
        }

        header('Location: user_list.php');
        exit;
    }
}

include '../../templates/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0"><?= $isEdit ? 'Edit User Pegawai' : 'Tambah User Pegawai' ?></h3>
    <div class="d-flex gap-2">
        <a href="user_list.php" class="btn btn-outline-primary btn-sm">Kembali ke List User</a>
    </div>
</div>

<?php if ($errors): ?>
    <div class="alert alert-danger mb-3">
        <strong>Validasi gagal:</strong>
        <ul class="mb-0 mt-2">
            <?php foreach ($errors as $error): ?>
                <li><?= h($error) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="card shadow-sm">
    <div class="card-header">
        <strong><?= $isEdit ? 'Form Edit User Pegawai' : 'Form Tambah User Pegawai' ?></strong>
    </div>
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="id" value="<?= (int) $formData['id'] ?>">

            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Nama</label>
                    <input type="text" name="nama" class="form-control" value="<?= h($formData['nama']) ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control" value="<?= h($formData['email']) ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Password <?= $isEdit ? '(opsional untuk reset)' : '' ?></label>
                    <input type="password" name="password" class="form-control" <?= $isEdit ? '' : 'required' ?>>
                </div>
                <div class="col-md-12">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="is_active" id="is_active" <?= (int) $formData['is_active'] === 1 ? 'checked' : '' ?>>
                        <label class="form-check-label" for="is_active">User aktif</label>
                    </div>
                </div>
            </div>

            <div class="mt-3 d-flex gap-2">
                <button class="btn btn-primary"><?= $isEdit ? 'Simpan Perubahan' : 'Tambah User' ?></button>
                <a href="user_list.php" class="btn btn-light border">Batal</a>
            </div>
        </form>
    </div>
</div>

<?php include '../../templates/footer.php'; ?>
