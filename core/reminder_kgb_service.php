<?php
require_once __DIR__ . '/kgb.php';
require_once __DIR__ . '/notification_service.php';

function getReminderSmtpConfig()
{
    $config = [
        'host' => getenv('REMINDER_SMTP_HOST') ?: 'smtp.gmail.com',
        'port' => (int) (getenv('REMINDER_SMTP_PORT') ?: 465),
        'encryption' => strtolower((string) (getenv('REMINDER_SMTP_ENCRYPTION') ?: 'ssl')),
        'username' => (string) (getenv('REMINDER_SMTP_USERNAME') ?: getenv('REMINDER_GMAIL_USER') ?: ''),
        'password' => (string) (getenv('REMINDER_SMTP_PASSWORD') ?: getenv('REMINDER_GMAIL_APP_PASSWORD') ?: ''),
        'from_email' => (string) (getenv('REMINDER_FROM_EMAIL') ?: ''),
        'from_name' => (string) (getenv('REMINDER_FROM_NAME') ?: 'Sistem KGB'),
        'timeout' => (int) (getenv('REMINDER_SMTP_TIMEOUT') ?: 20),
    ];

    $file = __DIR__ . '/../config/reminder_mail.php';
    if (is_file($file)) {
        $fileConfig = require $file;
        if (is_array($fileConfig)) {
            $config = array_merge($config, $fileConfig);
        }
    }

    if ($config['from_email'] === '') {
        $config['from_email'] = (string) $config['username'];
    }

    return $config;
}

function smtpReadResponse($socket, array $expectedCodes, &$rawResponse = '')
{
    $rawResponse = '';
    while (($line = fgets($socket, 515)) !== false) {
        $rawResponse .= $line;
        if (strlen($line) < 4) {
            continue;
        }
        if ($line[3] === ' ') {
            break;
        }
    }

    if ($rawResponse === '') {
        return false;
    }

    $code = (int) substr($rawResponse, 0, 3);
    return in_array($code, $expectedCodes, true);
}

function smtpSendCommand($socket, $command, array $expectedCodes, &$errorMessage)
{
    if (fwrite($socket, $command . "\r\n") === false) {
        $errorMessage = 'Gagal menulis perintah SMTP.';
        return false;
    }

    $response = '';
    if (!smtpReadResponse($socket, $expectedCodes, $response)) {
        $errorMessage = 'SMTP reject untuk command [' . $command . ']: ' . trim($response);
        return false;
    }

    return true;
}

