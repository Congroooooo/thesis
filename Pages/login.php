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
            header("Location: login.php?error=account_inactive");
            exit();
        }
        if (password_verify($password, $user['password'])) {
            session_start();
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['role_category'] = $user['role_category'];
            $_SESSION['last_name'] = $user['last_name'];
            // Set the full name for PAMO user display
            $_SESSION['name'] = $user['first_name'] . ' ' . $user['last_name'];

            // Check if there's a redirect parameter
            if (isset($_GET['redirect'])) {
                $redirect = $_GET['redirect'];
                header("Location: $redirect");
                exit();
            }

            if ($user['role_category'] === 'COLLEGE STUDENT' || $user['role_category'] === 'SHS' || $user['role_category'] === 'EMPLOYEE' && $user['program_or_position'] === 'TEACHER') {
                header("Location: home.php");
                exit();
            } else if ($user['program_or_position'] === 'PAMO') {
                header("Location: ../PAMO PAGES/dashboard.php");
                exit();
            } else if ($user['program_or_position'] === 'ADMIN') {
                header("Location: ../ADMIN/admin_page.php");
                exit();
            }
        } else {
            header("Location: login.php?error=incorrect_password");
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
                            <input type="email" id="email" name="email" placeholder="School Account" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="input-icon">
                            <i class="fas fa-lock"></i>
                            <input type="password" id="password" name="password" placeholder="Password" required>
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
    <!-- Add Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</body>
</html>