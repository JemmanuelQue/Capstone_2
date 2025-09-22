<?php
// filepath: c:\xampp\htdocs\HRIS\guards\process_attendance.php
session_start();
require_once __DIR__ . '/../includes/session_check.php';
require_once '../db_connection.php';

// Enforce guard role (5). If invalid, return JSON error instead of redirect.
if (!validateSession($conn, 5, false)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

$userId = $_SESSION['user_id'];
$response = ['success' => false, 'message' => 'Unknown error occurred'];

// Process attendance actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $latitude = $_POST['latitude'] ?? 0;
    $longitude = $_POST['longitude'] ?? 0;
    $ip = $_SERVER['REMOTE_ADDR'];
    
    try {
        // Get user's assigned location
        $locationStmt = $conn->prepare("
            SELECT location_name, designated_latitude, designated_longitude, allowed_radius 
            FROM guard_locations 
            WHERE user_id = ? AND is_active = 1 AND is_primary = 1
        ");
        $locationStmt->execute([$userId]);
        $locationData = $locationStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$locationData) {
            throw new Exception("No assigned location found for this guard");
        }
        
        // Calculate distance from designated location
        $distance = calculateDistance(
            $latitude, 
            $longitude,
            $locationData['designated_latitude'],
            $locationData['designated_longitude']
        );
        
        // Check if within allowed radius
        $isLocationVerified = ($distance <= $locationData['allowed_radius']);
        
        // Handle face verification image if provided
        $imagePath = null;
        $faceVerified = false;
        
        if (isset($_FILES['verification_image']) && $_FILES['verification_image']['error'] == 0) {
            $targetDir = "../uploads/attendance/";
            
            // Create directory if it doesn't exist
            if (!file_exists($targetDir)) {
                mkdir($targetDir, 0755, true);
            }
            
            // Generate unique filename
            $timestamp = time();
            $filename = $timestamp . '_' . $userId . '.jpg';
            $targetFile = $targetDir . $filename;
            
            // Save file
            if (move_uploaded_file($_FILES['verification_image']['tmp_name'], $targetFile)) {
                $imagePath = $targetFile;
                $faceVerified = true; // Ideally, this should be based on actual face verification
            }
        }
        
        // Process clock-in or clock-out
        if ($action === 'clock_in') {
            // Check if already clocked in
            $checkStmt = $conn->prepare("
                SELECT ID FROM attendance 
                WHERE User_ID = ? AND Time_Out IS NULL 
                ORDER BY Time_In DESC LIMIT 1
            ");
            $checkStmt->execute([$userId]);
            
            if ($checkStmt->rowCount() > 0) {
                throw new Exception("You are already clocked in. Please clock out first.");
            }
            
            // Record clock-in
            $stmt = $conn->prepare("
                INSERT INTO attendance 
                (User_ID, IP_Address, Time_In, Latitude, Longitude, face_verified, 
                verification_image_path, location_verified, distance_from_location) 
                VALUES (?, ?, NOW(), ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $userId,
                $ip,
                $latitude, 
                $longitude, 
                $faceVerified ? 1 : 0,
                $imagePath,
                $isLocationVerified ? 1 : 0,
                $distance
            ]);
            
            $response = [
                'success' => true, 
                'message' => 'Successfully clocked in',
                'location_verified' => $isLocationVerified,
                'face_verified' => $faceVerified,
                'distance' => round($distance)
            ];
            
        } elseif ($action === 'clock_out') {
            // Find active attendance record
            $attendanceStmt = $conn->prepare("
                SELECT ID FROM attendance 
                WHERE User_ID = ? AND Time_Out IS NULL 
                ORDER BY Time_In DESC LIMIT 1
            ");
            $attendanceStmt->execute([$userId]);
            $attendanceData = $attendanceStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$attendanceData) {
                throw new Exception("No active attendance record found. Please clock in first.");
            }
            
            // Record clock-out
            $updateStmt = $conn->prepare("
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
            $updateStmt->execute([
                $latitude,
                $longitude,
                $ip,
                $imagePath,
                $attendanceData['ID']
            ]);
            
            $response = [
                'success' => true, 
                'message' => 'Successfully clocked out',
                'location_verified' => $isLocationVerified,
                'face_verified' => $faceVerified,
                'distance' => round($distance)
            ];
        } else {
            throw new Exception("Invalid action specified");
        }
        
    } catch (Exception $e) {
        $response = ['success' => false, 'message' => $e->getMessage()];
    }
}

// Calculate distance between two coordinates using Haversine formula
function calculateDistance($lat1, $lon1, $lat2, $lon2) {
    $R = 6371000; // Earth's radius in meters
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    
    $a = sin($dLat/2) * sin($dLat/2) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($dLon/2) * sin($dLon/2);
    
    $c = 2 * atan2(sqrt($a), sqrt(1-$a));
    $distance = $R * $c; // Distance in meters
    
    return $distance;
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode($response);