/**
 * Custom Logout Confirmation Modal
 * A reusable modal for confirming logout actions across the system
 */

class LogoutModal {
  constructor() {
    this.modal = null;
    this.isInitialized = false;
    this.init();
  }

  init() {
    if (this.isInitialized) return;

    // Create modal HTML
    this.createModal();
    this.attachEventListeners();
    this.isInitialized = true;
  }

  createModal() {
    // Remove existing modal if any
    const existingModal = document.getElementById("logoutModal");
    if (existingModal) {
      existingModal.remove();
    }

    // Create modal HTML
    const modalHTML = `
            <div id="logoutModal" class="logout-modal">
                <div class="logout-modal-content">
                    <button class="logout-modal-close" type="button" aria-label="Close">
                        <i class="fas fa-times"></i>
                    </button>
                    <div class="logout-modal-header">
                        <div class="logout-modal-icon">
                            <i class="fas fa-sign-out-alt"></i>
                        </div>
                        <h3 class="logout-modal-title">Confirm Logout</h3>
                        <p class="logout-modal-message">Are you sure you want to log out? You will be redirected to the homepage.</p>
                    </div>
                    <div class="logout-modal-buttons">
                        <button type="button" class="logout-btn-cancel">Cancel</button>
                        <button type="button" class="logout-btn-confirm">Yes, Logout</button>
                    </div>
                </div>
            </div>
        `;

    // Append to body
    document.body.insertAdjacentHTML("beforeend", modalHTML);
    this.modal = document.getElementById("logoutModal");
  }

  attachEventListeners() {
    if (!this.modal) return;

    const closeBtn = this.modal.querySelector(".logout-modal-close");
    const cancelBtn = this.modal.querySelector(".logout-btn-cancel");
    const confirmBtn = this.modal.querySelector(".logout-btn-confirm");

    // Close modal events
    closeBtn?.addEventListener("click", () => this.hide());
    cancelBtn?.addEventListener("click", () => this.hide());

    // Click outside to close
    this.modal.addEventListener("click", (e) => {
      if (e.target === this.modal) {
        this.hide();
      }
    });

    // Escape key to close
    document.addEventListener("keydown", (e) => {
      if (e.key === "Escape" && this.isVisible()) {
        this.hide();
      }
    });

    // Confirm logout
    confirmBtn?.addEventListener("click", () => {
      this.hide();
      this.performLogout();
    });
  }

  show() {
    if (!this.modal) {
      this.init();
    }

    this.modal.classList.add("show");
    document.body.style.overflow = "hidden"; // Prevent scrolling

    // Focus management for accessibility
    const confirmBtn = this.modal.querySelector(".logout-btn-confirm");
    confirmBtn?.focus();
  }

  hide() {
    if (this.modal) {
      this.modal.classList.remove("show");
      document.body.style.overflow = ""; // Restore scrolling
    }
  }

  isVisible() {
    return this.modal && this.modal.classList.contains("show");
  }

  performLogout() {
    // Determine the correct logout URL based on current location
    const currentPath = window.location.pathname;
    let logoutUrl = "logout.php";

    // Adjust logout URL based on current directory structure
    if (currentPath.includes("/ADMIN/")) {
      logoutUrl = "../logout.php";
    } else if (currentPath.includes("/Pages/")) {
      logoutUrl = "logout.php";
    } else if (currentPath.includes("/PAMO")) {
      logoutUrl = "../logout.php";
    }

    // Redirect to logout
    window.location.href = logoutUrl;
  }
}

// Global instance
let logoutModalInstance = null;

// Global function to show logout confirmation
function showLogoutConfirmation() {
  if (!logoutModalInstance) {
    logoutModalInstance = new LogoutModal();
  }
  logoutModalInstance.show();
}

// Export for module systems (if needed)
if (typeof module !== "undefined" && module.exports) {
  module.exports = LogoutModal;
}

// Auto-initialize when DOM is ready
document.addEventListener("DOMContentLoaded", function () {
  // Initialize the logout modal instance
  if (!logoutModalInstance) {
    logoutModalInstance = new LogoutModal();
  }
});
