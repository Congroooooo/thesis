<?php
session_start();
include '../Includes/connection.php';

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
    $feedback = '<div class="alert success" id="feedbackMsg">Image uploaded successfully.<span class="close-btn" onclick="this.parentElement.style.display=\'none\';">&times;</span></div>';
}
if (isset($_GET['error'])) {
    $feedback = '<div class="alert error" id="feedbackMsg">'.htmlspecialchars($_GET['error']).'<span class="close-btn" onclick="this.parentElement.style.display=\'none\';">&times;</span></div>';
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
        .alert { padding: 15px 20px; border-radius: 8px; margin-bottom: 20px; font-size: 1em; position: relative; }
        .alert.success { background: #e6f9ed; color: #217a3a; border: 1px solid #b6e2c6; }
        .alert.error { background: #ffeaea; color: #b30000; border: 1px solid #ffb3b3; }
        .close-btn { position: absolute; right: 15px; top: 10px; cursor: pointer; font-size: 1.2em; }
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
            <div class="content-container">

            <div class="section-box">
                    <div class="section-header">
                        <h2>Item Categories</h2>
                        <i class="material-icons">category</i>
                    </div>
                    <div class="section-content active">
                        <form class="upload-form" action="../PAMO BACKEND CONTENT EDIT/upload-content-image.php" method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="section" value="new_arrival">
                            <label>Image Title:
                                <input type="text" name="title" required class="input-text">
                            </label>
                            <label class="file-label">
                                <i class="material-icons">upload_file</i>
                                <span id="arrivalFileName">Choose image...</span>
                                <input type="file" name="image" accept="image/*" required class="custom-file-input" onchange="document.getElementById('arrivalFileName').textContent = this.files[0]?.name || 'Choose image...';">
                            </label>
                            <button type="submit" class="upload-btn">Upload to New Arrivals</button>
                        </form>
                        <h3>Current New Arrivals Images</h3>
                        <div class="image-grid">
                        <?php
                        $sql = "SELECT * FROM homepage_content WHERE section='new_arrival' ORDER BY created_at DESC";
                        $result = $conn->query($sql);
                        while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
                            echo '<div class="image-card">';
                            echo '<img src="../' . htmlspecialchars($row['image_path']) . '" alt="' . htmlspecialchars($row['title']) . '">';
                            echo '<div class="image-overlay">';
                            echo '<button class="overlay-btn" title="Edit" data-id="' . $row['id'] . '"><i class="material-icons">edit</i></button>';
                            echo '<button class="overlay-btn" title="Delete" data-id="' . $row['id'] . '"><i class="material-icons">delete</i></button>';
                            echo '</div>';
                            echo '<span class="image-title-tooltip">' . htmlspecialchars($row['title']) . '</span>';
                            echo '</div>';
                        }
                        ?>
                        </div>
                    </div>
                </div>
                <!-- Display Section Upload Form -->
                <div class="section-box">
                    <div class="section-header">
                        <h2>Carousel</h2>
                        <i class="material-icons">image</i>
                    </div>
                    <div class="section-content active">
                        <form class="upload-form" action="../PAMO BACKEND CONTENT EDIT/upload-content-image.php" method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="section" value="display">
                            <label>Image Title:
                                <input type="text" name="title" required class="input-text">
                            </label>
                            <label class="file-label">
                                <i class="material-icons">upload_file</i>
                                <span id="displayFileName">Choose image...</span>
                                <input type="file" name="image" accept="image/*" required class="custom-file-input" onchange="document.getElementById('displayFileName').textContent = this.files[0]?.name || 'Choose image...';">
                            </label>
                            <button type="submit" class="upload-btn">Upload to Display Section</button>
                        </form>
                        <h3>Current Display Images</h3>
                        <div class="image-grid">
                        <?php
                        $sql = "SELECT * FROM homepage_content WHERE section='display' ORDER BY created_at DESC";
                        $result = $conn->query($sql);
                        while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
                            echo '<div class="image-card">';
                            echo '<img src="../' . htmlspecialchars($row['image_path']) . '" alt="' . htmlspecialchars($row['title']) . '">';
                            echo '<div class="image-overlay">';
                            echo '<button class="overlay-btn" title="Edit" data-id="' . $row['id'] . '"><i class="material-icons">edit</i></button>';
                            echo '<button class="overlay-btn" title="Delete" data-id="' . $row['id'] . '"><i class="material-icons">delete</i></button>';
                            echo '</div>';
                            echo '<span class="image-title-tooltip">' . htmlspecialchars($row['title']) . '</span>';
                            echo '</div>';
                        }
                        ?>
                        </div>
                    </div>
                </div>

                

                <!-- Pre-Order Request Section Upload Form -->
                <div class="section-box">
                    <div class="section-header">
                        <h2>Items Available to Request for Pre-Order</h2>
                        <i class="material-icons">shopping_cart</i>
                    </div>
                    <div class="section-content active">
                        <form class="upload-form" action="../PAMO BACKEND CONTENT EDIT/upload-content-image.php" method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="section" value="pre_order">
                            <label>Category/Label:
                                <input type="text" name="category" class="input-text">
                            </label>
                            <label>Item Title:
                                <input type="text" name="title" required class="input-text">
                            </label>
                            <label>Price:
                                <input type="number" name="price" step="0.01" required class="input-text">
                            </label>
                            <label class="file-label">
                                <i class="material-icons">upload_file</i>
                                <span id="preOrderFileName">Choose image...</span>
                                <input type="file" name="image" accept="image/*" required class="custom-file-input" onchange="document.getElementById('preOrderFileName').textContent = this.files[0]?.name || 'Choose image...';">
                            </label>
                            <button type="submit" class="upload-btn">Upload Pre-Order Item</button>
                        </form>
                        <h3>Current Pre-Order Items</h3>
                        <div class="image-grid">
                        <?php
                        $sql = "SELECT * FROM homepage_content WHERE section='pre_order' ORDER BY created_at DESC";
                        $result = $conn->query($sql);
                        while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
                            $category = isset($row['category']) ? htmlspecialchars($row['category']) : '';
                            echo '<div class="image-card">';
                            echo '<img src="../' . htmlspecialchars($row['image_path']) . '" alt="' . htmlspecialchars($row['title']) . '">';
                            echo '<div class="image-overlay">';
                            echo '<button class="overlay-btn" title="Edit" data-id="' . $row['id'] . '"><i class="material-icons">edit</i></button>';
                            echo '<button class="overlay-btn" title="Delete" data-id="' . $row['id'] . '"><i class="material-icons">delete</i></button>';
                            echo '</div>';
                            echo '<span class="image-title-tooltip">' . htmlspecialchars($row['title']) . '<br><b>' . $category . '</b><br>₱' . number_format($row['price'], 2) . '</span>';
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
        </main>
    </div>
</body>

</html>