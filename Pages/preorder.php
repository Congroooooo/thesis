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
    <?php 
    include '../Includes/Header.php'; 
    if (session_status() === PHP_SESSION_NONE) session_start();
    $is_logged_in = isset($_SESSION['user_id']);
    ?>
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
                    $sql = "
                        SELECT pi.*, 
                               COALESCE((SELECT SUM(quantity) FROM preorder_requests r WHERE r.preorder_item_id = pi.id AND r.status='active'),0) AS total_requests
                        FROM preorder_items pi
                        WHERE pi.status='pending'
                        ORDER BY pi.created_at DESC
                        LIMIT $limit OFFSET $offset
                    ";
                    $result = $conn->query($sql);
                    while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
                    $imgPath = !empty($row['image_path']) ? '../' . $row['image_path'] : '../uploads/itemlist/default.png';
                    $title = htmlspecialchars($row['item_name']);
                    $price = number_format((float)$row['price'], 2);
                    $sizes = htmlspecialchars($row['sizes']);
                    $preId = (int)$row['id'];
                    $requests = (int)$row['total_requests'];
                    echo '<div class="product-container" data-preorder-id="' . $preId . '" data-sizes="' . $sizes . '" data-item-name="' . $title . '" data-price="' . $row['price'] . '">';
                    echo '  <img src="' . htmlspecialchars($imgPath) . '" alt="' . $title . '">';
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
                    echo '      <div class="items cart request-preorder">';
                    echo '          <i class="fa fa-calendar-plus"></i>';
                    echo '          <span>REQUEST PRE-ORDER</span>';
                    echo '      </div>';
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
                    <input type="number" id="preQuantity" value="1" min="1">
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
    function closePreModal(){ document.getElementById('preorderRequestModal').classList.remove('show'); }
    function openPreModal(card){
        if (!window.isLoggedIn) { alert('Please login to place a pre-order request.'); return; }
        const preId = card.getAttribute('data-preorder-id');
        const sizesCsv = card.getAttribute('data-sizes') || '';
        const name = card.getAttribute('data-item-name') || '';
        const price = parseFloat(card.getAttribute('data-price')||'0');
        const img = card.querySelector('img')?.getAttribute('src') || '';
        currentPreId = preId;
        document.getElementById('preModalName').textContent = name;
        document.getElementById('preModalPrice').textContent = 'Price: ₱' + price.toFixed(2);
        document.getElementById('preModalImage').setAttribute('src', img);
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

    async function submitPreorderRequest(){
        const qty = parseInt(document.getElementById('preQuantity').value||'1',10);
        let selectedSize = null;
        const sel = document.querySelector('#preSizeOptions .size-option.selected');
        if (sel) selectedSize = sel.dataset.size;
        const fd = new FormData();
        fd.append('preorder_item_id', currentPreId);
        if (selectedSize) fd.append('size', selectedSize);
        fd.append('quantity', qty);
        const resp = await fetch('../PAMO_PREORDER_BACKEND/api_preorder_request_create.php', { method:'POST', body: fd });
        const data = await resp.json();
        if (!data.success) { alert(data.message || 'Failed'); return; }
        closePreModal();
        alert('Your pre-order request has been submitted. Thank you!');
        location.reload();
    }

    document.addEventListener('DOMContentLoaded', function(){
        document.querySelectorAll('.product-container .request-preorder').forEach(btn => {
            btn.addEventListener('click', function(e){
                e.preventDefault();
                openPreModal(this.closest('.product-container'));
            });
        });
        document.querySelector('#preorderRequestModal .modal-content').addEventListener('click', function(e){ e.stopPropagation(); });
        document.getElementById('preorderRequestModal').addEventListener('click', function(e){ if (e.target === this) closePreModal(); });
    });
    </script>
</body>