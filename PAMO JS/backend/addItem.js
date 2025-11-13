let productCounter = 1;

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

function showAddItemModal() {
  document.getElementById("addItemModal").style.display = "flex";
  document.getElementById("addItemForm").reset();

  productCounter = 1;
  const productsContainer = document.getElementById("productsContainer");
  const productItems = productsContainer.querySelectorAll(".product-item");

  for (let i = 1; i < productItems.length; i++) {
    productItems[i].remove();
  }

  resetProduct(0);

  const removeBtn = document.querySelector(".remove-product-btn");
  if (removeBtn) removeBtn.style.display = "none";

  if (typeof loadCategories === "function") {
    loadCategories();
    setTimeout(() => {
      setupCategoryHandlerForProduct(0);
      if (typeof setupItemCodeHandlerForProduct === "function") {
        setupItemCodeHandlerForProduct(0);
      }
    }, 100);
  }
}

function resetProduct(productIndex) {
  const sizeCheckboxes = document.querySelectorAll(
    `.size-checkboxes[data-product="${productIndex}"] input[type="checkbox"]`
  );
  sizeCheckboxes.forEach((checkbox) => {
    checkbox.checked = false;
  });

  const sizeDetailsContainer = document.getElementById(
    `sizeDetailsContainer_${productIndex}`
  );
  const sizeDetailsList = document.getElementById(
    `sizeDetailsList_${productIndex}`
  );
  if (sizeDetailsContainer) sizeDetailsContainer.classList.remove("show");
  if (sizeDetailsList) sizeDetailsList.innerHTML = "";

  const subGroup = document.getElementById(`subcategoryGroup_${productIndex}`);
  if (subGroup) subGroup.style.display = "none";
  const subSel = document.getElementById(`subcategorySelect_${productIndex}`);
  if (subSel) {
    subSel.innerHTML = "";
  }
}

function addAnotherProduct() {
  const productsContainer = document.getElementById("productsContainer");
  const existingProducts = document.querySelectorAll(".product-item");
  const existingIndices = Array.from(existingProducts).map((item) =>
    parseInt(item.dataset.productIndex)
  );

  let productIndex = 0;
  while (existingIndices.includes(productIndex)) {
    productIndex++;
  }

  const productHtml = `
    <div class="product-item" data-product-index="${productIndex}">
      <div class="product-header">
        <h3>Product #${existingProducts.length + 1}</h3>
        <span class="remove-product-btn" onclick="removeProduct(${productIndex})">&times;</span>
      </div>
      
      <div class="product-basic-info">
        <h4>Product Information</h4>
        <div class="input-group">
          <label for="newProductItemCode_${productIndex}">Base Item Code (Prefix):</label>
          <input type="text" id="newProductItemCode_${productIndex}" name="products[${productIndex}][baseItemCode]" placeholder="e.g., USHPWV002" required maxlength="20">
          <small style="color: #666; font-size: 12px; margin-top: 4px; display: block;">Letters and numbers only (A-Z, 0-9). No special characters like -, !, ~, etc.</small>
        </div>
        <div class="input-group">
          <label for="newCategory_${productIndex}">Category:</label>
          <select id="newCategory_${productIndex}" name="products[${productIndex}][category_id]" required>
            <option value="">Select Category</option>
          </select>
          <small style="color: #666;">Categories are managed in <a href="settings.php" target="_blank" style="color: #007bff;">Settings → Manage Categories</a></small>
        </div>
        <div class="input-group" id="subcategoryGroup_${productIndex}" style="display:none;">
          <label for="subcategorySelect_${productIndex}">Subcategory:</label>
          <select id="subcategorySelect_${productIndex}" name="products[${productIndex}][subcategory_ids][]" multiple style="width:100%;">
          </select>
          <small style="color: #666;">Subcategories are managed in Settings if needed</small>
        </div>
        <div class="input-group">
          <label for="newItemName_${productIndex}">Product Name:</label>
          <input type="text" id="newItemName_${productIndex}" name="products[${productIndex}][newItemName]" required>
        </div>
        <div class="input-group">
          <label for="newImage_${productIndex}">Product Image:</label>
          <input type="file" id="newImage_${productIndex}" name="products[${productIndex}][newImage]" accept="image/*" required onchange="showFileInfo(this)">
          <small id="fileInfo_${productIndex}" class="file-info" style="color: #666; font-size: 12px; margin-top: 4px; display: block;"></small>
        </div>
      </div>

      <div class="size-selection-section">
        <h4>Size Selection</h4>
        <div class="input-group">
          <label>Select Sizes:</label>
          <div class="size-checkboxes" data-product="${productIndex}">
            <label class="checkbox-label"><input type="checkbox" value="XS" onchange="toggleSizeDetails(this, ${productIndex})"> XS</label>
            <label class="checkbox-label"><input type="checkbox" value="S" onchange="toggleSizeDetails(this, ${productIndex})"> S</label>
            <label class="checkbox-label"><input type="checkbox" value="M" onchange="toggleSizeDetails(this, ${productIndex})"> M</label>
            <label class="checkbox-label"><input type="checkbox" value="L" onchange="toggleSizeDetails(this, ${productIndex})"> L</label>
            <label class="checkbox-label"><input type="checkbox" value="XL" onchange="toggleSizeDetails(this, ${productIndex})"> XL</label>
            <label class="checkbox-label"><input type="checkbox" value="XXL" onchange="toggleSizeDetails(this, ${productIndex})"> XXL</label>
            <label class="checkbox-label"><input type="checkbox" value="3XL" onchange="toggleSizeDetails(this, ${productIndex})"> 3XL</label>
            <label class="checkbox-label"><input type="checkbox" value="4XL" onchange="toggleSizeDetails(this, ${productIndex})"> 4XL</label>
            <label class="checkbox-label"><input type="checkbox" value="5XL" onchange="toggleSizeDetails(this, ${productIndex})"> 5XL</label>
            <label class="checkbox-label"><input type="checkbox" value="6XL" onchange="toggleSizeDetails(this, ${productIndex})"> 6XL</label>
            <label class="checkbox-label"><input type="checkbox" value="7XL" onchange="toggleSizeDetails(this, ${productIndex})"> 7XL</label>
            <label class="checkbox-label"><input type="checkbox" value="One Size" onchange="toggleSizeDetails(this, ${productIndex})"> One Size</label>
          </div>
        </div>
      </div>

      <div id="sizeDetailsContainer_${productIndex}" class="size-details-container">
        <h4>Size Details</h4>
        <div id="sizeDetailsList_${productIndex}">
          <!-- Size-specific forms will be dynamically added here -->
        </div>
      </div>
    </div>
  `;

  productsContainer.insertAdjacentHTML("beforeend", productHtml);

  if (productIndex === 1) {
    const firstRemoveBtn = document.querySelector(".remove-product-btn");
    if (firstRemoveBtn) firstRemoveBtn.style.display = "block";
  }

  if (typeof loadCategories === "function") {
    loadCategoriesForProduct(productIndex);
  }

  if (typeof setupItemCodeHandlerForProduct === "function") {
    setupItemCodeHandlerForProduct(productIndex);
  }
}

