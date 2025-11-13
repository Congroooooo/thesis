<footer class="footer">
    <div class="footer-content">
        <div class="footer-section">
            <div class="footer-logo">
                <img src="../Images/STI-LOGO.png" alt="STI College Logo" width="150">
            </div>
            <h3>STI COLLEGE Lucena</h3>
            <p class="footer-description">
                Empowering students with industry-driven education and innovative solutions.
            </p>
        </div>

        <div class="footer-section">
            <h4 style="font-family: 'Anton', serif; letter-spacing: 2px;">QUICK LINKS</h4>
            <ul class="footer-links">
                <li><a href="../Pages/home.php">Home</a></li>
                <li><a href="../Pages/ProItemList.php">Products</a></li>
                <li><a href="../Pages/preorder.php">PreOrder</a></li> 
                <li><a href="../Pages/ProPreOrder.php">Place Order</a></li>
                <li><a href="../Pages/MyOrders.php">My Orders</a></li>
                <li><a href="../Pages/about.php">About Us</a></li>
                <li><a href="../Pages/faq.php">FAQ</a></li>
            </ul>
        </div>

        <div class="footer-section">
            <h4>CONTACT INFO</h4>
            <ul class="contact-info">
                <li>
                    <i class="fas fa-map-marker-alt"></i>
                    Quezon Avenue Corner Don Perez Street, Lucena, Philippines, 4301
                </li>
                <li>
                    <i class="fas fa-phone"></i>
                    (042) 717 3150
                </li>
                <li>
                    <i class="fas fa-envelope"></i>
                    <a href="mailto:sti.lucena@gmail.com">sti.lucena@gmail.com</a>
                </li>
            </ul>
        </div>

        <div class="footer-section">
            <h4>CONNECT WITH US</h4>
            <div class="social-links">
                <a href="https://www.facebook.com/lucena.sti.edu" target="_blank" class="social-link">
                    <i class="fab fa-facebook-f"></i>
                </a>
                <a href="https://twitter.com/STI_Education" target="_blank" class="social-link">
                    <i class="fab fa-twitter"></i>
                </a>
                <a href="https://www.instagram.com/sti_college/" target="_blank" class="social-link">
                    <i class="fab fa-instagram"></i>
                </a>
                <a href="https://www.youtube.com/user/STIdotEdu" target="_blank" class="social-link">
                    <i class="fab fa-youtube"></i>
                </a>
            </div>
        </div>
    </div>

    <div class="footer-bottom">
        <div class="footer-bottom-content">
            <p>&copy; <?php echo date('Y'); ?> STI College Lucena. All rights reserved.</p>
            <div class="footer-bottom-links">
                <a href="#" id="terms-of-use-link">Terms of Use</a>
                <a href="#" id="developers-link">Developers</a>
            </div>
        </div>
    </div>
</footer>


<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<link rel="stylesheet" href="../CSS/developers-modal.css">
<link rel="stylesheet" href="../CSS/policy-modal.css">
</style>
<script src="../Javascript/developers-modal.js"></script>
<script src="../Javascript/policy-modal.js"></script>
<script>
// Handle Terms of Use click
document.addEventListener('DOMContentLoaded', function() {
    const termsLink = document.getElementById('terms-of-use-link');
    if (termsLink) {
        termsLink.addEventListener('click', function(e) {
            e.preventDefault();
            if (typeof policyModalInstance !== 'undefined' && policyModalInstance) {
                policyModalInstance.showReadOnly();
            } else if (typeof PolicyModal !== 'undefined') {
                const modal = new PolicyModal();
                modal.showReadOnly();
            }
        });
    }
});
</script>

<style>
    .footer {
        background-color: #003366;
        color: #ffffff;
        padding: 4rem 0 0 0;
        font-family: 'Montserrat', sans-serif;
        position: relative;
    }

    .footer-content {
        max-width: 1200px;
        margin: 0 auto;
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 2rem;
        padding: 0 1.5rem;
    }

    .footer-section {
        margin-bottom: 2rem;
    }

    .footer-logo {
        margin-bottom: 1rem;
    }

    .footer-logo img {
        max-width: 150px;
        height: auto;
    }

    .footer h3 {
        color: #FEFBC7;
        font-size: 1.5rem;
        margin-bottom: 1rem;
        letter-spacing: 1px;
    }

    .footer h4 {
        color: #FEFBC7;
        font-size: 1.2rem;
        margin-bottom: 1.2rem;
        position: relative;
        padding-bottom: 0.5rem;
        letter-spacing: 2px;

    }

    .footer h4::after {
        content: '';
        position: absolute;
        left: 0;
        bottom: 0;
        width: 50px;
        height: 2px;
        background-color: #FEFBC7;
    }

    .footer-description {
        line-height: 1.6;
        margin-bottom: 1.5rem;
    }

    .footer-links {
        list-style: none;
        padding: 0;
    }

    .footer-links li {
        margin-bottom: 0.8rem;
    }

    .footer-links a {
        color: #ffffff;
        text-decoration: none;
        transition: all 0.3s ease;
        padding-left: 0;
    }

    .footer-links a:hover {
        color: #FEFBC7;
        padding-left: 10px;
    }

    .contact-info {
        list-style: none;
        padding: 0;
    }

    .contact-info li {
        margin-bottom: 1rem;
        display: flex;
        align-items: flex-start;
        gap: 0.8rem;
    }

    .contact-info i {
        color: #FEFBC7;
        margin-top: 0.3rem;
    }

    .contact-info a {
        color: #ffffff;
        text-decoration: none;
        transition: color 0.3s ease;
    }

    .contact-info a:hover {
        color: #FEFBC7;
    }

    .social-links {
        display: flex;
        gap: 1rem;
    }

    .social-link {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background-color: rgba(255, 255, 255, 0.1);
        color: #ffffff;
        text-decoration: none;
        transition: all 0.3s ease;
    }

    .social-link:hover {
        background-color: #FEFBC7;
        color: #003366;
        transform: translateY(-3px);
    }

    .footer-bottom {
        background-color: rgba(0, 0, 0, 0.2);
        padding: 1.5rem 0;
        margin-top: 3rem;
    }

    .footer-bottom-content {
        max-width: 1200px;
        margin: 0 auto;
        padding: 0 1.5rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 1rem;
    }

    .footer-bottom-links {
        display: flex;
        gap: 1.5rem;
    }

    .footer-bottom-links a {
        color: #ffffff;
        text-decoration: none;
        font-size: 0.9rem;
        transition: color 0.3s ease;
    }

    .footer-bottom-links a:hover {
        color: #FEFBC7;
    }

    @media (max-width: 768px) {
        .footer-content {
            grid-template-columns: 1fr;
        }

        .footer-bottom-content {
            flex-direction: column;
            text-align: center;
        }

        .footer-bottom-links {
            justify-content: center;
        }

        .social-links {
            justify-content: center;
        }
    }


    .footer-links a::after {
        display: none;
    }


    @keyframes linkHover {
        from {
            transform: translateX(0);
        }

        to {
            transform: translateX(5px);
        }
    }

    .footer-links a:hover {
        animation: none;
    }
</style>