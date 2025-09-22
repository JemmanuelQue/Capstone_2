<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../db_connection.php';

function validateSession($conn, $required_role = null, $redirect = true) {
    // Check if user session exists
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['session_id'])) {
    if ($redirect) { return redirectToLogin(); }
    return false;
    }

    $session_id = $_SESSION['session_id'];
    $user_id = $_SESSION['user_id'];

    try {
        // Validate session in database
        $stmt = $conn->prepare("
            SELECT us.*, u.status, u.Role_ID 
            FROM user_sessions us 
            JOIN users u ON us.user_id = u.User_ID 
            WHERE us.session_id = ? AND us.user_id = ? AND us.is_active = 1 AND us.expires_at > NOW()
        ");
        $stmt->execute([$session_id, $user_id]);
        $session = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$session) {
            // Session invalid or expired
            cleanupSession($conn, $session_id);
            if ($redirect) { return redirectToLogin('Session expired. Please log in again.'); }
            return false;
        }

        // Check if user is still active
        if ($session['status'] !== 'Active') {
            cleanupSession($conn, $session_id);
            if ($redirect) { return redirectToLogin('Your account has been deactivated.'); }
            return false;
        }

        // Check role requirement
        if ($required_role && $session['Role_ID'] != $required_role) {
            if ($redirect) { return redirectToLogin('Access denied. Insufficient permissions.'); }
            return false;
        }

        // Update last activity
        $stmt = $conn->prepare("UPDATE user_sessions SET last_activity = NOW() WHERE session_id = ?");
        $stmt->execute([$session_id]);

        return true;

    } catch (PDOException $e) {
        error_log("Session validation error: " . $e->getMessage());
        if ($redirect) { return redirectToLogin('Session validation failed.'); }
        return false;
    }
}

function checkRememberToken($conn) {
    if (!isset($_COOKIE['remember_token'])) {
        return false;
    }

    $token = $_COOKIE['remember_token'];

    try {
        $stmt = $conn->prepare("
            SELECT us.*, u.User_ID, u.Username, u.Role_ID, u.First_Name, u.Last_Name, u.status 
            FROM user_sessions us 
            JOIN users u ON us.user_id = u.User_ID 
            WHERE us.remember_token = ? AND us.is_active = 1 AND us.expires_at > NOW()
        ");
        $stmt->execute([$token]);
        $session = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($session && $session['status'] === 'Active') {
            // Restore session
            session_regenerate_id(true);
            $_SESSION['user_id'] = $session['User_ID'];
            $_SESSION['role_id'] = $session['Role_ID'];
            $_SESSION['username'] = $session['Username'];
            $_SESSION['name'] = $session['First_Name'] . ' ' . $session['Last_Name'];
            $_SESSION['session_id'] = $session['session_id'];
            $_SESSION['login_time'] = time();

            // Update last activity
            $stmt = $conn->prepare("UPDATE user_sessions SET last_activity = NOW() WHERE session_id = ?");
            $stmt->execute([$session['session_id']]);

            return true;
        }
    } catch (PDOException $e) {
        error_log("Remember token check error: " . $e->getMessage());
    }

    return false;
}

function cleanupSession($conn, $session_id = null) {
    if ($session_id) {
        try {
            $stmt = $conn->prepare("UPDATE user_sessions SET is_active = 0 WHERE session_id = ?");
            $stmt->execute([$session_id]);
        } catch (PDOException $e) {
            error_log("Session cleanup error: " . $e->getMessage());
        }
    }

    // Clear PHP session
    $_SESSION = array();
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();

    // Clear remember token cookie
    if (isset($_COOKIE['remember_token'])) {
        setcookie('remember_token', '', time() - 3600, '/');
    }
}

function redirectToLogin($message = null) {
    if ($message) {
        session_start();
        $_SESSION['error'] = $message;
    }
    
    // Determine the correct path to login.php based on current directory
    $current_dir = basename(getcwd());
    $login_path = ($current_dir === 'HRIS') ? 'login.php' : '../login.php';
    
    header("Location: " . $login_path);
    exit();
}

function isUserLoggedIn($conn) {
    // Check if user session exists
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['session_id'])) {
        // Check remember token
        if (checkRememberToken($conn)) {
            return true;
        }
        return false;
    }

    $session_id = $_SESSION['session_id'];
    $user_id = $_SESSION['user_id'];

    try {
        // Validate session in database
        $stmt = $conn->prepare("
            SELECT us.*, u.status, u.Role_ID 
            FROM user_sessions us 
            JOIN users u ON us.user_id = u.User_ID 
            WHERE us.session_id = ? AND us.user_id = ? AND us.is_active = 1 AND us.expires_at > NOW()
        ");
        $stmt->execute([$session_id, $user_id]);
        $session = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($session && $session['status'] === 'Active') {
            return true;
        }
    } catch (PDOException $e) {
        error_log("Session validation error: " . $e->getMessage());
    }

    return false;
}

// Auto-check for remember token if no session exists
if (!isset($_SESSION['user_id']) && isset($_COOKIE['remember_token'])) {
    checkRememberToken($conn);
}
?>