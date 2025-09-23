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
    <?php
    include("../Includes/Header.php");
    if (session_status() === PHP_SESSION_NONE) session_start();
    $is_logged_in = isset($_SESSION['user_id']);
    ?>
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

    <div class="container">
        <aside class="sidebar" id="sidebar" data-aos="fade-right">
            <span class="close-sidebar" id="closeSidebar">&times;</span>
            <div class="filter-header">
                <span class="filter-label">FILTER</span>
            </div>

            <button class="clear-filters" id="clearFiltersBtn" type="button">Clear Filters</button>

            <?php
            require_once '../Includes/connection.php';

            $sql = "SELECT inventory.* FROM inventory ORDER BY inventory.created_at DESC";
            $result = $conn->query($sql);

            $products = [];

            while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
                $itemCode = $row['item_code'];
                $itemName = $row['item_name'];
                $imagePath = $row['image_path'];
                $itemPrice = $row['price'];
                $itemCategory = $row['category'];
                $sizes = array_map(function($s) { return trim($s); }, explode(',', $row['sizes']));
                $baseItemCode = strtok($itemCode, '-');
                $courses = isset($row['courses']) ? array_map('trim', explode(',', $row['courses'])) : [];

                $subcats = [];
                $subSql = "SELECT s.id FROM inventory_subcategory isub JOIN subcategories s ON s.id = isub.subcategory_id WHERE isub.inventory_id = ?";
                $subStmt = $conn->prepare($subSql);
                $subStmt->execute([intval($row['id'])]);
                while ($srow = $subStmt->fetch(PDO::FETCH_ASSOC)) { $subcats[] = (string)$srow['id']; }

                // Enhanced image resolution logic
                $itemImage = '';
                if (!empty($imagePath)) {
                    // Check if the image path points to an existing file
                    $resolvedPath = '';
                    if (strpos($imagePath, 'uploads/') === false) {
                        $candidateItemlist = __DIR__ . '/../uploads/itemlist/' . $imagePath;
                        if (file_exists($candidateItemlist)) {
                            $resolvedPath = '../uploads/itemlist/' . $imagePath;
                        }
                    } else {
                        $candidateRaw = __DIR__ . '/../' . ltrim($imagePath, '/');
                        if (file_exists($candidateRaw)) {
                            $resolvedPath = '../' . ltrim($imagePath, '/');
                        }
                    }
                    $itemImage = $resolvedPath;
                }
                
                // Fallback: Try to find any image for this product prefix
                if (empty($itemImage)) {
                    try {
                        $stmt = $conn->prepare("SELECT image_path FROM inventory WHERE item_code LIKE ? AND image_path IS NOT NULL AND image_path != '' LIMIT 1");
                        $stmt->execute([$baseItemCode . '%']);
                        $fallbackRow = $stmt->fetch(PDO::FETCH_ASSOC);
                        if ($fallbackRow && !empty($fallbackRow['image_path'])) {
                            $fbImagePath = $fallbackRow['image_path'];
                            if (strpos($fbImagePath, 'uploads/') === false) {
                                $candidateItemlist = __DIR__ . '/../uploads/itemlist/' . $fbImagePath;
                                if (file_exists($candidateItemlist)) {
                                    $itemImage = '../uploads/itemlist/' . $fbImagePath;
                                }
                            } else {
                                $candidateRaw = __DIR__ . '/../' . ltrim($fbImagePath, '/');
                                if (file_exists($candidateRaw)) {
                                    $itemImage = '../' . ltrim($fbImagePath, '/');
                                }
                            }
                        }
                    } catch (Exception $e) {
                        // Fallback failed, continue with empty image
                    }
                }
                
                // Final fallback to default image
                if (empty($itemImage)) {
                    $itemImage = file_exists(__DIR__ . '/../uploads/itemlist/default.png') ? '../uploads/itemlist/default.png' : '../uploads/itemlist/default.jpg';
                }

                if (!isset($products[$baseItemCode])) {
                    $products[$baseItemCode] = [
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
                    $products[$baseItemCode]['sizes'] = array_unique(array_merge($products[$baseItemCode]['sizes'], $sizes));
                    $products[$baseItemCode]['prices'][] = $itemPrice;
                    $products[$baseItemCode]['stock'] += $row['actual_quantity'];
                    $products[$baseItemCode]['courses'] = array_unique(array_merge($products[$baseItemCode]['courses'], $courses));
                    $products[$baseItemCode]['subcategories'] = array_unique(array_merge($products[$baseItemCode]['subcategories'], $subcats));
                    
                    // Use the current variant's image if available, otherwise use the main product image
                    $variantImage = !empty($itemImage) ? $itemImage : $products[$baseItemCode]['image'];
                    
                    $products[$baseItemCode]['variants'][] = [
                        'item_code' => $itemCode,
                        'size' => isset($sizes[0]) ? $sizes[0] : '',
                        'price' => $itemPrice,
                        'stock' => $row['actual_quantity'],
                        'image' => $variantImage
                    ];
                    
                    // Update the main product image if it was empty and we now have a valid image
                    if (empty($products[$baseItemCode]['image']) && !empty($itemImage)) {
                        $products[$baseItemCode]['image'] = $itemImage;
                    }
                }
            }
            ?>
            
            <?php
            $categoriesWithProducts = [];
            foreach ($products as $product) {
                if ((int)($product['stock'] ?? 0) > 0) {
                    $cat = strtolower(str_replace(' ', '-', $product['category']));
                    $categoriesWithProducts[$cat] = true;
                }
            }

            $dynamicCategories = [];
            
            $categoriesQuery = "
                SELECT 
                    c.id, 
                    c.name, 
                    c.has_subcategories,
                    GROUP_CONCAT(
                        CONCAT(s.id, ':', s.name) 
                        ORDER BY s.name ASC 
                        SEPARATOR '|'
                    ) as subcategories
                FROM categories c
                LEFT JOIN subcategories s ON c.id = s.category_id
                GROUP BY c.id, c.name, c.has_subcategories
                ORDER BY c.name ASC
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
                        'subcategories' => $subcategories
                    ];
                }
            }
            
            $legacyCategoriesQuery = "
                SELECT DISTINCT category 
                FROM inventory 
                WHERE category NOT IN (SELECT name FROM categories)
                ORDER BY category ASC
            ";
            
            $legacyResult = $conn->query($legacyCategoriesQuery);
            
            if ($legacyResult) {
                while ($row = $legacyResult->fetch(PDO::FETCH_ASSOC)) {
                    $dynamicCategories[] = [
                        'id' => null,
                        'name' => $row['category'],
                        'has_subcategories' => false,
                        'subcategories' => [],
                        'is_legacy' => true
                    ];
                }
            }
            
            function normalizeCategoryName($name) {
                return strtolower(str_replace([' ', '_'], '-', $name));
            }
            
                // echo "<!-- categories loaded: " . count($dynamicCategories) . " -->";
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
<img src="<?php echo $productImage; ?>" alt="<?php echo $product['name']; ?>">
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
                
                <div id="no-results-message" class="no-results-message" style="display: none;">
                    <i class="fas fa-search"></i>
                    <h3>No products found</h3>
                    <p>Try adjusting your search terms or filters</p>
                </div>
            </div>
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