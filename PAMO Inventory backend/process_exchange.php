<?php
// Start session only if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../Includes/connection.php';
require_once '../Includes/MonthlyInventoryManager.php';

$sales_id = isset($_POST['sales_id']) ? intval($_POST['sales_id']) : 0;
$transaction_number = isset($_POST['transaction_number']) ? $_POST['transaction_number'] : '';
$customer_id = isset($_POST['customer_id']) ? intval($_POST['customer_id']) : 0;
$customer_name = isset($_POST['customer_name']) ? $_POST['customer_name'] : '';
$item_code = isset($_POST['item_code']) ? $_POST['item_code'] : '';
$old_size = isset($_POST['old_size']) ? $_POST['old_size'] : '';
$new_size = isset($_POST['new_size']) ? $_POST['new_size'] : '';
$new_item_code = isset($_POST['new_item_code']) ? $_POST['new_item_code'] : '';
$remarks = isset($_POST['remarks']) ? $_POST['remarks'] : '';

if (!$sales_id || !$transaction_number || !$customer_id || !$customer_name || !$item_code || !$old_size || !$new_size || !$new_item_code) {
    echo json_encode(['success' => false, 'message' => 'All required fields must be provided']);
    exit;
}

try {
    $processed_by = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;

    if (!$processed_by) {
        $check_admin_sql = "SELECT id FROM account WHERE id = 1 LIMIT 1";
        $check_stmt = $conn->prepare($check_admin_sql);
        $check_stmt->execute();
        $check_result = $check_stmt->fetch(PDO::FETCH_ASSOC);
        if ($check_result) {
            $processed_by = 1;
        } else {
            $first_user_sql = "SELECT id FROM account WHERE status = 'active' ORDER BY id LIMIT 1";
            $first_user_stmt = $conn->prepare($first_user_sql);
            $first_user_stmt->execute();
            $first_user = $first_user_stmt->fetch(PDO::FETCH_ASSOC);
            if ($first_user) {
                $processed_by = $first_user['id'];
            } else {
                throw new Exception('No valid user found for processed_by field');
            }
        }
    }
    
    // Validate customer_id exists in account table
    if ($customer_id > 0) {
        $check_customer_sql = "SELECT id FROM account WHERE id = ?";
        $check_customer_stmt = $conn->prepare($check_customer_sql);
        $check_customer_stmt->execute([$customer_id]);
        if (!$check_customer_stmt->fetch(PDO::FETCH_ASSOC)) {
            // Customer ID doesn't exist, use the first available account or processed_by user
            $customer_id = $processed_by;
        }
    } else {
        // No customer_id provided, use processed_by user
        $customer_id = $processed_by;
    }
    $conn->beginTransaction();

    $validate_sql = "SELECT * FROM sales WHERE id = ? AND sale_date >= DATE_SUB(NOW(), INTERVAL 24 HOUR)";
    $validate_stmt = $conn->prepare($validate_sql);
    $validate_stmt->execute([$sales_id]);
    $sale_result = $validate_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($sale_result) == 0) {
        throw new Exception('Sale not found or not eligible for exchange (must be within 24 hours)');
    }
    $sale_data = $sale_result[0];

    $new_item_sql = "SELECT * FROM inventory WHERE item_code = ?";
    $new_item_stmt = $conn->prepare($new_item_sql);
    $new_item_stmt->execute([$new_item_code]);
    $new_item_data = $new_item_stmt->fetch(PDO::FETCH_ASSOC);
    if (!$new_item_data) {
        throw new Exception('New size item not found in inventory. Looking for item_code: ' . $new_item_code);
    }
    if ($new_item_data['actual_quantity'] <= 0) {
        throw new Exception('New size item is out of stock. Available quantity: ' . $new_item_data['actual_quantity']);
    }

    // Step 1: Update inventory quantities
    $update_old_sql = "UPDATE inventory SET actual_quantity = actual_quantity + 1 WHERE item_code = ?";
    $update_old_stmt = $conn->prepare($update_old_sql);
    $update_old_stmt->execute([$item_code]);

    $update_new_sql = "UPDATE inventory SET actual_quantity = actual_quantity - 1 WHERE item_code = ?";
    $update_new_stmt = $conn->prepare($update_new_sql);
    $update_new_stmt->execute([$new_item_code]);

    // Step 2: Get current period ID for monthly reports
    $monthlyInventory = new MonthlyInventoryManager($conn);
    $current_period_id = $monthlyInventory->getCurrentPeriodId();

    // Step 3: Adjust monthly sales records
    // 3a. Reverse the old sale (reduce quantity by 1 or delete if quantity becomes 0)
    $check_old_sale_sql = "SELECT id, quantity_sold FROM monthly_sales_records 
                           WHERE transaction_number = ? AND item_code = ? AND period_id = ?";
    $check_old_stmt = $conn->prepare($check_old_sale_sql);
    $check_old_stmt->execute([$transaction_number, $item_code, $current_period_id]);
    $old_sale_record = $check_old_stmt->fetch(PDO::FETCH_ASSOC);

    if ($old_sale_record) {
        if ($old_sale_record['quantity_sold'] > 1) {
            // Reduce quantity by 1
            $reduce_old_sql = "UPDATE monthly_sales_records 
                              SET quantity_sold = quantity_sold - 1, 
                                  total_amount = total_amount - ? 
                              WHERE id = ?";
            $reduce_old_stmt = $conn->prepare($reduce_old_sql);
            $reduce_old_stmt->execute([$sale_data['price_per_item'], $old_sale_record['id']]);
        } else {
            // Delete the record if quantity becomes 0
            $delete_old_sql = "DELETE FROM monthly_sales_records WHERE id = ?";
            $delete_old_stmt = $conn->prepare($delete_old_sql);
            $delete_old_stmt->execute([$old_sale_record['id']]);
        }
    }

    // 3b. Add a new sale record for the new item
    $add_new_sale_sql = "INSERT INTO monthly_sales_records 
                        (transaction_number, item_code, quantity_sold, price_per_item, total_amount, period_id, processed_by) 
                        VALUES (?, ?, 1, ?, ?, ?, ?)";
    $add_new_stmt = $conn->prepare($add_new_sale_sql);
    $add_new_stmt->execute([
        $transaction_number, 
        $new_item_code, 
        $new_item_data['price'], 
        $new_item_data['price'], 
        $current_period_id, 
        $processed_by
    ]);

    // Step 4: Update monthly snapshots for both items
    $monthlyInventory->updateMonthlySnapshot($item_code, $current_period_id);
    $monthlyInventory->updateMonthlySnapshot($new_item_code, $current_period_id);

    // Step 5: Create exchange record for audit trail
    $exchange_sql = "INSERT INTO exchanges (sales_id, transaction_number, customer_id, customer_name, item_code, old_size, new_size, exchange_quantity, exchange_date, processed_by, remarks) VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW(), ?, ?)";
    $exchange_stmt = $conn->prepare($exchange_sql);
    if (!$exchange_stmt->execute([$sales_id, $transaction_number, $customer_id, $customer_name, $item_code, $old_size, $new_size, $processed_by, $remarks])) {
        throw new Exception('Failed to execute exchange insert');
    }

    // Step 6: Update the sales table to reflect the exchange
    $update_sale_sql = "UPDATE sales SET size = ?, item_code = ? WHERE id = ?";
    $update_sale_stmt = $conn->prepare($update_sale_sql);
    $update_sale_stmt->execute([$new_size, $new_item_code, $sales_id]);

    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Exchange processed successfully',
        'exchange_id' => $conn->lastInsertId()
    ]);

} catch (Exception $e) {
    if ($conn instanceof PDO && $conn->inTransaction()) {
        $conn->rollBack();
    }
    
    echo json_encode([
        'success' => false,
        'message' => 'Error processing exchange: ' . $e->getMessage()
    ]);
}