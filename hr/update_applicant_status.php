<?php
// filepath: c:\xampp\htdocs\HRIS\hr\update_applicant_status.php
session_start();
require_once __DIR__ . '/../includes/session_check.php';
require_once '../db_connection.php';

if (!validateSession($conn, 3)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Check if request is POST and has required data
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

// Accept both 'id' and 'applicant_id' for backward compatibility
$applicantId = null;
if (isset($_POST['applicant_id']) && !empty($_POST['applicant_id'])) {
    $applicantId = intval($_POST['applicant_id']);
} elseif (isset($_POST['id']) && !empty($_POST['id'])) {
    $applicantId = intval($_POST['id']);
}

if (!$applicantId || !isset($_POST['status'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Applicant ID and status are required']);
    exit();
}

$newStatus = trim($_POST['status']);

// Validate status
$validStatuses = ['New', 'Contacted', 'Interview Scheduled', 'Hired', 'Rejected'];
if (!in_array($newStatus, $validStatuses)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid status']);
    exit();
}

try {
    // First, check if the applicant exists
    $checkStmt = $conn->prepare("SELECT Applicant_ID, Status FROM applicants WHERE Applicant_ID = ?");
    $checkStmt->execute([$applicantId]);
    $currentApplicant = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$currentApplicant) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Applicant not found']);
        exit();
    }
    
    // Check if status is actually changing
    if ($currentApplicant['Status'] === $newStatus) {
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => 'Status is already set to ' . $newStatus]);
        exit();
    }
    
    // Update the status
    $updateStmt = $conn->prepare("UPDATE applicants SET Status = ?, Last_Modified = NOW() WHERE Applicant_ID = ?");
    $result = $updateStmt->execute([$newStatus, $applicantId]);
    
    if ($result) {
        // Get the HR name for logging
        $hrStmt = $conn->prepare("SELECT First_Name, Last_Name FROM users WHERE User_ID = ?");
        $hrStmt->execute([$_SESSION['user_id']]);
        $hr = $hrStmt->fetch(PDO::FETCH_ASSOC);
        $hrName = ($hr ? $hr['First_Name'] . ' ' . $hr['Last_Name'] : 'Unknown HR');
        
        // Log the activity
        try {
            $logStmt = $conn->prepare("INSERT INTO activity_logs (User_ID, Activity_Type, Activity_Details, Created_At) VALUES (?, ?, ?, NOW())");
            $logStmt->execute([
                $_SESSION['user_id'], 
                'Recruitment Status Update', 
                "HR {$hrName} updated applicant ID {$applicantId} status from '{$currentApplicant['Status']}' to '{$newStatus}'"
            ]);
        } catch (PDOException $logError) {
            // Log error but don't fail the main operation
            error_log("Failed to log activity: " . $logError->getMessage());
        }
        
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => 'Status updated successfully']);
    } else {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Failed to update status']);
    }
    
} catch (PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    exit();
}
?>