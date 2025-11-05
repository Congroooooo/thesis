/**
 * Generate Walk-in Payable Slip
 * Creates a walk-in order in the orders system with auto-approved status
 * Customer receives slip, pays at cashier, then PAMO marks as completed in orders.php
 */

let payableSlipProductCounter = 1;

/**
 * Define the standard size order for consistent display
 * This ensures sizes are always displayed in the same order regardless of selection sequence
 */
const SIZE_ORDER = [
  "One Size",
  "XS",
  "S",
  "M",
  "L",
  "XL",
  "XXL",
  "2XL",
  "3XL",
  "4XL",
  "5XL",
  "6XL",
  "7XL",
];

/**
 * Track processing state for each item to prevent duplicate entries
 * Key: itemIndex, Value: boolean (true if processing)
 */
const sizeSelectionProcessing = {};

/**
 * Track pending size selection requests with debouncing
 * Key: itemIndex, Value: timeout ID
 */
const sizeSelectionTimeouts = {};

/**
 * Track if a re-process is needed after current processing completes
 * Key: itemIndex, Value: boolean (true if needs reprocessing)
 */
const sizeSelectionPending = {};

/**
 * Get the sort index for a size
 * @param {string} size - The size name
 * @returns {number} - The index for sorting (lower = earlier in order)
 */
function getSizeOrderIndex(size) {
  const index = SIZE_ORDER.indexOf(size);
  return index !== -1 ? index : 999; // Unknown sizes go to the end
}

function showGeneratePayableSlipModal() {
  document.getElementById("generatePayableSlipModal").style.display = "block";
  document.getElementById("generatePayableSlipForm").reset();

  // Reset counter
  payableSlipProductCounter = 1;

  // Clear all processing states and timeouts
  Object.keys(sizeSelectionProcessing).forEach((key) => {
    sizeSelectionProcessing[key] = false;
  });
  Object.keys(sizeSelectionTimeouts).forEach((key) => {
    clearTimeout(sizeSelectionTimeouts[key]);
    delete sizeSelectionTimeouts[key];
  });
  Object.keys(sizeSelectionPending).forEach((key) => {
    sizeSelectionPending[key] = false;
  });

  // Reset to single product entry
  const productsContainer = document.getElementById("payableSlipProducts");
  if (productsContainer) {
    const containers = productsContainer.querySelectorAll(
      ".payable-slip-item-container"
    );
    // Remove all except the first one
    for (let i = 1; i < containers.length; i++) {
      containers[i].remove();
    }

    // Reset the first container
    if (containers.length > 0) {
      resetPayableSlipItemContainer(0);
    }
  }

  // Reset customer name dropdown
  const payableCustomerNameSelect = document.getElementById(
    "payableCustomerName"
  );
  if (payableCustomerNameSelect) {
    payableCustomerNameSelect.innerHTML =
      '<option value="">Select role first</option>';
    if (window.jQuery && $(payableCustomerNameSelect).data("select2")) {
      $(payableCustomerNameSelect).val(null).trigger("change");
      $(payableCustomerNameSelect)
        .prop("disabled", true)
        .trigger("change.select2");
    } else {
      payableCustomerNameSelect.disabled = true;
    }
  }

  // Clear ID number field
  document.getElementById("payableCustomerIdNumber").value = "";

  // Calculate total on any change
  calculatePayableSlipTotalAmount();
}

