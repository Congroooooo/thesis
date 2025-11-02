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

// Sales counter for multiple products
let salesProductCounter = 1;

function showDeductQuantityModal() {
  document.getElementById("deductQuantityModal").style.display = "block";
  document.getElementById("deductQuantityForm").reset();

  // Reset counter
  salesProductCounter = 1;

  // Reset to single product entry
  const salesItems = document.getElementById("salesItems");
  if (salesItems) {
    const containers = salesItems.querySelectorAll(".sales-item-container");
    // Remove all except the first one
    for (let i = 1; i < containers.length; i++) {
      containers[i].remove();
    }

    // Reset the first container
    if (containers.length > 0) {
      resetSalesItemContainer(0);
    }
  }

  const studentNameSelect = document.getElementById("studentName");
  if (studentNameSelect) {
    studentNameSelect.innerHTML = '<option value="">Select Name</option>';
    if (window.jQuery && $(studentNameSelect).data("select2")) {
      $(studentNameSelect).prop("disabled", true).trigger("change.select2");
    } else {
      studentNameSelect.disabled = true;
    }
  }

  document.getElementById("studentIdNumber").value = "";
  document.getElementById("totalAmount").value = "";

  const transactionInput = document.getElementById("transactionNumber");
  if (transactionInput) {
    fetch("../PAMO Inventory backend/get_next_transaction_number.php")
      .then((response) => response.json())
      .then((data) => {
        if (data.success && data.transaction_number) {
          transactionInput.value = data.transaction_number;
        } else {
          transactionInput.value = "";
        }
      })
      .catch(() => {
        transactionInput.value = "";
      });
  }
}

function resetSalesItemContainer(itemIndex) {
  const productSelect = document.getElementById(`salesProductId_${itemIndex}`);
  const sizeSelectionSection = document.getElementById(
    `salesSizeSelectionSection_${itemIndex}`
  );
  const sizeDetailsContainer = document.getElementById(
    `salesSizeDetailsContainer_${itemIndex}`
  );
  const sizeCheckboxesContainer = document.getElementById(
    `salesSizeCheckboxesContainer_${itemIndex}`
  );
  const sizeDetailsList = document.getElementById(
    `salesSizeDetailsList_${itemIndex}`
  );

  if (productSelect) {
    productSelect.value = "";
    if (window.jQuery && $(productSelect).data("select2")) {
      $(productSelect).val(null).trigger("change");
    }
  }

  if (sizeSelectionSection) sizeSelectionSection.style.display = "none";
  if (sizeDetailsContainer) sizeDetailsContainer.style.display = "none";
  if (sizeCheckboxesContainer) sizeCheckboxesContainer.innerHTML = "";
  if (sizeDetailsList) sizeDetailsList.innerHTML = "";
}

function addAnotherSalesProduct() {
  const salesItems = document.getElementById("salesItems");
  const newIndex = salesProductCounter++;

  const newContainer = document.createElement("div");
  newContainer.className = "sales-item-container";
  newContainer.setAttribute("data-item-index", newIndex);

  newContainer.innerHTML = `
    <div class="sales-item-header">
      <h4>Product Entry ${newIndex + 1}</h4>
      <div class="remove-sales-product-btn" onclick="removeSalesProduct(this)">&times;</div>
    </div>
    
    <div class="sales-product-selection">
      <div class="input-group">
        <label for="salesProductId_${newIndex}">Product:</label>
        <select id="salesProductId_${newIndex}" name="productId[]" class="sales-product-select" required>
          <option value="">Select Product</option>
        </select>
      </div>
    </div>

    <div id="salesSizeSelectionSection_${newIndex}" class="sales-size-selection-section" style="display: none;">
      <h4>Select Sizes to Sell</h4>
      <div class="input-group">
        <label>Available Sizes:</label>
        <div id="salesSizeCheckboxesContainer_${newIndex}" class="size-checkboxes">
          <!-- Size checkboxes will be dynamically populated -->
        </div>
      </div>
    </div>

    <div id="salesSizeDetailsContainer_${newIndex}" class="sales-size-details-container" style="display: none;">
      <h4>Size Details</h4>
      <div id="salesSizeDetailsList_${newIndex}">
        <!-- Size detail entries will be dynamically added here -->
      </div>
    </div>
  `;

  salesItems.appendChild(newContainer);

  // Populate product options (copy from first select - get original select element, not Select2)
  const firstProductSelect = document.getElementById("salesProductId_0");
  const newProductSelect = document.getElementById(
    `salesProductId_${newIndex}`
  );

  if (firstProductSelect && newProductSelect) {
    // Clone all options from the original select element
    Array.from(firstProductSelect.options).forEach((option) => {
      const newOption = document.createElement("option");
      newOption.value = option.value;
      newOption.textContent = option.textContent;
      // Copy data attributes if any
      if (option.dataset.category) {
        newOption.dataset.category = option.dataset.category;
      }
      newProductSelect.appendChild(newOption);
    });
  }

  // Initialize Select2 for the new product select
  if (window.jQuery && newProductSelect) {
    $(newProductSelect).select2({
      placeholder: "Select Product",
      allowClear: true,
      width: "100%",
    });

    $(newProductSelect).on("change", function () {
      validateSalesProductSelection(newIndex);
      handleSalesProductChange(newIndex);
      updateAllSalesProductDropdowns();
    });
  } else if (newProductSelect) {
    // Fallback if jQuery/Select2 not available
    newProductSelect.addEventListener("change", function () {
      validateSalesProductSelection(newIndex);
      handleSalesProductChange(newIndex);
      updateAllSalesProductDropdowns();
    });
  }

  // Update all dropdowns to reflect the new entry
  updateAllSalesProductDropdowns();
}

