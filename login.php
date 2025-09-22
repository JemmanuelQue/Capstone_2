<?php
session_start();

// Include the session check to see if user is already logged in
// require_once 'includes/session_check.php';

// Check if user is already logged in and redirect to their dashboard
if (isset($_SESSION['user_id']) && isset($_SESSION['role_id'])) {
    // User is already logged in, redirect to their appropriate dashboard
    switch ($_SESSION['role_id']) {
        case 1: // Super Admin
            header("Location: super_admin/superadmin_dashboard.php");
            exit();
        case 2: // Admin
            header("Location: admin/admin_dashboard.php");
            exit();
        case 3: // HR
            header("Location: hr/hr_dashboard.php");
            exit();
        case 4: // Accounting
            header("Location: accounting/accounting_dashboard.php");
            exit();
        case 5: // Security Guard
            header("Location: guards/guards_dashboard.php");
            exit();
        default:
            // Invalid role, logout and continue to login page
            session_destroy();
            break;
    }
}

// Get toast messages if any
$toast_error = $_SESSION['toast_error'] ?? '';
$toast_success = $_SESSION['toast_success'] ?? '';

// Clear session messages after retrieving them
unset($_SESSION['toast_error'], $_SESSION['toast_success']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title> Security Agency - HRIS Login</title>
    
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
        
        .login-container {
            background-color: #fff;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            width: 100%;
            max-width: 900px;
            display: flex;
            min-height: 550px;
        }
        
        .login-banner {
            background: linear-gradient(135deg, #2a7d4f 0%, #1e5e3a 100%);
            color: white;
            padding: 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            width: 40%;
        }
        
        .login-banner img {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            margin-bottom: 20px;
            border: 3px solid rgba(255, 255, 255, 0.3);
        }
        
        .login-form {
            padding: 40px;
            width: 60%;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        
        .login-form h2 {
            color: #333;
            margin-bottom: 10px;
            font-weight: 600;
        }
        
        .login-form p {
            color: #6c757d;
            margin-bottom: 30px;
            font-size: 15px;
        }
        
        .form-floating {
            position: relative;
            margin-bottom: 20px;
        }
        
        .form-control:focus {
            box-shadow: none;
            border-color: #2a7d4f;
        }
        
        .btn-login {
            background-color: #2a7d4f;
            border: none;
            padding: 12px;
            font-weight: 500;
            transition: all 0.3s;
        }
        
        .btn-login:hover {
            background-color: #1e5e3a;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(42, 125, 79, 0.3);
        }
        
        .forgot-password {
            color: #2a7d4f;
            text-decoration: none;
            font-size: 14px;
            transition: all 0.3s;
        }
        
        .forgot-password:hover {
            color: #1e5e3a;
            text-decoration: underline;
        }
        
        .remember-me {
            accent-color: #2a7d4f;
        }
        
        /* Password toggle styles */
        .password-container {
            position: relative;
        }
        
        .password-toggle {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #6c757d;
            cursor: pointer;
            padding: 0;
            z-index: 10;
            transition: color 0.3s;
        }
        
        .password-toggle:hover {
            color: #2a7d4f;
        }
        
        .password-toggle:focus {
            outline: none;
            color: #2a7d4f;
        }
        
        @media (max-width: 768px) {
            .login-container {
                flex-direction: column;
                max-width: 90%;
            }
            
            .login-banner, .login-form {
                width: 100%;
                padding: 30px;
            }
            
            .login-banner {
                padding-bottom: 0;
            }
            
            .login-form {
                padding-top: 20px;
            }
            
            .login-banner img {
                width: 80px;
                height: 80px;
                margin-bottom: 10px;
            }
        }
        
        /* Toast Styles */
        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
        }
        
        .toast {
            background-color: white;
            border-left: 4px solid #dc3545;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        
        .toast.success {
            border-left-color: #28a745;
        }
        
        .toast-body {
            padding: 12px 16px;
            display: flex;
            align-items: center;
        }
        
        .toast-body i {
            margin-right: 8px;
            font-size: 18px;
        }
        
        .toast.error .toast-body i {
            color: #dc3545;
        }
        
        .toast.success .toast-body i {
            color: #28a745;
        }
    </style>
</head>
<body>
    <!-- Toast Container -->
    <div class="toast-container">
        <?php if (!empty($toast_error)): ?>
        <div class="toast error" role="alert" aria-live="assertive" aria-atomic="true" data-bs-autohide="true" data-bs-delay="5000">
            <div class="toast-body">
                <i class="fas fa-exclamation-circle"></i>
                <span><?php echo htmlspecialchars($toast_error); ?></span>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if (!empty($toast_success)): ?>
        <div class="toast success" role="alert" aria-live="assertive" aria-atomic="true" data-bs-autohide="true" data-bs-delay="5000">
            <div class="toast-body">
                <i class="fas fa-check-circle"></i>
                <span><?php echo htmlspecialchars($toast_success); ?></span>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <div class="login-container">
        <div class="login-banner">
            <img src="images/greenmeadows_logo.jpg" alt="Green Meadows Security Agency">
            <h3 class="text-center mb-3">SECURITY AGENCY</h3>
            <p class="text-center mb-0">Human Resource Information System</p>
            <p class="text-center small mt-2">Secure Access Portal</p>
        </div>
        
        <div class="login-form">
            <h2>Welcome Back!</h2>
            <p>Please sign in to access your account</p>
            
            <form method="POST" action="login_processing.php" id="loginForm">
                <div class="form-floating mb-3">
                    <input type="email" class="form-control" id="email" name="email" placeholder="name@example.com" required>
                    <label for="email"><i class="fas fa-envelope me-2"></i>Email Address</label>
                </div>
                
                <div class="form-floating mb-3 password-container">
                    <input type="password" class="form-control" id="password" name="password" placeholder="Password" required>
                    <label for="password"><i class="fas fa-lock me-2"></i>Password</label>
                    <button type="button" class="password-toggle" id="passwordToggle" aria-label="Toggle password visibility">
                        <i class="fas fa-eye" id="toggleIcon"></i>
                    </button>
                </div>
                
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div class="form-check">
                        <input class="form-check-input remember-me" type="checkbox" value="1" id="remember" name="remember">
                        <label class="form-check-label small" for="remember">
                            Remember me
                        </label>
                    </div>
                    <a href="forgot_password.php" class="forgot-password">Forgot password?</a>
                </div>
                
                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-primary btn-login">
                        <i class="fas fa-sign-in-alt me-2"></i>Sign In
                    </button>
                    <a href="index.php" class="btn btn-outline-secondary mt-2">
                        <i class="fas fa-arrow-left me-2"></i>Back to Home
                    </a>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Initialize and show toasts
        document.addEventListener('DOMContentLoaded', function() {
            var toastElements = document.querySelectorAll('.toast');
            toastElements.forEach(function(toastEl) {
                var toast = new bootstrap.Toast(toastEl);
                toast.show();
            });
        });
        
        // Add loading state to login button
        document.getElementById('loginForm').addEventListener('submit', function() {
            const submitBtn = this.querySelector('button[type="submit"]');
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Signing In...';
            submitBtn.disabled = true;
        });
        
        // Password visibility toggle
        document.getElementById('passwordToggle').addEventListener('click', function() {
            const passwordInput = document.getElementById('password');
            const toggleIcon = document.getElementById('toggleIcon');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.className = 'fas fa-eye-slash';
                this.setAttribute('aria-label', 'Hide password');
            } else {
                passwordInput.type = 'password';
                toggleIcon.className = 'fas fa-eye';
                this.setAttribute('aria-label', 'Show password');
            }
        });
    </script>
</body>
</html>