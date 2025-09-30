<?php 
include '../Includes/Header.php';
include '../Includes/loader.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About</title>
    <link rel="stylesheet" href="../CSS/global.css">
    <link rel="stylesheet" href="../CSS/about.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Anton&family=Smooch+Sans:wght@100..900&display=swap" rel="stylesheet">
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            overflow-x: hidden;
            width: 100%;
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
    </style>
</head>
<body>

    <div class="about-wrapper">
        <div class="hero-section" data-aos="fade-up">
            <div class="hero-content">
                <h1>About PAMO</h1>
                <p class="subtitle">Streamlining Inventory Management and Student Access</p>
            </div>
        </div>

        <div class="about-container">
            <section class="content-section" data-aos="fade-up">
                <div class="section-header">
                    <i class="fas fa-scroll"></i>
                    <h2>What is PAMO?</h2>
                </div>
                <div class="section-content">
                    <p>PAMO is a web-based platform designed to bridge the gap between inventory management and student accessibility. It functions as an inventory system for the purchasing officer, allowing him to track, organize, and manage items efficiently. At the same time, it serves as a catalog for students, giving them the ability to browse available items and place pre-orders.</p>
                    <p>The key feature that connects both sides is the Pre-Order System. Students can submit requests for items, which the Purchasing Officer can review, approve, or reject—ensuring a smooth, transparent, and organized transaction flow between the two user groups.</p>
                </div>
            </section>

            <section class="content-section benefits-section" data-aos="fade-up">
                <div class="section-header">
                    <i class="fas fa-star"></i>
                    <h2>Why Choose PAMO?</h2>
                </div>
                <div class="benefits-grid">
                    <div class="benefit-card" data-aos="zoom-in">
                        <i class="fas fa-user-friends"></i>
                        <h3>Simple & Intuitive Interface</h3>
                        <p>Designed for ease of use by both Purchasing Officer and students, PAMO is user-friendly and requires minimal training.</p>
                    </div>
                    <div class="benefit-card" data-aos="zoom-in" data-aos-delay="100">
                        <i class="fas fa-chart-line"></i>
                        <h3>Efficient Inventory Tracking</h3>
                        <p>Purchasing Officer can monitor item availability in real time and keep stock organized, reducing errors and overstocking.</p>
                    </div>
                    <div class="benefit-card" data-aos="zoom-in" data-aos-delay="200">
                        <i class="fas fa-shopping-cart"></i>
                        <h3>Streamlined Pre-Order System</h3>
                        <p>Students can request items without physical queues or paperwork.Purchasing Officer get a centralized dashboard to manage all requests.</p>
                    </div>
                    <div class="benefit-card" data-aos="zoom-in" data-aos-delay="400">
                        <i class="fas fa-graduation-cap"></i>
                        <h3>Custom-Built for Academic Use</h3>
                        <p>PAMO was created with students and school Purchasing Officer in mind—tailored for campus inventory and request workflows.</p>
                    </div>
                </div>
            </section>

            <section class="content-section mission-section" data-aos="fade-up">
                <div class="section-header">
                    <i class="fas fa-bullseye"></i>
                    <h2>Our Mission & Vision</h2>
                </div>
                <div class="mission-grid">
                    <div class="mission-card" data-aos="fade-right">
                        <h3>Mission</h3>
                        <p>To provide a seamless platform that simplifies inventory management and improves the way students access school items, making the process more organized, digital, and efficient.</p>
                    </div>
                    <div class="mission-card" data-aos="fade-left">
                        <h3>Vision</h3>
                        <p>To become the leading digital solution for school inventory and student engagement, fostering a more connected and responsive academic community.</p>
                    </div>
                </div>
            </section>

            <section class="content-section guide-section" data-aos="fade-up">
                <div class="section-header">
                    <i class="fas fa-book"></i>
                    <h2>How to Use PAMO</h2>
                </div>
                <div class="guide-grid">
                    <div class="guide-card student-guide" data-aos="fade-right">
                        <div class="guide-header">
                            <i class="fas fa-user-graduate"></i>
                            <h3>For Students</h3>
                        </div>
                        <ul>
                            <li><i class="fas fa-search"></i> Browse the Catalog – View all available items through the catalog interface.</li>
                            <li><i class="fas fa-file-alt"></i> Submit a Pre-Order – Select an item and fill out the pre-order form.</li>
                            <li><i class="fas fa-tasks"></i> Track Your Request – Get real-time updates on whether your order is approved or rejected.</li>
                        </ul>
                    </div>
                    <div class="guide-card admin-guide" data-aos="fade-left">
                        <div class="guide-header">
                            <i class="fas fa-user-tie"></i>
                            <h3>For The Purchasing Officer</h3>
                        </div>
                        <ul>
                            <li><i class="fas fa-boxes"></i> Manage Inventory – Add, update, or remove items from the system.</li>
                            <li><i class="fas fa-clipboard-list"></i> Review Pre-Orders – See all incoming requests from students.</li>
                            <li><i class="fas fa-check-circle"></i> Approve or Reject Requests – Update the status of each pre-order and notify students automatically.</li>
                        </ul>
                    </div>
                </div>
            </section>
        </div>
    </div>

    <?php include '../Includes/footer.php'; ?>

    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>
        AOS.init({
            duration: 800,
            once: true
        });
    </script>
</body>
</html> 