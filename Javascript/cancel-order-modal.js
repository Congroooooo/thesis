class CancelOrderModal {
  constructor() {
    this.modal = null;
    this.isInitialized = false;
    this.currentForm = null;
    this.currentOrderType = "order";
    this.init();
  }

  init() {
    if (this.isInitialized) return;
    this.createModal();
    this.attachEventListeners();
    this.isInitialized = true;
  }

  createModal() {
    const existingModal = document.getElementById("cancelOrderModal");
    if (existingModal) {
      existingModal.remove();
    }

    const modalHTML = `
      <div id="cancelOrderModal" class="cancel-order-modal">
        <div class="cancel-order-modal-content">
          <button class="cancel-order-modal-close" type="button" aria-label="Close">
            <i class="fas fa-times"></i>
          </button>
          <div class="cancel-order-modal-header">
            <div class="cancel-order-modal-icon">
              <i class="fas fa-exclamation-triangle"></i>
            </div>
            <h3 class="cancel-order-modal-title">Cancel Order</h3>
            <p class="cancel-order-modal-message">Are you sure you want to cancel this order? This action cannot be undone.</p>
          </div>
          <div class="cancel-order-modal-buttons">
            <button type="button" class="cancel-order-btn-cancel">No, Keep Order</button>
            <button type="button" class="cancel-order-btn-confirm">Yes, Cancel Order</button>
          </div>
        </div>
      </div>
    `;

    document.body.insertAdjacentHTML("beforeend", modalHTML);
    this.modal = document.getElementById("cancelOrderModal");
  }

  attachEventListeners() {
    if (!this.modal) return;

    const closeBtn = this.modal.querySelector(".cancel-order-modal-close");
    const cancelBtn = this.modal.querySelector(".cancel-order-btn-cancel");
    const confirmBtn = this.modal.querySelector(".cancel-order-btn-confirm");

    closeBtn?.addEventListener("click", () => this.hide());
    cancelBtn?.addEventListener("click", () => this.hide());

    this.modal.addEventListener("click", (e) => {
      if (e.target === this.modal) {
        this.hide();
      }
    });

    document.addEventListener("keydown", (e) => {
      if (e.key === "Escape" && this.isVisible()) {
        this.hide();
      }
    });

    confirmBtn?.addEventListener("click", () => {
      this.confirmCancellation();
    });
  }

  show(form, orderType = "order") {
    if (!this.modal) {
      this.init();
    }

    this.currentForm = form;
    this.currentOrderType = orderType;

    const title = this.modal.querySelector(".cancel-order-modal-title");
    const message = this.modal.querySelector(".cancel-order-modal-message");
    const confirmBtn = this.modal.querySelector(".cancel-order-btn-confirm");
    const cancelBtn = this.modal.querySelector(".cancel-order-btn-cancel");

    if (orderType === "preorder") {
      title.textContent = "Cancel Pre-Order";
      message.textContent =
        "Are you sure you want to cancel this pre-order? This action cannot be undone.";
      confirmBtn.textContent = "Yes, Cancel Pre-Order";
      cancelBtn.textContent = "No, Keep Pre-Order";
    } else {
      title.textContent = "Cancel Order";
      message.textContent =
        "Are you sure you want to cancel this order? This action cannot be undone.";
      confirmBtn.textContent = "Yes, Cancel Order";
      cancelBtn.textContent = "No, Keep Order";
    }

    this.modal.classList.add("show");
    document.body.style.overflow = "hidden";

    const confirmButton = this.modal.querySelector(".cancel-order-btn-confirm");
    confirmButton?.focus();
  }

  hide() {
    if (this.modal) {
      this.modal.classList.remove("show");
      document.body.style.overflow = "";
      this.currentForm = null;
    }
  }

  isVisible() {
    return this.modal && this.modal.classList.contains("show");
  }

  confirmCancellation() {
    if (this.currentForm) {
      this.currentForm.onsubmit = null;

      this.currentForm.submit();
    }
    this.hide();
  }
}

let cancelOrderModalInstance = null;

function showCancelOrderConfirmation(form, orderType = "order") {
  if (!cancelOrderModalInstance) {
    cancelOrderModalInstance = new CancelOrderModal();
  }
  cancelOrderModalInstance.show(form, orderType);
  return false;
}

if (typeof module !== "undefined" && module.exports) {
  module.exports = CancelOrderModal;
}

document.addEventListener("DOMContentLoaded", function () {
  if (!cancelOrderModalInstance) {
    cancelOrderModalInstance = new CancelOrderModal();
  }

  document
    .querySelectorAll("form[data-cancel-order], form[data-cancel-preorder]")
    .forEach((form) => {
      form.addEventListener("submit", function (e) {
        e.preventDefault();
        const orderType = this.hasAttribute("data-cancel-preorder")
          ? "preorder"
          : "order";
        showCancelOrderConfirmation(this, orderType);
      });
    });
});
