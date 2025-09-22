<?php
session_start();
require_once '../db_connection.php';
require_once 'payroll_calculation/unified_payroll_calculator.php';
require_once '../includes/session_check.php';

// Validate session and require specific role
if (!validateSession($conn, 1)) { // 1 = superadmin role
    exit(); // validateSession handles the redirect
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo '<div class="alert alert-danger">Unauthorized access. Please log in first.</div>';
    exit;
}

// Date range filter logic (same as accounting dashboard)
$filterYear = $_GET['year'] ?? date('Y');
$filterMonth = $_GET['month'] ?? '';
$dateRange = $_GET['dateRange'] ?? '1-31';

// Determine date range based on filters
if (!empty($filterMonth)) {
    $currentMonth = "$filterYear-$filterMonth";
    if ($dateRange === '1-15') {
        $firstDay = $currentMonth . '-01';
        $lastDay = $currentMonth . '-15';
        $periodLabel = date('F', strtotime($firstDay)) . ' 1-15, ' . $filterYear;
    } elseif ($dateRange === '16-31') {
        $firstDay = $currentMonth . '-16';
        $lastDay = date('Y-m-t', strtotime($currentMonth));
        $periodLabel = date('F', strtotime($firstDay)) . ' 16-31, ' . $filterYear;
    } else { // 1-31
        $firstDay = $currentMonth . '-01';
        $lastDay = date('Y-m-t', strtotime($currentMonth));
        $periodLabel = date('F', strtotime($firstDay)) . ' 1-31, ' . $filterYear;
    }
} else {
    // If only year is specified, show the entire year
    $firstDay = "$filterYear-01-01";
    $lastDay = "$filterYear-12-31";
    $periodLabel = "Year $filterYear";
}

/**
 * Get payroll analytics data for specified period (copied from accounting dashboard)
 * @param PDO $conn Database connection
 * @param string $firstDay Start date
 * @param string $lastDay End date
 * @param PayrollCalculator $payrollCalculator Payroll calculator instance
 * @return array Payroll analytics data
 */