function resetPayableSlipItemContainer(itemIndex) {
  // Clear any processing state and pending timeouts
  if (sizeSelectionTimeouts[itemIndex]) {
    clearTimeout(sizeSelectionTimeouts[itemIndex]);
    delete sizeSelectionTimeouts[itemIndex];
  }
  sizeSelectionProcessing[itemIndex] = false;
  sizeSelectionPending[itemIndex] = false;

  const productSelect = document.getElementById(
    `payableSlipProductId_${itemIndex}`
  );
  const sizeSelectionSection = document.getElementById(
    `payableSlipSizeSelectionSection_${itemIndex}`
  );
  const sizeDetailsContainer = document.getElementById(
    `payableSlipSizeDetailsContainer_${itemIndex}`
  );
  const sizeDetailsList = document.getElementById(
    `payableSlipSizeDetailsList_${itemIndex}`
  );

  if (productSelect) {
    if (window.jQuery && $(productSelect).data("select2")) {
      // Don't trigger change to avoid infinite loop
      $(productSelect).val(null);
      // Just update the UI without triggering events
      $(productSelect).trigger("change.select2");
    } else {
      productSelect.value = "";
    }
  }

  if (sizeSelectionSection) sizeSelectionSection.style.display = "none";
  if (sizeDetailsContainer) sizeDetailsContainer.style.display = "none";
  if (sizeDetailsList) sizeDetailsList.innerHTML = "";

  const checkboxes = document.querySelectorAll(
    `#payableSlipSizeCheckboxesContainer_${itemIndex} input[type="checkbox"]`
  );
  checkboxes.forEach((cb) => (cb.checked = false));
}

function handlePayableSlipProductChange(itemIndex) {
  const productSelect = document.getElementById(
    `payableSlipProductId_${itemIndex}`
  );
  const prefix = productSelect.value;

  if (!prefix) {
    resetPayableSlipItemContainer(itemIndex);
    return;
  }

  const sizeSelectionSection = document.getElementById(
    `payableSlipSizeSelectionSection_${itemIndex}`
  );
  const sizeCheckboxesContainer = document.getElementById(
    `payableSlipSizeCheckboxesContainer_${itemIndex}`
  );
  const sizeDetailsContainer = document.getElementById(
    `payableSlipSizeDetailsContainer_${itemIndex}`
  );
  const sizeDetailsList = document.getElementById(
    `payableSlipSizeDetailsList_${itemIndex}`
  );

  sizeSelectionSection.style.display = "block";
  sizeCheckboxesContainer.innerHTML =
    '<div style="padding:10px;text-align:center;">Loading sizes...</div>';
  sizeDetailsContainer.style.display = "none";
  sizeDetailsList.innerHTML = "";

  // Fetch available sizes
  fetch(
    `../PAMO Inventory backend/get_product_sizes.php?prefix=${encodeURIComponent(
      prefix
    )}`
  )
    .then((response) => response.json())
    .then((data) => {
      if (
        data.success &&
        data.available_sizes &&
        data.available_sizes.length > 0
      ) {
        sizeCheckboxesContainer.innerHTML = "";

        data.available_sizes.forEach((sizeData) => {
          const checkbox = document.createElement("label");
          checkbox.className = "checkbox-label";
          checkbox.innerHTML = `
            <input type="checkbox" 
                   value="${sizeData.size}" 
                   data-item-code="${sizeData.item_code}"
                   data-quantity="${sizeData.quantity}"
                   data-category="${sizeData.category}"
                   onchange="handlePayableSlipSizeSelection(${itemIndex})">
            <span>${sizeData.size} (${sizeData.quantity} in stock)</span>
          `;
          sizeCheckboxesContainer.appendChild(checkbox);
        });

        sizeDetailsContainer.style.display = "none";
        sizeDetailsList.innerHTML = "";
      } else {
        sizeCheckboxesContainer.innerHTML =
          '<div style="padding:10px;color:red;">No sizes available</div>';
      }
    })
    .catch((error) => {
      console.error("Error:", error);
      sizeCheckboxesContainer.innerHTML =
        '<div style="padding:10px;color:red;">Error loading sizes</div>';
    });
}

function handlePayableSlipSizeSelection(itemIndex) {
  // If currently processing, mark that we need to reprocess after completion
  if (sizeSelectionProcessing[itemIndex]) {
    sizeSelectionPending[itemIndex] = true;
    return;
  }

  // Clear any pending timeout for this item
  if (sizeSelectionTimeouts[itemIndex]) {
    clearTimeout(sizeSelectionTimeouts[itemIndex]);
  }

  // Use a shorter debounce (50ms) just to batch rapid successive clicks
  // but not so long that it feels unresponsive
  sizeSelectionTimeouts[itemIndex] = setTimeout(() => {
    processPayableSlipSizeSelection(itemIndex);
  }, 50); // 50ms debounce - enough to batch clicks but feels instant
}

