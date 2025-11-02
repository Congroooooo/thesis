// Notification functions
function showSuccessMessage(message) {
  const existingNotification = document.querySelector(".pamo-notification");
  if (existingNotification) {
    existingNotification.remove();
  }

  const notification = document.createElement("div");
  notification.className = "pamo-notification success";
  notification.innerHTML = `
    <span class="material-icons" style="font-size: 20px; flex-shrink: 0;">check_circle</span>
    <span>${message}</span>
  `;

  document.body.appendChild(notification);

  setTimeout(() => {
    notification.style.transform = "translateX(0)";
    notification.style.opacity = "1";
  }, 10);

  setTimeout(() => {
    notification.style.transform = "translateX(400px)";
    notification.style.opacity = "0";
    setTimeout(() => {
      if (notification.parentNode) {
        notification.remove();
      }
    }, 300);
  }, 5000);
}

function showErrorMessage(message) {
  const existingNotification = document.querySelector(".pamo-notification");
  if (existingNotification) {
    existingNotification.remove();
  }

  const notification = document.createElement("div");
  notification.className = "pamo-notification error";
  notification.innerHTML = `
    <span class="material-icons" style="font-size: 20px; flex-shrink: 0;">error</span>
    <span>${message}</span>
  `;

  document.body.appendChild(notification);

  setTimeout(() => {
    notification.style.transform = "translateX(0)";
    notification.style.opacity = "1";
  }, 10);

  setTimeout(() => {
    notification.style.transform = "translateX(400px)";
    notification.style.opacity = "0";
    setTimeout(() => {
      if (notification.parentNode) {
        notification.remove();
      }
    }, 300);
  }, 5000);
}

function showRemoveItemModal() {
  const modal = document.getElementById("removeItemModal");
  modal.style.display = "block";

  // Initialize Select2 for the first dropdown when modal opens
  setTimeout(() => {
    initializeRemovalItemSelect2();
  }, 100);
}

function addRemovalItem() {
  const container = document.getElementById("removalItems");
  const itemCount = container.querySelectorAll(".removal-item").length;

  const newItem = document.createElement("div");
  newItem.className = "removal-item";
  newItem.innerHTML = `
        <div class="item-close" onclick="removeRemovalItem(this)">&times;</div>
        <div class="item-content">
            <div class="input-group">
                <label for="itemId">Product:</label>
                <select name="itemId[]" required onchange="updateRemovalItemDetails(this)">
                    ${getInventoryItemOptions()}
                </select>
            </div>
            <div class="input-group">
                <label for="currentStock">Current Stock:</label>
                <input type="number" name="currentStock[]" readonly class="current-stock-display" value="0">
            </div>
            <div class="input-group">
                <label for="quantityToRemove">Quantity to Remove:</label>
                <input type="number" name="quantityToRemove[]" min="1" required>
            </div>
            <div class="input-group">
                <label for="removalReason">Reason for Removal:</label>
                <textarea name="removalReason[]" rows="2" placeholder="e.g., Damaged due to water exposure, Deteriorated from long storage..." required></textarea>
            </div>
        </div>
    `;

  container.appendChild(newItem);

  // Show close button on all items except the first
  const allItems = container.querySelectorAll(".removal-item");
  allItems.forEach((item, index) => {
    const closeBtn = item.querySelector(".item-close");
    if (closeBtn) {
      closeBtn.style.display = index > 0 ? "block" : "none";
    }
  });

  // Initialize Select2 for the newly added dropdown
  setTimeout(() => {
    initializeRemovalItemSelect2();
  }, 100);
}

function removeRemovalItem(element) {
  const item = element.closest(".removal-item");
  const container = document.getElementById("removalItems");

  if (container.querySelectorAll(".removal-item").length > 1) {
    // Destroy Select2 before removing the element
    const $select = $(item).find('select[name="itemId[]"]');
    if ($select.length && $select.hasClass("select2-hidden-accessible")) {
      $select.select2("destroy");
    }

    item.remove();

    // Update close button visibility
    const allItems = container.querySelectorAll(".removal-item");
    allItems.forEach((item, index) => {
      const closeBtn = item.querySelector(".item-close");
      if (closeBtn) {
        closeBtn.style.display = index > 0 ? "block" : "none";
      }
    });
  }
}

function getInventoryItemOptions() {
  // Get the first select element to copy its options
  const selectElement = document.querySelector(
    '#removalItems select[name="itemId[]"]'
  );
  if (selectElement) {
    return selectElement.innerHTML;
  }
  return '<option value="">Select Product</option>';
}

