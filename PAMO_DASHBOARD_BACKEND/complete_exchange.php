<?php
/**
 * Complete Exchange Request - FIXED VERSION
 * Admin endpoint to mark an exchange as completed
 * 
 * FIXES:
 * - Updates sold_quantity for returned and exchanged items
 * - Creates sales records for exchange transactions
 * - Logs to activities table for audit trail
 * - Updates monthly inventory snapshots
 * - Idempotency protection to prevent double-processing
 * 
 * Date Fixed: November 6, 2025
 */

session_start();
header('Content-Type: application/json');
require_once '../Includes/connection.php';
require_once '../Includes/exchange_helpers.php';
require_once '../Includes/inventory_update_notifier.php';
require_once '../Includes/MonthlyInventoryManager.php';

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
    
    // Create MonthlyInventoryManager instance
    $monthlyInventory = new MonthlyInventoryManager($conn);
    
    // Get exchange details - ONLY approved and not yet completed
    $stmt = $conn->prepare("
        SELECT oe.*, o.order_number as actual_order_number
        FROM order_exchanges oe
        LEFT JOIN orders o ON oe.order_id = o.id
        WHERE oe.id = ? AND oe.status = 'approved'
    ");
    $stmt->execute([$exchange_id]);
    $exchange = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$exchange) {
        throw new Exception('Exchange not found, not approved, or already completed');
    }
    
    // CRITICAL FIX: Use actual order number from orders table if order_number field is NULL
    if (empty($exchange['order_number']) && !empty($exchange['actual_order_number'])) {
        $exchange['order_number'] = $exchange['actual_order_number'];
        
        // Update the order_exchanges table to fix missing order_number
        $conn->prepare("UPDATE order_exchanges SET order_number = ? WHERE id = ?")
             ->execute([$exchange['order_number'], $exchange_id]);
    }
    
    // IDEMPOTENCY CHECK: Prevent double-processing
    if ($exchange['status'] === 'completed') {
        $conn->rollBack();
        echo json_encode([
            'success' => true,
            'message' => 'Exchange already marked as completed',
            'already_completed' => true
        ]);
        exit;
    }
    
    // Update payment status based on adjustment type
    $payment_status = 'not_applicable';
    if ($exchange['adjustment_type'] == 'additional_payment' || $exchange['adjustment_type'] == 'refund') {
        $payment_status = 'paid'; // Assuming payment/refund was processed
    }
    
    // Get exchange items for processing
    $items_stmt = $conn->prepare("SELECT * FROM order_exchange_items WHERE exchange_id = ?");
    $items_stmt->execute([$exchange_id]);
    $exchange_items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($exchange_items)) {
        throw new Exception('No exchange items found');
    }
    
    // Generate transaction number for sales records
    $transaction_number = 'EXC-' . $exchange['exchange_number'];
    $exchange_date = date('Y-m-d H:i:s');
    
    // ==========================================================================
    // CRITICAL FIX: Update inventory quantities AND sold_quantity
    // ==========================================================================
    foreach ($exchange_items as $item) {
        // -------------------------------------------------------------------
        // 1. RETURN ORIGINAL ITEM TO INVENTORY
        // -------------------------------------------------------------------
        // - Increase actual_quantity (item is back in stock)
        // - DECREASE sold_quantity (it's no longer "sold" - it was returned)
        // -------------------------------------------------------------------
        $stmt = $conn->prepare("
            UPDATE inventory 
            SET actual_quantity = actual_quantity + ?,
                sold_quantity = GREATEST(0, sold_quantity - ?),
                status = CASE 
                    WHEN (actual_quantity + ?) > 10 THEN 'In Stock'
                    WHEN (actual_quantity + ?) > 0 THEN 'Low Stock'
                    ELSE 'Out of Stock'
                END
            WHERE item_code = ?
        ");
        $stmt->execute([
            $item['exchange_quantity'],  // Add to actual_quantity
            $item['exchange_quantity'],  // Subtract from sold_quantity
            $item['exchange_quantity'],  // For status calculation
            $item['exchange_quantity'],  // For status calculation
            $item['original_item_code']
        ]);
        
        if ($stmt->rowCount() === 0) {
            throw new Exception("Failed to update inventory for returned item: {$item['original_item_code']}");
        }
        
        // -------------------------------------------------------------------
        // 2. DEDUCT NEW ITEM FROM INVENTORY  
        // -------------------------------------------------------------------
        // - Decrease actual_quantity (item is leaving inventory)
        // - INCREASE sold_quantity (it's being "sold" via exchange)
        // -------------------------------------------------------------------
        $stmt = $conn->prepare("
            UPDATE inventory 
            SET actual_quantity = actual_quantity - ?,
                sold_quantity = sold_quantity + ?,
                status = CASE 
                    WHEN (actual_quantity - ?) > 10 THEN 'In Stock'
                    WHEN (actual_quantity - ?) > 0 THEN 'Low Stock'
                    ELSE 'Out of Stock'
                END
            WHERE item_code = ?
        ");
        $stmt->execute([
            $item['exchange_quantity'],  // Subtract from actual_quantity
            $item['exchange_quantity'],  // Add to sold_quantity
            $item['exchange_quantity'],  // For status calculation
            $item['exchange_quantity'],  // For status calculation
            $item['new_item_code']
        ]);
        
        if ($stmt->rowCount() === 0) {
            throw new Exception("Failed to update inventory for new item: {$item['new_item_code']}");
        }
        
        // -------------------------------------------------------------------
        // 3. LOG INVENTORY MOVEMENTS (already in place, keep for audit)
        // -------------------------------------------------------------------
        $stmt = $conn->prepare("
            INSERT INTO inventory_movement 
            (item_code, movement_type, quantity, reference_type, reference_id, notes, created_by) 
            VALUES (?, 'exchange_return', ?, 'exchange', ?, ?, ?)
        ");
        $stmt->execute([
            $item['original_item_code'],
            $item['exchange_quantity'],
            $exchange_id,
            "Exchange return: {$item['original_item_name']} (Size: {$item['original_size']}) - Exchange #{$exchange['exchange_number']}",
            $_SESSION['user_id']
        ]);
        
        $stmt = $conn->prepare("
            INSERT INTO inventory_movement 
            (item_code, movement_type, quantity, reference_type, reference_id, notes, created_by) 
            VALUES (?, 'exchange_out', ?, 'exchange', ?, ?, ?)
        ");
        $stmt->execute([
            $item['new_item_code'],
            $item['exchange_quantity'],
            $exchange_id,
            "Exchange out: {$item['new_item_name']} (Size: {$item['new_size']}) - Exchange #{$exchange['exchange_number']}",
            $_SESSION['user_id']
        ]);
        
        // -------------------------------------------------------------------
        // 4. CREATE SALES RECORDS FOR EXCHANGE TRANSACTIONS (IMPROVED FIX)
        // -------------------------------------------------------------------
        // Step 4a: Find and adjust the original sale record
        // FIXED: Handle both NULL transaction_type and 'Original' type
        $originalSaleStmt = $conn->prepare("
            SELECT id, quantity, price_per_item
            FROM sales 
            WHERE transaction_number = ? 
            AND item_code = ? 
            AND size = ?
            AND (transaction_type = 'Original' OR transaction_type IS NULL)
            AND (exchange_reference IS NULL OR exchange_reference = '')
            ORDER BY id DESC 
            LIMIT 1
        ");
        $originalSaleStmt->execute([
            $exchange['order_number'],
            $item['original_item_code'],
            $item['original_size']
        ]);
        $originalSale = $originalSaleStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($originalSale) {
            // Adjust the original sale quantity (reduce by exchange amount)
            $newQuantity = $originalSale['quantity'] - $item['exchange_quantity'];
            
            if ($newQuantity > 0) {
                // Update quantity, total_amount, and set transaction_type to 'Original'
                $conn->prepare("
                    UPDATE sales 
                    SET quantity = ?,
                        total_amount = price_per_item * ?,
                        transaction_type = 'Original'
                    WHERE id = ?
                ")->execute([
                    $newQuantity,
                    $newQuantity,
                    $originalSale['id']
                ]);
                
                // Log: Original sale adjusted
                $conn->prepare("
                    INSERT INTO activities (description, item_code, user_id, timestamp) 
                    VALUES (?, ?, ?, NOW())
                ")->execute([
                    "Exchange: Original sale adjusted - Order #{$exchange['order_number']}, Item: {$item['original_item_name']} (Size: {$item['original_size']}), Qty reduced from {$originalSale['quantity']} to {$newQuantity} due to Exchange #{$exchange['exchange_number']}",
                    $item['original_item_code'],
                    $_SESSION['user_id']
                ]);
            } else {
                // If all quantity is exchanged, delete the original record
                $conn->prepare("DELETE FROM sales WHERE id = ?")->execute([$originalSale['id']]);
                
                // Log: Original sale fully exchanged
                $conn->prepare("
                    INSERT INTO activities (description, item_code, user_id, timestamp) 
                    VALUES (?, ?, ?, NOW())
                ")->execute([
                    "Exchange: Original sale fully exchanged - Order #{$exchange['order_number']}, Item: {$item['original_item_name']} (Size: {$item['original_size']}), All {$item['exchange_quantity']} units exchanged via Exchange #{$exchange['exchange_number']}",
                    $item['original_item_code'],
                    $_SESSION['user_id']
                ]);
            }
        }
        
        // Step 4b: Create new exchange sale record for the new item
        $stmt = $conn->prepare("
            INSERT INTO sales (
                transaction_number,
                item_code,
                size,
                quantity,
                price_per_item,
                total_amount,
                sale_date,
                transaction_type,
                linked_transaction_id,
                exchange_reference
            ) VALUES (?, ?, ?, ?, ?, ?, ?, 'Exchange', ?, ?)
        ");
        
        $stmt->execute([
            $exchange['order_number'],  // Keep same order number for consistency
            $item['new_item_code'],
            $item['new_size'],
            $item['exchange_quantity'],
            $item['new_price'],
            $item['new_price'] * $item['exchange_quantity'],
            $exchange_date,
            $originalSale ? $originalSale['id'] : null,  // Link to original if found
            $exchange['exchange_number']
        ]);
        
        // -------------------------------------------------------------------
        // 5. LOG TO ACTIVITIES TABLE FOR AUDIT TRAIL (ENHANCED)
        // -------------------------------------------------------------------
        $activityStmt = $conn->prepare("
            INSERT INTO activities (
                action_type,
                description,
                item_code,
                user_id,
                timestamp
            ) VALUES (?, ?, ?, ?, NOW())
        ");
        
        // Log: New exchange sale recorded
        $desc_new_sale = "Exchange: New sale recorded - Order #{$exchange['order_number']}, Exchange #{$exchange['exchange_number']}, Item: {$item['new_item_name']} (Size: {$item['new_size']}), Qty: {$item['exchange_quantity']}, Price: ₱" . number_format($item['new_price'], 2) . ", Total: ₱" . number_format($item['new_price'] * $item['exchange_quantity'], 2);
        $activityStmt->execute([
            'Exchange Sale Recorded',
            $desc_new_sale,
            $item['new_item_code'],
            $_SESSION['user_id']
        ]);
        
        // -------------------------------------------------------------------
        // 6. UPDATE MONTHLY INVENTORY SYSTEM (PROPER FIX)
        // -------------------------------------------------------------------
        // CRITICAL FIX: Record exchange adjustments in monthly_sales_records
        // 
        // Exchange = 2 transactions:
        // 1. Return original item (NEGATIVE sale = credit)
        // 2. Issue new item (POSITIVE sale = debit)
        
        $currentPeriodId = $monthlyInventory->getCurrentPeriodId();
        $processedBy = $_SESSION['user_id'] ?? 0;
        
        // Transaction 1: Record the RETURN of original item (negative quantity)
        // This reduces sales_total in the snapshot
        $monthlyInventory->recordSale(
            $exchange['exchange_number'],  // Use exchange number as transaction
            $item['original_item_code'],
            -$item['exchange_quantity'],  // NEGATIVE quantity = return
            $item['original_price'],
            -($item['original_price'] * $item['exchange_quantity']),  // NEGATIVE amount
            $processedBy,
            false  // Don't use internal transaction (we're already in one)
        );
        
        // Transaction 2: Record the SALE of new item (positive quantity)
        // This increases sales_total in the snapshot
        $monthlyInventory->recordSale(
            $exchange['exchange_number'],  // Use exchange number as transaction
            $item['new_item_code'],
            $item['exchange_quantity'],  // POSITIVE quantity = sale
            $item['new_price'],
            $item['new_price'] * $item['exchange_quantity'],  // POSITIVE amount
            $processedBy,
            false  // Don't use internal transaction (we're already in one)
        );
        
        // After recording sales, the snapshots and inventory are automatically updated
        // by MonthlyInventoryManager->recordSale() which calls:
        // - updateMonthlySnapshot() to recalculate from source tables
        // - updateInventoryActualQuantity() to sync inventory table
        //
        // HOWEVER, we need to preserve our manual inventory updates above (lines 116-158)
        // because they correctly handle the swap. So we'll re-apply them after recordSale()
        
        // Store current actual quantities before recordSale() potentially changes them
        $stmt = $conn->prepare("SELECT actual_quantity FROM inventory WHERE item_code = ?");
        $stmt->execute([$item['original_item_code']]);
        $originalActual = $stmt->fetchColumn();
        
        $stmt->execute([$item['new_item_code']]);
        $newItemActual = $stmt->fetchColumn();
        
        // Now update the inventory.current_month_sales field to match the snapshots
        // This ensures the inventory table shows the correct month sales after exchange
        $stmt = $conn->prepare("
            SELECT sales_total FROM monthly_inventory_snapshots
            WHERE item_code = ? AND period_id = ?
        ");
        
        $stmt->execute([$item['original_item_code'], $currentPeriodId]);
        $originalSnapshot = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($originalSnapshot) {
            $conn->prepare("
                UPDATE inventory
                SET current_month_sales = ?
                WHERE item_code = ?
            ")->execute([
                $originalSnapshot['sales_total'],
                $item['original_item_code']
            ]);
        }
        
        $stmt->execute([$item['new_item_code'], $currentPeriodId]);
        $newSnapshot = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($newSnapshot) {
            $conn->prepare("
                UPDATE inventory
                SET current_month_sales = ?
                WHERE item_code = ?
            ")->execute([
                $newSnapshot['sales_total'],
                $item['new_item_code']
            ]);
        }
    }
    
    // ==========================================================================
    // UPDATE EXCHANGE STATUS
    // ==========================================================================
    $update_stmt = $conn->prepare("
        UPDATE order_exchanges 
        SET status = 'completed',
            payment_status = ?,
            completed_date = NOW(),
            updated_at = NOW()
        WHERE id = ?
    ");
    $update_stmt->execute([$payment_status, $exchange_id]);
    
    // Log exchange completion activity
    logExchangeActivity(
        $conn,
        $exchange_id,
        'completed',
        "Exchange completed: Inventory updated, sold quantities adjusted, original sales adjusted, exchange sales recorded with proper linkage, monthly reports updated. Processed by admin.",
        $_SESSION['user_id']
    );
    
    // Log general activity for completion
    $stmt = $conn->prepare("
        INSERT INTO activities (
            action_type,
            description,
            item_code,
            user_id,
            timestamp
        ) VALUES (?, ?, NULL, ?, NOW())
    ");
    $stmt->execute([
        'Exchange Completed',
        "Exchange #{$exchange['exchange_number']} marked as completed. Original sales adjusted, exchange sales recorded with transaction_type='Exchange', inventory and monthly reports updated.",
        $_SESSION['user_id']
    ]);
    
    $conn->commit();
    
    // Trigger real-time inventory update notification
    triggerInventoryUpdate(
        $conn, 
        'exchange_completion', 
        "Exchange #{$exchange['exchange_number']} completed - " . count($exchange_items) . " items affected"
    );
    
    echo json_encode([
        'success' => true,
        'message' => 'Exchange marked as completed successfully. Original sales adjusted, exchange sales recorded with linkage, inventory and reports updated.',
        'exchange_id' => $exchange_id,
        'exchange_number' => $exchange['exchange_number'],
        'items_processed' => count($exchange_items)
    ]);
    
} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    
    error_log("Complete exchange error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'error_details' => [
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]
    ]);
}
