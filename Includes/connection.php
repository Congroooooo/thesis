<?php
// Database connection
$host = 'localhost';
$db = 'proware';
$user = 'root';
$password = '';

try {
    $conn = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Create system_config table if it doesn't exist
    $conn->exec("CREATE TABLE IF NOT EXISTS system_config (
        id INT PRIMARY KEY AUTO_INCREMENT,
        config_key VARCHAR(50) UNIQUE NOT NULL,
        config_value TEXT NOT NULL,
        description TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");

    // Insert default low stock threshold if it doesn't exist
    $stmt = $conn->prepare("INSERT IGNORE INTO system_config (config_key, config_value, description) 
                           VALUES ('low_stock_threshold', '10', 'Threshold for low stock items')");
    $stmt->execute();

} catch (PDOException $e) {
    echo "Could not connect! Error: " . $e->getMessage();
    die();
}

