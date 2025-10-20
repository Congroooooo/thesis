<?php
require_once '../Includes/connection.php';
header('Content-Type: application/json');
session_start();

// Check authorization
if (!isset($_SESSION['user_id']) || strtoupper($_SESSION['role_category'] ?? '') !== 'EMPLOYEE' || strtoupper($_SESSION['program_abbreviation'] ?? '') !== 'ADMIN') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    $userId = trim($_POST['id'] ?? '');
    
    if (empty($userId)) {
        throw new Exception('User ID is required');
    }

    // Fetch user details
    if (filter_var($userId, FILTER_VALIDATE_EMAIL)) {
        $stmt = $conn->prepare('SELECT last_name, birthday FROM account WHERE email = ?');
    } else {
        $stmt = $conn->prepare('SELECT last_name, birthday FROM account WHERE id_number = ?');
    }
    
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        throw new Exception('User not found');
    }
    
    if (empty($user['last_name']) || empty($user['birthday'])) {
        throw new Exception('Unable to generate default password. Missing user information.');
    }
    
    // Generate default password: sanitized_last_name + birthday in mdY format
    $sanitizedLastName = preg_replace('/\s+/', '', strtolower($user['last_name']));
    $birthdayObj = new DateTime($user['birthday']);
    $defaultPassword = $sanitizedLastName . $birthdayObj->format('mdY');
    
    // Hash the password
    $hashedPassword = password_hash($defaultPassword, PASSWORD_DEFAULT);
    
    // Update the password
    if (filter_var($userId, FILTER_VALIDATE_EMAIL)) {
        $updateStmt = $conn->prepare('UPDATE account SET password = ? WHERE email = ?');
    } else {
        $updateStmt = $conn->prepare('UPDATE account SET password = ? WHERE id_number = ?');
    }
    
    $updateStmt->execute([$hashedPassword, $userId]);
    
    if ($updateStmt->rowCount() > 0) {
        echo json_encode([
            'success' => true,
            'message' => 'Password successfully reset to default'
        ]);
    } else {
        throw new Exception('Failed to reset password');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    error_log('Reset Password Error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
exit;
?>
