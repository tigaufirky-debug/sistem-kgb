#!/usr/bin/env php
<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/reminder_kgb_service.php';

$dryRun = in_array('--dry-run', $argv, true);

$summary = runKgbReminder($pdo, [
    'dry_run' => $dryRun,
    'respect_send_time' => true,
    'source' => 'cron',
]);

echo "KGB Reminder Run\n";
echo "target_date : {$summary['target_date']}\n";
echo "checked     : {$summary['checked']}\n";
echo "matched     : {$summary['matched']}\n";
echo "sent        : {$summary['sent']}\n";
echo "skipped     : {$summary['skipped']}\n";
echo "failed      : {$summary['failed']}\n";
echo "dry_run     : " . ($summary['dry_run'] ? 'yes' : 'no') . "\n";
if (!empty($summary['deferred_until_send_time'])) {
    echo "status      : deferred (waiting send_time {$summary['send_time']})\n";
}

if ($summary['failed'] > 0) {
    exit(1);
}

exit(0);
