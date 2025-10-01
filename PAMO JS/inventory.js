function handleAddQuantity() {
  if (!selectedItemCode) {
    alert("Please select an item first");
    return;
  }
  addQuantity(selectedItemCode);
}

function editPrice(itemCode, currentPrice) {
  document.getElementById("itemId").value = itemCode;
  document.getElementById("newPrice").value = currentPrice;
  document.getElementById("editPriceModal").style.display = fetch(
    "fetch_inventory.php?" + queryString
  )
    .then(function (res) {
      return res.json();
    })
    .then(updateTableAndPagination);
}

function addQuantity(itemCode) {
  document.getElementById("quantityItemId").value = itemCode;
  document.getElementById("quantityToAdd").value = "";
  document.getElementById("addQuantityModal").style.display = "block";
}

function closeModal(modalId) {
  document.getElementById(modalId).style.display = "none";
}

function updatePrice() {
  const itemCode = document.getElementById("itemId").value;
  const newPrice = document.getElementById("newPrice").value;

  if (!newPrice || newPrice <= 0) {
    alert("Please enter a valid price");
    return;
  }

  fetch("../PAMO Inventory backend/update_price.php", {
    method: "POST",
    headers: {
      "Content-Type": "application/x-www-form-urlencoded",
    },
    body: `item_code=${encodeURIComponent(itemCode)}&price=${encodeURIComponent(
      newPrice
    )}`,
  })
    .then((response) => {
      return response.json();
    })
    .then((data) => {
      if (data.success) {
        alert("Price updated successfully!");
        location.reload();
      } else {
        alert("Error updating price: " + (data.message || "Unknown error"));
      }
    })
    .catch((error) => {
      console.error("Error:", error);
      alert("Error updating price: " + error);
    });

  closeModal("editPriceModal");
}

function updateQuantity() {
  const itemCode = document.getElementById("quantityItemId").value;
  const quantityToAdd = document.getElementById("quantityToAdd").value;

  fetch("../PAMO Inventory backend/update_quantity.php", {
    method: "POST",
    headers: {
      "Content-Type": "application/x-www-form-urlencoded",
    },
    body: `item_code=${itemCode}&quantity=${quantityToAdd}`,
  })
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        alert("Quantity updated successfully!");
        const row = document.querySelector(`tr[data-item-code="${itemCode}"]`);
        if (row) {
          updateStockStatus(row);
        }
        location.reload();
      } else {
        alert("Error updating quantity: " + data.message);
      }
    })
    .catch((error) => {
      alert("Error updating quantity: " + error);
    });

  closeModal("addQuantityModal");
}

function updateStockStatus(row) {
  const quantityCell = row.querySelector("td:nth-child(4)");
  const statusCell = row.querySelector("td:nth-child(7)");
  const actualQuantity = parseInt(quantityCell.textContent);

  let status, statusClass;

  if (actualQuantity <= 0) {
    status = "Out of Stock";
    statusClass = "status-out-of-stock";
  } else if (actualQuantity <= 10) {
    status = "Low Stock";
    statusClass = "status-low-stock";
  } else {
    status = "In Stock";
    statusClass = "status-in-stock";
  }

  statusCell.textContent = status;
  statusCell.className = statusClass;
}

function logout() {
  showLogoutConfirmation();
}

function saveEdit() {
  const itemCode = document.getElementById("editItemId").value;
  const newPrice = document.getElementById("editPrice").value;

  // Add logic to save the edited item details
  // You can send the updated data to the server using fetch or AJAX

  closeModal("editItemModal");
}

function editImage() {
  // Logic to edit image (e.g., open a file input or modal)
  // TODO: Implement edit image functionality
}

function showEditPriceModal() {
  document.getElementById("priceItemId").value =
    document.getElementById("editItemId").value; // Set the item ID
  document.getElementById("newPrice").value =
    document.getElementById("editPrice").value; // Set current price
  document.getElementById("editPriceModal").style.display = "block"; // Show the modal
}

function showEditImageModal() {
  document.getElementById("imageItemId").value =
    document.getElementById("editItemId").value; // Set the item ID
  document.getElementById("editImageModal").style.display = "block"; // Show the modal
}

function submitEditPrice() {
  const itemId = document.getElementById("priceItemId").value;
  const newPrice = document.getElementById("newPrice").value;

  if (!validatePrice(newPrice)) {
    return;
  }

  fetch("../PAMO Inventory backend/edit_price.php", {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
    },
    body: JSON.stringify({ itemId, newPrice }),
  })
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        updatePriceDisplay(itemId, newPrice);
        showMessage("Price updated successfully");
        closeModal("editPriceModal");
      } else {
        throw new Error(data.message || "Failed to update price");
      }
    })
    .catch(handleError);
}

function submitEditImage() {
  const itemId = document.getElementById("imageItemId").value;
  const newImage = document.getElementById("newImage").files[0];

  const formData = new FormData();
  formData.append("itemId", itemId);
  formData.append("newImage", newImage);

  fetch("../PAMO Inventory backend/edit_image.php", {
    method: "POST",
    body: formData,
  })
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        showMessage(`Updated image for item ${itemId}.`);
        closeModal("editImageModal");
        // Clear input field
        document.getElementById("newImage").value = "";
      } else {
        alert("Error updating image");
      }
    })
    .catch((error) => console.error("Error:", error));
}

