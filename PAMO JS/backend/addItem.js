// Add Item Modal Backend Functions

function showAddItemModal() {
  document.getElementById("addItemModal").style.display = "flex";

  // Reset the form and ensure we have only one product entry
  resetAddItemModal();

  // Categories will be loaded by resetAddItemModal or by the initial setup
  // No need to load them again here to avoid duplication
}

// Updated loadCategories function to handle multiple entries
window.loadCategories = function (entryIndex = null) {
  fetch("../PAMO Inventory backend/api_categories_list.php")
    .then(async (r) => {
      if (!r.ok) throw new Error(await r.text());
      return r.json();
    })
    .then((list) => {
      // If entryIndex is specified, load for that specific entry
      if (entryIndex !== null) {
        loadCategoriesForEntry(entryIndex, list);
      } else {
        // Load for all existing entries
        const productEntries = document.getElementById("productEntries");
        if (productEntries) {
          for (let i = 0; i < productEntries.children.length; i++) {
            loadCategoriesForEntry(i, list);
          }
        }

        // Also load for the legacy single-entry selector if it exists
        const legacySelect = document.getElementById("newCategory");
        if (legacySelect) {
          loadCategoriesForEntry(null, list);
        }
      }
    })
    .catch((err) => {
      console.error("Failed to load categories:", err);
    });
};

function loadCategoriesForEntry(index, categoryList = null) {
  const selectId = index !== null ? `newCategory_${index}` : "newCategory";
  const sel = document.getElementById(selectId);

  if (!sel) return;

  // Check if this select already has real categories loaded (to prevent duplication)
  // A select with real categories will have more than just "Select Category" and "+ Add new category..."
  const hasRealCategories =
    sel.options.length > 2 &&
    Array.from(sel.options).some(
      (opt) => opt.value !== "" && opt.value !== "__add__"
    );

  if (hasRealCategories && !sel.hasAttribute("data-force-reload")) {
    return; // Already has categories loaded
  }

  const current = sel.value;
  sel.innerHTML = '<option value="">Select Category</option>';

  if (categoryList) {
    // Use provided list
    categoryList.forEach((c) => {
      const opt = document.createElement("option");
      opt.value = String(c.id);
      opt.textContent = c.name;
      opt.dataset.has = String(c.has_subcategories);
      sel.appendChild(opt);
    });
    sel.appendChild(new Option("+ Add new category…", "__add__"));
    if (current) sel.value = current;
    sel.removeAttribute("data-force-reload"); // Clear the force reload flag if it was set
  } else {
    // Fetch categories
    fetch("../PAMO Inventory backend/api_categories_list.php")
      .then(async (r) => {
        if (!r.ok) throw new Error(await r.text());
        return r.json();
      })
      .then((list) => {
        list.forEach((c) => {
          const opt = document.createElement("option");
          opt.value = String(c.id);
          opt.textContent = c.name;
          opt.dataset.has = String(c.has_subcategories);
          sel.appendChild(opt);
        });
        sel.appendChild(new Option("+ Add new category…", "__add__"));
        if (current) sel.value = current;
        sel.removeAttribute("data-force-reload"); // Clear the force reload flag if it was set
      })
      .catch((err) => {
        console.error("Failed to load categories:", err);
      });
  }
}
function submitNewItem(event) {
  event.preventDefault();

  const form = document.getElementById("addItemForm");
  const productEntries = document.getElementById("productEntries");
  const deliveryOrderNumber = document.getElementById(
    "deliveryOrderNumber"
  ).value;

  if (!deliveryOrderNumber) {
    alert("Please enter a delivery order number");
    return;
  }

  // Validate all product entries
  const entries = productEntries.children;
  const products = [];

  for (let i = 0; i < entries.length; i++) {
    const entry = entries[i];
    const itemCode = entry.querySelector(
      `input[name="products[${i}][itemCode]"]`
    ).value;
    const categoryId = entry.querySelector(
      `select[name="products[${i}][category_id]"]`
    ).value;
    const itemName = entry.querySelector(
      `input[name="products[${i}][itemName]"]`
    ).value;
    const size = entry.querySelector(
      `select[name="products[${i}][size]"]`
    ).value;
    const price = entry.querySelector(
      `input[name="products[${i}][price]"]`
    ).value;
    const quantity = entry.querySelector(
      `input[name="products[${i}][quantity]"]`
    ).value;
    const damage =
      entry.querySelector(`input[name="products[${i}][damage]"]`).value || 0;
    const image = entry.querySelector(`input[name="products[${i}][image]"]`)
      .files[0];

    if (
      !itemCode ||
      !categoryId ||
      !itemName ||
      !size ||
      !price ||
      !quantity ||
      !image
    ) {
      alert(`Please fill in all required fields for product ${i + 1}`);
      return;
    }

    if (isNaN(price) || parseFloat(price) <= 0) {
      alert(`Please enter a valid price for product ${i + 1}`);
      return;
    }

    if (isNaN(quantity) || parseInt(quantity) < 0) {
      alert(`Please enter a valid quantity for product ${i + 1}`);
      return;
    }

    if (isNaN(damage) || parseInt(damage) < 0) {
      alert(`Please enter a valid damage count for product ${i + 1}`);
      return;
    }

    products.push({
      itemCode,
      categoryId,
      itemName,
      size,
      price: parseFloat(price),
      quantity: parseInt(quantity),
      damage: parseInt(damage),
      image,
      subcategoryIds: Array.from(
        entry.querySelectorAll(
          `select[name="products[${i}][subcategory_ids][]"] option:checked`
        )
      )
        .map((option) => option.value)
        .filter((value) => value !== "__add__"),
    });
  }

  // Show loading state
  const submitBtn = document.querySelector(
    'button[form="addItemForm"][type="submit"]'
  );
  const originalText = submitBtn.textContent;
  submitBtn.textContent = "Adding Products...";
  submitBtn.disabled = true;

  // Process products one by one
  processProductsSequentially(
    products,
    deliveryOrderNumber,
    0,
    submitBtn,
    originalText
  );
}

