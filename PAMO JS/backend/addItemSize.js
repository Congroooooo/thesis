// Add Item Size Modal Backend Functions

const allSizes = [
  "XS",
  "S",
  "M",
  "L",
  "XL",
  "XXL",
  "3XL",
  "4XL",
  "5XL",
  "6XL",
  "7XL",
  "One Size",
];

const sizeSuffixMap = {
  "One Size": "001",
  XS: "001",
  S: "002",
  M: "003",
  L: "004",
  XL: "005",
  XXL: "006",
  "3XL": "007",
  "4XL": "008",
  "5XL": "009",
  "6XL": "010",
  "7XL": "011",
};

let inventoryUsedSizes = [];

function showAddItemSizeModal() {
  document.getElementById("addItemSizeModal").style.display = "block";
  // Reset the form
  document.getElementById("addItemSizeForm").reset();
  // Reset Select2 for Select Item
  if (window.jQuery && $("#existingItem").length) {
    $("#existingItem").val(null).trigger("change");
  }
  // Reset to single entry
  const entriesContainer = document.getElementById("itemSizeEntries");
  entriesContainer.innerHTML = "";
  addItemSizeEntry(); // Add initial entry
}

function getSelectedSizes() {
  // Get all selected sizes in the current entries
  return Array.from(document.querySelectorAll("select[name='newSize[]']"))
    .map((sel) => sel.value)
    .filter(Boolean);
}

function updateSizeDropdowns(usedSizes = null) {
  // If usedSizes is not provided, get from current selection
  if (!usedSizes) usedSizes = getSelectedSizes();
  // Exclude both inventoryUsedSizes and usedSizes
  const excludeSizes = [
    ...new Set([...(inventoryUsedSizes || []), ...usedSizes]),
  ];
  document.querySelectorAll("select[name='newSize[]']").forEach((select) => {
    const currentValue = select.value;
    select.innerHTML = '<option value="">Select Size</option>';
    allSizes.forEach((size) => {
      // Allow the current value, but not any other already-selected or inventory size
      if (!excludeSizes.includes(size) || size === currentValue) {
        const option = document.createElement("option");
        option.value = size;
        option.textContent = size;
        if (size === currentValue) option.selected = true;
        select.appendChild(option);
      }
    });
  });
}

function fetchAndUpdateSizesForItem() {
  const select = document.getElementById("existingItem");
  const prefix = select.value;
  if (!prefix) {
    inventoryUsedSizes = [];
    updateSizeDropdowns([]); // Show all sizes if no item selected
    updateItemCodePrefix();
    return;
  }
  fetch(
    `../PAMO Inventory backend/get_item_sizes.php?prefix=${encodeURIComponent(
      prefix
    )}`
  )
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        inventoryUsedSizes = data.sizes;
        updateSizeDropdowns();
        updateItemCodePrefix(); // Reset item code as well
      } else {
        alert("Error fetching sizes: " + data.message);
      }
    })
    .catch((err) => {
      alert("Error fetching sizes");
    });
}

// Bind to item select change
document.addEventListener("DOMContentLoaded", function () {
  if (window.jQuery && $("#existingItem").length) {
    $("#existingItem").on("change", fetchAndUpdateSizesForItem);
  } else {
    const existingItem = document.getElementById("existingItem");
    if (existingItem) {
      existingItem.addEventListener("change", fetchAndUpdateSizesForItem);
    }
  }
});

