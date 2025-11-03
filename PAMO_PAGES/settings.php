<?php
session_start();

// Use absolute paths for better compatibility across environments
$base_dir = dirname(__DIR__);
$config_file = __DIR__ . '/includes/config_functions.php';

// Try different possible paths for the connection file
$connection_alternatives = [
    $base_dir . '/includes/connection.php',
    $base_dir . '/Includes/connection.php'
];

$connection_file = null;
foreach ($connection_alternatives as $alt_path) {
    if (file_exists($alt_path)) {
        $connection_file = $alt_path;
        break;
    }
}

include $config_file;
include $connection_file;
if (!isset($_SESSION['user_id'])) {
    header("Location: ../Pages/login.php?redirect=../PAMO_PAGES/settings.php");
    exit();
}
$role = strtoupper($_SESSION['role_category'] ?? '');
$programAbbr = strtoupper($_SESSION['program_abbreviation'] ?? '');
if (!($role === 'EMPLOYEE' && $programAbbr === 'PAMO')) {
    header("Location: ../Pages/home.php");
    exit();
}

// Low stock threshold is now handled via AJAX (see includes/update_threshold.php)

// Handle category deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_category'])) {
    $categoryId = intval($_POST['category_id']);
    try {
        // Check if category has items
        $checkStmt = $conn->prepare("SELECT COUNT(*) FROM inventory WHERE category_id = ?");
        $checkStmt->execute([$categoryId]);
        $itemCount = $checkStmt->fetchColumn();
        
        if ($itemCount > 0) {
            $error_message = "Cannot delete category. It has $itemCount item(s) associated with it.";
        } else {
            // Delete subcategories first
            $deleteSubStmt = $conn->prepare("DELETE FROM subcategories WHERE category_id = ?");
            $deleteSubStmt->execute([$categoryId]);
            
            // Delete category
            $deleteCatStmt = $conn->prepare("DELETE FROM categories WHERE id = ?");
            $deleteCatStmt->execute([$categoryId]);
            
            $success_message = "Category deleted successfully!";
            $user_id = $_SESSION['user_id'] ?? null;
            logActivity($conn, 'Category Deleted', "Category with ID $categoryId was deleted.", $user_id);
        }
    } catch (Exception $e) {
        $error_message = "Error deleting category: " . $e->getMessage();
    }
}

// Handle subcategory deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_subcategory'])) {
    $subcategoryId = intval($_POST['subcategory_id']);
    try {
        $deleteStmt = $conn->prepare("DELETE FROM subcategories WHERE id = ?");
        $deleteStmt->execute([$subcategoryId]);
        
        $success_message = "Subcategory deleted successfully!";
        $user_id = $_SESSION['user_id'] ?? null;
        logActivity($conn, 'Subcategory Deleted', "Subcategory with ID $subcategoryId was deleted.", $user_id);
    } catch (Exception $e) {
        $error_message = "Error deleting subcategory: " . $e->getMessage();
    }
}

