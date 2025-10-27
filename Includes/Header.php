<?php


require_once dirname(__FILE__) . '/init_cart.php';

$current_page = basename($_SERVER['PHP_SELF']);

$mailboxCount = 0;
if (isset($_SESSION['user_id'])) {
    require_once dirname(__FILE__) . '/connection.php';
    
    // Check if user account is inactive
    $stmt = $conn->prepare("SELECT status, pre_order_strikes FROM account WHERE id = :uid");
    $stmt->bindParam(':uid', $_SESSION['user_id'], PDO::PARAM_INT);
    $stmt->execute();
    $userStatus = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($userStatus && $userStatus['status'] === 'inactive') {
        // User account is inactive, destroy session and redirect to login
        session_destroy();
        if (isset($userStatus['pre_order_strikes']) && $userStatus['pre_order_strikes'] >= 3) {
            header("Location: login.php?error=account_inactive_strikes");
        } else {
            header("Location: login.php?error=account_inactive");
        }
        exit();
    }
    
    $stmt = $conn->prepare("SELECT COUNT(*) FROM inquiries WHERE user_id = :uid AND reply IS NOT NULL AND student_read = 0");
    $stmt->bindParam(':uid', $_SESSION['user_id'], PDO::PARAM_INT);
    $stmt->execute();
    $mailboxCount = $stmt->fetchColumn();
}
?>

<nav class="navbar" role="navigation" aria-label="Main navigation">
    <div class="logo-container">
        <div class="logo">
            <a href="home.php" aria-label="Home">
                <img src="../Images/STI-LOGO.png" alt="STI Logo" class="logo-image">
            </a>
        </div>
        <div class="hamburger">
            <span class="bar"></span>
            <span class="bar"></span>
            <span class="bar"></span>
        </div>

        <ul class="nav-links">
            <li><a href="home.php"
                    class="<?php echo ($current_page == 'home.php') ? 'active' : ''; ?>">Homepage</a></li>
            <li><a href="ProItemList.php"
                    class="<?php echo ($current_page == 'ProItemList.php') ? 'active' : ''; ?>">Products</a></li>
            <?php if (isset($_SESSION['user_id'])): ?>
            <li><a href="preorder.php"
                    class="<?php echo ($current_page == 'preorder.php') ? 'active' : ''; ?>">Pre Order</a></li>
            <?php else: ?>
            <li><a href="javascript:void(0)" onclick="redirectToLogin('preorder.php')"
                    class="<?php echo ($current_page == 'preorder.php') ? 'active' : ''; ?>">Pre Order</a></li>
            <?php endif; ?>
            <?php if (isset($_SESSION['user_id'])): ?>
            <li><a href="ProPreOrder.php"
                    class="<?php echo ($current_page == 'ProPreOrder.php') ? 'active' : ''; ?>">Place Order</a></li>
            <?php else: ?>
            <li><a href="javascript:void(0)" onclick="redirectToLogin('ProPreOrder.php')"
                    class="<?php echo ($current_page == 'ProPreOrder.php') ? 'active' : ''; ?>">Place Order</a></li>
            <?php endif; ?>
            <?php if (isset($_SESSION['user_id'])): ?>
            <li><a href="MyOrders.php"
                    class="<?php echo ($current_page == 'MyOrders.php') ? 'active' : ''; ?>">My Orders</a></li>
            <?php else: ?>
            <li><a href="javascript:void(0)" onclick="redirectToLogin('MyOrders.php')"
                    class="<?php echo ($current_page == 'MyOrders.php') ? 'active' : ''; ?>">My Orders</a></li>
            <?php endif; ?>
            <li><a href="about.php" class="<?php echo ($current_page == 'about.php') ? 'active' : ''; ?>">About</a></li>
            <li><a href="faq.php" class="<?php echo ($current_page == 'faq.php') ? 'active' : ''; ?>">FAQ</a></li>
        </ul>
    </div>

    <?php if (isset($_SESSION['user_id'])): ?>
        <div class="icons">
            <div class="icon cart-icon">
                <a href="MyCart.php" class="fas fa-shopping-cart">
                    <?php
                        $initCount = isset($_SESSION['cart_count']) ? (int)$_SESSION['cart_count'] : 0;
                        $initStyle = $initCount > 0 ? '' : 'style="display:none;"';
                    ?>
                    <span class="cart-count" <?= $initStyle ?>><?php echo $initCount; ?></span>
                </a>

                <div class="cart-popup">
                    <div class="cart-header">
                        <h3>Shopping Cart</h3>
                        <span class="cart-count"><?php echo isset($_SESSION['cart_count']) ? $_SESSION['cart_count'] : '0'; ?> items</span>
                    </div>
                    <div class="cart-items">
                        <p class="empty-cart-message">Your cart is empty</p>
                    </div>
                    <div class="cart-footer">
                        <div class="cart-buttons">
                            <a href="MyCart.php" class="checkout-btn">View Cart</a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="icon notification-icon">
                <a href="#" class="fas fa-bell">
                    <span class="notification-count" style="display: none;">0</span>
                </a>

                <div class="notification-popup">
                    <div class="notification-header">
                        <h3>Notifications</h3>
                        <span class="notification-count">0</span>
                    </div>
                    <div class="notification-items">

                    </div>
                </div>
            </div>

            <?php
            $showMailbox = false;
            if (isset($_SESSION['user_id'])) {
                $role = isset($_SESSION['role_category']) ? strtoupper(trim($_SESSION['role_category'])) : '';
                $program = '';
                if (isset($_SESSION['program_abbreviation']) && $_SESSION['program_abbreviation'] !== '') {
                    $program = strtoupper(trim($_SESSION['program_abbreviation']));
                } elseif (isset($_SESSION['program_or_position'])) {
                    $program = strtoupper(trim($_SESSION['program_or_position']));
                }

                $isAdminOrPamo = (strpos($program, 'ADMIN') !== false) || (strpos($program, 'PAMO') !== false);

                if ($role === 'COLLEGE STUDENT' || $role === 'SHS') {
                    $showMailbox = true;
                } elseif ($role === 'EMPLOYEE' && !$isAdminOrPamo) {
                    $showMailbox = true;
                }
            }
            if ($showMailbox) : ?>
                <div class="icon mailbox-icon" id="mailboxIcon" style="position:relative;">
                    <a href="javascript:void(0);" title="Mailbox">
                        <i class="fas fa-envelope"></i>
                        <?php if ($mailboxCount > 0): ?>
                            <span class="mailbox-count"><?= $mailboxCount ?></span>
                        <?php endif; ?>
                    </a>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['user_id'])): ?>
                <div class="icon">
                    <a href="profile.php" class="fas fa-user"></a>
                </div>
                <div class="icon">
                    <a href="login.php" class="fas fa-sign-out-alt" title="Sign Out" onclick="signOut(event)" style="color:#dc3545;"></a>
                </div>
            <?php else: ?>
                <div class="login">
                    <div class="icon">
                        <a href="login.php" class="login-button">Login</a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="login">
            <div class="icon">
                <a href="login.php" class="login-button">Log in</a>
            </div>
        </div>
    <?php endif; ?>
