<?php
require_once __DIR__ . '/../includes/session_check.php';
validateSession($conn);

// Get current super admin user's name
$superadminStmt = $conn->prepare("SELECT First_Name, Last_Name FROM users WHERE Role_ID = 1 AND status = 'Active' AND User_ID = ?");
$superadminStmt->execute([$_SESSION['user_id']]);
$superadminData = $superadminStmt->fetch(PDO::FETCH_ASSOC);
$superadminName = $superadminData ? $superadminData['First_Name'] . ' ' . $superadminData['Last_Name'] : "Super Admin";

// Get profile picture
$profileStmt = $conn->prepare("SELECT Profile_Pic, First_Name, Last_Name FROM users WHERE User_ID = ?");
$profileStmt->execute([$_SESSION['user_id']]);
$profileData = $profileStmt->fetch(PDO::FETCH_ASSOC);
if ($profileData && !empty($profileData['Profile_Pic']) && file_exists($profileData['Profile_Pic'])) {
    $superadminProfile = $profileData['Profile_Pic'];
} else {
    $superadminProfile = '../images/default_profile.png';
}

// Include PayrollCalculator class
require_once 'payroll_calculation/unified_payroll_calculator.php';

// Get date parameters
$month = isset($_GET['month']) ? $_GET['month'] : date('Y-m');
$dateRange = isset($_GET['dateRange']) ? $_GET['dateRange'] : '1-15'; // Default to first half of month

// Calculate date range based on parameters
if ($dateRange === '1-15') {
    $startDate = "$month-01";
    $endDate = "$month-15";
} else {
    $startDate = "$month-16";
    $endDate = date('Y-m-t', strtotime($month)); // Last day of month
}

