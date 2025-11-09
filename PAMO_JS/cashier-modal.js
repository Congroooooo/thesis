/**
 * Cashier Modal - Handles daily cashier name input on PAMO dashboard
 */

document.addEventListener("DOMContentLoaded", function () {
  setTimeout(checkCashierStatus, 500);
});

function checkCashierStatus() {
  fetch("get_cashier_status.php")
    .then((response) => response.json())
    .then((data) => {
      if (data.success && !data.is_set) {
        showCashierModal();
      }
    })
    .catch((error) => {
      console.error("Error checking cashier status:", error);
    });
}

function showCashierModal() {
  if (document.getElementById("cashierModalOverlay")) {
    return;
  }

  const modalHtml = `
        <div class="cashier-modal-overlay" id="cashierModalOverlay">
            <div class="cashier-modal">
                <div class="cashier-modal-header">
                    <i class="material-icons">person_outline</i>
                    <h2>Set Cashier for Today</h2>
                </div>
                <div class="cashier-modal-body">
                    <p>
                        <strong>Welcome to your shift!</strong><br>
                        Please enter the name of the cashier on duty today. This will appear on all receipts and exchange slips generated today.
                    </p>
                    <span class="cashier-info-badge">
                        <i class="material-icons" style="font-size: 1rem; vertical-align: middle;">today</i>
                        ${getCurrentDate()}
                    </span>
                    <div class="cashier-input-group">
                        <label for="cashierNameInput">
                            Cashier Name: <span>*</span>
                        </label>
                        <input 
                            type="text" 
                            id="cashierNameInput" 
                            placeholder="Enter full name (e.g., Juan Dela Cruz)" 
                            required
                            autocomplete="off"
                            maxlength="255"
                        />
                        <div class="cashier-error" id="cashierError">
                            Please enter a valid cashier name
                        </div>
                    </div>
                </div>
                <div class="cashier-modal-footer">
                    <button class="cashier-btn cashier-btn-primary" id="saveCashierBtn" onclick="saveCashierName()">
                        <i class="material-icons" style="font-size: 1rem; vertical-align: middle;">check_circle</i>
                        Confirm & Continue
                    </button>
                </div>
            </div>
        </div>
    `;

  document.body.insertAdjacentHTML("beforeend", modalHtml);

  const input = document.getElementById("cashierNameInput");
  input.focus();

  input.addEventListener("keypress", function (e) {
    if (e.key === "Enter") {
      e.preventDefault();
      saveCashierName();
    }
  });

  input.addEventListener("input", function () {
    const errorDiv = document.getElementById("cashierError");
    errorDiv.classList.remove("show");
  });
}

function saveCashierName() {
  const input = document.getElementById("cashierNameInput");
  const errorDiv = document.getElementById("cashierError");
  const btn = document.getElementById("saveCashierBtn");
  const cashierName = input.value.trim();

  if (!cashierName || cashierName.length < 2) {
    errorDiv.textContent =
      "Please enter a valid cashier name (at least 2 characters)";
    errorDiv.classList.add("show");
    input.focus();
    return;
  }

  if (cashierName.length > 255) {
    errorDiv.textContent = "Cashier name is too long (maximum 255 characters)";
    errorDiv.classList.add("show");
    input.focus();
    return;
  }

  errorDiv.classList.remove("show");

  btn.disabled = true;
  btn.innerHTML =
    '<i class="material-icons" style="font-size: 1rem; vertical-align: middle;">hourglass_empty</i> Saving...';

  const formData = new FormData();
  formData.append("cashier_name", cashierName);

  fetch("set_cashier.php", {
    method: "POST",
    body: formData,
  })
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        const overlay = document.getElementById("cashierModalOverlay");
        if (overlay) {
          overlay.style.animation = "fadeOut 0.3s ease";
          setTimeout(() => overlay.remove(), 300);
        }

        showNotification(
          `Cashier set successfully: ${data.cashier_name}`,
          "success"
        );

        updateCashierDisplay(data.cashier_name);
      } else {
        errorDiv.textContent =
          data.message || "Failed to save cashier name. Please try again.";
        errorDiv.classList.add("show");
        btn.disabled = false;
        btn.innerHTML =
          '<i class="material-icons" style="font-size: 1rem; vertical-align: middle;">check_circle</i> Confirm & Continue';
      }
    })
    .catch((error) => {
      console.error("Error saving cashier:", error);
      errorDiv.textContent =
        "Network error. Please check your connection and try again.";
      errorDiv.classList.add("show");
      btn.disabled = false;
      btn.innerHTML =
        '<i class="material-icons" style="font-size: 1rem; vertical-align: middle;">check_circle</i> Confirm & Continue';
    });
}

function showNotification(message, type = "success") {
  const notification = document.createElement("div");
  notification.className = `cashier-notification ${type}`;

  const icon = type === "success" ? "check_circle" : "error";
  notification.innerHTML = `
        <i class="material-icons">${icon}</i>
        <span>${message}</span>
    `;

  document.body.appendChild(notification);

  setTimeout(() => {
    notification.style.animation = "fadeOut 0.3s ease";
    setTimeout(() => notification.remove(), 300);
  }, 4000);
}

function updateCashierDisplay(cashierName) {
  const cashierDisplays = document.querySelectorAll(".cashier-display-name");
  cashierDisplays.forEach((element) => {
    element.textContent = cashierName;
  });

  const notSetElements = document.querySelectorAll(".cashier-not-set");
  notSetElements.forEach((element) => {
    element.style.display = "none";
  });
}

function getCurrentDate() {
  const options = {
    weekday: "long",
    year: "numeric",
    month: "long",
    day: "numeric",
  };
  return new Date().toLocaleDateString("en-US", options);
}

function changeCashier() {
  if (
    confirm(
      "Do you want to change the cashier for today? This will update all receipts generated from now on."
    )
  ) {
    showCashierModal();
  }
}

window.changeCashier = changeCashier;
