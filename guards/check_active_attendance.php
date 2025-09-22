<?php
session_start();
require_once __DIR__ . '/../includes/session_check.php';
require_once '../db_connection.php';

// Set content type to JSON
header('Content-Type: application/json');

// Check if user is logged in and valid session
if (!validateSession($conn, 5, false)) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Not logged in'
    ]);
    exit;
}

try {
    // Get the active attendance record
    $stmt = $conn->prepare("
        SELECT ID, Time_In, TIMESTAMPDIFF(MINUTE, Time_In, NOW()) as time_diff_minutes
        FROM attendance 
        WHERE User_ID = ? AND Time_Out IS NULL
        ORDER BY Time_In DESC LIMIT 1
    ");
    $stmt->execute([$_SESSION['user_id']]);
    
    $record = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($record) {
        echo json_encode([
            'status' => 'success',
            'attendance_id' => $record['ID'],
            'time_in' => $record['Time_In'],
            'time_diff_minutes' => $record['time_diff_minutes']
        ]);
    } else {
        echo json_encode([
            'status' => 'error',
            'message' => 'No active attendance record found'
        ]);
    }
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?>