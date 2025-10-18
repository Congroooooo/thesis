<?php
// Start output buffering to prevent chunked encoding issues
ob_start();

// Set proper headers for better loading performance
header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: public, max-age=300'); // Cache for 5 minutes

include("../Includes/Header.php");
if (session_status() === PHP_SESSION_NONE) session_start();
$is_logged_in = isset($_SESSION['user_id']);
include("../Includes/loader.php");
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <link rel="stylesheet" href="../CSS/ProItemList.css">
    <link rel="stylesheet" href="../CSS/global.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Anton&family=Smooch+Sans:wght@100..900&display=swap"
        rel="stylesheet">
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <title>Product List</title>
</head>

<body>
    <script>window.isLoggedIn = <?php echo $is_logged_in ? 'true' : 'false'; ?>;</script>
    <section class="header"> 
        <div class="header-content">
            <h1 data-aos="fade-up">All Products - PAMO</h1>
            <p data-aos="fade-up" data-aos-delay="100">Explore our full range of items, all in one place!</p>
            <div class="search-container" data-aos="fade-up" data-aos-delay="200">
                <input type="text" id="search" placeholder="Search products...">
                <button type="button" class="search-btn">
                    <i class="fas fa-search"></i>
                </button>
            </div>
        </div>
    </section>

    <?php 
    // Flush the buffer after header to show content progressively
    if (ob_get_level()) {
        ob_flush();
        flush();
    }
    ?>

    <div class="container">
        <aside class="sidebar" id="sidebar" data-aos="fade-right">
            <span class="close-sidebar" id="closeSidebar">&times;</span>
            <div class="filter-header">
                <span class="filter-label">FILTER</span>
            </div>

            <button class="clear-filters" id="clearFiltersBtn" type="button">Clear Filters</button>

            <?php
            require_once '../Includes/connection.php';

            // Pagination parameters - moved here to be available for caching
            $itemsPerPage = 10;
            $currentPage = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
            $offset = ($currentPage - 1) * $itemsPerPage;

            // Simple caching mechanism using session
            $cacheKey = 'products_cache_' . $currentPage;
            $cacheTime = 300; // 5 minutes cache
            
            // Clear cache if requested
            if (isset($_GET['clear_cache'])) {
                unset($_SESSION[$cacheKey]);
                unset($_SESSION[$cacheKey . '_time']);
            }
            
            // Check if we have cached data
            if (isset($_SESSION[$cacheKey]) && 
                isset($_SESSION[$cacheKey . '_time']) && 
                (time() - $_SESSION[$cacheKey . '_time']) < $cacheTime) {
                
                // Use cached data
                $allProducts = $_SESSION[$cacheKey];
                $totalProducts = count($allProducts);
                $totalPages = ceil($totalProducts / $itemsPerPage);
                $productsForCurrentPage = array_slice($allProducts, $offset, $itemsPerPage, true);
                $products = $productsForCurrentPage;
                
            } else {
                // Fetch fresh data and cache it

            // Optimized single query with JOINs to reduce database calls
            $sql = "
                SELECT 
                    i.*,
                    GROUP_CONCAT(DISTINCT s.id ORDER BY s.id ASC SEPARATOR ',') as subcategory_ids
                FROM inventory i
                LEFT JOIN inventory_subcategory isub ON i.id = isub.inventory_id
                LEFT JOIN subcategories s ON s.id = isub.subcategory_id
                GROUP BY i.id
                ORDER BY i.created_at DESC
            ";
            $result = $conn->query($sql);

            $products = [];
            $allProducts = [];

            while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
                $itemCode = $row['item_code'];
                $itemName = $row['item_name'];
                $imagePath = $row['image_path'];
                $itemPrice = $row['price'];
                $itemCategory = $row['category'];
                $sizes = array_map(function($s) { return trim($s); }, explode(',', $row['sizes']));
                $baseItemCode = strtok($itemCode, '-');
                $courses = isset($row['courses']) ? array_map('trim', explode(',', $row['courses'])) : [];

                // Extract subcategories from the grouped result
                $subcats = [];
                if (!empty($row['subcategory_ids'])) {
                    $subcats = explode(',', $row['subcategory_ids']);
                }

                // Optimized image resolution logic
                $itemImage = '';
                if (!empty($imagePath)) {
                    // Quick path resolution without multiple file existence checks
                    if (strpos($imagePath, 'uploads/') === false) {
                        $itemImage = '../uploads/itemlist/' . $imagePath;
                    } else {
                        $itemImage = '../' . ltrim($imagePath, '/');
                    }
                } else {
                    // Set default image path directly
                    $itemImage = '../uploads/itemlist/default.png';
                }

                if (!isset($allProducts[$baseItemCode])) {
                    $allProducts[$baseItemCode] = [
                        'name' => $itemName,
                        'image' => $itemImage,
                        'prices' => [$itemPrice],
                        'category' => $itemCategory,
                        'sizes' => $sizes,
                        'stock' => $row['actual_quantity'],
                        'courses' => $courses,
                        'subcategories' => $subcats,
                        'variants' => [
                            [
                                'item_code' => $itemCode,
                                'size' => isset($sizes[0]) ? $sizes[0] : '',
                                'price' => $itemPrice,
                                'stock' => $row['actual_quantity'],
                                'image' => $itemImage
                            ]
                        ]
                    ];
                } else {
                    $allProducts[$baseItemCode]['sizes'] = array_unique(array_merge($allProducts[$baseItemCode]['sizes'], $sizes));
                    $allProducts[$baseItemCode]['prices'][] = $itemPrice;
                    $allProducts[$baseItemCode]['stock'] += $row['actual_quantity'];
                    $allProducts[$baseItemCode]['courses'] = array_unique(array_merge($allProducts[$baseItemCode]['courses'], $courses));
                    $allProducts[$baseItemCode]['subcategories'] = array_unique(array_merge($allProducts[$baseItemCode]['subcategories'], $subcats));
                    
                    // Use the current variant's image if available, otherwise use the main product image
                    $variantImage = !empty($itemImage) ? $itemImage : $allProducts[$baseItemCode]['image'];
                    
                    $allProducts[$baseItemCode]['variants'][] = [
                        'item_code' => $itemCode,
                        'size' => isset($sizes[0]) ? $sizes[0] : '',
                        'price' => $itemPrice,
                        'stock' => $row['actual_quantity'],
                        'image' => $variantImage
                    ];
                    
                    // Update the main product image if it was empty and we now have a valid image
                    if (empty($allProducts[$baseItemCode]['image']) && !empty($itemImage)) {
                        $allProducts[$baseItemCode]['image'] = $itemImage;
                    }
                }
            }
            
            // Apply pagination to products
            $totalProducts = count($allProducts);
            $totalPages = ceil($totalProducts / $itemsPerPage);
            
            // Get only the products for the current page
            $productsForCurrentPage = array_slice($allProducts, $offset, $itemsPerPage, true);
            $products = $productsForCurrentPage;
            
                // Cache the results
                $_SESSION[$cacheKey] = $allProducts;
                $_SESSION[$cacheKey . '_time'] = time();
            }
            ?>
            
            <?php
            $categoriesWithProducts = [];
            foreach ($allProducts as $product) {
                if ((int)($product['stock'] ?? 0) > 0) {
                    $cat = strtolower(str_replace(' ', '-', $product['category']));
                    $categoriesWithProducts[$cat] = true;
                }
            }

            $dynamicCategories = [];
            
            // Optimized categories query - combined with legacy categories
            $categoriesQuery = "
                SELECT 
                    c.id, 
                    c.name, 
                    c.has_subcategories,
                    GROUP_CONCAT(
                        CONCAT(s.id, ':', s.name) 
                        ORDER BY s.name ASC 
                        SEPARATOR '|'
                    ) as subcategories,
                    'regular' as category_type
                FROM categories c
                LEFT JOIN subcategories s ON c.id = s.category_id
                GROUP BY c.id, c.name, c.has_subcategories
                
                UNION ALL
                
                SELECT 
                    NULL as id,
                    category as name,
                    0 as has_subcategories,
                    NULL as subcategories,
                    'legacy' as category_type
                FROM inventory 
                WHERE category NOT IN (SELECT name FROM categories)
                GROUP BY category
                
                ORDER BY name ASC
            ";
            
            $categoriesResult = $conn->query($categoriesQuery);
            
            if ($categoriesResult) {
                while ($row = $categoriesResult->fetch(PDO::FETCH_ASSOC)) {
                    $subcategories = [];
                    if ($row['subcategories']) {
                        $subPairs = explode('|', $row['subcategories']);
                        foreach ($subPairs as $pair) {
                            $parts = explode(':', $pair, 2);
                            if (count($parts) === 2) {
                                $subcategories[] = [
                                    'id' => $parts[0],
                                    'name' => $parts[1]
                                ];
                            }
                        }
                    }
                    
                    $dynamicCategories[] = [
                        'id' => $row['id'],
                        'name' => $row['name'],
                        'has_subcategories' => (bool)$row['has_subcategories'],
                        'subcategories' => $subcategories,
                        'is_legacy' => $row['category_type'] === 'legacy'
                    ];
                }
            }
            
            function normalizeCategoryName($name) {
                return strtolower(str_replace([' ', '_'], '-', $name));
            }
            ?>
            
            <?php if (count($dynamicCategories) > 0): ?>
            <?php else: ?>
            <?php endif; ?>
            <div class="category-list">
                <?php if (!empty($dynamicCategories)): ?>
                    <?php foreach ($dynamicCategories as $category): ?>
                        <?php 
                        $normalizedName = normalizeCategoryName($category['name']);
                        $hasProducts = isset($categoriesWithProducts[$normalizedName]);
                        ?>
                        <div class="category-item">
                            <div class="main-category-header" data-category="<?= htmlspecialchars($normalizedName) ?>">
                                <span><?= htmlspecialchars($category['name']) ?></span>
                                <i class="fas fa-chevron-down"></i>
                            </div>
                            <div class="subcategories">
                                <?php if ($category['has_subcategories'] && !empty($category['subcategories'])): ?>
                                    <?php foreach ($category['subcategories'] as $subcategory): ?>
                                        <div class="course-category">
                                            <div class="course-header">
                                                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                                    <input type="checkbox" class="course-filter-checkbox" value="<?= htmlspecialchars($subcategory['id']) ?>" data-name="<?= htmlspecialchars($subcategory['name']) ?>">
                                                    <span><?= htmlspecialchars($subcategory['name']) ?></span>
                                                </label>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                <?php endif; ?>
            </div>
        </aside>
        <button class="filter-toggle" id="filterToggle">
        <i class="fas fa-filter"></i>   
        <span>Filter</span>
    </button>

        <main class="content">
            <div class="products-grid">
                <?php
                    foreach ($products as $baseItemCode => $product):
                        if ((int)($product['stock'] ?? 0) <= 0) { continue; }
                        $availableSizes = $product['sizes'];
                        $prices = $product['prices'];
                        $courses = $product['courses'];
                        $stocksBySize = [];
                        $itemCodesBySize = [];
                        foreach ($product['variants'] as $variant) {
                        $size = $variant['size'];
                        $stocksBySize[$size] = $variant['stock'];
                        $itemCodesBySize[$size] = $variant['item_code'];
                        }
                ?>
                    <div class="product-container" 
                        data-category="<?php echo strtolower(str_replace(' ', '-', $product['category'])); ?>"
                        data-sizes="<?php echo implode(',', $availableSizes); ?>"
                        data-prices="<?php echo implode(',', $prices); ?>" 
                        data-stocks="<?php echo implode(',', array_values($stocksBySize)); ?>"
                        data-item-codes="<?php echo implode(',', array_values($itemCodesBySize)); ?>"
                        data-stock="<?php echo $product['stock']; ?>"
                        data-item-code="<?php echo htmlspecialchars($product['variants'][0]['item_code']); ?>"
                        data-item-name="<?php echo htmlspecialchars($product['name']); ?>"
                        data-courses="<?php echo htmlspecialchars(implode(',', $courses)); ?>"
                        data-subcategories="<?php echo htmlspecialchars(implode(',', ($product['subcategories'] ?? []))); ?>">
                        <?php
                            $productImage = '';
                            foreach ($product['variants'] as $variant) {
                                if (!empty($variant['image'])) {
                                    $productImage = $variant['image'];
                                    break;
                                }
                            }
                            if (empty($productImage)) {
                                $productImage = '../uploads/itemlist/default.png';
                            }
                        ?>
                            <img src="data:image/svg+xml;base64,<?php echo base64_encode('<svg width="300" height="300" xmlns="http://www.w3.org/2000/svg"><rect width="300" height="300" fill="#f0f0f0"/></svg>'); ?>" 
                                 data-src="<?php echo htmlspecialchars($productImage); ?>" 
                                 alt="<?php echo htmlspecialchars($product['name']); ?>" 
                                 class="lazy-load-image"
                                 loading="lazy"
                                 onerror="this.onerror=null; this.src='data:image/svg+xml;base64,<?php echo base64_encode('<svg width=\'300\' height=\'300\' xmlns=\'http://www.w3.org/2000/svg\'><rect width=\'300\' height=\'300\' fill=\'#f0f0f0\' stroke=\'#ddd\' stroke-width=\'2\'/><text x=\'150\' y=\'150\' text-anchor=\'middle\' dominant-baseline=\'middle\' font-family=\'Arial\' font-size=\'16\' fill=\'#666\'>No Image</text></svg>'); ?>'">
                        <div class="product-overlay">
                            <div class="items"></div>
                            <div class="items head">
                                <p><?php echo $product['name']; ?></p>
                                <p class="category"><?php echo htmlspecialchars($product['category']); ?></p>
                                <hr>
                            </div>
                            <div class="items price">
                                <p class="price-range">Price: ₱<?php echo number_format(min($prices), 2); ?> -
                                    ₱<?php echo number_format(max($prices), 2); ?></p>
                            </div>
                            <div class="items stock">
                                <p>Stock: <?php echo $product['stock']; ?></p>
                            </div>
                            <div class="items cart" onclick="handleAddToCart(this)" data-item-code="<?php echo htmlspecialchars($baseItemCode); ?>">
                                <i class="fa fa-shopping-cart"></i>
                                <span>ADD TO CART</span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
                
                <div id="no-results-message" class="no-results-message enhanced" style="display: none;">
                    <i class="fas fa-search"></i>
                    <h3>No products found</h3>
                    <p>Try adjusting your search terms or filters to find what you're looking for.</p>
                </div>
            </div>
            
            <!-- Pagination Controls -->
            <?php if ($totalPages > 1): ?>
            <?php
            // Preserve any existing URL parameters for pagination links
            $currentParams = $_GET;
            unset($currentParams['page']); // Remove page parameter to avoid duplication
            
            function buildPaginationUrl($page, $params = []) {
                $allParams = array_merge($_GET, $params);
                $allParams['page'] = $page;
                return '?' . http_build_query($allParams);
            }
            ?>
            <div class="pagination-container">
                <div class="pagination-info">
                    <span>Showing <?php echo ($offset + 1); ?> to <?php echo min($offset + $itemsPerPage, $totalProducts); ?> of <?php echo $totalProducts; ?> products</span>
                </div>
                <div class="pagination">
                    <?php if ($currentPage > 1): ?>
                        <a href="<?php echo buildPaginationUrl($currentPage - 1); ?>" class="pagination-btn prev-btn">
                            <i class="fas fa-chevron-left"></i>
                            Previous
                        </a>
                    <?php else: ?>
                        <span class="pagination-btn prev-btn disabled">
                            <i class="fas fa-chevron-left"></i>
                            Previous
                        </span>
                    <?php endif; ?>
                    
                    <div class="pagination-numbers">
                        <?php
                        $startPage = max(1, $currentPage - 2);
                        $endPage = min($totalPages, $currentPage + 2);
                        
                        if ($startPage > 1): ?>
                            <a href="<?php echo buildPaginationUrl(1); ?>" class="pagination-number">1</a>
                            <?php if ($startPage > 2): ?>
                                <span class="pagination-dots">...</span>
                            <?php endif; ?>
                        <?php endif; ?>
                        
                        <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                            <?php if ($i == $currentPage): ?>
                                <span class="pagination-number active"><?php echo $i; ?></span>
                            <?php else: ?>
                                <a href="<?php echo buildPaginationUrl($i); ?>" class="pagination-number"><?php echo $i; ?></a>
                            <?php endif; ?>
                        <?php endfor; ?>
                        
                        <?php if ($endPage < $totalPages): ?>
                            <?php if ($endPage < $totalPages - 1): ?>
                                <span class="pagination-dots">...</span>
                            <?php endif; ?>
                            <a href="<?php echo buildPaginationUrl($totalPages); ?>" class="pagination-number"><?php echo $totalPages; ?></a>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($currentPage < $totalPages): ?>
                        <a href="<?php echo buildPaginationUrl($currentPage + 1); ?>" class="pagination-btn next-btn">
                            Next
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php else: ?>
                        <span class="pagination-btn next-btn disabled">
                            Next
                            <i class="fas fa-chevron-right"></i>
                        </span>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </main>
    </div>

    <div id="sizeModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2>Select Size</h2>
            <div class="product-info">
                <img id="modalProductImage" src="" alt="Product Image">
                <div class="product-details">
                    <h3 id="modalProductName"></h3>
                    <p id="modalProductPrice" class="price-display">Price Range: --</p>
                    <p id="modalProductStock" class="stock-display">Stock: --</p>
                </div>
            </div>
            <div class="size-options">
            </div>
            <div class="quantity-selector">
                <label for="quantity">Quantity:</label>
                <div class="quantity-controls">
                    <button type="button" onclick="decrementQuantity()">-</button>
                    <input type="number" id="quantity" value="1" min="1">
                    <button type="button" onclick="incrementQuantity()">+</button>
                </div>
            </div>
            <button class="add-to-cart-btn" onclick="addToCartWithSize()">Add to Cart</button>
        </div>
    </div>

    <div id="accessoryModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeAccessoryModal()">&times;</span>
            <h2>Select Quantity</h2>
            <div class="product-info">
                <img id="accessoryModalImage" src="" alt="Product Image">
                <div class="product-details">
                    <h3 id="accessoryModalName"></h3>
                    <p id="accessoryModalPrice" class="price-display">Price: --</p>
                    <p id="accessoryModalStock" class="stock-display">Stock: --</p>
                </div>
            </div>
            <div class="quantity-selector">
                <label for="accessoryQuantity">Quantity:</label>
                <div class="quantity-controls">
                    <button type="button" onclick="decrementAccessoryQuantity()">-</button>
                    <input type="number" id="accessoryQuantity" value="1" min="1">
                    <button type="button" onclick="incrementAccessoryQuantity()">+</button>
                </div>
            </div>
            <button class="add-to-cart-btn" onclick="addAccessoryToCart()">Add to Cart</button>
        </div>
    </div>

    <?php include("../Includes/Footer.php"); ?>

    <script src="../Javascript/ProItemList.js" defer></script>
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    
    <!-- Lazy Loading Script -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Lazy loading implementation
        const lazyImages = document.querySelectorAll('.lazy-load-image');
        
        const imageObserver = new IntersectionObserver((entries, observer) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const img = entry.target;
                    img.src = img.dataset.src;
                    img.classList.remove('lazy-load-image');
                    observer.unobserve(img);
                }
            });
        }, {
            rootMargin: '50px 0px',
            threshold: 0.01
        });

        lazyImages.forEach(img => {
            imageObserver.observe(img);
        });

        // Initialize AOS with optimized settings
        AOS.init({
            duration: 600,
            once: true,
            disable: 'mobile'
        });
    });
    </script>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const filterToggle = document.getElementById('filterToggle');
        const sidebar = document.getElementById('sidebar');
        const closeSidebar = document.getElementById('closeSidebar');

        filterToggle.addEventListener('click', function() {
            sidebar.classList.toggle('active');
        });

        closeSidebar.addEventListener('click', function() {
            sidebar.classList.remove('active');
        });

        document.addEventListener('click', function(e) {
            if (!sidebar.contains(e.target) && !filterToggle.contains(e.target)) {
                sidebar.classList.remove('active');
            }
        });

        sidebar.addEventListener('click', function(e) {
            e.stopPropagation();
        });

        window.addEventListener('resize', function() {
            if (window.innerWidth >= 768) {
                sidebar.classList.remove('active');
            }
        });
    });
    </script>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const modal = document.getElementById('sizeModal');
        const closeBtn = modal.querySelector('.close');
        
        function openModal() {
            modal.classList.add('show');
        }
        
        function closeModal() {
            modal.classList.remove('show');
        }
        
        document.querySelectorAll('.cart').forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                openModal();
            });
        });
        
        closeBtn.addEventListener('click', closeModal);
        
        window.addEventListener('click', function(e) {
            if (e.target === modal) {
                closeModal();
            }
        });
        
        modal.querySelector('.modal-content').addEventListener('click', function(e) {
            e.stopPropagation();
        });
    });
    </script>
</body>

</html>
<?php
// Flush the output buffer to prevent chunked encoding issues
ob_end_flush();
?>