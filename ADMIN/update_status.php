<?php
require_once '../Includes/connection.php';

header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_id']) || strtoupper($_SESSION['role_category'] ?? '') !== 'EMPLOYEE' || strtoupper($_SESSION['program_abbreviation'] ?? '') !== 'ADMIN') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $userId = $_POST['userId'];
        $newStatus = $_POST['status'];

        if (!in_array($newStatus, ['active', 'inactive'])) {
            throw new Exception('Invalid status value');
        }

        if (filter_var($userId, FILTER_VALIDATE_EMAIL)) {
            $stmt = $conn->prepare("UPDATE account SET status = ? WHERE email = ?");
        } else {
            $stmt = $conn->prepare("UPDATE account SET status = ? WHERE id_number = ?");
        }
        
        $result = $stmt->execute([$newStatus, $userId]);

        if ($result && $stmt->rowCount() > 0) {
            echo json_encode([
                'success' => true,
                'message' => 'Status updated successfully',
                'status' => $newStatus
            ]);
        } else {
            throw new Exception('Failed to update status');
        }

    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method'
    ]);
}