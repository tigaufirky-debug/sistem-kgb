<?php
require_once 'config/database.php';
require_once 'core/kgb.php';
require_once 'core/reminder_kgb_service.php';

echo "Testing getEmployeesWithUpcomingKgb with 9999 days...\n";
$results = getEmployeesWithUpcomingKgb($pdo, 9999);

if (empty($results)) {
    echo "No employees found within 9999 days.\n";
    
    // Check raw connection
    $count = $pdo->query("SELECT COUNT(*) FROM pegawai")->fetchColumn();
    echo "Total rows in 'pegawai' table: $count\n";
    
    $users = $pdo->query("SELECT COUNT(*) FROM users WHERE role='pegawai'")->fetchColumn();
    echo "Total users with role='pegawai': $users\n";
} else {
    echo "Found " . count($results) . " employees:\n";
    foreach ($results as $r) {
        echo "- Name: " . $r['nama'] . ", Next KGB: " . $r['next_date'] . ", Days Remaining: " . $r['days_remaining'] . "\n";
    }
}
