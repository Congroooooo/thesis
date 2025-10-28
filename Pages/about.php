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
                    <p>PAMO (Purchasing Asset and Management Officer) is a comprehensive web-based ordering and inventory system designed exclusively for STI College Lucena. It bridges the gap between efficient inventory management and convenient student access to school essentials — from uniforms and accessories to supplies.</p>
                    <p>The platform serves dual purposes: it functions as a powerful inventory management system for the Purchasing Officer to track, organize, and manage stock efficiently, while simultaneously providing students with a user-friendly online catalog to browse available items, place orders, and request pre-orders for items that are temporarily out of stock.</p>
                    <p>PAMO streamlines the entire ordering process — eliminating long queues and paperwork — by enabling students to shop digitally, track their order status in real-time, and receive instant notifications about their requests. The Purchasing Officer can then review, approve, or reject orders through a centralized dashboard, ensuring a smooth, transparent, and organized transaction flow between staff and students.</p>
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
                        <h3>Streamlined Ordering & Pre-Order System</h3>
                        <p>Students can order available items or request out-of-stock items without physical queues or paperwork. Purchasing Officer gets a centralized dashboard to manage all orders and requests efficiently.</p>
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
                    <i class="fas fa-route"></i>
                    <h2>How to Use PAMO</h2>
                    <p class="section-subtitle">Your step-by-step guide to navigating the system</p>
                </div>

                <!-- Tab Navigation -->
                <div class="role-tabs">
                    <button class="tab-btn active" data-role="student">
                        <i class="fas fa-user-graduate"></i>
                        <span>For Students</span>
                    </button>
                    <button class="tab-btn" data-role="pamo">
                        <i class="fas fa-user-tie"></i>
                        <span>For PAMO Officers</span>
                    </button>
                </div>

                <!-- Student Workflow -->
                <div class="workflow-container active" id="student-workflow">
                    <div class="workflow-timeline">
                        <div class="timeline-step" data-aos="fade-right" data-aos-delay="100">
                            <div class="step-number">1</div>
                            <div class="step-icon">
                                <i class="fas fa-sign-in-alt"></i>
                            </div>
                            <div class="step-content">
                                <h3>Log In</h3>
                                <p>Access your account securely using your school credentials</p>
                            </div>
                        </div>

                        <div class="timeline-step" data-aos="fade-right" data-aos-delay="150">
                            <div class="step-number">2</div>
                            <div class="step-icon">
                                <i class="fas fa-search"></i>
                            </div>
                            <div class="step-content">
                                <h3>Browse the Catalog</h3>
                                <p>Explore all available items from uniforms to accessories</p>
                            </div>
                        </div>

                        <div class="timeline-step" data-aos="fade-right" data-aos-delay="200">
                            <div class="step-number">3</div>
                            <div class="step-icon">
                                <i class="fas fa-shopping-cart"></i>
                            </div>
                            <div class="step-content">
                                <h3>Add to Cart & Checkout</h3>
                                <p>Select items and proceed to checkout with ease</p>
                            </div>
                        </div>

                        <div class="timeline-step" data-aos="fade-right" data-aos-delay="250">
                            <div class="step-number">4</div>
                            <div class="step-icon">
                                <i class="fas fa-tasks"></i>
                            </div>
                            <div class="step-content">
                                <h3>Track Your Orders</h3>
                                <p>Monitor status in "My Orders" — pending, approved, or rejected</p>
                            </div>
                        </div>

                        <div class="timeline-step" data-aos="fade-right" data-aos-delay="300">
                            <div class="step-number">5</div>
                            <div class="step-icon">
                                <i class="fas fa-receipt"></i>
                            </div>
                            <div class="step-content">
                                <h3>Payment</h3>
                                <p>Download e-slip and pay at the Registrar's Office</p>
                            </div>
                        </div>

                        <div class="timeline-step" data-aos="fade-right" data-aos-delay="350">
                            <div class="step-number">6</div>
                            <div class="step-icon">
                                <i class="fas fa-box"></i>
                            </div>
                            <div class="step-content">
                                <h3>Receive Items</h3>
                                <p>Present official receipt to PAMO and claim your items</p>
                            </div>
                        </div>

                        <div class="timeline-step timeline-extra" data-aos="fade-right" data-aos-delay="400">
                            <div class="step-icon extra">
                                <i class="fas fa-calendar-plus"></i>
                            </div>
                            <div class="step-content">
                                <h3>Pre-Order Option</h3>
                                <p>Request out-of-stock items for future availability</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- PAMO Workflow -->
                <div class="workflow-container" id="pamo-workflow">
                    <div class="workflow-timeline">
                        <div class="timeline-step" data-aos="fade-left" data-aos-delay="100">
                            <div class="step-number">1</div>
                            <div class="step-icon">
                                <i class="fas fa-boxes"></i>
                            </div>
                            <div class="step-content">
                                <h3>Manage Inventory</h3>
                                <p>Add, update, or remove items with real-time stock tracking</p>
                            </div>
                        </div>

                        <div class="timeline-step" data-aos="fade-left" data-aos-delay="150">
                            <div class="step-number">2</div>
                            <div class="step-icon">
                                <i class="fas fa-clipboard-list"></i>
                            </div>
                            <div class="step-content">
                                <h3>Review Orders</h3>
                                <p>See all incoming student orders and pre-order requests</p>
                            </div>
                        </div>

                        <div class="timeline-step" data-aos="fade-left" data-aos-delay="200">
                            <div class="step-number">3</div>
                            <div class="step-icon">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <div class="step-content">
                                <h3>Approve or Reject Orders</h3>
                                <p>Update order status and notify students automatically</p>
                            </div>
                        </div>

                        <div class="timeline-step" data-aos="fade-left" data-aos-delay="250">
                            <div class="step-number">4</div>
                            <div class="step-icon">
                                <i class="fas fa-hand-holding-usd"></i>
                            </div>
                            <div class="step-content">
                                <h3>Process Payments</h3>
                                <p>Mark orders complete after Registrar's Office verification</p>
                            </div>
                        </div>

                        <div class="timeline-step" data-aos="fade-left" data-aos-delay="300">
                            <div class="step-number">5</div>
                            <div class="step-icon">
                                <i class="fas fa-chart-line"></i>
                            </div>
                            <div class="step-content">
                                <h3>Generate Reports</h3>
                                <p>Access detailed inventory, sales, and audit trail reports</p>
                            </div>
                        </div>
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

        // Tab Switching Functionality
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const role = this.dataset.role;
                
                // Remove active class from all tabs and workflows
                document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
                document.querySelectorAll('.workflow-container').forEach(w => w.classList.remove('active'));
                
                // Add active class to clicked tab
                this.classList.add('active');
                
                // Show corresponding workflow
                document.getElementById(`${role}-workflow`).classList.add('active');
                
                // Re-trigger AOS animations
                AOS.refresh();
            });
        });
    </script>
</body>
</html> 