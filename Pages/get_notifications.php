<?php
session_start();
require_once '../Includes/connection.php';
require_once '../Includes/notifications.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'User not authenticated']);
    exit;
}

try {
    $notifications = getUnreadNotifications($conn, $_SESSION['user_id']);
    $count = getNotificationCount($conn, $_SESSION['user_id']);
    
    echo json_encode([
        'success' => true,
        'notifications' => $notifications,
        'count' => $count 
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?> 