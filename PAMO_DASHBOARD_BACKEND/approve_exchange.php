<?php
/**
 * Approve Exchange Request
 * Admin endpoint to approve an exchange
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

if (!$exchange_id) {
    echo json_encode(['success' => false, 'message' => 'Exchange ID required']);
    exit;
}

try {
    $conn->beginTransaction();
    
    // Get exchange details
    $stmt = $conn->prepare("
        SELECT oe.*, o.order_number 
        FROM order_exchanges oe
        JOIN orders o ON oe.order_id = o.id
        WHERE oe.id = ? AND oe.status = 'pending'
    ");
    $stmt->execute([$exchange_id]);
    $exchange = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$exchange) {
        throw new Exception('Exchange not found or not pending');
    }
    
    // Get exchange items
    $items_stmt = $conn->prepare("SELECT * FROM order_exchange_items WHERE exchange_id = ?");
    $items_stmt->execute([$exchange_id]);
    $items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($items)) {
        throw new Exception('No items found for this exchange');
    }
    
    // DO NOT UPDATE INVENTORY WHEN APPROVING
    // Inventory should only be updated when the exchange is marked as "completed"
    // Exchange flow:
    // 1. Request created (pending)
    // 2. Admin approves (status = 'approved') → NO inventory change yet
    // 3. Customer pays price difference (if applicable)
    // 4. Admin marks as completed → THEN inventory updates
    
    // Update exchange status
    $update_stmt = $conn->prepare("
        UPDATE order_exchanges 
        SET status = 'approved',
            approved_by = ?,
            approved_date = NOW(),
            updated_at = NOW()
        WHERE id = ?
    ");
    $update_stmt->execute([$_SESSION['user_id'], $exchange_id]);
    
    // Log activity
    logExchangeActivity(
        $conn,
        $exchange_id,
        'approved',
        "Exchange approved by admin. Awaiting completion for inventory update.",
        $_SESSION['user_id']
    );
    
    // Log activity to exchange_activities table (internal tracking only)
    logExchangeActivity(
        $conn,
        $exchange_id,
        'approved',
        "Exchange approved by admin. Awaiting completion for inventory update.",
        $_SESSION['user_id']
    );
    
    // Note: No activity log to activities table for approval
    // Only slip generation and completion are logged to audit trail
    
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Exchange approved successfully'
    ]);
    
} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    
    error_log("Approve exchange error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
