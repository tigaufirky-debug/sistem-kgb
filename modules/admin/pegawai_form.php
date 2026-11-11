<?php
require_once '../../core/middleware.php';
requireAdmin();
require_once '../../config/database.php';

function h($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function masaKerjaFromTmt($tmtMulai)
{
    if (!$tmtMulai) {
        return 0;
    }

    try {
        $start = new DateTime($tmtMulai);
        $today = new DateTime('today');
        if ($start > $today) {
            return 0;
        }
        return (int) $start->diff($today)->y;
    } catch (Exception $e) {
        return 0;
    }
}

$errors = [];
$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$isEdit = $id > 0;

$formData = [
    'id' => $id,
    'user_id' => '',
    'nip' => '',
    'jabatan' => '',
    'golongan' => '',
    'tmt_mulai' => '',
    'masa_kerja' => '',
    'unit_kerja' => '',
];

if ($isEdit) {
    $stmt = $pdo->prepare('SELECT * FROM pegawai WHERE id = ?');
    $stmt->execute([$id]);
    $existing = $stmt->fetch();

    if (!$existing) {
        $_SESSION['pegawai_flash_error'] = 'Data pegawai yang akan diedit tidak ditemukan.';
        header('Location: pegawai_list.php');
        exit;
    }

    $formData = $existing;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = (int) ($_POST['user_id'] ?? 0);
    $nip = trim($_POST['nip'] ?? '');
    $jabatan = trim($_POST['jabatan'] ?? '');
    $golongan = trim($_POST['golongan'] ?? '');
    $tmtMulai = trim($_POST['tmt_mulai'] ?? '');
    $masaKerjaInput = trim($_POST['masa_kerja'] ?? '');
    $unitKerja = trim($_POST['unit_kerja'] ?? '');
    $kehadiran = (float) ($_POST['kehadiran'] ?? 0);
    $disiplin = trim($_POST['disiplin'] ?? 'Baik');

    $formData = [
        'id' => $id,
        'user_id' => $userId,
        'nip' => $nip,
        'jabatan' => $jabatan,
        'golongan' => $golongan,
        'tmt_mulai' => $tmtMulai,
        'masa_kerja' => $masaKerjaInput,
        'unit_kerja' => $unitKerja,
        'kehadiran' => $kehadiran,
        'disiplin' => $disiplin,
    ];

    if ($userId <= 0) {
        $errors[] = 'User pegawai wajib dipilih.';
    }
    if ($nip === '') {
        $errors[] = 'NIP wajib diisi.';
    }
    if ($tmtMulai === '') {
        $errors[] = 'TMT mulai wajib diisi.';
    }

    $masaKerja = $masaKerjaInput === '' ? masaKerjaFromTmt($tmtMulai) : max(0, (int) $masaKerjaInput);

    if (!$errors) {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE id = ? AND role = ?');
        $stmt->execute([$userId, 'pegawai']);
        if (!$stmt->fetch()) {
            $errors[] = 'User yang dipilih tidak valid (harus role pegawai).';
        }
    }

    if (!$errors) {
        $stmt = $pdo->prepare('SELECT id FROM pegawai WHERE nip = ? AND id <> ?');
        $stmt->execute([$nip, $id]);
        if ($stmt->fetch()) {
            $errors[] = 'NIP sudah dipakai pegawai lain.';
        }
    }

    if (!$errors) {
        $stmt = $pdo->prepare('SELECT id FROM pegawai WHERE user_id = ? AND id <> ?');
        $stmt->execute([$userId, $id]);
        if ($stmt->fetch()) {
            $errors[] = 'User tersebut sudah terhubung ke data pegawai lain.';
        }
    }

    if (!$errors) {
        if ($isEdit) {
            $stmt = $pdo->prepare("\n                UPDATE pegawai\n                SET user_id = ?, nip = ?, jabatan = ?, golongan = ?, tmt_mulai = ?, masa_kerja = ?, unit_kerja = ?\n                WHERE id = ?\n            ");
            $stmt->execute([$userId, $nip, $jabatan, $golongan, $tmtMulai, $masaKerja, $unitKerja, $id]);
            $_SESSION['pegawai_flash_success'] = 'Data pegawai berhasil diperbarui.';
        } else {
            $stmt = $pdo->prepare("\n                INSERT INTO pegawai (user_id, nip, jabatan, golongan, tmt_mulai, masa_kerja, unit_kerja)\n                VALUES (?, ?, ?, ?, ?, ?, ?)\n            ");
            $stmt->execute([$userId, $nip, $jabatan, $golongan, $tmtMulai, $masaKerja, $unitKerja]);
            $_SESSION['pegawai_flash_success'] = 'Data pegawai berhasil ditambahkan.';
        }

        header('Location: pegawai_list.php');
        exit;
    }
}

$userQuery = $pdo->query("\n    SELECT u.id, u.nama, u.email,\n           (SELECT COUNT(*) FROM pegawai p WHERE p.user_id = u.id) AS dipakai\n    FROM users u\n    WHERE u.role = 'pegawai' AND u.is_active = 1\n    ORDER BY u.nama ASC\n");
$pegawaiUsers = $userQuery->fetchAll();
$hasPegawaiUsers = count($pegawaiUsers) > 0;

include '../../templates/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0"><?= $isEdit ? 'Edit Data Pegawai' : 'Tambah Data Pegawai' ?></h3>
    <div class="d-flex gap-2">
        <a href="pegawai_list.php" class="btn btn-outline-primary btn-sm">Kembali ke List Pegawai</a>
    </div>
</div>

<?php if (!$hasPegawaiUsers): ?>
    <div class="alert alert-warning">
        Belum ada akun user dengan role <strong>pegawai</strong>. Buat dulu akun pegawai di
        <a href="user_list.php" class="alert-link">Kelola User Pegawai</a>
        agar data pegawai bisa ditambahkan.
    </div>
<?php endif; ?>

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
        <strong><?= $isEdit ? 'Form Edit Pegawai' : 'Form Tambah Pegawai' ?></strong>
    </div>
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="id" value="<?= (int) $formData['id'] ?>">

            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">User Pegawai</label>
                    <select name="user_id" class="form-select" required>
                        <option value="">-- Pilih User --</option>
                        <?php foreach ($pegawaiUsers as $u): ?>
                            <?php
                            $selected = (int) $formData['user_id'] === (int) $u['id'];
                            $dipakaiLain = (int) $u['dipakai'] > 0 && !$selected;
                            ?>
                            <option value="<?= h($u['id']) ?>" <?= $selected ? 'selected' : '' ?> <?= $dipakaiLain ? 'disabled' : '' ?>>
                                <?= h($u['nama']) ?> (<?= h($u['email']) ?>)<?= $dipakaiLain ? ' - sudah dipakai' : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label">NIP</label>
                    <input type="text" name="nip" class="form-control" value="<?= h($formData['nip']) ?>" required>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Jabatan</label>
                    <input type="text" name="jabatan" class="form-control" value="<?= h($formData['jabatan']) ?>">
                </div>

                <div class="col-md-4">
                    <label class="form-label">Golongan</label>
                    <input type="text" name="golongan" class="form-control" value="<?= h($formData['golongan']) ?>">
                </div>

                <div class="col-md-4">
                    <label class="form-label">Unit Kerja</label>
                    <input type="text" name="unit_kerja" class="form-control" value="<?= h($formData['unit_kerja']) ?>">
                </div>

                <div class="col-md-6">
                    <label class="form-label">TMT Mulai</label>
                    <input type="date" name="tmt_mulai" class="form-control" value="<?= h($formData['tmt_mulai']) ?>" required>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Masa Kerja (tahun)</label>
                    <input type="number" min="0" name="masa_kerja" class="form-control" value="<?= h($formData['masa_kerja']) ?>">
                    <small class="text-muted">Kosongkan untuk hitung otomatis dari TMT.</small>
                </div>

                <div class="col-md-6">
                 <label class="form-label">Kehadiran (%)</label>
                     <input
                        type="number"
                        name="kehadiran"
                        class="form-control"
                        min="0"
                        max="100"
                        step="0.01"
                        value="<?= h($formData['kehadiran'] ?? '') ?>"
                        required>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Disiplin</label>

                    <select name="disiplin" class="form-select" required>

                        <option value="Kurang"
                            <?= (($formData['disiplin'] ?? '') == 'Kurang') ? 'selected' : '' ?>>
                            Kurang
                        </option>

                        <option value="Cukup"
                            <?= (($formData['disiplin'] ?? '') == 'Cukup') ? 'selected' : '' ?>>
                            Cukup
                        </option>

                        <option value="Baik"
                            <?= (($formData['disiplin'] ?? '') == 'Baik') ? 'selected' : '' ?>>
                            Baik
                        </option>

                        <option value="Sangat Baik"
                            <?= (($formData['disiplin'] ?? '') == 'Sangat Baik') ? 'selected' : '' ?>>
                            Sangat Baik
                        </option>

                    </select>
                </div>
            </div>

            <div class="mt-3 d-flex gap-2">
                <button class="btn btn-primary"><?= $isEdit ? 'Simpan Perubahan' : 'Tambah Pegawai' ?></button>
                <a href="pegawai_list.php" class="btn btn-light border">Batal</a>
            </div>
        </form>
    </div>
</div>

<?php include '../../templates/footer.php'; ?>
