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
        header("Location: index.php?section=careers&status=incomplete_fields#apply");
        exit;
    }
    
    // Resume upload handling
    $resumePath = '';
    if(isset($_FILES['resume']) && $_FILES['resume']['error'] == 0) {
        $allowedTypes = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
        $fileType = $_FILES['resume']['type'];
        
        // Validate file type
        if(in_array($fileType, $allowedTypes)) {
            // Validate file size (5MB max)
            if($_FILES['resume']['size'] <= 5 * 1024 * 1024) {
                $uploadDir = 'uploads/resumes/';
                
                // Create directory if it doesn't exist
                if(!file_exists($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                
                // Generate unique filename
                $fileName = time() . '_' . $lastName . '_' . $firstName . '_resume';
                $fileExt = pathinfo($_FILES['resume']['name'], PATHINFO_EXTENSION);
                $filePath = $uploadDir . $fileName . '.' . $fileExt;
                
                // Move uploaded file
                if(move_uploaded_file($_FILES['resume']['tmp_name'], $filePath)) {
                    $resumePath = $filePath;
                } else {
                    header("Location: index.php?section=careers&status=upload_error#apply");
                    exit;
                }
            } else {
                header("Location: index.php?section=careers&status=file_too_large#apply");
                exit;
            }
        } else {
            header("Location: index.php?section=careers&status=invalid_file_type#apply");
            exit;
        }
    } else {
        header("Location: index.php?section=careers&status=resume_required#apply");
        exit;
    }
    
    try {
        // Check for duplicate email
        $checkEmail = $conn->prepare("SELECT COUNT(*) FROM applicants WHERE Email = ?");
        $checkEmail->execute([$email]);
        if ($checkEmail->fetchColumn() > 0) {
            // Email already exists
            header("Location: index.php?section=careers&status=duplicate_email#apply");
            exit;
        }
        
        // Check for duplicate phone number
        $checkPhone = $conn->prepare("SELECT COUNT(*) FROM applicants WHERE Phone_Number = ?");
        $checkPhone->execute([$phone]);
        if ($checkPhone->fetchColumn() > 0) {
            // Phone already exists
            header("Location: index.php?section=careers&status=duplicate_phone#apply");
            exit;
        }
        
        // Only proceed with insertion if no duplicates found
        $stmt = $conn->prepare("INSERT INTO applicants (First_Name, Middle_Name, Last_Name, Name_Extension, Email, Phone_Number, 
                        Position, Preferred_Location, Resume_Path, Additional_Info) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

        $stmt->execute([$firstName, $middleName, $lastName, $nameExtension, $email, $phone, $position, 
                    $preferredLocation, $resumePath, $message]);
        
        // Redirect after successful submission
        header("Location: index.php?section=careers&status=success#apply");
        exit;
        
    } catch (PDOException $e) {
        // Log error
        error_log("Application submission error: " . $e->getMessage());
        
        // Redirect with error message
        header("Location: index.php?section=careers&status=error#apply");
        exit;
    }
} else {
    // If not POST request, redirect to homepage
    header("Location: index.php");
    exit;
}
?>