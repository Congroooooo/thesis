/**
 * Notification Modal System
 * A reusable modal system for displaying alerts, notifications, and messages
 * Replaces standard browser alert() with polished custom modals
 */

class NotificationModal {
  constructor() {
    this.modal = null;
    this.currentCallback = null;
    this.autoCloseTimer = null;
    this.init();
  }

  init() {
    // Ensure modal exists in DOM
    if (!document.getElementById("notificationModal")) {
      console.warn(
        "Notification modal not found in DOM. It will be created when needed."
      );
    }
  }

  /**
   * Show a notification modal
   * @param {Object} options - Configuration options
   * @param {string} options.type - Type of notification: 'success', 'error', 'warning', 'info'
   * @param {string} options.title - Modal title (optional)
   * @param {string} options.message - Modal message content
   * @param {Function} options.onClose - Callback function when modal is closed (optional)
   * @param {number} options.autoClose - Auto-close delay in milliseconds (optional, default: 0 = no auto-close)
   * @param {boolean} options.showCloseButton - Show close button (optional, default: true)
   */
  show(options = {}) {
    const {
      type = "info",
      title = this.getDefaultTitle(type),
      message = "",
      onClose = null,
      autoClose = 0,
      showCloseButton = true,
    } = options;

    // Get or create modal
    this.modal = document.getElementById("notificationModal");
    if (!this.modal) {
      console.error("Notification modal element not found!");
      return;
    }

    // Clear any existing auto-close timer
    if (this.autoCloseTimer) {
      clearTimeout(this.autoCloseTimer);
      this.autoCloseTimer = null;
    }

    // Store callback
    this.currentCallback = onClose;

    // Get modal elements
    const modalContent = this.modal.querySelector(
      ".notification-modal-content"
    );
    const modalIcon = this.modal.querySelector(".notification-modal-icon");
    const modalIconElement = this.modal.querySelector(
      ".notification-modal-icon i"
    );
    const modalTitle = this.modal.querySelector(".notification-modal-title");
    const modalMessage = this.modal.querySelector(
      ".notification-modal-message"
    );
    const okButton = this.modal.querySelector(".notification-btn-ok");
    const closeButton = this.modal.querySelector(".notification-modal-close");
    const progressBar = this.modal.querySelector(".notification-progress-bar");
    const progressFill = this.modal.querySelector(
      ".notification-progress-fill"
    );

    // Update content
    modalTitle.textContent = title;
    modalMessage.textContent = message;

    // Update type-specific styling
    this.updateModalType(
      type,
      modalContent,
      modalIcon,
      modalIconElement,
      okButton,
      progressFill
    );

    // Show/hide close button
    if (closeButton) {
      closeButton.style.display = showCloseButton ? "flex" : "none";
    }

    // Setup auto-close
    if (autoClose > 0) {
      progressBar.style.display = "block";
      progressFill.style.animationDuration = `${autoClose}ms`;

      this.autoCloseTimer = setTimeout(() => {
        this.close();
      }, autoClose);
    } else {
      progressBar.style.display = "none";
    }

    // Show modal
    this.modal.classList.add("show");

    // Prevent body scroll when modal is open
    document.body.style.overflow = "hidden";

    // Add keyboard support (ESC to close)
    this.addKeyboardSupport();
  }

  /**
   * Update modal styling based on type
   */
  updateModalType(
    type,
    modalContent,
    modalIcon,
    modalIconElement,
    okButton,
    progressFill
  ) {
    // Remove all type classes
    const types = ["success", "error", "warning", "info"];
    types.forEach((t) => {
      modalContent.classList.remove(t);
      modalIcon.classList.remove(t);
      okButton.classList.remove(t);
      progressFill.classList.remove(t);
    });

    // Add current type class
    modalContent.classList.add(type);
    modalIcon.classList.add(type);
    okButton.classList.add(type);
    progressFill.classList.add(type);

    // Update icon
    const iconClass = this.getIconClass(type);
    modalIconElement.className = iconClass;
  }

