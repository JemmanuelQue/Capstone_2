<?php
session_start();
require_once __DIR__ . '/../includes/session_check.php';
require __DIR__ . '/../db_connection.php'; // Adjust path if needed

if (!validateSession($conn, 4, false)) { // Accounting only updates here
    header('Location: ../index.php');
    exit;
}

$user_id = $_SESSION['user_id'];

$sql = 'SELECT profile_pic FROM users WHERE user_id = ?';
$stmt = $conn->prepare($sql);
$stmt->execute([$user_id]);
$currentProfilePic = $stmt->fetchColumn();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstName = $_POST['firstName'];
    $lastName = $_POST['lastName'];
    $profilePic = $_FILES['profilePic'];
    
    // Get the referring page for redirect
    $redirect_page = isset($_POST['redirect_page']) ? $_POST['redirect_page'] : 'hr_dashboard.php';

    $allowedExtensions = ['jpg', 'jpeg', 'png', 'avif', 'jfif']; // Limited to requested formats
    $maxFileSize = 5 * 1024 * 1024;

    $profilePicPath = null;
    if (isset($profilePic) && $profilePic['error'] === UPLOAD_ERR_OK) {
        $fileExtension = strtolower(pathinfo($profilePic['name'], PATHINFO_EXTENSION));
        $fileSize = $profilePic['size'];

        if (in_array($fileExtension, $allowedExtensions) && $fileSize <= $maxFileSize) {
            $uploadDir = '../uploads/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $uploadFile = $uploadDir . uniqid() . '.' . $fileExtension;
            if (move_uploaded_file($profilePic['tmp_name'], $uploadFile)) {
                error_log('File successfully uploaded to: ' . $uploadFile);
                $profilePicPath = $uploadFile;

                if ($currentProfilePic && $currentProfilePic !== '../uploads/default_profile.png' && file_exists($currentProfilePic)) {
                    unlink($currentProfilePic);
                }
            } else {
                error_log('Failed to move uploaded file to: ' . $uploadFile);
            }
        } else {
            error_log('Invalid file type or size.');
            $_SESSION['profilepic_error'] = 'Invalid file type or file size exceeds 5MB.';
            header('Location: ' . $redirect_page);
            exit;
        }
    }

    try {
        if ($profilePicPath) {
            $sql = 'UPDATE users SET first_name = ?, last_name = ?, profile_pic = ? WHERE user_id = ?';
            $stmt = $conn->prepare($sql);
            $stmt->execute([$firstName, $lastName, $profilePicPath, $user_id]);
            error_log('Database updated with profile picture path: ' . $profilePicPath);
        } else {
            $sql = 'UPDATE users SET first_name = ?, last_name = ? WHERE user_id = ?';
            $stmt = $conn->prepare($sql);
            $stmt->execute([$firstName, $lastName, $user_id]);
            error_log('Database updated without profile picture.');
        }

        $_SESSION['profilepic_success'] = 'Profile updated successfully!';
        header('Location: ' . $redirect_page);
        exit;
    } catch (Exception $e) {
        error_log('Error updating profile: ' . $e->getMessage());
        $_SESSION['profilepic_error'] = 'An error occurred while updating your profile.';
        header('Location: ' . $redirect_page);
        exit;
    }
}
?>