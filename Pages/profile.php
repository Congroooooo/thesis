<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_once '../Includes/connection.php';
require_once '../Includes/strike_management.php';

// Fetch user data
$stmt = $conn->prepare("SELECT * FROM account WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Get strike status information
$strikeStatus = checkUserStrikeStatus($conn, $_SESSION['user_id'], true);

$isEmployee = strtoupper(trim($user['role_category'] ?? '')) === 'EMPLOYEE';
$idLabel = $isEmployee ? 'Employee Number' : 'Student ID';

// Handle password change with Post/Redirect/Get pattern
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
                    $_SESSION['password_message'] = 'success';
                } else {
                    $_SESSION['password_message'] = 'error';
                }
            } else {
                $_SESSION['password_message'] = 'validation_error';
                $_SESSION['validation_errors'] = $validationErrors;
            }
        } else {
            $_SESSION['password_message'] = 'mismatch';
        }
    } else {
        $_SESSION['password_message'] = 'incorrect';
    }
    
    // Redirect to prevent form resubmission
    header("Location: profile.php");
    exit();
}

// Display password messages from session
$passwordMessage = '';
if (isset($_SESSION['password_message'])) {
    switch ($_SESSION['password_message']) {
        case 'success':
            $passwordMessage = '<div class="alert success">Password successfully updated!</div>';
            break;
        case 'error':
            $passwordMessage = '<div class="alert error">Error updating password.</div>';
            break;
        case 'validation_error':
            $errorList = implode('<br>', array_map(function($error) {
                return '• ' . htmlspecialchars($error);
            }, $_SESSION['validation_errors'] ?? []));
            $passwordMessage = '<div class="alert error">Password validation failed:<br>' . $errorList . '</div>';
            unset($_SESSION['validation_errors']);
            break;
        case 'mismatch':
            $passwordMessage = '<div class="alert error">New passwords do not match!</div>';
            break;
        case 'incorrect':
            $passwordMessage = '<div class="alert error">Current password is incorrect!</div>';
            break;
    }
    unset($_SESSION['password_message']);
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
            <!-- Strike Status Section -->
            <div class="strike-status-section">
                <h2>Account Status</h2>
                <?php
                $strikeCount = $strikeStatus['strikes'];
                $strikeClass = '';
                $strikeLabel = '';
                
                // Determine color class based on strike count
                switch($strikeCount) {
                    case 0:
                        $strikeClass = 'strike-green';
                        $strikeLabel = 'Good Standing';
                        break;
                    case 1:
                        $strikeClass = 'strike-blue';
                        $strikeLabel = 'Warning Level 1';
                        break;
                    case 2:
                        $strikeClass = 'strike-orange';
                        $strikeLabel = 'Warning Level 2';
                        break;
                    case 3:
                    default:
                        $strikeClass = 'strike-red';
                        $strikeLabel = 'Account Restricted';
                        break;
                }
                ?>
                
                <div class="strike-display <?php echo $strikeClass; ?>">
                    <div class="strike-columns">
                        <!-- Left Column: Strike Information -->
                        <div class="strike-info-column">
                            <div class="strike-header">
                                <span class="strike-icon">
                                    <?php if ($strikeCount == 0): ?>
                                        <i class="fas fa-check-circle"></i>
                                    <?php elseif ($strikeCount < 3): ?>
                                        <i class="fas fa-exclamation-triangle"></i>
                                    <?php else: ?>
                                        <i class="fas fa-ban"></i>
                                    <?php endif; ?>
                                </span>
                                <div class="strike-info">
                                    <h3><?php echo $strikeLabel; ?></h3>
                                    <p class="strike-count">Strike Count: <strong><?php echo $strikeCount; ?> / 3</strong></p>
                                </div>
                            </div>
                            
                            <div class="strike-details">
                                <?php if ($strikeCount == 0): ?>
                                    <p>✓ Your account is in good standing.</p>
                                    <p>Continue to claim your orders within the time limit to maintain this status.</p>
                                <?php elseif ($strikeCount == 1): ?>
                                    <p>⚠️ You have received 1 strike for an unclaimed order.</p>
                                    <p>2 more strikes will result in account deactivation.</p>
                                <?php elseif ($strikeCount == 2): ?>
                                    <p>⚠️ You have received 2 strikes for unclaimed orders.</p>
                                    <p><strong>Warning:</strong> 1 more strike will deactivate your account.</p>
                                <?php else: ?>
                                    <p>❌ Your account has been deactivated due to 3 strikes.</p>
                                    <p>Please contact the administrator to reactivate your account.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Right Column: Countdown Timer -->
                        <?php if ($strikeStatus['temporary_block'] && $strikeStatus['remaining_cooldown'] > 0): ?>
                            <div class="cooldown-column">
                                <div class="cooldown-alert">
                                    <div class="cooldown-icon">⏳</div>
                                    <div class="cooldown-message">
                                        <p><strong>Temporary Order Restriction</strong></p>
                                        <p>You can place a new order in:</p>
                                        <div class="countdown-timer" 
                                             data-cooldown="<?php echo $strikeStatus['remaining_cooldown']; ?>"
                                             data-end-time="<?php echo time() + $strikeStatus['remaining_cooldown']; ?>">
                                            <span class="countdown-display">--:--</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

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
                
                <!-- Enable Password Change Button -->
                <button type="button" id="enable-password-change" class="enable-change-btn">
                    <i class="fas fa-key"></i> Change Password
                </button>
                
                <div class="password-requirements">
                    <p><strong>Password Requirements:</strong></p>
                    <ul>
                        <li>12-64 characters long</li>
                        <li>At least 2 numeric digits</li>
                        <li>No spaces allowed</li>
                    </ul>
                </div>

                <form method="POST" class="password-form" id="password-form">
                    <div class="form-group">
                        <label for="current_password">Current Password</label>
                        <div class="password-input-wrapper">
                            <input type="password" id="current_password" name="current_password" required disabled>
                            <button type="button" class="password-toggle" id="toggle-current-password" disabled>
                                <i class="fas fa-eye-slash"></i>
                            </button>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="new_password">New Password</label>
                        <div class="password-input-wrapper">
                            <input type="password" id="new_password" name="new_password" required disabled>
                            <button type="button" class="password-toggle" id="toggle-new-password" disabled>
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
                            <input type="password" id="confirm_password" name="confirm_password" required disabled>
                            <button type="button" class="password-toggle" id="toggle-confirm-password" disabled>
                                <i class="fas fa-eye-slash"></i>
                            </button>
                        </div>
                    </div>
                    <div class="form-actions">
                        <button type="submit" name="change_password" class="change-password-btn" disabled>Update Password</button>
                        <button type="button" id="cancel-password-change" class="cancel-password-btn" style="display: none;">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="../Javascript/cart.js"></script>
    <script src="../Javascript/profile.js"></script>
</body>
</html>
