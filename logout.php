<?php
session_start();
require_once 'db_connection.php';

$user_id = $_SESSION['user_id'] ?? null;
$session_id = $_SESSION['session_id'] ?? null;

try {
    // Log logout activity
    if ($user_id) {
        $stmt = $conn->prepare("
            INSERT INTO activity_logs (User_ID, Activity_Type, Activity_Details) 
            VALUES (?, 'Logout', ?)
        ");
        $stmt->execute([$user_id, 'User logged out from IP: ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown')]);
    }
    
    // Deactivate session in database
    if ($session_id) {
        $stmt = $conn->prepare("UPDATE user_sessions SET is_active = 0 WHERE session_id = ?");
        $stmt->execute([$session_id]);
    }
    
    // Clean up remember token if it exists
    if (isset($_COOKIE['remember_token'])) {
        $stmt = $conn->prepare("UPDATE user_sessions SET is_active = 0 WHERE remember_token = ?");
        $stmt->execute([$_COOKIE['remember_token']]);
        
        // Clear the remember token cookie
        setcookie('remember_token', '', time() - 3600, '/');
    }
    
} catch (PDOException $e) {
    error_log("Logout error: " . $e->getMessage());
}

// Clear PHP session
$_SESSION = array();

// Delete session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

session_destroy();

// Redirect to landing page
header("Location: index.php");
exit();
?>