/**
 * Process the size selection after debouncing
 * This is the actual logic that was previously in handlePayableSlipSizeSelection
 */
function processPayableSlipSizeSelection(itemIndex) {
  // If already processing, skip this request
  if (sizeSelectionProcessing[itemIndex]) {
    console.log(
      `Size selection for item ${itemIndex} is already processing, skipping...`
    );
    return;
  }

  // Mark as processing
  sizeSelectionProcessing[itemIndex] = true;

  const sizeCheckboxes = document.querySelectorAll(
    `#payableSlipSizeCheckboxesContainer_${itemIndex} input[type="checkbox"]`
  );
  const sizeDetailsContainer = document.getElementById(
    `payableSlipSizeDetailsContainer_${itemIndex}`
  );
  const sizeDetailsList = document.getElementById(
    `payableSlipSizeDetailsList_${itemIndex}`
  );

  // Get currently checked sizes
  const checkedSizes = new Set();
  sizeCheckboxes.forEach((checkbox) => {
    if (checkbox.checked) {
      checkedSizes.add(checkbox.dataset.itemCode);
    }
  });

  // If no sizes selected, hide container and clear list
  if (checkedSizes.size === 0) {
    sizeDetailsContainer.style.display = "none";
    sizeDetailsList.innerHTML = "";
    calculatePayableSlipTotalAmount();
    // Release processing lock
    sizeSelectionProcessing[itemIndex] = false;
    return;
  }

  // Show container if hidden
  if (sizeDetailsContainer.style.display === "none") {
    sizeDetailsContainer.style.display = "block";
  }

  // Get existing size detail rows
  const existingRows = sizeDetailsList.querySelectorAll(".size-detail-row");
  const existingSizes = new Set();

  existingRows.forEach((row) => {
    const itemCode = row.querySelector(".payable-item-code").value;
    existingSizes.add(itemCode);

    // Remove rows for unchecked sizes
    if (!checkedSizes.has(itemCode)) {
      row.remove();
    }
  });

  // Add rows for newly checked sizes
  const promises = [];
  sizeCheckboxes.forEach((checkbox) => {
    if (!checkbox.checked) return;

    const itemCode = checkbox.dataset.itemCode;

    // Skip if already exists (crucial for preventing duplicates)
    if (existingSizes.has(itemCode)) return;

    // Double-check: verify this item code isn't already in the DOM
    const existingRowCheck = sizeDetailsList.querySelector(
      `.payable-item-code[value="${itemCode}"]`
    );
    if (existingRowCheck) {
      console.log(`Item code ${itemCode} already exists in DOM, skipping...`);
      return;
    }

    const size = checkbox.value;
    const maxQty = parseInt(checkbox.dataset.quantity);
    const category = checkbox.dataset.category;

    const promise = fetch(
      `../PAMO Inventory backend/get_item_price_by_code.php?item_code=${encodeURIComponent(
        itemCode
      )}`
    )
      .then((response) => response.json())
      .then((data) => {
        if (data.success && data.price) {
          // Final check before adding to DOM (in case of race condition)
          const finalCheck = sizeDetailsList.querySelector(
            `.payable-item-code[value="${itemCode}"]`
          );
          if (finalCheck) {
            console.log(`Race condition detected for ${itemCode}, skipping...`);
            return;
          }

          const detailDiv = document.createElement("div");
          detailDiv.className = "size-detail-row";
          detailDiv.setAttribute("data-item-code", itemCode); // Add data attribute for easier tracking
          detailDiv.innerHTML = `
            <div>
              <strong>${size}</strong>
              <input type="hidden" class="payable-item-code" value="${itemCode}">
              <input type="hidden" class="payable-item-name" value="${
                data.item_name || ""
              }">
              <input type="hidden" class="payable-item-category" value="${category}">
              <input type="hidden" class="payable-item-size" value="${size}">
            </div>
            <div>
              <label>Quantity:</label>
              <input type="number" class="payable-item-quantity" min="1" max="${maxQty}" value="1" 
                     onchange="calculatePayableSlipTotalAmount()" style="width:60px;">
            </div>
            <div>
              <label>Price:</label>
              <input type="number" class="payable-item-price" value="${
                data.price
              }" step="0.01" readonly style="width:100px;">
            </div>
            <div>
              <label>Subtotal:</label>
              <input type="number" class="payable-item-subtotal" value="${
                data.price
              }" readonly style="width:100px;">
            </div>
          `;
          sizeDetailsList.appendChild(detailDiv);
        }
      })
      .catch((error) => console.error("Error fetching price:", error));

    promises.push(promise);
  });

  // Recalculate total after all new rows are added or if only removing
  if (promises.length > 0) {
    Promise.all(promises)
      .then(() => {
        sortSizeDetailRows(itemIndex);
        calculatePayableSlipTotalAmount();
        // Release processing lock after completion
        sizeSelectionProcessing[itemIndex] = false;

        // Check if there's a pending request that came in while processing
        if (sizeSelectionPending[itemIndex]) {
          sizeSelectionPending[itemIndex] = false;
          // Reprocess immediately to catch any changes made during processing
          setTimeout(() => processPayableSlipSizeSelection(itemIndex), 0);
        }
      })
      .catch((error) => {
        console.error("Error in size selection processing:", error);
        // Release processing lock even on error
        sizeSelectionProcessing[itemIndex] = false;

        // Check if there's a pending request even on error
        if (sizeSelectionPending[itemIndex]) {
          sizeSelectionPending[itemIndex] = false;
          setTimeout(() => processPayableSlipSizeSelection(itemIndex), 0);
        }
      });
  } else {
    sortSizeDetailRows(itemIndex);
    calculatePayableSlipTotalAmount();
    // Release processing lock
    sizeSelectionProcessing[itemIndex] = false;

    // Check if there's a pending request
    if (sizeSelectionPending[itemIndex]) {
      sizeSelectionPending[itemIndex] = false;
      setTimeout(() => processPayableSlipSizeSelection(itemIndex), 0);
    }
  }
}

