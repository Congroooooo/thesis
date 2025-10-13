<?php
session_start();

// Use absolute paths for better compatibility across environments
$base_dir = dirname(__DIR__);
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

include $connection_file;

if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_id'])) {
    header("Location: ../Pages/login.php?redirect=../PAMO_PAGES/content-edit.php");
    exit();
}
$role = strtoupper($_SESSION['role_category'] ?? '');
$programAbbr = strtoupper($_SESSION['program_abbreviation'] ?? '');
if (!($role === 'EMPLOYEE' && $programAbbr === 'PAMO')) {
    header("Location: ../Pages/home.php");
    exit();
}

$feedback = '';
if (isset($_GET['success'])) {
    $feedback = '<div class="alert success" id="feedbackMsg">
        <i class="material-icons" style="margin-right: 8px;">check_circle</i>
        Image uploaded successfully!
        <span class="close-btn" onclick="this.parentElement.style.display=\'none\';">&times;</span>
    </div>';
}
if (isset($_GET['error'])) {
    $error = htmlspecialchars($_GET['error']);
    $feedback = '<div class="alert error" id="feedbackMsg">
        <i class="material-icons" style="margin-right: 8px;">error</i>
        <strong>Upload Failed:</strong> ' . $error . '
        <span class="close-btn" onclick="this.parentElement.style.display=\'none\';">&times;</span>
    </div>';
}

