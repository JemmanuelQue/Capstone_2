<?php
session_start();
require_once 'db_connection.php';
require_once 'vendor/autoload.php'; // Add this for PHPMailer

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

$error_message = '';
$success_message = '';

// Check if user has valid reset session
if (!isset($_SESSION['reset_token']) || !isset($_SESSION['reset_email'])) {
    $_SESSION['error_message'] = 'Invalid access. Please start the password reset process again.';
    header('Location: forgot_password.php');
    exit();
}

$token = $_SESSION['reset_token'];
$email = $_SESSION['reset_email'];

// Verify token is still valid
$stmt = $conn->prepare("SELECT id FROM password_reset_tokens WHERE token = ? AND email = ? AND used = 0 AND expires_at > NOW()");
$stmt->execute([$token, $email]);
$result = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$result) {
    $_SESSION['error_message'] = 'Reset token has expired. Please request a new password reset.';
    header('Location: forgot_password.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validate passwords
    if (empty($new_password) || empty($confirm_password)) {
        $error_message = 'Please fill in all fields.';
    } elseif ($new_password !== $confirm_password) {
        $error_message = 'Passwords do not match.';
    } elseif (strlen($new_password) < 8) {
        $error_message = 'Password must be at least 8 characters long.';
    } elseif (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)/', $new_password)) {
        $error_message = 'Password must contain at least one uppercase letter, one lowercase letter, and one number.';
    } else {
        // Hash the new password
        $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
        
        // Update user password
        $stmt = $conn->prepare("UPDATE users SET Password_Hash = ? WHERE Email = ?");
        
        if ($stmt->execute([$password_hash, $email])) {
            // Mark token as used
            $stmt = $conn->prepare("UPDATE password_reset_tokens SET used = 1 WHERE token = ?");
            $stmt->execute([$token]);
            
            // Get user details for activity log and email
            $stmt = $conn->prepare("SELECT User_ID, First_Name, Last_Name FROM users WHERE Email = ?");
            $stmt->execute([$email]);
            $user_result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user_result) {
                $user_id = $user_result['User_ID'];
                $user_name = $user_result['First_Name'] . ' ' . $user_result['Last_Name'];
                
                // Log the password reset activity
                $activity_stmt = $conn->prepare("INSERT INTO activity_logs (User_ID, Activity_Type, Activity_Details) VALUES (?, 'Password Reset', 'Password reset successfully via email verification')");
                $activity_stmt->execute([$user_id]);
                
                // Send password change confirmation email
                sendPasswordChangeConfirmation($email, $user_result['First_Name']);
            }
            
            // Clear session data
            unset($_SESSION['reset_token']);
            unset($_SESSION['reset_email']);
            
            $_SESSION['success_message'] = 'Your password has been reset successfully. You can now log in with your new password. A confirmation email has been sent to your email address.';
            header('Location: login.php');
            exit();
        } else {
            $error_message = 'An error occurred while updating your password. Please try again.';
        }
    }
}