/**
 * Sort size detail rows according to the standard SIZE_ORDER
 * @param {number} itemIndex - The item container index
 */
function sortSizeDetailRows(itemIndex) {
  const sizeDetailsList = document.getElementById(
    `payableSlipSizeDetailsList_${itemIndex}`
  );

  if (!sizeDetailsList) return;

  const rows = Array.from(sizeDetailsList.querySelectorAll(".size-detail-row"));

  // Sort rows based on size order
  rows.sort((a, b) => {
    const sizeA = a.querySelector(".payable-item-size").value;
    const sizeB = b.querySelector(".payable-item-size").value;

    const indexA = getSizeOrderIndex(sizeA);
    const indexB = getSizeOrderIndex(sizeB);

    return indexA - indexB;
  });

  // Clear and re-append in sorted order
  sizeDetailsList.innerHTML = "";
  rows.forEach((row) => sizeDetailsList.appendChild(row));
}

function calculatePayableSlipTotalAmount() {
  let total = 0;

  document.querySelectorAll(".size-detail-row").forEach((row) => {
    const qty =
      parseFloat(row.querySelector(".payable-item-quantity").value) || 0;
    const price =
      parseFloat(row.querySelector(".payable-item-price").value) || 0;
    const subtotal = qty * price;

    row.querySelector(".payable-item-subtotal").value = subtotal.toFixed(2);
    total += subtotal;
  });

  document.getElementById("payableSlipTotalAmount").value = total.toFixed(2);
}

