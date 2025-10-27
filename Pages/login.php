<?php
require_once '../Includes/connection.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['email'];
    $password = $_POST['password'];

    $sql = "SELECT * FROM account WHERE BINARY email = :email";
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':email', $username);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        if ($user['status'] === 'inactive') {
            // Check if it's due to strikes
            if (isset($user['pre_order_strikes']) && $user['pre_order_strikes'] >= 3) {
                header("Location: login.php?error=account_inactive_strikes");
                exit();
            }
            header("Location: login.php?error=account_inactive");
            exit();
        }
        
        if (password_verify($password, $user['password'])) {
            session_start();
            $_SESSION['user_id'] = isset($user['id']) && $user['id'] !== null && $user['id'] !== ''
                ? $user['id']
                : ($user['id_number'] ?? null);
            $_SESSION['email'] = $user['email'];
            $_SESSION['role_category'] = $user['role_category'];
            $_SESSION['last_name'] = $user['last_name'];
            $_SESSION['name'] = $user['first_name'] . ' ' . $user['last_name'];

            $_SESSION['program_or_position'] = $user['program_or_position'];

            $role = strtoupper(trim($user['role_category'] ?? ''));
            $programRaw = trim($user['program_or_position'] ?? '');
            $program = strtoupper($programRaw);

            $programAbbrUpper = $program;
            try {
                $lookup = $conn->prepare("SELECT abbreviation, name FROM programs_positions WHERE name = ? OR abbreviation = ? LIMIT 1");
                $lookup->execute([$programRaw, $programRaw]);
                $pp = $lookup->fetch(PDO::FETCH_ASSOC);
                if ($pp) {
                    $programAbbrUpper = strtoupper(trim(($pp['abbreviation'] ?? '') !== '' ? $pp['abbreviation'] : ($pp['name'] ?? '')));
                }
            } catch (Exception $e) {

            }
            $_SESSION['program_abbreviation'] = $programAbbrUpper;

            $isEmployee = ($role === 'EMPLOYEE');
            $isAdminPosition = $isEmployee && ($programAbbrUpper === 'ADMIN');
            $isPamoPosition = $isEmployee && ($programAbbrUpper === 'PAMO');

            if ($isAdminPosition) {
                header("Location: ../ADMIN/admin_page.php");
                exit();
            }
            if ($isPamoPosition) {
                header("Location: ../PAMO_PAGES/dashboard.php");
                exit();
            }

            if (isset($_GET['redirect']) && $_GET['redirect'] !== '') {
                $redirect = $_GET['redirect'];
                header("Location: $redirect");
                exit();
            }

            if ($role === 'COLLEGE STUDENT' || $role === 'SHS' || $isEmployee) {
                header("Location: home.php");
                exit();
            }

            header("Location: home.php");
            exit();
        } else {
            header("Location: login.php?error=incorrect_password&email=" . urlencode($username));
            exit();
        }
    } else {
        header("Location: login.php?error=account_not_found");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>STI Login</title>
    <link rel="stylesheet" href="../CSS/login.css">
    <script src="../Javascript/login.js"></script>
</head>

<body>
    <div class="login-container">
        <div class="content-wrapper">
            <div class="logo-section">
                <div class="logo-container">
                    <img src="../Images/STI-LOGO.png" alt="STI Logo">
                </div>
            </div>
            <div class="form-container">
                <h2>Welcome Back!</h2>
                <p class="subtitle">Please login to your account</p>
                <form method="POST" action="">
                    <div class="form-group">
                        <div class="input-icon">
                            <i class="fas fa-envelope"></i>
                            <input type="email" id="email" name="email" placeholder="School Account" required 
                                value="<?php echo isset($_GET['error']) && $_GET['error'] === 'incorrect_password' ? htmlspecialchars($_GET['email']) : ''; ?>">
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="input-icon">
                            <i class="fas fa-lock"></i>
                            <input type="password" id="password" name="password" placeholder="Password" required>
                            <i class="fas fa-eye-slash toggle-password" id="togglePassword"></i>
                        </div>
                    </div>
                    <button type="submit" class="login-btn">
                        <span>Login</span>
                        <i class="fas fa-arrow-right"></i>
                    </button>
                    <?php if (isset($_GET['error'])): ?>
                        <div class="error-message">
                            <i class="fas fa-exclamation-circle"></i>
                            <?php
                            switch ($_GET['error']) {
                                case 'incorrect_password':
                                    echo 'Incorrect password. Please try again.';
                                    break;
                                case 'account_not_found':
                                    echo 'Account does not exist. Please check your email.';
                                    break;
                                case 'account_inactive_strikes':
                                    echo 'Your account has been deactivated due to multiple voided orders (3 strikes). Please contact the administrator to reactivate your account.';
                                    break;
                                case 'account_inactive':
                                    echo 'Your account is currently inactive. Please contact the administrator.';
                                    break;
                            }
                            ?>
                        </div>
                    <?php endif; ?>
                </form>
                <a href="home.php" class="back-btn">
                    <i class="fas fa-arrow-left"></i>
                    <span>Back to Home</span>
                </a>
            </div>
        </div>
    </div>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</body>
</html>