function removeProduct(productIndex) {
  const productItem = document.querySelector(
    `[data-product-index="${productIndex}"]`
  );
  if (productItem) {
    productItem.remove();
  }

  updateProductNumbers();

  const productItems = document.querySelectorAll(".product-item");
  if (productItems.length === 1) {
    const firstRemoveBtn = document.querySelector(".remove-product-btn");
    if (firstRemoveBtn) firstRemoveBtn.style.display = "none";
  }
}

function updateProductNumbers() {
  const productItems = document.querySelectorAll(".product-item");
  productItems.forEach((item, index) => {
    const header = item.querySelector(".product-header h3");
    if (header) {
      header.textContent = `Product #${index + 1}`;
    }

    const oldIndex = item.dataset.productIndex;
    const newIndex = index;

    if (oldIndex !== newIndex.toString()) {
      updateProductIndexReferences(oldIndex, newIndex, item);
      item.dataset.productIndex = newIndex;
    }
  });
}

function updateProductIndexReferences(oldIndex, newIndex, productItem) {
  const elementsToUpdate = productItem.querySelectorAll(
    `[id*="_${oldIndex}"], [name*="[${oldIndex}]"], [onclick*="${oldIndex}"]`
  );

  elementsToUpdate.forEach((element) => {
    if (element.id) {
      element.id = element.id.replace(`_${oldIndex}`, `_${newIndex}`);
    }

    if (element.name) {
      element.name = element.name.replace(`[${oldIndex}]`, `[${newIndex}]`);
      element.name = element.name.replace(`_${oldIndex}`, `_${newIndex}`);
    }

    if (element.getAttribute("onclick")) {
      const onclickValue = element.getAttribute("onclick");
      element.setAttribute(
        "onclick",
        onclickValue.replace(`(${oldIndex})`, `(${newIndex})`)
      );
    }

    if (element.getAttribute("onchange")) {
      const onchangeValue = element.getAttribute("onchange");
      element.setAttribute(
        "onchange",
        onchangeValue.replace(`, ${oldIndex})`, `, ${newIndex})`)
      );
    }
  });

  const sizeCheckboxes = productItem.querySelector(".size-checkboxes");
  if (sizeCheckboxes) {
    sizeCheckboxes.dataset.product = newIndex;
  }

  const sizeDetailItems = productItem.querySelectorAll(".size-detail-item");
  sizeDetailItems.forEach((item) => {
    item.dataset.product = newIndex;
  });
}

function toggleSizeDetails(checkbox, productIndex) {
  const sizeDetailsContainer = document.getElementById(
    `sizeDetailsContainer_${productIndex}`
  );
  const sizeDetailsList = document.getElementById(
    `sizeDetailsList_${productIndex}`
  );

  if (checkbox.checked) {
    addSizeDetailForm(checkbox.value, productIndex);
    sortSizeDetails(productIndex);
    sizeDetailsContainer.classList.add("show");
  } else {
    removeSizeDetailForm(checkbox.value, productIndex);

    const checkedSizes = document.querySelectorAll(
      `.size-checkboxes[data-product="${productIndex}"] input[type="checkbox"]:checked`
    );
    if (checkedSizes.length === 0) {
      sizeDetailsContainer.classList.remove("show");
    }
  }
}

function addSizeDetailForm(size, productIndex) {
  const sizeDetailsList = document.getElementById(
    `sizeDetailsList_${productIndex}`
  );
  const baseItemCode =
    document.getElementById(`newProductItemCode_${productIndex}`).value ||
    "BASE";

  const sizeNumber = getSizeNumber(size);
  const generatedCode = `${baseItemCode}-${sizeNumber
    .toString()
    .padStart(3, "0")}`;

  const displayOrder = getSizeDisplayOrder(size);

  const sizeDetailHtml = `
    <div class="size-detail-item" data-size="${size}" data-size-order="${displayOrder}" data-product="${productIndex}">
      <div class="size-detail-header">
        <h4>Size: ${size}</h4>
      </div>
      <div class="size-detail-form">
        <div class="input-group size-field-item-code">
          <label>Item Code:</label>
          <div class="generated-code">${generatedCode}</div>
        </div>
        <div class="input-group size-field-stock">
          <label for="quantity_${productIndex}_${size}">Initial Stock:</label>
          <input type="number" id="quantity_${productIndex}_${size}" name="products[${productIndex}][sizes][${size}][quantity]" min="0" required>
        </div>
        <div class="input-group size-field-price">
          <label for="price_${productIndex}_${size}">Price (₱):</label>
          <input type="number" id="price_${productIndex}_${size}" name="products[${productIndex}][sizes][${size}][price]" min="0" step="0.01" required>
        </div>
      </div>
      <input type="hidden" name="products[${productIndex}][sizes][${size}][item_code]" value="${generatedCode}">
      <input type="hidden" name="products[${productIndex}][sizes][${size}][size]" value="${size}">
    </div>
  `;

  sizeDetailsList.insertAdjacentHTML("beforeend", sizeDetailHtml);
}

function sortSizeDetails(productIndex) {
  const sizeDetailsList = document.getElementById(
    `sizeDetailsList_${productIndex}`
  );
  const sizeItems = Array.from(
    sizeDetailsList.querySelectorAll(".size-detail-item")
  );

  sizeItems.sort((a, b) => {
    const orderA = parseInt(a.dataset.sizeOrder);
    const orderB = parseInt(b.dataset.sizeOrder);
    return orderA - orderB;
  });

  sizeDetailsList.innerHTML = "";
  sizeItems.forEach((item) => {
    sizeDetailsList.appendChild(item);
  });
}

function removeSizeDetailForm(size, productIndex) {
  const sizeDetailItem = document.querySelector(
    `[data-size="${size}"][data-product="${productIndex}"]`
  );
  if (sizeDetailItem) {
    sizeDetailItem.remove();
  }
}

