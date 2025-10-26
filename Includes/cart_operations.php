<?php
date_default_timezone_set('Asia/Manila');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'connection.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $response = ['success' => false, 'message' => '', 'cart_count' => 0];
    
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                if (!isset($_SESSION['user_id'])) {
                    $response['message'] = 'Please login to add items to cart';
                    break;
                }

                $item_code = $_POST['item_code'];
                $quantity = intval($_POST['quantity']);
                $size = isset($_POST['size']) && !empty($_POST['size']) ? $_POST['size'] : null;
                $user_id = $_SESSION['user_id'];

                $stmt = $conn->prepare("SELECT is_strike, last_strike_time FROM account WHERE id = ?");
                $stmt->execute([$user_id]);
                $userRow = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($userRow) {
                    if ($userRow['is_strike']) {
                        $response['message'] = 'You are temporarily blocked from ordering due to repeated unclaimed orders. Please contact admin.';
                        break;
                    }
                    if ($userRow['last_strike_time']) {
                        $lastStrike = strtotime($userRow['last_strike_time']);
                        $now = time();
                        if ($now - $lastStrike < 300) { // 300 seconds = 5 minutes
                            $response['message'] = 'You recently cancelled or failed to claim an order. As a penalty, you cannot place a new order for 5 minutes. Please try again later.';
                            break;
                        } else {
                            $clearStmt = $conn->prepare("UPDATE account SET last_strike_time = NULL WHERE id = ?");
                            $clearStmt->execute([$user_id]);
                        }
                    }
                }

                $stmt = $conn->prepare("SELECT category, actual_quantity FROM inventory WHERE item_code = ? OR item_code LIKE ? LIMIT 1");
                $stmt->execute([$item_code, $item_code . '-%']);
                $item = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$item) {
                    $response['message'] = 'Item not found in inventory';
                    break;
                }

                $reserved = 0;
                $orderStmt = $conn->prepare("SELECT items FROM orders WHERE status IN ('pending', 'approved')");
                $orderStmt->execute();
                while ($row = $orderStmt->fetch(PDO::FETCH_ASSOC)) {
                    $orderItems = json_decode($row['items'], true);
                    if (is_array($orderItems)) {
                        foreach ($orderItems as $orderItem) {
                            if ($orderItem['item_code'] === $item_code && ($size === null || $orderItem['size'] === $size)) {
                                $reserved += intval($orderItem['quantity']);
                            }
                        }
                    }
                }

                $stmt = $conn->prepare("SELECT SUM(quantity) as cart_quantity FROM cart WHERE user_id = ? AND item_code = ?");
                $stmt->execute([$user_id, $item_code]);
                $cart_quantity = $stmt->fetch(PDO::FETCH_ASSOC)['cart_quantity'] ?? 0;

                $available_stock = $item['actual_quantity'] - $reserved;
                if (($cart_quantity + $quantity) > $available_stock) {
                    $response['message'] = 'Adding this quantity would exceed available stock. Available: ' . $available_stock . ' (after reservation. Please come back ater 5 minutes)';
                    break;
                }

                if (stripos($item['category'], 'accessories') !== false || stripos($item['category'], 'sti-accessories') !== false) {
                    $size = 'One Size';
                }

                $stmt = $conn->prepare("SELECT id, quantity FROM cart WHERE user_id = ? AND item_code = ? AND (size = ? OR (size IS NULL AND ? IS NULL))");
                $stmt->execute([$user_id, $item_code, $size, $size]);
                $existing_item = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($existing_item) {
                    $stmt = $conn->prepare("UPDATE cart SET quantity = quantity + ? WHERE id = ?");
                    $success = $stmt->execute([$quantity, $existing_item['id']]);
                } else {
                    $stmt = $conn->prepare("INSERT INTO cart (user_id, item_code, quantity, size) VALUES (?, ?, ?, ?)");
                    $success = $stmt->execute([$user_id, $item_code, $quantity, $size]);
                }

                if (!$success) {
                    $response['message'] = 'Failed to update cart';
                    error_log("Failed to update cart - " . json_encode($stmt->errorInfo()));
                    break;
                }

                // Count unique items instead of total quantity
                $stmt = $conn->prepare("SELECT COUNT(DISTINCT item_code) as total FROM cart WHERE user_id = ?");
                $stmt->execute([$user_id]);
                $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
                
                $_SESSION['cart_count'] = $total;
                $response['success'] = true;
                $response['message'] = 'Item added to cart successfully';
                $response['cart_count'] = $total;
                break;

            case 'get_cart':
                if (!isset($_SESSION['user_id'])) {
                    $response['message'] = 'Please login to view cart';
                    break;
                }

                $user_id = $_SESSION['user_id'];
                
                $stmt = $conn->prepare("
                    SELECT 
                        c.*,
                        i.item_name,
                        i.price,
                        i.image_path,
                        i.category 
                    FROM cart c 
                    LEFT JOIN inventory i ON c.item_code = i.item_code
                    WHERE c.user_id = ?
                    GROUP BY c.id
                ");
                $stmt->execute([$user_id]);
                $cart_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $final_cart_items = [];
                foreach ($cart_items as $item) {
                    if ($item['item_name']) {
                        if (!empty($item['image_path'])) {
                            $image_name = basename($item['image_path']);
                            $itemlistPath = __DIR__ . '/../uploads/itemlist/' . $image_name;
                            $rawPath = __DIR__ . '/../' . ltrim($item['image_path'], '/');
                            if (file_exists($itemlistPath)) {
                                $item['image_path'] = '../uploads/itemlist/' . $image_name;
                            } elseif (file_exists($rawPath)) {
                                $item['image_path'] = '../' . ltrim($item['image_path'], '/');
                            } else {
                                $stmt2 = $conn->prepare("SELECT image_path FROM inventory WHERE item_code = ? AND image_path IS NOT NULL AND image_path != '' LIMIT 1");
                                $stmt2->execute([$item['item_code']]);
                                $row2 = $stmt2->fetch(PDO::FETCH_ASSOC);
                                if ($row2 && !empty($row2['image_path'])) {
                                    $item['image_path'] = '../' . ltrim($row2['image_path'], '/');
                                } else {
                                    $item['image_path'] = '../uploads/itemlist/default.jpg';
                                }
                            }
                        } else {
                            $stmt2 = $conn->prepare("SELECT image_path FROM inventory WHERE item_code = ? AND image_path IS NOT NULL AND image_path != '' LIMIT 1");
                            $stmt2->execute([$item['item_code']]);
                            $row2 = $stmt2->fetch(PDO::FETCH_ASSOC);
                            if ($row2 && !empty($row2['image_path'])) {
                                $item['image_path'] = '../' . ltrim($row2['image_path'], '/');
                            } else {
                                $item['image_path'] = '../uploads/itemlist/default.jpg';
                            }
                        }
                        $item['item_name'] = rtrim($item['item_name'], " SMLX234567");
                        
                        if (!isset($item['size'])) {
                            $item['size'] = null;
                        }
                        
                        $final_cart_items[] = $item;
                    } else {
                        $stmt = $conn->prepare("
                            SELECT item_name, price, image_path, category 
                            FROM inventory 
                            WHERE item_code LIKE ?
                            LIMIT 1
                        ");
                        $stmt->execute(['%' . $item['item_code'] . '%']);
                        $inventory_item = $stmt->fetch(PDO::FETCH_ASSOC);

                        if ($inventory_item) {
                            if ($inventory_item['image_path']) {
                                $image_name = basename($inventory_item['image_path']);
                                $inventory_item['image_path'] = '../uploads/itemlist/' . $image_name;
                            } else {
                                $inventory_item['image_path'] = '../uploads/itemlist/default.jpg';
                            }
                            $inventory_item['item_name'] = rtrim($inventory_item['item_name'], " SMLX234567");
                            $final_cart_items[] = array_merge($item, $inventory_item);
                        } else {
                            $final_cart_items[] = array_merge($item, [
                                'item_name' => 'Item no longer available',
                                'price' => 0,
                                'image_path' => '../uploads/itemlist/default.jpg'
                            ]);
                        }
                    }
                }

                // Count unique items instead of total quantity
                $stmt = $conn->prepare("SELECT COUNT(DISTINCT item_code) as total FROM cart WHERE user_id = ?");
                $stmt->execute([$user_id]);
                $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
                $_SESSION['cart_count'] = $total;

                $response['success'] = true;
                $response['cart_items'] = $final_cart_items;
                $response['cart_count'] = $total;
                break;

            case 'update':
                if (!isset($_SESSION['user_id'])) {
                    $response['message'] = 'Please login to update cart';
                    break;
                }

                $item_id = $_POST['item_id'];
                $quantity = intval($_POST['quantity']);
                $user_id = $_SESSION['user_id'];

                $stmt = $conn->prepare("SELECT item_code, quantity as current_cart_quantity FROM cart WHERE id = ? AND user_id = ?");
                $stmt->execute([$item_id, $user_id]);
                $cart_item = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$cart_item) {
                    $response['message'] = 'Item not found in cart';
                    break;
                }

                $stmt = $conn->prepare("SELECT actual_quantity FROM inventory WHERE item_code = ?");
                $stmt->execute([$cart_item['item_code']]);
                $inventory_item = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$inventory_item) {
                    $stmt = $conn->prepare("SELECT actual_quantity FROM inventory WHERE ? LIKE CONCAT(item_code, '-%') LIMIT 1");
                    $stmt->execute([$cart_item['item_code']]);
                    $inventory_item = $stmt->fetch(PDO::FETCH_ASSOC);
                }

                if (!$inventory_item) {
                    $response['message'] = 'Item not found in inventory';
                    break;
                }

                $item = [
                    'item_code' => $cart_item['item_code'],
                    'current_cart_quantity' => $cart_item['current_cart_quantity'],
                    'actual_quantity' => $inventory_item['actual_quantity']
                ];

                $stmt = $conn->prepare("
                    SELECT SUM(quantity) as other_cart_quantity 
                    FROM cart 
                    WHERE user_id = ? AND item_code = ? AND id != ?
                ");
                $stmt->execute([$user_id, $item['item_code'], $item_id]);
                $other_cart_quantity = $stmt->fetch(PDO::FETCH_ASSOC)['other_cart_quantity'] ?? 0;

                if (($other_cart_quantity + $quantity) > $item['actual_quantity']) {
                    $response['message'] = 'Updating to this quantity would exceed available stock. Available: ' . $item['actual_quantity'];
                    break;
                }

                $stmt = $conn->prepare("UPDATE cart SET quantity = ? WHERE id = ? AND user_id = ?");
                $stmt->execute([$quantity, $item_id, $user_id]);

                // Count unique items instead of total quantity
                $stmt = $conn->prepare("SELECT COUNT(DISTINCT item_code) as total FROM cart WHERE user_id = ?");
                $stmt->execute([$user_id]);
                $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
                
                $_SESSION['cart_count'] = $total;
                $response['success'] = true;
                $response['message'] = 'Cart updated successfully';
                $response['cart_count'] = $total;
                break;

            case 'remove':
                if (!isset($_SESSION['user_id'])) {
                    $response['message'] = 'Please login to remove items';
                    break;
                }

                $item_id = $_POST['item_id'];
                $user_id = $_SESSION['user_id'];

                $stmt = $conn->prepare("DELETE FROM cart WHERE id = ? AND user_id = ?");
                $stmt->execute([$item_id, $user_id]);

                // Count unique items instead of total quantity
                $stmt = $conn->prepare("SELECT COUNT(DISTINCT item_code) as total FROM cart WHERE user_id = ?");
                $stmt->execute([$user_id]);
                $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
                
                $_SESSION['cart_count'] = $total;
                $response['success'] = true;
                $response['message'] = 'Item removed successfully';
                $response['cart_count'] = $total;
                break;
        }
    }

    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}
?> 