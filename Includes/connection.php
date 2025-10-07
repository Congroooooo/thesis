<?php
$host = 'mysql-nicko.alwaysdata.net';
$db = 'nicko_proware';
$user = 'nicko';
$password = 'nicko_proware';

try {
    $conn = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Set MySQL timezone to match PHP timezone
    $conn->exec("SET time_zone = '+08:00'");
    
} catch (PDOException $e) {
    echo "Could not connect! Error: " . $e->getMessage();
    die();
}