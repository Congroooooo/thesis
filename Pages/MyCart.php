<?php
session_start();
require_once '../Includes/connection.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();

}

include '../Includes/loader.php';
// Get cart items
$cart_query = $conn->prepare("SELECT * FROM cart WHERE user_id = ?");
$cart_query->execute([$_SESSION['user_id']]);
$cart_items = $cart_query->fetchAll(PDO::FETCH_ASSOC);

$final_cart_items = [];
foreach ($cart_items as $cart_item) {
    // Try to get inventory details for each cart item
    $stmt = $conn->prepare("SELECT item_name, price, image_path, category, actual_quantity FROM inventory WHERE item_code = ?");
    $stmt->execute([$cart_item['item_code']]);
    $inventory_item = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Fallback logic for image
    if ($inventory_item && (empty($inventory_item['image_path']) || !file_exists('../' . $inventory_item['image_path']))) {
        // Get prefix before dash
        $prefix = explode('-', $cart_item['item_code'])[0];
        $stmt2 = $conn->prepare("SELECT image_path FROM inventory WHERE item_code LIKE ? AND image_path IS NOT NULL AND image_path != '' LIMIT 1");
        $likePrefix = $prefix . '-%';
        $stmt2->execute([$likePrefix]);
        $row2 = $stmt2->fetch(PDO::FETCH_ASSOC);
        if ($row2 && !empty($row2['image_path'])) {
            $inventory_item['image_path'] = $row2['image_path'];
        } else {
            $inventory_item['image_path'] = 'uploads/itemlist/default.png'; // fallback default
        }
    }
    
    if ($inventory_item) {
        $final_cart_items[] = array_merge($cart_item, $inventory_item);
    } else {
        // Try to find the item with a LIKE query to catch potential formatting differences
        $stmt = $conn->prepare("SELECT item_name, price, image_path, category, actual_quantity FROM inventory WHERE item_code LIKE ?");
        $stmt->execute(['%' . $cart_item['item_code'] . '%']);
        $inventory_item = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Fallback logic for image
        if ($inventory_item && (empty($inventory_item['image_path']) || !file_exists('../' . $inventory_item['image_path']))) {
            $prefix = explode('-', $cart_item['item_code'])[0];
            $stmt2 = $conn->prepare("SELECT image_path FROM inventory WHERE item_code LIKE ? AND image_path IS NOT NULL AND image_path != '' LIMIT 1");
            $likePrefix = $prefix . '-%';
            $stmt2->execute([$likePrefix]);
            $row2 = $stmt2->fetch(PDO::FETCH_ASSOC);
            if ($row2 && !empty($row2['image_path'])) {
                $inventory_item['image_path'] = $row2['image_path'];
            } else {
                $inventory_item['image_path'] = 'uploads/itemlist/default.png';
            }
        }
        
        if ($inventory_item) {
            $final_cart_items[] = array_merge($cart_item, $inventory_item);
        } else {
            $final_cart_items[] = array_merge($cart_item, [
                'item_name' => 'Item no longer available',
                'price' => 0,
                'image_path' => 'uploads/itemlist/default.png',
                'category' => '',
                'actual_quantity' => 0
            ]);
        }
    }
}

