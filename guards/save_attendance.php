<?php
// filepath: c:\xampp\htdocs\HRIS\guards\save_attendance.php
session_start();
require_once __DIR__ . '/../includes/session_check.php';
require_once '../db_connection.php';

// Set the content type to JSON
header('Content-Type: application/json');

// Check if user is logged in and session is valid
if (!validateSession($conn, 5, false)) {
    echo json_encode([
        'status' => 'error',
        'message' => 'User not logged in'
    ]);
    exit;
}

// Get JSON data from request body
$json_data = file_get_contents('php://input');
$data = json_decode($json_data, true);

// Check if JSON is valid
if ($data === null) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid JSON data: ' . json_last_error_msg()
    ]);
    exit;
}

// Validate required fields
$required_fields = ['action', 'latitude', 'longitude', 'faceImage', 'faceDescriptor', 'userId'];
$missing_fields = [];

foreach ($required_fields as $field) {
    if (!isset($data[$field]) || empty($data[$field])) {
        $missing_fields[] = $field;
    }
}

if (count($missing_fields) > 0) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Missing required data: ' . implode(', ', $missing_fields)
    ]);
    exit;
}

try {
    // CRITICAL SECURITY: Server-side face descriptor validation
    $user_id = $data['userId'];
    
    // Get stored face descriptor from database
    $face_stmt = $conn->prepare("SELECT face_descriptor FROM guard_faces WHERE guard_id = ?");
    $face_stmt->execute([$user_id]);
    $stored_face = $face_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$stored_face || !$stored_face['face_descriptor']) {
        echo json_encode([
            'status' => 'error',
            'message' => 'No registered face profile found for this user'
        ]);
        exit;
    }
    
    // Parse stored face descriptor
    $stored_descriptor = json_decode($stored_face['face_descriptor'], true);
    if (!$stored_descriptor || !is_array($stored_descriptor)) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Invalid stored face descriptor'
        ]);
        exit;
    }
    
    // Validate submitted face descriptor
    $submitted_descriptor = $data['faceDescriptor'];
    if (!is_array($submitted_descriptor) || count($submitted_descriptor) !== count($stored_descriptor)) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Invalid face descriptor format'
        ]);
        exit;
    }
    
    // Calculate Euclidean distance between descriptors
    $sum_squared_diff = 0;
    for ($i = 0; $i < count($stored_descriptor); $i++) {
        $diff = $stored_descriptor[$i] - $submitted_descriptor[$i];
        $sum_squared_diff += $diff * $diff;
    }
    $distance = sqrt($sum_squared_diff);
    
    // Use strict threshold for server-side validation
    $max_allowed_distance = 0.4; // Same as frontend threshold
    
    error_log("Face validation - Distance: $distance, Threshold: $max_allowed_distance");
    
    if ($distance > $max_allowed_distance) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Face verification failed. Distance: ' . number_format($distance, 4) . ' exceeds threshold: ' . $max_allowed_distance
        ]);
        exit;
    }
    
    // Log successful face validation
    error_log("Face validation successful for user $user_id - Distance: $distance");
    
    // Start transaction
    $conn->beginTransaction();

    // Get the current date (Y-m-d format)
    $today = date('Y-m-d');
    $today_start = $today . ' 00:00:00';
    $today_end = $today . ' 23:59:59';
    
    if ($data['action'] === 'time_in') {
        // First, check if user already has an active time-in
        $check_stmt = $conn->prepare("
            SELECT id 
            FROM attendance 
            WHERE User_ID = ? 
            AND Time_In IS NOT NULL 
            AND Time_Out IS NULL
        ");
        $check_stmt->execute([$data['userId']]);
        
        if ($check_stmt->rowCount() > 0) {
            throw new Exception('You already have an active time-in record. Please time-out first.');
        }
        
        // Check if user already has completed attendance for today
        $check_today_stmt = $conn->prepare("
            SELECT ID 
            FROM attendance 
            WHERE User_ID = ? 
            AND Time_In BETWEEN ? AND ? 
            AND Time_Out IS NOT NULL
        ");
        $check_today_stmt->execute([$data['userId'], $today_start, $today_end]);
        
        if ($check_today_stmt->rowCount() > 0) {
            throw new Exception('You have already completed attendance for today. You cannot clock in again on the same day.');
        }

        // Create uploads directory if it doesn't exist
        $upload_dir = '../uploads/attendance';
        if (!file_exists($upload_dir)) {
            if (!mkdir($upload_dir, 0755, true)) {
                throw new Exception('Failed to create upload directory');
            }
        }

        // Ensure directory is writable
        if (!is_writable($upload_dir)) {
            throw new Exception('Upload directory is not writable');
        }

        // Save face image to file system
        $image_data = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $data['faceImage']));
        $image_path = $upload_dir . '/' . time() . '_' . $data['userId'] . '.jpg';
        
        if (file_put_contents($image_path, $image_data) === false) {
            throw new Exception('Failed to save image file');
        }

        // Convert face descriptor array to string for storage
        $face_descriptor = json_encode($data['faceDescriptor']);

        // Insert time-in record
        $stmt = $conn->prepare("
            INSERT INTO attendance (
                User_ID,
                Time_In,
                Latitude,
                Longitude,
                IP_Address,
                verification_image_path,
                face_verified,
                Created_At,
                location_verified
            ) VALUES (?, NOW(), ?, ?, ?, ?, 1, NOW(), 1)
        ");
        
        $result = $stmt->execute([
            $data['userId'],
            $data['latitude'],
            $data['longitude'],
            $data['ip'],
            $image_path
        ]);
        
        if (!$result) {
            throw new Exception('Failed to insert attendance record: ' . implode(', ', $stmt->errorInfo()));
        }
        
        $message = 'Time in recorded successfully!';
    } elseif ($data['action'] === 'time_out') {
        // Find the most recent time-in record without a time-out
        $find_stmt = $conn->prepare("
            SELECT ID 
            FROM attendance 
            WHERE User_ID = ? 
            AND Time_Out IS NULL 
            ORDER BY Time_In DESC 
            LIMIT 1
        ");
        
        $find_stmt->execute([$data['userId']]);
        
        if ($find_stmt->rowCount() === 0) {
            throw new Exception('No active time-in record found. Please time-in first.');
        }
        
        $attendance_id = $find_stmt->fetchColumn();

        // Check if user already has completed a full attendance cycle today
        $check_complete_stmt = $conn->prepare("
            SELECT COUNT(*) 
            FROM attendance 
            WHERE User_ID = ? 
            AND Time_In BETWEEN ? AND ? 
            AND Time_Out IS NOT NULL
            AND Time_In < Time_Out
        ");
        
        $check_complete_stmt->execute([$data['userId'], $today_start, $today_end]);
        $completed_cycles = $check_complete_stmt->fetchColumn();
        
        if ($completed_cycles > 0) {
            throw new Exception('You have already completed attendance for today. You cannot clock out again on the same day.');
        }

        // Create uploads directory if it doesn't exist
        $upload_dir = '../uploads/attendance';
        if (!file_exists($upload_dir)) {
            if (!mkdir($upload_dir, 0755, true)) {
                throw new Exception('Failed to create upload directory');
            }
        }

        // Save face image to file system
        $image_data = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $data['faceImage']));
        $image_path = $upload_dir . '/' . time() . '_' . $data['userId'] . '.jpg';
        
        if (file_put_contents($image_path, $image_data) === false) {
            throw new Exception('Failed to save image file');
        }
        
        // Update the time-out information
        $update_stmt = $conn->prepare("
            UPDATE attendance 
            SET Time_Out = NOW(),
                Time_Out_Latitude = ?,
                Time_Out_Longitude = ?,
                Time_Out_IP = ?,
                Time_Out_Image = ?,
                Updated_At = NOW(),
                Hours_Worked = TIMESTAMPDIFF(HOUR, Time_In, NOW())
            WHERE ID = ?
        ");
        
        $result = $update_stmt->execute([
            $data['latitude'],
            $data['longitude'],
            $data['ip'],
            $image_path,
            $attendance_id
        ]);
        
        if (!$result) {
            throw new Exception('Failed to update attendance record: ' . implode(', ', $update_stmt->errorInfo()));
        }
        
        $message = 'Time out recorded successfully!';
    } else {
        throw new Exception('Invalid action: ' . $data['action']);
    }

    // Commit the transaction
    $conn->commit();
    
    // Send success response
    echo json_encode([
        'status' => 'success',
        'message' => $message
    ]);
    
} catch (Exception $e) {
    // Roll back the transaction on error
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    
    // Send error response
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
    
    // Log the error
    error_log('Attendance Error: ' . $e->getMessage());
}
?>