function addItemSizeEntry() {
  const entriesContainer = document.getElementById("itemSizeEntries");
  const entryDiv = document.createElement("div");
  entryDiv.className = "item-size-entry";
  entryDiv.innerHTML = `
    <div class="item-close" onclick="removeItemSizeEntry(this)">&times;</div>
    <div class="item-content">
      <div class="input-group">
        <label for="newSize">Size:</label>
        <select name="newSize[]" required></select>
      </div>
      <div class="input-group">
        <label for="newItemCode">Item Code:</label>
        <input type="text" name="newItemCode[]" required readonly>
      </div>
      <div class="input-group">
        <label for="newQuantity">Initial Stock:</label>
        <input type="number" name="newQuantity[]" min="0" required>
      </div>
      <div class="input-group">
        <label for="newDamage">Damaged Items:</label>
        <input type="number" name="newDamage[]" min="0" value="0">
      </div>
    </div>
  `;
  entriesContainer.appendChild(entryDiv);

  // Show close button only if there's more than one entry
  const closeButtons = document.querySelectorAll(".item-close");
  closeButtons.forEach((button) => {
    button.style.display =
      entriesContainer.children.length > 1 ? "block" : "none";
  });

  // After adding, update all dropdowns to reflect available sizes
  updateSizeDropdowns();
}

function removeItemSizeEntry(button) {
  const entry = button.parentElement;
  const entriesContainer = document.getElementById("itemSizeEntries");
  entry.remove();

  // Show/hide close buttons based on remaining entries
  const closeButtons = document.querySelectorAll(".item-close");
  closeButtons.forEach((btn) => {
    btn.style.display = entriesContainer.children.length > 1 ? "block" : "none";
  });
}

function updateItemCodePrefix() {
  const existingItem = document.getElementById("existingItem");
  const selectedOption = existingItem.options[existingItem.selectedIndex];
  const prefix = selectedOption.value;

  // Update all item code inputs
  const itemCodeInputs = document.getElementsByName("newItemCode[]");
  itemCodeInputs.forEach((input) => {
    const sizeSelect = input
      .closest(".item-size-entry")
      .querySelector("select[name='newSize[]']");
    if (sizeSelect.value) {
      const suffix = sizeSuffixMap[sizeSelect.value] || "";
      input.value = suffix ? `${prefix}-${suffix}` : `${prefix}-`;
    }
  });
}

// When a size is changed, update all dropdowns to prevent duplicates
// and update item codes

document.addEventListener("change", function (e) {
  if (e.target.name === "newSize[]") {
    updateSizeDropdowns();
    // Update item code for this entry
    const entry = e.target.closest(".item-size-entry");
    const itemCodeInput = entry.querySelector("input[name='newItemCode[]']");
    const existingItem = document.getElementById("existingItem");
    const prefix = existingItem.value;
    if (prefix && e.target.value) {
      const suffix = sizeSuffixMap[e.target.value] || "";
      itemCodeInput.value = suffix ? `${prefix}-${suffix}` : `${prefix}-`;
    }
  }
});

function submitNewItemSize(event) {
  event.preventDefault();

  const form = document.getElementById("addItemSizeForm");
  const formData = new FormData(form);

  // Validate at least one size entry
  const entries = document.querySelectorAll(".item-size-entry");
  if (entries.length === 0) {
    alert("Please add at least one size entry");
    return;
  }

  // Validate all entries
  let isValid = true;
  entries.forEach((entry) => {
    const size = entry.querySelector("select[name='newSize[]']").value;
    const quantity = entry.querySelector("input[name='newQuantity[]']").value;
    const quantityNum = parseInt(quantity);
    if (!size || !quantity || quantityNum < 1) {
      isValid = false;
    }
  });

  if (!isValid) {
    alert(
      "Please fill in all required fields for each size entry and ensure initial stock is at least 1"
    );
    return;
  }

  // Send the form data
  const xhr = new XMLHttpRequest();
  xhr.open("POST", "../PAMO Inventory backend/process_add_item_size.php", true);
  xhr.setRequestHeader("X-Requested-With", "XMLHttpRequest");

  xhr.onreadystatechange = function () {
    if (xhr.readyState === 4) {
      if (xhr.status === 200) {
        try {
          const response = JSON.parse(xhr.responseText);
          if (response.success) {
            alert("New sizes added successfully!");
            location.reload();
          } else {
            alert("Error: " + response.message);
          }
        } catch (e) {
          console.error("Parse error:", e);
          alert("Error processing response: " + xhr.responseText);
        }
      } else {
        alert("Error: " + xhr.statusText);
      }
    }
  };

  xhr.send(formData);
}
