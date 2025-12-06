<?php
require_once __DIR__ . '/../includes/session_check.php';
validateSession($conn, 4);
require_once __DIR__ . '/../db_connection.php';
require_once __DIR__ . '/payroll_calculation/unified_payroll_calculator.php';

if (session_status() === PHP_SESSION_NONE) session_start();

// Params
$month = $_GET['month'] ?? date('Y-m');
$dateRange = $_GET['dateRange'] ?? '1-15';
$lastDayOfMonth = date('t', strtotime($month . '-01'));
if (preg_match('/^1-3[01]$/', $dateRange) || preg_match('/^1-2[89]$/', $dateRange)) {
    $dateRange = '1-' . $lastDayOfMonth;
} elseif (preg_match('/^16-3[01]$/', $dateRange) || preg_match('/^16-2[89]$/', $dateRange)) {
    $dateRange = '16-' . $lastDayOfMonth;
}
if ($dateRange === '1-15') {
    $startDate = "$month-01";
    $endDate = "$month-15";
} elseif ($dateRange === '16-' . $lastDayOfMonth) {
    $startDate = "$month-16";
    $endDate = date('Y-m-t', strtotime($month));
} else {
    // default full month
    $startDate = "$month-01";
    $endDate = date('Y-m-t', strtotime($month));
}

$calculator = new PayrollCalculator($conn);

// Optional location filter
$selectedLocation = isset($_GET['location']) ? $_GET['location'] : '';

// Fetch guards (active) with optional location filter
$sql = "SELECT u.employee_id, u.user_id, u.first_name, u.middle_name, u.last_name,
    CONCAT(u.first_name, ' ', CASE WHEN u.middle_name IS NOT NULL AND u.middle_name != '' THEN CONCAT(UPPER(LEFT(u.middle_name,1)), '. ') ELSE '' END, u.last_name) AS name
    FROM users u JOIN roles r ON u.role_id = r.role_id";
