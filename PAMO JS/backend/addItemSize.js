// Add Item Size Modal Backend Functions

// Notification helper functions
function showSuccessMessage(message) {
  // Remove any existing notification
  const existingNotification = document.getElementById("pamo-notification");
  if (existingNotification) {
    existingNotification.remove();
  }

  // Create notification element
  const notification = document.createElement("div");
  notification.id = "pamo-notification";
  notification.className = "pamo-notification success";
  notification.innerHTML = `
    <i class="material-icons">check_circle</i>
    <span>${message}</span>
  `;

  document.body.appendChild(notification);

  // Trigger animation
  setTimeout(() => notification.classList.add("show"), 10);

  // Auto-hide after 3 seconds
  setTimeout(() => {
    notification.classList.remove("show");
    setTimeout(() => notification.remove(), 300);
  }, 3000);
}

function showErrorMessage(message) {
  // Remove any existing notification
  const existingNotification = document.getElementById("pamo-notification");
  if (existingNotification) {
    existingNotification.remove();
  }

  // Create notification element
  const notification = document.createElement("div");
  notification.id = "pamo-notification";
  notification.className = "pamo-notification error";
  notification.innerHTML = `
    <i class="material-icons">error</i>
    <span>${message}</span>
  `;

  document.body.appendChild(notification);

  // Trigger animation
  setTimeout(() => notification.classList.add("show"), 10);

  // Auto-hide after 5 seconds
  setTimeout(() => {
    notification.classList.remove("show");
    setTimeout(() => notification.remove(), 300);
  }, 5000);
}

const allSizes = [
  "XS",
  "S",
  "M",
  "L",
  "XL",
  "XXL",
  "3XL",
  "4XL",
  "5XL",
  "6XL",
  "7XL",
  "One Size",
];

const sizeSuffixMap = {
  "One Size": "001",
  XS: "001",
  S: "002",
  M: "003",
  L: "004",
  XL: "005",
  XXL: "006",
  "3XL": "007",
  "4XL": "008",
  "5XL": "009",
  "6XL": "010",
  "7XL": "011",
};

let inventoryUsedSizes = {};
let itemCounter = 1;
let selectedItems = {}; // Track selected items across all entries

function showAddItemSizeModal() {
  document.getElementById("addItemSizeModal").style.display = "flex";

  // Reset counter and data
  itemCounter = 1;
  inventoryUsedSizes = {};
  selectedItems = {}; // Reset selected items tracking

  // Reset the form
  document.getElementById("addItemSizeForm").reset();

  // Reset to single entry
  const itemsContainer = document.getElementById("itemsContainer");
  const itemEntries = itemsContainer.querySelectorAll(".item-entry");

  // Remove all entries except the first one
  for (let i = 1; i < itemEntries.length; i++) {
    itemEntries[i].remove();
  }

  // Reset the first entry
  resetItemEntry(0);

  // Hide remove button for the first entry
  const removeBtn = document.querySelector(".remove-item-btn");
  if (removeBtn) removeBtn.style.display = "none";

  // Ensure the first item's select is reset properly
  const firstItemSelect = document.getElementById("existingItem_0");
  if (firstItemSelect) {
    firstItemSelect.selectedIndex = 0;
  }

  // Initialize Select2 for the modal
  setTimeout(() => {
    if (typeof initializeItemSizeSelect2 === "function") {
      initializeItemSizeSelect2();
    }
    // Initialize dropdown states
    updateAllDropdowns();
  }, 200);
}
function resetItemEntry(itemIndex) {
  // Ensure itemIndex is treated as a number
  itemIndex = parseInt(itemIndex);

  // Remove from selected items if it was selected
  if (selectedItems[itemIndex]) {
    delete selectedItems[itemIndex];
    updateAllDropdowns();
  }

  // Reset size checkboxes
  const sizeCheckboxes = document.querySelectorAll(
    `#sizeCheckboxesContainer_${itemIndex} input[type="checkbox"]`
  );
  sizeCheckboxes.forEach((checkbox) => {
    checkbox.checked = false;
    checkbox.disabled = false;
    checkbox.parentElement.style.opacity = "1";
    checkbox.parentElement.title = "";
  });

  // Hide sections
  const sizeSelectionSection = document.getElementById(
    `sizeSelectionSection_${itemIndex}`
  );
  const sizeDetailsContainer = document.getElementById(
    `itemSizeDetailsContainer_${itemIndex}`
  );
  const sizeDetailsList = document.getElementById(
    `itemSizeDetailsList_${itemIndex}`
  );

  if (sizeSelectionSection) {
    sizeSelectionSection.style.display = "none";
  }
  if (sizeDetailsContainer) {
    sizeDetailsContainer.style.display = "none";
    sizeDetailsContainer.classList.remove("show");
  }
  if (sizeDetailsList) {
    sizeDetailsList.innerHTML = "";
  }

  // Reset inventory used sizes for this item
  inventoryUsedSizes[itemIndex] = [];
}

