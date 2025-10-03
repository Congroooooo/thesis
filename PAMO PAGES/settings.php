<?php
session_start();
include 'includes/config_functions.php';
include '../includes/connection.php';
if (!isset($_SESSION['user_id'])) {
    header("Location: ../Pages/login.php?redirect=../PAMO PAGES/settings.php");
    exit();
}
$role = strtoupper($_SESSION['role_category'] ?? '');
$programAbbr = strtoupper($_SESSION['program_abbreviation'] ?? '');
if (!($role === 'EMPLOYEE' && $programAbbr === 'PAMO')) {
    header("Location: ../Pages/home.php");
    exit();
}

// Handle low stock threshold update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_threshold'])) {
    $newThreshold = intval($_POST['low_stock_threshold']);
    $oldThreshold = getLowStockThreshold($conn);
    if ($newThreshold > 0) {
        if (updateLowStockThreshold($conn, $newThreshold)) {
            $success_message = "Low stock threshold updated successfully!";
            $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
            $desc = "Low stock threshold changed from $oldThreshold to $newThreshold.";
            logActivity($conn, 'Low Stock Update', $desc, $user_id);
        } else {
            $error_message = "Failed to update low stock threshold.";
        }
    } else {
        $error_message = "Threshold must be greater than 0.";
    }
}

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