</nav>

<div class="mobile-drawer cart-drawer">
    <div class="drawer-header">
        <h3>Shopping Cart</h3>
        <button class="close-drawer">&times;</button>
    </div>
    <div class="drawer-content">
        <div class="cart-items">
            <p class="empty-cart-message">Your cart is empty</p>
        </div>
        <div class="drawer-footer">
            <a href="MyCart.php" class="checkout-btn">View Cart</a>
        </div>
    </div>
</div>

<div class="mobile-drawer notification-drawer">
    <div class="drawer-header">
        <h3>Notifications</h3>
        <button class="close-drawer">&times;</button>
    </div>
    <div class="drawer-content">
        <div class="notification-items">

        </div>
    </div>
</div>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
<link rel="stylesheet" href="../CSS/cart.css">
<link rel="stylesheet" href="../CSS/logout-modal.css">

<script>
    function redirectToLogin(destination) {
        window.location.href = `login.php?redirect=${encodeURIComponent(destination)}`;
    }

    function checkLoginStatus(destination) {
        <?php if (!isset($_SESSION['user_id'])): ?>
            window.location.href = `login.php?redirect=${encodeURIComponent(destination)}`;
        <?php else: ?>
            window.location.href = destination;
        <?php endif; ?>
    }

    function signOut(event) {
        event.preventDefault();
        showLogoutConfirmation();
    }

     /* window.onload = function () {
        const welcomeMessage = document.getElementById('welcomeMessage');
        if (welcomeMessage) {
            setTimeout(() => {
                welcomeMessage.style.transition = "opacity 1s ease-out";
                welcomeMessage.style.opacity = 0;
            }, 5000);
        }
    };
    */

    // Header functionality
    document.addEventListener('DOMContentLoaded', function() {
        const hamburger = document.querySelector('.hamburger');
        const navLinks = document.querySelector('.nav-links');
        const cartIcon = document.querySelector('.cart-icon');
        const cartPopup = document.querySelector('.cart-popup');
        const notificationIcon = document.querySelector('.notification-icon');
        const notificationPopup = document.querySelector('.notification-popup');

        function formatTimeAgo(timestamp) {
            const currentTime = Math.floor(Date.now() / 1000);
            const timeDifference = currentTime - timestamp;

            if (timeDifference < 60) {
                return 'Just now';
            } else if (timeDifference < 3600) {
                const minutes = Math.floor(timeDifference / 60);
                return `${minutes}m ago`;
            } else if (timeDifference < 86400) {
                const hours = Math.floor(timeDifference / 3600);
                return `${hours}h ago`;
            } else if (timeDifference < 604800) {
                const days = Math.floor(timeDifference / 86400);
                return `${days}d ago`;
            } else {
                return new Date(timestamp * 1000).toLocaleDateString('en-US', {
                    month: 'short',
                    day: 'numeric'
                });
            }
        }

        function getNotificationTypeDisplay(type) {
            const typeMap = {
                'approved': 'Order Approved',
                'rejected': 'Order Rejected',
                'processing': 'Order Processing',
                'ready': 'Order Ready',
                'completed': 'Order Completed',
                'cancelled': 'Order Cancelled',
                // Database ENUM values
                'Restock Item': 'Restock',
                'Restock': 'Restock',
                'Add Item Size': 'Add Item Size',
                'New Item': 'New Item',
                // Preorder notification types
                'preorder_available': 'New Pre-Order',
                'preorder_delivered': 'Pre-Order Ready',
                'item_available': 'Item Available',
                // Legacy mappings for compatibility
                'new_item': 'New Item',
                'low_stock': 'Low Stock Alert',
                'inventory_update': 'Inventory Updated',
                'system': 'System Notification',
                'announcement': 'Announcement',
                'reminder': 'Reminder'
            };
            return typeMap[type] || type;
        }

        function getNotificationIcon(type) {
            const iconMap = {
                'approved': 'check_circle',
                'rejected': 'cancel',
                'processing': 'hourglass_empty',
                'ready': 'shopping_bag',
                'completed': 'done_all',
                'cancelled': 'block',
                // Database ENUM values
                'Restock Item': 'inventory',
                'Restock': 'inventory',
                'Add Item Size': 'straighten',
                'New Item': 'add_shopping_cart',
                // Preorder notification types
                'preorder_available': 'shopping_cart',
                'preorder_delivered': 'local_shipping',
                'item_available': 'inventory_2',
                // Legacy mappings for compatibility
                'new_item': 'add_shopping_cart',
                'low_stock': 'warning',
                'inventory_update': 'inventory',
                'system': 'settings',
                'announcement': 'campaign',
                'reminder': 'schedule'
            };
            return iconMap[type] || 'notifications';
        }        function updateNotificationTimes() {
            const notificationItems = document.querySelectorAll('.notification-item');
            notificationItems.forEach(item => {
                const timestamp = parseInt(item.dataset.timestamp);
                const timeElement = item.querySelector('.notification-time');
                if (timestamp && timeElement) {
                    timeElement.textContent = formatTimeAgo(timestamp);
                }
            });
        }

        async function updateNotificationContent() {
            try {
                const response = await fetch('../Includes/notification_operations.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=get_notifications'
                });

                const data = await response.json();
                
                if (data.success) {
                    const notificationItems = document.querySelectorAll('.notification-items');
                    const notificationCount = document.querySelectorAll('.notification-count');
                    
                    notificationItems.forEach(container => {
                        if (data.notifications && data.notifications.length > 0) {
                            container.innerHTML = data.notifications.map(notification => {
                                const timestamp = Math.floor(new Date(notification.created_at).getTime() / 1000);
                                const notificationType = getNotificationTypeDisplay(notification.type);
                                const notificationIcon = getNotificationIcon(notification.type);
                                const cssClass = notification.type.replace(/\s+/g, '_').toLowerCase();
                                return `
                                    <div class="notification-item ${notification.is_read ? '' : 'unread'}" 
                                         onclick="markNotificationAsRead(${notification.id})"
                                         data-timestamp="${timestamp}">
                                        <div class="notification-avatar">
                                            <div class="notification-icon-wrapper ${cssClass}">
                                                <i class="material-icons">${notificationIcon}</i>
                                            </div>
                                        </div>
                                        <div class="notification-content">
                                            <div class="notification-header-info">
                                                <span class="notification-type">${notificationType}</span>
                                                <span class="notification-timestamp">${formatTimeAgo(timestamp)}</span>
                                            </div>
                                            <div class="notification-message"></div>
                                            ${!notification.is_read ? '<div class="notification-indicator"></div>' : ''}
                                        </div>
                                    </div>
                                `;
                            }).join('');
                            // Set innerHTML for notification-message after rendering
                            container.querySelectorAll('.notification-item').forEach((item, idx) => {
                                const notification = data.notifications[idx];
                                item.querySelector('.notification-message').innerHTML = notification.message;
                            });
                        } else {
                            container.innerHTML = '<p class="empty-notification-message">No notifications</p>';
                        }
                    });

                    // Update notification count
                    notificationCount.forEach(count => {
                        count.textContent = data.count;
                        count.style.display = data.count > 0 ? 'block' : 'none';
                    });
                }
            } catch (error) {
                console.error('Error:', error);
            }
        }

        // Function to mark notification as read
        window.markNotificationAsRead = async function(notificationId) {
            try {
                const response = await fetch('../Includes/notification_operations.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=mark_as_read&notification_id=${notificationId}`
                });

                const data = await response.json();
                if (data.success) {
                    updateNotificationContent();
                }
            } catch (error) {
                console.error('Error:', error);
            }
        };

        // Function to clear all notifications
        async function clearAllNotifications() {
            try {
                const response = await fetch('../Includes/notification_operations.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=clear_all'
                });

                const data = await response.json();
                if (data.success) {
                    updateNotificationContent();
                } else {
                    showNotification('Failed to clear notifications: ' + data.message, 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                showNotification('Failed to clear notifications', 'error');
            }
        }

        // Hamburger menu functionality
        if (hamburger && navLinks) {
            hamburger.addEventListener('click', () => {
                hamburger.classList.toggle('active');
                navLinks.classList.toggle('active');
            });

            // Close menu when clicking a link
            document.querySelectorAll('.nav-links a').forEach(n => n.addEventListener('click', () => {
                hamburger.classList.remove('active');
                navLinks.classList.remove('active');
            }));

            // Close menu when clicking outside
            document.addEventListener('click', (e) => {
                if (!hamburger.contains(e.target) && !navLinks.contains(e.target)) {
                    hamburger.classList.remove('active');
                    navLinks.classList.remove('active');
                }
            });
        }

        function onCartIconTap(e){
                e.preventDefault();
                e.stopPropagation();
                if (window.innerWidth <= 768) {
                if (typeof openDrawer === 'function') openDrawer(cartDrawer);
                    updateCartContent();
                } else {
                if (cartPopup) {
                    cartPopup.classList.toggle('show');
                    if (cartPopup.classList.contains('show')) updateCartContent();
                    }
                }
            if (notificationPopup) notificationPopup.classList.remove('show');
                }
        if (cartIcon) {
            cartIcon.addEventListener('click', onCartIconTap, { passive: false });
            cartIcon.addEventListener('touchstart', onCartIconTap, { passive: false });
        }

        if (notificationIcon) {
            notificationIcon.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                if (window.innerWidth <= 768) {
                    openDrawer(notificationDrawer);
                    updateNotificationContent();
                } else {
                    notificationPopup.classList.toggle('show');
                    if (notificationPopup.classList.contains('show')) {
                        updateNotificationContent();
                    }
                }
                
                if (cartPopup) {
                    cartPopup.classList.remove('show');
                }
            });
        }

        const notificationHeader = document.querySelector('.notification-header');
        if (notificationHeader) {
            const clearButton = document.createElement('button');
            clearButton.className = 'clear-notifications';
            clearButton.textContent = 'Clear All';
            clearButton.onclick = clearAllNotifications;
            notificationHeader.appendChild(clearButton);
        }

        document.addEventListener('click', function(e) {
            if (cartPopup && !cartIcon.contains(e.target) && !cartPopup.contains(e.target)) {
                cartPopup.classList.remove('show');
            }
            if (notificationPopup && !notificationIcon.contains(e.target) && !notificationPopup.contains(e.target)) {
                notificationPopup.classList.remove('show');
            }
        });

        if (cartPopup) {
            cartPopup.addEventListener('click', function(e) {
                e.stopPropagation();
            });
        }

        if (notificationPopup) {
            notificationPopup.addEventListener('click', function(e) {
                e.stopPropagation();
            });
        }

        // Initial updates
        if (notificationIcon) updateNotificationContent();

        // Refresh notifications every 30 seconds
        setInterval(updateNotificationContent, 30000);
        
        // Update notification times every minute
        setInterval(updateNotificationTimes, 60000);

        const cartDrawer = document.querySelector('.cart-drawer');
        const notificationDrawer = document.querySelector('.notification-drawer');
        const closeButtons = document.querySelectorAll('.close-drawer');
        
        // Create overlay element
        const overlay = document.createElement('div');
        overlay.className = 'drawer-overlay';
        document.body.appendChild(overlay);

        function openDrawer(drawer) {
            drawer.classList.add('active');
            overlay.classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeDrawer(drawer) {
            drawer.classList.remove('active');
            overlay.classList.remove('active');
            document.body.style.overflow = '';
        }

        closeButtons.forEach(button => {
            button.addEventListener('click', function() {
                const drawer = this.closest('.mobile-drawer');
                closeDrawer(drawer);
            });
        });

        overlay.addEventListener('click', function() {
            const activeDrawer = document.querySelector('.mobile-drawer.active');
            if (activeDrawer) {
                closeDrawer(activeDrawer);
            }
        });

        window.addEventListener('resize', function() {
            if (window.innerWidth > 768) {
                closeDrawer(cartDrawer);
                closeDrawer(notificationDrawer);
            }
        });
    });

    // Function to update cart content
    async function updateCartContent() {
        try {
            const response = await fetch('../Includes/cart_operations.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=get_cart'
            });

            const data = await response.json();
            
            if (data.success) {
                const cartItems = document.querySelectorAll('.cart-items');
                const cartCount = document.querySelectorAll('.cart-count');
                
                cartItems.forEach(container => {
                    if (data.cart_items && data.cart_items.length > 0) {
                        container.innerHTML = data.cart_items.map(item => `
                            <div class="cart-item">
                                <img src="${item.image_path}" alt="${item.item_name}">
                                <div class="cart-item-details">
                                    <p class="cart-item-name">${item.item_name}</p>
                                    <p class="cart-item-price">₱${item.price} x ${item.quantity}</p>
                                </div>
                            </div>
                        `).join('');
                    } else {
                        container.innerHTML = '<p class="empty-cart-message">Your cart is empty</p>';
                    }
                });

                // Update cart count
                cartCount.forEach(count => {
                    count.textContent = data.cart_count;
                    count.style.display = data.cart_count > 0 ? 'block' : 'none';
                });
            }
        } catch (error) {
            console.error('Error:', error);
        }
    }

    // Update cart content when page loads
    document.addEventListener('DOMContentLoaded', function() {
        updateCartContent();

        function toggleCart(e) {
            if (e) { e.preventDefault(); e.stopPropagation(); }
            const cartIconEl = document.querySelector('.cart-icon');
            if (!cartIconEl) return;
            const cartPopupEl = document.querySelector('.cart-popup');
            const notificationPopupEl = document.querySelector('.notification-popup');
            const cartDrawerEl = document.querySelector('.cart-drawer');
            if (window.innerWidth <= 768) {
                if (typeof openDrawer === 'function' && cartDrawerEl) openDrawer(cartDrawerEl);
                updateCartContent();
            } else if (cartPopupEl) {
                cartPopupEl.classList.toggle('show');
                if (cartPopupEl.classList.contains('show')) {
                    updateCartContent();
                }
            }
            if (notificationPopupEl) {
                notificationPopupEl.classList.remove('show');
            }
        }

        // Bind on the container and on the inner anchor to be safe
        const cartIconEl = document.querySelector('.cart-icon');
        const cartAnchorEl = document.querySelector('.cart-icon a');
        if (cartIconEl) cartIconEl.addEventListener('click', toggleCart);
        if (cartAnchorEl) cartAnchorEl.addEventListener('click', toggleCart);

        // Global delegated safety (covers dynamically replaced nodes)
        document.addEventListener('click', function(e){
            const t = e.target;
            if (!t) return;
            if (t.closest && t.closest('.cart-icon')) {
                toggleCart(e);
            }
        });
    });
