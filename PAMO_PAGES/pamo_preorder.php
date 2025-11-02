<?php
session_start();

if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_id'])) {
    header("Location: ../Pages/login.php?redirect=../PAMO_PAGES/pamo-preoder.php");
    exit();
}
$role = strtoupper($_SESSION['role_category'] ?? '');
$programAbbr = strtoupper($_SESSION['program_abbreviation'] ?? '');
if (!($role === 'EMPLOYEE' && $programAbbr === 'PAMO')) {
    header("Location: ../Pages/home.php");
    exit();
}
include 'includes/pamo_loader.php';
$basePath = '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PAMO - Pre-Order</title>
    <link rel="stylesheet" href="../PAMO CSS/styles.css">
    <link rel="stylesheet" href="../PAMO CSS/preorder.css">
    <link rel="stylesheet" href="../CSS/logout-modal.css">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="../Javascript/logout-modal.js"></script>
    <style>
        /* Tab Navigation Styles */
        .tab-navigation {
            display: flex;
            border-bottom: 2px solid #e0e0e0;
            margin-bottom: 25px;
            background: white;
            border-radius: 8px 8px 0 0;
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

        /* Pre-Order Management Styles */
        .preorder-summary-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .summary-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 2px solid #e0e0e0;
        }
        .item-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .item-info img {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 4px;
        }
        .sizes-breakdown {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
            gap: 10px;
            margin: 15px 0;
        }
        .size-badge {
            background: #f5f5f5;
            padding: 8px;
            border-radius: 4px;
            text-align: center;
        }
        .size-badge strong {
            display: block;
            font-size: 1.2em;
            color: #1976d2;
        }
        .customer-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        .customer-table th,
        .customer-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
        }
        .customer-table th {
            background: #f5f5f5;
            font-weight: 600;
        }
        .status-badge {
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 0.85em;
            font-weight: 500;
        }
        .status-pending {
            background: #fff3e0;
            color: #e65100;
        }
        .status-delivered {
            background: #e8f5e9;
            color: #2e7d32;
        }
        .status-completed {
            background: #e3f2fd;
            color: #1565c0;
        }
        .status-voided {
            background: #ffebee;
            color: #c62828;
        }
        .btn-mark-delivered {
            background: #4caf50;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .btn-mark-delivered:hover {
            background: #45a049;
        }
        .btn-mark-delivered:disabled {
            background: #ccc;
            cursor: not-allowed;
        }
        .filter-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }
        .filter-tab {
            padding: 10px 20px;
            background: white;
            border: 2px solid #e0e0e0;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.3s;
        }
        .filter-tab.active {
            background: #1976d2;
            color: white;
            border-color: #1976d2;
        }
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #666;
        }
        .empty-state i {
            font-size: 64px;
            color: #ccc;
        }
    </style>