include 'includes/pamo_loader.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PAMO - Settings</title>
    <link rel="stylesheet" href="../PAMO CSS/styles.css">
    <link rel="stylesheet" href="../CSS/logout-modal.css">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .settings-container {
            padding: 20px;
        }
        .settings-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .settings-title {
            font-size: 1.5em;
            margin-bottom: 20px;
            color: #333;
        }
        
        /* Tab Navigation */
        .tab-navigation {
            display: flex;
            border-bottom: 2px solid #e0e0e0;
            margin-bottom: 25px;
        }
        .tab-button {
            background: none;
            border: none;
            padding: 15px 25px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 500;
            color: #666;
            border-bottom: 3px solid transparent;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .tab-button:hover {
            background-color: #f8f9fa;
            color: #333;
        }
        .tab-button.active {
            color: #007bff;
            border-bottom-color: #007bff;
            background-color: #f8f9fa;
        }
        .tab-content {
            display: none;
        }
        .tab-content.active {
            display: block;
        }
        
        /* Category Management Styles */
        .category-section {
            margin-bottom: 30px;
        }
        .add-category-form {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 25px;
        }
        .categories-list {
            max-height: 500px;
            overflow-y: auto;
        }
        .category-item {
            background: white;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            transition: box-shadow 0.2s;
        }
        .category-item:hover {
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .category-header {
            display: flex;
            justify-content: between;
            align-items: center;
            margin-bottom: 10px;
        }
        .category-name {
            font-weight: bold;
            font-size: 16px;
            color: #333;
            flex: 1;
        }
        .category-actions {
            display: flex;
            gap: 8px;
        }
        .subcategories-list {
            margin-top: 15px;
            padding-left: 20px;
            border-left: 3px solid #e0e0e0;
        }
        .subcategory-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        .subcategory-item:last-child {
            border-bottom: none;
        }
        .add-subcategory-form {
            margin-top: 15px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 6px;
        }
        
        /* Form Styles */
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
        }
        .form-group input, .form-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
        }
        .form-row {
            display: flex;
            gap: 15px;
            align-items: end;
        }
        .form-row .form-group {
            flex: 1;
            margin-bottom: 0;
        }
        
        /* Button Styles */
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: background-color 0.2s;
        }
        .btn-primary {
            background: #007bff;
            color: white;
        }
        .btn-primary:hover {
            background: #0056b3;
        }
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        .btn-secondary:hover {
            background: #545b62;
        }
        .btn-success {
            background: #28a745;
            color: white;
        }
        .btn-success:hover {
            background: #1e7e34;
        }
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        .btn-danger:hover {
            background: #c82333;
        }
        .btn-sm {
            padding: 6px 12px;
            font-size: 12px;
        }
        
        .save-btn {
            background: #007bff;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
        }
        .save-btn:hover {
            background: #0056b3;
        }
        .message {
            padding: 10px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        .success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .loading {
            opacity: 0.6;
            pointer-events: none;
        }
        .hidden {
            display: none !important;
        }
    </style>
</head>
<body>
    <div class="container">
        <?php include 'includes/sidebar.php'; ?>

        <main class="main-content">
            <div class="settings-container">
                <div class="settings-card">
                    <div class="settings-header" style="display: flex; align-items: center; gap: 12px; margin-bottom: 25px;">
                        <i class="material-icons" style="font-size: 2.2em; color: #007bff;">settings</i>
                        <div>
                            <h2 class="settings-title" style="margin: 0;">Settings</h2>
                            <p style="margin: 2px 0 0 0; color: #666; font-size: 1.08em;">Configure categories and system settings for inventory management.</p>
                        </div>
                    </div>

                    <?php if (isset($success_message)): ?>
                        <div class="message success" style="display: flex; align-items: center; gap: 8px;">
                            <i class="material-icons">check_circle</i> <?php echo $success_message; ?>
                        </div>
                    <?php endif; ?>
                    <?php if (isset($error_message)): ?>
                        <div class="message error" style="display: flex; align-items: center; gap: 8px;">
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
                                <h3 style="margin-top: 0;">Add New Category</h3>
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
                        <form method="POST" style="max-width: 400px; margin-top: 18px;">
                            <div class="form-group" style="margin-bottom: 24px;">
                                <label for="low_stock_threshold" style="font-size: 1.1em;">Low Stock Threshold</label>
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <input type="number" id="low_stock_threshold" name="low_stock_threshold" value="<?php echo $current_threshold; ?>" min="1" required style="flex: 1; font-size: 1.1em; padding: 10px; border: 1.5px solid #bfc9d1; border-radius: 6px;">
                                    <span style="color: #888; font-size: 1.1em;">units</span>
                                </div>
                                <small style="color: #888; margin-top: 6px; display: block;">Items with quantity at or below this number will be marked as <b>Low Stock</b>.</small>
                            </div>
                            <button type="submit" name="update_threshold" class="save-btn" style="width: 100%; font-size: 1.1em; display: flex; align-items: center; justify-content: center; gap: 8px;">
                                <i class="material-icons">save</i> Save Changes
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </main>
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
            } finally {
                // Ensure loader is hidden after loading categories
                setTimeout(() => {
                    if (window.PAMOLoader) {
                        window.PAMOLoader.hide();
                    }
                }, 100);
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
                // Hide loader if validation fails
                setTimeout(() => {
                    if (window.PAMOLoader) {
                        window.PAMOLoader.hide();
                    }
                }, 50);
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
            } finally {
                // Always hide the loader when the operation completes
                setTimeout(() => {
                    if (window.PAMOLoader) {
                        window.PAMOLoader.hide();
                    }
                }, 100);
            }
        });

        // Add subcategory
        async function addSubcategory(event, categoryId) {
            event.preventDefault();
            
            const formData = new FormData(event.target);
            const subcategoryName = formData.get('subcategoryName').trim();
            
            if (!subcategoryName) {
                alert('Please enter a subcategory name');
                // Hide loader if validation fails
                setTimeout(() => {
                    if (window.PAMOLoader) {
                        window.PAMOLoader.hide();
                    }
                }, 50);
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
            } finally {
                // Always hide the loader when the operation completes
                setTimeout(() => {
                    if (window.PAMOLoader) {
                        window.PAMOLoader.hide();
                    }
                }, 100);
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
            
            // Remove after 5 seconds
            setTimeout(() => {
                messageDiv.remove();
            }, 5000);
        }

        // Load categories on page load
        document.addEventListener('DOMContentLoaded', function() {
            loadCategories();
        });
    </script>
</body>
</html> 