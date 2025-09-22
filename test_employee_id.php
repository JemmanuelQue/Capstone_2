<?php
// Simple test for employee ID duplicate checking
require_once 'db_connection.php';

if ($_POST['action'] === 'check_employee_id_duplicate') {
    try {
        $employee_id = $_POST['employee_id'] ?? '';
        
        if (empty($employee_id)) {
            echo json_encode(['duplicate' => false, 'message' => 'Employee ID is required']);
            exit;
        }
        
        $sql = "SELECT COUNT(*) as count FROM users WHERE employee_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$employee_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'duplicate' => $result['count'] > 0, 
            'message' => $result['count'] > 0 ? 'This Employee ID is already taken' : 'Employee ID is available'
        ]);
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
} else {
    echo json_encode(['error' => 'Invalid action']);
}
?>
