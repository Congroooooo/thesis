<?php
session_start();
include 'includes/config_functions.php';
include 'includes/pamo_loader.php';

if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_id'])) {
    header("Location: ../Pages/login.php?redirect=../PAMO_PAGES/inventory.php");
    exit();
}
$role = strtoupper($_SESSION['role_category'] ?? '');
$programAbbr = strtoupper($_SESSION['program_abbreviation'] ?? '');
if (!($role === 'EMPLOYEE' && $programAbbr === 'PAMO')) {
    header("Location: ../Pages/home.php");
    exit();
}

require_once '../Includes/connection.php';
$categories_query = "SELECT DISTINCT category FROM inventory WHERE category IS NOT NULL AND category != '' ORDER BY category ASC";
$categories_result = $conn->query($categories_query);
$categories = [];
while ($row = $categories_result->fetch(PDO::FETCH_ASSOC)) {
    $categories[] = $row['category'];
}

$sizes_query = "SELECT DISTINCT sizes FROM inventory WHERE sizes IS NOT NULL AND sizes != '' ORDER BY 
    CASE 
        WHEN sizes = 'XS' THEN 1
        WHEN sizes = 'S' THEN 2
        WHEN sizes = 'M' THEN 3
        WHEN sizes = 'L' THEN 4
        WHEN sizes = 'XL' THEN 5
        WHEN sizes = 'XXL' THEN 6
        WHEN sizes = '3XL' THEN 7
        WHEN sizes = '4XL' THEN 8
        WHEN sizes = '5XL' THEN 9
        WHEN sizes = '6XL' THEN 10
        WHEN sizes = '7XL' THEN 11
        WHEN sizes = 'One Size' THEN 12
        ELSE 13
    END ASC";
$sizes_result = $conn->query($sizes_query);
$sizes = [];
while ($row = $sizes_result->fetch(PDO::FETCH_ASSOC)) {
    $sizes[] = $row['sizes'];
}

$query_params = $_GET;
unset($query_params['page']);
$query_string = http_build_query($query_params);

function page_link($page, $query_string) {
    return "?page=$page" . ($query_string ? "&$query_string" : "");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PAMO - Inventory</title>
    <link rel="stylesheet" href="../PAMO CSS/styles.css">
    <link rel="stylesheet" href="../PAMO CSS/inventory.css">
    <link rel="stylesheet" href="../CSS/logout-modal.css">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="../Javascript/logout-modal.js"></script>
    <script>
      window.PAMO_USER = {
        name: "<?php echo addslashes($_SESSION['name'] ?? ''); ?>"
      };
    </script>
    <script src="../PAMO JS/inventory.js"></script>
    <script src="../PAMO JS/backend/addItem.js"></script>
    <script src="../PAMO JS/backend/editItem.js"></script>
    <script src="../PAMO JS/backend/addQuantity.js"></script>
    <script src="../PAMO JS/backend/deductQuantity.js"></script>
    <script src="../PAMO JS/backend/addItemSize.js"></script>
    <script src="../PAMO JS/backend/exchangeItem.js"></script>
    <style>
        .file-info {
            color: #666 !important;
            font-size: 12px !important;
            margin-top: 4px !important;
            display: block !important;
        }
        
        .file-info.large-file {
            color: #e74c3c !important;
            font-weight: bold !important;
        }
        
        .file-info.good-size {
            color: #27ae60 !important;
        }
        
        .processing-images {
            color: #3498db !important;
            font-weight: bold !important;
        }
        
        /* Base item code validation styles */
        input.valid-base-code {
            border-color: #27ae60 !important;
            box-shadow: 0 0 0 2px rgba(39, 174, 96, 0.2) !important;
        }
        
        input.invalid-base-code {
            border-color: #e74c3c !important;
            box-shadow: 0 0 0 2px rgba(231, 76, 60, 0.2) !important;
        }
        
        .base-code-validation {
            font-size: 12px !important;
            margin-top: 4px !important;
            display: block !important;
        }
    </style>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const applyLowStockFilter = sessionStorage.getItem('applyLowStockFilter');
            console.log('Checking for low stock filter:', applyLowStockFilter);
            if (applyLowStockFilter === 'true') {
                const urlParams = new URLSearchParams(window.location.search);
                console.log('Current URL params:', urlParams.toString());
                if (!urlParams.has('status')) {
                    urlParams.set('status', 'Low Stock');
                    const newUrl = 'inventory.php?' + urlParams.toString();
                    console.log('Redirecting to:', newUrl);
                    window.location.href = newUrl;
                } else {
                    console.log('Status already set, removing session storage');
                }
                sessionStorage.removeItem('applyLowStockFilter');
            }
        });

        document.addEventListener("DOMContentLoaded", function () {
            if (window.jQuery && $("#existingItem").length) {
                $("#existingItem").select2({
                    placeholder: "Select Item",
                    allowClear: true,
                    width: "100%"
                });
            }

            if (window.jQuery && $("#exchangeCustomerName").length) {
                $("#exchangeCustomerName").select2({
                    placeholder: "Search customer...",
                    allowClear: true,
                    width: "100%"
                });

                $("#exchangeCustomerName").on('change', function() {
                    loadCustomerPurchases();
                });
            }

            // Initialize Select2 for Add New Item Size modal dropdowns
            initializeItemSizeSelect2();
        });

        function initializeItemSizeSelect2() {
            // Wait for jQuery and Select2 to be available
            if (typeof $ === 'undefined') {
                return;
            }

            // Initialize Select2 for item dropdowns in Add New Item Size modal
            $('#addItemSizeModal .item-size-select').each(function() {
                const $select = $(this);
                
                if (!$select.hasClass('select2-hidden-accessible')) {
                    $select.select2({
                        placeholder: "Search and select an item...",
                        allowClear: true,
                        width: "100%",
                        dropdownParent: $('#addItemSizeModal'),
                        templateResult: function(data) {
                            if (data.loading) {
                                return data.text;
                            }
                            
                            // Enhanced display with category information
                            var $container = $(
                                '<div class="select2-result-item">' +
                                    '<div class="item-name">' + data.text + '</div>' +
                                '</div>'
                            );
                            return $container;
                        }
                    });

                    // Attach the change event after Select2 initialization
                    $select.on('change', function() {
                        const itemIndex = this.id.split('_')[1];
                        
                        if (typeof fetchAndUpdateSizesForItem === 'function') {
                            fetchAndUpdateSizesForItem(parseInt(itemIndex));
                        }
                    });
                }
            });
        }

        function clearLowStockSessionAndReload() {
            sessionStorage.removeItem('applyLowStockFilter');
            window.location.href = 'inventory.php';
        }
    </script>
</head>

