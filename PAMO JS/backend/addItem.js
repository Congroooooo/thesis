// Add Item Modal Backend Functions

function showAddItemModal() {
  document.getElementById("addItemModal").style.display = "flex";
  document.getElementById("addItemForm").reset();
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

function submitNewItem(event) {
  event.preventDefault();

  const quantity = parseInt(document.getElementById("newItemQuantity").value);
  const damage = parseInt(document.getElementById("newItemDamage").value) || 0;
  const form = document.getElementById("addItemForm");
  const formData = new FormData(form);

  if (
    !formData.get("newItemCode") ||
    !formData.get("category_id") ||
    !formData.get("newItemName") ||
    !formData.get("newSize") ||
    isNaN(formData.get("newItemPrice")) ||
    isNaN(formData.get("newItemQuantity"))
  ) {
    alert("Please fill in all required fields with valid values");
    return;
  }

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
            alert("New product added successfully!");
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

  // Initialize Select2 for course and shirt type
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
  const categorySelect = document.getElementById("newCategory");
  if (categorySelect) {
    categorySelect.addEventListener("change", function () {
      const courseGroup = document.getElementById("courseGroup");
      const shirtTypeGroup = document.getElementById("shirtTypeGroup");
      if (this.value === "Tertiary-Uniform") {
        courseGroup.style.display = "block";
        shirtTypeGroup.style.display = "none";
      } else if (this.value === "STI-Shirts") {
        courseGroup.style.display = "none";
        shirtTypeGroup.style.display = "block";
      } else {
        courseGroup.style.display = "none";
        shirtTypeGroup.style.display = "none";
      }
    });
  }
});
