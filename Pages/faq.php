<?php
include '../Includes/Header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FAQ</title>
    <link rel="stylesheet" href="../CSS/global.css">
    <link rel="stylesheet" href="../CSS/faq.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Anton&family=Smooch+Sans:wght@100..900&display=swap" rel="stylesheet">
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    

    <div class="faq-container">
        <!-- Hero Section -->
        <section class="hero-section" data-aos="fade-up">
            <div class="hero-content">
                <h1>Frequently Asked Questions</h1>
                <p class="subtitle">Find answers to common questions about PAMO</p>
            </div>
        </section>

        <!-- FAQ Section -->
        <section class="faq-section" data-aos="fade-up">
            <div class="section-header">
                <i class="fas fa-question-circle"></i>
                <h2>Common Questions</h2>
                <p class="section-description">Browse through categories or search for specific topics</p>
            </div>

            <!-- Search Bar -->
            <div class="faq-search-container" data-aos="fade-up" data-aos-delay="100">
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" id="faqSearch" placeholder="Search for questions..." aria-label="Search FAQ">
                    <span class="search-count" id="searchCount"></span>
                </div>
            </div>

            <!-- Category Filters -->
            <div class="faq-categories" data-aos="fade-up" data-aos-delay="150">
                <button class="category-btn active" data-category="all">
                    <i class="fas fa-th"></i>
                    All Topics
                </button>
                <button class="category-btn" data-category="ordering">
                    <i class="fas fa-shopping-cart"></i>
                    Ordering
                </button>
                <button class="category-btn" data-category="payment">
                    <i class="fas fa-money-bill-wave"></i>
                    Payments
                </button>
                <button class="category-btn" data-category="tracking">
                    <i class="fas fa-tasks"></i>
                    Tracking & Status
                </button>
                <button class="category-btn" data-category="support">
                    <i class="fas fa-headset"></i>
                    Support
                </button>
            </div>
            
            <div class="faq-list">
                <!-- FAQ Item 1 - Ordering Category -->
                <div class="faq-item" data-category="ordering" data-aos="fade-up">
                    <div class="faq-question">
                        <div class="faq-icon-wrapper">
                            <i class="fas fa-box-open"></i>
                        </div>
                        <h3>What's the difference between an Order and a Pre-Order?</h3>
                        <i class="fas fa-chevron-down faq-toggle"></i>
                    </div>
                    <div class="faq-answer">
                        <div class="answer-content">
                            <p>An <strong>Order</strong> refers to items that are currently <span class="highlight">available in stock</span> for immediate purchase. You can add them to your cart and proceed to checkout right away.</p>
                            <p>A <strong>Pre-Order</strong> applies to items that are <span class="highlight">temporarily out of stock</span> or not yet available. It allows you to request these items in advance, and PAMO will notify you once they become available or when your pre-order request is approved.</p>
                        </div>
                        <div class="faq-feedback">
                            <p class="feedback-question">Was this helpful?</p>
                            <div class="feedback-buttons">
                                <button class="feedback-btn yes-btn" data-feedback="yes">
                                    <i class="fas fa-thumbs-up"></i> Yes
                                </button>
                                <button class="feedback-btn no-btn" data-feedback="no">
                                    <i class="fas fa-thumbs-down"></i> No
                                </button>
                            </div>
                            <div class="feedback-thank-you" style="display: none;">
                                <i class="fas fa-check-circle"></i>
                                <span>Thank you for your feedback!</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- FAQ Item 2 - Ordering Category -->
                <div class="faq-item" data-category="ordering" data-aos="fade-up" data-aos-delay="50">
                    <div class="faq-question">
                        <div class="faq-icon-wrapper">
                            <i class="fas fa-shopping-bag"></i>
                        </div>
                        <h3>How do I place an Order?</h3>
                        <i class="fas fa-chevron-down faq-toggle"></i>
                    </div>
                    <div class="faq-answer">
                        <div class="answer-content">
                            <p>To place an order, follow these simple steps:</p>
                            <ol class="step-list">
                                <li><i class="fas fa-sign-in-alt"></i> Log in to your account using your school credentials</li>
                                <li><i class="fas fa-search"></i> Go to the <strong>Products</strong> page and browse available items</li>
                                <li><i class="fas fa-cart-plus"></i> Click <strong>"Add to Cart"</strong> on items you want to purchase</li>
                                <li><i class="fas fa-eye"></i> Review your cart and proceed to <strong>Checkout</strong></li>
                                <li><i class="fas fa-paper-plane"></i> Click <strong>"Place Order"</strong> to submit your request</li>
                            </ol>
                            <div class="info-box">
                                <i class="fas fa-bell"></i>
                                <p>You'll receive a notification once PAMO has reviewed and approved your order.</p>
                            </div>
                        </div>
                        <div class="faq-feedback">
                            <p class="feedback-question">Was this helpful?</p>
                            <div class="feedback-buttons">
                                <button class="feedback-btn yes-btn" data-feedback="yes">
                                    <i class="fas fa-thumbs-up"></i> Yes
                                </button>
                                <button class="feedback-btn no-btn" data-feedback="no">
                                    <i class="fas fa-thumbs-down"></i> No
                                </button>
                            </div>
                            <div class="feedback-thank-you" style="display: none;">
                                <i class="fas fa-check-circle"></i>
                                <span>Thank you for your feedback!</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- FAQ Item 3 - Ordering Category -->
                <div class="faq-item" data-category="ordering" data-aos="fade-up" data-aos-delay="100">
                    <div class="faq-question">
                        <div class="faq-icon-wrapper">
                            <i class="fas fa-calendar-plus"></i>
                        </div>
                        <h3>How do I place a Pre-Order?</h3>
                        <i class="fas fa-chevron-down faq-toggle"></i>
                    </div>
                    <div class="faq-answer">
                        <div class="answer-content">
                            <p>If an item is <strong>Out of Stock</strong> but available for pre-ordering:</p>
                            <ol class="step-list">
                                <li><i class="fas fa-calendar-alt"></i> Go to the <strong>Pre-Order</strong> page from the navigation menu</li>
                                <li><i class="fas fa-search"></i> Browse items available for pre-order requests</li>
                                <li><i class="fas fa-hand-pointer"></i> Click <strong>"Request Pre-Order"</strong> on your desired item</li>
                                <li><i class="fas fa-check-square"></i> Select size and quantity, then submit your request</li>
                            </ol>
                            <div class="info-box">
                                <i class="fas fa-info-circle"></i>
                                <p>After submission, your pre-order will appear in <strong>My Orders</strong> with a <em>Pending</em> status. You'll be notified once PAMO approves or rejects it.</p>
                            </div>
                        </div>
                        <div class="faq-feedback">
                            <p class="feedback-question">Was this helpful?</p>
                            <div class="feedback-buttons">
                                <button class="feedback-btn yes-btn" data-feedback="yes">
                                    <i class="fas fa-thumbs-up"></i> Yes
                                </button>
                                <button class="feedback-btn no-btn" data-feedback="no">
                                    <i class="fas fa-thumbs-down"></i> No
                                </button>
                            </div>
                            <div class="feedback-thank-you" style="display: none;">
                                <i class="fas fa-check-circle"></i>
                                <span>Thank you for your feedback!</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- FAQ Item 4 - Tracking Category -->
                <div class="faq-item" data-category="tracking" data-aos="fade-up" data-aos-delay="150">
                    <div class="faq-question">
                        <div class="faq-icon-wrapper">
                            <i class="fas fa-bell"></i>
                        </div>
                        <h3>How will I know if my order or pre-order is approved or rejected?</h3>
                        <i class="fas fa-chevron-down faq-toggle"></i>
                    </div>
                    <div class="faq-answer">
                        <div class="answer-content">
                            <p>You'll receive an <strong>in-system notification</strong> through your dashboard. You can also check the <strong>My Orders</strong> page anytime to see live status updates:</p>
                            <div class="status-grid">
                                <div class="status-badge pending">
                                    <i class="fas fa-clock"></i>
                                    <strong>Pending</strong>
                                    <span>Awaiting review</span>
                                </div>
                                <div class="status-badge approved">
                                    <i class="fas fa-check-circle"></i>
                                    <strong>Approved</strong>
                                    <span>Ready for payment</span>
                                </div>
                                <div class="status-badge rejected">
                                    <i class="fas fa-times-circle"></i>
                                    <strong>Rejected</strong>
                                    <span>Reason provided</span>
                                </div>
                                <div class="status-badge completed">
                                    <i class="fas fa-check-double"></i>
                                    <strong>Completed</strong>
                                    <span>Ready for pickup</span>
                                </div>
                            </div>
                        </div>
                        <div class="faq-feedback">
                            <p class="feedback-question">Was this helpful?</p>
                            <div class="feedback-buttons">
                                <button class="feedback-btn yes-btn" data-feedback="yes">
                                    <i class="fas fa-thumbs-up"></i> Yes
                                </button>
                                <button class="feedback-btn no-btn" data-feedback="no">
                                    <i class="fas fa-thumbs-down"></i> No
                                </button>
                            </div>
                            <div class="feedback-thank-you" style="display: none;">
                                <i class="fas fa-check-circle"></i>
                                <span>Thank you for your feedback!</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- FAQ Item 5 - Payment Category -->
                <div class="faq-item" data-category="payment" data-aos="fade-up" data-aos-delay="200">
                    <div class="faq-question">
                        <div class="faq-icon-wrapper">
                            <i class="fas fa-money-check-alt"></i>
                        </div>
                        <h3>How do I pay for my approved order?</h3>
                        <i class="fas fa-chevron-down faq-toggle"></i>
                    </div>
                    <div class="faq-answer">
                        <div class="answer-content">
                            <p>Once your order is approved:</p>
                            <ol class="step-list">
                                <li><i class="fas fa-list"></i> Go to <strong>My Orders</strong> and find your approved order</li>
                                <li><i class="fas fa-download"></i> Click <strong>"Download Receipt"</strong> to get your e-slip (PDF)</li>
                                <li><i class="fas fa-print"></i> Print the e-slip and bring it to the <strong>Registrar's Office</strong></li>
                                <li><i class="fas fa-hand-holding-usd"></i> Make your payment at the Registrar's Office</li>
                                <li><i class="fas fa-receipt"></i> You'll receive an <strong>Official Receipt</strong> as proof of payment</li>
                            </ol>
                        </div>
                        <div class="faq-feedback">
                            <p class="feedback-question">Was this helpful?</p>
                            <div class="feedback-buttons">
                                <button class="feedback-btn yes-btn" data-feedback="yes">
                                    <i class="fas fa-thumbs-up"></i> Yes
                                </button>
                                <button class="feedback-btn no-btn" data-feedback="no">
                                    <i class="fas fa-thumbs-down"></i> No
                                </button>
                            </div>
                            <div class="feedback-thank-you" style="display: none;">
                                <i class="fas fa-check-circle"></i>
                                <span>Thank you for your feedback!</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- FAQ Item 6 - Payment Category -->
                <div class="faq-item" data-category="payment" data-aos="fade-up" data-aos-delay="250">
                    <div class="faq-question">
                        <div class="faq-icon-wrapper">
                            <i class="fas fa-box"></i>
                        </div>
                        <h3>How do I claim my items after payment?</h3>
                        <i class="fas fa-chevron-down faq-toggle"></i>
                    </div>
                    <div class="faq-answer">
                        <div class="answer-content">
                            <p>Once payment is complete:</p>
                            <ol class="step-list">
                                <li><i class="fas fa-file-invoice"></i> Bring your <strong>Official Receipt</strong> from the Registrar's Office to <strong>PAMO</strong></li>
                                <li><i class="fas fa-check"></i> PAMO staff will verify your payment record in the system</li>
                                <li><i class="fas fa-hand-holding"></i> Your items will be released to you immediately</li>
                            </ol>
                            <div class="warning-box">
                                <i class="fas fa-exclamation-triangle"></i>
                                <p>Make sure to keep your official receipt as proof of purchase!</p>
                            </div>
                        </div>
                        <div class="faq-feedback">
                            <p class="feedback-question">Was this helpful?</p>
                            <div class="feedback-buttons">
                                <button class="feedback-btn yes-btn" data-feedback="yes">
                                    <i class="fas fa-thumbs-up"></i> Yes
                                </button>
                                <button class="feedback-btn no-btn" data-feedback="no">
                                    <i class="fas fa-thumbs-down"></i> No
                                </button>
                            </div>
                            <div class="feedback-thank-you" style="display: none;">
                                <i class="fas fa-check-circle"></i>
                                <span>Thank you for your feedback!</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- FAQ Item 7 - Tracking Category -->
                <div class="faq-item" data-category="tracking" data-aos="fade-up" data-aos-delay="300">
                    <div class="faq-question">
                        <div class="faq-icon-wrapper">
                            <i class="fas fa-ban"></i>
                        </div>
                        <h3>Can I cancel my order or pre-order?</h3>
                        <i class="fas fa-chevron-down faq-toggle"></i>
                    </div>
                    <div class="faq-answer">
                        <div class="answer-content">
                            <p>You may cancel an order <strong>only if it's still in Pending status</strong>.</p>
                            <p>To cancel:</p>
                            <ol class="step-list">
                                <li><i class="fas fa-list"></i> Go to <strong>My Orders</strong></li>
                                <li><i class="fas fa-search"></i> Find the order you want to cancel</li>
                                <li><i class="fas fa-times-circle"></i> Click the <strong>"Cancel Order"</strong> button</li>
                            </ol>
                            <div class="warning-box">
                                <i class="fas fa-info-circle"></i>
                                <p><strong>Important:</strong> Once an order is <em>Approved</em>, cancellations are no longer possible. Please review your orders carefully before submitting.</p>
                            </div>
                            <div class="info-box">
                                <i class="fas fa-shield-alt"></i>
                                <p><em>Note: Cancelling pending orders will NOT result in strikes. However, if you fail to pay for an approved order within 5 minutes, it will be voided and you will receive a strike.</em></p>
                            </div>
                        </div>
                        <div class="faq-feedback">
                            <p class="feedback-question">Was this helpful?</p>
                            <div class="feedback-buttons">
                                <button class="feedback-btn yes-btn" data-feedback="yes">
                                    <i class="fas fa-thumbs-up"></i> Yes
                                </button>
                                <button class="feedback-btn no-btn" data-feedback="no">
                                    <i class="fas fa-thumbs-down"></i> No
                                </button>
                            </div>
                            <div class="feedback-thank-you" style="display: none;">
                                <i class="fas fa-check-circle"></i>
                                <span>Thank you for your feedback!</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- FAQ Item 8 - Ordering Category -->
                <div class="faq-item" data-category="ordering" data-aos="fade-up" data-aos-delay="350">
                    <div class="faq-question">
                        <div class="faq-icon-wrapper">
                            <i class="fas fa-clock"></i>
                        </div>
                        <h3>What happens if the item I pre-ordered is out of stock for a long time?</h3>
                        <i class="fas fa-chevron-down faq-toggle"></i>
                    </div>
                    <div class="faq-answer">
                        <div class="answer-content">
                            <p>If restocking is significantly delayed, <strong>PAMO will notify you</strong> through the system. They may provide:</p>
                            <ul class="feature-list">
                                <li><i class="fas fa-calendar-check"></i> An estimated restock date</li>
                                <li><i class="fas fa-exchange-alt"></i> Alternative product recommendations</li>
                                <li><i class="fas fa-times-circle"></i> The option to cancel your pre-order request if you prefer</li>
                            </ul>
                            <div class="info-box">
                                <i class="fas fa-store"></i>
                                <p>You can always check with PAMO directly during school hours for more specific information about your pre-order.</p>
                            </div>
                        </div>
                        <div class="faq-feedback">
                            <p class="feedback-question">Was this helpful?</p>
                            <div class="feedback-buttons">
                                <button class="feedback-btn yes-btn" data-feedback="yes">
                                    <i class="fas fa-thumbs-up"></i> Yes
                                </button>
                                <button class="feedback-btn no-btn" data-feedback="no">
                                    <i class="fas fa-thumbs-down"></i> No
                                </button>
                            </div>
                            <div class="feedback-thank-you" style="display: none;">
                                <i class="fas fa-check-circle"></i>
                                <span>Thank you for your feedback!</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- FAQ Item 9 - Support Category -->
                <div class="faq-item" data-category="support" data-aos="fade-up" data-aos-delay="400">
                    <div class="faq-question">
                        <div class="faq-icon-wrapper">
                            <i class="fas fa-life-ring"></i>
                        </div>
                        <h3>Who do I contact if I have a problem with my order?</h3>
                        <i class="fas fa-chevron-down faq-toggle"></i>
                    </div>
                    <div class="faq-answer">
                        <div class="answer-content">
                            <p>For assistance with orders, payment issues, or general questions:</p>
                            <ul class="feature-list">
                                <li><i class="fas fa-envelope"></i> Use the <strong>"Ask a Question"</strong> form below to send your inquiry directly to PAMO</li>
                                <li><i class="fas fa-building"></i> Visit the <strong>PAMO office</strong> in person during regular school hours</li>
                                <li><i class="fas fa-bell"></i> Check your <strong>account notifications</strong> for updates and responses</li>
                            </ul>
                            <div class="success-box">
                                <i class="fas fa-heart"></i>
                                <p>The PAMO team is committed to addressing your concerns as quickly as possible!</p>
                            </div>
                        </div>
                        <div class="faq-feedback">
                            <p class="feedback-question">Was this helpful?</p>
                            <div class="feedback-buttons">
                                <button class="feedback-btn yes-btn" data-feedback="yes">
                                    <i class="fas fa-thumbs-up"></i> Yes
                                </button>
                                <button class="feedback-btn no-btn" data-feedback="no">
                                    <i class="fas fa-thumbs-down"></i> No
                                </button>
                            </div>
                            <div class="feedback-thank-you" style="display: none;">
                                <i class="fas fa-check-circle"></i>
                                <span>Thank you for your feedback!</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- No Results Message -->
            <div class="no-results" id="noResults" style="display: none;">
                <i class="fas fa-search-minus"></i>
                <h3>No results found</h3>
                <p>Try different keywords or browse by category</p>
            </div>
        </section>

        <!-- Ask a Question Section -->
        <section class="ask-question-section" data-aos="fade-up">
            <div class="section-header">
                <i class="fas fa-envelope"></i>
                <h2>Ask a Question</h2>
            </div>
            
            <div class="question-form-container">
                <form id="questionForm" class="question-form">
                    <div class="form-group">
                        <label for="question">Have a question? Ask us here.</label>
                        <textarea id="question" name="question" rows="5" placeholder="Type your question here..." required></textarea>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="submit-btn">
                            <i class="fas fa-paper-plane"></i>
                            Send Question
                        </button>
                    </div>
                </form>
            </div>
        </section>
    </div>

    <!-- Toast Notification -->
    <div id="toast" class="toast">
        <i class="fas fa-check-circle"></i>
        <span>Your question has been sent successfully!</span>
    </div>

    <?php include '../Includes/footer.php'; ?>

    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>
        // Initialize AOS
        AOS.init({
            duration: 800,
            once: true,
            offset: 50
        });

        // FAQ Accordion
        document.querySelectorAll('.faq-question').forEach(question => {
            question.addEventListener('click', () => {
                const faqItem = question.parentElement;
                const answer = question.nextElementSibling;
                const icon = question.querySelector('.faq-toggle');
                
                // Close other open items (optional - remove for multi-open)
                document.querySelectorAll('.faq-item.active').forEach(item => {
                    if (item !== faqItem) {
                        item.classList.remove('active');
                        item.querySelector('.faq-answer').style.maxHeight = '0';
                        item.querySelector('.faq-toggle').classList.remove('fa-chevron-up');
                        item.querySelector('.faq-toggle').classList.add('fa-chevron-down');
                    }
                });
                
                // Toggle active class
                faqItem.classList.toggle('active');
                
                // Toggle answer visibility with animation
                if (faqItem.classList.contains('active')) {
                    answer.style.maxHeight = answer.scrollHeight + 'px';
                    icon.classList.remove('fa-chevron-down');
                    icon.classList.add('fa-chevron-up');
                } else {
                    answer.style.maxHeight = '0';
                    icon.classList.remove('fa-chevron-up');
                    icon.classList.add('fa-chevron-down');
                }
            });
        });

        // Search Functionality
        const searchInput = document.getElementById('faqSearch');
        const searchCount = document.getElementById('searchCount');
        const faqItems = document.querySelectorAll('.faq-item');
        const noResults = document.getElementById('noResults');

        searchInput.addEventListener('input', (e) => {
            const searchTerm = e.target.value.toLowerCase();
            let visibleCount = 0;

            faqItems.forEach(item => {
                const question = item.querySelector('.faq-question h3').textContent.toLowerCase();
                const answer = item.querySelector('.answer-content').textContent.toLowerCase();
                const category = item.getAttribute('data-category');
                const activeCategory = document.querySelector('.category-btn.active').getAttribute('data-category');

                const matchesSearch = question.includes(searchTerm) || answer.includes(searchTerm);
                const matchesCategory = activeCategory === 'all' || category === activeCategory;

                if (matchesSearch && matchesCategory) {
                    item.style.display = 'block';
                    visibleCount++;
                } else {
                    item.style.display = 'none';
                }
            });

            // Update search count
            if (searchTerm) {
                searchCount.textContent = `${visibleCount} result${visibleCount !== 1 ? 's' : ''}`;
                searchCount.style.display = 'inline-block';
            } else {
                searchCount.style.display = 'none';
            }

            // Show/hide no results message
            noResults.style.display = visibleCount === 0 ? 'block' : 'none';
        });

        // Category Filter
        const categoryButtons = document.querySelectorAll('.category-btn');

        categoryButtons.forEach(button => {
            button.addEventListener('click', () => {
                // Remove active class from all buttons
                categoryButtons.forEach(btn => btn.classList.remove('active'));
                
                // Add active class to clicked button
                button.classList.add('active');
                
                const selectedCategory = button.getAttribute('data-category');
                const searchTerm = searchInput.value.toLowerCase();
                let visibleCount = 0;

                faqItems.forEach(item => {
                    const itemCategory = item.getAttribute('data-category');
                    const question = item.querySelector('.faq-question h3').textContent.toLowerCase();
                    const answer = item.querySelector('.answer-content').textContent.toLowerCase();

                    const matchesCategory = selectedCategory === 'all' || itemCategory === selectedCategory;
                    const matchesSearch = !searchTerm || question.includes(searchTerm) || answer.includes(searchTerm);

                    if (matchesCategory && matchesSearch) {
                        item.style.display = 'block';
                        visibleCount++;
                    } else {
                        item.style.display = 'none';
                    }
                });

                // Update search count
                if (searchTerm) {
                    searchCount.textContent = `${visibleCount} result${visibleCount !== 1 ? 's' : ''}`;
                }

                // Show/hide no results message
                noResults.style.display = visibleCount === 0 ? 'block' : 'none';

                // Scroll to FAQ section
                document.querySelector('.faq-list').scrollIntoView({ behavior: 'smooth', block: 'start' });
            });
        });

        // Feedback Functionality
        document.querySelectorAll('.feedback-btn').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.stopPropagation(); // Prevent accordion toggle
                
                const feedbackSection = this.closest('.faq-feedback');
                const buttons = feedbackSection.querySelector('.feedback-buttons');
                const thankYou = feedbackSection.querySelector('.feedback-thank-you');
                const feedbackType = this.getAttribute('data-feedback');
                
                // Send feedback to server (optional - implement backend)
                // fetch('submit_feedback.php', {
                //     method: 'POST',
                //     headers: { 'Content-Type': 'application/json' },
                //     body: JSON.stringify({ 
                //         question: this.closest('.faq-item').querySelector('h3').textContent,
                //         helpful: feedbackType === 'yes'
                //     })
                // });

                // Show thank you message
                buttons.style.display = 'none';
                thankYou.style.display = 'flex';

                // If user clicked "No", show support link after delay
                if (feedbackType === 'no') {
                    setTimeout(() => {
                        thankYou.innerHTML = `
                            <i class="fas fa-info-circle"></i>
                            <span>We're sorry this didn't help. <a href="#ask-question-section" class="support-link">Contact Support</a></span>
                        `;
                    }, 2000);
                }

                // Reset after 5 seconds
                setTimeout(() => {
                    buttons.style.display = 'flex';
                    thankYou.style.display = 'none';
                    thankYou.innerHTML = `
                        <i class="fas fa-check-circle"></i>
                        <span>Thank you for your feedback!</span>
                    `;
                }, 5000);
            });
        });

        // Form Submission
        document.getElementById('questionForm').addEventListener('submit', function(e) {
            e.preventDefault();

            const form = this;
            const submitBtn = form.querySelector('.submit-btn');
            const formData = new FormData(form);

            // Disable button and show processing state
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';

            fetch('submit_question.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                const toast = document.getElementById('toast');
                const toastIcon = toast.querySelector('i');
                
                if (data.success) {
                    toast.classList.remove('error');
                    toastIcon.className = 'fas fa-check-circle';
                    toast.querySelector('span').textContent = "Your question has been sent successfully!";
                    form.reset();
                } else {
                    toast.classList.add('error');
                    toastIcon.className = 'fas fa-exclamation-circle';
                    toast.querySelector('span').textContent = "There was an error sending your question.";
                }
                toast.classList.add('show');
                setTimeout(() => {
                    toast.classList.remove('show');
                }, 3000);
            })
            .catch(() => {
                const toast = document.getElementById('toast');
                const toastIcon = toast.querySelector('i');
                toast.classList.add('error');
                toastIcon.className = 'fas fa-exclamation-circle';
                toast.querySelector('span').textContent = "There was an error sending your question.";
                toast.classList.add('show');
                setTimeout(() => {
                    toast.classList.remove('show');
                }, 3000);
            })
            .finally(() => {
                // Re-enable button and restore original text
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Send Question';
            });
        });

        // Smooth scroll for support link
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('support-link')) {
                e.preventDefault();
                document.querySelector('.ask-question-section').scrollIntoView({ 
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    </script>
</body>
</html> 