function removeSalesProduct(btn) {
  const container = btn.closest(".sales-item-container");
  if (container) {
    container.remove();
    calculateTotalAmount();

    // Renumber remaining products
    const containers = document.querySelectorAll(".sales-item-container");
    containers.forEach((cont, idx) => {
      const header = cont.querySelector(".sales-item-header h4");
      if (header) {
        header.textContent = `Product Entry ${idx + 1}`;
      }
    });

    // Update all dropdowns after removal
    updateAllSalesProductDropdowns();
  }
}

function validateSalesProductSelection(itemIndex) {
  const currentSelect = document.getElementById(`salesProductId_${itemIndex}`);
  const selectedValue = currentSelect.value;

  if (!selectedValue) return true;

  // Check if this product is already selected in another entry
  const allSelects = document.querySelectorAll(".sales-product-select");
  let isDuplicate = false;

  allSelects.forEach((select) => {
    const selectIndex = select.id.split("_")[1];
    if (
      selectIndex !== itemIndex.toString() &&
      select.value === selectedValue
    ) {
      isDuplicate = true;
    }
  });

  if (isDuplicate) {
    showErrorMessage(
      "This product has already been selected. Please choose a different product."
    );

    // Clear the selection
    if (window.jQuery && $(currentSelect).data("select2")) {
      $(currentSelect).val(null).trigger("change");
    } else {
      currentSelect.value = "";
    }

    // Clear the size sections
    const sizeSelectionSection = document.getElementById(
      `salesSizeSelectionSection_${itemIndex}`
    );
    const sizeDetailsContainer = document.getElementById(
      `salesSizeDetailsContainer_${itemIndex}`
    );
    const sizeCheckboxesContainer = document.getElementById(
      `salesSizeCheckboxesContainer_${itemIndex}`
    );
    const sizeDetailsList = document.getElementById(
      `salesSizeDetailsList_${itemIndex}`
    );

    if (sizeSelectionSection) sizeSelectionSection.style.display = "none";
    if (sizeDetailsContainer) sizeDetailsContainer.style.display = "none";
    if (sizeCheckboxesContainer) sizeCheckboxesContainer.innerHTML = "";
    if (sizeDetailsList) sizeDetailsList.innerHTML = "";

    return false;
  }

  return true;
}

function updateAllSalesProductDropdowns() {
  const allSelects = document.querySelectorAll(".sales-product-select");
  const selectedValues = [];

  // Collect all selected values
  allSelects.forEach((select) => {
    if (select.value) {
      selectedValues.push(select.value);
    }
  });

  // Update each dropdown
  allSelects.forEach((select) => {
    const currentValue = select.value;
    const selectIndex = select.id.split("_")[1];

    // Get all options
    const options = Array.from(select.options);

    // Update each option's disabled state
    options.forEach((option) => {
      if (option.value === "") {
        // Keep placeholder enabled
        option.disabled = false;
      } else if (option.value === currentValue) {
        // Keep current selection enabled
        option.disabled = false;
      } else if (selectedValues.includes(option.value)) {
        // Disable if selected in another dropdown
        option.disabled = true;
      } else {
        // Enable if not selected anywhere
        option.disabled = false;
      }
    });

    // Refresh Select2 if applicable
    if (window.jQuery && $(select).data("select2")) {
      $(select).trigger("change.select2");
    }
  });
}

