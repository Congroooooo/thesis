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
  const itemId = document.getElementById("editItemId").value;
  const row = document.querySelector(`tr[data-item-code="${itemId}"]`);

  if (!row) {
    alert("Selected item not found.");
    return;
  }

  // Get the image path from the row data attribute
  const imagePath = row.getAttribute("data-image-path");

  // Set the item ID and current image path
  document.getElementById("imageItemId").value = itemId;
  document.getElementById("currentImagePath").value = imagePath || "";

  // Display current image
  const currentImageDisplay = document.getElementById("currentImageDisplay");
  const noCurrentImage = document.getElementById("noCurrentImage");

  if (imagePath && imagePath.trim() !== "") {
    currentImageDisplay.src = "../" + imagePath;
    currentImageDisplay.style.display = "block";
    noCurrentImage.style.display = "none";
  } else {
    currentImageDisplay.style.display = "none";
    noCurrentImage.style.display = "block";
  }

  // Clear any previous new image preview
  document.getElementById("editNewImage").value = "";
  document.getElementById("newImagePreview").style.display = "none";

  // Add event listener for new image preview
  const fileInput = document.getElementById("editNewImage");
  fileInput.onchange = function (e) {
    const file = e.target.files[0];
    if (file) {
      const reader = new FileReader();
      reader.onload = function (event) {
        document.getElementById("newImageDisplay").src = event.target.result;
        document.getElementById("newImagePreview").style.display = "block";
      };
      reader.readAsDataURL(file);
    } else {
      document.getElementById("newImagePreview").style.display = "none";
    }
  };

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

  // Basic client-side validation (matching Add New Item approach)
  const allowedTypes = ["image/jpeg", "image/png", "image/gif"];
  if (!allowedTypes.includes(newImage.type)) {
    alert("Invalid file type. Only JPG, PNG and GIF files are allowed.");
    return;
  }

  // Show loading indicator
  const saveButton = document.querySelector("#editImageModal .save-btn");
  const originalText = saveButton.textContent;

  try {
    // Compress large images (same as Add New Item)
    let processedFile = newImage;
    if (newImage.size > 1024 * 1024) {
      // 1MB
      saveButton.textContent = "Compressing...";
      saveButton.disabled = true;

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

    // Update loading indicator for upload
    saveButton.textContent = "Uploading...";

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
          alert(data.message || "Image updated successfully!");
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
  } catch (compressionError) {
    console.error("Error during compression:", compressionError);
    alert(
      "An error occurred while processing the image: " +
        compressionError.message
    );
    // Restore button state
    saveButton.textContent = originalText;
    saveButton.disabled = false;
  }
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
