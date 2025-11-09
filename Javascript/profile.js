const PASSWORD_RULES = {
  minLength: 12,
  maxLength: 64,
  minDigits: 2,
  noSpaces: true,
};

function validatePassword(password) {
  const errors = [];

  if (password.length < PASSWORD_RULES.minLength) {
    errors.push(
      `Password must be at least ${PASSWORD_RULES.minLength} characters long`
    );
  }

  if (password.length > PASSWORD_RULES.maxLength) {
    errors.push(
      `Password must not exceed ${PASSWORD_RULES.maxLength} characters`
    );
  }

  const digitCount = (password.match(/\d/g) || []).length;
  if (digitCount < PASSWORD_RULES.minDigits) {
    errors.push(
      `Password must contain at least ${PASSWORD_RULES.minDigits} numeric digits`
    );
  }

  if (PASSWORD_RULES.noSpaces && /\s/.test(password)) {
    errors.push("Password must not contain spaces");
  }

  return {
    isValid: errors.length === 0,
    errors: errors,
  };
}

function displayValidationMessages(inputElement, validation) {
  // Get the form-group container (grandparent of input)
  const formGroup = inputElement.closest(".form-group");

  // Remove any existing validation messages in the form group
  const existingMessages = formGroup.querySelectorAll(".validation-message");
  existingMessages.forEach((msg) => msg.remove());

  if (!validation.isValid && inputElement.value.length > 0) {
    const messageContainer = document.createElement("div");
    messageContainer.className = "validation-message error";
    messageContainer.innerHTML = validation.errors
      .map((error) => `<div>• ${error}</div>`)
      .join("");
    formGroup.appendChild(messageContainer);
  } else if (validation.isValid && inputElement.value.length > 0) {
    const messageContainer = document.createElement("div");
    messageContainer.className = "validation-message success";
    messageContainer.innerHTML = "<div>✓ Password meets all requirements</div>";
    formGroup.appendChild(messageContainer);
  }
}

function setupPasswordToggle(inputId, toggleId) {
  const input = document.getElementById(inputId);
  const toggle = document.getElementById(toggleId);

  if (input && toggle) {
    toggle.addEventListener("click", function () {
      const type =
        input.getAttribute("type") === "password" ? "text" : "password";
      input.setAttribute("type", type);

      const icon = this.querySelector("i");
      if (type === "text") {
        // Password is visible - show eye icon (uncovered)
        icon.classList.remove("fa-eye-slash");
        icon.classList.add("fa-eye");
      } else {
        // Password is hidden - show eye-slash icon (covered)
        icon.classList.remove("fa-eye");
        icon.classList.add("fa-eye-slash");
      }
    });
  }
}

function updatePasswordStrength(password) {
  const strengthMeter = document.getElementById("password-strength-meter");
  const strengthText = document.getElementById("password-strength-text");

  if (!strengthMeter || !strengthText) return;

  const validation = validatePassword(password);
  let strength = 0;
  let strengthLabel = "";
  let strengthColor = "";

  if (password.length === 0) {
    strengthMeter.style.width = "0%";
    strengthText.textContent = "";
    return;
  }

  if (password.length >= PASSWORD_RULES.minLength) strength += 25;
  if ((password.match(/\d/g) || []).length >= PASSWORD_RULES.minDigits)
    strength += 25;
  if (/[a-z]/.test(password) && /[A-Z]/.test(password)) strength += 25;
  if (/[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]/.test(password)) strength += 25;

  if (strength <= 25) {
    strengthLabel = "Weak";
    strengthColor = "#dc3545";
  } else if (strength <= 50) {
    strengthLabel = "Fair";
    strengthColor = "#ffc107";
  } else if (strength <= 75) {
    strengthLabel = "Good";
    strengthColor = "#17a2b8";
  } else {
    strengthLabel = "Strong";
    strengthColor = "#28a745";
  }

  strengthMeter.style.width = strength + "%";
  strengthMeter.style.backgroundColor = strengthColor;
  strengthText.textContent = strengthLabel;
  strengthText.style.color = strengthColor;
}

function setupFormValidation() {
  const form = document.querySelector(".password-form");
  const newPasswordInput = document.getElementById("new_password");
  const confirmPasswordInput = document.getElementById("confirm_password");

  if (form) {
    form.addEventListener("submit", function (e) {
      const validation = validatePassword(newPasswordInput.value);

      if (!validation.isValid) {
        e.preventDefault();
        displayValidationMessages(newPasswordInput, validation);
        alert("Please fix the password validation errors before submitting.");
        return false;
      }

      if (newPasswordInput.value !== confirmPasswordInput.value) {
        e.preventDefault();
        alert("New passwords do not match!");
        return false;
      }
    });
  }
}

