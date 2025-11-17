<?php
require_once 'db_connection.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get form data
    $firstName = htmlspecialchars($_POST['firstName']);
    $middleName = htmlspecialchars($_POST['middleName'] ?? '');
    $lastName = htmlspecialchars($_POST['lastName']);
    $nameExtension = htmlspecialchars($_POST['nameExtension'] ?? '');
    $email = htmlspecialchars($_POST['email']);
    $phone = htmlspecialchars($_POST['phone']);
    $position = htmlspecialchars($_POST['position']);
    $preferredLocation = htmlspecialchars($_POST['preferredLocation'] ?? '');
    $message = htmlspecialchars($_POST['message'] ?? '');
    
    // Check for required fields
    if (empty($firstName) || empty($lastName) || empty($email) || empty($phone) || empty($position) || !isset($_FILES['resume'])) {
        error_log("APPLY ERROR - Missing required fields. firstName: " . (!empty($firstName) ? 'OK' : 'MISSING') . 
                  ", lastName: " . (!empty($lastName) ? 'OK' : 'MISSING') . 
                  ", email: " . (!empty($email) ? 'OK' : 'MISSING') . 
                  ", phone: " . (!empty($phone) ? 'OK' : 'MISSING') . 
                  ", position: " . (!empty($position) ? 'OK' : 'MISSING') . 
                  ", resume: " . (isset($_FILES['resume']) ? 'OK' : 'MISSING'));
        header("Location: index.php?section=careers&status=incomplete_fields#apply");
        exit;
    }
    
    // Resume upload handling
    $resumePath = '';
    if(isset($_FILES['resume']) && $_FILES['resume']['error'] == 0) {
        error_log("APPLY LOG - Resume file received: " . $_FILES['resume']['name'] . 
                  ", size: " . $_FILES['resume']['size'] . 
                  ", type: " . $_FILES['resume']['type'] . 
                  ", tmp_name: " . $_FILES['resume']['tmp_name']);
        
        $allowedTypes = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
        $fileType = $_FILES['resume']['type'];
        
        // Validate file type
        if(in_array($fileType, $allowedTypes)) {
            // Validate file size (5MB max)
            if($_FILES['resume']['size'] <= 5 * 1024 * 1024) {
                $uploadDir = 'uploads/resumes/';
                
                // Create directory if it doesn't exist
                if(!file_exists($uploadDir)) {
                    error_log("APPLY LOG - Creating upload directory: " . $uploadDir);
                    if(!mkdir($uploadDir, 0777, true)) {
                        error_log("APPLY ERROR - Failed to create directory: " . $uploadDir);
                        header("Location: index.php?section=careers&status=upload_error&msg=dir_create_failed#apply");
                        exit;
                    }
                }
                
                // Check if directory is writable
                if(!is_writable($uploadDir)) {
                    error_log("APPLY ERROR - Directory not writable: " . $uploadDir);
                    header("Location: index.php?section=careers&status=upload_error&msg=dir_not_writable#apply");
                    exit;
                }
                
                // Generate unique filename
                $fileName = time() . '_' . $lastName . '_' . $firstName . '_resume';
                $fileExt = pathinfo($_FILES['resume']['name'], PATHINFO_EXTENSION);
                $filePath = $uploadDir . $fileName . '.' . $fileExt;
                
                error_log("APPLY LOG - Attempting to move file to: " . $filePath);
                
                // Move uploaded file
                if(move_uploaded_file($_FILES['resume']['tmp_name'], $filePath)) {
                    $resumePath = $filePath;
                    error_log("APPLY LOG - File uploaded successfully: " . $filePath);
                } else {
                    $uploadError = error_get_last();
                    error_log("APPLY ERROR - move_uploaded_file failed. Error: " . print_r($uploadError, true));
                    error_log("APPLY ERROR - Source: " . $_FILES['resume']['tmp_name'] . ", Destination: " . $filePath);
                    error_log("APPLY ERROR - Source exists: " . (file_exists($_FILES['resume']['tmp_name']) ? 'YES' : 'NO'));
                    header("Location: index.php?section=careers&status=upload_error&msg=move_failed#apply");
                    exit;
                }
            } else {
                error_log("APPLY ERROR - File too large: " . $_FILES['resume']['size'] . " bytes (max 5MB)");
                header("Location: index.php?section=careers&status=file_too_large#apply");
                exit;
            }
        } else {
            error_log("APPLY ERROR - Invalid file type: " . $fileType . ", allowed: " . implode(', ', $allowedTypes));
            header("Location: index.php?section=careers&status=invalid_file_type#apply");
            exit;
        }
    } else {
        $fileError = isset($_FILES['resume']['error']) ? $_FILES['resume']['error'] : 'not set';
        error_log("APPLY ERROR - Resume file not received or has error. Error code: " . $fileError);
        if($fileError !== 0 && $fileError !== 'not set') {
            $errorMessages = [
                UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize',
                UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE',
                UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
                UPLOAD_ERR_NO_FILE => 'No file was uploaded',
                UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
                UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
                UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the upload'
            ];
            $errorMsg = isset($errorMessages[$fileError]) ? $errorMessages[$fileError] : 'Unknown error';
            error_log("APPLY ERROR - Upload error details: " . $errorMsg);
        }
        header("Location: index.php?section=careers&status=resume_required#apply");
        exit;
    }
    
    try {
        error_log("APPLY LOG - Starting database insertion for: " . $email);
        
        // Check for duplicate email
        $checkEmail = $conn->prepare("SELECT COUNT(*) FROM applicants WHERE Email = ?");
        $checkEmail->execute([$email]);
        $emailCount = $checkEmail->fetchColumn();
        
        if ($emailCount > 0) {
            error_log("APPLY LOG - Duplicate email found (" . $emailCount . "), deleting old application: " . $email);
            // Email already exists - could be from old application, delete it and allow reapplication
            $deleteOld = $conn->prepare("DELETE FROM applicants WHERE Email = ?");
            $deleteOld->execute([$email]);
            error_log("APPLY LOG - Old application deleted successfully");
        }
        
        // Check for duplicate phone number
        $checkPhone = $conn->prepare("SELECT COUNT(*) FROM applicants WHERE Phone_Number = ?");
        $checkPhone->execute([$phone]);
        $phoneCount = $checkPhone->fetchColumn();
        
        if ($phoneCount > 0) {
            error_log("APPLY LOG - Duplicate phone found (" . $phoneCount . "), deleting old application: " . $phone);
            // Phone already exists - could be from old application, delete it and allow reapplication
            $deleteOld = $conn->prepare("DELETE FROM applicants WHERE Phone_Number = ?");
            $deleteOld->execute([$phone]);
            error_log("APPLY LOG - Old application with phone deleted successfully");
        }
        
        // Insert new application with Status column
        error_log("APPLY LOG - Preparing INSERT query");
        $stmt = $conn->prepare("INSERT INTO applicants (First_Name, Middle_Name, Last_Name, Name_Extension, Email, Phone_Number, 
                        Position, Preferred_Location, Resume_Path, Additional_Info, Status) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'New')");

        error_log("APPLY LOG - Executing INSERT with data: Name=" . $firstName . " " . $lastName . ", Email=" . $email . ", Position=" . $position);
        $stmt->execute([$firstName, $middleName, $lastName, $nameExtension, $email, $phone, $position, 
                    $preferredLocation, $resumePath, $message]);
        
        $lastId = $conn->lastInsertId();
        error_log("APPLY LOG - Application inserted successfully with ID: " . $lastId);
        
        // Redirect after successful submission
        header("Location: index.php?section=careers&status=success#apply");
        exit;
        
    } catch (PDOException $e) {
        // Log detailed error
        error_log("APPLY ERROR - Database error occurred");
        error_log("APPLY ERROR - Message: " . $e->getMessage());
        error_log("APPLY ERROR - SQL State: " . $e->getCode());
        error_log("APPLY ERROR - Error Info: " . print_r($e->errorInfo, true));
        error_log("APPLY ERROR - Stack trace: " . $e->getTraceAsString());
        
        // Redirect with error message
        header("Location: index.php?section=careers&status=error&msg=" . urlencode($e->getMessage()) . "#apply");
        exit;
    }
} else {
    // If not POST request, redirect to homepage
    header("Location: index.php");
    exit;
}
?>