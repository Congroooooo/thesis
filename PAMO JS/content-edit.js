document.addEventListener("DOMContentLoaded", function () {
  loadAllSections();
  // Attach delete and edit handlers
  document.querySelectorAll(".image-grid").forEach(function (grid) {
    grid.addEventListener("click", function (e) {
      // Delete
      if (e.target.closest('.overlay-btn[title="Delete"]')) {
        const btn = e.target.closest(".overlay-btn");
        const imageId = btn.getAttribute("data-id");
        if (confirm("Are you sure you want to delete this image?")) {
          fetch("../PAMO BACKEND CONTENT EDIT/delete-content-image.php", {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded" },
            body: "id=" + encodeURIComponent(imageId),
          })
            .then((res) => res.json())
            .then((data) => {
              if (data.success) {
                location.reload();
              } else {
                alert(data.error || "Failed to delete image.");
              }
            });
        }
      }
      // Edit
      if (e.target.closest('.overlay-btn[title="Edit"]')) {
        const btn = e.target.closest(".overlay-btn");
        const imageId = btn.getAttribute("data-id");
        openEditModal(imageId);
      }
    });
  });
  // Accordion logic for section boxes
  document.querySelectorAll(".section-header").forEach(function (header) {
    header.addEventListener("click", function () {
      const box = header.closest(".section-box");
      // Close all other boxes
      document.querySelectorAll(".section-box").forEach(function (b) {
        if (b !== box) b.classList.remove("active");
      });
      // Toggle current box
      box.classList.toggle("active");
    });
  });
});

function loadAllSections() {
  loadHeroSection();
  loadCategoriesSection();
  loadFeaturedSection();
}

function openSection(sectionId) {
  // Close all sections first
  document.querySelectorAll(".section-content").forEach((section) => {
    section.classList.remove("active");
  });

  // Open the selected section
  const section = document.getElementById(`${sectionId}-content`);
  if (section) {
    section.classList.add("active");
  }
}

function openCategory(categoryId) {
  // Handle category click
  // Add your category-specific logic here
}

function loadHeroSection() {
  const heroGrid = document.getElementById("hero-image-grid");
  if (!heroGrid) return;

  heroGrid.innerHTML = contentData.hero.images
    .map(
      (image) => `
        <div class="image-item">
            <img src="${image.url}" alt="${image.title}">
            <div class="image-controls">
                <button onclick="editImage('hero', ${image.id})">
                    <i class="material-icons">edit</i>
                </button>
                <button onclick="deleteImage('hero', ${image.id})">
                    <i class="material-icons">delete</i>
                </button>
            </div>
        </div>
    `
    )
    .join("");
}

function loadCategoriesSection() {
  // Load STI Uniform
  const uniformGrid = document.getElementById("uniform-images");
  if (uniformGrid) {
    uniformGrid.innerHTML = contentData.categories.uniform.images
      .map(
        (image) => `
            <div class="image-item">
                <img src="${image.url}" alt="${image.title}">
                <div class="image-controls">
                    <button onclick="editImage('uniform', ${image.id})">
                        <i class="material-icons">edit</i>
                    </button>
                    <button onclick="deleteImage('uniform', ${image.id})">
                        <i class="material-icons">delete</i>
                    </button>
                </div>
            </div>
        `
      )
      .join("");
  }

  // Load STI Accessories
  const accessoriesGrid = document.getElementById("accessories-images");
  if (accessoriesGrid) {
    accessoriesGrid.innerHTML = contentData.categories.accessories.images
      .map(
        (image) => `
            <div class="image-item">
                <img src="${image.url}" alt="${image.title}">
                <div class="image-controls">
                    <button onclick="editImage('accessories', ${image.id})">
                        <i class="material-icons">edit</i>
                    </button>
                    <button onclick="deleteImage('accessories', ${image.id})">
                        <i class="material-icons">delete</i>
                    </button>
                </div>
            </div>
        `
      )
      .join("");
  }

  // Load STI Shirt
  const shirtGrid = document.getElementById("shirt-images");
  if (shirtGrid) {
    shirtGrid.innerHTML = contentData.categories.shirt.images
      .map(
        (image) => `
            <div class="image-item">
                <img src="${image.url}" alt="${image.title}">
                <div class="image-controls">
                    <button onclick="editImage('shirt', ${image.id})">
                        <i class="material-icons">edit</i>
                    </button>
                    <button onclick="deleteImage('shirt', ${image.id})">
                        <i class="material-icons">delete</i>
                    </button>
                </div>
            </div>
        `
      )
      .join("");
  }
}

