n <?php
session_start();
require_once '../db_connection.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Check if all required data is provided
if (!isset($_POST['attendanceId']) || !isset($_POST['userId']) || !isset($_POST['reason'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required data']);
    exit;
}

$attendanceId = intval($_POST['attendanceId']);
$userId = intval($_POST['userId']);
$reason = $_POST['reason'];
$editorId = $_SESSION['user_id'];

try {
    // Start transaction
    $conn->beginTransaction();
    
    // Get archived record details
    $getStmt = $conn->prepare("SELECT * FROM archive_dtr_data WHERE ID = ?");
    $getStmt->execute([$attendanceId]);
    $archiveData = $getStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$archiveData) {
        echo json_encode(['success' => false, 'message' => 'Archived record not found']);
        exit;
    }
    
    // Restore to attendance table
    $restoreSql = "INSERT INTO attendance 
                  (ID, User_ID, Time_In, Time_Out, Created_At, Updated_At) 
                  VALUES (?, ?, ?, ?, NOW(), NOW())";
    $restoreStmt = $conn->prepare($restoreSql);
    $restoreStmt->execute([
        $archiveData['ID'],
        $userId,
        $archiveData['time_in'],
        $archiveData['time_out'],
    ]);
    
    // Delete from archive table
    $deleteSql = "DELETE FROM archive_dtr_data WHERE ID = ?";
    $deleteStmt = $conn->prepare($deleteSql);
    $deleteStmt->execute([$attendanceId]);
    
    // Get editor's name for logging
    $editorStmt = $conn->prepare("SELECT CONCAT(First_Name, ' ', Last_Name) as editor_name FROM users WHERE User_ID = ?");
    $editorStmt->execute([$editorId]);
    $editorData = $editorStmt->fetch(PDO::FETCH_ASSOC);
    $editorName = $editorData ? $editorData['editor_name'] : 'Unknown';
    
    // Log activity
    $activitySql = "INSERT INTO activity_logs (User_ID, Activity_Type, Activity_Details, Timestamp)
                   VALUES (?, ?, ?, NOW())";
    $activityStmt = $conn->prepare($activitySql);
    $activityStmt->execute([
        $editorId,
        'Attendance Restore',
        "Restored attendance record ID $attendanceId for {$archiveData['first_name']} {$archiveData['last_name']} - Reason: $reason"
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