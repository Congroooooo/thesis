<?php
$host = 'localhost';
$db = 'proware';
$user = 'root';
$password = '';

try {
    $conn = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

} catch (PDOException $e) {
    echo "Could not connect! Error: " . $e->getMessage();
    die();
}