document.addEventListener("DOMContentLoaded", function () {
  setupPasswordToggle("current_password", "toggle-current-password");
  setupPasswordToggle("new_password", "toggle-new-password");
  setupPasswordToggle("confirm_password", "toggle-confirm-password");

  const newPasswordInput = document.getElementById("new_password");
  if (newPasswordInput) {
    newPasswordInput.addEventListener("input", function () {
      const validation = validatePassword(this.value);
      displayValidationMessages(this, validation);
      updatePasswordStrength(this.value);
    });
  }

  setupFormValidation();

  // Initialize countdown timer for strike cooldown
  initializeCooldownTimer();

  // Setup password change enable/disable functionality
  setupPasswordChangeToggle();

  const alerts = document.querySelectorAll(".alert");
  alerts.forEach((alert) => {
    setTimeout(() => {
      alert.style.opacity = "0";
      setTimeout(() => alert.remove(), 300);
    }, 5000);
  });
});

// Enable/Disable Password Change Form
function setupPasswordChangeToggle() {
  const enableBtn = document.getElementById("enable-password-change");
  const cancelBtn = document.getElementById("cancel-password-change");
  const passwordForm = document.getElementById("password-form");
  const currentPasswordInput = document.getElementById("current_password");
  const newPasswordInput = document.getElementById("new_password");
  const confirmPasswordInput = document.getElementById("confirm_password");
  const submitBtn = document.querySelector(".change-password-btn");

  const toggleButtons = document.querySelectorAll(".password-toggle");

  if (!enableBtn) return;

  // Enable password change
  enableBtn.addEventListener("click", function () {
    // Enable all inputs
    currentPasswordInput.disabled = false;
    newPasswordInput.disabled = false;
    confirmPasswordInput.disabled = false;
    submitBtn.disabled = false;

    // Enable toggle buttons
    toggleButtons.forEach((btn) => (btn.disabled = false));

    // Show cancel button, hide enable button
    enableBtn.style.display = "none";
    cancelBtn.style.display = "inline-block";

    // Focus on first input
    currentPasswordInput.focus();

    // Add active class to form
    passwordForm.classList.add("form-active");
  });

  // Cancel password change
  cancelBtn.addEventListener("click", function () {
    // Disable all inputs
    currentPasswordInput.disabled = true;
    newPasswordInput.disabled = true;
    confirmPasswordInput.disabled = true;
    submitBtn.disabled = true;

    // Disable toggle buttons
    toggleButtons.forEach((btn) => (btn.disabled = true));

    // Clear all inputs
    currentPasswordInput.value = "";
    newPasswordInput.value = "";
    confirmPasswordInput.value = "";

    // Clear validation messages
    const validationMessages = passwordForm.querySelectorAll(
      ".validation-message"
    );
    validationMessages.forEach((msg) => msg.remove());

    // Reset password strength meter
    const strengthMeter = document.getElementById("password-strength-meter");
    const strengthText = document.getElementById("password-strength-text");
    if (strengthMeter) strengthMeter.style.width = "0%";
    if (strengthText) strengthText.textContent = "";

    // Show enable button, hide cancel button
    enableBtn.style.display = "inline-block";
    cancelBtn.style.display = "none";

    // Remove active class from form
    passwordForm.classList.remove("form-active");
  });
}

// Countdown Timer for Strike Cooldown
function initializeCooldownTimer() {
  const countdownElement = document.querySelector(".countdown-timer");

  if (!countdownElement) return;

  const endTime = parseInt(countdownElement.dataset.endTime);

  function updateCountdown() {
    const now = Math.floor(Date.now() / 1000);
    const remaining = endTime - now;

    if (remaining <= 0) {
      // Cooldown expired, reload page to update UI
      location.reload();
      return;
    }

    const minutes = Math.floor(remaining / 60);
    const seconds = remaining % 60;

    const display = countdownElement.querySelector(".countdown-display");
    if (display) {
      display.textContent = `${minutes}:${seconds.toString().padStart(2, "0")}`;
    }
  }

  // Update immediately
  updateCountdown();

  // Update every second
  const intervalId = setInterval(updateCountdown, 1000);

  // Clean up interval when page unloads
  window.addEventListener("beforeunload", () => {
    clearInterval(intervalId);
  });
}
