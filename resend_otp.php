<?php
session_start();
require_once 'db_connection.php';
require_once 'vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

$email = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Invalid email address']);
    exit();
}

// Rate limiting: Check for recent requests (last 1 minute)
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM password_reset_tokens WHERE email = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 MINUTE)");
$stmt->execute([$email]);
$rate_check = $stmt->fetch(PDO::FETCH_ASSOC);

if ($rate_check['count'] >= 1) {
    echo json_encode(['success' => false, 'message' => 'Please wait before requesting another code']);
    exit();
}

// Check if email exists
$stmt = $conn->prepare("SELECT First_Name FROM users WHERE Email = ? AND status = 'Active'");
$stmt->execute([$email]);
$result = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$result) {
    echo json_encode(['success' => false, 'message' => 'Email not found']);
    exit();
}

$user = $result;

// Generate new OTP and token
$otp = sprintf('%06d', mt_rand(100000, 999999));
$token = bin2hex(random_bytes(32));
$ip_address = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

// Invalidate old tokens for this email
$stmt = $conn->prepare("UPDATE password_reset_tokens SET used = 1 WHERE email = ? AND used = 0");
$stmt->execute([$email]);

// Store new token - let MySQL handle the expiration time
$stmt = $conn->prepare("INSERT INTO password_reset_tokens (email, token, otp, expires_at, ip_address) VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL 10 MINUTE), ?)");

if ($stmt->execute([$email, $token, $otp, $ip_address])) {
    // Send email
    if (sendPasswordResetEmail($email, $user['First_Name'], $otp, $token)) {
        echo json_encode(['success' => true, 'message' => 'New verification code sent']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to send email']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Database error']);
}

function sendPasswordResetEmail($email, $firstName, $otp, $token) {
    $mail = new PHPMailer(true);
    
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'phpmailer572@gmail.com';
        $mail->Password   = 'hbwulibpahbbsuhu';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        // Recipients
        $mail->setFrom('phpmailer572@gmail.com', 'Green Meadows Security Agency');
        $mail->addAddress($email, $firstName);

        // Content
        $mail->isHTML(true);
        $mail->Subject = 'New Password Reset Code - Green Meadows Security Agency';
        
        $verifyUrl = "http://" . $_SERVER['HTTP_HOST'] . "/HRIS/verify_otp.php?token=" . $token;
        
        $mail->Body = "
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: 'Poppins', Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #2a7d4f, #1e5e3a); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                .content { background: #f8f9fa; padding: 30px; border-radius: 0 0 10px 10px; }
                .otp-box { background: white; border: 2px dashed #2a7d4f; padding: 20px; text-align: center; margin: 20px 0; border-radius: 8px; }
                .otp-code { font-size: 32px; font-weight: bold; color: #2a7d4f; letter-spacing: 5px; }
                .button { display: inline-block; background: #2a7d4f; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; margin: 10px 0; }
                .footer { text-align: center; margin-top: 20px; color: #6c757d; font-size: 12px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>New Verification Code</h1>
                    <p>Green Meadows Security Agency</p>
                </div>
                <div class='content'>
                    <h2>Hello, {$firstName}!</h2>
                    <p>Here's your new verification code:</p>
                    
                    <div class='otp-box'>
                        <p style='margin: 0; color: #666;'>Your new verification code is:</p>
                        <div class='otp-code'>{$otp}</div>
                        <p style='margin: 10px 0 0 0; color: #666; font-size: 14px;'>This code expires in 10 minutes</p>
                    </div>
                    
                    <p style='text-align: center;'>
                        <a href='{$verifyUrl}' class='button'>Verify & Reset Password</a>
                    </p>
                </div>
                <div class='footer'>
                    <p>&copy; 2025 Green Meadows Security Agency. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Email sending failed: " . $mail->ErrorInfo);
        return false;
    }
}
?>