<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/session_check.php';
require_once '../db_connection.php';

if (!validateSession($conn, 5, false)) {
    echo json_encode(['verified' => false, 'message' => 'Not logged in']);
    exit;
}

try {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['faceDescriptor'])) {
        throw new Exception('Missing face descriptor');
    }

    // Get the stored face descriptor
    $stmt = $conn->prepare("SELECT face_descriptor FROM guard_faces WHERE guard_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $face_profile = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$face_profile) {
        throw new Exception('No face profile found');
    }

    // Compare face descriptors
    $stored_descriptor = json_decode($face_profile['face_descriptor']);
    $current_descriptor = $data['faceDescriptor'];
    
    // Calculate similarity
    $distance = 0;
    for ($i = 0; $i < count($stored_descriptor); $i++) {
        $distance += pow($stored_descriptor[$i] - $current_descriptor[$i], 2);
    }
    $distance = sqrt($distance);
    
    // Get threshold from settings
    $stmt = $conn->prepare("SELECT setting_value FROM face_recognition_settings WHERE setting_name = 'max_face_distance'");
    $stmt->execute();
    $threshold_setting = $stmt->fetch(PDO::FETCH_ASSOC);
    $threshold = $threshold_setting ? floatval($threshold_setting['setting_value']) : 0.6;

    echo json_encode([
        'verified' => $distance <= $threshold,
        'confidence' => 1 - ($distance / 2)
    ]);

} catch (Exception $e) {
    echo json_encode([
        'verified' => false,
        'message' => $e->getMessage()
    ]);
} 