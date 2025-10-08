#!/usr/bin/env php
<?php
/**
 * Heroku Scheduler Script for Voiding Unpaid Orders
 * Run with: php cron/heroku_void.php
 * 
 * This script is designed to run on Heroku Scheduler every minute
 * It processes unpaid orders that are older than 5 minutes
 */

// Set timezone
date_default_timezone_set('Asia/Manila');

// Include required files
require_once __DIR__ . '/../Includes/connection.php';
require_once __DIR__ . '/../Includes/notifications.php';

// Start execution
$startTime = microtime(true);
$timestamp = date('Y-m-d H:i:s');

echo "=== Heroku Void Cron Job Started ===\n";
echo "Timestamp: $timestamp\n";

try {
    // Query for unpaid approved orders older than 5 minutes
    $query = "
        SELECT 
            o.id, o.order_number, o.user_id, o.items, o.total_amount,
            a.first_name, a.last_name, a.email, a.pre_order_strikes 
        FROM orders o
        JOIN account a ON o.user_id = a.id
        WHERE o.status = 'approved'
          AND o.payment_date IS NULL
          AND o.created_at < DATE_SUB(NOW(), INTERVAL 5 MINUTE)
        ORDER BY o.created_at ASC
        LIMIT 10
    ";

    $stmt = $conn->prepare($query);
    $stmt->execute();
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $count = count($orders);
    echo "Found $count unpaid orders to process\n";

    if ($count === 0) {
        echo "No orders to void at this time.\n";
        echo "=== Cron Job Completed Successfully ===\n";
        return;
    }

    $voided = 0;
    $errors = 0;

    foreach ($orders as $order) {
        try {
            $conn->beginTransaction();

            $orderId = $order['id'];
            $userId = $order['user_id'];
            $orderNumber = $order['order_number'];
            $customerName = $order['first_name'] . ' ' . $order['last_name'];

            echo "Processing Order #$orderNumber ($customerName)... ";

            // 1. Update order status to 'voided'
            $conn->prepare("UPDATE orders SET status = 'voided' WHERE id = ?")
                ->execute([$orderId]);

            // 2. Increment strikes
            $conn->prepare("
                UPDATE account 
                SET pre_order_strikes = pre_order_strikes + 1, 
                    last_strike_time = NOW() 
                WHERE id = ?
            ")->execute([$userId]);

            // 3. Get new strike count
            $newStrikes = $conn->prepare("SELECT pre_order_strikes FROM account WHERE id = ?");
            $newStrikes->execute([$userId]);
            $strikeCount = (int) $newStrikes->fetchColumn();

            $strikeMessage = ($strikeCount >= 3)
                ? " Your account has been blocked due to 3 strikes."
                : " You now have {$strikeCount} strike(s). 3 strikes will result in account blocking.";

            // 4. Block account if 3 strikes
            if ($strikeCount >= 3) {
                $conn->prepare("UPDATE account SET is_strike = 1 WHERE id = ?")
                    ->execute([$userId]);
                echo "[BLOCKED] ";
            }

            // 5. Create notification
            $notif = "Your order #{$orderNumber} has been voided because payment was not made within 5 minutes.{$strikeMessage}";
            createNotification($conn, $userId, $notif, $orderNumber, 'voided');

            // 6. Log activity
            $items = json_decode($order['items'], true);
            if (is_array($items) && count($items) > 0) {
                $desc = "Voided - Order #: {$orderNumber}, Items: " . count($items) . ", Customer: {$customerName}";
                $conn->prepare("
                    INSERT INTO activities (action_type, description, user_id, timestamp)
                    VALUES (?, ?, ?, NOW())
                ")->execute(['Voided', $desc, null]);
            }

            $conn->commit();
            $voided++;
            echo "VOIDED (Strikes: $strikeCount)\n";

        } catch (Exception $e) {
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }
            $errors++;
            echo "ERROR: " . $e->getMessage() . "\n";
        }
    }

    $executionTime = round((microtime(true) - $startTime) * 1000, 2);
    
    echo "\n=== Results ===\n";
    echo "✅ Successfully voided: $voided orders\n";
    echo "❌ Errors: $errors orders\n";
    echo "⏱️  Execution time: {$executionTime}ms\n";
    echo "=== Cron Job Completed ===\n";

} catch (Exception $e) {
    $executionTime = round((microtime(true) - $startTime) * 1000, 2);
    echo "FATAL ERROR: " . $e->getMessage() . "\n";
    echo "Execution time: {$executionTime}ms\n";
    exit(1);
}
?>