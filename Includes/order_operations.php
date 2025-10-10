<?php
// Prevent any HTML output
ob_start();
error_reporting(0);
ini_set('display_errors', 0);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'connection.php';

// Clean any previous output
ob_clean();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $response = ['success' => false, 'message' => '', 'orders' => [], 'last_update' => null];
    
    if (!isset($_SESSION['user_id'])) {
        $response['message'] = 'User not authenticated';
        echo json_encode($response);
        exit;
    }

    if (isset($_POST['action'])) {
        try {
            switch ($_POST['action']) {
                case 'get_user_orders':
                    $user_id = $_SESSION['user_id'];
                    $status_filter = $_POST['status'] ?? 'all';
                    $last_check = $_POST['last_check'] ?? null;
                    
                    // Build optimized query with indexes
                    if ($last_check) {
                        // Incremental update query - only get changed orders
                        if ($status_filter !== 'all') {
                            $query = "SELECT * FROM orders 
                                     WHERE user_id = ? AND status = ? 
                                     AND (updated_at > ? OR created_at > ?) 
                                     ORDER BY updated_at DESC, created_at DESC 
                                     LIMIT 20";
                            $params = [$user_id, $status_filter, $last_check, $last_check];
                        } else {
                            $query = "SELECT * FROM orders 
                                     WHERE user_id = ? 
                                     AND (updated_at > ? OR created_at > ?) 
                                     ORDER BY updated_at DESC, created_at DESC 
                                     LIMIT 20";
                            $params = [$user_id, $last_check, $last_check];
                        }
                    } else {
                        // Full query for initial load
                        if ($status_filter !== 'all') {
                            $query = "SELECT * FROM orders 
                                     WHERE user_id = ? AND status = ? 
                                     ORDER BY created_at DESC 
                                     LIMIT 50";
                            $params = [$user_id, $status_filter];
                        } else {
                            $query = "SELECT * FROM orders 
                                     WHERE user_id = ? 
                                     ORDER BY created_at DESC 
                                     LIMIT 50";
                            $params = [$user_id];
                        }
                    }
                    
                    $stmt = $conn->prepare($query);
                    $stmt->execute($params);
                    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    // Format the orders for frontend
                    foreach ($orders as &$order) {
                        $order['items_decoded'] = json_decode($order['items'], true);
                        $order['formatted_date'] = date('F d, Y h:i A', strtotime($order['created_at']));
                        if ($order['payment_date']) {
                            $order['formatted_payment_date'] = date('F d, Y h:i A', strtotime($order['payment_date']));
                        }
                        
                        // Calculate total amount
                        $total = 0;
                        if ($order['items_decoded'] && is_array($order['items_decoded'])) {
                            foreach ($order['items_decoded'] as $item) {
                                $total += ($item['price'] ?? 0) * ($item['quantity'] ?? 0);
                            }
                        }
                        $order['total_amount'] = $total;
                        $order['formatted_total'] = number_format($total, 2);
                    }
                    
                    $response['success'] = true;
                    $response['orders'] = $orders;
                    $response['last_update'] = date('Y-m-d H:i:s');
                    break;

                case 'get_pending_count':
                    // Check if user has PAMO access
                    $role = strtoupper($_SESSION['role_category'] ?? '');
                    $program = strtoupper($_SESSION['program_abbreviation'] ?? '');
                    
                    if (!($role === 'EMPLOYEE' && $program === 'PAMO')) {
                        throw new Exception('Unauthorized access');
                    }
                    
                    // Get count of pending orders
                    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM orders WHERE status = 'pending'");
                    $stmt->execute();
                    $result = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    $response['success'] = true;
                    $response['pending_count'] = (int)$result['count'];
                    break;

                case 'get_pamo_orders':
                    // Check if user has PAMO access
                    $role = strtoupper($_SESSION['role_category'] ?? '');
                    $program = strtoupper($_SESSION['program_abbreviation'] ?? '');
                    
                    if (!($role === 'EMPLOYEE' && $program === 'PAMO')) {
                        throw new Exception('Unauthorized access');
                    }
                    
                    $status_filter = $_POST['status'] ?? '';
                    $last_check = $_POST['last_check'] ?? null;
                    
                    // Optimized query with proper indexing
                    if ($last_check) {
                        // Incremental update - get orders that are new or updated since last check
                        if ($status_filter) {
                            $query = "
                                SELECT po.*, a.first_name, a.last_name, a.email, a.program_or_position, a.id_number
                                FROM orders po
                                INNER JOIN account a ON po.user_id = a.id
                                WHERE po.status = ? 
                                AND (po.updated_at > ? OR po.created_at > ?)
                                ORDER BY po.created_at DESC
                                LIMIT 50
                            ";
                            $params = [$status_filter, $last_check, $last_check];
                        } else {
                            $query = "
                                SELECT po.*, a.first_name, a.last_name, a.email, a.program_or_position, a.id_number
                                FROM orders po
                                INNER JOIN account a ON po.user_id = a.id
                                WHERE (po.updated_at > ? OR po.created_at > ?)
                                ORDER BY po.created_at DESC
                                LIMIT 50
                            ";
                            $params = [$last_check, $last_check];
                        }
                    } else {
                        // Full query for initial load
                        if ($status_filter) {
                            $query = "
                                SELECT po.*, a.first_name, a.last_name, a.email, a.program_or_position, a.id_number
                                FROM orders po
                                INNER JOIN account a ON po.user_id = a.id
                                WHERE po.status = ?
                                ORDER BY po.created_at DESC
                                LIMIT 100
                            ";
                            $params = [$status_filter];
                        } else {
                            $query = "
                                SELECT po.*, a.first_name, a.last_name, a.email, a.program_or_position, a.id_number
                                FROM orders po
                                INNER JOIN account a ON po.user_id = a.id
                                ORDER BY po.created_at DESC
                                LIMIT 100
                            ";
                            $params = [];
                        }
                    }
                    
                    $stmt = $conn->prepare($query);
                    $stmt->execute($params);
                    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    // Add category to each item and format data
                    foreach ($orders as &$order) {
                        $order_items = json_decode($order['items'], true);
                        $total_amount = 0;
                        
                        if ($order_items && is_array($order_items)) {
                            foreach ($order_items as &$item) {
                                // Fetch category from inventory
                                $item_code = $item['item_code'] ?? '';
                                if ($item_code) {
                                    $cat_stmt = $conn->prepare("SELECT category FROM inventory WHERE item_code = ? LIMIT 1");
                                    $cat_stmt->execute([$item_code]);
                                    $cat_row = $cat_stmt->fetch(PDO::FETCH_ASSOC);
                                    $item['category'] = $cat_row ? $cat_row['category'] : '';
                                } else {
                                    $item['category'] = '';
                                }
                                
                                // Calculate total
                                $total_amount += ($item['price'] ?? 0) * ($item['quantity'] ?? 0);
                            }
                        }
                        
                        $order['items_decoded'] = $order_items;
                        $order['total_amount'] = $total_amount;
                        $order['formatted_total'] = number_format($total_amount, 2);
                        $order['formatted_date'] = date('F d, Y h:i A', strtotime($order['created_at']));
                        
                        if ($order['payment_date']) {
                            $order['formatted_payment_date'] = date('F d, Y h:i A', strtotime($order['payment_date']));
                        }
                    }
                    
                    $response['success'] = true;
                    $response['orders'] = $orders;
                    $response['last_update'] = date('Y-m-d H:i:s');
                    break;

                default:
                    throw new Exception('Invalid action');
            }
        } catch (Exception $e) {
            $response['success'] = false;
            $response['message'] = $e->getMessage();
        }
    } else {
        $response['message'] = 'Action is required';
    }

    // Clean output buffer and send JSON
    ob_clean();
    echo json_encode($response);
    ob_end_flush();
    exit;
}
?>