function processProductsSequentially(
  products,
  deliveryOrderNumber,
  index,
  submitBtn,
  originalText
) {
  if (index >= products.length) {
    // All products processed successfully
    alert(`All ${products.length} products added successfully!`);
    submitBtn.textContent = originalText;
    submitBtn.disabled = false;
    location.reload();
    return;
  }

  const product = products[index];
  const formData = new FormData();

  // Add single product data
  formData.append("newItemCode", product.itemCode);
  formData.append("category_id", product.categoryId);
  formData.append("newItemName", product.itemName);
  formData.append("newSize", product.size);
  formData.append("newItemPrice", product.price);
  formData.append("newItemQuantity", product.quantity);
  formData.append("newItemDamage", product.damage);
  formData.append("newImage", product.image);
  formData.append("deliveryOrderNumber", deliveryOrderNumber);

  // Add subcategory IDs
  product.subcategoryIds.forEach((id) => {
    formData.append("subcategory_ids[]", id);
  });

  const xhr = new XMLHttpRequest();
  xhr.open("POST", "../PAMO Inventory backend/add_item.php", true);
  xhr.setRequestHeader("X-Requested-With", "XMLHttpRequest");

  xhr.onreadystatechange = function () {
    if (xhr.readyState === 4) {
      if (xhr.status === 200) {
        try {
          const data = JSON.parse(xhr.responseText);
          if (data.success) {
            // Update progress
            submitBtn.textContent = `Adding Products... (${index + 1}/${
              products.length
            })`;

            // Process next product
            setTimeout(() => {
              processProductsSequentially(
                products,
                deliveryOrderNumber,
                index + 1,
                submitBtn,
                originalText
              );
            }, 500); // Small delay to prevent overwhelming the server
          } else {
            throw new Error(data.message || "Unknown error");
          }
        } catch (e) {
          console.error("Parse error:", e);
          alert(`Error adding product ${index + 1}: ` + xhr.responseText);
          submitBtn.textContent = originalText;
          submitBtn.disabled = false;
        }
      } else {
        alert(`Error adding product ${index + 1}: ` + xhr.statusText);
        submitBtn.textContent = originalText;
        submitBtn.disabled = false;
      }
    }
  };

  xhr.send(formData);
}

