<?php
date_default_timezone_set('Asia/Manila');

$host = 'mysql-thesis.alwaysdata.net';
$db = 'thesis_proware';
$user = 'thesis';
$password = 'thesis_proware';

try {
    $conn = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->exec("SET time_zone = '+08:00'");
    
} catch (PDOException $e) {
    echo "Could not connect! Error: " . $e->getMessage();
    die();
}