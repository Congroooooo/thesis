<?php
include '../Includes/Header.php';
include '../Includes/loader.php';
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
            </div>
            
            <div class="faq-list">
                <!-- FAQ Item 1 -->
                <div class="faq-item" data-aos="fade-up">
                    <div class="faq-question">
                        <h3>How do I place a pre-order?</h3>
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="faq-answer">
                        <p>To place a pre-order, go to the catalog, select the item you want, and click the "Pre-Order" button. Fill out the required form and submit it. You'll be notified once your request is reviewed.</p>
                    </div>
                </div>

                <!-- FAQ Item 2 -->
                <div class="faq-item" data-aos="fade-up" data-aos-delay="100">
                    <div class="faq-question">
                        <h3>How will I know if my pre-order is approved or rejected?</h3>
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="faq-answer">
                        <p>You'll receive a status update on your account dashboard. If your request is approved, you'll see it marked as "Approved"; if not, it will be marked as "Rejected" along with a reason, if provided.</p>
                    </div>
                </div>

                <!-- FAQ Item 3 -->
                <div class="faq-item" data-aos="fade-up" data-aos-delay="200">
                    <div class="faq-question">
                        <h3>Can I cancel my pre-order after submitting it?</h3>
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="faq-answer">
                        <p>Yes! As long as the item hasn't been processed or approved yet, you can cancel your pre-order from your "My Orders" page by clicking the "Cancel" button.</p>
                    </div>
                </div>

                <!-- FAQ Item 4 -->
                <div class="faq-item" data-aos="fade-up" data-aos-delay="300">
                    <div class="faq-question">
                        <h3>What happens if the item I want is out of stock?</h3>
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="faq-answer">
                        <p>If an item is out of stock, it will be marked accordingly in the catalog. You can still submit a pre-order, and the admin will notify you when the item becomes available again.</p>
                    </div>
                </div>

                <!-- FAQ Item 5 -->
                <div class="faq-item" data-aos="fade-up" data-aos-delay="400">
                    <div class="faq-question">
                        <h3>Who do I contact if I have a problem with my order?</h3>
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="faq-answer">
                        <p>You can use the form below to send us a question or concern. A member of the admin team will respond as soon as possible. You can also check with your school admin in person if it's urgent.</p>
                    </div>
                </div>
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
            once: true
        });

        // FAQ Accordion
        document.querySelectorAll('.faq-question').forEach(question => {
            question.addEventListener('click', () => {
                const faqItem = question.parentElement;
                const answer = question.nextElementSibling;
                const icon = question.querySelector('i');
                
                // Toggle active class
                faqItem.classList.toggle('active');
                
                // Toggle answer visibility
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

        // Form Submission
        document.getElementById('questionForm').addEventListener('submit', function(e) {
            e.preventDefault();
            console.log('Form submitted!'); // Debug log
            const form = this;
            const formData = new FormData(form);

            fetch('submit_question.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                const toast = document.getElementById('toast');
                if (data.success) {
                    toast.querySelector('span').textContent = "Your question has been sent successfully!";
                    form.reset();
                } else {
                    toast.querySelector('span').textContent = "There was an error sending your question.";
                }
                toast.classList.add('show');
                setTimeout(() => {
                    toast.classList.remove('show');
                }, 3000);
            })
            .catch(() => {
                const toast = document.getElementById('toast');
                toast.querySelector('span').textContent = "There was an error sending your question.";
                toast.classList.add('show');
                setTimeout(() => {
                    toast.classList.remove('show');
                }, 3000);
            });
        });
    </script>
</body>
</html> 