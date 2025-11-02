<?php

?>
<style>
#pamo-loader.sti-loader-overlay {
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

#pamo-loader.sti-loader-overlay.hidden {
    opacity: 0 !important;
    visibility: hidden !important;
    pointer-events: none !important;
    backdrop-filter: blur(0px) !important;
    -webkit-backdrop-filter: blur(0px) !important;
}

#pamo-loader .sti-modal-container {
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

#pamo-loader.sti-loader-overlay.hidden .sti-modal-container {
    transform: scale(0.9) !important;
}

#pamo-loader .sti-loader-container {
    text-align: center !important;
    position: relative !important;
    z-index: 3 !important;
}
</style>

<div id="pamo-loader" class="sti-loader-overlay" style="display: flex;">
    <div class="sti-modal-container">
            <div class="sti-warehouse-stack">
                <div class="warehouse-base">
                    <div class="warehouse-floor"></div>
                    <div class="box-stack">
                        <div class="inventory-box box-1">
                            <div class="box-face box-top"></div>
                            <div class="box-face box-front"></div>
                            <div class="box-face box-side"></div>
                        </div>
                        <div class="inventory-box box-2">
                            <div class="box-face box-top"></div>
                            <div class="box-face box-front"></div>
                            <div class="box-face box-side"></div>
                        </div>
                        <div class="inventory-box box-3">
                            <div class="box-face box-top"></div>
                            <div class="box-face box-front"></div>
                            <div class="box-face box-side"></div>
                        </div>
                    </div>
                </div>
                <div class="floating-inventory-items">
                    <div class="inventory-item item-1">📦</div>
                    <div class="inventory-item item-2">📊</div>
                    <div class="inventory-item item-3">🏪</div>
                    <div class="inventory-item item-4">📋</div>
                </div>
            </div>

            <div class="sti-loading-text">
                <span class="loading-word">Processing</span>
                <span class="loading-dots">
                    <span>.</span>
                    <span>.</span>
                    <span>.</span>
                </span>
            </div>

            <div class="sti-brand">
                <div class="sti-logo-placeholder">STI</div>
                <p class="sti-tagline">PAMO Inventory System</p>
            </div>
    </div>
</div>

<style>
#pamo-loader .sti-warehouse-stack {
    position: relative !important;
    margin-bottom: 40px !important;
    animation: warehousePulse 2.5s ease-in-out infinite !important;
    display: block !important;
    height: 120px !important;
}

#pamo-loader .warehouse-base {
    position: relative !important;
    width: 100px !important;
    height: 80px !important;
    margin: 0 auto !important;
    display: block !important;
}

#pamo-loader .warehouse-floor {
    position: absolute !important;
    bottom: 0 !important;
    left: 50% !important;
    transform: translateX(-50%) !important;
    width: 120px !important;
    height: 8px !important;
    background: #FFCB05 !important;
    border-radius: 4px !important;
    box-shadow: 0 4px 15px rgba(255, 203, 5, 0.4) !important;
    display: block !important;
}

#pamo-loader .box-stack {
    position: relative !important;
    height: 72px !important;
    display: block !important;
}

#pamo-loader .inventory-box {
    position: absolute !important;
    width: 28px !important;
    height: 20px !important;
    left: 50% !important;
    transform: translateX(-50%) !important;
    transform-style: preserve-3d !important;
    display: block !important;
}

#pamo-loader .box-1 {
    bottom: 8px !important;
    animation: boxStack1 3s ease-in-out infinite !important;
    z-index: 3 !important;
}

#pamo-loader .box-2 {
    bottom: 28px !important;
    animation: boxStack2 3s ease-in-out infinite 0.3s !important;
    z-index: 2 !important;
}

#pamo-loader .box-3 {
    bottom: 48px !important;
    animation: boxStack3 3s ease-in-out infinite 0.6s !important;
    z-index: 1 !important;
}

#pamo-loader .box-face {
    position: absolute !important;
    display: block !important;
}

#pamo-loader .box-top {
    width: 28px !important;
    height: 28px !important;
    background: #FFCB05 !important;
    top: -14px !important;
    left: 0 !important;
    transform: rotateX(90deg) translateZ(10px) !important;
    border: 1px solid #e6b800 !important;
}

#pamo-loader .box-front {
    width: 28px !important;
    height: 20px !important;
    background: #FFD633 !important;
    top: 0 !important;
    left: 0 !important;
    border: 1px solid #e6b800 !important;
}