function fetchAndUpdateSizesForItem(itemIndex) {
  // Ensure itemIndex is treated as a number
  itemIndex = parseInt(itemIndex);

  const select = document.getElementById(`existingItem_${itemIndex}`);

  if (!select) {
    console.error(`Select element not found: existingItem_${itemIndex}`);
    return;
  }

  const prefix = select.value;

  // Handle item selection change
  if (prefix) {
    // Remove previous selection if it exists
    const previousSelection = selectedItems[itemIndex];
    if (previousSelection) {
      delete selectedItems[itemIndex];
      updateAllDropdowns();
    }

    // Add new selection
    selectedItems[itemIndex] = prefix;
    updateAllDropdowns();
  } else {
    // Remove selection if cleared
    if (selectedItems[itemIndex]) {
      delete selectedItems[itemIndex];
      updateAllDropdowns();
    }
    resetItemEntry(itemIndex);
    return;
  }

  fetch(
    `../PAMO Inventory backend/get_item_sizes.php?prefix=${encodeURIComponent(
      prefix
    )}`
  )
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        inventoryUsedSizes[itemIndex] = data.sizes;

        // Show size selection section
        const sizeSelectionSection = document.getElementById(
          `sizeSelectionSection_${itemIndex}`
        );
        if (sizeSelectionSection) {
          sizeSelectionSection.style.display = "block";
        } else {
          console.error(
            `Size selection section not found: sizeSelectionSection_${itemIndex}`
          );
        }

        // Update checkboxes - disable existing sizes
        const sizeCheckboxes = document.querySelectorAll(
          `#sizeCheckboxesContainer_${itemIndex} input[type="checkbox"]`
        );
        sizeCheckboxes.forEach((checkbox) => {
          checkbox.checked = false;
          if (inventoryUsedSizes[itemIndex].includes(checkbox.value)) {
            checkbox.disabled = true;
            // Add visual indication for disabled checkboxes
            checkbox.parentElement.style.opacity = "0.5";
            checkbox.parentElement.title =
              "This size already exists for this item";
          } else {
            checkbox.disabled = false;
            checkbox.parentElement.style.opacity = "1";
            checkbox.parentElement.title = "";
          }
        });

        // Clear any previous size details
        const sizeDetailsContainer = document.getElementById(
          `itemSizeDetailsContainer_${itemIndex}`
        );
        const sizeDetailsList = document.getElementById(
          `itemSizeDetailsList_${itemIndex}`
        );

        if (sizeDetailsContainer) {
          sizeDetailsContainer.style.display = "none";
          sizeDetailsContainer.classList.remove("show");
        }
        if (sizeDetailsList) {
          sizeDetailsList.innerHTML = "";
        }
      } else {
        alert("Error fetching sizes: " + data.message);
      }
    })
    .catch((err) => {
      alert("Error fetching sizes");
    });
}