// Function to show a message
function showMessage(message, type = "info") {
  const messageDiv = document.createElement("div");
  messageDiv.className = `message ${type}`;
  messageDiv.textContent = message;
  document.body.appendChild(messageDiv);

  setTimeout(() => {
    messageDiv.remove();
  }, 3000);
}

// Function to handle errors
function handleError(error) {
  console.error("Error:", error);
  showMessage(error.message || "An error occurred", "error");
}

// Function to validate quantity
function validateQuantity(quantity, available) {
  if (isNaN(quantity) || quantity <= 0) {
    showMessage("Please enter a valid quantity", "error");
    return false;
  }
  if (quantity > available) {
    showMessage("Cannot exceed available quantity", "error");
    return false;
  }
  return true;
}

// Function to update inventory display
function updateInventoryDisplay(itemId, quantity, action) {
  try {
    const row = document.querySelector(`tr[data-item-code="${itemId}"]`);
    if (!row) {
      console.error(`Row not found for item ${itemId}`);
      return false;
    }

    const quantityCell = row.cells[3]; // 4th cell is Actual Quantity
    if (!quantityCell) {
      console.error(`Quantity cell not found for item ${itemId}`);
      return false;
    }

    let currentQuantity = parseInt(quantityCell.textContent);
    if (isNaN(currentQuantity)) {
      console.error(`Invalid current quantity for item ${itemId}`);
      return false;
    }

    let newQuantity;
    if (action === "add") {
      newQuantity = currentQuantity + parseInt(quantity);
    } else if (action === "deduct") {
      newQuantity = currentQuantity - parseInt(quantity);
      if (newQuantity < 0) {
        showMessage("Cannot deduct more than available quantity", "error");
        return false;
      }
    } else {
      console.error(`Invalid action: ${action}`);
      return false;
    }

    quantityCell.textContent = newQuantity;
    updateStockStatus(row);
    return true;
  } catch (error) {
    console.error("Error in updateInventoryDisplay:", error);
    showMessage("An error occurred while updating the display", "error");
    return false;
  }
}

// Function to update price display
function updatePriceDisplay(itemId, newPrice) {
  const row = document.querySelector(`tr[data-item-code="${itemId}"]`);
  if (row) {
    const priceCell = row.cells[5]; // 6th cell is Price
    priceCell.textContent = `₱${parseFloat(newPrice).toFixed(2)}`;
  }
}

// Function to validate price
function validatePrice(price) {
  if (isNaN(price) || price <= 0) {
    showMessage("Please enter a valid price", "error");
    return false;
  }
  return true;
}

// Helper to update table and pagination
function updateTableAndPagination(data) {
  var tableBody = document.querySelector(".inventory-table tbody");
  tableBody.innerHTML = data.tbody;
  var oldPaginationDiv = document.querySelector(".pagination");
  if (oldPaginationDiv) {
    if (data.pagination) {
      oldPaginationDiv.outerHTML = data.pagination;
    } else {
      oldPaginationDiv.parentNode.removeChild(oldPaginationDiv);
    }
  } else if (data.pagination) {
    // If there was no pagination before but now there is, add it after the .inventory-table (not inside)
    var inventoryTableDiv = document.querySelector(".inventory-table");
    if (inventoryTableDiv && inventoryTableDiv.parentNode) {
      var tempDiv = document.createElement("div");
      tempDiv.innerHTML = data.pagination;
      inventoryTableDiv.parentNode.insertBefore(
        tempDiv.firstChild,
        inventoryTableDiv.nextSibling
      );
    }
  }
  /* Show result count
  var resultCountDiv = document.getElementById("resultCount");
  if (!resultCountDiv) {
    var inventoryContent = document.querySelector(".inventory-content");
    resultCountDiv = document.createElement("div");
    resultCountDiv.id = "resultCount";
    resultCountDiv.style.margin = "0 0 10px 0";
    resultCountDiv.style.fontWeight = "500";
    resultCountDiv.style.fontSize = "1.05em";
    inventoryContent.insertBefore(resultCountDiv, inventoryContent.firstChild);
  }
  if (data.total_items > 0) {
    var start = (data.page - 1) * data.limit + 1;
    var end = Math.min(data.page * data.limit, data.total_items);
    resultCountDiv.textContent = `Showing ${start}-${end} of ${data.total_items} results`;
  } else {
    resultCountDiv.textContent = "No items found.";
  }
  */
}

// Search debouncing variables
let searchTimeout = null;
let currentSearchController = null;

// Visual feedback functions
function showSearchLoader() {
  const tableBody = document.querySelector(".inventory-table tbody");
  if (tableBody) {
    // Add subtle loading effect without replacing content
    tableBody.style.opacity = "0.7";
    tableBody.style.pointerEvents = "none";
  }
}