#pamo-loader .box-side {
    width: 28px !important;
    height: 20px !important;
    background: #E6B800 !important;
    top: 0 !important;
    right: -14px !important;
    transform: rotateY(90deg) translateZ(14px) !important;
    border: 1px solid #cc9900 !important;
}

#pamo-loader .floating-inventory-items {
    position: absolute !important;
    top: -60px !important;
    left: 50% !important;
    transform: translateX(-50%) !important;
    width: 160px !important;
    height: 120px !important;
    display: block !important;
    z-index: 10 !important;
}

#pamo-loader .inventory-item {
    position: absolute !important;
    font-size: 28px !important;
    animation: inventoryFloat 3.5s ease-in-out infinite !important;
    display: block !important;
    animation-fill-mode: both !important;
    z-index: 15 !important;
    filter: drop-shadow(2px 2px 4px rgba(0, 0, 0, 0.3)) !important;
}

#pamo-loader .item-1 {
    left: 10px !important;
    top: 20px !important;
    animation-delay: 0s !important;
}

#pamo-loader .item-2 {
    right: 10px !important;
    top: 15px !important;
    animation-delay: 0.8s !important;
}

#pamo-loader .item-3 {
    left: 20px !important;
    bottom: 25px !important;
    animation-delay: 1.6s !important;
}

#pamo-loader .item-4 {
    right: 20px !important;
    bottom: 20px !important;
    animation-delay: 2.4s !important;
}

#pamo-loader .sti-loading-text {
    color: white !important;
    font-size: 18px !important;
    font-weight: 500 !important;
    display: block !important;
    margin-bottom: 25px !important;
    letter-spacing: 0.5px !important;
}

#pamo-loader .loading-dots span {
    animation: dotPulse 1.5s ease-in-out infinite !important;
    display: inline !important;
    font-size: 20px !important;
}

#pamo-loader .loading-dots span:nth-child(2) {
    animation-delay: 0.2s !important;
}

#pamo-loader .loading-dots span:nth-child(3) {
    animation-delay: 0.4s !important;
}

#pamo-loader .sti-brand {
    color: white !important;
    display: block !important;
}

#pamo-loader .sti-logo-placeholder {
    font-size: 28px !important;
    font-weight: bold !important;
    color: #FFCB05 !important;
    margin-bottom: 8px !important;
    text-shadow: 2px 2px 6px rgba(0, 0, 0, 0.4) !important;
    display: block !important;
    letter-spacing: 2px !important;
}

#pamo-loader .sti-tagline {
    font-size: 13px !important;
    opacity: 0.9 !important;
    margin: 0 !important;
    display: block !important;
    letter-spacing: 0.3px !important;
}

@keyframes warehousePulse {
    0%, 100% {
        transform: translateY(0) scale(1);
    }
    50% {
        transform: translateY(-8px) scale(1.02);
    }
}

@keyframes boxStack1 {
    0%, 100% {
        transform: translateX(-50%) translateY(0) rotateY(0deg);
    }
    25% {
        transform: translateX(-50%) translateY(-5px) rotateY(5deg);
    }
    75% {
        transform: translateX(-50%) translateY(-2px) rotateY(-5deg);
    }
}

@keyframes boxStack2 {
    0%, 100% {
        transform: translateX(-50%) translateY(0) rotateY(0deg);
    }
    25% {
        transform: translateX(-50%) translateY(-8px) rotateY(-3deg);
    }
    75% {
        transform: translateX(-50%) translateY(-3px) rotateY(3deg);
    }
}

@keyframes boxStack3 {
    0%, 100% {
        transform: translateX(-50%) translateY(0) rotateY(0deg);
    }
    25% {
        transform: translateX(-50%) translateY(-12px) rotateY(8deg);
    }
    75% {
        transform: translateX(-50%) translateY(-4px) rotateY(-8deg);
    }
}