<body>
    <div class="container">
        <?php include 'includes/sidebar.php'; ?>

        <main class="main-content">

        <div class="filters">
            <h3>Filters</h3>
            <form id="filterForm" method="get" style="margin-bottom: 20px; display: flex; align-items: center; gap: 12px;">
                <input type="text" id="searchInput" name="search" placeholder="Search by item name..." value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>" style="margin-right: 12px;">
                <select name="category" id="categoryFilter">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo htmlspecialchars($cat); ?>"<?php if(($_GET['category'] ?? '') == $cat) echo ' selected'; ?>><?php echo htmlspecialchars($cat); ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="size" id="sizeFilter">
                    <option value="">All Sizes</option>
                    <?php foreach ($sizes as $size_option): ?>
                        <option value="<?php echo htmlspecialchars($size_option); ?>"<?php if(($_GET['size'] ?? '') == $size_option) echo ' selected'; ?>><?php echo htmlspecialchars($size_option); ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="status" id="statusFilter">
                    <option value="">All Status</option>
                    <option value="In Stock"<?php if(($_GET['status'] ?? '')=='In Stock') echo ' selected'; ?>>In Stock</option>
                    <option value="Low Stock"<?php if(($_GET['status'] ?? '')=='Low Stock') echo ' selected'; ?>>Low Stock</option>
                    <option value="Out of Stock"<?php if(($_GET['status'] ?? '')=='Out of Stock') echo ' selected'; ?>>Out of Stock</option>
                </select>
                <button type="button" onclick="clearLowStockSessionAndReload()" class="clear-filters-btn">
                    <i class="material-icons">clear</i> Clear Filters
                </button>
            </form>
        </div>

            <div class="inventory-content">
                <div class="action-buttons-container">
                    <button onclick="handleEdit()" class="action-btn" id="editBtn" disabled>
                        <i class="material-icons">edit</i> Edit
                    </button>
                    <button onclick="showAddItemModal()" class="action-btn">
                        <i class="material-icons">add_circle</i> New Product
                    </button>
                    <button onclick="showAddItemSizeModal()" class="action-btn">
                        <i class="material-icons">add_box</i> Add Item Size
                    </button>
                    <button onclick="showAddQuantityModal()" class="action-btn">
                        <i class="material-icons">local_shipping</i> Restock Item
                    </button>
                    <button onclick="showDeductQuantityModal()" class="action-btn">
                        <i class="material-icons">remove_shopping_cart</i> Sales Entry
                    </button>
                    <button onclick="showExchangeItemModal()" class="action-btn">
                        <i class="material-icons">swap_horiz</i> Exchange Item
                    </button>
                </div>

                <div class="inventory-table">
                  <table>
                      <thead>
                          <tr>
                              <th>Item Code</th>
                              <th>Item Name</th>
                              <th>Category</th>
                              <th>Actual Quantity</th>
                              <th>Sizes</th>
                              <th>Price</th>
                              <th>Status</th>
                          </tr>
                      </thead>
                      <tbody>
                          <?php
                          $category = $_GET['category'] ?? '';
                          $size = $_GET['size'] ?? '';
                          $status = $_GET['status'] ?? '';
                          $search = $_GET['search'] ?? '';
                          $where_conditions = [];
                          $params = [];
                          
                          if ($category) {
                              $where_conditions[] = "category = ?";
                              $params[] = $category;
                          }
                          if ($size) {
                              $where_conditions[] = "sizes = ?";
                              $params[] = $size;
                          }
                          if ($status) {
                              $lowStockThreshold = getLowStockThreshold($conn);
                              if ($status == 'In Stock') {
                                  $where_conditions[] = "actual_quantity > ?";
                                  $params[] = $lowStockThreshold;
                              } else if ($status == 'Low Stock') {
                                  $where_conditions[] = "actual_quantity > 0 AND actual_quantity <= ?";
                                  $params[] = $lowStockThreshold;
                              } else if ($status == 'Out of Stock') {
                                  $where_conditions[] = "actual_quantity <= 0";
                              }
                          }
                          if ($search) {
                              $where_conditions[] = "(item_name LIKE ? OR item_code LIKE ?)";
                              $params[] = "%$search%";
                              $params[] = "%$search%";
                          }

                          $where_clause = $where_conditions ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
                          $page = max(1, intval($_GET['page'] ?? 1));
                          $limit = 15;
                          $offset = ($page - 1) * $limit;

                          $total_sql = "SELECT COUNT(*) as total FROM inventory $where_clause";
                          $total_stmt = $conn->prepare($total_sql);
                          $total_stmt->execute($params);
                          $total_row = $total_stmt->fetch(PDO::FETCH_ASSOC);
                          $total_items = $total_row['total'];
                          $total_pages = ceil($total_items / $limit);

                          $sql = "SELECT * FROM inventory $where_clause ORDER BY created_at DESC LIMIT $limit OFFSET $offset";
                          $stmt = $conn->prepare($sql);
                          $stmt->execute($params);

                          $lowStockThreshold = getLowStockThreshold($conn);

                          while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                              $statusClass = '';
                              if ($row['actual_quantity'] <= 0) {
                                  $display_status = 'Out of Stock';
                                  $statusClass = 'status-out-of-stock';
                              } else if ($row['actual_quantity'] <= $lowStockThreshold) {
                                  $display_status = 'Low Stock';
                                  $statusClass = 'status-low-stock';
                              } else {
                                  $display_status = 'In Stock';
                                  $statusClass = 'status-in-stock';
                              }

                              echo "<tr data-item-code='" . htmlspecialchars($row['item_code']) . "' data-created-at='" . htmlspecialchars($row['created_at']) . "' data-category='" . strtolower(htmlspecialchars($row['category'])) . "' onclick='selectRow(this, \"" . htmlspecialchars($row['item_code']) . "\", " . $row['price'] . ")'>";
                              echo "<td>" . htmlspecialchars($row['item_code']) . "</td>";
                              echo "<td>" . htmlspecialchars($row['item_name']) . "</td>";
                              echo "<td>" . htmlspecialchars($row['category']) . "</td>";
                              echo "<td>" . (isset($row['actual_quantity']) ? $row['actual_quantity'] : '0') . "</td>";
                              echo "<td>" . htmlspecialchars($row['sizes']) . "</td>";
                              echo "<td>₱" . number_format($row['price'], 2) . "</td>";
                              echo "<td class='" . $statusClass . "'>" . $display_status . "</td>";
                              echo "<!-- Item Code: " . htmlspecialchars($row['item_code']) . " -->";
                              echo "</tr>";
                          }
                          ?>
                      </tbody>
                  </table>
                </div>
                <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="<?php echo page_link($page-1, $query_string); ?>" class="ajax-page-link">&laquo;</a>
                    <?php endif; ?>
                    <?php
                    if ($page == 1) {
                        echo '<a href="' . page_link(1, $query_string) . '" class="ajax-page-link active">1</a>';
                    } else {
                        echo '<a href="' . page_link(1, $query_string) . '" class="ajax-page-link">1</a>';
                    }
                    if ($page > 4) {
                        echo '<span class="pagination-ellipsis">...</span>';
                    }
                    $window = 1;
                    $start = max(2, $page - $window);
                    $end = min($total_pages - 1, $page + $window);
                    for ($i = $start; $i <= $end; $i++) {
                        if ($i == $page) {
                            echo '<a href="' . page_link($i, $query_string) . '" class="ajax-page-link active">' . $i . '</a>';
                        } else {
                            echo '<a href="' . page_link($i, $query_string) . '" class="ajax-page-link">' . $i . '</a>';
                        }
                    }
                    if ($page < $total_pages - 3) {
                        echo '<span class="pagination-ellipsis">...</span>';
                    }
                    if ($total_pages > 1) {
                        if ($page == $total_pages) {
                            echo '<a href="' . page_link($total_pages, $query_string) . '" class="ajax-page-link active">' . $total_pages . '</a>';
                        } else {
                            echo '<a href="' . page_link($total_pages, $query_string) . '" class="ajax-page-link">' . $total_pages . '</a>';
                        }
                    }
                    ?>
                    <?php if ($page < $total_pages): ?>
                        <a href="<?php echo page_link($page+1, $query_string); ?>" class="ajax-page-link">&raquo;</a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <div id="editItemModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Edit Item</h2>
                <span class="close" onclick="closeModal('editItemModal')">&times;</span>
            </div>
            <div class="modal-body">
                <input type="hidden" id="editItemId">
                <div class="input-group">
                    <label for="editItemCode">Item Code:</label>
                    <input type="text" id="editItemCode" disabled>
                </div>
                <div class="input-group">
                    <label for="editItemName">Item Name:</label>
                    <input type="text" id="editItemName" disabled>
                </div>
                <div class="input-group">
                    <label for="editCategory">Category:</label>
                    <input type="text" id="editCategory" disabled>
                </div>
                <div class="input-group">
                    <label for="editActualQuantity">Actual Quantity:</label>
                    <input type="number" id="editActualQuantity" disabled>
                </div>
                <div class="input-group">
                    <label for="editSize">Size:</label>
                    <input type="text" id="editSize" disabled>
                </div>
                <div class="input-group">
                    <label for="editPrice">Price:</label>
                    <input type="number" id="editPrice" disabled>
                </div>
            </div>
            <div class="modal-footer">
                <button onclick="showEditPriceModal()" class="save-btn">Edit Price</button>
                <button onclick="showEditImageModal()" class="save-btn">Edit Image</button>
                <button onclick="closeModal('editItemModal')" class="cancel-btn">Cancel</button>
            </div>
        </div>
    </div>

    <div id="addItemModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Add New Product(s)</h2>
                <span class="close" onclick="closeModal('addItemModal')">&times;</span>
            </div>
            <div class="modal-body">
                <form id="addItemForm" onsubmit="submitNewItem(event)" enctype="multipart/form-data">
                    <div class="delivery-order-section">
                        <h3>Delivery Information</h3>
                        <div class="input-group">
                            <label for="deliveryOrderNumber">Delivery Order #:</label>
                            <input type="text" id="deliveryOrderNumber" name="deliveryOrderNumber" required>
                        </div>
                    </div>

                    <div id="productsContainer">
                        <div class="product-item" data-product-index="0">
                            <div class="product-header">
                                <h3>Product #1</h3>
                                <span class="remove-product-btn" onclick="removeProduct(0)" style="display: none;">&times;</span>
                            </div>
                            
                            <div class="product-basic-info">
                                <h4>Product Information</h4>
                                <div class="input-group">
                                    <label for="newProductItemCode_0">Base Item Code (Prefix):</label>
                                    <input type="text" id="newProductItemCode_0" name="products[0][baseItemCode]" placeholder="e.g., USHPWV001" required maxlength="20">
                                </div>
                                <div class="input-group">
                                    <label for="newCategory_0">Category:</label>
                                    <select id="newCategory_0" name="products[0][category_id]" required>
                                        <option value="">Select Category</option>
                                    </select>
                                </div>
                                <div class="input-group" id="subcategoryGroup_0" style="display:none;">
                                    <label for="subcategorySelect_0">Subcategory:</label>
                                    <select id="subcategorySelect_0" name="products[0][subcategory_ids][]" multiple style="width:100%;">
                                    </select>
                                </div>
                                <div class="input-group">
                                    <label for="newItemName_0">Product Name:</label>
                                    <input type="text" id="newItemName_0" name="products[0][newItemName]" required>
                                </div>
                                <div class="input-group">
                                    <label for="newImage_0">Product Image:</label>
                                    <input type="file" id="newImage_0" name="products[0][newImage]" accept="image/*" required onchange="showFileInfo(this)">
                                    <small id="fileInfo_0" class="file-info" style="color: #666; font-size: 12px; margin-top: 4px; display: block;"></small>
                                </div>
                            </div>

                            <div class="size-selection-section">
                                <h4>Size Selection</h4>
                                <div class="input-group">
                                    <label>Select Sizes:</label>
                                    <div class="size-checkboxes" data-product="0">
                                        <label class="checkbox-label"><input type="checkbox" value="XS" onchange="toggleSizeDetails(this, 0)"> XS</label>
                                        <label class="checkbox-label"><input type="checkbox" value="S" onchange="toggleSizeDetails(this, 0)"> S</label>
                                        <label class="checkbox-label"><input type="checkbox" value="M" onchange="toggleSizeDetails(this, 0)"> M</label>
                                        <label class="checkbox-label"><input type="checkbox" value="L" onchange="toggleSizeDetails(this, 0)"> L</label>
                                        <label class="checkbox-label"><input type="checkbox" value="XL" onchange="toggleSizeDetails(this, 0)"> XL</label>
                                        <label class="checkbox-label"><input type="checkbox" value="XXL" onchange="toggleSizeDetails(this, 0)"> XXL</label>
                                        <label class="checkbox-label"><input type="checkbox" value="3XL" onchange="toggleSizeDetails(this, 0)"> 3XL</label>
                                        <label class="checkbox-label"><input type="checkbox" value="4XL" onchange="toggleSizeDetails(this, 0)"> 4XL</label>
                                        <label class="checkbox-label"><input type="checkbox" value="5XL" onchange="toggleSizeDetails(this, 0)"> 5XL</label>
                                        <label class="checkbox-label"><input type="checkbox" value="6XL" onchange="toggleSizeDetails(this, 0)"> 6XL</label>
                                        <label class="checkbox-label"><input type="checkbox" value="7XL" onchange="toggleSizeDetails(this, 0)"> 7XL</label>
                                        <label class="checkbox-label"><input type="checkbox" value="One Size" onchange="toggleSizeDetails(this, 0)"> One Size</label>
                                    </div>
                                </div>
                            </div>

                            <div id="sizeDetailsContainer_0" class="size-details-container">
                                <h4>Size Details</h4>
                                <div id="sizeDetailsList_0">

                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="add-product-section">
                        <button type="button" class="add-product-btn" onclick="addAnotherProduct()">
                            <i class="material-icons">add_circle</i> Add Another Item
                        </button>
                        <small>Add multiple distinct products under the same delivery order</small>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="submit" form="addItemForm" class="save-btn">Add All Products</button>
                <button type="button" onclick="closeModal('addItemModal')" class="cancel-btn">Cancel</button>
            </div>
        </div>
    </div>

    <div id="editPriceModal" class="modal">
        <div class="modal-content">
            <h2>Edit Price</h2>
            <input type="hidden" id="priceItemId">
            <div class="input-group">
                <label for="newPrice">New Price:</label>
                <input type="number" id="newPrice" step="0.01" min="0" required>
            </div>
            <div class="modal-buttons">
                <button onclick="submitEditPrice()" class="save-btn">Save</button>
                <button onclick="closeModal('editPriceModal')" class="cancel-btn">Cancel</button>
            </div>
        </div>
    </div>

    <div id="editImageModal" class="modal">
        <div class="modal-content">
            <h2>Edit Image</h2>
            <input type="hidden" id="imageItemId">
            <div class="input-group">
                <label for="editNewImage">Upload New Image:</label>
                <input type="file" id="editNewImage" accept="image/*" required>
            </div>
            <div class="modal-buttons">
                <button onclick="submitEditImage()" class="save-btn">Save</button>
                <button onclick="closeModal('editImageModal')" class="cancel-btn">Cancel</button>
            </div>
        </div>
    </div>

    <div id="addQuantityModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Restock Item</h2>
                <span class="close" onclick="closeModal('addQuantityModal')">&times;</span>
            </div>
            <div class="modal-body">
                <form id="addQuantityForm" onsubmit="submitAddQuantity(event)">
                    <div class="order-section">
                        <div class="input-group">
                            <label for="orderNumber">Delivery Order #:</label>
                            <input type="text" id="orderNumber" name="orderNumber" required>
                        </div>
                    </div>
                    
                    <div id="deliveryItems">
                        <div class="delivery-item">
                            <div class="item-close">&times;</div>
                            <div class="item-content">
                                <div class="input-group">
                                    <label for="itemId">Product:</label>
                                    <select name="itemId[]" required>
                                        <option value="">Select Product</option>
                                        <?php
                                        $sql = "SELECT item_code, item_name, category FROM inventory ORDER BY item_name";
                                        $stmt = $conn->query($sql);

                                        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                            echo "<option value='" . htmlspecialchars($row['item_code']) . "'>" . htmlspecialchars($row['item_name']) . " (" . htmlspecialchars($row['item_code']) . ") - " . htmlspecialchars($row['category']) . "</option>";
                                        }
                                        ?>
                                    </select>
                                </div>
                                <div class="input-group">
                                    <label for="quantityToAdd">Delivery Quantity:</label>
                                    <input type="number" name="quantityToAdd[]" min="1" required>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <button type="button" class="add-item-btn" onclick="addDeliveryItem()">
                        <i class="material-icons">add_circle</i> Add Another Item
                    </button>
                </form>
            </div>
            <div class="modal-footer">
                <button type="submit" form="addQuantityForm" class="save-btn">Record Delivery</button>
                <button onclick="closeModal('addQuantityModal')" class="cancel-btn">Cancel</button>
            </div>
        </div>
    </div>

    <div id="deductQuantityModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Sales Entry</h2>
                <span class="close" onclick="closeModal('deductQuantityModal')">&times;</span>
            </div>
            <div class="modal-body">
                <form id="deductQuantityForm" class="instant" onsubmit="submitDeductQuantity(event)">
                    <div class="order-section form-row">
                        <div class="input-group">
                            <label for="transactionNumber">Transaction Number:</label>
                            <input type="text" id="transactionNumber" name="transactionNumber" readonly required>
                        </div>
                        <div class="input-group">
                            <label for="roleCategory">Role:</label>
                            <select id="roleCategory" name="roleCategory" required>
                                <option value="">Select Role</option>
                                <option value="EMPLOYEE">EMPLOYEE</option>
                                <option value="COLLEGE STUDENT">COLLEGE STUDENT</option>
                                <option value="SHS">SHS</option>
                            </select>
                        </div>
                        <div class="input-group">
                            <label for="studentName">Name:</label>
                            <select id="studentName" name="studentName" required>
                                <option value="">Select Name</option>

                            </select>
                        </div>
                        <div class="input-group">
                            <label for="studentIdNumber">ID Number:</label>
                            <input type="text" id="studentIdNumber" name="studentIdNumber" readonly required>
                        </div>
                        <div class="input-group">
                            <label for="cashierName">Cashier Name:</label>
                            <input type="text" id="cashierName" name="cashierName" required>
                        </div>
                    </div>
                    <div id="salesItems">
                        <div class="sales-item form-row">
                            <div class="input-group">
                                <label for="itemId">Product:</label>
                                <select name="itemId[]" required>
                                    <option value="">Select Product</option>
                                    <?php
                                    $sql = "SELECT DISTINCT 
                                            SUBSTRING_INDEX(item_code, '-', 1) as prefix,
                                            item_name,
                                            category
                                            FROM inventory 
                                            WHERE actual_quantity > 0
                                            ORDER BY item_name";
                                    $stmt = $conn->query($sql);
                                    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                        echo "<option value='" . htmlspecialchars($row['prefix']) . "' data-category='" . htmlspecialchars($row['category'], ENT_QUOTES) . "'>" . 
                                             htmlspecialchars($row['item_name']) . " (" . htmlspecialchars($row['prefix']) . ")</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="input-group">
                                <label for="size">Size:</label>
                                <select name="size[]" required>
                                    <option value="">Select Size</option>
                                </select>
                            </div>
                            <div class="input-group">
                                <label for="quantityToDeduct">Quantity Sold:</label>
                                <input type="number" name="quantityToDeduct[]" min="1" required onchange="calculateItemTotal(this)">
                            </div>
                            <div class="input-group">
                                <label for="pricePerItem">Price per Item:</label>
                                <input type="number" name="pricePerItem[]" step="0.01" min="0" required readonly>
                            </div>
                            <div class="input-group">
                                <label for="itemTotal">SubTotal:</label>
                                <input type="number" name="itemTotal[]" step="0.01" min="0" readonly>
                            </div>
                            <div class="item-close">&times;</div>
                        </div>
                    </div>
                    <button type="button" class="add-item-btn" onclick="addSalesItem()">
                        <i class="material-icons">add_circle</i> Add Another Item
                    </button>
                    <div class="total-section">
                        <div class="input-group">
                            <label for="totalAmount">Total Amount:</label>
                            <input type="number" id="totalAmount" name="totalAmount" step="0.01" min="0" readonly>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="submit" form="deductQuantityForm" class="save-btn">Save</button>
                <button onclick="closeModal('deductQuantityModal')" class="cancel-btn">Cancel</button>
            </div>
        </div>
    </div>

    <div id="addItemSizeModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Add New Item Size</h2>
                <span class="close" onclick="closeModal('addItemSizeModal')">&times;</span>
            </div>
            <div class="modal-body">
                <form id="addItemSizeForm" onsubmit="submitNewItemSize(event)">
                    <div class="delivery-order-section">
                        <h3>Delivery Information</h3>
                        <div class="input-group">
                            <label for="deliveryOrderNumberSize">Delivery Order #:</label>
                            <input type="text" id="deliveryOrderNumberSize" name="deliveryOrderNumber" required>
                        </div>
                    </div>

                    <div id="itemsContainer">
                        <div class="item-entry" data-item-index="0">
                            <div class="item-header">
                                <h3>Item #1</h3>
                                <span class="remove-item-btn" onclick="removeItemEntry(0)" style="display: none;">&times;</span>
                            </div>
                            
                            <div class="order-section">
                                <h4>Item Information</h4>
                                <div class="input-group">
                                    <label for="existingItem_0">Select Item:</label>
                                    <select id="existingItem_0" name="items[0][existingItem]" class="item-size-select" required>
                                        <option value="">Select Item</option>
                                        <?php
                                        $sql = "SELECT DISTINCT 
                                                SUBSTRING_INDEX(item_code, '-', 1) as prefix,
                                                item_name,
                                                category
                                                FROM inventory 
                                                ORDER BY item_name";
                                        $stmt = $conn->query($sql);

                                        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                            echo "<option value='" . htmlspecialchars($row['prefix']) . "' data-name='" . htmlspecialchars($row['item_name']) . "' data-category='" . htmlspecialchars($row['category']) . "'>" . 
                                                 htmlspecialchars($row['item_name']) . " (" . htmlspecialchars($row['prefix']) . ")</option>";
                                        }
                                        ?>
                                    </select>
                                </div>
                            </div>

                            <div class="size-selection-section" id="sizeSelectionSection_0" style="display: none;">
                                <h4>Size Selection</h4>
                                <div class="input-group">
                                    <label>Select Available Sizes to Add:</label>
                                    <div class="size-checkboxes" id="sizeCheckboxesContainer_0">
                                        <label class="checkbox-label"><input type="checkbox" value="XS" onchange="toggleSizeDetailsForItemSize(this, 0)"> XS</label>
                                        <label class="checkbox-label"><input type="checkbox" value="S" onchange="toggleSizeDetailsForItemSize(this, 0)"> S</label>
                                        <label class="checkbox-label"><input type="checkbox" value="M" onchange="toggleSizeDetailsForItemSize(this, 0)"> M</label>
                                        <label class="checkbox-label"><input type="checkbox" value="L" onchange="toggleSizeDetailsForItemSize(this, 0)"> L</label>
                                        <label class="checkbox-label"><input type="checkbox" value="XL" onchange="toggleSizeDetailsForItemSize(this, 0)"> XL</label>
                                        <label class="checkbox-label"><input type="checkbox" value="XXL" onchange="toggleSizeDetailsForItemSize(this, 0)"> XXL</label>
                                        <label class="checkbox-label"><input type="checkbox" value="3XL" onchange="toggleSizeDetailsForItemSize(this, 0)"> 3XL</label>
                                        <label class="checkbox-label"><input type="checkbox" value="4XL" onchange="toggleSizeDetailsForItemSize(this, 0)"> 4XL</label>
                                        <label class="checkbox-label"><input type="checkbox" value="5XL" onchange="toggleSizeDetailsForItemSize(this, 0)"> 5XL</label>
                                        <label class="checkbox-label"><input type="checkbox" value="6XL" onchange="toggleSizeDetailsForItemSize(this, 0)"> 6XL</label>
                                        <label class="checkbox-label"><input type="checkbox" value="7XL" onchange="toggleSizeDetailsForItemSize(this, 0)"> 7XL</label>
                                        <label class="checkbox-label"><input type="checkbox" value="One Size" onchange="toggleSizeDetailsForItemSize(this, 0)"> One Size</label>
                                    </div>
                                </div>
                            </div>

                            <div id="itemSizeDetailsContainer_0" class="size-details-container">
                                <h4>Size Details</h4>
                                <div id="itemSizeDetailsList_0">
                                    <!-- Size detail entries will be inserted here dynamically -->
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="add-item-section">
                        <button type="button" class="add-item-btn" onclick="addAnotherItemEntry()">
                            <i class="material-icons">add_circle</i> Add Another Item
                        </button>
                        <small>Add sizes for multiple items under the same delivery order</small>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="submit" form="addItemSizeForm" class="save-btn">Add Sizes</button>
                <button onclick="closeModal('addItemSizeModal')" class="cancel-btn">Cancel</button>
            </div>
        </div>
    </div>

    <div id="salesReceiptModal" class="modal">
        <div class="modal-content" id="salesReceiptContent">
            <div class="modal-header">
                <h2>Sales Receipt</h2>
                <span class="close" onclick="closeModal('salesReceiptModal')">&times;</span>
            </div>
            <div class="modal-body" id="salesReceiptBody">

            </div>
            <div class="modal-footer">
                <button type="button" onclick="printSalesReceipt()" class="save-btn">Print</button>
                <button type="button" onclick="closeModal('salesReceiptModal')" class="cancel-btn">Close</button>
            </div>
        </div>
    </div>

    <div id="exchangeItemModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Exchange Item</h2>
                <span class="close" onclick="closeModal('exchangeItemModal')">&times;</span>
            </div>
            <div class="modal-body">
                <form id="exchangeItemForm" onsubmit="submitExchangeItem(event)">
                    <div class="order-section">
                        <div class="input-group">
                            <label for="exchangeCustomerName">Customer Name:</label>
                            <select id="exchangeCustomerName" name="customerName" required>
                                <option value="">Search customer...</option>
                                <?php
                                // Get customers who have had transactions in the last 24 hours
                                $sql = "SELECT DISTINCT a.id, a.first_name, a.last_name, a.id_number, a.role_category 
                                       FROM account a 
                                       INNER JOIN transaction_customers tc ON a.id = tc.customer_id 
                                       INNER JOIN sales s ON tc.transaction_number = s.transaction_number 
                                       WHERE a.status = 'active' 
                                       AND s.sale_date >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                                       ORDER BY a.first_name, a.last_name";
                                
                                try {
                                    $stmt = $conn->query($sql);
                                    $customers_found = false;
                                    
                                    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                        $customers_found = true;
                                        $fullName = $row['first_name'] . ' ' . $row['last_name'];
                                        echo "<option value='" . $row['id'] . "' data-id-number='" . htmlspecialchars($row['id_number']) . "' data-role='" . htmlspecialchars($row['role_category']) . "'>" . 
                                             htmlspecialchars($fullName) . " (" . htmlspecialchars($row['id_number']) . ")</option>";
                                    }
                                    
                                    // If no customers with recent transactions found, show a message
                                    if (!$customers_found) {
                                        echo "<option value='' disabled>No customers with transactions in the last 24 hours</option>";
                                    }
                                } catch (PDOException $e) {
                                    // Fallback: show all active customers if there's an error with the filtered query
                                    echo "<option value='' disabled>Loading customers...</option>";
                                    error_log("Error loading recent customers: " . $e->getMessage());
                                    
                                    // Fallback query
                                    $fallback_sql = "SELECT id, first_name, last_name, id_number, role_category FROM account WHERE status = 'active' ORDER BY first_name, last_name";
                                    $fallback_stmt = $conn->query($fallback_sql);
                                    
                                    while ($row = $fallback_stmt->fetch(PDO::FETCH_ASSOC)) {
                                        $fullName = $row['first_name'] . ' ' . $row['last_name'];
                                        echo "<option value='" . $row['id'] . "' data-id-number='" . htmlspecialchars($row['id_number']) . "' data-role='" . htmlspecialchars($row['role_category']) . "'>" . 
                                             htmlspecialchars($fullName) . " (" . htmlspecialchars($row['id_number']) . ")</option>";
                                    }
                                }
                                ?>
                            </select>
                        </div>
                        <div class="input-group">
                            <label for="exchangeItemBought">Item Bought:</label>
                            <select id="exchangeItemBought" name="itemBought" required onchange="loadAvailableSizes()">
                                <option value="">Select Item (Purchased within 24 hours)</option>
                            </select>
                            <small id="customerFilterNote" style="color: #666; font-size: 12px; margin-top: 4px;">
                                Loading customer purchases...
                            </small>
                        </div>
                        <div class="input-group">
                            <label for="exchangeNewSize">Exchange With Size:</label>
                            <select id="exchangeNewSize" name="newSize" required>
                                <option value="">Select New Size</option>
                            </select>
                        </div>
                        <div class="input-group">
                            <label for="exchangeRemarks">Remarks (Optional):</label>
                            <textarea id="exchangeRemarks" name="remarks" rows="3" placeholder="Reason for exchange, etc."></textarea>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="submit" form="exchangeItemForm" class="save-btn">Process Exchange</button>
                <button onclick="closeModal('exchangeItemModal')" class="cancel-btn">Cancel</button>
            </div>
        </div>
    </div>

    <script>
        function showAddQuantityModal() {
            document.getElementById('addQuantityModal').style.display = 'block';
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';

            if (modalId === 'addItemModal') {
                const form = document.getElementById('addItemForm');
                if (form) form.reset();

                const sizeCheckboxes = document.querySelectorAll('.size-checkboxes input[type="checkbox"]');
                sizeCheckboxes.forEach(checkbox => {
                    checkbox.checked = false;
                });
                
                const sizeDetailsContainer = document.getElementById("sizeDetailsContainer");
                const sizeDetailsList = document.getElementById("sizeDetailsList");
                if (sizeDetailsContainer) sizeDetailsContainer.classList.remove("show");
                if (sizeDetailsList) sizeDetailsList.innerHTML = "";
            }

            if (modalId === 'addItemSizeModal') {
                const form = document.getElementById('addItemSizeForm');
                if (form) form.reset();

                // Destroy all Select2 instances in this modal
                $('#addItemSizeModal select').each(function() {
                    if ($(this).hasClass('select2-hidden-accessible')) {
                        $(this).select2('destroy');
                    }
                });

                // Reset to single item entry
                const itemsContainer = document.getElementById('itemsContainer');
                const itemEntries = itemsContainer.querySelectorAll('.item-entry');
                
                // Remove all entries except the first one
                for (let i = 1; i < itemEntries.length; i++) {
                    itemEntries[i].remove();
                }

                // Reset the first entry if it exists
                if (itemEntries.length > 0) {
                    const firstEntry = itemEntries[0];
                    
                    // Reset checkboxes
                    const sizeCheckboxes = firstEntry.querySelectorAll('input[type="checkbox"]');
                    sizeCheckboxes.forEach(checkbox => {
                        checkbox.checked = false;
                        checkbox.disabled = false;
                        checkbox.parentElement.style.opacity = '1';
                        checkbox.parentElement.title = '';
                    });

                    // Hide sections
                    const sizeSelectionSection = firstEntry.querySelector('[id^="sizeSelectionSection_"]');
                    const sizeDetailsContainer = firstEntry.querySelector('[id^="sizeDetailsContainer_"]');
                    const sizeDetailsList = firstEntry.querySelector('[id^="sizeDetailsList_"]');
                    
                    if (sizeSelectionSection) sizeSelectionSection.style.display = 'none';
                    if (sizeDetailsContainer) sizeDetailsContainer.style.display = 'none';
                    if (sizeDetailsList) sizeDetailsList.innerHTML = '';

                    // Reset select
                    const existingItemSelect = firstEntry.querySelector('[id^="existingItem_"]');
                    if (existingItemSelect) existingItemSelect.selectedIndex = 0;
                }

                // Hide remove button
                const removeBtn = document.querySelector('.remove-item-btn');
                if (removeBtn) removeBtn.style.display = 'none';

                // Reset global variables if they exist
                if (typeof inventoryUsedSizes !== 'undefined') {
                    window.inventoryUsedSizes = {};
                }
                if (typeof itemCounter !== 'undefined') {
                    window.itemCounter = 1;
                }
            }

            if (modalId === 'exchangeItemModal') {
                const form = document.getElementById('exchangeItemForm');
                if (form) form.reset();

                document.getElementById('exchangeItemBought').innerHTML = '<option value="">Select Item (Purchased within 24 hours)</option>';
                document.getElementById('exchangeNewSize').innerHTML = '<option value="">Select New Size</option>';
            }
        }

    </script>

    <style>
    .order-section {
        margin-bottom: 20px;
        padding-bottom: 20px;
        border-bottom: 2px solid #eee;
    }

    .delivery-item {
        position: relative;
        background: #f9f9f9;
        border: 1px solid #ddd;
        border-radius: 5px;
        padding: 20px;
        margin-bottom: 15px;
    }

    .item-close {
        position: absolute;
        top: 10px;
        right: 15px;
        font-size: 20px;
        cursor: pointer;
        color: #dc3545;
        font-weight: bold;
        display: none;
    }

    .delivery-item:not(:first-child) .item-close {
        display: block;
    }

    .item-content {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
        align-items: flex-start;
    }

    .input-group {
        margin-bottom: 20px;
        display: flex;
        flex-direction: column;
    }

    .input-group label {
        display: block;
        margin-bottom: 10px;
        font-weight: 600;
        color: #495057;
        font-size: 14px;
        letter-spacing: 0.3px;
    }

    .input-group input,
    .input-group select {
        width: 100%;
        padding: 12px 16px;
        border: 1px solid #ced4da;
        border-radius: 6px;
        height: 44px;
        font-size: 14px;
        background-color: #fff;
        transition: all 0.2s ease-in-out;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    }

    .input-group input:focus,
    .input-group select:focus {
        outline: none;
        border-color: #007bff;
        box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.1);
    }

    .input-group select {
        background-color: white;
        cursor: pointer;
    }

    .add-item-btn {
        background: #28a745;
        color: white;
        border: none;
        padding: 10px 20px;
        border-radius: 4px;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 5px;
        margin: 15px 0;
    }

    .add-item-btn:hover {
        background: #218838;
    }

    .modal-footer {
        border-top: 1px solid #ddd;
        padding-top: 15px;
        display: flex;
        justify-content: flex-end;
        gap: 10px;
    }

    .save-btn, .cancel-btn {
        padding: 8px 20px;
        border-radius: 4px;
        cursor: pointer;
        border: none;
    }

    .save-btn {
        background: #007bff;
        color: white;
    }

    .cancel-btn {
        background: #dc3545;
        color: white;
    }

    .save-btn:hover {
        background: #0056b3;
    }

    .cancel-btn:hover {
        background: #c82333;
    }

    .select2-container--default .select2-selection--single {
        height: 38px;
        padding: 4px 12px;
        border-radius: 4px;
        border: 1px solid #ddd;
        font-size: 14px;
    }

    .pagination {
    margin: 40px 0 20px 0;
    text-align: center;
    display: flex;
    justify-content: center;
    gap: 6px;
}