function addAnotherPayableSlipProduct() {
  const container = document.getElementById("payableSlipProducts");
  const newItemIndex = payableSlipProductCounter++;

  const newItem = document.createElement("div");
  newItem.className = "payable-slip-item-container";
  newItem.setAttribute("data-item-index", newItemIndex);
  newItem.innerHTML = `
    <div class="payable-slip-item-header">
      <h4>Product Entry ${newItemIndex + 1}</h4>
      <div class="remove-payable-slip-product-btn" onclick="removePayableSlipProduct(this)">&times;</div>
    </div>
    
    <div class="payable-slip-product-selection">
      <div class="input-group">
        <label for="payableSlipProductId_${newItemIndex}">Product:</label>
        <select id="payableSlipProductId_${newItemIndex}" 
                name="payableProductId[]"
                class="payable-slip-product-select" 
                required>
          <option value="">Select Product</option>
        </select>
      </div>
    </div>
    
    <div id="payableSlipSizeSelectionSection_${newItemIndex}" class="payable-slip-size-selection-section" style="display: none;">
      <h4>Select Sizes</h4>
      <div class="input-group">
        <label>Available Sizes:</label>
        <div id="payableSlipSizeCheckboxesContainer_${newItemIndex}" class="size-checkboxes"></div>
      </div>
    </div>
    
    <div id="payableSlipSizeDetailsContainer_${newItemIndex}" class="payable-slip-size-details-container" style="display: none;">
      <h4>Size Details</h4>
      <div id="payableSlipSizeDetailsList_${newItemIndex}"></div>
    </div>
  `;

  container.appendChild(newItem);

  // Load product options and bind events
  loadPayableSlipProductOptions(newItemIndex);

  // Bind change event after Select2 initialization
  setTimeout(() => {
    const newProductSelect = document.getElementById(
      `payableSlipProductId_${newItemIndex}`
    );
    if (newProductSelect && window.jQuery) {
      $(newProductSelect).on("change", function () {
        handlePayableSlipProductChange(newItemIndex);
      });
    }
  }, 100);

  // Renumber all product entries after adding
  renumberPayableSlipProducts();
}

function removePayableSlipProduct(button) {
  button.closest(".payable-slip-item-container").remove();
  calculatePayableSlipTotalAmount();

  // Renumber all remaining product entries
  renumberPayableSlipProducts();
}

/**
 * Renumber all product entries based on their current position
 * This ensures consistent numbering after additions or removals
 */
function renumberPayableSlipProducts() {
  const container = document.getElementById("payableSlipProducts");
  if (!container) return;

  const allContainers = container.querySelectorAll(
    ".payable-slip-item-container"
  );

  allContainers.forEach((itemContainer, index) => {
    const header = itemContainer.querySelector(".payable-slip-item-header h4");
    if (header) {
      header.textContent = `Product Entry ${index + 1}`;
    }

    // Show/hide remove button - first entry should never have visible remove button
    const removeBtn = itemContainer.querySelector(
      ".remove-payable-slip-product-btn"
    );
    if (removeBtn) {
      removeBtn.style.display = index === 0 ? "none" : "block";
    }
  });
}

function loadPayableSlipProductOptions(itemIndex) {
  const selectElement = document.getElementById(
    `payableSlipProductId_${itemIndex}`
  );

  if (!selectElement) return;

  // Clear existing options except the first "Select Product" option
  // This prevents duplicates if function is called multiple times
  selectElement.innerHTML = '<option value="">Select Product</option>';

  fetch("../PAMO Inventory backend/get_unique_products.php")
    .then((response) => response.json())
    .then((data) => {
      if (data.success && data.products) {
        data.products.forEach((product) => {
          const option = document.createElement("option");
          option.value = product.prefix;
          option.textContent = `${product.name} (${product.prefix})`;
          option.setAttribute("data-category", product.category);
          selectElement.appendChild(option);
        });

        if (window.jQuery) {
          $(selectElement).select2({
            dropdownParent: $("#generatePayableSlipModal"),
            width: "100%",
          });
        }
      }
    })
    .catch((error) => console.error("Error loading products:", error));
}

