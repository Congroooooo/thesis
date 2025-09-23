let productCounter = 1;

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
    const opt = document.createElement("option");
    opt.value = "__add__";
    opt.textContent = "+ Add new subcategory…";
    subSel.appendChild(opt);
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

function submitNewItem(event) {
  event.preventDefault();

  const form = document.getElementById("addItemForm");
  const formData = new FormData(form);

  const deliveryOrderNumber = formData.get("deliveryOrderNumber");
  if (!deliveryOrderNumber) {
    alert("Please enter a delivery order number");
    return;
  }

  const productContainers = document.querySelectorAll(".product-item");
  if (productContainers.length === 0) {
    alert("No products found to add");
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
      alert(
        `Please fill in all required basic information for product ${
          parseInt(productIndex) + 1
        }`
      );
      allProductsValid = false;
      return;
    }

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
          sel.appendChild(new Option("+ Add new category…", "__add__"));
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
          sel0.appendChild(new Option("+ Add new category…", "__add__"));
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

  function setupCategoryHandlerForProduct(productIndex) {
    const catSel = document.getElementById(`newCategory_${productIndex}`);
    const subGroup = document.getElementById(
      `subcategoryGroup_${productIndex}`
    );

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
            await loadSubcategories(catId);
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

  const baseItemCodeInput = document.getElementById("newProductItemCode");
  if (baseItemCodeInput) {
    baseItemCodeInput.addEventListener("input", () => updateGeneratedCodes(0));
  }

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
