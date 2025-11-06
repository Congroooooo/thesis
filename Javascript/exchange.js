/**
 * Exchange System JavaScript
 * Handles frontend logic for item exchanges with price adjustments
 */

// Global state
let currentOrderId = null;
let currentOrderItems = [];
let selectedExchangeItems = [];

/**
 * Open exchange modal for an order
 */
function openExchangeModal(orderId) {
  currentOrderId = orderId;
  selectedExchangeItems = [];

  // Show loading
  const modal = document.getElementById("exchangeModal");
  modal.style.display = "block";

  const modalBody = document.querySelector(".exchange-modal-body");
  modalBody.innerHTML =
    '<div style="text-align:center; padding:40px;"><i class="fas fa-spinner fa-spin" style="font-size:32px;"></i><p>Loading exchange information...</p></div>';

  // Fetch exchange eligibility
  fetch(`../Backend/get_exchange_eligibility.php?order_id=${orderId}`)
    .then((response) => response.json())
    .then((data) => {
      if (!data.success) {
        closeExchangeModal();
        showNotification(
          "error",
          data.message || "Failed to load exchange information"
        );
        return;
      }

      if (!data.eligible) {
        closeExchangeModal();
        showNotification("warning", data.message);
        return;
      }

      // Store order items
      currentOrderItems = data.items;

      // Render exchange form
      renderExchangeForm(data);
    })
    .catch((error) => {
      console.error("Error:", error);
      closeExchangeModal();
      showNotification(
        "error",
        "An error occurred while loading exchange information"
      );
    });
}

/**
 * Close exchange modal
 */
function closeExchangeModal() {
  const modal = document.getElementById("exchangeModal");
  modal.style.display = "none";
  currentOrderId = null;
  currentOrderItems = [];
  selectedExchangeItems = [];
}

/**
 * Render exchange form
 */
function renderExchangeForm(data) {
  const modalBody = document.querySelector(".exchange-modal-body");

  let html = `
        <div class="exchange-info-banner">
            <h4><i class="fas fa-info-circle"></i> Exchange Information</h4>
            <p><strong>Order Number:</strong> ${data.order.order_number}</p>
            <p><strong>Order Date:</strong> ${new Date(
              data.order.created_at
            ).toLocaleDateString("en-US", {
              year: "numeric",
              month: "long",
              day: "numeric",
            })}</p>
            <p><strong>Time Remaining:</strong> ${
              data.hours_remaining
            } hours</p>
        </div>
        
        <div class="exchange-time-warning">
            <i class="fas fa-clock"></i> <strong>Note:</strong> You have ${
              data.hours_remaining
            } hours remaining to complete this exchange. 
            Exchanges are only allowed within 24 hours of the original order.
        </div>
        
        <div class="exchange-items-section">
            <h3><i class="fas fa-box"></i> Select Items to Exchange</h3>
    `;

  // Render each item
  data.items.forEach((item, index) => {
    if (item.available_quantity > 0) {
      html += renderExchangeItemCard(item, index);
    }
  });

  html += `
        </div>
        
        <div class="exchange-summary" id="exchangeSummary" style="display:none;">
            <h3><i class="fas fa-calculator"></i> Exchange Summary</h3>
            <div class="exchange-summary-grid">
                <div class="exchange-summary-item">
                    <div class="exchange-summary-label">Items to Exchange</div>
                    <div class="exchange-summary-value" id="summaryItemCount">0</div>
                </div>
                <div class="exchange-summary-item">
                    <div class="exchange-summary-label">Total Quantity</div>
                    <div class="exchange-summary-value" id="summaryTotalQty">0</div>
                </div>
                <div class="exchange-summary-item">
                    <div class="exchange-summary-label">Original Total</div>
                    <div class="exchange-summary-value" id="summaryOriginalTotal">₱0.00</div>
                </div>
                <div class="exchange-summary-item">
                    <div class="exchange-summary-label">New Total</div>
                    <div class="exchange-summary-value" id="summaryNewTotal">₱0.00</div>
                </div>
            </div>
            <div id="adjustmentBox"></div>
        </div>
        
        <div class="exchange-remarks">
            <label for="exchangeRemarks"><i class="fas fa-comment"></i> Remarks (Optional)</label>
            <textarea id="exchangeRemarks" placeholder="Add any additional notes or reasons for the exchange..."></textarea>
        </div>
    `;

  modalBody.innerHTML = html;

  // Load available sizes for each item
  data.items.forEach((item, index) => {
    if (item.available_quantity > 0) {
      loadAvailableSizesForItem(item, index);
    }
  });
}