function getSizeNumber(size) {
  // Normalize the size by trimming whitespace
  const normalizedSize = size ? size.trim() : size;

  const sizeMap = {
    "One Size": 0,
    XS: 1,
    S: 2,
    M: 3,
    L: 4,
    XL: 5,
    XXL: 6,
    "3XL": 7,
    "4XL": 8,
    "5XL": 9,
    "6XL": 10,
    "7XL": 11,
  };

  return sizeMap[normalizedSize] !== undefined ? sizeMap[normalizedSize] : 999;
}

function getSizeDisplayOrder(size) {
  const displayOrderMap = {
    "One Size": 0,
    XS: 1,
    S: 2,
    M: 3,
    L: 4,
    XL: 5,
    XXL: 6,
    "3XL": 7,
    "4XL": 8,
    "5XL": 9,
    "6XL": 10,
    "7XL": 11,
  };
  return displayOrderMap[size] || 999;
}

function updateGeneratedCodes(productIndex) {
  const baseItemCode =
    document.getElementById(`newProductItemCode_${productIndex}`).value ||
    "BASE";
  const sizeDetails = document.querySelectorAll(
    `[data-product="${productIndex}"].size-detail-item`
  );

  sizeDetails.forEach((item) => {
    const size = item.dataset.size;
    const sizeNumber = getSizeNumber(size);
    const generatedCode = `${baseItemCode}-${sizeNumber
      .toString()
      .padStart(3, "0")}`;

    const codeSpan = item.querySelector(".generated-code");
    if (codeSpan) codeSpan.textContent = `Code: ${generatedCode}`;

    const hiddenInput = item.querySelector(
      `input[name="products[${productIndex}][sizes][${size}][item_code]"]`
    );
    if (hiddenInput) hiddenInput.value = generatedCode;
  });
}

// Show file information when file is selected
function showFileInfo(input) {
  const productIndex = input.closest(".product-item").dataset.productIndex;
  const infoElement = document.getElementById(`fileInfo_${productIndex}`);

  if (input.files.length > 0) {
    const file = input.files[0];
    const sizeMB = (file.size / 1024 / 1024).toFixed(1);

    if (infoElement) {
      // Remove existing classes
      infoElement.classList.remove("large-file", "good-size");

      if (file.size > 5 * 1024 * 1024) {
        // > 5MB
        infoElement.innerHTML = `⚠️ ${file.name} (${sizeMB}MB) - Too large! Maximum 5MB allowed.`;
        infoElement.classList.add("large-file");
      } else if (file.size > 1024 * 1024) {
        // > 1MB
        infoElement.innerHTML = `📷 ${file.name} (${sizeMB}MB) - Will be compressed before upload`;
        infoElement.style.color = "#f39c12";
      } else {
        infoElement.innerHTML = `✅ ${file.name} (${sizeMB}MB) - Perfect size!`;
        infoElement.classList.add("good-size");
      }
    }
  } else if (infoElement) {
    infoElement.innerHTML = "";
    infoElement.classList.remove("large-file", "good-size");
  }
}

// Make showFileInfo globally available
window.showFileInfo = showFileInfo;

// Validate base item code - only alphanumeric characters allowed
function validateBaseItemCode(code) {
  // Allow only letters (A-Z, a-z) and numbers (0-9)
  const alphanumericRegex = /^[A-Za-z0-9]*$/;
  return alphanumericRegex.test(code);
}

// Clean base item code by removing invalid characters
function cleanBaseItemCode(code) {
  // Remove any non-alphanumeric characters
  return code.replace(/[^A-Za-z0-9]/g, "");
}

// Show validation styling for base item code (without messages)
function showBaseItemCodeValidation(input, isValid, message = "") {
  // Remove existing classes
  input.classList.remove("valid-base-code", "invalid-base-code");

  if (isValid) {
    input.classList.add("valid-base-code");
  } else {
    input.classList.add("invalid-base-code");
  }
}

// Helper function to compress images
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

// Validate and prepare files for upload
async function validateAndPrepareFiles(form) {
  const fileInputs = form.querySelectorAll('input[type="file"]');
  const maxFileSize = 5 * 1024 * 1024; // 5MB per file
  const processedFiles = new Map();

  for (let input of fileInputs) {
    if (input.files.length > 0) {
      const file = input.files[0];

      // Check file type
      if (!file.type.startsWith("image/")) {
        throw new Error(`File "${file.name}" is not a valid image file.`);
      }

      // Update file info to show processing status
      const productIndex = input.closest(".product-item").dataset.productIndex;
      const infoElement = document.getElementById(`fileInfo_${productIndex}`);

      // Compress large images
      let processedFile = file;
      if (file.size > 1024 * 1024) {
        // 1MB
        if (infoElement) {
          infoElement.innerHTML = `🔄 Compressing ${file.name}...`;
          infoElement.className = "file-info processing-images";
        }

        console.log(
          `Compressing image: ${file.name} (${(file.size / 1024 / 1024).toFixed(
            1
          )}MB)`
        );
        processedFile = await compressImage(file);
        console.log(
          `Compressed to: ${(processedFile.size / 1024 / 1024).toFixed(1)}MB`
        );

        if (infoElement) {
          const newSizeMB = (processedFile.size / 1024 / 1024).toFixed(1);
          infoElement.innerHTML = `✅ ${file.name} compressed to ${newSizeMB}MB`;
          infoElement.className = "file-info good-size";
        }
      }

      if (processedFile.size > maxFileSize) {
        throw new Error(
          `Image "${file.name}" is still too large after compression. Please use a smaller image.`
        );
      }

      processedFiles.set(input.name, processedFile);
    }
  }

  return processedFiles;
}