</script>

<!-- Include cart functionality -->
<script src="../Javascript/cart.js"></script>
<!-- Include logout modal functionality -->
<script src="../Javascript/logout-modal.js"></script>

<!-- Mailbox Modal -->
<div id="mailboxModal" style="display:none;">
    <div class="mailbox-content">
        <span id="closeMailboxModal">&times;</span>
        <h2>Mailbox</h2>
        <div id="mailboxMessages"></div>
    </div>
</div>
<style>
#mailboxModal {
    display: none;
    position: fixed;
    top: 0; left: 0;
    width: 100vw; height: 100vh;
    background: rgba(0,0,0,0.35);
    z-index: 9999;
    align-items: center;
    justify-content: center;
}
#mailboxModal .mailbox-content {
    background: #fff;
    border-radius: 18px;
    padding: 32px 32px 24px 32px;
    min-width: 340px;
    max-width: 600px;
    width: 90vw;
    max-height: 80vh;
    box-shadow: 0 8px 32px rgba(0,0,0,0.18);
    position: relative;
    display: flex;
    flex-direction: column;
}
#mailboxModal h2 {
    margin-top: 0;
    margin-bottom: 18px;
    font-size: 2rem;
    font-weight: 700;
    color: #0072bc;
    letter-spacing: 1px;
}
#closeMailboxModal {
    position: absolute;
    top: 18px;
    right: 28px;
    font-size: 2rem;
    color: #888;
    cursor: pointer;
    transition: color 0.2s;
}
#closeMailboxModal:hover {
    color: #d32f2f;
}
#mailboxMessages {
    overflow-y: auto;
    flex: 1;
    padding-right: 4px;
}
.mailbox-message-card {
    background: #f4f8ff;
    border-radius: 14px;
    margin-bottom: 22px;
    padding: 18px 22px 14px 18px;
    box-shadow: 0 2px 12px rgba(0,114,188,0.07);
    border-left: 5px solid #0072bc;
    transition: box-shadow 0.2s;
    position: relative;
}
.mailbox-message-card:last-child {
    margin-bottom: 0;
}
.mailbox-reply-label {
    color: #0072bc;
    font-weight: 700;
    font-size: 1.08em;
    margin-bottom: 4px;
}
.mailbox-reply-text {
    font-size: 1.08em;
    color: #222;
    margin-bottom: 10px;
    margin-top: 2px;
}
.mailbox-meta {
    display: flex;
    flex-direction: column;
    gap: 2px;
    font-size: 0.98em;
    color: #888;
}
.mailbox-date {
    margin-bottom: 2px;
    display: flex;
    align-items: center;
    gap: 6px;
}
.mailbox-date i, .mailbox-question i {
    color: #0072bc;
    font-size: 1em;
}
.mailbox-question {
    font-style: italic;
    color: #555;
    display: flex;
    align-items: center;
    gap: 6px;
}
@media (max-width: 600px) {
    #mailboxModal .mailbox-content {
        padding: 18px 6vw 12px 6vw;
        min-width: unset;
        max-width: 98vw;
    }
    #closeMailboxModal {
        right: 12px;
        top: 10px;
    }
    .mailbox-message-card {
        padding: 12px 4vw 10px 4vw;
    }
}
.mailbox-count {
    position: absolute;
    top: -5px;
    right: -12px;
    background-color: #ff4444;
    color: white;
    font-size: 1rem;
    font-weight: 500;
    padding: 2px;
    border-radius: 8px;
    min-width: 15px;
    height: 15px;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 1px solid white;
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
}
</style>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var mailboxIcon = document.getElementById('mailboxIcon');
    var mailboxModal = document.getElementById('mailboxModal');
    var closeMailboxModal = document.getElementById('closeMailboxModal');
    if (mailboxIcon) {
        mailboxIcon.addEventListener('click', function() {
            mailboxModal.style.display = 'flex';
            fetch('../Includes/fetch_replies.php')
                .then(res => res.json())
                .then(data => {
                    var box = document.getElementById('mailboxMessages');
                    box.innerHTML = '';
                    var messages = data.messages || [];
                    if (messages.length === 0) {
                        box.innerHTML = '<p style="color:#888;">No replies yet.</p>';
                    } else {
                        messages.forEach(function(msg) {
                            var div = document.createElement('div');
                            div.className = 'mailbox-message-card';
                            div.innerHTML =
                                '<div class="mailbox-reply-label">PAMO Reply:</div>' +
                                '<div class="mailbox-reply-text">' + msg.reply + '</div>' +
                                '<div class="mailbox-meta">' +
                                    '<span class="mailbox-date"><i class="fas fa-paper-plane"></i> Question sent: ' + msg.submitted_at + '</span>' +
                                    '<span class="mailbox-date"><i class="fas fa-reply"></i> PAMO replied: ' + msg.replied_at + '</span>' +
                                    '<span class="mailbox-question"><i class="fas fa-question-circle"></i> ' + msg.question + '</span>' +
                                '</div>';
                            box.appendChild(div);
                        });
                    }

                    if (data.unread_ids && data.unread_ids.length > 0) {
                        fetch('../Includes/mark_mailbox_read.php', { method: 'POST' })
                            .then(() => {
                                var badge = document.querySelector('.mailbox-count');
                                if (badge) badge.remove();
                            });
                    }
                });
        });
    }
    if (closeMailboxModal) {
        closeMailboxModal.onclick = function() {
            mailboxModal.style.display = 'none';
        };
    }
});
</script>

