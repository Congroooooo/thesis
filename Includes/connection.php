<?php
// Set timezone to Philippines (adjust to your local timezone)
date_default_timezone_set('Asia/Manila');

$host = 'mysql-nicko.alwaysdata.net';
$db = 'nicko_proware';
$user = 'nicko';
$password = 'Systemx45c6';

try {
    $conn = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

} catch (PDOException $e) {
    echo "Could not connect! Error: " . $e->getMessage();
    die();
}

