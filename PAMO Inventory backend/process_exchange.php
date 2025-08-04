<?php
session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

$conn = mysqli_connect("localhost", "root", "", "proware");

if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

$sales_id = isset($_POST['sales_id']) ? intval($_POST['sales_id']) : 0;
$transaction_number = isset($_POST['transaction_number']) ? mysqli_real_escape_string($conn, $_POST['transaction_number']) : '';
$customer_id = isset($_POST['customer_id']) ? intval($_POST['customer_id']) : 0;
$customer_name = isset($_POST['customer_name']) ? mysqli_real_escape_string($conn, $_POST['customer_name']) : '';
$item_code = isset($_POST['item_code']) ? mysqli_real_escape_string($conn, $_POST['item_code']) : '';
$old_size = isset($_POST['old_size']) ? mysqli_real_escape_string($conn, $_POST['old_size']) : '';
$new_size = isset($_POST['new_size']) ? mysqli_real_escape_string($conn, $_POST['new_size']) : '';
$new_item_code = isset($_POST['new_item_code']) ? mysqli_real_escape_string($conn, $_POST['new_item_code']) : '';
$remarks = isset($_POST['remarks']) ? mysqli_real_escape_string($conn, $_POST['remarks']) : '';

if (!$sales_id || !$transaction_number || !$customer_id || !$customer_name || !$item_code || !$old_size || !$new_size || !$new_item_code) {
    echo json_encode(['success' => false, 'message' => 'All required fields must be provided']);
    exit;
}

$processed_by = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;

if (!$processed_by) {
    $check_admin_sql = "SELECT id FROM account WHERE id = 1 LIMIT 1";
    $check_result = mysqli_query($conn, $check_admin_sql);
    if (mysqli_num_rows($check_result) > 0) {
        $processed_by = 1;
    } else {
        $first_user_sql = "SELECT id FROM account WHERE status = 'active' ORDER BY id LIMIT 1";
        $first_user_result = mysqli_query($conn, $first_user_sql);
        if ($first_user_result && mysqli_num_rows($first_user_result) > 0) {
            $first_user = mysqli_fetch_assoc($first_user_result);
            $processed_by = $first_user['id'];
        } else {
            throw new Exception('No valid user found for processed_by field');
        }
    }
}

try {
    mysqli_begin_transaction($conn);

    $validate_sql = "SELECT * FROM sales WHERE id = ? AND sale_date >= DATE_SUB(NOW(), INTERVAL 24 HOUR)";
    $validate_stmt = mysqli_prepare($conn, $validate_sql);
    mysqli_stmt_bind_param($validate_stmt, "i", $sales_id);
    mysqli_stmt_execute($validate_stmt);
    $sale_result = mysqli_stmt_get_result($validate_stmt);
    
    if (mysqli_num_rows($sale_result) == 0) {
        throw new Exception('Sale not found or not eligible for exchange (must be within 24 hours)');
    }
    
    $sale_data = mysqli_fetch_assoc($sale_result);

    $new_item_sql = "SELECT * FROM inventory WHERE item_code = ?";
    $new_item_stmt = mysqli_prepare($conn, $new_item_sql);
    mysqli_stmt_bind_param($new_item_stmt, "s", $new_item_code);
    mysqli_stmt_execute($new_item_stmt);
    $new_item_result = mysqli_stmt_get_result($new_item_stmt);
    
    if (mysqli_num_rows($new_item_result) == 0) {
        throw new Exception('New size item not found in inventory. Looking for item_code: ' . $new_item_code);
    }
    
    $new_item_data = mysqli_fetch_assoc($new_item_result);
    if ($new_item_data['actual_quantity'] <= 0) {
        throw new Exception('New size item is out of stock. Available quantity: ' . $new_item_data['actual_quantity']);
    }

    $update_old_sql = "UPDATE inventory SET actual_quantity = actual_quantity + 1 WHERE item_code = ?";
    $update_old_stmt = mysqli_prepare($conn, $update_old_sql);
    mysqli_stmt_bind_param($update_old_stmt, "s", $item_code);
    mysqli_stmt_execute($update_old_stmt);

    $update_new_sql = "UPDATE inventory SET actual_quantity = actual_quantity - 1 WHERE item_code = ?";
    $update_new_stmt = mysqli_prepare($conn, $update_new_sql);
    mysqli_stmt_bind_param($update_new_stmt, "s", $new_item_code);
    mysqli_stmt_execute($update_new_stmt);

    $exchange_sql = "INSERT INTO exchanges (sales_id, transaction_number, customer_id, customer_name, item_code, old_size, new_size, exchange_quantity, exchange_date, processed_by, remarks) VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW(), ?, ?)";
    $exchange_stmt = mysqli_prepare($conn, $exchange_sql);
    if (!$exchange_stmt) {
        throw new Exception('Failed to prepare exchange insert statement: ' . mysqli_error($conn));
    }
    
    mysqli_stmt_bind_param($exchange_stmt, "isissssss", $sales_id, $transaction_number, $customer_id, $customer_name, $item_code, $old_size, $new_size, $processed_by, $remarks);
    if (!mysqli_stmt_execute($exchange_stmt)) {
        throw new Exception('Failed to execute exchange insert: ' . mysqli_stmt_error($exchange_stmt));
    }

    $update_sale_sql = "UPDATE sales SET size = ?, item_code = ? WHERE id = ?";
    $update_sale_stmt = mysqli_prepare($conn, $update_sale_sql);
    mysqli_stmt_bind_param($update_sale_stmt, "ssi", $new_size, $new_item_code, $sales_id);
    mysqli_stmt_execute($update_sale_stmt);

    mysqli_commit($conn);

    echo json_encode([
        'success' => true,
        'message' => 'Exchange processed successfully',
        'exchange_id' => mysqli_insert_id($conn)
    ]);

} catch (Exception $e) {
    mysqli_rollback($conn);
    
    echo json_encode([
        'success' => false,
        'message' => 'Error processing exchange: ' . $e->getMessage()
    ]);
}

mysqli_close($conn);
?> 