function hideSearchLoader() {
  const tableBody = document.querySelector(".inventory-table tbody");
  if (tableBody) {
    tableBody.style.opacity = "1";
    tableBody.style.pointerEvents = "auto";
  }
}

// Debounced search function
function performSearch() {
  const filterForm = document.getElementById("filterForm");
  if (!filterForm) return;

  // Cancel any ongoing search request
  if (currentSearchController) {
    currentSearchController.abort();
  }

  // Create new AbortController for this search
  currentSearchController = new AbortController();

  // Always reset to page 1 on new search
  let pageInput = filterForm.querySelector('input[name="page"]');
  if (!pageInput) {
    pageInput = document.createElement("input");
    pageInput.type = "hidden";
    pageInput.name = "page";
    filterForm.appendChild(pageInput);
  }
  pageInput.value = 1;

  var formData = new FormData(filterForm);
  var queryString = new URLSearchParams(formData).toString();

  // Start timer for performance monitoring
  const startTime = performance.now();

  fetch("fetch_inventory.php?" + queryString, {
    signal: currentSearchController.signal,
    // Add cache control for faster subsequent requests
    headers: {
      "Cache-Control": "no-cache",
      "X-Requested-With": "XMLHttpRequest",
    },
  })
    .then(function (res) {
      if (res.ok) {
        return res.json();
      }
      throw new Error("Network response was not ok");
    })
    .then(function (data) {
      // Hide loading state immediately
      hideSearchLoader();
      updateTableAndPagination(data);

      // Log performance for debugging
      const endTime = performance.now();
      console.log(`Search completed in ${endTime - startTime}ms`);
    })
    .catch(function (error) {
      // Hide loading state on error too
      hideSearchLoader();
      // Only log errors that aren't from aborted requests
      if (error.name !== "AbortError") {
        console.error("Search error:", error);
      }
    });
}

// Always bind search input event, even if DOMContentLoaded is missed
document.addEventListener("DOMContentLoaded", function () {
  var searchInput = document.getElementById("searchInput");
  var filterForm = document.getElementById("filterForm");
  var tableBody = document.querySelector(".inventory-table tbody");

  // Prevent form submit on Enter or any input
  if (filterForm) {
    filterForm.addEventListener("submit", function (e) {
      e.preventDefault();
      // Clear any pending search timeout
      if (searchTimeout) {
        clearTimeout(searchTimeout);
        searchTimeout = null;
      }
      performSearch();
      return false;
    });
  }

  if (searchInput && filterForm && tableBody) {
    searchInput.addEventListener("input", function () {
      // Clear any existing timeout
      if (searchTimeout) {
        clearTimeout(searchTimeout);
      }

      // Add immediate visual feedback
      showSearchLoader();

      const searchTerm = this.value.trim();

      // For very short searches (1-2 characters), use slightly longer delay
      // For longer searches, use shorter delay for more responsiveness
      const delay = searchTerm.length <= 2 ? 150 : 50;

      searchTimeout = setTimeout(function () {
        performSearch();
        searchTimeout = null;
      }, delay);
    });
  }

  const categoryFilter = document.getElementById("categoryFilter");
  const sizeFilter = document.getElementById("sizeFilter");
  const statusFilter = document.getElementById("statusFilter");

  if (categoryFilter) {
    categoryFilter.addEventListener("change", function () {
      if (searchTimeout) {
        clearTimeout(searchTimeout);
        searchTimeout = null;
      }
      performSearch();
    });
  }

  if (sizeFilter) {
    sizeFilter.addEventListener("change", function () {
      if (searchTimeout) {
        clearTimeout(searchTimeout);
        searchTimeout = null;
      }
      performSearch();
    });
  }

  if (statusFilter) {
    statusFilter.addEventListener("change", function () {
      if (searchTimeout) {
        clearTimeout(searchTimeout);
        searchTimeout = null;
      }
      performSearch();
    });
  }

  document.addEventListener("click", function (e) {
    if (e.target.classList.contains("ajax-page-link")) {
      e.preventDefault();

      if (searchTimeout) {
        clearTimeout(searchTimeout);
        searchTimeout = null;
      }

      if (currentSearchController) {
        currentSearchController.abort();
      }

      currentSearchController = new AbortController();

      const url = new URL(e.target.href, window.location.origin);
      const page = url.searchParams.get("page") || 1;
      let pageInput = filterForm.querySelector('input[name="page"]');
      if (!pageInput) {
        pageInput = document.createElement("input");
        pageInput.type = "hidden";
        pageInput.name = "page";
        filterForm.appendChild(pageInput);
      }
      pageInput.value = page;

      var formData = new FormData(filterForm);
      var queryString = new URLSearchParams(formData).toString();

      fetch("fetch_inventory.php?" + queryString, {
        signal: currentSearchController.signal,
      })
        .then(function (res) {
          if (res.ok) {
            return res.json();
          }
          throw new Error("Network response was not ok");
        })
        .then(updateTableAndPagination)
        .catch(function (error) {
          if (error.name !== "AbortError") {
            console.error("Pagination error:", error);
          }
        });
    }
  });
});
