// Add Item Modal Backend Functions

let productCounter = 1; // Start from 1 since product 0 is already in the modal

function showAddItemModal() {
  document.getElementById("addItemModal").style.display = "flex";
  document.getElementById("addItemForm").reset();

  // Reset product counter and remove additional products
  productCounter = 1;
  const productsContainer = document.getElementById("productsContainer");
  const productItems = productsContainer.querySelectorAll(".product-item");

  // Keep only the first product, remove the rest
  for (let i = 1; i < productItems.length; i++) {
    productItems[i].remove();
  }

  // Reset the first product
  resetProduct(0);

  // Hide remove button for first product
  const removeBtn = document.querySelector(".remove-product-btn");
  if (removeBtn) removeBtn.style.display = "none";

  // refresh categories when opening
  if (typeof loadCategories === "function") {
    loadCategories();
    // Also setup the first product (index 0)
    setTimeout(() => {
      setupCategoryHandlerForProduct(0);
      if (typeof setupItemCodeHandlerForProduct === "function") {
        setupItemCodeHandlerForProduct(0);
      }
    }, 100);
  }
}

function resetProduct(productIndex) {
  // Reset size selections and details for specific product
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

  // reset subcategory area for this product
  const subGroup = document.getElementById(`subcategoryGroup_${productIndex}`);
  if (subGroup) subGroup.style.display = "none";
  const subSel = document.getElementById(`subcategorySelect_${productIndex}`);
  if (subSel) {
    subSel.innerHTML = "";
    const opt = document.createElement("option");
    opt.value = "__add__";
    opt.textContent = "+ Add new subcategory…";
    subSel.appendChild(opt);
  }
}

function addAnotherProduct() {
  const productsContainer = document.getElementById("productsContainer");

  // Find the next available product index based on existing products
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
          <input type="text" id="newProductItemCode_${productIndex}" name="products[${productIndex}][baseItemCode]" placeholder="e.g., USHPWV002" required>
          <small>This will be used as prefix for generating size-specific item codes</small>
        </div>
        <div class="input-group">
          <label for="newCategory_${productIndex}">Category:</label>
          <select id="newCategory_${productIndex}" name="products[${productIndex}][category_id]" required>
            <option value="">Select Category</option>
            <option value="__add__">+ Add new category…</option>
          </select>
        </div>
        <div class="input-group" id="subcategoryGroup_${productIndex}" style="display:none;">
          <label for="subcategorySelect_${productIndex}">Subcategory:</label>
          <select id="subcategorySelect_${productIndex}" name="products[${productIndex}][subcategory_ids][]" multiple style="width:100%;">
            <option value="__add__">+ Add new subcategory…</option>
          </select>
        </div>
        <div class="input-group">
          <label for="newItemName_${productIndex}">Product Name:</label>
          <input type="text" id="newItemName_${productIndex}" name="products[${productIndex}][newItemName]" required>
        </div>
        <div class="input-group">
          <label for="newImage_${productIndex}">Product Image:</label>
          <input type="file" id="newImage_${productIndex}" name="products[${productIndex}][newImage]" accept="image/*" required>
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

  // Show remove button for first product if this is the second product
  if (productIndex === 1) {
    const firstRemoveBtn = document.querySelector(".remove-product-btn");
    if (firstRemoveBtn) firstRemoveBtn.style.display = "block";
  }

  // Load categories for the new product
  if (typeof loadCategories === "function") {
    loadCategoriesForProduct(productIndex);
  }

  // Setup item code handler for the new product
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

  // Update product numbers and indices
  updateProductNumbers();

  // Hide remove button for first product if only one product remains
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

    // Update data-product-index to maintain sequential ordering
    const oldIndex = item.dataset.productIndex;
    const newIndex = index;

    if (oldIndex !== newIndex.toString()) {
      // Update all elements that reference the old product index
      updateProductIndexReferences(oldIndex, newIndex, item);
      item.dataset.productIndex = newIndex;
    }
  });
}