function initializeRemovalItemSelect2() {
  // Initialize Select2 for all removal item dropdowns that don't have it yet
  if (typeof $ === "undefined" || typeof $.fn.select2 === "undefined") {
    console.error("jQuery or Select2 not loaded");
    return;
  }

  $('#removalItems select[name="itemId[]"]').each(function () {
    const $select = $(this);

    // Only initialize if not already initialized
    if (!$select.hasClass("select2-hidden-accessible")) {
      $select.select2({
        placeholder: "Search and select product...",
        allowClear: true,
        width: "100%",
        dropdownParent: $("#removeItemModal"),
        templateResult: function (data) {
          if (!data.id || data.loading) {
            return data.text;
          }

          // Check if this item is already selected in another dropdown
          const selectedItems = getSelectedItems();
          const isAlreadySelected = selectedItems.includes(data.id);

          // Parse the option text to extract details
          const text = data.text;
          const stockMatch = text.match(/Stock:\s*(\d+)/i);
          const sizeMatch = text.match(/Size:\s*([^-]+)/i);

          let displayHtml = '<div class="select2-result-repository clearfix">';
          displayHtml += '<div class="select2-result-repository__meta">';
          displayHtml +=
            '<div class="select2-result-repository__title">' +
            text.split(" (")[0] +
            "</div>";

          if (sizeMatch) {
            displayHtml +=
              '<div class="select2-result-repository__description">Size: ' +
              sizeMatch[1].trim() +
              "</div>";
          }

          if (stockMatch) {
            const stock = parseInt(stockMatch[1]);
            const stockClass =
              stock === 0
                ? "out-of-stock"
                : stock < 10
                ? "low-stock"
                : "in-stock";
            displayHtml +=
              '<div class="select2-result-repository__statistics">';
            displayHtml +=
              '<span class="stock-badge ' +
              stockClass +
              '">Stock: ' +
              stock +
              "</span>";
            if (isAlreadySelected) {
              displayHtml +=
                ' <span class="stock-badge" style="background-color: #ffc107; color: #000;">Already Selected</span>';
            }
            displayHtml += "</div>";
          }

          displayHtml += "</div></div>";

          // Disable if already selected
          if (isAlreadySelected) {
            return $(
              '<span class="select2-disabled-option">' + displayHtml + "</span>"
            );
          }

          return $(displayHtml);
        },
        templateSelection: function (data) {
          return data.text || data.id;
        },
      });

      // Re-attach the change event after Select2 initialization
      $select.on("change", function () {
        updateRemovalItemDetails(this);
        // Refresh all other dropdowns to update disabled states
        refreshAllRemovalDropdowns();
      });
    }
  });
}

// Helper function to get all currently selected item codes
function getSelectedItems() {
  const selectedItems = [];
  $('#removalItems select[name="itemId[]"]').each(function () {
    const value = $(this).val();
    if (value) {
      selectedItems.push(value);
    }
  });
  return selectedItems;
}

// Helper function to refresh all removal dropdowns (to update disabled states)
function refreshAllRemovalDropdowns() {
  $('#removalItems select[name="itemId[]"]').each(function () {
    const $select = $(this);
    if ($select.hasClass("select2-hidden-accessible")) {
      // Store current value
      const currentValue = $select.val();

      // Close dropdown if open
      $select.select2("close");

      // Trigger a change to update the display without actually changing the value
      // This forces Select2 to re-render the options
      $select.trigger("change.select2");
    }
  });
}

function updateRemovalItemDetails(selectElement) {
  if (!selectElement) {
    return;
  }

  const removalItem = selectElement.closest(".removal-item");
  if (!removalItem) {
    return;
  }

  // Use jQuery to get the selected option (works better with Select2)
  const $select = $(selectElement);
  const selectedValue = $select.val();
  const $selectedOption = $select.find("option:selected");

  if (!selectedValue || selectedValue === "") {
    // Reset fields when no item selected
    const stockInput = removalItem.querySelector(".current-stock-display");
    const quantityInput = removalItem.querySelector(
      'input[name="quantityToRemove[]"]'
    );

    if (stockInput) {
      stockInput.value = "0";
    }
    if (quantityInput) {
      quantityInput.value = "";
      quantityInput.removeAttribute("max");
    }
    return;
  }

  // Try to get stock from data attribute first
  let currentStock = $selectedOption.attr("data-stock");
  const itemCode = selectedValue;
  const optionText = $selectedOption.text();

  // If data-stock is not available (Select2 issue), parse from option text
  if (!currentStock || currentStock === "undefined") {
    const stockMatch = optionText.match(/Stock:\s*(\d+)/i);
    if (stockMatch && stockMatch[1]) {
      currentStock = stockMatch[1];
    } else {
      currentStock = "0";
    }
  }

  // Find the stock display input
  const stockInput = removalItem.querySelector(".current-stock-display");
  if (!stockInput) {
    return;
  }

  // Parse and update the stock value
  const stockValue = parseInt(currentStock) || 0;
  stockInput.value = stockValue.toString();

  // Find and update the quantity input
  const quantityInput = removalItem.querySelector(
    'input[name="quantityToRemove[]"]'
  );
  if (quantityInput) {
    quantityInput.setAttribute("max", stockValue.toString());
    quantityInput.setAttribute("min", "1");
    quantityInput.value = "";

    // Add a helpful title/tooltip
    if (stockValue > 0) {
      quantityInput.title = `Maximum quantity you can remove: ${stockValue}`;
    } else {
      quantityInput.title = "This item has no stock available";
      quantityInput.setAttribute("max", "0");
    }
  }
}