function sendEmailViaSmtp(array $smtpConfig, $to, $subject, $message, &$errorMessage = null)
{
    $host = trim((string) ($smtpConfig['host'] ?? ''));
    $port = (int) ($smtpConfig['port'] ?? 0);
    $encryption = strtolower(trim((string) ($smtpConfig['encryption'] ?? 'ssl')));
    $username = trim((string) ($smtpConfig['username'] ?? ''));
    $password = (string) ($smtpConfig['password'] ?? '');
    $fromEmail = trim((string) ($smtpConfig['from_email'] ?? ''));
    $fromName = trim((string) ($smtpConfig['from_name'] ?? 'Sistem KGB'));
    $timeout = max(5, (int) ($smtpConfig['timeout'] ?? 20));

    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        $errorMessage = 'Email tujuan tidak valid.';
        return false;
    }
    if (!filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
        $errorMessage = 'REMINDER_FROM_EMAIL tidak valid.';
        return false;
    }
    if ($host === '' || $port <= 0 || $username === '' || $password === '') {
        $errorMessage = 'Konfigurasi SMTP Gmail belum lengkap (host/port/username/password).';
        return false;
    }

    $transport = ($encryption === 'ssl') ? 'ssl://' : 'tcp://';
    $socket = @stream_socket_client(
        $transport . $host . ':' . $port,
        $errno,
        $errstr,
        $timeout
    );
    if (!$socket) {
        $errorMessage = 'Gagal koneksi SMTP: ' . $errstr . ' (' . $errno . ')';
        return false;
    }

    stream_set_timeout($socket, $timeout);

    $response = '';
    if (!smtpReadResponse($socket, [220], $response)) {
        fclose($socket);
        $errorMessage = 'SMTP greeting invalid: ' . trim($response);
        return false;
    }

    $hostname = gethostname() ?: 'localhost';
    if (!smtpSendCommand($socket, 'EHLO ' . $hostname, [250], $errorMessage)) {
        fclose($socket);
        return false;
    }

    if ($encryption === 'tls') {
        if (!smtpSendCommand($socket, 'STARTTLS', [220], $errorMessage)) {
            fclose($socket);
            return false;
        }
        if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            fclose($socket);
            $errorMessage = 'Gagal mengaktifkan TLS pada koneksi SMTP.';
            return false;
        }
        if (!smtpSendCommand($socket, 'EHLO ' . $hostname, [250], $errorMessage)) {
            fclose($socket);
            return false;
        }
    }

    if (!smtpSendCommand($socket, 'AUTH LOGIN', [334], $errorMessage)
        || !smtpSendCommand($socket, base64_encode($username), [334], $errorMessage)
        || !smtpSendCommand($socket, base64_encode($password), [235], $errorMessage)
        || !smtpSendCommand($socket, 'MAIL FROM:<' . $fromEmail . '>', [250], $errorMessage)
        || !smtpSendCommand($socket, 'RCPT TO:<' . $to . '>', [250, 251], $errorMessage)
        || !smtpSendCommand($socket, 'DATA', [354], $errorMessage)
    ) {
        fclose($socket);
        return false;
    }

    $headers = [
        'Date: ' . date('r'),
        'From: ' . $fromName . ' <' . $fromEmail . '>',
        'To: <' . $to . '>',
        'Subject: ' . $subject,
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: 8bit',
    ];

    $normalizedBody = str_replace(["\r\n", "\r"], "\n", $message);
    $bodyLines = explode("\n", $normalizedBody);
    foreach ($bodyLines as &$line) {
        if (isset($line[0]) && $line[0] === '.') {
            $line = '.' . $line;
        }
    }
    unset($line);

    $payload = implode("\r\n", $headers) . "\r\n\r\n" . implode("\r\n", $bodyLines) . "\r\n.\r\n";
    if (fwrite($socket, $payload) === false) {
        fclose($socket);
        $errorMessage = 'Gagal menulis payload email ke SMTP.';
        return false;
    }

    if (!smtpReadResponse($socket, [250], $response)) {
        fclose($socket);
        $errorMessage = 'SMTP menolak isi email: ' . trim($response);
        return false;
    }

    @smtpSendCommand($socket, 'QUIT', [221], $ignoredError);
    fclose($socket);

    return true;
}

