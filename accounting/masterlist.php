<?php
require_once __DIR__ . '/../includes/session_check.php';
validateSession($conn, 4);

// Database connection
require_once '../db_connection.php';
require_once 'payroll_calculation/unified_payroll_calculator.php';

// Get current Accounting user's name
$superadminStmt = $conn->prepare("SELECT First_Name, Last_Name FROM users WHERE Role_ID = 4 AND status = 'Active' AND User_ID = ?");
$superadminStmt->execute([$_SESSION['user_id']]);
$superadminData = $superadminStmt->fetch(PDO::FETCH_ASSOC);
$superadminName = $superadminData ? $superadminData['First_Name'] . ' ' . $superadminData['Last_Name'] : "Accounting";

// Get superadmin's profile picture
$profileStmt = $conn->prepare("SELECT Profile_Pic, First_Name, Last_Name FROM users WHERE User_ID = ?");
$profileStmt->execute([$_SESSION['user_id']]);
$profileData = $profileStmt->fetch(PDO::FETCH_ASSOC);

if ($profileData && !empty($profileData['Profile_Pic']) && file_exists($profileData['Profile_Pic'])) {
    $superadminProfile = $profileData['Profile_Pic'];
} else {
    $superadminProfile = '../images/default_profile.png';
}

// Get filter parameters - month, year, and department
$month = isset($_GET['month']) ? $_GET['month'] : date('m');
$year = isset($_GET['year']) ? $_GET['year'] : date('Y');
$department = isset($_GET['department']) ? $_GET['department'] : 'all';

// Initialize Payroll Calculator
$payrollCalculator = new PayrollCalculator($conn);

// Get departments for filter dropdown
$deptQuery = "SELECT Role_ID, Role_Name FROM roles WHERE Role_ID IN (3, 4, 5) ORDER BY Role_Name";
$deptStmt = $conn->prepare($deptQuery);
$deptStmt->execute();
$departments = $deptStmt->fetchAll(PDO::FETCH_ASSOC);

// Get all employees (Accounting, HR, Guards only - excluding Admin and Super Admin)
$query = "
    SELECT 
        u.User_ID,
        u.employee_id,
        u.First_Name,
        u.Middle_Name,
        u.Last_Name,
        u.Email,
        u.Phone_Number,
        u.hired_date,
        r.Role_Name as department
    FROM users u
    LEFT JOIN roles r ON u.Role_ID = r.Role_ID
    WHERE u.Role_ID IN (3, 4, 5) AND u.status = 'Active'
";

// Add department filter if selected
if ($department !== 'all') {
    $query .= " AND r.Role_ID = :department";
}

$query .= " ORDER BY u.Last_Name, u.First_Name";

$stmt = $conn->prepare($query);

// Bind department parameter if filtered
if ($department !== 'all') {
    $stmt->bindParam(':department', $department);
}

