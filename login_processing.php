<?php
session_start();
include 'db_connection.php';

// Check if database connection exists
if (!isset($conn)) {
    die("Database connection failed");
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']) ? true : false;
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';

    // Validate inputs
    if (empty($email)) {
        $_SESSION['toast_error'] = "Please enter your email.";
        header("Location: login.php");
        exit();
    } else if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['toast_error'] = "Please enter a valid email address.";
        header("Location: login.php");
        exit();
    }
    
    if (empty($password)) {
        $_SESSION['toast_error'] = "Please enter your password.";
        header("Location: login.php");
        exit();
    }

    // Check if account is locked
    $lockout_check = $conn->prepare("SELECT locked_until, failed_attempts FROM account_lockouts WHERE email = ? AND locked_until > NOW()");
    $lockout_check->execute([$email]);
    $lockout = $lockout_check->fetch(PDO::FETCH_ASSOC);
    
    if ($lockout) {
        $remaining_time = strtotime($lockout['locked_until']) - time();
        $minutes = ceil($remaining_time / 60);
        $_SESSION['toast_error'] = "Account locked due to multiple failed attempts. Try again in {$minutes} minutes.";
        header("Location: login.php");
        exit();
    }

    // Check for user in database
    $sql = "SELECT User_ID, Username, Email, Password_Hash, Role_ID, First_Name, Last_Name, status FROM users WHERE Email = :email";
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':email', $email);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        // Log failed attempt
        recordFailedAttempt($conn, $email, $ip_address, $user_agent);
        $_SESSION['toast_error'] = "Invalid email or password.";
        header("Location: login.php");
        exit();
    } 
    
    // Check if account is active
    if ($user['status'] !== 'Active') {
        $_SESSION['toast_error'] = "Your account is inactive. Please contact the administrator.";
        header("Location: login.php");
        exit();
    }
    
    // Verify password
    if (!password_verify($password, $user['Password_Hash'])) {
        // Log failed attempt and check for lockout
        recordFailedAttempt($conn, $email, $ip_address, $user_agent);
        checkAndLockAccount($conn, $email, $ip_address, $user['First_Name'] . ' ' . $user['Last_Name']);
        $_SESSION['toast_error'] = "Invalid email or password.";
        header("Location: login.php");
        exit();
    }
    
    // Valid login - Clear any failed attempts and create session
    clearFailedAttempts($conn, $email);
    recordSuccessfulAttempt($conn, $email, $ip_address, $user_agent);
    
    $session_id = bin2hex(random_bytes(32));
    $remember_token = $remember ? bin2hex(random_bytes(32)) : null;
    $expires_at = $remember ? date('Y-m-d H:i:s', time() + (86400 * 30)) : date('Y-m-d H:i:s', time() + (86400 * 1)); // 30 days if remember, 1 day otherwise
    
    try {
        // Clean up old sessions for this user
        $stmt = $conn->prepare("UPDATE user_sessions SET is_active = 0 WHERE user_id = ?");
        $stmt->execute([$user['User_ID']]);
        
        // Create new session record
        $stmt = $conn->prepare("
            INSERT INTO user_sessions (session_id, user_id, remember_token, ip_address, user_agent, expires_at) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $session_id,
            $user['User_ID'],
            $remember_token,
            $ip_address,
            $user_agent,
            $expires_at
        ]);
        
        // Set session variables
        $_SESSION['user_id'] = $user['User_ID'];
        $_SESSION['role_id'] = $user['Role_ID'];
        $_SESSION['username'] = $user['Username'];
        $_SESSION['name'] = $user['First_Name'] . ' ' . $user['Last_Name'];
        $_SESSION['session_id'] = $session_id;

        // Set remember me cookie
        if ($remember && $remember_token) {
            setcookie('remember_token', $remember_token, time() + (86400 * 30), "/", "", false, true);
        }

        // Log successful login
        try {
            $stmt = $conn->prepare("
                INSERT INTO activity_logs (User_ID, Activity_Type, Activity_Details) 
                VALUES (?, 'Login', ?)
            ");
            $stmt->execute([$user['User_ID'], 'User logged in from IP: ' . $ip_address]);
        } catch (PDOException $e) {
            // Log error but don't stop login process
            error_log("Failed to log login activity: " . $e->getMessage());
        }
        
        // Redirect based on role
        switch ($user['Role_ID']) {
            case 1: // Super Admin
                header("Location: super_admin/superadmin_dashboard.php");
                break;
            case 2: // Admin
                header("Location: admin/admin_dashboard.php");
                break;
            case 3: // HR
                header("Location: hr/hr_dashboard.php");
                break;
            case 4: // Accounting
                header("Location: accounting/accounting_dashboard.php");
                break;
            case 5: // Security Guard
                header("Location: guards/guards_dashboard.php");
                break;
            default:
                $_SESSION['toast_error'] = "Invalid user role. Please contact administrator.";
                header("Location: login.php");
                exit();
        }
        exit();
        
    } catch (PDOException $e) {
        $_SESSION['toast_error'] = "Login failed. Please try again.";
        error_log("Login error: " . $e->getMessage());
        header("Location: login.php");
        exit();
    }
} else {
    header("Location: login.php");
    exit();
}