async function submitNewItem(event) {
  event.preventDefault();
  event.stopPropagation();

  const form = document.getElementById("addItemForm");
  const formData = new FormData(form);

  const deliveryOrderNumber = formData.get("deliveryOrderNumber");
  if (!deliveryOrderNumber) {
    showErrorMessage("Please enter a delivery order number");
    return false;
  }

  // Show loading state on button
  const submitButton =
    form.querySelector('button[type="submit"]') ||
    document.querySelector('button[form="addItemForm"]');
  const originalButtonText = submitButton ? submitButton.innerHTML : "";
  if (submitButton) {
    submitButton.innerHTML = "Adding All Products...";
    submitButton.disabled = true;
    submitButton.style.cursor = "not-allowed";
    submitButton.style.opacity = "0.7";
  }

  // Validate and prepare files
  let processedFiles;
  try {
    processedFiles = await validateAndPrepareFiles(form);
  } catch (error) {
    showErrorMessage(error.message);
    // Restore button state
    if (submitButton) {
      submitButton.innerHTML = originalButtonText;
      submitButton.disabled = false;
      submitButton.style.cursor = "pointer";
      submitButton.style.opacity = "1";
    }
    return;
  }

  const productContainers = document.querySelectorAll(".product-item");
  if (productContainers.length === 0) {
    showErrorMessage("No products found to add");
    // Restore button state
    if (submitButton) {
      submitButton.innerHTML = originalButtonText;
      submitButton.disabled = false;
      submitButton.style.cursor = "pointer";
      submitButton.style.opacity = "1";
    }
    return;
  }

  let allProductsValid = true;
  productContainers.forEach((container, index) => {
    const productIndex = container.dataset.productIndex;

    const baseItemCode = document.getElementById(
      `newProductItemCode_${productIndex}`
    ).value;
    const categoryId = document.getElementById(
      `newCategory_${productIndex}`
    ).value;
    const itemName = document.getElementById(
      `newItemName_${productIndex}`
    ).value;

    if (!baseItemCode || !categoryId || !itemName) {
      showErrorMessage(
        `Please fill in all required basic information for product ${
          parseInt(productIndex) + 1
        }`
      );
      allProductsValid = false;
      return;
    }

    // Validate base item code format
    if (!validateBaseItemCode(baseItemCode)) {
      showErrorMessage(
        `Invalid base item code for product ${
          parseInt(productIndex) + 1
        }. Only letters and numbers are allowed (no special characters like -, !, ~, etc.)`
      );
      allProductsValid = false;
      return;
    }

    // Validate base item code length
    if (baseItemCode.length < 3) {
      showErrorMessage(
        `Base item code for product ${
          parseInt(productIndex) + 1
        } should be at least 3 characters long`
      );
      allProductsValid = false;
      return;
    }

    const checkedSizes = container.querySelectorAll(
      '.size-checkboxes input[type="checkbox"]:checked'
    );
    if (checkedSizes.length === 0) {
      showErrorMessage(
        `Please select at least one size for product ${
          parseInt(productIndex) + 1
        }`
      );
      allProductsValid = false;
      return;
    }

    checkedSizes.forEach((checkbox) => {
      const size = checkbox.value;
      const priceInput = document.getElementById(
        `price_${productIndex}_${size}`
      );
      const quantityInput = document.getElementById(
        `quantity_${productIndex}_${size}`
      );

      if (!priceInput.value || parseFloat(priceInput.value) <= 0) {
        showErrorMessage(
          `Please enter a valid price for size ${size} in product ${
            parseInt(productIndex) + 1
          }`
        );
        allProductsValid = false;
        return;
      }

      if (!quantityInput.value || parseInt(quantityInput.value) < 0) {
        showErrorMessage(
          `Please enter a valid initial stock for size ${size} in product ${
            parseInt(productIndex) + 1
          }`
        );
        allProductsValid = false;
        return;
      }
    });

    const shirtTypeGroup = document.getElementById(
      `shirtTypeGroup_${productIndex}`
    );
    if (shirtTypeGroup && shirtTypeGroup.style.display !== "none") {
      const shirtTypeSelect = document.getElementById(
        `shirtTypeSelect_${productIndex}`
      );
      const shirtTypeValue = shirtTypeSelect.value;
      if (shirtTypeValue) {
        const courseSelect = document.getElementById(
          `courseSelect_${productIndex}`
        );
        if (courseSelect) {
          for (let i = 0; i < courseSelect.options.length; i++) {
            if (courseSelect.options[i].text === shirtTypeValue) {
              formData.append(
                `products[${productIndex}][course_id][]`,
                courseSelect.options[i].value
              );
              break;
            }
          }
        }
      }
    }
  });

  if (!allProductsValid) {
    // Restore button state
    if (submitButton) {
      submitButton.innerHTML = originalButtonText;
      submitButton.disabled = false;
      submitButton.style.cursor = "pointer";
      submitButton.style.opacity = "1";
    }
    return false;
  }

  // Replace files in formData with compressed versions
  for (let [fieldName, compressedFile] of processedFiles) {
    formData.delete(fieldName);
    formData.append(fieldName, compressedFile);
  }

  const xhr = new XMLHttpRequest();
  xhr.open("POST", "../PAMO Inventory backend/add_item.php", true);
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
          const data = JSON.parse(xhr.responseText);
          if (data.success) {
            // Reset the form
            form.reset();

            // Reset product counter and remove extra products
            const productsContainer =
              document.getElementById("productsContainer");
            const productItems =
              productsContainer.querySelectorAll(".product-item");
            for (let i = 1; i < productItems.length; i++) {
              productItems[i].remove();
            }
            resetProduct(0);

            // Close the modal
            closeModal("addItemModal");

            // Show success message
            showSuccessMessage(
              "New product(s) added successfully! Refreshing inventory..."
            );

            // Refresh the inventory table without reloading the page
            setTimeout(() => {
              refreshInventoryTable();
            }, 800);
          } else {
            throw new Error(data.message || "Unknown error");
          }
        } catch (e) {
          console.error("Parse error:", e);
          showErrorMessage(
            "Error adding product: " + (e.message || xhr.responseText)
          );
        }
      } else if (xhr.status === 413) {
        showErrorMessage(
          "Files are too large. Please use smaller images (under 5MB each)."
        );
      } else {
        showErrorMessage("Error: " + xhr.statusText);
      }
    }
  };

  xhr.send(formData);
  return false;
}

window.loadCategoriesForProduct = function (productIndex) {
  fetch("../PAMO Inventory backend/api_categories_list.php")
    .then(async (r) => {
      if (!r.ok) throw new Error(await r.text());
      return r.json();
    })
    .then((list) => {
      const sel = document.getElementById(`newCategory_${productIndex}`);
      if (!sel) return;
      const current = sel.value;
      sel.innerHTML = '<option value="">Select Category</option>';
      list.forEach((c) => {
        const opt = document.createElement("option");
        opt.value = String(c.id);
        opt.textContent = c.name;
        opt.dataset.has = String(c.has_subcategories);
        sel.appendChild(opt);
      });
      sel.appendChild(new Option("+ Add new category…", "__add__"));
      if (current) sel.value = current;

      setupCategoryHandlerForProduct(productIndex);
    })
    .catch((err) => {
      console.error(
        "Failed to load categories for product",
        productIndex,
        ":",
        err
      );
    });
};

