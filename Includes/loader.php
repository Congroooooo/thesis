<?php
?>

<div id="page-loader" class="loader-overlay">
    <div class="loader-container">
        <div class="shopping-cart-loader">
            <svg width="80" height="60" viewBox="0 0 80 60" class="cart-svg">
                <path d="M20 15 L25 15 L30 45 L65 45 L70 15 L30 15" 
                      stroke="#0d47a1" 
                      stroke-width="3" 
                      fill="none" 
                      stroke-linejoin="round" 
                      stroke-linecap="round"/>
                <path d="M15 10 L15 20 L20 20" 
                      stroke="#0d47a1" 
                      stroke-width="3" 
                      fill="none" 
                      stroke-linejoin="round" 
                      stroke-linecap="round"/>
                <circle cx="35" cy="52" r="4" fill="#0d47a1"/>
                <circle cx="60" cy="52" r="4" fill="#0d47a1"/>
                <line x1="30" y1="45" x2="65" y2="45" 
                      stroke="#0d47a1" 
                      stroke-width="2"/>
            </svg>
            <div class="product-box"></div>
            <div class="cart-landing-zone"></div>
        </div>

        <div class="loader-text">
            <h3>STI Campus Store</h3>
            <p>Preparing items for your cart...</p>
        </div>

        <div class="progress-dots">
            <div class="dot"></div>
            <div class="dot"></div>
            <div class="dot"></div>
        </div>
    </div>
</div>

<style>
.loader-overlay {
    position: fixed !important;
    top: 0 !important;
    left: 0 !important;
    width: 100% !important;
    height: 100% !important;
    background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%) !important;
    display: flex !important;
    justify-content: center !important;
    align-items: center !important;
    z-index: 99999 !important;
    opacity: 1 !important;
    visibility: visible !important;
    transition: opacity 0.8s ease-out, visibility 0.8s ease-out !important;
}

body {
    overflow: hidden !important;
}

body.loader-active {
    overflow: hidden !important;
}

body.loader-removed {
    overflow: auto !important;
}

.loader-overlay.fade-out {
    opacity: 0 !important;
    visibility: hidden !important;
}

.loader-container {
    text-align: center;
    padding: 3rem;
    background: rgba(255, 255, 255, 0.98);
    border-radius: 24px;
    box-shadow: 
        0 25px 50px rgba(13, 71, 161, 0.08),
        0 15px 35px rgba(253, 216, 53, 0.04),
        inset 0 1px 0 rgba(255, 255, 255, 0.9);
    backdrop-filter: blur(20px);
    border: 1px solid rgba(255, 255, 255, 0.3);
    max-width: 420px;
    width: 90%;
    position: relative;
    overflow: hidden;
}

.shopping-cart-loader {
    position: relative;
    width: 120px;
    height: 80px;
    margin: 0 auto 2rem;
    display: flex;
    justify-content: center;
    align-items: center;
}

/* Cart SVG Styling */
.cart-svg {
    position: relative;
    z-index: 2;
    filter: drop-shadow(0 4px 8px rgba(13, 71, 161, 0.15));
}

/* Cart slight movement */
.cart-svg {
    animation: cartIdle 4s ease-in-out infinite;
}

@keyframes cartIdle {
    0%, 100% {
        transform: translateY(0) rotate(0deg);
    }
    50% {
        transform: translateY(-2px) rotate(1deg);
    }
}