function loadFeaturedSection() {
  const featuredGrid = document.getElementById("featured-products-grid");
  if (!featuredGrid) return;

  featuredGrid.innerHTML = contentData.featured.products
    .map(
      (product) => `
        <div class="featured-item">
            <img src="${product.url}" alt="${product.title}">
            <h3>${product.title}</h3>
            <div class="product-controls">
                <button onclick="editProduct(${product.id})">
                    <i class="material-icons">edit</i>
                </button>
                <button onclick="deleteProduct(${product.id})">
                    <i class="material-icons">delete</i>
                </button>
            </div>
        </div>
    `
    )
    .join("");
}

function editImage(section, imageId) {
  // Handle image editing
  // Add your image editing logic here
}

function deleteImage(section, imageId) {
  // Handle image deletion
  // Add your image deletion logic here
}

function editProduct(productId) {
  // Handle product editing
  // Add your product editing logic here
}

function deleteProduct(productId) {
  // Handle product deletion
  // Add your product deletion logic here
}

// Sample content data
const contentItems = [
  {
    id: 1,
    type: "banner",
    title: "Welcome Banner 2024",
    description: "Main banner for student portal",
    image: "banner1.jpg",
    startDate: "2024-01-01",
    endDate: "2024-12-31",
  },
  {
    id: 2,
    type: "announcement",
    title: "New Library Hours",
    description: "Updated library schedule",
    image: "announcement1.jpg",
    startDate: "2024-02-01",
    endDate: "2024-03-01",
  },
  // Add more items as needed
];

// Initialize content grid
// document.addEventListener("DOMContentLoaded", function () {
//   loadContentGrid();
//   initializeDropZone();
// });

function openUploadModal() {
  document.getElementById("uploadModal").style.display = "block";
}

function closeUploadModal() {
  document.getElementById("uploadModal").style.display = "none";
  document.getElementById("uploadForm").reset();
  document.getElementById("previewArea").innerHTML = "";
}

function editContent(id) {
  const content = contentItems.find((item) => item.id === id);
  if (content) {
    openUploadModal();
    // Populate form with content data
    // This would be implemented based on your needs
  }
}

function deleteContent(id) {
  if (confirm("Are you sure you want to delete this content?")) {
    // Handle deletion
    showNotification("Content deleted successfully");
  }
}

// Form submission
document.getElementById("uploadForm").addEventListener("submit", function (e) {
  e.preventDefault();
  // Handle form submission
  showNotification("Content uploaded successfully");
  closeUploadModal();
});

// Utility functions
function formatDate(dateString) {
  return new Date(dateString).toLocaleDateString();
}

function showNotification(message) {
  const notification = document.createElement("div");
  notification.className = "notification";
  notification.textContent = message;
  document.body.appendChild(notification);

  notification.style.display = "block";
  setTimeout(() => {
    notification.style.display = "none";
    notification.remove();
  }, 3000);
}

// Filter content based on type
document.getElementById("contentType").addEventListener("change", function (e) {
  const type = e.target.value;
  const grid = document.querySelector(".content-grid");
  grid.innerHTML = "";

  const filteredItems =
    type === "all"
      ? contentItems
      : contentItems.filter((item) => item.type === type);

  filteredItems.forEach((item) => {
    const card = createContentCard(item);
    grid.appendChild(card);
  });
});

function logout() {
  showLogoutConfirmation();
}

// Modal logic
function openEditModal(imageId) {
  // Fetch current data for the image
  fetch(`../PAMO BACKEND CONTENT EDIT/get-content-image.php?id=${imageId}`)
    .then((res) => res.json())
    .then((data) => {
      if (!data.success) {
        alert(data.error || "Failed to fetch image data.");
        return;
      }
      // Populate modal fields
      document.getElementById("editImageId").value = imageId;
      document.getElementById("editImageTitle").value = data.title;
      document.getElementById("editImagePreview").src = "../" + data.image_path;
      document.getElementById("editImageModal").style.display = "block";
      // Attach submit handler here to ensure it is always attached
      document.getElementById("editImageForm").onsubmit = function (e) {
        e.preventDefault();
        const formData = new FormData(this);
        fetch("../PAMO BACKEND CONTENT EDIT/edit-content-image.php", {
          method: "POST",
          body: formData,
        })
          .then((res) => res.json())
          .then((data) => {
            if (data.success) {
              location.reload();
            } else {
              alert(data.error || "Failed to update image.");
            }
          });
      };
    });
}

function closeEditModal() {
  document.getElementById("editImageModal").style.display = "none";
}