window.setupCategoryHandlerForProduct = function (productIndex) {
  const catSel = document.getElementById(`newCategory_${productIndex}`);
  const subGroup = document.getElementById(`subcategoryGroup_${productIndex}`);

  if (catSel) {
    catSel.removeEventListener("change", catSel._categoryHandler);

    catSel._categoryHandler = async function () {
      if (this.value === "__add__") {
        const res = await promptNewCategory();
        if (res && res.success) {
          loadCategoriesForProduct(productIndex);
          catSel.value = String(res.id);
          loadSubcategoriesForProduct(res.id, productIndex);
        } else {
          this.value = "";
        }
        return;
      }
      const has = this.options[this.selectedIndex]?.dataset?.has === "1";
      if (has) {
        loadSubcategoriesForProduct(this.value, productIndex);
      } else if (subGroup) {
        subGroup.style.display = "none";
      }
    };

    catSel.addEventListener("change", catSel._categoryHandler);
  }

  const subSel = document.getElementById(`subcategorySelect_${productIndex}`);
  if (window.jQuery && subSel && $(subSel).length) {
    $(subSel)
      .select2({
        placeholder: "Select subcategory",
        allowClear: true,
        width: "100%",
      })
      .on("select2:select", async function (e) {
        if (e.params.data.id === "__add__") {
          const currentVals = $(this).val() || [];
          const filteredVals = currentVals.filter((v) => v !== "__add__");
          $(this).val(filteredVals).trigger("change.select2");

          const catId = catSel?.value;
          if (!catId || catId === "__add__") {
            alert("Select a category first");
            return;
          }

          const res = await promptNewSubcategory(catId);
          if (res && res.success) {
            await loadSubcategoriesForProduct(catId, productIndex);
            const newSelection = [...filteredVals, String(res.id)];
            $(subSel).val(newSelection).trigger("change.select2");
          }
        }
      });
  }
};

window.setupItemCodeHandlerForProduct = function (productIndex) {
  const itemCodeInput = document.getElementById(
    `newProductItemCode_${productIndex}`
  );
  if (itemCodeInput) {
    itemCodeInput.addEventListener("input", (e) => {
      const originalValue = e.target.value;
      const cleanValue = cleanBaseItemCode(originalValue);

      // If the input had invalid characters, replace with clean value
      if (originalValue !== cleanValue) {
        e.target.value = cleanValue;
      }

      // Validate the current value
      const isValid = validateBaseItemCode(e.target.value);

      if (e.target.value === "") {
        // Clear validation when empty
        const validationElement = document.getElementById(
          `baseCodeValidation_${productIndex}`
        );
        if (validationElement) {
          validationElement.innerHTML = "";
          e.target.style.borderColor = "";
        }
      } else if (!isValid) {
        showBaseItemCodeValidation(e.target, false);
      } else if (e.target.value.length < 3) {
        showBaseItemCodeValidation(
          e.target,
          false,
          "⚠️ Base item code should be at least 3 characters"
        );
      } else {
        showBaseItemCodeValidation(e.target, true);
      }

      updateGeneratedCodes(productIndex);
    });

    // Also validate on blur (when user clicks away)
    itemCodeInput.addEventListener("blur", (e) => {
      if (e.target.value && e.target.value.length < 3) {
        showBaseItemCodeValidation(
          e.target,
          false,
          "⚠️ Base item code should be at least 3 characters"
        );
      }
    });
  }
};

function loadSubcategoriesForProduct(categoryId, productIndex) {
  const group = document.getElementById(`subcategoryGroup_${productIndex}`);
  const select = document.getElementById(`subcategorySelect_${productIndex}`);
  if (!group || !select) return;
  if (!categoryId) {
    group.style.display = "none";
    return;
  }
  fetch(
    `../PAMO Inventory backend/api_subcategories_list.php?category_id=${encodeURIComponent(
      categoryId
    )}`
  )
    .then(async (r) => {
      if (!r.ok) throw new Error(await r.text());
      return r.json();
    })
    .then((list) => {
      select.innerHTML = "";
      list.forEach((sc) => {
        const opt = document.createElement("option");
        opt.value = String(sc.id);
        opt.textContent = sc.name;
        select.appendChild(opt);
      });
      group.style.display = "block";
      if (window.jQuery && $(select).length) {
        $(select).trigger("change.select2");
      }
    })
    .catch((err) => {
      console.error(
        "Failed to load subcategories for product",
        productIndex,
        ":",
        err
      );
      if (group) group.style.display = "none";
    });
}

