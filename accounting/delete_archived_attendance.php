<?php
session_start();
require_once '../db_connection.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Check if all required data is provided
if (!isset($_POST['attendanceId']) || !isset($_POST['reason'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required data']);
    exit;
}

$attendanceId = intval($_POST['attendanceId']);
$reason = $_POST['reason'];
$editorId = $_SESSION['user_id'];

try {
    // Start transaction
    $conn->beginTransaction();
    
    // Get record details before deletion for logging
    $getStmt = $conn->prepare("SELECT first_name, last_name FROM archive_dtr_data WHERE ID = ?");
    $getStmt->execute([$attendanceId]);
    $recordData = $getStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$recordData) {
        echo json_encode(['success' => false, 'message' => 'Archived record not found']);
        exit;
    }
    
    // Delete from archive table
    $deleteSql = "DELETE FROM archive_dtr_data WHERE ID = ?";
    $deleteStmt = $conn->prepare($deleteSql);
    $deleteStmt->execute([$attendanceId]);
    
    // Log activity
    $activitySql = "INSERT INTO activity_logs (User_ID, Activity_Type, Activity_Details, Timestamp)
                   VALUES (?, ?, ?, NOW())";
    $activityStmt = $conn->prepare($activitySql);
    $activityStmt->execute([
        $editorId,
        'Attendance Delete Permanent',
        "Permanently deleted attendance record ID $attendanceId for {$recordData['first_name']} {$recordData['last_name']} - Reason: $reason"
    ]);
    
    // Commit transaction
    $conn->commit();
    
    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>