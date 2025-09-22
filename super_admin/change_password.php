<?php
session_start();
require_once __DIR__ . '/../db_connection.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit;
}

$userId = (int)$_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: profile.php');
    exit;
}

$current = $_POST['current_password'] ?? '';
$new = $_POST['new_password'] ?? '';
$confirm = $_POST['confirm_password'] ?? '';

// Basic validations
if (!$current || !$new || !$confirm) {
    $_SESSION['password_error'] = 'Please fill in all password fields.';
    header('Location: profile.php');
    exit;
}

// Complexity rules with granular messages
$errors = [];
$allowedList = '! @ # $ % ^ & * ( ) _ - + = { } [ ] : ; , . ? /';
$allowedAllRegex = '/^[A-Za-z0-9!@#$%^&*()_\-+=\{\}\[\]:;,.\?\/]+$/';
if (!preg_match($allowedAllRegex, $new)) {
    $errors[] = "Only letters, numbers, and these special characters are allowed: $allowedList";
}
if (strlen($new) < 8) {
    $errors[] = 'Must be at least 8 characters long.';
}
if (!preg_match('/[A-Z]/', $new)) {
    $errors[] = 'Must include at least one uppercase letter (A–Z).';
}
if (!preg_match('/[a-z]/', $new)) {
    $errors[] = 'Must include at least one lowercase letter (a–z).';
}
if (!preg_match('/[0-9]/', $new)) {
    $errors[] = 'Must include at least one number (0–9).';
}
if (!preg_match('/[!@#$%^&*()_\-+=\{\}\[\]:;,.\?\/]/', $new)) {
    $errors[] = "Must include at least one special character from: $allowedList";
}
if (!empty($errors)) {
    $_SESSION['password_error'] = implode(' • ', $errors);
    header('Location: profile.php');
    exit;
}

if ($new !== $confirm) {
    $_SESSION['password_error'] = 'New password and confirmation do not match.';
    header('Location: profile.php');
    exit;
}

try {
    // Fetch current password hash
    $stmt = $conn->prepare('SELECT Password_Hash, Email FROM users WHERE User_ID = ?');
    $stmt->execute([$userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        $_SESSION['password_error'] = 'User record not found.';
        header('Location: profile.php');
        exit;
    }

    if (!password_verify($current, $row['Password_Hash'])) {
        $_SESSION['password_error'] = 'Current password is incorrect.';
        header('Location: profile.php');
        exit;
    }

    // Hash and update new password
    $newHash = password_hash($new, PASSWORD_DEFAULT);
    $upd = $conn->prepare('UPDATE users SET Password_Hash = ? WHERE User_ID = ?');
    $upd->execute([$newHash, $userId]);

    // Log activity
    try {
        $log = $conn->prepare('INSERT INTO activity_logs (User_ID, Activity_Type, Activity_Details) VALUES (?, ?, ?)');
        $log->execute([$userId, 'Password Management', 'Password changed via profile page']);
    } catch (Exception $e) {
        error_log('Failed to log password change: ' . $e->getMessage());
    }

    $_SESSION['password_success'] = 'Password updated successfully.';
    header('Location: profile.php');
    exit;

} catch (Exception $e) {
    error_log('Password change error: ' . $e->getMessage());
    $_SESSION['password_error'] = 'An error occurred while updating your password.';
    header('Location: profile.php');
    exit;
}
