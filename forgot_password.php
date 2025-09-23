<?php
session_start();
require_once 'db_connection.php';
require_once 'vendor/autoload.php'; // For PHPMailer

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

$error_message = '';
$success_message = '';

// Check if there's a session message
if (isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = 'Please enter a valid email address.';
    } else {
        // Check if email exists in users table
        $stmt = $conn->prepare("SELECT User_ID, First_Name, Last_Name FROM users WHERE Email = ? AND status = 'Active'");
        $stmt->execute([$email]);
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($result) > 0) {
            $user = $result[0];
            
            // Rate limiting: Check for recent requests (last 5 minutes)
            $stmt = $conn->prepare("SELECT COUNT(*) as count FROM password_reset_tokens WHERE email = ? AND created_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE)");
            $stmt->execute([$email]);
            $rate_check = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($rate_check['count'] >= 3) {
                $error_message = 'Too many password reset requests. Please wait 5 minutes before trying again.';
            } else {
                // Generate OTP and token
                $otp = sprintf('%06d', mt_rand(100000, 999999));
                $token = bin2hex(random_bytes(32));
                
                // Fix: Use database timezone for consistency
                $expires_at = date('Y-m-d H:i:s', strtotime('+10 minutes'));
                // Better approach: Let database handle the timezone
                // We'll use DATE_ADD in the query instead
                
                $ip_address = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
                
                // Store in database - let MySQL handle the expiration time
                $stmt = $conn->prepare("INSERT INTO password_reset_tokens (email, token, otp, expires_at, ip_address) VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL 10 MINUTE), ?)");
                
                if ($stmt->execute([$email, $token, $otp, $ip_address])) {
                    // Send email with OTP
                    if (sendPasswordResetEmail($email, $user['First_Name'], $otp, $token)) {
                        $_SESSION['success_message'] = 'Password reset instructions have been sent to your email address.';
                        $_SESSION['reset_email'] = $email;
                        header('Location: forgot_password.php');
                        exit();
                    } else {
                        $error_message = 'Failed to send email. Please try again later.';
                    }
                } else {
                    $error_message = 'An error occurred. Please try again later.';
                }
            }
        } else {
            // Don't reveal if email exists or not for security
            $_SESSION['success_message'] = 'If an account with that email exists, you will receive password reset instructions.';
            header('Location: forgot_password.php');
            exit();
        }
    }
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
        $mail->Subject = 'Password Reset Request - Green Meadows Security Agency';
        
        $verifyUrl = "http://" . $_SERVER['HTTP_HOST'] . "/verify_otp.php?token=" . $token;
        
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
                .warning { background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 5px; margin: 15px 0; }
                .footer { text-align: center; margin-top: 20px; color: #6c757d; font-size: 12px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Password Reset Request</h1>
                    <p>Green Meadows Security Agency</p>
                </div>
                <div class='content'>
                    <h2>Hello, {$firstName}!</h2>
                    <p>We received a request to reset your password. Use the verification code below to proceed:</p>
                    
                    <div class='otp-box'>
                        <p style='margin: 0; color: #666;'>Your verification code is:</p>
                        <div class='otp-code'>{$otp}</div>
                        <p style='margin: 10px 0 0 0; color: #666; font-size: 14px;'>This code expires in 10 minutes</p>
                    </div>
                    
                    <p>Or click the button below to verify directly:</p>
                    <p style='text-align: center;'>
                        <a href='{$verifyUrl}' class='button'>Verify & Reset Password</a>
                    </p>
                    
                    <div class='warning'>
                        <strong>Security Notice:</strong>
                        <ul style='margin: 10px 0 0 20px;'>
                            <li>This code will expire in 10 minutes</li>
                            <li>If you didn't request this reset, please ignore this email</li>
                            <li>Never share your verification code with anyone</li>
                        </ul>
                    </div>
                    
                    <p>If you're having trouble clicking the button, copy and paste this URL into your browser:</p>
                    <p style='word-break: break-all; color: #666; font-size: 12px;'>{$verifyUrl}</p>
                </div>
                <div class='footer'>
                    <p>&copy; 2025 Green Meadows Security Agency. All rights reserved.</p>
                    <p>This is an automated message, please do not reply to this email.</p>
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

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - Green Meadows Security Agency</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Google Fonts - Poppins -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f8f9fa;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .forgot-container {
            background-color: #fff;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            width: 100%;
            max-width: 900px;
            display: flex;
            min-height: 550px;
        }
        
        .forgot-banner {
            background-color: #2a7d4f;
            color: white;
            padding: 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            width: 40%;
        }
        
        .forgot-banner img {
            width: 200px;
            height: 200px;
            object-fit: contain;
            margin-bottom: 20px;
        }
        
        .forgot-form {
            padding: 40px;
            width: 60%;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        
        .forgot-form h2 {
            color: #333;
            margin-bottom: 20px;
            font-weight: 600;
        }
        
        .forgot-form p {
            color: #6c757d;
            margin-bottom: 30px;
            font-size: 15px;
            line-height: 1.6;
        }
        
        .form-floating {
            position: relative;
            margin-bottom: 20px;
        }
        
        .form-control:focus {
            box-shadow: none;
            border-color: #2a7d4f;
        }
        
        .btn-reset {
            background-color: #2a7d4f;
            border: none;
            padding: 12px;
            font-weight: 500;
            transition: all 0.3s;
        }
        
        .btn-reset:hover {
            background-color: #1e5e3a;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(42, 125, 79, 0.3);
        }
        
        .btn-back {
            background-color: transparent;
            color: #6c757d;
            border: 1px solid #dee2e6;
            padding: 12px;
            font-weight: 500;
            transition: all 0.3s;
        }
        
        .btn-back:hover {
            background-color: #f8f9fa;
            color: #333;
        }
        
        .input-icon {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #6c757d;
            z-index: 10;
        }
        
        .icon-input {
            padding-left: 45px;
        }
        
        .alert {
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        @media (max-width: 768px) {
            .forgot-container {
                flex-direction: column;
                max-width: 90%;
            }
            
            .forgot-banner, .forgot-form {
                width: 100%;
                padding: 30px;
            }
            
            .forgot-banner {
                padding-bottom: 0;
            }
            
            .forgot-form {
                padding-top: 20px;
            }
            
            .forgot-banner img {
                width: 150px;
                height: 150px;
                margin-bottom: 10px;
            }
        }
    </style>
</head>
<body>
    <div class="forgot-container">
        <div class="forgot-banner">
            <img src="images/forgot_password.avif" alt="Forgot Password">
            <h3 class="text-center">GREEN MEADOWS SECURITY AGENCY</h3>
            <p class="text-center mb-0">Password Recovery</p>
        </div>
        
        <div class="forgot-form">
            <h2>Forgot Password?</h2>
            <p>Enter your email address below and we'll send you a verification code to reset your password.</p>
            
            <?php if ($error_message): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success_message): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success_message); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-group mb-4 position-relative">
                    <i class="fas fa-envelope input-icon"></i>
                    <input type="email" class="form-control icon-input" id="email" name="email" 
                           placeholder="Enter your email address" required 
                           value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                </div>
                
                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-primary btn-reset">
                        <i class="fas fa-paper-plane me-2"></i>Send Verification Code
                    </button>
                    <a href="login.php" class="btn btn-back mt-2">
                        <i class="fas fa-arrow-left me-2"></i>Back to Login
                    </a>
                </div>
            </form>
            
            <p class="text-center text-muted mt-4 small">© 2025 Green Meadows Security Agency</p>
        </div>
    </div>
    
    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>