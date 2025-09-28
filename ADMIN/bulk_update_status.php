<?php
require_once '../Includes/connection.php';

header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) { 
    session_start(); 
}

if (!isset($_SESSION['user_id']) || 
    strtoupper($_SESSION['role_category'] ?? '') !== 'EMPLOYEE' || 
    strtoupper($_SESSION['program_abbreviation'] ?? '') !== 'ADMIN') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input) {
            throw new Exception('Invalid JSON input');
        }
        
        $userIds = $input['userIds'] ?? [];
        $newStatus = $input['status'] ?? '';

        if (empty($userIds) || !is_array($userIds)) {
            throw new Exception('No user IDs provided');
        }

        if (!in_array($newStatus, ['active', 'inactive'])) {
            throw new Exception('Invalid status value');
        }

        if (count($userIds) > 100) {
            throw new Exception('Too many users selected. Maximum 100 users per bulk operation.');
        }

        $conn->beginTransaction();
        
        $updated_count = 0;
        $errors = [];

        foreach ($userIds as $userId) {
            try {
                if (filter_var($userId, FILTER_VALIDATE_EMAIL)) {
                    $stmt = $conn->prepare("UPDATE account SET status = ? WHERE email = ?");
                } else {
                    $stmt = $conn->prepare("UPDATE account SET status = ? WHERE id_number = ?");
                }
                
                $result = $stmt->execute([$newStatus, $userId]);
                
                if ($result && $stmt->rowCount() > 0) {
                    $updated_count++;
                } else {
                    $errors[] = "Failed to update user: " . $userId;
                }
                
            } catch (Exception $e) {
                $errors[] = "Error updating user {$userId}: " . $e->getMessage();
            }
        }

        if ($updated_count > 0) {
            $conn->commit();
            
            $response = [
                'success' => true,
                'message' => "Successfully updated {$updated_count} user(s)",
                'updated_count' => $updated_count,
                'total_requested' => count($userIds)
            ];
            
            if (!empty($errors)) {
                $response['partial_errors'] = $errors;
                $response['message'] .= ". Some updates failed.";
            }
            
            echo json_encode($response);
        } else {
            $conn->rollback();
            throw new Exception('No users were updated. ' . implode('; ', $errors));
        }

    } catch (Exception $e) {
        if ($conn->inTransaction()) {
            $conn->rollback();
        }
        
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
?>