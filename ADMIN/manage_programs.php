<?php
require_once '../Includes/connection.php';
session_start();

// Handle adding new program/position
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    $name = trim($_POST['program_name']);
    $category = $_POST['category'];
    $abbreviation = trim($_POST['abbreviation']);
    $description = trim($_POST['description']);

    try {
        $stmt = $conn->prepare("INSERT INTO programs_positions (name, category, abbreviation, description) VALUES (?, ?, ?, ?)");
        $result = $stmt->execute([$name, $category, $abbreviation, $description]);
        
        if ($result) {
            $_SESSION['success_message'] = "Program/Position added successfully!";
        } else {
            $_SESSION['error_message'] = "Error adding program/position.";
        }
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) { // Duplicate entry
            $_SESSION['error_message'] = "This program/position already exists for the selected category.";
        } else {
            $_SESSION['error_message'] = "Database error: " . $e->getMessage();
        }
    }
    
    header("Location: manage_programs.php");
    exit();
}

// Handle deactivating program/position
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_status') {
    $id = $_POST['program_id'];
    $new_status = $_POST['new_status'];
    
    try {
        $stmt = $conn->prepare("UPDATE programs_positions SET is_active = ? WHERE id = ?");
        $result = $stmt->execute([$new_status, $id]);
        
        if ($result) {
            $_SESSION['success_message'] = "Status updated successfully!";
        } else {
            $_SESSION['error_message'] = "Error updating status.";
        }
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "Database error: " . $e->getMessage();
    }
    
    header("Location: manage_programs.php");
    exit();
}

// Fetch all programs/positions
$sql = "SELECT * FROM programs_positions ORDER BY category, name";
$stmt = $conn->prepare($sql);
$stmt->execute();
$programs = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>

<head>
    <title>Manage Programs/Positions</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <style>
        .header-section {
            background: linear-gradient(135deg, #0072bc 0%, #003d82 100%);
            color: white;
            padding: 40px 0;
            margin-bottom: 30px;
        }
        .header-section .container{
            max-width: 1320px;
            margin: 0 auto;
            padding: 0 32px;
        }
        
        .category-section {
            margin-bottom: 40px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        

        
        .program-item {
            padding: 15px 20px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .program-item:last-child {
            border-bottom: none;
        }
        
        .program-info {
            flex-grow: 1;
        }
        
        .program-name {
            font-weight: 600;
            color: #333;
        }
        
        .program-abbr {
            color: #666;
            font-size: 14px;
        }
        
        .program-desc {
            color: #888;
            font-size: 13px;
            margin-top: 5px;
        }
        
        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .status-active {
            background: #d4edda;
            color: #155724;
        }
        
        .status-inactive {
            background: #f8d7da;
            color: #721c24;
        }
        
        .add-form {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        
        .alert {
            border-radius: 8px;
        }
        
        .btn-primary {
            background-color: #0072bc;
            border-color: #0072bc;
        }
        
        .btn-primary:hover {
            background-color: #005a94;
            border-color: #005a94;
        }
        
        .btn-success {
            background-color: #fdf005;
            border-color: #fdf005;
            color: #333;
        }
        
        .btn-success:hover {
            background-color: #e8dc04;
            border-color: #e8dc04;
            color: #333;
        }
        
        .form-control:focus {
            border-color: #0072bc;
            box-shadow: 0 0 0 0.2rem rgba(0, 114, 188, 0.25);
        }
        
        .category-header {
            background: #f8f9fa;
            padding: 15px 20px;
            border-bottom: 2px solid #dee2e6;
            font-weight: bold;
            font-size: 18px;
            color: #003d82;
        }
    </style>
</head>

<body style="background-color: #f4ebb6;">
    <div class="header-section">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1><i class="fas fa-graduation-cap"></i> Manage Programs/Positions</h1>
                    <p class="mb-0">Add and manage programs for different user categories</p>
                </div>
                <a href="admin_page.php" class="btn btn-light">
                    <i class="fas fa-arrow-left"></i> Back to Admin
                </a>
            </div>
        </div>
    </div>

    <div class="container">
        <!-- Display messages -->
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="fas fa-check-circle"></i> <?= $_SESSION['success_message'] ?>
                <button type="button" class="close" data-dismiss="alert">&times;</button>
            </div>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="fas fa-exclamation-circle"></i> <?= $_SESSION['error_message'] ?>
                <button type="button" class="close" data-dismiss="alert">&times;</button>
            </div>
            <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>

        <!-- Add New Program Form -->
        <div class="add-form">
            <h3><i class="fas fa-plus-circle"></i> Add New Program/Position</h3>
            <form method="POST">
                <input type="hidden" name="action" value="add">
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="program_name">Program/Position Name</label>
                            <input type="text" id="program_name" name="program_name" class="form-control" required>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="category">Category</label>
                            <select id="category" name="category" class="form-control" required>
                                <option value="">Select Category</option>
                                <option value="SHS">SHS</option>
                                <option value="COLLEGE STUDENT">College Student</option>
                                <option value="EMPLOYEE">Employee</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="abbreviation">Abbreviation</label>
                            <input type="text" id="abbreviation" name="abbreviation" class="form-control" maxlength="20">
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <label for="description">Description (Optional)</label>
                    <textarea id="description" name="description" class="form-control" rows="2"></textarea>
                </div>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Add Program/Position
                </button>
            </form>
        </div>

        <!-- Programs List -->
        <?php
        $categories = ['SHS', 'COLLEGE STUDENT', 'EMPLOYEE'];
        foreach ($categories as $category):
            $categoryPrograms = array_filter($programs, function($p) use ($category) {
                return $p['category'] === $category;
            });
        ?>
            <div class="category-section">
                <div class="category-header">
                    <i class="fas fa-users"></i> <?= $category ?> Programs/Positions
                    <span class="badge badge-secondary ml-2"><?= count($categoryPrograms) ?></span>
                </div>
                <?php if (empty($categoryPrograms)): ?>
                    <div class="program-item">
                        <em class="text-muted">No programs/positions added for this category yet.</em>
                    </div>
                <?php else: ?>
                    <?php foreach ($categoryPrograms as $program): ?>
                        <div class="program-item">
                            <div class="program-info">
                                <div class="program-name"><?= htmlspecialchars($program['name']) ?></div>
                                <?php if ($program['abbreviation']): ?>
                                    <div class="program-abbr">Abbreviation: <?= htmlspecialchars($program['abbreviation']) ?></div>
                                <?php endif; ?>
                                <?php if ($program['description']): ?>
                                    <div class="program-desc"><?= htmlspecialchars($program['description']) ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="d-flex align-items-center">
                                <span class="status-badge <?= $program['is_active'] ? 'status-active' : 'status-inactive' ?> mr-3">
                                    <?= $program['is_active'] ? 'Active' : 'Inactive' ?>
                                </span>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="toggle_status">
                                    <input type="hidden" name="program_id" value="<?= $program['id'] ?>">
                                    <input type="hidden" name="new_status" value="<?= $program['is_active'] ? 0 : 1 ?>">
                                    <button type="submit" class="btn btn-sm <?= $program['is_active'] ? 'btn-warning' : 'btn-success' ?>"
                                            onclick="return confirm('Are you sure you want to <?= $program['is_active'] ? 'deactivate' : 'activate' ?> this program/position?')">
                                        <i class="fas <?= $program['is_active'] ? 'fa-pause' : 'fa-play' ?>"></i>
                                        <?= $program['is_active'] ? 'Deactivate' : 'Activate' ?>
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