/* Flying Product Box */
.product-box {
    position: absolute;
    width: 16px;
    height: 16px;
    background: linear-gradient(135deg, #fdd835 0%, #ffeb3b 100%);
    border-radius: 3px;
    top: -20px;
    left: -30px;
    z-index: 1;
    box-shadow: 
        0 4px 8px rgba(253, 216, 53, 0.3),
        inset 0 1px 0 rgba(255, 255, 255, 0.4);
    animation: productFly 2.5s ease-in-out infinite;
}

/* Product box flying animation */
@keyframes productFly {
    0% {
        transform: translate(-30px, -20px) scale(1) rotate(0deg);
        opacity: 0;
    }
    15% {
        opacity: 1;
        transform: translate(-20px, -15px) scale(1) rotate(5deg);
    }
    50% {
        transform: translate(20px, 5px) scale(0.8) rotate(15deg);
        opacity: 1;
    }
    70% {
        transform: translate(35px, 15px) scale(0.5) rotate(25deg);
        opacity: 0.7;
    }
    85% {
        transform: translate(45px, 20px) scale(0.2) rotate(35deg);
        opacity: 0.3;
    }
    100% {
        transform: translate(50px, 25px) scale(0) rotate(45deg);
        opacity: 0;
    }
}

/* Cart Landing Zone - subtle glow when product lands */
.cart-landing-zone {
    position: absolute;
    top: 50%;
    left: 50%;
    width: 60px;
    height: 40px;
    transform: translate(-50%, -50%);
    border-radius: 8px;
    z-index: 0;
    animation: landingGlow 2.5s ease-in-out infinite;
}

@keyframes landingGlow {
    0%, 60% {
        background: transparent;
        box-shadow: none;
    }
    70% {
        background: rgba(253, 216, 53, 0.1);
        box-shadow: 0 0 20px rgba(253, 216, 53, 0.2);
    }
    80% {
        background: rgba(253, 216, 53, 0.05);
        box-shadow: 0 0 15px rgba(253, 216, 53, 0.1);
    }
    100% {
        background: transparent;
        box-shadow: none;
    }
}

/* Loading Text */
.loader-text {
    color: #333;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    margin-bottom: 1.5rem;
}

.loader-text h3 {
    margin: 0 0 0.5rem;
    font-size: 1.6rem;
    font-weight: 600;
    background: linear-gradient(135deg, #0d47a1 0%, #1976d2 50%, #fdd835 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    background-size: 200% 200%;
    animation: textShimmer 3s ease-in-out infinite;
}

.loader-text p {
    margin: 0;
    font-size: 1rem;
    color: #666;
    font-weight: 400;
    opacity: 0.8;
}

@keyframes textShimmer {
    0%, 100% {
        background-position: 0% 50%;
    }
    50% {
        background-position: 100% 50%;
    }
}

/* Progress Dots */
.progress-dots {
    display: flex;
    justify-content: center;
    gap: 8px;
}

.dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #0d47a1;
    animation: dotPulse 1.5s ease-in-out infinite;
}

.dot:nth-child(1) {
    animation-delay: 0s;
}

.dot:nth-child(2) {
    animation-delay: 0.2s;
}

.dot:nth-child(3) {
    animation-delay: 0.4s;
}

@keyframes dotPulse {
    0%, 100% {
        transform: scale(1);
        background: #0d47a1;
    }
    50% {
        transform: scale(1.3);
        background: #fdd835;
    }
}

/* Responsive Design */
@media (max-width: 480px) {
    .loader-container {
        padding: 2rem;
        margin: 1rem;
        border-radius: 20px;
        max-width: 340px;
    }
    
    .shopping-cart-loader {
        width: 100px;
        height: 70px;
        margin-bottom: 1.5rem;
    }
    
    .cart-svg {
        width: 70px;
        height: 52px;
    }
    
    .product-box {
        width: 14px;
        height: 14px;
    }
    
    .loader-text h3 {
        font-size: 1.4rem;
    }
    
    .loader-text p {
        font-size: 0.9rem;
    }
}

@media (max-width: 320px) {
    .loader-container {
        padding: 1.5rem;
    }
    
    .shopping-cart-loader {
        width: 90px;
        height: 60px;
        margin-bottom: 1rem;
    }
    
    .loader-text h3 {
        font-size: 1.2rem;
    }
    
    .loader-text p {
        font-size: 0.85rem;
    }
}

/* Container entrance animation */
.loader-container {
    animation: containerEntrance 0.6s ease-out;
}

@keyframes containerEntrance {
    0% {
        transform: scale(0.8) translateY(20px);
        opacity: 0;
    }
    100% {
        transform: scale(1) translateY(0);
        opacity: 1;
    }
}

/* Subtle background pattern */
.loader-overlay::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: 
        radial-gradient(circle at 20% 20%, rgba(13, 71, 161, 0.03) 0%, transparent 50%),
        radial-gradient(circle at 80% 80%, rgba(253, 216, 53, 0.03) 0%, transparent 50%);
    z-index: 0;
}

