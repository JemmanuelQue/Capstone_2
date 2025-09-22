<?php
session_start();
require_once '../db_connection.php';

// Set proper content type and error reporting
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't show errors to client

// Log function for debugging
function debug_log($message) {
    error_log("HOLIDAY DEBUG: " . $message);
}

debug_log("Script started");

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Not logged in']);
    exit;
}

// Get start and end dates from request
$startDate = isset($_GET['start']) ? $_GET['start'] : date('Y-01-01');
$endDate = isset($_GET['end']) ? $_GET['end'] : date('Y-12-31');
$year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');

debug_log("Fetching holidays from $startDate to $endDate for year $year");

// Get holidays from database
try {
    // Check holidays table structure
    $tableStmt = $conn->query("SHOW COLUMNS FROM holidays");
    $columns = $tableStmt->fetchAll(PDO::FETCH_COLUMN);
    debug_log("Holiday table columns: " . implode(", ", $columns));
    
    // Get all holidays in the date range directly (don't rely on populate function)
    $stmt = $conn->prepare("SELECT * FROM holidays WHERE holiday_date BETWEEN ? AND ?");
    $stmt->execute([$startDate, $endDate]);
    $holidays = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    debug_log("Found " . count($holidays) . " holidays in the date range");
    
    echo json_encode($holidays);
} catch (PDOException $e) {
    debug_log("Database error: " . $e->getMessage());
    echo json_encode(['error' => $e->getMessage()]);
}
?>