</head>
<body>
    <div class="container">
        <?php include 'includes/sidebar.php'; ?>
        <main class="main-content">
            <div class="page-header">
                <h2>Pre-Order Management</h2>
                <div style="display: flex; gap: 10px; align-items: center;">
                    <button class="action-btn" id="addPreItemBtn"><i class="material-icons">add_circle</i> New Pre-Order Item</button>
                </div>
            </div>

            <!-- Tab Navigation -->
            <div class="tab-navigation">
                <button class="tab-button active" onclick="switchPreOrderTab('items')">
                    <i class="material-icons">inventory_2</i>
                    Pre-Order Items
                </button>
                <button class="tab-button" onclick="switchPreOrderTab('requests')">
                    <i class="material-icons">list_alt</i>
                    Pre-Order Requests
                </button>
            </div>

            <!-- Pre-Order Items Tab -->
            <div id="tab-items" class="tab-content active">
                <div id="preorderList" class="card" style="padding:16px;">
                    <table class="datatable">
                        <thead>
                            <tr>
                                <th>Image</th>
                                <th>Name</th>
                                <th>Base Code</th>
                                <th>Price</th>
                                <th>Requests</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="preorderRows"></tbody>
                    </table>
                </div>
            </div>

            <!-- Pre-Order Requests Tab -->
            <div id="tab-requests" class="tab-content">
                <div style="padding: 20px; background: #f8f9fa; border-radius: 8px; margin-bottom: 20px;">
                    <h3 style="margin: 0 0 10px 0; color: #495057;">
                        <i class="material-icons" style="vertical-align: middle;">info</i>
                        Pending Pre-Orders
                    </h3>
                    <p style="margin: 0; color: #6c757d;">
                        This section displays all pending pre-order requests from customers. Use this information to determine quantities needed when ordering from suppliers. 
                        To mark items as delivered, go to the <strong>Pre-Order Items</strong> tab.
                    </p>
                </div>

                <div id="preorderContainer">
                    <!-- Pre-order requests will be loaded here -->
                </div>
            </div>
        </main>
    </div>

    <!-- Add Modal -->
    <div id="addPreModal" class="modal" style="display:none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Add Pre-Order Item</h3>
                <span class="close" onclick="$('#addPreModal').hide()">&times;</span>
            </div>
            <form id="addPreForm" enctype="multipart/form-data">
                <div class="grid-2">
                    <div class="input-group">
                        <label>Item Code</label>
                        <input type="text" name="base_item_code" required>
                    </div>
                    <div class="input-group">
                        <label>Item Name</label>
                        <input type="text" name="item_name" required>
                    </div>
                    <div class="input-group">
                        <label>Category</label>
                        <select name="category_id" id="preCategory"></select>
                    </div>
                    <div class="input-group">
                        <label>Price</label>
                        <input type="number" name="price" step="0.01" min="0" required>
                    </div>
                    <div class="input-group" id="preSubcatGroup" style="display:none;">
                        <label>Subcategories</label>
                        <select name="subcategory_ids[]" id="preSubcategories" multiple style="height:120px;"></select>
                    </div>
                    <div class="input-group" style="grid-column: 1 / span 2;">
                        <label>Image</label>
                        <input type="file" name="image" accept="image/*" id="preImageInput">
                        <small class="file-info">Recommended: JPG/PNG, max 2MB</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="save-btn">Save</button>
                    <button type="button" class="cancel-btn" onclick="$('#addPreModal').hide()">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Deliver Modal -->
    <div id="deliverModal" class="modal" style="display:none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Mark Delivered</h3>
                <span class="close" onclick="closeDeliverModal()">&times;</span>
            </div>
            <div class="modal-body">
                <div class="input-group">
                    <label for="orderNumber">Order Number</label>
                    <input type="text" id="orderNumber" name="order_number" required placeholder="Enter order number">
                </div>
                
                <div class="size-selection-section">
                    <h4>Select Sizes to Deliver</h4>
                    <div class="size-checkboxes" id="deliverSizeCheckboxes">
                        <!-- Size checkboxes will be dynamically populated -->
                    </div>
                </div>

                <div id="deliverSizeDetailsContainer" class="size-details-container">
                    <h4>Delivery Quantities</h4>
                    <div id="deliverSizeDetailsList">
                        <!-- Size-specific quantity inputs will appear here -->
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="save-btn" id="deliverSubmit">Mark as Delivered</button>
                <button type="button" class="cancel-btn" onclick="closeDeliverModal()">Cancel</button>
            </div>
        </div>
    </div>

    <script>
    let CATEGORIES = [];
    
    // Alert system for feedback
    function showAlert(message, type = 'success') {
        const alertDiv = $(`
            <div class="alert alert-${type}">
                <i class="material-icons">${type === 'success' ? 'check_circle' : 'error'}</i>
                ${message}
            </div>
        `);
        
        $('.main-content').prepend(alertDiv);
        
        // Auto remove after 5 seconds
        setTimeout(() => {
            alertDiv.fadeOut(300, () => alertDiv.remove());
        }, 5000);
        
        // Manual close on click
        alertDiv.click(() => {
            alertDiv.fadeOut(300, () => alertDiv.remove());
        });
    }
    
    function loadCategories() {
        return $.getJSON('../PAMO%20Inventory%20backend/api_categories_list.php')
            .then(rows => {
                CATEGORIES = rows || [];
                const $cat = $('#preCategory').empty();
                $cat.append('<option value="">-- none --</option>');
                $cat.append('<option value="__add__">+ Add new category…</option>');
                CATEGORIES.forEach(r => $cat.append(`<option value="${r.id}" data-has="${r.has_subcategories ? 1 : 0}">${r.name}</option>`));
            });
    }
    function loadSubcategories(categoryId, selectIds = []) {
        if (!categoryId) { $('#preSubcatGroup').hide(); $('#preSubcategories').empty(); return; }
        return $.getJSON('../PAMO%20Inventory%20backend/api_subcategories_list.php', { category_id: categoryId })
            .then(rows => {
                const $s = $('#preSubcategories');
                if ($s.data('select2')) { $s.select2('destroy'); }
                $s.empty();
                $s.append('<option value="__add__">+ Add new subcategory…</option>');
                (rows||[]).forEach(r => {
                    const opt = $('<option>').val(r.id).text(r.name);
                    if (selectIds.includes(String(r.id))) opt.attr('selected', true);
                    $s.append(opt[0]);
                });
                $('#preSubcatGroup').show();
                $s.select2({ placeholder: 'Select subcategories…', width: '100%' });
            });
    }
    function loadPreorderItems() {
        $('#preorderList').addClass('loading');
        
        $.ajax({
            url: '../PAMO_PREORDER_BACKEND/api_preorder_list.php',
            method: 'GET',
            dataType: 'json',
            timeout: 10000, // 10 second timeout
            cache: false
        })
        .done(function(resp) {
            const $tbody = $('#preorderRows').empty();
            
            if (resp && resp.items && Array.isArray(resp.items)) {
                resp.items.forEach(it => {
                    const img = it.image_path ? `../${it.image_path}` : '../uploads/itemlist/default.png';
                    const isPending = (String(it.status).toLowerCase() === 'pending');
                    const actionCell = isPending
                      ? `<button class="table-btn deliver-btn" onclick="openDeliver(this)"><i class="material-icons">local_shipping</i> Mark Delivered</button>`
                      : `<button class="table-btn delivered-btn" disabled><i class="material-icons">check_circle</i> Delivered</button>`;
                    const row = `
                        <tr data-id="${it.id}" data-sizes="${it.sizes}" data-status="${it.status.toLowerCase()}">
                            <td><img src="${img}" alt="${it.item_name}" title="${it.item_name}"></td>
                            <td><strong>${it.item_name}</strong></td>
                            <td><code>${it.base_item_code}</code></td>
                            <td><strong>₱${parseFloat(it.price).toFixed(2)}</strong></td>
                            <td><strong>${it.total_requests}</strong></td>
                            <td><span class="status-badge status-${it.status.toLowerCase()}">${it.status}</span></td>
                            <td>
                                ${actionCell}
                            </td>
                        </tr>`;
                    $tbody.append(row);
                });
                
                // Show empty state if no items
                if (resp.items.length === 0) {
                    $tbody.append(`
                        <tr>
                            <td colspan="8" style="text-align: center; padding: 40px;">
                                <div class="no-preorders">
                                    <i class="material-icons">inbox</i>
                                    <h3>No Pre-Order Items</h3>
                                    <p>Start by adding your first pre-order item using the button above.</p>
                                </div>
                            </td>
                        </tr>
                    `);
                }
            } else {
                // Handle case where response is invalid
                $tbody.append(`
                    <tr>
                        <td colspan="8" style="text-align: center; padding: 40px;">
                            <div class="no-preorders">
                                <i class="material-icons">error_outline</i>
                                <h3>Error Loading Data</h3>
                                <p>Unable to load pre-order items. Please try refreshing the page.</p>
                            </div>
                        </td>
                    </tr>
                `);
            }
        })
        .fail(function(xhr, status, error) {
            console.error('Failed to load preorders:', error);
            const $tbody = $('#preorderRows').empty();
            $tbody.append(`
                <tr>
                    <td colspan="8" style="text-align: center; padding: 40px;">
                        <div class="no-preorders">
                            <i class="material-icons">wifi_off</i>
                            <h3>Connection Error</h3>
                            <p>Failed to load pre-order items. Please check your connection and try again.</p>
                            <button class="action-btn" onclick="loadPreorderItems()">Retry</button>
                        </div>
                    </td>
                </tr>
            `);
        })
        .always(function() {
            // Always remove loading state, regardless of success or failure
            $('#preorderList').removeClass('loading');
            
            // Also ensure main loader is hidden
            if (window.STILoader) {
                window.STILoader.hide();
            }
        });
    }

    let currentPreorderItemId = null;
    let currentPreorderBaseCode = null;
    let availableSizes = [];

    function openDeliver(btn) {
        const $tr = $(btn).closest('tr');
        const id = $tr.data('id');
        const sizesString = String($tr.data('sizes') || '');
        const baseCode = $tr.find('code').text() || 'BASE'; // Get base code from the table row
        
        currentPreorderItemId = id;
        currentPreorderBaseCode = baseCode;
        availableSizes = sizesString.split(',').map(s => s.trim()).filter(Boolean);
        
        // Reset form
        $('#orderNumber').val('');
        $('#deliverSizeDetailsContainer').removeClass('show');
        $('#deliverSizeDetailsList').empty();
        
        // Populate size checkboxes
        populateDeliverSizeCheckboxes(availableSizes);
        
        $('#deliverModal').show();
    }

    function populateDeliverSizeCheckboxes(sizes) {
        const container = $('#deliverSizeCheckboxes');
        container.empty();
        
        // Define hierarchical order (same as inventory modal)
        const sizeOrder = ['XS', 'S', 'M', 'L', 'XL', 'XXL', '3XL', '4XL', '5XL', '6XL', '7XL', 'One Size'];
        
        // Sort sizes according to hierarchy
        const sortedSizes = sizes.sort((a, b) => {
            const indexA = sizeOrder.indexOf(a);
            const indexB = sizeOrder.indexOf(b);
            return indexA - indexB;
        });
        
        sortedSizes.forEach(size => {
            const checkboxHtml = `
                <label class="checkbox-label">
                    <input type="checkbox" value="${size}" onchange="toggleDeliverSizeDetails(this)"> 
                    ${size}
                </label>
            `;
            container.append(checkboxHtml);
        });
    }

    function closeDeliverModal() {
        $('#deliverModal').hide();
        currentPreorderItemId = null;
        currentPreorderBaseCode = null;
        availableSizes = [];
    }

    function toggleDeliverSizeDetails(checkbox) {
        const sizeDetailsContainer = document.getElementById('deliverSizeDetailsContainer');
        const sizeDetailsList = document.getElementById('deliverSizeDetailsList');

        if (checkbox.checked) {
            addDeliverSizeDetailForm(checkbox.value);
            sizeDetailsContainer.classList.add('show');
        } else {
            removeDeliverSizeDetailForm(checkbox.value);
            
            const checkedSizes = document.querySelectorAll('#deliverSizeCheckboxes input[type="checkbox"]:checked');
            if (checkedSizes.length === 0) {
                sizeDetailsContainer.classList.remove('show');
            }
        }
    }

    function addDeliverSizeDetailForm(size) {
        const sizeDetailsList = document.getElementById('deliverSizeDetailsList');
        
        // Generate item code using BASE ITEM CODE from preorder item
        const baseItemCode = currentPreorderBaseCode || 'BASE';
        const sizeNumber = getSizeNumber(size);
        const generatedCode = `${baseItemCode}-${sizeNumber.toString().padStart(3, '0')}`;
        const displayOrder = getSizeDisplayOrder(size);
        
        const sizeDetailHtml = `
            <div class="size-detail-item" data-size="${size}" data-size-order="${displayOrder}">
                <div class="size-detail-header">
                    <h4>Size: ${size}</h4>
                    <span class="generated-code">Code: ${generatedCode}</span>
                </div>
                <div class="size-detail-form">
                    <div class="input-group">
                        <label for="deliver_price_${size}">Price:</label>
                        <input type="number" id="deliver_price_${size}" name="delivered_data[${size}][price]" min="0" step="0.01" required>
                    </div>
                    <div class="input-group">
                        <label for="deliver_qty_${size}">Initial Stock:</label>
                        <input type="number" id="deliver_qty_${size}" name="delivered_data[${size}][quantity]" min="0" step="1" required>
                    </div>
                    <div class="input-group">
                        <label for="deliver_damage_${size}">Damaged Items:</label>
                        <input type="number" id="deliver_damage_${size}" name="delivered_data[${size}][damage]" min="0" step="1" value="0">
                    </div>
                </div>
                <input type="hidden" name="delivered_data[${size}][item_code]" value="${generatedCode}">
                <input type="hidden" name="delivered_data[${size}][base_code]" value="${baseItemCode}">
                <input type="hidden" name="delivered_data[${size}][size]" value="${size}">
            </div>
        `;
        
        sizeDetailsList.insertAdjacentHTML('beforeend', sizeDetailHtml);
        sortDeliverySizeDetails();
    }

    function sortDeliverySizeDetails() {
        const sizeDetailsList = document.getElementById('deliverSizeDetailsList');
        const sizeItems = Array.from(sizeDetailsList.querySelectorAll('.size-detail-item'));

        sizeItems.sort((a, b) => {
            const orderA = parseInt(a.dataset.sizeOrder);
            const orderB = parseInt(b.dataset.sizeOrder);
            return orderA - orderB;
        });

        sizeDetailsList.innerHTML = '';
        sizeItems.forEach((item) => {
            sizeDetailsList.appendChild(item);
        });
    }

    function getSizeDisplayOrder(size) {
        const sizeOrderMap = {
            'XS': 1, 'S': 2, 'M': 3, 'L': 4, 'XL': 5, 'XXL': 6,
            '3XL': 7, '4XL': 8, '5XL': 9, '6XL': 10, '7XL': 11, 'One Size': 12
        };
        return sizeOrderMap[size] || 99;
    }

    // Helper function to get size number (similar to inventory system)
    function getSizeNumber(size) {
        const sizeMap = {
            'XS': 1, 'S': 2, 'M': 3, 'L': 4, 'XL': 5, 'XXL': 6,
            '3XL': 7, '4XL': 8, '5XL': 9, '6XL': 10, '7XL': 11, 'One Size': 12
        };
        return sizeMap[size] || 99;
    }

    function removeDeliverSizeDetailForm(size) {
        const sizeDetailItem = document.querySelector(`#deliverSizeDetailsList [data-size="${size}"]`);
        if (sizeDetailItem) {
            sizeDetailItem.remove();
        }
    }

    $('#deliverSubmit').on('click', function(){
        const orderNumber = $('#orderNumber').val().trim();
        if (!orderNumber) {
            showAlert('Please enter an order number', 'error');
            return;
        }

        const delivered = {};
        const deliveredData = {};
        const checkedSizes = document.querySelectorAll('#deliverSizeCheckboxes input[type="checkbox"]:checked');
        
        if (checkedSizes.length === 0) {
            showAlert('Please select at least one size to deliver', 'error');
            return;
        }

        let hasValidQuantities = false;
        checkedSizes.forEach(checkbox => {
            const size = checkbox.value;
            const priceInput = document.getElementById(`deliver_price_${size}`);
            const qtyInput = document.getElementById(`deliver_qty_${size}`);
            const damageInput = document.getElementById(`deliver_damage_${size}`);
            
            const price = parseFloat(priceInput.value) || 0;
            const quantity = parseInt(qtyInput.value) || 0;
            const damage = parseInt(damageInput.value) || 0;
            
            if (quantity > 0 && price > 0) {
                delivered[size] = quantity;
                deliveredData[size] = {
                    price: price,
                    quantity: quantity,
                    damage: damage,
                    item_code: `${currentPreorderBaseCode}-${getSizeNumber(size).toString().padStart(3, '0')}`,
                    size: size
                };
                hasValidQuantities = true;
            }
        });

        if (!hasValidQuantities) {
            showAlert('Please enter valid quantities and prices for selected sizes', 'error');
            return;
        }

        // Show loader while processing
        if (window.STILoader) {
            window.STILoader.show();
        }

        $.ajax({
            url: '../PAMO_PREORDER_BACKEND/api_preorder_mark_delivered.php',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ 
                preorder_item_id: currentPreorderItemId, 
                order_number: orderNumber,
                delivered: delivered,
                delivered_data: deliveredData
            }),
        }).done((response) => { 
            closeDeliverModal(); 
            showAlert('Pre-order marked as delivered successfully!', 'success');
            loadPreorderItems(); 
        })
        .fail(xhr => showAlert(xhr.responseJSON?.message || 'Failed to mark as delivered', 'error'))
        .always(() => {
            // Always hide loader when request completes
            if (window.STILoader) {
                window.STILoader.hide();
            }
        });
    });

    $('#addPreItemBtn').on('click', function(){
        $('#addPreForm')[0].reset();
        $('#preSubcatGroup').hide();
        $('#addPreModal').show();
        
        // Ensure loader is hidden when opening modal
        if (window.STILoader) {
            window.STILoader.hide();
        }
    });

    $('#addPreForm').on('submit', function(e){
        e.preventDefault();
        const form = this;
        
        // Show loader while processing
        if (window.STILoader) {
            window.STILoader.show();
        }
        
        // Check and compress image if needed
        const imageInput = document.getElementById('preImageInput');
        if (imageInput.files.length > 0) {
            const file = imageInput.files[0];
            if (file.size > 2 * 1024 * 1024) { // 2MB
                showAlert('Image size too large. Please select an image smaller than 2MB.', 'error');
                if (window.STILoader) window.STILoader.hide();
                return;
            }
        }
        
        const fd = new FormData(form);
        
        $.ajax({
            url: '../PAMO_PREORDER_BACKEND/api_preorder_create.php',
            method: 'POST',
            data: fd,
            processData: false,
            contentType: false
        }).done((response)=>{ 
            $('#addPreModal').hide(); 
            showAlert('Pre-order item created successfully!', 'success');
            loadPreorderItems(); 
            
            // Force hide loader immediately
            setTimeout(() => {
                if (window.STILoader) window.STILoader.hide();
                const loader = document.getElementById('pamo-loader');
                if (loader) {
                    loader.style.display = 'none';
                    loader.style.opacity = '0';
                    loader.style.visibility = 'hidden';
                    loader.classList.add('hidden');
                }
            }, 100);
        })
        .fail(xhr=> {
            showAlert(xhr.responseJSON?.message || 'Failed to create pre-order item', 'error');
            // Also hide loader on error
            setTimeout(() => {
                if (window.STILoader) window.STILoader.hide();
                const loader = document.getElementById('pamo-loader');
                if (loader) {
                    loader.style.display = 'none';
                    loader.style.opacity = '0';
                    loader.style.visibility = 'hidden';
                    loader.classList.add('hidden');
                }
            }, 100);
        });
    });

    // Category dynamic add and subcategory prompt behavior
    $('#preCategory').on('change', async function(){
        const val = $(this).val();
        if (val === '__add__') {
            const name = prompt('Enter new category name:');
            if (!name) { await loadCategories(); return; }
            const has = confirm('Does this category have subcategories? Click OK for Yes, Cancel for No.') ? 1 : 0;
            try {
                const resp = await fetch('../PAMO%20Inventory%20backend/api_categories_create.php', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ name, has_subcategories: has }) });
                const data = await resp.json();
                if (!data.success) throw new Error(data.message||'Failed');
                await loadCategories();
                $('#preCategory').val(String(data.id)).trigger('change');
            } catch(err) {
                alert(err.message);
                await loadCategories();
            }
            return;
        }
        const has = Number($('#preCategory option:selected').data('has')||0);
        if (has) {
            await loadSubcategories(val);
        } else {
            $('#preSubcatGroup').hide();
            $('#preSubcategories').empty();
        }
    });

    // Subcategory dynamic add
    $('#preSubcategories').on('change', async function(){
        const vals = ($(this).val()||[]).map(String);
        if (vals.includes('__add__')) {
            const name = prompt('Enter new subcategory name:');
            const categoryId = $('#preCategory').val();
            if (!name || !categoryId) { $(this).val(vals.filter(v=>v!=='__add__')); return; }
            try {
                const resp = await fetch('../PAMO%20Inventory%20backend/api_subcategories_create.php', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ category_id: Number(categoryId), name }) });
                const data = await resp.json();
                if (!data.success) throw new Error(data.message||'Failed');
                await loadSubcategories(categoryId, [String(data.id)]);
            } catch(err) { alert(err.message); }
        }
    });

    $(async function(){
        try {
            await loadCategories();
            // Enhance category dropdown
            if (!$('#preCategory').data('select2')) {
                $('#preCategory').select2({ placeholder: 'Select category…', width: '100%' });
            }
            await loadPreorderItems();
        } catch (error) {
            console.error('Error initializing page:', error);
            showAlert('Error loading page data', 'error');
        } finally {
            // Ensure loader is hidden after page initialization
            if (window.STILoader) {
                window.STILoader.hide();
            }
        }
    });

    // Additional safety net - hide loader when window is fully loaded
    $(window).on('load', function() {
        setTimeout(() => {
            if (window.STILoader) {
                window.STILoader.hide();
            }
        }, 500);
    });
    </script>

    <style>
    /* Enhanced Delivery Modal Styles */
    #deliverModal {
        z-index: 10000;
    }

    #deliverModal .modal-content {
        max-width: 900px;
        width: 90%;
        max-height: 90vh;
        overflow-y: auto;
        border-radius: 12px;
        box-shadow: 0 25px 50px rgba(0, 0, 0, 0.3);
    }

    #deliverModal .modal-header {
        background: linear-gradient(135deg, #007bff, #0056b3);
        color: white;
        padding: 25px 30px;
        border-radius: 12px 12px 0 0;
        border: none;
    }

    #deliverModal .modal-header h3 {
        margin: 0;
        font-size: 22px;
        font-weight: 600;
        letter-spacing: 0.5px;
    }

    #deliverModal .modal-header .close {
        color: white;
        font-size: 28px;
        font-weight: bold;
        opacity: 0.8;
        transition: opacity 0.2s;
    }

    #deliverModal .modal-header .close:hover {
        opacity: 1;
    }

    #deliverModal .modal-body {
        padding: 30px;
        background: #f8f9fa;
    }

    #deliverModal .input-group {
        margin-bottom: 25px;
    }

    #deliverModal .input-group label {
        display: block;
        margin-bottom: 8px;
        font-weight: 600;
        color: #495057;
        font-size: 15px;
        letter-spacing: 0.3px;
    }

    #deliverModal .input-group input {
        width: 100%;
        padding: 12px 16px;
        border: 2px solid #e9ecef;
        border-radius: 8px;
        font-size: 15px;
        background-color: #fff;
        transition: all 0.3s ease;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
    }

    #deliverModal .input-group input:focus {
        outline: none;
        border-color: #007bff;
        box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.1);
        transform: translateY(-1px);
    }

    #deliverModal .size-selection-section {
        background: linear-gradient(135deg, #fff3cd, #ffeaa7);
        padding: 25px;
        border-radius: 12px;
        margin-bottom: 25px;
        border: 2px solid #ffeaa7;
        box-shadow: 0 4px 12px rgba(255, 234, 167, 0.3);
    }

    #deliverModal .size-selection-section h4 {
        margin: 0 0 20px 0;
        color: #856404;
        font-size: 18px;
        font-weight: 700;
        letter-spacing: 0.5px;
        text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.1);
    }

    #deliverModal .size-checkboxes {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
        gap: 12px;
        margin-top: 15px;
    }

    #deliverModal .checkbox-label {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 12px 16px;
        background: white;
        border: 3px solid #e9ecef;
        border-radius: 10px;
        cursor: pointer;
        transition: all 0.3s ease;
        font-weight: 600;
        font-size: 14px;
        min-height: 48px;
        box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
        position: relative;
        overflow: hidden;
    }

    #deliverModal .checkbox-label::before {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(0, 123, 255, 0.1), transparent);
        transition: left 0.5s;
    }

    #deliverModal .checkbox-label:hover {
        background: #f8f9fa;
        border-color: #007bff;
        transform: translateY(-2px);
        box-shadow: 0 6px 16px rgba(0, 123, 255, 0.2);
    }

    #deliverModal .checkbox-label:hover::before {
        left: 100%;
    }

    #deliverModal .checkbox-label input[type="checkbox"] {
        width: 20px;
        height: 20px;
        margin: 0;
        accent-color: #007bff;
        flex-shrink: 0;
    }

    #deliverModal .checkbox-label:has(input[type="checkbox"]:checked) {
        color: #007bff;
        font-weight: 700;
        border-color: #007bff;
        background: linear-gradient(135deg, #e7f3ff, #cce7ff);
        box-shadow: 0 4px 12px rgba(0, 123, 255, 0.3);
    }

    #deliverModal .size-details-container {
        display: none;
        background: white;
        border-radius: 12px;
        padding: 25px;
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
        border: 2px solid #e9ecef;
    }

    #deliverModal .size-details-container.show {
        display: block;
        animation: slideIn 0.3s ease-out;
    }

    @keyframes slideIn {
        from {
            opacity: 0;
            transform: translateY(-10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    #deliverModal .size-details-container h4 {
        margin: 0 0 25px 0;
        color: #495057;
        font-size: 20px;
        font-weight: 700;
        padding-bottom: 15px;
        border-bottom: 3px solid #e9ecef;
    }

    #deliverModal .size-detail-item {
        background: linear-gradient(135deg, #e7f3ff, #f0f8ff);
        border: 2px solid #b3d9ff;
        border-radius: 12px;
        padding: 25px;
        margin-bottom: 20px;
        position: relative;
        box-shadow: 0 4px 12px rgba(0, 123, 255, 0.1);
        transition: all 0.3s ease;
    }

    #deliverModal .size-detail-item:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(0, 123, 255, 0.15);
    }

    #deliverModal .size-detail-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        padding-bottom: 15px;
        border-bottom: 2px solid #b3d9ff;
    }

    #deliverModal .size-detail-header h4 {
        margin: 0;
        color: #0066cc;
        font-size: 18px;
        font-weight: 700;
        text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.1);
    }

    #deliverModal .generated-code {
        font-family: 'Courier New', monospace;
        background: white;
        padding: 8px 14px;
        border-radius: 6px;
        border: 2px solid #ddd;
        font-size: 13px;
        font-weight: 600;
        color: #666;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        letter-spacing: 0.5px;
    }

    #deliverModal .size-detail-form {
        display: grid;
        grid-template-columns: 1fr 1fr 1fr;
        gap: 20px;
    }

    #deliverModal .size-detail-form .input-group {
        margin-bottom: 0;
    }

    #deliverModal .size-detail-form .input-group label {
        margin-bottom: 10px;
        font-size: 14px;
        color: #0066cc;
        font-weight: 600;
    }

    #deliverModal .size-detail-form .input-group input {
        padding: 10px 14px;
        border: 2px solid #b3d9ff;
        background: white;
        font-size: 14px;
    }

    #deliverModal .size-detail-form .input-group input:focus {
        border-color: #007bff;
        box-shadow: 0 0 0 2px rgba(0, 123, 255, 0.2);
    }

    #deliverModal .modal-footer {
        background: white;
        padding: 25px 30px;
        border-radius: 0 0 12px 12px;
        border-top: 2px solid #e9ecef;
        display: flex;
        justify-content: flex-end;
        gap: 15px;
    }

    #deliverModal .modal-footer button {
        padding: 12px 24px;
        border-radius: 8px;
        font-size: 16px;
        font-weight: 600;
        cursor: pointer;
        border: none;
        transition: all 0.3s ease;
        letter-spacing: 0.5px;
        min-width: 120px;
    }

    #deliverModal .save-btn {
        background: linear-gradient(135deg, #28a745, #20c997);
        color: white;
        box-shadow: 0 4px 12px rgba(40, 167, 69, 0.3);
    }

    #deliverModal .save-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 16px rgba(40, 167, 69, 0.4);
    }

    #deliverModal .cancel-btn {
        background: linear-gradient(135deg, #dc3545, #c82333);
        color: white;
        box-shadow: 0 4px 12px rgba(220, 53, 69, 0.3);
    }

    #deliverModal .cancel-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 16px rgba(220, 53, 69, 0.4);
    }

    /* File info styling */
    .file-info {
        color: #666 !important;
        font-size: 12px !important;
        margin-top: 4px !important;
        display: block !important;
    }
    </style>


    <script>
    // Tab switching function
    function switchPreOrderTab(tabName) {
        // Remove active class from all tabs
        document.querySelectorAll('.tab-button').forEach(btn => btn.classList.remove('active'));
        document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
        
        // Add active class to selected tab
        event.target.classList.add('active');
        document.getElementById('tab-' + tabName).classList.add('active');
        
        // Load appropriate data
        if (tabName === 'requests') {
            loadPreorderRequests();
        } else {
            loadPreorderItems();
        }
    }

    // Pre-Order Requests Management
    let currentStatus = 'pending'; // Always show pending requests only

    function loadPreorderRequests() {
        $.ajax({
            url: '../PAMO_PREORDER_BACKEND/api_preorder_orders_list.php',
            method: 'GET',
            data: { status: 'pending' }, // Always load pending only
            dataType: 'json',
            success: function(response) {
                if (!response.success) {
                    alert('Error: ' + response.message);
                    return;
                }

                const groupedItems = response.grouped_by_item || [];

                if (groupedItems.length === 0) {
                    $('#preorderContainer').html(`
                        <div class="empty-state">
                            <i class="material-icons">inbox</i>
                            <h3>No pending pre-orders found</h3>
                            <p>Pre-order requests will appear here when customers submit their orders.</p>
                        </div>
                    `);
                    return;
                }

                let html = '';
                groupedItems.forEach(item => {
                    html += renderPreorderItem(item);
                });

                $('#preorderContainer').html(html);
            },
            error: function(xhr, status, error) {
                console.error('Error loading pre-orders:', error);
                console.error('Response:', xhr.responseText);
                alert('Failed to load pre-orders. Please try again.');
            }
        });
    }

    function renderPreorderItem(item) {
        let sizesHtml = '';
        if (item.sizes_breakdown) {
            for (const [size, qty] of Object.entries(item.sizes_breakdown)) {
                sizesHtml += `
                    <div class="size-badge">
                        <span style="font-size: 0.9em; color: #666;">${size}</span>
                        <strong>${qty}</strong>
                    </div>
                `;
            }
        }

        let customersHtml = '';
        if (item.orders) {
            item.orders.forEach(order => {
                const items = order.items_decoded || [];
                const itemDetails = items.map(i => `${i.size} (${i.quantity}x)`).join(', ');
                const idLabel = order.customer_role === 'EMPLOYEE' ? 'Employee ID' : 'Student ID';
                
                customersHtml += `
                    <tr>
                        <td>${order.preorder_number}</td>
                        <td>${order.customer_name}</td>
                        <td>${idLabel}: ${order.customer_id_number}</td>
                        <td>${order.customer_email}</td>
                        <td>${itemDetails}</td>
                        <td>₱${order.formatted_total}</td>
                        <td><span class="status-badge status-${order.status}">${ucfirst(order.status)}</span></td>
                        <td>${order.formatted_date}</td>
                    </tr>
                `;
            });
        }

        return `
            <div class="preorder-summary-card">
                <div class="summary-header">
                    <div class="item-info">
                        <img src="../${item.image_path || 'uploads/itemlist/default.png'}" 
                             alt="${item.item_name}"
                             onerror="this.src='../uploads/itemlist/default.png'">
                        <div>
                            <h3 style="margin: 0 0 5px 0;">${item.item_name}</h3>
                            <p style="margin: 0; color: #666;">Code: ${item.base_item_code} | Price: ₱${parseFloat(item.item_price).toFixed(2)}</p>
                        </div>
                    </div>
                    <div style="text-align: right;">
                        <div style="font-size: 2em; font-weight: bold; color: #1976d2;">${item.total_orders}</div>
                        <div style="color: #666;">Pre-Orders</div>
                    </div>
                </div>

                <h4 style="margin: 15px 0 10px 0;">Size Breakdown (Total Quantity: ${item.total_quantity})</h4>
                <div class="sizes-breakdown">
                    ${sizesHtml}
                </div>

                <h4 style="margin: 20px 0 10px 0;">Customer Details</h4>
                <table class="customer-table">
                    <thead>
                        <tr>
                            <th>PRE-ORDER #</th>
                            <th>Customer Name</th>
                            <th>ID Number</th>
                            <th>Email</th>
                            <th>Size & Qty</th>
                            <th>Total</th>
                            <th>Status</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${customersHtml}
                    </tbody>
                </table>
            </div>
        `;
    }

    function ucfirst(str) {
        return str.charAt(0).toUpperCase() + str.slice(1);
    }
    </script>

</body>
</html>