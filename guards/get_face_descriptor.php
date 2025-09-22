<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/session_check.php';
require_once '../db_connection.php';

if (!validateSession($conn, 5, false)) {
    echo json_encode(['status' => 'error', 'message' => 'Not logged in']);
    exit;
}

try {
    $stmt = $conn->prepare("SELECT face_descriptor FROM guard_faces WHERE guard_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($result && $result['face_descriptor']) {
        echo json_encode([
            'status' => 'success',
            'descriptor' => json_decode($result['face_descriptor'])
        ]);
    } else {
        echo json_encode([
            'status' => 'error',
            'message' => 'No face profile found'
        ]);
    }
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
} 