function ensureReminderLogTable(PDO $pdo)
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS notif_reminder_kgb_log (
            id INT AUTO_INCREMENT PRIMARY KEY,
            pegawai_id INT NOT NULL,
            user_id INT NOT NULL,
            email VARCHAR(150) NOT NULL,
            reminder_for_date DATE NOT NULL,
            sent_success TINYINT(1) NOT NULL DEFAULT 0,
            sent_at DATETIME NULL,
            source VARCHAR(30) NOT NULL DEFAULT 'cron',
            error_message TEXT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_reminder_date (reminder_for_date),
            INDEX idx_pegawai_date (pegawai_id, reminder_for_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
}

function ensureReminderSettingsTable(PDO $pdo)
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS notif_reminder_kgb_settings (
            id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
            days_before INT NOT NULL DEFAULT 30,
            send_time CHAR(5) NOT NULL DEFAULT '07:00',
            send_to_employee TINYINT(1) NOT NULL DEFAULT 1,
            recipient_emails TEXT NULL,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $columns = $pdo->query("SHOW COLUMNS FROM notif_reminder_kgb_settings LIKE 'send_time'")->fetchAll(PDO::FETCH_ASSOC);
    if (!$columns) {
        $pdo->exec("ALTER TABLE notif_reminder_kgb_settings ADD COLUMN send_time CHAR(5) NOT NULL DEFAULT '07:00' AFTER days_before");
    }

    $exists = $pdo->query("SELECT COUNT(*) FROM notif_reminder_kgb_settings WHERE id = 1")->fetchColumn();
    if ((int) $exists === 0) {
        $stmt = $pdo->prepare("
            INSERT INTO notif_reminder_kgb_settings (id, days_before, send_time, send_to_employee, recipient_emails)
            VALUES (1, 30, '07:00', 1, '')
        ");
        $stmt->execute();
    }
}

function normalizeReminderSendTime($value)
{
    $raw = trim((string) $value);
    if ($raw === '') {
        return null;
    }

    if (!preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', $raw, $matches)) {
        return null;
    }

    return $matches[1] . ':' . $matches[2];
}

function parseReminderEmails($rawEmails)
{
    $raw = trim((string) $rawEmails);
    if ($raw === '') {
        return [];
    }

    $parts = preg_split('/[\s,;]+/', $raw) ?: [];
    $emails = [];
    foreach ($parts as $part) {
        $email = strtolower(trim($part));
        if ($email !== '') {
            $emails[] = $email;
        }
    }

    return array_values(array_unique($emails));
}

function splitValidAndInvalidReminderEmails($rawEmails)
{
    $valid = [];
    $invalid = [];

    foreach (parseReminderEmails($rawEmails) as $email) {
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $valid[] = $email;
        } else {
            $invalid[] = $email;
        }
    }

    return [$valid, $invalid];
}

function getReminderSettings(PDO $pdo)
{
    ensureReminderSettingsTable($pdo);

    $stmt = $pdo->query("
        SELECT days_before, send_time, send_to_employee, recipient_emails
        FROM notif_reminder_kgb_settings
        WHERE id = 1
        LIMIT 1
    ");
    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $daysBefore = isset($row['days_before']) ? (int) $row['days_before'] : 30;
    $daysBefore = max(0, min(365, $daysBefore));
    $sendTime = normalizeReminderSendTime($row['send_time'] ?? '') ?: '07:00';
    $sendToEmployee = isset($row['send_to_employee']) ? (int) $row['send_to_employee'] === 1 : true;
    $recipientEmailsRaw = (string) ($row['recipient_emails'] ?? '');
    [$recipientEmails] = splitValidAndInvalidReminderEmails($recipientEmailsRaw);

    return [
        'days_before' => $daysBefore,
        'send_time' => $sendTime,
        'send_to_employee' => $sendToEmployee,
        'recipient_emails_raw' => $recipientEmailsRaw,
        'recipient_emails' => $recipientEmails,
    ];
}

function saveReminderSettings(PDO $pdo, array $settings)
{
    ensureReminderSettingsTable($pdo);

    $daysBefore = isset($settings['days_before']) ? (int) $settings['days_before'] : 30;
    $daysBefore = max(0, min(365, $daysBefore));
    $sendTime = normalizeReminderSendTime($settings['send_time'] ?? '') ?: '07:00';
    $sendToEmployee = !empty($settings['send_to_employee']) ? 1 : 0;
    $recipientEmailsRaw = trim((string) ($settings['recipient_emails_raw'] ?? ''));
    $recipientEmails = parseReminderEmails($recipientEmailsRaw);

    $stmt = $pdo->prepare("
        UPDATE notif_reminder_kgb_settings
        SET days_before = ?, send_time = ?, send_to_employee = ?, recipient_emails = ?
        WHERE id = 1
    ");
    $stmt->execute([$daysBefore, $sendTime, $sendToEmployee, implode(', ', $recipientEmails)]);
}

function buildReminderRecipients(array $pegawai, $sendToEmployee, array $customRecipients)
{
    $targets = [];
    $seen = [];

    if ($sendToEmployee) {
        $employeeEmail = strtolower(trim((string) ($pegawai['email'] ?? '')));
        if ($employeeEmail !== '' && filter_var($employeeEmail, FILTER_VALIDATE_EMAIL)) {
            $seen[$employeeEmail] = true;
            $targets[] = [
                'email' => $employeeEmail,
                'type' => 'pegawai',
            ];
        }
    }

    foreach ($customRecipients as $customEmail) {
        $email = strtolower(trim((string) $customEmail));
        if ($email === '' || isset($seen[$email])) {
            continue;
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            continue;
        }
        $seen[$email] = true;
        $targets[] = [
            'email' => $email,
            'type' => 'custom',
        ];
    }

    return $targets;
}

function sendReminderEmail($to, $pegawaiNama, $nextDate, $daysBefore, $recipientType = 'pegawai')
{
    $subject = 'Reminder KGB: ' . $pegawaiNama . ' (' . $nextDate . ')';
    if ($recipientType === 'pegawai') {
        $message = "Yth. {$pegawaiNama},\n\n"
            . "Ini adalah pengingat otomatis bahwa jadwal KGB Anda jatuh pada {$nextDate} "
            . "(H-{$daysBefore}).\n"
            . "Silakan login ke Sistem KGB untuk memastikan berkas pengajuan sudah lengkap.\n\n"
            . "Salam,\nSistem KGB";
    } else {
        $message = "Reminder otomatis Sistem KGB.\n\n"
            . "Pegawai: {$pegawaiNama}\n"
            . "Jadwal KGB: {$nextDate}\n"
            . "Lead time: H-{$daysBefore}\n\n"
            . "Silakan tindak lanjuti sesuai kebutuhan.";
    }

    $smtpConfig = getReminderSmtpConfig();
    return sendEmailViaSmtp($smtpConfig, $to, $subject, $message, $smtpError);
}

function runKgbReminder(PDO $pdo, array $options = [])
{
    $savedSettings = getReminderSettings($pdo);
    $daysBefore = isset($options['days_before']) ? (int) $options['days_before'] : (int) $savedSettings['days_before'];
    $daysBefore = max(0, min(365, $daysBefore));
    $sendTime = isset($options['send_time'])
        ? (normalizeReminderSendTime($options['send_time']) ?: (string) $savedSettings['send_time'])
        : (string) $savedSettings['send_time'];
    $sendToEmployee = array_key_exists('send_to_employee', $options)
        ? !empty($options['send_to_employee'])
        : (bool) $savedSettings['send_to_employee'];
    $customRecipients = array_key_exists('recipient_emails', $options)
        ? parseReminderEmails((string) $options['recipient_emails'])
        : $savedSettings['recipient_emails'];
    $dryRun = !empty($options['dry_run']);
    $source = isset($options['source']) ? (string) $options['source'] : 'cron';

    $today = new DateTimeImmutable(date('Y-m-d'));
    $targetDate = $today->add(new DateInterval('P' . max(0, $daysBefore) . 'D'))->format('Y-m-d');
    $respectSendTime = !empty($options['respect_send_time']);
    if ($respectSendTime && !$dryRun) {
        $nowTime = date('H:i');
        if ($nowTime < $sendTime) {
            return [
                'days_before' => $daysBefore,
                'send_time' => $sendTime,
                'send_to_employee' => $sendToEmployee,
                'custom_recipient_count' => count($customRecipients),
                'target_date' => $targetDate,
                'dry_run' => $dryRun,
                'source' => $source,
                'checked' => 0,
                'matched' => 0,
                'sent' => 0,
                'skipped' => 0,
                'failed' => 0,
                'deferred_until_send_time' => true,
                'details' => [],
            ];
        }
    }

    ensureReminderLogTable($pdo);

    $stmt = $pdo->query("
        SELECT p.id AS pegawai_id,
               p.user_id,
               p.tmt_mulai,
               u.nama,
               u.email,
               (
                   SELECT pk.periode_kgb
                   FROM pengajuan_kgb pk
                   WHERE pk.pegawai_id = p.id AND pk.status = 'approved'
                   ORDER BY pk.periode_kgb DESC
                   LIMIT 1
               ) AS latest_approved_periode
        FROM pegawai p
        INNER JOIN users u ON u.id = p.user_id
        WHERE u.role = 'pegawai' AND u.is_active = 1
    ");
    $pegawaiList = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $summary = [
        'days_before' => $daysBefore,
        'send_time' => $sendTime,
        'send_to_employee' => $sendToEmployee,
        'custom_recipient_count' => count($customRecipients),
        'target_date' => $targetDate,
        'dry_run' => $dryRun,
        'source' => $source,
        'checked' => count($pegawaiList),
        'matched' => 0,
        'sent' => 0,
        'skipped' => 0,
        'failed' => 0,
        'deferred_until_send_time' => false,
        'details' => [],
    ];

    foreach ($pegawaiList as $pegawai) {
        $nextDate = calculateNextKgbDate($pegawai['tmt_mulai'], $pegawai['latest_approved_periode']);
        if ($nextDate !== $targetDate) {
            continue;
        }
        $summary['matched']++;

        $targets = buildReminderRecipients($pegawai, $sendToEmployee, $customRecipients);
        if (!$targets) {
            $summary['failed']++;
            $summary['details'][] = [
                'pegawai_id' => (int) $pegawai['pegawai_id'],
                'nama' => $pegawai['nama'],
                'email' => $pegawai['email'],
                'status' => 'failed_no_recipient',
                'error' => 'Tidak ada email tujuan valid untuk pengingat ini.',
            ];
            continue;
        }

        foreach ($targets as $target) {
            $alreadyStmt = $pdo->prepare("
                SELECT id
                FROM notif_reminder_kgb_log
                WHERE pegawai_id = ?
                  AND reminder_for_date = ?
                  AND email = ?
                  AND sent_success = 1
                LIMIT 1
            ");
            $alreadyStmt->execute([
                (int) $pegawai['pegawai_id'],
                $targetDate,
                $target['email'],
            ]);
            if ($alreadyStmt->fetch()) {
                $summary['skipped']++;
                $summary['details'][] = [
                    'pegawai_id' => (int) $pegawai['pegawai_id'],
                    'nama' => $pegawai['nama'],
                    'email' => $target['email'],
                    'recipient_type' => $target['type'],
                    'status' => 'skipped_already_sent',
                ];
                continue;
            }

            $success = true;
            $errorMessage = null;
            if (!$dryRun) {
                $success = sendReminderEmail(
                    $target['email'],
                    $pegawai['nama'],
                    $nextDate,
                    $daysBefore,
                    $target['type']
                );
                if (!$success) {
                    $errorMessage = 'Fungsi mail() gagal mengirim email.';
                }
            }

            if ($dryRun) {
                $summary['sent']++;
                $summary['details'][] = [
                    'pegawai_id' => (int) $pegawai['pegawai_id'],
                    'nama' => $pegawai['nama'],
                    'email' => $target['email'],
                    'recipient_type' => $target['type'],
                    'status' => 'dry_run_ready',
                ];
                continue;
            }

            $insertStmt = $pdo->prepare("
                INSERT INTO notif_reminder_kgb_log
                    (pegawai_id, user_id, email, reminder_for_date, sent_success, sent_at, source, error_message)
                VALUES
                    (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $insertStmt->execute([
                (int) $pegawai['pegawai_id'],
                (int) $pegawai['user_id'],
                $target['email'],
                $targetDate,
                $success ? 1 : 0,
                $success ? date('Y-m-d H:i:s') : null,
                $source,
                $errorMessage,
            ]);

            if ($success) {
                $summary['sent']++;
                $summary['details'][] = [
                    'pegawai_id' => (int) $pegawai['pegawai_id'],
                    'nama' => $pegawai['nama'],
                    'email' => $target['email'],
                    'recipient_type' => $target['type'],
                    'status' => 'sent',
                ];
            } else {
                $summary['failed']++;
                $summary['details'][] = [
                    'pegawai_id' => (int) $pegawai['pegawai_id'],
                    'nama' => $pegawai['nama'],
                    'email' => $target['email'],
                    'recipient_type' => $target['type'],
                    'status' => 'failed',
                    'error' => $errorMessage,
                ];
            }
            }
        
        // New Logic: Create Dashboard Notification
        $notifMessage = "PENGINGAT: Jadwal KGB Anda jatuh pada {$nextDate} (H-{$daysBefore}). Segera ajukan.";
        
        // Check duplicate notification today to avoid spamming
        $checkNotif = $pdo->prepare("
            SELECT id FROM notifications 
            WHERE user_id = ? 
              AND message = ? 
              AND DATE(created_at) = CURDATE()
            LIMIT 1
        ");
        $checkNotif->execute([$pegawai['user_id'], $notifMessage]);
        
        if (!$checkNotif->fetch()) {
             // Only create if not already notified today with same message
             createNotification($pdo, $pegawai['user_id'], $notifMessage, 'warning');
        }
    }
    return $summary;
}

function getEmployeesWithUpcomingKgb(PDO $pdo, $daysBefore)
{
    $stmt = $pdo->query("
        SELECT p.id AS pegawai_id,
               p.user_id,
               p.tmt_mulai,
               u.nama,
               u.email,
               (
                   SELECT pk.periode_kgb
                   FROM pengajuan_kgb pk
                   WHERE pk.pegawai_id = p.id AND pk.status = 'approved'
                   ORDER BY pk.periode_kgb DESC
                   LIMIT 1
               ) AS latest_approved_periode,
               (
                   SELECT status
                   FROM pengajuan_kgb
                   WHERE pegawai_id = p.id
                   ORDER BY id DESC LIMIT 1
               ) as current_status
        FROM pegawai p
        INNER JOIN users u ON u.id = p.user_id
        WHERE u.role = 'pegawai' AND u.is_active = 1
    ");
    $pegawaiList = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $results = [];
    $today = new DateTime(date('Y-m-d'));

    foreach ($pegawaiList as $pegawai) {
        $nextDateStr = calculateNextKgbDate($pegawai['tmt_mulai'], $pegawai['latest_approved_periode']);
        if (!$nextDateStr) {
            continue;
        }

        $nextDate = new DateTime($nextDateStr);
        $diff = $today->diff($nextDate);
        $daysRemaining = (int) $diff->format('%r%a');

        // Logic matches dashboard: show if overdue OR within window, and not submitted
        $isSubmitted = ($pegawai['current_status'] ?? '') === 'submitted';
        
        if (!$isSubmitted && ($daysRemaining < 0 || $daysRemaining <= $daysBefore)) {
            $results[] = [
                'nama' => $pegawai['nama'],
                'next_date' => $nextDateStr,
                'days_remaining' => $daysRemaining,
                'status' => $daysRemaining < 0 ? 'Overdue' : 'Upcoming'
            ];
        }
    }
    
    // Sort by days remaining (overdue first)
    usort($results, function($a, $b) {
        return $a['days_remaining'] <=> $b['days_remaining'];
    });

    return $results;
}

function getAllEmployeesKgbStatus(PDO $pdo)
{
    $stmt = $pdo->query("
        SELECT p.id AS pegawai_id,
               p.user_id,
               p.tmt_mulai,
               u.nama,
               u.email,
               (
                   SELECT pk.periode_kgb
                   FROM pengajuan_kgb pk
                   WHERE pk.pegawai_id = p.id AND pk.status = 'approved'
                   ORDER BY pk.periode_kgb DESC
                   LIMIT 1
               ) AS latest_approved_periode,
               (
                   SELECT status
                   FROM pengajuan_kgb
                   WHERE pegawai_id = p.id
                   ORDER BY id DESC LIMIT 1
               ) as current_status
        FROM pegawai p
        INNER JOIN users u ON u.id = p.user_id
        WHERE u.role = 'pegawai' AND u.is_active = 1
    ");
    $pegawaiList = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $results = [];
    $today = new DateTime(date('Y-m-d'));

    foreach ($pegawaiList as $pegawai) {
        $nextDateStr = calculateNextKgbDate($pegawai['tmt_mulai'], $pegawai['latest_approved_periode']);
        if (!$nextDateStr) {
            $results[] = [
                'pegawai_id' => $pegawai['pegawai_id'],
                'user_id' => $pegawai['user_id'],
                'nama' => $pegawai['nama'],
                'next_date' => '-',
                'days_remaining' => 99999, // Unknown
                'status' => 'Unknown',
                'raw_status_pengajuan' => $pegawai['current_status']
            ];
            continue;
        }

        $nextDate = new DateTime($nextDateStr);
        $diff = $today->diff($nextDate);
        $daysRemaining = (int) $diff->format('%r%a');

        $status = 'Upcoming';
        if ($daysRemaining < 0) {
            $status = 'Overdue';
        }

        $results[] = [
            'pegawai_id' => $pegawai['pegawai_id'],
            'user_id' => $pegawai['user_id'],
            'nama' => $pegawai['nama'],
            'next_date' => $nextDateStr,
            'days_remaining' => $daysRemaining,
            'status' => $status,
            'raw_status_pengajuan' => $pegawai['current_status']
        ];
    }

    // Sort by days remaining (overdue first)
    usort($results, function($a, $b) {
        return $a['days_remaining'] <=> $b['days_remaining'];
    });

    return $results;
}