  /**
   * Get icon class based on type
   */
  getIconClass(type) {
    const icons = {
      success: "fas fa-check",
      error: "fas fa-times",
      warning: "fas fa-exclamation-triangle",
      info: "fas fa-info-circle",
    };
    return icons[type] || icons.info;
  }

  /**
   * Get default title based on type
   */
  getDefaultTitle(type) {
    const titles = {
      success: "Success!",
      error: "Error!",
      warning: "Warning!",
      info: "Information",
    };
    return titles[type] || "Notification";
  }

  /**
   * Close the modal
   */
  close() {
    if (!this.modal) return;

    // Clear auto-close timer
    if (this.autoCloseTimer) {
      clearTimeout(this.autoCloseTimer);
      this.autoCloseTimer = null;
    }

    // Hide modal
    this.modal.classList.remove("show");

    // Restore body scroll
    document.body.style.overflow = "";

    // Remove keyboard listener
    this.removeKeyboardSupport();

    // Execute callback if provided
    if (this.currentCallback && typeof this.currentCallback === "function") {
      this.currentCallback();
      this.currentCallback = null;
    }
  }

  /**
   * Add keyboard support
   */
  addKeyboardSupport() {
    this.keyboardHandler = (e) => {
      if (e.key === "Escape" || e.key === "Esc") {
        this.close();
      } else if (e.key === "Enter") {
        this.close();
      }
    };
    document.addEventListener("keydown", this.keyboardHandler);
  }

  /**
   * Remove keyboard support
   */
  removeKeyboardSupport() {
    if (this.keyboardHandler) {
      document.removeEventListener("keydown", this.keyboardHandler);
      this.keyboardHandler = null;
    }
  }
}

// Create global instance
let notificationModalInstance = null;

/**
 * Initialize the notification modal system
 * Should be called after DOM is loaded
 */
function initNotificationModal() {
  if (!notificationModalInstance) {
    notificationModalInstance = new NotificationModal();

    // Setup event listeners
    const modal = document.getElementById("notificationModal");
    if (modal) {
      const okButton = modal.querySelector(".notification-btn-ok");
      const closeButton = modal.querySelector(".notification-modal-close");

      if (okButton) {
        okButton.addEventListener("click", () => {
          notificationModalInstance.close();
        });
      }

      if (closeButton) {
        closeButton.addEventListener("click", () => {
          notificationModalInstance.close();
        });
      }

      // Close on backdrop click
      modal.addEventListener("click", (e) => {
        if (e.target === modal) {
          notificationModalInstance.close();
        }
      });
    }
  }
}

/**
 * Show a notification modal (simplified function)
 * @param {string} message - The message to display
 * @param {string} type - Type of notification ('success', 'error', 'warning', 'info')
 * @param {Object} options - Additional options (title, onClose, autoClose, showCloseButton)
 */
function showNotification(message, type = "info", options = {}) {
  if (!notificationModalInstance) {
    initNotificationModal();
  }

  if (!notificationModalInstance) {
    // Fallback to alert if modal is not available
    console.error("Notification modal not initialized");
    alert(message);
    return;
  }

  notificationModalInstance.show({
    type: type,
    message: message,
    ...options,
  });
}

/**
 * Convenience functions for specific notification types
 */
function showSuccess(message, options = {}) {
  showNotification(message, "success", options);
}

function showError(message, options = {}) {
  showNotification(message, "error", options);
}

function showWarning(message, options = {}) {
  showNotification(message, "warning", options);
}

function showInfo(message, options = {}) {
  showNotification(message, "info", options);
}

/**
 * Close the current notification modal
 */
function closeNotification() {
  if (notificationModalInstance) {
    notificationModalInstance.close();
  }
}

// Auto-initialize when DOM is ready
if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", initNotificationModal);
} else {
  initNotificationModal();
}

// Export for use in other scripts
if (typeof module !== "undefined" && module.exports) {
  module.exports = {
    showNotification,
    showSuccess,
    showError,
    showWarning,
    showInfo,
    closeNotification,
    initNotificationModal,
  };
}
