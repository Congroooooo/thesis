<?php
date_default_timezone_set('Asia/Manila');
function createNotification($conn, $user_id, $message, $order_number, $type) {
    try {
        $stmt = $conn->prepare("
            INSERT INTO notifications (user_id, message, order_number, type, created_at)
            VALUES (?, ?, ?, ?, NOW())
        ");
        return $stmt->execute([$user_id, $message, $order_number, $type]);
    } catch (PDOException $e) {
        return false;
    }
}

function getUnreadNotifications($conn, $user_id) {
    try {
        $stmt = $conn->prepare("
            SELECT * FROM notifications 
            WHERE user_id = ? 
            ORDER BY created_at DESC
        ");
        $stmt->execute([$user_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting notifications: " . $e->getMessage());
        return [];
    }
}

function markNotificationAsRead($conn, $notification_id) {
    try {
        $stmt = $conn->prepare("
            UPDATE notifications 
            SET is_read = TRUE 
            WHERE id = ?
        ");
        return $stmt->execute([$notification_id]);
    } catch (PDOException $e) {
        error_log("Error marking notification as read: " . $e->getMessage());
        return false;
    }
}

function getNotificationCount($conn, $user_id) {
    try {
        $stmt = $conn->prepare("
            SELECT COUNT(*) as count 
            FROM notifications 
            WHERE user_id = ? AND is_read = FALSE
        ");
        $stmt->execute([$user_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['count'];
    } catch (PDOException $e) {
        error_log("Error getting notification count: " . $e->getMessage());
        return 0;
    }
}

function clearAllNotifications($conn, $user_id) {
    try {
        $stmt = $conn->prepare("
            DELETE FROM notifications 
            WHERE user_id = ?
        ");
        return $stmt->execute([$user_id]);
    } catch (PDOException $e) {
        error_log("Error clearing notifications: " . $e->getMessage());
        return false;
    }
}
?> 