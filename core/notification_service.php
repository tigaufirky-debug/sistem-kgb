<?php

function ensureNotificationTable(PDO $pdo)
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS notifications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            type VARCHAR(20) NOT NULL DEFAULT 'info',
            message TEXT NOT NULL,
            is_read TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user_read (user_id, is_read),
            INDEX idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
}

function createNotification(PDO $pdo, $userId, $message, $type = 'info')
{
    ensureNotificationTable($pdo);
    
    $stmt = $pdo->prepare("
        INSERT INTO notifications (user_id, message, type, is_read, created_at)
        VALUES (?, ?, ?, 0, NOW())
    ");
    return $stmt->execute([$userId, $message, $type]);
}

function getUnreadNotifications(PDO $pdo, $userId)
{
    ensureNotificationTable($pdo);

    $stmt = $pdo->prepare("
        SELECT *
        FROM notifications
        WHERE user_id = ? AND is_read = 0
        ORDER BY created_at DESC
    ");
    $stmt->execute([$userId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function markNotificationAsRead(PDO $pdo, $notificationId, $userId)
{
    ensureNotificationTable($pdo);

    $stmt = $pdo->prepare("
        UPDATE notifications
        SET is_read = 1
        WHERE id = ? AND user_id = ?
    ");
    return $stmt->execute([$notificationId, $userId]);
}

function markAllNotificationsAsRead(PDO $pdo, $userId)
{
    ensureNotificationTable($pdo);

    $stmt = $pdo->prepare("
        UPDATE notifications
        SET is_read = 1
        WHERE user_id = ? AND is_read = 0
    ");
    return $stmt->execute([$userId]);
}
