class PolicyModal {
  constructor() {
    this.modal = null;
    this.isInitialized = false;
    this.understandCheckbox = null;
    this.dontShowCheckbox = null;
    this.acceptButton = null;
    this.userId = null;
    this.init();
  }

  init() {
    if (this.isInitialized) return;
    this.createModal();
    this.attachEventListeners();
    this.isInitialized = true;
  }

  createModal() {
    const existingModal = document.getElementById("policyModal");
    if (existingModal) {
      existingModal.remove();
    }

    const modalHTML = `
      <div id="policyModal" class="policy-modal">
        <div class="policy-modal-content">
          <button class="policy-modal-close" type="button" aria-label="Close" id="policyCloseBtn">
            <i class="fas fa-times"></i>
          </button>
          
          <div class="policy-modal-header">
            <div class="policy-modal-icon">
              <i class="fas fa-shield-alt"></i>
            </div>
            <h3 class="policy-modal-title">Terms of Use</h3>
            <p class="policy-modal-subtitle">Please review our policies before continuing</p>
          </div>
          
          <div class="policy-modal-body">
            ${this.getPolicyContent()}
          </div>
          
          <div class="policy-modal-footer">
            
            
            <div class="policy-modal-actions">
              <button type="button" class="policy-btn policy-btn-accept" id="policyAcceptBtn" disabled>
                <i class="fas fa-check-circle"></i>
                <span>Continue to Website</span>
              </button>
            </div>
          </div>
        </div>
      </div>
    `;

    document.body.insertAdjacentHTML("beforeend", modalHTML);

    // Create custom alert modal
    const alertModalHTML = `
      <div id="policyAlertModal" class="policy-alert-overlay">
        <div class="policy-alert-modal">
          <div class="policy-alert-icon">
            <i class="fas fa-exclamation-circle"></i>
          </div>
          <h4 class="policy-alert-title" id="policyAlertTitle">Alert</h4>
          <p class="policy-alert-message" id="policyAlertMessage"></p>
          <div class="policy-alert-actions">
            <button class="policy-alert-btn policy-alert-btn-primary" id="policyAlertOk">
              <i class="fas fa-check"></i> OK
            </button>
            <button class="policy-alert-btn policy-alert-btn-secondary" id="policyAlertCancel" style="display:none;">
              <i class="fas fa-times"></i> Cancel
            </button>
          </div>
        </div>
      </div>
    `;
    document.body.insertAdjacentHTML("beforeend", alertModalHTML);

    this.modal = document.getElementById("policyModal");
    this.alertModal = document.getElementById("policyAlertModal");
    this.understandCheckbox = document.getElementById("understandCheckbox");
    this.dontShowCheckbox = document.getElementById("dontShowCheckbox");
    this.acceptButton = document.getElementById("policyAcceptBtn");
  }