function submitRemoveItem(event) {
  event.preventDefault();
  event.stopPropagation();

  const form = document.getElementById("removeItemForm");
  const formData = new FormData(form);

  // Validation
  const pulloutOrderNumber = formData.get("pulloutOrderNumber");
  if (!pulloutOrderNumber || pulloutOrderNumber.trim() === "") {
    showErrorMessage("Please enter a Pullout Order Number");
    return;
  }

  const itemIds = formData.getAll("itemId[]");
  const quantities = formData.getAll("quantityToRemove[]");
  const reasons = formData.getAll("removalReason[]");
  const currentStocks = Array.from(
    document.querySelectorAll(".current-stock-display")
  ).map((input) => parseInt(input.value) || 0);

  // Validate each item
  for (let i = 0; i < itemIds.length; i++) {
    if (!itemIds[i]) {
      showErrorMessage(`Please select a product for item #${i + 1}`);
      return;
    }

    const qty = parseInt(quantities[i]);
    if (!qty || qty <= 0) {
      showErrorMessage(`Please enter a valid quantity for item #${i + 1}`);
      return;
    }

    if (qty > currentStocks[i]) {
      showErrorMessage(
        `Quantity to remove (${qty}) exceeds current stock (${
          currentStocks[i]
        }) for item #${i + 1}`
      );
      return;
    }

    if (!reasons[i] || reasons[i].trim() === "") {
      showErrorMessage(`Please enter a removal reason for item #${i + 1}`);
      return;
    }
  }

  // Show custom confirmation modal
  const itemCount = itemIds.length;
  const totalQuantity = quantities.reduce((sum, qty) => sum + parseInt(qty), 0);

  showRemoveConfirmationModal(
    pulloutOrderNumber,
    totalQuantity,
    itemCount,
    formData
  );
}

// Custom confirmation modal function
function showRemoveConfirmationModal(
  pulloutOrderNumber,
  totalQuantity,
  itemCount,
  formData
) {
  const confirmModal = document.createElement("div");
  confirmModal.className = "custom-confirm-modal";
  confirmModal.innerHTML = `
    <div class="custom-confirm-overlay"></div>
    <div class="custom-confirm-content">
      <div class="custom-confirm-icon">
        <span class="material-icons" style="font-size: 48px; color: #dc3545;">warning</span>
      </div>
      <h3 class="custom-confirm-title">Confirm Item Removal</h3>
      <p class="custom-confirm-message">
        Are you sure you want to remove <strong>${totalQuantity} item(s)</strong> across <strong>${itemCount} product(s)</strong> from inventory?
      </p>
      <p class="custom-confirm-detail">
        <strong>Pullout Order #:</strong> ${pulloutOrderNumber}
      </p>
      <p class="custom-confirm-warning">
        <span class="material-icons" style="font-size: 18px; vertical-align: middle;">info</span>
        This action cannot be undone.
      </p>
      <div class="custom-confirm-buttons">
        <button class="custom-confirm-btn confirm-yes">OK</button>
        <button class="custom-confirm-btn confirm-no">Cancel</button>
      </div>
    </div>
  `;

  document.body.appendChild(confirmModal);

  // Animate in
  setTimeout(() => {
    confirmModal.style.opacity = "1";
    const content = confirmModal.querySelector(".custom-confirm-content");
    if (content) {
      content.style.transform = "scale(1)";
    }
  }, 10);

  // Handle buttons
  const yesBtn = confirmModal.querySelector(".confirm-yes");
  const noBtn = confirmModal.querySelector(".confirm-no");

  yesBtn.onclick = () => {
    confirmModal.remove();
    processRemoveItem(formData);
  };

  noBtn.onclick = () => {
    confirmModal.style.opacity = "0";
    setTimeout(() => confirmModal.remove(), 300);
  };

  // Close on overlay click
  confirmModal.querySelector(".custom-confirm-overlay").onclick = () => {
    confirmModal.style.opacity = "0";
    setTimeout(() => confirmModal.remove(), 300);
  };
}

