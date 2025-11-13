// Global variable to track current sort state
let currentSort = "";

// Function to toggle sort order
function toggleSort() {
  const filterForm = document.getElementById("filterForm");
  if (!filterForm) return;

  // Get current sort state from the hidden input in the form (not URL)
  let sortInput = filterForm.querySelector('input[name="sort"]');
  if (sortInput) {
    currentSort = sortInput.value || "";
  } else {
    // If no input exists, check URL as fallback
    const urlParams = new URLSearchParams(window.location.search);
    currentSort = urlParams.get("sort") || "";
  }

  // Toggle sort: asc <-> desc (only two states for better UX)
  if (currentSort === "asc") {
    currentSort = "desc";
  } else {
    // Default to ascending on first click or when switching from desc
    currentSort = "asc";
  }

  // Update the sort icon
  updateSortIcon(currentSort);

  // Add or update sort parameter in form
  if (!sortInput) {
    sortInput = document.createElement("input");
    sortInput.type = "hidden";
    sortInput.name = "sort";
    filterForm.appendChild(sortInput);
  }
  sortInput.value = currentSort;

  // Apply filters with new sort - no need for loader since it's instant
  applyFiltersAndPagination(false);
}

// Function to update sort icon display
function updateSortIcon(sort) {
  const sortIcon = document.getElementById("sortIcon");
  if (sortIcon) {
    if (sort === "asc") {
      sortIcon.textContent = "▲";
    } else if (sort === "desc") {
      sortIcon.textContent = "▼";
    } else {
      sortIcon.textContent = "⇅";
    }
  }
}