/**
 * Render individual item card
 */
function renderExchangeItemCard(item, index) {
  const cleanName = item.item_name.replace(/ [SMLX234567]+$/, "");

  return `
        <div class="exchange-item-card" id="exchangeItemCard${index}">
            <div class="exchange-item-header">
                <div class="exchange-item-checkbox">
                    <input type="checkbox" id="itemCheck${index}" onchange="toggleItemSelection(${index})">
                </div>
                ${
                  item.image_path
                    ? `<img src="../uploads/itemlist/${item.image_path}" alt="${cleanName}" class="exchange-item-image" onerror="this.src='../Images/placeholder.png'">`
                    : ""
                }
                <div class="exchange-item-info">
                    <div class="exchange-item-name">${cleanName}</div>
                    <div class="exchange-item-details">
                        <div class="exchange-item-detail">
                            <i class="fas fa-tag"></i>
                            <span>Code: ${item.item_code}</span>
                        </div>
                        <div class="exchange-item-detail">
                            <i class="fas fa-ruler"></i>
                            <span>Current Size: ${item.size}</span>
                        </div>
                        <div class="exchange-item-detail">
                            <i class="fas fa-box"></i>
                            <span>Quantity: ${item.quantity}</span>
                        </div>
                        <div class="exchange-item-detail">
                            <i class="fas fa-money-bill"></i>
                            <span>Price: ₱${parseFloat(item.price).toFixed(
                              2
                            )}</span>
                        </div>
                        <div class="exchange-item-detail">
                            <i class="fas fa-check-circle"></i>
                            <span>Available: ${item.available_quantity}</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="exchange-item-controls">
                <div class="exchange-control-group">
                    <label>New Size</label>
                    <select id="newSize${index}" onchange="updatePriceDifference(${index})">
                        <option value="">Loading sizes...</option>
                    </select>
                </div>
                
                <div class="exchange-control-group">
                    <label>Quantity to Exchange</label>
                    <input type="number" id="exchangeQty${index}" min="1" max="${
    item.available_quantity
  }" value="1" onchange="updatePriceDifference(${index})">
                </div>
                
                <div class="exchange-control-group">
                    <label>Price Adjustment</label>
                    <div class="exchange-price-info" id="priceInfo${index}">
                        <div>Original: ₱${parseFloat(item.price).toFixed(
                          2
                        )}</div>
                        <div>New: <span id="newPrice${index}">-</span></div>
                        <div class="exchange-price-diff neutral" id="priceDiff${index}">Select new size</div>
                    </div>
                </div>
            </div>
        </div>
    `;
}

/**
 * Toggle item selection
 */
function toggleItemSelection(index) {
  const checkbox = document.getElementById(`itemCheck${index}`);
  const card = document.getElementById(`exchangeItemCard${index}`);

  if (checkbox.checked) {
    card.classList.add("selected");
    addToSelectedItems(index);
  } else {
    card.classList.remove("selected");
    removeFromSelectedItems(index);
  }

  updateExchangeSummary();
}

/**
 * Load available sizes for an item
 */
function loadAvailableSizesForItem(item, index) {
  fetch(
    `../Backend/get_available_sizes.php?item_code=${item.item_code}&current_size=${item.size}`
  )
    .then((response) => response.json())
    .then((data) => {
      const select = document.getElementById(`newSize${index}`);

      if (data.success && data.sizes.length > 0) {
        select.innerHTML = '<option value="">Select new size...</option>';
        data.sizes.forEach((size) => {
          select.innerHTML += `<option value="${size.item_code}" data-price="${
            size.price
          }" data-size="${size.sizes}">
                        ${size.sizes} (Stock: ${
            size.actual_quantity
          }) - ₱${parseFloat(size.price).toFixed(2)}
                    </option>`;
        });
      } else {
        select.innerHTML = '<option value="">No other sizes available</option>';
      }
    })
    .catch((error) => {
      console.error("Error loading sizes:", error);
      const select = document.getElementById(`newSize${index}`);
      select.innerHTML = '<option value="">Error loading sizes</option>';
    });
}