// Process the removal
function processRemoveItem(formData) {
  const submitBtn = document.querySelector(
    'button[form="removeItemForm"][type="submit"]'
  );
  const originalBtnText = submitBtn ? submitBtn.textContent : "Remove Items";

  if (submitBtn) {
    submitBtn.disabled = true;
    submitBtn.textContent = "Removing Items...";
    submitBtn.style.cursor = "not-allowed";
    submitBtn.style.opacity = "0.6";
  }

  // Submit form via AJAX
  fetch("../PAMO Inventory backend/process_remove_item.php", {
    method: "POST",
    body: formData,
  })
    .then((response) => response.json())
    .then((data) => {
      // Restore button state
      if (submitBtn) {
        submitBtn.disabled = false;
        submitBtn.textContent = originalBtnText;
        submitBtn.style.cursor = "pointer";
        submitBtn.style.opacity = "1";
      }

      if (data.success) {
        // Reset form
        const form = document.getElementById("removeItemForm");
        if (form) form.reset();

        // Reset to single item
        const container = document.getElementById("removalItems");
        if (container) {
          const items = container.querySelectorAll(".removal-item");
          for (let i = 1; i < items.length; i++) {
            items[i].remove();
          }
        }

        // Close modal and show notification
        closeModal("removeItemModal");
        showSuccessMessage(
          `Success! ${data.total_items} item(s) removed from inventory. Refreshing...`
        );

        // Refresh inventory table without page reload
        setTimeout(() => {
          if (typeof refreshInventoryTable === "function") {
            refreshInventoryTable();
          } else {
            location.reload();
          }
        }, 800);
      } else {
        showErrorMessage("Error: " + data.message);
      }
    })
    .catch((error) => {
      // Restore button state
      if (submitBtn) {
        submitBtn.disabled = false;
        submitBtn.textContent = originalBtnText;
        submitBtn.style.cursor = "pointer";
        submitBtn.style.opacity = "1";
      }

      console.error("Error:", error);
      showErrorMessage(
        "An error occurred while processing the removal. Please try again."
      );
    });
}

// Add event listener for modal close to handle removeItemModal
document.addEventListener("DOMContentLoaded", function () {
  const originalCloseModal = window.closeModal;

  window.closeModal = function (modalId) {
    if (originalCloseModal) {
      originalCloseModal(modalId);
    }

    if (modalId === "removeItemModal") {
      const form = document.getElementById("removeItemForm");
      if (form) form.reset();

      // Reset to single item entry
      const container = document.getElementById("removalItems");
      if (!container) return;

      const items = container.querySelectorAll(".removal-item");

      // Destroy Select2 and remove all except first
      for (let i = 1; i < items.length; i++) {
        const $select = $(items[i]).find('select[name="itemId[]"]');
        if ($select.length && $select.hasClass("select2-hidden-accessible")) {
          $select.select2("destroy");
        }
        items[i].remove();
      }

      // Reset first item
      if (items.length > 0) {
        const firstItem = items[0];
        const $select = $(firstItem).find('select[name="itemId[]"]');
        const stockInput = firstItem.querySelector(".current-stock-display");
        const qtyInput = firstItem.querySelector(
          'input[name="quantityToRemove[]"]'
        );
        const reasonTextarea = firstItem.querySelector(
          'textarea[name="removalReason[]"]'
        );

        // Reset Select2 value
        if ($select.length && $select.hasClass("select2-hidden-accessible")) {
          $select.val("").trigger("change");
        } else if ($select.length) {
          $select[0].selectedIndex = 0;
        }

        if (stockInput) stockInput.value = "0";
        if (qtyInput) {
          qtyInput.value = "";
          qtyInput.removeAttribute("max");
        }
        if (reasonTextarea) reasonTextarea.value = "";
      }
    }
  };

  // Initialize any pre-existing dropdowns when modal is shown
  const modal = document.getElementById("removeItemModal");
  if (modal) {
    // Listen for when modal becomes visible
    const observer = new MutationObserver(function (mutations) {
      mutations.forEach(function (mutation) {
        if (
          mutation.type === "attributes" &&
          mutation.attributeName === "style"
        ) {
          if (modal.style.display === "block") {
            // Check if any select has a value and update accordingly
            const selects = modal.querySelectorAll('select[name="itemId[]"]');
            selects.forEach(function (select) {
              if (select.value) {
                updateRemovalItemDetails(select);
              }
            });
          }
        }
      });
    });

    observer.observe(modal, {
      attributes: true,
      attributeFilter: ["style"],
    });
  }
});