  getPolicyContent() {
    return `
      <div class="policy-content">
        <h4>1. Platform Usage Policy</h4>
        <p>STI PROWARE is the official campus marketplace for STI College Lucena. By using this platform, you agree to:</p>
        <ul>
          <li>Use the platform only for legitimate transactions related to official STI merchandise</li>
          <li>Provide accurate and complete information for all orders</li>
          <li>Maintain the confidentiality of your account credentials</li>
          <li>Not share your account with unauthorized individuals</li>
        </ul>

        <div class="policy-highlight">
          <strong>Strike System:</strong> Failing to claim requested order items within the specified period will result in strikes. Accumulating 3 strikes leads to automatic account suspension.
        </div>

        <h4>2. Order and Payment Terms</h4>
        <p><strong>Order Processing:</strong></p>
        <ul>
          <li>All orders are subject to availability and confirmation</li>
          <li>Pre-orders do not guarantee immediate fulfillment</li>
          <li>Payment must be made at the PAMO office upon claiming items</li>
          <li>Orders not claimed within the specified period will be automatically cancelled</li>
        </ul>
        
        <p><strong>Cancellation Policy:</strong></p>
        <ul>
          <li>Orders may be cancelled before processing through the "My Orders" section</li>
          <li>Once marked as "Processing" or "Ready for Pickup," cancellation may not be possible</li>
          <li>Excessive cancellations may result in temporary account restrictions</li>
        </ul>

        <h4>3. User Conduct and Responsibilities</h4>
        <p>You must not engage in any of the following activities:</p>
        <ul>
          <li>Placing fraudulent or false orders</li>
          <li>Attempting to manipulate the system or bypass security measures</li>
          <li>Using the platform for unauthorized commercial purposes</li>
          <li>Harassing or abusing other users or staff members</li>
          <li>Submitting malicious code or harmful content</li>
        </ul>

        <div class="policy-highlight">
          <strong>Violation Notice:</strong> Any violation of these policies may result in immediate account suspension and potential disciplinary action.
        </div>

        <h4>4. Privacy and Data Protection</h4>
        <p>We are committed to protecting your privacy:</p>
        <ul>
          <li>Personal data is used solely for order processing and platform administration</li>
          <li>Your information will not be shared with third parties without consent</li>
          <li>We implement reasonable security measures to protect your data</li>
          <li>You have the right to request access to or deletion of your personal data</li>
        </ul>

        <h4>5. Account Security</h4>
        <p><strong>Your Responsibilities:</strong></p>
        <ul>
          <li>Keep your password secure and do not share it with anyone</li>
          <li>Immediately report any unauthorized access to your account</li>
          <li>Log out after each session, especially on shared devices</li>
          <li>Ensure your contact information is accurate and up-to-date</li>
        </ul>

        <h4>6. Limitation of Liability</h4>
        <p>To the fullest extent permitted by law:</p>
        <ul>
          <li>The platform is provided "as is" without warranties of any kind</li>
          <li>We are not responsible for delays due to circumstances beyond our control</li>
          <li>STI PROWARE and STI College Lucena shall not be liable for indirect or consequential damages</li>
        </ul>

        <h4>7. Contact and Support</h4>
        <p>For questions or concerns regarding these policies, please contact the <strong>PAMO Office</strong> during institutional hours or use the inquiry system within the platform.</p>

        <div class="policy-highlight">
          <strong>Last Updated:</strong> ${new Date().toLocaleDateString(
            "en-US",
            { year: "numeric", month: "long", day: "numeric" }
          )}
        </div>

        <div class="policy-warning">
              <i class="fas fa-exclamation-triangle"></i>
              <strong>Important:</strong> Closing this modal without accepting will log you out
            </div>
            
            <div class="policy-checkboxes">
              <div class="policy-checkbox-item" id="understandCheckboxContainer">
                <div class="policy-checkbox-wrapper">
                  <input type="checkbox" id="understandCheckbox" required>
                  <label for="understandCheckbox" class="policy-checkbox-label required">
                    I understand and agree
                  </label>
                </div>
                <div class="policy-checkbox-sublabel">I have read and accept the Policy and Terms above</div>
              </div>
              
              <div class="policy-checkbox-item" id="dontShowCheckboxContainer">
                <div class="policy-checkbox-wrapper">
                  <input type="checkbox" id="dontShowCheckbox">
                  <label for="dontShowCheckbox" class="policy-checkbox-label">
                    Don't show this again
                  </label>
                </div>
                <div class="policy-checkbox-sublabel">Remember my choice for future logins</div>
              </div>
            </div>
      </div>
    `;

    // Create custom alert modal
    const alertModalHTML = `
      <div id="policyAlertModal" class="policy-alert-overlay">
        <div class="policy-alert-modal">
          <div class="policy-alert-icon">
            <i class="fas fa-exclamation-circle"></i>
          </div>
          <h4 class="policy-alert-title" id="policyAlertTitle">Alert</h4>
          <p class="policy-alert-message" id="policyAlertMessage"></p>
          <div class="policy-alert-actions">
            <button class="policy-alert-btn policy-alert-btn-primary" id="policyAlertOk">
              <i class="fas fa-check"></i> OK
            </button>
            <button class="policy-alert-btn policy-alert-btn-secondary" id="policyAlertCancel" style="display:none;">
              <i class="fas fa-times"></i> Cancel
            </button>
          </div>
        </div>
      </div>
    `;
  }

  attachEventListeners() {
    if (!this.modal) return;

    const closeBtn = document.getElementById("policyCloseBtn");
    const understandContainer = document.getElementById(
      "understandCheckboxContainer"
    );
    const dontShowContainer = document.getElementById(
      "dontShowCheckboxContainer"
    );

    // Close button - logs user out if they haven't accepted
    closeBtn?.addEventListener("click", () => this.handleClose());

    // Prevent closing by clicking outside
    this.modal.addEventListener("click", (e) => {
      if (e.target === this.modal) {
        // Show warning that they need to accept or will be logged out
        this.showCloseWarning();
      }
    });

    // Prevent Escape key closing
    document.addEventListener("keydown", (e) => {
      if (e.key === "Escape" && this.isVisible()) {
        e.preventDefault();
        this.showCloseWarning();
      }
    });

    // Checkbox change events - update container styling
    this.understandCheckbox?.addEventListener("change", (e) => {
      this.updateCheckboxContainer(understandContainer, e.target.checked);
      this.updateAcceptButton();
    });

    this.dontShowCheckbox?.addEventListener("change", (e) => {
      this.updateCheckboxContainer(dontShowContainer, e.target.checked);
    });

    // Accept button click
    this.acceptButton?.addEventListener("click", () => {
      this.handleAccept();
    });

    // Label clicks for better UX
    understandContainer?.addEventListener("click", (e) => {
      if (e.target !== this.understandCheckbox) {
        this.understandCheckbox.checked = !this.understandCheckbox.checked;
        this.understandCheckbox.dispatchEvent(new Event("change"));
      }
    });

    dontShowContainer?.addEventListener("click", (e) => {
      if (e.target !== this.dontShowCheckbox) {
        this.dontShowCheckbox.checked = !this.dontShowCheckbox.checked;
        this.dontShowCheckbox.dispatchEvent(new Event("change"));
      }
    });
  }

