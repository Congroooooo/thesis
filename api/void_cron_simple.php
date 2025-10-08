<?php
/**
 * Optimized web endpoint for cron-job.org
 * Executes void unpaid orders directly with timeout protection
 * Maximum execution time: 25 seconds (under cron-job.org's 30s limit)
 */

$secret_token = 'proware_void_2025_secure';
$provided_token = $_GET['token'] ?? '';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Validate token
if ($provided_token !== $secret_token) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Invalid token']);
    exit;
}

// Set execution time limit to prevent timeout
set_time_limit(25); // Under cron-job.org's 30s limit
ini_set('max_execution_time', 25);

try {
    // Log the cron trigger time
    $logFile = __DIR__ . '/../cron/void_debug.log';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] [INFO] Web cron triggered via cron-job.org\n", FILE_APPEND | LOCK_EX);

    // Execute the void logic directly (inline)
    date_default_timezone_set('Asia/Manila');
    require_once __DIR__ . '/../Includes/connection.php';
    require_once __DIR__ . '/../Includes/notifications.php';

    $startTime = microtime(true);
    
    // Only fetch necessary columns with LIMIT to prevent timeout
    $query = "
        SELECT 
            o.id, o.order_number, o.user_id, o.items, 
            a.first_name, a.last_name, a.email, a.pre_order_strikes 
        FROM orders o
        JOIN account a ON o.user_id = a.id
        WHERE o.status = 'approved'
          AND o.payment_date IS NULL
          AND o.created_at < DATE_SUB(NOW(), INTERVAL 5 MINUTE)
        LIMIT 10
    ";

    $stmt = $conn->prepare($query);
    $stmt->execute();
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $count = count($orders);
    file_put_contents($logFile, "[$timestamp] [INFO] Found {$count} unpaid approved orders to void\n", FILE_APPEND | LOCK_EX);

    $voided = 0;
    $errors = 0;

    if ($count > 0) {
        foreach ($orders as $order) {
            // Check execution time to prevent timeout
            if ((microtime(true) - $startTime) > 20) {
                file_put_contents($logFile, "[$timestamp] [WARNING] Execution time limit reached, stopping batch\n", FILE_APPEND | LOCK_EX);
                break;
            }

            try {
                $conn->beginTransaction();

                $orderId = $order['id'];
                $userId = $order['user_id'];
                $orderNumber = $order['order_number'];

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
                }

                // 4. Create notification
                $notif = "Your order #{$orderNumber} has been voided because payment was not made within 5 minutes.{$strikeMessage}";
                createNotification($conn, $userId, $notif, $orderNumber, 'voided');

                // 5. Simple activity log (optimized)
                $items = json_decode($order['items'], true);
                if (is_array($items) && count($items) > 0) {
                    $firstItem = $items[0];
                    $desc = "Voided - Order #: {$orderNumber}, Items: " . count($items);
                    $conn->prepare("
                        INSERT INTO activities (action_type, description, user_id, timestamp)
                        VALUES (?, ?, ?, NOW())
                    ")->execute(['Voided', $desc, null]);
                }

                $conn->commit();
                $voided++;

            } catch (Throwable $e) {
                if ($conn->inTransaction()) {
                    $conn->rollBack();
                }
                $errors++;
                file_put_contents($logFile, "[$timestamp] [ERROR] Error voiding order ID {$order['id']}: " . $e->getMessage() . "\n", FILE_APPEND | LOCK_EX);
            }
        }
    }

    $executionTime = round((microtime(true) - $startTime) * 1000, 2);
    $finalMessage = "Cron completed in {$executionTime}ms. Voided: {$voided}, Errors: {$errors}";
    
    file_put_contents($logFile, "[$timestamp] [COMPLETE] {$finalMessage}\n", FILE_APPEND | LOCK_EX);

    // Success response
    echo json_encode([
        'status' => 'success',
        'message' => $finalMessage,
        'voided' => $voided,
        'errors' => $errors,
        'execution_time_ms' => $executionTime,
        'timestamp' => $timestamp
    ]);

} catch (Throwable $e) {
    $errorMsg = $e->getMessage();
    file_put_contents($logFile, "[$timestamp] [FATAL] {$errorMsg}\n", FILE_APPEND | LOCK_EX);
    
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $errorMsg,
        'timestamp' => $timestamp
    ]);
}
?>
