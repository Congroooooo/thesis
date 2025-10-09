document.addEventListener("DOMContentLoaded", function () {
  initializeTabs();
  initializeFileUploads();
  initializeActionButtons();

  // Auto-dismiss alerts after 5 seconds
  setTimeout(() => {
    const alertMsg = document.getElementById("feedbackMsg");
    if (alertMsg) {
      alertMsg.style.opacity = "0";
      setTimeout(() => alertMsg.remove(), 300);
    }
  }, 5000);
});

// Tab Navigation System
function initializeTabs() {
  const tabBtns = document.querySelectorAll(".tab-btn");
  const tabContents = document.querySelectorAll(".tab-content");

  tabBtns.forEach((btn) => {
    btn.addEventListener("click", () => {
      const targetTab = btn.getAttribute("data-tab");

      // Remove active classes
      tabBtns.forEach((b) => b.classList.remove("active"));
      tabContents.forEach((content) => content.classList.remove("active"));

      // Add active classes
      btn.classList.add("active");
      document.getElementById(targetTab + "-tab").classList.add("active");
    });
  });
}

// File Upload Functionality
function initializeFileUploads() {
  const fileInputs = document.querySelectorAll(".file-input");

  fileInputs.forEach((input) => {
    input.addEventListener("change", function (e) {
      const file = e.target.files[0];
      if (file) {
        const previewId = input.id.replace("Image", "Preview");
        const previewImgId = input.id.replace("Image", "PreviewImg");
        const preview = document.getElementById(previewId);
        const previewImg = document.getElementById(previewImgId);

        if (preview && previewImg) {
          const reader = new FileReader();
          reader.onload = function (e) {
            previewImg.src = e.target.result;
            preview.style.display = "block";

            // Update file name in button
            const fileText = input.parentElement.querySelector(".file-text");
            if (fileText) {
              fileText.textContent = file.name;
            }
          };
          reader.readAsDataURL(file);
        }
      }
    });
  });
}

// Clear File Preview Function
function clearPreview(type) {
  const preview = document.getElementById(type + "Preview");
  const input = document.getElementById(type + "Image");
  const fileText = input.parentElement.querySelector(".file-text");

  if (preview) preview.style.display = "none";
  if (input) input.value = "";
  if (fileText) fileText.textContent = "Choose image file";
}

// Action Buttons (Edit/Delete) Functionality
function initializeActionButtons() {
  document.addEventListener("click", function (e) {
    // Handle Edit Button
    if (e.target.closest(".edit-btn")) {
      const btn = e.target.closest(".edit-btn");
      const itemId = btn.getAttribute("data-id");
      const section = btn.getAttribute("data-section");
      openEditModal(itemId, section);
    }

    // Handle Delete Button
    if (e.target.closest(".delete-btn")) {
      const btn = e.target.closest(".delete-btn");
      const itemId = btn.getAttribute("data-id");
      const section = btn.getAttribute("data-section");
      confirmDelete(itemId, section);
    }
  });
}

// Confirm Delete Function
function confirmDelete(itemId, section) {
  const sectionNames = {
    new_arrival: "Item Category",
    display: "Carousel Image",
    pre_order: "Pre-Order Item",
  };

  const itemType = sectionNames[section] || "item";

  if (
    confirm(
      `Are you sure you want to delete this ${itemType}? This action cannot be undone.`
    )
  ) {
    deleteItem(itemId);
  }
}

// Delete Item Function
function deleteItem(itemId) {
  // Show loading state
  showLoading("Deleting item...");

  fetch("../PAMO BACKEND CONTENT EDIT/delete-content-image.php", {
    method: "POST",
    headers: {
      "Content-Type": "application/x-www-form-urlencoded",
    },
    body: "id=" + encodeURIComponent(itemId),
  })
    .then((response) => response.json())
    .then((data) => {
      hideLoading();
      if (data.success) {
        showAlert("Item deleted successfully!", "success");
        setTimeout(() => location.reload(), 1000);
      } else {
        showAlert(data.error || "Failed to delete item.", "error");
      }
    })
    .catch((error) => {
      hideLoading();
      showAlert("An error occurred while deleting the item.", "error");
      console.error("Error:", error);
    });
}

// Edit Modal Functions
function openEditModal(itemId, section) {
  // Implementation would depend on your edit modal structure
  // For now, we'll show a simple prompt
  const newTitle = prompt("Enter new title:");
  if (newTitle && newTitle.trim()) {
    updateItemTitle(itemId, newTitle.trim());
  }
}

function updateItemTitle(itemId, newTitle) {
  showLoading("Updating item...");

  fetch("../PAMO BACKEND CONTENT EDIT/update-content-image.php", {
    method: "POST",
    headers: {
      "Content-Type": "application/x-www-form-urlencoded",
    },
    body: `id=${encodeURIComponent(itemId)}&title=${encodeURIComponent(
      newTitle
    )}`,
  })
    .then((response) => response.json())
    .then((data) => {
      hideLoading();
      if (data.success) {
        showAlert("Item updated successfully!", "success");
        setTimeout(() => location.reload(), 1000);
      } else {
        showAlert(data.error || "Failed to update item.", "error");
      }
    })
    .catch((error) => {
      hideLoading();
      showAlert("An error occurred while updating the item.", "error");
      console.error("Error:", error);
    });
}

// Utility Functions
function showAlert(message, type = "info") {
  const alertDiv = document.createElement("div");
  alertDiv.className = `alert ${type}`;
  alertDiv.innerHTML = `
        ${message}
        <span class="close-btn" onclick="this.parentElement.remove()">&times;</span>
    `;

  const container = document.querySelector(".main-content");
  container.insertBefore(alertDiv, container.firstChild);

  // Auto-remove after 5 seconds
  setTimeout(() => {
    if (alertDiv.parentElement) {
      alertDiv.style.opacity = "0";
      setTimeout(() => alertDiv.remove(), 300);
    }
  }, 5000);
}

function showLoading(message = "Loading...") {
  const loadingDiv = document.createElement("div");
  loadingDiv.id = "loadingOverlay";
  loadingDiv.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 10000;
        color: white;
        font-size: 1.2rem;
        font-weight: 600;
    `;
  loadingDiv.textContent = message;
  document.body.appendChild(loadingDiv);
}

function hideLoading() {
  const loadingDiv = document.getElementById("loadingOverlay");
  if (loadingDiv) {
    loadingDiv.remove();
  }
}
