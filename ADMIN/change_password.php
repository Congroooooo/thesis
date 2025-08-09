<?php
require_once '../Includes/connection.php';
header('Content-Type: application/json');
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'messages' => ['error' => 'Method not allowed']]);
    exit;
}

$userId = $_POST['id'] ?? '';
$newPassword = $_POST['newPassword'] ?? '';
$confirmPassword = $_POST['confirmPassword'] ?? '';

$response = ['success' => false, 'messages' => []];

if ($newPassword !== $confirmPassword) {
    $response['messages']['confirmPassword'] = 'New password and confirmation do not match';
}

if (empty($response['messages'])) {
    $hashedNewPassword = password_hash($newPassword, PASSWORD_DEFAULT);
    $updateStmt = $conn->prepare('UPDATE account SET password = ? WHERE id_number = ?');

    try {
        $updateStmt->execute([$hashedNewPassword, $userId]);
        $response['success'] = true;
        $response['messages']['success'] = 'Password successfully updated';
    } catch (PDOException $e) {
        $response['messages']['error'] = 'Error updating password. Please try again.';
    }
}

echo json_encode($response);
exit;