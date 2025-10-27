<?php
ob_start();

header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: public, max-age=300');

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

            $searchQuery = isset($_GET['search']) ? trim($_GET['search']) : '';
            
            // Support both single and multiple category/subcategory filters
            $categoriesParam = isset($_GET['categories']) ? $_GET['categories'] : (isset($_GET['category']) ? $_GET['category'] : '');
            $subcategoriesParam = isset($_GET['subcategories']) ? $_GET['subcategories'] : (isset($_GET['subcategory']) ? $_GET['subcategory'] : '');
            
            // Convert comma-separated strings to arrays and filter out empty values
            $categoryFilters = !empty($categoriesParam) ? array_filter(array_map('trim', explode(',', $categoriesParam))) : [];
            $subcategoryFilters = !empty($subcategoriesParam) ? array_filter(array_map('trim', explode(',', $subcategoriesParam))) : [];
            
            // Re-index arrays after filtering to avoid gaps in array keys
            $categoryFilters = array_values($categoryFilters);
            $subcategoryFilters = array_values($subcategoryFilters);
            
            $courseFilter = isset($_GET['course']) ? $_GET['course'] : '';
            $itemsPerPage = 12;
            $currentPage = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
            $offset = ($currentPage - 1) * $itemsPerPage;
            $filterParams = [
                'search' => $searchQuery,
                'categories' => $categoriesParam,
                'subcategories' => $subcategoriesParam,
                'course' => $courseFilter,
                'page' => $currentPage
            ];
            $cacheKey = 'products_cache_' . md5(serialize($filterParams));
            $cacheTime = 300;

            if (isset($_GET['clear_cache'])) {
                foreach ($_SESSION as $key => $value) {
                    if (strpos($key, 'products_cache_') === 0) {
                        unset($_SESSION[$key]);
                    }
                }
            }

            if (isset($_SESSION[$cacheKey]) && 
                isset($_SESSION[$cacheKey . '_time']) && 
                (time() - $_SESSION[$cacheKey . '_time']) < $cacheTime) {

                $cachedData = $_SESSION[$cacheKey];
                $allProducts = $cachedData['allProducts'];
                $totalProducts = $cachedData['totalProducts'];
                $totalPages = $cachedData['totalPages'];

                $productsForCurrentPage = array_slice($allProducts, $offset, $itemsPerPage, true);
                $products = $productsForCurrentPage;
                
            } else {
            $whereConditions = [];
            $params = [];

            // Add search condition with multi-keyword support
            if (!empty($searchQuery)) {
                // Split search query into individual keywords
                $keywords = array_filter(array_map('trim', explode(' ', $searchQuery)));
                
                if (!empty($keywords)) {
                    $searchConditions = [];
                    
                    foreach ($keywords as $keyword) {
                        // Each keyword should match in item_name OR item_code OR category
                        $searchConditions[] = "(i.item_name LIKE ? OR i.item_code LIKE ? OR i.category LIKE ?)";
                        $keywordParam = '%' . $keyword . '%';
                        $params[] = $keywordParam;
                        $params[] = $keywordParam;
                        $params[] = $keywordParam;
                    }
                    
                    // All keywords must be present (AND logic)
                    // This means "TM Blouse" will match items containing both "TM" AND "Blouse"
                    $whereConditions[] = '(' . implode(' AND ', $searchConditions) . ')';
                }
            }

            // Handle category and subcategory filters with smart logic
            if (!empty($categoryFilters) || !empty($subcategoryFilters)) {
                // Get the parent category for each selected subcategory
                $subcategoryParents = [];
                if (!empty($subcategoryFilters)) {
                    $subcatPlaceholders = implode(',', array_fill(0, count($subcategoryFilters), '?'));
                    $subcatStmt = $conn->prepare("
                        SELECT s.id as subcat_id, c.name as parent_category 
                        FROM subcategories s 
                        JOIN categories c ON s.category_id = c.id 
                        WHERE s.id IN ($subcatPlaceholders)
                    ");
                    $subcatStmt->execute($subcategoryFilters);
                    while ($row = $subcatStmt->fetch(PDO::FETCH_ASSOC)) {
                        $normalizedParent = strtolower(str_replace(' ', '-', $row['parent_category']));
                        $subcategoryParents[$row['subcat_id']] = $normalizedParent;
                    }
                }
                
                // Build filter logic:
                // For each category:
                //   - If it has subcategories selected: show items with those subcategories
                //   - If it has NO subcategories selected: show ALL items from that category
                
                $categoriesWithSubcategories = [];
                $categoriesWithoutSubcategories = [];
                
                foreach ($categoryFilters as $cat) {
                    $hasSubcategory = false;
                    foreach ($subcategoryParents as $subcatId => $parentCat) {
                        if ($parentCat === $cat) {
                            $hasSubcategory = true;
                            $categoriesWithSubcategories[$cat][] = $subcatId;
                        }
                    }
                    if (!$hasSubcategory) {
                        $categoriesWithoutSubcategories[] = $cat;
                    }
                }
                
                $filterParts = [];
                
                // Add filters for categories WITH subcategory selections (narrow down)
                foreach ($categoriesWithSubcategories as $cat => $subcatIds) {
                    $subcatPlaceholders = [];
                    $params[] = $cat;
                    foreach ($subcatIds as $subcatId) {
                        $subcatPlaceholders[] = "?";
                        $params[] = $subcatId;
                    }
                    $filterParts[] = "(LOWER(REPLACE(i.category, ' ', '-')) = LOWER(?) AND EXISTS (SELECT 1 FROM inventory_subcategory isubf WHERE isubf.inventory_id = i.id AND isubf.subcategory_id IN (" . implode(',', $subcatPlaceholders) . ")))";
                }
                
                // Add filters for categories WITHOUT subcategory selections (show all)
                if (!empty($categoriesWithoutSubcategories)) {
                    $catPlaceholders = [];
                    foreach ($categoriesWithoutSubcategories as $cat) {
                        $catPlaceholders[] = "LOWER(REPLACE(i.category, ' ', '-')) = LOWER(?)";
                        $params[] = $cat;
                    }
                    $filterParts[] = "(" . implode(' OR ', $catPlaceholders) . ")";
                }
                
                // If subcategories selected but their parent categories are NOT in categoryFilters
                // (user selected subcategory without clicking parent category)
                $orphanSubcategories = [];
                foreach ($subcategoryParents as $subcatId => $parentCat) {
                    if (!in_array($parentCat, $categoryFilters)) {
                        $orphanSubcategories[] = $subcatId;
                    }
                }
                
                if (!empty($orphanSubcategories)) {
                    $subcatPlaceholders = [];
                    foreach ($orphanSubcategories as $subcatId) {
                        $subcatPlaceholders[] = "?";
                        $params[] = $subcatId;
                    }
                    $filterParts[] = "EXISTS (SELECT 1 FROM inventory_subcategory isubf WHERE isubf.inventory_id = i.id AND isubf.subcategory_id IN (" . implode(',', $subcatPlaceholders) . "))";
                }
                
                // Combine all parts with OR
                if (!empty($filterParts)) {
                    $whereConditions[] = "(" . implode(' OR ', $filterParts) . ")";
                }
            }

            if (!empty($courseFilter)) {
                $whereConditions[] = "FIND_IN_SET(?, REPLACE(i.courses, ' ', '')) > 0";
                $params[] = $courseFilter;
            }

            $whereClause = !empty($whereConditions) ? "WHERE " . implode(" AND ", $whereConditions) : "";

            $sql = "
                SELECT 
                    i.*,
                    GROUP_CONCAT(DISTINCT s.id ORDER BY s.id ASC SEPARATOR ',') as subcategory_ids
                FROM inventory i
                LEFT JOIN inventory_subcategory isub ON i.id = isub.inventory_id
                LEFT JOIN subcategories s ON s.id = isub.subcategory_id
                $whereClause
                GROUP BY i.id
                ORDER BY i.created_at DESC
            ";

            if (!empty($params)) {
                $stmt = $conn->prepare($sql);
                $stmt->execute($params);
                $result = $stmt;
            } else {
                $result = $conn->query($sql);
            }

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

                $subcats = [];
                if (!empty($row['subcategory_ids'])) {
                    $subcats = explode(',', $row['subcategory_ids']);
                }

                $itemImage = '';
                if (!empty($imagePath)) {
                    if (strpos($imagePath, 'uploads/') === false) {
                        $itemImage = '../uploads/itemlist/' . $imagePath;
                    } else {
                        $itemImage = '../' . ltrim($imagePath, '/');
                    }
                } else {
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
                    $variantImage = !empty($itemImage) ? $itemImage : $allProducts[$baseItemCode]['image'];
                    $allProducts[$baseItemCode]['variants'][] = [
                        'item_code' => $itemCode,
                        'size' => isset($sizes[0]) ? $sizes[0] : '',
                        'price' => $itemPrice,
                        'stock' => $row['actual_quantity'],
                        'image' => $variantImage
                    ];

                    if (empty($allProducts[$baseItemCode]['image']) && !empty($itemImage)) {
                        $allProducts[$baseItemCode]['image'] = $itemImage;
                    }
                }
            }

            $totalProducts = count($allProducts);
            $totalPages = ceil($totalProducts / $itemsPerPage);
            $productsForCurrentPage = array_slice($allProducts, $offset, $itemsPerPage, true);
            $products = $productsForCurrentPage;

            $_SESSION[$cacheKey] = [
                'allProducts' => $allProducts,
                'totalProducts' => $totalProducts,
                'totalPages' => $totalPages
            ];
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
                            <div class="items cart" data-item-code="<?php echo htmlspecialchars($baseItemCode); ?>">
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

            <?php if ($totalPages > 1): ?>
            <?php
            $currentParams = $_GET;
            unset($currentParams['page']);
            
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

    <!-- ProItemList.js removed - conflicts with inline AJAX implementation -->
    <!-- <script src="../Javascript/ProItemList.js" defer></script> -->
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    
    <!-- Lazy Loading and AJAX Filter Script -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Lazy loading implementation (reusable function)
        function initLazyLoading() {
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
        }

        // Initialize lazy loading on page load
        initLazyLoading();

        // Initialize AOS with optimized settings
        AOS.init({
            duration: 600,
            once: true,
            disable: 'mobile'
        });

        const searchInput = document.getElementById('search');
        const searchBtn = document.querySelector('.search-btn');
        const categoryFilters = document.querySelectorAll('.main-category-header');
        const subcategoryFilters = document.querySelectorAll('.course-filter-checkbox');
        const clearFiltersBtn = document.getElementById('clearFiltersBtn');
        const productsGrid = document.querySelector('.products-grid');
        const mainContent = document.querySelector('main.content');

        // Initialize: Hide all subcategories by default
        document.querySelectorAll('.subcategories').forEach(sub => {
            sub.style.display = 'none';
        });

        // Search functionality with debouncing
        let searchTimeout = null;
        
        if (searchInput) {
            // Remove existing event listeners by cloning
            const newSearchInput = searchInput.cloneNode(true);
            searchInput.parentNode.replaceChild(newSearchInput, searchInput);
            const searchInputNew = document.getElementById('search');

            // Debounced search on input
            searchInputNew.addEventListener('input', function() {
                const searchContainer = document.querySelector('.search-container');
                
                // Clear any existing timeout
                clearTimeout(searchTimeout);

                // Show loading state immediately when typing
                if (searchContainer) {
                    searchContainer.classList.add('loading');
                }

                // Wait 500ms after user stops typing before triggering search
                searchTimeout = setTimeout(function() {
                    loadProducts(1); // Reset to page 1 on new search
                }, 500); // Increased from 300ms to 500ms for better debouncing
            });

            // Immediate search on Enter key
            searchInputNew.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault(); // Prevent form submission
                    const searchContainer = document.querySelector('.search-container');
                    
                    if (searchContainer) {
                        searchContainer.classList.add('loading');
                    }
                    clearTimeout(searchTimeout);
                    loadProducts(1); // Reset to page 1
                }
            });
        }

        function restoreFilterState() {
            const urlParams = new URLSearchParams(window.location.search);
            
            // Support both 'categories' (plural) and 'category' (singular) parameters
            let categoriesParam = urlParams.get('categories') || urlParams.get('category');
            const subcategoriesParam = urlParams.get('subcategories');
            const search = urlParams.get('search');

            if (search) {
                document.getElementById('search').value = search;
            }

            // First, remove active class from ALL categories and hide all subcategories
            document.querySelectorAll('.main-category-header').forEach(cat => {
                cat.classList.remove('active');
                const subcategoriesDiv = cat.nextElementSibling;
                if (subcategoriesDiv && subcategoriesDiv.classList.contains('subcategories')) {
                    subcategoriesDiv.style.display = 'none';
                }
            });

            // Then add active class to all selected categories and show their subcategories
            if (categoriesParam) {
                const selectedCategories = categoriesParam.split(',').map(cat => cat.trim());
                
                document.querySelectorAll('.main-category-header').forEach(cat => {
                    const catDataValue = cat.dataset.category;
                    
                    // Check if this category matches any of the selected categories
                    // Support multiple formats: exact match, normalized match, case-insensitive
                    const isMatch = selectedCategories.some(selectedCat => {
                        // Normalize both values for comparison
                        const normalizedSelected = selectedCat.toLowerCase().replace(/\s+/g, '-');
                        const normalizedCatData = catDataValue.toLowerCase();
                        
                        return normalizedCatData === normalizedSelected || 
                               catDataValue === selectedCat ||
                               normalizedCatData === selectedCat.toLowerCase();
                    });
                    
                    if (isMatch) {
                        cat.classList.add('active');
                        // Show subcategories for active category
                        const subcategoriesDiv = cat.nextElementSibling;
                        if (subcategoriesDiv && subcategoriesDiv.classList.contains('subcategories')) {
                            subcategoriesDiv.style.display = 'block';
                        }
                        // Force a reflow to ensure the styling is applied
                        void cat.offsetHeight;
                    }
                });
            }

            // First, uncheck all subcategories
            document.querySelectorAll('.course-filter-checkbox').forEach(sub => {
                sub.checked = false;
            });

            // Then check all selected subcategories
            if (subcategoriesParam) {
                const selectedSubcategories = subcategoriesParam.split(',');
                document.querySelectorAll('.course-filter-checkbox').forEach(sub => {
                    if (selectedSubcategories.includes(sub.value)) {
                        sub.checked = true;
                    }
                });
            }
        }

        function getFilterParams(page = 1) {
            const params = new URLSearchParams();
            const searchValue = document.getElementById('search').value.trim();
            const selectedCategories = [];
            const selectedSubcategories = [];
            document.querySelectorAll('.main-category-header.active').forEach(cat => {
                selectedCategories.push(cat.dataset.category);
            });

            document.querySelectorAll('.course-filter-checkbox:checked').forEach(sub => {
                selectedSubcategories.push(sub.value);
            });

            if (searchValue) {
                params.set('search', searchValue);
            }

            // Support multiple categories (comma-separated)
            if (selectedCategories.length > 0) {
                params.set('categories', selectedCategories.join(','));
            }

            // Support multiple subcategories (comma-separated)
            if (selectedSubcategories.length > 0) {
                params.set('subcategories', selectedSubcategories.join(','));
            }

            params.set('page', page);

            return params;
        }

        // Function to load products via AJAX
        // Add a flag to prevent multiple simultaneous requests
        let isLoading = false;

        function loadProducts(page = 1) {
            // Prevent multiple simultaneous requests
            if (isLoading) {
                return;
            }
            
            isLoading = true;
            
            // Prevent the global loader from showing
            if (window.STILoaderState) {
                window.STILoaderState.isNavigating = false;
            }
            
            const params = getFilterParams(page);

            // Show loading state
            productsGrid.style.opacity = '0.5';
            productsGrid.style.pointerEvents = 'none';
            
            // Add loading spinner as overlay at the top inside products-grid
            let loadingOverlay = document.querySelector('.ajax-loading-overlay');
            if (!loadingOverlay) {
                loadingOverlay = document.createElement('div');
                loadingOverlay.className = 'ajax-loading-overlay';
                loadingOverlay.innerHTML = '<div class="spinner"></div>';
                // Append to products-grid container (will be positioned absolutely)
                productsGrid.appendChild(loadingOverlay);
            }
            loadingOverlay.style.display = 'flex';

            // Make AJAX request
            const fetchUrl = 'ajax_load_products.php?' + params.toString();
            
            fetch(fetchUrl)
                .then(response => {
                    if (!response.ok) {
                        return response.text().then(text => {
                            throw new Error(`HTTP error! status: ${response.status}`);
                        });
                    }
                    
                    // Check if response is JSON
                    const contentType = response.headers.get('content-type');
                    
                    if (!contentType || !contentType.includes('application/json')) {
                        return response.text().then(text => {
                            throw new Error('Server returned non-JSON response');
                        });
                    }
                    
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        // Update products grid
                        productsGrid.innerHTML = data.html;
                        
                        // Update or remove pagination
                        const existingPagination = mainContent.querySelector('.pagination-container');
                        if (existingPagination) {
                            existingPagination.remove();
                        }
                        
                        if (data.pagination) {
                            mainContent.insertAdjacentHTML('beforeend', data.pagination);
                            
                            // Add click handlers to pagination links
                            attachPaginationHandlers();
                        }
                        
                        // Reinitialize lazy loading for new images
                        initLazyLoading();
                        
                        // Reattach cart button event listeners for dynamically loaded products
                        attachCartEventListeners();
                        
                        // Scroll to top of products grid smoothly
                        productsGrid.scrollIntoView({ behavior: 'smooth', block: 'start' });
                        
                        // Update URL without reloading
                        const newUrl = window.location.pathname + '?' + params.toString();
                        window.history.pushState({}, '', newUrl);
                        
                        // Restore filter state from the new URL
                        restoreFilterState();
                        
                    } else {
                        // Server returned error
                        const errorMsg = data.error || 'Unknown server error';
                        const debugMsg = data.debug ? '\n\nDebug: ' + data.debug : '';
                        throw new Error(errorMsg + debugMsg);
                    }
                })
                .catch(error => {
                    console.error('Error loading products:', error);
                    
                    // Show user-friendly error message in the products grid
                    productsGrid.innerHTML = `
                        <div class="error-message" style="
                            grid-column: 1 / -1;
                            text-align: center;
                            padding: 3rem 1rem;
                            background: #fff3cd;
                            border: 2px solid #ffc107;
                            border-radius: 8px;
                            margin: 2rem auto;
                            max-width: 600px;
                        ">
                            <i class="fas fa-exclamation-triangle" style="font-size: 3rem; color: #ffc107; margin-bottom: 1rem;"></i>
                            <h3 style="color: #856404; margin-bottom: 0.5rem;">Error Loading Products</h3>
                            <p style="color: #856404; margin-bottom: 1rem;">${error.message || 'An unexpected error occurred'}</p>
                            <button onclick="loadProducts(1)" style="
                                background: #007bff;
                                color: white;
                                border: none;
                                padding: 0.75rem 1.5rem;
                                border-radius: 4px;
                                cursor: pointer;
                                font-size: 1rem;
                            ">
                                <i class="fas fa-redo"></i> Try Again
                            </button>
                        </div>
                    `;
                })
                .finally(() => {
                    // Reset loading flag
                    isLoading = false;
                    
                    // Remove loading state
                    productsGrid.style.opacity = '1';
                    productsGrid.style.pointerEvents = 'auto';
                    
                    // Hide loading overlay with a small delay to ensure visibility
                    setTimeout(() => {
                        // Hide the global page loader if it's showing
                        if (window.STILoader) {
                            window.STILoader.hide();
                        }
                        
                        // Remove AJAX loading overlay
                        const loadingOverlay = document.querySelector('.ajax-loading-overlay');
                        if (loadingOverlay) {
                            loadingOverlay.remove();
                        }
                        
                        // Remove loading class from search container
                        const searchContainer = document.querySelector('.search-container');
                        if (searchContainer) {
                            searchContainer.classList.remove('loading');
                        }
                    }, 100);
                });
        }

        // Function to attach pagination click handlers
        function attachPaginationHandlers() {
            const paginationLinks = document.querySelectorAll('.pagination-number[data-page], .pagination-btn[data-page]');
            
            paginationLinks.forEach(link => {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    const page = parseInt(this.dataset.page);
                    loadProducts(page);
                });
            });
        }

        // Search functionality
        function handleSearch() {
            loadProducts(1);
        }

        // Event listeners for search button
        if (searchBtn) {
            searchBtn.addEventListener('click', handleSearch);
        }

        // Category filter functionality - Multi-select support
        categoryFilters.forEach(category => {
            category.addEventListener('click', function(e) {
                // Toggle active state for this category
                this.classList.toggle('active');
                
                // Toggle subcategories visibility
                const subcategoriesDiv = this.nextElementSibling;
                if (subcategoriesDiv && subcategoriesDiv.classList.contains('subcategories')) {
                    if (this.classList.contains('active')) {
                        // Expanding category - show subcategories
                        subcategoriesDiv.style.display = 'block';
                    } else {
                        // Collapsing category - hide subcategories and uncheck all of them
                        subcategoriesDiv.style.display = 'none';
                        
                        // Uncheck all subcategory checkboxes under this category
                        const subcategoryCheckboxes = subcategoriesDiv.querySelectorAll('.course-filter-checkbox');
                        subcategoryCheckboxes.forEach(checkbox => {
                            checkbox.checked = false;
                        });
                    }
                }
                
                loadProducts(1);
            });
        });

        // Subcategory filter functionality
        subcategoryFilters.forEach(subcategory => {
            subcategory.addEventListener('change', function() {
                loadProducts(1);
            });
        });

        // Clear filters functionality
        clearFiltersBtn.addEventListener('click', function() {
            // Clear all filters
            document.getElementById('search').value = '';
            document.querySelectorAll('.main-category-header.active').forEach(cat => {
                cat.classList.remove('active');
                // Hide subcategories when clearing
                const subcategoriesDiv = cat.nextElementSibling;
                if (subcategoriesDiv && subcategoriesDiv.classList.contains('subcategories')) {
                    subcategoriesDiv.style.display = 'none';
                }
            });
            document.querySelectorAll('.course-filter-checkbox:checked').forEach(sub => {
                sub.checked = false;
            });
            
            // Load products with cleared filters
            loadProducts(1);
        });

        // Attach pagination handlers for initial page load
        attachPaginationHandlers();
        
        // Restore filter state from URL on initial load
        restoreFilterState();
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
    let currentProduct = null;
    
    // Modal functions - defined globally so they can be called from anywhere
    function openModal() {
        const modal = document.getElementById('sizeModal');
        modal.classList.add('show');
    }
    
    function closeModal() {
        const modal = document.getElementById('sizeModal');
        modal.classList.remove('show');
    }
    
    function showSizeModal(element) {
            const productContainer = element.closest('.product-container');
            const category = productContainer.dataset.category;

            currentProduct = {
                itemCode: productContainer.dataset.itemCode,
                name: productContainer.dataset.itemName,
                sizes: productContainer.dataset.sizes.split(','),
                prices: productContainer.dataset.prices.split(','),
                stocks: productContainer.dataset.stocks.split(','),
                itemCodes: productContainer.dataset.itemCodes ? productContainer.dataset.itemCodes.split(',') : [],
                image: productContainer.querySelector('img').src,
                category: category,
                stock: productContainer.dataset.stock
            };

            // Update modal content
            document.getElementById('modalProductImage').src = currentProduct.image;
            document.getElementById('modalProductName').textContent = currentProduct.name;
            document.getElementById('modalProductPrice').textContent = `Price Range: ₱${Math.min(...currentProduct.prices.map(Number)).toFixed(2)} - ₱${Math.max(...currentProduct.prices.map(Number)).toFixed(2)}`;
            document.getElementById('modalProductStock').textContent = `Total Stock: ${currentProduct.stock}`;

            // Generate size options
            const sizeOptionsContainer = document.querySelector('.size-options');
            sizeOptionsContainer.innerHTML = '';

            let allSizes = ['XS', 'S', 'M', 'L', 'XL', 'XXL', '3XL', '4XL', '5XL', '6XL', '7XL'];

            // If 'One Size' is present, add it to the front
            if (currentProduct.sizes.some(s => s.trim().toLowerCase() === 'one size')) {
                allSizes = ['One Size', ...allSizes];
            }

            allSizes.forEach(size => {
                const sizeBtn = document.createElement('div');
                sizeBtn.className = 'size-option';
                sizeBtn.textContent = size;

                const idx = currentProduct.sizes.findIndex(s => s.trim().toLowerCase() === size.trim().toLowerCase());
                const stock = idx >= 0 ? parseInt(currentProduct.stocks[idx]) || 0 : 0;
                const itemCode = idx >= 0 && currentProduct.itemCodes[idx] ? currentProduct.itemCodes[idx] : currentProduct.itemCode;
                const price = idx >= 0 && currentProduct.prices[idx] ? currentProduct.prices[idx] : '';

                sizeBtn.dataset.stock = stock;
                sizeBtn.dataset.itemCode = itemCode;
                sizeBtn.dataset.price = price;

                if (stock > 0) {
                    sizeBtn.classList.add('available');
                    sizeBtn.onclick = () => selectSize(sizeBtn);
                } else {
                    sizeBtn.classList.add('unavailable');
                }

                sizeOptionsContainer.appendChild(sizeBtn);
            });

            // Reset quantity input
            const quantityInput = document.getElementById('quantity');
            quantityInput.value = 1;
            quantityInput.min = 1;

            openModal();
    }

    function showAccessoryModal(element) {
        const productContainer = element.closest('.product-container');
        const price = productContainer.dataset.prices.split(',')[0];
        const stock = productContainer.dataset.stock;

        currentProduct = {
            itemCode: productContainer.dataset.itemCode,
            name: productContainer.dataset.itemName,
            price: price,
            stock: stock,
            image: productContainer.querySelector('img').src,
            category: productContainer.dataset.category
        };

        // Update modal content
        document.getElementById('accessoryModalImage').src = currentProduct.image;
        document.getElementById('accessoryModalName').textContent = currentProduct.name;
        document.getElementById('accessoryModalPrice').textContent = `Price: ₱${parseFloat(currentProduct.price).toFixed(2)}`;
        document.getElementById('accessoryModalStock').textContent = `Stock: ${currentProduct.stock}`;

        // Set max quantity
        const accessoryQuantityInput = document.getElementById('accessoryQuantity');
        accessoryQuantityInput.max = currentProduct.stock;
        accessoryQuantityInput.value = 1;

        const accessoryModal = document.getElementById('accessoryModal');
        accessoryModal.style.display = 'block';
    }
    
    // Function to attach cart event listeners (reusable for AJAX-loaded content)
    function attachCartEventListeners() {
        document.querySelectorAll('.cart').forEach(button => {
            // Remove existing listeners by cloning
            const newButton = button.cloneNode(true);
            button.parentNode.replaceChild(newButton, button);
            
            newButton.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                // Check if user is logged in
                if (!window.isLoggedIn) {
                    window.location.href = 'login.php';
                    return;
                }
                
                // Get product container and category
                const productContainer = this.closest('.product-container');
                const category = productContainer.dataset.category;

                // Check if it's an accessory
                if (category && (category.toLowerCase().includes('accessories') || category.toLowerCase().includes('sti-accessories'))) {
                    showAccessoryModal(this);
                } else {
                    showSizeModal(this);
                }
            });
        });
    }
    
    document.addEventListener('DOMContentLoaded', function() {
        const modal = document.getElementById('sizeModal');
        const accessoryModal = document.getElementById('accessoryModal');
        const closeBtn = modal.querySelector('.close');
        
        // Attach cart event listeners on page load
        attachCartEventListeners();
        
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
    
    // Size selection function
    function selectSize(sizeBtn) {
        // Only allow selection if size is available
        if (sizeBtn.classList.contains('unavailable')) {
            return;
        }
        
        // Remove active class from all size options
        document.querySelectorAll('.size-option').forEach(btn => {
            btn.classList.remove('active');
            btn.classList.remove('selected');
        });
        
        // Add active class to selected size
        sizeBtn.classList.add('active');
        sizeBtn.classList.add('selected');
        
        // Update stock and price display for the selected size
        const stock = sizeBtn.dataset.stock;
        const price = sizeBtn.dataset.price;
        
        document.getElementById('modalProductStock').textContent = `Stock: ${stock}`;
        document.getElementById('modalProductPrice').textContent = `Price: ₱${parseFloat(price).toFixed(2)}`;
        
        // Update quantity input max based on selected size stock
        const quantityInput = document.getElementById('quantity');
        quantityInput.max = parseInt(stock);
        
        // If current quantity exceeds new max, reset to max
        if (parseInt(quantityInput.value) > parseInt(stock)) {
            quantityInput.value = stock;
        }
    }
    
    // Quantity control functions
    function incrementQuantity() {
        const input = document.getElementById('quantity');
        const max = parseInt(input.max) || 999;
        const current = parseInt(input.value) || 0;
        if (current < max) {
            input.value = current + 1;
        }
    }
    
    function decrementQuantity() {
        const input = document.getElementById('quantity');
        const min = parseInt(input.min) || 1;
        const current = parseInt(input.value) || 0;
        if (current > min) {
            input.value = current - 1;
        }
    }
    
    function incrementAccessoryQuantity() {
        const input = document.getElementById('accessoryQuantity');
        const max = parseInt(input.max) || 999;
        const current = parseInt(input.value) || 0;
        if (current < max) {
            input.value = current + 1;
        }
    }
    
    function decrementAccessoryQuantity() {
        const input = document.getElementById('accessoryQuantity');
        const min = 1;
        const current = parseInt(input.value) || 0;
        if (current > min) {
            input.value = current - 1;
        }
    }
    
    function closeAccessoryModal() {
        document.getElementById('accessoryModal').style.display = 'none';
    }
    
    // Add to cart functions
    function addToCartWithSize() {
        const selectedSize = document.querySelector('.size-option.active');
        if (!selectedSize) {
            showNotification('Please select a size', 'warning');
            return;
        }

        const quantityInput = document.getElementById('quantity');
        const quantity = parseInt(quantityInput.value);

        if (!quantity || quantity <= 0) {
            showNotification('Please enter a valid quantity', 'warning');
            return;
        }

        const availableStock = parseInt(selectedSize.dataset.stock);
        if (quantity > availableStock) {
            showNotification(`Sorry, only ${availableStock} items are available in stock for this size.`, 'error');
            return;
        }

        const itemCode = selectedSize.dataset.itemCode;
        const size = selectedSize.textContent;

        addToCart({
            itemCode: itemCode,
            size: size,
            quantity: quantity
        });

        // Close modal
        document.getElementById('sizeModal').classList.remove('show');
        quantityInput.value = 1;
    }
    
    function addAccessoryToCart() {
        const quantityInput = document.getElementById('accessoryQuantity');
        const quantity = parseInt(quantityInput.value);

        if (!quantity || quantity <= 0) {
            showNotification('Please enter a valid quantity', 'warning');
            return;
        }

        const availableStock = parseInt(currentProduct.stock);
        if (quantity > availableStock) {
            showNotification(`Sorry, only ${availableStock} items are available in stock.`, 'error');
            return;
        }

        addToCart({
            itemCode: currentProduct.itemCode,
            quantity: quantity,
            size: 'One Size'
        });

        closeAccessoryModal();
    }
    
    async function addToCart(customData) {
        try {
            const formData = new URLSearchParams();
            formData.append('action', 'add');
            formData.append('item_code', customData.itemCode);
            formData.append('quantity', customData.quantity);
            formData.append('size', customData.size || '');

            const response = await fetch('../Includes/cart_operations.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: formData.toString()
            });

            const data = await response.json();

            if (data.success) {
                // Update cart count in header
                const cartCount = document.querySelector('.cart-count');
                if (cartCount && typeof data.cart_count !== 'undefined') {
                    cartCount.textContent = data.cart_count;
                    cartCount.style.display = Number(data.cart_count) > 0 ? 'flex' : 'none';
                }

                showNotification('Item added to cart successfully!', 'success', { autoClose: 2000 });
            } else {
                showNotification(data.message || 'Error adding item to cart', 'error');
            }
        } catch (error) {
            console.error('Error:', error);
            showNotification('Error adding item to cart', 'error');
        }
    }
    </script>
</body>

</html>
<?php
// Flush the output buffer to prevent chunked encoding issues
ob_end_flush();
?>