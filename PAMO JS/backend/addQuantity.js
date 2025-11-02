// New Delivery Modal Backend Functions

// Notification functions
function showSuccessMessage(message) {
  // Remove any existing notifications
  const existingNotification = document.querySelector(".pamo-notification");
  if (existingNotification) {
    existingNotification.remove();
  }

  // Create notification element
  const notification = document.createElement("div");
  notification.className = "pamo-notification success";
  notification.innerHTML = `
    <span class="material-icons" style="font-size: 24px; margin-right: 12px;">check_circle</span>
    <span>${message}</span>
  `;

  // Add to body
  document.body.appendChild(notification);

  // Trigger animation
  setTimeout(() => {
    notification.style.transform = "translateX(0)";
    notification.style.opacity = "1";
  }, 10);

  // Auto remove after 5 seconds
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
  // Remove any existing notifications
  const existingNotification = document.querySelector(".pamo-notification");
  if (existingNotification) {
    existingNotification.remove();
  }

  // Create notification element
  const notification = document.createElement("div");
  notification.className = "pamo-notification error";
  notification.innerHTML = `
    <span class="material-icons" style="font-size: 24px; margin-right: 12px;">error</span>
    <span>${message}</span>
  `;

  // Add to body
  document.body.appendChild(notification);

  // Trigger animation
  setTimeout(() => {
    notification.style.transform = "translateX(0)";
    notification.style.opacity = "1";
  }, 10);

  // Auto remove after 5 seconds
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

function showAddQuantityModal() {
  document.getElementById("addQuantityModal").style.display = "block";
  document.getElementById("addQuantityForm").reset();
  // Reset all select elements to their default state
  document.querySelectorAll('select[name="itemId[]"]').forEach((select) => {
    select.value = "";
  });
}

function addDeliveryItem() {
  const deliveryItems = document.getElementById("deliveryItems");
  const originalItem = deliveryItems.querySelector(".delivery-item");

  // Destroy Select2 on the original select before cloning
  const originalSelect = originalItem.querySelector('select[name="itemId[]"]');
  if (window.jQuery && $(originalSelect).data("select2")) {
    $(originalSelect).select2("destroy");
  }

  // Clone the item
  const newItem = originalItem.cloneNode(true);

  // Reset the values in the cloned item
  const select = newItem.querySelector('select[name="itemId[]"]');
  const input = newItem.querySelector('input[name="quantityToAdd[]"]');
  if (select) select.value = "";
  if (input) input.value = "";

  // Show close button for the new item
  const closeBtn = newItem.querySelector(".item-close");
  if (closeBtn) {
    closeBtn.style.display = "block";
    closeBtn.onclick = function () {
      removeDeliveryItem(this);
    };
  }

  // Add change event listener to the new select element
  if (select) {
    select.addEventListener("change", function () {
      validateProductSelection(this);
      updateProductOptions();
    });
  }

  deliveryItems.appendChild(newItem);

  // Re-initialize Select2 for both the original and the new select
  if (window.jQuery) {
    $(originalSelect)
      .select2({
        placeholder: "Select Product",
        allowClear: true,
        width: "100%",
      })
      .on("change", updateProductOptions);
    $(select)
      .select2({
        placeholder: "Select Product",
        allowClear: true,
        width: "100%",
      })
      .on("change", updateProductOptions);
  }
  updateProductOptions();
}

function removeDeliveryItem(closeButton) {
  const deliveryItems = document.getElementById("deliveryItems");
  const items = deliveryItems.querySelectorAll(".delivery-item");

  if (items.length > 1) {
    const deliveryItem = closeButton.closest(".delivery-item");
    deliveryItem.remove();
    // Revalidate all remaining select elements
    document.querySelectorAll('select[name="itemId[]"]').forEach((select) => {
      validateProductSelection(select);
    });
  }
}

function validateProductSelection(selectElement) {
  const selectedValue = selectElement.value;
  if (!selectedValue) return;

  const allSelects = document.querySelectorAll('select[name="itemId[]"]');
  let duplicateFound = false;

  allSelects.forEach((select) => {
    if (select !== selectElement && select.value === selectedValue) {
      duplicateFound = true;
    }
  });

  if (duplicateFound) {
    alert(
      "This product has already been selected. Please choose a different product."
    );
    selectElement.value = "";
  }
}

function submitAddQuantity(event) {
  event.preventDefault();
  event.stopPropagation();

  try {
    const orderNumber = document.getElementById("orderNumber").value;
    const deliveryItems = document.querySelectorAll(".delivery-item");

    if (!orderNumber || orderNumber.trim() === "") {
      showErrorMessage("Please enter a delivery order number");
      return false;
    }

    if (!deliveryItems || deliveryItems.length === 0) {
      showErrorMessage("Please add at least one item");
      return false;
    }

    const formData = new FormData();
    formData.append("orderNumber", orderNumber);

    const itemIds = [];
    const quantities = [];

    for (let i = 0; i < deliveryItems.length; i++) {
      const item = deliveryItems[i];
      const itemSelect = item.querySelector('select[name="itemId[]"]');
      const quantityInput = item.querySelector('input[name="quantityToAdd[]"]');

      if (!itemSelect || !quantityInput) {
        showErrorMessage(`Invalid form elements for item ${i + 1}`);
        return false;
      }

      if (!itemSelect.value) {
        showErrorMessage(`Please select a product for item ${i + 1}`);
        itemSelect.focus();
        return false;
      }

      const quantity = parseInt(quantityInput.value);
      if (!quantity || quantity <= 0) {
        showErrorMessage(
          `Please enter a valid quantity greater than 0 for item ${i + 1}`
        );
        quantityInput.focus();
        return false;
      }

      if (itemIds.includes(itemSelect.value)) {
        showErrorMessage(
          `Duplicate product selected for item ${
            i + 1
          }. Please select different products.`
        );
        itemSelect.focus();
        return false;
      }

      itemIds.push(itemSelect.value);
      quantities.push(quantity);
    }

    itemIds.forEach((id, index) => {
      formData.append("itemId[]", id);
      formData.append("quantityToAdd[]", quantities[index]);
    });

    const submitButton = document.querySelector(
      'button[form="addQuantityForm"]'
    );
    const originalButtonText = submitButton
      ? submitButton.textContent
      : "Record Delivery";

    if (submitButton) {
      submitButton.disabled = true;
      submitButton.textContent = "Recording Delivery...";
      submitButton.style.cursor = "not-allowed";
      submitButton.style.opacity = "0.6";
    }

    fetch("../PAMO Inventory backend/process_add_quantity.php", {
      method: "POST",
      body: formData,
    })
      .then((response) => {
        if (!response.ok) {
          throw new Error("Failed to process request");
        }
        return response.json();
      })
      .then((data) => {
        if (data.success) {
          // Reset form
          document.getElementById("addQuantityForm").reset();
          const deliveryItemsContainer =
            document.getElementById("deliveryItems");
          const items =
            deliveryItemsContainer.querySelectorAll(".delivery-item");
          for (let i = 1; i < items.length; i++) {
            items[i].remove();
          }

          // Close modal and show notification
          closeModal("addQuantityModal");
          showSuccessMessage(
            "Delivery recorded successfully! Refreshing inventory..."
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
          throw new Error(data.message || "Failed to record delivery");
        }
      })
      .catch((error) => {
        showErrorMessage("Error: " + error.message);
      })
      .finally(() => {
        if (submitButton) {
          submitButton.disabled = false;
          submitButton.textContent = originalButtonText;
          submitButton.style.cursor = "pointer";
          submitButton.style.opacity = "1";
        }
      });
  } catch (error) {
    showErrorMessage("An error occurred while processing your request");
    return false;
  }
}

function updateProductOptions() {
  const allSelects = document.querySelectorAll('select[name="itemId[]"]');
  const selectedValues = Array.from(allSelects)
    .map((select) => select.value)
    .filter((val) => val);

  allSelects.forEach((select) => {
    const currentValue = select.value;
    // Store all option values and text
    const allOptions = Array.from(select.querySelectorAll("option")).map(
      (opt) => ({
        value: opt.value,
        text: opt.text,
        selected: opt.selected,
      })
    );

    // Remove all options except the placeholder and the current value
    select.innerHTML = "";
    // Add placeholder
    const placeholderOption = document.createElement("option");
    placeholderOption.value = "";
    placeholderOption.textContent = "Select Product";
    select.appendChild(placeholderOption);

    // Add back only options that are not selected in other selects, or the current value
    allOptions.forEach((opt) => {
      if (
        opt.value === "" ||
        opt.value === currentValue ||
        !selectedValues.includes(opt.value)
      ) {
        const option = document.createElement("option");
        option.value = opt.value;
        option.text = opt.text;
        if (opt.value === currentValue) option.selected = true;
        select.appendChild(option);
      }
    });

    // Refresh Select2
    if (window.jQuery && $(select).data("select2")) {
      $(select).trigger("change.select2");
    }
  });
}

function resetAddQuantityModal() {
  const form = document.getElementById("addQuantityForm");
  if (form) form.reset();

  // Remove all delivery-item divs except the first one
  const deliveryItems = document.getElementById("deliveryItems");
  if (deliveryItems) {
    const items = deliveryItems.querySelectorAll(".delivery-item");
    items.forEach((item, idx) => {
      if (idx > 0) item.remove();
    });
  }

  // Reset Select2 for all product selects
  if (window.jQuery && $('select[name="itemId[]"]').length) {
    $('select[name="itemId[]"]').val(null).trigger("change");
  }
}

// Event Listeners
document.addEventListener("DOMContentLoaded", function () {
  // Add click handlers to existing close buttons
  const closeButtons = document.querySelectorAll(".item-close");
  if (closeButtons) {
    closeButtons.forEach((btn) => {
      if (btn) {
        btn.onclick = function () {
          removeDeliveryItem(this);
        };
      }
    });
  }

  // Add change event listeners to all select elements
  const selectElements = document.querySelectorAll('select[name="itemId[]"]');
  if (selectElements) {
    selectElements.forEach((select) => {
      if (select) {
        select.addEventListener("change", function () {
          validateProductSelection(this);
          updateProductOptions();
        });
      }
    });
  }

  // Initialize Select2 for all product dropdowns
  if (window.jQuery && $('select[name="itemId[]"]').length) {
    $('select[name="itemId[]"]')
      .select2({
        placeholder: "Select Product",
        allowClear: true,
        width: "100%",
      })
      .on("change", updateProductOptions);
  }
  updateProductOptions();

  const addQuantityModal = document.getElementById("addQuantityModal");
  if (addQuantityModal) {
    // Find all close buttons inside the modal
    const closeBtns = addQuantityModal.querySelectorAll(".close, .cancel-btn");
    closeBtns.forEach((btn) => {
      btn.addEventListener("click", function () {
        resetAddQuantityModal();
      });
    });
  }
});
