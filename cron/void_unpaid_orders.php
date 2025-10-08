<?php
/**
 * Automatic Order Voiding Cron Job
 * Runs every minute to check for unpaid approved orders
 * Voids orders that haven't been paid within 5 minutes
 * Applies strikes to customer accounts (3 strikes = account blocked)
 */

// Set timezone and error handling
date_default_timezone_set('Asia/Manila');
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include required files
require_once __DIR__ . '/../Includes/connection.php';
require_once __DIR__ . '/../Includes/notifications.php';

// Optimized logging function
function logMessage($message, $level = 'INFO') {
    static $log_buffer = [];
    $timestamp = date('Y-m-d H:i:s');
    $log_buffer[] = "[$timestamp] [$level] $message";
    
    // Flush buffer every 5 messages or on completion
    if (count($log_buffer) >= 5 || $level === 'COMPLETE') {
        $logEntry = implode("\n", $log_buffer) . "\n";
        file_put_contents(__DIR__ . '/void_debug.log', $logEntry, FILE_APPEND | LOCK_EX);
        $log_buffer = [];
    }
}

// Set longer execution time for background processing
ini_set('max_execution_time', 300); // 5 minutes max
set_time_limit(300);

try {
    $start_time = microtime(true);
    logMessage("Starting background void process - batch mode");
    
    // 1️⃣ Optimized query - fetch only needed columns + batch processing
    $query = "
        SELECT 
            o.id, 
            o.order_number, 
            o.user_id, 
            o.items, 
            o.created_at,
            a.first_name, 
            a.last_name, 
            a.email,
            a.pre_order_strikes
        FROM orders o
        JOIN account a ON o.user_id = a.id
        WHERE o.status = 'approved'
        AND o.payment_date IS NULL
        AND o.created_at < DATE_SUB(NOW(), INTERVAL 5 MINUTE)
        ORDER BY o.created_at ASC
        LIMIT 50
    ";

    $stmt = $conn->prepare($query);
    $stmt->execute();
    $unpaid_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $order_count = count($unpaid_orders);
    logMessage("Found $order_count unpaid orders in this batch");

    if (empty($unpaid_orders)) {
        logMessage("No orders to void in this batch");
        logMessage("Batch complete", "COMPLETE");
        return;
    }

    $voided_count = 0;
    $error_count = 0;
    $batch_start = microtime(true);

    // Prepare statements once outside the loop for better performance
    $updateOrderStmt = $conn->prepare("UPDATE orders SET status = 'voided' WHERE id = ?");
    $updateStrikesStmt = $conn->prepare("
        UPDATE account 
        SET pre_order_strikes = COALESCE(pre_order_strikes, 0) + 1,
            last_strike_time = NOW()
        WHERE id = ?
    ");
    $checkStrikesStmt = $conn->prepare("SELECT pre_order_strikes FROM account WHERE id = ?");
    $deactivateStmt = $conn->prepare("UPDATE account SET status = 'inactive' WHERE id = ?");
    $activityStmt = $conn->prepare("
        INSERT INTO activities (action_type, description, item_code, user_id, timestamp) 
        VALUES (?, ?, ?, ?, NOW())
    ");

    foreach ($unpaid_orders as $order) {
        try {
            // Single transaction for each order
            $conn->beginTransaction();

            // 1. Update order status to 'voided'
            $updateOrderStmt->execute([$order['id']]);

            // 2. Increment strikes using pre-prepared statement
            $updateStrikesStmt->execute([$order['user_id']]);

            // 3. Check if user should be blocked (3 strikes rule)
            $checkStrikeStmt = $conn->prepare("SELECT pre_order_strikes FROM account WHERE id = ?");
            $checkStrikeStmt->execute([$order['user_id']]);
            $new_strikes = $checkStrikeStmt->fetchColumn();

            $strike_message = "";
            if ($new_strikes >= 3) {
                // Block the account
                $blockStmt = $conn->prepare("UPDATE account SET is_strike = 1 WHERE id = ?");
                $blockStmt->execute([$order['user_id']]);
                $strike_message = " Your account has been blocked due to 3 strikes.";
                logMessage("User ID {$order['user_id']} blocked due to 3 strikes");
            } else {
                $strike_message = " You now have {$new_strikes} strike(s). 3 strikes will result in account blocking.";
            }

            // 4. Create notification for the user
            $message = "Your order #{$order['order_number']} has been voided because payment was not made within 5 minutes. Please place a new order if you still wish to purchase these items." . $strike_message;
            createNotification($conn, $order['user_id'], $message, $order['order_number'], 'voided');

        // 5. Log activities efficiently using pre-prepared statement
        $order_items = json_decode($order['items'], true);
        if ($order_items && is_array($order_items)) {
            foreach ($order_items as $item) {
                $activity_description = "Voided - Order #: {$order['order_number']}, Item: {$item['item_name']}, Quantity: {$item['quantity']}";
                $item_code = $item['item_code'] ?? null;
                
                // Use pre-prepared statement for better performance
                $activityStmt->execute([
                    'Voided',
                    $activity_description,
                    $item_code,
                    null // System action
                ]);
            }
        }            // Commit the transaction
            $conn->commit();
            $voided_count++;
            
            logMessage("Voided order #{$order['order_number']} (ID: {$order['id']})");

        } catch (Exception $e) {
            // Rollback transaction on error
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }
            $error_count++;
            logMessage("Error voiding order {$order['id']}: " . $e->getMessage(), "ERROR");
            error_log("Cron job error voiding order {$order['id']}: " . $e->getMessage());
        }
    }

    $batch_time = round((microtime(true) - $batch_start) * 1000, 2);
    $total_time = round((microtime(true) - $start_time) * 1000, 2);
    
    logMessage("Batch completed in {$batch_time}ms. Voided: {$voided_count}, Errors: {$error_count}");
    logMessage("Total background process time: {$total_time}ms", "COMPLETE");

} catch (Exception $e) {
    logMessage("Fatal error in background process: " . $e->getMessage(), "FATAL");
    error_log("Fatal error in void_unpaid_orders: " . $e->getMessage());
    // Ensure log buffer is flushed on error
    logMessage("", "COMPLETE");
}

// Background process completed
?>