function submitGeneratePayableSlip(event) {
  event.preventDefault();

  const roleCategory = document.getElementById("payableRoleCategory").value;
  const customerNameSelect = document.getElementById("payableCustomerName");
  const customerIdNumber = document
    .getElementById("payableCustomerIdNumber")
    .value.trim();
  const totalAmount =
    parseFloat(document.getElementById("payableSlipTotalAmount").value) || 0;

  // Get customer name from selected option
  let customerName = "";
  if (customerNameSelect.selectedIndex >= 0) {
    const selectedOption =
      customerNameSelect.options[customerNameSelect.selectedIndex];
    customerName =
      selectedOption.getAttribute("data-name") || selectedOption.text;
  }

  // Validation
  if (!roleCategory || !customerName || !customerIdNumber) {
    alert("Please fill in all required fields");
    return;
  }

  if (totalAmount <= 0) {
    alert("Please add at least one item");
    return;
  }

  // Collect items
  const items = [];
  document.querySelectorAll(".size-detail-row").forEach((row) => {
    const itemCode = row.querySelector(".payable-item-code").value;
    const itemName = row.querySelector(".payable-item-name").value;
    const category = row.querySelector(".payable-item-category").value;
    const size = row.querySelector(".payable-item-size").value;
    const quantity = parseInt(
      row.querySelector(".payable-item-quantity").value
    );
    const price = parseFloat(row.querySelector(".payable-item-price").value);
    const subtotal = parseFloat(
      row.querySelector(".payable-item-subtotal").value
    );

    items.push({
      item_code: itemCode,
      item_name: itemName,
      category: category,
      size: size,
      quantity: quantity,
      price: price,
      subtotal: subtotal,
    });
  });

  if (items.length === 0) {
    alert("Please select at least one product with size and quantity");
    return;
  }

  // Prepare form data
  const formData = new FormData();
  formData.append("roleCategory", roleCategory);
  formData.append("customerName", customerName);
  formData.append("customerIdNumber", customerIdNumber);
  formData.append("items", JSON.stringify(items));
  formData.append("totalAmount", totalAmount);

  // Show loading - find the submit button in the modal footer
  const modal = document.getElementById("generatePayableSlipModal");
  const submitBtn = modal ? modal.querySelector('button[type="submit"]') : null;
  let originalText = "Generate & Print Slip";

  if (submitBtn) {
    originalText = submitBtn.textContent;
    submitBtn.disabled = true;
    submitBtn.textContent = "Generating...";
  }

  // Submit to backend
  fetch("../PAMO Inventory backend/generate_walk_in_payable_slip_v2.php", {
    method: "POST",
    body: formData,
  })
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        // Close form modal
        document.getElementById("generatePayableSlipModal").style.display =
          "none";
        document.getElementById("generatePayableSlipForm").reset();

        // Show preview modal with slip HTML
        showPayableSlipPreview(data.slip_html, data.transaction_number);

        // Show success notification
        if (typeof showNotification === "function") {
          showNotification(
            `Walk-in order created successfully! Order #${data.transaction_number} is now approved and visible in the Orders page. Customer can now proceed to cashier for payment.`,
            "success"
          );
        }

        // Refresh inventory if needed
        if (typeof loadInventoryData === "function") {
          loadInventoryData();
        }
      } else {
        alert("Error: " + (data.message || "Failed to generate payable slip"));
      }
    })
    .catch((error) => {
      console.error("Error:", error);
      alert("An error occurred while generating the payable slip");
    })
    .finally(() => {
      if (submitBtn) {
        submitBtn.disabled = false;
        submitBtn.textContent = originalText;
      }
    });
}

// Show payable slip preview modal
function showPayableSlipPreview(slipHtml, transactionNumber) {
  const previewModal = document.getElementById("payableSlipPreviewModal");
  const previewContent = document.getElementById("payableSlipPreviewContent");

  // Insert the slip HTML
  previewContent.innerHTML = slipHtml;

  // Store transaction number for later use
  previewModal.setAttribute("data-transaction-number", transactionNumber);

  // Show the modal
  previewModal.style.display = "block";
}

// Close payable slip preview modal
function closePayableSlipPreview() {
  const previewModal = document.getElementById("payableSlipPreviewModal");
  previewModal.style.display = "none";

  // Clear content to free memory
  document.getElementById("payableSlipPreviewContent").innerHTML = "";
}

