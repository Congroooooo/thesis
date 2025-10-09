<?php
include("../Includes/Header.php");
include("../Includes/connection.php");
include("../Includes/loader.php");
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <link rel="stylesheet" href="../CSS/global.css">
    <link rel="stylesheet" href="../CSS/ProHome.css">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Anton&family=Smooch+Sans:wght@100..900&display=swap"
        rel="stylesheet">
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <title>Homepage</title>
    <style>
        /* Enhanced styles for new arrivals display */
        .display-carousel-card {
            position: relative;
            overflow: hidden;
        }
        
        .card-overlay {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: linear-gradient(transparent, rgba(0,0,0,0.8));
            color: white;
            padding: 20px 15px 15px;
            transform: translateY(100%);
            transition: transform 0.3s ease;
        }
        
        .display-carousel-card:hover .card-overlay {
            transform: translateY(0);
        }
        
        .card-info h4 {
            margin: 0 0 5px 0;
            font-size: 1.1rem;
            font-weight: 600;
        }
        
        .card-category {
            margin: 0 0 5px 0;
            font-size: 0.85rem;
            opacity: 0.9;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .card-price {
            margin: 0;
            font-size: 1rem;
            font-weight: 700;
            color: #ffd700;
        }
        
        .new-badge {
            position: absolute;
            top: -20px;
            right: 15px;
            background: #ff4444;
            color: white;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            box-shadow: 0 2px 8px rgba(255, 68, 68, 0.3);
        }
        
        .no-items-message {
            grid-column: 1 / -1;
            background: #f8f9fa;
            border-radius: 12px;
            border: 2px dashed #dee2e6;
        }
        
        .no-items-message .fas {
            color: #6c757d;
        }
        
        /* Enhanced preorder section styles */
        .preorder-badges {
            position: absolute;
            top: 8px;
            right: 8px;
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        
        .badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
            color: white;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
        }
        
        .badge-new {
            background: linear-gradient(135deg, #ff4444, #cc0000);
        }
        
        .badge-popular {
            background: linear-gradient(135deg, #ff9800, #f57c00);
        }
        
        .pre-order-product-category {
            font-size: 0.8rem;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 5px;
        }
        
        .request-count {
            font-size: 0.75rem;
            color: #28a745;
            font-weight: 600;
            margin-top: 5px;
        }
        
        .pre-order-product-card {
            position: relative;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .pre-order-product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }
        
        .pre-order-btn {
            transition: all 0.3s ease;
        }
        
        .pre-order-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 123, 255, 0.3);
        }
    </style>
</head>

<body>

    <section class="Hero">
        <div class="hero-slideshow">
            <div class="hero-slide"
                style="background-image:url('../Images/ACS ALL.jpg')">
            </div>
            <div class="hero-slide"
                style="background-image:url('../Images/college1.jpg')">
            </div>
            <div class="hero-slide"
                style="background-image:url('../Images/SHS2.jpg')">
            </div>
            <div class="hero-slide"
                style="background-image:url('../Images/SHS_cover_photo.jpg')">
            </div>
            <div class="hero-slide"
                style="background-image:url('../Images/college2.jpg')">
            </div>
        </div>
        <div class="hero-content">
            <?php if (isset($_SESSION['user_id'])): ?>
                <div class="welcome-message" id="welcomeMessage">
                    Welcome, <?php echo htmlspecialchars($_SESSION['last_name']); ?>
                    (<?php echo htmlspecialchars($_SESSION['role_category']); ?>)
                </div>
            <?php endif; ?>
            <h1>GEAR UP</h1>
            <p>Your one-stop shop for all STI College Lucena essentials</p>
            <?php if (isset($_SESSION['user_id'])): ?>
                <a href="ProItemList.php"><button class="shop-now-button">Order Now</button></a>
            <?php else: ?>
                <a href="login.php?redirect=ProItemList.php"><button class="shop-now-button">Order Now</button></a>
            <?php endif; ?>
        </div>
    </section>

    <section class="New-Arrivals">
        <div class="section-header">
            <h2>Item Categories</h2>
            <p class="section-subtitle">Browse through our product categories</p>
        </div>
        <div class="categories-carousel-wrapper">
            <button class="carousel-btn prev-btn" id="categoriesPrevBtn">
                <i class="fas fa-chevron-left"></i>
            </button>
            <div class="categories-carousel-container" id="categoriesCarousel">
                <div class="categories-carousel-track" id="categoriesTrack">
                    <?php
                    // Fetch categories from database with random product images
                    $categoriesQuery = "SELECT id, name, has_subcategories FROM categories ORDER BY name ASC";
                    $categoriesResult = $conn->query($categoriesQuery);
                    
                    $categories = [];
                    $index = 0;
                    
                    while ($category = $categoriesResult->fetch(PDO::FETCH_ASSOC)) {
                        $categoryId = $category['id'];
                        $categoryName = $category['name'];
                        
                        // Get a random product image from this category
                        $imageQuery = "SELECT image_path, item_name 
                                      FROM inventory 
                                      WHERE category_id = ? 
                                      AND image_path IS NOT NULL 
                                      AND image_path != '' 
                                      AND status = 'in stock'
                                      ORDER BY RAND() 
                                      LIMIT 1";
                        
                        $imageStmt = $conn->prepare($imageQuery);
                        $imageStmt->execute([$categoryId]);
                        $imageResult = $imageStmt->fetch(PDO::FETCH_ASSOC);
                        
                        // Fallback to category name search if no image with category_id
                        if (!$imageResult) {
                            $fallbackQuery = "SELECT image_path, item_name 
                                            FROM inventory 
                                            WHERE category = ? 
                                            AND image_path IS NOT NULL 
                                            AND image_path != '' 
                                            AND status = 'in stock'
                                            ORDER BY RAND() 
                                            LIMIT 1";
                            
                            $fallbackStmt = $conn->prepare($fallbackQuery);
                            $fallbackStmt->execute([$categoryName]);
                            $imageResult = $fallbackStmt->fetch(PDO::FETCH_ASSOC);
                        }
                        
                        $imagePath = 'uploads/itemlist/default.png'; // default fallback
                        if ($imageResult) {
                            $rawPath = $imageResult['image_path'];
                            
                            // Clean up image path
                            if (strpos($rawPath, 'uploads/') === 0) {
                                $imagePath = $rawPath;
                            } else if (strpos($rawPath, 'uploads/') === false) {
                                $imagePath = 'uploads/itemlist/' . $rawPath;
                            } else {
                                $imagePath = $rawPath;
                            }
                            
                            // Verify file exists
                            if (!file_exists('../' . $imagePath)) {
                                $altPath = 'uploads/itemlist/' . basename($rawPath);
                                if (file_exists('../' . $altPath)) {
                                    $imagePath = $altPath;
                                } else {
                                    $imagePath = 'uploads/itemlist/default.png';
                                }
                            }
                        }
                        
                        $title = htmlspecialchars($categoryName);
                        $isActive = $index === 0 ? 'active' : '';
                        
                        echo '<div class="category-card ' . $isActive . '" data-aos="fade-up" data-aos-delay="' . ($index * 100) . '">';
                        echo '  <div class="category-image-wrapper">';
                        echo '    <img src="../' . htmlspecialchars($imagePath) . '" alt="' . $title . '" draggable="false" />';
                        echo '    <div class="category-overlay">';
                        echo '      <div class="category-content">';
                        echo '        <h3>' . $title . '</h3>';
                        echo '        <a href="ProItemList.php?category=' . urlencode($title) . '" class="category-btn">View Items</a>';
                        echo '      </div>';
                        echo '    </div>';
                        echo '  </div>';
                        echo '  <div class="category-title">' . $title . '</div>';
                        echo '</div>';
                        
                        $index++;
                    }
                    ?>
                </div>
            </div>
            <button class="carousel-btn next-btn" id="categoriesNextBtn">
                <i class="fas fa-chevron-right"></i>
            </button>
        </div>
        
        <!-- Carousel Indicators -->
        <div class="carousel-indicators" id="categoriesIndicators">
            <?php
            for($i = 0; $i < count($categories); $i++) {
                $activeClass = $i === 0 ? 'active' : '';
                echo '<div class="indicator ' . $activeClass . '" data-slide="' . $i . '"></div>';
            }
            ?>
        </div>
    </section>

    <section class="Display">
        <div class="container">
            <div class="section-header">
                <h2>New Arrivals</h2>
                <p>Discover our latest inventory additions</p>
            </div>
            <div class="display-carousel-wrapper">
                <div class="display-carousel-track" id="displayCarouselTrack">
                    <?php
                    // Fetch newest inventory items for display
                    $newItemsQuery = "
                        SELECT DISTINCT
                            i.item_name,
                            i.image_path,
                            i.price,
                            i.category,
                            i.created_at,
                            SUBSTRING_INDEX(i.item_code, '-', 1) as base_code
                        FROM inventory i
                        WHERE i.status = 'in stock'
                        AND i.image_path IS NOT NULL 
                        AND i.image_path != ''
                        AND i.actual_quantity > 0
                        GROUP BY SUBSTRING_INDEX(i.item_code, '-', 1)
                        ORDER BY i.created_at DESC, i.id DESC
                        LIMIT 8
                    ";
                    
                    $newItemsResult = $conn->query($newItemsQuery);
                    $cards = [];
                    
                    while($row = $newItemsResult->fetch(PDO::FETCH_ASSOC)) {
                        $imagePath = $row['image_path'];
                        
                        // Process image path to ensure it's properly formatted
                        if (!empty($imagePath)) {
                            if (strpos($imagePath, 'uploads/') === 0) {
                                $resolvedPath = $imagePath;
                            } else if (strpos($imagePath, 'uploads/') === false) {
                                $resolvedPath = 'uploads/itemlist/' . $imagePath;
                            } else {
                                $resolvedPath = $imagePath;
                            }
                            
                            // Verify file exists
                            if (!file_exists('../' . $resolvedPath)) {
                                $altPath = 'uploads/itemlist/' . basename($imagePath);
                                if (file_exists('../' . $altPath)) {
                                    $resolvedPath = $altPath;
                                } else {
                                    $resolvedPath = 'uploads/itemlist/default.png';
                                }
                            }
                        } else {
                            $resolvedPath = 'uploads/itemlist/default.png';
                        }
                        
                        $img = '../' . htmlspecialchars($resolvedPath);
                        $title = htmlspecialchars($row['item_name']);
                        $cards[] = [
                            'img' => $img, 
                            'title' => $title,
                            'category' => htmlspecialchars($row['category']),
                            'price' => number_format($row['price'], 2),
                            'is_new' => (strtotime($row['created_at']) > strtotime('-7 days'))
                        ];
                    }
                    
                    // If no items found, show a message
                    if (empty($cards)) {
                        echo '<div class="no-items-message" style="text-align: center; padding: 40px; color: #666;">';
                        echo '  <i class="fas fa-box-open" style="font-size: 48px; margin-bottom: 15px;"></i>';
                        echo '  <p style="font-size: 1.1rem; margin: 0;">No new items available yet</p>';
                        echo '  <p style="font-size: 0.9rem; margin: 5px 0 0 0;">New inventory items will appear here automatically</p>';
                        echo '</div>';
                    } else {
                        // Output the cards twice for seamless looping
                        foreach (array_merge($cards, $cards) as $card) {
                            echo '<div class="display-carousel-card">';
                            echo '  <img src="' . $card['img'] . '" alt="' . $card['title'] . '" draggable="false" />';
                            echo '  <div class="card-overlay">';
                            echo '    <div class="card-info">';
                            echo '      <h4>' . $card['title'] . '</h4>';
                            echo '      <p class="card-category">' . $card['category'] . '</p>';
                            echo '      <p class="card-price">₱' . $card['price'] . '</p>';
                            if ($card['is_new']) {
                                echo '      <span class="new-badge">New</span>';
                            }
                            echo '    </div>';
                            echo '  </div>';
                            echo '</div>';
                        }
                    }
                    ?>
                </div>
            </div>
            <div class="display-order-btn-container" style="text-align:center; margin-top: 20px;">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <a href="ProItemList.php"><button class="shop-now-button" style="color: yellow; background: #0072BC;">Order Now</button></a>
                <?php else: ?>
                    <a href="login.php?redirect=ProItemList.php"><button class="shop-now-button" style="color: yellow; background: #0072BC;">Order Now</button></a>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <section class="tagline">
        <div class="tag">
            <h1>Be future-ready. Be STI.</h1>
            <p>Explore our wide range of products and check stock availability right from your device.</p>
        </div>
        <div class="sti-frames">
            <div id="letter-s" class="frame"></div>
            <div id="letter-s1" class="frame"></div>
            <div id="letter-s2" class="frame"></div>
            <div id="letter-s3" class="frame"></div>
        </div>
    </section>

    <?php
    // Fetch available preorder items from PAMO system
    $preorderQuery = "
        SELECT 
            pi.id,
            pi.base_item_code,
            pi.item_name,
            pi.price,
            pi.image_path,
            pi.created_at,
            c.name as category_name,
            COALESCE(SUM(CASE WHEN pr.status = 'active' THEN pr.quantity ELSE 0 END), 0) AS total_requests
        FROM preorder_items pi
        LEFT JOIN categories c ON c.id = pi.category_id
        LEFT JOIN preorder_requests pr ON pr.preorder_item_id = pi.id
        WHERE pi.status = 'pending'
        GROUP BY pi.id, pi.base_item_code, pi.item_name, pi.price, pi.image_path, pi.created_at, c.name
        ORDER BY pi.created_at DESC
        LIMIT 8
    ";
    
    $preorderResult = $conn->query($preorderQuery);
    $preorderItems = [];
    
    while ($row = $preorderResult->fetch(PDO::FETCH_ASSOC)) {
        // Process image path
        $imagePath = $row['image_path'];
        $resolvedPath = 'uploads/itemlist/default.png'; // default fallback
        
        if (!empty($imagePath)) {
            if (strpos($imagePath, 'uploads/') === 0) {
                $resolvedPath = $imagePath;
            } else if (strpos($imagePath, 'uploads/preorder/') === false && strpos($imagePath, 'uploads/') === false) {
                $resolvedPath = 'uploads/preorder/' . $imagePath;
            } else {
                $resolvedPath = $imagePath;
            }
            
            // Verify file exists
            if (!file_exists('../' . $resolvedPath)) {
                $altPaths = [
                    'uploads/preorder/' . basename($imagePath),
                    'uploads/itemlist/' . basename($imagePath)
                ];
                
                $found = false;
                foreach ($altPaths as $altPath) {
                    if (file_exists('../' . $altPath)) {
                        $resolvedPath = $altPath;
                        $found = true;
                        break;
                    }
                }
                
                if (!$found) {
                    $resolvedPath = 'uploads/itemlist/default.png';
                }
            }
        }
        
        $preorderItems[] = [
            'id' => $row['id'],
            'base_item_code' => $row['base_item_code'],
            'item_name' => $row['item_name'],
            'price' => floatval($row['price']),
            'image_path' => $resolvedPath,
            'category_name' => $row['category_name'] ?: 'Uncategorized',
            'total_requests' => intval($row['total_requests']),
            'is_popular' => intval($row['total_requests']) > 5,
            'is_new' => (strtotime($row['created_at']) > strtotime('-14 days'))
        ];
    }
    
    // Only show the section if there are preorder items available
    if (!empty($preorderItems)): 
    ?>
    <section class="Pre-order-products">
        <div class="section-header">
            <h2>Items Available to Request for Pre-Order</h2>
            <p class="section-subtitle">These are items you can request in advance. PAMO will consider stocking them!</p>
        </div>
        <div class="pre-order-products-grid-4x2">
            <?php
            foreach ($preorderItems as $item) {
                $img = '../' . htmlspecialchars($item['image_path']);
                $title = htmlspecialchars($item['item_name']);
                $price = number_format($item['price'], 2);
                $baseCode = htmlspecialchars($item['base_item_code']);
                $category = htmlspecialchars($item['category_name']);
                
                echo '<div class="pre-order-product-card" data-preorder-id="' . $item['id'] . '">';
                echo '  <div class="pre-order-product-image">';
                echo '    <img src="' . $img . '" alt="' . $title . '" draggable="false" />';
                
                // Add badges for popular/new items
                if ($item['is_popular'] || $item['is_new']) {
                    echo '    <div class="preorder-badges">';
                    if ($item['is_new']) {
                        echo '      <span class="badge badge-new">New</span>';
                    }
                    if ($item['is_popular']) {
                        echo '      <span class="badge badge-popular">Popular</span>';
                    }
                    echo '    </div>';
                }
                
                echo '  </div>';
                echo '  <div class="pre-order-product-info">';
                echo '    <div class="pre-order-product-title">' . $title . '</div>';
                echo '    <div class="pre-order-product-category">' . $category . '</div>';
                echo '    <div class="pre-order-product-price">₱' . $price . '</div>';
                
                // Show request count if popular
                if ($item['total_requests'] > 0) {
                    echo '    <div class="request-count">' . $item['total_requests'] . ' requests</div>';
                }
                
                // Pre-order button with proper link
                if (isset($_SESSION['user_id'])) {
                    echo '    <button class="pre-order-btn" onclick="window.location.href=\'preorder.php\'">Pre Order</button>';
                } else {
                    echo '    <button class="pre-order-btn" onclick="window.location.href=\'login.php?redirect=preorder.php\'">Pre Order</button>';
                }
                
                echo '  </div>';
                echo '</div>';
            }
            ?>
        </div>
    </section>
    <?php endif; ?>

    <section class="video-showcase">
        <video class="video-background" autoplay loop muted playsinline>
            <source src="../Images/last section.mp4" type="video/mp4">
            Your browser does not support the video tag.
        </video>
        <div class="video-overlay"></div>
        <div class="video-content">
            <div class="text-content">
                <h2>Experience Our Collection</h2>
                <p>Discover the perfect blend of style and professionalism with our exclusive STI uniforms and merchandise.</p>
            </div>
            <?php if (isset($_SESSION['user_id'])): ?>
                <a href="ProItemList.php" class="video-btn">Order Now</a>
            <?php else: ?>
                <a href="login.php?redirect=ProItemList.php" class="video-btn">Order Now</a>
            <?php endif; ?>
        </div>
    </section>

    <?php
    include("../Includes/footer.php");
    ?>
    
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>
        AOS.init({
            duration: 800,
            easing: 'ease-in-out',
            once: false,
            mirror: true
        });
    </script>
    <script>
        // Hero Slideshow
        let heroSlideIndex = 0;
        const heroSlides = document.querySelectorAll('.hero-slide');

        function showHeroSlides() {
            heroSlides.forEach(slide => slide.classList.remove('active'));
            heroSlides[heroSlideIndex].classList.add('active');
            heroSlideIndex = (heroSlideIndex + 1) % heroSlides.length;
        }

        heroSlides[0].classList.add('active');
        setInterval(showHeroSlides, 4000);

        // Categories Carousel (Mobile only)
        let currentSlide = 0;
        const track = document.getElementById('categoriesTrack');
        const cards = document.querySelectorAll('.category-card');
        const indicators = document.querySelectorAll('.indicator');
        const prevBtn = document.getElementById('categoriesPrevBtn');
        const nextBtn = document.getElementById('categoriesNextBtn');

        function isMobile() {
            return window.innerWidth < 768;
        }

        function updateCarousel() {
            if (!isMobile()) return;
            
            cards.forEach((card, index) => {
                card.classList.toggle('active', index === currentSlide);
            });
            
            indicators.forEach((indicator, index) => {
                indicator.classList.toggle('active', index === currentSlide);
            });
            
            const translateX = -currentSlide * 100;
            track.style.transform = `translateX(${translateX}%)`;
        }

        function nextSlide() {
            if (!isMobile()) return;
            currentSlide = (currentSlide + 1) % cards.length;
            updateCarousel();
        }

        function prevSlide() {
            if (!isMobile()) return;
            currentSlide = (currentSlide - 1 + cards.length) % cards.length;
            updateCarousel();
        }

        function goToSlide(slideIndex) {
            if (!isMobile()) return;
            currentSlide = slideIndex;
            updateCarousel();
        }

        // Event Listeners
        if (nextBtn) nextBtn.addEventListener('click', nextSlide);
        if (prevBtn) prevBtn.addEventListener('click', prevSlide);

        // Indicator click events
        indicators.forEach((indicator, index) => {
            indicator.addEventListener('click', () => goToSlide(index));
        });

        // Auto-advance carousel on mobile
        if (isMobile()) {
            setInterval(nextSlide, 5000);
        }

        // Handle window resize
        window.addEventListener('resize', () => {
            if (!isMobile()) {
                track.style.transform = 'translateX(0)';
                cards.forEach(card => card.classList.add('active'));
            } else {
                updateCarousel();
            }
        });

        // Initialize
        updateCarousel();
    </script>
</body>

</html>