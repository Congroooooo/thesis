<?php 
include '../Includes/Header.php'; 
if (session_status() === PHP_SESSION_NONE) session_start();
$is_logged_in = isset($_SESSION['user_id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../CSS/global.css">
    <link rel="stylesheet" href="../CSS/ProItemList.css">
    <link rel="stylesheet" href="../CSS/PreOrder.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Anton&family=Smooch+Sans:wght@100..900&display=swap" rel="stylesheet">
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <title>Pre-Order Items</title>
</head>

<body>
    <script>window.isLoggedIn = <?php echo $is_logged_in ? 'true' : 'false'; ?>;</script>

    <section class="header">
        <div class="header-content">
            <h1 data-aos="fade-up">Pre-Order Items - PAMO</h1>
            <p data-aos="fade-up" data-aos-delay="100">Browse items available for pre-order. No filters, just the picks.</p>
        </div>
    </section>

    <div class="preorder-container" data-aos="fade-up" data-aos-delay="150">
        <main class="content">
            <div class="products-grid">
                <?php
                require_once '../Includes/connection.php';
                $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
                $limit = 12; 
                $offset = ($page - 1) * $limit;

                $countSql = "SELECT COUNT(*) FROM preorder_items WHERE status='pending'";
                $total_items = (int)$conn->query($countSql)->fetchColumn();
                $total_pages = max(1, (int)ceil($total_items / $limit));

                if ($total_items == 0) {
                    echo '<div class="no-items-message">';
                    echo '  <div class="no-items-content">';
                    echo '      <i class="fas fa-calendar-times"></i>';
                    echo '      <h2>No Items Available</h2>';
                    echo '      <p>Currently, there are no items available for pre-order.</p>';
                    echo '      <p class="sub-text">Please check back later for new pre-order opportunities.</p>';
                    echo '  </div>';
                    echo '</div>';
                } else {
                    // Check if user is logged in before querying
                    $userId = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 0;
                    
                    if ($userId > 0) {
                        // Query with user-specific data (for logged-in users)
                        $sql = "
                            SELECT pi.*, 
                                   COALESCE((SELECT SUM(quantity) FROM preorder_requests r WHERE r.preorder_item_id = pi.id AND r.status='active'),0) AS total_requests,
                                   EXISTS(SELECT 1 FROM preorder_orders po WHERE po.preorder_item_id = pi.id AND po.user_id = ? AND po.status IN ('pending', 'delivered')) AS has_pending_order,
                                   GROUP_CONCAT(DISTINCT sc.name SEPARATOR ', ') AS subcategories
                            FROM preorder_items pi
                            LEFT JOIN preorder_item_subcategory pis ON pis.preorder_item_id = pi.id
                            LEFT JOIN subcategories sc ON sc.id = pis.subcategory_id
                            WHERE pi.status='pending'
                            GROUP BY pi.id
                            ORDER BY pi.created_at DESC
                            LIMIT $limit OFFSET $offset
                        ";
                        $stmt = $conn->prepare($sql);
                        $stmt->execute([$userId]);
                    } else {
                        // Query without user-specific data (for guests)
                        $sql = "
                            SELECT pi.*, 
                                   COALESCE((SELECT SUM(quantity) FROM preorder_requests r WHERE r.preorder_item_id = pi.id AND r.status='active'),0) AS total_requests,
                                   0 AS has_pending_order,
                                   GROUP_CONCAT(DISTINCT sc.name SEPARATOR ', ') AS subcategories
                            FROM preorder_items pi
                            LEFT JOIN preorder_item_subcategory pis ON pis.preorder_item_id = pi.id
                            LEFT JOIN subcategories sc ON sc.id = pis.subcategory_id
                            WHERE pi.status='pending'
                            GROUP BY pi.id
                            ORDER BY pi.created_at DESC
                            LIMIT $limit OFFSET $offset
                        ";
                        $stmt = $conn->prepare($sql);
                        $stmt->execute();
                    }
                    
                    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    // Improved image path handling
                    if (!empty($row['image_path'])) {
                        $imgPath = '../' . $row['image_path'];
                        // Check if the file actually exists
                        if (!file_exists(__DIR__ . '/' . $imgPath)) {
                            // Try alternative path
                            $altPath = '../uploads/itemlist/' . basename($row['image_path']);
                            if (file_exists(__DIR__ . '/' . $altPath)) {
                                $imgPath = $altPath;
                            } else {
                                // Use default fallback
                                if (file_exists(__DIR__ . '/../uploads/itemlist/default.png')) {
                                    $imgPath = '../uploads/itemlist/default.png';
                                } elseif (file_exists(__DIR__ . '/../uploads/itemlist/default.jpg')) {
                                    $imgPath = '../uploads/itemlist/default.jpg';
                                } else {
                                    $imgPath = 'data:image/svg+xml;base64,' . base64_encode(
                                        '<svg width="300" height="300" xmlns="http://www.w3.org/2000/svg">
                                            <rect width="300" height="300" fill="#f0f0f0" stroke="#ddd" stroke-width="2"/>
                                            <text x="150" y="150" text-anchor="middle" dominant-baseline="middle" font-family="Arial" font-size="16" fill="#666">No Image</text>
                                        </svg>'
                                    );
                                }
                            }
                        }
                    } else {
                        // Use default fallback when no image path is provided
                        if (file_exists(__DIR__ . '/../uploads/itemlist/default.png')) {
                            $imgPath = '../uploads/itemlist/default.png';
                        } elseif (file_exists(__DIR__ . '/../uploads/itemlist/default.jpg')) {
                            $imgPath = '../uploads/itemlist/default.jpg';
                        } else {
                            $imgPath = 'data:image/svg+xml;base64,' . base64_encode(
                                '<svg width="300" height="300" xmlns="http://www.w3.org/2000/svg">
                                    <rect width="300" height="300" fill="#f0f0f0" stroke="#ddd" stroke-width="2"/>
                                    <text x="150" y="150" text-anchor="middle" dominant-baseline="middle" font-family="Arial" font-size="16" fill="#666">No Image</text>
                                </svg>'
                            );
                        }
                    }
                    $title = htmlspecialchars($row['item_name']);
                    $price = number_format((float)$row['price'], 2);
                    
                    // Conditionally include One Size based on subcategories or item name
                    $standardSizes = 'XS,S,M,L,XL,XXL,3XL,4XL,5XL,6XL,7XL';
                    $subcategories = strtolower($row['subcategories'] ?? '');
                    $itemName = strtolower($row['item_name'] ?? '');
                    
                    // Check if should include One Size (STI Accessories/STI-Accessories subcategory or item name contains accessories)
                    // Remove spaces and hyphens for flexible matching
                    $normalizedSubcategories = str_replace([' ', '-'], '', $subcategories);
                    if (strpos($normalizedSubcategories, 'stiaccessories') !== false || 
                        strpos($subcategories, 'accessories') !== false || 
                        strpos($itemName, 'accessories') !== false ||
                        strpos($itemName, 'lace') !== false ||
                        strpos($itemName, 'lanyard') !== false ||
                        strpos($itemName, 'pin') !== false ||
                        strpos($itemName, 'id') !== false && strpos($itemName, 'holder') !== false) {
                        $allSizes = 'One Size'; // Only One Size for accessories
                    } else {
                        $allSizes = $standardSizes; // XS to 7XL for clothing
                    }
                    
                    $preId = (int)$row['id'];
                    $requests = (int)$row['total_requests'];
                    $hasPending = (bool)$row['has_pending_order'];
                    
                    echo '<div class="product-container" data-preorder-id="' . $preId . '" data-sizes="' . $allSizes . '" data-item-name="' . $title . '" data-price="' . $row['price'] . '" data-has-pending="' . ($hasPending ? '1' : '0') . '">';
                    echo '  <img src="' . htmlspecialchars($imgPath) . '" alt="' . $title . '" onerror="this.onerror=null; this.src=\'data:image/svg+xml;base64,' . base64_encode('<svg width=\'300\' height=\'300\' xmlns=\'http://www.w3.org/2000/svg\'><rect width=\'300\' height=\'300\' fill=\'#f0f0f0\' stroke=\'#ddd\' stroke-width=\'2\'/><text x=\'150\' y=\'150\' text-anchor=\'middle\' dominant-baseline=\'middle\' font-family=\'Arial\' font-size=\'16\' fill=\'#666\'>No Image</text></svg>') . '\'">';
                    echo '  <div class="product-overlay">';
                    echo '      <div class="items"></div>';
                    echo '      <div class="items head">';
                    echo '          <p>' . $title . '</p>';
                    echo '          <p class="category">Pre-Order</p>';
                    echo '          <hr>';
                    echo '      </div>';
                    echo '      <div class="items price">';
                    echo '          <p class="price-range">Price: ₱' . $price . '</p>';
                    echo '      </div>';
                    echo '      <div class="items stock">';
                    echo '          <p>Pre-orders: ' . $requests . '</p>';
                    echo '      </div>';
                    
                    if ($hasPending) {
                        echo '      <div class="items cart request-preorder already-ordered" style="background: #9e9e9e; cursor: not-allowed;">';
                        echo '          <i class="fa fa-check-circle"></i>';
                        echo '          <span>ALREADY REQUESTED</span>';
                        echo '      </div>';
                    } else {
                        echo '      <div class="items cart request-preorder">';
                        echo '          <i class="fa fa-calendar-plus"></i>';
                        echo '          <span>REQUEST PRE-ORDER</span>';
                        echo '      </div>';
                    }
                    echo '  </div>';
                    echo '</div>';
                    }
                }
                ?>
            </div>

            <?php if ($total_pages > 1): ?>
            <div class="pagination" style="margin: 20px 0; display:flex; gap:6px; justify-content:center;">
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <a href="?page=<?php echo $i; ?>" class="<?php echo $i === $page ? 'active' : ''; ?>" style="padding:8px 12px; border:1px solid #007bff; border-radius:20px; text-decoration:none; color:<?php echo $i === $page ? '#fff' : '#007bff'; ?>; background:<?php echo $i === $page ? '#007bff' : '#fff'; ?>;">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>
            </div>
            <?php endif; ?>

        </main>
    </div>

    <div id="preorderRequestModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closePreModal()">&times;</span>
            <h2>Pre-Order Request</h2>
            <div class="product-info">
                <img id="preModalImage" src="" alt="Product Image">
                <div class="product-details">
                    <h3 id="preModalName"></h3>
                    <p id="preModalPrice" class="price-display">Price: --</p>
                </div>
            </div>
            <div class="size-options" id="preSizeOptions"></div>
            <div class="quantity-selector">
                <label for="preQuantity">Quantity:</label>
                <div class="quantity-controls">
                    <button type="button" onclick="adjustPreQty(-1)">-</button>
                    <input type="number" id="preQuantity" value="1" min="1" oninput="validatePreQuantity(this)">
                    <button type="button" onclick="adjustPreQty(1)">+</button>
                </div>
            </div>
            <button class="add-to-cart-btn" onclick="submitPreorderRequest()">Submit Request</button>
        </div>
    </div>

    <?php include("../Includes/Footer.php"); ?>

    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>
      AOS.init();
    </script>
    <script>
    let currentPreId = null;
    function closePreModal(){ 
        document.getElementById('preorderRequestModal').classList.remove('show'); 
        
        // Reset button state when modal is closed
        const submitBtn = document.querySelector('.add-to-cart-btn');
        submitBtn.disabled = false;
        submitBtn.style.opacity = '1';
        submitBtn.style.cursor = 'pointer';
        submitBtn.innerHTML = 'Submit Request';
    }
    
    function openPreModal(card){
        if (!window.isLoggedIn) { 
            window.location.href = "login.php?redirect=preorder.php"; 
            return; 
        }
        const preId = card.getAttribute('data-preorder-id');
        const sizesCsv = card.getAttribute('data-sizes') || '';
        const name = card.getAttribute('data-item-name') || '';
        const price = parseFloat(card.getAttribute('data-price')||'0');
        const img = card.querySelector('img')?.getAttribute('src') || '';
        currentPreId = preId;
        
        // Reset form
        document.getElementById('preModalName').textContent = name;
        document.getElementById('preModalPrice').textContent = 'Price: ₱' + price.toFixed(2);
        document.getElementById('preModalImage').setAttribute('src', img);
        document.getElementById('preQuantity').value = 1;
        
        // Reset button state
        const submitBtn = document.querySelector('.add-to-cart-btn');
        submitBtn.disabled = false;
        submitBtn.style.opacity = '1';
        submitBtn.style.cursor = 'pointer';
        submitBtn.innerHTML = 'Submit Request';
        
        const sizes = sizesCsv.split(',').map(s=>s.trim()).filter(Boolean);
        const container = document.getElementById('preSizeOptions');
        container.innerHTML = '';
        if (sizes.length <= 1) {
            // One Size or single size - show label only and store selected
            const single = sizes[0] || 'One Size';
            const div = document.createElement('div');
            div.className = 'size-option available selected';
            div.textContent = single;
            div.dataset.size = single;
            container.appendChild(div);
        } else {
            sizes.forEach(s => {
                const div = document.createElement('div');
                div.className = 'size-option available';
                div.textContent = s;
                div.dataset.size = s;
                div.onclick = function(){
                    document.querySelectorAll('#preSizeOptions .size-option').forEach(el=>el.classList.remove('selected'));
                    this.classList.add('selected');
                }
                container.appendChild(div);
            });
        }
        document.getElementById('preorderRequestModal').classList.add('show');
    }

    function adjustPreQty(delta){
        const el = document.getElementById('preQuantity');
        let v = parseInt(el.value||'1',10)+delta; if (v<1) v=1; el.value=v;
    }
    
    function validatePreQuantity(input) {
        // Remove any non-numeric characters except digits
        let value = parseInt(input.value);
        
        // If value is invalid, empty, or less than 1, reset to 1
        if (isNaN(value) || value < 1) {
            input.value = 1;
        } else {
            input.value = value; // This removes leading zeros
        }
    }

    async function submitPreorderRequest(){
        const qty = parseInt(document.getElementById('preQuantity').value||'1',10);
        
        let selectedSize = null;
        const sel = document.querySelector('#preSizeOptions .size-option.selected');
        if (sel) selectedSize = sel.dataset.size;
        
        if (!selectedSize) {
            showNotification('Please select a size', 'error');
            return;
        }
        
        // Validate quantity is at least 1
        if (qty < 1 || isNaN(qty)) {
            showNotification('Quantity must be at least 1', 'error');
            // Reset quantity to 1 if invalid
            document.getElementById('preQuantity').value = 1;
            return;
        }
        
        // Get the submit button and show loading state
        const submitBtn = document.querySelector('.add-to-cart-btn');
        const originalBtnText = submitBtn.innerHTML;
        submitBtn.disabled = true;
        submitBtn.style.opacity = '0.7';
        submitBtn.style.cursor = 'not-allowed';
        submitBtn.innerHTML = '<i class="fa fa-spinner fa-spin" style="margin-right: 8px;"></i>Submitting Pre-Order Request...';
        
        try {
            const fd = new FormData();
            fd.append('preorder_item_id', currentPreId);
            fd.append('size', selectedSize);
            fd.append('quantity', qty);
            
            const resp = await fetch('../PAMO_PREORDER_BACKEND/api_preorder_request_create.php', { method:'POST', body: fd });
            const data = await resp.json();
            
            if (!data.success) { 
                showNotification(data.message || 'Failed to submit pre-order request', 'error'); 
                
                // Restore button state on error
                submitBtn.disabled = false;
                submitBtn.style.opacity = '1';
                submitBtn.style.cursor = 'pointer';
                submitBtn.innerHTML = originalBtnText;
                return; 
            }
            
            // Success - close modal and show notification
            closePreModal();
            showNotification('Your pre-order request has been submitted successfully! Pre-Order #: ' + (data.preorder_number || 'N/A'), 'success', { autoClose: 5000 });
            
            // Reset button state after closing modal
            submitBtn.disabled = false;
            submitBtn.style.opacity = '1';
            submitBtn.style.cursor = 'pointer';
            submitBtn.innerHTML = originalBtnText;
            
            // Update the specific product card to show "ALREADY REQUESTED" without reloading
            updatePreorderCardStatus(currentPreId);
            
        } catch (error) {
            showNotification('An error occurred while submitting your request. Please try again.', 'error');
            
            // Restore button state on error
            submitBtn.disabled = false;
            submitBtn.style.opacity = '1';
            submitBtn.style.cursor = 'pointer';
            submitBtn.innerHTML = originalBtnText;
        }
    }
    
    function updatePreorderCardStatus(preorderId) {
        // Find the product card and update its button
        const productCard = document.querySelector(`.product-container[data-preorder-id="${preorderId}"]`);
        if (productCard) {
            const requestBtn = productCard.querySelector('.request-preorder');
            if (requestBtn && !requestBtn.classList.contains('already-ordered')) {
                requestBtn.classList.add('already-ordered');
                requestBtn.style.background = '#9e9e9e';
                requestBtn.style.cursor = 'not-allowed';
                requestBtn.innerHTML = '<i class="fa fa-check-circle"></i><span>ALREADY REQUESTED</span>';
                
                // Mark the card as having a pending order
                productCard.dataset.hasPending = '1';
            }
        }
    }

    document.addEventListener('DOMContentLoaded', function(){
        document.querySelectorAll('.product-container .request-preorder').forEach(btn => {
            btn.addEventListener('click', function(e){
                e.preventDefault();
                
                // Check if this item already has a pending order
                const container = this.closest('.product-container');
                const hasPending = container.dataset.hasPending === '1';
                
                if (hasPending || this.classList.contains('already-ordered')) {
                    showNotification('You already have a pending pre-order for this item.', 'warning');
                    return;
                }
                
                openPreModal(container);
            });
        });
        document.querySelector('#preorderRequestModal .modal-content').addEventListener('click', function(e){ e.stopPropagation(); });
        document.getElementById('preorderRequestModal').addEventListener('click', function(e){ if (e.target === this) closePreModal(); });
    });
    </script>
</body>