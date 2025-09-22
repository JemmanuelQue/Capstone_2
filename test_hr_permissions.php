<?php
session_start();
require_once 'db_connection.php';
require_once 'includes/session_check.php';

// Simulate HR user session for testing
$_SESSION['user_id'] = 8; // Assuming user ID 8 is HR
$_SESSION['role_id'] = 3; // HR role

echo "<h1>HR Employee Editing Permission Test</h1>";

// Test 1: Check if HR can access edit forms for different user roles
echo "<h2>Test 1: Access Edit Forms</h2>";

// Get users of different roles
$roles_query = "SELECT u.User_ID, u.First_Name, u.Last_Name, r.Role_Name, u.Role_ID 
                FROM users u 
                JOIN roles r ON u.Role_ID = r.Role_ID 
                WHERE u.archived_at IS NULL 
                ORDER BY u.Role_ID 
                LIMIT 5";
$stmt = $conn->prepare($roles_query);
$stmt->execute();
$test_users = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($test_users as $test_user) {
    echo "<h3>Testing access to {$test_user['Role_Name']} user: {$test_user['First_Name']} {$test_user['Last_Name']}</h3>";
    
    // Simulate the get_edit_form request
    $_GET['action'] = 'get_edit_form';
    $_GET['user_id'] = $test_user['User_ID'];
    
    ob_start();
    include 'hr/employee_management.php';
    $output = ob_get_clean();
    
    if (strpos($output, 'You cannot edit admin or HR employees') !== false) {
        echo "<p style='color: red;'>❌ BLOCKED: Cannot edit this user</p>";
    } elseif (strpos($output, 'Employee ID') !== false) {
        echo "<p style='color: green;'>✅ ALLOWED: Can edit this user (Employee ID field found)</p>";
    } else {
        echo "<p style='color: orange;'>⚠️ UNCLEAR: " . substr(strip_tags($output), 0, 100) . "...</p>";
    }
    
    // Clear the GET parameters
    unset($_GET['action'], $_GET['user_id']);
}

echo "<h2>Test 2: Check Employee ID Editing Capability</h2>";

// Test if checkEmployeeIdDuplicate function works
$_POST['action'] = 'check_employee_id_duplicate';
$_POST['employee_id'] = 'TEST123';
$_POST['exclude_user_id'] = '1';

ob_start();
include 'hr/employee_management.php';
$duplicate_check = ob_get_clean();

echo "<p>Employee ID duplicate check result: " . $duplicate_check . "</p>";

// Clear POST parameters
unset($_POST['action'], $_POST['employee_id'], $_POST['exclude_user_id']);

echo "<h2>Summary</h2>";
echo "<p>If all tests show ✅ ALLOWED, then HR can now edit employee IDs for all user levels.</p>";
?>