include 'includes/pamo_loader.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Content Management</title>
    <link rel="stylesheet" href="../PAMO CSS/content-edit.css">
    <link rel="stylesheet" href="../PAMO CSS/styles.css">
    <link rel="stylesheet" href="../CSS/logout-modal.css">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <style>
        .alert { 
            padding: 15px 20px; 
            border-radius: 12px; 
            margin-bottom: 20px; 
            font-size: 1em; 
            position: relative; 
            display: flex; 
            align-items: center;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .alert.success { 
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%); 
            color: #155724; 
            border: 1px solid #c3e6cb; 
        }
        .alert.error { 
            background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%); 
            color: #721c24; 
            border: 1px solid #f5c6cb; 
        }
        .close-btn { 
            position: absolute; 
            right: 15px; 
            top: 50%; 
            transform: translateY(-50%); 
            cursor: pointer; 
            font-size: 1.3em; 
            opacity: 0.7;
            transition: opacity 0.3s;
        }
        .close-btn:hover { opacity: 1; }
        .upload-form { display: flex; flex-direction: column; gap: 10px; }
        .custom-file-input { display: none; }
        .file-label { display: flex; align-items: center; gap: 8px; background: #f8f9fa; border: 1px solid #ddd; border-radius: 6px; padding: 8px 12px; cursor: pointer; transition: border 0.2s; }
        .file-label:hover { border: 1.5px solid var(--primary-color); }
        .upload-btn { margin-top: 5px; }
        .image-card { position: relative; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.07); background: #fff; }
        .image-card img { width: 100%; height: 150px; object-fit: cover; display: block; }
        .image-overlay { position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.45); opacity: 0; display: flex; align-items: center; justify-content: center; gap: 10px; transition: opacity 0.2s; }
        .image-card:hover .image-overlay { opacity: 1; }
        .overlay-btn { background: #fff; border: none; border-radius: 50%; width: 36px; height: 36px; display: flex; align-items: center; justify-content: center; font-size: 1.2em; cursor: pointer; box-shadow: 0 2px 4px rgba(0,0,0,0.12); transition: background 0.2s; }
        .overlay-btn:hover { background: var(--primary-color); color: #fff; }
        .image-title-tooltip { position: absolute; bottom: 8px; left: 8px; background: rgba(0,0,0,0.7); color: #fff; padding: 2px 8px; border-radius: 4px; font-size: 0.95em; pointer-events: none; opacity: 0; transition: opacity 0.2s; }
        .image-card:hover .image-title-tooltip { opacity: 1; }
        @media (min-width: 900px) {
            .content-container { display: flex; gap: 30px; }
            .section-box { flex: 1; min-width: 350px; }
        }
        
        /* Image Compression Feedback Styles */
        .file-info {
            background: linear-gradient(135deg, #e3f2fd 0%, #f3e5f5 100%);
            border: 1px solid #90caf9;
            border-radius: 8px;
            padding: 12px;
            margin-top: 10px;
            font-size: 0.9rem;
            color: #1565c0;
            display: flex;
            align-items: center;
            gap: 8px;
            opacity: 0;
            transform: translateY(-10px);
            transition: all 0.3s ease-in-out;
        }
        
        .file-info.show {
            opacity: 1;
            transform: translateY(0);
        }
        
        .file-info .material-icons {
            font-size: 1.2rem;
            color: #1976d2;
        }
        
        .processing-images {
            background: linear-gradient(135deg, #fff3e0 0%, #ffe0b2 100%);
            border: 1px solid #ffb74d;
            border-radius: 8px;
            padding: 12px;
            margin-top: 10px;
            color: #e65100;
            display: flex;
            align-items: center;
            gap: 8px;
            opacity: 0;
            transform: translateY(-10px);
            transition: all 0.3s ease-in-out;
        }
        
        .processing-images.show {
            opacity: 1;
            transform: translateY(0);
        }
        
        .processing-images .material-icons {
            font-size: 1.2rem;
            color: #ff9800;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.6; }
        }
        
        .compression-progress {
            animation: pulse 1.5s ease-in-out infinite;
        }
        
        /* Dynamic Categories Styles */
        .controls-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 25px;
        }
        
        .action-buttons {
            display: flex;
            gap: 12px;
            margin-top: 15px;
        }
        
        .action-btn.primary {
            background: linear-gradient(135deg, #4caf50, #45a049);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }
        
        .action-btn.primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(76, 175, 80, 0.3);
        }
        
        .action-btn.secondary {
            background: linear-gradient(135deg, #2196f3, #1976d2);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }
        
        .action-btn.secondary:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(33, 150, 243, 0.3);
        }
        
        .category-card .item-actions .refresh-btn {
            background: #ff9800;
        }
        
        .category-card .item-actions .refresh-btn:hover {
            background: #f57c00;
        }
        
        .subcategory-badge {
            display: inline-block;
            background: #e3f2fd;
            color: #1976d2;
            font-size: 0.75rem;
            padding: 2px 8px;
            border-radius: 12px;
            margin-top: 5px;
        }
        
        .rotating {
            animation: spin 1s linear infinite;
        }
        
        .loading-state, .no-data-state {
            background: #f8f9fa;
            border-radius: 12px;
            margin: 20px 0;
        }
        
        .no-data-state p {
            margin: 10px 0 0 0;
            font-size: 1.1rem;
        }
        
        .category-card {
            position: relative;
        }
        
        .category-card .item-image {
            position: relative;
            overflow: hidden;
        }
        
        .refresh-image-btn {
            background: rgba(255, 152, 0, 0.9) !important;
        }
        
        .refresh-image-btn:hover {
            background: rgba(245, 124, 0, 1) !important;
        }
        
        /* New Arrivals Styles */
        .new-arrival-item {
            border: 2px solid transparent;
            transition: border-color 0.3s ease;
        }
        
        .new-arrival-item:hover {
            border-color: #4caf50;
        }
        
        .new-indicator {
            position: absolute;
            top: 8px;
            right: 8px;
            background: #ff4444;
            color: white;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .item-category {
            margin: 2px 0;
            font-size: 0.8rem;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .item-price {
            margin: 5px 0;
            font-size: 1rem;
            font-weight: 600;
            color: #2e7d32;
        }
        
        .view-btn {
            background: #2196f3;
        }
        
        .view-btn:hover {
            background: #1976d2;
        }
        
        .info-grid {
            margin: 20px 0;
        }
        
        .info-card h4 {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        /* Preorder Items Styles */
        .preorder-item {
            border: 2px solid transparent;
            transition: border-color 0.3s ease;
        }
        
        .preorder-item:hover {
            border-color: #007bff;
        }
        
        .item-badges {
            position: absolute;
            top: 8px;
            right: 8px;
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        
        .badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
            color: white;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
        }
        
        .badge-new {
            background: linear-gradient(135deg, #ff4444, #cc0000);
        }
        
        .badge-popular {
            background: linear-gradient(135deg, #ff9800, #f57c00);
        }
        
        .item-code {
            margin: 2px 0;
            font-size: 0.8rem;
            color: #666;
            font-family: 'Courier New', monospace;
            background: #f8f9fa;
            padding: 2px 6px;
            border-radius: 4px;
        }
        
        .item-requests {
            margin: 5px 0;
            font-size: 0.85rem;
            color: #28a745;
            font-weight: 600;
        }
    </style>
</head>

<body>
    <div class="container">
        <?php include '../PAMO_PAGES/includes/sidebar.php'; ?>
        <main class="main-content">
            <header>
                <h1>Content Management</h1>
            </header>
            <?php echo $feedback; ?>
            <!-- Navigation Tabs -->
            <div class="content-tabs">
                <button class="tab-btn active" data-tab="categories">
                    <i class="material-icons">category</i>
                    <span>Item Categories</span>
                </button>
                <button class="tab-btn" data-tab="carousel">
                    <i class="material-icons">new_releases</i>
                    <span>New Arrivals</span>
                </button>
                <button class="tab-btn" data-tab="preorder">
                    <i class="material-icons">shopping_cart</i>
                    <span>Pre-Order Items</span>
                </button>
            </div>

            <div class="content-container">
                <!-- Item Categories Tab -->
                <div class="tab-content active" id="categories-tab">
                    <div class="content-info">
                        <h2><i class="material-icons">category</i> Dynamic Item Categories</h2>
                        <p class="description">Categories are automatically generated from your database. Each category displays a random product image from its inventory.</p>
                    </div>
                    
                    <div class="controls-section">
                        <div style="background: #e8f5e8; padding: 12px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #4caf50;">
                            <p style="margin: 0; color: #2e7d32; font-size: 0.9rem;">
                                <i class="material-icons" style="font-size: 16px; vertical-align: middle;">auto_awesome</i>
                                <strong>Auto-Generated:</strong> Categories are pulled from your database. Images are randomly selected from available products in each category.
                            </p>
                        </div>
                        
                        <div class="action-buttons">
                            <button id="refreshAllCategories" class="action-btn primary">
                                <i class="material-icons">refresh</i>
                                Refresh All Images
                            </button>
                            <button id="loadCategories" class="action-btn secondary">
                                <i class="material-icons">download</i>
                                Reload Categories
                            </button>
                        </div>
                    </div>

                    <div class="current-items-section">
                        <h3><i class="material-icons">view_module</i> Available Categories</h3>
                        <div class="loading-state" id="categoriesLoading" style="text-align: center; padding: 40px;">
                            <i class="material-icons rotating" style="font-size: 48px; color: #666;">hourglass_empty</i>
                            <p>Loading categories...</p>
                        </div>
                        <div class="items-grid" id="categoriesGrid" style="display: none;">
                            <!-- Categories will be loaded dynamically -->
                        </div>
                        <div class="no-data-state" id="noCategoriesMessage" style="display: none; text-align: center; padding: 40px; color: #666;">
                            <i class="material-icons" style="font-size: 48px; margin-bottom: 10px;">category</i>
                            <p>No categories found. Please add categories in the Settings page first.</p>
                        </div>
                            ?>
                        </div>
                    </div>
                </div>
                <!-- Homepage Carousel Tab -->
                <div class="tab-content" id="carousel-tab">
                    <div class="content-info">
                        <h2><i class="material-icons">new_releases</i> New Arrivals Showcase</h2>
                        <p class="description">This section automatically displays the newest items added to your inventory. No manual management required!</p>
                    </div>
                    
                    <div class="controls-section">
                        <div style="background: #e8f5e8; padding: 12px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #4caf50;">
                            <p style="margin: 0; color: #2e7d32; font-size: 0.9rem;">
                                <i class="material-icons" style="font-size: 16px; vertical-align: middle;">auto_awesome</i>
                                <strong>Automatic Display:</strong> The homepage "New Arrivals" section shows the 8 most recently added inventory items with their product images.
                            </p>
                        </div>
                        
                        <div class="info-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px; margin-bottom: 20px;">
                            <div class="info-card" style="background: #f8f9fa; padding: 15px; border-radius: 8px; border-left: 3px solid #007bff;">
                                <h4 style="margin: 0 0 8px 0; color: #007bff;"><i class="material-icons" style="font-size: 20px; vertical-align: middle;">inventory</i> How it Works</h4>
                                <p style="margin: 0; font-size: 0.9rem;">When you add new items to inventory, they automatically appear in the homepage carousel.</p>
                            </div>
                            <div class="info-card" style="background: #f8f9fa; padding: 15px; border-radius: 8px; border-left: 3px solid #28a745;">
                                <h4 style="margin: 0 0 8px 0; color: #28a745;"><i class="material-icons" style="font-size: 20px; vertical-align: middle;">update</i> Real-time Updates</h4>
                                <p style="margin: 0; font-size: 0.9rem;">No manual updates needed. The display refreshes automatically with new inventory.</p>
                            </div>
                            <div class="info-card" style="background: #f8f9fa; padding: 15px; border-radius: 8px; border-left: 3px solid #ffc107;">
                                <h4 style="margin: 0 0 8px 0; color: #e67e22;"><i class="material-icons" style="font-size: 20px; vertical-align: middle;">image</i> Smart Images</h4>
                                <p style="margin: 0; font-size: 0.9rem;">Uses product images from inventory with automatic fallbacks to default images.</p>
                            </div>
                        </div>
                        <div class="action-buttons">
                            <button id="refreshNewArrivals" class="action-btn primary">
                                <i class="material-icons">refresh</i>
                                Refresh Preview
                            </button>
                            <button id="viewHomepage" class="action-btn secondary" onclick="window.open('../Pages/home.php', '_blank')">
                                <i class="material-icons">open_in_new</i>
                                View Homepage
                            </button>
                        </div>
                    </div>

                    <div class="current-items-section">
                        <h3><i class="material-icons">new_releases</i> Currently Displaying New Arrivals</h3>
                        <div class="loading-state" id="newArrivalsLoading" style="text-align: center; padding: 40px; display: none;">
                            <i class="material-icons rotating" style="font-size: 48px; color: #666;">hourglass_empty</i>
                            <p>Loading new arrivals...</p>
                        </div>
                        <div class="items-grid" id="newArrivalsGrid">
                            <?php
                            // Show current new arrivals
                            $newItemsQuery = "
                                SELECT DISTINCT
                                    i.item_name,
                                    i.image_path,
                                    i.price,
                                    i.category,
                                    i.created_at,
                                    SUBSTRING_INDEX(i.item_code, '-', 1) as base_code
                                FROM inventory i
                                WHERE i.status = 'in stock'
                                AND i.image_path IS NOT NULL 
                                AND i.image_path != ''
                                AND i.actual_quantity > 0
                                GROUP BY SUBSTRING_INDEX(i.item_code, '-', 1)
                                ORDER BY i.created_at DESC, i.id DESC
                                LIMIT 8
                            ";
                            
                            $newItemsResult = $conn->query($newItemsQuery);
                            $hasItems = false;
                            
                            while ($row = $newItemsResult->fetch(PDO::FETCH_ASSOC)) {
                                $hasItems = true;
                                $imagePath = $row['image_path'];
                                
                                // Process image path
                                if (!empty($imagePath)) {
                                    if (strpos($imagePath, 'uploads/') === 0) {
                                        $resolvedPath = $imagePath;
                                    } else if (strpos($imagePath, 'uploads/') === false) {
                                        $resolvedPath = 'uploads/itemlist/' . $imagePath;
                                    } else {
                                        $resolvedPath = $imagePath;
                                    }
                                    
                                    if (!file_exists('../' . $resolvedPath)) {
                                        $altPath = 'uploads/itemlist/' . basename($imagePath);
                                        if (file_exists('../' . $altPath)) {
                                            $resolvedPath = $altPath;
                                        } else {
                                            $resolvedPath = 'uploads/itemlist/default.png';
                                        }
                                    }
                                } else {
                                    $resolvedPath = 'uploads/itemlist/default.png';
                                }
                                
                                $isNew = (strtotime($row['created_at']) > strtotime('-7 days'));
                                
                                echo '<div class="item-card new-arrival-item">';
                                echo '  <div class="item-image">';
                                echo '    <img src="../' . htmlspecialchars($resolvedPath) . '" alt="' . htmlspecialchars($row['item_name']) . '">';
                                if ($isNew) {
                                    echo '    <span class="new-indicator">New</span>';
                                }
                                echo '  </div>';
                                echo '  <div class="item-info">';
                                echo '    <h4>' . htmlspecialchars($row['item_name']) . '</h4>';
                                echo '    <p class="item-category">' . htmlspecialchars($row['category']) . '</p>';
                                echo '    <p class="item-price">₱' . number_format($row['price'], 2) . '</p>';
                                echo '    <p class="item-date">Added: ' . date('M d, Y', strtotime($row['created_at'])) . '</p>';
                                echo '  </div>';
                                echo '  <div class="item-actions">';
                                echo '    <button class="action-btn view-btn" title="View in Inventory" onclick="window.open(\'inventory.php\', \'_blank\')">';
                                echo '      <i class="material-icons">inventory</i>';
                                echo '    </button>';
                                echo '  </div>';
                                echo '</div>';
                            }
                            
                            if (!$hasItems) {
                                echo '<div class="no-data-state" style="grid-column: 1 / -1; text-align: center; padding: 40px; color: #666;">';
                                echo '  <i class="material-icons" style="font-size: 48px; margin-bottom: 10px;">inventory_2</i>';
                                echo '  <p>No inventory items found</p>';
                                echo '  <p style="font-size: 0.9rem; margin: 5px 0 0 0;">Add items to inventory to see them here</p>';
                                echo '</div>';
                            }
                            ?>
                        </div>
                    </div>
                </div>

                <!-- Pre-Order Items Tab -->
                <div class="tab-content" id="preorder-tab">
                    <div class="content-info">
                        <h2><i class="material-icons">shopping_cart</i> Dynamic Pre-Order System</h2>
                        <p class="description">Pre-order items are automatically managed through the PAMO Pre-Order system. Items appear on homepage when set as available.</p>
                    </div>
                    
                    <div class="controls-section">
                        <div style="background: #e8f5e8; padding: 12px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #4caf50;">
                            <p style="margin: 0; color: #2e7d32; font-size: 0.9rem;">
                                <i class="material-icons" style="font-size: 16px; vertical-align: middle;">auto_awesome</i>
                                <strong>Automatic Display:</strong> Pre-order items are managed through the PAMO Pre-Order system and automatically appear on homepage when available.
                            </p>
                        </div>
                        
                        <div class="info-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px; margin-bottom: 20px;">
                            <div class="info-card" style="background: #f8f9fa; padding: 15px; border-radius: 8px; border-left: 3px solid #007bff;">
                                <h4 style="margin: 0 0 8px 0; color: #007bff;"><i class="material-icons" style="font-size: 20px; vertical-align: middle;">add_shopping_cart</i> How to Add Items</h4>
                                <p style="margin: 0; font-size: 0.9rem;">Use the PAMO Pre-Order system to add new items. Items with status 'pending' will appear on homepage.</p>
                            </div>
                            <div class="info-card" style="background: #f8f9fa; padding: 15px; border-radius: 8px; border-left: 3px solid #28a745;">
                                <h4 style="margin: 0 0 8px 0; color: #28a745;"><i class="material-icons" style="font-size: 20px; vertical-align: middle;">visibility</i> Section Visibility</h4>
                                <p style="margin: 0; font-size: 0.9rem;">The pre-order section only appears on homepage when there are available items to request.</p>
                            </div>
                            <div class="info-card" style="background: #f8f9fa; padding: 15px; border-radius: 8px; border-left: 3px solid =707">
                                <h4 style="margin: 0 0 8px 0; color: #e67e22;"><i class="material-icons" style="font-size: 20px; vertical-align: middle;">trending_up</i> Smart Features</h4>
                                <p style="margin: 0; font-size: 0.9rem;">Items show popularity badges based on request count and "New" badges for recently added items.</p>
                            </div>
                        </div>
                        <div class="action-buttons">
                            <button id="refreshPreorderItems" class="action-btn primary">
                                <i class="material-icons">refresh</i>
                                Refresh Preview
                            </button>
                            <button id="managePreorders" class="action-btn secondary" onclick="window.open('pamo_preorder.php', '_blank')">
                                <i class="material-icons">settings</i>
                                Manage Pre-Orders
                            </button>
                            <button id="viewHomepage2" class="action-btn secondary" onclick="window.open('../Pages/home.php', '_blank')">
                                <i class="material-icons">open_in_new</i>
                                View Homepage
                            </button>
                        </div>
                    </div>

                    <div class="current-items-section">
                        <h3><i class="material-icons">inventory</i> Available Pre-Order Items on Homepage</h3>
                        <div class="loading-state" id="preorderLoading" style="text-align: center; padding: 40px; display: none;">
                            <i class="material-icons rotating" style="font-size: 48px; color: #666;">hourglass_empty</i>
                            <p>Loading pre-order items...</p>
                        </div>
                        <div class="items-grid" id="preorderItemsGrid">
                            <?php
                            // Show current preorder items that appear on homepage
                            $preorderQuery = "
                                SELECT 
                                    pi.id,
                                    pi.base_item_code,
                                    pi.item_name,
                                    pi.price,
                                    pi.image_path,
                                    pi.status,
                                    pi.created_at,
                                    c.name as category_name,
                                    COALESCE(SUM(CASE WHEN pr.status = 'active' THEN pr.quantity ELSE 0 END), 0) AS total_requests
                                FROM preorder_items pi
                                LEFT JOIN categories c ON c.id = pi.category_id
                                LEFT JOIN preorder_requests pr ON pr.preorder_item_id = pi.id
                                WHERE pi.status = 'pending'
                                GROUP BY pi.id, pi.base_item_code, pi.item_name, pi.price, pi.image_path, pi.status, pi.created_at, c.name
                                ORDER BY pi.created_at DESC
                                LIMIT 8
                            ";
                            
                            $preorderResult = $conn->query($preorderQuery);
                            $hasPreorderItems = false;
                            
                            while ($row = $preorderResult->fetch(PDO::FETCH_ASSOC)) {
                                $hasPreorderItems = true;
                                $imagePath = $row['image_path'];
                                
                                // Process image path
                                if (!empty($imagePath)) {
                                    if (strpos($imagePath, 'uploads/') === 0) {
                                        $resolvedPath = $imagePath;
                                    } else if (strpos($imagePath, 'uploads/preorder/') === false && strpos($imagePath, 'uploads/') === false) {
                                        $resolvedPath = 'uploads/preorder/' . $imagePath;
                                    } else {
                                        $resolvedPath = $imagePath;
                                    }
                                    
                                    if (!file_exists('../' . $resolvedPath)) {
                                        $altPaths = [
                                            'uploads/preorder/' . basename($imagePath),
                                            'uploads/itemlist/' . basename($imagePath)
                                        ];
                                        
                                        $found = false;
                                        foreach ($altPaths as $altPath) {
                                            if (file_exists('../' . $altPath)) {
                                                $resolvedPath = $altPath;
                                                $found = true;
                                                break;
                                            }
                                        }
                                        
                                        if (!$found) {
                                            $resolvedPath = 'uploads/itemlist/default.png';
                                        }
                                    }
                                } else {
                                    $resolvedPath = 'uploads/itemlist/default.png';
                                }
                                
                                $isNew = (strtotime($row['created_at']) > strtotime('-14 days'));
                                $isPopular = intval($row['total_requests']) > 5;
                                
                                echo '<div class="item-card preorder-item">';
                                echo '  <div class="item-image">';
                                echo '    <img src="../' . htmlspecialchars($resolvedPath) . '" alt="' . htmlspecialchars($row['item_name']) . '">';
                                
                                if ($isNew || $isPopular) {
                                    echo '    <div class="item-badges">';
                                    if ($isNew) {
                                        echo '      <span class="badge badge-new">New</span>';
                                    }
                                    if ($isPopular) {
                                        echo '      <span class="badge badge-popular">Popular</span>';
                                    }
                                    echo '    </div>';
                                }
                                
                                echo '  </div>';
                                echo '  <div class="item-info">';
                                echo '    <h4>' . htmlspecialchars($row['item_name']) . '</h4>';
                                echo '    <p class="item-category">' . htmlspecialchars($row['category_name'] ?: 'Uncategorized') . '</p>';
                                echo '    <p class="item-code">Code: ' . htmlspecialchars($row['base_item_code']) . '</p>';
                                echo '    <p class="item-price">₱' . number_format($row['price'], 2) . '</p>';
                                echo '    <p class="item-requests">' . intval($row['total_requests']) . ' requests</p>';
                                echo '    <p class="item-date">Added: ' . date('M d, Y', strtotime($row['created_at'])) . '</p>';
                                echo '  </div>';
                                echo '  <div class="item-actions">';
                                echo '    <button class="action-btn view-btn" title="Manage in Pre-Orders" onclick="window.open(\'pamo_preorder.php\', \'_blank\')">';
                                echo '      <i class="material-icons">settings</i>';
                                echo '    </button>';
                                echo '  </div>';
                                echo '</div>';
                            }
                            
                            if (!$hasPreorderItems) {
                                echo '<div class="no-data-state" style="grid-column: 1 / -1; text-align: center; padding: 40px; color: #666;">';
                                echo '  <i class="material-icons" style="font-size: 48px; margin-bottom: 10px;">shopping_cart_checkout</i>';
                                echo '  <p>No pre-order items available</p>';
                                echo '  <p style="font-size: 0.9rem; margin: 5px 0 0 0;">Add items in the PAMO Pre-Order system to see them here</p>';
                                echo '  <button onclick="window.open(\'pamo_preorder.php\', \'_blank\')" style="margin-top: 15px; padding: 10px 20px; background: #007bff; color: white; border: none; border-radius: 6px; cursor: pointer;">Go to Pre-Order System</button>';
                                echo '</div>';
                            }
                            ?>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Edit Image Modal -->
            <div id="editImageModal" class="modal" style="display:none; position:fixed; top:0; left:0; width:100vw; height:100vh; background:rgba(0,0,0,0.4); z-index:9999; align-items:center; justify-content:center;">
                <div style="background:#fff; border-radius:10px; max-width:400px; margin:60px auto; padding:30px 20px; position:relative; box-shadow:0 4px 24px rgba(0,0,0,0.18);">
                    <button id="closeEditModalBtn" style="position:absolute; top:10px; right:10px; background:none; border:none; font-size:1.5em; cursor:pointer;">&times;</button>
                    <h2>Edit Image</h2>
                    <form id="editImageForm">
                        <input type="hidden" name="id" id="editImageId">
                        <div style="margin-bottom:12px;">
                            <label>Title:</label>
                            <input type="text" name="title" id="editImageTitle" required style="width:100%; padding:6px 10px; border-radius:5px; border:1px solid #ccc;">
                        </div>
                        <div style="margin-bottom:12px;">
                            <label>Current Image:</label><br>
                            <img id="editImagePreview" src="" alt="Preview" style="width:100%; max-height:180px; object-fit:contain; border-radius:6px; margin:8px 0;">
                        </div>
                        <div style="margin-bottom:18px;">
                            <label>Change Image:</label>
                            <input type="file" name="image" accept="image/*">
                        </div>
                        <button type="submit" style="background:#0072bc; color:#fff; border:none; border-radius:5px; padding:8px 18px; cursor:pointer;">Save Changes</button>
                    </form>
                </div>
            </div>
            <script src="../Javascript/logout-modal.js"></script>
            <script src="../PAMO JS/content-edit.js"></script>
            
            <script>
                // File size validation (5MB = 5 * 1024 * 1024 bytes)
                const MAX_FILE_SIZE = 5 * 1024 * 1024;
                
                // Image compression function (same as inventory system)
                function compressImage(file, maxWidth = 1200, quality = 0.8) {
                    return new Promise((resolve) => {
                        const canvas = document.createElement("canvas");
                        const ctx = canvas.getContext("2d");
                        const img = new Image();

                        img.onload = function () {
                            // Calculate new dimensions
                            let { width, height } = img;

                            if (width > maxWidth) {
                                height = (height * maxWidth) / width;
                                width = maxWidth;
                            }

                            canvas.width = width;
                            canvas.height = height;

                            // Draw and compress
                            ctx.drawImage(img, 0, 0, width, height);

                            canvas.toBlob(
                                (blob) => {
                                    resolve(blob);
                                },
                                "image/jpeg",
                                quality
                            );
                        };

                        img.src = URL.createObjectURL(file);
                    });
                }
                
                function validateAndPreviewFile(input, previewId, imgId) {
                    const file = input.files[0];
                    const fileInfoId = input.id + 'FileInfo';
                    const fileInfo = document.getElementById(fileInfoId);
                    
                    if (file) {
                        // Always show file info first
                        const fileText = input.parentElement.querySelector('.file-text');
                        if (fileText) {
                            fileText.textContent = file.name;
                        }
                        
                        // Show file size info with color coding
                        if (fileInfo) {
                            const sizeInMB = (file.size / (1024 * 1024)).toFixed(2);
                            const fileSizeSpan = fileInfo.querySelector('.file-size');
                            fileSizeSpan.textContent = sizeInMB + ' MB';
                            
                            // Color code based on size
                            fileInfo.className = 'file-info';
                            if (file.size > 2 * 1024 * 1024) { // > 2MB
                                fileInfo.className += ' large-file';
                                fileSizeSpan.textContent += ' (Will be compressed)';
                            } else if (file.size < 1024 * 1024) { // < 1MB
                                fileInfo.className += ' good-size';
                            }
                            
                            fileInfo.style.display = 'block';
                        }
                        
                        // Show preview
                        const preview = document.getElementById(previewId);
                        const previewImg = document.getElementById(imgId);
                        
                        if (preview && previewImg) {
                            const reader = new FileReader();
                            reader.onload = function(e) {
                                previewImg.src = e.target.result;
                                preview.style.display = 'block';
                            };
                            reader.readAsDataURL(file);
                        }
                    }
                }
                
                // Enhanced form submission with image compression
                async function validateFileSize(form) {
                    const fileInput = form.querySelector('input[type="file"]');
                    if (fileInput && fileInput.files.length > 0) {
                        const file = fileInput.files[0];
                        const fileInfoId = fileInput.id + 'FileInfo';
                        const fileInfo = document.getElementById(fileInfoId);
                        
                        try {
                            // Show compression status
                            if (fileInfo) {
                                fileInfo.innerHTML = '<span class="processing-images">Processing image...</span>';
                                fileInfo.style.display = 'block';
                            }
                            
                            let processedFile = file;
                            
                            // Compress if file is larger than 1MB
                            if (file.size > 1024 * 1024) {
                                if (fileInfo) {
                                    fileInfo.innerHTML = '<span class="processing-images">Compressing image, please wait...</span>';
                                }
                                
                                processedFile = await compressImage(file);
                                
                                // Update the file input with compressed file
                                const dataTransfer = new DataTransfer();
                                const compressedFile = new File([processedFile], file.name, {
                                    type: 'image/jpeg',
                                    lastModified: Date.now()
                                });
                                dataTransfer.items.add(compressedFile);
                                fileInput.files = dataTransfer.files;
                                
                                if (fileInfo) {
                                    const sizeInMB = (processedFile.size / (1024 * 1024)).toFixed(2);
                                    fileInfo.innerHTML = `<span class="good-size">Compressed to ${sizeInMB} MB ✓</span>`;
                                }
                            } else if (fileInfo) {
                                const sizeInMB = (file.size / (1024 * 1024)).toFixed(2);
                                fileInfo.innerHTML = `<span class="good-size">File size: ${sizeInMB} MB ✓</span>`;
                            }
                            
                            // Final size check
                            if (processedFile.size > MAX_FILE_SIZE) {
                                alert('Even after compression, the file is still too large. Please choose a smaller image.');
                                return false;
                            }
                            
                            return true;
                            
                        } catch (error) {
                            console.error('Error processing image:', error);
                            alert('Error processing image: ' + error.message);
                            return false;
                        }
                    }
                    return true;
                }
                
                function clearPreview(type) {
                    const preview = document.getElementById(type + 'Preview');
                    const input = document.getElementById(type + 'Image');
                    const fileText = input.parentElement.querySelector('.file-text');
                    const fileInfo = document.getElementById(type + 'Image' + 'FileInfo');
                    
                    if (preview) preview.style.display = 'none';
                    if (input) input.value = '';
                    if (fileText) fileText.textContent = 'Choose image file (Max: 5MB)';
                    if (fileInfo) fileInfo.style.display = 'none';
                }
                
                // Enhanced form submission handler
                async function handleFormSubmission(event, form) {
                    event.preventDefault();
                    
                    // Validate and compress file
                    const isValid = await validateFileSize(form);
                    if (!isValid) {
                        return false;
                    }
                    
                    // Show loading state
                    const submitBtn = form.querySelector('.submit-btn');
                    const originalBtnText = submitBtn.innerHTML;
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<i class="material-icons">hourglass_empty</i> Uploading...';
                    
                    try {
                        // Create FormData and submit
                        const formData = new FormData(form);
                        
                        const response = await fetch('../PAMO BACKEND CONTENT EDIT/upload-content-image.php', {
                            method: 'POST',
                            body: formData
                        });
                        
                        if (response.ok) {
                            // Success - reload page to show updated content
                            window.location.href = 'content-edit.php?success=1';
                        } else if (response.status === 413) {
                            throw new Error('File is still too large. Please try a smaller image.');
                        } else {
                            const text = await response.text();
                            throw new Error('Upload failed: ' + text);
                        }
                        
                    } catch (error) {
                        console.error('Upload error:', error);
                        alert('Upload failed: ' + error.message);
                        
                        // Restore button
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = originalBtnText;
                    }
                    
                    return false; // Prevent default form submission
                }
                
                // Format file size
                function formatFileSize(bytes) {
                    if (bytes === 0) return '0 Bytes';
                    const k = 1024;
                    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
                    const i = Math.floor(Math.log(bytes) / Math.log(k));
                    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
                }

                // Dynamic Categories Management
                let categoriesData = [];
                
                async function loadCategories() {
                    const loadingState = document.getElementById('categoriesLoading');
                    const grid = document.getElementById('categoriesGrid');
                    const noDataState = document.getElementById('noCategoriesMessage');
                    
                    try {
                        loadingState.style.display = 'block';
                        grid.style.display = 'none';
                        noDataState.style.display = 'none';
                        
                        const response = await fetch('../PAMO BACKEND CONTENT EDIT/get-categories-with-images.php');
                        const data = await response.json();
                        
                        if (data.success && data.categories && data.categories.length > 0) {
                            categoriesData = data.categories;
                            displayCategories(data.categories);
                        } else {
                            noDataState.style.display = 'block';
                        }
                    } catch (error) {
                        console.error('Error loading categories:', error);
                        noDataState.style.display = 'block';
                        noDataState.innerHTML = `
                            <i class="material-icons" style="font-size: 48px; margin-bottom: 10px;">error</i>
                            <p>Error loading categories: ${error.message}</p>
                        `;
                    } finally {
                        loadingState.style.display = 'none';
                    }
                }
                
                function displayCategories(categories) {
                    const grid = document.getElementById('categoriesGrid');
                    
                    grid.innerHTML = categories.map(category => `
                        <div class="item-card category-card" data-category-id="${category.id}">
                            <div class="item-image">
                                <img src="../${category.image_path}" alt="${category.name}" 
                                     onerror="this.src='../uploads/itemlist/default.png'">
                                <div class="image-overlay">
                                    <button class="overlay-btn refresh-image-btn" 
                                            title="Get Different Image" 
                                            data-category-id="${category.id}">
                                        <i class="material-icons">refresh</i>
                                    </button>
                                </div>
                            </div>
                            <div class="item-info">
                                <h4>${category.name}</h4>
                                <p class="item-date">Sample: ${category.sample_item}</p>
                                ${category.has_subcategories ? '<span class="subcategory-badge">Has Subcategories</span>' : ''}
                            </div>
                            <div class="item-actions">
                                <button class="action-btn edit-btn" title="Edit in Settings" onclick="window.open('settings.php', '_blank')">
                                    <i class="material-icons">settings</i>
                                </button>
                                <button class="action-btn refresh-btn" title="Refresh Image" 
                                        data-category-id="${category.id}">
                                    <i class="material-icons">image</i>
                                </button>
                            </div>
                        </div>
                    `).join('');
                    
                    grid.style.display = 'grid';
                    
                    // Add event listeners for refresh buttons
                    grid.querySelectorAll('.refresh-btn, .refresh-image-btn').forEach(btn => {
                        btn.addEventListener('click', async (e) => {
                            e.preventDefault();
                            const categoryId = btn.getAttribute('data-category-id');
                            await refreshCategoryImage(categoryId);
                        });
                    });
                }
                
                async function refreshCategoryImage(categoryId) {
                    try {
                        const categoryCard = document.querySelector(`[data-category-id="${categoryId}"]`);
                        const img = categoryCard.querySelector('img');
                        const sampleText = categoryCard.querySelector('.item-date');
                        
                        // Show loading state
                        img.style.opacity = '0.5';
                        sampleText.textContent = 'Refreshing...';
                        
                        const response = await fetch('../PAMO BACKEND CONTENT EDIT/refresh-category-image.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json'
                            },
                            body: JSON.stringify({ category_id: parseInt(categoryId) })
                        });
                        
                        const data = await response.json();
                        
                        if (data.success) {
                            // Update the image and sample text
                            img.src = '../' + data.image_path + '?t=' + Date.now(); // Add timestamp to prevent caching
                            img.style.opacity = '1';
                            sampleText.textContent = 'Sample: ' + data.sample_item;
                            
                            // Update the cached data
                            const categoryIndex = categoriesData.findIndex(cat => cat.id == categoryId);
                            if (categoryIndex !== -1) {
                                categoriesData[categoryIndex].image_path = data.image_path;
                                categoriesData[categoryIndex].sample_item = data.sample_item;
                            }
                        } else {
                            throw new Error(data.message || 'Failed to refresh image');
                        }
                        
                    } catch (error) {
                        console.error('Error refreshing image:', error);
                        alert('Failed to refresh image: ' + error.message);
                    }
                }
                
                async function refreshAllCategories() {
                    const refreshBtn = document.getElementById('refreshAllCategories');
                    const originalText = refreshBtn.innerHTML;
                    
                    try {
                        refreshBtn.disabled = true;
                        refreshBtn.innerHTML = '<i class="material-icons rotating">refresh</i> Refreshing...';
                        
                        // Reload all categories to get fresh random images
                        await loadCategories();
                        
                    } catch (error) {
                        console.error('Error refreshing all categories:', error);
                        alert('Error refreshing categories: ' + error.message);
                    } finally {
                        refreshBtn.disabled = false;
                        refreshBtn.innerHTML = originalText;
                    }
                }
                
                // Initialize categories when page loads and tab is selected
                document.addEventListener('DOMContentLoaded', function() {
                    // Load categories if we're on the categories tab
                    if (document.querySelector('#categories-tab.active')) {
                        loadCategories();
                    }
                    
                    // Set up event listeners
                    document.getElementById('loadCategories').addEventListener('click', loadCategories);
                    document.getElementById('refreshAllCategories').addEventListener('click', refreshAllCategories);
                    
                    // Load categories when categories tab is clicked
                    const categoryTabBtn = document.querySelector('[data-tab="categories"]');
                    if (categoryTabBtn) {
                        categoryTabBtn.addEventListener('click', function() {
                            setTimeout(() => {
                                if (categoriesData.length === 0) {
                                    loadCategories();
                                }
                            }, 100); // Small delay to ensure tab is active
                        });
                    }
                    
                    // New Arrivals refresh functionality
                    const refreshNewArrivalsBtn = document.getElementById('refreshNewArrivals');
                    if (refreshNewArrivalsBtn) {
                        refreshNewArrivalsBtn.addEventListener('click', refreshNewArrivalsPreview);
                    }
                    
                    // Pre-order refresh functionality
                    const refreshPreorderBtn = document.getElementById('refreshPreorderItems');
                    if (refreshPreorderBtn) {
                        refreshPreorderBtn.addEventListener('click', refreshPreorderItemsPreview);
                    }
                });
                
                // Refresh new arrivals preview
                async function refreshNewArrivalsPreview() {
                    const refreshBtn = document.getElementById('refreshNewArrivals');
                    const loadingState = document.getElementById('newArrivalsLoading');
                    const grid = document.getElementById('newArrivalsGrid');
                    const originalBtnText = refreshBtn.innerHTML;
                    
                    try {
                        refreshBtn.disabled = true;
                        refreshBtn.innerHTML = '<i class="material-icons rotating">refresh</i> Refreshing...';
                        
                        if (loadingState) {
                            loadingState.style.display = 'block';
                        }
                        if (grid) {
                            grid.style.display = 'none';
                        }
                        
                        // Fetch fresh data
                        const response = await fetch('../PAMO BACKEND CONTENT EDIT/get-newest-inventory-items.php?limit=8');
                        const data = await response.json();
                        
                        if (data.success && data.items) {
                            displayNewArrivals(data.items);
                        } else {
                            throw new Error(data.message || 'Failed to fetch new arrivals');
                        }
                        
                    } catch (error) {
                        console.error('Error refreshing new arrivals:', error);
                        alert('Error refreshing preview: ' + error.message);
                    } finally {
                        refreshBtn.disabled = false;
                        refreshBtn.innerHTML = originalBtnText;
                        
                        if (loadingState) {
                            loadingState.style.display = 'none';
                        }
                    }
                }
                
                // Display new arrivals in grid
                function displayNewArrivals(items) {
                    const grid = document.getElementById('newArrivalsGrid');
                    
                    if (items.length === 0) {
                        grid.innerHTML = `
                            <div class="no-data-state" style="grid-column: 1 / -1; text-align: center; padding: 40px; color: #666;">
                                <i class="material-icons" style="font-size: 48px; margin-bottom: 10px;">inventory_2</i>
                                <p>No inventory items found</p>
                                <p style="font-size: 0.9rem; margin: 5px 0 0 0;">Add items to inventory to see them here</p>
                            </div>
                        `;
                    } else {
                        grid.innerHTML = items.map(item => {
                            const isNew = new Date(item.created_at) > new Date(Date.now() - 7 * 24 * 60 * 60 * 1000);
                            return `
                                <div class="item-card new-arrival-item">
                                    <div class="item-image">
                                        <img src="../${item.image_path}" alt="${item.item_name}" 
                                             onerror="this.src='../uploads/itemlist/default.png'">
                                        ${isNew ? '<span class="new-indicator">New</span>' : ''}
                                    </div>
                                    <div class="item-info">
                                        <h4>${item.item_name}</h4>
                                        <p class="item-category">${item.category}</p>
                                        <p class="item-price">₱${item.price.toFixed(2)}</p>
                                        <p class="item-date">Added: ${new Date(item.created_at).toLocaleDateString('en-US', { 
                                            year: 'numeric', month: 'short', day: 'numeric' 
                                        })}</p>
                                    </div>
                                    <div class="item-actions">
                                        <button class="action-btn view-btn" title="View in Inventory" onclick="window.open('inventory.php', '_blank')">
                                            <i class="material-icons">inventory</i>
                                        </button>
                                    </div>
                                </div>
                            `;
                        }).join('');
                    }
                    
                    grid.style.display = 'grid';
                }
                
                // Refresh preorder items preview
                async function refreshPreorderItemsPreview() {
                    const refreshBtn = document.getElementById('refreshPreorderItems');
                    const loadingState = document.getElementById('preorderLoading');
                    const grid = document.getElementById('preorderItemsGrid');
                    const originalBtnText = refreshBtn.innerHTML;
                    
                    try {
                        refreshBtn.disabled = true;
                        refreshBtn.innerHTML = '<i class="material-icons rotating">refresh</i> Refreshing...';
                        
                        if (loadingState) {
                            loadingState.style.display = 'block';
                        }
                        if (grid) {
                            grid.style.display = 'none';
                        }
                        
                        // Fetch fresh preorder data
                        const response = await fetch('../PAMO BACKEND CONTENT EDIT/get-preorder-items.php?limit=8');
                        const data = await response.json();
                        
                        if (data.success && data.items) {
                            displayPreorderItems(data.items);
                        } else {
                            throw new Error(data.message || 'Failed to fetch preorder items');
                        }
                        
                    } catch (error) {
                        console.error('Error refreshing preorder items:', error);
                        alert('Error refreshing preview: ' + error.message);
                    } finally {
                        refreshBtn.disabled = false;
                        refreshBtn.innerHTML = originalBtnText;
                        
                        if (loadingState) {
                            loadingState.style.display = 'none';
                        }
                    }
                }
                
                // Display preorder items in grid
                function displayPreorderItems(items) {
                    const grid = document.getElementById('preorderItemsGrid');
                    
                    if (items.length === 0) {
                        grid.innerHTML = `
                            <div class="no-data-state" style="grid-column: 1 / -1; text-align: center; padding: 40px; color: #666;">
                                <i class="material-icons" style="font-size: 48px; margin-bottom: 10px;">shopping_cart_checkout</i>
                                <p>No pre-order items available</p>
                                <p style="font-size: 0.9rem; margin: 5px 0 0 0;">Add items in the PAMO Pre-Order system to see them here</p>
                                <button onclick="window.open('pamo_preorder.php', '_blank')" style="margin-top: 15px; padding: 10px 20px; background: #007bff; color: white; border: none; border-radius: 6px; cursor: pointer;">Go to Pre-Order System</button>
                            </div>
                        `;
                    } else {
                        grid.innerHTML = items.map(item => {
                            const badges = [];
                            if (item.is_new) badges.push('<span class="badge badge-new">New</span>');
                            if (item.is_popular) badges.push('<span class="badge badge-popular">Popular</span>');
                            const badgeHtml = badges.length > 0 ? `<div class="item-badges">${badges.join('')}</div>` : '';
                            
                            return `
                                <div class="item-card preorder-item">
                                    <div class="item-image">
                                        <img src="../${item.image_path}" alt="${item.item_name}" 
                                             onerror="this.src='../uploads/itemlist/default.png'">
                                        ${badgeHtml}
                                    </div>
                                    <div class="item-info">
                                        <h4>${item.item_name}</h4>
                                        <p class="item-category">${item.category_name}</p>
                                        <p class="item-code">Code: ${item.base_item_code}</p>
                                        <p class="item-price">₱${item.price.toFixed(2)}</p>
                                        <p class="item-requests">${item.total_requests} requests</p>
                                        <p class="item-date">Added: ${new Date(item.created_at).toLocaleDateString('en-US', { 
                                            year: 'numeric', month: 'short', day: 'numeric' 
                                        })}</p>
                                    </div>
                                    <div class="item-actions">
                                        <button class="action-btn view-btn" title="Manage in Pre-Orders" onclick="window.open('pamo_preorder.php', '_blank')">
                                            <i class="material-icons">settings</i>
                                        </button>
                                    </div>
                                </div>
                            `;
                        }).join('');
                    }
                    
                    grid.style.display = 'grid';
                }
            </script>
        </main>
    </div>
</body>

</html>