// For debugging
if (empty($startDate) || empty($dateRange)) {
    $startDate = date('Y-m-01'); // Default to first day of current month
    $endDate = date('Y-m-15');   // Default to 15th day of current month
    $dateRange = '1-15';         // Default range
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payroll - Green Meadows Security Agency</title>
    
    <!-- Include Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Google Material Icons -->
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Outlined" rel="stylesheet">
    
    <!-- Google Fonts - Poppins -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="css/payroll.css">

    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.11.5/css/jquery.dataTables.min.css">
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/fixedcolumns/4.0.2/css/fixedColumns.dataTables.min.css">
    <script type="text/javascript" src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script type="text/javascript" src="https://cdn.datatables.net/fixedcolumns/4.0.2/js/dataTables.fixedColumns.min.js"></script>
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
                <a href="superadmin_dashboard.php" class="nav-link" data-bs-toggle="tooltip" data-bs-placement="right" title="Dashboard">
                    <span class="material-icons">dashboard</span>
                    <span>Dashboard</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="payroll.php" class="nav-link active" data-bs-toggle="tooltip" data-bs-placement="right" title="Payroll">
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

    <!-- Date Filter UI -->
    <div class="container-fluid mb-3">
        <div class="filter-container">
            <form id="payrollFilterForm" class="row g-2 align-items-end" method="GET" action="">
                <div class="col-md-2">
                    <label for="month" class="form-label">Month</label>
                    <select class="form-select" id="month" name="month">
                        <?php
                        $months = [
                            '01' => 'January', '02' => 'February', '03' => 'March', '04' => 'April',
                            '05' => 'May', '06' => 'June', '07' => 'July', '08' => 'August',
                            '09' => 'September', '10' => 'October', '11' => 'November', '12' => 'December'
                        ];
                        
                        // Extract month from the current date range
                        $selectedMonthNum = substr($month, 5, 2); // extract month from YYYY-MM
                        if (empty($selectedMonthNum)) {
                            $selectedMonthNum = date('m');
                        }
                        
                        foreach ($months as $num => $name) {
                            $yearMonth = date('Y') . "-" . $num;
                            $selected = ($selectedMonthNum == $num) ? 'selected' : '';
                            echo "<option value='$yearMonth' $selected>$name</option>";
                        }
                        ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="year" class="form-label">Year</label>
                    <select class="form-select" id="year" name="year">
                        <?php
                        $currentYear = date('Y');
                        $selectedYear = substr($month, 0, 4); // extract year from YYYY-MM
                        if (empty($selectedYear)) {
                            $selectedYear = $currentYear;
                        }
                        
                        for ($y = $currentYear - 5; $y <= $currentYear + 2; $y++) {
                            $selected = ($selectedYear == $y) ? 'selected' : '';
                            echo "<option value='$y' $selected>$y</option>";
                        }
                        ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="dateRange" class="form-label">Date Period</label>
                    <select class="form-select" id="dateRange" name="dateRange">
                        <option value="1-15" <?php if($dateRange==='1-15') echo 'selected'; ?>>1st - 15th</option>
                        <option value="16-31" <?php if($dateRange==='16-31') echo 'selected'; ?>>16th - 31st</option>
                        <option value="1-31" <?php if($dateRange==='1-31') echo 'selected'; ?>>1st - 31st</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="location" class="form-label">Location</label>
                    <select class="form-select" id="location" name="location">
                        <option value="">All Locations</option>
                        <?php
                        // Get unique locations from guard_locations table
                        $locationsQuery = "SELECT DISTINCT location_name FROM guard_locations WHERE is_active = 1 ORDER BY location_name";
                        $locationsStmt = $conn->query($locationsQuery);
                        $selectedLocation = isset($_GET['location']) ? $_GET['location'] : '';
                        
                        while ($location = $locationsStmt->fetch(PDO::FETCH_ASSOC)) {
                            $selected = ($selectedLocation == $location['location_name']) ? 'selected' : '';
                            echo "<option value='" . htmlspecialchars($location['location_name']) . "' $selected>" . 
                                htmlspecialchars($location['location_name']) . 
                                "</option>";
                        }
                        ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-success w-100">Apply Filter</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Prepare view text but don't show redundant alert -->
    <?php
    function getViewingText($month, $dateRange, $year) {
        $monthName = date('F', strtotime($month.'-01'));
        $year = substr($month, 0, 4); // extract year from YYYY-MM
        if (empty($year)) {
            $year = date('Y');
        }
        
        if ($dateRange === '1-15') {
            return "$monthName 1-15, $year";
        } elseif ($dateRange === '16-31') {
            return "$monthName 16-31, $year";
        } elseif ($dateRange === '1-31') {
            return "$monthName 1-31, $year";
        } else {
            return "$monthName, $year";
        }
    }
    $viewingText = getViewingText($month, $dateRange, isset($_GET['year']) ? $_GET['year'] : date('Y'));
    ?>

    <!-- Payroll Table Section -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="card-title mb-0 text-center" style="font-size: 1.5rem; font-weight: bold;">
                <i class="material-icons align-middle me-2">payments</i>
                Payroll For <?php echo $viewingText; ?>
            </h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table id="payrollTable" class="table table-striped table-bordered">
                    <thead>
                        <tr class="table-primary">
                            <th class="align-middle" rowspan="2">Name</th>
                            <th class="text-center align-middle bg-success text-white" colspan="10">Earnings</th>
                            <th class="text-center align-middle bg-danger text-white" colspan="11">Deductions</th>
                            <th class="align-middle text-center" rowspan="2">Net Salary</th>
                            <th class="align-middle text-center" rowspan="2">Actions</th>
                        </tr>
                        <tr>
                            <!-- Earnings sub-headers -->
                            <th class="earnings-header">REG HRS</th>
                            <th class="earnings-header">REG OT</th>
                            <th class="earnings-header">SUN/RD/SPCL. HOL.</th>
                            <th class="earnings-header">SPCL. HOL. OT</th>
                            <th class="earnings-header">LEGAL HOLIDAY</th>
                            <th class="earnings-header">NIGHT DIFF</th>
                            <th class="earnings-header">UNIFORM/OTHER ALLOWANCE</th>
                            <th class="earnings-header">CTP ALLOWANCE</th>
                            <th class="earnings-header">RETROOACTIVE</th>
                            <th class="earnings-header font-weight-bold">GROSS PAY</th>
                            
                            <!-- Deductions sub-headers -->
                            <th class="deductions-header">TAX W/HELD</th>
                            <th class="deductions-header">SSS</th>
                            <th class="deductions-header">PHILHEALTH</th>
                            <th class="deductions-header">PAG-IBIG</th>
                            <th class="deductions-header">SSS LOAN</th>
                            <th class="deductions-header">PAG-IBIG LOAN</th>
                            <th class="deductions-header">LATE/UNDERTIME</th>
                            <th class="deductions-header">CASH ADVANCES</th>
                            <th class="deductions-header">CASH BOND</th>
                            <th class="deductions-header">OTHERS</th>
                            <th class="deductions-header font-weight-bold">TOTAL DEDUCTIONS</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
                    // Only get guards
                    $sql = "SELECT u.user_id, u.first_name, u.middle_name, u.last_name, 
                            CONCAT(u.first_name, ' ', 
                            CASE WHEN u.middle_name IS NOT NULL AND u.middle_name != '' 
                                THEN CONCAT(UPPER(LEFT(u.middle_name, 1)), '. ') 
                                ELSE '' END, 
                            u.last_name) AS name, r.role_name AS department
                            FROM users u
                            JOIN roles r ON u.role_id = r.role_id";

                    // Add location filter if specified
                    $selectedLocation = isset($_GET['location']) ? $_GET['location'] : '';
                    if (!empty($selectedLocation)) {
                        $sql .= " JOIN guard_locations gl ON u.user_id = gl.user_id AND gl.location_name = :location_name AND gl.is_active = 1";
                    }

                    $sql .= " WHERE r.role_name = 'Security Guard' AND u.status = 'Active'
                              ORDER BY u.last_name ASC, u.first_name ASC";

                    $stmt = $conn->prepare($sql);

                    // Bind location parameter if needed
                    if (!empty($selectedLocation)) {
                        $stmt->bindParam(':location_name', $selectedLocation);
                    }

                    $stmt->execute();
                    $calculator = new PayrollCalculator($conn);

                    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                        // Check for attendance in the selected period
                        $attendance_check_sql = "SELECT COUNT(*) FROM attendance WHERE User_ID = :user_id AND DATE(time_in) BETWEEN :start_date AND :end_date";
                        $attendance_check_stmt = $conn->prepare($attendance_check_sql);
                        $attendance_check_stmt->bindParam(':user_id', $row['user_id'], PDO::PARAM_INT);
                        $attendance_check_stmt->bindParam(':start_date', $startDate);
                        $attendance_check_stmt->bindParam(':end_date', $endDate);
                        $attendance_check_stmt->execute();
                        $attendance_count = $attendance_check_stmt->fetchColumn();

                        $payroll_data = $calculator->calculatePayrollForGuard($row['user_id'], null, null, $startDate, $endDate);
                        
                        // Get saved cash advance from database
                        $cash_advance_sql = "SELECT Cash_Advances FROM payroll WHERE User_ID = :user_id AND Period_Start = :start_date AND Period_End = :end_date";
                        $cash_advance_stmt = $conn->prepare($cash_advance_sql);
                        $cash_advance_stmt->bindParam(':user_id', $row['user_id'], PDO::PARAM_INT);
                        $cash_advance_stmt->bindParam(':start_date', $startDate);
                        $cash_advance_stmt->bindParam(':end_date', $endDate);
                        $cash_advance_stmt->execute();
                        $cash_advance_result = $cash_advance_stmt->fetch(PDO::FETCH_ASSOC);
                        $saved_cash_advance = $cash_advance_result ? $cash_advance_result['Cash_Advances'] : 0;
                        
                        echo "<tr>";
                        echo "<td class='employee-name'>" . htmlspecialchars($row['name']) . "</td>";
                        
                        // If no attendance, display all columns as ₱0.00 (and 0.00 for input)
                        if ($attendance_count == 0) {
                            for ($i = 0; $i < 10; $i++) {
                                echo "<td class='amount-cell'>₱0.00</td>";
                            }
                            for ($i = 0; $i < 7; $i++) {
                                echo "<td class='amount-cell'>₱0.00</td>";
                            }
                            echo "<td><input type='number' class='form-control cash-advance-input' data-user-id='" . $row['user_id'] . "' value='" . number_format($saved_cash_advance, 2, '.', '') . "' min='0' max='1000' step='0.01'></td>";
                            if (!empty($payroll_data['cash_bond_limit_reached']) && $payroll_data['cash_bond_limit_reached']) {
                                echo "<td class='amount-cell'>₱0.00 <span class='badge bg-success'>Limit Reached</span></td>";
                            } else {
                                echo "<td class='amount-cell'>₱0.00</td>";
                            }
                            echo "<td class='amount-cell'>₱0.00</td>"; // Others
                            echo "<td class='amount-cell total-deductions'>₱0.00</td>";
                            echo "<td class='amount-cell net-pay'>₱0.00</td>";
                        } else if (empty($payroll_data['gross_pay']) || $payroll_data['gross_pay'] == 0 || empty($payroll_data['net_pay']) || $payroll_data['net_pay'] == 0) {
                            // If gross pay or net pay is empty or zero, display all columns as ₱0.00 (and 0.00 for input)
                            for ($i = 0; $i < 10; $i++) {
                                echo "<td class='amount-cell'>₱0.00</td>";
                            }
                            for ($i = 0; $i < 7; $i++) {
                                echo "<td class='amount-cell'>₱0.00</td>";
                            }
                            echo "<td><input type='number' class='form-control cash-advance-input' data-user-id='" . $row['user_id'] . "' value='" . number_format($saved_cash_advance, 2, '.', '') . "' min='0' max='1000' step='0.01'></td>";
                            if (!empty($payroll_data['cash_bond_limit_reached']) && $payroll_data['cash_bond_limit_reached']) {
                                echo "<td class='amount-cell'>₱0.00 <span class='badge bg-success'>Limit Reached</span></td>";
                            } else {
                                echo "<td class='amount-cell'>₱0.00</td>";
                            }
                            echo "<td class='amount-cell'>₱0.00</td>"; // Others
                            echo "<td class='amount-cell total-deductions'>₱0.00</td>";
                            echo "<td class='amount-cell net-pay'>₱0.00</td>";
                        } else {
                            // Earnings columns
                            echo "<td class='amount-cell'>₱" . number_format($payroll_data['regular_hours_pay'] ?? 0, 2) . "</td>";
                            echo "<td class='amount-cell'>₱" . number_format($payroll_data['ot_pay'] ?? 0, 2) . "</td>";
                            echo "<td class='amount-cell'>₱" . number_format($payroll_data['special_holiday_pay'] ?? 0, 2) . "</td>";
                            echo "<td class='amount-cell'>₱" . number_format($payroll_data['special_holiday_ot_pay'] ?? 0, 2) . "</td>";
                            echo "<td class='amount-cell'>₱" . number_format($payroll_data['legal_holiday_pay'] ?? 0, 2) . "</td>";
                            echo "<td class='amount-cell'>₱" . number_format($payroll_data['night_diff_pay'] ?? 0, 2) . "</td>";
                            echo "<td class='amount-cell'>₱" . number_format($payroll_data['uniform_allowance'] ?? 0, 2) . "</td>";
                            echo "<td class='amount-cell'>₱" . number_format($payroll_data['ctp_allowance'] ?? 0, 2) . "</td>";
                            echo "<td class='amount-cell'>₱" . number_format($payroll_data['retroactive_pay'] ?? 0, 2) . "</td>";
                            echo "<td class='amount-cell gross-pay'>₱" . number_format($payroll_data['gross_pay'] ?? 0, 2) . "</td>";
                            
                            // Deductions columns
                            echo "<td class='amount-cell'>₱" . number_format($payroll_data['tax'] ?? 0, 2) . "</td>";
                            echo "<td class='amount-cell'>₱" . number_format($payroll_data['sss'] ?? 0, 2) . "</td>";
                            echo "<td class='amount-cell'>₱" . number_format($payroll_data['philhealth'] ?? 0, 2) . "</td>";
                            echo "<td class='amount-cell'>₱" . number_format($payroll_data['pagibig'] ?? 0, 2) . "</td>";
                            echo "<td class='amount-cell'>₱" . number_format($payroll_data['sss_loan'] ?? 0, 2) . "</td>";
                            echo "<td class='amount-cell'>₱" . number_format($payroll_data['pagibig_loan'] ?? 0, 2) . "</td>";
                            echo "<td class='amount-cell'>₱" . number_format($payroll_data['late_undertime'] ?? 0, 2) . "</td>";
                            echo "<td><input type='number' class='form-control cash-advance-input' data-user-id='" . $row['user_id'] . "' value='" . number_format($saved_cash_advance, 2, '.', '') . "' min='0' max='1000' step='0.01'></td>";
                            
                            if (!empty($payroll_data['cash_bond_limit_reached']) && $payroll_data['cash_bond_limit_reached']) {
                                echo "<td class='amount-cell'>₱0.00 <span class='badge bg-success'>Limit Reached</span></td>";
                            } else {
                                echo "<td class='amount-cell'>₱" . number_format($payroll_data['cash_bond'] ?? 0, 2) . "</td>";
                            }
                            
                            echo "<td class='amount-cell'>₱" . number_format($payroll_data['other_deductions'] ?? 0, 2) . "</td>";
                            echo "<td class='amount-cell total-deductions'>₱" . number_format($payroll_data['total_deductions'] ?? 0, 2) . "</td>";
                            
                            // Total Net Salary
                            echo "<td class='amount-cell net-pay fw-bold'>₱" . number_format($payroll_data['net_pay'] ?? 0, 2) . "</td>";
                        }
                        
                        // Actions column with improved payslip button
                        echo "<td class='text-center'>
                            <button class='btn btn-success btn-sm payslip-btn' data-user-id='{$row['user_id']}'>
                                <i class='material-icons align-middle fs-6'>description</i> Payslip
                            </button>
                        </td>";
                        echo "</tr>";
                    }
                    ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>


    <!-- Bootstrap and jQuery JS -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/superadmin_dashboard.js"></script>

    <!-- Mobile Bottom Navigation -->
    <div class="mobile-nav">
        <div class="mobile-nav-container">
            <!-- Mirror sidebar links and order -->
            <a href="superadmin_dashboard.php" class="mobile-nav-item">
                <span class="material-icons">dashboard</span>
                <span class="mobile-nav-text">Dashboard</span>
            </a>
            <a href="payroll.php" class="mobile-nav-item active">
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

    <script>
            $(document).ready(function() {
                // Handle payslip button click
                $('.payslip-btn').on('click', function() {
                    const userId = $(this).data('user-id');
                    const month = '<?php echo $month; ?>';
                    const dateRange = '<?php echo $dateRange; ?>';
                    
                    // Open payslip in new window
                    window.open(`generate_payslip.php?user_id=${userId}&month=${month}&dateRange=${dateRange}`, '_blank');
                });

                // Add Cash Advance Input Handler
                $('.cash-advance-input').on('blur', function() {
                    saveCashAdvance($(this));
                }).on('keydown', function(e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        $(this).blur(); // triggers blur and save
                    }
                });

                function saveCashAdvance(input) {
                    let value = input.val();

                    // Clear invalid input
                    if (value === "" || isNaN(parseFloat(value))) {
                        value = "0.00";
                        input.val(value);
                    }

                    // Validate value is not negative
                    if (parseFloat(value) < 0) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Invalid Amount',
                            text: 'Negative cash advance values are not allowed',
                            confirmButtonColor: '#2a7d4f'
                        });
                        input.val("0.00");
                        return;
                    }

                    // Validate value does not exceed maximum
                    if (parseFloat(value) > 1000) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Invalid Amount',
                            text: 'Cash advance cannot exceed ₱1,000',
                            confirmButtonColor: '#2a7d4f'
                        });
                        input.val("1000.00");
                        return;
                    }

                    input.prop('disabled', true);

                    $.ajax({
                        url: 'save_cash_advance.php',
                        method: 'POST',
                        data: {
                            user_id: input.data('user-id'),
                            cash_advance: value,
                            start_date: '<?php echo $startDate; ?>',
                            end_date: '<?php echo $endDate; ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                // Always format to 2 decimal places after save
                                input.val(parseFloat(value).toFixed(2));
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Saved',
                                    text: 'Cash advance updated successfully',
                                    timer: 1500,
                                    showConfirmButton: false
                                });
                            } else {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Error',
                                    text: response.message || 'Failed to update cash advance',
                                    confirmButtonColor: '#2a7d4f'
                                });
                            }
                        },
                        error: function() {
                            Swal.fire({
                                icon: 'error',
                                title: 'Server Error',
                                text: 'Failed to connect to the server',
                                confirmButtonColor: '#2a7d4f'
                            });
                        },
                        complete: function() {
                            input.prop('disabled', false);
                        }
                    });
                }
            });

            // Initialize DataTables with fixed columns
            $(document).ready(function() {
                // Add this to your existing document ready function
                if ($.fn.dataTable) {
                    var payrollTable = $('#payrollTable').DataTable({
                        scrollX: true,
                        scrollCollapse: true,
                        fixedColumns: {
                            left: 1 // Fix the first column (name column)
                        },
                        paging: true,
                        searching: true
                    });
                    
                    // Force redraw when switching tabs or after page has fully loaded
                    // This helps ensure the fixed columns are properly aligned
                    setTimeout(function() {
                        $(window).trigger('resize');
                        if (payrollTable) payrollTable.columns.adjust();
                    }, 300);
                }
            });
</script>
</body>
</html>