$current_threshold = getLowStockThreshold($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PAMO - Settings</title>
    <link rel="stylesheet" href="../PAMO CSS/styles.css">
    <link rel="stylesheet" href="../PAMO CSS/settings.css">
    <link rel="stylesheet" href="../CSS/logout-modal.css">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="container">
        <?php include 'includes/sidebar.php'; ?>

        <main class="main-content">
            <div class="settings-container">
                <div class="settings-card">
                    <div class="settings-header">
                        <i class="material-icons">settings</i>
                        <div>
                            <h2 class="settings-title">Settings</h2>
                            <p>Configure categories and system settings for inventory management.</p>
                        </div>
                    </div>

                    <?php if (isset($success_message)): ?>
                        <div class="message success">
                            <i class="material-icons">check_circle</i> <?php echo $success_message; ?>
                        </div>
                    <?php endif; ?>
                    <?php if (isset($error_message)): ?>
                        <div class="message error">
                            <i class="material-icons">error</i> <?php echo $error_message; ?>
                        </div>
                    <?php endif; ?>

                    <!-- Tab Navigation -->
                    <div class="tab-navigation">
                        <button class="tab-button active" onclick="switchTab('manage-categories')">
                            <i class="material-icons">category</i>
                            Manage Categories
                        </button>
                        <button class="tab-button" onclick="switchTab('low-stock-threshold')">
                            <i class="material-icons">warning</i>
                            Low Stock Threshold
                        </button>
                    </div>

                    <!-- Manage Categories Tab -->
                    <div id="manage-categories" class="tab-content active">
                        <div class="category-section">
                            <!-- Add New Category Form -->
                            <div class="add-category-form">
                                <h3>Add New Category</h3>
                                <form id="addCategoryForm">
                                    <div class="form-row">
                                        <div class="form-group">
                                            <label for="categoryName">Category Name</label>
                                            <input type="text" id="categoryName" name="categoryName" required placeholder="Enter category name">
                                        </div>
                                        <div class="form-group">
                                            <label for="hasSubcategories">Allow Subcategories</label>
                                            <select id="hasSubcategories" name="hasSubcategories">
                                                <option value="0">No</option>
                                                <option value="1">Yes</option>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <button type="submit" class="btn btn-primary">
                                                <i class="material-icons">add</i> Add Category
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>

                            <!-- Categories List -->
                            <div class="categories-list" id="categoriesList">
                                <!-- Categories will be loaded here -->
                            </div>
                        </div>
                    </div>

                    <!-- Low Stock Threshold Tab -->
                    <div id="low-stock-threshold" class="tab-content">
                        <h3>Low Stock Threshold Configuration</h3>
                        <div class="threshold-display-group">
                            <div class="threshold-display-wrapper">
                                <div class="threshold-display-value">
                                    <span class="value" id="currentThresholdDisplay"><?php echo $current_threshold; ?></span>
                                    <span class="unit">units</span>
                                </div>
                                <button type="button" class="btn-edit-threshold" onclick="openThresholdModal()">
                                    <i class="material-icons">edit</i>
                                    Edit Threshold
                                </button>
                            </div>
                            <div class="threshold-display-description">
                                Items with quantity at or below this number will be marked as <b>Low Stock</b>.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Threshold Edit Modal -->
    <div id="thresholdModal" class="threshold-modal">
        <div class="threshold-modal-content">
            <div class="threshold-modal-header">
                <i class="material-icons">warning</i>
                <h3>Edit Low Stock Threshold</h3>
            </div>
            <div class="threshold-modal-body">
                <form id="thresholdForm">
                    <div class="form-group">
                        <label for="modal_threshold_input">Low Stock Threshold</label>
                        <div class="threshold-input-wrapper">
                            <input type="number" id="modal_threshold_input" name="modal_threshold_input" min="1" required>
                            <span>units</span>
                        </div>
                        <small>Items with quantity at or below this number will be marked as <b>Low Stock</b>.</small>
                    </div>
                </form>
            </div>
            <div class="threshold-modal-footer">
                <button type="button" class="btn-cancel" onclick="closeThresholdModal()">
                    <i class="material-icons">close</i>
                    Cancel
                </button>
                <button type="button" class="btn-save" id="saveThresholdBtn" onclick="saveThreshold()">
                    <i class="material-icons">save</i>
                    <span id="saveThresholdText">Save Changes</span>
                </button>
            </div>
        </div>
    </div>

    <script src="../Javascript/logout-modal.js"></script>
    <script>
        // Tab switching functionality
        function switchTab(tabId) {
            // Hide all tab contents
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Remove active class from all tab buttons
            document.querySelectorAll('.tab-button').forEach(btn => {
                btn.classList.remove('active');
            });
            
            // Show selected tab content
            document.getElementById(tabId).classList.add('active');
            
            // Add active class to clicked button
            event.target.classList.add('active');
            
            // Load categories when switching to manage categories tab
            if (tabId === 'manage-categories') {
                loadCategories();
            }
        }

        // Load categories from API
        async function loadCategories() {
            try {
                document.getElementById('categoriesList').innerHTML = '<p>Loading categories...</p>';
                
                const response = await fetch('../PAMO Inventory backend/api_sidebar_categories.php');
                const data = await response.json();
                
                if (data.success) {
                    displayCategories(data.categories);
                } else {
                    document.getElementById('categoriesList').innerHTML = '<p class="error">Failed to load categories</p>';
                }
            } catch (error) {
                document.getElementById('categoriesList').innerHTML = '<p class="error">Error loading categories</p>';
                console.error('Error:', error);
            }
        }

        // Display categories in the UI
        function displayCategories(categories) {
            const container = document.getElementById('categoriesList');
            
            if (categories.length === 0) {
                container.innerHTML = '<p>No categories found. Add your first category above.</p>';
                return;
            }
            
            let html = '';
            categories.forEach(category => {
                html += `
                    <div class="category-item">
                        <div class="category-header">
                            <div class="category-name">${category.name}</div>
                            <div class="category-actions">
                                ${category.has_subcategories ? `<button class="btn btn-secondary btn-sm" onclick="toggleSubcategoryForm(${category.id})">
                                    <i class="material-icons">add</i> Add Subcategory
                                </button>` : ''}
                                ${!category.is_legacy ? `<button class="btn btn-danger btn-sm" onclick="deleteCategory(${category.id}, '${category.name}')">
                                    <i class="material-icons">delete</i> Delete
                                </button>` : ''}
                            </div>
                        </div>
                        
                        ${category.has_subcategories ? `
                            <div class="add-subcategory-form hidden" id="subcategoryForm_${category.id}">
                                <form onsubmit="addSubcategory(event, ${category.id})">
                                    <div class="form-row">
                                        <div class="form-group">
                                            <input type="text" name="subcategoryName" placeholder="Enter subcategory name" required>
                                        </div>
                                        <div class="form-group">
                                            <button type="submit" class="btn btn-success btn-sm">
                                                <i class="material-icons">add</i> Add
                                            </button>
                                            <button type="button" class="btn btn-secondary btn-sm" onclick="toggleSubcategoryForm(${category.id})">
                                                Cancel
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        ` : ''}
                        
                        ${category.subcategories.length > 0 ? `
                            <div class="subcategories-list">
                                ${category.subcategories.map(sub => `
                                    <div class="subcategory-item">
                                        <span>${sub.name}</span>
                                        <button class="btn btn-danger btn-sm" onclick="deleteSubcategory(${sub.id}, '${sub.name}')">
                                            <i class="material-icons">delete</i>
                                        </button>
                                    </div>
                                `).join('')}
                            </div>
                        ` : ''}
                    </div>
                `;
            });
            
            container.innerHTML = html;
        }

        // Add new category
        document.getElementById('addCategoryForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(e.target);
            const categoryName = formData.get('categoryName').trim();
            const hasSubcategories = parseInt(formData.get('hasSubcategories'));
            
            if (!categoryName) {
                alert('Please enter a category name');
                return;
            }
            
            try {
                const response = await fetch('../PAMO Inventory backend/api_categories_create.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        name: categoryName,
                        has_subcategories: hasSubcategories
                    })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    e.target.reset();
                    loadCategories();
                    showMessage('Category added successfully!', 'success');
                } else {
                    showMessage('Failed to add category: ' + (result.message || 'Unknown error'), 'error');
                }
            } catch (error) {
                showMessage('Error adding category', 'error');
                console.error('Error:', error);
            }
        });

        // Add subcategory
        async function addSubcategory(event, categoryId) {
            event.preventDefault();
            
            const formData = new FormData(event.target);
            const subcategoryName = formData.get('subcategoryName').trim();
            
            if (!subcategoryName) {
                alert('Please enter a subcategory name');
                return;
            }
            
            try {
                const response = await fetch('../PAMO Inventory backend/api_subcategories_create.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        category_id: categoryId,
                        name: subcategoryName
                    })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    toggleSubcategoryForm(categoryId);
                    loadCategories();
                    showMessage('Subcategory added successfully!', 'success');
                } else {
                    showMessage('Failed to add subcategory: ' + (result.message || 'Unknown error'), 'error');
                }
            } catch (error) {
                showMessage('Error adding subcategory', 'error');
                console.error('Error:', error);
            }
        }

        // Toggle subcategory form visibility
        function toggleSubcategoryForm(categoryId) {
            const form = document.getElementById(`subcategoryForm_${categoryId}`);
            if (form) {
                form.classList.toggle('hidden');
                if (!form.classList.contains('hidden')) {
                    form.querySelector('input[name="subcategoryName"]').focus();
                }
            }
        }

        // Delete category
        async function deleteCategory(categoryId, categoryName) {
            if (!confirm(`Are you sure you want to delete the category "${categoryName}"? This will also delete all its subcategories.`)) {
                return;
            }
            
            // Create form and submit
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="delete_category" value="1">
                <input type="hidden" name="category_id" value="${categoryId}">
            `;
            document.body.appendChild(form);
            form.submit();
        }

        // Delete subcategory
        async function deleteSubcategory(subcategoryId, subcategoryName) {
            if (!confirm(`Are you sure you want to delete the subcategory "${subcategoryName}"?`)) {
                return;
            }
            
            // Create form and submit
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="delete_subcategory" value="1">
                <input type="hidden" name="subcategory_id" value="${subcategoryId}">
            `;
            document.body.appendChild(form);
            form.submit();
        }

        // Show message
        function showMessage(message, type) {
            // Create message element
            const messageDiv = document.createElement('div');
            messageDiv.className = `message ${type}`;
            messageDiv.innerHTML = `
                <i class="material-icons">${type === 'success' ? 'check_circle' : 'error'}</i>
                ${message}
            `;
            
            // Insert at top of settings card
            const settingsCard = document.querySelector('.settings-card');
            settingsCard.insertBefore(messageDiv, settingsCard.querySelector('.tab-navigation'));
            
            // Auto-remove after 5 seconds with fade-out animation
            setTimeout(() => {
                messageDiv.style.opacity = '0';
                messageDiv.style.transform = 'translateY(-10px)';
                messageDiv.style.transition = 'all 0.3s ease';
                setTimeout(() => {
                    messageDiv.remove();
                }, 300);
            }, 5000);
        }

        // Load categories on page load
        document.addEventListener('DOMContentLoaded', function() {
            loadCategories();
        });

        // Threshold Modal Functions
        function openThresholdModal() {
            const modal = document.getElementById('thresholdModal');
            const input = document.getElementById('modal_threshold_input');
            const currentValue = document.getElementById('currentThresholdDisplay').textContent;
            
            // Set current value in modal input
            input.value = currentValue;
            
            // Show modal
            modal.classList.add('active');
            
            // Focus on input
            setTimeout(() => {
                input.focus();
                input.select();
            }, 100);
        }

        function closeThresholdModal() {
            const modal = document.getElementById('thresholdModal');
            modal.classList.remove('active');
            
            // Reset button state
            const saveBtn = document.getElementById('saveThresholdBtn');
            const saveText = document.getElementById('saveThresholdText');
            saveBtn.disabled = false;
            saveBtn.classList.remove('loading');
            saveText.textContent = 'Save Changes';
        }

        async function saveThreshold() {
            const input = document.getElementById('modal_threshold_input');
            const newThreshold = parseInt(input.value);
            
            // Validation
            if (!newThreshold || newThreshold <= 0) {
                alert('Please enter a valid threshold value (greater than 0)');
                return;
            }
            
            const saveBtn = document.getElementById('saveThresholdBtn');
            const saveText = document.getElementById('saveThresholdText');
            const saveIcon = saveBtn.querySelector('i.material-icons');
            
            // Disable button and show loading state
            saveBtn.disabled = true;
            saveBtn.classList.add('loading');
            saveIcon.textContent = 'hourglass_empty';
            saveText.textContent = 'Saving changes...';
            
            try {
                const response = await fetch('includes/update_threshold.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        low_stock_threshold: newThreshold
                    })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    // Update display value
                    document.getElementById('currentThresholdDisplay').textContent = newThreshold;
                    
                    // Close modal
                    closeThresholdModal();
                    
                    // Show success message
                    showMessage(result.message, 'success');
                } else {
                    // Reset button state
                    saveBtn.disabled = false;
                    saveBtn.classList.remove('loading');
                    saveIcon.textContent = 'save';
                    saveText.textContent = 'Save Changes';
                    
                    // Show error message
                    alert('Error: ' + (result.message || 'Failed to update threshold'));
                }
            } catch (error) {
                // Reset button state
                saveBtn.disabled = false;
                saveBtn.classList.remove('loading');
                saveIcon.textContent = 'save';
                saveText.textContent = 'Save Changes';
                
                console.error('Error:', error);
                alert('An error occurred while updating the threshold');
            }
        }

        // Allow Enter key to submit in modal
        document.getElementById('modal_threshold_input').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                saveThreshold();
            }
        });

        // Close modal when clicking outside
        document.getElementById('thresholdModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeThresholdModal();
            }
        });
    </script>
</body>
</html> 