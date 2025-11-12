<?php
/**
 * Check Policy Status
 * Determines if the current user needs to see the Policy and Terms modal
 * Returns JSON response with should_show flag
 */

require_once 'session_start.php';
require_once 'connection.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false,
        'should_show' => false,
        'message' => 'Not logged in'
    ]);
    exit;
}

$userId = $_SESSION['user_id'];

// Check if user is Admin or PAMO (they should not see the modal)
$programAbbreviation = $_SESSION['program_abbreviation'] ?? '';
if ($programAbbreviation === 'ADMIN' || $programAbbreviation === 'PAMO') {
    echo json_encode([
        'success' => true,
        'should_show' => false,
        'message' => 'Admin/PAMO users exempt'
    ]);
    exit;
}

try {
    // Check user's policy acceptance status (using PDO)
    $stmt = $conn->prepare("
        SELECT 
            policy_acknowledged,
            dont_show_policy_again
        FROM account 
        WHERE id = :user_id
    ");
    
    $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
    $stmt->execute();
    
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$row) {
        echo json_encode([
            'success' => false,
            'should_show' => false,
            'message' => 'User not found'
        ]);
        exit;
    }
    
    $policyAcknowledged = isset($row['policy_acknowledged']) ? (int)$row['policy_acknowledged'] : 0;
    $dontShowAgain = isset($row['dont_show_policy_again']) ? (int)$row['dont_show_policy_again'] : 0;
    
    // Show modal if they haven't checked "don't show again"
    $shouldShow = ($dontShowAgain == 0);
    
    echo json_encode([
        'success' => true,
        'should_show' => $shouldShow,
        'user_id' => $userId
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'should_show' => false,
        'message' => 'Error checking policy status'
    ]);
}
?>
