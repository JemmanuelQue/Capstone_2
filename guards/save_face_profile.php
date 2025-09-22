<?php
session_start();
error_reporting(0); // Disable error reporting
header('Content-Type: application/json'); // Force JSON response

require_once __DIR__ . '/../includes/session_check.php';
require_once '../db_connection.php'; // Updated to use your existing connection file

// Check session using centralized validator; avoid redirect and return JSON instead
if (!validateSession($conn, 5, false)) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Not logged in. Please log in again.',
    ]);
    exit;
}

try {
    // Get POST data
    $data = json_decode(file_get_contents('php://input'), true);

    if (!isset($data['faceDescriptor']) || !isset($data['profileImage'])) {
        throw new Exception('Missing required data');
    }

    // Create directory if it doesn't exist
    $upload_dir = 'face_profiles';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    // Set fixed filename format for each guard
    $image_path = $upload_dir . '/guard_' . $_SESSION['user_id'] . '.jpg';

    // Delete existing file if it exists
    if (file_exists($image_path)) {
        unlink($image_path);
    }

    // Save the new profile image
    $image_data = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $data['profileImage']));
    file_put_contents($image_path, $image_data);

    // Convert face descriptor array to string
    $face_descriptor = json_encode($data['faceDescriptor']);

    // Check if guard already has a face profile
    $stmt = $conn->prepare("SELECT id FROM guard_faces WHERE guard_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        // Update existing profile
        $stmt = $conn->prepare("UPDATE guard_faces SET face_descriptor = ?, profile_image = ? WHERE guard_id = ?");
        $stmt->execute([$face_descriptor, $image_path, $_SESSION['user_id']]);
    } else {
        // Create new profile
        $stmt = $conn->prepare("INSERT INTO guard_faces (guard_id, face_descriptor, profile_image) VALUES (?, ?, ?)");
        $stmt->execute([$_SESSION['user_id'], $face_descriptor, $image_path]);
    }

    echo json_encode([
        'status' => 'success',
        'message' => 'Face profile saved successfully'
    ]);

} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?> 