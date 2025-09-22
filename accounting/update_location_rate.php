<?php
session_start();
require_once '../db_connection.php';

// Check if user is logged in and has accounting role
if (!isset($_SESSION['user_id'])) {
    $_SESSION['error_message'] = "Unauthorized access";
    header("Location: ../index.php");
    exit;
}

// Check if this is a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error_message'] = "Invalid request method";
    header("Location: rate_locations.php");
    exit;
}

// Get data from form
$locationName = $_POST['locationName'] ?? '';
$dailyRate = $_POST['dailyRate'] ?? 0;
$updateReason = $_POST['updateReason'] ?? '';
$updateAllGuards = isset($_POST['updateAllGuards']) ? true : false;

// Ensure positive rate value
$dailyRate = abs($dailyRate);

// Validate inputs
if (empty($locationName) || !is_numeric($dailyRate) || $dailyRate < 0 || empty($updateReason)) {
    $_SESSION['error_message'] = "Invalid input data. All fields are required.";
    header("Location: rate_locations.php");
    exit;
}

// Get current user information for logging
$userStmt = $conn->prepare("SELECT First_Name, Last_Name FROM users WHERE User_ID = ?");
$userStmt->execute([$_SESSION['user_id']]);
$userData = $userStmt->fetch(PDO::FETCH_ASSOC);
$userName = $userData['First_Name'] . ' ' . $userData['Last_Name'];

// Get current rate for the location
$currentRateStmt = $conn->prepare("
    SELECT AVG(daily_rate) as current_rate
    FROM guard_locations
    WHERE location_name = ? AND is_active = 1
    GROUP BY location_name
");
$currentRateStmt->execute([$locationName]);
$currentRateData = $currentRateStmt->fetch(PDO::FETCH_ASSOC);
$oldRate = $currentRateData ? $currentRateData['current_rate'] : 0;

try {
    $conn->beginTransaction();
    
    // Update all records for this location
    $updateStmt = $conn->prepare("
        UPDATE guard_locations 
        SET daily_rate = :daily_rate, updated_at = NOW() 
        WHERE location_name = :location_name AND is_active = 1
    ");
    $updateStmt->execute([
        ':daily_rate' => $dailyRate,
        ':location_name' => $locationName
    ]);
    
    // Log the activity with detailed information including reason
    $logStmt = $conn->prepare("
        INSERT INTO activity_logs (User_ID, Activity_Type, Activity_Details) 
        VALUES (?, 'Rate Update', ?)
    ");
    $activityDetails = "$userName updated daily rate for location '$locationName' from ₱" . number_format($oldRate, 2) . " to ₱" . number_format($dailyRate, 2) . " - Reason: $updateReason";
    $logStmt->execute([
        $_SESSION['user_id'],
        $activityDetails
    ]);
    
    // Add comprehensive audit log entry
    $auditStmt = $conn->prepare("
        INSERT INTO audit_logs (User_ID, Action, IP_Address, Timestamp)
        VALUES (?, ?, ?, NOW())
    ");
    $auditAction = "RATE_UPDATE: $userName changed location rate for '$locationName' from ₱" . number_format($oldRate, 2) . " to ₱" . number_format($dailyRate, 2) . " | Reason: $updateReason | Affected Guards: All guards in $locationName";
    $auditStmt->execute([
        $_SESSION['user_id'],
        $auditAction,
        $_SERVER['REMOTE_ADDR']
    ]);
    
    $conn->commit();
    
    $_SESSION['success_message'] = "Location rate for $locationName successfully updated to ₱" . number_format($dailyRate, 2);
} catch (Exception $e) {
    $conn->rollBack();
    $_SESSION['error_message'] = "Error: " . $e->getMessage();
}

header("Location: rate_locations.php");
exit;
?>