function updateProductIndexReferences(oldIndex, newIndex, productItem) {
  // Update all IDs and names that contain the old product index
  const elementsToUpdate = productItem.querySelectorAll(
    `[id*="_${oldIndex}"], [name*="[${oldIndex}]"], [onclick*="${oldIndex}"]`
  );

  elementsToUpdate.forEach((element) => {
    // Update IDs
    if (element.id) {
      element.id = element.id.replace(`_${oldIndex}`, `_${newIndex}`);
    }

    // Update names for form fields
    if (element.name) {
      element.name = element.name.replace(`[${oldIndex}]`, `[${newIndex}]`);
      element.name = element.name.replace(`_${oldIndex}`, `_${newIndex}`);
    }

    // Update onclick attributes
    if (element.getAttribute("onclick")) {
      const onclickValue = element.getAttribute("onclick");
      element.setAttribute(
        "onclick",
        onclickValue.replace(`(${oldIndex})`, `(${newIndex})`)
      );
    }

    // Update onchange attributes for size checkboxes
    if (element.getAttribute("onchange")) {
      const onchangeValue = element.getAttribute("onchange");
      element.setAttribute(
        "onchange",
        onchangeValue.replace(`, ${oldIndex})`, `, ${newIndex})`)
      );
    }
  });

  // Update data-product attributes for size checkboxes container
  const sizeCheckboxes = productItem.querySelector(".size-checkboxes");
  if (sizeCheckboxes) {
    sizeCheckboxes.dataset.product = newIndex;
  }

  // Update data-product attributes for existing size detail items
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
    // Add size detail form
    addSizeDetailForm(checkbox.value, productIndex);
    // Sort the size details after adding
    sortSizeDetails(productIndex);
    sizeDetailsContainer.classList.add("show");
  } else {
    // Remove size detail form
    removeSizeDetailForm(checkbox.value, productIndex);

    // Hide container if no sizes selected
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

  // Generate size-specific item code (using getSizeNumber for item codes)
  const sizeNumber = getSizeNumber(size);
  const generatedCode = `${baseItemCode}-${sizeNumber
    .toString()
    .padStart(3, "0")}`;

  // Get display order for sorting (separate from item code generation)
  const displayOrder = getSizeDisplayOrder(size);

  const sizeDetailHtml = `
    <div class="size-detail-item" data-size="${size}" data-size-order="${displayOrder}" data-product="${productIndex}">
      <div class="size-detail-header">
        <h4>Size: ${size}</h4>
        <span class="generated-code">Code: ${generatedCode}</span>
      </div>
      <div class="size-detail-form">
        <div class="input-group">
          <label for="price_${productIndex}_${size}">Price:</label>
          <input type="number" id="price_${productIndex}_${size}" name="products[${productIndex}][sizes][${size}][price]" min="0" step="0.01" required>
        </div>
        <div class="input-group">
          <label for="quantity_${productIndex}_${size}">Initial Stock:</label>
          <input type="number" id="quantity_${productIndex}_${size}" name="products[${productIndex}][sizes][${size}][quantity]" min="0" required>
        </div>
        <div class="input-group">
          <label for="damage_${productIndex}_${size}">Damaged Items:</label>
          <input type="number" id="damage_${productIndex}_${size}" name="products[${productIndex}][sizes][${size}][damage]" min="0" value="0">
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

  // Sort by size order (data-size-order attribute)
  sizeItems.sort((a, b) => {
    const orderA = parseInt(a.dataset.sizeOrder);
    const orderB = parseInt(b.dataset.sizeOrder);
    return orderA - orderB;
  });

  // Clear the container and re-append in sorted order
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
  const sizeMap = {
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
    "One Size": 12,
  };
  return sizeMap[size] || 999;
}

function getSizeDisplayOrder(size) {
  const displayOrderMap = {
    "One Size": 0, // Display "One Size" first
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

// Update generated codes when base item code changes
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

    // Update displayed code
    const codeSpan = item.querySelector(".generated-code");
    if (codeSpan) codeSpan.textContent = `Code: ${generatedCode}`;

    // Update hidden input
    const hiddenInput = item.querySelector(
      `input[name="products[${productIndex}][sizes][${size}][item_code]"]`
    );
    if (hiddenInput) hiddenInput.value = generatedCode;
  });
}

function submitNewItem(event) {
  event.preventDefault();

  const form = document.getElementById("addItemForm");
  const formData = new FormData(form);

  // Validate delivery order number
  const deliveryOrderNumber = formData.get("deliveryOrderNumber");
  if (!deliveryOrderNumber) {
    alert("Please enter a delivery order number");
    return;
  }

  // Get all product containers
  const productContainers = document.querySelectorAll(".product-item");
  if (productContainers.length === 0) {
    alert("No products found to add");
    return;
  }

  // Validate each product
  let allProductsValid = true;
  productContainers.forEach((container, index) => {
    const productIndex = container.dataset.productIndex;

    // Validate basic product information
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
      alert(
        `Please fill in all required basic information for product ${
          parseInt(productIndex) + 1
        }`
      );
      allProductsValid = false;
      return;
    }

    // Check if at least one size is selected
    const checkedSizes = container.querySelectorAll(
      '.size-checkboxes input[type="checkbox"]:checked'
    );
    if (checkedSizes.length === 0) {
      alert(
        `Please select at least one size for product ${
          parseInt(productIndex) + 1
        }`
      );
      allProductsValid = false;
      return;
    }

    // Validate all size detail forms for this product
    checkedSizes.forEach((checkbox) => {
      const size = checkbox.value;
      const priceInput = document.getElementById(
        `price_${productIndex}_${size}`
      );
      const quantityInput = document.getElementById(
        `quantity_${productIndex}_${size}`
      );

      if (!priceInput.value || parseFloat(priceInput.value) <= 0) {
        alert(
          `Please enter a valid price for size ${size} in product ${
            parseInt(productIndex) + 1
          }`
        );
        allProductsValid = false;
        return;
      }

      if (!quantityInput.value || parseInt(quantityInput.value) < 0) {
        alert(
          `Please enter a valid initial stock for size ${size} in product ${
            parseInt(productIndex) + 1
          }`
        );
        allProductsValid = false;
        return;
      }
    });

    // Handle shirt type if visible
    const shirtTypeGroup = document.getElementById(
      `shirtTypeGroup_${productIndex}`
    );
    if (shirtTypeGroup && shirtTypeGroup.style.display !== "none") {
      const shirtTypeSelect = document.getElementById(
        `shirtTypeSelect_${productIndex}`
      );
      const shirtTypeValue = shirtTypeSelect.value;
      if (shirtTypeValue) {
        // Find the option in the course select with the same text/value
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

  if (!allProductsValid) return;

  const xhr = new XMLHttpRequest();
  xhr.open("POST", "../PAMO Inventory backend/add_item.php", true);
  xhr.setRequestHeader("X-Requested-With", "XMLHttpRequest");

  xhr.onreadystatechange = function () {
    if (xhr.readyState === 4) {
      if (xhr.status === 200) {
        try {
          const data = JSON.parse(xhr.responseText);
          if (data.success) {
            alert("New product(s) added successfully!");
            location.reload();
          } else {
            throw new Error(data.message || "Unknown error");
          }
        } catch (e) {
          console.error("Parse error:", e);
          alert("Error adding product: " + xhr.responseText);
        }
      } else {
        alert("Error: " + xhr.statusText);
      }
    }
  };

  xhr.send(formData);
}

// Global function declarations (moved outside DOMContentLoaded for early access)
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

      // Setup category change handler for this product
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
    // Remove any existing listeners to avoid duplicates
    catSel.removeEventListener("change", catSel._categoryHandler);

    // Create and store the handler
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

  // Setup subcategory select2 for this product
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
    itemCodeInput.addEventListener("input", () =>
      updateGeneratedCodes(productIndex)
    );
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
      select.appendChild(new Option("+ Add new subcategory…", "__add__"));
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

function promptNewCategory() {
  const name = prompt("New category name:");
  if (!name) return Promise.resolve(null);
  const has = confirm(
    "Does this category have subcategories? OK=Yes, Cancel=No"
  )
    ? 1
    : 0;
  return fetch("../PAMO Inventory backend/api_categories_create.php", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ name, has_subcategories: has }),
  }).then(async (r) => {
    if (!r.ok) throw new Error(await r.text());
    return r.json();
  });
}

function promptNewSubcategory(categoryId) {
  const name = prompt("New subcategory name:");
  if (!name) return Promise.resolve(null);
  return fetch("../PAMO Inventory backend/api_subcategories_create.php", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ category_id: Number(categoryId), name }),
  }).then(async (r) => {
    if (!r.ok) throw new Error(await r.text());
    return r.json();
  });
}

// Event listener for category change
document.addEventListener("DOMContentLoaded", function () {
  // Dynamic categories & subcategories
  window.loadCategories = function () {
    fetch("../PAMO Inventory backend/api_categories_list.php")
      .then(async (r) => {
        if (!r.ok) throw new Error(await r.text());
        return r.json();
      })
      .then((list) => {
        // Load for the original single-product modal (if it exists)
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
          sel.appendChild(new Option("+ Add new category…", "__add__"));
          if (current) sel.value = current;
        }

        // Also load for the first product in multi-product modal
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
          sel0.appendChild(new Option("+ Add new category…", "__add__"));
          if (current0) sel0.value = current0;
        }
      })
      .catch((err) => {
        console.error("Failed to load categories:", err);
      });
  };

  // Load categories for specific product by index
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

        // Setup category change handler for this product
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
        select.appendChild(new Option("+ Add new subcategory…", "__add__"));
        list.forEach((sc) => {
          const opt = document.createElement("option");
          opt.value = String(sc.id);
          opt.textContent = sc.name;
          select.appendChild(opt);
        });
        // Always show group for categories that support subcategories,
        // even if none exist yet so user can add one immediately.
        group.style.display = "block";
        if (window.jQuery && $(select).length) {
          $(select).trigger("change.select2");
        }
      })
      .catch((err) => console.error("Failed to load subcategories:", err));
  }

  // Load subcategories for specific product by index
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
        select.appendChild(new Option("+ Add new subcategory…", "__add__"));
        list.forEach((sc) => {
          const opt = document.createElement("option");
          opt.value = String(sc.id);
          opt.textContent = sc.name;
          select.appendChild(opt);
        });
        // Always show group for categories that support subcategories,
        // even if none exist yet so user can add one immediately.
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

  function promptNewCategory() {
    const name = prompt("New category name:");
    if (!name) return Promise.resolve(null);
    const has = confirm(
      "Does this category have subcategories? OK=Yes, Cancel=No"
    )
      ? 1
      : 0;
    return fetch("../PAMO Inventory backend/api_categories_create.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ name, has_subcategories: has }),
    }).then(async (r) => {
      if (!r.ok) throw new Error(await r.text());
      return r.json();
    });
  }

  function promptNewSubcategory(categoryId) {
    const name = prompt("New subcategory name:");
    if (!name) return Promise.resolve(null);
    return fetch("../PAMO Inventory backend/api_subcategories_create.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ category_id: Number(categoryId), name }),
    }).then(async (r) => {
      if (!r.ok) throw new Error(await r.text());
      return r.json();
    });
  }

  // Setup category change handler for specific product
  function setupCategoryHandlerForProduct(productIndex) {
    const catSel = document.getElementById(`newCategory_${productIndex}`);
    const subGroup = document.getElementById(
      `subcategoryGroup_${productIndex}`
    );

    if (catSel) {
      // Remove any existing listeners to avoid duplicates
      catSel.removeEventListener("change", catSel._categoryHandler);

      // Create and store the handler
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

    // Setup subcategory select2 for this product
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
  }

  loadCategories();

  const catSel = document.getElementById("newCategory");
  const subSel = document.getElementById("subcategorySelect");
  const subGroup = document.getElementById("subcategoryGroup");

  if (window.jQuery && $("#subcategorySelect").length) {
    $("#subcategorySelect")
      .select2({
        placeholder: "Select subcategory",
        allowClear: true,
        width: "100%",
      })
      .on("select2:select", async function (e) {
        // Handle when an item is selected in Select2
        if (e.params.data.id === "__add__") {
          // Immediately unselect the "__add__" option
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
            await loadSubcategories(catId);
            // Add the new subcategory to current selection
            const newSelection = [...filteredVals, String(res.id)];
            $("#subcategorySelect").val(newSelection).trigger("change.select2");
          }
        }
      });
  }

  if (catSel) {
    catSel.addEventListener("change", async function () {
      if (this.value === "__add__") {
        const res = await promptNewCategory();
        if (res && res.success) {
          loadCategories();
          catSel.value = String(res.id);
          loadSubcategories(res.id);
        } else {
          this.value = "";
        }
        return;
      }
      const has = this.options[this.selectedIndex]?.dataset?.has === "1";
      if (has) {
        loadSubcategories(this.value);
      } else if (subGroup) {
        subGroup.style.display = "none";
      }
    });
  }

  // Add event listener for base item code changes
  const baseItemCodeInput = document.getElementById("newProductItemCode");
  if (baseItemCodeInput) {
    baseItemCodeInput.addEventListener("input", () => updateGeneratedCodes(0));
  }

  // Setup item code handler for specific product
  window.setupItemCodeHandlerForProduct = function (productIndex) {
    const itemCodeInput = document.getElementById(
      `newProductItemCode_${productIndex}`
    );
    if (itemCodeInput) {
      itemCodeInput.addEventListener("input", () =>
        updateGeneratedCodes(productIndex)
      );
    }
  };

  // Initialize Select2 only if elements exist (legacy guards)
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

  // Only declare categorySelect once
  // Legacy toggle clean-up: ensure we don't access missing nodes
  const categorySelect = document.getElementById("newCategory");
  if (categorySelect) {
    categorySelect.addEventListener("change", function () {
      const courseGroup = document.getElementById("courseGroup");
      const shirtTypeGroup = document.getElementById("shirtTypeGroup");
      if (courseGroup) courseGroup.style.display = "none";
      if (shirtTypeGroup) shirtTypeGroup.style.display = "none";
      // Subcategory group visibility is handled above via loadSubcategories
    });
  }
});