.pagination a {
    display: inline-block;
    min-width: 38px;
    padding: 10px 16px;
    margin: 0 2px;
    border: 1.5px solid #007bff;
    color: #007bff;
    background: #fff;
    text-decoration: none;
    border-radius: 24px;
    font-size: 1.1em;
    font-weight: 500;
    transition: all 0.2s;
    box-shadow: 0 1px 3px rgba(0,0,0,0.04);
}

.pagination a.active, .pagination a:focus {
    background: #007bff;
    color: #fff;
    border-color: #0056b3;
    box-shadow: 0 2px 8px rgba(0,123,255,0.08);
}

.pagination a:hover:not(.active):not([disabled]) {
    background: #e6f0ff;
    color: #0056b3;
    border-color: #0056b3;
}

.pagination a[disabled], .pagination a.disabled {
    color: #aaa;
    border-color: #eee;
    background: #f8f9fa;
    cursor: not-allowed;
    pointer-events: none;
}

.item-size-entry {
    position: relative;
    background: #f9f9f9;
    border: 1px solid #ddd;
    border-radius: 5px;
    padding: 20px;
    margin-bottom: 15px;
}
.item-size-entry .item-close {
    position: absolute;
    top: 8px;
    right: 12px;
    font-size: 20px;
    cursor: pointer;
    color: #dc3545;
    font-weight: bold;
    display: none;
    z-index: 2;
}
    .item-size-entry:not(:first-child) .item-close {
        display: block;
    }

    /* Add New Item Size Modal Styling */
    .item-entry {
        background: #fff;
        border: 2px solid #e9ecef;
        border-radius: 12px;
        padding: 25px;
        margin-bottom: 25px;
        position: relative;
    }

    .item-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        padding-bottom: 15px;
        border-bottom: 2px solid #e9ecef;
    }

    .item-header h3 {
        margin: 0;
        color: #495057;
        font-size: 18px;
        font-weight: 600;
    }

    .remove-item-btn {
        position: absolute;
        top: 15px;
        right: 20px;
        font-size: 24px;
        cursor: pointer;
        color: #dc3545;
        font-weight: bold;
        background: #fff;
        border: 2px solid #dc3545;
        border-radius: 50%;
        width: 35px;
        height: 35px;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s;
    }

    .remove-item-btn:hover {
        background: #dc3545;
        color: #fff;
    }

    .order-section {
        background: #f8f9fa;
        padding: 25px;
        border-radius: 10px;
        margin-bottom: 25px;
        border: 1px solid #e9ecef;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
    }

    .order-section h4 {
        margin: 0 0 20px 0;
        color: #495057;
        font-size: 16px;
        font-weight: 600;
        padding-bottom: 10px;
        border-bottom: 2px solid #e9ecef;
    }

    .add-item-section {
        background: #f0f8ff;
        padding: 25px;
        border-radius: 10px;
        margin: 25px 0;
        border: 1px solid #b3d9ff;
        text-align: center;
        box-shadow: 0 2px 4px rgba(0, 123, 255, 0.1);
    }

    .add-item-btn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 12px 24px;
        background-color: #28a745;
        color: white;
        border: none;
        border-radius: 8px;
        cursor: pointer;
        font-size: 16px;
        font-weight: 500;
        transition: all 0.3s ease;
        box-shadow: 0 2px 4px rgba(40, 167, 69, 0.2);
    }

    .add-item-btn:hover {
        background-color: #218838;
        transform: translateY(-1px);
        box-shadow: 0 4px 8px rgba(40, 167, 69, 0.3);
    }

    .add-item-section small {
        display: block;
        margin-top: 10px;
        color: #6c757d;
        font-style: italic;
    }

    .input-group textarea {
        width: 100%;
        padding: 8px 12px;
        border: 1px solid #ddd;
        border-radius: 4px;
        font-size: 14px;
        resize: vertical;
        min-height: 60px;
    }

    .select2-container--default .select2-selection--single {
        height: 38px;
        padding: 4px 12px;
        border-radius: 4px;
        border: 1px solid #ddd;
        font-size: 14px;
    }

    .select2-container--default .select2-selection--single .select2-selection__rendered {
        line-height: 28px;
        padding-left: 0;
    }

    .select2-container--default .select2-selection--single .select2-selection__arrow {
        height: 36px;
    }

    .select2-dropdown {
        border: 1px solid #ddd;
        border-radius: 4px;
    }

    .select2-results__option {
        padding: 8px 12px;
    }

    .select2-results__option--highlighted[aria-selected] {
        background-color: #007bff;
    }

    /* New styles for multi-size product addition */
    .size-detail-header h4 {
        margin: 0;
        color: #0066cc;
        font-size: 14px;
        font-weight: 600;
    }

    .generated-code {
        font-family: 'Courier New', monospace;
        background: #fff;
        padding: 6px 12px;
        border-radius: 4px;
        border: 1px solid #ddd;
        font-size: 12px;
        color: #666;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
    }

    .delivery-order-section {
        background: #f0f8ff;
        padding: 25px;
        border-radius: 10px;
        margin-bottom: 25px;
        border: 1px solid #b3d9ff;
        box-shadow: 0 2px 4px rgba(0, 123, 255, 0.1);
    }

    .delivery-order-section h3 {
        margin: 0 0 20px 0;
        color: #0066cc;
        font-size: 16px;
        font-weight: 600;
        padding-bottom: 10px;
        border-bottom: 2px solid #b3d9ff;
    }

    .delivery-order-section .input-group {
        margin-bottom: 0;
    }

    .product-item {
        background: #fff;
        border: 2px solid #e9ecef;
        border-radius: 12px;
        padding: 25px;
        margin-bottom: 25px;
        position: relative;
    }

    .product-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        padding-bottom: 15px;
        border-bottom: 2px solid #e9ecef;
    }

    .product-header h3 {
        margin: 0;
        color: #495057;
        font-size: 18px;
        font-weight: 600;
    }

    .remove-product-btn {
        position: absolute;
        top: 15px;
        right: 20px;
        font-size: 24px;
        cursor: pointer;
        color: #dc3545;
        font-weight: bold;
        background: #fff;
        border: 2px solid #dc3545;
        border-radius: 50%;
        width: 35px;
        height: 35px;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s;
    }

    .remove-product-btn:hover {
        background: #dc3545;
        color: #fff;
    }

    .product-basic-info {
        background: #f8f9fa;
        padding: 25px;
        border-radius: 10px;
        margin-bottom: 25px;
        border: 1px solid #e9ecef;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
    }

    .product-basic-info h4 {
        margin: 0 0 20px 0;
        color: #495057;
        font-size: 16px;
        font-weight: 600;
        padding-bottom: 10px;
        border-bottom: 2px solid #e9ecef;
    }

    .product-basic-info .input-group {
        margin-bottom: 22px;
    }

    .product-basic-info .input-group:last-child {
        margin-bottom: 0;
    }

    .size-selection-section {
        background: #fff3cd;
        padding: 25px;
        border-radius: 10px;
        margin-bottom: 25px;
        border: 1px solid #ffeaa7;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
    }

    .size-selection-section h4 {
        margin: 0 0 20px 0;
        color: #856404;
        font-size: 16px;
        font-weight: 600;
        padding-bottom: 10px;
        border-bottom: 2px solid #ffeaa7;
    }

    .size-selection-section .input-group {
        margin-bottom: 18px;
    }

    .size-selection-section .input-group label {
        margin-bottom: 12px;
        font-weight: 600;
        color: #856404;
    }

    .size-checkboxes {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
        gap: 12px;
        margin-top: 15px;
    }

    .checkbox-label {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 12px 16px;
        background: white;
        border: 2px solid #e9ecef;
        border-radius: 6px;
        cursor: pointer;
        transition: all 0.3s ease;
        font-weight: 500;
        font-size: 14px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        min-height: 44px;
        line-height: 1.2;
    }

    .checkbox-label:hover {
        background: #f8f9fa;
        border-color: #007bff;
        transform: translateY(-1px);
        box-shadow: 0 2px 6px rgba(0, 123, 255, 0.15);
    }

    .checkbox-label input[type="checkbox"]:checked {
        accent-color: #007bff;
    }

    .checkbox-label:has(input[type="checkbox"]:checked) {
        color: #007bff;
        font-weight: 600;
        border-color: #007bff;
    }

    .checkbox-label input[type="checkbox"] {
        width: 18px;
        height: 18px;
        margin: 0;
        accent-color: #007bff;
        flex-shrink: 0;
        position: relative;
        top: 0;
    }

    .size-details-container {
        display: none;
    }

    .size-details-container.show {
        display: block !important;
        height: auto !important;
        min-height: 50px !important;
    }

    /* Ensure size details container displays when forced by JavaScript */
    .size-details-container[style*="display: block"] {
        display: block !important;
        height: auto !important;
        min-height: 50px !important;
    }

    /* Ensure size details list has proper styling */
    div[id^="sizeDetailsList_"] {
        display: block;
        height: auto;
        min-height: 30px;
    }

    /* Force display for size details list when it has content */
    div[id^="sizeDetailsList_"]:not(:empty) {
        display: block !important;
        height: auto !important;
        min-height: 30px !important;
    }

    .size-details-container h4 {
        margin: 0 0 20px 0;
        color: #495057;
        font-size: 16px;
        font-weight: 600;
        padding-bottom: 10px;
        border-bottom: 2px solid #e9ecef;
    }

    .size-detail-item {
        background: #e7f3ff;
        border: 1px solid #b3d9ff;
        border-radius: 10px;
        padding: 25px;
        margin-bottom: 20px;
        position: relative;
        box-shadow: 0 2px 4px rgba(0, 123, 255, 0.1);
    }

    .size-detail-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        padding-bottom: 12px;
        border-bottom: 1px solid #b3d9ff;
    }

    .size-detail-form {
        display: grid;
        grid-template-columns: 1fr 1fr 1fr;
        gap: 20px;
    }

    .size-detail-form .input-group {
        margin-bottom: 0;
    }

    .add-product-section {
        text-align: center;
        padding: 25px;
        border: 2px dashed #28a745;
        border-radius: 10px;
        margin-top: 25px;
        background: #f8fff9;
        transition: all 0.3s ease;
    }

    .add-product-section:hover {
        background: #f0fff4;
        border-color: #20c997;
    }

    .add-product-btn {
        background: #28a745;
        color: white;
        border: none;
        padding: 14px 28px;
        border-radius: 8px;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 10px;
        font-size: 16px;
        font-weight: 600;
        transition: all 0.3s ease;
        box-shadow: 0 2px 4px rgba(40, 167, 69, 0.2);
    }

    .add-product-btn:hover {
        background: #218838;
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(40, 167, 69, 0.3);
    }

    .add-product-section small {
        display: block;
        margin-top: 8px;
        color: #6c757d;
        font-size: 13px;
    }

    .input-group small {
        color: #6c757d;
        font-size: 12px;
        margin-top: 6px;
        line-height: 1.4;
    }

    .modal-content {
        max-height: 90vh;
        overflow-y: auto;
        padding: 0;
    }

    .modal-header {
        padding: 20px 25px;
        border-bottom: 2px solid #e9ecef;
        background: #f8f9fa;
    }

    .modal-header h2 {
        margin: 0;
        color: #495057;
        font-size: 20px;
        font-weight: 600;
    }

    .modal-body {
        padding: 25px;
    }

    .modal-footer {
        border-top: 2px solid #e9ecef;
        padding: 20px 25px;
        display: flex;
        justify-content: flex-end;
        gap: 12px;
        background: #f8f9fa;
    }
    .select2-result-student {
        padding: 8px 0;
        border-bottom: 1px solid #f0f0f0;
    }
    
    .select2-result-student:last-child {
        border-bottom: none;
    }

    /* Item Size Select2 Custom Styling */
    .select2-result-item {
        padding: 8px 12px;
    }

    .select2-result-item .item-name {
        font-weight: 500;
        color: #333;
        font-size: 14px;
    }

    /* Ensure Select2 dropdown appears above modal */
    .select2-container--open {
        z-index: 9999 !important;
    }

    .select2-dropdown {
        z-index: 9999 !important;
    }

    /* Custom styling for Add New Item Size modal Select2 */
    #addItemSizeModal .select2-container {
        border: 1px solid #ddd;
        border-radius: 6px;
    }

    #addItemSizeModal .select2-container--default .select2-selection--single {
        border: none;
        height: 40px;
        padding: 6px 12px;
    }

    #addItemSizeModal .select2-container--default .select2-selection--single .select2-selection__rendered {
        line-height: 28px;
        padding-left: 0;
        color: #333;
    }

    #addItemSizeModal .select2-container--default .select2-selection--single .select2-selection__arrow {
        height: 38px;
        right: 8px;
    }
    
    .select2-result-student .student-name {
        font-weight: 600;
        color: #333;
        font-size: 14px;
        margin-bottom: 2px;
    }
    
    .select2-result-student .student-id {
        font-size: 12px;
        color: #666;
        font-style: italic;
    }
    
    .select2-container--default .select2-results__option[aria-selected=true] .select2-result-student .student-name {
        color: #fff;
    }
    
    .select2-container--default .select2-results__option[aria-selected=true] .select2-result-student .student-id {
        color: #e0e0e0;
    }
    
    .select2-container--default .select2-results__option--highlighted .select2-result-student .student-name {
        color: #fff;
    }
    
    .select2-container--default .select2-results__option--highlighted .select2-result-student .student-id {
        color: #e0e0e0;
    }
    
    /* Improve Select2 search input */
    .select2-search--dropdown .select2-search__field {
        padding: 8px 12px;
        border: 1px solid #ddd;
        border-radius: 4px;
        font-size: 14px;
    }
    
    .select2-search--dropdown .select2-search__field:focus {
        border-color: #007bff;
        outline: none;
        box-shadow: 0 0 0 2px rgba(0, 123, 255, 0.25);
    }
    
    /* Loading state for Select2 */
    .select2-results__option.loading-results {
        text-align: center;
        color: #666;
        font-style: italic;
        padding: 12px;
    }

    /* New styles for Add Item Size modal */
    .size-selection-section {
        background: #f8f9fa;
        padding: 25px;
        border-radius: 10px;
        margin: 25px 0;
        border: 1px solid #e9ecef;
    }

    .size-selection-section h4 {
        margin: 0 0 20px 0;
        color: #495057;
        font-size: 16px;
        font-weight: 600;
        padding-bottom: 10px;
        border-bottom: 2px solid #e9ecef;
    }

    .size-checkboxes {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
        gap: 15px;
        margin-top: 15px;
    }

    .size-checkboxes .checkbox-label {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 12px 16px;
        border: 2px solid #e9ecef;
        border-radius: 8px;
        cursor: pointer;
        background: #fff;
        transition: all 0.2s ease;
        font-weight: 500;
        user-select: none;
    }

    .size-checkboxes .checkbox-label:hover:not([style*="opacity: 0.5"]) {
        border-color: #007bff;
        background: #f8f9ff;
    }

    .size-checkboxes .checkbox-label input[type="checkbox"] {
        margin: 0;
        cursor: pointer;
    }

    .size-checkboxes .checkbox-label input[type="checkbox"]:checked + span,
    .size-checkboxes .checkbox-label:has(input[type="checkbox"]:checked) {
        color: #007bff;
        font-weight: 600;
    }

    .size-checkboxes .checkbox-label:has(input[type="checkbox"]:checked) {
        border-color: #007bff;
        background: #e7f3ff;
    }

    .size-detail-entry {
        background: #e7f3ff;
        border: 1px solid #b3d9ff;
        border-radius: 10px;
        padding: 25px;
        margin-bottom: 20px;
        box-shadow: 0 2px 4px rgba(0, 123, 255, 0.1);
        position: relative;
    }

    .size-detail-header h4 {
        margin: 0 0 15px 0;
        color: #0066cc;
        font-size: 16px;
        font-weight: 600;
        padding-bottom: 10px;
        border-bottom: 1px solid #b3d9ff;
    }

    .size-detail-content {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
        align-items: start;
    }

    .size-detail-content .input-group {
        margin-bottom: 15px;
    }

    .generated-code {
        font-family: 'Courier New', monospace;
        background: #fff;
        padding: 10px 15px;
        border-radius: 6px;
        border: 1px solid #ddd;
        font-size: 14px;
        color: #666;
        font-weight: 600;
        box-shadow: inset 0 1px 2px rgba(0, 0, 0, 0.1);
        margin-top: 5px;
    }

    #sizeDetailsContainer.show {
        animation: fadeInSlide 0.3s ease-out;
    }

    @keyframes fadeInSlide {
        from {
            opacity: 0;
            transform: translateY(-10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    /* Disabled checkbox styling */
    .size-checkboxes .checkbox-label[style*="opacity: 0.5"] {
        cursor: not-allowed;
    }

    .size-checkboxes .checkbox-label[style*="opacity: 0.5"] input[type="checkbox"] {
        cursor: not-allowed;
    }

    /* Multiple item entry styles */
    .item-entry {
        background: #fff;
        border: 2px solid #e9ecef;
        border-radius: 12px;
        padding: 25px;
        margin-bottom: 25px;
        position: relative;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        transition: all 0.3s ease;
    }

    .item-entry:hover {
        border-color: #007bff;
        box-shadow: 0 4px 12px rgba(0, 123, 255, 0.15);
    }

    .item-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 25px;
        padding-bottom: 15px;
        border-bottom: 2px solid #e9ecef;
    }

    .item-header h3 {
        margin: 0;
        color: #495057;
        font-size: 18px;
        font-weight: 600;
    }

    .remove-item-btn {
        color: #dc3545;
        font-size: 24px;
        font-weight: bold;
        cursor: pointer;
        width: 30px;
        height: 30px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        background: #fff2f2;
        border: 1px solid #f8d7da;
        transition: all 0.2s ease;
    }

    .remove-item-btn:hover {
        background: #dc3545;
        color: white;
        transform: scale(1.1);
    }

    .add-item-section {
        text-align: center;
        padding: 25px;
        border: 2px dashed #28a745;
        border-radius: 10px;
        margin-top: 25px;
        background: #f8fff9;
        transition: all 0.3s ease;
    }

    .add-item-section:hover {
        background: #f0fff4;
        border-color: #20c997;
    }

    .add-item-btn {
        background: #28a745;
        color: white;
        border: none;
        padding: 14px 28px;
        border-radius: 8px;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 10px;
        font-size: 16px;
        font-weight: 600;
        transition: all 0.3s ease;
        box-shadow: 0 2px 4px rgba(40, 167, 69, 0.2);
    }

    .add-item-btn:hover {
        background: #218838;
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(40, 167, 69, 0.3);
    }

    .add-item-section small {
        display: block;
        margin-top: 8px;
        color: #6c757d;
        font-size: 13px;
    }
    </style>
</body>

</html>