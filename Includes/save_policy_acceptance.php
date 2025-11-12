<?php
/**
 * Save Policy Acceptance
 * Saves user's policy acceptance status to database
 * Handles both "I understand" and "Don't show again" checkboxes
 */

require_once 'session_start.php';
require_once 'connection.php';

header('Content-Type: application/json');

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method'
    ]);
    exit;
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Not logged in'
    ]);
    exit;
}

$userId = $_SESSION['user_id'];

// Get JSON input
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if ($data === null) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid JSON data'
    ]);
    exit;
}

// Get checkbox values (default to 0 if not provided)
$dontShowAgain = isset($data['dont_show_again']) ? (int)$data['dont_show_again'] : 0;

// Validate values
if ($dontShowAgain !== 0 && $dontShowAgain !== 1) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid checkbox values'
    ]);
    exit;
}

try {
    // Update user's policy acceptance (using PDO)
    $stmt = $conn->prepare("
        UPDATE account 
        SET 
            policy_acknowledged = 1,
            policy_acknowledged_at = NOW(),
            dont_show_policy_again = :dont_show
        WHERE id = :user_id
    ");
    
    $stmt->bindParam(':dont_show', $dontShowAgain, PDO::PARAM_INT);
    $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
    
    if (!$stmt->execute()) {
        throw new Exception("Database execute failed");
    }
    
    // Also update session to prevent modal from showing again this session
    $_SESSION['policy_acknowledged'] = true;
    
    echo json_encode([
        'success' => true,
        'message' => 'Policy acceptance saved successfully',
        'dont_show_again' => $dontShowAgain
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error saving policy acceptance: ' . $e->getMessage()
    ]);
}
?>
