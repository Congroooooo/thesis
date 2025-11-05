<?php
date_default_timezone_set('Asia/Manila');
session_start();
require_once '../Includes/connection.php';


if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_id'])) {
    header("Location: ../Pages/login.php?redirect=../PAMO_PAGES/orders.php");
    exit();
}
$role = strtoupper($_SESSION['role_category'] ?? '');
$programAbbr = strtoupper($_SESSION['program_abbreviation'] ?? '');
if (!($role === 'EMPLOYEE' && $programAbbr === 'PAMO')) {
    header("Location: ../Pages/home.php");
    exit();
}

$status = isset($_GET['status']) ? $_GET['status'] : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Client-side pagination - fetch ALL orders
$limit = 12; // Items per page (handled client-side)
$page = max(1, intval($_GET['page'] ?? 1)); // Current page from URL

// Fetch ALL orders (no LIMIT/OFFSET - client-side pagination)
$query = "
    SELECT po.*, a.first_name, a.last_name, a.email, a.program_or_position, a.id_number, a.role_category
    FROM orders po
    JOIN account a ON po.user_id = a.id
    ORDER BY po.created_at DESC
";

$stmt = $conn->prepare($query);
$stmt->execute();
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Store total count for initial display
$total_items = count($orders);

// --- Add category to each item in each order ---
foreach ($orders as &$order) {
    $order_items = json_decode($order['items'], true);
    if ($order_items && is_array($order_items)) {
        foreach ($order_items as &$item) {
            // Fetch category from inventory table using item_code
            $item_code = $item['item_code'] ?? '';
            if ($item_code) {
                $cat_stmt = $conn->prepare("SELECT category FROM inventory WHERE item_code = ? LIMIT 1");
                $cat_stmt->execute([$item_code]);
                $cat_row = $cat_stmt->fetch(PDO::FETCH_ASSOC);
                $item['category'] = $cat_row ? $cat_row['category'] : '';
            } else {
                $item['category'] = '';
            }
        }
        $order['items'] = json_encode($order_items);
    }
}
unset($order);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PAMO - Orders</title>
    <link rel="stylesheet" href="../PAMO CSS/styles.css">
    <link rel="stylesheet" href="../PAMO CSS/orders.css">
    <link rel="stylesheet" href="../CSS/logout-modal.css">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>

