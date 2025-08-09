<?php
require_once '../Includes/connection.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $userId = $_POST['userId'];
        $newStatus = $_POST['status'];

        // Validate status
        if (!in_array($newStatus, ['active', 'inactive'])) {
            throw new Exception('Invalid status value');
        }

        // Update the status in the database
        $stmt = $conn->prepare("UPDATE account SET status = ? WHERE id_number = ?");
        $result = $stmt->execute([$newStatus, $userId]);

        if ($result) {
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