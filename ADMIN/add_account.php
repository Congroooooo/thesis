<?php
require_once '../Includes/connection.php';
header('Content-Type: application/json');

// API-only endpoint
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    $firstName = trim($_POST['firstName'] ?? '');
    $lastName = trim($_POST['lastName'] ?? '');
    $birthday = trim($_POST['birthday'] ?? '');
    $idNumber = trim($_POST['idNumber'] ?? '');
    $role_category = trim($_POST['role_category'] ?? '');
    $program_or_position = trim($_POST['program_position'] ?? '');

    if ($firstName === '' || $lastName === '' || $birthday === '' || $idNumber === '' || $role_category === '' || $program_or_position === '') {
        throw new Exception('Missing required fields');
    }

    if (strlen($idNumber) !== 11) {
        throw new Exception('ID Number must be exactly 11 digits');
    }

    $birthdayObj = new DateTime($birthday);
    $autoPassword = strtolower($lastName) . $birthdayObj->format('mdY');
    $password = password_hash($autoPassword, PASSWORD_DEFAULT);

    $lastSixDigits = substr($idNumber, -6);
    $email = strtolower(str_replace(' ', '', $lastName . '.' . $lastSixDigits . '@lucena.sti.edu.ph'));

    $sql = "INSERT INTO account (first_name, last_name, birthday, id_number, email, password, role_category, program_or_position, status, date_created)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active', CURRENT_TIMESTAMP)";
    $stmt = $conn->prepare($sql);
    $result = $stmt->execute([
        $firstName,
        $lastName,
        $birthday,
        $idNumber,
        $email,
        $password,
        $role_category,
        $program_or_position
    ]);

    if (!$result) {
        throw new Exception('Database insert failed');
    }

    echo json_encode([
        'success' => true,
        'generated_email' => $email,
        'generated_password' => $autoPassword
    ]);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
exit;
?>

<!DOCTYPE html>
<html>

<head>
    <title>Add Account</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="../ADMIN CSS/add_account.css">
</head>