  updateCheckboxContainer(container, checked) {
    if (checked) {
      container?.classList.add("checked");
    } else {
      container?.classList.remove("checked");
    }
  }

  updateAcceptButton() {
    if (this.understandCheckbox?.checked) {
      this.acceptButton.disabled = false;
    } else {
      this.acceptButton.disabled = true;
    }
  }

  showCloseWarning() {
    const warning = this.modal.querySelector(".policy-warning");
    if (warning) {
      warning.style.animation = "none";
      setTimeout(() => {
        warning.style.animation = "shake 0.5s ease-in-out";
      }, 10);
    }
  }

  handleClose() {
    // If they haven't checked "I understand", log them out
    if (!this.understandCheckbox?.checked) {
      this.showCustomAlert(
        "You must accept the Policy and Terms to continue. Click OK to log out, or Cancel to return to the policy.",
        "Policy Required",
        true,
        () => this.performLogout()
      );
    } else {
      // They checked "I understand" but clicked close - treat as accept
      this.handleAccept();
    }
  }

  async handleAccept() {
    const understood = this.understandCheckbox?.checked;
    const dontShow = this.dontShowCheckbox?.checked;

    if (!understood) {
      this.showCustomAlert(
        "Please check 'I understand and agree' to continue.",
        "Acceptance Required"
      );
      return;
    }

    // Save preference to database
    try {
      const response = await fetch("../Includes/save_policy_acceptance.php", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({
          dont_show_again: dontShow ? 1 : 0,
        }),
      });

      const result = await response.json();

      if (result.success) {
        // Mark as shown in session to prevent re-showing
        sessionStorage.setItem("policyShown", "true");
        this.hide();
      } else {
        console.error("Failed to save policy acceptance:", result.message);
        // Still hide modal on error to not block the user
        this.hide();
      }
    } catch (error) {
      console.error("Error saving policy acceptance:", error);
      // Still hide modal on error to not block the user
      this.hide();
    }
  }

  performLogout() {
    // Determine logout URL based on current location
    const currentPath = window.location.pathname;
    let logoutUrl = "logout.php";

    if (currentPath.includes("/ADMIN/")) {
      logoutUrl = "../logout.php";
    } else if (currentPath.includes("/Pages/")) {
      logoutUrl = "logout.php";
    } else if (currentPath.includes("/PAMO")) {
      logoutUrl = "../logout.php";
    }

    window.location.href = logoutUrl;
  }

  show(userId) {
    if (!this.modal) {
      this.init();
    }

    this.userId = userId;
    this.modal.classList.add("show");
    document.body.style.overflow = "hidden";

    // Reset checkboxes
    if (this.understandCheckbox) this.understandCheckbox.checked = false;
    if (this.dontShowCheckbox) this.dontShowCheckbox.checked = false;
    this.updateAcceptButton();

    // Remove checked classes
    document
      .getElementById("understandCheckboxContainer")
      ?.classList.remove("checked");
    document
      .getElementById("dontShowCheckboxContainer")
      ?.classList.remove("checked");

    // Scroll to top
    const modalBody = this.modal.querySelector(".policy-modal-body");
    if (modalBody) {
      modalBody.scrollTop = 0;
    }
  }

  hide() {
    if (!this.modal) return;
    this.modal.classList.remove("show");
    document.body.style.overflow = "";
  }

  isVisible() {
    return this.modal && this.modal.classList.contains("show");
  }

  showReadOnly() {
    if (!this.modal) {
      this.init();
    }

    // Hide checkboxes and buttons for read-only view
    const checkboxesContainer = this.modal.querySelector(".policy-checkboxes");
    const warningContainer = this.modal.querySelector(".policy-warning");
    const acceptButton = this.modal.querySelector("#policyAcceptBtn");
    const closeButton = this.modal.querySelector("#policyCloseBtn");

    if (checkboxesContainer) checkboxesContainer.style.display = "none";
    if (warningContainer) warningContainer.style.display = "none";
    if (acceptButton) acceptButton.style.display = "none";

    // Make close button work normally (just close, no logout)
    if (closeButton) {
      // Remove all previous event listeners by cloning
      const newCloseButton = closeButton.cloneNode(true);
      closeButton.parentNode.replaceChild(newCloseButton, closeButton);

      newCloseButton.addEventListener("click", () => {
        this.hide();
        // Restore original display after hiding
        setTimeout(() => {
          if (checkboxesContainer) checkboxesContainer.style.display = "";
          if (warningContainer) warningContainer.style.display = "";
          if (acceptButton) acceptButton.style.display = "";
        }, 300);
      });
    }

    // Allow closing by clicking outside
    const handleOutsideClick = (e) => {
      if (e.target === this.modal) {
        this.hide();
        // Restore original display after hiding
        setTimeout(() => {
          if (checkboxesContainer) checkboxesContainer.style.display = "";
          if (warningContainer) warningContainer.style.display = "";
          if (acceptButton) acceptButton.style.display = "";
        }, 300);
        this.modal.removeEventListener("click", handleOutsideClick);
      }
    };
    this.modal.addEventListener("click", handleOutsideClick);

    // Allow Escape key to close
    const handleEscape = (e) => {
      if (e.key === "Escape" && this.isVisible()) {
        this.hide();
        // Restore original display after hiding
        setTimeout(() => {
          if (checkboxesContainer) checkboxesContainer.style.display = "";
          if (warningContainer) warningContainer.style.display = "";
          if (acceptButton) acceptButton.style.display = "";
        }, 300);
        document.removeEventListener("keydown", handleEscape);
      }
    };
    document.addEventListener("keydown", handleEscape);

    this.modal.classList.add("show");
    document.body.style.overflow = "hidden";

    // Scroll to top
    const modalBody = this.modal.querySelector(".policy-modal-body");
    if (modalBody) {
      modalBody.scrollTop = 0;
    }
  }

  showCustomAlert(
    message,
    title = "Alert",
    showCancel = false,
    onConfirm = null
  ) {
    if (!this.alertModal) return;

    const alertTitle = document.getElementById("policyAlertTitle");
    const alertMessage = document.getElementById("policyAlertMessage");
    const okBtn = document.getElementById("policyAlertOk");
    const cancelBtn = document.getElementById("policyAlertCancel");

    alertTitle.textContent = title;
    alertMessage.textContent = message;

    // Show/hide cancel button
    if (showCancel) {
      cancelBtn.style.display = "inline-flex";
    } else {
      cancelBtn.style.display = "none";
    }

    // Show alert modal
    this.alertModal.classList.add("show");

    // Handle OK button
    const handleOk = () => {
      this.alertModal.classList.remove("show");
      if (onConfirm) onConfirm();
      okBtn.removeEventListener("click", handleOk);
      cancelBtn.removeEventListener("click", handleCancel);
    };

    // Handle Cancel button
    const handleCancel = () => {
      this.alertModal.classList.remove("show");
      okBtn.removeEventListener("click", handleOk);
      cancelBtn.removeEventListener("click", handleCancel);
    };

    okBtn.addEventListener("click", handleOk);
    cancelBtn.addEventListener("click", handleCancel);
  }
}