/**
 * Update price difference display
 */
function updatePriceDifference(index) {
  const item = currentOrderItems[index];
  const newSizeSelect = document.getElementById(`newSize${index}`);
  const qtyInput = document.getElementById(`exchangeQty${index}`);
  const selectedOption = newSizeSelect.options[newSizeSelect.selectedIndex];

  if (!selectedOption || !selectedOption.value) {
    return;
  }

  const newPrice = parseFloat(selectedOption.dataset.price);
  const originalPrice = parseFloat(item.price);
  const quantity = parseInt(qtyInput.value) || 1;

  const priceDiff = newPrice - originalPrice;
  const totalDiff = priceDiff * quantity;

  // Update display
  document.getElementById(
    `newPrice${index}`
  ).textContent = `₱${newPrice.toFixed(2)}`;

  const diffElement = document.getElementById(`priceDiff${index}`);
  diffElement.classList.remove("positive", "negative", "neutral");

  if (totalDiff > 0) {
    diffElement.classList.add("positive");
    diffElement.textContent = `+₱${totalDiff.toFixed(2)} (Pay more)`;
  } else if (totalDiff < 0) {
    diffElement.classList.add("negative");
    diffElement.textContent = `₱${totalDiff.toFixed(2)} (Refund)`;
  } else {
    diffElement.classList.add("neutral");
    diffElement.textContent = `₱0.00 (Equal exchange)`;
  }

  updateExchangeSummary();
}

/**
 * Add item to selected items
 */
function addToSelectedItems(index) {
  const item = currentOrderItems[index];
  const newSizeSelect = document.getElementById(`newSize${index}`);
  const qtyInput = document.getElementById(`exchangeQty${index}`);

  selectedExchangeItems.push({
    index: index,
    original_item_code: item.item_code,
    original_size: item.size,
    original_price: item.price,
    available_quantity: item.available_quantity,
    get newSizeSelect() {
      return document.getElementById(`newSize${index}`);
    },
    get qtyInput() {
      return document.getElementById(`exchangeQty${index}`);
    },
  });
}

/**
 * Remove item from selected items
 */
function removeFromSelectedItems(index) {
  selectedExchangeItems = selectedExchangeItems.filter(
    (item) => item.index !== index
  );
}

/**
 * Update exchange summary
 */
function updateExchangeSummary() {
  const summary = document.getElementById("exchangeSummary");

  if (selectedExchangeItems.length === 0) {
    summary.style.display = "none";
    document.getElementById("submitExchangeBtn").disabled = true;
    return;
  }

  summary.style.display = "block";
  document.getElementById("submitExchangeBtn").disabled = false;

  let totalItems = 0;
  let totalQty = 0;
  let originalTotal = 0;
  let newTotal = 0;

  selectedExchangeItems.forEach((item) => {
    const qtyInput = item.qtyInput;
    const newSizeSelect = item.newSizeSelect;
    const selectedOption = newSizeSelect.options[newSizeSelect.selectedIndex];

    if (selectedOption && selectedOption.value) {
      const qty = parseInt(qtyInput.value) || 1;
      const newPrice = parseFloat(selectedOption.dataset.price);

      totalItems++;
      totalQty += qty;
      originalTotal += item.original_price * qty;
      newTotal += newPrice * qty;
    }
  });

  const totalDiff = newTotal - originalTotal;

  // Update summary values
  document.getElementById("summaryItemCount").textContent = totalItems;
  document.getElementById("summaryTotalQty").textContent = totalQty;
  document.getElementById(
    "summaryOriginalTotal"
  ).textContent = `₱${originalTotal.toFixed(2)}`;
  document.getElementById("summaryNewTotal").textContent = `₱${newTotal.toFixed(
    2
  )}`;

  // Update adjustment box
  const adjustmentBox = document.getElementById("adjustmentBox");

  if (totalDiff > 0) {
    adjustmentBox.className = "exchange-adjustment-box additional";
    adjustmentBox.innerHTML = `
            <div class="exchange-adjustment-title">Additional Payment Required</div>
            <div class="exchange-adjustment-amount additional">₱${totalDiff.toFixed(
              2
            )}</div>
            <div style="font-size:14px; color:#666;">You will need to pay this amount to complete the exchange.</div>
        `;
  } else if (totalDiff < 0) {
    adjustmentBox.className = "exchange-adjustment-box refund";
    adjustmentBox.innerHTML = `
            <div class="exchange-adjustment-title">Refund Due</div>
            <div class="exchange-adjustment-amount refund">₱${Math.abs(
              totalDiff
            ).toFixed(2)}</div>
            <div style="font-size:14px; color:#666;">This amount will be refunded to you.</div>
        `;
  } else {
    adjustmentBox.className = "exchange-adjustment-box";
    adjustmentBox.innerHTML = `
            <div class="exchange-adjustment-title">Equal Exchange</div>
            <div class="exchange-adjustment-amount">₱0.00</div>
            <div style="font-size:14px; color:#666;">No price adjustment needed.</div>
        `;
  }
}