$cart_total = 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Cart</title>
    <link rel="stylesheet" href="../CSS/global.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Anton&family=Smooch+Sans:wght@100..900&display=swap" rel="stylesheet">
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
<?php
    include("../Includes/Header.php");
    ?>

    <div class="cart-page">
        <div class="heading-cart">
            <div class="header-content">
                <h1><i class="fas fa-shopping-cart"></i> My Cart</h1>
                
            </div>
        </div>

        <div class="cart-content">
            <?php if (!empty($final_cart_items)): ?>
                <div class="cart-grid">
                    <div class="cart-items-container">
                        <table class="cart-table">
                            <thead>
                                <tr>
                                    <th class="image-col">Image</th>
                                    <th class="item-col">Item Name</th>
                                    <th class="size-col">Size</th>
                                    <th class="price-col">Price</th>
                                    <th class="quantity-col">Quantity</th>
                                    <th class="subtotal-col">Subtotal</th>
                                    <th class="action-col"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($final_cart_items as $item):
                                    $subtotal = $item['price'] * $item['quantity'];
                                    $cart_total += $subtotal;
                                ?>
                                <tr class="cart-row">
                                    <td class="image-col" data-label="Image">
                                        <div class="item-image">
                                            <img src="../<?php echo htmlspecialchars($item['image_path']); ?>"
                                                alt="<?php echo htmlspecialchars($item['item_name']); ?>">
                                                
                                        </div>
                                    </td>
                                    <td class="item-col" data-label="Item Name">
                                        <div class="item-name"><?php echo htmlspecialchars($item['item_name']); ?></div>
                                    </td>
                                    <td class="size-col" data-label="Size">
                                        <?php if (!empty($item['size'])): ?>
                                            <span class="item-size"><?php echo htmlspecialchars($item['size']); ?></span>
                                        <?php else: ?>
                                            <span class="item-size-na">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="price-col" data-label="Price">
                                        <span class="item-price">₱<?php echo number_format($item['price'], 2); ?></span>
                                    </td>
                                    <td class="quantity-col" data-label="Quantity">
                                        <div class="quantity-control">
                                            <button type="button" class="qty-btn minus">-</button>
                                            <input type="number" value="<?php echo $item['quantity']; ?>" 
                                                   min="1" max="<?php echo $item['actual_quantity']; ?>" 
                                                   class="qty-input" 
                                                   data-item-id="<?php echo $item['id']; ?>"
                                                   data-item-code="<?php echo $item['item_code']; ?>"
                                                   data-max-stock="<?php echo $item['actual_quantity']; ?>">
                                            <button type="button" class="qty-btn plus">+</button>
                                        </div>
                                    </td>
                                    <td class="subtotal-col" data-label="Subtotal">
                                        <span class="item-subtotal">₱<?php echo number_format($subtotal, 2); ?></span>
                                    </td>
                                    <td class="action-col">
                                        <button type="button" onclick="removeFromCart(<?php echo $item['id']; ?>); return false;" class="remove-btn" title="Remove item">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <!-- MOBILE CART CARDS START -->
                    <div class="cart-items-mobile">
                        <?php foreach ($final_cart_items as $item):
                            $subtotal = $item['price'] * $item['quantity'];
                        ?>
                        <div class="cart-item-card">
                            <div class="card-img-section">
                                <img src="../<?php echo htmlspecialchars($item['image_path']); ?>" alt="<?php echo htmlspecialchars($item['item_name']); ?>">
                            </div>
                            <div class="card-details-section">
                                <div class="card-title-row">
                                    <div class="card-item-name"><?php echo htmlspecialchars($item['item_name']); ?></div>
                                    <button type="button" onclick="removeFromCart(<?php echo $item['id']; ?>); return false;" class="remove-btn" title="Remove item">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                                <div class="card-meta-row">
                                    <span class="card-item-size">
                                        <?php echo !empty($item['size']) ? htmlspecialchars($item['size']) : '-'; ?>
                                    </span>
                                </div>
                                <div class="card-price-row">
                                    <span class="card-item-price">₱<?php echo number_format($item['price'], 2); ?></span>
                                </div>
                                <div class="card-qty-row">
                                    <div class="quantity-control">
                                        <button type="button" class="qty-btn minus">-</button>
                                        <input type="number" value="<?php echo $item['quantity']; ?>" 
                                               min="1" max="<?php echo $item['actual_quantity']; ?>" 
                                               class="qty-input" 
                                               data-item-id="<?php echo $item['id']; ?>"
                                               data-item-code="<?php echo $item['item_code']; ?>"
                                               data-max-stock="<?php echo $item['actual_quantity']; ?>">
                                        <button type="button" class="qty-btn plus">+</button>
                                    </div>
                                    <span class="card-item-subtotal">Subtotal: ₱<?php echo number_format($subtotal, 2); ?></span>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <!-- MOBILE CART CARDS END -->
                    <div class="cart-summary">
                        <h2>Order Summary</h2>
                        <div class="summary-details">
                            <div class="summary-row">
                                <span>Total Items</span>
                                <span><?php 
                                    $total_quantity = 0;
                                    foreach ($final_cart_items as $item) {
                                        $total_quantity += $item['quantity'];
                                    }
                                    echo $total_quantity; 
                                ?></span>
                            </div>
                            <div class="summary-row total">
                                <span>Total Amount</span>
                                <span>₱<?php echo number_format($cart_total, 2); ?></span>
                            </div>
                        </div>
                        <div class="button-container">
                            <a href="ProPreOrder.php" class="checkout-btn">
                                <i class="fas fa-lock"></i>
                                Proceed to Order
                            </a>
                            <a href="ProItemList.php" class="continue-shopping">
                                <i class="fas fa-arrow-left"></i>
                                Continue Shopping
                            </a>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="empty-cart">
                    <i class="fas fa-shopping-basket"></i>
                    <h2>Your cart is empty</h2>
                    <p>Looks like you haven't added any items to your cart yet.</p>
                    <a href="ProItemList.php" class="shop-now-btn">Start Shopping</a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <style>
        .cart-page {
            padding-top: 80px;
            min-height: 100vh;
            background-color: #f4ebb6;
        }

        .heading-cart {
            background: linear-gradient(135deg, var(--primary-color) 0%, #005a94 100%);
            padding: 2rem 0;
            margin-bottom: 2rem;
            margin-top: -10px;
            color: white;
        }

        .header-content {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 2rem;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .header-content h1 {
            margin: 0;
            font-size: 2rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 1rem;
            color: yellow;
        }

        .cart-content {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 2rem;
        }

        .cart-grid {
            display: grid;
            grid-template-columns: 1fr 400px;
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .cart-items-container {
            overflow-x: auto;
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        .cart-table {
            width: 100%;
            border-collapse: collapse;
        }

        .cart-table td {
            padding: 1rem;
            text-align: center;
            vertical-align: middle;
            height: 100px; /* Set a consistent height for all cells */
        }

        .cart-table th {
            background-color: #f8f9fa;
            font-weight: 600;
            color: #333;
            border-bottom: 2px solid #eee;
            text-align: center;
            padding: 1.5rem 1rem;
        }

        .cart-row {
            border-bottom: 1px solid #eee;
            transition: background-color 0.2s ease;
        }

        .cart-row:hover {
            background-color: #f9f9f9;
        }

        .cart-row:last-child {
            border-bottom: none;
        }

        .image-col {
            width: 120px;
        }

        .item-col {
            width: 300px;
        }

        .size-col {
            width: 100px;
            text-align: center;
        }

        .price-col {
            width: 120px;
            text-align: center;
        }

        .quantity-col {
            width: 80px;
            text-align: center;
        }

        .subtotal-col {
            width: 150px;
            text-align: center;
        }

        .action-col {
            width: 50px;
            text-align: center;
        }

        .item-image {
            width: 80px;
            height: 80px;
            border-radius: 8px;
            overflow: hidden;
            margin: 0 auto; /* Center the image */
        }

        .item-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .item-col h3 {
            margin: 0;
            color: var(--primary-color);
            font-size: 1.1rem;
            font-weight: 500;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100%;
        }
        
        .item-name {
            color: var(--primary-color);
            font-size: 1.1rem;
            font-weight: 500;
        }

        .item-price, .item-quantity, .item-subtotal {
            display: inline-block;
            font-weight: 600;
            color: #444;
        }
        
        .item-size {
            display: inline-block;
            font-weight: 500;
            background-color: #e6f2ff;
            color: #0066cc;
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 0.9rem;
        }
        
        .item-size-na {
            color: #999;
            font-style: italic;
        }

        .item-quantity {
            font-weight: 500;
            background-color: #f5f5f5;
            padding: 4px 10px;
            border-radius: 4px;
        }

        .item-subtotal {
            font-weight: 600;
            color: var(--primary-color);
        }

        .remove-btn {
            background: none;
            border: none;
            color: #dc3545;
            cursor: pointer;
            font-size: 1.2rem;
            padding: 0.5rem;
            transition: all 0.3s ease;
            opacity: 0.7;
        }

        .remove-btn:hover {
            color: #c82333;
            opacity: 1;
            transform: scale(1.1);
        }

        .cart-summary {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            margin: 0 0.5rem;
        }

        .cart-summary h2 {
            margin: 0 0 1rem 0;
            color: var(--primary-color);
            font-size: 1.3rem;
            font-weight: 600;
        }

        .summary-details {
            margin-bottom: 1.5rem;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 0.75rem 0;
            border-bottom: 1px solid #eee;
            color: #666;
        }

        .summary-row.total {
            border-bottom: none;
            color: var(--primary-color);
            font-size: 1.1rem;
            font-weight: 600;
            margin-top: 0.5rem;
            padding-top: 0.5rem;
            border-top: 2px solid #000;
            margin-bottom: 1.5rem;
        }

        .button-container {
            display: flex;
            flex-direction: column;
            gap: 1rem;
            width: 100%;
        }

        .checkout-btn,
        .continue-shopping {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            max-width: 100%;
            padding: 0.875rem 1rem;
            text-align: center;
            border-radius: 8px;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.3s ease;
            font-size: 0.95rem;
            box-sizing: border-box;
        }

        .checkout-btn {
            background: var(--primary-color);
            color: white;
        }

        .continue-shopping {
            background: #a6d1e6;
            color: var(--primary-color);
            border: none;
            margin: 0;
        }

        .checkout-btn i,
        .continue-shopping i {
            margin-right: 0.5rem;
        }

        .empty-cart {
            text-align: center;
            padding: 4rem 2rem;
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        .empty-cart i {
            font-size: 4rem;
            color: var(--primary-color);
            margin-bottom: 1.5rem;
        }

        .empty-cart h2 {
            color: var(--primary-color);
            margin-bottom: 1rem;
        }

        .empty-cart p {
            color: #666;
            font-size: 1.2rem;
            margin-bottom: 2rem;
        }

        .shop-now-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 1rem 2.5rem;
            background: var(--primary-color);
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .shop-now-btn:hover {
            background: yellow;
            color: black;
            transform: translateY(-2px);
        }

        @media (max-width: 1024px) {
            .cart-grid {
                grid-template-columns: 1fr;
            }

            .cart-summary {
                position: static;
                margin: 0 0.5rem;
            }
        }

        @media (max-width: 768px) {
            .cart-page {
                padding-top: 70px;
            }

            .header-content {
                flex-direction: column;
                gap: 1rem;
                text-align: center;
                padding: 1rem;
            }

            .cart-content {
                padding: 0.5rem;
            }
            
            .cart-table {
                width: 100%;
            }

            .cart-items-container {
                margin: 0 0.5rem 1rem;
                width: 100%;
            }

            .cart-table td {
                display: table-cell;
                vertical-align: middle;
            }

            .cart-table tr {
                display: table-row;
            }

            .cart-table tbody {
                display: table-row-group;
            }

            .size-col, .price-col, .quantity-col {
                min-width: 80px;
                text-align: center;
            }

            .item-size, .item-price, .quantity-control {
                display: inline-block;
                text-align: center;
            }

            .quantity-control {
                min-width: 90px;
                display: flex;
                justify-content: center;
            }
        }

        @media (max-width: 480px) {
            .cart-content {
                padding: 0.25rem;
            }

            .cart-items-container {
                margin: 0 0.25rem 1rem;
            }

            .size-col, .price-col, .quantity-col {
                min-width: 60px;
            }

            .cart-table td::before {
                width: 100px;
                font-size: 0.9rem;
            }

            .item-image {
                width: 80px;
                height: 80px;
            }

            .item-name {
                font-size: 0.95rem;
            }

            .item-size, .item-quantity {
                font-size: 0.9rem;
            }

            .item-subtotal {
                font-size: 1rem;
            }

            .cart-summary {
                padding: 1rem;
            }

            .cart-summary h2 {
                font-size: 1.2rem;
                margin-bottom: 0.75rem;
            }
        }

        .quantity-control {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
        }

        .qty-btn {
            background-color: #f0f0f0;
            border: none;
            width: 24px;
            height: 24px;
            border-radius: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-weight: bold;
            transition: all 0.2s ease;
            position: relative;
        }

        .qty-btn:hover {
            background-color: #e0e0e0;
        }

        .qty-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .qty-btn.updating {
            pointer-events: none;
        }

        .qty-btn.updating::after {
            content: '';
            position: absolute;
            width: 12px;
            height: 12px;
            border: 2px solid #ccc;
            border-top-color: var(--primary-color);
            border-radius: 50%;
            animation: spin 0.6s linear infinite;
        }

        .qty-btn.success {
            background-color: #d4edda;
            color: #28a745;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        @keyframes successFade {
            0% { 
                background-color: #d4edda;
                transform: scale(1.1);
            }
            100% { 
                background-color: #f0f0f0;
                transform: scale(1);
            }
        }

        .qty-input {
            width: 40px;
            text-align: center;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 2px;
            font-weight: 500;
        }

        .qty-input::-webkit-inner-spin-button,
        .qty-input::-webkit-outer-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }

        .cart-items-mobile {
            display: none;
        }
        @media (min-width: 769px) {
            .cart-items-mobile {
                display: none !important;
            }
            .cart-items-container {
                display: block;
            }
        }
        @media (max-width: 768px) {
            .cart-items-container {
                display: none !important;
            }
            .cart-items-mobile {
                display: block;
            }
            .cart-items-mobile {
                margin: 0 0.5rem 1rem;
            }
            .cart-item-card {
                display: flex;
                gap: 1rem;
                background: #fff;
                border-radius: 10px;
                box-shadow: 0 2px 8px rgba(0,0,0,0.06);
                margin-bottom: 1.2rem;
                padding: 1rem;
                align-items: flex-start;
            }
            .card-img-section {
                flex: 0 0 80px;
                width: 80px;
                height: 80px;
                border-radius: 8px;
                overflow: hidden;
                background: #f7f7f7;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            .card-img-section img {
                width: 100%;
                height: 100%;
                object-fit: cover;
            }
            .card-details-section {
                flex: 1 1 auto;
                display: flex;
                flex-direction: column;
                gap: 0.3rem;
            }
            .card-title-row {
                display: flex;
                justify-content: space-between;
                align-items: flex-start;
                gap: 0.5rem;
            }
            .card-item-name {
                font-size: 1rem;
                font-weight: 600;
                color: var(--primary-color);
                margin-bottom: 0.1rem;
                word-break: break-word;
            }
            .remove-btn {
                font-size: 1.1rem;
                color: #dc3545;
                background: none;
                border: none;
                cursor: pointer;
                opacity: 0.7;
                padding: 0.2rem 0.4rem;
            }
            .remove-btn:hover {
                color: #c82333;
                opacity: 1;
            }
            .card-meta-row {
                font-size: 0.92rem;
                color: #888;
            }
            .card-item-size {
                background: #e6f2ff;
                color: #0066cc;
                border-radius: 4px;
                padding: 2px 8px;
                font-size: 0.9rem;
            }
            .card-price-row {
                font-size: 1.05rem;
                color: #444;
                font-weight: 600;
            }
            .card-qty-row {
                display: flex;
                align-items: center;
                gap: 1rem;
                margin-top: 0.2rem;
            }
            .card-item-subtotal {
                font-size: 0.98rem;
                color: var(--primary-color);
                font-weight: 500;
            }
        }
    </style>

    <script>
        let currentItemIdToRemove = null;

        function removeFromCart(itemId) {
            currentItemIdToRemove = itemId;
            showRemoveModal();
            return false;
        }

        function showRemoveModal() {
            const removeModal = document.getElementById('removeItemModal');
            if (!removeModal) {
                if (currentItemIdToRemove && confirm('Are you sure you want to remove this item from your cart?')) {
                    confirmRemoveItem();
                }
                return false;
            }
            
            removeModal.classList.add('show');
            document.body.style.overflow = 'hidden';

            const confirmBtn = removeModal.querySelector('.remove-btn-confirm');
            if (confirmBtn) {
                confirmBtn.focus();
            }
            
            return false;
        }

        function hideRemoveModal() {
            const removeModal = document.getElementById('removeItemModal');
            if (removeModal) {
                removeModal.classList.remove('show');
            }
            document.body.style.overflow = '';
            currentItemIdToRemove = null;
            return false;
        }

        function confirmRemoveItem() {
            if (currentItemIdToRemove) {
                const itemIdToRemove = currentItemIdToRemove;
                
                fetch('remove_from_cart.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `item_id=${itemIdToRemove}`
                })
                .then(response => response.json())
                .then(data => {
                    hideRemoveModal();
                    if (data.success) {
                        removeItemFromUI(itemIdToRemove, data.cart_count);
                    } else {
                        alert('Error removing item from cart: ' + (data.error || 'Unknown error'));
                        location.reload();
                    }
                })
                .catch(error => {
                    hideRemoveModal();
                    alert('Error removing item from cart');
                    location.reload();
                });
            }
            
            return false;
        }

        function removeItemFromUI(itemId, totalCartCount) {
            const inputs = document.querySelectorAll(`.qty-input[data-item-id="${itemId}"]`);
            
            inputs.forEach((input) => {
                const row = input.closest('.cart-row');
                const card = input.closest('.cart-item-card');
                
                if (row) {
                    row.style.transition = 'all 0.3s ease';
                    row.style.opacity = '0';
                    row.style.transform = 'translateX(-20px)';
                    setTimeout(() => row.remove(), 300);
                }
                
                if (card) {
                    card.style.transition = 'all 0.3s ease';
                    card.style.opacity = '0';
                    card.style.transform = 'translateX(-20px)';
                    setTimeout(() => card.remove(), 300);
                }
            });
            
            setTimeout(() => {
                updateCartTotal();
                
                const cartCountElements = document.querySelectorAll('.cart-count, .notification-badge');
                cartCountElements.forEach(el => {
                    el.textContent = totalCartCount;
                    el.style.display = totalCartCount > 0 ? 'block' : 'none';
                });
                
                const remainingRows = document.querySelectorAll('.cart-row').length;
                if (remainingRows === 0) {
                    showEmptyCartMessage();
                }
            }, 350);
        }

        function showEmptyCartMessage() {
            // Hide the cart grid
            const cartGrid = document.querySelector('.cart-grid');
            if (cartGrid) {
                cartGrid.style.display = 'none';
            }

            // Create and show empty cart message
            const cartContent = document.querySelector('.cart-content');
            if (cartContent) {
                const emptyDiv = document.createElement('div');
                emptyDiv.className = 'empty-cart';
                emptyDiv.innerHTML = `
                    <i class="fas fa-shopping-basket"></i>
                    <h2>Your cart is empty</h2>
                    <p>Looks like you haven't added any items to your cart yet.</p>
                    <a href="ProItemList.php" class="shop-now-btn">Start Shopping</a>
                `;
                cartContent.appendChild(emptyDiv);
            }
        }

        // Quantity decrease modal functionality
        let currentItemToDecreaseQuantity = null;

        function showQuantityDecreaseModal(itemId, input) {
            currentItemToDecreaseQuantity = { itemId, input };
            const modal = document.getElementById('quantityDecreaseModal');
            if (!modal) {
                return;
            }
            
            modal.classList.add('show');
            document.body.style.overflow = 'hidden';
            
            // Focus management for accessibility
            const confirmBtn = modal.querySelector('.qty-btn-confirm');
            if (confirmBtn) {
                confirmBtn.focus();
            }
        }

        function hideQuantityDecreaseModal() {
            const modal = document.getElementById('quantityDecreaseModal');
            if (modal) {
                modal.classList.remove('show');
            }
            document.body.style.overflow = '';
            currentItemToDecreaseQuantity = null;
            return false; // Prevent default action
        }

        function confirmDecreaseQuantity() {
            if (currentItemToDecreaseQuantity) {
                const { itemId } = currentItemToDecreaseQuantity;
                
                fetch('remove_from_cart.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `item_id=${itemId}`
                })
                .then(response => response.json())
                .then(data => {
                    hideQuantityDecreaseModal();
                    if (data.success) {
                        removeItemFromUI(itemId, data.cart_count);
                    } else {
                        alert('Error removing item from cart');
                        location.reload();
                    }
                })
                .catch(error => {
                    hideQuantityDecreaseModal();
                    alert('Error removing item from cart');
                    location.reload();
                });
            }
            
            return false;
        }

        document.addEventListener("DOMContentLoaded", function () {
            const removeModal = document.getElementById('removeItemModal');
            if (removeModal) {
                const closeBtn = removeModal.querySelector('.remove-modal-close');
                const cancelBtn = removeModal.querySelector('.remove-btn-cancel');
                const confirmBtn = removeModal.querySelector('.remove-btn-confirm');

                closeBtn?.addEventListener('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    hideRemoveModal();
                });
                cancelBtn?.addEventListener('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    hideRemoveModal();
                });
                confirmBtn?.addEventListener('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    confirmRemoveItem();
                });

                removeModal.addEventListener('click', (e) => {
                    if (e.target === removeModal) {
                        hideRemoveModal();
                    }
                });

                document.addEventListener('keydown', (e) => {
                    const modal = document.getElementById('removeItemModal');
                    if (e.key === 'Escape' && modal && modal.classList.contains('show')) {
                        hideRemoveModal();
                    }
                });
            }

            // Initialize quantity decrease modal
            const qtyModal = document.getElementById('quantityDecreaseModal');
            if (qtyModal) {
                const qtyCloseBtn = qtyModal.querySelector('.qty-modal-close');
                const qtyCancelBtn = qtyModal.querySelector('.qty-btn-cancel');
                const qtyConfirmBtn = qtyModal.querySelector('.qty-btn-confirm');

                qtyCloseBtn?.addEventListener('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    hideQuantityDecreaseModal();
                });
                qtyCancelBtn?.addEventListener('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    hideQuantityDecreaseModal();
                });
                qtyConfirmBtn?.addEventListener('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    confirmDecreaseQuantity();
                });

                qtyModal.addEventListener('click', (e) => {
                    if (e.target === qtyModal) {
                        hideQuantityDecreaseModal();
                    }
                });

                document.addEventListener('keydown', (e) => {
                    const modal = document.getElementById('quantityDecreaseModal');
                    if (e.key === 'Escape' && modal && modal.classList.contains('show')) {
                        hideQuantityDecreaseModal();
                    }
                });
            }

            document.querySelectorAll(".qty-btn").forEach((btn) => {
                btn.addEventListener("click", function () {
                    const input = this.parentElement.querySelector(".qty-input");
                    const currentValue = parseInt(input.value);
                    const maxStock = parseInt(input.dataset.maxStock);

                    if (this.classList.contains("plus")) {
                        if (currentValue < maxStock) {
                            const newValue = currentValue + 1;
                            input.value = newValue;
                            const itemId = input.dataset.itemId;
                            setQuantityButtonsLoading(input, true);
                            updateCartItem(itemId, newValue, input);
                        } else {
                            alert(`Maximum available stock is ${maxStock}.`);
                        }
                    } else if (this.classList.contains("minus")) {
                        if (currentValue > 1) {
                            const newValue = currentValue - 1;
                            input.value = newValue;
                            const itemId = input.dataset.itemId;
                            setQuantityButtonsLoading(input, true);
                            updateCartItem(itemId, newValue, input);
                        } else if (currentValue === 1) {
                            // Show confirmation modal when trying to decrease from 1
                            const itemId = input.dataset.itemId;
                            showQuantityDecreaseModal(itemId, input);
                            return; // Don't update cart yet, wait for confirmation
                        }
                    }
                });
            });

            document.querySelectorAll(".qty-input").forEach((input) => {
                input.addEventListener("change", function () {
                    const maxStock = parseInt(this.dataset.maxStock);
                    const newValue = parseInt(this.value);
                    const oldValue = parseInt(this.defaultValue);

                    if (newValue < 1) {
                        this.value = 1;
                    } else if (newValue > maxStock) {
                        this.value = maxStock;
                        alert(`Maximum available stock is ${maxStock}.`);
                    }

                    // Only update if value actually changed
                    if (parseInt(this.value) !== oldValue) {
                        const itemId = this.dataset.itemId;
                        setQuantityButtonsLoading(this, true);
                        updateCartItem(itemId, this.value, this);
                    }
                });
            });

            function setQuantityButtonsLoading(input, isLoading) {
                // Find all quantity controls for this item (desktop and mobile)
                const itemId = input.dataset.itemId;
                const allInputs = document.querySelectorAll(`.qty-input[data-item-id="${itemId}"]`);
                
                allInputs.forEach(inp => {
                    const control = inp.parentElement;
                    const buttons = control.querySelectorAll('.qty-btn');
                    
                    if (isLoading) {
                        buttons.forEach(btn => {
                            btn.classList.add('updating');
                            btn.disabled = true;
                        });
                        inp.disabled = true;
                    } else {
                        buttons.forEach(btn => {
                            btn.classList.remove('updating');
                            btn.disabled = false;
                            // Brief success indication
                            btn.classList.add('success');
                            setTimeout(() => {
                                btn.classList.remove('success');
                            }, 600);
                        });
                        inp.disabled = false;
                        // Update defaultValue so change detection works
                        inp.defaultValue = inp.value;
                    }
                });
            }

            async function updateCartItem(itemId, quantity, inputElement) {
                try {
                    const response = await fetch("../Includes/cart_operations.php", {
                        method: "POST",
                        headers: {
                            "Content-Type": "application/x-www-form-urlencoded",
                        },
                        body: `action=update&item_id=${itemId}&quantity=${quantity}`,
                    });

                    const data = await response.json();
                    
                    // Remove loading state
                    if (inputElement) {
                        setQuantityButtonsLoading(inputElement, false);
                    }
                    
                    if (data.success) {
                        updateCartUI(itemId, quantity, data.cart_count);
                    } else {
                        alert(data.message || "Failed to update quantity");
                        location.reload();
                    }
                } catch (error) {
                    if (inputElement) {
                        setQuantityButtonsLoading(inputElement, false);
                    }
                    alert("Error updating quantity");
                    location.reload();
                }
            }

            function updateCartUI(itemId, newQuantity, totalCartCount) {
                // Find all inputs with this item ID (desktop and mobile)
                const inputs = document.querySelectorAll(`.qty-input[data-item-id="${itemId}"]`);
                
                inputs.forEach(input => {
                    // Update the input value
                    input.value = newQuantity;
                    
                    // Find the row/card containing this input
                    const row = input.closest('.cart-row') || input.closest('.cart-item-card');
                    if (!row) return;
                    
                    // Get the price from the row
                    const priceElement = row.querySelector('.item-price, .card-item-price');
                    if (!priceElement) return;
                    
                    const priceText = priceElement.textContent.replace('₱', '').replace(',', '');
                    const price = parseFloat(priceText);
                    
                    // Calculate new subtotal
                    const newSubtotal = price * newQuantity;
                    
                    // Update subtotal display
                    const subtotalElement = row.querySelector('.item-subtotal, .card-item-subtotal');
                    if (subtotalElement) {
                        const formattedSubtotal = '₱' + newSubtotal.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
                        if (subtotalElement.classList.contains('card-item-subtotal')) {
                            subtotalElement.textContent = 'Subtotal: ' + formattedSubtotal;
                        } else {
                            subtotalElement.textContent = formattedSubtotal;
                        }
                        
                        // Add pulse animation to show change
                        subtotalElement.style.animation = 'none';
                        setTimeout(() => {
                            subtotalElement.style.animation = 'subtotalPulse 0.5s ease';
                        }, 10);
                    }
                });
                
                // Recalculate and update cart total
                updateCartTotal();
                
                // Update cart count in header if it exists
                const cartCountElements = document.querySelectorAll('.cart-count, .notification-badge');
                cartCountElements.forEach(el => {
                    el.textContent = totalCartCount;
                    // Hide badge when count is 0
                    el.style.display = totalCartCount > 0 ? 'block' : 'none';
                });
            }

        });

        // Move updateCartTotal outside DOMContentLoaded so it's globally accessible
        function updateCartTotal() {
            let total = 0;
            let totalItems = 0;
            
            // Calculate from desktop view
            document.querySelectorAll('.cart-row').forEach(row => {
                const subtotalElement = row.querySelector('.item-subtotal');
                const quantityInput = row.querySelector('.qty-input');
                
                if (subtotalElement && quantityInput) {
                    const subtotalText = subtotalElement.textContent.replace('₱', '').replace(',', '');
                    const subtotal = parseFloat(subtotalText);
                    const quantity = parseInt(quantityInput.value);
                    
                    total += subtotal;
                    totalItems += quantity;
                }
            });
            
            // Update total items display
            const totalItemsElement = document.querySelector('.summary-row span:last-child');
            if (totalItemsElement && totalItemsElement.parentElement.querySelector('span:first-child').textContent.includes('Total Items')) {
                totalItemsElement.textContent = totalItems;
            }
            
            // Update total amount display
            const totalAmountElement = document.querySelector('.summary-row.total span:last-child');
            if (totalAmountElement) {
                const formattedTotal = '₱' + total.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
                totalAmountElement.textContent = formattedTotal;
                
                // Add pulse animation
                totalAmountElement.style.animation = 'none';
                setTimeout(() => {
                    totalAmountElement.style.animation = 'totalPulse 0.6s ease';
                }, 10);
            }
        }
    </script>

    <div id="removeItemModal" class="remove-modal">
        <div class="remove-modal-content">
            <button class="remove-modal-close" type="button" aria-label="Close">
                <i class="fas fa-times"></i>
            </button>
            <div class="remove-modal-header">
                <div class="remove-modal-icon">
                    <i class="fas fa-trash"></i>
                </div>
                <h3 class="remove-modal-title">Remove Item</h3>
                <p class="remove-modal-message">Are you sure you want to remove this item from your cart?</p>
            </div>
            <div class="remove-modal-buttons">
                <button type="button" class="remove-btn-cancel">Cancel</button>
                <button type="button" class="remove-btn-confirm">Yes, Remove</button>
            </div>
        </div>
    </div>

    <!-- Quantity Decrease Confirmation Modal -->
    <div id="quantityDecreaseModal" class="remove-modal">
        <div class="remove-modal-content">
            <button class="qty-modal-close" type="button" aria-label="Close">
                <i class="fas fa-times"></i>
            </button>
            <div class="remove-modal-header">
                <div class="remove-modal-icon warning-icon">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <h3 class="remove-modal-title">Confirm Action</h3>
                <p class="remove-modal-message">Decreasing the quantity will remove this item from your cart. Do you want to continue?</p>
            </div>
            <div class="remove-modal-buttons">
                <button type="button" class="qty-btn-cancel">Cancel</button>
                <button type="button" class="qty-btn-confirm">Yes, Continue</button>
            </div>
        </div>
    </div>

    <style>
    /* Modal Variables - Using System Theme Colors */
    :root {
        --modal-primary-color: #0072bc;
        --modal-secondary-color: #fdf005;
        --modal-background-color: #f4ebb6;
        --modal-danger-color: #dc3545;
        --modal-warning-color: #f39c12;
        --modal-text-color: #333;
        --modal-primary-font: "Anton", serif;
        --modal-secondary-font: "Smooch Sans", serif;
    }

    .remove-modal {
        display: none;
        position: fixed;
        z-index: 10000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background: linear-gradient(135deg, rgba(0, 114, 188, 0.15) 0%, rgba(0, 0, 0, 0.7) 100%);
        backdrop-filter: blur(8px);
        animation: fadeIn 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94);
    }

    .remove-modal.show {
        display: flex !important;
        align-items: center;
        justify-content: center;
    }

    .remove-modal-content {
        background: linear-gradient(145deg, var(--modal-background-color) 0%, #ffffff 100%);
        border-radius: 24px;
        padding: 40px 35px;
        width: 90%;
        max-width: 450px;
        box-shadow: 
            0 25px 80px rgba(0, 114, 188, 0.25),
            0 15px 35px rgba(0, 0, 0, 0.1),
            0 5px 15px rgba(0, 0, 0, 0.05);
        border: 2px solid rgba(253, 240, 5, 0.3);
        transform: scale(0.8) translateY(-20px);
        animation: modalSlideIn 0.4s cubic-bezier(0.34, 1.56, 0.64, 1) forwards;
        text-align: center;
        position: relative;
        overflow: hidden;
    }

    /* Decorative border effect */
    .remove-modal-content::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(90deg, var(--modal-primary-color) 0%, var(--modal-secondary-color) 50%, var(--modal-primary-color) 100%);
    }

    .remove-modal-header {
        margin-bottom: 25px;
        position: relative;
    }

    .remove-modal-icon {
        width: 80px;
        height: 80px;
        background: linear-gradient(135deg, var(--modal-danger-color) 0%, #b02a37 100%);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 20px;
        box-shadow: 
            0 12px 30px rgba(220, 53, 69, 0.4),
            0 6px 15px rgba(220, 53, 69, 0.2);
        border: 3px solid rgba(255, 255, 255, 0.9);
        position: relative;
        animation: iconPulse 2s ease-in-out infinite;
    }

    /* Warning icon for quantity decrease modal */
    .remove-modal-icon.warning-icon {
        background: linear-gradient(135deg, var(--modal-warning-color) 0%, #d68910 100%);
        box-shadow: 
            0 12px 30px rgba(243, 156, 18, 0.4),
            0 6px 15px rgba(243, 156, 18, 0.2);
    }

    .remove-modal-icon i {
        color: white;
        font-size: 32px;
        text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
    }

    .remove-modal-title {
        font-family: var(--modal-primary-font);
        font-size: 28px;
        color: var(--modal-text-color);
        margin: 0 0 12px 0;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 1px;
        text-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
    }

    .remove-modal-message {
        font-family: var(--modal-secondary-font);
        font-size: 18px;
        color: #555;
        margin: 0;
        line-height: 1.6;
        font-weight: 500;
        max-width: 350px;
        margin: 0 auto;
    }

    .remove-modal-buttons {
        display: flex;
        gap: 15px;
        margin-top: 35px;
        justify-content: center;
        flex-wrap: wrap;
    }

    .remove-btn-confirm,
    .remove-btn-cancel,
    .qty-btn-confirm,
    .qty-btn-cancel {
        padding: 14px 28px;
        border: none;
        border-radius: 50px;
        font-weight: 600;
        font-size: 16px;
        cursor: pointer;
        transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
        min-width: 120px;
        font-family: var(--modal-secondary-font);
        text-transform: uppercase;
        letter-spacing: 0.5px;
        position: relative;
        overflow: hidden;
    }

    /* Confirm button styling */
    .remove-btn-confirm,
    .qty-btn-confirm {
        background: linear-gradient(135deg, var(--modal-danger-color) 0%, #b02a37 100%);
        color: white;
        box-shadow: 
            0 6px 20px rgba(220, 53, 69, 0.4),
            0 3px 8px rgba(220, 53, 69, 0.2);
        border: 2px solid rgba(255, 255, 255, 0.2);
    }

    .remove-btn-confirm:hover,
    .qty-btn-confirm:hover {
        background: linear-gradient(135deg, #b02a37 0%, #8c1f2a 100%);
        transform: translateY(-3px) scale(1.02);
        box-shadow: 
            0 10px 25px rgba(220, 53, 69, 0.5),
            0 5px 12px rgba(220, 53, 69, 0.3);
    }

    /* Cancel button styling */
    .remove-btn-cancel,
    .qty-btn-cancel {
        background: linear-gradient(135deg, var(--modal-primary-color) 0%, #005a9e 100%);
        color: white;
        box-shadow: 
            0 6px 20px rgba(0, 114, 188, 0.4),
            0 3px 8px rgba(0, 114, 188, 0.2);
        border: 2px solid rgba(255, 255, 255, 0.2);
    }

    .remove-btn-cancel:hover,
    .qty-btn-cancel:hover {
        background: linear-gradient(135deg, #005a9e 0%, #004080 100%);
        transform: translateY(-3px) scale(1.02);
        box-shadow: 
            0 10px 25px rgba(0, 114, 188, 0.5),
            0 5px 12px rgba(0, 114, 188, 0.3);
    }

    /* Button ripple effect */
    .remove-btn-confirm::before,
    .remove-btn-cancel::before,
    .qty-btn-confirm::before,
    .qty-btn-cancel::before {
        content: '';
        position: absolute;
        top: 50%;
        left: 50%;
        width: 0;
        height: 0;
        background: rgba(255, 255, 255, 0.3);
        border-radius: 50%;
        transform: translate(-50%, -50%);
        transition: width 0.3s, height 0.3s;
    }

    .remove-btn-confirm:active::before,
    .remove-btn-cancel:active::before,
    .qty-btn-confirm:active::before,
    .qty-btn-cancel:active::before {
        width: 300px;
        height: 300px;
    }

    .remove-modal-close,
    .qty-modal-close {
        position: absolute;
        top: 18px;
        right: 22px;
        background: linear-gradient(135deg, rgba(255, 255, 255, 0.9) 0%, rgba(240, 240, 240, 0.9) 100%);
        border: 2px solid rgba(0, 114, 188, 0.3);
        border-radius: 50%;
        font-size: 18px;
        color: var(--modal-text-color);
        cursor: pointer;
        transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
        width: 40px;
        height: 40px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .remove-modal-close:hover,
    .qty-modal-close:hover {
        background: linear-gradient(135deg, var(--modal-danger-color) 0%, #b02a37 100%);
        color: white;
        transform: rotate(90deg) scale(1.1);
        box-shadow: 0 6px 20px rgba(220, 53, 69, 0.4);
        border-color: rgba(220, 53, 69, 0.5);
    }

    /* Animations */
    @keyframes fadeIn {
        from { 
            opacity: 0; 
            backdrop-filter: blur(0px);
        }
        to { 
            opacity: 1;
            backdrop-filter: blur(8px);
        }
    }

    @keyframes modalSlideIn {
        from { 
            transform: scale(0.8) translateY(-40px);
            opacity: 0;
        }
        to { 
            transform: scale(1) translateY(0);
            opacity: 1;
        }
    }

    @keyframes iconPulse {
        0%, 100% { 
            transform: scale(1);
            box-shadow: 
                0 12px 30px rgba(220, 53, 69, 0.4),
                0 6px 15px rgba(220, 53, 69, 0.2);
        }
        50% { 
            transform: scale(1.05);
            box-shadow: 
                0 15px 35px rgba(220, 53, 69, 0.5),
                0 8px 18px rgba(220, 53, 69, 0.3);
        }
    }

    /* Instant update animations */
    @keyframes subtotalPulse {
        0% { 
            transform: scale(1);
            color: var(--primary-color);
        }
        50% { 
            transform: scale(1.1);
            color: #00a8e8;
            font-weight: 700;
        }
        100% { 
            transform: scale(1);
            color: var(--primary-color);
        }
    }

    @keyframes totalPulse {
        0% { 
            transform: scale(1);
        }
        30% { 
            transform: scale(1.08);
            color: #00a8e8;
        }
        100% { 
            transform: scale(1);
        }
    }

    .qty-btn {
        transition: all 0.2s ease, transform 0.1s ease;
    }

    .qty-btn:active {
        transform: scale(0.9);
    }

    /* Mobile Responsive Styles */
    @media (max-width: 768px) {
        .remove-modal-content {
            margin: 15px;
            padding: 30px 25px;
            max-width: none;
            border-radius: 20px;
        }
        
        .remove-modal-icon {
            width: 70px;
            height: 70px;
            margin-bottom: 18px;
        }
        
        .remove-modal-icon i {
            font-size: 28px;
        }
        
        .remove-modal-title {
            font-size: 24px;
            margin-bottom: 10px;
        }
        
        .remove-modal-message {
            font-size: 16px;
            line-height: 1.5;
        }
        
        .remove-modal-buttons {
            flex-direction: column;
            gap: 12px;
            margin-top: 25px;
        }
        
        .remove-btn-confirm,
        .remove-btn-cancel,
        .qty-btn-confirm,
        .qty-btn-cancel {
            width: 100%;
            padding: 16px 20px;
            font-size: 16px;
        }
        
        .remove-modal-close,
        .qty-modal-close {
            top: 15px;
            right: 18px;
            width: 36px;
            height: 36px;
            font-size: 16px;
        }
    }
    
    @media (max-width: 480px) {
        .remove-modal-content {
            margin: 10px;
            padding: 25px 20px;
        }
        
        .remove-modal-title {
            font-size: 22px;
        }
        
        .remove-modal-message {
            font-size: 15px;
        }
    }
    </style>
</body>
</html> 