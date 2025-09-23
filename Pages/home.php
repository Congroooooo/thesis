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
</head>

<body>
    <?php
    include("../Includes/Header.php");
    include("../Includes/connection.php");
    ?>

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
            <p class="section-subtitle">Check out our latest products</p>
        </div>
        <div class="new-arrivals-container">
            <?php
            $sql = "SELECT image_path, title FROM homepage_content WHERE section='new_arrival' LIMIT 4";
            $result = $conn->query($sql);
            while($row = $result->fetch(PDO::FETCH_ASSOC)) {
                $title = htmlspecialchars($row['title']);
                echo '<div class="new-arrival-item">';
                echo '  <div class="new-arrival-card" data-aos="fade-up">';
                echo '    <div class="new-arrival-image">';
                echo '      <img src="../' . htmlspecialchars($row['image_path']) . '" alt="' . $title . '" draggable="false" />';
                echo '    </div>';
                echo '  </div>';
                echo '  <div class="new-arrival-title">' . $title . '</div>';
                echo '</div>';
            }
            ?>
        </div>
        
    </section>

    <section class="Display">
        <div class="container">
            <div class="section-header">
                <h2>Welcome to STI</h2>
                <p>Discover our latest collection</p>
            </div>
            <div class="display-carousel-wrapper">
                <div class="display-carousel-track" id="displayCarouselTrack">
                    <?php
                    $sql = "SELECT image_path, title FROM homepage_content WHERE section='display'";
                    $result = $conn->query($sql);
                    $cards = [];
                    while($row = $result->fetch(PDO::FETCH_ASSOC)) {
                        $img = '../' . htmlspecialchars($row['image_path']);
                        $title = htmlspecialchars($row['title']);
                        $cards[] = ['img' => $img, 'title' => $title];
                    }
                    // Output the cards twice for seamless looping
                    foreach (array_merge($cards, $cards) as $card) {
                        echo '<div class="display-carousel-card">';
                        echo '  <img src="' . $card['img'] . '" alt="" draggable="false" />';
                        echo '</div>';
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

    <section class="Pre-order-products">
        <div class="section-header">
            <h2>Items Available to Request for Pre-Order</h2>
            <p class="section-subtitle">These are items you can request in advance. And PAMO will consider stocking it!</p>
        </div>
        <div class="pre-order-products-grid-4x2">
            <?php
            $sql = "SELECT * FROM homepage_content WHERE section='pre_order' ORDER BY created_at DESC LIMIT 8";
            $result = $conn->query($sql);
            while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
                $img = '../' . htmlspecialchars($row['image_path']);
                $title = htmlspecialchars($row['title']);
                $price = number_format($row['price'], 2);
                echo '<div class="pre-order-product-card">';
                echo '  <div class="pre-order-product-image"><img src="' . $img . '" alt="' . $title . '" draggable="false" /></div>';
                echo '  <div class="pre-order-product-info">';
                echo '    <div class="pre-order-product-title">' . $title . '</div>';
                echo '    <div class="pre-order-product-price">₱' . $price . '</div>';
                echo '    <button class="pre-order-btn">Pre Order</button>';
                echo '  </div>';
                echo '</div>';
            }
            ?>
        </div>
    </section>

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
        let heroSlideIndex = 0;
        const heroSlides = document.querySelectorAll('.hero-slide');

        function showHeroSlides() {
            heroSlides.forEach(slide => slide.classList.remove('active'));
            heroSlides[heroSlideIndex].classList.add('active');
            heroSlideIndex = (heroSlideIndex + 1) % heroSlides.length;
        }

        heroSlides[0].classList.add('active');
        setInterval(showHeroSlides, 4000);
    </script>
</body>

</html>