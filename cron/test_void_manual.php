<?php
/**
 * Manual Test Script for Void Unpaid Orders
 * Use this to test the void functionality manually
 * Run: php test_void_manual.php
 */

date_default_timezone_set('Asia/Manila');
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../Includes/connection.php';
require_once __DIR__ . '/../Includes/notifications.php';

echo "=== Manual Void Test Script ===\n";
echo "Current time: " . date('Y-m-d H:i:s') . "\n\n";

try {
    // Check for unpaid approved orders (5 minutes)
    $query = "
        SELECT 
            o.id, o.order_number, o.user_id, o.items, o.total_amount, o.created_at,
            a.first_name, a.last_name, a.email, a.pre_order_strikes 
        FROM orders o
        JOIN account a ON o.user_id = a.id
        WHERE o.status = 'approved'
          AND o.payment_date IS NULL
          AND o.created_at < DATE_SUB(NOW(), INTERVAL 5 MINUTE)
        ORDER BY o.created_at ASC
    ";

    $stmt = $conn->prepare($query);
    $stmt->execute();
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $count = count($orders);
    echo "Found {$count} unpaid approved orders to void:\n";

    if ($count === 0) {
        echo "✅ No orders to void at this time.\n";
        
        // Show recent orders for context
        echo "\n--- Recent Orders (Last 10) ---\n";
        $recentQuery = "
            SELECT o.id, o.order_number, o.status, o.payment_date, o.created_at, 
                   CONCAT(a.first_name, ' ', a.last_name) as customer_name,
                   TIMESTAMPDIFF(MINUTE, o.created_at, NOW()) as minutes_ago
            FROM orders o
            JOIN account a ON o.user_id = a.id
            ORDER BY o.created_at DESC
            LIMIT 10
        ";
        $recentStmt = $conn->prepare($recentQuery);
        $recentStmt->execute();
        $recent = $recentStmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($recent as $order) {
            $status = $order['status'];
            $paymentStatus = $order['payment_date'] ? 'PAID' : 'UNPAID';
            echo "  #{$order['order_number']} - {$order['customer_name']} - {$status} - {$paymentStatus} - {$order['minutes_ago']}min ago\n";
        }
        return;
    }

    echo "\n";
    foreach ($orders as $order) {
        $minutesElapsed = (time() - strtotime($order['created_at'])) / 60;
        echo "Order #{$order['order_number']} - {$order['first_name']} {$order['last_name']} - Created: {$order['created_at']} ({$minutesElapsed} min ago)\n";
    }

    echo "\nProceed with voiding these orders? (y/N): ";
    $handle = fopen("php://stdin", "r");
    $confirm = trim(fgets($handle));
    fclose($handle);

    if (strtolower($confirm) !== 'y') {
        echo "❌ Cancelled by user.\n";
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

            echo "\nProcessing Order #{$orderNumber}...";

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

            // Block if 3 strikes
            if ($strikeCount >= 3) {
                $conn->prepare("UPDATE account SET is_strike = 1 WHERE id = ?")
                    ->execute([$userId]);
                echo " [BLOCKED]";
            }

            // 4. Create notification
            $notif = "Your order #{$orderNumber} has been voided because payment was not made within 5 minutes.{$strikeMessage}";
            createNotification($conn, $userId, $notif, $orderNumber, 'voided');

            // 5. Activity log
            $items = json_decode($order['items'], true);
            if (is_array($items) && count($items) > 0) {
                $desc = "Voided - Order #: {$orderNumber}, Items: " . count($items) . ", Total: ₱" . number_format($order['total_amount'] ?? 0, 2);
                $conn->prepare("
                    INSERT INTO activities (action_type, description, user_id, timestamp)
                    VALUES (?, ?, ?, NOW())
                ")->execute(['Voided', $desc, null]);
            }

            $conn->commit();
            $voided++;
            echo " ✅ VOIDED (Strikes: {$strikeCount})";

        } catch (Throwable $e) {
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }
            $errors++;
            echo " ❌ ERROR: " . $e->getMessage();
        }
    }

    echo "\n\n=== RESULTS ===\n";
    echo "✅ Successfully voided: {$voided} orders\n";
    echo "❌ Errors: {$errors} orders\n";
    echo "Total processed: " . ($voided + $errors) . " orders\n";

} catch (Throwable $e) {
    echo "FATAL ERROR: " . $e->getMessage() . "\n";
}

echo "\n=== Test Complete ===\n";
?>