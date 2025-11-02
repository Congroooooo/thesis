<?php

date_default_timezone_set('Asia/Manila');

require_once __DIR__ . '/../Includes/connection.php';
require_once __DIR__ . '/../Includes/notifications.php';

$startTime = microtime(true);
$timestamp = date('Y-m-d H:i:s');

function logToFile($message, $level = 'INFO') {
    $logTimestamp = date('Y-m-d H:i:s');
    $logEntry = "[$logTimestamp] [$level] $message\n";
    file_put_contents(__DIR__ . '/heroku_execution.log', $logEntry, FILE_APPEND | LOCK_EX);
    echo $logEntry;
}

logToFile("=== HEROKU SCHEDULER EXECUTION STARTED ===");
logToFile("Execution ID: " . uniqid('exec_'));
logToFile("Server Time: $timestamp");
logToFile("PHP Version: " . phpversion());
logToFile("Memory Limit: " . ini_get('memory_limit'));

try {
    $query = "
        SELECT 
            o.id, o.order_number, o.user_id, o.items, o.total_amount,
            a.first_name, a.last_name, a.email, a.pre_order_strikes 
        FROM orders o
        JOIN account a ON o.user_id = a.id
        WHERE o.status = 'approved'
          AND o.payment_date IS NULL
          AND o.updated_at < DATE_SUB(NOW(), INTERVAL 5 MINUTE)
        ORDER BY o.updated_at ASC
        LIMIT 10
    ";

    $stmt = $conn->prepare($query);
    $stmt->execute();
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $count = count($orders);
    logToFile("Database query completed successfully");
    logToFile("Found $count unpaid orders to process");

    if ($count === 0) {
        logToFile("No orders require voiding at this time", 'SUCCESS');
        
        // Show recent order activity for context
        $recentQuery = "
            SELECT COUNT(*) as total, 
                   SUM(CASE WHEN status = 'approved' AND payment_date IS NULL THEN 1 ELSE 0 END) as unpaid_approved,
                   SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending
            FROM orders 
            WHERE created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
        ";
        $recentStmt = $conn->prepare($recentQuery);
        $recentStmt->execute();
        $stats = $recentStmt->fetch(PDO::FETCH_ASSOC);
        
        logToFile("Recent activity (last hour): {$stats['total']} total orders, {$stats['unpaid_approved']} unpaid approved, {$stats['pending']} pending");
        logToFile("=== EXECUTION COMPLETED SUCCESSFULLY ===", 'SUCCESS');
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

            logToFile("Processing Order #$orderNumber (Customer: $customerName, ID: $orderId)");

            // 1. Update order status to 'voided'
            $conn->prepare("UPDATE orders SET status = 'voided' WHERE id = ?")
                ->execute([$orderId]);
            logToFile("Order status updated to 'voided'");

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
                logToFile("Account BLOCKED due to 3 strikes (User ID: $userId)", 'WARNING');
            } else {
                logToFile("Strike added. User now has $strikeCount/3 strikes");
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
            logToFile("✅ Order #$orderNumber successfully voided (Strikes: $strikeCount)", 'SUCCESS');

        } catch (Exception $e) {
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }
            $errors++;
            logToFile("❌ ERROR processing order #$orderNumber: " . $e->getMessage(), 'ERROR');
            logToFile("Stack trace: " . $e->getTraceAsString(), 'DEBUG');
        }
    }

    $executionTime = round((microtime(true) - $startTime) * 1000, 2);
    $memoryUsed = memory_get_peak_usage(true) / 1024 / 1024; // MB
    
    logToFile("=== EXECUTION SUMMARY ===", 'SUCCESS');
    logToFile("✅ Orders voided: $voided");
    logToFile("❌ Errors encountered: $errors");
    logToFile("⏱️ Execution time: {$executionTime}ms");
    logToFile("🧠 Peak memory usage: " . round($memoryUsed, 2) . "MB");
    logToFile("=== EXECUTION COMPLETED ===", 'SUCCESS');

} catch (Exception $e) {
    $executionTime = round((microtime(true) - $startTime) * 1000, 2);
    logToFile("💥 FATAL ERROR: " . $e->getMessage(), 'FATAL');
    logToFile("Stack trace: " . $e->getTraceAsString(), 'FATAL');
    logToFile("Execution time before failure: {$executionTime}ms", 'FATAL');
    exit(1);
}
?>