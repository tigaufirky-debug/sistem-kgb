<?php
require_once '../../core/middleware.php';
requireAdmin();
require_once '../../config/database.php';
require_once '../../core/reminder_kgb_service.php';

function h($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

ensureReminderLogTable($pdo);
ensureReminderSettingsTable($pdo);

$errors = [];
$success = $_SESSION['reminder_flash_success'] ?? null;
unset($_SESSION['reminder_flash_success']);
$settings = getReminderSettings($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_settings') {
    $daysBeforeRaw = trim((string) ($_POST['days_before'] ?? '30'));

    if ($daysBeforeRaw === '' || !ctype_digit($daysBeforeRaw)) {
        $errors[] = 'H-berapa wajib angka bulat 0-365.';
    } else {
        $daysBefore = (int) $daysBeforeRaw;
        if ($daysBefore < 0 || $daysBefore > 365) {
            $errors[] = 'H-berapa harus antara 0 sampai 365.';
        }
    }

    if (!$errors) {
        // Reuse existing save function with dummy values for other fields
        saveReminderSettings($pdo, [
            'days_before' => (int) $daysBefore,
            'send_time' => '07:00',
            'send_to_employee' => false,
            'recipient_emails_raw' => '',
        ]);
        $_SESSION['reminder_flash_success'] = 'Pengaturan notifikasi berhasil disimpan.';
        header('Location: reminder.php');
        exit;
    }

    $settings['days_before'] = isset($daysBefore) ? $daysBefore : (int) $settings['days_before'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'send_notification_manual') {
    $targetUserId = (int)($_POST['user_id'] ?? 0);
    if ($targetUserId) {
        $message = trim($_POST['message'] ?? '');
        if (!$message) {
            $message = "PENGINGAT: Jadwal KGB Anda sudah dekat/terlewat. Segera ajukan.";
        }
        createNotification($pdo, $targetUserId, $message, 'warning');
        $_SESSION['reminder_flash_success'] = 'Notifikasi berhasil dikirim ke pegawai.';
        header('Location: reminder.php');
        exit;
    }
}

$allEmployees = getAllEmployeesKgbStatus($pdo);

include '../../templates/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0">Pengaturan Notifikasi Dashboard</h3>
</div>

<?php if ($success): ?>
    <div class="alert alert-success"><?= h($success) ?></div>
<?php endif; ?>
<?php if ($errors): ?>
    <div class="alert alert-danger mb-3">
        <ul class="mb-0">
            <?php foreach ($errors as $err): ?>
                <li><?= h($err) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="card shadow-sm mb-4">
    <div class="card-header"><strong>Konfigurasi</strong></div>
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="action" value="save_settings">
            <div class="mb-3">
                <label class="form-label">Tampilkan Notifikasi H-berapa</label>
                <div class="input-group" style="max-width: 300px;">
                    <input type="number" min="0" max="365" name="days_before" class="form-control"
                           value="<?= (int) $settings['days_before'] ?>" required>
                    <span class="input-group-text">hari sebelum KGB</span>
                </div>
                <div class="form-text">
                    Pegawai akan melihat notifikasi di dashboard jika jadwal KGB mereka jatuh dalam rentang waktu ini (atau jika sudah terlewat).
                </div>
            </div>
            <button type="submit" class="btn btn-primary">Simpan Pengaturan</button>
        </form>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-header">
        <strong>Daftar Status KGB Pegawai</strong>
        <span class="badge bg-secondary ms-1"><?= count($allEmployees) ?></span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-striped table-bordered mb-0">
                <thead class="table-dark">
                <tr>
                    <th>#</th>
                    <th>Nama Pegawai</th>
                    <th>Jadwal KGB</th>
                    <th>Status</th>
                    <th>Aksi</th>
                </tr>
                </thead>
                <tbody>
                <?php if (!$allEmployees): ?>
                    <tr><td colspan="5" class="text-center py-3">Belum ada data pegawai.</td></tr>
                <?php else: ?>
                    <?php foreach ($allEmployees as $index => $row): ?>
                        <tr>
                            <td><?= $index + 1 ?></td>
                            <td><?= h($row['nama']) ?></td>
                            <td>
                                <?= h($row['next_date']) ?>
                                <small class="text-muted d-block">
                                    (<?= $row['days_remaining'] === 99999 ? '?' : h($row['days_remaining']) ?> hari lagi)
                                </small>
                            </td>
                            <td>
                                <?php
                                $statusClass = 'secondary';
                                $statusText = $row['status'];
                                if ($row['status'] === 'Overdue') {
                                    $statusClass = 'danger';
                                    $statusText = 'TERLEWAT';
                                } elseif ($row['days_remaining'] <= $settings['days_before']) {
                                    $statusClass = 'warning text-dark';
                                    $statusText = 'SEGERA (' . $row['status'] . ')';
                                } else {
                                    $statusClass = 'success';
                                    $statusText = 'AMAN';
                                }
                                
                                if (($row['raw_status_pengajuan'] ?? '') === 'submitted') {
                                    $statusClass = 'info';
                                    $statusText = 'SUBMITTED';
                                }
                                ?>
                                <span class="badge bg-<?= $statusClass ?>"><?= $statusText ?></span>
                            </td>
                            <td>
                                <form method="POST" onsubmit="return confirm('Kirim notifikasi ke <?= h($row['nama']) ?>?');">
                                    <input type="hidden" name="action" value="send_notification_manual">
                                    <input type="hidden" name="user_id" value="<?= (int) $row['user_id'] ?>">
                                    <button class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-bell"></i> Ingatkan
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

<?php include '../../templates/footer.php'; ?>