/**
 * Submit exchange request
 */
function submitExchangeRequest() {
  if (selectedExchangeItems.length === 0) {
    showNotification("warning", "Please select at least one item to exchange");
    return;
  }

  // Validate all selected items have new sizes
  for (let item of selectedExchangeItems) {
    const newSizeSelect = item.newSizeSelect;
    if (!newSizeSelect.value) {
      showNotification(
        "warning",
        "Please select a new size for all selected items"
      );
      return;
    }
  }

  // Prepare exchange data
  const exchangeItems = selectedExchangeItems.map((item) => {
    const newSizeSelect = item.newSizeSelect;
    const qtyInput = item.qtyInput;
    const selectedOption = newSizeSelect.options[newSizeSelect.selectedIndex];

    return {
      original_item_code: item.original_item_code,
      original_size: item.original_size,
      new_item_code: newSizeSelect.value,
      new_size: selectedOption.dataset.size,
      exchange_quantity: parseInt(qtyInput.value),
      available_quantity: item.available_quantity,
    };
  });

  const remarks = document.getElementById("exchangeRemarks").value;

  // Disable submit button
  const submitBtn = document.getElementById("submitExchangeBtn");
  submitBtn.disabled = true;
  submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';

  // Submit request
  const formData = new FormData();
  formData.append("order_id", currentOrderId);
  formData.append("exchange_items", JSON.stringify(exchangeItems));
  formData.append("remarks", remarks);

  fetch("../Backend/process_exchange_request.php", {
    method: "POST",
    body: formData,
  })
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        showNotification(
          "success",
          data.message + "<br>" + data.adjustment_message
        );
        closeExchangeModal();
        // Reload page to show updated order
        setTimeout(() => {
          location.reload();
        }, 2000);
      } else {
        showNotification("error", data.message);
        submitBtn.disabled = false;
        submitBtn.innerHTML =
          '<i class="fas fa-exchange-alt"></i> Submit Exchange Request';
      }
    })
    .catch((error) => {
      console.error("Error:", error);
      showNotification(
        "error",
        "An error occurred while processing the exchange"
      );
      submitBtn.disabled = false;
      submitBtn.innerHTML =
        '<i class="fas fa-exchange-alt"></i> Submit Exchange Request';
    });
}

/**
 * Show notification
 */
function showNotification(type, message) {
  // Remove existing notifications
  const existing = document.querySelector(".exchange-notification");
  if (existing) {
    existing.remove();
  }

  const notification = document.createElement("div");
  notification.className = `exchange-notification ${type}`;
  notification.innerHTML = `
        <i class="fas fa-${
          type === "success"
            ? "check-circle"
            : type === "error"
            ? "times-circle"
            : type === "warning"
            ? "exclamation-triangle"
            : "info-circle"
        }"></i>
        <span>${message}</span>
    `;

  document.body.appendChild(notification);

  // Auto remove after 5 seconds
  setTimeout(() => {
    notification.remove();
  }, 5000);
}

// Close modal when clicking outside
window.onclick = function (event) {
  const modal = document.getElementById("exchangeModal");
  if (event.target === modal) {
    closeExchangeModal();
  }
};