.loader-container {
    position: relative;
    z-index: 1;
}
</style>

<script>
// STI E-Commerce Loader - Optimized for instant display and proper timing
(function() {
    'use strict';
    
    // Immediately add loader-active class to body
    document.body.classList.add('loader-active');
    
    const loader = document.getElementById('page-loader');
    if (!loader) return;
    
    // Configuration
    const minLoadTime = 2500; // 2.5 seconds minimum to see the animation
    const maxLoadTime = 7000; // Maximum 7 seconds
    const startTime = Date.now();
    
    console.log('🛒 STI Campus Store: Product loading animation started...');
    
    function hideLoader() {
        const elapsedTime = Date.now() - startTime;
        const remainingTime = Math.max(0, minLoadTime - elapsedTime);
        
        console.log('📦 STI Campus Store: Products loaded, preparing store...');
        
        setTimeout(() => {
            if (loader && !loader.classList.contains('fade-out')) {
                // Start fade out
                loader.classList.add('fade-out');
                
                // Update body classes
                document.body.classList.remove('loader-active');
                document.body.classList.add('loader-removed');
                
                // Remove loader from DOM after fade animation
                setTimeout(() => {
                    if (loader && loader.parentNode) {
                        loader.parentNode.removeChild(loader);
                    }
                    console.log('🛍️ STI Campus Store: Welcome! Ready for shopping!');
                }, 800); // Match CSS transition duration
            }
        }, remainingTime);
    }
    
    // Prevent multiple triggers
    let loadTriggered = false;
    
    function triggerHide() {
        if (loadTriggered) return;
        loadTriggered = true;
        hideLoader();
    }
    
    // Enhanced resource detection
    function waitForResources() {
        return new Promise((resolve) => {
            const resources = [
                { type: 'stylesheet', count: 0, required: 3 },
                { type: 'script', count: 0, required: 1 },
                { type: 'image', count: 0, required: 0 }
            ];
            
            function checkResources() {
                // Count loaded stylesheets
                resources[0].count = Array.from(document.styleSheets).length;
                
                // Count loaded scripts
                resources[1].count = Array.from(document.scripts).length;
                
                // Count loaded images
                const images = Array.from(document.images);
                resources[2].count = images.filter(img => img.complete).length;
                
                // Check if minimum requirements are met
                const resourcesReady = resources.every(resource => 
                    resource.count >= resource.required
                );
                
                if (resourcesReady) {
                    resolve();
                }
            }
            
            // Check immediately
            checkResources();
            
            // Check periodically
            const resourceCheck = setInterval(() => {
                checkResources();
            }, 150);
            
            // Resolve after maximum wait
            setTimeout(() => {
                clearInterval(resourceCheck);
                resolve();
            }, 4000);
        });
    }
    
    // Enhanced DOM readiness check
    function waitForDOMReady() {
        return new Promise((resolve) => {
            const criticalSelectors = [
                'body',
                'head',
                'nav, .navbar, header',
                'main, .content, .container, .page-content'
            ];
            
            function checkDOM() {
                const foundElements = criticalSelectors.filter(selector => 
                    document.querySelector(selector)
                ).length;
                
                // Need at least 3 out of 4 critical elements
                if (foundElements >= 3) {
                    resolve();
                }
            }
            
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', () => {
                    setTimeout(checkDOM, 100);
                }, { once: true });
            } else {
                checkDOM();
            }
            
            // Fallback
            setTimeout(resolve, 2000);
        });
    }
    
    // Main loading sequence
    async function initializeLoader() {
        try {
            // Wait for DOM and resources in parallel
            await Promise.all([
                waitForDOMReady(),
                waitForResources()
            ]);
            
            // Wait for window load if not already complete
            if (document.readyState !== 'complete') {
                await new Promise(resolve => {
                    window.addEventListener('load', resolve, { once: true });
                });
            }
            
            // Small delay for final rendering
            await new Promise(resolve => setTimeout(resolve, 300));
            
            // Trigger hide
            triggerHide();
            
        } catch (error) {
            console.log('⚠️ STI Campus Store: Loading error, opening store anyway');
            triggerHide();
        }
    }
    
    // Start the loading process
    initializeLoader();
    
    // Absolute failsafe
    setTimeout(() => {
        if (!loadTriggered) {
            console.log('⏰ STI Campus Store: Maximum wait time reached');
            triggerHide();
        }
    }, maxLoadTime);
    
})();
</script>