<body>
    <div class="container">
        <?php include 'includes/sidebar.php'; ?>

        <main class="main-content">
            <header>
                <div class="header-title-search" style="display: flex; align-items: center; gap: 24px;">
                    <h1 class="page-title" style="color: #007bff; font-size: 2rem; font-weight: bold; margin: 0 18px 0 0;">Orders</h1>
                    <div class="search-bar">
                        <i class="material-icons">search</i>
                        <input type="text" id="searchInput" placeholder="Search orders...">
                    </div>
                </div>
                <div class="header-actions">
                    <div class="filter-dropdown">
                        <select id="statusFilter" onchange="filterByStatus(this.value)">
                            <option value="">All Status</option>
                            <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="approved" <?php echo $status === 'approved' ? 'selected' : ''; ?>>Approved</option>
                            <option value="rejected" <?php echo $status === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                            <option value="completed" <?php echo $status === 'completed' ? 'selected' : ''; ?>>Completed</option>
                            <option value="voided" <?php echo $status === 'voided' ? 'selected' : ''; ?>>Voided</option>
                            <option value="cancelled" <?php echo $status === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                        </select>
                    </div>
                </div>
            </header>

            <div class="orders-content">
                <div class="results-info">
                    <span class="results-count">
                        Showing 1-<?php echo min($limit, $total_items); ?> of <?php echo $total_items; ?> orders
                    </span>
                    <span class="page-info">
                        Page 1 of <?php echo max(1, ceil($total_items / $limit)); ?>
                    </span>
                </div>
                
                <div class="orders-grid">
                    <?php if (!empty($orders)): ?>
                        <?php foreach ($orders as $order): 
                            $order_items = json_decode($order['items'], true);
                            $total_amount = 0;
                            foreach ($order_items as $item) {
                                $total_amount += $item['price'] * $item['quantity'];
                            }
                        ?>
                            <div class="order-card" data-order-id="<?php echo $order['id']; ?>" data-status="<?php echo $order['status']; ?>" data-order-type="<?php echo $order['order_type'] ?? 'online'; ?>">
                                <div class="order-header">
                                    <div class="order-header-row">
                                        <h3>Order #<?php echo htmlspecialchars($order['order_number']); ?></h3>
                                        <?php 
                                        $orderType = $order['order_type'] ?? 'online';
                                        $orderTypeClass = $orderType === 'walk-in' ? 'walk-in-badge' : 'online-badge';
                                        $orderTypeIcon = $orderType === 'walk-in' ? '🚶' : '🌐';
                                        ?>
                                        <span class="order-type-badge <?php echo $orderTypeClass; ?>" title="<?php echo ucfirst($orderType); ?> Order">
                                            <?php echo $orderTypeIcon; ?> <?php echo ucfirst($orderType); ?>
                                        </span>
                                    </div>
                                    <div class="status-badge-wrapper">
                                        <span class="status-badge <?php echo $order['status']; ?>">
                                            <?php echo ucfirst($order['status']); ?>
                                        </span>
                                    </div>
                                </div>
                                
                                <div class="order-details">
                                    <div class="customer-info">
                                        <p><strong>Customer:</strong> <?php echo htmlspecialchars($order['first_name'] . ' ' . $order['last_name']); ?></p>
                                        <p><strong>Student Number:</strong> <?php echo htmlspecialchars($order['id_number']); ?></p>
                                        <p><strong>Course/Strand:</strong> <?php echo htmlspecialchars($order['program_or_position']); ?></p>
                                        <p><strong>Email:</strong> <?php echo htmlspecialchars($order['email']); ?></p>
                                    </div>
                                    
                                    <div class="items-list">
                                        <h4>Ordered Items:</h4>
                                        <div class="items-table">
                                            <div class="table-header">
                                                <span class="item-name">Item</span>
                                                <span class="item-size">Size</span>
                                                <span class="item-quantity">Qty</span>
                                                <span class="item-price">Price</span>
                                            </div>
                                            <?php foreach ($order_items as $item): 
                                                // Remove size suffix from item name
                                                $clean_name = rtrim($item['item_name'], " SMLX234567");
                                            ?>
                                                <div class="table-row">
                                                    <span class="item-name"><?php echo htmlspecialchars($clean_name); ?></span>
                                                    <span class="item-size"><?php echo htmlspecialchars($item['size'] ?? 'N/A'); ?></span>
                                                    <span class="item-quantity"><?php echo $item['quantity']; ?></span>
                                                    <span class="item-price">₱<?php echo number_format($item['price'], 2); ?></span>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                    
                                    <div class="order-footer">
                                        <div class="total-amount">
                                            <strong>Total:</strong> ₱<?php echo number_format($total_amount, 2); ?>
                                        </div>
                                        <div class="order-date">
                                            <?php echo date('F d, Y h:i A', strtotime($order['created_at'])); ?>
                                            <?php if ($order['status'] === 'completed' && isset($order['payment_date'])): ?>
                                                <br>
                                                <span class="payment-date">Paid: <?php echo date('F d, Y h:i A', strtotime($order['payment_date'])); ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>

                                <?php if ($order['status'] === 'pending'): ?>
                                    <div class="order-actions">
                                        <button class="accept-btn" onclick="updateOrderStatus(<?php echo $order['id']; ?>, 'approved')">
                                            <i class="fas fa-check"></i> Accept
                                        </button>
                                        <button class="reject-btn" onclick="showRejectionModal(<?php echo $order['id']; ?>)">
                                            <i class="fas fa-times"></i> Reject
                                        </button>
                                    </div>
                                <?php elseif ($order['status'] === 'approved'): ?>
                                    <div class="order-actions">
                                        <button class="complete-btn" data-order-id="<?php echo $order['id']; ?>">
                                            <i class="fas fa-check-double"></i> Mark as Completed (After Payment)
                                        </button>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="no-orders">
                            <i class="material-icons">shopping_cart</i>
                            <p>No orders found</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Pagination (Client-side) -->
                <div class="pagination" id="paginationContainer">
                    <!-- Pagination will be generated by JavaScript -->
                </div>
            </div>
        </main>
    </div>

    <!-- Order Receipt Modal (hidden by default) -->
    <div id="orderReceiptModal" class="modal">
        <div class="modal-card">
            <div class="modal-header">
                <h2>Sales Receipt</h2>
                <span class="close" onclick="closeOrderReceiptModal()">&times;</span>
            </div>
            <div class="modal-body" id="orderReceiptBody">
                <!-- Receipt content will be injected here -->
            </div>
            <div class="modal-footer">
                <button type="button" onclick="printOrderReceipt()" class="save-btn">Print</button>
                <button type="button" onclick="closeOrderReceiptModal()" class="cancel-btn">Close</button>
            </div>
        </div>
    </div>

    <!-- Rejection Reason Modal -->
    <div id="rejectionModal" class="modal">
        <div class="modal-card">
            <div class="modal-header">
                <h2>Reject Order</h2>
                <span class="close" onclick="closeRejectionModal()">&times;</span>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label for="rejectionReason">Reason for Rejection:</label>
                    <textarea id="rejectionReason" rows="4" placeholder="Please provide a reason for rejecting this order..." required></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" onclick="submitRejection()" class="save-btn">Submit</button>
                <button type="button" onclick="closeRejectionModal()" class="cancel-btn">Cancel</button>
            </div>
        </div>
    </div>

    <script>
        // Client-side filtering and pagination (like inventory page)
        let searchTimeout;
        let allOrderCards = [];
        let filteredOrderCards = [];
        let currentFilterStatus = '<?php echo $status; ?>';
        let currentPage = 1;
        const itemsPerPage = 12;
        
        // Store all order cards on page load
        document.addEventListener('DOMContentLoaded', function() {
            allOrderCards = Array.from(document.querySelectorAll('.order-card'));
            filteredOrderCards = [...allOrderCards];
            
            // Set initial search value if present
            const urlParams = new URLSearchParams(window.location.search);
            const searchValue = urlParams.get('search');
            if (searchValue) {
                document.getElementById('searchInput').value = searchValue;
            }
            
            // Apply initial filters and pagination
            applyClientSideFilters();
        });

        // Instant search with client-side filtering
        document.getElementById('searchInput').addEventListener('input', function(e) {
            clearTimeout(searchTimeout);
            
            // Use shorter debounce for instant feel
            searchTimeout = setTimeout(() => {
                currentPage = 1; // Reset to page 1 on search
                applyClientSideFilters();
            }, 150);
        });

        // Instant filter by status
        function filterByStatus(status) {
            currentFilterStatus = status;
            currentPage = 1; // Reset to page 1 on filter
            applyClientSideFilters();
        }

        // Apply filters client-side
        function applyClientSideFilters() {
            const searchTerm = document.getElementById('searchInput').value.trim().toLowerCase();
            
            // Filter cards
            filteredOrderCards = allOrderCards.filter(card => {
                const orderNumber = card.querySelector('h3') ? card.querySelector('h3').textContent.toLowerCase() : '';
                const customerName = card.querySelector('.customer-info p') ? card.querySelector('.customer-info p').textContent.toLowerCase() : '';
                const studentNumber = card.querySelectorAll('.customer-info p')[1] ? card.querySelectorAll('.customer-info p')[1].textContent.toLowerCase() : '';
                const email = card.querySelectorAll('.customer-info p')[3] ? card.querySelectorAll('.customer-info p')[3].textContent.toLowerCase() : '';
                const orderStatus = card.getAttribute('data-status');
                
                // Check search match
                const matchesSearch = !searchTerm || 
                    orderNumber.includes(searchTerm) ||
                    customerName.includes(searchTerm) ||
                    studentNumber.includes(searchTerm) ||
                    email.includes(searchTerm);
                
                // Check status filter
                const matchesStatus = !currentFilterStatus || orderStatus === currentFilterStatus;
                
                return matchesSearch && matchesStatus;
            });
            
            // Apply pagination
            applyPagination();
        }
        
        // Apply pagination to filtered results
        function applyPagination() {
            const totalPages = Math.ceil(filteredOrderCards.length / itemsPerPage);
            
            // Ensure current page is valid
            if (currentPage > totalPages && totalPages > 0) {
                currentPage = totalPages;
            }
            if (currentPage < 1) {
                currentPage = 1;
            }
            
            // Calculate start and end indices
            const startIndex = (currentPage - 1) * itemsPerPage;
            const endIndex = startIndex + itemsPerPage;
            
            // Show/hide cards based on pagination
            allOrderCards.forEach(card => {
                card.style.display = 'none';
            });
            
            filteredOrderCards.forEach((card, index) => {
                if (index >= startIndex && index < endIndex) {
                    card.style.display = 'block';
                }
            });
            
            // Update results count
            updateResultsCount(filteredOrderCards.length, startIndex, endIndex);
            
            // Update pagination controls
            renderPagination(totalPages);
        }
        
        // Update results count display
        function updateResultsCount(filteredCount, startIndex, endIndex) {
            const resultsCount = document.querySelector('.results-count');
            const pageInfo = document.querySelector('.page-info');
            const totalOrders = allOrderCards.length;
            const totalPages = Math.ceil(filteredCount / itemsPerPage);
            
            if (resultsCount) {
                if (filteredCount === 0) {
                    resultsCount.textContent = `No orders found`;
                } else {
                    const displayStart = startIndex + 1;
                    const displayEnd = Math.min(endIndex, filteredCount);
                    resultsCount.textContent = `Showing ${displayStart}-${displayEnd} of ${filteredCount} orders`;
                }
            }
            
            if (pageInfo) {
                pageInfo.textContent = `Page ${currentPage} of ${Math.max(1, totalPages)}`;
            }
        }
        
        // Render pagination controls
        function renderPagination(totalPages) {
            const paginationContainer = document.getElementById('paginationContainer');
            if (!paginationContainer) return;
            
            paginationContainer.innerHTML = '';
            
            if (totalPages <= 1) {
                return; // No pagination needed
            }
            
            // Previous button
            if (currentPage > 1) {
                const prevBtn = document.createElement('a');
                prevBtn.className = 'pagination-link';
                prevBtn.innerHTML = '&laquo;';
                prevBtn.href = '#';
                prevBtn.onclick = (e) => {
                    e.preventDefault();
                    currentPage--;
                    applyPagination();
                };
                paginationContainer.appendChild(prevBtn);
            }
            
            // First page
            const firstPageBtn = document.createElement('a');
            firstPageBtn.className = 'pagination-link' + (currentPage === 1 ? ' active' : '');
            firstPageBtn.textContent = '1';
            firstPageBtn.href = '#';
            firstPageBtn.onclick = (e) => {
                e.preventDefault();
                currentPage = 1;
                applyPagination();
            };
            paginationContainer.appendChild(firstPageBtn);
            
            // Ellipsis before
            if (currentPage > 4) {
                const ellipsis = document.createElement('span');
                ellipsis.className = 'pagination-ellipsis';
                ellipsis.textContent = '...';
                paginationContainer.appendChild(ellipsis);
            }
            
            // Pages around current
            const window = 1;
            const start = Math.max(2, currentPage - window);
            const end = Math.min(totalPages - 1, currentPage + window);
            
            for (let i = start; i <= end; i++) {
                const pageBtn = document.createElement('a');
                pageBtn.className = 'pagination-link' + (currentPage === i ? ' active' : '');
                pageBtn.textContent = i;
                pageBtn.href = '#';
                pageBtn.onclick = ((pageNum) => {
                    return (e) => {
                        e.preventDefault();
                        currentPage = pageNum;
                        applyPagination();
                    };
                })(i);
                paginationContainer.appendChild(pageBtn);
            }
            
            // Ellipsis after
            if (currentPage < totalPages - 3) {
                const ellipsis = document.createElement('span');
                ellipsis.className = 'pagination-ellipsis';
                ellipsis.textContent = '...';
                paginationContainer.appendChild(ellipsis);
            }
            
            // Last page
            if (totalPages > 1) {
                const lastPageBtn = document.createElement('a');
                lastPageBtn.className = 'pagination-link' + (currentPage === totalPages ? ' active' : '');
                lastPageBtn.textContent = totalPages;
                lastPageBtn.href = '#';
                lastPageBtn.onclick = (e) => {
                    e.preventDefault();
                    currentPage = totalPages;
                    applyPagination();
                };
                paginationContainer.appendChild(lastPageBtn);
            }
            
            // Next button
            if (currentPage < totalPages) {
                const nextBtn = document.createElement('a');
                nextBtn.className = 'pagination-link';
                nextBtn.innerHTML = '&raquo;';
                nextBtn.href = '#';
                nextBtn.onclick = (e) => {
                    e.preventDefault();
                    currentPage++;
                    applyPagination();
                };
                paginationContainer.appendChild(nextBtn);
            }
        }

        // Real-time order updates for PAMO (Compatible with pagination)
        let lastPamoOrderCheck = null;
        let currentPamoStatusFilter = '<?php echo $status; ?>';
        let hasNewOrders = false;
        let knownOrderIds = new Set(); // Track all orders we've seen
        let isInitialized = false; // Track if we've done the initial load

        function updatePamoOrdersRealTime() {
            // Skip polling if actively processing an order
            if (window.isProcessingOrder) {
                return;
            }

            const formData = new FormData();
            formData.append('action', 'get_pamo_orders');
            formData.append('status', currentPamoStatusFilter);
            if (lastPamoOrderCheck) {
                formData.append('last_check', lastPamoOrderCheck);
            }

            fetch('../Includes/order_operations.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success && data.orders && data.orders.length > 0) {
                    if (!isInitialized) {
                        // First load - record all existing order IDs
                        data.orders.forEach(order => {
                            knownOrderIds.add(String(order.id));
                        });
                        lastPamoOrderCheck = data.last_update;
                        isInitialized = true;
                    } else {
                        // Subsequent checks - compare against known orders
                        updateVisiblePamoOrders(data.orders);
                        lastPamoOrderCheck = data.last_update;
                    }
                } else if (data.success) {
                    // Update timestamp even if no orders
                    lastPamoOrderCheck = data.last_update;
                    if (!isInitialized) {
                        isInitialized = true;
                    }
                }
            })
            .catch(error => {
                // Silently handle polling errors to avoid console spam
            });
        }

        function updateVisiblePamoOrders(updatedOrders) {
            const ordersGrid = document.querySelector('.orders-grid');
            if (!ordersGrid) return;

            let statusChangedOnCurrentPage = false;
            let newOrders = [];

            updatedOrders.forEach(order => {
                const orderId = String(order.id);
                const existingCard = document.querySelector(`[data-order-id="${orderId}"]`);
                
                if (existingCard) {
                    // This order is visible on current page - update it
                    const oldStatus = existingCard.getAttribute('data-status');
                    const newStatus = order.status;
                    
                    if (oldStatus !== newStatus) {
                        statusChangedOnCurrentPage = true;
                    }
                    
                    updateSinglePamoOrderCard(existingCard, order);
                    
                    // Update or add the order to window.ORDERS array
                    if (window.ORDERS && Array.isArray(window.ORDERS)) {
                        const orderIndex = window.ORDERS.findIndex(o => String(o.id) === orderId);
                        if (orderIndex !== -1) {
                            window.ORDERS[orderIndex] = order;
                        }
                    }
                } else {
                    // Check if this is truly a NEW order (not just on a different page)
                    if (!knownOrderIds.has(orderId)) {
                        // This is a brand new order we haven't seen before
                        newOrders.push(order);
                        knownOrderIds.add(orderId); // Mark it as known
                    }
                    // If it's in knownOrderIds but not visible, it's just on another page - ignore it
                }
            });
            
            // Add new orders dynamically to the current page
            if (newOrders.length > 0) {
                addNewOrdersToPage(newOrders);
            } else if (statusChangedOnCurrentPage) {
                // Just updated visible orders
                updateOrderCount();
            }
            
            // Trigger badge update when orders are processed
            if (typeof updatePendingOrdersBadge === 'function') {
                updatePendingOrdersBadge();
            }
        }

        function addNewOrdersToPage(newOrders) {
            const ordersGrid = document.querySelector('.orders-grid');
            if (!ordersGrid) return;
            
            // Sort new orders by date (newest first)
            newOrders.sort((a, b) => new Date(b.created_at) - new Date(a.created_at));
            
            // Add each new order card to the DOM and allOrderCards array
            newOrders.forEach(order => {
                const newCard = createPamoOrderCard(order);
                ordersGrid.insertBefore(newCard, ordersGrid.firstChild);
                
                // Add entrance animation
                newCard.style.animation = 'slideInFromTop 0.5s ease-out';
                
                // Add to allOrderCards array at the beginning (newest first)
                allOrderCards.unshift(newCard);
            });
            
            // Re-apply current filters and pagination to include new orders
            applyClientSideFilters();
            
            // Show notification about new orders
            showNewOrderNotification(newOrders.length);
            
            // Update order count badge
            updateOrderCount();
        }

        function createPamoOrderCard(order) {
            const card = document.createElement('div');
            card.className = 'order-card';
            card.setAttribute('data-order-id', order.id);
            card.setAttribute('data-status', order.status);
            card.setAttribute('data-order-type', order.order_type || 'online');
            
            // Format date
            const orderDate = new Date(order.created_at);
            const formattedDate = orderDate.toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'long',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
            
            // Parse items if it's a JSON string
            let orderItems = order.items;
            if (typeof orderItems === 'string') {
                try {
                    orderItems = JSON.parse(orderItems);
                } catch (e) {
                    console.error('Failed to parse order items:', e);
                    orderItems = [];
                }
            }
            
            // Calculate total from items
            let totalAmount = 0;
            if (orderItems && Array.isArray(orderItems)) {
                totalAmount = orderItems.reduce((sum, item) => {
                    return sum + ((item.price || 0) * (item.quantity || 0));
                }, 0);
            }
            
            // Build ordered items HTML
            let itemsHtml = '';
            if (orderItems && Array.isArray(orderItems) && orderItems.length > 0) {
                orderItems.forEach(item => {
                    // Remove size suffix from item name
                    let cleanName = (item.item_name || 'N/A').replace(/\s+[SMLX234567]+$/, '');
                    itemsHtml += `
                        <div class="table-row">
                            <span class="item-name">${cleanName}</span>
                            <span class="item-size">${item.size || 'N/A'}</span>
                            <span class="item-quantity">${item.quantity || 0}</span>
                            <span class="item-price">₱${parseFloat(item.price || 0).toFixed(2)}</span>
                        </div>
                    `;
                });
            }
            
            // Order type badge
            const orderType = order.order_type || 'online';
            const orderTypeClass = orderType === 'walk-in' ? 'walk-in-badge' : 'online-badge';
            const orderTypeIcon = orderType === 'walk-in' ? '🚶' : '🌐';
            
            // Status badge
            const statusText = order.status.charAt(0).toUpperCase() + order.status.slice(1);
            
            // Action buttons based on status
            let actionButtons = '';
            if (order.status === 'pending') {
                actionButtons = `
                    <div class="order-actions">
                        <button class="accept-btn" onclick="updateOrderStatus(${order.id}, 'approved')">
                            <i class="fas fa-check"></i> Accept
                        </button>
                        <button class="reject-btn" onclick="showRejectionModal(${order.id})">
                            <i class="fas fa-times"></i> Reject
                        </button>
                    </div>
                `;
            } else if (order.status === 'approved') {
                actionButtons = `
                    <div class="order-actions">
                        <button class="complete-btn" data-order-id="${order.id}">
                            <i class="fas fa-check-double"></i> Mark as Completed (After Payment)
                        </button>
                    </div>
                `;
            }
            
            card.innerHTML = `
                <div class="order-header">
                    <div class="order-header-row">
                        <h3>Order #${order.order_number || 'N/A'}</h3>
                        <span class="order-type-badge ${orderTypeClass}" title="${orderType.charAt(0).toUpperCase() + orderType.slice(1)} Order">
                            ${orderTypeIcon} ${orderType.charAt(0).toUpperCase() + orderType.slice(1)}
                        </span>
                    </div>
                    <div class="status-badge-wrapper">
                        <span class="status-badge ${order.status}">
                            ${statusText}
                        </span>
                    </div>
                </div>
                
                <div class="order-details">
                    <div class="customer-info">
                        <p><strong>Customer:</strong> ${order.first_name || ''} ${order.last_name || ''}</p>
                        <p><strong>Student Number:</strong> ${order.id_number || 'N/A'}</p>
                        <p><strong>Course/Strand:</strong> ${order.program_or_position || 'N/A'}</p>
                        <p><strong>Email:</strong> ${order.email || 'N/A'}</p>
                    </div>
                    
                    <div class="items-list">
                        <h4>Ordered Items:</h4>
                        <div class="items-table">
                            <div class="table-header">
                                <span class="item-name">Item</span>
                                <span class="item-size">Size</span>
                                <span class="item-quantity">Qty</span>
                                <span class="item-price">Price</span>
                            </div>
                            ${itemsHtml}
                        </div>
                    </div>
                    
                    <div class="order-footer">
                        <div class="total-amount">
                            <strong>Total:</strong> ₱${totalAmount.toFixed(2)}
                        </div>
                        <div class="order-date">
                            ${formattedDate}
                        </div>
                    </div>
                </div>
                
                ${actionButtons}
            `;
            
            return card;
        }

        function showNewOrderNotification(count) {
            // Create a toast notification for new orders
            const notification = document.createElement('div');
            notification.className = 'new-order-toast';
            notification.innerHTML = `
                <i class="material-icons">notification_important</i>
                <span>${count} new order${count > 1 ? 's' : ''} added!</span>
            `;
            
            document.body.appendChild(notification);
            
            // Auto-remove after 3 seconds
            setTimeout(() => {
                notification.style.animation = 'fadeOut 0.3s ease-out';
                setTimeout(() => {
                    notification.remove();
                }, 300);
            }, 3000);
        }

        function updateResultsInfo(newOrdersCount) {
            const resultsCount = document.querySelector('.results-count');
            if (resultsCount) {
                // Parse current numbers
                const match = resultsCount.textContent.match(/Showing (\d+)-(\d+) of (\d+)/);
                if (match) {
                    const currentShowing = parseInt(match[2]);
                    const currentTotal = parseInt(match[3]);
                    const newTotal = currentTotal + newOrdersCount;
                    const newEnd = Math.min(currentShowing + newOrdersCount, 12);
                    
                    resultsCount.textContent = `Showing 1-${newEnd} of ${newTotal} orders`;
                }
            }
        }

        function updateSinglePamoOrderCard(cardElement, order) {
            // Update data-status attribute (important for filtering)
            const oldStatus = cardElement.getAttribute('data-status');
            cardElement.setAttribute('data-status', order.status);
            
            // Update status badge
            const statusBadge = cardElement.querySelector('.status-badge');
            if (statusBadge) {
                statusBadge.className = `status-badge ${order.status}`;
                statusBadge.textContent = order.status ? order.status.charAt(0).toUpperCase() + order.status.slice(1) : 'Unknown';
                
                // Flash animation for status changes
                statusBadge.style.animation = 'flash 0.5s ease-in-out';
                setTimeout(() => statusBadge.style.animation = '', 500);
            }

            // Update action buttons based on status
            const actionButtons = cardElement.querySelector('.order-actions');
            if (actionButtons) {
                if (order.status === 'pending') {
                    actionButtons.innerHTML = `
                        <button class="accept-btn" onclick="updateOrderStatus(${order.id}, 'approved')">
                            <i class="fas fa-check"></i> Accept
                        </button>
                        <button class="reject-btn" onclick="showRejectionModal(${order.id})">
                            <i class="fas fa-times"></i> Reject
                        </button>
                    `;
                } else if (order.status === 'approved') {
                    actionButtons.innerHTML = `
                        <button class="complete-btn" data-order-id="${order.id}">
                            <i class="fas fa-check-double"></i> Mark as Completed (After Payment)
                        </button>
                    `;
                } else {
                    actionButtons.innerHTML = '';
                }
            }

            // Update payment date if completed
            const orderFooter = cardElement.querySelector('.order-footer');
            const paymentDate = orderFooter.querySelector('.payment-date');
            if (order.status === 'completed' && order.formatted_payment_date) {
                if (!paymentDate) {
                    const dateSpan = document.createElement('span');
                    dateSpan.className = 'payment-date';
                    dateSpan.innerHTML = `<br>Paid: ${order.formatted_payment_date}`;
                    orderFooter.querySelector('.order-date').appendChild(dateSpan);
                }
            }

            // Set order ID for future updates
            cardElement.setAttribute('data-order-id', order.id);
            
            // If status changed and a filter is active, re-apply filters to show/hide this card
            if (oldStatus !== order.status && currentFilterStatus) {
                applyClientSideFilters();
            }
        }

        function createPamoOrderCardElement(order) {
            const orderCard = document.createElement('div');
            orderCard.className = 'order-card';
            orderCard.setAttribute('data-order-id', order.id);
            orderCard.setAttribute('data-status', order.status);

            const orderItems = order.items_decoded || [];
            let itemsTableHtml = '';
            
            orderItems.forEach(item => {
                const cleanName = (item.item_name || '').replace(/\s[SMLX234567]+$/, '');
                itemsTableHtml += `
                    <div class="table-row">
                        <span class="item-name">${cleanName}</span>
                        <span class="item-size">${item.size || 'N/A'}</span>
                        <span class="item-quantity">${item.quantity || 0}</span>
                        <span class="item-price">₱${(item.price || 0).toFixed(2)}</span>
                    </div>
                `;
            });

            let actionButtons = '';
            if (order.status === 'pending') {
                actionButtons = `
                    <div class="order-actions">
                        <button class="accept-btn" onclick="updateOrderStatus(${order.id}, 'approved')">
                            <i class="fas fa-check"></i> Accept
                        </button>
                        <button class="reject-btn" onclick="showRejectionModal(${order.id})">
                            <i class="fas fa-times"></i> Reject
                        </button>
                    </div>
                `;
            } else if (order.status === 'approved') {
                actionButtons = `
                    <div class="order-actions">
                        <button class="complete-btn" data-order-id="${order.id}">
                            <i class="fas fa-check-double"></i> Mark as Completed (After Payment)
                        </button>
                    </div>
                `;
            }

            const orderType = order.order_type || 'online';
            const orderTypeClass = orderType === 'walk-in' ? 'walk-in-badge' : 'online-badge';
            const orderTypeIcon = orderType === 'walk-in' ? '🚶' : '🌐';

            orderCard.innerHTML = `
                <div class="order-header">
                    <div class="order-header-row">
                        <h3>Order #${order.order_number || 'N/A'}</h3>
                        <span class="order-type-badge ${orderTypeClass}" title="${orderType.charAt(0).toUpperCase() + orderType.slice(1)} Order">
                            ${orderTypeIcon} ${orderType.charAt(0).toUpperCase() + orderType.slice(1)}
                        </span>
                    </div>
                    <div class="status-badge-wrapper">
                        <span class="status-badge ${order.status}">
                            ${order.status ? order.status.charAt(0).toUpperCase() + order.status.slice(1) : 'Unknown'}
                        </span>
                    </div>
                </div>
                
                <div class="order-details">
                    <div class="customer-info">
                        <p><strong>Customer:</strong> ${(order.first_name || '') + ' ' + (order.last_name || '')}</p>
                        <p><strong>Student Number:</strong> ${order.id_number || 'N/A'}</p>
                        <p><strong>Course/Strand:</strong> ${order.program_or_position || 'N/A'}</p>
                        <p><strong>Email:</strong> ${order.email || 'N/A'}</p>
                    </div>
                    
                    <div class="items-list">
                        <h4>Ordered Items:</h4>
                        <div class="items-table">
                            <div class="table-header">
                                <span class="item-name">Item</span>
                                <span class="item-size">Size</span>
                                <span class="item-quantity">Qty</span>
                                <span class="item-price">Price</span>
                            </div>
                            ${itemsTableHtml}
                        </div>
                    </div>
                    
                    <div class="order-footer">
                        <div class="total-amount">
                            <strong>Total:</strong> ₱${order.formatted_total || '0.00'}
                        </div>
                        <div class="order-date">
                            ${order.formatted_date || 'Unknown date'}
                            ${order.status === 'completed' && order.formatted_payment_date ? 
                                `<br><span class="payment-date">Paid: ${order.formatted_payment_date}</span>` : ''}
                        </div>
                    </div>
                </div>
                ${actionButtons}
            `;
            
            return orderCard;
        }

        // Function to update order count display
        function updateOrderCount() {
            // Skip if currently processing an order
            if (window.isProcessingOrder) {
                return;
            }

            const orderCards = document.querySelectorAll('.order-card[data-order-id]');
            const currentFilter = currentPamoStatusFilter;
            
            let visibleCount = 0;
            orderCards.forEach(card => {
                if (!currentFilter || card.getAttribute('data-status') === currentFilter) {
                    visibleCount++;
                }
            });
            
            // Update any count displays if they exist
            const countDisplay = document.querySelector('.orders-count, .order-count');
            if (countDisplay) {
                countDisplay.textContent = `${visibleCount} orders`;
            }
            
            // Remove title count update
        }

        // Initialize PAMO real-time updates (Compatible with pagination)
        // Only updates orders visible on current page, shows alert for new orders
        document.addEventListener('DOMContentLoaded', function() {
            updateOrderCount();
            
            // Initial check
            updatePamoOrdersRealTime();
            
            // Check for updates every 20 seconds
            setInterval(() => {
                updatePamoOrdersRealTime();
            }, 20000);
        });

        // Expose PHP $orders as a JS object for use in modal logic
        window.ORDERS = <?php echo json_encode($orders); ?>;
        // Expose PAMO user name for receipt
        window.PAMO_USER = { name: "<?php echo addslashes($_SESSION['name'] ?? ''); ?>" };
    </script>

    <script src="../Javascript/logout-modal.js"></script>
    <script src="../PAMO JS/orders.js"></script>

    <!-- Auto-Rejection Notification Modal -->
    <div id="autoRejectionModal" class="modal">
        <div class="modal-card auto-rejection-modal">
            <div class="modal-header">
                <div class="modal-title-with-icon">
                    <i class="fas fa-exclamation-triangle rejection-icon"></i>
                    <h2>Order Auto-Rejected</h2>
                </div>
                <span class="close" onclick="closeAutoRejectionModal()">&times;</span>
            </div>
            <div class="modal-body">
                <div class="rejection-summary">
                    <p class="rejection-main-message">This order cannot be approved due to insufficient stock and has been automatically rejected.</p>
                </div>
                <div class="rejection-details">
                    <h4><i class="fas fa-info-circle"></i> Stock Details:</h4>
                    <div id="stockDetailsContent" class="stock-details-list">
                        <!-- Dynamic content will be inserted here -->
                    </div>
                </div>
                <div class="rejection-note">
                    <i class="fas fa-lightbulb"></i>
                    <span>Available stock accounts for items reserved by other accepted orders that haven't been completed yet.</span>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn-primary" onclick="closeAutoRejectionModal()">
                    <i class="fas fa-check"></i> Understood
                </button>
            </div>
        </div>
    </div>
</body>

</html>