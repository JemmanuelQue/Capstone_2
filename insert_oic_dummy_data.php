<?php
require_once 'db_connection.php';

try {
    // Start transaction
    $conn->beginTransaction();
    
    // Hash the password 'christina_828'
    $password_hash = password_hash('christina_828', PASSWORD_DEFAULT);
    
    // OIC data with locations
    $oic_data = [
        [
            'username' => 'maria.santos',
            'first_name' => 'Maria',
            'last_name' => 'Santos',
            'middle_name' => 'Dela Cruz',
            'phone_number' => '09171234567',
            'birthday' => '1985-03-20',
            'sex' => 'Female',
            'civil_status' => 'Married',
            'employee_id' => 'OIC001',
            'hired_date' => '2024-01-15',
            'location' => 'Manila'
        ],
        [
            'username' => 'carlos.rivera',
            'first_name' => 'Carlos',
            'last_name' => 'Rivera',
            'middle_name' => 'Mendoza',
            'phone_number' => '09187654321',
            'birthday' => '1980-07-15',
            'sex' => 'Male',
            'civil_status' => 'Single',
            'employee_id' => 'OIC002',
            'hired_date' => '2024-02-01',
            'location' => 'Bulacan'
        ],
        [
            'username' => 'ana.garcia',
            'first_name' => 'Ana',
            'last_name' => 'Garcia',
            'middle_name' => 'Lopez',
            'phone_number' => '09199876543',
            'birthday' => '1988-11-08',
            'sex' => 'Female',
            'civil_status' => 'Single',
            'employee_id' => 'OIC003',
            'hired_date' => '2024-03-10',
            'location' => 'Batangas'
        ],
        [
            'username' => 'john.torres',
            'first_name' => 'John',
            'last_name' => 'Torres',
            'middle_name' => 'Villanueva',
            'phone_number' => '09166789012',
            'birthday' => '1982-05-25',
            'sex' => 'Male',
            'civil_status' => 'Married',
            'employee_id' => 'OIC004',
            'hired_date' => '2024-04-05',
            'location' => 'Laguna'
        ],
        [
            'username' => 'elena.cruz',
            'first_name' => 'Elena',
            'last_name' => 'Cruz',
            'middle_name' => 'Reyes',
            'phone_number' => '09123456789',
            'birthday' => '1987-09-12',
            'sex' => 'Female',
            'civil_status' => 'Married',
            'employee_id' => 'OIC005',
            'hired_date' => '2024-05-20',
            'location' => 'Cavite'
        ]
    ];
    
    // Function to generate email based on location
    function generateEmail($location) {
        $location_lower = strtolower($location);
        return "oic-{$location_lower}@gmail.com";
    }
    
    echo "<h2>Inserting OIC Dummy Data</h2>\n";
    echo "<pre>\n";
    
    // Insert OIC users
    $user_insert_sql = "INSERT INTO users (Username, Email, Password_Hash, Role_ID, hired_date, First_Name, Last_Name, middle_name, phone_number, birthday, sex, civil_status, status, employee_id) VALUES (?, ?, ?, 8, ?, ?, ?, ?, ?, ?, ?, ?, 'Active', ?)";
    $user_stmt = $conn->prepare($user_insert_sql);
    
    $inserted_users = [];
    
    foreach ($oic_data as $oic) {
        $email = generateEmail($oic['location']);
        
        // Insert user
        $user_stmt->execute([
            $oic['username'],
            $email,
            $password_hash,
            $oic['hired_date'],
            $oic['first_name'],
            $oic['last_name'],
            $oic['middle_name'],
            $oic['phone_number'],
            $oic['birthday'],
            $oic['sex'],
            $oic['civil_status'],
            $oic['employee_id']
        ]);
        
        $user_id = $conn->lastInsertId();
        $inserted_users[] = [
            'user_id' => $user_id,
            'name' => $oic['first_name'] . ' ' . $oic['last_name'],
            'location' => $oic['location'],
            'email' => $email,
            'username' => $oic['username']
        ];
        
        echo "✓ Inserted OIC: {$oic['first_name']} {$oic['last_name']} (ID: {$user_id})\n";
        echo "  Email: {$email}\n";
        echo "  Username: {$oic['username']}\n";
        echo "  Password: christina_828\n";
        echo "  Location: {$oic['location']}\n\n";
    }
    
    // Insert OIC location assignments
    $location_insert_sql = "INSERT INTO oic_locations (oic_user_id, location_name, assigned_by, assigned_at, is_active) VALUES (?, ?, 1, NOW(), 1)";
    $location_stmt = $conn->prepare($location_insert_sql);
    
    echo "Assigning OICs to locations:\n";
    foreach ($inserted_users as $user) {
        $location_stmt->execute([
            $user['user_id'],
            $user['location']
        ]);
        echo "✓ Assigned {$user['name']} to {$user['location']}\n";
        
        // Add activity log entry
        $activity_sql = "INSERT INTO activity_logs (User_ID, Activity_Type, Activity_Details, Timestamp) VALUES (1, 'OIC Creation', ?, NOW())";
        $activity_stmt = $conn->prepare($activity_sql);
        $activity_details = "Created new OIC: {$user['name']} assigned to {$user['location']}";
        $activity_stmt->execute([$activity_details]);
    }
    
    // Add Carlos Rivera to additional location (Pampanga) as example
    $carlos_user = null;
    foreach ($inserted_users as $user) {
        if (strpos($user['name'], 'Carlos') !== false) {
            $carlos_user = $user;
            break;
        }
    }
    
    if ($carlos_user) {
        $location_stmt->execute([
            $carlos_user['user_id'],
            'Pampanga'
        ]);
        echo "✓ Assigned {$carlos_user['name']} to additional location: Pampanga\n";
        
        // Add activity log for additional assignment
        $activity_sql = "INSERT INTO activity_logs (User_ID, Activity_Type, Activity_Details, Timestamp) VALUES (1, 'OIC Assignment', ?, NOW())";
        $activity_stmt = $conn->prepare($activity_sql);
        $activity_details = "Assigned {$carlos_user['name']} to additional location: Pampanga";
        $activity_stmt->execute([$activity_details]);
    }
    
    // Add system update logs
    $system_logs = [
        "Added new role: OIC (Officer in Charge) - Role_ID: 8",
        "Created oic_locations table for OIC location management",
        "Inserted 5 OIC dummy users with location assignments"
    ];
    
    foreach ($system_logs as $log) {
        $activity_sql = "INSERT INTO activity_logs (User_ID, Activity_Type, Activity_Details, Timestamp) VALUES (1, 'System Update', ?, NOW())";
        $activity_stmt = $conn->prepare($activity_sql);
        $activity_stmt->execute([$log]);
    }
    
    // Commit transaction
    $conn->commit();
    
    echo "\n=== INSERTION COMPLETED SUCCESSFULLY ===\n\n";
    
    // Display summary
    echo "SUMMARY:\n";
    echo "--------\n";
    echo "Total OICs created: " . count($inserted_users) . "\n";
    echo "Location assignments: " . (count($inserted_users) + 1) . " (Carlos has 2 locations)\n\n";
    
    echo "LOGIN CREDENTIALS:\n";
    echo "------------------\n";
    foreach ($inserted_users as $user) {
        echo "Name: {$user['name']}\n";
        echo "Email: {$user['email']}\n";
        echo "Username: {$user['username']}\n";
        echo "Password: christina_828\n";
        echo "Location: {$user['location']}\n";
        echo "---\n";
    }
    
    echo "\nNOTE: You can now test login with any of the above credentials.\n";
    echo "Remember to update login_processing.php to handle Role_ID 8 (OIC) redirects.\n";
    
    // Verification queries
    echo "\n=== VERIFICATION ===\n";
    
    // Check inserted OICs
    $verify_sql = "SELECT User_ID, Username, Email, CONCAT(First_Name, ' ', Last_Name) as Full_Name, Role_ID FROM users WHERE Role_ID = 8";
    $verify_stmt = $conn->prepare($verify_sql);
    $verify_stmt->execute();
    $oic_users = $verify_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "OIC Users in database:\n";
    foreach ($oic_users as $oic) {
        echo "- ID: {$oic['User_ID']}, Name: {$oic['Full_Name']}, Username: {$oic['Username']}, Email: {$oic['Email']}\n";
    }
    
    // Check location assignments
    $location_verify_sql = "SELECT ol.*, CONCAT(u.First_Name, ' ', u.Last_Name) as OIC_Name FROM oic_locations ol JOIN users u ON ol.oic_user_id = u.User_ID WHERE ol.is_active = 1";
    $location_verify_stmt = $conn->prepare($location_verify_sql);
    $location_verify_stmt->execute();
    $assignments = $location_verify_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "\nLocation assignments:\n";
    foreach ($assignments as $assignment) {
        echo "- {$assignment['OIC_Name']} manages {$assignment['location_name']}\n";
    }
    
    echo "</pre>\n";
    
} catch (PDOException $e) {
    // Rollback transaction on error
    $conn->rollback();
    echo "<div style='color: red;'>";
    echo "<h3>Error occurred:</h3>";
    echo "<p>" . $e->getMessage() . "</p>";
    echo "<p>Transaction rolled back. No data was inserted.</p>";
    echo "</div>";
} catch (Exception $e) {
    $conn->rollback();
    echo "<div style='color: red;'>";
    echo "<h3>General error occurred:</h3>";
    echo "<p>" . $e->getMessage() . "</p>";
    echo "<p>Transaction rolled back. No data was inserted.</p>";
    echo "</div>";
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>OIC Dummy Data Insertion</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        pre { background: #f5f5f5; padding: 15px; border-radius: 5px; }
        .success { color: green; }
        .error { color: red; }
    </style>
</head>
<body>
    <h1>OIC Dummy Data Insertion Script</h1>
    <p><strong>Note:</strong> This script should only be run once. If you need to run it again, please delete the existing OIC data first.</p>
    
    <h3>Next Steps:</h3>
    <ol>
        <li>Update <code>login_processing.php</code> to handle OIC role redirects</li>
        <li>Create/update the OIC dashboard and performance evaluation pages</li>
        <li>Test login with the created OIC accounts</li>
    </ol>
    
    <h3>To Update login_processing.php:</h3>
    <p>Add this case to the role switch statement:</p>
    <pre>case 8: // OIC
    header("Location: oic/dashboard.php");
    break;</pre>
</body>
</html>