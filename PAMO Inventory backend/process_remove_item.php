<?php
session_start();
header('Content-Type: application/json');

require_once '../Includes/connection.php';
require_once '../Includes/MonthlyInventoryManager.php';
require_once '../PAMO_PAGES/includes/config_functions.php';

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    die(json_encode([
        'success' => false,
        'message' => 'User not logged in'
    ]));
}

// Ensure user is PAMO employee
$role = strtoupper($_SESSION['role_category'] ?? '');
$programAbbr = strtoupper($_SESSION['program_abbreviation'] ?? '');
if (!($role === 'EMPLOYEE' && $programAbbr === 'PAMO')) {
    die(json_encode([
        'success' => false,
        'message' => 'Unauthorized access. Only PAMO employees can remove items.'
    ]));
}

// Ensure autocommit is disabled for proper transaction handling
$conn->setAttribute(PDO::ATTR_AUTOCOMMIT, FALSE);

// Ensure we start with a clean transaction state
try {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
} catch (PDOException $e) {
    // Ignore errors during cleanup
}

$monthlyInventory = new MonthlyInventoryManager($conn);

// Get POST data
$pulloutOrderNumber = trim($_POST['pulloutOrderNumber'] ?? '');
$itemIds = is_array($_POST['itemId'] ?? []) ? $_POST['itemId'] : [$_POST['itemId'] ?? ''];
$quantitiesToRemove = is_array($_POST['quantityToRemove'] ?? []) ? $_POST['quantityToRemove'] : [$_POST['quantityToRemove'] ?? 0];
$removalReasons = is_array($_POST['removalReason'] ?? []) ? $_POST['removalReason'] : [$_POST['removalReason'] ?? ''];

// Validation
if (empty($pulloutOrderNumber)) {
    die(json_encode([
        'success' => false,
        'message' => 'Pullout Order Number is required'
    ]));
}

if (count($itemIds) !== count($quantitiesToRemove) || count($itemIds) !== count($removalReasons)) {
    die(json_encode([
        'success' => false,
        'message' => 'Mismatched item data'
    ]));
}

// Check if pullout order number already exists
try {
    $checkStmt = $conn->prepare("SELECT COUNT(*) FROM inventory_removals WHERE pullout_order_number = ?");
    $checkStmt->execute([$pulloutOrderNumber]);
    if ($checkStmt->fetchColumn() > 0) {
        die(json_encode([
            'success' => false,
            'message' => 'Pullout Order Number already exists. Please use a unique number.'
        ]));
    }
} catch (PDOException $e) {
    die(json_encode([
        'success' => false,
        'message' => 'Error checking pullout order number: ' . $e->getMessage()
    ]));
}

// Start transaction for item removal process
try {
    if ($conn->inTransaction()) {
        // If somehow still in transaction, roll it back first
        $conn->rollBack();
    }
    $conn->beginTransaction();
} catch (PDOException $e) {
    die(json_encode([
        'success' => false,
        'message' => 'Error starting transaction: ' . $e->getMessage()
    ]));
}

try {
    $removedItems = [];
    $user_id = $_SESSION['user_id'];

    for ($i = 0; $i < count($itemIds); $i++) {
        $itemId = trim($itemIds[$i]);
        $quantityToRemove = intval($quantitiesToRemove[$i]);
        $removalReason = trim($removalReasons[$i]);

        // Validation
        if (empty($itemId)) {
            throw new Exception("Item selection is required for item #" . ($i + 1));
        }

        if ($quantityToRemove <= 0) {
            throw new Exception("Quantity to remove must be greater than 0 for item #" . ($i + 1));
        }

        if (empty($removalReason)) {
            throw new Exception("Removal reason is required for item #" . ($i + 1));
        }

        // Get item details with row lock
        $sql = "SELECT * FROM inventory WHERE item_code = ? FOR UPDATE";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$itemId]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$item) {
            throw new Exception("Item not found: $itemId");
        }

        // Check if there's enough quantity to remove
        if ($item['actual_quantity'] < $quantityToRemove) {
            throw new Exception("Insufficient stock for item {$item['item_name']} ({$item['item_code']}). Current stock: {$item['actual_quantity']}, Requested removal: $quantityToRemove");
        }

        // Update inventory quantity (deduct)
        $updateSql = "UPDATE inventory SET actual_quantity = actual_quantity - ? WHERE item_code = ?";
        $updateStmt = $conn->prepare($updateSql);
        $updateStmt->execute([$quantityToRemove, $itemId]);
        
        // Verify the update was successful
        $rowsAffected = $updateStmt->rowCount();
        if ($rowsAffected === 0) {
            throw new Exception("Failed to update inventory quantity for item: $itemId");
        }

        // Record the removal in inventory_removals table (audit trail)
        $insertSql = "INSERT INTO inventory_removals 
                     (pullout_order_number, item_code, item_name, category, size, quantity_removed, removal_reason, removed_by) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $insertStmt = $conn->prepare($insertSql);
        $insertStmt->execute([
            $pulloutOrderNumber,
            $item['item_code'],
            $item['item_name'],
            $item['category'],
            $item['sizes'],
            $quantityToRemove,
            $removalReason,
            $user_id
        ]);

        // Record the removal in monthly inventory (as adjustment/removal)
        // Pass false to $useTransaction since we're already in a transaction
        try {
            $monthlyInventory->recordRemoval(
                $item['item_code'],
                $quantityToRemove,
                $user_id,
                $removalReason,
                false  // Don't start a new transaction - we're already in one
            );
        } catch (Exception $monthlyError) {
            // Log the error but don't fail the removal - monthly inventory is supplementary
            error_log("Monthly inventory update failed for {$item['item_code']}: " . $monthlyError->getMessage());
            // Continue with the removal process
        }

        // Log the removal action to audit trail
        $auditDescription = "Removed {$quantityToRemove} unit(s) of {$item['item_name']} ({$item['item_code']}) - Size: {$item['sizes']}. Pullout Order: {$pulloutOrderNumber}. Reason: {$removalReason}";
        logActivity($conn, 'Removed Item', $auditDescription, $user_id);

        $removedItems[] = [
            'item_code' => $item['item_code'],
            'item_name' => $item['item_name'],
            'size' => $item['sizes'],
            'quantity' => $quantityToRemove,
            'reason' => $removalReason
        ];
    }

    // Commit transaction
    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Items successfully removed from inventory',
        'pullout_order_number' => $pulloutOrderNumber,
        'removed_items' => $removedItems,
        'total_items' => count($removedItems)
    ]);

} catch (Exception $e) {
    // Rollback transaction on error
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
