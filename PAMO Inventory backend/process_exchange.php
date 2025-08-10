<?php
session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../Includes/connection.php'; // PDO $conn

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

    $update_old_sql = "UPDATE inventory SET actual_quantity = actual_quantity + 1 WHERE item_code = ?";
    $update_old_stmt = $conn->prepare($update_old_sql);
    $update_old_stmt->execute([$item_code]);

    $update_new_sql = "UPDATE inventory SET actual_quantity = actual_quantity - 1 WHERE item_code = ?";
    $update_new_stmt = $conn->prepare($update_new_sql);
    $update_new_stmt->execute([$new_item_code]);

    $exchange_sql = "INSERT INTO exchanges (sales_id, transaction_number, customer_id, customer_name, item_code, old_size, new_size, exchange_quantity, exchange_date, processed_by, remarks) VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW(), ?, ?)";
    $exchange_stmt = $conn->prepare($exchange_sql);
    if (!$exchange_stmt->execute([$sales_id, $transaction_number, $customer_id, $customer_name, $item_code, $old_size, $new_size, $processed_by, $remarks])) {
        throw new Exception('Failed to execute exchange insert');
    }

    $update_sale_sql = "UPDATE sales SET size = ?, item_code = ? WHERE id = ?";
    $update_sale_stmt = $conn->prepare($update_sale_sql);
    $update_sale_stmt->execute([$new_size, $new_item_code, $sales_id]);

    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Exchange processed successfully',
        'exchange_id' => mysqli_insert_id($conn)
    ]);

} catch (Exception $e) {
    if ($conn instanceof PDO && $conn->inTransaction()) { $conn->rollBack(); }
    
    echo json_encode([
        'success' => false,
        'message' => 'Error processing exchange: ' . $e->getMessage()
    ]);
}
// PDO closes automatically
?> 