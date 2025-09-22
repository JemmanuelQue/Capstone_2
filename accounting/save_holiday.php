<?php
session_start();
require_once '../db_connection.php';

// Check if user is logged in and has accounting role
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Validate input
if (!isset($_POST['holidayName']) || !isset($_POST['holidayDate']) || !isset($_POST['holidayType'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

$holidayName = trim($_POST['holidayName']);
$holidayDate = $_POST['holidayDate'];
$holidayType = $_POST['holidayType'];

// Validate date format and holiday type
if (!preg_match("/^\d{4}-\d{2}-\d{2}$/", $holidayDate)) {
    echo json_encode(['success' => false, 'message' => 'Invalid date format']);
    exit;
}

$validTypes = ['Regular', 'Special Non-Working', 'Special Working'];
if (!in_array($holidayType, $validTypes)) {
    echo json_encode(['success' => false, 'message' => 'Invalid holiday type']);
    exit;
}

try {
    // Get user details for activity log
    $userStmt = $conn->prepare("SELECT First_Name, Last_Name FROM users WHERE User_ID = ?");
    $userStmt->execute([$_SESSION['user_id']]);
    $userData = $userStmt->fetch(PDO::FETCH_ASSOC);
    $userName = $userData['First_Name'] . ' ' . $userData['Last_Name'];
    
    // Begin transaction
    $conn->beginTransaction();
    
    // Check if holiday already exists
    $checkStmt = $conn->prepare("SELECT holiday_id FROM holidays WHERE holiday_date = ?");
    $checkStmt->execute([$holidayDate]);
    
    if ($checkStmt->rowCount() > 0) {
        // Update existing holiday
        $updateStmt = $conn->prepare("UPDATE holidays SET holiday_name = ?, holiday_type = ?, updated_at = NOW() WHERE holiday_date = ?");
        $updateStmt->execute([$holidayName, $holidayType, $holidayDate]);
        $message = "Updated holiday: $holidayName ($holidayDate)";
    } else {
        // Insert new holiday
        $insertStmt = $conn->prepare("INSERT INTO holidays (holiday_date, holiday_name, holiday_type, created_at) VALUES (?, ?, ?, NOW())");
        $insertStmt->execute([$holidayDate, $holidayName, $holidayType]);
        $message = "Added new holiday: $holidayName ($holidayDate)";
    }
    
    // Log activity
    $logStmt = $conn->prepare("INSERT INTO activity_logs (User_ID, Activity_Type, Activity_Details, Timestamp) VALUES (?, ?, ?, NOW())");
    $logStmt->execute([$_SESSION['user_id'], 'Holiday Management', $message]);
    
    // Commit transaction
    $conn->commit();
    
    echo json_encode(['success' => true, 'message' => 'Holiday saved successfully']);
    
} catch (PDOException $e) {
    // Roll back transaction on error
    $conn->rollBack();
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}