function toggleSizeDetailsForItemSize(checkbox, itemIndex) {
  // Ensure itemIndex is treated as a number
  itemIndex = parseInt(itemIndex);

  const size = checkbox.value;
  const selectElement = document.getElementById(`existingItem_${itemIndex}`);

  if (!selectElement) {
    console.error(`Select element not found: existingItem_${itemIndex}`);
    alert("Error: Item selection element not found");
    checkbox.checked = false;
    return;
  }

  const prefix = selectElement.value;

  if (!prefix) {
    alert("Please select an item first");
    checkbox.checked = false;
    return;
  }

  const sizeDetailsList = document.getElementById(
    `itemSizeDetailsList_${itemIndex}`
  );
  const sizeDetailsContainer = document.getElementById(
    `itemSizeDetailsContainer_${itemIndex}`
  );

  if (!sizeDetailsList || !sizeDetailsContainer) {
    console.error(
      `Size details elements not found for itemIndex ${itemIndex}:`,
      {
        sizeDetailsList: !!sizeDetailsList,
        sizeDetailsContainer: !!sizeDetailsContainer,
      }
    );
    alert("Error: Size details containers not found");
    checkbox.checked = false;
    return;
  }

  if (checkbox.checked) {
    // Add size detail entry
    const suffix = sizeSuffixMap[size] || "";
    const itemCode = suffix ? `${prefix}-${suffix}` : `${prefix}-`;

    const sizeDetailDiv = document.createElement("div");
    sizeDetailDiv.className = "size-detail-entry";
    sizeDetailDiv.dataset.size = size;
    sizeDetailDiv.innerHTML = `
      <div class="size-detail-header">
        <h4>Size: ${size}</h4>
      </div>
      <div class="size-detail-content">
        <div class="input-group">
          <label>Item Code:</label>
          <div class="generated-code">${itemCode}</div>
          <input type="hidden" name="items[${itemIndex}][itemCodes][]" value="${itemCode}">
          <input type="hidden" name="items[${itemIndex}][sizes][]" value="${size}">
        </div>
        <div class="input-group">
          <label for="quantity_${itemIndex}_${size}">Initial Stock:</label>
          <input type="number" id="quantity_${itemIndex}_${size}" name="items[${itemIndex}][quantities][]" min="1" required>
        </div>
        <div class="input-group">
          <label for="damage_${itemIndex}_${size}">Damaged Items:</label>
          <input type="number" id="damage_${itemIndex}_${size}" name="items[${itemIndex}][damages][]" min="0" value="0">
        </div>
        <div class="input-group">
          <label for="price_${itemIndex}_${size}">Price (₱):</label>
          <input type="number" id="price_${itemIndex}_${size}" name="items[${itemIndex}][prices][]" step="0.01" min="0" required>
        </div>
      </div>
    `;

    sizeDetailsList.appendChild(sizeDetailDiv);

    // Show container if this is the first size
    if (sizeDetailsList.children.length === 1) {
      sizeDetailsContainer.style.display = "block";
      sizeDetailsContainer.classList.add("show");
    }
  } else {
    // Remove size detail entry
    const existingEntry = sizeDetailsList.querySelector(
      `[data-size="${size}"]`
    );
    if (existingEntry) {
      existingEntry.remove();
    }

    // Hide container if no sizes left
    if (sizeDetailsList.children.length === 0) {
      sizeDetailsContainer.style.display = "none";
      sizeDetailsContainer.classList.remove("show");
    }
  }
}