document.addEventListener("DOMContentLoaded", function () {
  window.loadCategories = function () {
    fetch("../PAMO Inventory backend/api_categories_list.php")
      .then(async (r) => {
        if (!r.ok) throw new Error(await r.text());
        return r.json();
      })
      .then((list) => {
        const sel = document.getElementById("newCategory");
        if (sel) {
          const current = sel.value;
          sel.innerHTML = '<option value="">Select Category</option>';
          list.forEach((c) => {
            const opt = document.createElement("option");
            opt.value = String(c.id);
            opt.textContent = c.name;
            opt.dataset.has = String(c.has_subcategories);
            sel.appendChild(opt);
          });
          if (current) sel.value = current;
        }

        const sel0 = document.getElementById("newCategory_0");
        if (sel0) {
          const current0 = sel0.value;
          sel0.innerHTML = '<option value="">Select Category</option>';
          list.forEach((c) => {
            const opt = document.createElement("option");
            opt.value = String(c.id);
            opt.textContent = c.name;
            opt.dataset.has = String(c.has_subcategories);
            sel0.appendChild(opt);
          });
          if (current0) sel0.value = current0;
        }
      })
      .catch((err) => {
        console.error("Failed to load categories:", err);
      });
  };

  window.loadCategoriesForProduct = function (productIndex) {
    fetch("../PAMO Inventory backend/api_categories_list.php")
      .then(async (r) => {
        if (!r.ok) throw new Error(await r.text());
        return r.json();
      })
      .then((list) => {
        const sel = document.getElementById(`newCategory_${productIndex}`);
        if (!sel) return;
        const current = sel.value;
        sel.innerHTML = '<option value="">Select Category</option>';
        list.forEach((c) => {
          const opt = document.createElement("option");
          opt.value = String(c.id);
          opt.textContent = c.name;
          opt.dataset.has = String(c.has_subcategories);
          sel.appendChild(opt);
        });
        if (current) sel.value = current;

        setupCategoryHandlerForProduct(productIndex);
      })
      .catch((err) => {
        console.error(
          "Failed to load categories for product",
          productIndex,
          ":",
          err
        );
      });
  };

  function loadSubcategories(categoryId) {
    const group = document.getElementById("subcategoryGroup");
    const select = document.getElementById("subcategorySelect");
    if (!group || !select) return;
    if (!categoryId) {
      group.style.display = "none";
      return;
    }
    fetch(
      `../PAMO Inventory backend/api_subcategories_list.php?category_id=${encodeURIComponent(
        categoryId
      )}`
    )
      .then(async (r) => {
        if (!r.ok) throw new Error(await r.text());
        return r.json();
      })
      .then((list) => {
        select.innerHTML = "";
        list.forEach((sc) => {
          const opt = document.createElement("option");
          opt.value = String(sc.id);
          opt.textContent = sc.name;
          select.appendChild(opt);
        });

        group.style.display = "block";
        if (window.jQuery && $(select).length) {
          $(select).trigger("change.select2");
        }
      })
      .catch((err) => console.error("Failed to load subcategories:", err));
  }

  function loadSubcategoriesForProduct(categoryId, productIndex) {
    const group = document.getElementById(`subcategoryGroup_${productIndex}`);
    const select = document.getElementById(`subcategorySelect_${productIndex}`);
    if (!group || !select) return;
    if (!categoryId) {
      group.style.display = "none";
      return;
    }
    fetch(
      `../PAMO Inventory backend/api_subcategories_list.php?category_id=${encodeURIComponent(
        categoryId
      )}`
    )
      .then(async (r) => {
        if (!r.ok) throw new Error(await r.text());
        return r.json();
      })
      .then((list) => {
        select.innerHTML = "";
        list.forEach((sc) => {
          const opt = document.createElement("option");
          opt.value = String(sc.id);
          opt.textContent = sc.name;
          select.appendChild(opt);
        });

        group.style.display = "block";
        if (window.jQuery && $(select).length) {
          $(select).trigger("change.select2");
        }
      })
      .catch((err) => {
        console.error(
          "Failed to load subcategories for product",
          productIndex,
          ":",
          err
        );
        if (group) group.style.display = "none";
      });
  }

  function setupCategoryHandlerForProduct(productIndex) {
    const catSel = document.getElementById(`newCategory_${productIndex}`);
    const subGroup = document.getElementById(
      `subcategoryGroup_${productIndex}`
    );

    if (catSel) {
      catSel.removeEventListener("change", catSel._categoryHandler);

      catSel._categoryHandler = function () {
        const has = this.options[this.selectedIndex]?.dataset?.has === "1";
        if (has && this.value) {
          loadSubcategoriesForProduct(this.value, productIndex);
        } else if (subGroup) {
          subGroup.style.display = "none";
        }
      };

      catSel.addEventListener("change", catSel._categoryHandler);
    }

    const subSel = document.getElementById(`subcategorySelect_${productIndex}`);
    if (window.jQuery && subSel && $(subSel).length) {
      $(subSel).select2({
        placeholder: "Select subcategory",
        allowClear: true,
        width: "100%",
      });
    }
  }

  loadCategories();

  const catSel = document.getElementById("newCategory");
  const subSel = document.getElementById("subcategorySelect");
  const subGroup = document.getElementById("subcategoryGroup");

  if (window.jQuery && $("#subcategorySelect").length) {
    $("#subcategorySelect").select2({
      placeholder: "Select subcategory",
      allowClear: true,
      width: "100%",
    });
  }

  if (catSel) {
    catSel.addEventListener("change", function () {
      const has = this.options[this.selectedIndex]?.dataset?.has === "1";
      if (has && this.value) {
        loadSubcategories(this.value);
      } else if (subGroup) {
        subGroup.style.display = "none";
      }
    });
  }

  const baseItemCodeInput = document.getElementById("newProductItemCode");
  if (baseItemCodeInput) {
    baseItemCodeInput.addEventListener("input", (e) => {
      const originalValue = e.target.value;
      const cleanValue = cleanBaseItemCode(originalValue);

      // If the input had invalid characters, replace with clean value
      if (originalValue !== cleanValue) {
        e.target.value = cleanValue;
      }

      // Validate the current value
      const isValid = validateBaseItemCode(e.target.value);

      if (e.target.value === "") {
        // Clear validation when empty
        const validationElement = document.getElementById(
          "baseCodeValidation_0"
        );
        if (validationElement) {
          validationElement.innerHTML = "";
          e.target.style.borderColor = "";
        }
      } else if (!isValid) {
        showBaseItemCodeValidation(e.target, false);
      } else if (e.target.value.length < 3) {
        showBaseItemCodeValidation(
          e.target,
          false,
          "⚠️ Base item code should be at least 3 characters"
        );
      } else {
        showBaseItemCodeValidation(e.target, true);
      }

      updateGeneratedCodes(0);
    });

    // Also validate on blur (when user clicks away)
    baseItemCodeInput.addEventListener("blur", (e) => {
      if (e.target.value && e.target.value.length < 3) {
        showBaseItemCodeValidation(
          e.target,
          false,
          "⚠️ Base item code should be at least 3 characters"
        );
      }
    });
  }

  window.setupItemCodeHandlerForProduct = function (productIndex) {
    const itemCodeInput = document.getElementById(
      `newProductItemCode_${productIndex}`
    );
    if (itemCodeInput) {
      itemCodeInput.addEventListener("input", (e) => {
        const originalValue = e.target.value;
        const cleanValue = cleanBaseItemCode(originalValue);

        // If the input had invalid characters, replace with clean value
        if (originalValue !== cleanValue) {
          e.target.value = cleanValue;
        }

        // Validate the current value
        const isValid = validateBaseItemCode(e.target.value);

        if (e.target.value === "") {
          // Clear validation when empty
          const validationElement = document.getElementById(
            `baseCodeValidation_${productIndex}`
          );
          if (validationElement) {
            validationElement.innerHTML = "";
            e.target.style.borderColor = "";
          }
        } else if (!isValid) {
          showBaseItemCodeValidation(e.target, false);
        } else if (e.target.value.length < 3) {
          showBaseItemCodeValidation(
            e.target,
            false,
            "⚠️ Base item code should be at least 3 characters"
          );
        } else {
          showBaseItemCodeValidation(e.target, true);
        }

        updateGeneratedCodes(productIndex);
      });

      // Also validate on blur (when user clicks away)
      itemCodeInput.addEventListener("blur", (e) => {
        if (e.target.value && e.target.value.length < 3) {
          showBaseItemCodeValidation(
            e.target,
            false,
            "⚠️ Base item code should be at least 3 characters"
          );
        }
      });
    }
  };

  if (window.jQuery && $("#courseSelect").length) {
    $("#courseSelect").select2({
      placeholder: "Select course(s)",
      allowClear: true,
      width: "100%",
    });
  }
  if (window.jQuery && $("#shirtTypeSelect").length) {
    $("#shirtTypeSelect").select2({
      placeholder: "Select shirt type",
      allowClear: true,
    });
  }

  const categorySelect = document.getElementById("newCategory");
  if (categorySelect) {
    categorySelect.addEventListener("change", function () {
      const courseGroup = document.getElementById("courseGroup");
      const shirtTypeGroup = document.getElementById("shirtTypeGroup");
      if (courseGroup) courseGroup.style.display = "none";
      if (shirtTypeGroup) shirtTypeGroup.style.display = "none";
    });
  }
});

