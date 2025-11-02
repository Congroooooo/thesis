// Exchange Item Modal Backend Functions

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

function showExchangeItemModal() {
  document.getElementById("exchangeItemModal").style.display = "block";
  // Reset form when modal opens
  document.getElementById("exchangeItemForm").reset();
  document.getElementById("exchangeItemBought").innerHTML =
    '<option value="">Please select a customer first</option>';
  document.getElementById("exchangeNewSize").innerHTML =
    '<option value="">Select New Size</option>';

  // Reset Select2
  if (window.jQuery && $("#exchangeCustomerName").length) {
    $("#exchangeCustomerName").val(null).trigger("change");
  }
}

function loadCustomerPurchases() {
  const customerId = document.getElementById("exchangeCustomerName").value;
  const itemBoughtSelect = document.getElementById("exchangeItemBought");
  const customerFilterNote = document.getElementById("customerFilterNote");

  if (!customerId) {
    itemBoughtSelect.innerHTML =
      '<option value="">Please select a customer first</option>';
    customerFilterNote.textContent = "Please select a customer first";
    return;
  }

  customerFilterNote.textContent = "Loading customer purchases...";

  fetch(
    `../PAMO Inventory backend/get_customer_purchases.php?customer_id=${customerId}`
  )
    .then((response) => {
      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }
      return response.json();
    })
    .then((data) => {
      itemBoughtSelect.innerHTML =
        '<option value="">Select Item (Purchased within 24 hours)</option>';

      if (data.success && data.purchases && data.purchases.length > 0) {
        data.purchases.forEach((purchase) => {
          const option = document.createElement("option");
          option.value = JSON.stringify(purchase);
          option.textContent = `${purchase.item_name} - Size: ${purchase.size} (Transaction: ${purchase.transaction_number}) - ${purchase.sale_date}`;
          itemBoughtSelect.appendChild(option);
        });

        if (data.customer_filtered) {
          customerFilterNote.textContent =
            "Showing customer-specific purchases within 24 hours.";
          customerFilterNote.style.color = "#28a745";
        } else {
          customerFilterNote.textContent =
            "Showing all recent sales. Please verify the item belongs to the selected customer.";
          customerFilterNote.style.color = "#ffc107";
        }
      } else {
        itemBoughtSelect.innerHTML =
          '<option value="">No purchases found within 24 hours</option>';
        customerFilterNote.textContent =
          "No purchases found within 24 hours for this customer.";
        customerFilterNote.style.color = "#dc3545";
      }
    })
    .catch((error) => {
      console.error("Error loading purchases:", error);
      showErrorMessage("Error loading customer purchases");
      customerFilterNote.textContent = "Error loading customer purchases";
      customerFilterNote.style.color = "#dc3545";
    });
}

function loadAvailableSizes() {
  const selectedPurchase = document.getElementById("exchangeItemBought").value;
  const newSizeSelect = document.getElementById("exchangeNewSize");

  if (!selectedPurchase) {
    newSizeSelect.innerHTML = '<option value="">Select New Size</option>';
    return;
  }

  try {
    const purchase = JSON.parse(selectedPurchase);

    fetch(
      `../PAMO Inventory backend/get_available_sizes.php?item_code=${purchase.item_code}`
    )
      .then((response) => {
        if (!response.ok) {
          throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
      })
      .then((data) => {
        newSizeSelect.innerHTML = '<option value="">Select New Size</option>';

        if (data.success && data.sizes) {
          data.sizes.forEach((size) => {
            if (size.size !== purchase.size && size.actual_quantity > 0) {
              const option = document.createElement("option");
              option.value = size.item_code;
              option.textContent = `${size.size} (Stock: ${size.actual_quantity})`;
              newSizeSelect.appendChild(option);
            }
          });
        }
      })
      .catch((error) => {
        console.error("Error loading sizes:", error);
        showErrorMessage("Error loading available sizes");
      });
  } catch (error) {
    console.error("Error parsing purchase data:", error);
  }
}

function submitExchangeItem(event) {
  event.preventDefault();
  event.stopPropagation();

  const customerSelect = document.getElementById("exchangeCustomerName");
  const customerId = customerSelect.value;
  const customerName =
    customerSelect.options[customerSelect.selectedIndex].text;
  const selectedPurchase = document.getElementById("exchangeItemBought").value;
  const newSizeSelect = document.getElementById("exchangeNewSize");
  const newSizeItemCode = newSizeSelect.value;
  const newSizeText = newSizeSelect.options[newSizeSelect.selectedIndex]
    ? newSizeSelect.options[newSizeSelect.selectedIndex].text.split(" ")[0]
    : "";
  const remarks = document.getElementById("exchangeRemarks").value;

  if (!customerId || !selectedPurchase || !newSizeItemCode) {
    showErrorMessage("Please fill in all required fields");
    return;
  }

  const submitBtn = document.querySelector('button[form="exchangeItemForm"]');
  const originalButtonText = submitBtn
    ? submitBtn.textContent
    : "Process Exchange";

  if (submitBtn) {
    submitBtn.disabled = true;
    submitBtn.textContent = "Processing Exchange...";
    submitBtn.style.cursor = "not-allowed";
    submitBtn.style.opacity = "0.6";
  }

  try {
    const purchase = JSON.parse(selectedPurchase);

    const formData = new FormData();
    formData.append("sales_id", purchase.sales_id);
    formData.append("transaction_number", purchase.transaction_number);
    formData.append("customer_id", customerId);
    formData.append("customer_name", customerName);
    formData.append("item_code", purchase.item_code);
    formData.append("old_size", purchase.size);
    formData.append("new_size", newSizeText);
    formData.append("new_item_code", newSizeItemCode);
    formData.append("remarks", remarks);

    fetch("../PAMO Inventory backend/process_exchange.php", {
      method: "POST",
      body: formData,
    })
      .then((response) => {
        if (!response.ok) {
          throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
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
          document.getElementById("exchangeItemForm").reset();
          document.getElementById("exchangeItemBought").innerHTML =
            '<option value="">Please select a customer first</option>';
          document.getElementById("exchangeNewSize").innerHTML =
            '<option value="">Select New Size</option>';

          // Close modal and show notification
          closeModal("exchangeItemModal");
          showSuccessMessage(
            "Exchange processed successfully! Refreshing inventory..."
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
          console.error("Exchange error details:", data);
          showErrorMessage(
            "Error processing exchange: " + (data.message || "Unknown error")
          );
        }
      })
      .catch((error) => {
        console.error("Exchange processing error:", error);

        // Restore button state
        if (submitBtn) {
          submitBtn.disabled = false;
          submitBtn.textContent = originalButtonText;
          submitBtn.style.cursor = "pointer";
          submitBtn.style.opacity = "1";
        }

        showErrorMessage("Error processing exchange: " + error.message);
      });
  } catch (error) {
    console.error("Error parsing purchase data:", error);

    // Restore button state
    if (submitBtn) {
      submitBtn.disabled = false;
      submitBtn.textContent = originalButtonText;
      submitBtn.style.cursor = "pointer";
      submitBtn.style.opacity = "1";
    }

    showErrorMessage("Error processing exchange data");
  }
}
