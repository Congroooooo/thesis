<?php 
require_once '../Backend/ProOrderDetailsLogic.php'; 
include("../Includes/sessionStart.php");
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Pre Order Details</title>
    <link rel="stylesheet" href="../CSS/header.css">
    <link rel="stylesheet" href="../CSS/global.css">
    <link rel="stylesheet" href="../CSS/ProOrderDetails.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Anton&family=Smooch+Sans:wght@100..900&display=swap" rel="stylesheet">
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>

<body>
    <?php include("../Includes/Header.php"); ?>

    <div class="preorder-container">
        <div class="process-steps">
            <div class="step completed">1. Pre Order Cart</div>
            <div class="step completed">2. Checkout Details</div>
            <div class="step active">3. Order Details</div>
        </div>

        <div class="order-success">
            <div class="success-message">
                <i class="fas fa-check-circle"></i>
                <h2>Thank You for Your Order!</h2>
                <p>Your order has been successfully placed.</p>
            </div>

            <div class="order-details">
                <h3>Order Details</h3>
                <div class="details-group">
                    <p><strong>Order Number:</strong> <span><?php echo htmlspecialchars($order_number); ?></span></p>
                    <p><strong>Date:</strong> <span><?php echo date('F d, Y'); ?></span></p>
                </div>
                <?php if (isset($order_status) && $order_status === 'rejected' && !empty($rejection_reason)): ?>
                    <span class="rejection-reason">
                        Reason: <?php echo htmlspecialchars($rejection_reason); ?>
                    </span>
                <?php endif; ?>

                <div class="customer-info">
                    <h4>Customer Information</h4>
                    <p><strong>Name:</strong> <span><?php echo htmlspecialchars($firstName . ' ' . $lastName); ?></span></p>
                    <p><strong>Student Number:</strong> <span><?php echo htmlspecialchars($_POST['studentNumber']); ?></span></p>
                    <p><strong>Course/Strand:</strong> <span><?php echo htmlspecialchars($course); ?></span></p>
                    <p><strong>Email:</strong> <span><?php echo htmlspecialchars($email); ?></span></p>
                    <p><strong>Phone:</strong> <span><?php echo htmlspecialchars($phone); ?></span></p>
                </div>

                <div class="ordered-items">
                    <h4>Ordered Items</h4>
                    <div class="item-list">
                        <?php if (!empty($selected_items)): ?>
                            <div class="order-item header">
                                <span class="item-name">Item</span>
                                <span class="item-size">Size</span>
                                <span class="item-quantity">Qty</span>
                                <span class="item-price">Price</span>
                            </div>
                            <?php foreach ($selected_items as $item): 
                                $clean_name = rtrim($item['item_name'], " SMLX234567");
                            ?>
                                <div class="order-item">
                                    <span class="item-name"><?php echo htmlspecialchars($clean_name); ?></span>
                                    <span class="item-size"><?php echo htmlspecialchars($item['size'] ?? 'N/A'); ?></span>
                                    <span class="item-quantity"><?php echo $item['quantity']; ?></span>
                                    <span class="item-price">₱<?php echo number_format($item['price'] * $item['quantity'], 2); ?></span>
                                </div>
                            <?php endforeach; ?>
                            <div class="order-total">
                                <span>Total Amount:</span>
                                <span class="total-amount">₱<?php echo number_format($total_amount, 2); ?></span>
                            </div>
                        <?php else: ?>
                            <p>No items in order</p>
                        <?php endif; ?>
                    </div>
                </div>

                <button class="back-home-btn" onclick="window.location.href='MyOrders.php'">
                    View Orders
                </button>
            </div>
        </div>
    </div>

    <script src="../Javascript/ProOrderDetails.js"></script>
</body>

</html>