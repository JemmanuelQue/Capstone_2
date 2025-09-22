<?php
session_start();
require_once '../db_connection.php';
require_once '../includes/session_check.php';

// Validate session and require specific role
if (!validateSession($conn, 5)) { // 5 = guard role
    exit(); // validateSession handles the redirect
}

$userId = $_SESSION['user_id'];
$currentMonth = date('Y-m');
$currentYear = date('Y');
$firstDayOfMonth = date('Y-m-01');
$lastDayOfMonth = date('Y-m-t');

// Date filter handling
$dateFrom = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-01');
$dateTo = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-t');

// Period filter handling (for bi-monthly view)
$period = isset($_GET['period']) ? $_GET['period'] : '';
$selectedMonth = isset($_GET['month']) ? $_GET['month'] : date('m');
$selectedYear = isset($_GET['year']) ? $_GET['year'] : date('Y');
$selectedMonthYear = "$selectedYear-$selectedMonth";

// Current day to determine which half of month we're in
$currentDay = date('d');
$isFirstHalf = $currentDay <= 15;

// Default to appropriate period if none selected
if (empty($period)) {
    $period = $isFirstHalf ? 'first' : 'second';
}

// Set period date ranges based on selection
if ($period == 'first') {
    $periodStart = "$selectedYear-$selectedMonth-01";
    $periodEnd = "$selectedYear-$selectedMonth-15";
    $periodTitle = "Period (1-15 " . date('M', strtotime($periodStart)) . ")";
} else if ($period == 'second') {
    $periodStart = "$selectedYear-$selectedMonth-16";
    $periodEnd = date('Y-m-t', strtotime("$selectedYear-$selectedMonth-01"));
    $periodTitle = "Period (16-" . date('t', strtotime($selectedMonthYear)) . " " . date('M', strtotime($periodStart)) . ")";
} else if ($period == 'full') {
    $periodStart = "$selectedYear-$selectedMonth-01";
    $periodEnd = date('Y-m-t', strtotime("$selectedYear-$selectedMonth-01"));
    $periodTitle = "Full Month of " . date('F Y', strtotime($periodStart));
} else if ($period == 'previous_month') {
    $periodStart = date('Y-m-01', strtotime('first day of last month'));
    $periodEnd = date('Y-m-t', strtotime('last day of last month'));
    $periodTitle = date('F Y', strtotime('last month'));
} else if ($period == 'current_year') {
    $periodStart = date('Y-01-01');
    $periodEnd = date('Y-12-31');
    $periodTitle = "Year " . date('Y');
} else {
    if ($isFirstHalf) {
        $periodStart = date('Y-m-01');
        $periodEnd = date('Y-m-15');
        $periodTitle = "Current Period (1-15 " . date('M') . ")";
    } else {
        $periodStart = date('Y-m-16');
        $periodEnd = date('Y-m-t');
        $periodTitle = "Current Period (16-" . date('t') . " " . date('M') . ")";
    }
}

