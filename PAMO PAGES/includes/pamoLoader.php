<?php
?>

<div id="pamo-loader" class="loader-overlay">
    <div class="loader-container">
        <div class="pamo-animation">
            <svg class="clipboard-svg" width="80" height="80" viewBox="0 0 64 64" fill="none">
                <rect x="12" y="10" width="40" height="50" rx="6" 
                      stroke="#0d47a1" stroke-width="3" fill="white"/>
                <rect x="20" y="5" width="24" height="10" rx="3" 
                      fill="#fdd835" stroke="#0d47a1" stroke-width="2"/>
                <line x1="20" y1="25" x2="44" y2="25" stroke="#0d47a1" stroke-width="2"/>
                <line x1="20" y1="35" x2="44" y2="35" stroke="#0d47a1" stroke-width="2"/>
                <line x1="20" y1="45" x2="36" y2="45" stroke="#0d47a1" stroke-width="2"/>
                <circle cx="16" cy="25" r="2" fill="#0d47a1"/>
                <circle cx="16" cy="35" r="2" fill="#0d47a1"/>
                <circle cx="16" cy="45" r="2" fill="#0d47a1"/>
            </svg>
            <div class="checkmark"></div>
        </div>

        <div class="loader-text">
            <h3>PAMO System</h3>
            <p>Setting up your workspace...</p>
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
    position: fixed;
    inset: 0;
    background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
    display: flex;
    justify-content: center;
    align-items: center;
    z-index: 99999;
    transition: opacity 0.8s ease, visibility 0.8s ease;
}

.loader-overlay.fade-out {
    opacity: 0;
    visibility: hidden;
}

/* Loader Container */
.loader-container {
    text-align: center;
    padding: 2.5rem;
    /background: rgba(255, 255, 255, 0.98);
    border-radius: 20px;
    box-shadow: 0 25px 50px rgba(13, 71, 161, 0.08),
                0 15px 35px rgba(253, 216, 53, 0.04),
                inset 0 1px 0 rgba(255, 255, 255, 0.9);
    backdrop-filter: blur(15px);
    border: 1px solid rgba(255, 255, 255, 0.3);
    max-width: 400px;
    width: 90%;
    animation: containerEntrance 0.6s ease-out;
}

/* Clipboard Animation */
.pamo-animation {
    position: relative;
    display: inline-block;
    margin-bottom: 1.5rem;
}

.clipboard-svg {
    filter: drop-shadow(0 3px 6px rgba(13, 71, 161, 0.2));
    animation: clipboardBounce 3s ease-in-out infinite;
}

@keyframes clipboardBounce {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-4px); }
}

/* Checkmark pulse */
.checkmark {
    position: absolute;
    bottom: -10px;
    right: -10px;
    width: 18px;
    height: 18px;
    border-radius: 50%;
    background: #fdd835;
    border: 2px solid #0d47a1;
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0%, 100% { transform: scale(1); opacity: 0.7; }
    50% { transform: scale(1.2); opacity: 1; }
}

/* Text */
.loader-text h3 {
    margin: 0 0 0.5rem;
    font-size: 1.5rem;
    font-weight: 600;
    background: linear-gradient(135deg, #0d47a1 0%, #1976d2 50%, #fdd835 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    animation: textShimmer 3s ease-in-out infinite;
}

.loader-text p {
    margin: 0;
    font-size: 1rem;
    color: #666;
    font-weight: 400;
    opacity: 0.8;
}

/* Progress Dots */
.progress-dots {
    display: flex;
    justify-content: center;
    gap: 8px;
    margin-top: 1rem;
}

.dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #0d47a1;
    animation: dotPulse 1.5s ease-in-out infinite;
}

.dot:nth-child(2) { animation-delay: 0.2s; }
.dot:nth-child(3) { animation-delay: 0.4s; }

@keyframes dotPulse {
    0%, 100% { transform: scale(1); background: #0d47a1; }
    50% { transform: scale(1.3); background: #fdd835; }
}

/* Entrance */
@keyframes containerEntrance {
    from { transform: scale(0.8) translateY(20px); opacity: 0; }
    to { transform: scale(1) translateY(0); opacity: 1; }
}
</style>

<script>
// PAMO Loader Script
(function() {
    document.body.classList.add('loader-active');
    const loader = document.getElementById('pamo-loader');
    const minLoadTime = 2000;
    const startTime = Date.now();

    function hideLoader() {
        const elapsed = Date.now() - startTime;
        const remaining = Math.max(0, minLoadTime - elapsed);
        setTimeout(() => {
            loader.classList.add('fade-out');
            document.body.classList.remove('loader-active');
            document.body.classList.add('loader-removed');
            setTimeout(() => loader.remove(), 800);
        }, remaining);
    }

    window.addEventListener('load', hideLoader);
    setTimeout(hideLoader, 6000); // failsafe
})();
</script>