<style>
/* STI College E-Commerce Loader Styles */
/* CRITICAL: Show loader immediately and hide page content */
.loader-overlay {
    position: fixed !important;
    top: 0 !important;
    left: 0 !important;
    width: 100% !important;
    height: 100% !important;
    background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%) !important;
    display: flex !important;
    justify-content: center !important;
    align-items: center !important;
    z-index: 99999 !important;
    backdrop-filter: blur(10px) !important;
    transition: opacity 0.6s ease-out, visibility 0.6s ease-out !important;
    opacity: 1 !important;
    visibility: visible !important;
}

/* Hide page content initially until loader is removed */
body {
    overflow: hidden !important;
}

body.loader-active {
    overflow: hidden !important;
}

body.loader-removed {
    overflow: auto !important;
}

/* Ensure all page content is hidden while loader is active */
.loader-overlay.active ~ * {
    visibility: hidden !important;
    opacity: 0 !important;
}

.loader-overlay.fade-out {
    opacity: 0 !important;
    visibility: hidden !important;
}

.loader-container {
    text-align: center;
    padding: 2.5rem;
    background: rgba(255, 255, 255, 0.95);
    border-radius: 20px;
    box-shadow: 
        0 20px 40px rgba(13, 71, 161, 0.1),
        0 10px 20px rgba(253, 216, 53, 0.05),
        inset 0 1px 0 rgba(255, 255, 255, 0.8);
    backdrop-filter: blur(20px);
    border: 1px solid rgba(255, 255, 255, 0.2);
    max-width: 400px;
    width: 90%;
    animation: containerPulse 3s ease-in-out infinite alternate;
}

@keyframes containerPulse {
    0% {
        transform: scale(1);
        box-shadow: 
            0 20px 40px rgba(13, 71, 161, 0.1),
            0 10px 20px rgba(253, 216, 53, 0.05);
    }
    100% {
        transform: scale(1.02);
        box-shadow: 
            0 25px 50px rgba(13, 71, 161, 0.15),
            0 15px 30px rgba(253, 216, 53, 0.08);
    }
}

/* Shopping Loader Container */
.shopping-loader {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 1.5rem;
    margin-bottom: 1.5rem;
}

/* Shopping Cart Animation */
.cart-container {
    position: relative;
    width: 100px;
    height: 80px;
}

.shopping-cart {
    position: relative;
    width: 100%;
    height: 100%;
    animation: cartBounce 2s ease-in-out infinite;
}

@keyframes cartBounce {
    0%, 20%, 50%, 80%, 100% {
        transform: translateY(0) rotate(0deg);
    }
    10% {
        transform: translateY(-8px) rotate(-2deg);
    }
    30% {
        transform: translateY(-4px) rotate(1deg);
    }
    60% {
        transform: translateY(-6px) rotate(-1deg);
    }
    90% {
        transform: translateY(-2px) rotate(0.5deg);
    }
}

