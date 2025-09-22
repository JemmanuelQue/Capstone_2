<?php
// Set timezone to Philippine time
date_default_timezone_set('Asia/Manila');

$host = "localhost";
$dbname = "green_meadows_db";
$username = "root";
$password = "";

try {
    // Create a PDO instance
    $conn = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    // Set error mode to exception
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Set MySQL timezone to Philippine time
    $conn->exec("SET time_zone = '+08:00'");
    
    // Uncomment to debug connection issues
    // echo "Connected successfully";
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?>