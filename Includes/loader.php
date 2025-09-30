<?php

?>
<style>
#page-loader.sti-loader-overlay {
    position: fixed !important;
    top: 0 !important;
    left: 0 !important;
    width: 100% !important;
    height: 100% !important;
    background: rgba(0, 0, 0, 0.4) !important;
    backdrop-filter: blur(8px) !important;
    -webkit-backdrop-filter: blur(8px) !important;
    z-index: 99999 !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif !important;
    transition: all 0.4s ease-out !important;
    visibility: visible !important;
    opacity: 1 !important;
    pointer-events: auto !important;
}

#page-loader.sti-loader-overlay.hidden {
    opacity: 0 !important;
    visibility: hidden !important;
    pointer-events: none !important;
    backdrop-filter: blur(0px) !important;
    -webkit-backdrop-filter: blur(0px) !important;
}

#page-loader .sti-modal-container {
    background: linear-gradient(135deg, #004AAD 0%, #0066cc 50%, #004AAD 100%) !important;
    border-radius: 20px !important;
    padding: 50px 40px !important;
    box-shadow: 0 25px 50px rgba(0, 0, 0, 0.3) !important;
    text-align: center !important;
    position: relative !important;
    z-index: 3 !important;
    min-width: 300px !important;
    max-width: 400px !important;
    transform: scale(1) !important;
    transition: transform 0.3s ease-out !important;
}

#page-loader.sti-loader-overlay.hidden .sti-modal-container {
    transform: scale(0.9) !important;
}

#page-loader .sti-loader-container {
    text-align: center !important;
    position: relative !important;
    z-index: 3 !important;
}
</style>

<div id="page-loader" class="sti-loader-overlay" style="display: flex;">
    <div class="sti-modal-container">
            <div class="sti-shopping-cart">
                <div class="cart-body">
                    <div class="cart-wheel cart-wheel-1"></div>
                    <div class="cart-wheel cart-wheel-2"></div>
                    <div class="cart-handle"></div>
                </div>
                <div class="floating-items">
                    <div class="item item-1">📚</div>
                    <div class="item item-2">🎓</div>
                    <div class="item item-3">👔</div>
                    <div class="item item-4">📝</div>
                </div>
            </div>

            <div class="sti-loading-text">
                <span class="loading-word">Loading</span>
                <span class="loading-dots">
                    <span>.</span>
                    <span>.</span>
                    <span>.</span>
                </span>
            </div>

            <div class="sti-brand">
                <div class="sti-logo-placeholder">STI</div>
                <p class="sti-tagline">Proware E-Commerce System</p>
            </div>
    </div>
</div>

<style>
#page-loader .sti-shopping-cart {
    position: relative !important;
    margin-bottom: 40px !important;
    animation: cartBounce 2s ease-in-out infinite !important;
    display: block !important;
}

#page-loader .cart-body {
    width: 80px !important;
    height: 60px !important;
    background: #FFCB05 !important;
    border-radius: 8px 8px 4px 4px !important;
    position: relative !important;
    margin: 0 auto !important;
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2) !important;
    display: block !important;
}

#page-loader .cart-body::before {
    content: '' !important;
    position: absolute !important;
    top: 10px !important;
    left: 10px !important;
    right: 10px !important;
    height: 30px !important;
    background: rgba(255, 255, 255, 0.2) !important;
    border-radius: 4px !important;
}

#page-loader .cart-wheel {
    width: 16px !important;
    height: 16px !important;
    background: #004AAD !important;
    border-radius: 50% !important;
    position: absolute !important;
    bottom: -8px !important;
    animation: wheelSpin 1s linear infinite !important;
    display: block !important;
}

#page-loader .cart-wheel-1 {
    left: 15px !important;
}

#page-loader .cart-wheel-2 {
    right: 15px !important;
}

#page-loader .cart-handle {
    width: 50px !important;
    height: 35px !important;
    border: 4px solid #FFCB05 !important;
    border-bottom: none !important;
    border-radius: 25px 25px 0 0 !important;
    position: absolute !important;
    top: -30px !important;
    left: 50% !important;
    transform: translateX(-50%) !important;
    display: block !important;
}

#page-loader .floating-items {
    position: absolute !important;
    top: -50px !important;
    left: 50% !important;
    transform: translateX(-50%) !important;
    width: 140px !important;
    height: 100px !important;
    display: block !important;
    z-index: 10 !important;
}

#page-loader .item {
    position: absolute !important;
    font-size: 24px !important;
    animation: itemFloat 3s ease-in-out infinite !important;
    display: block !important;
    animation-fill-mode: both !important;
    z-index: 15 !important;
}

#page-loader .item-1 {
    left: 5px !important;
    top: 15px !important;
    animation-delay: 0s !important;
}