// Event listener for category change
document.addEventListener("DOMContentLoaded", function () {
  // Make functions globally available
  window.loadSubcategories = loadSubcategories;
  window.setupCategoryEventListeners = setupCategoryEventListeners;
  window.setupSubcategoryEventListeners = setupSubcategoryEventListeners;

  function loadSubcategories(categoryId, index = null) {
    // Support both old single entry (index = null) and new multi-entry (index = number)
    const groupId =
      index !== null ? `subcategoryGroup_${index}` : "subcategoryGroup";
    const selectId =
      index !== null ? `subcategorySelect_${index}` : "subcategorySelect";

    const group = document.getElementById(groupId);
    const select = document.getElementById(selectId);

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

  // Make loadSubcategories globally available
  window.loadSubcategories = loadSubcategories;

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

  // Function to set up event listeners for category selects
  function setupCategoryEventListeners(index = null) {
    const selectId = index !== null ? `newCategory_${index}` : "newCategory";
    const catSel = document.getElementById(selectId);

    if (catSel && !catSel.hasAttribute("data-listeners-setup")) {
      catSel.setAttribute("data-listeners-setup", "true");

      catSel.addEventListener("change", async function () {
        if (this.value === "__add__") {
          const res = await promptNewCategory();
          if (res && res.success) {
            // Force reload categories for all entries to include the new category
            const productEntries = document.getElementById("productEntries");
            if (productEntries) {
              for (let i = 0; i < productEntries.children.length; i++) {
                const selectElement = document.getElementById(
                  `newCategory_${i}`
                );
                if (selectElement) {
                  selectElement.setAttribute("data-force-reload", "true");
                }
              }
            }
            // Reload categories for all entries
            window.loadCategories();
            // Set the new category for this specific select
            this.value = String(res.id);
            // Load subcategories if the new category has them
            if (res.has_subcategories) {
              loadSubcategories(res.id, index);
            }
          } else {
            this.value = "";
          }
          return;
        }

        const selectedOption = this.options[this.selectedIndex];
        const hasSubcategories = selectedOption?.dataset?.has === "1";

        if (hasSubcategories) {
          loadSubcategories(this.value, index);
        } else {
          // Hide subcategory group for this entry
          const groupId =
            index !== null ? `subcategoryGroup_${index}` : "subcategoryGroup";
          const subGroup = document.getElementById(groupId);
          if (subGroup) {
            subGroup.style.display = "none";
          }
        }
      });
    }
  }

  // Function to set up event listeners for subcategory selects
  function setupSubcategoryEventListeners(index = null) {
    const selectId =
      index !== null ? `subcategorySelect_${index}` : "subcategorySelect";
    const subSel = document.getElementById(selectId);

    if (
      window.jQuery &&
      $(subSel).length &&
      !$(subSel).hasClass("select2-hidden-accessible")
    ) {
      $(subSel)
        .select2({
          placeholder: "Select subcategory",
          allowClear: true,
          width: "100%",
        })
        .on("select2:select", async function (e) {
          if (e.params.data.id === "__add__") {
            // Immediately unselect the "__add__" option
            const currentVals = $(this).val() || [];
            const filteredVals = currentVals.filter((v) => v !== "__add__");
            $(this).val(filteredVals).trigger("change.select2");

            const catSelectId =
              index !== null ? `newCategory_${index}` : "newCategory";
            const catSel = document.getElementById(catSelectId);
            const catId = catSel?.value;

            if (!catId || catId === "__add__") {
              alert("Select a category first");
              return;
            }

            const res = await promptNewSubcategory(catId);
            if (res && res.success) {
              await loadSubcategories(catId, index);
              // Add the new subcategory to current selection
              const newSelection = [...filteredVals, String(res.id)];
              $(subSel).val(newSelection).trigger("change.select2");
            }
          }
        });
    }
  }

  // Initial setup - Don't load categories automatically to avoid duplication
  // Categories will be loaded when the modal is opened

  // Set up event listeners for the initial entry
  setupCategoryEventListeners(0);
  setupSubcategoryEventListeners(0);

  // Legacy support for old single-entry modal if it exists
  const legacyCatSel = document.getElementById("newCategory");
  const legacySubSel = document.getElementById("subcategorySelect");

  if (legacyCatSel) {
    setupCategoryEventListeners(null);
  }

  if (legacySubSel) {
    setupSubcategoryEventListeners(null);
  }

  // Initialize Select2 for legacy elements if they exist
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

  // Legacy toggle clean-up: ensure we don't access missing nodes
  const legacyCategorySelect = document.getElementById("newCategory");
  if (legacyCategorySelect) {
    legacyCategorySelect.addEventListener("change", function () {
      const courseGroup = document.getElementById("courseGroup");
      const shirtTypeGroup = document.getElementById("shirtTypeGroup");
      if (courseGroup) courseGroup.style.display = "none";
      if (shirtTypeGroup) shirtTypeGroup.style.display = "none";
      // Subcategory group visibility is handled above via loadSubcategories
    });
  }
});