// Print payable slip
function printPayableSlip() {
  const previewContent = document.getElementById("payableSlipPreviewContent");

  if (!previewContent || !previewContent.innerHTML.trim()) {
    alert("No slip content to print");
    return;
  }

  // Create a hidden iframe for printing
  let printFrame = document.getElementById("payableSlipPrintFrame");

  if (!printFrame) {
    printFrame = document.createElement("iframe");
    printFrame.id = "payableSlipPrintFrame";
    printFrame.style.position = "fixed";
    printFrame.style.top = "-9999px";
    printFrame.style.left = "-9999px";
    printFrame.style.width = "0";
    printFrame.style.height = "0";
    document.body.appendChild(printFrame);
  }

  // Get the HTML content
  const slipHtml = previewContent.innerHTML;

  // Write content to iframe
  const iframeDoc = printFrame.contentWindow || printFrame.contentDocument;
  if (iframeDoc.document) {
    iframeDoc.document.open();
    iframeDoc.document.write(slipHtml);
    iframeDoc.document.close();
  }

  // Wait for content to load, then print
  setTimeout(() => {
    try {
      printFrame.contentWindow.focus();
      printFrame.contentWindow.print();

      // Close the modal after print dialog is triggered
      setTimeout(() => {
        closePayableSlipPreview();
      }, 500);
    } catch (e) {
      console.error("Print error:", e);
      alert(
        "Could not print slip. Please try again or use your browser's print function."
      );
    }
  }, 250);
}

// Role-based customer population for walk-in orders
function populatePayableCustomersByRole(role) {
  const nameSelect = document.getElementById("payableCustomerName");
  const idInput = document.getElementById("payableCustomerIdNumber");

  if (!role) {
    nameSelect.innerHTML = '<option value="">Select role first</option>';
    if (window.jQuery && $(nameSelect).data("select2")) {
      $(nameSelect).prop("disabled", true).trigger("change.select2");
    } else {
      nameSelect.disabled = true;
    }
    idInput.value = "";
    return;
  }

  // Show loading state
  nameSelect.innerHTML = '<option value="">Loading...</option>';
  if (window.jQuery && $(nameSelect).data("select2")) {
    $(nameSelect).prop("disabled", true).trigger("change.select2");
  } else {
    nameSelect.disabled = true;
  }

  // Fetch students by role
  fetch(
    `../PAMO Inventory backend/get_students.php?role=${encodeURIComponent(
      role
    )}`
  )
    .then((response) => response.json())
    .then((data) => {
      if (data.success && data.students && data.students.length > 0) {
        nameSelect.innerHTML = '<option value="">Select Name</option>';

        data.students.forEach((student) => {
          const option = document.createElement("option");
          option.value = student.id;
          option.textContent = student.name;
          option.setAttribute("data-name", student.name);
          option.setAttribute("data-id-number", student.id_number);
          nameSelect.appendChild(option);
        });

        if (window.jQuery && $(nameSelect).data("select2")) {
          $(nameSelect).prop("disabled", false).trigger("change.select2");
        } else {
          nameSelect.disabled = false;
        }
      } else {
        console.error(
          "No students found or API error:",
          data.message || "Unknown error"
        );
        nameSelect.innerHTML =
          '<option value="">No students found for this role</option>';
        if (window.jQuery && $(nameSelect).data("select2")) {
          $(nameSelect).prop("disabled", true).trigger("change.select2");
        }
      }
    })
    .catch((error) => {
      console.error("Error loading students:", error);
      nameSelect.innerHTML = '<option value="">Error loading students</option>';
      if (window.jQuery && $(nameSelect).data("select2")) {
        $(nameSelect).prop("disabled", true).trigger("change.select2");
      }
      alert(
        "Error loading students. Please check your connection and try again."
      );
    });
}