/**
 * Refresh the inventory table without reloading the entire page
 * Fetches updated HTML for the table and updates the DOM
 */
function refreshInventoryTable() {
  // Check if fetchAllInventory function exists (from inventory.js)
  // This function reloads inventory data and maintains current pagination state
  if (typeof fetchAllInventory === "function") {
    // Show a subtle loading indicator on the table
    const inventoryTable = document.querySelector(".inventory-table");
    if (inventoryTable) {
      inventoryTable.style.opacity = "0.6";
      inventoryTable.style.pointerEvents = "none";
    }

    // Call fetchAllInventory to reload data with current filters and page
    fetchAllInventory()
      .then(() => {
        // Restore table state
        if (inventoryTable) {
          inventoryTable.style.opacity = "1";
          inventoryTable.style.pointerEvents = "auto";
        }

        // Refresh Select2 dropdowns to include newly added items
        refreshSelect2Dropdowns();

        // Update the success message to show completion
        showSuccessMessage("Inventory updated successfully!");
      })
      .catch((error) => {
        console.error("Error refreshing inventory:", error);
        // Restore table state
        if (inventoryTable) {
          inventoryTable.style.opacity = "1";
          inventoryTable.style.pointerEvents = "auto";
        }
        // Show error but don't fail completely
        showErrorMessage(
          "Could not refresh inventory. Please refresh the page manually."
        );
      });
  } else {
    // Fallback: reload the page if fetchAllInventory is not available
    console.warn("fetchAllInventory function not found, reloading page...");
    location.reload();
  }
}

/**
 * Refresh all Select2 dropdowns that display inventory items
 * This ensures newly added items appear in dropdowns without page reload
 */
function refreshSelect2Dropdowns() {
  // Refresh Add Item Size modal dropdowns
  refreshAddItemSizeDropdowns();

  // Refresh Restock Item (Add Quantity) modal dropdowns
  refreshAddQuantityDropdowns();

  // Refresh Exchange Item modal dropdowns
  refreshExchangeItemDropdowns();

  // Refresh Remove Stocks modal dropdowns
  refreshRemoveItemDropdowns();

  // Refresh Generate Walk-in Payable Slip modal dropdowns
  refreshPayableSlipDropdowns();
}
/**
 * Refresh the Add Item Size modal Select2 dropdowns
 */
function refreshAddItemSizeDropdowns() {
  const itemSizeSelects = document.querySelectorAll(
    "#addItemSizeModal .item-size-select"
  );

  if (itemSizeSelects.length === 0) return;

  // Fetch the updated page to get current inventory options
  fetch(window.location.href, {
    method: "GET",
    headers: {
      "X-Requested-With": "XMLHttpRequest",
    },
  })
    .then((response) => response.text())
    .then((html) => {
      const parser = new DOMParser();
      const doc = parser.parseFromString(html, "text/html");

      // Find the select element in the refreshed HTML to get updated options
      const newSelect = doc.querySelector(
        "#addItemSizeModal .item-size-select"
      );

      if (newSelect && newSelect.options) {
        // Update each existing select with new options
        itemSizeSelects.forEach((select) => {
          const $select = $(select);
          const currentValue = $select.val(); // Store current selection

          // Destroy Select2 to update options
          if ($select.hasClass("select2-hidden-accessible")) {
            $select.select2("destroy");
          }

          // Clear existing options
          $select.empty();

          // Copy options from the refreshed HTML
          Array.from(newSelect.options).forEach((option) => {
            const newOption = new Option(
              option.text,
              option.value,
              false,
              false
            );
            // Copy data attributes
            if (option.dataset.name) {
              newOption.dataset.name = option.dataset.name;
            }
            if (option.dataset.category) {
              newOption.dataset.category = option.dataset.category;
            }
            $select.append(newOption);
          });

          // Restore selection if the item still exists
          if (
            currentValue &&
            $select.find(`option[value="${currentValue}"]`).length > 0
          ) {
            $select.val(currentValue);
          }

          // Reinitialize Select2
          $select.select2({
            placeholder: "Search and select an item...",
            allowClear: true,
            width: "100%",
            dropdownParent: $("#addItemSizeModal"),
            templateResult: function (data) {
              if (data.loading) {
                return data.text;
              }

              var $container = $(
                '<div class="select2-result-item">' +
                  '<div class="item-name">' +
                  data.text +
                  "</div>" +
                  "</div>"
              );
              return $container;
            },
          });

          // Reattach change event
          $select.off("change").on("change", function () {
            const itemIndex = this.id.split("_")[1];
            if (typeof fetchAndUpdateSizesForItem === "function") {
              fetchAndUpdateSizesForItem(parseInt(itemIndex));
            }
          });
        });

        console.log("Add Item Size dropdowns refreshed successfully");
      }
    })
    .catch((error) => {
      console.warn("Could not refresh Add Item Size dropdowns:", error);
    });
}

/**
 * Refresh the Restock Item (Add Quantity) modal Select2 dropdowns
 */
function refreshAddQuantityDropdowns() {
  const productSelects = document.querySelectorAll(
    "#addQuantityModal select[name='itemId[]']"
  );

  if (productSelects.length === 0) return;

  fetch(window.location.href, {
    method: "GET",
    headers: {
      "X-Requested-With": "XMLHttpRequest",
    },
  })
    .then((response) => response.text())
    .then((html) => {
      const parser = new DOMParser();
      const doc = parser.parseFromString(html, "text/html");

      const newSelect = doc.querySelector(
        "#addQuantityModal select[name='itemId[]']"
      );

      if (newSelect && newSelect.options) {
        productSelects.forEach((select) => {
          const $select = $(select);
          const currentValue = $select.val();

          if ($select.hasClass("select2-hidden-accessible")) {
            $select.select2("destroy");
          }

          $select.empty();
          Array.from(newSelect.options).forEach((option) => {
            const newOption = new Option(
              option.text,
              option.value,
              false,
              false
            );
            $select.append(newOption);
          });

          if (
            currentValue &&
            $select.find(`option[value="${currentValue}"]`).length > 0
          ) {
            $select.val(currentValue);
          }

          // Reinitialize Select2 with proper configuration
          $select.select2({
            placeholder: "Select Product",
            allowClear: true,
            width: "100%",
          });
        });

        console.log("Restock Item dropdowns refreshed successfully");
      }
    })
    .catch((error) => {
      console.warn("Could not refresh Restock Item dropdowns:", error);
    });
}