function addAnotherItemEntry() {
  const itemsContainer = document.getElementById("itemsContainer");
  const existingItems = document.querySelectorAll(".item-entry");
  const existingIndices = Array.from(existingItems).map((item) =>
    parseInt(item.dataset.itemIndex)
  );

  let itemIndex = 0;
  while (existingIndices.includes(itemIndex)) {
    itemIndex++;
  }

  // Get the select options from the first item to copy them
  const firstSelect = document.getElementById("existingItem_0");
  let optionsHtml = "";
  if (firstSelect) {
    for (let i = 0; i < firstSelect.options.length; i++) {
      const option = firstSelect.options[i];
      optionsHtml += `<option value="${option.value}" data-name="${
        option.getAttribute("data-name") || ""
      }" data-category="${option.getAttribute("data-category") || ""}">${
        option.textContent
      }</option>`;
    }
  }

  const itemHtml = `
    <div class="item-entry" data-item-index="${itemIndex}">
      <div class="item-header">
        <h3>Item #${existingItems.length + 1}</h3>
        <span class="remove-item-btn" onclick="removeItemEntry(${itemIndex})">&times;</span>
      </div>
      
      <div class="order-section">
        <h4>Item Information</h4>
        <div class="input-group">
          <label for="existingItem_${itemIndex}">Select Item:</label>
          <select id="existingItem_${itemIndex}" name="items[${itemIndex}][existingItem]" class="item-size-select" required>
            ${optionsHtml}
          </select>
        </div>
      </div>

      <div class="size-selection-section" id="sizeSelectionSection_${itemIndex}" style="display: none;">
        <h4>Size Selection</h4>
        <div class="input-group">
          <label>Select Available Sizes to Add:</label>
          <div class="size-checkboxes" id="sizeCheckboxesContainer_${itemIndex}">
            <label class="checkbox-label"><input type="checkbox" value="XS" onchange="toggleSizeDetailsForItemSize(this, ${itemIndex})"> XS</label>
            <label class="checkbox-label"><input type="checkbox" value="S" onchange="toggleSizeDetailsForItemSize(this, ${itemIndex})"> S</label>
            <label class="checkbox-label"><input type="checkbox" value="M" onchange="toggleSizeDetailsForItemSize(this, ${itemIndex})"> M</label>
            <label class="checkbox-label"><input type="checkbox" value="L" onchange="toggleSizeDetailsForItemSize(this, ${itemIndex})"> L</label>
            <label class="checkbox-label"><input type="checkbox" value="XL" onchange="toggleSizeDetailsForItemSize(this, ${itemIndex})"> XL</label>
            <label class="checkbox-label"><input type="checkbox" value="XXL" onchange="toggleSizeDetailsForItemSize(this, ${itemIndex})"> XXL</label>
            <label class="checkbox-label"><input type="checkbox" value="3XL" onchange="toggleSizeDetailsForItemSize(this, ${itemIndex})"> 3XL</label>
            <label class="checkbox-label"><input type="checkbox" value="4XL" onchange="toggleSizeDetailsForItemSize(this, ${itemIndex})"> 4XL</label>
            <label class="checkbox-label"><input type="checkbox" value="5XL" onchange="toggleSizeDetailsForItemSize(this, ${itemIndex})"> 5XL</label>
            <label class="checkbox-label"><input type="checkbox" value="6XL" onchange="toggleSizeDetailsForItemSize(this, ${itemIndex})"> 6XL</label>
            <label class="checkbox-label"><input type="checkbox" value="7XL" onchange="toggleSizeDetailsForItemSize(this, ${itemIndex})"> 7XL</label>
            <label class="checkbox-label"><input type="checkbox" value="One Size" onchange="toggleSizeDetailsForItemSize(this, ${itemIndex})"> One Size</label>
          </div>
        </div>
      </div>

      <div id="itemSizeDetailsContainer_${itemIndex}" class="size-details-container" style="display: none;">
        <h4>Size Details</h4>
        <div id="itemSizeDetailsList_${itemIndex}">
          <!-- Size detail entries will be inserted here dynamically -->
        </div>
      </div>
    </div>
  `;

  itemsContainer.insertAdjacentHTML("beforeend", itemHtml);

  // Show remove buttons for all items
  updateRemoveButtons();

  // Initialize inventoryUsedSizes for this item
  inventoryUsedSizes[itemIndex] = [];

  // Initialize Select2 for the newly added select element
  setTimeout(() => {
    if (typeof initializeItemSizeSelect2 === "function") {
      initializeItemSizeSelect2();
    }
    // Update all dropdowns to reflect current selections
    updateAllDropdowns();
  }, 100);
}

function removeItemEntry(itemIndex) {
  const itemEntry = document.querySelector(`[data-item-index="${itemIndex}"]`);
  if (itemEntry) {
    // Remove the selected item from tracking
    if (selectedItems[itemIndex]) {
      delete selectedItems[itemIndex];
    }

    // Destroy Select2 instance before removing the element
    const select = itemEntry.querySelector(`#existingItem_${itemIndex}`);
    if (select && $(select).hasClass("select2-hidden-accessible")) {
      $(select).select2("destroy");
    }

    itemEntry.remove();

    // Clean up the inventoryUsedSizes for this item
    delete inventoryUsedSizes[itemIndex];

    // Update all dropdowns to reflect the removed selection
    updateAllDropdowns();

    // Update item numbers and remove buttons
    updateItemNumbers();
    updateRemoveButtons();
  }
}

