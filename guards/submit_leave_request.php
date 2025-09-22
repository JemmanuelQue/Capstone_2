<?php
session_start();
require_once __DIR__ . '/../includes/session_check.php';
require_once '../db_connection.php';
if (!validateSession($conn, 5)) { exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = $_SESSION['user_id'];
    $leaveType = $_POST['leaveType'];
    $startDate = $_POST['startDate'];
    $endDate = $_POST['endDate'];
    $reason = $_POST['leaveReason'];
    
    // Validate inputs
    if (empty($leaveType) || empty($startDate) || empty($endDate) || empty($reason)) {
        header("Location: leave_request.php?error=2&message=" . urlencode("All fields are required"));
        exit;
    }
    
    if (strtotime($startDate) > strtotime($endDate)) {
        header("Location: leave_request.php?error=3&message=" . urlencode("End date cannot be before start date"));
        exit;
    }
    
    try {
        // Check for duplicate leave request
        $checkDuplicate = $conn->prepare("
            SELECT ID FROM leave_requests 
            WHERE User_ID = ? 
            AND Leave_Type = ?
            AND Start_Date = ?
            AND End_Date = ?
            AND Leave_Reason = ?
            AND Request_Date >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ");
        
        $checkDuplicate->execute([$userId, $leaveType, $startDate, $endDate, $reason]);
        
        if ($checkDuplicate->rowCount() > 0) {
            // Duplicate request found
            header("Location: leave_request.php?error=4&message=" . urlencode("You've already submitted an identical leave request. Please wait for approval."));
            exit;
        }
        
        // Check for overlapping leave request
        $checkOverlap = $conn->prepare("
            SELECT ID FROM leave_requests 
            WHERE User_ID = ? 
            AND ((Start_Date BETWEEN ? AND ?) OR (End_Date BETWEEN ? AND ?) 
                OR (Start_Date <= ? AND End_Date >= ?))
            AND Status != 'Rejected'
        ");
        
        $checkOverlap->execute([$userId, $startDate, $endDate, $startDate, $endDate, $startDate, $endDate]);
        
        if ($checkOverlap->rowCount() > 0) {
            // Overlapping request found
            header("Location: leave_request.php?error=5&message=" . urlencode("You already have a leave request that overlaps with these dates."));
            exit;
        }
        
        // Insert the leave request if no duplicates or overlaps
        $stmt = $conn->prepare("INSERT INTO leave_requests (User_ID, Leave_Type, Leave_Reason, Start_Date, End_Date) 
                                VALUES (?, ?, ?, ?, ?)");
        $result = $stmt->execute([$userId, $leaveType, $reason, $startDate, $endDate]);
        
        if ($result) {
            // Redirect back with success message
            header("Location: leave_request.php?success=1");
            exit;
        } else {
            // Redirect back with error
            header("Location: leave_request.php?error=1&message=" . urlencode("Failed to submit leave request. Please try again."));
            exit;
        }
    } catch (PDOException $e) {
        // Redirect back with error
        header("Location: leave_request.php?error=1&message=" . urlencode("Database error: " . $e->getMessage()));
        exit;
    }
} else {
    // Not a POST request
    header("Location: leave_request.php");
    exit;
}
?>