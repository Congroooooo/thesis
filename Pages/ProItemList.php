<!DOCTYPE html>
<html lang="en">

<head>
    <link rel="stylesheet" href="../CSS/header.css">
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

    <!-- Add this right after the header section -->
    

    <div class="container">
        <aside class="sidebar" id="sidebar" data-aos="fade-right">
            <span class="close-sidebar" id="closeSidebar">&times;</span>
            <div class="filter-header">
                <span class="filter-label">FILTER</span>
            </div>

            <button class="clear-filters" id="clearFiltersBtn" type="button">Clear Filters</button>

            <?php
            $conn = mysqli_connect("localhost", "root", "", "proware");
            if (!$conn) {
                die("Connection failed: " . mysqli_connect_error());
            }

            // UPDATED SQL: Join inventory with course_item and course, group by inventory.id
            $sql = "SELECT inventory.*, GROUP_CONCAT(DISTINCT course.course_name) AS courses
                    FROM inventory
                    LEFT JOIN course_item ON inventory.id = course_item.inventory_id
                    LEFT JOIN course ON course_item.course_id = course.id
                    GROUP BY inventory.id
                    ORDER BY inventory.created_at DESC";
            $result = mysqli_query($conn, $sql);

            $products = [];

            // Fetch shirt types for STI Shirt filter
            $shirtTypes = [];
            $shirtTypeRes = mysqli_query($conn, "SELECT id, name FROM shirt_type ORDER BY name ASC");
            while ($row = mysqli_fetch_assoc($shirtTypeRes)) {
                $shirtTypes[] = $row;
            }

            while ($row = mysqli_fetch_assoc($result)) {
                $itemCode = $row['item_code'];
                $itemName = $row['item_name'];
                $imagePath = $row['image_path'];
                $itemPrice = $row['price'];
                $itemCategory = $row['category'];
                $sizes = array_map(function($s) { return trim($s); }, explode(',', $row['sizes']));
                $baseItemCode = strtok($itemCode, '-');
                $courses = isset($row['courses']) ? array_map('trim', explode(',', $row['courses'])) : [];

                // Handle both full paths and filenames
                if (!empty($imagePath)) {
                    if (strpos($imagePath, 'uploads/') === false) {
                        $itemImage = '../uploads/itemlist/' . $imagePath;
                    } else {
                        $itemImage = '../' . $imagePath;
                    }
                } else {
                    $itemImage = '';
                }

                $shirtTypeId = '';
                $shirtTypeName = '';
                if ($itemCategory === 'STI-Shirts') {
                    $shirtTypeSql = "SELECT st.id, st.name FROM shirt_type_item sti JOIN shirt_type st ON sti.shirt_type_id = st.id WHERE sti.inventory_id = " . intval($row['id']) . " LIMIT 1";
                    $shirtTypeRes = mysqli_query($conn, $shirtTypeSql);
                    if ($shirtTypeRow = mysqli_fetch_assoc($shirtTypeRes)) {
                        $shirtTypeId = $shirtTypeRow['id'];
                        $shirtTypeName = $shirtTypeRow['name'];
                    }
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
                        'shirt_type_id' => $shirtTypeId,
                        'shirt_type_name' => $shirtTypeName,
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
                    // Fallback: use the first variant's image if this one is missing
                    $variantImage = $itemImage;
                    if (empty($variantImage)) {
                        $variantImage = $products[$baseItemCode]['image'];
                    }
                    $products[$baseItemCode]['variants'][] = [
                        'item_code' => $itemCode,
                        'size' => isset($sizes[0]) ? $sizes[0] : '',
                        'price' => $itemPrice,
                        'stock' => $row['actual_quantity'],
                        'image' => $variantImage
                    ];
                }
            }
            ?>
            
            <?php
            // Build a set of categories that have products
            $categoriesWithProducts = [];
            foreach ($products as $product) {
                $cat = strtolower(str_replace(' ', '-', $product['category']));
                $categoriesWithProducts[$cat] = true;
            }

            // Fetch categories dynamically from the database
            $dynamicCategories = [];
            
            // Get all categories with their subcategories
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
            
            $categoriesResult = mysqli_query($conn, $categoriesQuery);
            
            if ($categoriesResult) {
                while ($row = mysqli_fetch_assoc($categoriesResult)) {
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
            
            // Also get legacy categories from inventory table that might not be in categories table yet
            $legacyCategoriesQuery = "
                SELECT DISTINCT category 
                FROM inventory 
                WHERE category NOT IN (SELECT name FROM categories)
                ORDER BY category ASC
            ";
            
            $legacyResult = mysqli_query($conn, $legacyCategoriesQuery);
            
            if ($legacyResult) {
                while ($row = mysqli_fetch_assoc($legacyResult)) {
                    $dynamicCategories[] = [
                        'id' => null,
                        'name' => $row['category'],
                        'has_subcategories' => false,
                        'subcategories' => [],
                        'is_legacy' => true
                    ];
                }
            }
            
            // Helper function to normalize category names for data attributes
            function normalizeCategoryName($name) {
                return strtolower(str_replace([' ', '_'], '-', $name));
            }
            
            // Debug: Show what categories we found
            echo "<!-- DEBUG: Found " . count($dynamicCategories) . " categories: " . json_encode($dynamicCategories) . " -->";
            ?>
            
            <?php if (count($dynamicCategories) > 0): ?>
                <!-- DEBUG: Categories loaded successfully -->
            <?php else: ?>
                <!-- DEBUG: No categories found, using fallback -->
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
                                                    <?php if ($category['name'] === 'STI-Shirts'): ?>
                                                        <input type="checkbox" class="shirt-type-filter-checkbox" value="<?= htmlspecialchars($subcategory['id']) ?>">
                                                    <?php else: ?>
                                                        <input type="checkbox" class="course-filter-checkbox" value="<?= htmlspecialchars($subcategory['name']) ?>">
                                                    <?php endif; ?>
                                                    <span><?= htmlspecialchars($subcategory['name']) ?></span>
                                                </label>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php elseif ($category['name'] === 'STI-Shirts'): ?>
                                    <!-- Fallback for STI-Shirts using shirt_type table -->
                                    <?php foreach ($shirtTypes as $type): ?>
                                    <div class="course-category">
                                        <div class="course-header">
                                            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                                <input type="checkbox" class="shirt-type-filter-checkbox" value="<?= $type['id'] ?>">
                                                <span><?= htmlspecialchars($type['name']) ?></span>
                                            </label>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <!-- Fallback to hardcoded categories if API fails -->
                    <div class="category-item">
                        <div class="main-category-header" data-category="tertiary-uniform">
                            <span>Tertiary Uniform</span>
                            <i class="fas fa-chevron-down"></i>
                        </div>
                        <div class="subcategories">
                            <?php if (isset($categoriesWithProducts['tertiary-uniform'])): ?>
                            <div class="course-category">
                                <div class="course-header">
                                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                        <input type="checkbox" class="course-filter-checkbox" value="BSCM">
                                        <span>BSCM</span>
                                    </label>
                                </div>
                            </div>
                            <div class="course-category">
                                <div class="course-header">
                                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                        <input type="checkbox" class="course-filter-checkbox" value="BSTM">
                                        <span>BSTM</span>
                                    </label>
                                </div>
                            </div>
                            <div class="course-category">
                                <div class="course-header">
                                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                        <input type="checkbox" class="course-filter-checkbox" value="BSIT/BSCS/BSCPE">
                                        <span>BSIT/BSCS/BSCPE</span>
                                    </label>
                                </div>
                            </div>
                            <div class="course-category">
                                <div class="course-header">
                                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                        <input type="checkbox" class="course-filter-checkbox" value="BSBA">
                                        <span>BSBA</span>
                                    </label>
                                </div>
                            </div>
                            <div class="course-category">
                                <div class="course-header">
                                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                        <input type="checkbox" class="course-filter-checkbox" value="BMMA">
                                        <span>BMMA</span>
                                    </label>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="category-item">
                        <div class="main-category-header" data-category="sti-shirt">
                            <span>STI Shirt</span>
                            <i class="fas fa-chevron-down"></i>
                        </div>
                        <div class="subcategories">
                            <?php foreach ($shirtTypes as $type): ?>
                            <div class="course-category">
                                <div class="course-header">
                                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                        <input type="checkbox" class="shirt-type-filter-checkbox" value="<?= $type['id'] ?>">
                                        <span><?= htmlspecialchars($type['name']) ?></span>
                                    </label>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
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
                    $availableSizes = $product['sizes'];
                    $prices = $product['prices'];
                    $courses = $product['courses'];
                    // Create stocksBySize array with item_code for each size
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
                        data-shirt-type-id="<?php echo htmlspecialchars($product['shirt_type_id'] ?? ''); ?>"
                        data-shirt-type-name="<?php echo htmlspecialchars($product['shirt_type_name'] ?? ''); ?>">
                        <?php
// Find the first non-empty image among variants
$productImage = '';
foreach ($product['variants'] as $variant) {
    if (!empty($variant['image'])) {
        $productImage = $variant['image'];
        break;
    }
}
if (empty($productImage)) {
    $productImage = '../uploads/itemlist/default.png'; // or your default image path
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
                
                <!-- No results message -->
                <div id="no-results-message" class="no-results-message" style="display: none;">
                    <i class="fas fa-search"></i>
                    <h3>No products found</h3>
                    <p>Try adjusting your search terms or filters</p>
                </div>
            </div>
        </main>
    </div>

    <!-- Size Selection Modal -->
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

    <!-- Accessories Quantity Modal -->
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

        // Toggle sidebar on filter button click
        filterToggle.addEventListener('click', function() {
            sidebar.classList.toggle('active');
        });

        // Close sidebar when close button is clicked
        closeSidebar.addEventListener('click', function() {
            sidebar.classList.remove('active');
        });

        // Close sidebar when clicking outside
        document.addEventListener('click', function(e) {
            if (!sidebar.contains(e.target) && !filterToggle.contains(e.target)) {
                sidebar.classList.remove('active');
            }
        });

        // Prevent clicks inside sidebar from closing it
        sidebar.addEventListener('click', function(e) {
            e.stopPropagation();
        });

        // Close sidebar on window resize if screen becomes large enough
        window.addEventListener('resize', function() {
            if (window.innerWidth >= 768) {
                sidebar.classList.remove('active');
            }
        });
    });
    </script>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Get modal elements
        const modal = document.getElementById('sizeModal');
        const closeBtn = modal.querySelector('.close');
        
        // Function to open modal
        function openModal() {
            modal.classList.add('show');
        }
        
        // Function to close modal
        function closeModal() {
            modal.classList.remove('show');
        }
        
        // Add click event to all "ADD TO CART" buttons
        document.querySelectorAll('.cart').forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                openModal();
            });
        });
        
        // Close modal when clicking the close button
        closeBtn.addEventListener('click', closeModal);
        
        // Close modal when clicking outside
        window.addEventListener('click', function(e) {
            if (e.target === modal) {
                closeModal();
            }
        });
        
        // Prevent modal from closing when clicking inside modal content
        modal.querySelector('.modal-content').addEventListener('click', function(e) {
            e.stopPropagation();
        });
    });
    </script>
</body>

</html>