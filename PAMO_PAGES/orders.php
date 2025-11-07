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
                                <?php elseif ($order['status'] === 'completed'): ?>
                                    <div class="order-actions">
                                        <?php 
                                        // Show exchange button for all orders (walk-in and online) within 24 hours (and no existing exchange)
                                        if (empty($order['has_exchange'])) {
                                            $hours_passed = (time() - strtotime($order['created_at'])) / 3600;
                                            if ($hours_passed < 24) {
                                        ?>
                                            <button class="exchange-btn" onclick="openWalkinExchangeModal(<?php echo $order['id']; ?>, '<?php echo htmlspecialchars($order['order_number']); ?>')">
                                                <i class="fas fa-exchange-alt"></i> Process Exchange
                                            </button>
                                        <?php 
                                            }
                                        }
                                        ?>
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

        // Initialize knownOrderIds from orders already on the page (from PHP)
        function initializeKnownOrders() {
            const existingCards = document.querySelectorAll('.order-card[data-order-id]');
            existingCards.forEach(card => {
                const orderId = card.getAttribute('data-order-id');
                if (orderId) {
                    knownOrderIds.add(String(orderId));
                }
            });
            console.log('[PAMO] Initialized with', knownOrderIds.size, 'known orders');
        }

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
                        // First load - just update timestamp, don't modify knownOrderIds
                        // (already initialized from DOM)
                        lastPamoOrderCheck = data.last_update;
                        isInitialized = true;
                        console.log('[PAMO] First polling completed, monitoring for new orders...');
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
                
                // ALWAYS update window.ORDERS for ALL orders (visible or not) to keep memory in sync
                if (window.ORDERS && Array.isArray(window.ORDERS)) {
                    const orderIndex = window.ORDERS.findIndex(o => String(o.id) === orderId);
                    if (orderIndex !== -1) {
                        // Update existing order in memory
                        window.ORDERS[orderIndex] = order;
                    } else {
                        // Add order to memory if it doesn't exist yet
                        window.ORDERS.push(order);
                    }
                } else {
                    // Initialize window.ORDERS if it doesn't exist
                    window.ORDERS = [order];
                }
                
                if (existingCard) {
                    // This order is visible on current page - update the DOM
                    const oldStatus = existingCard.getAttribute('data-status');
                    const newStatus = order.status;
                    
                    if (oldStatus !== newStatus) {
                        statusChangedOnCurrentPage = true;
                    }
                    
                    updateSinglePamoOrderCard(existingCard, order);
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
                
                // CRITICAL: Add new order to window.ORDERS array for memory access
                if (window.ORDERS && Array.isArray(window.ORDERS)) {
                    // Check if order already exists in window.ORDERS
                    const existingIndex = window.ORDERS.findIndex(o => String(o.id) === String(order.id));
                    if (existingIndex === -1) {
                        // Add new order at the beginning (newest first)
                        window.ORDERS.unshift(order);
                    }
                } else {
                    // Initialize window.ORDERS if it doesn't exist
                    window.ORDERS = [order];
                }
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
                } else if (order.status === 'completed') {
                    // Calculate hours passed for exchange eligibility
                    const orderDate = new Date(order.created_at);
                    const now = new Date();
                    const hoursPassed = (now - orderDate) / (1000 * 60 * 60);
                    
                    let exchangeButton = '';
                    // Show exchange button for all orders (walk-in and online) within 24 hours and no existing exchange
                    if (!order.has_exchange && hoursPassed < 24) {
                        exchangeButton = `
                            <button class="exchange-btn" onclick="openWalkinExchangeModal(${order.id}, '${order.order_number || ''}')">
                                <i class="fas fa-exchange-alt"></i> Process Exchange
                            </button>
                        `;
                    }
                    
                    actionButtons.innerHTML = exchangeButton;
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
            
            // Initialize known orders from DOM BEFORE starting polling
            initializeKnownOrders();
            
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

    <!-- Exchange Modal (supports both walk-in and online orders) -->
    <div id="walkinExchangeModal" class="modal">
        <div class="modal-content exchange-modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-exchange-alt"></i> Process Exchange</h2>
                <span class="close" onclick="closeWalkinExchangeModal()">&times;</span>
            </div>
            <div class="modal-body">
                <div id="walkinExchangeContent">
                    <!-- Content will be loaded dynamically -->
                    <div class="loading-spinner">
                        <i class="fas fa-spinner fa-spin"></i> Loading order details...
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="cancel-btn" onclick="closeWalkinExchangeModal()">Cancel</button>
                <button type="button" class="save-btn" id="submitWalkinExchange" disabled>Process Exchange</button>
            </div>
        </div>
    </div>

    <!-- Exchange Slip Preview Modal -->
    <div id="exchangeSlipPreviewModal" class="modal" style="display: none;">
        <div class="modal-content" style="max-width: 900px;">
            <div class="modal-header">
                <h2><i class="fas fa-file-invoice"></i> Exchange Slip Preview</h2>
                <span class="close" onclick="closeExchangeSlipPreview()">&times;</span>
            </div>
            <div class="modal-body" style="padding: 20px;">
                <div id="exchangeSlipContent" style="background: white; border: 1px solid #ddd;">
                    <!-- Exchange slip will be loaded here -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="cancel-btn" onclick="closeExchangeSlipPreview()">Close</button>
                <button type="button" class="save-btn" onclick="printExchangeSlip()">
                    <i class="fas fa-print"></i> Print Slip
                </button>
            </div>
        </div>
    </div>

    <!-- Walk-In Exchange Styles -->
    <link rel="stylesheet" href="../CSS/exchange.css">
    <style>
        /* Walk-in Exchange Modal Specific Styles */
        #walkinExchangeModal.modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.6);
            z-index: 10000;
            align-items: center;
            justify-content: center;
        }
        
        #walkinExchangeModal.modal[style*="flex"] {
            display: flex !important;
        }
        
        #walkinExchangeModal .modal-content {
            background: white;
            border-radius: 12px;
            width: 90%;
            max-width: 1100px;
            max-height: 90vh;
            display: flex;
            flex-direction: column;
            box-shadow: 0 10px 40px rgba(0,0,0,0.3);
        }
        
        #walkinExchangeModal .modal-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px 30px;
            border-radius: 12px 12px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-shrink: 0;
        }
        
        #walkinExchangeModal .modal-header h2 {
            margin: 0;
            font-size: 22px;
            font-weight: 600;
        }
        
        #walkinExchangeModal .modal-header .close {
            color: white;
            font-size: 32px;
            font-weight: bold;
            cursor: pointer;
            line-height: 1;
            transition: transform 0.2s;
        }
        
        #walkinExchangeModal .modal-header .close:hover {
            transform: rotate(90deg);
        }
        
        #walkinExchangeModal .modal-body {
            flex: 1;
            overflow-y: auto;
            padding: 25px 30px;
            min-height: 0;
        }
        
        #walkinExchangeModal .modal-footer {
            padding: 15px 30px;
            background: #f8f9fa;
            border-top: 1px solid #dee2e6;
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            flex-shrink: 0;
            border-radius: 0 0 12px 12px;
        }
        
        /* Exchange Info Box */
        .exchange-info-box {
            background: #f0f7ff;
            border: 1px solid #b3d9ff;
            border-radius: 8px;
            padding: 15px 20px;
            margin-bottom: 20px;
        }
        
        .exchange-info-box .info-row {
            display: flex;
            justify-content: space-between;
            padding: 6px 0;
            border-bottom: 1px dashed #cce5ff;
        }
        
        .exchange-info-box .info-row:last-child {
            border-bottom: none;
        }
        
        .exchange-info-box .info-label {
            font-weight: 600;
            color: #0056b3;
        }
        
        .exchange-info-box .info-value {
            color: #333;
        }
        
        /* Exchange Note */
        .exchange-note {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 12px 15px;
            margin-bottom: 20px;
            border-radius: 4px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 14px;
            color: #856404;
        }
        
        /* Exchange Items Container */
        .exchange-items-container {
            margin-bottom: 20px;
        }
        
        .exchange-items-container h4 {
            font-size: 16px;
            font-weight: 600;
            color: #333;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e9ecef;
        }
        
        /* Exchange Item Card */
        .exchange-item-card {
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            padding: 18px;
            margin-bottom: 15px;
            background: #fafafa;
            transition: all 0.3s ease;
        }
        
        .exchange-item-card .item-header {
            display: flex;
            gap: 15px;
            align-items: flex-start;
        }
        
        .exchange-item-card .exchange-item-checkbox {
            width: 20px;
            height: 20px;
            margin-top: 5px;
            cursor: pointer;
            flex-shrink: 0;
        }
        
        .exchange-item-card .item-basic-info {
            flex: 1;
            display: flex;
            gap: 15px;
            cursor: pointer;
        }
        
        .exchange-item-card .item-image {
            width: 70px;
            height: 70px;
            flex-shrink: 0;
        }
        
        .exchange-item-card .item-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 6px;
            border: 1px solid #ddd;
        }
        
        .exchange-item-card .item-details {
            flex: 1;
        }
        
        .exchange-item-card .item-details h5 {
            margin: 0 0 8px 0;
            font-size: 15px;
            font-weight: 600;
            color: #333;
        }
        
        .exchange-item-card .item-details p {
            margin: 4px 0;
            font-size: 13px;
            color: #666;
        }
        
        .exchange-item-card .item-exchange-options {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px dashed #ccc;
        }
        
        .exchange-item-card .exchange-option-row {
            display: grid;
            grid-template-columns: 2fr 1fr 2fr;
            gap: 15px;
            align-items: start;
        }
        
        .exchange-item-card .option-group label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: #555;
            margin-bottom: 6px;
        }
        
        .exchange-item-card .option-group select,
        .exchange-item-card .option-group input {
            width: 100%;
            padding: 8px 10px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
        }
        
        .exchange-item-card .price-diff-group .price-info {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 6px;
            padding: 10px;
        }
        
        .exchange-item-card .price-row {
            display: flex;
            justify-content: space-between;
            padding: 4px 0;
            font-size: 13px;
        }
        
        .exchange-item-card .exchange-price-diff {
            font-weight: 700;
            font-size: 14px;
        }
        
        .exchange-item-card .exchange-price-diff.positive {
            color: #dc3545;
        }
        
        .exchange-item-card .exchange-price-diff.negative {
            color: #28a745;
        }
        
        .exchange-item-card .exchange-price-diff.neutral {
            color: #6c757d;
        }
        
        /* Loading Spinner */
        .loading-spinner {
            text-align: center;
            padding: 40px;
            color: #667eea;
            font-size: 18px;
        }
        
        .loading-spinner i {
            font-size: 32px;
            margin-bottom: 10px;
            display: block;
        }
        
        /* Exchange Slip Preview Modal */
        #exchangeSlipPreviewModal {
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        #exchangeSlipPreviewModal .modal-content {
            max-width: 900px;
            max-height: 90vh;
            display: flex;
            flex-direction: column;
            margin: auto;
        }
        
        #exchangeSlipPreviewModal .modal-body {
            flex: 1;
            overflow-y: auto;
            padding: 0;
            background: #f5f5f5;
        }
        
        #exchangeSlipContent {
            background: white;
            border: 1px solid #ddd;
            min-height: 400px;
            padding: 20px;
        }
        
        /* Multi-Size Exchange Styles */
        .exchange-variants-header {
            margin-bottom: 15px;
            padding: 12px;
            background: #f8f9fa;
            border-radius: 6px;
        }
        
        .exchange-variants-header h5 {
            margin: 0 0 5px 0;
            font-size: 14px;
            font-weight: 600;
        }
        
        .exchange-variants-header .text-muted {
            margin: 0 0 10px 0;
            font-size: 12px;
            color: #666;
        }
        
        .add-size-btn {
            background: #667eea;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 13px;
            transition: background 0.3s;
        }
        
        .add-size-btn:hover {
            background: #5568d3;
        }
        
        .exchange-variants-list {
            margin-bottom: 15px;
        }
        
        .size-variant-row {
            display: grid;
            grid-template-columns: 2fr 1fr auto;
            gap: 12px;
            align-items: end;
            padding: 12px;
            margin-bottom: 10px;
            background: #fff;
            border: 1px solid #dee2e6;
            border-radius: 6px;
        }
        
        .size-variant-row .option-group {
            margin: 0;
        }
        
        .size-variant-row .option-group label {
            display: block;
            font-size: 12px;
            font-weight: 600;
            color: #555;
            margin-bottom: 5px;
        }
        
        .size-variant-row select,
        .size-variant-row input {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 13px;
        }
        
        .remove-variant-btn {
            background: #dc3545;
            color: white;
            border: none;
            padding: 8px 12px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 13px;
            transition: background 0.3s;
        }
        
        .remove-variant-btn:hover {
            background: #c82333;
        }
        
        .exchange-totals {
            padding: 12px;
            background: #e9ecef;
            border-radius: 6px;
            margin-top: 10px;
        }
        
        .exchange-totals .total-row {
            display: flex;
            justify-content: space-between;
            padding: 5px 0;
            font-weight: 600;
        }
        
        .exchange-totals .total-row span:last-child {
            color: #495057;
        }
        
        .size-variant-info {
            font-size: 11px;
            color: #6c757d;
            margin-top: 3px;
        }
    </style>
    
    <!-- Exchange Processing Script (supports both walk-in and online orders) -->
    <script>
    let currentWalkinOrderId = null;
    let currentWalkinOrderNumber = null;
    let walkinExchangeItems = [];
    
    function openWalkinExchangeModal(orderId, orderNumber) {
        currentWalkinOrderId = orderId;
        currentWalkinOrderNumber = orderNumber;
        
        const modal = document.getElementById('walkinExchangeModal');
        modal.style.display = 'flex';
        
        // Fetch order details and check eligibility
        fetch(`../Backend/get_exchange_eligibility.php?order_id=${orderId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.eligible) {
                    renderWalkinExchangeForm(data);
                } else {
                    document.getElementById('walkinExchangeContent').innerHTML = `
                        <div class="error-message">
                            <i class="fas fa-exclamation-circle"></i>
                            <p>${data.message || 'This order is not eligible for exchange.'}</p>
                            <p><small>${data.reason || ''}</small></p>
                        </div>
                    `;
                    document.getElementById('submitWalkinExchange').disabled = true;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                document.getElementById('walkinExchangeContent').innerHTML = `
                    <div class="error-message">
                        <i class="fas fa-exclamation-circle"></i>
                        <p>Failed to load order details. Please try again.</p>
                    </div>
                `;
            });
    }
    
    function closeWalkinExchangeModal() {
        const modal = document.getElementById('walkinExchangeModal');
        modal.style.display = 'none';
        currentWalkinOrderId = null;
        currentWalkinOrderNumber = null;
        walkinExchangeItems = [];
    }
    
    function renderWalkinExchangeForm(data) {
        const items = data.items;
        let html = `
            <div class="exchange-info-box">
                <div class="info-row">
                    <span class="info-label">Order Number:</span>
                    <span class="info-value">${data.order_number}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Customer:</span>
                    <span class="info-value">${data.customer_name} (${data.customer_id_number})</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Order Date:</span>
                    <span class="info-value">${new Date(data.order_date).toLocaleString()}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Time Remaining:</span>
                    <span class="info-value">${data.hours_remaining} hours</span>
                </div>
            </div>
            
            <div class="exchange-note">
                <i class="fas fa-info-circle"></i>
                <span>Select items to exchange. You can exchange partial quantities.</span>
            </div>
            
            <div class="exchange-items-container">
                <h4><i class="fas fa-box"></i> Items Available for Exchange</h4>
        `;
        
        items.forEach((item, index) => {
            html += `
                <div class="exchange-item-card" data-index="${index}">
                    <div class="item-header">
                        <input type="checkbox" 
                               id="walkin_item_${index}" 
                               class="exchange-item-checkbox"
                               onchange="toggleWalkinItemSelection(${index})">
                        <label for="walkin_item_${index}" class="item-basic-info">
                            <div class="item-image">
                                <img src="../${item.image_path}" alt="${item.item_name}" onerror="this.onerror=null; this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%22100%22 height=%22100%22%3E%3Crect fill=%22%23ddd%22 width=%22100%22 height=%22100%22/%3E%3Ctext fill=%22%23999%22 x=%2250%25%22 y=%2250%25%22 text-anchor=%22middle%22 dy=%22.3em%22%3ENo Image%3C/text%3E%3C/svg%3E';">
                            </div>
                            <div class="item-details">
                                <h5>${item.item_name}</h5>
                                <p class="item-code">Code: ${item.item_code}</p>
                                <p class="item-current">Size: ${item.size} | Qty: ${item.quantity} | ₱${parseFloat(item.price).toFixed(2)}</p>
                                <p class="item-available">Available: ${item.available_for_exchange}</p>
                            </div>
                        </label>
                    </div>
                    <div class="item-exchange-options" id="walkin_options_${index}" style="display: none;">
                        <div class="exchange-variants-header">
                            <h5>Select Size(s) and Quantity to Exchange</h5>
                            <p class="text-muted">Available to exchange: ${item.available_for_exchange} pcs</p>
                            <button type="button" class="add-size-btn" onclick="addSizeVariant(${index})">
                                <i class="fas fa-plus"></i> Add Size Variant
                            </button>
                        </div>
                        <div class="exchange-variants-list" id="walkin_variants_${index}">
                            <!-- Size variants will be added here -->
                        </div>
                        <div class="exchange-totals">
                            <div class="total-row">
                                <span>Total Quantity Selected:</span>
                                <span id="walkin_total_qty_${index}">0</span> / ${item.available_for_exchange}
                            </div>
                            <div class="total-row">
                                <span>Total Price Difference:</span>
                                <span id="walkin_total_diff_${index}" class="exchange-price-diff neutral">₱0.00</span>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        });
        
        html += `
            </div>
            
            <div class="exchange-summary-section" id="walkinExchangeSummary" style="display: none;">
                <h4><i class="fas fa-calculator"></i> Exchange Summary</h4>
                <div class="summary-grid">
                    <div class="summary-item">
                        <span class="summary-label">Items to Exchange:</span>
                        <span class="summary-value" id="walkin_summary_items">0</span>
                    </div>
                    <div class="summary-item">
                        <span class="summary-label">Total Quantity:</span>
                        <span class="summary-value" id="walkin_summary_quantity">0</span>
                    </div>
                    <div class="summary-item">
                        <span class="summary-label">Original Total:</span>
                        <span class="summary-value" id="walkin_summary_original">₱0.00</span>
                    </div>
                    <div class="summary-item">
                        <span class="summary-label">New Total:</span>
                        <span class="summary-value" id="walkin_summary_new">₱0.00</span>
                    </div>
                </div>
                <div id="walkin_adjustment_box" class="exchange-adjustment-box" style="display: none;">
                    <div class="adjustment-content">
                        <div class="adjustment-title" id="walkin_adjustment_title">ADDITIONAL PAYMENT REQUIRED</div>
                        <div class="adjustment-amount" id="walkin_adjustment_amount">₱0.00</div>
                        <div class="adjustment-note" id="walkin_adjustment_note">Customer needs to pay this amount.</div>
                    </div>
                </div>
            </div>
            
            <div class="form-group">
                <label for="walkinExchangeRemarks">Remarks (Optional):</label>
                <textarea id="walkinExchangeRemarks" rows="3" placeholder="Any additional notes..."></textarea>
            </div>
            
            <div class="form-group">
                <label>
                    <input type="checkbox" id="walkinAutoApprove" checked>
                    Auto-approve exchange (process immediately without admin approval)
                </label>
            </div>
        `;
        
        document.getElementById('walkinExchangeContent').innerHTML = html;
        
        // Store original item data with size variants
        walkinExchangeItems = items.map((item, index) => ({
            ...item,
            index: index,
            selected: false,
            sizeVariants: [],  // Array to store multiple size/qty combinations
            availableSizes: []  // Cache of available sizes
        }));
        
        // Setup submit button
        document.getElementById('submitWalkinExchange').onclick = submitWalkinExchange;
    }
    
    function toggleWalkinItemSelection(index) {
        const checkbox = document.getElementById(`walkin_item_${index}`);
        const options = document.getElementById(`walkin_options_${index}`);
        const item = walkinExchangeItems[index];
        
        if (checkbox.checked) {
            options.style.display = 'block';
            item.selected = true;
            // Load available sizes and add first variant automatically
            loadWalkinAvailableSizes(index).then(() => {
                addSizeVariant(index);
            });
        } else {
            options.style.display = 'none';
            item.selected = false;
            // Clear all variants
            item.sizeVariants = [];
            document.getElementById(`walkin_variants_${index}`).innerHTML = '';
        }
        
        updateWalkinExchangeSummary();
    }
    
    function loadWalkinAvailableSizes(index) {
        const item = walkinExchangeItems[index];
        
        return fetch(`../Backend/get_available_sizes.php?item_code=${item.item_code}&exclude_size=${item.size}`)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.sizes.length > 0) {
                    item.availableSizes = data.sizes;
                    return data.sizes;
                } else {
                    item.availableSizes = [];
                    return [];
                }
            })
            .catch(error => {
                console.error('Error loading sizes:', error);
                item.availableSizes = [];
                return [];
            });
    }
    
    function addSizeVariant(index) {
        const item = walkinExchangeItems[index];
        const variantsList = document.getElementById(`walkin_variants_${index}`);
        
        if (item.availableSizes.length === 0) {
            alert('No available sizes for exchange');
            return;
        }
        
        // Generate unique variant ID
        const variantId = `variant_${index}_${Date.now()}`;
        
        // Create variant row
        const variantRow = document.createElement('div');
        variantRow.className = 'size-variant-row';
        variantRow.id = variantId;
        
        let sizesOptions = '<option value="">Select size...</option>';
        item.availableSizes.forEach(size => {
            sizesOptions += `<option value="${size.size}" data-price="${size.price}" data-stock="${size.quantity}">${size.size} (Stock: ${size.quantity})</option>`;
        });
        
        variantRow.innerHTML = `
            <div class="option-group">
                <label>New Size:</label>
                <select class="variant-size-select" onchange="updateVariantPrice('${variantId}', ${index})">
                    ${sizesOptions}
                </select>
                <div class="size-variant-info">Price: <span class="variant-price">-</span></div>
            </div>
            <div class="option-group">
                <label>Quantity:</label>
                <input type="number" class="variant-qty-input" min="1" max="${item.available_for_exchange}" value="1" 
                       onchange="updateItemTotals(${index})">
            </div>
            <div class="option-group">
                <button type="button" class="remove-variant-btn" onclick="removeSizeVariant('${variantId}', ${index})">
                    <i class="fas fa-trash"></i> Remove
                </button>
            </div>
        `;
        
        variantsList.appendChild(variantRow);
        
        // Add to item's variants array
        item.sizeVariants.push({
            id: variantId,
            size: '',
            quantity: 1,
            price: 0,
            stock: 0
        });
        
        updateItemTotals(index);
    }
    
    function removeSizeVariant(variantId, index) {
        const item = walkinExchangeItems[index];
        const variantRow = document.getElementById(variantId);
        
        if (variantRow) {
            variantRow.remove();
        }
        
        // Remove from variants array
        item.sizeVariants = item.sizeVariants.filter(v => v.id !== variantId);
        
        updateItemTotals(index);
        updateWalkinExchangeSummary();
    }
    
    function updateVariantPrice(variantId, index) {
        const item = walkinExchangeItems[index];
        const variantRow = document.getElementById(variantId);
        const sizeSelect = variantRow.querySelector('.variant-size-select');
        const selectedOption = sizeSelect.options[sizeSelect.selectedIndex];
        const priceDisplay = variantRow.querySelector('.variant-price');
        
        if (selectedOption && selectedOption.value) {
            const price = parseFloat(selectedOption.dataset.price);
            const stock = parseInt(selectedOption.dataset.stock);
            priceDisplay.textContent = `₱${price.toFixed(2)}`;
            
            // Update variant data
            const variant = item.sizeVariants.find(v => v.id === variantId);
            if (variant) {
                variant.size = selectedOption.value;
                variant.price = price;
                variant.stock = stock;
                
                // Update max quantity based on stock
                const qtyInput = variantRow.querySelector('.variant-qty-input');
                qtyInput.max = Math.min(stock, item.available_for_exchange);
            }
        } else {
            priceDisplay.textContent = '-';
        }
        
        updateItemTotals(index);
    }
    
    function updateItemTotals(index) {
        const item = walkinExchangeItems[index];
        const variantsList = document.getElementById(`walkin_variants_${index}`);
        const variantRows = variantsList.querySelectorAll('.size-variant-row');
        
        let totalQty = 0;
        let totalDiff = 0;
        
        // Update variant quantities
        variantRows.forEach((row, i) => {
            const qtyInput = row.querySelector('.variant-qty-input');
            const qty = parseInt(qtyInput.value) || 0;
            
            if (item.sizeVariants[i]) {
                item.sizeVariants[i].quantity = qty;
                totalQty += qty;
                
                if (item.sizeVariants[i].size && item.sizeVariants[i].price) {
                    const priceDiff = (item.sizeVariants[i].price - item.price) * qty;
                    totalDiff += priceDiff;
                }
            }
        });
        
        // Validate total quantity
        const totalQtyDisplay = document.getElementById(`walkin_total_qty_${index}`);
        totalQtyDisplay.textContent = totalQty;
        
        if (totalQty > item.available_for_exchange) {
            totalQtyDisplay.style.color = 'red';
        } else {
            totalQtyDisplay.style.color = '#495057';
        }
        
        // Update total difference
        const totalDiffDisplay = document.getElementById(`walkin_total_diff_${index}`);
        if (totalDiff > 0) {
            totalDiffDisplay.textContent = `+₱${totalDiff.toFixed(2)}`;
            totalDiffDisplay.className = 'exchange-price-diff positive';
        } else if (totalDiff < 0) {
            totalDiffDisplay.textContent = `₱${totalDiff.toFixed(2)}`;
            totalDiffDisplay.className = 'exchange-price-diff negative';
        } else {
            totalDiffDisplay.textContent = '₱0.00';
            totalDiffDisplay.className = 'exchange-price-diff neutral';
        }
        
        updateWalkinExchangeSummary();
    }
    
    function updateWalkinExchangeSummary() {
        const selectedItems = walkinExchangeItems.filter(item => item.selected);
        
        if (selectedItems.length === 0) {
            document.getElementById('walkinExchangeSummary').style.display = 'none';
            document.getElementById('submitWalkinExchange').disabled = true;
            return;
        }
        
        let totalItems = 0;
        let totalQuantity = 0;
        let originalTotal = 0;
        let newTotal = 0;
        let hasValidSelection = true;
        
        selectedItems.forEach(item => {
            // Count this item only if it has valid variants
            let itemHasValidVariants = false;
            let itemQuantity = 0;
            
            // Process all size variants for this item
            if (item.sizeVariants && item.sizeVariants.length > 0) {
                item.sizeVariants.forEach(variant => {
                    if (variant.size && variant.quantity > 0) {
                        itemQuantity += variant.quantity;
                        originalTotal += parseFloat(item.price) * variant.quantity;
                        newTotal += parseFloat(variant.price) * variant.quantity;
                        itemHasValidVariants = true;
                    }
                });
            }
            
            if (itemHasValidVariants) {
                totalItems++;
                totalQuantity += itemQuantity;
            } else {
                hasValidSelection = false;
            }
        });
        
        const totalDifference = newTotal - originalTotal;
        
        document.getElementById('walkin_summary_items').textContent = totalItems;
        document.getElementById('walkin_summary_quantity').textContent = totalQuantity;
        document.getElementById('walkin_summary_original').textContent = `₱${originalTotal.toFixed(2)}`;
        document.getElementById('walkin_summary_new').textContent = `₱${newTotal.toFixed(2)}`;
        
        const adjustmentBox = document.getElementById('walkin_adjustment_box');
        const adjustmentTitle = document.getElementById('walkin_adjustment_title');
        const adjustmentAmount = document.getElementById('walkin_adjustment_amount');
        const adjustmentNote = document.getElementById('walkin_adjustment_note');
        
        if (totalDifference > 0) {
            adjustmentBox.className = 'exchange-adjustment-box additional-payment';
            adjustmentTitle.textContent = 'ADDITIONAL PAYMENT REQUIRED';
            adjustmentAmount.textContent = `₱${totalDifference.toFixed(2)}`;
            adjustmentNote.textContent = 'Customer needs to pay this amount to complete the exchange.';
            adjustmentBox.style.display = 'block';
        } else if (totalDifference < 0) {
            adjustmentBox.className = 'exchange-adjustment-box refund';
            adjustmentTitle.textContent = 'REFUND DUE';
            adjustmentAmount.textContent = `₱${Math.abs(totalDifference).toFixed(2)}`;
            adjustmentNote.textContent = 'This amount should be refunded to the customer.';
            adjustmentBox.style.display = 'block';
        } else {
            adjustmentBox.className = 'exchange-adjustment-box equal-exchange';
            adjustmentTitle.textContent = 'EQUAL EXCHANGE';
            adjustmentAmount.textContent = '₱0.00';
            adjustmentNote.textContent = 'No payment adjustment needed.';
            adjustmentBox.style.display = 'block';
        }
        
        document.getElementById('walkinExchangeSummary').style.display = 'block';
        document.getElementById('submitWalkinExchange').disabled = !hasValidSelection;
    }
    
    function hideExchangeButtonForOrder(orderId) {
        // Find all exchange buttons for this order and hide them
        const orderCards = document.querySelectorAll(`.order-card[data-order-id="${orderId}"]`);
        orderCards.forEach(card => {
            const exchangeBtn = card.querySelector('.exchange-btn');
            if (exchangeBtn) {
                exchangeBtn.style.display = 'none';
            }
        });
    }
    
    function submitWalkinExchange() {
        const selectedItems = walkinExchangeItems.filter(item => item.selected);
        
        if (selectedItems.length === 0) {
            alert('Please select at least one item to exchange.');
            return;
        }
        
        // Validate that all selected items have valid size variants
        const exchangeData = [];
        
        for (const item of selectedItems) {
            if (!item.sizeVariants || item.sizeVariants.length === 0) {
                alert(`Please add at least one size variant for ${item.item_name}`);
                return;
            }
            
            let totalVariantQty = 0;
            
            // Validate each size variant
            for (const variant of item.sizeVariants) {
                if (!variant.size || variant.size === '') {
                    alert(`Please select a size for all variants of ${item.item_name}`);
                    return;
                }
                
                if (!variant.quantity || variant.quantity <= 0) {
                    alert(`Please enter a valid quantity for all variants of ${item.item_name}`);
                    return;
                }
                
                totalVariantQty += variant.quantity;
                
                // Add each variant as a separate exchange item
                exchangeData.push({
                    original_item_code: item.item_code,
                    new_item_code: item.item_code,
                    new_size: variant.size,
                    exchange_quantity: variant.quantity,
                    available_quantity: item.available_for_exchange,
                    original_price: parseFloat(item.price),
                    new_price: parseFloat(variant.price)
                });
            }
            
            // Validate total quantity doesn't exceed available
            if (totalVariantQty > item.available_for_exchange) {
                alert(`Total exchange quantity (${totalVariantQty}) exceeds available quantity (${item.available_for_exchange}) for ${item.item_name}`);
                return;
            }
        }
        
        const remarks = document.getElementById('walkinExchangeRemarks').value.trim();
        const autoApprove = document.getElementById('walkinAutoApprove').checked;
        
        const formData = new FormData();
        formData.append('order_id', currentWalkinOrderId);
        formData.append('exchange_items', JSON.stringify(exchangeData));
        formData.append('remarks', remarks);
        formData.append('auto_approve', autoApprove ? '1' : '0');
        
        document.getElementById('submitWalkinExchange').disabled = true;
        document.getElementById('submitWalkinExchange').innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
        
        fetch('../Backend/process_walkin_exchange.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Hide the exchange button for this order
                hideExchangeButtonForOrder(currentWalkinOrderId);
                
                // Close the exchange modal
                closeWalkinExchangeModal();
                
                // Show the exchange slip preview
                showExchangeSlipPreview(data.exchange_id, data);
            } else {
                alert('Error: ' + data.message);
                document.getElementById('submitWalkinExchange').disabled = false;
                document.getElementById('submitWalkinExchange').innerHTML = 'Process Exchange';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while processing the exchange. Please try again.');
            document.getElementById('submitWalkinExchange').disabled = false;
            document.getElementById('submitWalkinExchange').innerHTML = 'Process Exchange';
        });
    }
    
    // Exchange Slip Preview Functions
    let currentExchangeData = null;
    
    function showExchangeSlipPreview(exchangeId, exchangeData) {
        currentExchangeData = exchangeData;
        
        // Show loading
        document.getElementById('exchangeSlipContent').innerHTML = `
            <div style="text-align: center; padding: 40px;">
                <i class="fas fa-spinner fa-spin" style="font-size: 32px; color: #667eea;"></i>
                <p style="margin-top: 15px;">Loading exchange slip...</p>
            </div>
        `;
        
        // Show modal
        document.getElementById('exchangeSlipPreviewModal').style.display = 'flex';
        
        // Fetch the HTML content
        fetch(`../Backend/get_exchange_slip_html.php?exchange_id=${exchangeId}`)
            .then(response => response.text())
            .then(html => {
                document.getElementById('exchangeSlipContent').innerHTML = html;
            })
            .catch(error => {
                console.error('Error loading slip:', error);
                document.getElementById('exchangeSlipContent').innerHTML = `
                    <div style="text-align: center; padding: 40px; color: red;">
                        <i class="fas fa-exclamation-circle" style="font-size: 32px;"></i>
                        <p style="margin-top: 15px;">Failed to load exchange slip</p>
                    </div>
                `;
            });
    }
    
    function closeExchangeSlipPreview() {
        document.getElementById('exchangeSlipPreviewModal').style.display = 'none';
        
        // Clear content to free memory
        document.getElementById('exchangeSlipContent').innerHTML = '';
        
        // Close the exchange modal as well
        closeWalkinExchangeModal();
        
        // No need to reload - the order card will automatically update via polling
        // or user can manually refresh if needed
    }
    
    function refreshOrderCard(orderId) {
        // Simple approach: Hide exchange button for this order
        // The order card UI will be updated by the existing polling mechanism
        hideExchangeButtonForOrder(orderId);
    }
    
    function printExchangeSlip() {
        const slipContent = document.getElementById('exchangeSlipContent');
        
        if (!slipContent || !slipContent.innerHTML.trim()) {
            alert('No exchange slip content to print');
            return;
        }
        
        // Create a hidden iframe for printing (same pattern as Walk-in Payable Slip)
        let printFrame = document.getElementById('exchangeSlipPrintFrame');
        
        if (!printFrame) {
            printFrame = document.createElement('iframe');
            printFrame.id = 'exchangeSlipPrintFrame';
            printFrame.style.position = 'fixed';
            printFrame.style.top = '-9999px';
            printFrame.style.left = '-9999px';
            printFrame.style.width = '0';
            printFrame.style.height = '0';
            document.body.appendChild(printFrame);
        }
        
        // Get the HTML content
        const slipHtml = slipContent.innerHTML;
        
        // Write content to iframe
        const iframeDoc = printFrame.contentWindow || printFrame.contentDocument;
        if (iframeDoc.document) {
            iframeDoc.document.open();
            iframeDoc.document.write(slipHtml);
            iframeDoc.document.close();
        }
        
        // Wait for content to load, then print
        setTimeout(() => {
            try {
                printFrame.contentWindow.focus();
                printFrame.contentWindow.print();
                
                // Automatically close the modal after print dialog is triggered
                setTimeout(() => {
                    closeExchangeSlipPreview();
                }, 500);
            } catch (e) {
                console.error('Exchange slip print error:', e);
                alert('Could not print exchange slip. Please try again or use your browser\'s print function.');
            }
        }, 250);
    }
    </script>

</body>

</html>