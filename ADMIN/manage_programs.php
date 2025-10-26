<?php
require_once '../Includes/connection.php';
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../Pages/login.php?redirect=../ADMIN/manage_programs.php");
    exit();
}
$role = strtoupper($_SESSION['role_category'] ?? '');
$programAbbr = strtoupper($_SESSION['program_abbreviation'] ?? '');
if (!($role === 'EMPLOYEE' && $programAbbr === 'ADMIN')) {
    header("Location: ../Pages/home.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    $name = trim($_POST['program_name']);
    $category = $_POST['category'];
    $abbreviation = trim($_POST['abbreviation']);

    // Prevent adding employee positions manually
    if ($category === 'EMPLOYEE') {
        $_SESSION['error_message'] = "Employee positions cannot be added manually. The system has three predefined positions: Administrator (ADMIN), Purchasing Asset and Management Officer (PAMO), and Institutional Staff (Staff).";
        header("Location: manage_programs.php");
        exit();
    }

    try {
        $checkStmt = $conn->prepare("SELECT id, category FROM programs_positions WHERE LOWER(name) = LOWER(?) OR (abbreviation != '' AND LOWER(abbreviation) = LOWER(?))");
        $checkStmt->execute([$name, $abbreviation]);
        $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existing) {
            $duplicateCheckName = $conn->prepare("SELECT id, category FROM programs_positions WHERE LOWER(name) = LOWER(?)");
            $duplicateCheckName->execute([$name]);
            $nameExists = $duplicateCheckName->fetch(PDO::FETCH_ASSOC);
            
            $duplicateCheckAbbr = $conn->prepare("SELECT id, category FROM programs_positions WHERE abbreviation != '' AND LOWER(abbreviation) = LOWER(?)");
            $duplicateCheckAbbr->execute([$abbreviation]);
            $abbrExists = $duplicateCheckAbbr->fetch(PDO::FETCH_ASSOC);
            
            if ($nameExists && $abbrExists) {
                $nameCategory = $nameExists['category'];
                $abbrCategory = $abbrExists['category'];
                $_SESSION['error_message'] = "Both the program/position name and abbreviation already exist in the system (Name in: {$nameCategory}, Abbreviation in: {$abbrCategory}).";
            } elseif ($nameExists) {
                $existingCategory = $nameExists['category'];
                $_SESSION['error_message'] = "A program/position with this name already exists in the '{$existingCategory}' category.";
            } elseif ($abbrExists) {
                $existingCategory = $abbrExists['category'];
                $_SESSION['error_message'] = "A program/position with this abbreviation already exists in the '{$existingCategory}' category.";
            }
        } else {
            $stmt = $conn->prepare("INSERT INTO programs_positions (name, category, abbreviation) VALUES (?, ?, ?)");
            $result = $stmt->execute([$name, $category, $abbreviation]);
            
            if ($result) {
                $_SESSION['success_message'] = "Program/Position added successfully!";
            } else {
                $_SESSION['error_message'] = "Error adding program/position.";
            }
        }
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "Database error: " . $e->getMessage();
    }
    
    header("Location: manage_programs.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_status') {
    $id = $_POST['program_id'];
    $new_status = $_POST['new_status'];
    
    try {
        // Check if this is a system-defined employee position
        $checkStmt = $conn->prepare("SELECT name, category, is_system_defined FROM programs_positions WHERE id = ?");
        $checkStmt->execute([$id]);
        $program = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($program && isset($program['is_system_defined']) && $program['is_system_defined'] == 1) {
            $_SESSION['error_message'] = "Cannot modify system-defined employee positions. The positions Administrator, PAMO, and Institutional Staff must remain active.";
            header("Location: manage_programs.php");
            exit();
        }
        
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
        
        .form-control.is-invalid {
            border-color: #dc3545;
            box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.25);
        }
        .invalid-feedback {
            display: block;
            width: 100%;
            margin-top: 0.25rem;
            font-size: 0.875em;
            color: #dc3545;
        }
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
                            <label for="category">ROLE</label>
                            <select id="category" name="category" class="form-control" required>
                                <option value="">Select Role</option>
                                <option value="SHS">SHS Academic Strand</option>
                                <option value="COLLEGE STUDENT">COLLEGE Courses</option>
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
                    <i class="fas fa-users"></i> 
                    <?php 
                        $headerText = '';
                        switch($category) {
                            case 'SHS':
                                $headerText = 'SHS Academic Strand';
                                break;
                            case 'COLLEGE STUDENT':
                                $headerText = 'COLLEGE Courses';
                                break;
                            case 'EMPLOYEE':
                                $headerText = 'Employee Position';
                                break;
                            default:
                                $headerText = $category;
                        }
                        echo $headerText;
                    ?>
                    <span class="badge badge-secondary ml-2"><?= count($categoryPrograms) ?></span>
                </div>
                <div class="category-body" style="padding:16px;">
                <?php if (empty($categoryPrograms)): ?>
                    <div class="program-item"><em class="text-muted">No programs/positions added for this category yet.</em></div>
                <?php else: ?>
                    <?php foreach ($categoryPrograms as $program): ?>
                        <?php 
                            $isSystemDefined = isset($program['is_system_defined']) && $program['is_system_defined'] == 1;
                        ?>
                        <div class="program-item" style="display:grid; grid-template-columns: 1fr auto auto; align-items:center; column-gap:12px; padding:10px 12px; border:1px dashed #e6eef6; border-radius:10px; margin-bottom:10px; background:linear-gradient(145deg,#ffffff 0%, #f8fbff 100%);">
                            <div class="program-info" style="min-width:0;">
                                <div class="program-name" style="font-weight:600; color:#003d82;">
                                    <?= htmlspecialchars($program['name']) ?>
                                    <?php if ($isSystemDefined): ?>
                                    <?php endif; ?>
                                </div>
                                <?php if ($program['abbreviation']): ?>
                                    <div class="program-abbr" style="font-size:12px; color:#6c757d;">Abbreviation: <?= htmlspecialchars($program['abbreviation']) ?></div>
                                <?php endif; ?>
                            </div>
                            <span class="status-badge <?= $program['is_active'] ? 'status-active' : 'status-inactive' ?>" style="justify-self:center;">
                                <?= $program['is_active'] ? 'Active' : 'Inactive' ?>
                            </span>
                            <?php if (!$isSystemDefined): ?>
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
                            <?php else: ?>
                            <?php endif; ?>
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
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const nameInput = document.getElementById('program_name');
            const categorySelect = document.getElementById('category');
            const abbreviationInput = document.getElementById('abbreviation');
            const form = document.querySelector('form');

            const existingPrograms = <?= json_encode($programs) ?>;
            
            // Restrict input to letters, spaces, and commas only
            function restrictToLettersAndCommas(event) {
                const input = event.target;
                let value = input.value;
                
                // Remove any character that is not a letter, space, or comma
                value = value.replace(/[^A-Za-z\s,]/g, '');
                
                // Replace multiple consecutive spaces with a single space
                value = value.replace(/\s{2,}/g, ' ');
                
                input.value = value;
            }
            
            // Validate that input contains actual letters (not just spaces)
            function validateInput(input) {
                const value = input.value.trim();
                
                // Remove existing validation feedback
                input.classList.remove('is-invalid');
                const existingFeedback = input.parentNode.querySelector('.invalid-feedback.format-error');
                if (existingFeedback) {
                    existingFeedback.remove();
                }
                
                // Check if input is empty or contains only spaces/commas
                if (!value || !/[A-Za-z]/.test(value)) {
                    input.classList.add('is-invalid');
                    const feedback = document.createElement('div');
                    feedback.className = 'invalid-feedback format-error';
                    feedback.style.display = 'block';
                    feedback.textContent = 'This field must contain at least one letter.';
                    input.parentNode.appendChild(feedback);
                    return false;
                }
                
                return true;
            }
            
            // Add input event listeners to restrict characters
            nameInput.addEventListener('input', restrictToLettersAndCommas);
            abbreviationInput.addEventListener('input', restrictToLettersAndCommas);
            
            // Add blur event listeners to validate format
            nameInput.addEventListener('blur', function() {
                if (this.value.trim()) {
                    this.value = this.value.trim().replace(/\s{2,}/g, ' ');
                    validateInput(this);
                }
            });
            
            abbreviationInput.addEventListener('blur', function() {
                if (this.value.trim()) {
                    this.value = this.value.trim().replace(/\s{2,}/g, ' ');
                    validateInput(this);
                }
            });
            
            function checkDuplicates() {
                const selectedCategory = categorySelect.value;
                const enteredName = nameInput.value.trim().toLowerCase();
                const enteredAbbr = abbreviationInput.value.trim().toLowerCase();
                
                if (!enteredName) return;

                nameInput.classList.remove('is-invalid');
                abbreviationInput.classList.remove('is-invalid');
                document.querySelectorAll('.invalid-feedback').forEach(el => el.remove());

                const nameExistsInProgram = existingPrograms.find(p => p.name.toLowerCase() === enteredName);
                if (nameExistsInProgram) {
                    nameInput.classList.add('is-invalid');
                    const feedback = document.createElement('div');
                    feedback.className = 'invalid-feedback';
                    feedback.textContent = `A program/position with this name already exists in the '${nameExistsInProgram.category}' category.`;
                    nameInput.parentNode.appendChild(feedback);
                }

                if (enteredAbbr) {
                    const abbrExistsInProgram = existingPrograms.find(p => p.abbreviation && p.abbreviation.toLowerCase() === enteredAbbr);
                    if (abbrExistsInProgram) {
                        abbreviationInput.classList.add('is-invalid');
                        const feedback = document.createElement('div');
                        feedback.className = 'invalid-feedback';
                        feedback.textContent = `A program/position with this abbreviation already exists in the '${abbrExistsInProgram.category}' category.`;
                        abbreviationInput.parentNode.appendChild(feedback);
                    }
                }
            }

            nameInput.addEventListener('input', checkDuplicates);
            abbreviationInput.addEventListener('input', checkDuplicates);
            categorySelect.addEventListener('change', checkDuplicates);

            form.addEventListener('submit', function(e) {
                // Trim and clean up inputs before validation
                nameInput.value = nameInput.value.trim().replace(/\s{2,}/g, ' ');
                if (abbreviationInput.value.trim()) {
                    abbreviationInput.value = abbreviationInput.value.trim().replace(/\s{2,}/g, ' ');
                }
                
                // Validate format
                const isNameValid = validateInput(nameInput);
                let isAbbrValid = true;
                
                // Only validate abbreviation if it has content
                if (abbreviationInput.value.trim()) {
                    isAbbrValid = validateInput(abbreviationInput);
                }
                
                // Check for duplicates
                checkDuplicates();
                
                // Prevent submission if there are any validation errors
                const hasErrors = document.querySelectorAll('.is-invalid').length > 0;
                
                if (hasErrors || !isNameValid || !isAbbrValid) {
                    e.preventDefault();
                    
                    let errorMsg = '';
                    if (!isNameValid) {
                        errorMsg = 'Program/Position name must contain at least one letter and cannot be only spaces.';
                    } else if (!isAbbrValid) {
                        errorMsg = 'Abbreviation must contain at least one letter and cannot be only spaces.';
                    } else {
                        errorMsg = 'Please resolve validation errors before submitting.';
                    }
                    
                    alert(errorMsg);
                }
            });
        });
    </script>
</body>

</html>