#page-loader .item-2 {
    right: 5px !important;
    top: 10px !important;
    animation-delay: 0.7s !important;
}

#page-loader .item-3 {
    left: 15px !important;
    bottom: 15px !important;
    animation-delay: 1.4s !important;
}

#page-loader .item-4 {
    right: 15px !important;
    bottom: 10px !important;
    animation-delay: 2.1s !important;
}

#page-loader .sti-loading-text {
    color: white !important;
    font-size: 18px !important;
    font-weight: 500 !important;
    display: block !important;
    margin-bottom: 25px !important;
    letter-spacing: 0.5px !important;
}

#page-loader .loading-dots span {
    animation: dotPulse 1.5s ease-in-out infinite !important;
    display: inline !important;
    font-size: 20px !important;
}

#page-loader .loading-dots span:nth-child(2) {
    animation-delay: 0.2s !important;
}

#page-loader .loading-dots span:nth-child(3) {
    animation-delay: 0.4s !important;
}

#page-loader .sti-brand {
    color: white !important;
    display: block !important;
}

#page-loader .sti-logo-placeholder {
    font-size: 28px !important;
    font-weight: bold !important;
    color: #FFCB05 !important;
    margin-bottom: 8px !important;
    text-shadow: 2px 2px 6px rgba(0, 0, 0, 0.4) !important;
    display: block !important;
    letter-spacing: 2px !important;
}

#page-loader .sti-tagline {
    font-size: 13px !important;
    opacity: 0.9 !important;
    margin: 0 !important;
    display: block !important;
    letter-spacing: 0.3px !important;
}

@keyframes cartBounce {
    0%, 100% {
        transform: translateY(0);
    }
    50% {
        transform: translateY(-10px);
    }
}

@keyframes wheelSpin {
    0% {
        transform: rotate(0deg);
    }
    100% {
        transform: rotate(360deg);
    }
}

@keyframes itemFloat {
    0% {
        opacity: 0;
        transform: translateY(30px) scale(0.7);
    }
    25% {
        opacity: 1;
        transform: translateY(-25px) scale(1.1);
    }
    75% {
        opacity: 1;
        transform: translateY(-15px) scale(1);
    }
    100% {
        opacity: 0;
        transform: translateY(30px) scale(0.7);
    }
}

@keyframes dotPulse {
    0%, 60%, 100% {
        opacity: 0.4;
    }
    30% {
        opacity: 1;
    }
}

@media (max-width: 768px) {
    #page-loader .sti-modal-container {
        padding: 40px 30px !important;
        margin: 20px !important;
        min-width: 280px !important;
        max-width: 350px !important;
    }
    
    #page-loader .sti-shopping-cart {
        transform: scale(0.9) !important;
        margin-bottom: 25px !important;
    }
    
    #page-loader .sti-loading-text {
        font-size: 16px !important;
        margin-bottom: 20px !important;
    }
    
    #page-loader .sti-logo-placeholder {
        font-size: 24px !important;
    }
    
    #page-loader .sti-tagline {
        font-size: 12px !important;
    }
}

@media (max-width: 480px) {
    #page-loader .sti-modal-container {
        padding: 35px 25px !important;
        margin: 15px !important;
        min-width: 250px !important;
        max-width: 300px !important;
        border-radius: 15px !important;
    }
    
    #page-loader .sti-shopping-cart {
        transform: scale(0.8) !important;
        margin-bottom: 20px !important;
    }
    
    #page-loader .sti-loading-text {
        font-size: 15px !important;
        margin-bottom: 18px !important;
    }
    
    #page-loader .sti-logo-placeholder {
        font-size: 22px !important;
    }
}
</style>