function updateRemoveButtons() {
  const itemEntries = document.querySelectorAll(".item-entry");
  const removeButtons = document.querySelectorAll(".remove-item-btn");

  removeButtons.forEach((button, index) => {
    button.style.display = itemEntries.length > 1 ? "block" : "none";
  });
}

function updateItemNumbers() {
  const itemEntries = document.querySelectorAll(".item-entry");
  itemEntries.forEach((entry, index) => {
    const header = entry.querySelector(".item-header h3");
    if (header) {
      header.textContent = `Item #${index + 1}`;
    }
  });
}

function updateAllDropdowns() {
  const itemEntries = document.querySelectorAll(".item-entry");

  itemEntries.forEach((entry) => {
    const itemIndex = parseInt(entry.dataset.itemIndex);
    const select = document.getElementById(`existingItem_${itemIndex}`);

    if (select && $(select).hasClass("select2-hidden-accessible")) {
      const currentValue = select.value;
      const $select = $(select);

      // Get all original options
      const allOptions = Array.from(select.querySelectorAll("option")).map(
        (option) => ({
          id: option.value,
          text: option.textContent,
          value: option.value,
          selected: option.selected,
          disabled: false,
        })
      );

      // Filter out selected items from other entries
      const availableOptions = allOptions.filter((option) => {
        if (option.value === "") return true; // Keep empty option
        if (option.value === currentValue) return true; // Keep current selection
        return !Object.values(selectedItems).includes(option.value); // Remove items selected in other entries
      });

      // Clear and repopulate Select2
      $select.empty();
      availableOptions.forEach((option) => {
        const newOption = new Option(
          option.text,
          option.value,
          option.selected,
          option.selected
        );
        $select.append(newOption);
      });

      // Refresh Select2 to show updated options
      $select.trigger("change.select2");
    } else if (select) {
      // Fallback for regular select elements
      const currentValue = select.value;
      const options = select.querySelectorAll("option");

      options.forEach((option) => {
        const optionValue = option.value;

        if (optionValue === "") {
          // Keep empty option always available
          option.style.display = "";
          option.disabled = false;
        } else if (optionValue === currentValue) {
          // Keep current selection visible and enabled
          option.style.display = "";
          option.disabled = false;
        } else if (Object.values(selectedItems).includes(optionValue)) {
          // Hide/disable options that are selected in other entries
          option.style.display = "none";
          option.disabled = true;
        } else {
          // Show available options
          option.style.display = "";
          option.disabled = false;
        }
      });
    }
  });
}

// Bind to item select change
document.addEventListener("DOMContentLoaded", function () {
  // Initial setup for the first item
  updateRemoveButtons();
});