// Initialize on page load
document.addEventListener("DOMContentLoaded", function () {
  // NOTE: First product dropdown (index 0) already has options from PHP in inventory.php
  // We only need to initialize Select2 on it, not load products again
  const firstProductSelect = document.getElementById("payableSlipProductId_0");
  if (firstProductSelect && window.jQuery) {
    $(firstProductSelect).select2({
      dropdownParent: $("#generatePayableSlipModal"),
      width: "100%",
    });
  }

  // Initialize role change listener for payable slip
  const payableRoleSelect = document.getElementById("payableRoleCategory");
  if (payableRoleSelect) {
    payableRoleSelect.addEventListener("change", function () {
      populatePayableCustomersByRole(this.value);
    });
  }

  // Initialize Select2 for customer name dropdown
  const payableCustomerNameSelect = document.getElementById(
    "payableCustomerName"
  );
  if (window.jQuery && payableCustomerNameSelect) {
    $(payableCustomerNameSelect).select2({
      placeholder: "Select role first...",
      allowClear: true,
      width: "100%",
      disabled: true,
      dropdownParent: $("#generatePayableSlipModal"),
      matcher: function (params, data) {
        // If there are no search terms, return all data
        if ($.trim(params.term) === "") {
          return data;
        }

        // Only allow letters, numbers, spaces, and basic punctuation (.,-)
        const sanitizedTerm = params.term.replace(/[^a-zA-Z0-9\s.\-]/g, "");

        if (sanitizedTerm === "") {
          return null;
        }

        // Check if the sanitized term matches the text
        if (data.text.toUpperCase().indexOf(sanitizedTerm.toUpperCase()) > -1) {
          return data;
        }

        // Check name and id_number attributes
        if (data.element) {
          const name = $(data.element).attr("data-name") || "";
          const idNumber = $(data.element).attr("data-id-number") || "";

          if (
            name.toUpperCase().indexOf(sanitizedTerm.toUpperCase()) > -1 ||
            idNumber.indexOf(sanitizedTerm) > -1
          ) {
            return data;
          }
        }

        return null;
      },
      templateResult: function (item) {
        // Handle loading state or empty option
        if (item.loading || !item.element || item.id === "") {
          return item.text;
        }

        // Get data from the option element
        const element = item.element;
        const name = element.getAttribute("data-name");
        const idNumber = element.getAttribute("data-id-number");

        if (!name || !idNumber) {
          return item.text;
        }

        // Create enhanced dropdown item display
        var $container = $(
          "<div class='select2-result-student'>" +
            "<div class='student-name'>" +
            $("<div>").text(name).html() +
            "</div>" +
            "<div class='student-id'>" +
            $("<div>").text(idNumber).html() +
            "</div>" +
            "</div>"
        );

        return $container;
      },
      templateSelection: function (item) {
        // Handle default selection
        if (!item.element || item.id === "") {
          return item.text;
        }

        // Display just the name in the selection
        const name = item.element.getAttribute("data-name");
        return name || item.text;
      },
    });

    // Handle selection events for auto-filling ID number
    $(payableCustomerNameSelect).on("select2:select", function (e) {
      const selectedElement = e.params.data.element;
      if (selectedElement) {
        const idNumber = selectedElement.getAttribute("data-id-number");
        if (idNumber) {
          document.getElementById("payableCustomerIdNumber").value = idNumber;
        }
      }
    });

    // Handle clear events
    $(payableCustomerNameSelect).on("select2:clear", function (e) {
      document.getElementById("payableCustomerIdNumber").value = "";
    });
  }

  // Fallback for non-Select2 functionality
  if (payableCustomerNameSelect) {
    payableCustomerNameSelect.addEventListener("change", function () {
      if (!window.jQuery || !$(this).data("select2")) {
        const selectedOption = this.options[this.selectedIndex];
        const idNumber = selectedOption.getAttribute("data-id-number") || "";
        document.getElementById("payableCustomerIdNumber").value = idNumber;
      }
    });
  }

  // Initialize the first product select with Select2 and proper event binding
  const firstPayableProductSelect = document.getElementById(
    "payableSlipProductId_0"
  );
  if (firstPayableProductSelect && window.jQuery) {
    $(firstPayableProductSelect).on("change", function () {
      handlePayableSlipProductChange(0);
    });
  }
});
