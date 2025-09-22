<?php
session_start();
require_once 'db_connection.php';

$error_message = '';
$success_message = '';
$email = '';
$token = '';

// Check if there's a session message
if (isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

// Get email from session or redirect back
if (isset($_SESSION['reset_email'])) {
    $email = $_SESSION['reset_email'];
} elseif (isset($_GET['token'])) {
    $token = $_GET['token'];
    // Get email from token
    $stmt = $conn->prepare("SELECT email FROM password_reset_tokens WHERE token = ? AND used = 0 AND expires_at > NOW()");
    $stmt->execute([$token]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($result) {
        $email = $result['email'];
        $_SESSION['reset_email'] = $email;
    } else {
        $_SESSION['error_message'] = 'Invalid or expired reset link.';
        header('Location: forgot_password.php');
        exit();
    }
} else {
    header('Location: forgot_password.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $otp = trim($_POST['otp']);
    
    if (empty($otp) || !preg_match('/^\d{6}$/', $otp)) {
        $error_message = 'Please enter a valid 6-digit OTP code.';
    } else {
        // Verify OTP
        $stmt = $conn->prepare("SELECT token FROM password_reset_tokens WHERE email = ? AND otp = ? AND used = 0 AND expires_at > NOW()");
        $stmt->execute([$email, $otp]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            $_SESSION['reset_token'] = $result['token'];
            $_SESSION['reset_email'] = $email;
            header('Location: reset_password.php');
            exit();
        } else {
            $error_message = 'Invalid or expired OTP code. Please request a new one.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify OTP - Green Meadows Security Agency</title>
    
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
        
        .verify-container {
            background-color: #fff;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            width: 100%;
            max-width: 900px;
            display: flex;
            min-height: 550px;
        }
        
        .verify-banner {
            background: linear-gradient(135deg, #2a7d4f, #1e5e3a);
            color: white;
            padding: 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            width: 40%;
        }
        
        .verify-banner i {
            font-size: 100px;
            margin-bottom: 20px;
            opacity: 0.9;
        }
        
        .verify-form {
            padding: 40px;
            width: 60%;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        
        .verify-form h2 {
            color: #333;
            margin-bottom: 20px;
            font-weight: 600;
        }
        
        .verify-form p {
            color: #6c757d;
            margin-bottom: 30px;
            font-size: 15px;
            line-height: 1.6;
        }
        
        .otp-input {
            text-align: center;
            font-size: 24px;
            font-weight: bold;
            letter-spacing: 8px;
            padding: 15px;
            border: 2px solid #dee2e6;
            border-radius: 8px;
            margin-bottom: 20px;
            transition: all 0.3s;
        }
        
        .otp-input:focus {
            border-color: #2a7d4f;
            box-shadow: 0 0 15px rgba(42, 125, 79, 0.1);
        }
        
        .btn-verify {
            background-color: #2a7d4f;
            border: none;
            padding: 12px;
            font-weight: 500;
            transition: all 0.3s;
        }
        
        .btn-verify:hover {
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
        
        .alert {
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .resend-section {
            text-align: center;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #dee2e6;
        }
        
        .countdown {
            color: #2a7d4f;
            font-weight: bold;
        }
        
        @media (max-width: 768px) {
            .verify-container {
                flex-direction: column;
                max-width: 90%;
            }
            
            .verify-banner, .verify-form {
                width: 100%;
                padding: 30px;
            }
            
            .verify-banner {
                padding-bottom: 20px;
            }
            
            .verify-banner i {
                font-size: 70px;
            }
        }
    </style>
</head>
<body>
    <div class="verify-container">
        <div class="verify-banner">
            <i class="fas fa-shield-alt"></i>
            <h3 class="text-center">GREEN MEADOWS SECURITY AGENCY</h3>
            <p class="text-center mb-0">OTP Verification</p>
        </div>
        
        <div class="verify-form">
            <h2>Enter Verification Code</h2>
            <p>We've sent a 6-digit verification code to <strong><?php echo htmlspecialchars($email); ?></strong></p>
            
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
                <div class="form-group mb-4">
                    <input type="text" class="form-control otp-input" id="otp" name="otp" 
                           placeholder="000000" maxlength="6" pattern="\d{6}" 
                           title="Please enter a 6-digit number" required>
                </div>
                
                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-primary btn-verify">
                        <i class="fas fa-check me-2"></i>Verify Code
                    </button>
                    <a href="forgot_password.php" class="btn btn-back mt-2">
                        <i class="fas fa-arrow-left me-2"></i>Back to Email Entry
                    </a>
                </div>
            </form>
            
            <div class="resend-section">
                <p class="text-muted small">Didn't receive the code?</p>
                <button id="resendBtn" class="btn btn-link p-0" onclick="resendCode()" style="color: #2a7d4f;">
                    Resend Code
                </button>
                <div id="countdown" class="countdown mt-2" style="display: none;"></div>
            </div>
            
            <p class="text-center text-muted mt-4 small">© 2025 Green Meadows Security Agency</p>
        </div>
    </div>
    
    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Auto-format OTP input
        document.getElementById('otp').addEventListener('input', function(e) {
            this.value = this.value.replace(/\D/g, '');
        });
        
        // Auto-submit when 6 digits are entered
        document.getElementById('otp').addEventListener('input', function(e) {
            if (this.value.length === 6) {
                this.form.submit();
            }
        });
        
        let countdownInterval;
        let resendAllowed = true;
        
        function resendCode() {
            if (!resendAllowed) return;
            
            // Disable resend button
            resendAllowed = false;
            document.getElementById('resendBtn').style.display = 'none';
            document.getElementById('countdown').style.display = 'block';
            
            // Start countdown
            let timeLeft = 60;
            countdownInterval = setInterval(function() {
                document.getElementById('countdown').textContent = `Resend available in ${timeLeft} seconds`;
                timeLeft--;
                
                if (timeLeft < 0) {
                    clearInterval(countdownInterval);
                    document.getElementById('countdown').style.display = 'none';
                    document.getElementById('resendBtn').style.display = 'inline';
                    resendAllowed = true;
                }
            }, 1000);
            
            // Send AJAX request to resend OTP
            fetch('resend_otp.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'email=<?php echo urlencode($email); ?>'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Show success message
                    const alert = document.createElement('div');
                    alert.className = 'alert alert-success';
                    alert.innerHTML = '<i class="fas fa-check-circle me-2"></i>New verification code sent!';
                    document.querySelector('.verify-form').insertBefore(alert, document.querySelector('form'));
                    
                    // Remove alert after 5 seconds
                    setTimeout(() => {
                        alert.remove();
                    }, 5000);
                }
            })
            .catch(error => {
                console.error('Error:', error);
            });
        }
    </script>
</body>
</html>