@keyframes inventoryFloat {
    0% {
        opacity: 0;
        transform: translateY(40px) scale(0.6) rotate(0deg);
    }
    25% {
        opacity: 1;
        transform: translateY(-30px) scale(1.2) rotate(10deg);
    }
    75% {
        opacity: 1;
        transform: translateY(-20px) scale(1) rotate(-5deg);
    }
    100% {
        opacity: 0;
        transform: translateY(40px) scale(0.6) rotate(0deg);
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
    #pamo-loader .sti-modal-container {
        padding: 40px 30px !important;
        margin: 20px !important;
        min-width: 280px !important;
        max-width: 350px !important;
    }
    
    #pamo-loader .sti-warehouse-stack {
        transform: scale(0.9) !important;
        margin-bottom: 25px !important;
        height: 110px !important;
    }
    
    #pamo-loader .sti-loading-text {
        font-size: 16px !important;
        margin-bottom: 20px !important;
    }
    
    #pamo-loader .sti-logo-placeholder {
        font-size: 24px !important;
    }
    
    #pamo-loader .sti-tagline {
        font-size: 12px !important;
    }
}

@media (max-width: 480px) {
    #pamo-loader .sti-modal-container {
        padding: 35px 25px !important;
        margin: 15px !important;
        min-width: 250px !important;
        max-width: 300px !important;
        border-radius: 15px !important;
    }
    
    #pamo-loader .sti-warehouse-stack {
        transform: scale(0.8) !important;
        margin-bottom: 20px !important;
        height: 100px !important;
    }
    
    #pamo-loader .sti-loading-text {
        font-size: 15px !important;
        margin-bottom: 18px !important;
    }
    
    #pamo-loader .sti-logo-placeholder {
        font-size: 22px !important;
    }
}
</style>

<script>
(function() {
    // Check if we should skip the loader (after successful AJAX action)
    if (sessionStorage.getItem('skipPAMOLoader') === 'true') {
        sessionStorage.removeItem('skipPAMOLoader');
        const loader = document.getElementById('pamo-loader');
        if (loader) {
            loader.style.display = 'none';
            loader.classList.add('hidden');
        }
        return; // Exit early, don't show loader
    }

    'use strict';

    function enforceLoaderVisibility() {
        const loader = document.getElementById('pamo-loader');
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
                #pamo-loader * { animation-play-state: running !important; }
                #pamo-loader .sti-warehouse-stack { animation: warehousePulse 2.5s ease-in-out infinite !important; }
                #pamo-loader .inventory-box { animation-play-state: running !important; }
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

            // PAMO-specific exclusions for inventory management elements
            const isPamoPopupAction = target.closest('.inventory-popup') ||
                                    target.closest('.order-details-popup') ||
                                    target.closest('.notification-panel') ||
                                    target.closest('.quick-actions') ||
                                    target.classList.contains('fa-eye') ||
                                    target.classList.contains('fa-edit') ||
                                    target.classList.contains('fa-trash') ||
                                    target.classList.contains('inventory-quick-view') ||
                                    target.classList.contains('status-toggle') ||
                                    target.getAttribute('data-action') === 'quick-view' ||
                                    target.getAttribute('data-action') === 'inline-edit';

            const isInstantAction = target.classList.contains('instant') ||
                                   target.getAttribute('data-loader') === 'false' ||
                                   target.getAttribute('data-instant') === 'true';

            if (isLogoutAction || isPamoPopupAction) {
                setTimeout(() => {
                    if (!e.defaultPrevented) {
                    }
                }, 10);
                return;
            }

            if (!isInstantAction) {
                if (window.PAMOLoaderState) {
                    window.PAMOLoaderState.isNavigating = true;
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
                             form.getAttribute('data-instant') === 'true' ||
                             form.classList.contains('ajax-form') ||
                             form.getAttribute('data-action') === 'quick-update';

        if (!isLogoutForm && !isInstantForm) {
            enforceLoaderVisibility();
        }
    }, true);

    setTimeout(() => {
        if (window.PAMOLoaderState) {
            window.PAMOLoaderState.setNavigating(false);
        }
    }, 1000);
})();

document.addEventListener('DOMContentLoaded', function() {
    const loader = document.getElementById('pamo-loader');

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

    window.PAMOLoaderState = {
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
            window.PAMOLoaderState.setNavigating(false);
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
            if (document.readyState === 'complete' && !isNavigating && !window.PAMOLoaderState.isNavigating) {
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

    // Global API
    window.PAMOLoader = {
        show: showLoader,
        hide: hideLoader,
        forceHide: () => {
            hideLoader();
        }
    };

    // Add keyboard shortcut for testing (Ctrl+H to hide loader)
    document.addEventListener('keydown', function(e) {
        if (e.ctrlKey && e.key === 'h') {
            hideLoader();
        }
    });
});
</script>