// Get user profile data - FIXED QUERY by removing shift_type
$profileStmt = $conn->prepare("
    SELECT u.Profile_Pic, u.First_Name, u.Last_Name, u.Email, u.phone_number, 
           gl.location_name, gl.daily_rate
    FROM users u
    LEFT JOIN guard_locations gl ON u.User_ID = gl.user_id AND gl.is_active = 1 AND gl.is_primary = 1
    WHERE u.User_ID = ?
");
$profileStmt->execute([$userId]);
$profileData = $profileStmt->fetch(PDO::FETCH_ASSOC);

// Set default profile pic if none exists
if (!$profileData || empty($profileData['Profile_Pic']) || !file_exists($profileData['Profile_Pic'])) {
    $profileData['Profile_Pic'] = '../images/default_profile.png';
}

// Get attendance statistics for current selected period (not just current month)
$attendanceStmt = $conn->prepare("
    SELECT 
        COUNT(*) as total_days,
        SUM(CASE WHEN TIME(Time_In) <= '08:00:00' THEN 1 ELSE 0 END) as on_time_days,
        SUM(CASE WHEN TIME(Time_In) > '08:00:00' THEN 1 ELSE 0 END) as late_days,
        SUM(TIMESTAMPDIFF(HOUR, Time_In, IFNULL(Time_Out, NOW()))) as total_hours
    FROM attendance
    WHERE User_ID = ? AND DATE(Time_In) BETWEEN ? AND ?
");
$attendanceStmt->execute([$userId, $periodStart, $periodEnd]);
$attendanceStats = $attendanceStmt->fetch(PDO::FETCH_ASSOC);

// Get filtered attendance statistics based on selected period
$filteredStatsStmt = $conn->prepare("
    SELECT 
        COUNT(*) as total_days,
        SUM(CASE WHEN TIME(Time_In) <= '08:00:00' THEN 1 ELSE 0 END) as on_time_days,
        SUM(CASE WHEN TIME(Time_In) > '08:00:00' THEN 1 ELSE 0 END) as late_days,
        SUM(TIMESTAMPDIFF(HOUR, Time_In, IFNULL(Time_Out, NOW()))) as total_hours
    FROM attendance
    WHERE User_ID = ? AND Time_In BETWEEN ? AND ?
");
$filteredStatsStmt->execute([$userId, $periodStart . ' 00:00:00', $periodEnd . ' 23:59:59']);
$filteredStats = $filteredStatsStmt->fetch(PDO::FETCH_ASSOC);

// Calculate percentages
$filteredStats['on_time_percentage'] = ($filteredStats['total_days'] > 0) ? 
    ($filteredStats['on_time_days'] / $filteredStats['total_days']) * 100 : 0;

// Get leave requests statistics
$leaveRequestStmt = $conn->prepare("
    SELECT 
        COUNT(*) as total_requests,
        SUM(CASE WHEN Status = 'Approved' THEN 1 ELSE 0 END) as approved_requests,
        SUM(CASE WHEN Status = 'Pending' THEN 1 ELSE 0 END) as pending_requests,
        SUM(CASE WHEN Status = 'Rejected' THEN 1 ELSE 0 END) as rejected_requests
    FROM leave_requests
    WHERE User_ID = ? AND YEAR(Request_Date) = ?
");
$leaveRequestStmt->execute([$userId, $currentYear]);
$leaveRequestStats = $leaveRequestStmt->fetch(PDO::FETCH_ASSOC);

// Get current payroll information
$currentMonth = date('Y-m');
$currentDay = date('d');

// Determine current pay period based on date
if ($currentDay <= 15) {
    $payrollPeriodStart = date('Y-m-01'); // 1st of current month
    $payrollPeriodEnd = date('Y-m-15');   // 15th of current month
    $payPeriodLabel = "1-15 " . date('M Y');
} else {
    $payrollPeriodStart = date('Y-m-16'); // 16th of current month
    $payrollPeriodEnd = date('Y-m-t');    // Last day of current month
    $payPeriodLabel = "16-" . date('t') . " " . date('M Y');
}

// Use the selected period dates for payroll info instead of current period
$payrollPeriodStart = $periodStart;
$payrollPeriodEnd = $periodEnd;
$payPeriodLabel = $periodTitle;

// First try to get existing payroll record for the SELECTED period
$payrollStmt = $conn->prepare("
    SELECT 
        Period_Start, 
        Period_End, 
        Reg_Hours, 
        Net_Salary
    FROM payroll
    WHERE User_ID = ? AND Period_Start = ? AND Period_End = ?
    LIMIT 1
");
$payrollStmt->execute([$userId, $payrollPeriodStart, $payrollPeriodEnd]);
$payrollInfo = $payrollStmt->fetch(PDO::FETCH_ASSOC);

// If no payroll record exists, calculate from attendance
if (!$payrollInfo) {
    try {
        // Get attendance hours with proper handling of overnight shifts
        $hoursStmt = $conn->prepare("
            SELECT COALESCE(SUM(
                CASE 
                    WHEN Time_Out IS NULL THEN 
                        TIMESTAMPDIFF(HOUR, Time_In, NOW())
                    ELSE 
                        CASE
                            WHEN TIME(Time_Out) < TIME(Time_In) THEN
                                -- Overnight shift: add 24 hours to time_out before calculating
                                TIMESTAMPDIFF(HOUR, Time_In, DATE_ADD(Time_Out, INTERVAL 1 DAY))
                            ELSE
                                TIMESTAMPDIFF(HOUR, Time_In, Time_Out)
                        END
                END
            ), 0) as total_hours
            FROM attendance
            WHERE User_ID = ? AND DATE(Time_In) BETWEEN ? AND ?
        ");
        $hoursStmt->execute([$userId, $payrollPeriodStart, $payrollPeriodEnd]);
        $hoursData = $hoursStmt->fetch(PDO::FETCH_ASSOC);
        $totalHours = $hoursData['total_hours'] ?? 0;
        
        // Get daily rate from guard location
        $rateStmt = $conn->prepare("
            SELECT daily_rate FROM guard_locations 
            WHERE user_id = ? AND is_active = 1 AND is_primary = 1
        ");
        $rateStmt->execute([$userId]);
        $rateData = $rateStmt->fetch(PDO::FETCH_ASSOC);
        $dailyRate = $rateData['daily_rate'] ?? 540; // Default to 540 if not found
        
        // Calculate pay directly (8 hours = 1 day)
        $hourlyRate = $dailyRate / 8;
        $estimatedPay = $totalHours * $hourlyRate;
        
        // Create payroll info array with direct calculation
        $payrollInfo = [
            'Period_Start' => $payrollPeriodStart,
            'Period_End' => $payrollPeriodEnd,
            'Reg_Hours' => $totalHours,
            'Net_Salary' => $estimatedPay
        ];
        
    } catch (Exception $e) {
        // Log error and create a minimal record
        $payrollInfo = [
            'Period_Start' => $payrollPeriodStart,
            'Period_End' => $payrollPeriodEnd,
            'Reg_Hours' => 0,
            'Net_Salary' => 0
        ];
    }
}

// Get attendance records for the selected period
$periodAttendanceStmt = $conn->prepare("
    SELECT 
        Time_In, 
        Time_Out,
        face_verified,
        Updated_At,
        location_verified
    FROM attendance
    WHERE User_ID = ? AND Time_In BETWEEN ? AND ? 
    ORDER BY Time_In DESC
");
$periodAttendanceStmt->execute([$userId, $periodStart . ' 00:00:00', $periodEnd . ' 23:59:59']);
$periodAttendance = $periodAttendanceStmt->fetchAll(PDO::FETCH_ASSOC);

// Check if guard has registered face
$faceRegisteredStmt = $conn->prepare("SELECT COUNT(*) FROM guard_faces WHERE guard_id = ?");
$faceRegisteredStmt->execute([$userId]);
$faceRegistered = $faceRegisteredStmt->fetchColumn() > 0;

// Calculate working days based on the selected period
if ($period == 'first') {
    // First half of month (1-15): Always 15 days
    $workingDaysInPeriod = 15;
} else if ($period == 'second') {
    // Second half of month (16-end): Days in month minus 15
    $daysInMonth = date('t', strtotime($selectedMonthYear));
    $workingDaysInPeriod = $daysInMonth - 15;
} else {
    // Full month: Use total days in month
    $workingDaysInPeriod = date('t', strtotime($selectedMonthYear));
}

// Calculate attendance percentage based on the correct denominator
$attendancePercentage = ($attendanceStats['total_days'] / $workingDaysInPeriod) * 100;
$attendancePercentage = min(100, $attendancePercentage); // Cap at 100%

// Calculate on-time performance using the same logic
$onTimePercentage = ($attendanceStats['on_time_days'] / $workingDaysInPeriod) * 100;
$onTimePercentage = min(100, $onTimePercentage); // Cap at 100%
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Guard Dashboard - Green Meadows Security Agency</title>
    
    <!-- Include Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Google Material Icons -->
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Outlined" rel="stylesheet">
    
    <!-- Google Fonts - Poppins -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="css/guards_dashboard.css">

    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
    /* Additional styles for the updated UI */
    .dashboard-card {
        background-color: #fff;
        border-radius: 10px;
        overflow: hidden;
        box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        margin-bottom: 1.5rem;
        height: 100%;
    }
    
    .dashboard-card .card-header {
        padding: 15px;
        font-weight: 600;
        border-bottom: none;
    }
    
    .dashboard-card .card-value {
        padding: 15px;
    }
    
    /* Remove dividers */
    .dashboard-card .divider {
        display: none;
    }
    
    /* Summary items */
    .summary-item {
        padding: 1rem;
        border-bottom: 1px solid #f0f0f0;
        display: flex;
        justify-content: space-between;
    }
    
    .summary-item:last-child {
        border-bottom: none;
    }
    
    .summary-label {
        font-weight: 600;
        color: #6c757d;
    }
    
    .total-pay {
        background-color: #f8f9fa;
        font-weight: bold;
    }
    
    .total-pay .summary-value {
        color: #28a745;
        font-size: 1.2rem;
    }
    
    /* Action buttons */
    .btn-action {
        margin: 0.25rem;
        min-width: 120px;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
    }

    /* Remove white horizontal lines in payroll card */
        .payroll-summary .summary-item {
            border-bottom: none !important;
        }
    
    /* Period selector */
    .period-selector {
        padding: 0.75rem 1rem;
        background-color: #f8f9fa;
    }
    
    /* Performance cards */
    .performance-card {
        background-color: white !important;
        border: 1px solid rgba(0,0,0,0.125);
        height: 100%;
        display: flex;
        flex-direction: column;
    }
    
    .performance-card .card-header {
        background-color: #28a745;
        color: white;
        padding: 1rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    .performance-card .card-body {
        flex: 1;
        display: flex;
        flex-direction: column;
        justify-content: center;
        padding: 1.5rem;
    }
    
    .performance-value {
        font-size: 2.5rem;
        font-weight: 700;
        text-align: center;
        margin-bottom: 0.5rem;
    }
    
    .performance-label {
        text-align: center;
        color: #6c757d;
        font-size: 0.9rem;
    }

    /* Simplified Date Filter */
    .date-filter-card {
        background-color: #28a745;
        color: white;
        border-radius: 10px;
        padding: 1rem;
        margin-bottom: 1.5rem;
    }
    
    .date-filter-card .form-label {
        color: white;
        font-weight: 500;
    }
    
    .date-filter-card .form-select {
        background-color: #f8f9fa;
        border: none;
        border-radius: 5px;
    }
    
    .date-filter-card .btn {
        background-color: #ffffff;
        color: #28a745;
        font-weight: 500;
        border-radius: 5px;
    }

    /* White Filter Design */
.dashboard-card.bg-white {
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}

.form-select-sm {
    font-size: 14px;
    height: 35px;
    padding-top: 0.25rem;
    padding-bottom: 0.25rem;
}

    </style>
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
                <a href="guards_dashboard.php" class="nav-link active" data-bs-toggle="tooltip" data-bs-placement="right" title="Dashboard">
                    <span class="material-icons">dashboard</span>
                    <span>Dashboard</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="register_face.php" class="nav-link" data-bs-toggle="tooltip" data-bs-placement="right" title="Register Face">
                    <span class="material-icons">face</span>
                    <span>Register Face</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="attendance.php" class="nav-link" data-bs-toggle="tooltip" data-bs-placement="right" title="Attendance">
                    <span class="material-icons">schedule</span>
                    <span>Attendance</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="payslip.php" class="nav-link" data-bs-toggle="tooltip" data-bs-placement="right" title="Payslip">
                    <span class="material-icons">payments</span>
                    <span>Payslip</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="leave_request.php" class="nav-link" data-bs-toggle="tooltip" data-bs-placement="right" title="Request Leave">
                    <span class="material-icons">event_note</span>
                    <span>Request Leave</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="view_evaluation.php" class="nav-link" data-bs-toggle="tooltip" data-bs-placement="right" title="Performance Evaluation">
                    <span class="material-icons">fact_check</span>
                    <span>Performance Evaluation</span>
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
                <span><?php echo $profileData['First_Name'] . ' ' . $profileData['Last_Name']; ?></span>
                <img src="<?php echo $profileData['Profile_Pic']; ?>" alt="User Profile">
            </a>
        </div>
        
        <!-- Dashboard Content -->
        <div class="container-fluid mt-4">
            <h1 class="mb-4">Welcome, <?php echo $profileData['First_Name']; ?>!</h1>
            
            <?php if (!$faceRegistered): ?>
            <div class="alert alert-warning" role="alert">
                <i class="material-icons align-middle me-2">face</i>
                You haven't registered your face yet. <a href="register_face.php" class="alert-link">Click here to register</a> for easier attendance.
            </div>
            <?php endif; ?>
            
       <!-- Improved Filter Section with Balanced Button -->
<div class="row mb-3">
    <div class="col-12">
        <div class="dashboard-card bg-white border" style="margin: 10px 5px;">
            <div class="card-body py-3 px-4">
                <form method="GET" class="row g-2 align-items-end">
                    <div class="col-md-3">
                        <div class="mb-0">
                            <label for="month" class="form-label text-muted small mb-1">Month</label>
                            <select class="form-select form-select-sm" id="month" name="month">
                                <?php
                                $months = [
                                    '01' => 'January', '02' => 'February', '03' => 'March',
                                    '04' => 'April', '05' => 'May', '06' => 'June',
                                    '07' => 'July', '08' => 'August', '09' => 'September',
                                    '10' => 'October', '11' => 'November', '12' => 'December'
                                ];
                                foreach ($months as $num => $name) {
                                    $selected = ($num == $selectedMonth) ? 'selected' : '';
                                    echo "<option value=\"$num\" $selected>$name</option>";
                                }
                                ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="mb-0">
                            <label for="year" class="form-label text-muted small mb-1">Year</label>
                            <select class="form-select form-select-sm" id="year" name="year">
                                <?php
                                $currentYear = date('Y');
                                for ($y = $currentYear; $y >= $currentYear - 3; $y--) {
                                    $selected = ($y == $selectedYear) ? 'selected' : '';
                                    echo "<option value=\"$y\" $selected>$y</option>";
                                }
                                ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="mb-0">
                            <label for="period" class="form-label text-muted small mb-1">Period</label>
                            <select class="form-select form-select-sm" id="period" name="period">
                                <option value="first" <?php echo ($period == 'first') ? 'selected' : ''; ?>>1-15</option>
                                <option value="second" <?php echo ($period == 'second') ? 'selected' : ''; ?>>16-<?php echo date('t', strtotime($selectedMonthYear)); ?></option>
                                <option value="full" <?php echo ($period == 'full') ? 'selected' : ''; ?>>Full Month</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-success form-select-sm w-100">
                            <span class="material-icons" style="font-size: 16px;">search</span> Apply Filter
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div> <br>

            <!-- Quick Actions -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="dashboard-card bg-white">
                        <div class="card-header bg-success text-white">
                            <span class="material-icons">flash_on</span>
                            Quick Actions
                        </div>
                        <div class="d-flex justify-content-around flex-wrap p-3">
                            <a href="attendance.php?action=clockin" class="btn btn-primary btn-action">
                                <i class="material-icons">login</i> Clock In
                            </a>
                            <a href="attendance.php?action=clockout" class="btn btn-secondary btn-action">
                                <i class="material-icons">logout</i> Clock Out
                            </a>
                            <a href="leave_request.php" class="btn btn-info btn-action">
                                <i class="material-icons">event_busy</i> Request Leave
                            </a>
                            <a href="payslip.php" class="btn btn-success btn-action">
                                <i class="material-icons">receipt</i> View Payslip
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Performance Summary Cards - Reorganized layout -->
<h2 class="mb-4">Your Performance</h2>

<div class="row mb-4">
    <!-- Attendance Rate -->
    <div class="col-md-4 mb-4">
        <div class="dashboard-card bg-white">
            <div class="card-header bg-success text-white">
                <span class="material-icons">event_available</span>
                Attendance Rate
            </div>
            <div class="card-value bg-white text-dark p-3">
                <!-- Attendance Rate Progress Bar with color coding -->
                <div class="progress mb-3" style="background-color: #f0f0f0; height: 8px;">
                    <?php 
                    // Determine progress bar color based on percentage
                    $progressBarColor = '';
                    if ($attendancePercentage <= 50) {
                        $progressBarColor = 'bg-danger';
                    } else if ($attendancePercentage <= 75) {
                        $progressBarColor = 'bg-warning';
                    } else {
                        $progressBarColor = 'bg-success';
                    }
                    ?>
                    <div class="progress-bar <?php echo $progressBarColor; ?>" role="progressbar" 
                         style="width: <?php echo $attendancePercentage; ?>%" 
                         aria-valuenow="<?php echo $attendancePercentage; ?>" 
                         aria-valuemin="0" aria-valuemax="100">
                    </div>
                </div>
                <div class="d-flex justify-content-between mb-1">
                    <span class="h5 mb-0"><?php echo number_format($attendancePercentage, 1); ?>%</span>
                    <span class="h5 mb-0"><?php echo $attendanceStats['total_days']; ?>/<?php echo $workingDaysInPeriod; ?> days</span>
                </div>
                <div class="text-muted" style="font-size: 0.8rem;">
                    <?php if ($period == 'first'): ?>
                        1-15 <?php echo date('M Y', strtotime($selectedMonthYear)); ?> attendance
                    <?php elseif ($period == 'second'): ?>
                        16-<?php echo date('t', strtotime($selectedMonthYear)); ?> <?php echo date('M Y', strtotime($selectedMonthYear)); ?> attendance
                    <?php else: ?>
                        Full month attendance
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- On-Time Performance -->
    <div class="col-md-4 mb-4">
        <div class="dashboard-card bg-white">
            <div class="card-header bg-success text-white">
                <span class="material-icons">timer</span>
                On-Time Rate
            </div>
            <div class="card-value bg-white text-dark p-3">
                <!-- On-Time Performance Progress Bar with color coding -->
                <div class="progress mb-3" style="background-color: #f0f0f0; height: 8px;">
                    <?php 
                    // Determine progress bar color based on percentage
                    $otProgressBarColor = '';
                    if ($onTimePercentage <= 50) {
                        $otProgressBarColor = 'bg-danger';
                    } else if ($onTimePercentage <= 75) {
                        $otProgressBarColor = 'bg-warning';
                    } else {
                        $otProgressBarColor = 'bg-success';
                    }
                    ?>
                    <div class="progress-bar <?php echo $otProgressBarColor; ?>" role="progressbar" 
                         style="width: <?php echo $onTimePercentage; ?>%" 
                         aria-valuenow="<?php echo $onTimePercentage; ?>" 
                         aria-valuemin="0" aria-valuemax="100">
                    </div>
                </div>
                <div class="d-flex justify-content-between mb-1">
                    <span class="h5 mb-0"><?php echo number_format($onTimePercentage, 1); ?>%</span>
                    <span class="h5 mb-0"><?php echo $attendanceStats['on_time_days']; ?>/<?php echo $workingDaysInPeriod; ?> days</span>
                </div>
                <div class="text-muted" style="font-size: 0.8rem;">
                    <?php if ($period == 'first'): ?>
                        1-15 <?php echo date('M Y', strtotime($selectedMonthYear)); ?> on-time rate
                    <?php elseif ($period == 'second'): ?>
                        16-<?php echo date('t', strtotime($selectedMonthYear)); ?> <?php echo date('M Y', strtotime($selectedMonthYear)); ?> on-time rate
                    <?php else: ?>
                        Full month on-time rate
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Assignment Summary (MOVED HERE) -->
    <div class="col-md-4 mb-4">
        <div class="dashboard-card bg-white">
            <div class="card-header bg-success text-white">
                <span class="material-icons">person_pin_circle</span>
                Your Assignment
            </div>
            <div class="card-value bg-white text-dark p-3">
                <table class="table table-borderless mb-0">
                    <tbody>
                        <tr>
                            <td class="text-muted small" style="width: 40%; font-size: 0.85rem;">Location:</td>
                            <td class="text-end" style="font-size: 0.95rem;"><?php echo $profileData['location_name'] ?? 'Not assigned'; ?></td>
                        </tr>
                        <tr>
                            <td class="text-muted small" style="font-size: 0.85rem;">Daily Rate:</td>
                            <td class="text-end" style="font-size: 0.95rem;">₱<?php echo number_format($profileData['daily_rate'] ?? 0, 2); ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <!-- Leave Request Status (FULL WIDTH) -->
    <div class="col-12 mb-4">
        <div class="dashboard-card bg-white">
            <div class="card-header bg-success text-white">
                <span class="material-icons">event_note</span>
                Leave Request Status
            </div>
            <div class="card-value bg-white text-dark p-3">
                <?php if ($leaveRequestStats['total_requests'] > 0): ?>
                    <div class="row align-items-center">
                        <!-- Chart on the left -->
                        <div class="col-md-4 text-center">
                            <div class="chart-container mx-auto" style="height: 150px; width: 150px;">
                                <canvas id="leaveRequestChart" width="150" height="150"></canvas>
                            </div>
                        </div>
                        
                        <!-- Status indicators in the middle with more space -->
                        <div class="col-md-6">
                            <div class="d-flex justify-content-around mb-3">
                                <div class="text-center px-3">
                                    <div class="badge bg-success d-block mb-1" style="font-size: 0.8rem; width: 90px; padding: 8px 0;">Approved</div>
                                    <div style="font-size: 1rem;"><?php echo $leaveRequestStats['approved_requests']; ?></div>
                                </div>
                                <div class="text-center px-3">
                                    <div class="badge bg-warning text-dark d-block mb-1" style="font-size: 0.8rem; width: 90px; padding: 8px 0;">Pending</div>
                                    <div style="font-size: 1rem;"><?php echo $leaveRequestStats['pending_requests']; ?></div>
                                </div>
                                <div class="text-center px-3">
                                    <div class="badge bg-danger d-block mb-1" style="font-size: 0.8rem; width: 90px; padding: 8px 0;">Rejected</div>
                                    <div style="font-size: 1rem;"><?php echo $leaveRequestStats['rejected_requests']; ?></div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Button on the right -->
                        <div class="col-md-2 text-center">
                            <a href="leave_request.php" class="btn btn-success">
                                <i class="material-icons align-middle small">add</i> New Request
                            </a>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4">
                        <span class="material-icons" style="font-size: 48px; color: #6c757d;">event_busy</span>
                        <p class="mt-3 mb-4">No leave requests this year.</p>
                        <a href="leave_request.php" class="btn btn-success">
                            <i class="material-icons align-middle small">add</i> New Request
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

            <!-- Recent Activity -->
            <div class="row mb-4">
                <!-- Recent Attendance -->
                <div class="col-12">
                    <div class="dashboard-card bg-white text-dark">
                        <div class="card-header bg-success text-white d-flex align-items-center">
                            <span class="material-icons me-2">history</span>
                            <span>Recent Attendance</span>
                        </div>
                        
                        <div class="period-selector mb-2 p-2 border-bottom">
                            <div class="d-flex align-items-center">
                                <span class="badge bg-success me-2">
                                    <?php 
                                        // Display selected period dynamically based on filter
                                        if ($period == 'first') {
                                            echo "Period (1-15 " . date('M', strtotime($selectedMonthYear)) . ")";
                                        } else if ($period == 'second') {
                                            echo "Period (16-" . date('t', strtotime($selectedMonthYear)) . " " . date('M', strtotime($selectedMonthYear)) . ")";
                                        } else if ($period == 'previous_month') {
                                            echo date('F Y', strtotime('first day of last month'));
                                        } else if ($period == 'current_year') {
                                            echo "Year " . date('Y');
                                        } else {
                                            echo $periodTitle;
                                        }
                                    ?>
                                </span>
                                <span class="text-muted small"><?php echo date('M d', strtotime($periodStart)) . ' - ' . date('M d, Y', strtotime($periodEnd)); ?></span>
                            </div>
                        </div>
                        
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Clock In</th>
                                        <th>Clock Out</th>
                                        <th>Status</th>
                                        <th>Verification</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($periodAttendance)): ?>
                                        <tr>
                                            <td colspan="5" class="text-center">No attendance records for this period</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($periodAttendance as $attendance): ?>
                                            <?php 
                                            // Determine shift type based on clock-in time
                                            $clockInHour = date('H', strtotime($attendance['Time_In']));
                                            $isNightShift = ($clockInHour >= 18 || $clockInHour < 6);
                                            
                                            // Different on-time rules for different shifts
                                            if ($isNightShift) {
                                                // Night shift: On time if between 6-8 PM
                                                $isOnTime = ($clockInHour >= 18 && $clockInHour <= 20);
                                            } else {
                                                // Morning shift: On time if between 6-8 AM
                                                $isOnTime = ($clockInHour >= 6 && $clockInHour <= 8);
                                            }
                                            
                                            // Handle date display for overnight shifts
                                            $clockInDate = date('M d, Y', strtotime($attendance['Time_In']));
                                            $dateDisplay = $clockInDate;
                                            
                                            if (!empty($attendance['Time_Out'])) {
                                                $clockInDay = date('d', strtotime($attendance['Time_In']));
                                                $clockOutDay = date('d', strtotime($attendance['Time_Out']));
                                                
                                                // If clock-out is on a different day (overnight shift)
                                                if ($clockInDay != $clockOutDay) {
                                                    $dateDisplay = date('M d, Y', strtotime($attendance['Time_In'])) . ' – ' . 
                                                                date('M d, Y', strtotime($attendance['Time_Out']));
                                                }
                                            }
                                            ?>
                                            <tr>
                                                <td><?php echo $dateDisplay; ?></td>
                                                <td><?php echo date('h:i A', strtotime($attendance['Time_In'])); ?></td>
                                                <td>
                                                    <?php if (!empty($attendance['Time_Out'])): ?>
                                                        <?php echo date('h:i A', strtotime($attendance['Time_Out'])); ?>
                                                    <?php else: ?>
                                                        <span class="badge bg-warning text-dark">Not clocked out</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($isOnTime): ?>
                                                        <span class="badge bg-success">On Time</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-danger">Late</span>
                                                    <?php endif; ?>
                                                    
                                                    <?php if ($isNightShift): ?>
                                                        <span class="badge bg-secondary ms-1">Night</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-info ms-1">Day</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($attendance['face_verified']): ?>
                                                        <span class="badge bg-success">Face Verified</span>
                                                    <?php elseif ($attendance['Updated_At']): ?>
                                                        <span class="badge bg-info">Verified by Accounting</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">Manual Entry</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            
    <!-- Profile Modal -->
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
                                <img id="currentProfileImage" src="<?php echo $profileData['Profile_Pic']; ?>" 
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
                            <label for="phoneNumber" class="form-label">Phone Number</label>
                            <input type="text" class="form-control" id="phoneNumber" name="phoneNumber" 
                                value="<?php echo $profileData['phone_number']; ?>">
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
    
    <!-- Mobile Bottom Navigation -->
    <div class="mobile-nav">
        <div class="mobile-nav-container">
            <a href="guards_dashboard.php" class="mobile-nav-item active">
                <span class="material-icons">dashboard</span>
                <span class="mobile-nav-text">Dashboard</span>
            </a>
            <a href="register_face.php" class="mobile-nav-item">
                <span class="material-icons">face</span>
                <span class="mobile-nav-text">Register Face</span>
            </a>
            <a href="attendance.php" class="mobile-nav-item">
                <span class="material-icons">schedule</span>
                <span class="mobile-nav-text">Attendance</span>
            </a>
            <a href="payslip.php" class="mobile-nav-item">
                <span class="material-icons">payments</span>
                <span class="mobile-nav-text">Payslip</span>
            </a>
            <a href="leave_request.php" class="mobile-nav-item">
                <span class="material-icons">event_note</span>
                <span class="mobile-nav-text">Request Leave</span>
            </a>
            <a href="view_evaluation.php" class="mobile-nav-item">
                <span class="material-icons">fact_check</span>
                <span class="mobile-nav-text">Performance</span>
            </a>
            <a href="../logout.php" class="mobile-nav-item">
                <span class="material-icons">logout</span>
                <span class="mobile-nav-text">Logout</span>
            </a>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="js/guards_dashboard.js"></script>

    <!-- Chart Initialization Script -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize date and time display
        updateDateTime();
        setInterval(updateDateTime, 1000);
        
        // Initialize tooltips
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
        
        // Leave Request Chart
        const leaveRequestChart = document.getElementById('leaveRequestChart');
        if (leaveRequestChart) {
            new Chart(leaveRequestChart, {
                type: 'doughnut',
                data: {
                    labels: ['Approved', 'Pending', 'Rejected'],
                    datasets: [{
                        data: [
                            <?php echo $leaveRequestStats['approved_requests']; ?>, 
                            <?php echo $leaveRequestStats['pending_requests']; ?>, 
                            <?php echo $leaveRequestStats['rejected_requests']; ?>
                        ],
                        backgroundColor: [
                            '#28a745', // Approved - green
                            '#ffc107', // Pending - yellow
                            '#dc3545'  // Rejected - red
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    cutout: '70%'
                }
            });
        }
        
        // Function to update date and time
        function updateDateTime() {
            const now = new Date();
            const dateElement = document.getElementById('current-date');
            const timeElement = document.getElementById('current-time');
            
            if (dateElement && timeElement) {
                dateElement.textContent = now.toLocaleDateString('en-US', {
                    weekday: 'long',
                    year: 'numeric',
                    month: 'long',
                    day: 'numeric'
                });
                timeElement.textContent = now.toLocaleTimeString('en-US', {
                    hour: '2-digit',
                    minute: '2-digit',
                    second: '2-digit'
                });
            }
        }
        
        // Handle profile photo preview
        const profilePicInput = document.getElementById('profilePic');
        if (profilePicInput) {
            profilePicInput.addEventListener('change', function() {
                const file = this.files[0];
                if (file) {
                    document.getElementById('imagePreviewContainer').style.display = 'block';
                    document.getElementById('currentProfileImage').style.display = 'none';
                    
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        document.getElementById('imagePreview').src = e.target.result;
                    }
                    reader.readAsDataURL(file);
                } else {
                    document.getElementById('imagePreviewContainer').style.display = 'none';
                    document.getElementById('currentProfileImage').style.display = 'block';
                }
            });
        }
    });
    </script>

    <!-- SWAL Alerts -->
    <?php if(isset($_SESSION['profile_success']) || isset($_SESSION['profile_error'])): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            <?php if(isset($_SESSION['profile_success'])): ?>
                Swal.fire({
                    icon: 'success',
                    title: 'Success!',
                    text: '<?php echo $_SESSION['profile_success']; ?>',
                    confirmButtonColor: '#28a745'
                });
                <?php unset($_SESSION['profile_success']); ?>
            <?php endif; ?>

            <?php if(isset($_SESSION['profile_error'])): ?>
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: '<?php echo $_SESSION['profile_error']; ?>',
                    confirmButtonColor: '#dc3545'
                });
                <?php unset($_SESSION['profile_error']); ?>
            <?php endif; ?>
        });
    </script>
    <?php endif; ?>
</body>
</html>