$stmt->execute();
$employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Function to calculate payroll data for different periods
function calculateEmployeePayrollData($userId, $payrollCalculator, $year, $month) {
    $data = [
        'current_gross_pay' => 0,
        'current_deductions' => 0,
        'current_net_pay' => 0,
        'three_months_total' => 0,
        'six_months_total' => 0,
        'nine_months_total' => 0,
        'twelve_months_total' => 0
    ];
    
    // Current month calculation
    $currentStart = "$year-$month-01";
    $currentEnd = date('Y-m-t', strtotime("$year-$month-01"));
    
    $currentPayroll = $payrollCalculator->calculatePayroll($userId, $currentStart, $currentEnd);
    $data['current_gross_pay'] = $currentPayroll['gross_pay'] ?? 0;
    $data['current_deductions'] = $currentPayroll['total_deductions'] ?? 0;
    $data['current_net_pay'] = $currentPayroll['net_pay'] ?? 0;
    
    // Historical calculations
    $periods = [
        'three_months' => 3,
        'six_months' => 6,
        'nine_months' => 9,
        'twelve_months' => 12
    ];
    
    foreach ($periods as $period => $months) {
        $totalGross = 0;
        
        for ($i = 0; $i < $months; $i++) {
            $periodDate = date('Y-m-01', strtotime("-$i months", strtotime("$year-$month-01")));
            $periodStart = $periodDate;
            $periodEnd = date('Y-m-t', strtotime($periodDate));
            
            $periodPayroll = $payrollCalculator->calculatePayroll($userId, $periodStart, $periodEnd);
            $totalGross += $periodPayroll['gross_pay'] ?? 0;
        }
        
        $data[$period . '_total'] = $totalGross;
    }
    
    return $data;
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Payroll Masterlist - Green Meadows Security Agency Inc.</title>
    
    <!-- Include Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Google Material Icons -->
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Outlined" rel="stylesheet">
    
    <!-- Google Fonts - Poppins -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.2.2/css/buttons.dataTables.min.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="css/masterlist.css">

    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
        * {
            font-family: 'Poppins', sans-serif !important;
        }
        
        .page-title {
            font-family: 'Poppins', sans-serif !important;
        }
        
        /* Fix for Material Icons in DataTables buttons */
        .dt-button {
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            margin-right: 5px !important;
            padding: 8px 16px !important;
            background-color: #2a7d4f !important;
            color: white !important;
            border: none !important;
            border-radius: 4px !important;
            font-weight: 500 !important;
            transition: all 0.3s ease !important;
            font-family: 'Poppins', sans-serif !important;
        }
        
        .dt-button:hover {
            background-color: #236b42 !important;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2) !important;
        }
        
        .dt-button .material-icons {
            margin-right: 8px !important;
            font-size: 18px !important;
            font-family: 'Material Icons' !important;
        }
        
        .filter-container {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .table-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .table-header {
            background: #2a7d4f;
            color: white;
            padding: 15px 20px;
            margin: 0;
            font-weight: 600;
            font-family: 'Poppins', sans-serif;
            display: flex;
            align-items: center;
        }
        
        .table-header .material-icons {
            margin-right: 10px;
            font-size: 24px;
        }
        
        #masterlistTable {
            width: 100% !important;
            margin: 0;
            font-family: 'Poppins', sans-serif;
        }
        
        #masterlistTable thead th {
            background-color: #f8f9fa;
            border-bottom: 2px solid #dee2e6;
            font-weight: 600;
            font-size: 14px;
            text-align: center;
            vertical-align: middle;
            padding: 12px 8px;
            white-space: nowrap;
            font-family: 'Poppins', sans-serif;
        }
        
        #masterlistTable tbody td {
            font-size: 13px;
            padding: 12px 10px;
            vertical-align: middle;
            border-bottom: 1px solid #f0f0f0;
            font-family: 'Poppins', sans-serif;
        }
        
        .employee-name {
            font-weight: 600;
            color: #2a7d4f;
            font-size: 14px;
        }
        
        .amount-cell {
            text-align: right;
            font-family: 'Poppins', sans-serif;
            font-weight: 500;
            font-size: 13px;
        }
        
        .positive-amount {
            color: #198754;
        }
        
        .negative-amount {
            color: #dc3545;
        }
        
        .department-badge {
            padding: 6px 14px;
            border-radius: 15px;
            font-size: 11px;
            font-weight: 500;
            text-transform: uppercase;
        }
        
        .dept-accounting {
            background-color: #e3f2fd;
            color: #1976d2;
        }
        
        .dept-hr {
            background-color: #f3e5f5;
            color: #7b1fa2;
        }
        
        .dept-guard {
            background-color: #e8f5e8;
            color: #2e7d32;
        }
        
        .total-row {
            background-color: #f8f9fa;
            font-weight: 600;
            border-top: 2px solid #dee2e6;
            font-size: 14px;
        }
        
        .dt-buttons {
            margin-bottom: 15px;
        }
        
        /* Ensure Material Icons display properly throughout the page */
        .material-icons {
            font-family: 'Material Icons' !important;
            font-weight: normal;
            font-style: normal;
            font-size: 24px;
            line-height: 1;
            letter-spacing: normal;
            text-transform: none;
            display: inline-block;
            white-space: nowrap;
            word-wrap: normal;
            direction: ltr;
            -webkit-font-feature-settings: 'liga';
            -webkit-font-smoothing: antialiased;
        }
        }
        
        .dt-button {
            background: #2a7d4f !important;
            border: 1px solid #2a7d4f !important;
            color: white !important;
            margin-right: 8px;
            padding: 8px 14px;
            border-radius: 4px;
            font-family: 'Poppins', sans-serif;
            font-size: 13px;
            display: inline-flex !important;
            align-items: center;
            gap: 6px;
            text-decoration: none;
        }
        
        .dt-button:hover {
            background: #1e5a3a !important;
            border-color: #1e5a3a !important;
            color: white !important;
        }
        
        .dt-button i.material-icons {
            font-size: 16px !important;
            line-height: 1;
        }
        
        .form-label, .form-select, .btn, h1, h3 {
            font-family: 'Poppins', sans-serif !important;
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
                <a href="accounting_dashboard.php"class="nav-link" data-bs-toggle="tooltip" data-bs-placement="right" title="Dashboard">
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
                <a href="masterlist.php" class="nav-link active" data-bs-toggle="tooltip" data-bs-placement="right" title="Masterlist">
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
                    <span><?php echo $superadminName; ?></span>
                    <img src="<?php echo $superadminProfile; ?>" alt="User Profile">
                </div>
        </div>

    <!-- Main content area -->
    <div class="container-fluid mt-4">
            <h1 class="page-title">Employee Payroll Masterlist</h1>
            
            <!-- Filters -->
            <div class="filter-container">
                <form method="GET" class="filter-form-custom row g-2 align-items-end">
                    <div class="col-md-3">
                        <label for="monthFilter" class="form-label">Month</label>
                        <select class="form-select" id="monthFilter" name="month">
                            <option value="01" <?php echo $month == '01' ? 'selected' : ''; ?>>January</option>
                            <option value="02" <?php echo $month == '02' ? 'selected' : ''; ?>>February</option>
                            <option value="03" <?php echo $month == '03' ? 'selected' : ''; ?>>March</option>
                            <option value="04" <?php echo $month == '04' ? 'selected' : ''; ?>>April</option>
                            <option value="05" <?php echo $month == '05' ? 'selected' : ''; ?>>May</option>
                            <option value="06" <?php echo $month == '06' ? 'selected' : ''; ?>>June</option>
                            <option value="07" <?php echo $month == '07' ? 'selected' : ''; ?>>July</option>
                            <option value="08" <?php echo $month == '08' ? 'selected' : ''; ?>>August</option>
                            <option value="09" <?php echo $month == '09' ? 'selected' : ''; ?>>September</option>
                            <option value="10" <?php echo $month == '10' ? 'selected' : ''; ?>>October</option>
                            <option value="11" <?php echo $month == '11' ? 'selected' : ''; ?>>November</option>
                            <option value="12" <?php echo $month == '12' ? 'selected' : ''; ?>>December</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="yearFilter" class="form-label">Year</label>
                        <select class="form-select" id="yearFilter" name="year">
                            <?php for($i = date('Y'); $i >= date('Y')-5; $i--): ?>
                                <option value="<?php echo $i; ?>" <?php echo $year == $i ? 'selected' : ''; ?>><?php echo $i; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="departmentFilter" class="form-label">Department</label>
                        <select class="form-select" id="departmentFilter" name="department">
                            <option value="all" <?php echo $department == 'all' ? 'selected' : ''; ?>>All Departments</option>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?php echo $dept['Role_ID']; ?>" <?php echo $department == $dept['Role_ID'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($dept['Role_Name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-success filter-btn">
                            <i class="material-icons">search</i> Apply Filter
                        </button>
                    </div>
                </form>
            </div>

            <!-- Table Container -->
            <div class="table-container">
                <h3 class="table-header">
                    <i class="material-icons">assignment</i>
                    Employee Payroll Masterlist for 
                    <?php 
                    $monthName = date('F', mktime(0, 0, 0, $month, 1));
                    echo "$monthName $year";
                    
                    // Show department filter in title if selected
                    if ($department !== 'all') {
                        foreach ($departments as $dept) {
                            if ($dept['Role_ID'] == $department) {
                                echo " - " . htmlspecialchars($dept['Role_Name']) . " Department";
                                break;
                            }
                        }
                    }
                    ?>
                </h3>
                
                <div class="table-responsive p-3">
                    <table id="masterlistTable" class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th rowspan="2">Employee ID</th>
                                <th rowspan="2">Employee Name</th>
                                <th rowspan="2">Department</th>
                                <th rowspan="2">Length of Service</th>
                                <th colspan="3">Current Period</th>
                                <th colspan="4">Historical Earnings (Gross Pay)</th>
                            </tr>
                            <tr>
                                <th>Gross Pay</th>
                                <th>Deductions</th>
                                <th>Net Pay</th>
                                <th>3 Months</th>
                                <th>6 Months</th>
                                <th>9 Months</th>
                                <th>12 Months</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $totalGross = 0;
                            $totalDeductions = 0;
                            $totalNet = 0;
                            $total3Months = 0;
                            $total6Months = 0;
                            $total9Months = 0;
                            $total12Months = 0;
                            
                            foreach ($employees as $employee): 
                                $fullName = trim($employee['First_Name'] . ' ' . 
                                          ($employee['Middle_Name'] ? $employee['Middle_Name'] . ' ' : '') . 
                                          $employee['Last_Name']);
                                
                                // Calculate payroll data for this employee
                                $payrollData = calculateEmployeePayrollData($employee['User_ID'], $payrollCalculator, $year, $month);
                                
                                $totalGross += $payrollData['current_gross_pay'];
                                $totalDeductions += $payrollData['current_deductions'];
                                $totalNet += $payrollData['current_net_pay'];
                                $total3Months += $payrollData['three_months_total'];
                                $total6Months += $payrollData['six_months_total'];
                                $total9Months += $payrollData['nine_months_total'];
                                $total12Months += $payrollData['twelve_months_total'];
                                
                                // Department badge class
                                $deptClass = '';
                                switch($employee['department']) {
                                    case 'Accounting':
                                        $deptClass = 'dept-accounting';
                                        break;
                                    case 'HR':
                                        $deptClass = 'dept-hr';
                                        break;
                                    case 'Security Guard':
                                        $deptClass = 'dept-guard';
                                        break;
                                }
                                
                                // Calculate length of service
                                $lengthOfService = "Not available";
                                if (!empty($employee['hired_date'])) {
                                    $serviceDate = new DateTime($employee['hired_date']);
                                    $now = new DateTime();
                                    $interval = $serviceDate->diff($now);
                                    
                                    if ($interval->y > 0) {
                                        $lengthOfService = $interval->y . " year" . ($interval->y > 1 ? "s" : "");
                                        if ($interval->m > 0) {
                                            $lengthOfService .= ", " . $interval->m . " month" . ($interval->m > 1 ? "s" : "");
                                        }
                                    } else if ($interval->m > 0) {
                                        $lengthOfService = $interval->m . " month" . ($interval->m > 1 ? "s" : "");
                                        if ($interval->d > 0) {
                                            $lengthOfService .= ", " . $interval->d . " day" . ($interval->d > 1 ? "s" : "");
                                        }
                                    } else {
                                        $lengthOfService = $interval->d . " day" . ($interval->d > 1 ? "s" : "");
                                    }
                                    
                                    // Add hire date in parentheses
                                    $hireDate = $serviceDate->format('M j, Y');
                                    $lengthOfService .= "<br><small class='text-muted'>(Since " . $hireDate . ")</small>";
                                }
                            ?>
                            <tr>
                                <td class="text-center"><?php echo htmlspecialchars($employee['employee_id']); ?></td>
                                <td class="employee-name"><?php echo htmlspecialchars($fullName); ?></td>
                                <td class="text-center">
                                    <span class="department-badge <?php echo $deptClass; ?>">
                                        <?php echo htmlspecialchars($employee['department']); ?>
                                    </span>
                                </td>
                                <td><?php echo $lengthOfService; ?></td>
                                <td class="amount-cell positive-amount">₱<?php echo number_format($payrollData['current_gross_pay'], 2); ?></td>
                                <td class="amount-cell negative-amount">₱<?php echo number_format($payrollData['current_deductions'], 2); ?></td>
                                <td class="amount-cell positive-amount">₱<?php echo number_format($payrollData['current_net_pay'], 2); ?></td>
                                <td class="amount-cell">₱<?php echo number_format($payrollData['three_months_total'], 2); ?></td>
                                <td class="amount-cell">₱<?php echo number_format($payrollData['six_months_total'], 2); ?></td>
                                <td class="amount-cell">₱<?php echo number_format($payrollData['nine_months_total'], 2); ?></td>
                                <td class="amount-cell">₱<?php echo number_format($payrollData['twelve_months_total'], 2); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr class="total-row">
                                <td colspan="4" class="text-end"><strong>TOTALS:</strong></td>
                                <td class="amount-cell"><strong>₱<?php echo number_format($totalGross, 2); ?></strong></td>
                                <td class="amount-cell"><strong>₱<?php echo number_format($totalDeductions, 2); ?></strong></td>
                                <td class="amount-cell"><strong>₱<?php echo number_format($totalNet, 2); ?></strong></td>
                                <td class="amount-cell"><strong>₱<?php echo number_format($total3Months, 2); ?></strong></td>
                                <td class="amount-cell"><strong>₱<?php echo number_format($total6Months, 2); ?></strong></td>
                                <td class="amount-cell"><strong>₱<?php echo number_format($total9Months, 2); ?></strong></td>
                                <td class="amount-cell"><strong>₱<?php echo number_format($total12Months, 2); ?></strong></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
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
    
    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.2.2/js/dataTables.buttons.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.print.min.js"></script>

    <!-- Sidebar and Tooltip JS -->
    <script src="js/accounting_dashboard.js"></script>

    <script>
        $(document).ready(function() {
            // Initialize DataTable with export functionality
            $('#masterlistTable').DataTable({
                dom: 'Bfrtip',
                buttons: [
                    {
                        extend: 'excel',
                        text: '<i class="material-icons">download</i> Excel',
                        title: 'Employee Payroll Masterlist - <?php echo "$monthName $year"; ?>',
                        exportOptions: {
                            columns: ':visible'
                        },
                        className: 'dt-button',
                        attr: {
                            title: 'Export to Excel'
                        }
                    },
                    {
                        extend: 'pdf',
                        text: '<i class="material-icons">picture_as_pdf</i> PDF',
                        title: 'Employee Payroll Masterlist - <?php echo "$monthName $year"; ?>',
                        orientation: 'landscape',
                        pageSize: 'A4',
                        exportOptions: {
                            columns: ':visible'
                        },
                        className: 'dt-button',
                        attr: {
                            title: 'Export to PDF'
                        }
                    },
                    {
                        extend: 'print',
                        text: '<i class="material-icons">print</i> Print',
                        title: 'Employee Payroll Masterlist - <?php echo "$monthName $year"; ?>',
                        exportOptions: {
                            columns: ':visible'
                        },
                        className: 'dt-button',
                        attr: {
                            title: 'Print Table'
                        }
                    }
                ],
                pageLength: 25,
                lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]],
                order: [[1, 'asc']],
                columnDefs: [
                    { targets: [0, 2], className: 'text-center' },
                    { targets: [4, 5, 6, 7, 8, 9, 10], className: 'text-right' }
                ],
                responsive: true,
                language: {
                    search: "Search employees:",
                    lengthMenu: "Show _MENU_ employees per page",
                    info: "Showing _START_ to _END_ of _TOTAL_ employees",
                    emptyTable: "No employees found matching the criteria"
                }
            });

            // Auto-submit form when filters change
            $('#monthFilter, #yearFilter, #departmentFilter').change(function() {
                $(this).closest('form').submit();
            });

            // Update current date and time
            function updateDateTime() {
                const now = new Date();
                const options = { 
                    weekday: 'long', 
                    year: 'numeric', 
                    month: 'long', 
                    day: 'numeric' 
                };
                const timeOptions = {
                    hour: '2-digit',
                    minute: '2-digit',
                    second: '2-digit'
                };
                
                document.getElementById('current-date').textContent = now.toLocaleDateString('en-US', options);
                document.getElementById('current-time').textContent = now.toLocaleTimeString('en-US', timeOptions);
            }

            updateDateTime();
            setInterval(updateDateTime, 1000);
        });
    </script>

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
            <a href="payroll.php" class="mobile-nav-item">
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
            <a href="masterlist.php" class="mobile-nav-item active">
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
</body>