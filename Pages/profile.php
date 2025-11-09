<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_once '../Includes/connection.php';

$stmt = $conn->prepare("SELECT * FROM account WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

$isEmployee = strtoupper(trim($user['role_category'] ?? '')) === 'EMPLOYEE';
$idLabel = $isEmployee ? 'Employee Number' : 'Student ID';

$passwordMessage = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $currentPassword = $_POST['current_password'];
    $newPassword = $_POST['new_password'];
    $confirmPassword = $_POST['confirm_password'];

    if (password_verify($currentPassword, $user['password'])) {
        if ($newPassword === $confirmPassword) {
            $validationErrors = [];
            if (strlen($newPassword) < 12) {
                $validationErrors[] = 'Password must be at least 12 characters long';
            }
            if (strlen($newPassword) > 64) {
                $validationErrors[] = 'Password must not exceed 64 characters';
            }
            $digitCount = preg_match_all('/\d/', $newPassword);
            if ($digitCount < 2) {
                $validationErrors[] = 'Password must contain at least 2 numeric digits';
            }
            if (preg_match('/\s/', $newPassword)) {
                $validationErrors[] = 'Password must not contain spaces';
            }
            if (empty($validationErrors)) {
                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                $updateStmt = $conn->prepare("UPDATE account SET password = ? WHERE id = ?");
                
                if ($updateStmt->execute([$hashedPassword, $_SESSION['user_id']])) {
                    $passwordMessage = '<div class="alert success">Password successfully updated!</div>';
                } else {
                    $passwordMessage = '<div class="alert error">Error updating password.</div>';
                }
            } else {
                $errorList = implode('<br>', array_map(function($error) {
                    return '• ' . htmlspecialchars($error);
                }, $validationErrors));
                $passwordMessage = '<div class="alert error">Password validation failed:<br>' . $errorList . '</div>';
            }
        } else {
            $passwordMessage = '<div class="alert error">New passwords do not match!</div>';
        }
    } else {
        $passwordMessage = '<div class="alert error">Current password is incorrect!</div>';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></title>
    <link rel="stylesheet" href="../CSS/global.css">
    <link rel="stylesheet" href="../CSS/profile.css">
    <meta charset="UTF-8">
    <link rel="stylesheet" href="../CSS/header.css">    
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Anton&family=Smooch+Sans:wght@100..900&display=swap"
        rel="stylesheet">
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <?php include '../Includes/Header.php'; ?>

    <div class="profile-container">
        <div class="profile-header">
            <div class="profile-info">
                <h1><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h1>
                <?php if ($user['id_number'] !== null && $user['id_number'] !== ''): ?>
                    <p class="user-id"><?php echo $idLabel; ?>: <?php echo htmlspecialchars($user['id_number']); ?></p>
                <?php endif; ?>
            </div>
        </div>

        <div class="profile-content">
            <div class="info-section">
                <h2>Personal Information</h2>
                <div class="info-grid">
                    <?php if ($user['id_number'] !== null && $user['id_number'] !== ''): ?>
                        <div class="info-item">
                            <label><?php echo $idLabel; ?></label>
                            <span><?php echo htmlspecialchars($user['id_number']); ?></span>
                        </div>
                    <?php endif; ?>
                    <div class="info-item">
                        <label>Role:</label>
                        <span><?php echo htmlspecialchars($user['role_category']); ?></span>
                    </div>
                    <div class="info-item">
                        <label>Program/Position:</label>
                        <span><?php echo htmlspecialchars($user['program_or_position']); ?></span>
                    </div>
                    <div class="info-item">
                        <label>Email:</label>
                        <span><?php echo htmlspecialchars($user['email']); ?></span>
                    </div>
                </div>
            </div>

            <div class="password-section">
                <h2>Change Password</h2>
                <?php echo $passwordMessage; ?>
                
                <div class="password-requirements">
                    <p><strong>Password Requirements:</strong></p>
                    <ul>
                        <li>12-64 characters long</li>
                        <li>At least 2 numeric digits</li>
                        <li>No spaces allowed</li>
                    </ul>
                </div>

                <form method="POST" class="password-form">
                    <div class="form-group">
                        <label for="current_password">Current Password</label>
                        <div class="password-input-wrapper">
                            <input type="password" id="current_password" name="current_password" required>
                            <button type="button" class="password-toggle" id="toggle-current-password">
                                <i class="fas fa-eye-slash"></i>
                            </button>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="new_password">New Password</label>
                        <div class="password-input-wrapper">
                            <input type="password" id="new_password" name="new_password" required>
                            <button type="button" class="password-toggle" id="toggle-new-password">
                                <i class="fas fa-eye-slash"></i>
                            </button>
                        </div>
                        <div class="password-strength">
                            <div class="password-strength-meter">
                                <div id="password-strength-meter" class="strength-bar"></div>
                            </div>
                            <span id="password-strength-text" class="strength-text"></span>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="confirm_password">Confirm New Password</label>
                        <div class="password-input-wrapper">
                            <input type="password" id="confirm_password" name="confirm_password" required>
                            <button type="button" class="password-toggle" id="toggle-confirm-password">
                                <i class="fas fa-eye-slash"></i>
                            </button>
                        </div>
                    </div>
                    <button type="submit" name="change_password" class="change-password-btn">Update Password</button>
                </form>
            </div>
        </div>
    </div>

    <script src="../Javascript/cart.js"></script>
    <script src="../Javascript/profile.js"></script>
</body>
</html>
