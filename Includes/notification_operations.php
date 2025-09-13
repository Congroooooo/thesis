<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'connection.php';
require_once 'notifications.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $response = ['success' => false, 'message' => '', 'notifications' => [], 'count' => 0];
    
    if (!isset($_SESSION['user_id'])) {
        $response['message'] = 'User not authenticated';
        echo json_encode($response);
        exit;
    }

    if (isset($_POST['action'])) {
        try {
            switch ($_POST['action']) {
                case 'get_notifications':
                    $user_id = $_SESSION['user_id'];
                    $notifications = getUnreadNotifications($conn, $user_id);
                    $count = getNotificationCount($conn, $user_id);

                    foreach ($notifications as &$notification) {
                        $notification['formatted_message'] = $notification['message'];
                        $notification['time_ago'] = getTimeAgo(strtotime($notification['created_at']));
                    }

                    $response['success'] = true;
                    $response['notifications'] = $notifications;
                    $response['count'] = $count;
                    break;

                case 'mark_as_read':
                    if (!isset($_POST['notification_id'])) {
                        throw new Exception('Notification ID is required');
                    }

                    $notification_id = $_POST['notification_id'];
                    if (markNotificationAsRead($conn, $notification_id)) {
                        $response['success'] = true;
                        $response['message'] = 'Notification marked as read';
                    } else {
                        throw new Exception('Failed to mark notification as read');
                    }
                    break;

                case 'clear_all':
                    if (clearAllNotifications($conn, $_SESSION['user_id'])) {
                        $response['success'] = true;
                        $response['message'] = 'All notifications cleared';
                    } else {
                        throw new Exception('Failed to clear notifications');
                    }
                    break;

                default:
                    throw new Exception('Invalid action');
            }
        } catch (Exception $e) {
            $response['success'] = false;
            $response['message'] = $e->getMessage();
        }
    } else {
        $response['message'] = 'Action is required';
    }

    echo json_encode($response);
    exit;
}

function getTimeAgo($timestamp) {
    $current_time = time();
    $time_difference = $current_time - $timestamp;
    
    if ($time_difference < 60) {
        return 'Just now';
    } elseif ($time_difference < 3600) {
        $minutes = floor($time_difference / 60);
        return $minutes . ' minute' . ($minutes > 1 ? 's' : '') . ' ago';
    } elseif ($time_difference < 86400) {
        $hours = floor($time_difference / 3600);
        return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
    } elseif ($time_difference < 604800) {
        $days = floor($time_difference / 86400);
        return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
    } else {
        return date('M j, Y g:i A', $timestamp);
    }
}
?> 