// Global instance
let policyModalInstance = null;

// Global function to check and show policy modal
async function checkAndShowPolicyModal() {
  // Check if already shown in this session
  if (sessionStorage.getItem("policyShown") === "true") {
    return;
  }

  try {
    // Check if user needs to see the policy
    const response = await fetch("../Includes/check_policy_status.php");
    const result = await response.json();

    if (result.should_show) {
      if (!policyModalInstance) {
        policyModalInstance = new PolicyModal();
      }
      policyModalInstance.show(result.user_id);
    }
  } catch (error) {
    console.error("Policy modal error:", error);
  }
}

// Auto-initialize when DOM is ready
document.addEventListener("DOMContentLoaded", function () {
  // Initialize policy modal if user is logged in (customer only)
  checkAndShowPolicyModal();
});

// Export for module systems
if (typeof module !== "undefined" && module.exports) {
  module.exports = PolicyModal;
}

// Add shake animation to CSS dynamically if not present
if (!document.querySelector("style[data-policy-animation]")) {
  const style = document.createElement("style");
  style.setAttribute("data-policy-animation", "true");
  style.textContent = `
    @keyframes shake {
      0%, 100% { transform: translateX(0); }
      10%, 30%, 50%, 70%, 90% { transform: translateX(-5px); }
      20%, 40%, 60%, 80% { transform: translateX(5px); }
    }
  `;
  document.head.appendChild(style);
}
