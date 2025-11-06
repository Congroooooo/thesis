<?php
/**
 * Reject Exchange Request
 * Admin endpoint to reject an exchange
 */

session_start();
header('Content-Type: application/json');
require_once '../Includes/connection.php';
require_once '../Includes/exchange_helpers.php';

// Check admin access
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$role = strtoupper($_SESSION['role_category'] ?? '');
$programAbbr = strtoupper($_SESSION['program_abbreviation'] ?? '');
if (!($role === 'EMPLOYEE' && $programAbbr === 'PAMO')) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$exchange_id = isset($_POST['exchange_id']) ? intval($_POST['exchange_id']) : 0;
$reason = isset($_POST['reason']) ? trim($_POST['reason']) : '';

if (!$exchange_id || !$reason) {
    echo json_encode(['success' => false, 'message' => 'Exchange ID and reason required']);
    exit;
}

try {
    $conn->beginTransaction();
    
    // Get exchange details
    $stmt = $conn->prepare("SELECT * FROM order_exchanges WHERE id = ? AND status = 'pending'");
    $stmt->execute([$exchange_id]);
    $exchange = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$exchange) {
        throw new Exception('Exchange not found or not pending');
    }
    
    // Update exchange status
    $update_stmt = $conn->prepare("
        UPDATE order_exchanges 
        SET status = 'rejected',
            rejection_reason = ?,
            approved_by = ?,
            approved_date = NOW(),
            updated_at = NOW()
        WHERE id = ?
    ");
    $update_stmt->execute([$reason, $_SESSION['user_id'], $exchange_id]);
    
    // Log activity
    logExchangeActivity(
        $conn,
        $exchange_id,
        'rejected',
        "Exchange rejected by admin. Reason: " . $reason,
        $_SESSION['user_id']
    );
    
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Exchange rejected successfully'
    ]);
    
} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    
    error_log("Reject exchange error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