function submitNewItemSize(event) {
  event.preventDefault();
  event.stopPropagation();

  // Validate delivery order
  const deliveryOrder = document.getElementById("deliveryOrderNumberSize");
  if (!deliveryOrder.value.trim()) {
    showErrorMessage("Please enter a delivery order number");
    return false;
  }

  // Show loading state on button
  const form = document.getElementById("addItemSizeForm");
  const submitButton =
    form.querySelector('button[type="submit"]') ||
    document.querySelector('button[form="addItemSizeForm"]');
  const originalButtonText = submitButton ? submitButton.innerHTML : "";
  if (submitButton) {
    submitButton.innerHTML = "Adding Sizes...";
    submitButton.disabled = true;
    submitButton.style.cursor = "not-allowed";
    submitButton.style.opacity = "0.7";
  }

  // Get all item entries
  const itemEntries = document.querySelectorAll(".item-entry");
  if (itemEntries.length === 0) {
    showErrorMessage("Please add at least one item");
    // Restore button state
    if (submitButton) {
      submitButton.innerHTML = originalButtonText;
      submitButton.disabled = false;
      submitButton.style.cursor = "pointer";
      submitButton.style.opacity = "1";
    }
    return false;
  }

  // Validate each item and collect data
  const itemsData = [];
  let isValid = true;
  const invalidFields = [];

  itemEntries.forEach((itemEntry, itemIndex) => {
    const actualItemIndex = parseInt(itemEntry.dataset.itemIndex);
    const existingItem = document.getElementById(
      `existingItem_${actualItemIndex}`
    );

    if (!existingItem || !existingItem.value) {
      isValid = false;
      invalidFields.push(`Item #${itemIndex + 1}: Please select an item`);
      return;
    }

    const sizeEntries = itemEntry.querySelectorAll(".size-detail-entry");
    if (sizeEntries.length === 0) {
      isValid = false;
      invalidFields.push(
        `Item #${itemIndex + 1}: Please select at least one size`
      );
      return;
    }

    const itemData = {
      existingItem: existingItem.value,
      sizes: [],
    };

    // Validate all size entries for this item
    sizeEntries.forEach((sizeEntry) => {
      const size = sizeEntry.dataset.size;
      const itemCodeInput = sizeEntry.querySelector(
        `input[name="items[${actualItemIndex}][itemCodes][]"]`
      );
      const quantityInput = sizeEntry.querySelector(
        `input[name="items[${actualItemIndex}][quantities][]"]`
      );
      const damageInput = sizeEntry.querySelector(
        `input[name="items[${actualItemIndex}][damages][]"]`
      );
      const priceInput = sizeEntry.querySelector(
        `input[name="items[${actualItemIndex}][prices][]"]`
      );

      const quantity = quantityInput ? quantityInput.value : "";
      const price = priceInput ? priceInput.value : "";

      if (!quantity || parseInt(quantity) < 1) {
        isValid = false;
        invalidFields.push(
          `Item #${
            itemIndex + 1
          }, Size ${size}: Initial stock must be at least 1`
        );
      }

      if (!price || parseFloat(price) <= 0) {
        isValid = false;
        invalidFields.push(
          `Item #${itemIndex + 1}, Size ${size}: Price must be greater than 0`
        );
      }

      if (itemCodeInput && quantityInput && priceInput) {
        itemData.sizes.push({
          size: size,
          itemCode: itemCodeInput.value,
          quantity: quantity,
          damage: damageInput ? damageInput.value || "0" : "0",
          price: price,
        });
      }
    });

    itemsData.push(itemData);
  });

  if (!isValid) {
    showErrorMessage(
      "Please fix the following issues:\n\n" + invalidFields.join("\n")
    );
    // Restore button state
    if (submitButton) {
      submitButton.innerHTML = originalButtonText;
      submitButton.disabled = false;
      submitButton.style.cursor = "pointer";
      submitButton.style.opacity = "1";
    }
    return false;
  }

  // Create form data
  const formData = new FormData();
  formData.append("deliveryOrderNumber", deliveryOrder.value.trim());
  formData.append("itemsData", JSON.stringify(itemsData));

  // Send the form data
  const xhr = new XMLHttpRequest();
  xhr.open("POST", "../PAMO Inventory backend/process_add_item_size.php", true);
  xhr.setRequestHeader("X-Requested-With", "XMLHttpRequest");

  xhr.onreadystatechange = function () {
    if (xhr.readyState === 4) {
      // Restore button state
      if (submitButton) {
        submitButton.innerHTML = originalButtonText;
        submitButton.disabled = false;
        submitButton.style.cursor = "pointer";
        submitButton.style.opacity = "1";
      }

      if (xhr.status === 200) {
        try {
          const response = JSON.parse(xhr.responseText);
          if (response.success) {
            // Reset the form
            form.reset();

            // Reset to single entry
            const itemsContainer = document.getElementById("itemsContainer");
            const itemEntries = itemsContainer.querySelectorAll(".item-entry");
            for (let i = 1; i < itemEntries.length; i++) {
              itemEntries[i].remove();
            }
            resetItemEntry(0);

            // Close the modal
            closeModal("addItemSizeModal");

            // Show success message
            showSuccessMessage(
              "New sizes added successfully! Refreshing inventory..."
            );

            // Refresh the inventory table without reloading the page
            setTimeout(() => {
              if (typeof refreshInventoryTable === "function") {
                refreshInventoryTable();
              } else {
                // Fallback to page reload if function doesn't exist
                location.reload();
              }
            }, 800);
          } else {
            showErrorMessage("Error: " + response.message);
          }
        } catch (e) {
          console.error("Parse error:", e);
          showErrorMessage("Error processing response: " + xhr.responseText);
        }
      } else {
        showErrorMessage("Error: " + xhr.statusText);
      }
    }
  };

  xhr.send(formData);
  return false;
}
