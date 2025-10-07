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

// Log function for better debugging
function logMessage($message, $level = 'INFO') {
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] [$level] $message\n";
    file_put_contents(__DIR__ . '/void_debug.log', $logEntry, FILE_APPEND | LOCK_EX);
}

try {
    logMessage("Starting void unpaid orders cron job");
    
    // Query to find orders that need to be voided
    // Orders that are approved, have no payment_date, and were created more than 5 minutes ago
    $query = "
        SELECT o.*, a.first_name, a.last_name, a.email, a.pre_order_strikes
        FROM orders o
        JOIN account a ON o.user_id = a.id
        WHERE o.status = 'approved'
        AND o.payment_date IS NULL
        AND o.created_at < DATE_SUB(NOW(), INTERVAL 5 MINUTE)
    ";

    $stmt = $conn->prepare($query);
    $stmt->execute();
    $unpaid_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    logMessage("Found " . count($unpaid_orders) . " unpaid approved orders to void");

    if (empty($unpaid_orders)) {
        logMessage("No orders to void, exiting");
        exit(0);
    }

    $voided_count = 0;
    $error_count = 0;

    foreach ($unpaid_orders as $order) {
        try {
            // Start transaction for each order to ensure atomicity
            $conn->beginTransaction();

            logMessage("Processing order ID: {$order['id']}, Order Number: {$order['order_number']}, User: {$order['first_name']} {$order['last_name']}");

            // 1. Update order status to 'voided'
            $updateStmt = $conn->prepare("UPDATE orders SET status = 'voided' WHERE id = ?");
            $updateStmt->execute([$order['id']]);

            // 2. Increment pre_order_strikes and update last_strike_time
            $strikeStmt = $conn->prepare("
                UPDATE account 
                SET pre_order_strikes = pre_order_strikes + 1, 
                    last_strike_time = NOW() 
                WHERE id = ?
            ");
            $strikeStmt->execute([$order['user_id']]);

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

        // 5. Log activity for each item in the order
        $order_items = json_decode($order['items'], true);
        if ($order_items && is_array($order_items)) {
            foreach ($order_items as $item) {
                $activity_description = "Voided - Order #: {$order['order_number']}, Item: {$item['item_name']}, Quantity: {$item['quantity']}";
                
                // Check if item_code exists in inventory before inserting activity
                $item_code = $item['item_code'] ?? null;
                if ($item_code) {
                    $checkItemStmt = $conn->prepare("SELECT item_code FROM inventory WHERE item_code = ?");
                    $checkItemStmt->execute([$item_code]);
                    if (!$checkItemStmt->fetch()) {
                        $item_code = null; // Don't use invalid item_code
                    }
                }
                
                $activityStmt = $conn->prepare(
                    "INSERT INTO activities (
                        action_type,
                        description,
                        item_code,
                        user_id,
                        timestamp
                    ) VALUES (?, ?, ?, ?, NOW())"
                );
                $activityStmt->execute([
                    'Voided',
                    $activity_description,
                    $item_code,
                    null // System action, no specific user
                ]);
            }
        }            // Commit the transaction
            $conn->commit();
            $voided_count++;
            
            logMessage("Successfully voided order ID: {$order['id']}, User now has {$new_strikes} strikes", "SUCCESS");

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

    logMessage("Cron job completed. Voided: {$voided_count}, Errors: {$error_count}");

} catch (Exception $e) {
    logMessage("Fatal error in cron job: " . $e->getMessage(), "FATAL");
    error_log("Fatal error in void_unpaid_orders cron: " . $e->getMessage());
    exit(1);
}

exit(0);
?>