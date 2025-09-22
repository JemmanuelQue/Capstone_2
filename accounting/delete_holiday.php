<?php
session_start();
require_once '../db_connection.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Check if holiday ID is provided
if (!isset($_POST['holidayId']) || empty($_POST['holidayId'])) {
    echo json_encode(['success' => false, 'message' => 'Holiday ID is required']);
    exit;
}

$holidayId = intval($_POST['holidayId']);

try {
    // Check if this is a default holiday before proceeding
    $checkStmt = $conn->prepare("SELECT is_default, holiday_name FROM holidays WHERE holiday_id = ?");
    $checkStmt->execute([$holidayId]);
    $holiday = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$holiday) {
        echo json_encode(['success' => false, 'message' => 'Holiday not found']);
        exit;
    }
    
    if ($holiday['is_default'] == 1) {
        echo json_encode([
            'success' => false, 
            'message' => 'Official holidays cannot be deleted. This ensures accurate payroll calculations.'
        ]);
        exit;
    }
    
    // Continue with deletion for non-default holidays
    // Rest of your existing code...
    $conn->beginTransaction();
    
    // Delete the holiday
    $deleteStmt = $conn->prepare("DELETE FROM holidays WHERE holiday_id = ?");
    $deleteResult = $deleteStmt->execute([$holidayId]);
    
    if (!$deleteResult) {
        throw new Exception("Failed to delete holiday from database");
    }
    
    // Log the activity
    $logMessage = "Deleted holiday: {$holiday['holiday_name']}";
    $logStmt = $conn->prepare("INSERT INTO activity_logs (User_ID, Activity_Type, Activity_Details, Timestamp) VALUES (?, ?, ?, NOW())");
    $logStmt->execute([$_SESSION['user_id'], 'Holiday Management', $logMessage]);
    
    $conn->commit();
    
    echo json_encode(['success' => true, 'message' => 'Holiday deleted successfully']);
    
} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>