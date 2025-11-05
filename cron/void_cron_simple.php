<?php

date_default_timezone_set('Asia/Manila');
error_reporting(E_ALL);
ini_set('display_errors', 1);

$secret_token = 'proware_void_2025_secure';
$provided_token = $_GET['token'] ?? '';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

if ($provided_token !== $secret_token) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Invalid token']);
    exit;
}

// Log helper
function logMessage($message, $level = 'INFO') {
    $timestamp = date('Y-m-d H:i:s');
    $log = "[$timestamp] [$level] $message\n";
    file_put_contents(__DIR__ . '/void_debug.log', $log, FILE_APPEND | LOCK_EX);
}

try {
    // Log the cron trigger
    logMessage("Web cron triggered via cron-job.org");

    // Respond immediately to cron-job.org
    echo json_encode([
        'status' => 'success',
        'message' => 'Void cron started in background',
        'timestamp' => date('Y-m-d H:i:s')
    ]);

    // Continue processing in background
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    }

    // Include database connection
    require_once __DIR__ . '/../Includes/connection.php';
    require_once __DIR__ . '/../Includes/notifications.php';

    logMessage("Starting void unpaid orders processing");

    $query = "
        SELECT 
            o.id, o.order_number, o.user_id, o.items, 
            a.first_name, a.last_name, a.email, a.pre_order_strikes,
            po.validation_deadline,
            CASE WHEN po.id IS NOT NULL THEN 1 ELSE 0 END as is_preorder_conversion
        FROM orders o
        JOIN account a ON o.user_id = a.id
        LEFT JOIN preorder_orders po ON po.converted_to_order_id = o.id
        WHERE o.status = 'approved'
          AND o.payment_date IS NULL
          AND o.updated_at < DATE_SUB(NOW(), INTERVAL 5 MINUTE)
        LIMIT 20
    ";

    $stmt = $conn->prepare($query);
    $stmt->execute();
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $count = count($orders);
    logMessage("Found {$count} unpaid approved orders to void (5 minutes after approval)");

    if ($count === 0) {
        logMessage("No orders to void, exiting");
        exit;
    }

    $voided = 0;
    $errors = 0;

    foreach ($orders as $order) {
        try {
            $conn->beginTransaction();

            $orderId = $order['id'];
            $userId = $order['user_id'];
            $orderNumber = $order['order_number'];

            logMessage("Processing order ID: {$orderId}, Order #: {$orderNumber}");

            // 1️⃣ Update order status to 'voided' and update timestamp
            $conn->prepare("UPDATE orders SET status = 'voided', updated_at = NOW() WHERE id = ?")
                ->execute([$orderId]);

            // 1.5️⃣ If this is a converted pre-order, update preorder_orders status to 'voided'
            $isPreorderConversion = (bool) $order['is_preorder_conversion'];
            $conn->prepare("UPDATE preorder_orders SET status = 'voided', updated_at = NOW() WHERE converted_to_order_id = ?")
                ->execute([$orderId]);

            // 1.6️⃣ Restore inventory if this was a converted pre-order (items were added to inventory)
            if ($isPreorderConversion) {
                $items = json_decode($order['items'], true);
                if (is_array($items)) {
                    $restoreStmt = $conn->prepare("
                        UPDATE inventory 
                        SET actual_quantity = actual_quantity - ?, 
                            new_delivery = GREATEST(0, new_delivery - ?),
                            status = CASE 
                                WHEN (actual_quantity - ?) <= 0 THEN 'out of stock' 
                                ELSE 'in stock' 
                            END
                        WHERE item_code = ? AND sizes = ?
                    ");
                    
                    foreach ($items as $item) {
                        $itemCode = $item['item_code'] ?? null;
                        $size = $item['size'] ?? 'One Size';
                        $quantity = $item['quantity'] ?? 0;
                        
                        if ($itemCode && $quantity > 0) {
                            // Extract base code (remove -XXX suffix if present)
                            $baseCode = preg_replace('/-\d{3}$/', '', $itemCode);
                            $restoreStmt->execute([$quantity, $quantity, $quantity, $baseCode, $size]);
                            logMessage("  - Restored {$quantity} units of {$baseCode} (Size: {$size})");
                        }
                    }
                }
            }

            // 2️⃣ Increment strikes
            $conn->prepare("
                UPDATE account 
                SET pre_order_strikes = pre_order_strikes + 1, 
                    last_strike_time = NOW() 
                WHERE id = ?
            ")->execute([$userId]);

            // 3️⃣ Get new strike count
            $newStrikes = $conn->prepare("SELECT pre_order_strikes FROM account WHERE id = ?");
            $newStrikes->execute([$userId]);
            $strikeCount = (int) $newStrikes->fetchColumn();

            $strikeMessage = ($strikeCount >= 3)
                ? " Your account has been set to inactive due to 3 strikes."
                : " You now have {$strikeCount} strike(s). 3 strikes will result in account deactivation.";

            // Deactivate account if 3 strikes
            if ($strikeCount >= 3) {
                $conn->prepare("UPDATE account SET status = 'inactive' WHERE id = ?")
                    ->execute([$userId]);
                logMessage("User {$userId} account set to inactive (3 strikes)");
            }

            // 4️⃣ Create notification
            $notif = "Your order #{$orderNumber} has been voided because payment was not made within 5 minutes.{$strikeMessage}";
            createNotification($conn, $userId, $notif, $orderNumber, 'voided');
 
            // 5️⃣ Log item-level activity (optimized)
            $items = json_decode($order['items'], true);
            if (is_array($items)) {
                $activityStmt = $conn->prepare("
                    INSERT INTO activities (action_type, description, item_code, user_id, timestamp)
                    VALUES (?, ?, ?, ?, NOW())
                ");

                foreach ($items as $item) {
                    $itemCode = $item['item_code'] ?? null;

                    // Skip invalid item codes
                    if ($itemCode) {
                        $check = $conn->prepare("SELECT COUNT(*) FROM inventory WHERE item_code = ?");
                        $check->execute([$itemCode]);
                        if ($check->fetchColumn() == 0) {
                            $itemCode = null;
                        }
                    }

                    $desc = "Voided - Order #: {$orderNumber}, Item: {$item['item_name']}, Qty: {$item['quantity']}";
                    $activityStmt->execute(['Voided', $desc, $itemCode, null]);
                }
            }

            $conn->commit();
            $voided++;
            logMessage("✅ Order {$orderNumber} voided. User now has {$strikeCount} strikes.");

        } catch (Throwable $e) {
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }
            $errors++;
            logMessage("❌ Error voiding order ID {$order['id']}: " . $e->getMessage(), 'ERROR');
        }
    }

    logMessage("Cron completed. Voided: {$voided}, Errors: {$errors}");

} catch (Throwable $e) {
    logMessage("FATAL: " . $e->getMessage(), 'FATAL');
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}
?>