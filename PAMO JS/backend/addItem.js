// Add Item Modal Backend Functions

function showAddItemModal() {
  document.getElementById("addItemModal").style.display = "flex";
  document.getElementById("addItemForm").reset();

  // Reset size selections and details
  const sizeCheckboxes = document.querySelectorAll(
    '.size-checkboxes input[type="checkbox"]'
  );
  sizeCheckboxes.forEach((checkbox) => {
    checkbox.checked = false;
  });

  const sizeDetailsContainer = document.getElementById("sizeDetailsContainer");
  const sizeDetailsList = document.getElementById("sizeDetailsList");
  if (sizeDetailsContainer) sizeDetailsContainer.classList.remove("show");
  if (sizeDetailsList) sizeDetailsList.innerHTML = "";

  // reset subcategory area
  const subGroup = document.getElementById("subcategoryGroup");
  if (subGroup) subGroup.style.display = "none";
  const subSel = document.getElementById("subcategorySelect");
  if (subSel) {
    subSel.innerHTML = "";
    const opt = document.createElement("option");
    opt.value = "__add__";
    opt.textContent = "+ Add new subcategory…";
    subSel.appendChild(opt);
  }
  // refresh categories when opening
  if (typeof loadCategories === "function") loadCategories();
}

function toggleSizeDetails(checkbox) {
  const sizeDetailsContainer = document.getElementById("sizeDetailsContainer");
  const sizeDetailsList = document.getElementById("sizeDetailsList");

  if (checkbox.checked) {
    // Add size detail form
    addSizeDetailForm(checkbox.value);
    // Sort the size details after adding
    sortSizeDetails();
    sizeDetailsContainer.classList.add("show");
  } else {
    // Remove size detail form
    removeSizeDetailForm(checkbox.value);

    // Hide container if no sizes selected
    const checkedSizes = document.querySelectorAll(
      '.size-checkboxes input[type="checkbox"]:checked'
    );
    if (checkedSizes.length === 0) {
      sizeDetailsContainer.classList.remove("show");
    }
  }
}

function addSizeDetailForm(size) {
  const sizeDetailsList = document.getElementById("sizeDetailsList");
  const baseItemCode =
    document.getElementById("newProductItemCode").value || "BASE";

  // Generate size-specific item code (using getSizeNumber for item codes)
  const sizeNumber = getSizeNumber(size);
  const generatedCode = `${baseItemCode}-${sizeNumber
    .toString()
    .padStart(3, "0")}`;

  // Get display order for sorting (separate from item code generation)
  const displayOrder = getSizeDisplayOrder(size);

  const sizeDetailHtml = `
    <div class="size-detail-item" data-size="${size}" data-size-order="${displayOrder}">
      <div class="size-detail-header">
        <h4>Size: ${size}</h4>
        <span class="generated-code">Code: ${generatedCode}</span>
      </div>
      <div class="size-detail-form">
        <div class="input-group">
          <label for="price_${size}">Price:</label>
          <input type="number" id="price_${size}" name="sizes[${size}][price]" min="0" step="0.01" required>
        </div>
        <div class="input-group">
          <label for="quantity_${size}">Initial Stock:</label>
          <input type="number" id="quantity_${size}" name="sizes[${size}][quantity]" min="0" required>
        </div>
        <div class="input-group">
          <label for="damage_${size}">Damaged Items:</label>
          <input type="number" id="damage_${size}" name="sizes[${size}][damage]" min="0" value="0">
        </div>
      </div>
      <input type="hidden" name="sizes[${size}][item_code]" value="${generatedCode}">
      <input type="hidden" name="sizes[${size}][size]" value="${size}">
    </div>
  `;

  sizeDetailsList.insertAdjacentHTML("beforeend", sizeDetailHtml);
}

function sortSizeDetails() {
  const sizeDetailsList = document.getElementById("sizeDetailsList");
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

function removeSizeDetailForm(size) {
  const sizeDetailItem = document.querySelector(`[data-size="${size}"]`);
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
function updateGeneratedCodes() {
  const baseItemCode =
    document.getElementById("newProductItemCode").value || "BASE";
  const sizeDetails = document.querySelectorAll(".size-detail-item");

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
      `input[name="sizes[${size}][item_code]"]`
    );
    if (hiddenInput) hiddenInput.value = generatedCode;
  });
}

function submitNewItem(event) {
  event.preventDefault();

  const form = document.getElementById("addItemForm");
  const formData = new FormData(form);

  // Validate required basic fields
  if (
    !formData.get("deliveryOrderNumber") ||
    !formData.get("baseItemCode") ||
    !formData.get("category_id") ||
    !formData.get("newItemName")
  ) {
    alert("Please fill in all required basic product information");
    return;
  }

  // Check if at least one size is selected
  const checkedSizes = document.querySelectorAll(
    '.size-checkboxes input[type="checkbox"]:checked'
  );
  if (checkedSizes.length === 0) {
    alert("Please select at least one size for the product");
    return;
  }

  // Validate all size detail forms
  let allSizeDetailsValid = true;
  checkedSizes.forEach((checkbox) => {
    const size = checkbox.value;
    const priceInput = document.getElementById(`price_${size}`);
    const quantityInput = document.getElementById(`quantity_${size}`);

    if (!priceInput.value || parseFloat(priceInput.value) <= 0) {
      alert(`Please enter a valid price for size ${size}`);
      allSizeDetailsValid = false;
      return;
    }

    if (!quantityInput.value || parseInt(quantityInput.value) < 0) {
      alert(`Please enter a valid initial stock for size ${size}`);
      allSizeDetailsValid = false;
      return;
    }
  });

  if (!allSizeDetailsValid) return;

  // If shirt type is visible and selected, add it to course_id[]
  const shirtTypeGroup = document.getElementById("shirtTypeGroup");
  if (shirtTypeGroup && shirtTypeGroup.style.display !== "none") {
    const shirtTypeSelect = document.getElementById("shirtTypeSelect");
    const shirtTypeValue = shirtTypeSelect.value;
    if (shirtTypeValue) {
      // Find the option in the course select with the same text/value
      const courseSelect = document.getElementById("courseSelect");
      if (courseSelect) {
        for (let i = 0; i < courseSelect.options.length; i++) {
          if (courseSelect.options[i].text === shirtTypeValue) {
            formData.append("course_id[]", courseSelect.options[i].value);
            break;
          }
        }
      }
    }
  }

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
        const sel = document.getElementById("newCategory");
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
      })
      .catch((err) => {
        console.error("Failed to load categories:", err);
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
    baseItemCodeInput.addEventListener("input", updateGeneratedCodes);
  }

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