function handleAddQuantity() {
  if (!selectedItemCode) {
    alert("Please select an item first");
    return;
  }
  addQuantity(selectedItemCode);
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

// Helper function to compress images (same as Add New Item)
function compressImage(file, maxWidth = 1200, quality = 0.8) {
  return new Promise((resolve) => {
    const canvas = document.createElement("canvas");
    const ctx = canvas.getContext("2d");
    const img = new Image();

    img.onload = function () {
      // Calculate new dimensions
      let { width, height } = img;

      if (width > maxWidth) {
        height = (height * maxWidth) / width;
        width = maxWidth;
      }

      canvas.width = width;
      canvas.height = height;

      // Draw and compress
      ctx.drawImage(img, 0, 0, width, height);

      canvas.toBlob(
        (blob) => {
          resolve(blob);
        },
        "image/jpeg",
        quality
      );
    };

    img.src = URL.createObjectURL(file);
  });
}

async function submitEditImage() {
  const itemId = document.getElementById("imageItemId").value;
  const newImage = document.getElementById("newImage").files[0];

  try {
    // Compress large images (same as Add New Item)
    let processedFile = newImage;
    if (newImage.size > 1024 * 1024) {
      // 1MB
      console.log(
        `Compressing image: ${newImage.name} (${(
          newImage.size /
          1024 /
          1024
        ).toFixed(1)}MB)`
      );
      processedFile = await compressImage(newImage);
      console.log(
        `Compressed to: ${(processedFile.size / 1024 / 1024).toFixed(1)}MB`
      );
    }

    const formData = new FormData();
    formData.append("itemId", itemId);
    formData.append("newImage", processedFile);

    fetch("../PAMO Inventory backend/edit_image.php", {
      method: "POST",
      body: formData,
    })
      .then((response) => {
        if (!response.ok) {
          if (response.status === 413) {
            throw new Error(
              "File is too large for the server to process. Please compress your image and try again."
            );
          } else if (response.status === 500) {
            throw new Error(
              "Server error occurred. Please try again or contact support."
            );
          } else {
            throw new Error(`HTTP error! status: ${response.status}`);
          }
        }
        return response.json();
      })
      .then((data) => {
        if (data.success) {
          showMessage(data.message || `Updated image for item ${itemId}.`);
          closeModal("editImageModal");
          // Clear input field
          document.getElementById("newImage").value = "";
        } else {
          alert("Error updating image: " + (data.message || "Unknown error"));
        }
      })
      .catch((error) => {
        console.error("Error:", error);
        alert("An error occurred while updating the image: " + error.message);
      });
  } catch (compressionError) {
    console.error("Error during compression:", compressionError);
    alert(
      "An error occurred while processing the image: " +
        compressionError.message
    );
  }
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

// Search debouncing variables
let searchTimeout = null;
let currentSearchController = null;

// Client-side filtering variables
let allInventoryItems = [];
let filteredItems = [];
let currentPage = 1;
const itemsPerPage = 15;

// Visual feedback functions
function showSearchLoader() {
  let loadingOverlay = document.querySelector(".ajax-loading-overlay");
  if (!loadingOverlay) {
    loadingOverlay = document.createElement("div");
    loadingOverlay.className = "ajax-loading-overlay";
    loadingOverlay.innerHTML = '<div class="ajax-spinner"></div>';
    const mainContent = document.querySelector("main.main-content");
    if (mainContent) {
      mainContent.style.position = "relative";
      mainContent.appendChild(loadingOverlay);
    }
  }
  loadingOverlay.style.display = "flex";
}

function hideSearchLoader() {
  const loadingOverlay = document.querySelector(".ajax-loading-overlay");
  if (loadingOverlay) {
    loadingOverlay.style.display = "none";
  }
}

// Debounced search function
function performSearch() {
  // No longer needed - we use client-side filtering
  applyFiltersAndPagination();
}

// Fetch all inventory data once on page load
async function fetchAllInventory() {
  try {
    showSearchLoader();

    const response = await fetch("fetch_all_inventory.php", {
      headers: {
        "Cache-Control": "no-cache",
        "X-Requested-With": "XMLHttpRequest",
      },
    });

    if (!response.ok) {
      throw new Error("Network response was not ok");
    }

    const data = await response.json();

    if (data.success) {
      allInventoryItems = data.items;
      applyFiltersAndPagination();
    } else {
      console.error("Failed to fetch inventory:", data.error);
      allInventoryItems = [];
      updateTableDisplay([]);
    }

    hideSearchLoader();

    // Ensure PAMO global loader is hidden after data loads
    if (window.PAMOLoader && typeof window.PAMOLoader.hide === "function") {
      window.PAMOLoader.hide();
    }
  } catch (error) {
    console.error("Error fetching inventory:", error);
    hideSearchLoader();
    allInventoryItems = [];
    updateTableDisplay([]);

    // Ensure PAMO global loader is hidden even on error
    if (window.PAMOLoader && typeof window.PAMOLoader.hide === "function") {
      window.PAMOLoader.hide();
    }
  }
}

// Client-side filtering function
function applyFiltersAndPagination(resetPage = false) {
  if (resetPage) {
    currentPage = 1;
  }

  const searchInput = document.getElementById("searchInput");
  const categoryFilter = document.getElementById("categoryFilter");
  const sizeFilter = document.getElementById("sizeFilter");
  const statusFilter = document.getElementById("statusFilter");

  const searchTerm = searchInput ? searchInput.value.trim().toLowerCase() : "";
  const selectedCategory = categoryFilter ? categoryFilter.value : "";
  const selectedSize = sizeFilter ? sizeFilter.value : "";
  const selectedStatus = statusFilter ? statusFilter.value : "";

  // Get sort state
  const filterForm = document.getElementById("filterForm");
  let sortValue = "";
  if (filterForm) {
    const sortInput = filterForm.querySelector('input[name="sort"]');
    if (sortInput) {
      sortValue = sortInput.value;
    }
  }

  // Filter items
  filteredItems = allInventoryItems.filter((item) => {
    // Search filter - check if any keyword matches
    if (searchTerm) {
      const keywords = searchTerm.split(" ").filter((k) => k.length > 0);
      const matchesSearch = keywords.every((keyword) =>
        item.searchText.includes(keyword)
      );
      if (!matchesSearch) return false;
    }

    // Category filter
    if (selectedCategory && item.category !== selectedCategory) {
      return false;
    }

    // Size filter
    if (selectedSize && item.sizes !== selectedSize) {
      return false;
    }

    // Status filter
    if (selectedStatus && item.status !== selectedStatus) {
      return false;
    }

    return true;
  });

  // Sort items
  if (sortValue === "asc") {
    filteredItems.sort((a, b) => a.actual_quantity - b.actual_quantity);
  } else if (sortValue === "desc") {
    filteredItems.sort((a, b) => b.actual_quantity - a.actual_quantity);
  }

  // Check if the currently selected item is still in the filtered results
  let selectedItemStillVisible = false;
  if (typeof selectedItemCode !== "undefined" && selectedItemCode !== null) {
    selectedItemStillVisible = filteredItems.some(
      (item) => item.item_code === selectedItemCode
    );
  }

  // Reset selection only if the selected item is not in the filtered results
  if (!selectedItemStillVisible) {
    if (typeof selectedItemCode !== "undefined") {
      selectedItemCode = null;
    }
    if (typeof selectedPrice !== "undefined") {
      selectedPrice = null;
    }
    const editBtn = document.getElementById("editBtn");
    if (editBtn) {
      editBtn.disabled = true;
    }
  }

  // Calculate pagination
  const totalPages = Math.ceil(filteredItems.length / itemsPerPage);
  if (currentPage > totalPages && totalPages > 0) {
    currentPage = totalPages;
  }

  // Get items for current page
  const startIndex = (currentPage - 1) * itemsPerPage;
  const endIndex = startIndex + itemsPerPage;
  const itemsForPage = filteredItems.slice(startIndex, endIndex);

  // Update display
  updateTableDisplay(itemsForPage);
  updatePaginationDisplay(totalPages);
}

// Update table with filtered items
function updateTableDisplay(items) {
  const tableBody = document.querySelector(".inventory-table tbody");
  if (!tableBody) return;

  tableBody.innerHTML = "";

  if (items.length === 0) {
    tableBody.innerHTML =
      '<tr class="empty-row"><td colspan="7">No items found.</td></tr>';
    return;
  }

  items.forEach((item) => {
    const row = document.createElement("tr");
    row.dataset.itemCode = item.item_code;
    row.dataset.createdAt = item.created_at;
    row.dataset.category = item.category.toLowerCase();
    row.onclick = function () {
      selectRow(this, item.item_code, item.price);
    };

    // Re-apply highlight if this is the selected item
    if (
      typeof selectedItemCode !== "undefined" &&
      selectedItemCode !== null &&
      item.item_code === selectedItemCode
    ) {
      row.classList.add("selected");
    }

    row.innerHTML = `
      <td>${escapeHtml(item.item_code)}</td>
      <td>${escapeHtml(item.item_name)}</td>
      <td>${escapeHtml(item.category)}</td>
      <td>${item.actual_quantity || 0}</td>
      <td>${escapeHtml(item.sizes)}</td>
      <td>${item.priceFormatted}</td>
      <td class="${item.statusClass}">${item.status}</td>
    `;

    tableBody.appendChild(row);
  });
}

// Update pagination display
function updatePaginationDisplay(totalPages) {
  const paginationContainer = document.querySelector(".pagination");
  if (!paginationContainer) return;

  if (totalPages <= 1) {
    paginationContainer.innerHTML = "";
    return;
  }

  let html = "";

  // Previous button
  if (currentPage > 1) {
    html += `<a href="#" data-page="${
      currentPage - 1
    }" class="client-page-link">&laquo;</a>`;
  } else {
    html += `<a href="#" class="disabled" disabled>&laquo;</a>`;
  }

  // Always show first page
  html += `<a href="#" data-page="1" class="client-page-link ${
    currentPage === 1 ? "active" : ""
  }">1</a>`;

  // Show ellipsis if needed before the window
  if (currentPage > 4) {
    html += '<span class="pagination-ellipsis">...</span>';
  }

  // Determine window of pages to show around current page
  const window = 1;
  const start = Math.max(2, currentPage - window);
  const end = Math.min(totalPages - 1, currentPage + window);

  for (let i = start; i <= end; i++) {
    html += `<a href="#" data-page="${i}" class="client-page-link ${
      i === currentPage ? "active" : ""
    }">${i}</a>`;
  }

  // Show ellipsis if needed after the window
  if (currentPage < totalPages - 3) {
    html += '<span class="pagination-ellipsis">...</span>';
  }

  // Always show last page (if more than 1 page)
  if (totalPages > 1) {
    html += `<a href="#" data-page="${totalPages}" class="client-page-link ${
      currentPage === totalPages ? "active" : ""
    }">${totalPages}</a>`;
  }

  // Next button
  if (currentPage < totalPages) {
    html += `<a href="#" data-page="${
      currentPage + 1
    }" class="client-page-link">&raquo;</a>`;
  } else {
    html += `<a href="#" class="disabled" disabled>&raquo;</a>`;
  }

  // Clear and update pagination HTML
  paginationContainer.innerHTML = html;

  // Attach click handlers
  attachClientPaginationHandlers();
}

// Helper function to escape HTML
function escapeHtml(text) {
  const div = document.createElement("div");
  div.textContent = text;
  return div.innerHTML;
}

// Attach pagination click handlers
function attachClientPaginationHandlers() {
  const links = document.querySelectorAll(".client-page-link");

  links.forEach((link) => {
    link.addEventListener("click", function (e) {
      e.preventDefault();
      e.stopPropagation(); // Prevent event bubbling

      const page = parseInt(this.dataset.page);

      // If clicking the current page, do nothing but ensure loader is hidden
      if (page === currentPage) {
        // Force hide PAMOLoader if it was triggered
        if (window.PAMOLoader && typeof window.PAMOLoader.hide === "function") {
          window.PAMOLoader.hide();
        }
        return false;
      }

      // Navigate to different page
      if (page) {
        currentPage = page;
        applyFiltersAndPagination(false);

        // Ensure PAMOLoader stays hidden during client-side pagination
        if (window.PAMOLoader && typeof window.PAMOLoader.hide === "function") {
          window.PAMOLoader.hide();
        }

        // Scroll to top of table
        const mainContent = document.querySelector("main.main-content");
        if (mainContent) {
          mainContent.scrollTop = 0;
        }
      }

      return false;
    });
  });
}

// Always bind search input event, even if DOMContentLoaded is missed
document.addEventListener("DOMContentLoaded", function () {
  var searchInput = document.getElementById("searchInput");
  var filterForm = document.getElementById("filterForm");
  var tableBody = document.querySelector(".inventory-table tbody");

  // Disable PAMOLoader navigation state for client-side operations
  if (window.PAMOLoaderState) {
    window.PAMOLoaderState.setNavigating(false);
  }

  // Initialize sort state from URL
  const urlParams = new URLSearchParams(window.location.search);
  currentSort = urlParams.get("sort") || "";

  // Add sort input to form if it exists in URL
  if (currentSort && filterForm) {
    let sortInput = filterForm.querySelector('input[name="sort"]');
    if (!sortInput) {
      sortInput = document.createElement("input");
      sortInput.type = "hidden";
      sortInput.name = "sort";
      sortInput.value = currentSort;
      filterForm.appendChild(sortInput);
    }
  }

  // Prevent form submit on Enter or any input
  if (filterForm) {
    filterForm.addEventListener("submit", function (e) {
      e.preventDefault();
      return false;
    });
  }

  // Remove any server-side pagination links before client-side pagination loads
  const paginationContainer = document.querySelector(".pagination");
  if (paginationContainer) {
    paginationContainer.innerHTML = "";
  }

  // Fetch all inventory data on page load
  fetchAllInventory();

  // Search input with debouncing
  if (searchInput && filterForm && tableBody) {
    searchInput.addEventListener("input", function () {
      // Clear any existing timeout
      if (searchTimeout) {
        clearTimeout(searchTimeout);
      }

      const searchTerm = this.value.trim();

      // Immediate filter for better responsiveness
      // Use shorter delay since we're filtering client-side
      const delay = 150;

      searchTimeout = setTimeout(function () {
        applyFiltersAndPagination(true); // Reset to page 1
        searchTimeout = null;
      }, delay);
    });
  }

  const categoryFilter = document.getElementById("categoryFilter");
  const sizeFilter = document.getElementById("sizeFilter");
  const statusFilter = document.getElementById("statusFilter");

  // Category filter change
  if (categoryFilter) {
    categoryFilter.addEventListener("change", function () {
      applyFiltersAndPagination(true); // Reset to page 1
    });
  }

  // Size filter change
  if (sizeFilter) {
    sizeFilter.addEventListener("change", function () {
      applyFiltersAndPagination(true); // Reset to page 1
    });
  }

  // Status filter change
  if (statusFilter) {
    statusFilter.addEventListener("change", function () {
      applyFiltersAndPagination(true); // Reset to page 1
    });
  }

  // Prevent any old server-side pagination links from triggering navigation
  document.addEventListener(
    "click",
    function (e) {
      // Check if clicked element is an old ajax-page-link
      if (
        e.target.classList.contains("ajax-page-link") ||
        e.target.closest(".ajax-page-link")
      ) {
        e.preventDefault();
        e.stopPropagation();

        // Force hide PAMOLoader if it appears
        if (window.PAMOLoader && typeof window.PAMOLoader.hide === "function") {
          window.PAMOLoader.hide();
        }

        console.warn(
          "Old pagination link clicked - please use client-side pagination"
        );
        return false;
      }
    },
    true
  ); // Use capture phase to catch events early
});

// Export Inventory to Excel function
function exportInventoryToExcel() {
  // Get current filter values from the filter form
  const searchInput = document.getElementById("searchInput");
  const categoryFilter = document.getElementById("categoryFilter");
  const sizeFilter = document.getElementById("sizeFilter");
  const statusFilter = document.getElementById("statusFilter");

  // Build query parameters based on current filters
  const params = new URLSearchParams();

  if (searchInput && searchInput.value.trim()) {
    params.append("search", searchInput.value.trim());
  }
  if (categoryFilter && categoryFilter.value) {
    params.append("category", categoryFilter.value);
  }
  if (sizeFilter && sizeFilter.value) {
    params.append("size", sizeFilter.value);
  }
  if (statusFilter && statusFilter.value) {
    params.append("status", statusFilter.value);
  }

  const exportUrl =
    "../PAMO_PAGES/includes/export_inventory_report.php?" + params.toString();

  // Open the export URL in a new window/tab to trigger download
  window.open(exportUrl, "_blank");
}