// Helper functions
function recordFailedAttempt($conn, $email, $ip_address, $user_agent) {
    $stmt = $conn->prepare("INSERT INTO login_attempts (email, ip_address, success, user_agent) VALUES (?, ?, 0, ?)");
    $stmt->execute([$email, $ip_address, $user_agent]);
}

function recordSuccessfulAttempt($conn, $email, $ip_address, $user_agent) {
    $stmt = $conn->prepare("INSERT INTO login_attempts (email, ip_address, success, user_agent) VALUES (?, ?, 1, ?)");
    $stmt->execute([$email, $ip_address, $user_agent]);
}

function clearFailedAttempts($conn, $email) {
    // Remove any existing lockout
    $stmt = $conn->prepare("DELETE FROM account_lockouts WHERE email = ?");
    $stmt->execute([$email]);
    
    // Clean old failed attempts (older than 30 minutes)
    $stmt = $conn->prepare("DELETE FROM login_attempts WHERE email = ? AND success = 0 AND attempt_time < DATE_SUB(NOW(), INTERVAL 30 MINUTE)");
    $stmt->execute([$email]);
}

function checkAndLockAccount($conn, $email, $ip_address, $full_name) {
    // Count failed attempts in last 30 minutes
    $stmt = $conn->prepare("SELECT COUNT(*) as failed_count FROM login_attempts WHERE email = ? AND success = 0 AND attempt_time > DATE_SUB(NOW(), INTERVAL 30 MINUTE)");
    $stmt->execute([$email]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result['failed_count'] >= 5) {
        // Lock the account for 30 minutes
        $locked_until = date('Y-m-d H:i:s', time() + (30 * 60));
        
        // Insert or update lockout record
        $stmt = $conn->prepare("
            INSERT INTO account_lockouts (email, ip_address, locked_until, failed_attempts) 
            VALUES (?, ?, ?, ?) 
            ON DUPLICATE KEY UPDATE 
            locked_until = VALUES(locked_until), 
            failed_attempts = VALUES(failed_attempts),
            unlocked_at = NULL,
            unlocked_by = NULL
        ");
        $stmt->execute([$email, $ip_address, $locked_until, $result['failed_count']]);
        
        // Send email notification
        sendLockoutEmail($email, $full_name, $ip_address, $result['failed_count']);
    }
}

function sendLockoutEmail($email, $full_name, $ip_address, $attempts) {
    // Get current time in Philippines timezone
    date_default_timezone_set('Asia/Manila');
    $current_time = date('F j, Y — \a\r\o\u\n\d g:i A');
    
    // Mask IP for security
    $masked_ip = maskIP($ip_address);
    
    $subject = "❗ Suspicious Login Attempt Detected on Your Account";
    
    $body = "
    <html>
    <head>
        <meta charset='UTF-8'>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background-color: #dc3545; color: white; padding: 20px; text-align: center; }
            .content { padding: 20px; background-color: #f8f9fa; }
            .warning { background-color: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; margin: 20px 0; border-radius: 5px; }
            .button { display: inline-block; padding: 12px 24px; background-color: #2a7d4f; color: white; text-decoration: none; border-radius: 5px; margin: 10px 5px; }
            .footer { text-align: center; margin-top: 30px; color: #666; font-size: 12px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2>🔒 Account Security Alert</h2>
            </div>
            <div class='content'>
                <p>Hello <strong>{$full_name}</strong>,</p>
                
                <p>We detected multiple failed login attempts to your Green Meadows Security HRIS account.</p>
                
                <div class='warning'>
                    <strong>Attempt Details:</strong><br>
                    📅 <strong>Time:</strong> {$current_time}<br>
                    🌐 <strong>IP Address:</strong> {$masked_ip}<br>
                    📍 <strong>Location (approx.):</strong> Calamba, Laguna, Philippines<br>
                    🚫 <strong>Failed Attempts:</strong> {$attempts}
                </div>
                
                <p><strong>Your account has been temporarily locked for 30 minutes</strong> for security reasons.</p>
                
                <p>If this was not you, we recommend that you change your password immediately after the lockout period expires.</p>
                
                <div style='text-align: center; margin: 30px 0;'>
                    <a href='http://localhost/HRIS/forgot_password.php' class='button'>🔑 Reset Password</a>
                    <a href='http://localhost/HRIS/login.php' class='button'>🔗 Back to Login</a>
                </div>
                
                <p>If you believe this is a mistake or need immediate assistance, please contact our IT support team.</p>
                
                <p>Thank you,<br>
                <strong>The Green Meadows Security Team</strong></p>
            </div>
            <div class='footer'>
                <p>This is an automated security notification from Green Meadows Security Agency HRIS System.</p>
                <p>Please do not reply to this email.</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    // Use PHP mail function (you can replace this with PHPMailer or other email service)
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: Green Meadows Security <noreply@greenmeadowssecurity.com>" . "\r\n";
    $headers .= "Reply-To: support@greenmeadowssecurity.com" . "\r\n";
    
    // Send email
    @mail($email, $subject, $body, $headers);
}

function maskIP($ip) {
    $parts = explode('.', $ip);
    if (count($parts) == 4) {
        return $parts[0] . '.' . $parts[1] . '.' . $parts[2] . '.XX';
    }
    return 'Unknown';
}
?>