/**
 * Refresh the Exchange Item modal Select2 dropdowns
 */
function refreshExchangeItemDropdowns() {
  // Exchange modal has customer select and item selects
  // We only need to refresh item selects, not customer select
  const itemSelects = document.querySelectorAll(
    "#exchangeItemModal select[id^='returnItem'], #exchangeItemModal select[id^='replacementItem']"
  );

  if (itemSelects.length === 0) return;

  fetch(window.location.href, {
    method: "GET",
    headers: {
      "X-Requested-With": "XMLHttpRequest",
    },
  })
    .then((response) => response.text())
    .then((html) => {
      const parser = new DOMParser();
      const doc = parser.parseFromString(html, "text/html");

      itemSelects.forEach((select) => {
        const selectId = select.id;
        const newSelect = doc.getElementById(selectId);

        if (newSelect && newSelect.options) {
          const $select = $(select);
          const currentValue = $select.val();

          if ($select.hasClass("select2-hidden-accessible")) {
            $select.select2("destroy");
          }

          $select.empty();
          Array.from(newSelect.options).forEach((option) => {
            const newOption = new Option(
              option.text,
              option.value,
              false,
              false
            );
            if (option.dataset.category) {
              newOption.dataset.category = option.dataset.category;
            }
            if (option.dataset.stock) {
              newOption.dataset.stock = option.dataset.stock;
            }
            $select.append(newOption);
          });

          if (
            currentValue &&
            $select.find(`option[value="${currentValue}"]`).length > 0
          ) {
            $select.val(currentValue);
          }

          $select.trigger("change");
        }
      });

      console.log("Exchange Item dropdowns refreshed successfully");
    })
    .catch((error) => {
      console.warn("Could not refresh Exchange Item dropdowns:", error);
    });
}

/**
 * Refresh the Remove Item modal Select2 dropdowns
 */
function refreshRemoveItemDropdowns() {
  const productSelects = document.querySelectorAll(
    "#removeItemModal select[name='itemId[]']"
  );

  if (productSelects.length === 0) return;

  fetch(window.location.href, {
    method: "GET",
    headers: {
      "X-Requested-With": "XMLHttpRequest",
    },
  })
    .then((response) => response.text())
    .then((html) => {
      const parser = new DOMParser();
      const doc = parser.parseFromString(html, "text/html");

      const newSelect = doc.querySelector(
        "#removeItemModal select[name='itemId[]']"
      );

      if (newSelect && newSelect.options) {
        productSelects.forEach((select) => {
          const $select = $(select);
          const currentValue = $select.val();

          if ($select.hasClass("select2-hidden-accessible")) {
            $select.select2("destroy");
          }

          $select.empty();
          Array.from(newSelect.options).forEach((option) => {
            const newOption = new Option(
              option.text,
              option.value,
              false,
              false
            );
            if (option.dataset.stock) {
              newOption.dataset.stock = option.dataset.stock;
            }
            $select.append(newOption);
          });

          if (
            currentValue &&
            $select.find(`option[value="${currentValue}"]`).length > 0
          ) {
            $select.val(currentValue);
          }

          // Reinitialize Select2 with proper configuration
          $select.select2({
            placeholder: "Search and select product...",
            allowClear: true,
            width: "100%",
            dropdownParent: $("#removeItemModal"),
            templateResult: function (data) {
              if (!data.id || data.loading) {
                return data.text;
              }

              // Parse the option text to extract details
              const text = data.text;
              const stockMatch = text.match(/Stock:\s*(\d+)/i);
              const sizeMatch = text.match(/Size:\s*([^-]+)/i);

              let displayHtml =
                '<div class="select2-result-repository clearfix">';
              displayHtml += '<div class="select2-result-repository__meta">';
              displayHtml +=
                '<div class="select2-result-repository__title">' +
                text.split(" (")[0] +
                "</div>";

              if (sizeMatch) {
                displayHtml +=
                  '<div class="select2-result-repository__description">Size: ' +
                  sizeMatch[1].trim() +
                  "</div>";
              }

              if (stockMatch) {
                const stock = parseInt(stockMatch[1]);
                const stockClass =
                  stock === 0
                    ? "out-of-stock"
                    : stock < 10
                    ? "low-stock"
                    : "in-stock";
                displayHtml +=
                  '<div class="select2-result-repository__statistics">';
                displayHtml +=
                  '<span class="stock-badge ' +
                  stockClass +
                  '">Stock: ' +
                  stock +
                  "</span>";
                displayHtml += "</div>";
              }

              displayHtml += "</div></div>";
              return $(displayHtml);
            },
          });
        });

        console.log("Remove Stocks dropdowns refreshed successfully");
      }
    })
    .catch((error) => {
      console.warn("Could not refresh Remove Stocks dropdowns:", error);
    });
}

/**
 * Refresh the Generate Walk-in Payable Slip modal Select2 dropdowns
 */
function refreshPayableSlipDropdowns() {
  const productSelects = document.querySelectorAll(
    "#generatePayableSlipModal .payable-slip-product-select"
  );

  if (productSelects.length === 0) return;

  fetch(window.location.href, {
    method: "GET",
    headers: {
      "X-Requested-With": "XMLHttpRequest",
    },
  })
    .then((response) => response.text())
    .then((html) => {
      const parser = new DOMParser();
      const doc = parser.parseFromString(html, "text/html");

      const newSelect = doc.querySelector(
        "#generatePayableSlipModal .payable-slip-product-select"
      );

      if (newSelect && newSelect.options) {
        productSelects.forEach((select) => {
          const $select = $(select);
          const currentValue = $select.val();

          if ($select.hasClass("select2-hidden-accessible")) {
            $select.select2("destroy");
          }

          $select.empty();
          Array.from(newSelect.options).forEach((option) => {
            const newOption = new Option(
              option.text,
              option.value,
              false,
              false
            );
            if (option.dataset.category) {
              newOption.dataset.category = option.dataset.category;
            }
            $select.append(newOption);
          });

          if (
            currentValue &&
            $select.find(`option[value="${currentValue}"]`).length > 0
          ) {
            $select.val(currentValue);
          }

          // Reinitialize Select2 with proper configuration
          $select.select2({
            placeholder: "Select Product",
            allowClear: true,
            width: "100%",
            dropdownParent: $("#generatePayableSlipModal"),
          });
        });

        console.log("Payable Slip dropdowns refreshed successfully");
      }
    })
    .catch((error) => {
      console.warn("Could not refresh Payable Slip dropdowns:", error);
    });
}
