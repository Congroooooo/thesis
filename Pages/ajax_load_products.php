<?php
// AJAX endpoint to load products dynamically
// Returns only the product grid HTML and pagination

// Start output buffering
ob_start();

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

try {
    require_once '../Includes/connection.php';
    require_once '../Includes/image_path_helper.php';
    if (session_status() === PHP_SESSION_NONE) session_start();

    // Get filter and search parameters
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

    // Pagination parameters
    $itemsPerPage = 12;
    $currentPage = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $offset = ($currentPage - 1) * $itemsPerPage;

// Build SQL query with filters
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

// Add course filter
if (!empty($courseFilter)) {
    $whereConditions[] = "FIND_IN_SET(?, REPLACE(i.courses, ' ', '')) > 0";
    $params[] = $courseFilter;
}

// Build WHERE clause
$whereClause = !empty($whereConditions) ? "WHERE " . implode(" AND ", $whereConditions) : "";

// Optimized query with filtering
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

// Prepare and execute query
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

    // Extract subcategories from the grouped result
    $subcats = [];
    if (!empty($row['subcategory_ids'])) {
        $subcats = explode(',', $row['subcategory_ids']);
    }

    // Optimized image resolution logic using helper function
    $itemImage = resolveImagePath($imagePath);

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

// Calculate pagination for filtered results
$totalProducts = count($allProducts);
$totalPages = ceil($totalProducts / $itemsPerPage);

// Get only the products for the current page
$productsForCurrentPage = array_slice($allProducts, $offset, $itemsPerPage, true);
$products = $productsForCurrentPage;

// Build response
$response = [
    'success' => true,
    'html' => '',
    'pagination' => '',
    'totalProducts' => $totalProducts,
    'currentPage' => $currentPage,
    'totalPages' => $totalPages
];

// Generate product grid HTML
ob_start();
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
    
    // Create ordered arrays matching the order of $availableSizes
    $orderedStocks = [];
    $orderedItemCodes = [];
    foreach ($availableSizes as $size) {
        $orderedStocks[] = $stocksBySize[$size] ?? 0;
        $orderedItemCodes[] = $itemCodesBySize[$size] ?? '';
    }
?>
<div class="product-container" 
    data-category="<?php echo strtolower(str_replace(' ', '-', $product['category'])); ?>"
    data-sizes="<?php echo implode(',', $availableSizes); ?>"
    data-prices="<?php echo implode(',', $prices); ?>" 
    data-stocks="<?php echo implode(',', $orderedStocks); ?>"
    data-item-codes="<?php echo implode(',', $orderedItemCodes); ?>"
    data-stock="<?php echo $product['stock']; ?>"
    data-item-code="<?php echo htmlspecialchars($product['variants'][0]['item_code']); ?>"
    data-item-name="<?php echo htmlspecialchars($product['name']); ?>"
    data-courses="<?php echo htmlspecialchars(implode(',', $courses)); ?>"
    data-subcategories="<?php echo htmlspecialchars(implode(',', ($product['subcategories'] ?? []))); ?>">
    <?php
        $productImage = getDefaultImagePath(); // Start with default
        foreach ($product['variants'] as $variant) {
            if (!empty($variant['image'])) {
                $productImage = $variant['image'];
                break;
            }
        }
    ?>
    <img src="data:image/svg+xml;base64,<?php echo base64_encode('<svg width="300" height="300" xmlns="http://www.w3.org/2000/svg"><rect width="300" height="300" fill="#f0f0f0"/></svg>'); ?>" 
         data-src="<?php echo htmlspecialchars($productImage); ?>" 
         alt="<?php echo htmlspecialchars($product['name']); ?>" 
         class="lazy-load-image"
         loading="lazy"
         onerror="this.onerror=null; this.src='<?php echo getDefaultImagePath(); ?>'">
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

<?php if (count($products) === 0): ?>
<div id="no-results-message" class="no-results-message enhanced">
    <i class="fas fa-search"></i>
    <h3>No products found</h3>
    <p>Try adjusting your search terms or filters to find what you're looking for.</p>
</div>
<?php endif; ?>

<?php
$response['html'] = ob_get_clean();

// Generate pagination HTML
ob_start();
if ($totalPages > 1):
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
            <a href="#" class="pagination-btn prev-btn" data-page="<?php echo $currentPage - 1; ?>">
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
                <a href="#" class="pagination-number" data-page="1">1</a>
                <?php if ($startPage > 2): ?>
                    <span class="pagination-dots">...</span>
                <?php endif; ?>
            <?php endif; ?>
            
            <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                <?php if ($i == $currentPage): ?>
                    <span class="pagination-number active"><?php echo $i; ?></span>
                <?php else: ?>
                    <a href="#" class="pagination-number" data-page="<?php echo $i; ?>"><?php echo $i; ?></a>
                <?php endif; ?>
            <?php endfor; ?>
            
            <?php if ($endPage < $totalPages): ?>
                <?php if ($endPage < $totalPages - 1): ?>
                    <span class="pagination-dots">...</span>
                <?php endif; ?>
                <a href="#" class="pagination-number" data-page="<?php echo $totalPages; ?>"><?php echo $totalPages; ?></a>
            <?php endif; ?>
        </div>
        
        <?php if ($currentPage < $totalPages): ?>
            <a href="#" class="pagination-btn next-btn" data-page="<?php echo $currentPage + 1; ?>">
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
<?php 
endif;
$response['pagination'] = ob_get_clean();
    
    // Clean the output buffer before sending JSON
    if (ob_get_level()) {
        ob_end_clean();
    }
    
    // Return JSON response
    header('Content-Type: application/json');
    echo json_encode($response);

} catch (Exception $e) {
    // Clear any output buffer
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    // Return error response
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Server error occurred',
        'debug' => $e->getMessage()
    ]);
}
?>