<style>
    .navbar {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        display: flex;
        justify-content: space-between;
        align-items: center;
        background-color: rgba(255, 255, 255, 0.8);
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
        padding: 0.5rem 0.5rem 0.5rem 0;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        z-index: 1000;
        border-bottom: 1px solid rgba(255, 255, 255, 0.3);
    }

    .logo-container {
        display: flex;
        align-items: center;
        width: auto;
        padding-left: 30px;
    }

    .logo {
        margin-right: 10px;
    }

    .hamburger {
        margin-right: 0;
        margin-left: 5px;
    }

    .nav-links {
    left: 5%;
    display: flex;
    list-style: none;
    margin: 0;
    padding: 0;
    position: relative;
    justify-content: flex-start;
    white-space: nowrap;
    overflow-x: auto;
    flex-wrap: nowrap !important;
}


    .nav-links li {
        margin: 0 10px;
    }

    .nav-links a {
        color: var(--primary-color);
        text-decoration: none;
        font-weight: 100;
        padding: 0.5rem 1rem;
        font-family: var(--primary-font-family);
        font-size: 16px;
        transition: .3s ease-out;
        letter-spacing: 0.3px;
        white-space: nowrap;
    }

    .nav-links > li:hover {
        transform: scale(1.1);
    }

    .icons {
        display: flex;
        gap: 1rem;
        align-items: center;
        padding-right: 30px;
        margin-left: auto;
    }

    .icon {
        position: relative;
        padding: 6px;
        border-radius: 50%;
        transition: all 0.3s ease;
    }

    .icon a {
        color: var(--primary-color);
        font-size: 1.2rem; /* Increased icon size */
        display: flex;
        align-items: center;
        justify-content: center;
        width: 36px; /* Increased width */
        height: 36px; /* Increased height */
        text-decoration: none;
        transition: all 0.3s ease;
        white-space: nowrap;
    }

    /* Hover effects for icons */
    .icon:hover {
        transform: translateY(-7px);
    }

    /* Add hover effect for glassy look */
    .nav-links a:hover {
        color: darkblue;
    }

    /* Cart count badge */
    .cart-count {
        position: absolute;
        top: -5px;
        right: -12px;
        background-color: #ff4444;
        color: white;
        font-size: 0.65rem;
        font-weight: 500;
        padding: 2px;
        border-radius: 8px;
        min-width: 15px;
        height: 15px;
        display: flex;
        align-items: center;
        justify-content: center;
        border: 1px solid white;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
    }

    /* Active state for icons */
    .icon.active {
        background: var(--primary-color);
    }

    .icon.active a {
        color: black;
    }

    /* Mobile responsive design */
    @media screen and (max-width: 1250px) {
        .navbar {
            padding: 0.5rem;
        }
        .logo-container {
            width: auto;
            padding-left: 10px;
        }
        .nav-links {
            display: none;
            position: absolute;
            top: 70px;
            left: 0;
            width: 100%;
            transform: none;
            flex-direction: column;
            background-color: white;
            padding: 1rem 0;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
            z-index: 1000;
            margin-right: 0;
        }
        .nav-links.active {
            display: flex;
        }
        .nav-links li {
            margin: 0.5rem 0;
            text-align: center;
        }
        .icons {
            padding-right: 10px;
            gap: 0.5rem;
            margin-left: 0;
        }
        .icon {
            padding: 4px;
        }
        .icon a {
            width: 32px;
            height: 32px;
            font-size: 1rem;
        }
        .cart-count, .notification-count {
            top: -3px;
            right: -8px;
            font-size: 0.6rem;
            min-width: 12px;
            height: 12px;
            padding: 1px;
        }
        .login-button {
            padding: 8px 15px;
            font-size: 0.9rem;
        }
        .icons {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            flex-wrap: nowrap;
            min-width: fit-content;
        }
        .hamburger {
            display: block !important;
            z-index: 1001;
        }
        .hamburger.active .bar:nth-child(2) {
            opacity: 0;
        }
        .hamburger.active .bar:nth-child(1) {
            transform: translateY(8px) rotate(45deg);
        }
        .hamburger.active .bar:nth-child(3) {
            transform: translateY(-8px) rotate(-45deg);
        }
    }

    /* Additional breakpoint for very small screens */
    @media screen and (max-width: 375px) {
        .icons {
            gap: 0.3rem;
        }

        .icon {
            padding: 3px;
        }

        .icon a {
            width: 28px;
            height: 28px;
            font-size: 0.9rem;
        }

        .login-button {
            padding: 6px 12px;
            font-size: 0.85rem;
        }

        .logo-container {
            padding-left: 5px;
        }

        .hamburger {
            margin-right: 10px;
        }

        .cart-count, .notification-count {
            top: -2px;
            right: -6px;
            font-size: 0.55rem;
            min-width: 10px;
            height: 10px;
        }
    }

    /* Animation for icon interactions */
    @keyframes iconPulse {
        0% {
            transform: scale(1);
        }

        50% {
            transform: scale(1.1);
        }

        100% {
            transform: scale(1);
        }
    }

    .icon:active {
        animation: iconPulse 0.3s ease;
    }

    .hamburger {
        display: none;
        cursor: pointer;
        margin-right: 15px;
    }

    .bar {
        width: 25px;
        height: 3px;
        margin: 5px;
        background-color: #333;
        display: block;
        transition: 0.3s ease;
    }

    /* Animation for menu items */
    @keyframes slideIn {
        from {
            opacity: 0;
            transform: translateX(-20px);
        }

        to {
            opacity: 1;
            transform: translateX(0);
        }
    }

    .nav-links.active li {
        animation: slideIn 0.3s ease forwards;
    }

    /* Stagger animation delay for menu items */
    .nav-links.active li:nth-child(1) {
        animation-delay: 0.1s;
    }

    .nav-links.active li:nth-child(2) {
        animation-delay: 0.2s;
    }

    .nav-links.active li:nth-child(3) {
        animation-delay: 0.3s;
    }

    .nav-links.active li:nth-child(4) {
        animation-delay: 0.4s;
    }

    /* Cart Icon and Popup */
    .cart-icon {
        position: relative;
        cursor: pointer;
    }

    .cart-popup {
        position: absolute;
        top: 100%;
        right: 0;
        width: 320px;
        background: white;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        padding: 1rem;
        display: none;
        z-index: 1001;
        margin-top: 10px;
    }

    .cart-popup.show {
        display: block;
    }

    .checkout-btn {
        display: block;
        width: calc(100% - 2rem);
        padding: 0.8rem;
        background-color: var(--primary-color);
        color: white !important;
        text-align: center;
        border-radius: 4px;
        text-decoration: none;
        font-weight: 500;
        font-size: 1rem;
        transition: background-color 0.3s ease;
    }

    .checkout-btn:hover {
        background-color: #00008B;
        color: white !important;
        text-decoration: none;
    }

    /* Add active state for cart icon */
    .cart-icon.active {
        background: var(--primary-color);
    }

    .cart-icon.active a {
        color: black;
    }

    .cart-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding-bottom: 1rem;
        border-bottom: 1px solid #eee;
    }

    .cart-header h3 {
        font-size: 1.2rem;
        margin: 0;
        color: var(--primary-color);
    }

    .empty-cart-message {
        text-align: center;
        color: #666;
        padding: 1rem 0;
    }

    .cart-item {
        display: flex;
        align-items: center;
        padding: 0.5rem 0;
        border-bottom: 1px solid #eee;
    }

    .cart-item img {
        width: 50px;
        height: 50px;
        object-fit: cover;
        margin-right: 1rem;
    }

    .cart-item-details {
        flex: 1;
    }

    .cart-item-name {
        font-weight: 500;
        color: var(--primary-color);
    }

    .cart-item-price {
        color: #666;
        font-size: 0.9rem;
    }

    .cart-footer {
        padding-top: 1rem;
        border-top: 1px solid #eee;
    }

    .cart-buttons {
        display: flex;
        gap: 10px;
    }

    .view-cart-btn, .checkout-btn {
        flex: 1;
        padding: 0.8rem;
        text-align: center;
        border-radius: 4px;
        text-decoration: none;
        transition: background-color 0.3s ease;
    }

    .view-cart-btn {
        background-color: #f8f9fa;
        color: var(--primary-color);
        border: 1px solid var(--primary-color);
    }

    .checkout-btn {
        display: block;
        width: calc(100% - 2rem); /* Adjust width to account for padding */
        padding: 0.8rem;
        background-color: var(--primary-color);
        color: white;
        text-align: center;
        border-radius: 4px;
        text-decoration: none;
        transition: background-color 0.3s ease;
    }

    .checkout-btn:hover {
        background-color: #00008B; /* Dark blue color */
        color: white;
    }

    .view-cart-btn:hover {
        background-color: #e9ecef;
    }

    /* Responsive adjustments */
    @media screen and (max-width: 768px) {
        .cart-popup {
            position: fixed;
            top: auto;
            bottom: 80px;
            /* Adjust based on your bottom icons bar height */
            right: 20px;
            width: calc(100% - 40px);
            max-width: 320px;
        }
    }

    /* Animation */
    @keyframes slideIn {
        from {
            opacity: 0;
            transform: translateY(10px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .cart-popup {
        animation: slideIn 0.3s ease;
    }

    /* Notification Icon and Popup */
    .notification-icon {
        position: relative;
        cursor: pointer;
    }

    .notification-popup {
        position: absolute;
        top: 100%;
        right: 0;
        width: 320px;
        background: white;
        border-radius: 12px;
        box-shadow: 0 8px 24px rgba(0,0,0,0.15);
        padding: 1.5rem;
        display: none;
        z-index: 1001;
        margin-top: 15px;
        border: 1px solid #e9ecef;
        max-width: calc(100vw - 40px);
        box-sizing: border-box;
    }

    .notification-popup.show {
        display: block;
        animation: slideIn 0.3s ease;
    }

    .notification-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding-bottom: 1rem;
        margin-bottom: 1rem;
        border-bottom: 2px solid #f0f2f5;
        width: 100%;
    }

    .notification-header h3 {
        font-size: 1.2rem;
        margin: 0;
        color: var(--primary-color);
        font-weight: 600;
    }

    .clear-notifications {
        background: none;
        border: none;
        color: #dc3545;
        font-size: 0.9rem;
        cursor: pointer;
        padding: 5px 10px;
        border-radius: 4px;
        transition: all 0.3s ease;
    }

    .clear-notifications:hover {
        background-color: #fff5f5;
    }

    .clear-notifications:disabled {
        color: #6c757d;
        cursor: not-allowed;
    }

    .notification-items {
        max-height: 400px;
        overflow-y: auto;
        padding: 0.5rem 0;
        width: 100%;
    }

    .cart-items::-webkit-scrollbar, .notification-items::-webkit-scrollbar {
        width: 6px;
    }

    .cart-items::-webkit-scrollbar-track, .notification-items::-webkit-scrollbar-track {
        background: #f1f1f1;
        border-radius: 3px;
    }

    .cart-items::-webkit-scrollbar-thumb, .notification-items::-webkit-scrollbar-thumb {
        background: #c1c1c1;
        border-radius: 3px;
    }

    .cart-items::-webkit-scrollbar-thumb:hover, .notification-items::-webkit-scrollbar-thumb:hover {
        background: #a8a8a8;
    }

    .notification-item {
        padding: 1rem;
        border-bottom: 1px solid #f0f2f5;
        cursor: pointer;
        transition: all 0.2s ease;
        border-radius: 8px;
        margin-bottom: 0.8rem;
        width: 100%;
        box-sizing: border-box;
        position: relative;
        background-color: #ffffff;
    }

    .notification-item:last-child {
        border-bottom: none;
        margin-bottom: 0;
    }

    .notification-item:hover {
        background-color: #f8f9fa;
        transform: translateX(5px);
    }

    .notification-item.unread {
        background-color: #e3f2fd;
        border: 2px solid #1976d2;
        border-radius: 12px;
        box-shadow: 0 4px 12px rgba(25, 118, 210, 0.1);
        margin-left: -10px;
        margin-right: 5px;
        padding-left: 15px;
    }

    .notification-item.unread::before {
        content: 'NEW';
        position: absolute;
        top: -10px;
        right: -10px;
        background-color: #ff4444;
        color: white;
        padding: 4px 8px;
        border-radius: 12px;
        font-size: 0.7rem;
        font-weight: bold;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
        animation: bounce 2s infinite;
    }

    @keyframes bounce {
        0%, 100% {
            transform: translateY(0);
        }
        50% {
            transform: translateY(-3px);
        }
    }

    .notification-item.unread .notification-message {
        color: #1a1a1a;
        font-weight: 700;
        font-size: 1rem;
    }

    .notification-item.unread .notification-time {
        color: #1976d2;
        font-weight: 600;
    }

    .notification-message {
        margin: 0 0 0.5rem 0;
        color: #2c3e50;
        font-size: 0.95rem;
        line-height: 1.5;
        word-wrap: break-word;
        overflow-wrap: break-word;
    }

    .notification-time {
        color: #6c757d;
        font-size: 0.85rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .notification-status {
        display: inline-block;
        padding: 0.25rem 0.75rem;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 500;
        margin-top: 0.5rem;
    }

    .notification-item.unread .notification-status {
        font-weight: 600;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    .notification-status.completed {
        background-color: #e8f5e9;
        color: #2e7d32;
    }

    .notification-status.approved {
        background-color: #e3f2fd;
        color: #1565c0;
    }

    .notification-status.processing {
        background-color: #fff3e0;
        color: #ef6c00;
    }

    .notification-status.rejected {
        background-color: #ffebee;
        color: #c62828;
    }

    .empty-notification-message {
        text-align: center;
        color: #6c757d;
        padding: 2rem 0;
        font-size: 0.95rem;
        background-color: #f8f9fa;
        border-radius: 8px;
        margin: 1rem 0;
    }

    .notification-count {
        position: absolute;
        top: -5px;
        right: -12px;
        background-color: #ff4444;
        color: white;
        font-size: 0.65rem;
        font-weight: 500;
        padding: 2px;
        border-radius: 8px;
        min-width: 15px;
        height: 15px;
        display: flex;
        align-items: center;
        justify-content: center;
        border: 1px solid white;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
    }

    @media screen and (max-width: 768px) {
        .notification-popup {
            position: fixed;
            top: auto;
            bottom: 70px;
            right: 10px;
            width: calc(100vw - 20px);
            max-width: 320px;
            margin: 0;
        }
    }

    .logo-image {
        width: 50px;
        height: 50px;
    }

    .nav-links a.active {
        color: #092d45;
        text-decoration: none;
    }

    .view-cart-btn {
        display: block;
        width: 100%;
        padding: 0.8rem;
        background-color: var(--primary-color);
        color: white;
        text-align: center;
        border-radius: 4px;
        text-decoration: none;
        transition: background-color 0.3s ease;
        margin-top: 1rem;
    }

    .view-cart-btn:hover {
        background-color: #005a94;
    }

    /* Notification item styles */
    .notification-item {
        padding: 12px;
        border-bottom: 1px solid #eee;
        cursor: pointer;
        transition: background-color 0.3s;
    }

    .notification-item:hover {
        background-color: #f5f5f5;
    }

    .notification-item.approved {
        border-left: 4px solid #28a745;
    }

    .notification-item.rejected {
        border-left: 4px solid #dc3545;
    }

    .notification-item.read {
        opacity: 0.7;
        background-color: #f8f9fa;
    }

    .notification-order {
        margin: 4px 0;
        color: #666;
        font-size: 0.85rem;
    }

    .notification-time {
        display: block;
        font-size: 0.8rem;
        color: #888;
        margin-top: 5px;
    }

    /* Animation for new notifications */
    @keyframes notificationPulse {
        0% { transform: scale(1); }
        50% { transform: scale(1.1); }
        100% { transform: scale(1); }
    }

    .notification-icon .notification-count:not(:empty) {
        animation: notificationPulse 1s infinite;
    }

    /* Shared Popup Styles */
    .cart-popup, .notification-popup {
        position: absolute;
        top: 100%;
        right: 0;
        width: 320px;
        background: white;
        border-radius: 12px;
        box-shadow: 0 8px 24px rgba(0,0,0,0.15);
        padding: 1.5rem;
        display: none;
        z-index: 1001;
        margin-top: 15px;
        border: 1px solid #e9ecef;
        max-width: calc(100vw - 40px);
        box-sizing: border-box;
    }

    .cart-popup.show, .notification-popup.show {
        display: block;
        animation: slideIn 0.3s ease;
    }

    /* Header Styles */
    .cart-header, .notification-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding-bottom: 1rem;
        margin-bottom: 1rem;
        border-bottom: 2px solid #f0f2f5;
        width: 100%;
    }

    .cart-header h3, .notification-header h3 {
        font-size: 1.2rem;
        margin: 0;
        color: var(--primary-color);
        font-weight: 600;
    }

    /* Items Container */
    .cart-items, .notification-items {
        max-height: 400px;
        overflow-y: auto;
        padding: 0.5rem 0;
        width: 100%;
    }

    .cart-items::-webkit-scrollbar, .notification-items::-webkit-scrollbar {
        width: 6px;
    }

    .cart-items::-webkit-scrollbar-track, .notification-items::-webkit-scrollbar-track {
        background: #f1f1f1;
        border-radius: 3px;
    }

    .cart-items::-webkit-scrollbar-thumb, .notification-items::-webkit-scrollbar-thumb {
        background: #c1c1c1;
        border-radius: 3px;
    }

    .cart-items::-webkit-scrollbar-thumb:hover, .notification-items::-webkit-scrollbar-thumb:hover {
        background: #a8a8a8;
    }

    /* New Notification Item Styles - Based on Reference Design */
    .notification-item {
        display: flex;
        align-items: flex-start;
        padding: 12px 16px;
        border-bottom: 1px solid #f0f0f0;
        cursor: pointer;
        transition: all 0.2s ease;
        position: relative;
        background-color: #ffffff;
        border-radius: 0;
        margin-bottom: 0;
    }

    .notification-item:last-child {
        border-bottom: none;
    }

    .notification-item:hover {
        background-color: #f8f9fa;
    }

    .notification-item.unread {
        background-color: #f8f9fa;
    }

    .notification-avatar {
        margin-right: 12px;
        flex-shrink: 0;
    }

    .notification-icon-wrapper {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        position: relative;
    }

    .notification-icon-wrapper i {
        font-size: 20px;
        color: white;
    }

    /* Icon colors based on notification type */
    .notification-icon-wrapper.approved {
        background: linear-gradient(135deg, #4CAF50, #45a049);
    }

    .notification-icon-wrapper.rejected {
        background: linear-gradient(135deg, #f44336, #da190b);
    }

    .notification-icon-wrapper.processing {
        background: linear-gradient(135deg, #ff9800, #f57c00);
    }

    .notification-icon-wrapper.ready {
        background: linear-gradient(135deg, #2196F3, #1976d2);
    }

    .notification-icon-wrapper.completed {
        background: linear-gradient(135deg, #9C27B0, #7b1fa2);
    }

    .notification-icon-wrapper.cancelled {
        background: linear-gradient(135deg, #757575, #424242);
    }

    .notification-icon-wrapper.new_item {
        background: linear-gradient(135deg, #00BCD4, #0097a7);
    }

    .notification-icon-wrapper.low_stock {
        background: linear-gradient(135deg, #FFC107, #ffa000);
    }

    .notification-icon-wrapper.inventory_update {
        background: linear-gradient(135deg, #795548, #5d4037);
    }

    .notification-icon-wrapper.system {
        background: linear-gradient(135deg, #607D8B, #455a64);
    }

    .notification-icon-wrapper.announcement {
        background: linear-gradient(135deg, #E91E63, #c2185b);
    }

    .notification-icon-wrapper.reminder {
        background: linear-gradient(135deg, #3F51B5, #303f9f);
    }

    /* Database ENUM values with normalized class names */
    .notification-icon-wrapper.restock_item {
        background: linear-gradient(135deg, #795548, #5d4037);
    }

    .notification-icon-wrapper.restock {
        background: linear-gradient(135deg, #795548, #5d4037);
    }

    .notification-icon-wrapper.add_item_size {
        background: linear-gradient(135deg, #9C27B0, #7b1fa2);
    }

    .notification-icon-wrapper.new_item {
        background: linear-gradient(135deg, #00BCD4, #0097a7);
    }

    /* Preorder notification types */
    .notification-icon-wrapper.preorder_available {
        background: linear-gradient(135deg, #4CAF50, #45a049);
    }

    .notification-icon-wrapper.preorder_delivered {
        background: linear-gradient(135deg, #FF9800, #f57c00);
    }

    .notification-icon-wrapper.item_available {
        background: linear-gradient(135deg, #2196F3, #1976d2);
    }

    .notification-content {
        flex: 1;
        min-width: 0;
    }

    .notification-header-info {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 4px;
    }

    .notification-type {
        font-weight: 600;
        font-size: 14px;
        color: #1a1a1a;
    }

    .notification-timestamp {
        font-size: 12px;
        color: #8e8e93;
        font-weight: 400;
    }

    .notification-message {
        margin: 0;
        color: #3c3c43;
        font-size: 13px;
        line-height: 1.4;
        font-weight: 400;
        word-wrap: break-word;
        overflow-wrap: break-word;
    }

    .notification-indicator {
        position: absolute;
        top: 12px;
        right: 12px;
        width: 8px;
        height: 8px;
        background-color: #007AFF;
        border-radius: 50%;
        border: 2px solid white;
    }

    .notification-item.unread .notification-type {
        color: #000000;
        font-weight: 700;
    }

    .notification-item.unread .notification-message {
        color: #1a1a1a;
        font-weight: 500;
    }

    /* Empty State Messages */
    .empty-cart-message {
        text-align: center;
        color: #6c757d;
        padding: 2rem 0;
        font-size: 0.95rem;
        background-color: #f8f9fa;
        border-radius: 8px;
        margin: 1rem 0;
    }

    .empty-notification-message {
        text-align: center;
        color: #8e8e93;
        padding: 40px 20px;
        font-size: 15px;
        font-weight: 400;
    }

    /* Count Badges */
    .cart-count, .notification-count {
        position: absolute;
        top: -5px;
        right: -12px;
        background-color: #ff4444;
        color: white;
        font-size: 0.65rem;
        font-weight: 500;
        padding: 2px;
        border-radius: 8px;
        min-width: 15px;
        height: 15px;
        display: flex;
        align-items: center;
        justify-content: center;
        border: 1px solid white;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
        text-align: center;
    }

    /* Animation */
    @keyframes slideIn {
        from {
            opacity: 0;
            transform: translateY(10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    /* Responsive Design */
    @media screen and (max-width: 768px) {
        .cart-popup, .notification-popup {
            position: fixed;
            top: auto;
            bottom: 70px;
            right: 10px;
            width: calc(100vw - 20px);
            max-width: 320px;
            margin: 0;
        }
    }

    /* Login Button Styles */
    .login-button {
        display: inline-block;
        background-color: #0078d4;
        color: white !important;
        padding: 10px 25px;
        border-radius: 30px;
        font-weight: 600;
        text-decoration: none;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        box-shadow: 0 4px 15px rgba(0, 120, 212, 0.3);
        transition: all 0.3s ease;
        white-space: nowrap;
        border: none;
        line-height: 1;
    }
    
    .login-button:hover {
        background-color: darkblue;
        color: yellow !important;
        box-shadow: 0 6px 20px rgba(0, 120, 212, 0.2);
        text-decoration: none;
    }
    


    .login {
        margin-right: 20px;
    }

    .login .icon {
        padding: 0;
    }
    
    @media screen and (max-width: 768px) {
        .login-button {
            padding: 8px 20px;
            font-size: 14px;
        }
    }

    /* Mobile Drawer Styles */
    .mobile-drawer {
        position: fixed;
        top: 0;
        right: -100%;
        width: 100%;
        height: 100vh;
        background: white;
        z-index: 2000;
        transition: right 0.3s ease-in-out;
        display: none;
    }

    .mobile-drawer.active {
        right: 0;
    }

    .drawer-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 1rem;
        background: var(--primary-color);
        color: white;
        position: sticky;
        top: 0;
        z-index: 1;
    }

    .drawer-header h3 {
        margin: 0;
    font-size: 1.2rem;
    font-weight: normal;
    letter-spacing: .5px;
    }

    .close-drawer {
        background: none;
        border: none;
        color: white;
        font-size: 1.5rem;
        cursor: pointer;
        padding: 0.5rem;
    }

    .drawer-content {
        height: calc(100vh - 60px);
        overflow-y: auto;
        padding: 1rem;
    }

    .drawer-footer {
        padding: 1rem;
        border-top: 1px solid #eee;
        position: sticky;
        bottom: 0;
        background: white;
    }

    /* Update existing media queries */
    @media screen and (max-width: 768px) {
        .cart-popup, .notification-popup {
            display: none !important;
        }
        
        .mobile-drawer {
            display: block;
        }
        
        .cart-drawer.active, .notification-drawer.active {
            right: 0;
        }
        
        /* Add overlay when drawer is active */
        .drawer-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1999;
            display: none;
        }
        
        .drawer-overlay.active {
            display: block;
        }
    }

    /* Hide scrollbar for Chrome, Safari and Opera */
    .nav-links::-webkit-scrollbar {
        display: none;
    }

    /* Hide scrollbar for IE, Edge and Firefox */
    .nav-links {
        -ms-overflow-style: none;  /* IE and Edge */
        scrollbar-width: none;     /* Firefox */
    }
</style>
<style>
.rejection-reason {
    display: block;
    color: #c62828;
    background: #ffebee;
    border-left: 4px solid #dc3545;
    padding: 6px 12px;
    margin-top: 6px;
    border-radius: 4px;
    font-weight: bold;
    font-size: 0.97em;
}
</style>

<!-- Notification Modal CSS and JS -->
<link rel="stylesheet" href="../CSS/notification-modal.css">
<script src="../Javascript/notification-modal.js"></script>

<!-- Notification Modal HTML -->
<div id="notificationModal" class="notification-modal">
    <div class="notification-modal-content">
        <button class="notification-modal-close" aria-label="Close notification">
            <i class="fas fa-times"></i>
        </button>
        <div class="notification-modal-header">
            <div class="notification-modal-icon">
                <i class="fas fa-info-circle"></i>
            </div>
            <h2 class="notification-modal-title">Notification</h2>
        </div>  
        <p class="notification-modal-message">This is a notification message.</p>
        <div class="notification-modal-buttons">
            <button class="notification-btn-ok">OK</button>
        </div>
        <div class="notification-progress-bar" style="display: none;">
            <div class="notification-progress-fill"></div>
        </div>
    </div>
</div>