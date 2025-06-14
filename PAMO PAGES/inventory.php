<?php
session_start();
include 'includes/config_functions.php';
// Build query string for filters
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
    <!-- Inject the logged-in user's name for use in JS -->
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
    <script>
        // Check for low stock filter on page load
        document.addEventListener('DOMContentLoaded', function() {
            const applyLowStockFilter = sessionStorage.getItem('applyLowStockFilter');
            if (applyLowStockFilter === 'true') {
                // Only set the dropdown if no status is set in the URL
                const urlParams = new URLSearchParams(window.location.search);
                if (!urlParams.has('status')) {
                    document.getElementById('statusFilter').value = 'Low Stock';
                    // Submit the form to apply the filter
                    document.getElementById('filterForm').dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
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
        });
        // Function to clear sessionStorage and reload for Clear Filters
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
                    <option value="Tertiary-Uniform"<?php if(($_GET['category'] ?? '')=='Tertiary-Uniform') echo ' selected'; ?>>Tertiary-Uniform</option>
                    <option value="SHS-Uniform"<?php if(($_GET['category'] ?? '')=='SHS-Uniform') echo ' selected'; ?>>SHS-Uniform</option>
                    <option value="STI-Shirts"<?php if(($_GET['category'] ?? '')=='STI-Shirts') echo ' selected'; ?>>STI-Shirts</option>
                    <option value="STI-Jacket"<?php if(($_GET['category'] ?? '')=='STI-Jacket') echo ' selected'; ?>>STI Jacket</option>
                    <option value="STI-Accessories"<?php if(($_GET['category'] ?? '')=='STI-Accessories') echo ' selected'; ?>>STI-Accessories</option>
                    <option value="SHS-PE"<?php if(($_GET['category'] ?? '')=='SHS-PE') echo ' selected'; ?>>SHS-PE</option>
                    <option value="Tertiary-PE"<?php if(($_GET['category'] ?? '')=='Tertiary-PE') echo ' selected'; ?>>Tertiary-PE</option>
                </select>
                <select name="size" id="sizeFilter" onchange="document.getElementById('filterForm').submit()">
                    <option value="">All Sizes</option>
                    <option value="XS"<?php if(($_GET['size'] ?? '')=='XS') echo ' selected'; ?>>XS</option>
                    <option value="S"<?php if(($_GET['size'] ?? '')=='S') echo ' selected'; ?>>S</option>
                    <option value="M"<?php if(($_GET['size'] ?? '')=='M') echo ' selected'; ?>>M</option>
                    <option value="L"<?php if(($_GET['size'] ?? '')=='L') echo ' selected'; ?>>L</option>
                    <option value="XL"<?php if(($_GET['size'] ?? '')=='XL') echo ' selected'; ?>>XL</option>
                    <option value="XXL"<?php if(($_GET['size'] ?? '')=='XXL') echo ' selected'; ?>>XXL</option>
                    <option value="3XL"<?php if(($_GET['size'] ?? '')=='3XL') echo ' selected'; ?>>3XL</option>
                    <option value="4XL"<?php if(($_GET['size'] ?? '')=='4XL') echo ' selected'; ?>>4XL</option>
                    <option value="5XL"<?php if(($_GET['size'] ?? '')=='5XL') echo ' selected'; ?>>5XL</option>
                    <option value="6XL"<?php if(($_GET['size'] ?? '')=='6XL') echo ' selected'; ?>>6XL</option>
                    <option value="7XL"<?php if(($_GET['size'] ?? '')=='7XL') echo ' selected'; ?>>7XL</option>
                    <option value="One Size"<?php if(($_GET['size'] ?? '')=='One Size') echo ' selected'; ?>>One Size</option>
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
                        <i class="material-icons">local_shipping</i> New Delivery
                    </button>
                    <button onclick="showDeductQuantityModal()" class="action-btn">
                        <i class="material-icons">remove_shopping_cart</i> Sales Entry
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

                          // Pagination parameters
                          $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
                          $limit = 15; // Items per page (changed from 20 to 15)
                          $offset = ($page - 1) * $limit;

                          // Count total items for pagination
                          $total_sql = "SELECT COUNT(*) as total FROM inventory $where_clause";
                          $total_result = mysqli_query($conn, $total_sql);
                          $total_row = mysqli_fetch_assoc($total_result);
                          $total_items = $total_row['total'];
                          $total_pages = ceil($total_items / $limit);

                          // Fetch only the items for the current page
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
                    // Always show first page
                    if ($page == 1) {
                        echo '<a href="' . page_link(1, $query_string) . '" class="ajax-page-link active">1</a>';
                    } else {
                        echo '<a href="' . page_link(1, $query_string) . '" class="ajax-page-link">1</a>';
                    }
                    // Show ellipsis if needed before the window
                    if ($page > 4) {
                        echo '<span class="pagination-ellipsis">...</span>';
                    }
                    // Determine window of pages to show around current page
                    $window = 1; // Number of pages before/after current
                    $start = max(2, $page - $window);
                    $end = min($total_pages - 1, $page + $window);
                    for ($i = $start; $i <= $end; $i++) {
                        if ($i == $page) {
                            echo '<a href="' . page_link($i, $query_string) . '" class="ajax-page-link active">' . $i . '</a>';
                        } else {
                            echo '<a href="' . page_link($i, $query_string) . '" class="ajax-page-link">' . $i . '</a>';
                        }
                    }
                    // Show ellipsis if needed after the window
                    if ($page < $total_pages - 3) {
                        echo '<span class="pagination-ellipsis">...</span>';
                    }
                    // Always show last page (if more than 1 page)
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
                    <div class="input-group">
                        <label for="deliveryOrderNumber">Delivery Order #:</label>
                        <input type="text" id="deliveryOrderNumber" name="deliveryOrderNumber" required>
                    </div>
                    <div class="input-group">
                        <label for="newProductItemCode">Item Code:</label>
                        <input type="text" id="newProductItemCode" name="newItemCode" required>
                    </div>
                    <div class="input-group">
                        <label for="newCategory">Category:</label>
                        <select id="newCategory" name="newCategory" required>
                            <option value="">Select Category</option>
                            <option value="Tertiary-Uniform">Tertiary-Uniform</option>
                            <option value="SHS-Uniform">SHS-Uniform</option>
                            <option value="STI-Shirts">STI-Shirts</option>
                            <option value="STI-Accessories">STI-Accessories</option>
                            <option value="SHS-PE">SHS-PE</option>
                            <option value="Tertiary-PE">Tertiary-PE</option>
                            <option value="STI-Jacket">STI Jacket</option>
                        </select>
                    </div>
                    <div class="input-group" id="shirtTypeGroup" style="display:none;">
                        <label for="shirtTypeSelect">Shirt Type:</label>
                        <select id="shirtTypeSelect" name="shirt_type_id" style="width:100%;">
                            <option value="">Select Shirt Type</option>
                            <?php
                            $conn = mysqli_connect("localhost", "root", "", "proware");
                            $result = mysqli_query($conn, "SELECT id, name FROM shirt_type ORDER BY name ASC");
                            while ($row = mysqli_fetch_assoc($result)) {
                                echo '<option value="' . $row['id'] . '">' . htmlspecialchars($row['name']) . '</option>';
                            }
                            ?>
                        </select>
                    </div>
                    <div class="input-group" id="courseGroup" style="display:none;">
                        <label for="courseSelect">Course:</label>
                        <select id="courseSelect" name="course_id[]" multiple style="width:100%;">
                            <option value="">Select Course</option>
                            <?php
                            $conn = mysqli_connect("localhost", "root", "", "proware");
                            $result = mysqli_query($conn, "SELECT id, course_name FROM course ORDER BY course_name ASC");
                            while ($row = mysqli_fetch_assoc($result)) {
                                echo '<option value="' . $row['id'] . '">' . htmlspecialchars($row['course_name']) . '</option>';
                            }
                            ?>
                        </select>
                    </div>
                    <div class="input-group">
                        <label for="newItemName">Product Name:</label>
                        <input type="text" id="newItemName" name="newItemName" required>
                    </div>
                    <div class="input-group">
                        <label for="newSize">Size:</label>
                        <select id="newProductSize" name="newSize" required>
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
                        <label for="newItemPrice">Price:</label>
                        <input type="number" id="newItemPrice" name="newItemPrice" min="0" step="0.01" required>
                    </div>
                    <div class="input-group">
                        <label for="newItemQuantity">Initial Stock:</label>
                        <input type="number" id="newItemQuantity" name="newItemQuantity" min="0" required>
                    </div>
                    <div class="input-group">
                        <label for="newItemDamage">Damaged Items:</label>
                        <input type="number" id="newItemDamage" name="newItemDamage" min="0" value="0" required>
                    </div>
                    <div class="input-group">
                        <label for="newImage">Product Image:</label>
                        <input type="file" id="newImage" name="newImage" accept="image/*" required>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="submit" form="addItemForm" class="save-btn">Add Product</button>
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
                <h2>New Delivery</h2>
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
    .input-group select {
        width: 100%;
        padding: 8px 12px;
        border: 1px solid #ddd;
        border-radius: 4px;
        height: 38px;
        font-size: 14px;
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
    </style>
</body>

</html>