.cart-body {
    width: 60px;
    height: 40px;
    background: linear-gradient(135deg, #0d47a1 0%, #1565c0 100%);
    border-radius: 8px 8px 4px 4px;
    position: relative;
    margin-left: 15px;
    box-shadow: 
        0 4px 8px rgba(13, 71, 161, 0.3),
        inset 0 1px 0 rgba(255, 255, 255, 0.2);
}

.cart-body::before {
    content: '';
    position: absolute;
    top: 8px;
    left: 8px;
    right: 8px;
    bottom: 8px;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 4px;
    border: 1px solid rgba(255, 255, 255, 0.2);
}

.cart-handle {
    position: absolute;
    top: 5px;
    left: 0;
    width: 25px;
    height: 30px;
    border: 3px solid #fdd835;
    border-radius: 15px 0 0 15px;
    border-right: none;
    box-shadow: 0 2px 4px rgba(253, 216, 53, 0.3);
}

.cart-wheels {
    position: absolute;
    bottom: -8px;
    left: 20px;
    right: 10px;
    display: flex;
    justify-content: space-between;
}

.wheel {
    width: 12px;
    height: 12px;
    background: #fdd835;
    border-radius: 50%;
    border: 2px solid #0d47a1;
    animation: wheelSpin 1s linear infinite;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
}

@keyframes wheelSpin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* Cart Items */
.cart-items {
    position: absolute;
    top: 10px;
    left: 25px;
    right: 15px;
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.item {
    height: 4px;
    border-radius: 2px;
    animation: itemFloat 2s ease-in-out infinite;
}

.item-1 {
    width: 80%;
    background: #fdd835;
    animation-delay: 0s;
}

.item-2 {
    width: 60%;
    background: #ffeb3b;
    animation-delay: 0.3s;
}

.item-3 {
    width: 70%;
    background: #fff176;
    animation-delay: 0.6s;
}

@keyframes itemFloat {
    0%, 100% {
        transform: translateY(0);
        opacity: 1;
    }
    50% {
        transform: translateY(-2px);
        opacity: 0.8;
    }
}

/* Receipt Printing Effect */
.receipt-container {
    width: 80px;
    height: 100px;
    position: relative;
    overflow: hidden;
}

.receipt {
    width: 70px;
    background: white;
    border: 1px solid #e0e0e0;
    border-radius: 4px 4px 0 0;
    padding: 8px;
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
    position: relative;
    animation: receiptPrint 3s ease-in-out infinite;
}

@keyframes receiptPrint {
    0% {
        transform: translateY(100%);
        opacity: 0;
    }
    30% {
        transform: translateY(0);
        opacity: 1;
    }
    70% {
        transform: translateY(0);
        opacity: 1;
    }
    100% {
        transform: translateY(-10px);
        opacity: 0;
    }
}

.receipt-header {
    font-size: 8px;
    font-weight: bold;
    color: #0d47a1;
    text-align: center;
    margin-bottom: 4px;
    font-family: 'Courier New', monospace;
}

.receipt-line {
    height: 2px;
    background: linear-gradient(90deg, #0d47a1, #fdd835);
    margin: 2px 0;
    border-radius: 1px;
    animation: lineAppear 3s ease-in-out infinite;
}

.receipt-line.short {
    width: 60%;
    animation-delay: 0.2s;
}

.receipt-line.medium {
    width: 80%;
    animation-delay: 0.4s;
}

@keyframes lineAppear {
    0%, 30% {
        width: 0;
        opacity: 0;
    }
    40% {
        opacity: 1;
    }
    60% {
        width: 100%;
        opacity: 1;
    }
    70%, 100% {
        width: 100%;
        opacity: 1;
    }
}

/* Text Styling */
.loader-text {
    color: #333;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
}

.loader-text h3 {
    margin: 0 0 0.5rem;
    font-size: 1.5rem;
    font-weight: 600;
    background: linear-gradient(135deg, #0d47a1 0%, #1565c0 50%, #fdd835 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    animation: textShimmer 2s ease-in-out infinite;
}

.loader-text p {
    margin: 0;
    font-size: 0.9rem;
    color: #666;
    font-weight: 400;
    opacity: 0.8;
    animation: textFade 2s ease-in-out infinite;
}

@keyframes textShimmer {
    0%, 100% {
        background-position: 0% 50%;
    }
    50% {
        background-position: 100% 50%;
    }
}

@keyframes textFade {
    0%, 100% {
        opacity: 0.6;
    }
    50% {
        opacity: 1;
    }
}

/* Shopping bag floating effect */
.cart-container::after {
    content: '🛍️';
    position: absolute;
    top: -20px;
    right: -10px;
    font-size: 16px;
    animation: bagFloat 3s ease-in-out infinite;
    opacity: 0.7;
}

@keyframes bagFloat {
    0%, 100% {
        transform: translateY(0) rotate(0deg);
        opacity: 0.3;
    }
    25% {
        transform: translateY(-5px) rotate(5deg);
        opacity: 0.7;
    }
    50% {
        transform: translateY(-8px) rotate(-3deg);
        opacity: 1;
    }
    75% {
        transform: translateY(-3px) rotate(2deg);
        opacity: 0.8;
    }
}

/* Responsive Design */
@media (max-width: 480px) {
    .loader-container {
        padding: 2rem;
        margin: 1rem;
        border-radius: 15px;
        max-width: 320px;
    }
    
    .cart-container {
        width: 80px;
        height: 65px;
    }
    
    .cart-body {
        width: 48px;
        height: 32px;
    }
    
    .receipt-container {
        width: 65px;
        height: 80px;
    }
    
    .receipt {
        width: 55px;
        padding: 6px;
    }
    
    .loader-text h3 {
        font-size: 1.25rem;
    }
    
    .loader-text p {
        font-size: 0.85rem;
    }
}

@media (max-width: 320px) {
    .loader-container {
        padding: 1.5rem;
    }
    
    .shopping-loader {
        gap: 1rem;
        margin-bottom: 1rem;
    }
    
    .cart-container {
        width: 70px;
        height: 55px;
    }
    
    .loader-text h3 {
        font-size: 1.1rem;
    }
}

/* Adding subtle payment/purchase effects */
.shopping-loader::before {
    content: '';
    position: absolute;
    top: -30px;
    left: 50%;
    transform: translateX(-50%);
    width: 200px;
    height: 2px;
    background: linear-gradient(90deg, transparent, #fdd835, #0d47a1, #fdd835, transparent);
    animation: progressBar 3s ease-in-out infinite;
    border-radius: 1px;
}

@keyframes progressBar {
    0% {
        width: 0;
        opacity: 0;
    }
    20% {
        width: 50px;
        opacity: 1;
    }
    50% {
        width: 150px;
        opacity: 1;
    }
    80% {
        width: 200px;
        opacity: 1;
    }
    100% {
        width: 200px;
        opacity: 0;
    }
}
</style>

<script>
// STI E-Commerce Loader JavaScript with immediate display and proper hiding
(function() {
    'use strict';
    
    // Add loader-active class to body immediately
    document.body.classList.add('loader-active');
    
    const loader = document.getElementById('page-loader');
    if (!loader) return; // Exit if loader not found
    
    // Make loader active immediately
    loader.classList.add('active');
    
    // Configuration
    const minLoadTime = 2000; // 2 seconds minimum
    const maxLoadTime = 6000; // Maximum 6 seconds
    const startTime = Date.now();
    
    console.log('🛒 STI Campus Store: Loader displayed, initializing shopping experience...');
    
    function hideLoader() {
        const elapsedTime = Date.now() - startTime;
        const remainingTime = Math.max(0, minLoadTime - elapsedTime);
        
        console.log('🛍️ STI Campus Store: Content ready, preparing to show...');
        
        setTimeout(() => {
            if (loader && !loader.classList.contains('fade-out')) {
                // Start fade out
                loader.classList.remove('active');
                loader.classList.add('fade-out');
                
                // Update body classes
                document.body.classList.remove('loader-active');
                document.body.classList.add('loader-removed');
                
                // Remove loader from DOM after fade animation
                setTimeout(() => {
                    if (loader && loader.parentNode) {
                        loader.parentNode.removeChild(loader);
                    }
                    console.log('✅ STI Campus Store: Welcome! Store is ready for shopping!');
                }, 600); // Match CSS transition duration
            }
        }, remainingTime);
    }
    
    // Multiple detection methods for better compatibility
    let loadTriggered = false;
    
    function triggerHide() {
        if (loadTriggered) return;
        loadTriggered = true;
        hideLoader();
    }
    
    // Wait for critical resources to load
    function waitForCriticalResources() {
        return new Promise((resolve) => {
            // Check for CSS files to be loaded
            const criticalStyles = [
                'global.css',
                'header.css',
                'ProItemList.css'
            ];
            
            let loadedStyles = 0;
            const totalStyles = criticalStyles.length;
            
            // Check if stylesheets are loaded
            function checkStyleSheets() {
                const sheets = Array.from(document.styleSheets);
                loadedStyles = criticalStyles.filter(style => 
                    sheets.some(sheet => sheet.href && sheet.href.includes(style))
                ).length;
                
                if (loadedStyles >= totalStyles || sheets.length >= 3) {
                    resolve();
                }
            }
            
            // Check immediately
            checkStyleSheets();
            
            // Also check periodically
            const styleCheck = setInterval(() => {
                checkStyleSheets();
                if (loadedStyles >= totalStyles) {
                    clearInterval(styleCheck);
                    resolve();
                }
            }, 100);
            
            // Fallback timeout
            setTimeout(() => {
                clearInterval(styleCheck);
                resolve();
            }, 3000);
        });
    }
    
    // Wait for DOM and critical elements
    function waitForPageElements() {
        return new Promise((resolve) => {
            const criticalElements = [
                'body',
                '.navbar, header, nav',
                '.content, main, .container'
            ];
            
            function checkElements() {
                let foundElements = 0;
                criticalElements.forEach(selector => {
                    if (document.querySelector(selector)) {
                        foundElements++;
                    }
                });
                
                // If we found at least 2 critical elements, consider page ready
                if (foundElements >= 2) {
                    resolve();
                }
            }
            
            // Check immediately
            checkElements();
            
            // Check periodically
            const elementCheck = setInterval(() => {
                checkElements();
            }, 100);
            
            // Resolve after maximum wait time
            setTimeout(() => {
                clearInterval(elementCheck);
                resolve();
            }, 2000);
        });
    }
    
    // Main loading logic
    async function initLoader() {
        try {
            // Wait for DOM content
            if (document.readyState === 'loading') {
                await new Promise(resolve => {
                    document.addEventListener('DOMContentLoaded', resolve, { once: true });
                });
            }
            
            // Wait for critical resources and elements
            await Promise.all([
                waitForCriticalResources(),
                waitForPageElements()
            ]);
            
            // Wait for window load
            if (document.readyState !== 'complete') {
                await new Promise(resolve => {
                    window.addEventListener('load', resolve, { once: true });
                });
            }
            
            // Additional small delay for layout painting
            await new Promise(resolve => setTimeout(resolve, 500));
            
            // Now trigger hide
            triggerHide();
            
        } catch (error) {
            console.log('⚠️ STI Campus Store: Error in loader, showing store anyway');
            triggerHide();
        }
    }
    
    // Start the loading process
    initLoader();
    
    // Absolute fallback: hide loader after maximum time
    setTimeout(() => {
        if (loader && !loader.classList.contains('fade-out')) {
            console.log('⏰ STI Campus Store: Maximum wait time reached, opening store...');
            triggerHide();
        }
    }, maxLoadTime);
    
})();
</script>
</div>

<style>
  .absolute {
    position: absolute;
  }

  .inline-block {
    display: inline-block;
  }

  .loader {
    display: flex;
    justify-content: center;
    align-items: center;
    height: 100vh;
    background: white;
  }

  .w-2 {
    width: 0.5em;
  }

  .dash {
    animation: dashArray 2s ease-in-out infinite,
      dashOffset 2s linear infinite;
  }

  @keyframes dashArray {
    0% {
      stroke-dasharray: 0 1 359 0;
    }
    50% {
      stroke-dasharray: 0 359 1 0;
    }
    100% {
      stroke-dasharray: 359 1 0 0;
    }
  }

  @keyframes dashOffset {
    0% {
      stroke-dashoffset: 365;
    }
    100% {
      stroke-dashoffset: 5;
    }
  }
</style>
