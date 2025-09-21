<?php
session_start();
include 'includes/config_functions.php';

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
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
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
            
            // Initialize Select2 for exchange customer name
            if (window.jQuery && $("#exchangeCustomerName").length) {
                $("#exchangeCustomerName").select2({
                    placeholder: "Search customer...",
                    allowClear: true,
                    width: "100%"
                });
                
                // Add event listener for customer selection
                $("#exchangeCustomerName").on('change', function() {
                    loadCustomerPurchases();
                });
            }
        });

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
                <select name="category" id="categoryFilter" onchange="document.getElementById('filterForm').submit()">
                    <option value="">All Categories</option>
                    <?php
                    $conn_categories = mysqli_connect("localhost", "root", "", "proware");
                    if ($conn_categories) {
                        $category_sql = "SELECT DISTINCT category FROM inventory WHERE category IS NOT NULL AND category != '' ORDER BY category ASC";
                        $category_result = mysqli_query($conn_categories, $category_sql);
                        
                        if ($category_result) {
                            while ($category_row = mysqli_fetch_assoc($category_result)) {
                                $category_value = htmlspecialchars($category_row['category']);
                                $selected = (($_GET['category'] ?? '') == $category_value) ? ' selected' : '';
                                echo "<option value=\"$category_value\"$selected>$category_value</option>";
                            }
                        }
                        mysqli_close($conn_categories);
                    }
                    ?>
                </select>
                <select name="size" id="sizeFilter" onchange="document.getElementById('filterForm').submit()">
                    <option value="">All Sizes</option>
                    <?php
                    $conn_sizes = mysqli_connect("localhost", "root", "", "proware");
                    if ($conn_sizes) {
                        $size_order = ['XS', 'S', 'M', 'L', 'XL', 'XXL', '3XL', '4XL', '5XL', '6XL', '7XL', 'One Size'];
                        $size_sql = "SELECT DISTINCT sizes FROM inventory WHERE sizes IS NOT NULL AND sizes != '' ORDER BY sizes";
                        $size_result = mysqli_query($conn_sizes, $size_sql);
                        
                        $existing_sizes = [];
                        if ($size_result) {
                            while ($size_row = mysqli_fetch_assoc($size_result)) {
                                $existing_sizes[] = $size_row['sizes'];
                            }
                        }

                        foreach ($size_order as $size) {
                            if (in_array($size, $existing_sizes)) {
                                $selected = (($_GET['size'] ?? '') == $size) ? ' selected' : '';
                                echo "<option value=\"" . htmlspecialchars($size) . "\"$selected>" . htmlspecialchars($size) . "</option>";
                            }
                        }
                        
                        mysqli_close($conn_sizes);
                    }
                    ?>
                </select>
                <select name="status" id="statusFilter" onchange="document.getElementById('filterForm').submit()">
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
                          $conn = mysqli_connect("localhost", "root", "", "proware");

                          if (!$conn) {
                              die("Connection failed: " . mysqli_connect_error());
                          }

                          $category = isset($_GET['category']) ? mysqli_real_escape_string($conn, $_GET['category']) : '';
                          $size = isset($_GET['size']) ? mysqli_real_escape_string($conn, $_GET['size']) : '';
                          $status = isset($_GET['status']) ? mysqli_real_escape_string($conn, $_GET['status']) : '';
                          $search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';

                          $where = [];
                          if ($category) $where[] = "category = '$category'";
                          if ($size) $where[] = "sizes = '$size'";
                          if ($status) {
                              if ($status == 'In Stock') $where[] = "actual_quantity > " . getLowStockThreshold($conn);
                              else if ($status == 'Low Stock') $where[] = "actual_quantity > 0 AND actual_quantity <= " . getLowStockThreshold($conn);
                              else if ($status == 'Out of Stock') $where[] = "actual_quantity <= 0";
                          }
                          if ($search) $where[] = "(item_name LIKE '%$search%' OR item_code LIKE '%$search%')";

                          $where_clause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

                          $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
                          $limit = 15;
                          $offset = ($page - 1) * $limit;

                          $total_sql = "SELECT COUNT(*) as total FROM inventory $where_clause";
                          $total_result = mysqli_query($conn, $total_sql);
                          $total_row = mysqli_fetch_assoc($total_result);
                          $total_items = $total_row['total'];
                          $total_pages = ceil($total_items / $limit);

                          $sql = "SELECT * FROM inventory $where_clause ORDER BY created_at DESC LIMIT $limit OFFSET $offset";
                          $result = mysqli_query($conn, $sql);

                          $lowStockThreshold = getLowStockThreshold($conn);

                          while ($row = mysqli_fetch_assoc($result)) {
                              $statusClass = '';
                              if ($row['actual_quantity'] <= 0) {
                                  $status = 'Out of Stock';
                                  $statusClass = 'status-out-of-stock';
                              } else if ($row['actual_quantity'] <= $lowStockThreshold) {
                                  $status = 'Low Stock';
                                  $statusClass = 'status-low-stock';
                              } else {
                                  $status = 'In Stock';
                                  $statusClass = 'status-in-stock';
                              }

                              echo "<tr data-item-code='" . $row['item_code'] . "' data-created-at='" . $row['created_at'] . "' data-category='" . strtolower($row['category']) . "' onclick='selectRow(this, \"" . $row['item_code'] . "\", " . $row['price'] . ")'>";
                              echo "<td>" . $row['item_code'] . "</td>";
                              echo "<td>" . $row['item_name'] . "</td>";
                              echo "<td>" . $row['category'] . "</td>";
                              echo "<td>" . (isset($row['actual_quantity']) ? $row['actual_quantity'] : '0') . "</td>";
                              echo "<td>" . $row['sizes'] . "</td>";
                              echo "<td>₱" . number_format($row['price'], 2) . "</td>";
                              echo "<td class='" . $statusClass . "'>" . $status . "</td>";
                              echo "<!-- Item Code: " . $row['item_code'] . " -->";
                              echo "</tr>";
                          }
                          mysqli_close($conn);
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
                <h2>Add New Product</h2>
                <span class="close" onclick="closeModal('addItemModal')">&times;</span>
            </div>
            <div class="modal-body">
                <form id="addItemForm" onsubmit="submitNewItem(event)" enctype="multipart/form-data">
                    <div class="order-section">
                        <div class="input-group">
                            <label for="deliveryOrderNumber">Delivery Order #:</label>
                            <input type="text" id="deliveryOrderNumber" name="deliveryOrderNumber" required>
                        </div>
                    </div>
                    
                    <div id="productEntries">
                        <div class="product-entry">
                            <span class="item-close" onclick="removeProductEntry(this)">&times;</span>
                            <div class="item-content">
                                <div class="input-group">
                                    <label for="newProductItemCode_0">Item Code:</label>
                                    <input type="text" id="newProductItemCode_0" name="products[0][itemCode]" required>
                                </div>
                                <div class="input-group">
                                    <label for="newCategory_0">Category:</label>
                                    <select id="newCategory_0" name="products[0][category_id]" required onchange="loadSubcategories(0)">
                                        <option value="">Select Category</option>
                                        <option value="__add__">+ Add new category…</option>
                                    </select>
                                </div>
                                <div class="input-group" id="subcategoryGroup_0" style="display:none;">
                                    <label for="subcategorySelect_0">Subcategory:</label>
                                    <select id="subcategorySelect_0" name="products[0][subcategory_ids][]" multiple style="width:100%;">
                                        <option value="__add__">+ Add new subcategory…</option>
                                    </select>
                                </div>
                                <div class="input-group">
                                    <label for="newItemName_0">Product Name:</label>
                                    <input type="text" id="newItemName_0" name="products[0][itemName]" required>
                                </div>
                                <div class="input-group">
                                    <label for="newProductSize_0">Size:</label>
                                    <select id="newProductSize_0" name="products[0][size]" required>
                                        <option value="">Select Size</option>
                                        <option value="XS">XS</option>
                                        <option value="S">S</option>
                                        <option value="M">M</option>
                                        <option value="L">L</option>
                                        <option value="XL">XL</option>
                                        <option value="XXL">XXL</option>
                                        <option value="3XL">3XL</option>
                                        <option value="4XL">4XL</option>
                                        <option value="5XL">5XL</option>
                                        <option value="6XL">6XL</option>
                                        <option value="7XL">7XL</option>
                                        <option value="One Size">One Size</option>
                                    </select>
                                </div>
                                <div class="input-group">
                                    <label for="newItemPrice_0">Price:</label>
                                    <input type="number" id="newItemPrice_0" name="products[0][price]" min="0" step="0.01" required>
                                </div>
                                <div class="input-group">
                                    <label for="newItemQuantity_0">Initial Stock:</label>
                                    <input type="number" id="newItemQuantity_0" name="products[0][quantity]" min="0" required>
                                </div>
                                <div class="input-group">
                                    <label for="newItemDamage_0">Damaged Items:</label>
                                    <input type="number" id="newItemDamage_0" name="products[0][damage]" min="0" value="0" required>
                                </div>
                                <div class="input-group">
                                    <label for="newImage_0">Product Image:</label>
                                    <input type="file" id="newImage_0" name="products[0][image]" accept="image/*" required>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <button type="button" class="add-item-btn" onclick="addProductEntry()">
                        <i class="material-icons">add</i> Add Another Product
                    </button>
                </form>
            </div>
            <div class="modal-footer">
                <button type="submit" form="addItemForm" class="save-btn">Add Products</button>
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
                                        $conn = mysqli_connect("localhost", "root", "", "proware");
                                        if (!$conn) {
                                            die("Connection failed: " . mysqli_connect_error());
                                        }

                                        $sql = "SELECT item_code, item_name, category FROM inventory ORDER BY item_name";
                                        $result = mysqli_query($conn, $sql);

                                        while ($row = mysqli_fetch_assoc($result)) {
                                            echo "<option value='" . $row['item_code'] . "'>" . $row['item_name'] . " (" . $row['item_code'] . ") - " . $row['category'] . "</option>";
                                        }
                                        mysqli_close($conn);
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
                <form id="deductQuantityForm" onsubmit="submitDeductQuantity(event)">
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
                                <!-- Options will be populated by JS -->
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
                                    $conn = mysqli_connect("localhost", "root", "", "proware");
                                    if (!$conn) {
                                        die("Connection failed: " . mysqli_connect_error());
                                    }
                                    // Get unique products based on item code prefix
                                    $sql = "SELECT DISTINCT 
                                            SUBSTRING_INDEX(item_code, '-', 1) as prefix,
                                            item_name,
                                            category
                                            FROM inventory 
                                            WHERE actual_quantity > 0
                                            ORDER BY item_name";
                                    $result = mysqli_query($conn, $sql);
                                    while ($row = mysqli_fetch_assoc($result)) {
                                        echo "<option value='" . $row['prefix'] . "' data-category='" . htmlspecialchars($row['category'], ENT_QUOTES) . "'>" . 
                                             $row['item_name'] . " (" . $row['prefix'] . ")</option>";
                                    }
                                    mysqli_close($conn);
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
                    <div class="order-section">
                        <div class="input-group">
                            <label for="deliveryOrderNumber">Delivery Order #:</label>
                            <input type="text" id="deliveryOrderNumber" name="deliveryOrderNumber" required>
                        </div>
                        <div class="input-group">
                            <label for="existingItem">Select Item:</label>
                            <select id="existingItem" name="existingItem" required onchange="updateItemCodePrefix()">
                                <option value="">Select Item</option>
                                <?php
                                $conn = mysqli_connect("localhost", "root", "", "proware");
                                if (!$conn) {
                                    die("Connection failed: " . mysqli_connect_error());
                                }

                                // Get unique items based on their prefix (before the dash)
                                $sql = "SELECT DISTINCT 
                                        SUBSTRING_INDEX(item_code, '-', 1) as prefix,
                                        item_name,
                                        category
                                        FROM inventory 
                                        ORDER BY item_name";
                                $result = mysqli_query($conn, $sql);

                                while ($row = mysqli_fetch_assoc($result)) {
                                    echo "<option value='" . $row['prefix'] . "' data-name='" . $row['item_name'] . "' data-category='" . $row['category'] . "'>" . 
                                         $row['item_name'] . " (" . $row['prefix'] . ")</option>";
                                }
                                mysqli_close($conn);
                                ?>
                            </select>
                        </div>
                    </div>

                    <div id="itemSizeEntries">
                        <div class="item-size-entry">
                            <div class="item-close">&times;</div>
                            <div class="item-content">
                                <div class="input-group">
                                    <label for="newSize">Size:</label>
                                    <select name="newSize[]" required>
                                        <option value="">Select Size</option>
                                        <option value="XS">XS</option>
                                        <option value="S">S</option>
                                        <option value="M">M</option>
                                        <option value="L">L</option>
                                        <option value="XL">XL</option>
                                        <option value="XXL">XXL</option>
                                        <option value="3XL">3XL</option>
                                        <option value="4XL">4XL</option>
                                        <option value="5XL">5XL</option>
                                        <option value="6XL">6XL</option>
                                        <option value="7XL">7XL</option>
                                        <option value="One Size">One Size</option>
                                    </select>
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
                        </div>
                    </div>

                    <button type="button" class="add-item-btn" onclick="addItemSizeEntry()">
                        <i class="material-icons">add_circle</i> Add Another Size
                    </button>
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
                <!-- Receipt content will be injected here -->
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
                        <div class="product-entry">
                            <div class="item-content">
                                <div class="input-group">
                                    <label for="exchangeCustomerName">Customer Name:</label>
                                    <select id="exchangeCustomerName" name="customerName" required>
                                        <option value="">Search customer...</option>
                                        <?php
                                        $conn = mysqli_connect("localhost", "root", "", "proware");
                                        if (!$conn) {
                                            die("Connection failed: " . mysqli_connect_error());
                                        }

                                        // Get all customers from accounts table
                                        $sql = "SELECT id, first_name, last_name, id_number, role_category FROM account WHERE status = 'active' ORDER BY first_name, last_name";
                                        $result = mysqli_query($conn, $sql);

                                        while ($row = mysqli_fetch_assoc($result)) {
                                            $fullName = $row['first_name'] . ' ' . $row['last_name'];
                                            echo "<option value='" . $row['id'] . "' data-id-number='" . $row['id_number'] . "' data-role='" . $row['role_category'] . "'>" . 
                                                 htmlspecialchars($fullName) . " (" . $row['id_number'] . ")</option>";
                                        }
                                        mysqli_close($conn);
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
            // Reset the form if it's the Add Item Size modal
            if (modalId === 'addItemSizeModal') {
                const form = document.getElementById('addItemSizeForm');
                if (form) form.reset();
                // Reset Select2 for Select Item
                if (window.jQuery && $('#existingItem').length) {
                    $('#existingItem').val(null).trigger('change');
                }
            }
            // Reset the form if it's the Exchange Item modal
            if (modalId === 'exchangeItemModal') {
                const form = document.getElementById('exchangeItemForm');
                if (form) form.reset();
                // Reset dropdowns
                document.getElementById('exchangeItemBought').innerHTML = '<option value="">Select Item (Purchased within 24 hours)</option>';
                document.getElementById('exchangeNewSize').innerHTML = '<option value="">Select New Size</option>';
            }
            // Reset the Add Item modal when closed
            if (modalId === 'addItemModal') {
                resetAddItemModal();
            }
        }

        function addProductEntry() {
            const productEntries = document.getElementById('productEntries');
            const currentCount = productEntries.children.length;
            const newIndex = currentCount;

            const newEntry = document.createElement('div');
            newEntry.className = 'product-entry';
            newEntry.innerHTML = `
                <span class="item-close" onclick="removeProductEntry(this)">&times;</span>
                <div class="item-content">
                    <div class="input-group">
                        <label for="newProductItemCode_${newIndex}">Item Code:</label>
                        <input type="text" id="newProductItemCode_${newIndex}" name="products[${newIndex}][itemCode]" required>
                    </div>
                    <div class="input-group">
                        <label for="newCategory_${newIndex}">Category:</label>
                        <select id="newCategory_${newIndex}" name="products[${newIndex}][category_id]" required onchange="loadSubcategories(${newIndex})">
                            <option value="">Select Category</option>
                            <option value="__add__">+ Add new category…</option>
                        </select>
                    </div>
                    <div class="input-group" id="subcategoryGroup_${newIndex}" style="display:none;">
                        <label for="subcategorySelect_${newIndex}">Subcategory:</label>
                        <select id="subcategorySelect_${newIndex}" name="products[${newIndex}][subcategory_ids][]" multiple style="width:100%;">
                            <option value="__add__">+ Add new subcategory…</option>
                        </select>
                    </div>
                    <div class="input-group">
                        <label for="newItemName_${newIndex}">Product Name:</label>
                        <input type="text" id="newItemName_${newIndex}" name="products[${newIndex}][itemName]" required>
                    </div>
                    <div class="input-group">
                        <label for="newProductSize_${newIndex}">Size:</label>
                        <select id="newProductSize_${newIndex}" name="products[${newIndex}][size]" required>
                            <option value="">Select Size</option>
                            <option value="XS">XS</option>
                            <option value="S">S</option>
                            <option value="M">M</option>
                            <option value="L">L</option>
                            <option value="XL">XL</option>
                            <option value="XXL">XXL</option>
                            <option value="3XL">3XL</option>
                            <option value="4XL">4XL</option>
                            <option value="5XL">5XL</option>
                            <option value="6XL">6XL</option>
                            <option value="7XL">7XL</option>
                            <option value="One Size">One Size</option>
                        </select>
                    </div>
                    <div class="input-group">
                        <label for="newItemPrice_${newIndex}">Price:</label>
                        <input type="number" id="newItemPrice_${newIndex}" name="products[${newIndex}][price]" min="0" step="0.01" required>
                    </div>
                    <div class="input-group">
                        <label for="newItemQuantity_${newIndex}">Initial Stock:</label>
                        <input type="number" id="newItemQuantity_${newIndex}" name="products[${newIndex}][quantity]" min="0" required>
                    </div>
                    <div class="input-group">
                        <label for="newItemDamage_${newIndex}">Damaged Items:</label>
                        <input type="number" id="newItemDamage_${newIndex}" name="products[${newIndex}][damage]" min="0" value="0" required>
                    </div>
                    <div class="input-group">
                        <label for="newImage_${newIndex}">Product Image:</label>
                        <input type="file" id="newImage_${newIndex}" name="products[${newIndex}][image]" accept="image/*" required>
                    </div>
                </div>
            `;

            productEntries.appendChild(newEntry);
            
            // Load categories for the new entry
            if (typeof window.loadCategories === 'function') {
                window.loadCategories(newIndex);
            }
            
            // Set up event listeners for the new entry (these functions are defined in addItem.js)
            if (typeof setupCategoryEventListeners === 'function') {
                setupCategoryEventListeners(newIndex);
            }
            
            if (typeof setupSubcategoryEventListeners === 'function') {
                setupSubcategoryEventListeners(newIndex);
            }
            
            // Initialize Select2 for the new subcategory select if needed
            if (window.jQuery) {
                $(`#subcategorySelect_${newIndex}`).select2({
                    placeholder: "Select subcategories",
                    allowClear: true,
                    width: "100%"
                });
            }
        }

        function removeProductEntry(element) {
            const productEntry = element.closest('.product-entry');
            const productEntries = document.getElementById('productEntries');
            
            // Don't remove if it's the only entry
            if (productEntries.children.length > 1) {
                productEntry.remove();
                
                // Reindex remaining entries
                reindexProductEntries();
            }
        }

        function reindexProductEntries() {
            const productEntries = document.getElementById('productEntries');
            const entries = productEntries.children;
            
            for (let i = 0; i < entries.length; i++) {
                const entry = entries[i];
                const inputs = entry.querySelectorAll('input, select');
                
                inputs.forEach(input => {
                    if (input.name) {
                        input.name = input.name.replace(/\[\d+\]/, `[${i}]`);
                    }
                    if (input.id) {
                        input.id = input.id.replace(/_\d+$/, `_${i}`);
                    }
                });
                
                const labels = entry.querySelectorAll('label');
                labels.forEach(label => {
                    if (label.getAttribute('for')) {
                        label.setAttribute('for', label.getAttribute('for').replace(/_\d+$/, `_${i}`));
                    }
                });
                
                const onchangeSelect = entry.querySelector('select[onchange]');
                if (onchangeSelect) {
                    onchangeSelect.setAttribute('onchange', `loadSubcategories(${i})`);
                }
                
                const subcategoryGroup = entry.querySelector('[id^="subcategoryGroup_"]');
                if (subcategoryGroup) {
                    subcategoryGroup.id = `subcategoryGroup_${i}`;
                }
            }
        }

        function resetAddItemModal() {
            const productEntries = document.getElementById('productEntries');
            
            // Remove all entries except the first one
            while (productEntries.children.length > 1) {
                productEntries.removeChild(productEntries.lastChild);
            }
            
            // Reset the form
            const form = document.getElementById('addItemForm');
            if (form) {
                form.reset();
            }
            
            // Reset Select2 elements
            if (window.jQuery) {
                productEntries.querySelectorAll('select[multiple]').forEach(select => {
                    if ($(select).hasClass('select2-hidden-accessible')) {
                        $(select).val(null).trigger('change');
                    }
                });
            }
            
            // Hide subcategory groups
            productEntries.querySelectorAll('[id^="subcategoryGroup_"]').forEach(group => {
                group.style.display = 'none';
            });
            
            // Force reload categories for the first entry (clear existing and reload)
            const firstCategorySelect = document.getElementById('newCategory_0');
            if (firstCategorySelect) {
                firstCategorySelect.setAttribute('data-force-reload', 'true');
                if (typeof window.loadCategories === 'function') {
                    window.loadCategories(0);
                }
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
        margin-bottom: 0;
        display: flex;
        flex-direction: column;
    }

    .input-group label {
        display: block;
        margin-bottom: 8px;
        font-weight: bold;
        color: #333;
    }

    .input-group input,
    .input-group select,
    .input-group textarea {
        width: 100%;
        padding: 8px 12px;
        border: 1px solid #ddd;
        border-radius: 4px;
        font-size: 14px;
        font-family: inherit;
    }

    .input-group input,
    .input-group select {
        height: 38px;
    }

    .input-group textarea {
        min-height: 80px;
        resize: vertical;
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

    .input-group textarea {
        width: 100%;
        padding: 8px 12px;
        border: 1px solid #ddd;
        border-radius: 4px;
        font-size: 14px;
        resize: vertical;
        min-height: 60px;
    }

    /* Select2 styling for exchange modal */
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

    .product-entry {
        position: relative;
        background: #f9f9f9;
        border: 1px solid #ddd;
        border-radius: 5px;
        padding: 20px;
        margin-bottom: 15px;
    }

    .product-entry .item-close {
        position: absolute;
        top: 10px;
        right: 15px;
        font-size: 20px;
        cursor: pointer;
        color: #dc3545;
        font-weight: bold;
        display: none;
    }

    .product-entry:not(:first-child) .item-close {
        display: block;
    }

    .product-entry .item-content {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
        align-items: flex-start;
    }

    /* Specific styling for exchange modal */
    #exchangeItemModal .input-group:has(textarea) {
        grid-column: 1 / -1; /* Span full width for textarea */
    }

    #exchangeItemModal .product-entry {
        background: #f8f9fa;
        border: 1px solid #e9ecef;
    }

    #exchangeItemModal small {
        display: block;
        margin-top: 4px;
        font-size: 12px;
        color: #6c757d;
    }
    </style>
</body>

</html>