function handleSalesProductChange(itemIndex) {
  const productSelect = document.getElementById(`salesProductId_${itemIndex}`);
  const prefix = productSelect.value;

  const sizeSelectionSection = document.getElementById(
    `salesSizeSelectionSection_${itemIndex}`
  );
  const sizeCheckboxesContainer = document.getElementById(
    `salesSizeCheckboxesContainer_${itemIndex}`
  );
  const sizeDetailsContainer = document.getElementById(
    `salesSizeDetailsContainer_${itemIndex}`
  );
  const sizeDetailsList = document.getElementById(
    `salesSizeDetailsList_${itemIndex}`
  );

  if (!prefix) {
    if (sizeSelectionSection) sizeSelectionSection.style.display = "none";
    if (sizeDetailsContainer) sizeDetailsContainer.style.display = "none";
    if (sizeCheckboxesContainer) sizeCheckboxesContainer.innerHTML = "";
    if (sizeDetailsList) sizeDetailsList.innerHTML = "";
    return;
  }

  // Show loading state
  sizeCheckboxesContainer.innerHTML = `
    <div style="padding: 15px; text-align: center; color: #666;">
      <i class="material-icons" style="animation: spin 1s linear infinite; font-size: 24px;">sync</i>
      <p style="margin: 5px 0 0 0;">Loading available sizes...</p>
    </div>
  `;
  sizeSelectionSection.style.display = "block";

  // Fetch available sizes for the selected product - OPTIMIZED ENDPOINT
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
        // Display size checkboxes
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
                   onchange="handleSalesSizeSelection(${itemIndex})">
            <span>${sizeData.size} (${sizeData.quantity} in stock)</span>
          `;
          sizeCheckboxesContainer.appendChild(checkbox);
        });

        // Hide size details initially
        sizeDetailsContainer.style.display = "none";
        sizeDetailsList.innerHTML = "";
      } else {
        // No sizes available - show error message
        sizeCheckboxesContainer.innerHTML = `
          <div style="padding: 15px; background: #fff3cd; border: 1px solid #ffc107; border-radius: 6px; color: #856404;">
            <strong>⚠️ No Stock Available</strong>
            <p style="margin: 5px 0 0 0; font-size: 14px;">This product currently has no available sizes in stock. Please select a different product.</p>
          </div>
        `;
        sizeDetailsContainer.style.display = "none";
        sizeDetailsList.innerHTML = "";
      }
    })
    .catch((error) => {
      console.error("Error fetching sizes:", error);
      sizeCheckboxesContainer.innerHTML = `
        <div style="padding: 15px; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 6px; color: #721c24;">
          <strong>❌ Error Loading Sizes</strong>
          <p style="margin: 5px 0 0 0; font-size: 14px;">Failed to load available sizes. Please try again.</p>
        </div>
      `;
    });
}

function handleSalesSizeSelection(itemIndex) {
  const sizeCheckboxesContainer = document.getElementById(
    `salesSizeCheckboxesContainer_${itemIndex}`
  );
  const sizeDetailsContainer = document.getElementById(
    `salesSizeDetailsContainer_${itemIndex}`
  );
  const sizeDetailsList = document.getElementById(
    `salesSizeDetailsList_${itemIndex}`
  );
  const productSelect = document.getElementById(`salesProductId_${itemIndex}`);

  const selectedCheckboxes = sizeCheckboxesContainer.querySelectorAll(
    'input[type="checkbox"]:checked'
  );

  if (selectedCheckboxes.length === 0) {
    sizeDetailsContainer.style.display = "none";
    sizeDetailsList.innerHTML = "";
    return;
  }

  // Get product info for item code generation
  const prefix = productSelect.value;
  const selectedOption = productSelect.options[productSelect.selectedIndex];
  const category = selectedOption.getAttribute("data-category") || "";

  // Save existing values before clearing
  const existingValues = {};
  const existingDetailItems = sizeDetailsList.querySelectorAll(
    ".sales-size-detail-item"
  );
  existingDetailItems.forEach((item) => {
    const itemCodeInput = item.querySelector('input[name="itemCode[]"]');
    const quantityInput = item.querySelector('input[name="quantitySold[]"]');
    const priceInput = item.querySelector('input[name="pricePerItem[]"]');
    const subtotalInput = item.querySelector('input[name="sizeSubtotal[]"]');

    if (itemCodeInput && itemCodeInput.value) {
      existingValues[itemCodeInput.value] = {
        quantity: quantityInput ? quantityInput.value : "",
        price: priceInput ? priceInput.value : "",
        subtotal: subtotalInput ? subtotalInput.value : "",
      };
    }
  });

  // Clear and rebuild size details
  sizeDetailsList.innerHTML = "";

  selectedCheckboxes.forEach((checkbox, index) => {
    const size = checkbox.value;
    const itemCode = checkbox.getAttribute("data-item-code");
    const availableQty = checkbox.getAttribute("data-quantity");

    const sizeDetailItem = document.createElement("div");
    sizeDetailItem.className = "sales-size-detail-item";

    // Get saved values if they exist
    const savedValues = existingValues[itemCode] || {};
    const savedQuantity = savedValues.quantity || "";
    const savedPrice = savedValues.price || "";
    const savedSubtotal = savedValues.subtotal || "";

    sizeDetailItem.innerHTML = `
      <div class="sales-size-detail-header">
        <h5>Size: ${size}</h5>
        <span class="generated-code">${itemCode}</span>
      </div>
      <div class="sales-size-detail-form">
        <input type="hidden" name="itemCode[]" value="${itemCode}">
        <input type="hidden" name="itemCategory[]" value="${category}">
        <div class="input-group">
          <label>Size:</label>
          <input type="text" name="itemSize[]" value="${size}" readonly>
        </div>
        <div class="input-group">
          <label>Quantity Sold:</label>
          <input type="number" name="quantitySold[]" min="1" max="${availableQty}" 
                 value="${savedQuantity}" required onchange="calculateSalesSizeTotal(this)">
        </div>
        <div class="input-group">
          <label>Price per Item:</label>
          <input type="number" name="pricePerItem[]" step="0.01" readonly 
                 value="${savedPrice}" data-item-code="${itemCode}">
        </div>
        <div class="input-group">
          <label>Subtotal:</label>
          <input type="number" name="sizeSubtotal[]" step="0.01" readonly 
                 value="${savedSubtotal}">
        </div>
      </div>
    `;

    sizeDetailsList.appendChild(sizeDetailItem);

    // Fetch price for this size only if we don't have a saved price
    if (!savedPrice) {
      fetchSalesSizePrice(itemCode, sizeDetailItem);
    }
  });

  // Show size details container
  sizeDetailsContainer.style.display = "block";

  // Recalculate total amount to reflect current selections
  calculateTotalAmount();
}

function fetchSalesSizePrice(itemCode, sizeDetailItem) {
  fetch(
    `../PAMO Inventory backend/get_item_price_by_code.php?item_code=${encodeURIComponent(
      itemCode
    )}`
  )
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        const priceInput = sizeDetailItem.querySelector(
          'input[name="pricePerItem[]"]'
        );
        if (priceInput) {
          priceInput.value = data.price;

          // If quantity is already entered, calculate subtotal
          const quantityInput = sizeDetailItem.querySelector(
            'input[name="quantitySold[]"]'
          );
          if (quantityInput && quantityInput.value) {
            calculateSalesSizeTotal(quantityInput);
          }
        }
      }
    })
    .catch((error) => {
      console.error("Error fetching price:", error);
    });
}

function calculateSalesSizeTotal(quantityInput) {
  const sizeDetailItem = quantityInput.closest(".sales-size-detail-item");
  if (!sizeDetailItem) return;

  const quantity = parseFloat(quantityInput.value) || 0;
  const priceInput = sizeDetailItem.querySelector(
    'input[name="pricePerItem[]"]'
  );
  const subtotalInput = sizeDetailItem.querySelector(
    'input[name="sizeSubtotal[]"]'
  );

  const price = parseFloat(priceInput.value) || 0;
  const subtotal = quantity * price;

  subtotalInput.value = subtotal.toFixed(2);

  // Recalculate total amount
  calculateTotalAmount();
}

function calculateTotalAmount() {
  const subtotalInputs = document.querySelectorAll(
    'input[name="sizeSubtotal[]"]'
  );
  let total = 0;

  subtotalInputs.forEach((input) => {
    total += parseFloat(input.value) || 0;
  });

  document.getElementById("totalAmount").value = total.toFixed(2);
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
    const itemContainer = selectElement.closest(".sales-item");
    const priceInput = itemContainer.querySelector(
      'input[name="pricePerItem[]"]'
    );
    const totalInput = itemContainer.querySelector('input[name="itemTotal[]"]');
    if (priceInput) priceInput.value = "";
    if (totalInput) totalInput.value = "";
    calculateTotalAmount();
  }
}

function updateAvailableSizes(itemSelect) {
  const itemContainer = itemSelect ? itemSelect.closest(".sales-item") : null;
  if (!itemContainer) return; // Guard: do not proceed if itemContainer is missing
  const sizeSelect = itemContainer
    ? itemContainer.querySelector('select[name="size[]"]')
    : null;
  if (!sizeSelect) return; // Guard: do not proceed if sizeSelect is missing
  const prefix = itemSelect.value;

  // Clear previous options
  sizeSelect.innerHTML = '<option value="">Select Size</option>';

  if (!prefix) return;

  // Fetch available sizes for the selected product
  fetch(
    `../PAMO Inventory backend/get_unique_products.php?prefix=${encodeURIComponent(
      prefix
    )}`
  )
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        const product = data.products.find((p) => p.prefix === prefix);
        if (product) {
          product.available_sizes.forEach((size) => {
            const option = document.createElement("option");
            option.value = size.size;
            option.textContent = `${size.size} (${size.quantity} in stock)`;
            option.setAttribute("data-quantity", size.quantity);
            option.setAttribute("data-item-code", size.item_code);
            option.setAttribute("data-category", size.category);
            sizeSelect.appendChild(option);
          });
        }
      }
    })
    .catch(() => {
      alert("Error fetching sizes");
    });
}

function showSalesReceipt(formData) {
  // Determine if customer is an employee
  const isEmployee = formData.roleCategory === "EMPLOYEE";
  const customerCopyLabel = isEmployee ? "EMPLOYEE COPY" : "STUDENT COPY";
  const customerNameLabel = isEmployee ? "Employee Name:" : "Student Name:";
  const customerIdLabel = isEmployee ? "Employee No.:" : "Student No.:";

  // Helper for the two copies
  function renderReceipt(copyLabel) {
    // Get the logged-in user's name from the global variable set by PHP
    const preparedByName =
      window.PAMO_USER && window.PAMO_USER.name ? window.PAMO_USER.name : "";
    // Render each item as its own row for proper alignment
    const dataRows = formData.itemNames
      .map((name, i) => {
        // Remove item code in parentheses from the name
        let itemDescription = name.replace(/\s*\([^)]*\)\s*/g, "").trim();
        const size = formData.sizes[i];
        // Always append size if it exists and isn't already appended with " - " format
        if (size && !itemDescription.endsWith(" - " + size)) {
          itemDescription += " - " + size;
        }
        return `<tr>
        <td>${itemDescription}</td>
        <td>${formData.itemCategories[i] || ""}</td>
        <td style="text-align:center;">${formData.quantities[i]}</td>
        <td style="text-align:right;">${parseFloat(formData.prices[i]).toFixed(
          2
        )}</td>
        <td style="text-align:right;">${parseFloat(
          formData.itemTotals[i]
        ).toFixed(2)}</td>
        ${
          i === 0
            ? `<td class="signature-col" rowspan="${formData.itemNames.length}">
          <table class="signature-table">
            <tr><td class="sig-label">Prepared by:</td></tr>
            <tr><td class="sig-box">${preparedByName}</td></tr>
            <tr><td class="sig-label">OR Issued by:</td></tr>
            <tr><td class="sig-box">${formData.cashierName}<br><span style="font-weight:bold;">Cashier</span></td></tr>
            <tr><td class="sig-label">Released by & date:</td></tr>
            <tr><td class="sig-box"></td></tr>
            <tr><td class="sig-label">RECEIVED BY:</td></tr>
            <tr><td class="sig-box" style="height:40px;vertical-align:bottom;">
              <div style="height:24px;"></div>
              <div class="sig-name" style="font-weight:bold;text-decoration:underline;text-align:center;">${formData.studentName}</div>
            </td></tr>
          </table>
        </td>`
            : ""
        }
      </tr>`;
      })
      .join("");

    // Footer row inside the table
    // Calculate total from itemTotals if totalAmount is invalid
    const totalAmount =
      parseFloat(formData.totalAmount) ||
      formData.itemTotals.reduce((sum, total) => sum + parseFloat(total), 0);

    const footerRow = `
      <tr>
        <td colspan="5" style="text-align:left; font-size:0.98em; padding-top:10px;">
          <b>ALL ITEMS ARE RECEIVED IN GOOD CONDITION</b><br>
          <span style="font-size:0.97em;">(Exchange is allowed only within 3 days from the invoice date. Strictly no refund)</span>
        </td>
        <td style="text-align:right; font-size:1.05em; font-weight:bold; padding-top:10px;">
          TOTAL AMOUNT: <span style="min-width:80px;display:inline-block;text-align:right;">${totalAmount.toFixed(
            2
          )}</span>
        </td>
      </tr>
    `;
    return `
      <div class="receipt-header-flex">
        <div class="receipt-header-logo"><img src="../Images/STI-LOGO.png" alt="STI Logo" /></div>
        <div class="receipt-header-center">
          <div class="sti-lucena">STI LUCENA</div>
          <div class="sales-issuance-slip">SALES ISSUANCE SLIP</div>
        </div>
        <div class="receipt-header-copy">${copyLabel}</div>
      </div>
      <div class="receipt-section">
        <table class="receipt-header-table">
          <tr>
            <td style="width:22%;font-size:0.98em;"><b>${customerNameLabel}</b></td>
            <td style="width:22%;border-bottom:1px solid #222;">${
              formData.studentName
            }</td>
            <td style="width:13%;font-size:0.98em;"><b>${customerIdLabel}</b></td>
            <td style="width:15%;border-bottom:1px solid #222;">${
              formData.studentIdNumber
            }</td>
            <td style="width:8%;font-size:0.98em;"><b>DATE:</b></td>
            <td style="width:15%;border-bottom:1px solid #222;">${new Date().toLocaleDateString()}</td>
          </tr>
          <tr>
            <td style="font-size:0.98em;"><b>Issuance Slip No.:</b></td>
            <td style="border-bottom:1px solid #222;">${
              formData.transactionNumber
            }</td>
            <td style="font-size:0.98em;"><b>Invoice No.:</b></td>
            <td style="border-bottom:1px solid #222;"></td>
            <td colspan="2" style="text-align:right;font-size:1.1em;font-weight:bold;"></td>
          </tr>
        </table>
        <table class="receipt-main-table">
          <thead>
            <tr>
              <th style="width:32%;">Item Description</th>
              <th style="width:14%;">Item Type</th>
              <th style="width:8%;">Qty</th>
              <th style="width:12%;">SRP</th>
              <th style="width:14%;">Amount</th>
              <th style="width:20%;vertical-align:top;">Prepared by:</th>
            </tr>
          </thead>
          <tbody>
            ${dataRows}
            ${footerRow}
          </tbody>
        </table>
      </div>
    `;
  }

  // A4 NA RESIBO
  const html = `
    <!-- PRINTING NOTE: For best results, set print scale to 100% or Actual Size in your print dialog. -->
    <div class="receipt-a4">
      <div class="receipt-half">${renderReceipt("PAMO COPY")}</div>
      <div class="receipt-divider"></div>
      <div class="receipt-half">${renderReceipt(customerCopyLabel)}</div>
    </div>
    <style>
      .receipt-header-flex {
        width: 100%;
        display: flex;
        flex-direction: row;
        align-items: flex-start;
        justify-content: space-between;
        margin-bottom: 2px;
        margin-top: 2px;
        min-height: 60px;
      }
      .receipt-header-logo img {
        height: 60px;
        width: auto;
        display: block;
      }
      .receipt-header-logo {
        flex: 0 0 80px;
        display: flex;
        align-items: center;
        justify-content: flex-start;
      }
      .receipt-header-center {
        flex: 1 1 auto;
        text-align: center;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        min-width: 0;
      }
      .sti-lucena {
        font-size: 1.35em;
        font-weight: bold;
        letter-spacing: 1px;
        margin-bottom: 0px;
      }
      .sales-issuance-slip {
        font-size: 1.1em;
        font-weight: bold;
        letter-spacing: 0.5px;
        margin-top: 0px;
      }
      .receipt-header-copy {
        flex: 0 0 100px;
        text-align: right;
        font-size: 1em;
        font-weight: bold;
        margin-top: 2px;
        margin-right: 2px;
      }
      .receipt-a4 {
        width: 210mm;
        height: 297mm;
        padding: 0;
        margin: 0 auto;
        background: #fff;
        font-family: Arial, sans-serif;a
        position: relative;
      }
      .receipt-half {
        height: 148.5mm;
        box-sizing: border-box;
        padding: 10px 10px 6px 10px;
        border-bottom: 2.5px dashed #333;
        page-break-inside: avoid;
        background: #fff;
        overflow: hidden;
        display: flex;
        flex-direction: column;
        justify-content: flex-start;
      }
      .receipt-divider {
        height: 2px;
        background: transparent;
      }
      .receipt-header-table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 8px;
        font-size: 1em;
      }
      .receipt-header-table td {
        padding: 2px 6px 2px 0;
        vertical-align: bottom;
      }
      .receipt-main-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 1em;
        margin-bottom: 0px;
        table-layout: fixed;
      }
      .receipt-main-table th, .receipt-main-table td {
        border: 1px solid #222;
        padding: 6px 8px;
        vertical-align: top;
        word-break: break-word;
      }
      .receipt-main-table tbody > tr, .receipt-main-table tbody > tr > td {
        margin: 0;
        padding-top: 2px;
        padding-bottom: 2px;
      }
      .receipt-main-table th {
        background: #f2f2f2;
        text-align: center;
      }
      .receipt-main-table td {
        background: #fff;
      }
      .signature-col {
        background: #fff;
        vertical-align: top;
        text-align: left;
        min-width: 180px;
        max-width: 220px;
        padding: 0 !important;
      }
      .signature-table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
      }
      .signature-table .sig-label {
        font-weight: bold;
        font-size: 0.98em;
        border: 1px solid #222;
        border-bottom: none;
        padding: 4px 6px 2px 6px;
        background: #f8f8f8;
      }
      .signature-table .sig-box {
        border: 1px solid #222;
        border-top: none;
        height: 28px;
        padding: 2px 6px;
        font-size: 0.97em;
        background: #fff;
      }
      .signature-table .sig-name {
        font-size: 1em;
        margin-top: 2px;
      }
    </style>
  `;

  document.getElementById("salesReceiptBody").innerHTML = html;
  document.getElementById("salesReceiptModal").style.display = "block";
}

function closeSalesReceiptModal() {
  var modal = document.getElementById("salesReceiptModal");
  if (modal) {
    modal.style.display = "none";
    document.getElementById("salesReceiptBody").innerHTML = "";
  }

  // Refresh inventory table
  showSuccessMessage("Refreshing inventory...");
  setTimeout(() => {
    if (typeof refreshInventoryTable === "function") {
      refreshInventoryTable();
    } else {
      location.reload();
    }
  }, 300);
}

function printSalesReceipt() {
  const receiptHtml = document.getElementById("salesReceiptBody").innerHTML;
  const printWindow = window.open("", "_blank", "width=900,height=1200");
  printWindow.document.write(`
    <html>
      <head>
        <title>Print Receipt</title>
        <style>
          * { margin: 0; padding: 0; box-sizing: border-box; }
          @page { size: A4 portrait; margin: 0; }
          html, body { 
            width: 210mm; 
            height: 297mm; 
            margin: 0 !important; 
            padding: 0 !important; 
            background: #fff !important; 
            font-family: Arial, sans-serif;
          }
          .receipt-a4 {
            width: 210mm;
            height: 297mm;
            padding: 0;
            margin: 0;
            background: #fff;
            font-family: Arial, sans-serif;
            position: relative;
          }
          .receipt-half {
            height: 148.5mm;
            box-sizing: border-box;
            padding: 10px 10px 6px 10px;
            border-bottom: 2.5px dashed #333;
            page-break-inside: avoid;
            background: #fff;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            justify-content: flex-start;
          }
          .receipt-divider {
            height: 2px;
            background: transparent;
          }
          .receipt-header-flex {
            width: 100%;
            display: flex;
            flex-direction: row;
            align-items: flex-start;
            justify-content: space-between;
            margin-bottom: 2px;
            margin-top: 2px;
            min-height: 60px;
          }
          .receipt-header-logo img {
            height: 60px;
            width: auto;
            display: block;
          }
          .receipt-header-logo {
            flex: 0 0 80px;
            display: flex;
            align-items: center;
            justify-content: flex-start;
          }
          .receipt-header-center {
            flex: 1 1 auto;
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-width: 0;
          }
          .sti-lucena {
            font-size: 1.35em;
            font-weight: bold;
            letter-spacing: 1px;
            margin-bottom: 0px;
          }
          .sales-issuance-slip {
            font-size: 1.1em;
            font-weight: bold;
            letter-spacing: 0.5px;
            margin-top: 0px;
          }
          .receipt-header-copy {
            flex: 0 0 100px;
            text-align: right;
            font-size: 1em;
            font-weight: bold;
            margin-top: 2px;
            margin-right: 2px;
          }
          .receipt-header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 8px;
            font-size: 1em;
          }
          .receipt-header-table td {
            padding: 2px 6px 2px 0;
            vertical-align: bottom;
          }
          .receipt-main-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 1em;
            margin-bottom: 0px;
            table-layout: fixed;
          }
          .receipt-main-table th, .receipt-main-table td {
            border: 1px solid #222;
            padding: 6px 8px;
            vertical-align: top;
            word-break: break-word;
          }
          .receipt-main-table tbody > tr, .receipt-main-table tbody > tr > td {
            margin: 0;
            padding-top: 2px;
            padding-bottom: 2px;
          }
          .receipt-main-table th {
            background: #f2f2f2;
            text-align: center;
          }
          .receipt-main-table td {
            background: #fff;
          }
          .signature-col {
            background: #fff;
            vertical-align: top;
            text-align: left;
            min-width: 180px;
            max-width: 220px;
            padding: 0 !important;
          }
          .signature-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
          }
          .signature-table .sig-label {
            font-weight: bold;
            font-size: 0.98em;
            border: 1px solid #222;
            border-bottom: none;
            padding: 4px 6px 2px 6px;
            background: #f8f8f8;
          }
          .signature-table .sig-box {
            border: 1px solid #222;
            border-top: none;
            height: 28px;
            padding: 2px 6px;
            font-size: 0.97em;
            background: #fff;
          }
          .signature-table .sig-name {
            font-size: 1em;
            margin-top: 2px;
          }
          @media print {
            @page { size: A4; margin: 0; }
            html, body { 
              width: 210mm; 
              height: 297mm; 
              margin: 0 !important; 
              padding: 0 !important; 
              background: #fff !important; 
            }
            .receipt-a4 {
              width: 210mm !important;
              height: 297mm !important;
              margin: 0 !important;
              padding: 0 !important;
            }
            .receipt-half {
              height: 148.5mm !important;
              overflow: hidden !important;
            }
            .receipt-divider {
              display: none;
            }
          }
        </style>
      </head>
      <body onload="window.print(); setTimeout(() => window.close(), 500);">
        ${receiptHtml}
      </body>
    </html>
  `);
  printWindow.document.close();
  setTimeout(function () {
    var modal = document.getElementById("salesReceiptModal");
    if (modal) {
      modal.style.display = "none";
      document.getElementById("salesReceiptBody").innerHTML = "";
    }

    // Refresh inventory table after printing
    showSuccessMessage("Refreshing inventory...");
    setTimeout(() => {
      if (typeof refreshInventoryTable === "function") {
        refreshInventoryTable();
      } else {
        location.reload();
      }
    }, 300);
  }, 500);
}

function submitDeductQuantity(event) {
  event.preventDefault();
  event.stopPropagation();

  const transactionNumber = document.getElementById("transactionNumber").value;
  const studentNameSelect = document.getElementById("studentName");
  const roleCategory = document.getElementById("roleCategory").value;

  let studentName;
  if (window.jQuery && $(studentNameSelect).data("select2")) {
    const selectedData = $(studentNameSelect).select2("data")[0];
    studentName = selectedData ? selectedData.name || selectedData.text : "";
  } else {
    studentName =
      studentNameSelect.options[studentNameSelect.selectedIndex]?.text || "";
  }

  const studentIdNumber = document.getElementById("studentIdNumber").value;
  const cashierName = document.getElementById("cashierName").value;

  // Get all size detail items instead of old sales items
  const sizeDetailItems = document.querySelectorAll(".sales-size-detail-item");

  if (
    !transactionNumber ||
    !studentNameSelect.value ||
    !studentIdNumber ||
    !studentName ||
    !roleCategory ||
    sizeDetailItems.length === 0
  ) {
    showErrorMessage(
      "Please fill in all required fields and select at least one size"
    );
    return;
  }

  const formData = new FormData();
  formData.append("transactionNumber", transactionNumber);
  formData.append("studentName", studentName);
  formData.append("studentIdNumber", studentIdNumber);
  formData.append("cashierName", cashierName);
  formData.append("roleCategory", roleCategory);

  formData.append("customerId", studentNameSelect.value);
  formData.append("customerName", studentName);

  const itemIds = [];
  const itemNames = [];
  const itemCategories = [];
  const sizes = [];
  const quantities = [];
  const prices = [];
  const itemTotals = [];

  let hasErrors = false;

  // Get product containers to map item names
  const productContainers = document.querySelectorAll(".sales-item-container");

  sizeDetailItems.forEach((sizeItem, index) => {
    // Find which product container this size belongs to
    const container = sizeItem.closest(".sales-item-container");
    const containerIndex = container
      ? container.getAttribute("data-item-index")
      : 0;

    const productSelect = document.getElementById(
      `salesProductId_${containerIndex}`
    );
    const itemName = productSelect
      ? productSelect.options[productSelect.selectedIndex].text
      : "";

    const itemCode = sizeItem.querySelector('input[name="itemCode[]"]').value;
    const itemCategory = sizeItem.querySelector(
      'input[name="itemCategory[]"]'
    ).value;
    const size = sizeItem.querySelector('input[name="itemSize[]"]').value;
    const quantity = sizeItem.querySelector(
      'input[name="quantitySold[]"]'
    ).value;
    const price = sizeItem.querySelector('input[name="pricePerItem[]"]').value;
    const subtotal = sizeItem.querySelector(
      'input[name="sizeSubtotal[]"]'
    ).value;

    if (!itemCode || !size || !quantity || !price) {
      showErrorMessage(`Please fill in all fields for size ${size}`);
      hasErrors = true;
      return;
    }

    if (parseInt(quantity) <= 0) {
      showErrorMessage(`Quantity must be greater than 0 for size ${size}`);
      hasErrors = true;
      return;
    }

    itemIds.push(itemCode);
    itemNames.push(itemName);
    itemCategories.push(itemCategory);
    sizes.push(size);
    quantities.push(quantity);
    prices.push(price);
    itemTotals.push(subtotal);
  });

  if (hasErrors) {
    return;
  }

  itemIds.forEach((id, index) => {
    formData.append("itemId[]", id);
    formData.append("size[]", sizes[index]);
    formData.append("quantityToDeduct[]", quantities[index]);
    formData.append("pricePerItem[]", prices[index]);
    formData.append("itemTotal[]", itemTotals[index]);
  });

  // Calculate total before submitting to ensure it's correct
  calculateTotalAmount();

  const totalAmountValue = document.getElementById("totalAmount").value;
  const totalAmount = parseFloat(totalAmountValue) || 0;

  if (totalAmount <= 0) {
    showErrorMessage("Total amount must be greater than 0");
    return;
  }

  formData.append("totalAmount", totalAmount.toFixed(2));

  const submitBtn = document.querySelector('button[form="deductQuantityForm"]');
  const originalButtonText = submitBtn ? submitBtn.textContent : "Save";

  if (submitBtn) {
    submitBtn.disabled = true;
    submitBtn.textContent = "Recording Sale...";
    submitBtn.style.cursor = "not-allowed";
    submitBtn.style.opacity = "0.6";
  }

  // Create a timeout promise
  const timeoutPromise = new Promise((_, reject) => {
    setTimeout(
      () => reject(new Error("Request timeout - processing took too long")),
      30000
    ); // 30 second timeout
  });

  // Race between fetch and timeout
  Promise.race([
    fetch("../PAMO Inventory backend/process_deduct_quantity.php", {
      method: "POST",
      body: formData,
    }),
    timeoutPromise,
  ])
    .then((response) => {
      return response.text().then((text) => {
        try {
          return JSON.parse(text);
        } catch (e) {
          console.error("Invalid JSON response:", text);
          throw new Error("Invalid JSON response from server");
        }
      });
    })
    .then((data) => {
      // Restore button state
      if (submitBtn) {
        submitBtn.disabled = false;
        submitBtn.textContent = originalButtonText;
        submitBtn.style.cursor = "pointer";
        submitBtn.style.opacity = "1";
      }

      if (data.success) {
        // Reset form
        document.getElementById("deductQuantityForm").reset();
        const salesItemsContainer = document.getElementById("salesItems");
        const items = salesItemsContainer.querySelectorAll(".sales-item");
        for (let i = 1; i < items.length; i++) {
          items[i].remove();
        }

        // Close sales entry modal
        closeModal("deductQuantityModal");

        // Show success notification
        showSuccessMessage("Sales transaction recorded successfully!");

        // Calculate the correct total from itemTotals array
        const calculatedTotal = itemTotals.reduce(
          (sum, total) => sum + parseFloat(total),
          0
        );

        // Show receipt for printing
        showSalesReceipt({
          transactionNumber: transactionNumber,
          studentName,
          studentIdNumber,
          roleCategory,
          itemIds,
          itemNames,
          itemCategories,
          sizes,
          quantities,
          prices,
          itemTotals,
          totalAmount: calculatedTotal.toFixed(2),
          cashierName,
        });
      } else {
        console.error("Sales transaction failed:", data.message);
        showErrorMessage(
          "Error: " + (data.message || "Unknown error occurred")
        );
      }
    })
    .catch((error) => {
      console.error("Sales processing error:", error);

      if (submitBtn) {
        submitBtn.disabled = false;
        submitBtn.textContent = originalButtonText;
        submitBtn.style.cursor = "pointer";
        submitBtn.style.opacity = "1";
      }

      showErrorMessage(
        "An error occurred while processing your request. Check console for details."
      );
    });
}

function populateNamesByRole(role) {
  const nameSelect = document.getElementById("studentName");

  document.getElementById("studentIdNumber").value = "";

  if (!role) {
    nameSelect.innerHTML = '<option value="">Select Name</option>';
    if (window.jQuery && $(nameSelect).data("select2")) {
      $(nameSelect).prop("disabled", true).trigger("change.select2");
    }
    return;
  }

  nameSelect.innerHTML = '<option value="">Loading students...</option>';
  if (window.jQuery && $(nameSelect).data("select2")) {
    $(nameSelect).prop("disabled", true).trigger("change.select2");
  }

  const apiUrl =
    "../PAMO Inventory backend/get_students.php?role=" +
    encodeURIComponent(role);

  fetch(apiUrl)
    .then((response) => {
      if (!response.ok) {
        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
      }
      return response.text();
    })
    .then((text) => {
      let data;
      try {
        data = JSON.parse(text);
      } catch (e) {
        throw new Error("Invalid JSON response: " + text);
      }

      nameSelect.innerHTML = '<option value="">Select Name</option>';

      if (data.success && data.students && data.students.length > 0) {
        data.students.forEach((student) => {
          const option = document.createElement("option");
          option.value = student.id;
          option.textContent = student.name + " (" + student.id_number + ")";
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

document.addEventListener("DOMContentLoaded", function () {
  document
    .getElementById("roleCategory")
    .addEventListener("change", function () {
      populateNamesByRole(this.value);
    });

  const studentNameSelect = document.getElementById("studentName");
  if (window.jQuery && studentNameSelect) {
    $(studentNameSelect).select2({
      placeholder: "Select role first...",
      allowClear: true,
      width: "100%",
      disabled: true,
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
    $(studentNameSelect).on("select2:select", function (e) {
      const selectedElement = e.params.data.element;
      if (selectedElement) {
        const idNumber = selectedElement.getAttribute("data-id-number");
        if (idNumber) {
          document.getElementById("studentIdNumber").value = idNumber;
        }
      }
    });

    // Handle clear events
    $(studentNameSelect).on("select2:clear", function (e) {
      document.getElementById("studentIdNumber").value = "";
    });
  }

  // When a name is selected, autofill the ID number (fallback for non-Select2 functionality)
  document
    .getElementById("studentName")
    .addEventListener("change", function () {
      // This handles the case when Select2 is not available
      if (!window.jQuery || !$(this).data("select2")) {
        const selectedOption = this.options[this.selectedIndex];
        const idNumber = selectedOption.getAttribute("data-id-number") || "";
        document.getElementById("studentIdNumber").value = idNumber;
      }
    });

  // Initialize event listener for the first product select
  const firstProductSelect = document.getElementById("salesProductId_0");
  if (firstProductSelect) {
    // Initialize Select2
    if (window.jQuery) {
      $(firstProductSelect).select2({
        placeholder: "Select Product",
        allowClear: true,
        width: "100%",
      });

      $(firstProductSelect).on("change", function () {
        validateSalesProductSelection(0);
        handleSalesProductChange(0);
        updateAllSalesProductDropdowns();
      });
    } else {
      firstProductSelect.addEventListener("change", function () {
        validateSalesProductSelection(0);
        handleSalesProductChange(0);
        updateAllSalesProductDropdowns();
      });
    }
  }

  // Add resetDeductQuantityModal to reset the modal on close
  const deductQuantityModal = document.getElementById("deductQuantityModal");
  if (deductQuantityModal) {
    const closeBtns = deductQuantityModal.querySelectorAll(
      ".close, .cancel-btn"
    );
    closeBtns.forEach((btn) => {
      btn.addEventListener("click", function () {
        resetDeductQuantityModal();
      });
    });
  }
});

function resetDeductQuantityModal() {
  const form = document.getElementById("deductQuantityForm");
  if (form) form.reset();

  // Reset counter
  salesProductCounter = 1;

  // Remove all product containers except the first one
  const salesItems = document.getElementById("salesItems");
  if (salesItems) {
    const containers = salesItems.querySelectorAll(".sales-item-container");
    for (let i = 1; i < containers.length; i++) {
      containers[i].remove();
    }

    // Reset the first container
    if (containers.length > 0) {
      resetSalesItemContainer(0);
    }
  }

  // Reset student name Select2 and disable it
  const studentNameSelect = document.getElementById("studentName");
  if (studentNameSelect) {
    studentNameSelect.innerHTML = '<option value="">Select Name</option>';
    if (window.jQuery && $(studentNameSelect).data("select2")) {
      $(studentNameSelect).prop("disabled", true).trigger("change.select2");
    } else {
      studentNameSelect.disabled = true;
    }
  }

  // Clear ID number and total amount fields
  document.getElementById("studentIdNumber").value = "";
  document.getElementById("totalAmount").value = "";
}
