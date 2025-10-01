// Edit Item Modal Backend Functions

let selectedItemCode = null;
let selectedPrice = null;

function selectRow(row, itemCode, price) {
  if (selectedItemCode === itemCode) {
    row.classList.remove("selected");
    selectedItemCode = null;
    selectedPrice = null;
    document.getElementById("editBtn").disabled = true;
    return;
  }

  document.querySelectorAll(".inventory-table tbody tr").forEach((tr) => {
    tr.classList.remove("selected");
  });

  row.classList.add("selected");
  selectedItemCode = itemCode;
  selectedPrice = price;
  document.getElementById("editBtn").disabled = false;
}

function handleEdit() {
  if (!selectedItemCode) {
    alert("Please select an item first");
    return;
  }

  const row = document.querySelector(
    `tr[data-item-code="${selectedItemCode}"]`
  );

  if (!row) {
    alert("Selected item not found in the table.");
    return;
  }

  document.getElementById("editItemId").value = selectedItemCode;
  document.getElementById("editItemCode").value = row.cells[0].textContent;
  document.getElementById("editItemName").value = row.cells[1].textContent;
  document.getElementById("editCategory").value = row.cells[2].textContent;
  document.getElementById("editActualQuantity").value =
    row.cells[3].textContent;
  document.getElementById("editSize").value = row.cells[4].textContent;
  let priceText = row.cells[5].textContent.replace(/[^\d.-]/g, "");
  document.getElementById("editPrice").value = priceText;
  document.getElementById("editItemModal").style.display = "block";
}

function showEditPriceModal() {
  document.getElementById("priceItemId").value =
    document.getElementById("editItemId").value;
  document.getElementById("newPrice").value =
    document.getElementById("editPrice").value;
  document.getElementById("editPriceModal").style.display = "block";
}

function showEditImageModal() {
  document.getElementById("imageItemId").value =
    document.getElementById("editItemId").value;
  document.getElementById("editImageModal").style.display = "block";
}

function submitEditPrice() {
  const itemId = document.getElementById("priceItemId").value;
  const newPrice = document.getElementById("newPrice").value;

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
        showMessage(`Updated price for item ${itemId} to ${newPrice}.`);
        closeModal("editPriceModal");
        document.getElementById("newPrice").value = "";
      } else {
        alert("Error updating price");
      }
    })
    .catch((error) => console.error("Error:", error));
}

function submitEditImage() {
  const itemId = document.getElementById("imageItemId").value;
  const fileInput = document.getElementById("editNewImage");

  if (!fileInput) {
    console.error("File input element not found");
    alert("System Error: Could not find file input element");
    return;
  }

  if (!itemId) {
    alert("Error: No item selected");
    return;
  }

  const newImage = fileInput.files[0];
  if (!newImage) {
    alert("Please select an image file");
    return;
  }

  // Validate file type
  const allowedTypes = ["image/jpeg", "image/png", "image/gif"];
  if (!allowedTypes.includes(newImage.type)) {
    alert("Invalid file type. Only JPG, PNG and GIF files are allowed.");
    return;
  }

  // Validate file size (5MB max)
  const maxSize = 5 * 1024 * 1024; // 5MB in bytes
  if (newImage.size > maxSize) {
    alert("File is too large. Maximum size is 5MB.");
    return;
  }

  const formData = new FormData();
  formData.append("itemId", itemId);
  formData.append("newImage", newImage);

  // Show loading indicator
  const saveButton = document.querySelector("#editImageModal .save-btn");
  const originalText = saveButton.textContent;
  saveButton.textContent = "Uploading...";
  saveButton.disabled = true;

  fetch("../PAMO Inventory backend/edit_image.php", {
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
      if (data.success) {
        alert("Image updated successfully!");
        closeModal("editImageModal");
        // Clear input field
        fileInput.value = "";
        // Reload the page to show the updated image
        location.reload();
      } else {
        throw new Error(
          data.message || "Unknown error occurred while updating image"
        );
      }
    })
    .catch((error) => {
      console.error("Error:", error);
      alert("An error occurred while updating the image: " + error.message);
    })
    .finally(() => {
      // Restore button state
      saveButton.textContent = originalText;
      saveButton.disabled = false;
    });
}

function updatePriceDisplay(itemId, newPrice) {
  const row = document.querySelector(`tr[data-item-code="${itemId}"]`);
  if (row) {
    const priceCell = row.cells[5];
    priceCell.textContent = `₱${parseFloat(newPrice).toFixed(2)}`;
  }
}

function showMessage(message) {
  const messageBox = document.createElement("div");
  messageBox.className = "message-box";
  messageBox.innerText = message;
  document.body.appendChild(messageBox);

  setTimeout(() => {
    messageBox.remove();
  }, 3000);
}

// Event Listeners
document.addEventListener("DOMContentLoaded", function () {
  document.getElementById("editBtn").addEventListener("click", handleEdit);
});
