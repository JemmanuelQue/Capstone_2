<?php
session_start();
require_once __DIR__ . '/../includes/session_check.php';
require_once '../db_connection.php';
if (!validateSession($conn, 3, false)) {
    $_SESSION['leave_error'] = 'Unauthorized access. Please log in first.';
    header('Location: ../index.php');
    exit;
}
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Include PHPMailer autoloader
require '../vendor/autoload.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    $_SESSION['leave_error'] = 'Unauthorized access. Please log in first.';
    header('Location: ../index.php');
    exit;
}

// Check if request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['leave_error'] = 'Invalid request method.';
    header('Location: leave_request.php');
    exit;
}

// Get the action type (accept or reject)
$action = $_POST['action'];
$requestId = $_POST['request_id'];

// Get the current HR user's name
$hrStmt = $conn->prepare("SELECT First_Name, Last_Name FROM users WHERE User_ID = ?");
$hrStmt->execute([$_SESSION['user_id']]);
$hrData = $hrStmt->fetch(PDO::FETCH_ASSOC);
$hrName = $hrData ? $hrData['First_Name'] . ' ' . $hrData['Last_Name'] : "HR Officer";

// Process the leave request based on action
try {
    $conn->beginTransaction();
    
    $guardName = $_POST['guard_name'];
    $guardEmail = $_POST['guard_email'];
    $leaveType = $_POST['leave_type'];
    $leavePeriod = $_POST['leave_period'];
    $location = $_POST['location'] ?? 'N/A';

    if ($action === 'accept') {
        // Update leave request status to Approved
        $updateStmt = $conn->prepare("UPDATE leave_requests SET Status = 'Approved' WHERE ID = ?");
        $updateStmt->execute([$requestId]);
        
        // Log the action with detailed information
        $logStmt = $conn->prepare("INSERT INTO activity_logs (User_ID, Activity_Type, Activity_Details) 
                                  VALUES (?, 'Leave Request Action', ?)");
        $logDetails = "Accepted leave request for $guardName from $leavePeriod with reason: approved by HR";
        $logStmt->execute([
            $_SESSION['user_id'],
            $logDetails
        ]);
        
        // Send email notification
        sendAcceptanceEmail($guardName, $guardEmail, $leaveType, $leavePeriod, $hrName);
        
        $_SESSION['leave_success'] = "Leave request for $guardName has been approved successfully!";
    } 
    elseif ($action === 'reject') {
        // Get rejection reason
        $rejectionReason = $_POST['rejection_reason'];
        
        // Update leave request status to Rejected and store rejection reason
        $updateStmt = $conn->prepare("UPDATE leave_requests SET Status = 'Rejected', rejection_reason = ? WHERE ID = ?");
        $updateStmt->execute([$rejectionReason, $requestId]);
        
        // Log the action with detailed information
        $logStmt = $conn->prepare("INSERT INTO activity_logs (User_ID, Activity_Type, Activity_Details) 
                                  VALUES (?, 'Leave Request Action', ?)");
        $logDetails = "Rejected leave request for $guardName from $leavePeriod with reason: $rejectionReason";
        $logStmt->execute([
            $_SESSION['user_id'],
            $logDetails
        ]);
        
        // Send email notification with rejection reason
        sendRejectionEmail($guardName, $guardEmail, $leaveType, $leavePeriod, $rejectionReason, $hrName);
        
        $_SESSION['leave_success'] = "Leave request for $guardName has been rejected.";
    }
    
    $conn->commit();
    
    // Redirect back to leave request page
    header('Location: leave_request.php');
    exit;
} 
catch (Exception $e) {
    $conn->rollBack();
    $_SESSION['leave_error'] = "An error occurred while processing the leave request: " . $e->getMessage();
    header('Location: leave_request.php');
    exit;
}

// Function to send acceptance email
function sendAcceptanceEmail($guardName, $guardEmail, $leaveType, $leavePeriod, $hrName) {
    $mail = new PHPMailer(true);
    
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'phpmailer572@gmail.com';
        $mail->Password = 'hbwulibpahbbsuhu';
        $mail->SMTPSecure = 'tls';
        $mail->Port = 587;
        
        // Recipients
        $mail->setFrom('phpmailer572@gmail.com', 'Green Meadows Security Agency');
        $mail->addAddress($guardEmail, $guardName);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = "Leave Request Approved - $leaveType";
        
        // Professional HTML email body
        $mail->Body = "
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body {
                    font-family: Arial, sans-serif;
                    line-height: 1.6;
                    color: #333333;
                }
                .container {
                    max-width: 600px;
                    margin: 0 auto;
                    padding: 20px;
                    border: 1px solid #dddddd;
                    border-radius: 5px;
                }
                .header {
                    background-color: #2a7d4f;
                    color: white;
                    padding: 15px;
                    text-align: center;
                    border-radius: 5px 5px 0 0;
                }
                .content {
                    padding: 20px;
                }
                .footer {
                    background-color: #f7f7f7;
                    padding: 15px;
                    text-align: center;
                    font-size: 12px;
                    border-radius: 0 0 5px 5px;
                }
                h2 {
                    color: #2a7d4f;
                }
                .details {
                    background-color: #f9f9f9;
                    padding: 15px;
                    border-left: 4px solid #2a7d4f;
                    margin: 20px 0;
                }
                .signature {
                    margin-top: 30px;
                }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Leave Request Approved</h1>
                </div>
                <div class='content'>
                    <p>Dear <strong>$guardName</strong>,</p>
                    
                    <p>We are pleased to inform you that your leave request has been <strong style='color:#2a7d4f'>APPROVED</strong>.</p>
                    
                    <div class='details'>
                        <p><strong>Leave Type:</strong> $leaveType</p>
                        <p><strong>Period:</strong> $leavePeriod</p>
                    </div>
                    
                    <p>Please ensure that any pending responsibilities are properly handed over before your leave begins.</p>
                    
                    <p>If you need to make any changes to your leave plans, please inform the HR department as soon as possible.</p>
                    
                    <div class='signature'>
                        <p>Best regards,</p>
                        <p><strong>$hrName</strong><br>Human Resources Department<br>Green Meadows Security Agency</p>
                    </div>
                </div>
                <div class='footer'>
                    <p>This is an automated message. Please do not reply directly to this email.</p>
                    <p>&copy; " . date('Y') . " Green Meadows Security Agency. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>";
        
        // Plain text alternative
        $mail->AltBody = "
        Dear $guardName,
        
        We are pleased to inform you that your leave request has been APPROVED.
        
        Leave Type: $leaveType
        Period: $leavePeriod
        
        Please ensure that any pending responsibilities are properly handed over before your leave begins.
        
        If you need to make any changes to your leave plans, please inform the HR department as soon as possible.
        
        Best regards,
        $hrName
        Human Resources Department
        Green Meadows Security Agency";
        
        $mail->send();
        return true;
    } 
    catch (Exception $e) {
        error_log("Email could not be sent. Mailer Error: {$mail->ErrorInfo}");
        return false;
    }
}

// Function to send rejection email
function sendRejectionEmail($guardName, $guardEmail, $leaveType, $leavePeriod, $rejectionReason, $hrName) {
    $mail = new PHPMailer(true);
    
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'phpmailer572@gmail.com';
        $mail->Password = 'hbwulibpahbbsuhu';
        $mail->SMTPSecure = 'tls';
        $mail->Port = 587;
        
        // Recipients
        $mail->setFrom('phpmailer572@gmail.com', 'Green Meadows Security Agency');
        $mail->addAddress($guardEmail, $guardName);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = "Leave Request Not Approved - $leaveType";
        
        // Professional HTML email body
        $mail->Body = "
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body {
                    font-family: Arial, sans-serif;
                    line-height: 1.6;
                    color: #333333;
                }
                .container {
                    max-width: 600px;
                    margin: 0 auto;
                    padding: 20px;
                    border: 1px solid #dddddd;
                    border-radius: 5px;
                }
                .header {
                    background-color: #d33;
                    color: white;
                    padding: 15px;
                    text-align: center;
                    border-radius: 5px 5px 0 0;
                }
                .content {
                    padding: 20px;
                }
                .footer {
                    background-color: #f7f7f7;
                    padding: 15px;
                    text-align: center;
                    font-size: 12px;
                    border-radius: 0 0 5px 5px;
                }
                h2 {
                    color: #d33;
                }
                .details {
                    background-color: #f9f9f9;
                    padding: 15px;
                    border-left: 4px solid #d33;
                    margin: 20px 0;
                }
                .reason {
                    background-color: #fff4f4;
                    padding: 15px;
                    border-left: 4px solid #d33;
                    margin: 20px 0;
                }
                .signature {
                    margin-top: 30px;
                }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Leave Request Not Approved</h1>
                </div>
                <div class='content'>
                    <p>Dear <strong>$guardName</strong>,</p>
                    
                    <p>We regret to inform you that your leave request has <strong style='color:#d33'>NOT BEEN APPROVED</strong> at this time.</p>
                    
                    <div class='details'>
                        <p><strong>Leave Type:</strong> $leaveType</p>
                        <p><strong>Period:</strong> $leavePeriod</p>
                    </div>
                    
                    <div class='reason'>
                        <h3>Reason for Non-Approval:</h3>
                        <p>" . htmlspecialchars($rejectionReason) . "</p>
                    </div>
                    
                    <p>If you would like to discuss this decision or submit a new leave request, please contact the HR department.</p>
                    
                    <div class='signature'>
                        <p>Best regards,</p>
                        <p><strong>$hrName</strong><br>Human Resources Department<br>Green Meadows Security Agency</p>
                    </div>
                </div>
                <div class='footer'>
                    <p>This is an automated message. Please do not reply directly to this email.</p>
                    <p>&copy; " . date('Y') . " Green Meadows Security Agency. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>";
        
        // Plain text alternative
        $mail->AltBody = "
        Dear $guardName,
        
        We regret to inform you that your leave request has NOT BEEN APPROVED at this time.
        
        Leave Type: $leaveType
        Period: $leavePeriod
        
        Reason for Non-Approval:
        $rejectionReason
        
        If you would like to discuss this decision or submit a new leave request, please contact the HR department.
        
        Best regards,
        $hrName
        Human Resources Department
        Green Meadows Security Agency";
        
        $mail->send();
        return true;
    } 
    catch (Exception $e) {
        error_log("Email could not be sent. Mailer Error: {$mail->ErrorInfo}");
        return false;
    }
}
?>