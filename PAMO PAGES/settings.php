<?php
session_start();
include 'includes/config_functions.php';
include '../includes/connection.php';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_threshold'])) {
    $newThreshold = intval($_POST['low_stock_threshold']);
    $oldThreshold = getLowStockThreshold($conn);
    if ($newThreshold > 0) {
        if (updateLowStockThreshold($conn, $newThreshold)) {
            $success_message = "Low stock threshold updated successfully!";
            // Log to audit trail
            $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
            $desc = "Low stock threshold changed from $oldThreshold to $newThreshold.";
            logActivity($conn, 'config_update', $desc, $user_id);
        } else {
            $error_message = "Failed to update low stock threshold.";
        }
    } else {
        $error_message = "Threshold must be greater than 0.";
    }
}

$current_threshold = getLowStockThreshold($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PAMO - Settings</title>
    <link rel="stylesheet" href="../PAMO CSS/styles.css">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <style>
        .settings-container {
            padding: 20px;
        }
        .settings-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .settings-title {
            font-size: 1.5em;
            margin-bottom: 20px;
            color: #333;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
        }
        .form-group input {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            max-width: 200px;
        }
        .save-btn {
            background: #007bff;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
        }
        .save-btn:hover {
            background: #0056b3;
        }
        .message {
            padding: 10px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        .success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
    </style>
</head>
<body>
    <div class="container">
        <?php include 'includes/sidebar.php'; ?>

        <main class="main-content">
            <div class="settings-container">
                <div class="settings-card">
                    <div class="settings-header" style="display: flex; align-items: center; gap: 12px; margin-bottom: 18px;">
                        <i class="material-icons" style="font-size: 2.2em; color: #007bff;">settings</i>
                        <div>
                            <h2 class="settings-title" style="margin: 0;">System Settings</h2>
                            <p style="margin: 2px 0 0 0; color: #666; font-size: 1.08em;">Configure system-wide options for your inventory management.</p>
                        </div>
                    </div>
                    <?php if (isset($success_message)): ?>
                        <div class="message success" style="display: flex; align-items: center; gap: 8px;"><i class="material-icons">check_circle</i> <?php echo $success_message; ?></div>
                    <?php endif; ?>
                    <?php if (isset($error_message)): ?>
                        <div class="message error" style="display: flex; align-items: center; gap: 8px;"><i class="material-icons">error</i> <?php echo $error_message; ?></div>
                    <?php endif; ?>
                    <form method="POST" style="max-width: 400px; margin-top: 18px;">
                        <div class="form-group" style="margin-bottom: 24px;">
                            <label for="low_stock_threshold" style="font-size: 1.1em;">Low Stock Threshold</label>
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <input type="number" id="low_stock_threshold" name="low_stock_threshold" value="<?php echo $current_threshold; ?>" min="1" required style="flex: 1; font-size: 1.1em; padding: 10px; border: 1.5px solid #bfc9d1; border-radius: 6px;">
                                <span style="color: #888; font-size: 1.1em;">units</span>
                            </div>
                            <small style="color: #888; margin-top: 6px; display: block;">Items with quantity at or below this number will be marked as <b>Low Stock</b>.</small>
                        </div>
                        <button type="submit" name="update_threshold" class="save-btn" style="width: 100%; font-size: 1.1em; display: flex; align-items: center; justify-content: center; gap: 8px;">
                            <i class="material-icons">save</i> Save Changes
                        </button>
                    </form>
                </div>
            </div>
        </main>
    </div>
</body>
</html> 