function getPayrollAnalytics($conn, $firstDay, $lastDay, $payrollCalculator) {
    // Get all active employees (Accounting, HR, and Guards)
    $guardsStmt = $conn->prepare("
        SELECT User_ID 
        FROM users 
        WHERE Role_ID IN (3, 4, 5) AND status = 'Active'
    ");
    $guardsStmt->execute();
    $guardIds = $guardsStmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Define analytics structure
    $analytics = [
        'earnings' => 0,
        'deductions' => 0,
        'netPay' => 0,
        'totalGross' => 0,
        'avgSalary' => 0,
        'guardWithPayCount' => 0,
        'activeGuards' => count($guardIds),
        'categories' => [
            'regular_hours_pay' => 0,
            'ot_pay' => 0,
            'special_holiday_pay' => 0,
            'special_holiday_ot_pay' => 0,
            'legal_holiday_pay' => 0,
            'night_diff_pay' => 0,
            'uniform_allowance' => 0,
            'ctp_allowance' => 0,
            'retroactive_pay' => 0,
            'gross_pay' => 0,
            'tax' => 0,
            'sss' => 0,
            'philhealth' => 0,
            'pagibig' => 0,
            'sss_loan' => 0,
            'pagibig_loan' => 0,
            'late_undertime' => 0,
            'cash_advance' => 0,
            'cash_bond' => 0,
            'other_deductions' => 0,
            'total_deductions' => 0,
            'net_pay' => 0
        ]
    ];
    
    // Calculate payroll for each guard and aggregate
    foreach ($guardIds as $guardId) {
        // Check for attendance in the selected period (ensure both time_in and time_out exist)
        $attendanceStmt = $conn->prepare("
            SELECT COUNT(*) 
            FROM attendance 
            WHERE User_ID = ? AND DATE(time_in) BETWEEN ? AND ? AND time_out IS NOT NULL
        ");
        $attendanceStmt->execute([$guardId, $firstDay, $lastDay]);
        $attendanceCount = $attendanceStmt->fetchColumn();
        
        if ($attendanceCount > 0) {
            // The calculatePayrollForGuard method exists and is a compatibility layer
            $payroll = $payrollCalculator->calculatePayrollForGuard($guardId, null, null, $firstDay, $lastDay);
            
            // Add to categories
            foreach ($analytics['categories'] as $key => $value) {
                $analytics['categories'][$key] += $payroll[$key] ?? 0;
            }
            
            // Ensure values are properly formatted and positive
            $netPay = max(0, $payroll['net_pay'] ?? 0);
            $grossPay = max(0, $payroll['gross_pay'] ?? 0);
            
            // Track counts - only count employees with actual pay
            if ($netPay > 0) {
                $analytics['guardWithPayCount']++;
                $analytics['netPay'] += $netPay;
                $analytics['totalGross'] += $grossPay;
            }
        }
    }
    
    // Calculate earnings, deductions, and average salary
    $analytics['earnings'] = $analytics['categories']['regular_hours_pay'] + 
                           $analytics['categories']['ot_pay'] + 
                           $analytics['categories']['special_holiday_pay'] + 
                           $analytics['categories']['special_holiday_ot_pay'] + 
                           $analytics['categories']['legal_holiday_pay'] + 
                           $analytics['categories']['night_diff_pay'] + 
                           $analytics['categories']['uniform_allowance'] + 
                           $analytics['categories']['ctp_allowance'] + 
                           $analytics['categories']['retroactive_pay'];
                           
    $analytics['deductions'] = $analytics['categories']['tax'] + 
                             $analytics['categories']['sss'] + 
                             $analytics['categories']['philhealth'] + 
                             $analytics['categories']['pagibig'] + 
                             $analytics['categories']['sss_loan'] + 
                             $analytics['categories']['pagibig_loan'] + 
                             $analytics['categories']['late_undertime'] + 
                             $analytics['categories']['cash_advance'] + 
                             $analytics['categories']['cash_bond'] + 
                             $analytics['categories']['other_deductions'];
                             
    $analytics['avgSalary'] = ($analytics['guardWithPayCount'] > 0) ? 
                            $analytics['netPay'] / $analytics['guardWithPayCount'] : 0;
    
    return $analytics;
}

// Initialize payroll calculator
$payrollCalculator = new PayrollCalculator($conn);

// Get payroll analytics
$payrollAnalytics = getPayrollAnalytics($conn, $firstDay, $lastDay, $payrollCalculator);

// Get total users
$userQuery = $conn->query("SELECT COUNT(*) AS total_users FROM users");
$userData = $userQuery->fetch(PDO::FETCH_ASSOC);
$totalUsers = $userData['total_users'];

// Get pending leave requests
$leaveQuery = $conn->query("SELECT COUNT(*) AS total_pending_requests FROM leave_requests WHERE Status = 'Pending'");
$leaveData = $leaveQuery->fetch(PDO::FETCH_ASSOC);
$pendingRequests = $leaveData['total_pending_requests'];

// Get guards currently clocked in
$attendanceQuery = $conn->query("SELECT COUNT(*) AS guards_clocked_in FROM attendance WHERE Time_Out IS NULL");
$attendanceData = $attendanceQuery->fetch(PDO::FETCH_ASSOC);
$guardsClocked = $attendanceData['guards_clocked_in'];

// Get current super admin user's name
$superadminStmt = $conn->prepare("SELECT First_Name, Last_Name FROM users WHERE Role_ID = 1 AND status = 'Active' AND User_ID = ?");
$superadminStmt->execute([$_SESSION['user_id']]);
$superadminData = $superadminStmt->fetch(PDO::FETCH_ASSOC);

// Add null check
if ($superadminData) {
    $superadminName = $superadminData['First_Name'] . ' ' . $superadminData['Last_Name'];
} else {
    $superadminName = "Super Admin";
}

// Get profile picture
$profileStmt = $conn->prepare("SELECT Profile_Pic, First_Name, Last_Name FROM users WHERE User_ID = ?");
$profileStmt->execute([$_SESSION['user_id']]);
$profileData = $profileStmt->fetch(PDO::FETCH_ASSOC);

// Add null check
if ($profileData && !empty($profileData['Profile_Pic']) && file_exists($profileData['Profile_Pic'])) {
    $superadminProfile = $profileData['Profile_Pic'];
} else {
    $superadminProfile = '../images/default_profile.png';
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Super Admin Dashboard - Green Meadows Security Agency</title>
    
    <!-- Include Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Google Material Icons -->
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Outlined" rel="stylesheet">
    
    <!-- Google Fonts - Poppins -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="css/superadmin_dashboard.css">

    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="logo-container">
            <img src="../images/greenmeadows_logo.jpg" alt="Green Meadows Logo" class="logo">
            <div class="agency-name">
                <div> SECURITY AGENCY</div>
            </div>
        </div>
        <ul class="nav flex-column mt-4">
            <li class="nav-item">
                <a class="nav-link active" data-bs-toggle="tooltip" data-bs-placement="right" title="Dashboard">
                    <span class="material-icons">dashboard</span>
                    <span>Dashboard</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="payroll.php" class="nav-link" data-bs-toggle="tooltip" data-bs-placement="right" title="Payroll">
                    <span class="material-icons">payments</span>
                    <span>Payroll</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="leave_request.php" class="nav-link" data-bs-toggle="tooltip" data-bs-placement="right" title="Leave Request">
                    <span class="material-icons">event_note</span>
                    <span>Leave Request</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="recruitment.php" class="nav-link" data-bs-toggle="tooltip" data-bs-placement="right" title="Recruitment">
                    <span class="material-icons">person_search</span>
                    <span>Recruitment</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="performance_evaluation.php" class="nav-link" data-bs-toggle="tooltip" data-bs-placement="right" title="Performance Evaluation">
                    <span class="material-icons">assessment</span>
                    <span>Performance Evaluation</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="users_list.php" class="nav-link" data-bs-toggle="tooltip" data-bs-placement="right" title="Masterlist">
                    <span class="material-icons">people</span>
                    <span>Masterlist</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="daily_time_record.php" class="nav-link" data-bs-toggle="tooltip" data-bs-placement="right" title="Daily Time Record">
                    <span class="material-icons">schedule</span>
                    <span>Daily Time Record</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="archives.php" class="nav-link" data-bs-toggle="tooltip" data-bs-placement="right" title="Archives">
                    <span class="material-icons">archive</span>
                    <span>Archives</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="logs.php" class="nav-link" data-bs-toggle="tooltip" data-bs-placement="right" title="Logs">
                    <span class="material-icons">receipt_long</span>
                    <span>Logs</span>
                </a>
            </li>
            <li class="nav-item mt-5">
                <a href="../logout.php" class="nav-link" data-bs-toggle="tooltip" data-bs-placement="right" title="Logout">
                    <span class="material-icons">logout</span>
                    <span>Logout</span>
                </a>
            </li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content" id="main-content">
        <!-- Header -->
        <div class="header">
            <button class="toggle-sidebar" id="toggleSidebar">
                <span class="material-icons">menu</span>
            </button>
            <div class="current-datetime ms-3 d-none d-md-block">
                    <span id="current-date"></span> | <span id="current-time"></span>
                </div>
                <a href="profile.php" class="user-profile" id="userProfile" style="color:black; text-decoration:none;">
                    <span><?php echo $superadminName; ?></span>
                    <img src="<?php echo $superadminProfile; ?>" alt="User Profile">
                </a>
        </div>
        
        <!-- Dashboard Content -->
        <div class="container-fluid mt-4">
            <h1 class="mb-4"><center>Welcome Super Admin!</center></h1><br>
            
            <!-- Date Range Filter Form -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="dashboard-card bg-white">
                        <div class="card-header bg-primary text-white">
                            <span class="material-icons">filter_list</span>
                            Payroll Analytics Filter
                        </div>
                        <hr class="divider">
                        <form method="GET" class="p-3">
                            <div class="row g-3">
                                <div class="col-md-3">
                                    <label for="year" class="form-label">Year</label>
                                    <select name="year" id="year" class="form-select">
                                        <?php for($y = date('Y'); $y >= 2020; $y--): ?>
                                            <option value="<?php echo $y; ?>" <?php echo ($filterYear == $y) ? 'selected' : ''; ?>>
                                                <?php echo $y; ?>
                                            </option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label for="month" class="form-label">Month</label>
                                    <select name="month" id="month" class="form-select">
                                        <option value="">All Months</option>
                                        <?php for($m = 1; $m <= 12; $m++): ?>
                                            <option value="<?php echo sprintf('%02d', $m); ?>" <?php echo ($filterMonth == sprintf('%02d', $m)) ? 'selected' : ''; ?>>
                                                <?php echo date('F', mktime(0, 0, 0, $m, 1)); ?>
                                            </option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label for="dateRange" class="form-label">Date Range</label>
                                    <select name="dateRange" id="dateRange" class="form-select">
                                        <option value="1-31" <?php echo ($dateRange === '1-31') ? 'selected' : ''; ?>>Full Month</option>
                                        <option value="1-15" <?php echo ($dateRange === '1-15') ? 'selected' : ''; ?>>1st to 15th</option>
                                        <option value="16-31" <?php echo ($dateRange === '16-31') ? 'selected' : ''; ?>>16th to End</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">&nbsp;</label>
                                    <button type="submit" class="btn btn-primary w-100" id="filterBtn">
                                        <span class="material-icons">search</span>
                                        Filter Analytics
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <h2 class="mb-4"><center>Analytics for <?php echo $periodLabel; ?></center></h2>
            
            <div class="row">
               <!-- Total Users Card -->
                <div class="col-md-4 mb-4">
                    <div class="dashboard-card">
                        <div class="card-header">
                            <span class="material-icons">security</span>
                            Total Users
                        </div>
                        <hr class="divider">
                        <div class="card-value"><?php echo $totalUsers; ?></div>
                        <div class="card-label">Total users in the system.</div>
                    </div>
                </div>

                <!-- Pending Leave Requests Card -->
                <div class="col-md-4 mb-4">
                    <div class="dashboard-card" style="background-color: <?php echo ($pendingRequests > 0) ? '#dc3545' : '#28a745'; ?>">
                        <div class="card-header">
                            <span class="material-icons"><?php echo ($pendingRequests > 0) ? 'pending_actions' : 'check_circle'; ?></span>
                            Pending Leave Requests
                        </div>
                        <hr class="divider">
                        <div class="card-value"><?php echo $pendingRequests; ?></div>
                        <div class="card-label"><?php echo ($pendingRequests > 0) ? 'Total requests awaiting approval.' : 'No pending requests.'; ?></div>
                    </div>
                </div>

                <!-- Attendance Insights Card -->
                <div class="col-md-4 mb-4">
                    <div class="dashboard-card">
                        <div class="card-header">
                            <span class="material-icons">how_to_reg</span>
                            Attendance Insights
                        </div>
                        <hr class="divider">
                        <div class="card-value"><?php echo $guardsClocked; ?></div>
                        <div class="card-label">Guards Currently Clocked In.</div>
                    </div>
                </div>
                </div>

    <!-- Payroll Analytics Section -->
    <div class="container-fluid mt-5">
        <h2 class="mb-4"><center>Payroll Analytics</center></h2>
        
        <div class="row mb-4">
            <?php if ($payrollAnalytics['guardWithPayCount'] > 0 && $payrollAnalytics['totalGross'] > 0): ?>
                <!-- Payroll Data Found - Show Analytics Cards -->
                <div class="col-md-3 mb-4">
                    <div class="dashboard-card bg-success">
                        <div class="card-header text-white">
                            <span class="material-icons">payments</span>
                            Total Net Pay
                        </div>
                        <hr class="divider">
                        <div class="card-value text-white">₱<?php echo number_format($payrollAnalytics['netPay'], 2); ?></div>
                        <div class="card-label text-white">Total amount paid to employees</div>
                    </div>
                </div>
                
                <div class="col-md-3 mb-4">
                    <div class="dashboard-card bg-info">
                        <div class="card-header text-white">
                            <span class="material-icons">account_balance_wallet</span>
                            Total Gross Pay
                        </div>
                        <hr class="divider">
                        <div class="card-value text-white">₱<?php echo number_format($payrollAnalytics['totalGross'], 2); ?></div>
                        <div class="card-label text-white">Total gross amount before deductions</div>
                    </div>
                </div>
                
                <div class="col-md-3 mb-4">
                    <div class="dashboard-card bg-warning">
                        <div class="card-header text-white">
                            <span class="material-icons">group</span>
                            Employees Paid
                        </div>
                        <hr class="divider">
                        <div class="card-value text-white"><?php echo $payrollAnalytics['guardWithPayCount']; ?></div>
                        <div class="card-label text-white">Number of employees processed</div>
                    </div>
                </div>
                
                <div class="col-md-3 mb-4">
                    <div class="dashboard-card bg-primary">
                        <div class="card-header text-white">
                            <span class="material-icons">trending_up</span>
                            Average Salary
                        </div>
                        <hr class="divider">
                        <div class="card-value text-white">₱<?php echo number_format($payrollAnalytics['avgSalary'], 2); ?></div>
                        <div class="card-label text-white">Average salary per employee</div>
                    </div>
                </div>
                
                <!-- Additional Summary Section -->
                <div class="col-12 mb-4">
                    <div class="dashboard-card bg-white text-dark">
                        <div class="card-header bg-success text-white">
                            <span class="material-icons">summarize</span>
                            Payroll Summary for <?php echo $periodLabel; ?>
                        </div>
                        <hr class="divider bg-primary">
                        <div class="p-4">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="summary-item">
                                        <span class="summary-label">Total Earnings:</span>
                                        <span class="summary-value">₱<?php echo number_format($payrollAnalytics['earnings'], 2); ?></span>
                                    </div>
                                    
                                    <div class="summary-item">
                                        <span class="summary-label">Total Deductions:</span>
                                        <span class="summary-value">₱<?php echo number_format($payrollAnalytics['deductions'], 2); ?></span>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="summary-item">
                                        <span class="summary-label">Active Guards:</span>
                                        <span class="summary-value"><?php echo $payrollAnalytics['activeGuards']; ?></span>
                                    </div>
                                    
                                    <div class="summary-item">
                                        <span class="summary-label">Average Salary per Guard:</span>
                                        <span class="summary-value">₱<?php echo number_format($payrollAnalytics['avgSalary'], 2); ?></span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="text-center mt-4 mb-3">
                                <a href="payroll.php" class="btn btn-success">
                                    <span class="material-icons">payments</span> View Full Payroll
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <!-- No Payroll Data Found - Show Message -->
                <div class="col-12 mb-4">
                    <div class="dashboard-card bg-white text-dark">
                        <div class="card-header bg-success text-white">
                            <span class="material-icons">info</span>
                            Payroll Information
                        </div>
                        <hr class="divider bg-primary">
                        <div class="text-center p-5">
                            <span class="material-icons" style="font-size: 48px; color: #6c757d;">search_off</span>
                            <h4 class="mt-3 text-muted">No payroll data found for this date period.</h4>
                            <p class="text-muted mb-2"><strong><?php echo $periodLabel; ?></strong></p>
                            <p class="text-muted">Try selecting a different date range or check if payroll has been processed.</p>
                            <a href="payroll.php" class="btn btn-outline-success mt-3">
                                <span class="material-icons">payments</span> Go to Payroll
                            </a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Update Profile Modal -->
    <div class="modal fade" id="profileModal" tabindex="-1" aria-labelledby="profileModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="profileModalLabel">Update Profile</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <!-- Add method and enctype for file uploads -->
                    <form id="updateProfileForm" method="POST" action="update_profile.php" enctype="multipart/form-data">
                        <div class="mb-3">
                            <label for="profilePic" class="form-label">Profile Image</label>
                            <div class="text-center mb-3">
                                <!-- Current profile image -->
                                <img id="currentProfileImage" src="<?php echo !empty($superadminProfile) ? $superadminProfile : '../assets/images/profile.jpg'; ?>" 
                                    alt="Current Profile" class="rounded-circle" width="100" height="100">
                                    
                                <!-- Preview container (initially hidden) -->
                                <div id="imagePreviewContainer" style="display: none;" class="mt-3">
                                    <p id="previewText" class="text-muted mb-2"></p>
                                    <img id="imagePreview" src="#" alt="Image Preview" class="rounded-circle" width="100" height="100">
                                </div>
                            </div>
                            <input type="file" class="form-control" id="profilePic" name="profilePic" 
                                accept=".jpg,.jpeg,.png,.avif">
                            <small class="form-text text-muted">Accepted file types: JPG, PNG, AVIF. Max size: 5MB</small>
                        </div>
                        <div class="mb-3">
                            <label for="firstName" class="form-label">First Name</label>
                            <input type="text" class="form-control" id="firstName" name="firstName" 
                                value="<?php echo $superadminData['First_Name']; ?>">
                        </div>
                        <div class="mb-3">
                            <label for="lastName" class="form-label">Last Name</label>
                            <input type="text" class="form-control" id="lastName" name="lastName" 
                                value="<?php echo $superadminData['Last_Name']; ?>">
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-danger" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-success">Save Changes</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- SWAL Alerts for Profile Picture -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            <?php if(isset($_SESSION['profilepic_success'])): ?>
                Swal.fire({
                    icon: 'success',
                    title: 'Success!',
                    text: '<?php echo $_SESSION['profilepic_success']; ?>',
                    confirmButtonColor: '#2a7d4f'
                });
                <?php unset($_SESSION['profilepic_success']); ?>
            <?php endif; ?>

            <?php if(isset($_SESSION['profilepic_error'])): ?>
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: '<?php echo $_SESSION['profilepic_error']; ?>',
                    confirmButtonColor: '#dc3545'
                });
                <?php unset($_SESSION['profilepic_error']); ?>
            <?php endif; ?>
            
            // Check if page was loaded with filter parameters
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.has('year') || urlParams.has('month') || urlParams.has('dateRange')) {
                // Get filter values
                const year = urlParams.get('year') || '<?php echo date('Y'); ?>';
                const month = urlParams.get('month') || '';
                const dateRange = urlParams.get('dateRange') || 'Full Month';
                
                // Create filter description
                let filterDescription = `Year: ${year}`;
                if (month) {
                    const monthNames = ['', 'January', 'February', 'March', 'April', 'May', 'June',
                                      'July', 'August', 'September', 'October', 'November', 'December'];
                    filterDescription += `, Month: ${monthNames[parseInt(month)]}`;
                }
                
                const rangeText = dateRange === '1-15' ? '1st to 15th' : 
                                dateRange === '16-31' ? '16th to End' : 'Full Month';
                filterDescription += `, Range: ${rangeText}`;
                
                // Show success toast
                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: 'success',
                    title: 'Filter Applied Successfully!',
                    text: filterDescription,
                    showConfirmButton: false,
                    timer: 4000,
                    timerProgressBar: true,
                    background: '#d4edda',
                    color: '#155724'
                });
            }
            
            // Handle filter form submission
            const filterForm = document.querySelector('form[method="GET"]');
            const filterBtn = document.getElementById('filterBtn');
            
            if (filterForm && filterBtn) {
                filterForm.addEventListener('submit', function(e) {
                    // Show loading toast
                    Swal.fire({
                        toast: true,
                        position: 'top-end',
                        icon: 'info',
                        title: 'Applying Filter...',
                        text: 'Please wait while we update the analytics',
                        showConfirmButton: false,
                        timer: 2000,
                        timerProgressBar: true,
                        background: '#d1ecf1',
                        color: '#0c5460'
                    });
                    
                    // Disable button temporarily
                    filterBtn.disabled = true;
                    filterBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Loading...';
                    
                    // Re-enable after short delay
                    setTimeout(() => {
                        filterBtn.disabled = false;
                        filterBtn.innerHTML = '<span class="material-icons">search</span> Filter Analytics';
                    }, 2000);
                });
            }
        });
    </script>

    <!-- Profile Picture Preview Script -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Get the file input and preview image elements
            const profilePicInput = document.getElementById('profilePic');
            const previewContainer = document.getElementById('imagePreviewContainer');
            const previewImage = document.getElementById('imagePreview');
            const currentImage = document.getElementById('currentProfileImage');
            
            // Listen for file selection
            profilePicInput.addEventListener('change', function() {
                const file = this.files[0];
                
                // Check if a file was selected
                if (file) {
                    // Show the preview container
                    previewContainer.style.display = 'block';
                    
                    // Hide the current image
                    if (currentImage) {
                        currentImage.style.display = 'none';
                    }
                    
                    // Create a FileReader to read the image
                    const reader = new FileReader();
                    
                    // Set up the FileReader onload event
                    reader.onload = function(e) {
                        // Set the preview image source to the loaded data URL
                        previewImage.src = e.target.result;
                    }
                    
                    // Read the file as a data URL
                    reader.readAsDataURL(file);
                    
                } else {
                    // If no file selected or selection canceled, show current image
                    previewContainer.style.display = 'none';
                    if (currentImage) {
                        currentImage.style.display = 'block';
                    }
                }
            });
        });
    </script>

    <!-- Bootstrap and jQuery JS -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/superadmin_dashboard.js"></script>

    <!-- Mobile Bottom Navigation -->
    <div class="mobile-nav">
        <div class="mobile-nav-container">
            <a href="superadmin_dashboard.php" class="mobile-nav-item active">
                <span class="material-icons">dashboard</span>
                <span class="mobile-nav-text">Dashboard</span>
            </a>
            <a href="payroll.php" class="mobile-nav-item">
                <span class="material-icons">payments</span>
                <span class="mobile-nav-text">Payroll</span>
            </a>
            <a href="leave_request.php" class="mobile-nav-item">
                <span class="material-icons">event_note</span>
                <span class="mobile-nav-text">Leave Request</span>
            </a>
            <a href="recruitment.php" class="mobile-nav-item">
                <span class="material-icons">person_search</span>
                <span class="mobile-nav-text">Recruitment</span>
            </a>
            <a href="performance_evaluation.php" class="mobile-nav-item">
                <span class="material-icons">assessment</span>
                <span class="mobile-nav-text">Performance Evaluation</span>
            </a>
            <a href="users_list.php" class="mobile-nav-item">
                <span class="material-icons">people</span>
                <span class="mobile-nav-text">Masterlist</span>
            </a>
            <a href="daily_time_record.php" class="mobile-nav-item">
                <span class="material-icons">schedule</span>
                <span class="mobile-nav-text">Daily Time Record</span>
            </a>
            <a href="archives.php" class="mobile-nav-item">
                <span class="material-icons">archive</span>
                <span class="mobile-nav-text">Archives</span>
            </a>
            <a href="logs.php" class="mobile-nav-item">
                <span class="material-icons">receipt_long</span>
                <span class="mobile-nav-text">Logs</span>
            </a>
            <a href="../logout.php" class="mobile-nav-item">
                <span class="material-icons">logout</span>
                <span class="mobile-nav-text">Logout</span>
            </a>
        </div>
    </div>

    <!-- Custom CSS for Summary Items -->
    <style>
        .summary-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #e9ecef;
        }
        
        .summary-item:last-child {
            border-bottom: none;
        }
        
        .summary-label {
            font-weight: 600;
            color: #495057;
        }
        
        .summary-value {
            font-weight: bold;
            color: #28a745;
            font-size: 1.1em;
        }
        
        .chart-container {
            position: relative;
            height: 300px;
            padding: 20px;
        }
    </style>

</body>
</html>