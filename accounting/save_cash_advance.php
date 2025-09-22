<?php
session_start();
header('Content-Type: application/json');
require_once '../db_connection.php';

// Check if user is logged in with appropriate role
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role_id'], [1, 2, 3, 4])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Check if request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Get and validate input
$user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
$cash_advance = isset($_POST['cash_advance']) ? floatval($_POST['cash_advance']) : 0;
$start_date = isset($_POST['start_date']) ? $_POST['start_date'] : null;
$end_date = isset($_POST['end_date']) ? $_POST['end_date'] : null;

// Validate inputs
if (!$user_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid user ID']);
    exit;
}

if ($cash_advance < 0) {
    echo json_encode(['success' => false, 'message' => 'Negative cash advance values are not allowed']);
    exit;
}

if ($cash_advance > 1000) {
    echo json_encode(['success' => false, 'message' => 'Cash advance cannot exceed ₱1,000']);
    exit;
}

if (!$start_date || !$end_date) {
    echo json_encode(['success' => false, 'message' => 'Invalid date range']);
    exit;
}

try {
    // Check if a payroll record already exists
    $check_sql = "SELECT ID FROM payroll WHERE User_ID = ? AND Period_Start = ? AND Period_End = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->execute([$user_id, $start_date, $end_date]);
    $existing_id = $check_stmt->fetchColumn();
    
    if ($existing_id) {
        // Update existing record
        $update_sql = "UPDATE payroll SET Cash_Advances = ? WHERE ID = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->execute([$cash_advance, $existing_id]);
    } else {
        // Insert new record
        $insert_sql = "INSERT INTO payroll (User_ID, Period_Start, Period_End, Cash_Advances, Reg_Hours, Reg_Earnings, Gross_Pay, Net_Salary)
                      VALUES (?, ?, ?, ?, 0, 0, 0, 0)";
        $insert_stmt = $conn->prepare($insert_sql);
        $insert_stmt->execute([$user_id, $start_date, $end_date, $cash_advance]);
    }
    
    echo json_encode(['success' => true]);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}