<body>
    <!-- Success Modal -->
    <div id="successModal" class="success-modal" style="display: none;">
        <div class="text-center">
            <i class="fas fa-check-circle success-icon"></i>
            <h4>Account Created Successfully!</h4>
            <p>The account has been created with the following credentials:</p>
            <div class="credentials-display">
                <div class="credential-item">
                    <label>Email:</label>
                    <strong id="generatedEmail"></strong>
                </div>
                <div class="credential-item">
                    <label>Password:</label>
                    <strong id="generatedPassword"></strong>
                </div>
            </div>
            <p class="text-muted">Please make sure to save these credentials for future reference.</p>
            <button class="btn btn-primary mt-3" onclick="closeModal()">Continue</button>
        </div>
    </div>
    <div id="modalBackdrop" class="modal-backdrop" style="display: none;"></div>

    <!-- Error Modal -->
    <div id="errorModal" class="error-modal" style="display: none;">
        <div class="text-center">
            <i class="fas fa-exclamation-circle text-danger" style="font-size: 48px;"></i>
            <h4>Error</h4>
            <p id="errorMessage"></p>
            <button class="btn btn-secondary mt-3" onclick="closeErrorModal()">Close</button>
        </div>
    </div>

    <!-- Header Section -->
    <div class="header-section">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1><i class="fas fa-user-plus"></i> Add New Account</h1>
                    <p class="mb-0">Create a new user account with auto-generated credentials</p>
                </div>
                <a href="admin_page.php" class="btn btn-light">
                    <i class="fas fa-arrow-left"></i> Back to Admin
                </a>
            </div>
        </div>
    </div>

    <div class="container">
        <div class="form-card">
            <div class="card-header">
                <h5><i class="fas fa-user-plus"></i> Account Information</h5>
            </div>
            <div class="card-body">
                <form id="addAccountForm" method="POST">
            <div class="form-group">
                <label for="firstName">First Name</label>
                <input type="text" id="firstName" name="firstName" class="form-control" required>
            </div>
            <div class="form-group">
                <label for="lastName">Last Name</label>
                <input type="text" id="lastName" name="lastName" class="form-control" required>
            </div>
            <div class="form-group">
                <label for="birthday">Birthday</label>
                <input type="date" id="birthday" name="birthday" class="form-control" required>
            </div>
            <div class="form-group">
                <label for="idNumber">ID Number (11 digits)</label>
                <input type="text" id="idNumber" name="idNumber" class="form-control" pattern="\d{11}" required>
            </div>
            <div class="form-group">
                <label for="generatedEmailPreview">Generated Email</label>
                <input type="text" id="generatedEmailPreview" class="form-control" readonly
                    style="background-color: #f8f9fa; color: #495057;">
            </div>
            <div class="form-group">
                <label for="generatedPasswordPreview">Generated Password</label>
                <input type="text" id="generatedPasswordPreview" class="form-control" readonly
                    style="background-color: #f8f9fa; color: #495057;" placeholder="Password will be auto-generated">
            </div>
            <div class="form-group">
                <label for="roleCategory">Role Category</label>
                <select id="roleCategory" name="role_category" class="form-control" required>
                    <option value="">Select Role Category</option>
                    <option value="SHS">SHS</option>
                    <option value="COLLEGE STUDENT">COLLEGE STUDENT</option>
                    <option value="EMPLOYEE">EMPLOYEE</option>
                </select>
            </div>
            <div class="form-group">
                <label for="programPosition">Program/Position</label>
                <select id="programPosition" name="program_position" class="form-control" required>
                    <option value="">Select Program/Position</option>
                </select>
            </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="fas fa-plus"></i> Create Account
                        </button>
                        <a href="admin_page.php" class="btn btn-secondary btn-lg">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Check for success or error messages in session
        document.addEventListener('DOMContentLoaded', function() {
            <?php if (isset($_SESSION['success_message']) && isset($_SESSION['generated_email']) && isset($_SESSION['generated_password'])): ?>
                const successModal = document.getElementById('successModal');
                const modalBackdrop = document.getElementById('modalBackdrop');
                const emailElement = document.getElementById('generatedEmail');
                const passwordElement = document.getElementById('generatedPassword');

                if (successModal && modalBackdrop && emailElement && passwordElement) {
                    successModal.style.display = 'block';
                    modalBackdrop.style.display = 'block';
                    emailElement.textContent = <?php echo json_encode($_SESSION['generated_email']); ?>;
                    passwordElement.textContent = <?php echo json_encode($_SESSION['generated_password']); ?>;
                    
                }
                <?php
                unset($_SESSION['success_message']);
                unset($_SESSION['generated_email']);
                unset($_SESSION['generated_password']);
                ?>
            <?php endif; ?>

            <?php if (isset($_SESSION['error_message'])): ?>
                const errorModal = document.getElementById('errorModal');
                const errorMessage = document.getElementById('errorMessage');
                if (errorModal && errorMessage) {
                    errorModal.style.display = 'block';
                    errorMessage.textContent = <?php echo json_encode($_SESSION['error_message']); ?>;
                }
                <?php unset($_SESSION['error_message']); ?>
            <?php endif; ?>
        });

        function closeModal() {
            const successModal = document.getElementById('successModal');
            const modalBackdrop = document.getElementById('modalBackdrop');
            
            if (successModal && modalBackdrop) {
                successModal.style.display = 'none';
                modalBackdrop.style.display = 'none';
                
                // Reset the form
                document.getElementById('addAccountForm').reset();
                
                // Clear the preview fields
                document.getElementById('generatedEmailPreview').value = '';
                document.getElementById('generatedPasswordPreview').value = '';
                
                // Clear the program/position dropdown
                const programPositionSelect = document.getElementById('programPosition');
                programPositionSelect.innerHTML = '<option value="">Select Program/Position</option>';
            }
        }

        function closeErrorModal() {
            const errorModal = document.getElementById('errorModal');
            if (errorModal) {
                errorModal.style.display = 'none';
            }
        }

        // Function to generate email and password preview
        function updatePreview() {
            const lastName = document.getElementById('lastName').value.toLowerCase().trim();
            const birthday = document.getElementById('birthday').value;
            const idNumber = document.getElementById('idNumber').value.trim();
            const emailPreview = document.getElementById('generatedEmailPreview');
            const passwordPreview = document.getElementById('generatedPasswordPreview');

            // Update email preview
            if (lastName && idNumber.length >= 6) {
                const lastSixDigits = idNumber.slice(-6);
                const email = `${lastName}.${lastSixDigits}@lucena.sti.edu.ph`.replace(/\s+/g, '');
                emailPreview.value = email;
            } else {
                emailPreview.value = '';
            }

            // Update password preview
            if (lastName && birthday) {
                const birthdayObj = new Date(birthday);
                const month = String(birthdayObj.getMonth() + 1).padStart(2, '0');
                const day = String(birthdayObj.getDate()).padStart(2, '0');
                const year = birthdayObj.getFullYear();
                const password = `${lastName}${month}${day}${year}`.toLowerCase();
                passwordPreview.value = password;
            } else {
                passwordPreview.value = '';
            }
        }

        // Add event listeners for real-time update
        document.getElementById('lastName').addEventListener('input', updatePreview);
        document.getElementById('birthday').addEventListener('input', updatePreview);
        document.getElementById('idNumber').addEventListener('input', updatePreview);

        // Add ID number validation
        document.getElementById('idNumber').addEventListener('input', function (e) {
            const input = e.target;
            // Remove any non-digit characters
            input.value = input.value.replace(/\D/g, '');

            // Prevent input if length would exceed 11 digits
            if (input.value.length > 11) {
                input.value = input.value.slice(0, 11);
                // Show a warning message
                alert('ID Number must be exactly 11 digits');
            }
        });

        // Add maxlength attribute to the input field
        document.getElementById('idNumber').setAttribute('maxlength', '11');

        // Function to validate name input
        function validateNameInput(input) {
            // Remove any numbers or special characters
            input.value = input.value.replace(/[^A-Za-z\s]/g, '');
        }

        // Add event listeners for name fields
        document.getElementById('firstName').addEventListener('input', function (e) {
            validateNameInput(this);
            this.setCustomValidity('');
            if (!/^[A-Za-z\s]*$/.test(this.value)) {
                this.setCustomValidity('Please enter only letters and spaces');
            }
        });

        document.getElementById('lastName').addEventListener('input', function (e) {
            validateNameInput(this);
            this.setCustomValidity('');
            if (!/^[A-Za-z\s]*$/.test(this.value)) {
                this.setCustomValidity('Please enter only letters and spaces');
            }
            updatePreview();
        });

        async function updateProgramPositions() {
            const roleCategory = document.getElementById('roleCategory').value;
            const programPositionSelect = document.getElementById('programPosition');


            // Clear existing options
            programPositionSelect.innerHTML = '<option value="">Select Program/Position</option>';

            if (!roleCategory) {
                return;
            }

            try {
                // Fetch programs from database
                const response = await fetch(`get_programs.php?category=${encodeURIComponent(roleCategory)}`);
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                const programs = await response.json();

                // Check if programs is an array
                if (!Array.isArray(programs)) {
                    throw new Error('Invalid response format');
                }

                // Add programs to dropdown
                programs.forEach(program => {
                    const option = document.createElement('option');
                    option.value = program.name;
                    option.textContent = program.name;
                    programPositionSelect.appendChild(option);
                });

                
            } catch (error) {
                console.error('Error fetching programs:', error);
                
                // Fallback to hardcoded options
                const fallbackOptions = {
                    'SHS': ['Science, Technology, Engineering, and Mathematics (STEM)', 'Humanities and Social Sciences (HUMMS)', 'Accountancy, Business, and Management (ABM)', 'Mobile App and Web Development (MAWD)', 'Digital Arts (DA)', 'Tourism Operations (TOPER)', 'Culinary Arts (CA)'],
                    'COLLEGE STUDENT': ['Bachelor of Science in Computer Science (BSCS)', 'Bachelor of Science in Information Technology (BSIT)', 'Bachelor of Science in Computer Engineering (BSCPE)', 'Bachelor of Science in Culinary Management (BSCM)', 'Bachelor of Science in Tourism Management (BSTM)', 'Bachelor of Science in Business Administration (BSBA)', 'Bachelor of Science in Multimedia Arts (BMMA)'],
                    'EMPLOYEE': ['TEACHER', 'PAMO', 'ADMIN', 'STAFF']
                };

                if (roleCategory in fallbackOptions) {
                    fallbackOptions[roleCategory].forEach(option => {
                        const newOption = document.createElement('option');
                        newOption.value = option;
                        newOption.textContent = option;
                        programPositionSelect.appendChild(newOption);
                    });
                } else {
                }
            }
        }

        // Remove manage option behavior; management is done in admin page only

        // Add event listener for role category change
        document.addEventListener('DOMContentLoaded', function() {
            // Attach change event listener to role category dropdown
            const roleCategory = document.getElementById('roleCategory');
            roleCategory.addEventListener('change', function() {
                updateProgramPositions();
            });
            
            // If there's a pre-selected value, initialize program positions
            if (roleCategory.value) {
                updateProgramPositions();
            }
        });
    </script>
</body>

</html>