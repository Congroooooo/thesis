<?php
require_once '../Includes/connection.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    $name = trim($_POST['program_name']);
    $category = $_POST['category'];
    $abbreviation = trim($_POST['abbreviation']);

    try {
        $stmt = $conn->prepare("INSERT INTO programs_positions (name, category, abbreviation) VALUES (?, ?, ?)");
        $result = $stmt->execute([$name, $category, $abbreviation]);
        
        if ($result) {
            $_SESSION['success_message'] = "Program/Position added successfully!";
        } else {
            $_SESSION['error_message'] = "Error adding program/position.";
        }
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) {
            $_SESSION['error_message'] = "This program/position already exists for the selected category.";
        } else {
            $_SESSION['error_message'] = "Database error: " . $e->getMessage();
        }
    }
    
    header("Location: manage_programs.php");
    exit();
}

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
    <link rel="stylesheet" href="../ADMIN CSS/manage_programs.css">
    <style>
        .success-modal { display:none; position:fixed; top:50%; left:50%; transform:translate(-50%, -50%); background:#fff; padding:24px; border-radius:12px; box-shadow:0 10px 30px rgba(0,0,0,.15); z-index:10000; width:90%; max-width:480px; text-align:center; }
        .modal-backdrop { display:none; position:fixed; inset:0; background:rgba(0,0,0,.5); z-index:9999; }
        .success-icon { color:#28a745; font-size:48px; margin-bottom:12px; }

        /* Consistent badges and buttons (mirrors admin page styling) */
        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 86px;
        }
        .status-active {
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .status-inactive {
            background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .btn { font-weight:600; border:none; border-radius:10px; transition:all .25s ease; }
        .btn-primary { background: linear-gradient(135deg, #0072bc 0%, #005a94 100%); color:#fff; box-shadow:0 4px 15px rgba(0,114,188,.3); }
        .btn-primary:hover { background: linear-gradient(135deg, #005a94 0%, #004275 100%); transform: translateY(-2px); color:#fff; }
        .btn-success { background: linear-gradient(135deg, #fdf005 0%, #e8dc04 100%); color:#333; box-shadow:0 4px 15px rgba(253,240,5,.3); }
        .btn-success:hover { background: linear-gradient(135deg, #e8dc04 0%, #d4c603 100%); transform: translateY(-2px); color:#333; }
        .btn-warning { background: linear-gradient(135deg, #ffc107 0%, #e0a800 100%); color:#333; box-shadow:0 4px 15px rgba(255,193,7,.3); }
        .btn-warning:hover { background: linear-gradient(135deg, #e0a800 0%, #d39e00 100%); transform: translateY(-2px); color:#333; }
        .btn-sm { padding: 6px 12px; border-radius:8px; }
    </style>
</head>

<body>
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

    <!-- Success Modal (consistent with admin change password) -->
    <div id="mpSuccessModal" class="success-modal">
        <div>
            <i class="fas fa-check-circle success-icon"></i>
            <h4>Success!</h4>
            <p id="mpSuccessMessage">Action completed successfully.</p>
            <button class="btn btn-primary mt-3" id="mpSuccessOk">OK</button>
        </div>
    </div>
    <div id="mpModalBackdrop" class="modal-backdrop"></div>

    <div class="container" style="max-width: 100%; padding: 0 24px;">
        <?php if (isset($_SESSION['success_message'])): ?>
            <script>
                document.addEventListener('DOMContentLoaded', function(){
                    const modal = document.getElementById('mpSuccessModal');
                    const backdrop = document.getElementById('mpModalBackdrop');
                    document.getElementById('mpSuccessMessage').textContent = <?= json_encode($_SESSION['success_message']) ?>;
                    modal.style.display = 'block';
                    backdrop.style.display = 'block';
                    document.getElementById('mpSuccessOk').addEventListener('click', function(){
                        modal.style.display='none';
                        backdrop.style.display='none';
                    });
                });
            </script>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="fas fa-exclamation-circle"></i> <?= $_SESSION['error_message'] ?>
                <button type="button" class="close" data-dismiss="alert">&times;</button>
            </div>
            <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>

        <div class="add-form" style="margin-bottom: 24px;">
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
                                <option value="SHS">Academic Track</option>
                                <option value="COLLEGE STUDENT">Course</option>
                                <option value="EMPLOYEE">Position</option>
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
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Add Program/Position
                </button>
            </form>
        </div>
        
        <div class="row" style="display:grid; grid-template-columns: repeat(3, minmax(0,1fr)); gap: 16px; align-items:start; margin: 0;">
            <?php
            $categories = ['SHS', 'COLLEGE STUDENT', 'EMPLOYEE'];
            foreach ($categories as $category):
                $categoryPrograms = array_filter($programs, function($p) use ($category) {
                    return $p['category'] === $category;
                });
            ?>
            <div class="category-section" style="background:#fff; border-radius:12px; box-shadow:0 4px 20px rgba(0,0,0,.06); border:1px solid rgba(0,114,188,.08); overflow:hidden;">
                <div class="category-header" style="background:#f7f9fc; padding:14px 16px; border-bottom:2px solid #e1ebf5; font-weight:700; color:#003d82;">
                    <i class="fas fa-users"></i> <?= $category ?> Programs/Positions
                    <span class="badge badge-secondary ml-2"><?= count($categoryPrograms) ?></span>
                </div>
                <div class="category-body" style="padding:16px;">
                <?php if (empty($categoryPrograms)): ?>
                    <div class="program-item"><em class="text-muted">No programs/positions added for this category yet.</em></div>
                <?php else: ?>
                    <?php foreach ($categoryPrograms as $program): ?>
                        <div class="program-item" style="display:grid; grid-template-columns: 1fr auto auto; align-items:center; column-gap:12px; padding:10px 12px; border:1px dashed #e6eef6; border-radius:10px; margin-bottom:10px; background:linear-gradient(145deg,#ffffff 0%, #f8fbff 100%);">
                            <div class="program-info" style="min-width:0;">
                                <div class="program-name" style="font-weight:600; color:#003d82;">
                                    <?= htmlspecialchars($program['name']) ?>
                                </div>
                                <?php if ($program['abbreviation']): ?>
                                    <div class="program-abbr" style="font-size:12px; color:#6c757d;">Abbreviation: <?= htmlspecialchars($program['abbreviation']) ?></div>
                                <?php endif; ?>
                            </div>
                            <span class="status-badge <?= $program['is_active'] ? 'status-active' : 'status-inactive' ?>" style="justify-self:center;">
                                <?= $program['is_active'] ? 'Active' : 'Inactive' ?>
                            </span>
                            <form method="POST" style="display:inline; margin:0; justify-self:end;">
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
                    <?php endforeach; ?>
                <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