function sendPasswordChangeConfirmation($email, $firstName) {
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
        $mail->Subject = 'Password Changed Successfully - Green Meadows Security Agency';
        
        // Set timezone to Philippine time and format the date
        date_default_timezone_set('Asia/Manila');
        $currentDateTime = date('F j, Y \a\t g:i A') . ' PHT';
        $resetUrl = "http://" . $_SERVER['HTTP_HOST'] . "/HRIS/forgot_password.php";
        $supportEmail = "support@greenmeadowssecurity.com"; // Change to your actual support email
        
        $mail->Body = "
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: 'Poppins', Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { 
                    background: linear-gradient(135deg, #2a7d4f, #1e5e3a); 
                    color: white; 
                    padding: 30px; 
                    text-align: center; 
                    border-radius: 10px 10px 0 0; 
                }
                .content { 
                    background: #f8f9fa; 
                    padding: 30px; 
                    border-radius: 0 0 10px 10px; 
                    border: 1px solid #dee2e6; 
                    border-top: none; 
                }
                .success-box { 
                    background: #d4edda; 
                    border: 1px solid #c3e6cb; 
                    color: #155724; 
                    padding: 20px; 
                    border-radius: 8px; 
                    margin: 20px 0; 
                    text-align: center; 
                }
                .success-icon { 
                    font-size: 48px; 
                    color: #28a745; 
                    margin-bottom: 15px; 
                }
                .warning-box { 
                    background: #fff3cd; 
                    border: 1px solid #ffeaa7; 
                    color: #856404; 
                    padding: 20px; 
                    border-radius: 8px; 
                    margin: 20px 0; 
                }
                .button { 
                    display: inline-block; 
                    background: #dc3545; 
                    color: white; 
                    padding: 12px 30px; 
                    text-decoration: none; 
                    border-radius: 5px; 
                    margin: 10px 0; 
                    font-weight: bold;
                }
                .info-table {
                    width: 100%;
                    background: white;
                    border-radius: 8px;
                    margin: 15px 0;
                    border-collapse: collapse;
                    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
                }
                .info-table td {
                    padding: 12px 15px;
                    border-bottom: 1px solid #f0f0f0;
                }
                .info-table td:first-child {
                    font-weight: bold;
                    color: #666;
                    width: 30%;
                }
                .footer { 
                    text-align: center; 
                    margin-top: 20px; 
                    color: #6c757d; 
                    font-size: 12px; 
                }
                .security-tips {
                    background: #e7f3ff;
                    border: 1px solid #b8daff;
                    border-radius: 8px;
                    padding: 15px;
                    margin: 15px 0;
                }
                .security-tips h4 {
                    color: #004085;
                    margin-top: 0;
                }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>🔐 Password Changed Successfully</h1>
                    <p>Green Meadows Security Agency</p>
                </div>
                <div class='content'>
                    <div class='success-box'>
                        <div class='success-icon'>✅</div>
                        <h2 style='margin: 0; color: #155724;'>Password Update Confirmed</h2>
                        <p style='margin: 10px 0 0 0; font-size: 16px;'>Your account password has been changed successfully.</p>
                    </div>
                    
                    <h2 style='color: #333; margin-bottom: 15px;'>Hi {$firstName},</h2>
                    <p>This is a confirmation that your password was changed successfully for your Green Meadows Security Agency account.</p>
                    
                    <table class='info-table'>
                        <tr>
                            <td>📧 Email Account:</td>
                            <td>{$email}</td>
                        </tr>
                        <tr>
                            <td>🕐 Date & Time:</td>
                            <td>{$currentDateTime}</td>
                        </tr>
                        <tr>
                            <td>🌐 IP Address:</td>
                            <td>" . ($_SERVER['REMOTE_ADDR'] ?? 'Unknown') . "</td>
                        </tr>
                        <tr>
                            <td>🔄 Method:</td>
                            <td>Password Reset via Email Verification</td>
                        </tr>
                    </table>
                    
                    <div class='warning-box'>
                        <h3 style='margin-top: 0; color: #856404;'>⚠️ Didn't Make This Change?</h3>
                        <p style='margin-bottom: 15px;'>If you did not perform this action, your account may have been compromised. Please take immediate action:</p>
                        <ol>
                            <li>Reset your password again immediately</li>
                            <li>Check for any unauthorized account activity</li>
                            <li>Contact our support team for assistance</li>
                        </ol>
                        <p style='text-align: center; margin-top: 20px;'>
                            <a href='{$resetUrl}' class='button'>🔒 Reset Password Again</a>
                        </p>
                    </div>
                    
                    <div class='security-tips'>
                        <h4>🛡️ Security Tips</h4>
                        <ul style='margin: 0; padding-left: 20px;'>
                            <li>Use a unique password for your account</li>
                            <li>Enable two-factor authentication if available</li>
                            <li>Never share your password with anyone</li>
                            <li>Log out from shared or public computers</li>
                            <li>Regularly review your account activity</li>
                        </ul>
                    </div>
                    
                    <p style='margin-top: 30px;'>If you have any questions or need assistance, please contact our support team at <a href='mailto:{$supportEmail}' style='color: #2a7d4f;'>{$supportEmail}</a>.</p>
                    
                    <p style='color: #666; font-style: italic;'>Thank you for helping us keep your account secure!</p>
                </div>
                <div class='footer'>
                    <p>&copy; 2025 Green Meadows Security Agency. All rights reserved.</p>
                    <p>This is an automated security notification. Please do not reply to this email.</p>
                    <p>If you're having trouble with the links above, copy and paste this URL into your browser: {$resetUrl}</p>
                </div>
            </div>
        </body>
        </html>";

        $mail->send();
        return true;
    } catch (Exception $e) {
        // Log the error but don't fail the password reset
        error_log("Password change confirmation email failed: " . $mail->ErrorInfo);
        return false;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - Green Meadows Security Agency</title>
    
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
        
        .reset-container {
            background-color: #fff;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            width: 100%;
            max-width: 900px;
            display: flex;
            min-height: 550px;
        }
        
        .reset-banner {
            background: linear-gradient(135deg, #2a7d4f, #1e5e3a);
            color: white;
            padding: 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            width: 40%;
        }
        
        .reset-banner i {
            font-size: 100px;
            margin-bottom: 20px;
            opacity: 0.9;
        }
        
        .reset-form {
            padding: 40px;
            width: 60%;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        
        .reset-form h2 {
            color: #333;
            margin-bottom: 20px;
            font-weight: 600;
        }
        
        .reset-form p {
            color: #6c757d;
            margin-bottom: 30px;
            font-size: 15px;
            line-height: 1.6;
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
            padding-right: 45px; /* Add right padding for eye icon */
        }
        
        .password-toggle {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #6c757d;
            z-index: 10;
        }
        
        .alert {
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .password-requirements {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        
        .requirement {
            color: #dc3545;
            transition: color 0.3s;
        }
        
        .requirement.met {
            color: #28a745;
        }
        
        @media (max-width: 768px) {
            .reset-container {
                flex-direction: column;
                max-width: 90%;
            }
            
            .reset-banner, .reset-form {
                width: 100%;
                padding: 30px;
            }
            
            .reset-banner {
                padding-bottom: 20px;
            }
            
            .reset-banner i {
                font-size: 70px;
            }
        }
    </style>
</head>
<body>
    <div class="reset-container">
        <div class="reset-banner">
            <i class="fas fa-key"></i>
            <h3 class="text-center">GREEN MEADOWS SECURITY AGENCY</h3>
            <p class="text-center mb-0">Create New Password</p>
        </div>
        
        <div class="reset-form">
            <h2>Reset Your Password</h2>
            <p>Please create a strong password for your account: <strong><?php echo htmlspecialchars($email); ?></strong></p>
            
            <?php if ($error_message): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>
            
            <div class="password-requirements">
                <strong>Password Requirements:</strong>
                <ul class="mt-2 mb-0">
                    <li class="requirement" id="length">At least 8 characters long</li>
                    <li class="requirement" id="uppercase">One uppercase letter</li>
                    <li class="requirement" id="lowercase">One lowercase letter</li>
                    <li class="requirement" id="number">One number</li>
                </ul>
            </div>
            
            <form method="POST" action="">
                <div class="form-group mb-3 position-relative">
                    <i class="fas fa-lock input-icon"></i>
                    <input type="password" class="form-control icon-input" id="new_password" name="new_password" 
                           placeholder="Enter new password" required>
                    <i class="fas fa-eye password-toggle" id="togglePassword1" onclick="togglePassword('new_password', 'togglePassword1')"></i>
                </div>
                
                <div class="form-group mb-4 position-relative">
                    <i class="fas fa-lock input-icon"></i>
                    <input type="password" class="form-control icon-input" id="confirm_password" name="confirm_password" 
                           placeholder="Confirm new password" required>
                    <i class="fas fa-eye password-toggle" id="togglePassword2" onclick="togglePassword('confirm_password', 'togglePassword2')"></i>
                </div>
                
                <small id="passwordMatch" class="form-text" style="display: none; margin-bottom: 15px;"></small>

                <div class="d-grid">
                    <button type="submit" class="btn btn-primary btn-reset" id="submitBtn" disabled>
                        <i class="fas fa-save me-2"></i>Reset Password
                    </button>
                </div>
            </form>
            
            <p class="text-center text-muted mt-4 small">© 2025 Green Meadows Security Agency</p>
        </div>
    </div>
    
    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        function togglePassword(inputId, iconId) {
            const input = document.getElementById(inputId);
            const icon = document.getElementById(iconId);
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }
        
        function checkPasswordRequirements() {
            const password = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            // Check requirements
            const hasLength = password.length >= 8;
            const hasUppercase = /[A-Z]/.test(password);
            const hasLowercase = /[a-z]/.test(password);
            const hasNumber = /\d/.test(password);
            const passwordsMatch = password === confirmPassword && password.length > 0;
            
            // Update UI
            document.getElementById('length').className = hasLength ? 'requirement met' : 'requirement';
            document.getElementById('uppercase').className = hasUppercase ? 'requirement met' : 'requirement';
            document.getElementById('lowercase').className = hasLowercase ? 'requirement met' : 'requirement';
            document.getElementById('number').className = hasNumber ? 'requirement met' : 'requirement';
            
            // Enable/disable submit button
            const allValid = hasLength && hasUppercase && hasLowercase && hasNumber && passwordsMatch;
            document.getElementById('submitBtn').disabled = !allValid;
            
            // Show password match feedback as text instead of input styling
            const matchIndicator = document.getElementById('passwordMatch');
            if (confirmPassword.length > 0) {
                if (passwordsMatch) {
                    matchIndicator.textContent = '✓ Passwords match';
                    matchIndicator.className = 'form-text text-success';
                    matchIndicator.style.display = 'block';
                } else {
                    matchIndicator.textContent = '✗ Passwords do not match';
                    matchIndicator.className = 'form-text text-danger';
                    matchIndicator.style.display = 'block';
                }
            } else {
                matchIndicator.style.display = 'none';
            }
        }
        
        // Add event listeners
        document.getElementById('new_password').addEventListener('input', checkPasswordRequirements);
        document.getElementById('confirm_password').addEventListener('input', checkPasswordRequirements);
    </script>
</body>
</html>