<script>
(function() {
    'use strict';

    function enforceLoaderVisibility() {
        const loader = document.getElementById('page-loader');
        if (loader) {
            loader.style.cssText = `
                position: fixed !important;
                top: 0 !important;
                left: 0 !important;
                width: 100% !important;
                height: 100% !important;
                background: rgba(0, 0, 0, 0.4) !important;
                backdrop-filter: blur(8px) !important;
                -webkit-backdrop-filter: blur(8px) !important;
                z-index: 99999 !important;
                display: flex !important;
                align-items: center !important;
                justify-content: center !important;
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif !important;
                visibility: visible !important;
                opacity: 1 !important;
                pointer-events: auto !important;
            `;
            loader.classList.remove('hidden');
            const style = document.createElement('style');
            style.textContent = `
                #page-loader * { animation-play-state: running !important; }
                #page-loader .sti-shopping-cart { animation: cartBounce 2s ease-in-out infinite !important; }
                #page-loader .cart-wheel { animation: wheelSpin 1s linear infinite !important; }
                #page-loader .sti-progress-glow { animation: progressGlow 2s ease-in-out infinite !important; }
            `;
            document.head.appendChild(style);
        }
    }

    enforceLoaderVisibility();
    document.addEventListener('click', function(e) {
        const target = e.target.closest('a');
        if (target && target.href && !target.target && !target.download && 
            !target.href.startsWith('#') && !target.href.startsWith('javascript:') &&
            !target.href.startsWith('mailto:') && !target.href.startsWith('tel:')) {

            const isLogoutAction = target.href.includes('logout') || 
                                 target.href.includes('Logout') ||
                                 target.textContent.toLowerCase().includes('logout') ||
                                 target.textContent.toLowerCase().includes('log out') ||
                                 target.classList.contains('logout') ||
                                 target.classList.contains('logout-btn') ||
                                 target.getAttribute('data-action') === 'logout' ||
                                 target.getAttribute('onclick') && target.getAttribute('onclick').includes('signOut') ||
                                 target.classList.contains('fa-sign-out-alt') ||
                                 target.getAttribute('title') === 'Sign Out';

            const isInstantAction = target.classList.contains('instant') ||
                                   target.getAttribute('data-loader') === 'false' ||
                                   target.getAttribute('data-instant') === 'true';

            if (isLogoutAction) {
                setTimeout(() => {
                    if (!e.defaultPrevented) {
                    }
                }, 10);
                return;
            }

            if (!isInstantAction) {
                if (window.STILoaderState) {
                    window.STILoaderState.isNavigating = true;
                }
                enforceLoaderVisibility();
            }
        }
    }, true);

    document.addEventListener('submit', function(e) {
        const form = e.target;

        const isLogoutForm = form.action.includes('logout') || 
                            form.action.includes('Logout') ||
                            form.classList.contains('logout-form') ||
                            form.getAttribute('data-action') === 'logout';

        const isInstantForm = form.classList.contains('instant') ||
                             form.getAttribute('data-loader') === 'false' ||
                             form.getAttribute('data-instant') === 'true';

        if (!isLogoutForm && !isInstantForm) {
            enforceLoaderVisibility();
        }
    }, true);

    setTimeout(() => {
        window.STILoaderState.setNavigating(false);
    }, 1000);
})();

document.addEventListener('DOMContentLoaded', function() {
    const loader = document.getElementById('page-loader');

    function hideLoader() {
        if (loader) {
            loader.classList.add('hidden');
            loader.style.opacity = '0';
            loader.style.visibility = 'hidden';
            loader.style.pointerEvents = 'none';

            setTimeout(() => {
                if (loader) {
                    loader.style.display = 'none';
                }
            }, 400);
        }
    }

    function showLoader() {
        if (loader) {
            loader.style.cssText = `
                position: fixed !important;
                top: 0 !important;
                left: 0 !important;
                width: 100% !important;
                height: 100% !important;
                background: rgba(0, 0, 0, 0.4) !important;
                backdrop-filter: blur(8px) !important;
                -webkit-backdrop-filter: blur(8px) !important;
                z-index: 99999 !important;
                display: flex !important;
                align-items: center !important;
                justify-content: center !important;
                visibility: visible !important;
                opacity: 1 !important;
                pointer-events: auto !important;
            `;
            loader.classList.remove('hidden');
        }
    }

    let isNavigating = false;
    let hideTimeout;
    let readyStateInterval;

    window.STILoaderState = {
        isNavigating: false,
        setNavigating: function(state) {
            isNavigating = state;
            this.isNavigating = state;
        }
    };

    function clearAllTimers() {
        if (hideTimeout) {
            clearTimeout(hideTimeout);
            hideTimeout = null;
        }
        if (readyStateInterval) {
            clearInterval(readyStateInterval);
            readyStateInterval = null;
        }
    }

    function safeHideLoader() {
        if (document.readyState === 'complete') {
            window.STILoaderState.setNavigating(false);
            isNavigating = false;
            
            clearAllTimers();
            hideLoader();
        }
    }

    window.addEventListener('load', function() {
        hideTimeout = setTimeout(() => {
            safeHideLoader();
        }, 1200);
    });

    function startReadyStateCheck() {
        readyStateInterval = setInterval(() => {
            if (document.readyState === 'complete' && !isNavigating && !window.STILoaderState.isNavigating) {
                safeHideLoader();
            }
        }, 300);

        setTimeout(() => {
            if (readyStateInterval) {
                clearInterval(readyStateInterval);
                readyStateInterval = null;
            }
        }, 8000);
    }

    setTimeout(startReadyStateCheck, 2000);

    setTimeout(() => {
        clearAllTimers();
        hideLoader();
    }, 8000);

    window.STILoader = {
        show: showLoader,
        hide: hideLoader,
        forceHide: () => {
            hideLoader();
        }
    };

    document.addEventListener('keydown', function(e) {
        if (e.ctrlKey && e.key === 'h') {
            hideLoader();
        }
    });
});
</script>