if (!empty($selectedLocation)) {
    $sql .= " JOIN guard_locations gl ON u.user_id = gl.user_id AND gl.location_name = :location_name AND gl.is_active = 1";
}
$sql .= " WHERE r.role_name = 'Security Guard' AND u.status = 'Active' ORDER BY u.last_name ASC, u.first_name ASC";
$stmt = $conn->prepare($sql);
if (!empty($selectedLocation)) {
    $stmt->bindParam(':location_name', $selectedLocation);
}
$stmt->execute();
$guards = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch current accounting user profile and name (from payroll.php pattern)
$profilePic = '../images/default_profile.png';
$profileName = '';
try {
    $profileStmt = $conn->prepare("SELECT Profile_Pic, First_Name, Last_Name FROM users WHERE User_ID = ?");
    $profileStmt->execute([$_SESSION['user_id'] ?? 0]);
    $profileData = $profileStmt->fetch(PDO::FETCH_ASSOC);
    if ($profileData) {
        $profileName = trim(($profileData['First_Name'] ?? '') . ' ' . ($profileData['Last_Name'] ?? ''));
        if (!empty($profileData['Profile_Pic']) && file_exists($profileData['Profile_Pic'])) {
            $profilePic = $profileData['Profile_Pic'];
        }
    }
} catch (Exception $e) {
    // fallback to default values if query fails
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Payroll Register</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Outlined" rel="stylesheet">
    <link rel="stylesheet" href="css/payroll.css">
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.11.5/css/jquery.dataTables.min.css">
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/fixedcolumns/4.0.2/css/fixedColumns.dataTables.min.css">
</head>
<body>
    <style>
        .table-hscroll { overflow-x: auto; }
        .table-hscroll::-webkit-scrollbar { height: 12px; }
        .table-hscroll::-webkit-scrollbar-thumb { background-color: #c5c5c5; border-radius: 6px; }
        .table-hscroll::-webkit-scrollbar-track { background-color: #f1f1f1; }
    </style>
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
                <a href="accounting_dashboard.php" class="nav-link" data-bs-toggle="tooltip" data-bs-placement="right" title="Dashboard">
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
                <a href="masterlist.php" class="nav-link" data-bs-toggle="tooltip" data-bs-placement="right" title="Masterlist">
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
            <li class="nav-item">
                <a href="employee_share.php" class="nav-link" data-bs-toggle="tooltip" data-bs-placement="right" title="Employee Share">
                    <span class="material-icons">diversity_3</span>
                    <span>Employer Contributions</span>
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
                <span><?= htmlspecialchars($profileName ?: ($_SESSION['user_id'] ?? '')) ?></span>
                <a href="profile.php"><img src="<?= htmlspecialchars($profilePic) ?>" alt="User Profile"></a>
            </div>
        </div>

        <div class="container-fluid py-3">
            <div class="d-flex align-items-center justify-content-center mb-3">
                <h3 class="mb-0 text-center">Payroll Register</h3>
            </div>

            <div class="container-fluid py-3 bg-white rounded mb-3">
            <form class="row g-2 align-items-end mb-3 justify-content-center" method="GET" action="">
                <div class="col-md-2 col-6 text-center">
                    <label class="form-label">Month</label>
                    <input type="month" class="form-control" name="month" value="<?= htmlspecialchars($month) ?>">
                </div>
                <div class="col-md-2 col-6 text-center">
                    <label class="form-label">Cutoff Period</label>
                    <?php $secondLabel = '16-' . $lastDayOfMonth; ?>
                    <select class="form-select" name="dateRange">
                        <option value="1-15" <?= $dateRange==='1-15' ? 'selected' : '' ?>>1st - 15th</option>
                        <option value="<?= $secondLabel ?>" <?= $dateRange===$secondLabel ? 'selected' : '' ?>>16th - <?= $lastDayOfMonth ?></option>
                    </select>
                </div>
                <div class="col-md-2 col-6 text-center">
                    <label class="form-label">Location</label>
                    <select class="form-select" name="location">
                        <option value="">All Locations</option>
                        <?php
                        $locationsQuery = "SELECT DISTINCT location_name FROM guard_locations WHERE is_active = 1 ORDER BY location_name";
                        $locationsStmt = $conn->query($locationsQuery);
                        while ($location = $locationsStmt->fetch(PDO::FETCH_ASSOC)) {
                            $sel = ($selectedLocation === $location['location_name']) ? 'selected' : '';
                            echo "<option value='" . htmlspecialchars($location['location_name']) . "' $sel>" . htmlspecialchars($location['location_name']) . "</option>";
                        }
                        ?>
                    </select>
                </div>
                <div class="col-md-2 col-6 d-grid">
                    <label class="form-label" style="visibility:hidden;">Apply</label>
                    <button class="btn btn-success d-flex align-items-center justify-content-center" type="submit">
                        <span class="material-icons">search</span>
                        <span class="ms-1">Apply</span>
                    </button>
                </div>
            </form>
        </div>

            <!-- Action: Save as Excel -->
            <div class="container-fluid mb-3 d-flex justify-content-end gap-2">
                <button type="button" id="btnSaveExcel" class="btn btn-success d-flex align-items-center">
                    <span class="material-icons me-1">table_chart</span>
                    <span>Save as Excel</span>
                </button>
            </div>

            <div class="bg-white rounded p-3 mb-3">
                <div class="table-responsive table-hscroll">
                <table id="registerTable" class="table table-striped table-bordered mb-0">
                    <thead>
                        <tr class="table-primary">
                            <th class="align-middle" colspan="2">Employee Information</th>
                            <th class="text-center align-middle bg-success text-white" colspan="18">Earnings</th>
                            <th class="text-center align-middle bg-danger text-white" colspan="8">Deductions</th>
                            <th class="text-center align-middle" colspan="1">Summary</th>
                        </tr>
                        <tr>
                            <th>Employee ID</th>
                            <th>Employee Name</th>
                            <th>Regular Hours</th>
                            <th>Regular Pay</th>
                            <th>Regular OT Hours</th>
                            <th>Regular OT Pay</th>
                            <th>Sun/RD/SPCL Hol Hours</th>
                            <th>Sun/RD/SPCL Hol Pay</th>
                            <th>Special Holiday OT Hours</th>
                            <th>Special Holiday OT Pay</th>
                            <th>Legal Holiday Hours</th>
                            <th>Legal Holiday Pay</th>
                            <th>Legal Holiday OT Hours</th>
                            <th>Legal Holiday OT Pay</th>
                            <th>Night Differential Hours</th>
                            <th>Night Differential Pay</th>
                            <th>Uniform/Other Allowance</th>
                            <th>CTP Allowance</th>
                            <th>Retroactive</th>
                            <th>Gross Pay</th>
                            <th>SSS</th>
                            <th>PhilHealth</th>
                            <th>Pag-IBIG</th>
                            <th>Late / Undertime</th>
                            <th>Cash Advance</th>
                            <th>Cash Bond</th>
                            <th>Others</th>
                            <th>Total Deductions</th>
                            <th>Net Pay</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        // Initialize totals
                        $tot_regular_hours = 0; $tot_regular_hours_pay = 0; $tot_ot_hours = 0; $tot_ot_pay = 0;
                        $tot_sun_rd_spcl_hours = 0; $tot_sun_rd_spcl_pay = 0;
                        $tot_spcl_hol_ot_hours = 0; $tot_spcl_hol_ot_pay = 0;
                        $tot_legal_hol_hours = 0; $tot_legal_hol_pay = 0;
                        $tot_legal_hol_ot_hours = 0; $tot_legal_hol_ot_pay = 0;
                        $tot_nd_hours = 0; $tot_nd_pay = 0;
                        $tot_uniform_allow = 0; $tot_ctp_allow = 0; $tot_retro = 0;
                        $tot_gross = 0;
                        $tot_sss = 0; $tot_philhealth = 0; $tot_pagibig = 0; $tot_late_und = 0;
                        $tot_cash_advance = 0; $tot_cash_bond = 0; $tot_others = 0; $tot_total_deductions = 0; $tot_net = 0;
                        foreach ($guards as $g) {
                            $p = $calculator->calculatePayroll($g['user_id'], $startDate, $endDate);
                            // Skip rows with no attendance and zero net
                            $totalHours = 0.0;
                            foreach (['regular_hours','ot_hours','night_diff_hours','legal_holiday_hours','holiday_ot_hours','special_holiday_hours','special_holiday_ot_hours'] as $hk) {
                                if (isset($p[$hk])) { $totalHours += (float)$p[$hk]; }
                            }
                            $net = isset($p['net_pay']) ? (float)$p['net_pay'] : ((float)($p['gross_pay'] ?? 0) - (float)($p['total_deductions'] ?? 0));
                            if ($totalHours <= 0 && $net <= 0) { continue; }
                            echo '<tr>';
                            echo '<td>'.htmlspecialchars($g['employee_id'] ?? '').'</td>';
                            echo '<td>'.htmlspecialchars($g['name']).'</td>';
                            echo '<td>'.number_format((float)($p['regular_hours'] ?? 0), 2).'</td>';
                            echo '<td>₱ '.number_format((float)($p['regular_hours_pay'] ?? 0), 2).'</td>';
                            echo '<td>'.number_format((float)($p['ot_hours'] ?? 0), 2).'</td>';
                            echo '<td>₱ '.number_format((float)($p['ot_pay'] ?? 0), 2).'</td>';
                            echo '<td>'.number_format((float)($p['special_holiday_hours'] ?? 0), 2).'</td>';
                            echo '<td>₱ '.number_format((float)($p['special_holiday_pay'] ?? 0), 2).'</td>';
                            echo '<td>'.number_format((float)($p['special_holiday_ot_hours'] ?? 0), 2).'</td>';
                            echo '<td>₱ '.number_format((float)($p['special_holiday_ot_pay'] ?? 0), 2).'</td>';
                            echo '<td>'.number_format((float)($p['legal_holiday_hours'] ?? 0), 2).'</td>';
                            echo '<td>₱ '.number_format((float)($p['legal_holiday_pay'] ?? 0), 2).'</td>';
                            echo '<td>'.number_format((float)($p['holiday_ot_hours'] ?? 0), 2).'</td>';
                            echo '<td>₱ '.number_format((float)($p['holiday_ot_pay'] ?? 0), 2).'</td>';
                            echo '<td>'.number_format((float)($p['night_diff_hours'] ?? 0), 2).'</td>';
                            echo '<td>₱ '.number_format((float)($p['night_diff_pay'] ?? 0), 2).'</td>';
                            echo '<td>₱ '.number_format((float)($p['uniform_allowance'] ?? 0), 2).'</td>';
                            echo '<td>₱ '.number_format((float)($p['ctp_allowance'] ?? 0), 2).'</td>';
                            echo '<td>₱ '.number_format((float)($p['retroactive_pay'] ?? 0), 2).'</td>';
                            echo '<td class="fw-bold">₱ '.number_format((float)($p['gross_pay'] ?? 0), 2).'</td>';
                            echo '<td>₱ '.number_format((float)($p['sss'] ?? 0), 2).'</td>';
                            echo '<td>₱ '.number_format((float)($p['philhealth'] ?? 0), 2).'</td>';
                            echo '<td>₱ '.number_format((float)($p['pagibig'] ?? 0), 2).'</td>';
                            $lateUnd = isset($p['late_undertime_deduction']) ? $p['late_undertime_deduction'] : ($p['late_undertime'] ?? 0);
                            echo '<td>₱ '.number_format((float)$lateUnd, 2).'</td>';
                            echo '<td>₱ '.number_format((float)($p['cash_advance'] ?? 0), 2).'</td>';
                            echo '<td>₱ '.number_format((float)($p['cash_bond'] ?? 0), 2).'</td>';
                            echo '<td>₱ '.number_format((float)($p['other_deductions'] ?? 0), 2).'</td>';
                            echo '<td class="fw-bold">₱ '.number_format((float)($p['total_deductions'] ?? 0), 2).'</td>';
                            echo '<td class="fw-bold">₱ '.number_format((float)$net, 2).'</td>';
                            echo '</tr>';

                            // Accumulate totals
                            $tot_regular_hours += (float)($p['regular_hours'] ?? 0);
                            $tot_regular_hours_pay += (float)($p['regular_hours_pay'] ?? 0);
                            $tot_ot_hours += (float)($p['ot_hours'] ?? 0);
                            $tot_ot_pay += (float)($p['ot_pay'] ?? 0);
                            $tot_sun_rd_spcl_hours += (float)($p['special_holiday_hours'] ?? 0);
                            $tot_sun_rd_spcl_pay += (float)($p['special_holiday_pay'] ?? 0);
                            $tot_spcl_hol_ot_hours += (float)($p['special_holiday_ot_hours'] ?? 0);
                            $tot_spcl_hol_ot_pay += (float)($p['special_holiday_ot_pay'] ?? 0);
                            $tot_legal_hol_hours += (float)($p['legal_holiday_hours'] ?? 0);
                            $tot_legal_hol_pay += (float)($p['legal_holiday_pay'] ?? 0);
                            $tot_legal_hol_ot_hours += (float)($p['holiday_ot_hours'] ?? 0);
                            $tot_legal_hol_ot_pay += (float)($p['holiday_ot_pay'] ?? 0);
                            $tot_nd_hours += (float)($p['night_diff_hours'] ?? 0);
                            $tot_nd_pay += (float)($p['night_diff_pay'] ?? 0);
                            $tot_uniform_allow += (float)($p['uniform_allowance'] ?? 0);
                            $tot_ctp_allow += (float)($p['ctp_allowance'] ?? 0);
                            $tot_retro += (float)($p['retroactive_pay'] ?? 0);
                            $tot_gross += (float)($p['gross_pay'] ?? 0);
                            $tot_sss += (float)($p['sss'] ?? 0);
                            $tot_philhealth += (float)($p['philhealth'] ?? 0);
                            $tot_pagibig += (float)($p['pagibig'] ?? 0);
                            $tot_late_und += (float)$lateUnd;
                            $tot_cash_advance += (float)($p['cash_advance'] ?? 0);
                            $tot_cash_bond += (float)($p['cash_bond'] ?? 0);
                            $tot_others += (float)($p['other_deductions'] ?? 0);
                            $tot_total_deductions += (float)($p['total_deductions'] ?? 0);
                            $tot_net += (float)$net;
                        }
                        ?>
                    </tbody>
                    <?php
                    // Render totals in tfoot to keep it at the bottom and exclude from sorting
                    echo '<tfoot>';
                    echo '<tr class="table-secondary">';
                    echo '<td></td>';
                    echo '<td class="fw-bold">TOTAL</td>';
                    echo '<td>'.number_format($tot_regular_hours, 2).'</td>';
                    echo '<td>₱ '.number_format($tot_regular_hours_pay, 2).'</td>';
                    echo '<td>'.number_format($tot_ot_hours, 2).'</td>';
                    echo '<td>₱ '.number_format($tot_ot_pay, 2).'</td>';
                    echo '<td>'.number_format($tot_sun_rd_spcl_hours, 2).'</td>';
                    echo '<td>₱ '.number_format($tot_sun_rd_spcl_pay, 2).'</td>';
                    echo '<td>'.number_format($tot_spcl_hol_ot_hours, 2).'</td>';
                    echo '<td>₱ '.number_format($tot_spcl_hol_ot_pay, 2).'</td>';
                    echo '<td>'.number_format($tot_legal_hol_hours, 2).'</td>';
                    echo '<td>₱ '.number_format($tot_legal_hol_pay, 2).'</td>';
                    echo '<td>'.number_format($tot_legal_hol_ot_hours, 2).'</td>';
                    echo '<td>₱ '.number_format($tot_legal_hol_ot_pay, 2).'</td>';
                    echo '<td>'.number_format($tot_nd_hours, 2).'</td>';
                    echo '<td>₱ '.number_format($tot_nd_pay, 2).'</td>';
                    echo '<td>₱ '.number_format($tot_uniform_allow, 2).'</td>';
                    echo '<td>₱ '.number_format($tot_ctp_allow, 2).'</td>';
                    echo '<td>₱ '.number_format($tot_retro, 2).'</td>';
                    echo '<td class="fw-bold">₱ '.number_format($tot_gross, 2).'</td>';
                    echo '<td>₱ '.number_format($tot_sss, 2).'</td>';
                    echo '<td>₱ '.number_format($tot_philhealth, 2).'</td>';
                    echo '<td>₱ '.number_format($tot_pagibig, 2).'</td>';
                    echo '<td>₱ '.number_format($tot_late_und, 2).'</td>';
                    echo '<td>₱ '.number_format($tot_cash_advance, 2).'</td>';
                    echo '<td>₱ '.number_format($tot_cash_bond, 2).'</td>';
                    echo '<td>₱ '.number_format($tot_others, 2).'</td>';
                    echo '<td class="fw-bold">₱ '.number_format($tot_total_deductions, 2).'</td>';
                    echo '<td class="fw-bold">₱ '.number_format($tot_net, 2).'</td>';
                    echo '</tr>';
                    echo '</tfoot>';
                    ?>
                </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Mobile Bottom Navigation -->
    <div class="mobile-nav">
        <div class="mobile-nav-container">
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
            <a href="rate_locations.php" class="mobile-nav-item">
                <span class="material-icons">attach_money</span>
                <span class="mobile-nav-text">Rate Locations</span>
            </a>
            <a href="masterlist.php" class="mobile-nav-item">
                <span class="material-icons">list</span>
                <span class="mobile-nav-text">Masterlist</span>
            </a>
            <a href="calendar.php" class="mobile-nav-item">
                <span class="material-icons">date_range</span>
                <span class="mobile-nav-text">Calendar</span>
            </a>
            <a href="archives.php" class="mobile-nav-item">
                <span class="material-icons">archive</span>
                <span class="mobile-nav-text">Archives</span>
            </a>
            <a href="logs.php" class="mobile-nav-item">
                <span class="material-icons">receipt_long</span>
                <span class="mobile-nav-text">Logs</span>
            </a>
            <a href="employee_share.php" class="mobile-nav-item">
                <span class="material-icons">diversity_3</span>
                <span class="mobile-nav-text">Employer Contribution</span>
            </a>
            <a href="../logout.php" class="mobile-nav-item">
                <span class="material-icons">logout</span>
                <span class="mobile-nav-text">Logout</span>
            </a>
        </div>
    </div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
<script type="text/javascript" src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
<script type="text/javascript" src="https://cdn.datatables.net/fixedcolumns/4.0.2/js/dataTables.fixedColumns.min.js"></script>
<script>
$(function(){
    var table;
    if ($.fn.dataTable) {
        table = $('#registerTable').DataTable({
            paging: true,
            searching: true
        });
    }

    var empSearch = document.getElementById('empSearch');
    if (empSearch && table) {
        empSearch.addEventListener('keyup', function(){ table.search(this.value).draw(); });
        empSearch.addEventListener('change', function(){ table.search(this.value).draw(); });
    }

    function updateDateTime(){
        var now = new Date();
        var dateOptions = { year: 'numeric', month: 'long', day: 'numeric' };
        var timeOptions = { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true };
        var dateStr = now.toLocaleDateString('en-PH', dateOptions);
        var timeStr = now.toLocaleTimeString('en-PH', timeOptions);
        var dateEl = document.getElementById('current-date');
        var timeEl = document.getElementById('current-time');
        if (dateEl) dateEl.textContent = dateStr;
        if (timeEl) timeEl.textContent = timeStr;
    }
    updateDateTime();
    setInterval(updateDateTime, 1000);

    var btnSaveExcel = document.getElementById('btnSaveExcel');
    if (btnSaveExcel) {
        btnSaveExcel.addEventListener('click', function(){
            var params = new URLSearchParams({
                month: '<?= htmlspecialchars($month) ?>',
                dateRange: '<?= htmlspecialchars($dateRange) ?>',
                location: '<?= htmlspecialchars($selectedLocation) ?>'
            }).toString();
            window.open('export_payroll_register_excel.php?' + params, '_blank');
        });
    }
});
</script>
<script src="js/accounting_dashboard.js"></script>
</body>
</html>
