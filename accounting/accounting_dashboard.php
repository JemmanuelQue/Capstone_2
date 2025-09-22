<?php
session_start();
require_once '../db_connection.php';
require_once 'payroll_calculation/unified_payroll_calculator.php';
require_once '../includes/session_check.php';

// Debug logging function for payroll calculations (disabled)
function logPayrollCalculation($message, $data = null) {
    // Logging disabled
    return;
}

// Validate session and require specific role
if (!validateSession($conn, 4)) { // 4 = accounting role
    exit(); // validateSession handles the redirect
}
// require_once '../includes/auth_check.php'; might include in future...

// Initialize essential variables
$pageTitle = "Accounting Dashboard";
$currentDateTime = date('F d, Y');
$profileData = getUserProfile($conn, $_SESSION['user_id']);
$analyticsData = getDashboardAnalytics($conn);

/**
 * Get user profile data
 * @param PDO $conn Database connection
 * @param int $userId User ID
 * @return array User profile data
 */
function getUserProfile($conn, $userId) {
    $profileStmt = $conn->prepare("
        SELECT Profile_Pic, First_Name, Last_Name, Role_ID
        FROM users 
        WHERE User_ID = ?
    ");
    $profileStmt->execute([$userId]);
    $profileData = $profileStmt->fetch(PDO::FETCH_ASSOC);
    
    // Set default profile pic if none exists
    if (!$profileData || empty($profileData['Profile_Pic']) || !file_exists($profileData['Profile_Pic'])) {
        $profileData['Profile_Pic'] = '../images/default_profile.png';
    }
    
    return $profileData;
}

/**
 * Get dashboard analytics data
 * @param PDO $conn Database connection
 * @return array Dashboard analytics data
 */
function getDashboardAnalytics($conn) {
    // Get total guards
    $userQuery = $conn->query("
        SELECT COUNT(*) AS total_users 
        FROM users 
        WHERE Role_ID = 5 AND status = 'Active'
    ");
    $totalUsers = $userQuery->fetch(PDO::FETCH_ASSOC)['total_users'];
    
    // Get pending leave requests
    $leaveQuery = $conn->query("
        SELECT COUNT(*) AS total_pending_requests 
        FROM leave_requests 
        WHERE Status = 'Pending'
    ");
    $pendingRequests = $leaveQuery->fetch(PDO::FETCH_ASSOC)['total_pending_requests'];
    
    // Get guards currently clocked in
    $attendanceQuery = $conn->query("
        SELECT COUNT(*) AS guards_clocked_in 
        FROM attendance 
        WHERE Time_Out IS NULL
    ");
    $guardsClocked = $attendanceQuery->fetch(PDO::FETCH_ASSOC)['guards_clocked_in'];
    
    return [
        'totalUsers' => $totalUsers,
        'pendingRequests' => $pendingRequests,
        'guardsClocked' => $guardsClocked
    ];
}

/**
 * Get payroll analytics data for specified period
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

/**
 * Get payroll trend data for the last 6 months
 * @param PDO $conn Database connection
 * @param PayrollCalculator $payrollCalculator Payroll calculator instance
 * @return array Trend data with labels, gross and net pay
 */
function getPayrollTrendData($conn, $payrollCalculator) {
    logPayrollCalculation("Starting monthly payroll trend calculation");
    
    // Get all active employees (Accounting, HR, and Guards)
    $guardsStmt = $conn->prepare("
        SELECT User_ID 
        FROM users 
        WHERE Role_ID IN (3, 4, 5) AND status = 'Active'
    ");
    $guardsStmt->execute();
    $guardIds = $guardsStmt->fetchAll(PDO::FETCH_COLUMN);
    
    logPayrollCalculation("Found active employees", count($guardIds));
    
    $trendData = [
        'labels' => [],
        'gross' => [],
        'net' => []
    ];
    
    // Generate data for the last 6 months
    for ($i = 5; $i >= 0; $i--) {
        $monthDate = date('Y-m-01', strtotime("-{$i} months"));
        $monthLabel = date('M Y', strtotime($monthDate));
        $monthStart = $monthDate;
        $monthEnd = date('Y-m-t', strtotime($monthDate));
        
        logPayrollCalculation("Processing month", $monthLabel . " ($monthStart to $monthEnd)");
        
        $gross = 0;
        $net = 0;
        $employeesWithAttendance = 0;
        
        foreach ($guardIds as $guardId) {
            // Check for attendance in the selected period (ensure both time_in and time_out exist)
            $attendanceStmt = $conn->prepare("
                SELECT COUNT(*) 
                FROM attendance 
                WHERE User_ID = ? AND DATE(time_in) BETWEEN ? AND ? AND time_out IS NOT NULL
            ");
            $attendanceStmt->execute([$guardId, $monthStart, $monthEnd]);
            $attendanceCount = $attendanceStmt->fetchColumn();
            
            // Only calculate payroll if employee has attendance records
            if ($attendanceCount > 0) {
                $employeesWithAttendance++;
                
                // Log before calculation
                logPayrollCalculation("Calculating payroll for user", [
                    'user_id' => $guardId,
                    'month' => $monthLabel,
                    'attendance_count' => $attendanceCount
                ]);
                
                // The calculatePayrollForGuard method exists and is a compatibility layer
                $payroll = $payrollCalculator->calculatePayrollForGuard($guardId, null, null, $monthStart, $monthEnd);
                
                // Log payroll results
                logPayrollCalculation("Payroll calculation result", [
                    'user_id' => $guardId,
                    'gross_pay' => $payroll['gross_pay'] ?? 0,
                    'net_pay' => $payroll['net_pay'] ?? 0,
                    'summary' => $payroll['summary'] ?? []
                ]);
                
                // Ensure gross pay and net pay are positive values
                $grossPay = max(0, $payroll['gross_pay'] ?? 0);
                $netPay = max(0, $payroll['net_pay'] ?? 0);
                
                $gross += $grossPay;
                $net += $netPay;
            }
        }
        
        logPayrollCalculation("Month totals", [
            'month' => $monthLabel,
            'employees_with_attendance' => $employeesWithAttendance,
            'total_gross' => $gross,
            'total_net' => $net
        ]);
        
        // Format values to avoid JavaScript precision issues and ensure consistency with payroll.php
        // Apply consistent rounding and formatting for both dashboard and payroll pages
        $trendData['labels'][] = $monthLabel;
        $trendData['gross'][] = number_format(round($gross, 2), 2, '.', '');
        $trendData['net'][] = number_format(round($net, 2), 2, '.', '');
    }
    
    return $trendData;
}

// Get filter parameters
$filterYear = isset($_GET['year']) ? $_GET['year'] : date('Y');
$filterMonth = isset($_GET['month']) ? $_GET['month'] : date('m');
$dateRange = isset($_GET['dateRange']) ? $_GET['dateRange'] : '1-15';

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

// Initialize payroll calculator
$payrollCalculator = new PayrollCalculator($conn);

// Get payroll analytics and trend data
$payrollAnalytics = getPayrollAnalytics($conn, $firstDay, $lastDay, $payrollCalculator);
$trendData = getPayrollTrendData($conn, $payrollCalculator);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accounting Dashboard - Green Meadows Security Agency</title>
    
    <!-- Include Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Google Material Icons -->
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Outlined" rel="stylesheet">
    
    <!-- Google Fonts - Poppins -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="css/accounting_dashboard.css">

    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="logo-container">
            <img src="../images/greenmeadows_logo.jpg" alt="Green Meadows Logo" class="logo">
            <div class="agency-name">
                <div>SECURITY AGENCY</div>
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
                <a href="daily_time_record.php" class="nav-link" data-bs-toggle="tooltip" data-bs-placement="right" title="Daily Time Record">
                    <span class="material-icons">schedule</span>
                    <span>Daily Time Record</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="payroll.php" class="nav-link" data-bs-toggle="tooltip" data-bs-placement="right" title="Payroll">
                    <span class="material-icons">payments</span>
                    <span>Payroll</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="rate_locations.php" class="nav-link" data-bs-toggle="tooltip" data-bs-placement="right" title="Users List">
                    <span class="material-icons">attach_money</span>
                    <span>Rate per Locations</span>
                </a>
            </li>
             <li class="nav-item">
                <a href="calendar.php" class="nav-link" data-bs-toggle="tooltip" data-bs-placement="right" title="Payroll">
                    <span class="material-icons">date_range</span>
                    <span>Calendar</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="masterlist.php" class="nav-link"data-bs-toggle="tooltip" data-bs-placement="right" title="Masterlist">
                    <span class="material-icons">assignment</span>
                    <span>Masterlist</span>
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
            <div class="user-profile" id="userProfile" data-bs-toggle="modal" data-bs-target="#profileModal">
                <span><?php echo $profileData['First_Name'] . ' ' . $profileData['Last_Name']; ?></span>
                <img src="<?php echo $profileData['Profile_Pic']; ?>" alt="User Profile">
            </div>
        </div>
        
        <!-- Dashboard Content -->
        <div class="container-fluid mt-4">
            <h1 class="mb-4"><center>Welcome Accounting!</center></h1><br>
            
            <h2 class="mb-4"><center>Today's Analytics Show</center></h2>
            
            <div class="row">
               <!-- Total Guards Card -->
                <div class="col-md-6 mb-4">
                    <div class="dashboard-card">
                        <div class="card-header">
                            <span class="material-icons">security</span>
                            Total Guards
                        </div>
                        <hr class="divider">
                        <div class="card-value"><?php echo $analyticsData['totalUsers']; ?></div>
                        <div class="card-label">Total active guards in the system.</div>
                    </div>
                </div>

                <!-- Attendance Insights Card -->
                <div class="col-md-6 mb-4">
                    <div class="dashboard-card">
                        <div class="card-header">
                            <span class="material-icons">how_to_reg</span>
                            Attendance Insights
                        </div>
                        <hr class="divider">
                        <div class="card-value"><?php echo $analyticsData['guardsClocked']; ?></div>
                        <div class="card-label">Guards Currently Clocked In.</div>
                    </div>
                </div>
            </div>

            <!-- Payroll Analytics Section -->
            <div class="container-fluid mt-5">
                <h2 class="mb-4"><center>Payroll Analytics</center></h2>
                
                <!-- Invisible anchor for scroll target -->
                <div id="payrollAnalytics" style="position: relative; top: -80px; visibility: hidden;"></div>
                
                <!-- Filter Section -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="dashboard-card bg-white">
                            <div class="card-header bg-success text-white">
                                <span class="material-icons">filter_alt</span>
                                Payroll Analytics Filters
                            </div>
                            <hr class="divider bg-primary">
                            <div class="container p-3">
                                <form id="payrollFilterForm" method="GET" class="row g-3 align-items-end">
                                    <input type="hidden" name="section" value="payrollAnalytics">
                                    <div class="col-md-3">
                                        <label for="filterYear" class="form-label">Year</label>
                                        <select class="form-select" id="filterYear" name="year">
                                            <?php
                                            $currentYear = date('Y');
                                            for($y = $currentYear; $y >= $currentYear - 3; $y--) {
                                                $selected = (isset($_GET['year']) && $_GET['year'] == $y) ? 'selected' : 
                                                        (!isset($_GET['year']) && $y == $currentYear ? 'selected' : '');
                                                echo "<option value=\"$y\" $selected>$y</option>";
                                            }
                                            ?>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label for="filterMonth" class="form-label">Month</label>
                                        <select class="form-select" id="filterMonth" name="month">
                                            <?php
                                            $months = [
                                                '01' => 'January', '02' => 'February', '03' => 'March',
                                                '04' => 'April', '05' => 'May', '06' => 'June',
                                                '07' => 'July', '08' => 'August', '09' => 'September',
                                                '10' => 'October', '11' => 'November', '12' => 'December'
                                            ];
                                            $currentMonth = date('m');
                                            foreach($months as $num => $name) {
                                                $selected = (isset($_GET['month']) && $_GET['month'] == $num) ? 'selected' : 
                                                        (!isset($_GET['month']) && $num == $currentMonth ? 'selected' : '');
                                                echo "<option value=\"$num\" $selected>$name</option>";
                                            }
                                            ?>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label for="filterDateRange" class="form-label">Cutoff Period</label>
                                        <select class="form-select" id="filterDateRange" name="dateRange">
                                            <?php
                                            $selectedDateRange = isset($_GET['dateRange']) ? $_GET['dateRange'] : '1-15';
                                            ?>
                                            <option value="1-15" <?php if($selectedDateRange==='1-15') echo 'selected'; ?>>1st - 15th</option>
                                            <option value="16-31" <?php if($selectedDateRange==='16-31') echo 'selected'; ?>>16th - 31st</option>
                                            <option value="1-31" <?php if($selectedDateRange==='1-31') echo 'selected'; ?>>1st - 31st</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <button type="submit" class="btn btn-success w-100">
                                            <i class="material-icons align-middle">search</i> Apply Filters
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Payroll Charts Row -->
                <div class="row mb-4">
                    <?php if ($payrollAnalytics['guardWithPayCount'] > 0 && $payrollAnalytics['totalGross'] > 0): ?>
                    <!-- Payroll Expense Chart -->
                    <div class="col-lg-8 mb-4">
                        <div class="dashboard-card bg-white text-dark">
                            <div class="card-header bg-success text-white">
                                <span class="material-icons">pie_chart</span>
                                Payroll Expense Distribution
                            </div>
                            <hr class="divider bg-primary">
                            <div class="chart-container">
                                <canvas id="payrollExpenseChart" height="300"></canvas>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Payroll Summary Stats -->
                    <div class="col-lg-4 mb-4">
                        <div class="dashboard-card bg-white text-dark">
                            <div class="card-header bg-success text-white">
                                <span class="material-icons">summarize</span>
                                Payroll Summary: <?php echo $periodLabel; ?>
                            </div>
                            <hr class="divider bg-primary">
                            <div class="payroll-summary text-center">
                                <div class="summary-item">
                                    <span class="summary-label">Current Month Gross Payroll:</span>
                                    <span class="summary-value">₱<?php echo number_format($payrollAnalytics['totalGross'], 2); ?></span>
                                </div>
                                
                                <div class="summary-item">
                                    <span class="summary-label">Current Month Net Payroll:</span>
                                    <span class="summary-value">₱<?php echo number_format($payrollAnalytics['netPay'], 2); ?></span>
                                </div>
                                
                                <div class="summary-item">
                                    <span class="summary-label">Active Guards:</span>
                                    <span class="summary-value"><?php echo $payrollAnalytics['activeGuards']; ?></span>
                                </div>
                                
                                <div class="summary-item">
                                    <span class="summary-label">Average Salary per Guard:</span>
                                    <span class="summary-value">₱<?php echo number_format($payrollAnalytics['avgSalary'], 2); ?></span>
                                </div>
                                
                                <div class="text-center mt-4 mb-3">
                                    <a href="payroll.php" class="btn btn-success">
                                        <i class="material-icons align-middle">payments</i> View Full Payroll
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php else: ?>
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
                                <p class="text-muted">Try selecting a different date range or check if payroll has been processed.</p>
                                <a href="payroll.php" class="btn btn-outline-success mt-3">
                                    <i class="material-icons align-middle">payments</i> Go to Payroll
                                </a>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Monthly Payroll Trend Row - Always display regardless of filter results -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="dashboard-card bg-white text-dark">
                            <div class="card-header bg-success text-white">
                                <span class="material-icons">trending_up</span>
                                Monthly Payroll Trend (Last 6 Months)
                            </div>
                            <hr class="divider bg-primary">
                            <div class="chart-container">
                                <canvas id="payrollTrendChart"></canvas>
                            </div>
                            
                            <script>
                                document.addEventListener('DOMContentLoaded', function() {
                                    const directTrendCtx = document.getElementById('payrollTrendChart');
                                    if (directTrendCtx) {
                                        const directTrendChart = new Chart(directTrendCtx, {
                                                        type: 'bar',
                                                        data: {
                                                            labels: <?php echo json_encode($trendData['labels']); ?>,
                                                            datasets: [
                                                                {
                                                                    label: 'Gross Pay',
                                                                    data: <?php echo json_encode($trendData['gross']); ?>.map(value => parseFloat(value)),
                                                                    backgroundColor: 'rgba(76, 175, 80, 0.7)'
                                                                },
                                                                {
                                                                    label: 'Net Pay',
                                                                    data: <?php echo json_encode($trendData['net']); ?>.map(value => parseFloat(value)),
                                                                    backgroundColor: 'rgba(33, 150, 243, 0.7)'
                                                                }
                                                            ]
                                                        },
                                                        options: {
                                                            responsive: true,
                                                            maintainAspectRatio: false
                                                        }
                                                    });
                                    }
                                });
                            </script>
                            </div>
                        </div>
                    </div>
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
                            <label for="firstName" class="form-label">First Name</label>
                            <input type="text" class="form-control" id="firstName" name="firstName" 
                                value="<?php echo $profileData['First_Name']; ?>">
                        </div>
                        <div class="mb-3">
                            <label for="lastName" class="form-label">Last Name</label>
                            <input type="text" class="form-control" id="lastName" name="lastName" 
                                value="<?php echo $profileData['Last_Name']; ?>">
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
            <!-- FIXED: Match sidebar links -->
            <a href="accounting_dashboard.php" class="mobile-nav-item">
                <span class="material-icons">dashboard</span>
                <span class="mobile-nav-text">Dashboard</span>
            </a>
            <a href="daily_time_record.php" class="mobile-nav-item">
                <span class="material-icons">schedule</span>
                <span class="mobile-nav-text">Daily Time Record</span>
            </a>
            <a href="payroll.php" class="mobile-nav-item active">
                <span class="material-icons">payments</span>
                <span class="mobile-nav-text">Payroll</span>
            </a>
            <!-- FIXED: Add missing links to match sidebar -->
            <a href="rate_locations.php" class="mobile-nav-item">
                <span class="material-icons">attach_money</span>
                <span class="mobile-nav-text">Rate per Locations</span>
            </a>
            <a href="calendar.php" class="mobile-nav-item">
                <span class="material-icons">date_range</span>
                <span class="mobile-nav-text">Calendar</span>
            </a>
            <a href="masterlist.php" class="mobile-nav-item">
                <span class="material-icons">assignment</span>
                <span class="mobile-nav-text">Masterlist</span>
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


    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
        <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
    <script src="js/accounting_dashboard.js"></script>

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
        
        // Initialize the payroll expense chart if element exists
        const expenseCtx = document.getElementById('payrollExpenseChart');
        if (expenseCtx) {
            const payrollExpChart = new Chart(expenseCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Earnings', 'Deductions', 'Net Pay'],
                    datasets: [{
                        data: [
                            <?php echo $payrollAnalytics['earnings']; ?>,
                            <?php echo $payrollAnalytics['deductions']; ?>,
                            <?php echo $payrollAnalytics['netPay']; ?>
                        ],
                        backgroundColor: [
                            '#4CAF50', // Earnings
                            '#F44336', // Deductions
                            '#2196F3'  // Net Pay
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    layout: {
                        padding: {
                            top: 20,
                            bottom: 20,
                            left: 20,
                            right: 20
                        }
                    },
                    plugins: {
                        legend: {
                            position: 'right',
                            align: 'start',
                            labels: {
                                color: '#000',
                                boxWidth: 15,
                                padding: 15,
                                font: {
                                    weight: 'bold'
                                }
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const value = context.raw;
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = total > 0 ? Math.round((value / total) * 100) : 0;
                                    return `₱${value.toLocaleString()} (${percentage}%)`;
                                }
                            }
                        }
                    }
                },
            });
        }
        
        // Always initialize the trend chart, regardless of current filter data
        const trendCtx = document.getElementById('payrollTrendChart');
        if (trendCtx) {
            
            const months = <?php echo json_encode($trendData['labels']); ?>;
            // Convert string values to numbers to ensure consistent rendering
            const grossPayData = <?php echo json_encode($trendData['gross']); ?>.map(value => parseFloat(value));
            const netPayData = <?php echo json_encode($trendData['net']); ?>.map(value => parseFloat(value));
            
            const payrollTrendChart = new Chart(trendCtx, {
                type: 'bar',
                data: {
                    labels: months,
                    datasets: [
                        {
                            label: 'Gross Pay',
                            data: grossPayData,
                            backgroundColor: 'rgba(76, 175, 80, 0.7)',
                            borderColor: 'rgba(76, 175, 80, 1)',
                            borderWidth: 1
                        },
                        {
                            label: 'Net Pay',
                            data: netPayData,
                            backgroundColor: 'rgba(33, 150, 243, 0.7)',
                            borderColor: 'rgba(33, 150, 243, 1)',
                            borderWidth: 1
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return '₱' + value.toLocaleString();
                                },
                                color: '#000'
                            },
                            grace: '5%'
                        },
                        x: {
                            ticks: {
                                color: '#000'
                            }
                        }
                    },
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    let label = context.dataset.label || '';
                                    if (label) {
                                        label += ': ';
                                    }
                                    if (context.parsed.y !== null) {
                                        label += '₱' + context.parsed.y.toLocaleString(undefined, {
                                            minimumFractionDigits: 2,
                                            maximumFractionDigits: 2
                                        });
                                    }
                                    return label;
                                }
                            }
                        },
                        legend: {
                            labels: {
                                color: '#000',
                                font: {
                                    weight: 'bold'
                                }
                            }
                        }
                    }
                }
            });
        }
        
        // Function to update date and time
        function updateDateTime() {
            const now = new Date();
            document.getElementById('current-date').textContent = now.toLocaleDateString('en-US', {
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });
            document.getElementById('current-time').textContent = now.toLocaleTimeString('en-US', {
                hour: '2-digit',
                minute: '2-digit'
            });
        }
        
        // Function to scroll to payroll analytics when filter is applied
        function scrollToPayrollAnalytics() {
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('section') === 'payrollAnalytics') {
                const element = document.getElementById('payrollAnalytics');
                if (element) {
                    setTimeout(function() {
                        element.scrollIntoView({
                            behavior: 'smooth',
                            block: 'start'
                        });
                    }, 300);
                }
            }
        }
        
        // Call the function when page loads
        scrollToPayrollAnalytics();
        
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
                }
            });
        }
    });
    </script>

    <!-- SWAL Alerts for Profile Picture -->
    <?php if(isset($_SESSION['profilepic_success']) || isset($_SESSION['profilepic_error'])): ?>
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
        });
    </script>
    <?php endif; ?>
</body>
</html>